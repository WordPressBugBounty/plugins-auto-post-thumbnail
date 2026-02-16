<?php
/**
 * Options module.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Modules;

use AutoPostThumbnail\Constants\Options_Schema;
use AutoPostThumbnail\Modules\Base;
use AutoPostThumbnail\Settings;

/**
 * Options module.
 *
 * @package AutoPostThumbnail
 */
class Options extends Base {
	const OPTION_GROUP = 'wapt';
	/**
	 * Run hooks for the options module.
	 *
	 * @return void
	 */
	public function run_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'mime_types', [ $this, 'allow_upload_webp' ] );
	}

	/**
	 * Register the settings for the options module.
	 *
	 * @return void
	 */
	public function register_settings() {
		$schema = Options_Schema::get();

		foreach ( $schema as $key => $value ) {
			$qualified_name = sprintf( '%s_%s', Settings::OPTION_PREFIX, $key );

			register_setting(
				self::OPTION_GROUP,
				$qualified_name,
				[
					'sanitize_callback' => function ( $value ) use ( $key ) {
						return Settings::instance()->sanitize_value( $key, $value );
					},
					'default'           => $value['default'],
				]
			);
		}
	}

	/**
	 * Allow upload webp.
	 *
	 * @param array $existing_mimes Existing mimes.
	 * @return array
	 */
	public function allow_upload_webp( $existing_mimes ) {
		if ( ! is_array( $existing_mimes ) ) {
			return $existing_mimes;
		}

		$existing_mimes['webp'] = 'image/webp';

		return $existing_mimes;
	}
}
