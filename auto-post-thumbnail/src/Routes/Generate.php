<?php
/**
 * Generate routes handler.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Routes;

use AutoPostThumbnail\Repository\Posts;
use AutoPostThumbnail\Services\Apt;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Generate routes handler.
 *
 * @package AutoPostThumbnail
 */
class Generate extends Base_Route {
	/**
	 * Get the namespace for the route.
	 *
	 * @return string
	 */
	public function get_namespace() {
		return 'generate';
	}
	/**
	 * Get routes.
	 *
	 * @inheritDoc Base_Route::get_routes()
	 */
	public function get_routes() {
		return [
			[
				'route'               => 'get-post-ids',
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'get_posts_ids' ],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
			],
			[
				'route'               => 'create',
				'methods'             => WP_REST_Server::CREATABLE,
				'args'                => [
					'id'     => [
						'type'     => 'integer',
						'required' => true,
					],
					'method' => [
						'type'     => 'string',
						'required' => true,
						'enum'     => [
							'find',
							'generate',
							'both',
							'google',
							'find_google',
							'use_default',
							'ai_generate',
						],
					],
					'forced' => [
						'type'     => 'boolean',
						'required' => false,
					],
				],
				'callback'            => [ $this, 'generate' ],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
			],
			[
				'route'               => 'delete',
				'methods'             => WP_REST_Server::CREATABLE,
				'args'                => [
					'id'               => [
						'type'     => 'integer',
						'required' => true,
					],
					'mode'             => [
						'type'     => 'string',
						'required' => false,
						'default'  => 'all',
						'enum'     => [ 'all', 'plugin_only' ],
					],
					'deleteAttachment' => [
						'type'     => 'boolean',
						'required' => false,
						'default'  => false,
					],
				],
				'callback'            => [ $this, 'delete_thumbnail' ],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
			],
			[
				'route'               => 'replace',
				'methods'             => WP_REST_Server::CREATABLE,
				'args'                => [
					'post_id'      => [
						'type'     => 'integer',
						'required' => true,
					],
					'thumbnail_id' => [
						'type'     => 'integer',
						'required' => true,
					],
				],
				'callback'            => [ $this, 'replace_thumbnail' ],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
			],
		];
	}

	/**
	 * Generate thumbnails.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response|void
	 */
	public function generate( WP_REST_Request $request ) {
		$data = $request->get_body();

		try {
			$data = json_decode( $data, true );

			$forced = isset( $data['forced'] ) ? boolval( $data['forced'] ) : false;

			$result = Apt::instance()->process_post( $data['id'], $data['method'], $forced );

			return $this->success_response( $result );
		} catch ( \Exception $e ) {
			return $this->error_response( $e->getMessage() );
		}
	}

	/**
	 * Get posts id's.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_posts_ids( WP_REST_Request $request ) {
		$data = $request->get_body();

		try {
			$data = json_decode( $data, true );

			if ( ! is_array( $data ) || empty( $data ) ) {
				return $this->error_response( __( 'Invalid request data', 'auto-post-thumbnail' ) );
			}

			$posts_repository = new Posts();
			$ids              = $posts_repository->get_posts_ids( $data );

			return $this->success_response( $ids );
		} catch ( \Exception $e ) {
			return $this->error_response( $e->getMessage() );
		}
	}

	/**
	 * Delete thumbnail from a post.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function delete_thumbnail( WP_REST_Request $request ) {
		$data = $request->get_body();
		try {
			$data              = json_decode( $data, true );
			$post_id           = $data['id'];
			$mode              = isset( $data['mode'] ) ? $data['mode'] : 'all';
			$delete_attachment = ! empty( $data['deleteAttachment'] );

			if ( 'plugin_only' === $mode ) {
				$apt_set = get_post_meta( $post_id, Apt::PLUGIN_SET_META_KEY, true );
				if ( ! $apt_set ) {
					return $this->success_response(
						[
							'id'      => $post_id,
							'deleted' => false,
							'skipped' => true,
						]
					);
				}
			}

			$thumbnail_id = get_post_thumbnail_id( $post_id );

			$result = delete_post_thumbnail( $post_id );
			delete_post_meta( $post_id, Apt::PLUGIN_SET_META_KEY );

			if ( $delete_attachment && $thumbnail_id ) {
				$this->maybe_delete_attachment( $thumbnail_id, $post_id );
			}

			return $this->success_response(
				[
					'id'      => $post_id,
					'deleted' => $result,
				]
			);
		} catch ( \Exception $e ) {
			return $this->error_response( $e->getMessage() );
		}
	}

	/**
	 * Safely delete an attachment only if it was created by this plugin
	 * and is not used as a featured image by any other post.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $excluded_post_id The post ID to exclude from the usage check.
	 *
	 * @return void
	 */
	private function maybe_delete_attachment( $attachment_id, $excluded_post_id ) {
		// Only delete attachments created by this plugin.
		$apt_created = get_post_meta( $attachment_id, Apt::PLUGIN_CREATED_ATTACHMENT, true );
		if ( ! $apt_created ) {
			return;
		}

		// Check if any other post uses this attachment as its featured image.
		global $wpdb;
		$other_usage = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s AND post_id != %d",
				$attachment_id,
				$excluded_post_id
			)
		);

		if ( $other_usage > 0 ) {
			return;
		}

		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Replace thumbnail.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function replace_thumbnail( WP_REST_Request $request ) {
		$data = $request->get_body();
		$data = json_decode( $data, true );

		if ( ! is_array( $data ) || ! isset( $data['post_id'] ) || ! isset( $data['thumbnail_id'] ) ) {
			return $this->error_response( __( 'Invalid data', 'auto-post-thumbnail' ) );
		}

		$result = Apt::instance()->replace_thumbnail( $data['post_id'], $data['thumbnail_id'] );

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result->get_error_message() );
		}

		return $this->success_response(
			[
				'message'       => __( 'Thumbnail replaced successfully', 'auto-post-thumbnail' ),
				'thumbnail_url' => $result,
				'thumbnail_id'  => $data['thumbnail_id'],
			]
		);
	}
}
