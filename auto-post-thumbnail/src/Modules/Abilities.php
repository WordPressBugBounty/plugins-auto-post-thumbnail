<?php
/**
 * Registers the plugin abilities with the WordPress Abilities API.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Modules;

use AutoPostThumbnail\Modules\Base;
use AutoPostThumbnail\Services\Apt;
use WP_Error;

/**
 * Abilities module.
 *
 * @package AutoPostThumbnail
 */
class Abilities extends Base {
	const CATEGORY = 'featured-image';

	const ABILITY_GENERATE = 'featured-image/generate';

	/**
	 * Maximum number of posts accepted in a single batch.
	 */
	const MAX_BATCH_SIZE = 500;

	/**
	 * Default and maximum time budget of a single call, in seconds.
	 */
	const DEFAULT_TIME_BUDGET = 20;
	const MAX_TIME_BUDGET     = 60;

	/**
	 * Generation methods implemented by Apt::publish_post().
	 *
	 * @var string[]
	 */
	const METHODS = [ 'find', 'generate', 'both', 'use_default' ];

	/**
	 * Run hooks for the abilities module.
	 *
	 * @inheritDoc Base::run_hooks()
	 */
	public function run_hooks() {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register the ability category.
	 *
	 * @return void
	 */
	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'Featured images', 'auto-post-thumbnail' ),
				'description' => __( 'Find or generate featured images with Auto Featured Image.', 'auto-post-thumbnail' ),
			]
		);
	}

	/**
	 * Register the abilities.
	 *
	 * @return void
	 */
	public function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		wp_register_ability(
			self::ABILITY_GENERATE,
			[
				'label'               => __( 'Generate featured images', 'auto-post-thumbnail' ),
				'description'         => __( 'Find or generate a featured image for each of the given posts and set it as the post thumbnail. Methods: "find" uses the first image in the post content (remote images are downloaded to the media library), "generate" renders the post title on the configured background, "both" finds and falls back to generating, "use_default" finds and falls back to the default image from the plugin settings. Posts that already have a featured image are skipped unless overwrite is true. Posts are processed until the list is finished or the time budget is used up; when done is false, call again with the same input and the returned cursor to continue. Results contain only the posts handled in this call.', 'auto-post-thumbnail' ),
				'category'            => self::CATEGORY,
				'input_schema'        => [
					'type'                 => 'object',
					'properties'           => [
						'post_ids'    => [
							'type'        => 'array',
							'description' => __( 'IDs of the posts to process.', 'auto-post-thumbnail' ),
							'items'       => [
								'type'    => 'integer',
								'minimum' => 1,
							],
							'minItems'    => 1,
							'maxItems'    => self::MAX_BATCH_SIZE,
						],
						'method'      => [
							'type'        => 'string',
							'description' => __( 'Generation method.', 'auto-post-thumbnail' ),
							'enum'        => self::METHODS,
						],
						'overwrite'   => [
							'type'        => 'boolean',
							'description' => __( 'Replace the featured image of posts that already have one.', 'auto-post-thumbnail' ),
							'default'     => false,
						],
						'cursor'      => [
							'type'        => 'string',
							'description' => __( 'Cursor returned by a previous call with the same input, to continue where it stopped.', 'auto-post-thumbnail' ),
						],
						'time_budget' => [
							'type'        => 'integer',
							'description' => __( 'Maximum number of seconds to spend in this call.', 'auto-post-thumbnail' ),
							'minimum'     => 1,
							'maximum'     => self::MAX_TIME_BUDGET,
							'default'     => self::DEFAULT_TIME_BUDGET,
						],
					],
					'required'             => [ 'post_ids', 'method' ],
					'additionalProperties' => false,
				],
				'output_schema'       => [
					'type'       => 'object',
					'properties' => [
						'method'    => [ 'type' => 'string' ],
						'total'     => [ 'type' => 'integer' ],
						'generated' => [ 'type' => 'integer' ],
						'skipped'   => [ 'type' => 'integer' ],
						'failed'    => [ 'type' => 'integer' ],
						'done'      => [ 'type' => 'boolean' ],
						'cursor'    => [ 'type' => 'string' ],
						'progress'  => [
							'type'       => 'object',
							'properties' => [
								'current' => [ 'type' => 'integer' ],
								'total'   => [ 'type' => 'integer' ],
								'message' => [ 'type' => 'string' ],
							],
						],
						'results'   => [
							'type'  => 'array',
							'items' => [
								'type'       => 'object',
								'properties' => [
									'post_id'       => [ 'type' => 'integer' ],
									'method'        => [ 'type' => 'string' ],
									'status'        => [
										'type' => 'string',
										'enum' => [ 'generated', 'skipped', 'failed' ],
									],
									'attachment_id' => [ 'type' => 'integer' ],
									'thumbnail_url' => [ 'type' => 'string' ],
									'error_code'    => [ 'type' => 'string' ],
									'error'         => [ 'type' => 'string' ],
								],
							],
						],
					],
				],
				'execute_callback'    => [ $this, 'generate' ],
				'permission_callback' => [ $this, 'can_generate' ],
				'meta'                => [
					'annotations'  => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					],
					'show_in_rest' => true,
					'task'         => [
						'mode'        => 'cursor',
						'results_key' => 'results',
					],
				],
			]
		);
	}

	/**
	 * Permission check, same capability as the plugin generate REST routes.
	 *
	 * @return bool
	 */
	public function can_generate() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Execute the generate ability.
	 *
	 * @param array $input Ability input.
	 *
	 * @return array|WP_Error
	 */
	public function generate( $input ) {
		$input = is_array( $input ) ? $input : [];

		$method = isset( $input['method'] ) && is_string( $input['method'] ) ? sanitize_key( $input['method'] ) : '';
		if ( ! in_array( $method, self::METHODS, true ) ) {
			return new WP_Error(
				'wapt_invalid_method',
				sprintf(
					/* translators: %s: list of supported generation methods */
					__( 'Invalid generation method. Supported methods: %s.', 'auto-post-thumbnail' ),
					implode( ', ', self::METHODS )
				)
			);
		}

		$post_ids = isset( $input['post_ids'] ) && is_array( $input['post_ids'] ) ? $input['post_ids'] : [];
		$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );

		if ( empty( $post_ids ) ) {
			return new WP_Error( 'wapt_missing_post_ids', __( 'Provide at least one valid post ID.', 'auto-post-thumbnail' ) );
		}

		if ( count( $post_ids ) > self::MAX_BATCH_SIZE ) {
			return new WP_Error(
				'wapt_batch_too_large',
				sprintf(
					/* translators: %d: maximum number of posts per call */
					__( 'Too many posts. A single call can process at most %d posts.', 'auto-post-thumbnail' ),
					self::MAX_BATCH_SIZE
				)
			);
		}

		$overwrite = isset( $input['overwrite'] ) && rest_sanitize_boolean( $input['overwrite'] );
		$total     = count( $post_ids );

		$offset = 0;
		if ( isset( $input['cursor'] ) && '' !== $input['cursor'] ) {
			$offset = $this->decode_cursor( $input['cursor'], $total );

			if ( is_wp_error( $offset ) ) {
				return $offset;
			}
		}

		$time_budget = isset( $input['time_budget'] ) ? (int) $input['time_budget'] : self::DEFAULT_TIME_BUDGET;
		$time_budget = max( 1, min( self::MAX_TIME_BUDGET, $time_budget ) );
		$deadline    = microtime( true ) + $time_budget;

		$results = [];
		$counts  = [
			'generated' => 0,
			'skipped'   => 0,
			'failed'    => 0,
		];

		while ( $offset < $total ) {
			$result = $this->generate_for_post( $post_ids[ $offset ], $method, $overwrite );

			++$counts[ $result['status'] ];
			$results[] = $result;
			++$offset;

			if ( microtime( true ) >= $deadline ) {
				break;
			}
		}

		$done = $offset >= $total;

		return [
			'method'    => $method,
			'total'     => count( $results ),
			'generated' => $counts['generated'],
			'skipped'   => $counts['skipped'],
			'failed'    => $counts['failed'],
			'done'      => $done,
			'cursor'    => $done ? '' : $this->encode_cursor( $offset, $total ),
			'progress'  => [
				'current' => $offset,
				'total'   => $total,
				'message' => sprintf(
					/* translators: 1: number of processed posts, 2: total number of posts */
					__( 'Processed %1$d of %2$d posts.', 'auto-post-thumbnail' ),
					$offset,
					$total
				),
			],
			'results'   => $results,
		];
	}

	/**
	 * Build the cursor for the next call.
	 *
	 * @param int $offset Number of posts already processed.
	 * @param int $total Number of posts in the batch.
	 *
	 * @return string
	 */
	private function encode_cursor( $offset, $total ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Opaque cursor, not obfuscation.
		return base64_encode(
			(string) wp_json_encode(
				[
					'offset' => $offset,
					'total'  => $total,
				]
			)
		);
	}

	/**
	 * Read the offset from a cursor.
	 *
	 * @param mixed $cursor Cursor from the ability input.
	 * @param int   $total Number of posts in the batch.
	 *
	 * @return int|WP_Error
	 */
	private function decode_cursor( $cursor, $total ) {
		$error = new WP_Error( 'wapt_invalid_cursor', __( 'Invalid cursor. Use the cursor returned by the previous call with the same input.', 'auto-post-thumbnail' ) );

		if ( ! is_string( $cursor ) ) {
			return $error;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Opaque cursor, not obfuscation.
		$json = base64_decode( $cursor, true );
		$data = false !== $json ? json_decode( $json, true ) : null;

		if ( ! is_array( $data ) || ! isset( $data['offset'], $data['total'] ) || ! is_int( $data['offset'] ) || ! is_int( $data['total'] ) ) {
			return $error;
		}

		if ( $data['total'] !== $total || $data['offset'] < 1 || $data['offset'] >= $total ) {
			return $error;
		}

		return $data['offset'];
	}

	/**
	 * Process a single post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $method Generation method.
	 * @param bool   $overwrite Whether to replace an existing featured image.
	 *
	 * @return array
	 */
	private function generate_for_post( $post_id, $method, $overwrite ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return $this->item_error( $post_id, $method, 'wapt_post_not_found', __( 'The post was not found', 'auto-post-thumbnail' ) );
		}

		if ( 'auto-draft' === $post->post_status || ! post_type_supports( $post->post_type, 'thumbnail' ) ) {
			return $this->item_error( $post_id, $method, 'wapt_post_not_supported', __( 'This post does not support featured images.', 'auto-post-thumbnail' ) );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->item_error( $post_id, $method, 'wapt_forbidden', __( 'You are not allowed to edit this post.', 'auto-post-thumbnail' ) );
		}

		if ( ! $overwrite ) {
			$current = (int) get_post_thumbnail_id( $post_id );

			if ( ( $current && 'attachment' === get_post_type( $current ) ) || get_post_meta( $post_id, 'skip_post_thumb', true ) ) {
				$item = [
					'post_id' => $post_id,
					'method'  => $method,
					'status'  => 'skipped',
					'error'   => __( 'The post has already been assigned a featured image', 'auto-post-thumbnail' ),
				];

				if ( $current ) {
					$item['attachment_id'] = $current;
				}

				return $item;
			}
		}

		try {
			$data = Apt::instance()->process_post( $post_id, $method, $overwrite );
		} catch ( \Exception $e ) {
			return $this->item_error( $post_id, $method, 'wapt_generation_failed', $e->getMessage() );
		}

		if ( empty( $data['thumbnail_id'] ) ) {
			$message = ! empty( $data['error_msg'] ) ? $data['error_msg'] : __( 'No image found or generated', 'auto-post-thumbnail' );

			return $this->item_error( $post_id, $method, 'wapt_generation_failed', $message );
		}

		$item = [
			'post_id'       => $post_id,
			'method'        => $method,
			'status'        => 'generated',
			'attachment_id' => (int) $data['thumbnail_id'],
		];

		if ( ! empty( $data['thumbnail_url'] ) ) {
			$item['thumbnail_url'] = $data['thumbnail_url'];
		}

		return $item;
	}

	/**
	 * Build a failed per-post result.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $method Generation method.
	 * @param string $code Stable error code.
	 * @param string $message Error message.
	 *
	 * @return array
	 */
	private function item_error( $post_id, $method, $code, $message ) {
		return [
			'post_id'    => $post_id,
			'method'     => $method,
			'status'     => 'failed',
			'error_code' => $code,
			'error'      => $message,
		];
	}
}
