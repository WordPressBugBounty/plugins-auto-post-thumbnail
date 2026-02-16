<?php
/**
 * Posts repository.
 *
 * @package AutoPostThumbnail\Repository
 */

namespace AutoPostThumbnail\Repository;

use AutoPostThumbnail\Logger;
use AutoPostThumbnail\Services\Apt;
use WP_Query;

/**
 * Posts repository class.
 */
class Posts {

	/**
	 * Return sql query, which allows to receive all the posts without thumbnails.
	 *
	 * @param array $params Query parameters.
	 *
	 * @return \WP_Query
	 */
	public function get_posts_query( $params ) {
		$default_params = [
			'has_thumb'    => false,
			'type'         => 'post',
			'status'       => 'publish',
			'category'     => 0,
			'date_start'   => 0,
			'date_end'     => 0,
			'is_log'       => true,
			'count'        => 0,
			'apt_set_only' => false,
		];

		$params = wp_parse_args( $params, $default_params );
		// phpcs:disable WordPress.PHP.DevelopmentFunctions
		if ( $params['is_log'] ) {
			Logger::instance()->info(
				'Posts query: ' . var_export(
					[
						'has_thumb'  => $params['has_thumb'],
						'post_type'  => $params['type'],
						'status'     => $params['status'],
						'category'   => $params['category'],
						'date_start' => $params['date_start'],
						'date_end'   => $params['date_end'],
					],
					true
				)
			);
		}
		// phpcs:enable

		$q_status    = $params['status'] ? $params['status'] : 'any';
		$q_type      = $params['type'] ? $params['type'] : 'any';
		$q_has_thumb = $params['has_thumb'] ? 'EXISTS' : 'NOT EXISTS';

		$args = [
			'posts_per_page'   => $params['count'] ? $params['count'] : - 1,
			'post_status'      => $q_status,
			'post_type'        => $q_type,
			'suppress_filters' => true,
			'fields'           => 'ids',
			'meta_query'       => [
				'relation' => ' and ',
				[
					'key'     => '_thumbnail_id',
					'compare' => $q_has_thumb,
				],
				[
					'key'     => 'skip_post_thumb',
					'compare' => 'NOT EXISTS',
				],
			],
		];
		if ( $params['apt_set_only'] ) {
			$args['meta_query'][] = [
				'key'     => Apt::PLUGIN_SET_META_KEY,
				'compare' => 'EXISTS',
			];
		}

		if ( $params['category'] ) {
			$args['cat'] = $params['category'];
		}

		if ( $params['date_start'] || $params['date_end'] ) {
			$date_query = [
				'inclusive' => true,
			];

			if ( $params['date_start'] ) {
				$date_query['after'] = $params['date_start'];
			}

			if ( $params['date_end'] ) {
				$date_query['before'] = $params['date_end'];
			}

			$args['date_query'][] = $date_query;
		}

		$query = new WP_Query( $args );

		return $query;
	}

	/**
	 * Return count of the posts.
	 *
	 * @param bool   $has_thumb Whether to count posts with thumbnails.
	 * @param string $type      Post type.
	 *
	 * @return int
	 */
	public function get_posts_count( $has_thumb = false, $type = 'post' ) {
		$query = $this->get_posts_query(
			[
				'is_log'    => false,
				'has_thumb' => $has_thumb,
				'type'      => $type,
			]
		);

		return $query->post_count;
	}


	/**
	 * Get posts ids by params.
	 *
	 * @param array $params Params.
	 *   - withThumb: bool
	 *   - postType: string
	 *   - postStatus: string
	 *   - category: int
	 *   - dateStart: string
	 *   - dateEnd: string.
	 *
	 * @return array
	 */
	public function get_posts_ids( $params ) {
		$has_thumb    = sanitize_text_field( wp_unslash( $params['withThumb'] ?? false ) );
		$type         = sanitize_text_field( wp_unslash( $params['postType'] ?? 'post' ) );
		$status       = isset( $params['postStatus'] ) ? sanitize_text_field( wp_unslash( $params['postStatus'] ) ) : 'any';
		$category     = sanitize_text_field( wp_unslash( $params['category'] ?? 0 ) );
		$date_start   = sanitize_text_field( wp_unslash( $params['dateStart'] ?? 0 ) );
		$date_end     = sanitize_text_field( wp_unslash( $params['dateEnd'] ?? 0 ) );
		$apt_set_only = ! empty( $params['aptSetOnly'] );
		$date_start   = $date_start ? \DateTime::createFromFormat( 'Y-m-d', $date_start )->format( 'd.m.Y' ) : 0;
		$date_end     = $date_end ? \DateTime::createFromFormat( 'Y-m-d', $date_end )->setTime( 23, 59 )->format( 'd.m.Y H:i' ) : 0;

		$params = [
			'has_thumb'    => $has_thumb,
			'type'         => $type,
			'status'       => $status,
			'apt_set_only' => $apt_set_only,
		];

		if ( $category ) {
			$params['category'] = $category;
		}

		if ( $date_start ) {
			$params['date_start'] = $date_start;
		}

		if ( $date_end ) {
			$params['date_end'] = $date_end;
		}

		$query = $this->get_posts_query( $params );

		$ids = [];
		if ( ! empty( $query->posts ) ) {
			foreach ( $query->posts as $post ) {
				$ids[] = (string) $post;
			}
		}

		$ids_str = implode( ',', $ids );

		Logger::instance()->info( "Queried posts IDs:  {$ids_str}" );

		return $ids;
	}
}
