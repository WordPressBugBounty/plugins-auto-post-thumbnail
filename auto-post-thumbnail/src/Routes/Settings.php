<?php
/**
 * Settings routes handler.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Routes;

use AutoPostThumbnail\Loader;
use AutoPostThumbnail\Settings as SettingsHandler;
use AutoPostThumbnail\Services\Image_Generator;
use AutoPostThumbnail\Utils\Helpers;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Settings routes handler.
 *
 * @package AutoPostThumbnail
 */
class Settings extends Base_Route {
	/**
	 * Get the namespace for the route.
	 *
	 * @return string
	 */
	public function get_namespace() {
		return 'settings';
	}
	/**
	 * Get routes.
	 *
	 * @inheritDoc Base_Route::get_routes()
	 */
	public function get_routes() {
		return [
			[
				'route'               => 'update',
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
				'args'                => [
					'settings' => [
						'type'              => 'object',
						'required'          => true,
						'validate_callback' => function ( $value ) {
							return is_array( $value );
						},
					],
				],
			],
			[
				'route'               => 'preview',
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'generate_preview' ],
				'args'                => [
					'settings' => [
						'type'              => 'object',
						'required'          => true,
						'validate_callback' => function ( $value ) {
							return is_array( $value );
						},
					],
				],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
			],
			[
				'route'               => 'tracking',
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_tracking_preference' ],
				'permission_callback' => [ $this, 'manage_options_permissions_check' ],
				'args'                => [
					'enabled' => [
						'type'     => 'boolean',
						'required' => true,
					],
				],
			],
		];
	}

	/**
	 * Update settings.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response|void
	 */
	public function update_settings( WP_REST_Request $request ) {
		$data = $request->get_body();

		$data = json_decode( $data, true );

		if ( ! is_array( $data ) || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return $this->error_response( __( 'Invalid settings', 'auto-post-thumbnail' ) );
		}

		$updated_settings = SettingsHandler::instance()->update_options( $data['settings'] );

		do_action( 'wapt_settings_updated', $data['settings'] );

		return $this->success_response(
			[
				'message'  => __( 'Settings updated', 'auto-post-thumbnail' ),
				'settings' => $updated_settings,
			]
		);
	}

	/**
	 * Generate preview image.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function generate_preview( WP_REST_Request $request ) {
		$data = $request->get_body();
		$data = json_decode( $data, true );

		if ( ! is_array( $data ) || ! isset( $data['settings'] ) ) {
			return $this->error_response( __( 'Invalid settings', 'auto-post-thumbnail' ) );
		}

		$sample_text = Helpers::get_random_post_title();
		$settings    = $data['settings'];

		try {
			// Sanitize settings
			foreach ( $settings as $key => $value ) {
				$settings[ $key ] = SettingsHandler::instance()->sanitize_value( $key, $value );
			}

			// Prepare preview settings for Image_Generator
			$preview_settings = $settings;
			$font_path        = Helpers::resolve_font_path( $preview_settings['font'] ?? '' );
			$font_path        = apply_filters( 'wapt/preview_font_path', $font_path, $preview_settings );

			// Add filter to override defaults with preview settings
			$filter_callback = function ( $defaults ) use ( $preview_settings, $font_path ) {
				// Map preview settings to Image_Generator defaults
				$defaults['font']           = $font_path;
				$defaults['font_size']      = (int) $preview_settings['font-size'];
				$defaults['font_color']     = $preview_settings['font-color'];
				$defaults['width']          = (int) $preview_settings['image-width'];
				$defaults['height']         = (int) $preview_settings['image-height'];
				$defaults['text_crop']      = (int) $preview_settings['text-crop'];
				$defaults['text_transform'] = $preview_settings['text-transform'];
				$defaults['background']     = $preview_settings['background-color'];
				$defaults['shadow']         = (bool) $preview_settings['shadow'];
				$defaults['shadow_color']   = $preview_settings['shadow-color'];
				$defaults['line_spacing']   = (float) $preview_settings['text-line-spacing'];

				// Pro settings (may not exist in free version)
				if ( isset( $preview_settings['before-text'] ) ) {
					$defaults['before_text'] = $preview_settings['before-text'];
				}
				if ( isset( $preview_settings['after-text'] ) ) {
					$defaults['after_text'] = $preview_settings['after-text'];
				}
				if ( isset( $preview_settings['text-align-horizontal'] ) ) {
					$defaults['align'] = $preview_settings['text-align-horizontal'];
				}
				if ( isset( $preview_settings['text-align-vertical'] ) ) {
					$defaults['valign'] = $preview_settings['text-align-vertical'];
				}
				if ( isset( $preview_settings['text-padding-tb'] ) ) {
					$defaults['padding_tb'] = (int) $preview_settings['text-padding-tb'];
				}
				if ( isset( $preview_settings['text-padding-lr'] ) ) {
					$defaults['padding_lr'] = (int) $preview_settings['text-padding-lr'];
				}
				if ( isset( $preview_settings['background-type'] ) ) {
					$defaults['background_type'] = $preview_settings['background-type'];
					if ( 'image' === $preview_settings['background-type'] && isset( $preview_settings['background-image'] ) && (int) $preview_settings['background-image'] > 0 ) {
						$defaults['background'] = (int) $preview_settings['background-image'];
					}
					if ( 'gradient' === $preview_settings['background-type'] ) {
						$defaults['gradient_start'] = $preview_settings['background-gradient-start'] ?? '#ff6262';
						$defaults['gradient_end']   = $preview_settings['background-gradient-end'] ?? '#6262ff';
						$defaults['gradient_angle'] = (int) ( $preview_settings['background-gradient-angle'] ?? 180 );
					}
				}

				return $defaults;
			};

			add_filter( 'wapt/image_generator_defaults', $filter_callback, 20 );

			// Generate image using Image_Generator
			$format          = isset( $preview_settings['image-type'] ) ? $preview_settings['image-type'] : 'jpg';
			$image_generator = new Image_Generator();
			$image           = $image_generator->generate( $sample_text, '', $format, (int) $preview_settings['image-width'], (int) $preview_settings['image-height'] );

			// Remove filter
			remove_filter( 'wapt/image_generator_defaults', $filter_callback, 20 );

			// Convert image to base64
			$image_resource = $image->get_image();
			if ( ! $image_resource ) {
				return $this->error_response( __( 'Failed to generate preview', 'auto-post-thumbnail' ) );
			}

			ob_start();
			if ( 'png' === $format ) {
				imagepng( $image_resource );
			} else {
				imagejpeg( $image_resource, null, 100 );
			}
			$image_data = ob_get_clean();
			imagedestroy( $image_resource );

			// @phpcs:disable
			$base64    = base64_encode( (string) $image_data );
			// @phpcs:enable

			$mime_type = 'png' === $format ? 'image/png' : 'image/jpeg';
			$data_url  = 'data:' . $mime_type . ';base64,' . $base64;

			return $this->success_response( [ 'preview' => $data_url ] );
		} catch ( \Exception $e ) {
			return $this->error_response( $e->getMessage() );
		}
	}

	/**
	 * Update tracking preference.
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function update_tracking_preference( WP_REST_Request $request ) {
		$enabled = $request->get_param( 'enabled' );
		$value   = $enabled ? 'yes' : 'no';

		update_option( Loader::get_product_key() . '_logger_flag', $value );

		return $this->success_response(
			[
				'message' => $enabled
					? __( 'Anonymous tracking enabled. Thank you for contributing!', 'auto-post-thumbnail' )
					: __( 'Anonymous tracking disabled.', 'auto-post-thumbnail' ),
				'enabled' => $enabled,
			]
		);
	}
}
