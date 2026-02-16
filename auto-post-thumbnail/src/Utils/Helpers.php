<?php
/**
 * Helper utilities.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Utils;

/**
 * Helper utility methods.
 */
class Helpers {
	/**
	 * Resolve font path from font name.
	 *
	 * @param string $font_name Font name.
	 *
	 * @return string Font path
	 */
	public static function resolve_font_path( $font_name ) {
		$font = WAPT_PLUGIN_DIR . '/fonts/' . $font_name;

		if ( file_exists( $font ) ) {
			return $font;
		}

		$upload_dir       = wp_upload_dir();
		$upload_font_path = $upload_dir['basedir'] . '/apt_fonts/' . $font_name;

		if ( file_exists( $upload_font_path ) ) {
			return $upload_font_path;
		}

		return WAPT_PLUGIN_DIR . '/fonts/arial.ttf';
	}

	/**
	 * Get the upsell URL.
	 *
	 * @param string $utm_campaign The UTM campaign.
	 * @return string
	 */
	public static function get_upsell_url( $utm_campaign ) {
		return tsdk_translate_link( tsdk_utmify( 'https://themeisle.com/plugins/auto-featured-image/upgrade', $utm_campaign, 'apt' ) );
	}

	/**
	 * Get a random post title.
	 *
	 * @return string
	 */
	public static function get_random_post_title() {
		$posts = get_posts( [ 'numberposts' => 10 ] );
		if ( ! empty( $posts ) ) {
			$post = $posts[ wp_rand( 0, count( $posts ) - 1 ) ];
			$txt  = $post->post_title;
		} else {
			$txt = __( 'Sample Post Title', 'auto-post-thumbnail' );
		}

		return $txt;
	}
}
