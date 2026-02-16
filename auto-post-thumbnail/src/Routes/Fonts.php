<?php
/**
 * Fonts routes handler.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Routes;

use WP_REST_Response;
use WP_REST_Server;

/**
 * Fonts routes handler.
 *
 * @package AutoPostThumbnail
 */
class Fonts extends Base_Route {
	/**
	 * Get the namespace for the route.
	 *
	 * @return string
	 */
	public function get_namespace() {
		return 'fonts';
	}

	/**
	 * Get routes.
	 *
	 * @inheritDoc Base_Route::get_routes()
	 */
	public function get_routes() {
		return [
			[
				'route'               => '',
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_fonts' ],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
			],
		];
	}

	/**
	 * Get list of available fonts.
	 *
	 * @return WP_REST_Response
	 */
	public function get_fonts() {
		$upload_dir       = wp_upload_dir();
		$upload_dir_fonts = $upload_dir['basedir'] . '/apt_fonts';
		$plugin_dir_fonts = WAPT_PLUGIN_DIR . '/fonts';
		$fonts            = [];

		$static_map = [
			'segoeui' => 'Segoe UI',
			'arial'   => 'Arial',
			'tahoma'  => 'Tahoma',
		];

		// Get default fonts from plugin directory.
		if ( is_dir( $plugin_dir_fonts ) ) {
			$files = scandir( $plugin_dir_fonts );
			foreach ( $files as $file ) {
				if ( '.' === $file || '..' === $file ) {
					continue;
				}
				$pathinfo = pathinfo( $plugin_dir_fonts . '/' . $file );
				if ( ! isset( $pathinfo['extension'] ) || 'ttf' !== strtolower( $pathinfo['extension'] ) ) {
					continue;
				}

				$fonts[] = [
					'name'  => $file,
					'label' => isset( $static_map[ $pathinfo['filename'] ] ) ? $static_map[ $pathinfo['filename'] ] : $pathinfo['filename'],
					'group' => 'default',
				];
			}
		}

		// Get uploaded fonts.
		if ( is_dir( $upload_dir_fonts ) ) {
			$files = scandir( $upload_dir_fonts );
			foreach ( $files as $file ) {
				if ( '.' === $file || '..' === $file ) {
					continue;
				}
				$pathinfo = pathinfo( $upload_dir_fonts . '/' . $file );
				if ( ! isset( $pathinfo['extension'] ) || 'ttf' !== strtolower( $pathinfo['extension'] ) ) {
					continue;
				}
				$fonts[] = [
					'name'  => $file,
					'label' => $pathinfo['filename'],
					'group' => 'uploaded',
				];
			}
		}

		return $this->success_response( [ 'fonts' => $fonts ] );
	}
}
