<?php
/**
 * Base module.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Modules;

use AutoPostThumbnail\Constants\Options_Schema;
use AutoPostThumbnail\Repository\Posts;
use AutoPostThumbnail\Settings;

/**
 * Abstract base module class.
 *
 * @package AutoPostThumbnail
 */
abstract class Base {
	/**
	 * Run hooks for the module.
	 *
	 * @return void
	 */
	abstract public function run_hooks();

	/**
	 * Get all post types
	 *
	 * @return array <string, string>
	 */
	final protected function get_post_types() {
		$post_types = get_post_types( [ 'public' => true ], 'objects' );

		unset( $post_types['attachment'] );

		foreach ( $post_types as $key => $post_type ) {
			if ( ! post_type_supports( $key, 'thumbnail' ) ) {
				unset( $post_types[ $key ] );
			}
		}

		return array_map(
			function ( $post_type ) {
				return $post_type->label;
			},
			$post_types
		);
	}

	/**
	 * Get all post statuses.
	 *
	 * @return array
	 */
	final protected function get_post_statuses() {
		$stati = get_post_stati(
			[
				'_builtin'                  => true,
				'show_in_admin_status_list' => true,
			],
			'objects'
		);

		$stati = array_map(
			function ( $status ) {
				return $status->label;
			},
			$stati
		);

		$stati = array_merge( [ 'all' => __( 'All Statuses', 'auto-post-thumbnail' ) ], $stati );

		return $stati;
	}

	/**
	 * Get all categories.
	 *
	 * @return array
	 */
	final protected function get_categories() {
		$categories = get_categories(
			[
				'taxonomy' => 'category',
				'type'     => 'post',
				'orderby'  => 'name',
				'order'    => 'ASC',
			]
		);

		$all_categories = [];

		foreach ( $categories as $category ) {
			$all_categories[] = [
				'id'    => $category->term_id,
				'label' => html_entity_decode( $category->name ),
			];
		}

		array_unshift(
			$all_categories,
			[
				'id'    => 0,
				'label' => __( 'All Categories', 'auto-post-thumbnail' ),
			]
		);

		return $all_categories;
	}

	/**
	 * Get the images status overview.
	 *
	 * @return array
	 */
	final protected function get_images_status() {
		$posts       = new Posts();
		$no_featured = $posts->get_posts_count();
		$w_featured  = $posts->get_posts_count( true );
		$percent     = ( 0 === $no_featured + $w_featured ) ? 0 : ceil( $w_featured / ( $no_featured + $w_featured ) * 100 );

		return [
			'noThumb'   => $no_featured,
			'withThumb' => $w_featured,
			'percent'   => $percent,
		];
	}
}
