<?php
/**
 * Auto generate module.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Modules;

use AutoPostThumbnail\Constants\Options_Schema;
use AutoPostThumbnail\Logger;
use AutoPostThumbnail\Modules\Base;
use AutoPostThumbnail\Services\Apt;
use AutoPostThumbnail\Settings;
use WP_Post;
use WP_REST_Request;

/**
 * Auto generate module.
 *
 * @package AutoPostThumbnail
 */
class Auto_Generate extends Base {
	/**
	 * Allowed post types for auto generation.
	 *
	 * @var array
	 */
	private $allowed_post_types = [];

	/**
	 * Constructor for the auto generate module.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->allowed_post_types = explode( ',', Settings::instance()->get( Options_Schema::AUTO_POST_TYPES ) );
	}

	/**
	 * Run hooks for the auto generate module.
	 *
	 * @return void
	 */
	public function run_hooks() {
		if ( ! $this->should_auto_generate() ) {
			return;
		}

		add_action( 'save_post', [ $this, 'save_post' ], 10, 3 );
		add_action( 'transition_post_status', [ $this, 'check_required_transition' ], 10, 3 );

		foreach ( $this->allowed_post_types as $post_type ) {
			add_action( "rest_after_insert_{$post_type}", [ $this, 'rest_after_insert' ], 10, 3 );
		}
	}

	/**
	 * On post save, auto generate thumbnail.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Whether this is an update.
	 *
	 * @return void
	 */
	public function save_post( $post_id, $post = null, $update = true ) {
		if ( 'revision' === $post->post_type ) {
			return;
		}

		if ( ! in_array( $post->post_type, $this->allowed_post_types, true ) ) {
			Logger::instance()->warning( "The post type ({$post->post_type}) is not allowed for generation in settings" );

			return;
		}

		Apt::instance()->publish_post( $post_id, $post, $update );
	}


	/**
	 * On post insert, auto generate thumbnail.
	 *
	 * @param WP_Post         $post Post object.
	 * @param WP_REST_Request $request Request object.
	 * @param bool            $is_insert Whether this is an insert.
	 *
	 * @return void
	 */
	public function rest_after_insert( $post, $request, $is_insert ) {
		if ( ! in_array( $post->post_type, $this->allowed_post_types, true ) ) {
			Logger::instance()->warning( "The post type ({$post->post_type}) is not allowed for generation in settings" );

			return;
		}

		Apt::instance()->publish_post( $post->ID, $post, ! $is_insert );
	}


	/**
	 * Function to check whether scheduled post is being published. If so, apt_publish_post should be called.
	 *
	 * @param string       $new_status New status.
	 * @param string       $old_status Old status.
	 * @param WP_Post|null $post Instance of post.
	 *
	 * @return void
	 */
	public function check_required_transition( $new_status = '', $old_status = '', $post = null ) {
		if ( 'publish' !== $new_status ) {
			return;
		}

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		Apt::instance()->publish_post( $post->ID, $post, false );
	}

	/**
	 * Check if auto generation is enabled.
	 *
	 * @return bool
	 */
	public function should_auto_generate() {
		return Settings::instance()->get( Options_Schema::AUTO_GENERATION ) === true;
	}
}
