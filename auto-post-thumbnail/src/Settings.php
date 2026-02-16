<?php
/**
 * Settings management.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail;

use AutoPostThumbnail\Constants\Options_Schema;
use AutoPostThumbnail\Utils\Sanitization;

/**
 * Plugin settings manager.
 */
class Settings {
	const OPTION_PREFIX = 'wapt';
	/**
	 * The instance of the class.
	 *
	 * @var \AutoPostThumbnail\Settings
	 */
	private static $instance;

	/**
	 * Summary of instance
	 *
	 * @return \AutoPostThumbnail\Settings
	 */
	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get the option value.
	 *
	 * @param string $key The key of the option.
	 * @return mixed The value of the option.
	 */
	public function get( $key ) {
		$qualified_name = sprintf( '%s_%s', self::OPTION_PREFIX, $key );
		$schema         = Options_Schema::get( $key );

		$default = isset( $schema['default'] ) ? $schema['default'] : false;

		if ( ! isset( $schema['type'] ) ) {
			return get_option( $qualified_name, $default );
		}

		$value = get_option( $qualified_name, $default );

		return $this->sanitize_value( $key, $value );
	}

	/**
	 * Update the option value.
	 *
	 * @param string $key The key of the option.
	 * @param mixed  $value The value of the option.
	 *
	 * @return bool The result of the update.
	 */
	public function update_option( $key, $value ) {
		$qualified_name = sprintf( '%s_%s', self::OPTION_PREFIX, $key );

		$sanitized_value = $this->sanitize_value( $key, $value );

		$old_value = $this->get( $key );
		$status    = update_option( $qualified_name, $sanitized_value );

		if ( $status ) {
			do_action( 'wapt/option_updated', $key, $sanitized_value, $old_value, $status );
		}
		return $status;
	}

	/**
	 * Update the options.
	 *
	 * @param array <string, mixed> $options The options to update.
	 *
	 * @return array <string, mixed> The updated options.
	 */
	public function update_options( $options ) {
		foreach ( $options as $key => $value ) {
			$this->update_option( $key, $value );
		}

		return $this->get_all_public();
	}

	/**
	 * Get all public settings.
	 *
	 * @return array <string, mixed>
	 */
	public function get_all_public() {
		$schema  = Options_Schema::get( null, include_private: false );
		$options = [];
		foreach ( $schema as $key => $value ) {
			$options[ $key ] = $this->get( $key );
		}
		return $options;
	}

	/**
	 * Get all settings.
	 *
	 * @return array <string, mixed>
	 */
	public function get_all() {
		$schema  = Options_Schema::get( null, include_private: true );
		$options = [];
		foreach ( $schema as $key => $value ) {
			$options[ $key ] = $this->get( $key );
		}
		return $options;
	}

	/**
	 * Get tracked options.
	 *
	 * @return array <string, mixed>
	 */
	public function get_tracked() {
		$schema  = Options_Schema::get( null, include_private: true );
		$tracked = array_filter(
			$schema,
			function ( $value ) {
				return isset( $value['tracked'] ) && $value['tracked'];
			}
		);

		$options = [];

		foreach ( $tracked as $key => $args ) {
			$options[ $key ] = $this->get( $key );
		}

		return $options;
	}

	/**
	 * Delete all settings.
	 *
	 * @return void
	 */
	public function delete_all() {
		$schema = Options_Schema::get( null, include_private: true );
		foreach ( $schema as $key => $value ) {
			delete_option( sprintf( '%s_%s', self::OPTION_PREFIX, $key ) );
		}
	}

	/**
	 * Sanitize a settings value.
	 *
	 * @param string $key The setting key.
	 * @param mixed  $value The value to sanitize.
	 *
	 * @return mixed
	 */
	public function sanitize_value( $key, $value ) {
		$schema = Options_Schema::get( $key );

		switch ( $schema['type'] ) {
			case Options_Schema::SETTING_TYPE_BOOL:
				return Sanitization::sanitize_bool( $value, $schema['default'] );
			case Options_Schema::SETTING_TYPE_ENUM:
				return Sanitization::sanitize_enum( $value, $schema['allowed'], $schema['default'] );
			case Options_Schema::SETTING_TYPE_STRING:
				return Sanitization::sanitize_string( $value, $schema['default'] );
			case Options_Schema::SETTING_TYPE_TEXT:
				return Sanitization::sanitize_string( $value, $schema['default'], true );
			case Options_Schema::SETTING_TYPE_ARRAY:
				return Sanitization::sanitize_array( $value, $schema['default'] );
			case Options_Schema::SETTING_TYPE_IMPLODED_ARRAY:
				return Sanitization::sanitize_string_array( $value, $schema['default'] );
			case Options_Schema::SETTING_TYPE_FLOAT:
				return Sanitization::sanitize_float( $value, $schema['default'] );
			case Options_Schema::SETTING_TYPE_INTEGER:
				return Sanitization::sanitize_integer( $value, $schema['default'] );
			case Options_Schema::SETTING_TYPE_COLOR:
				return Sanitization::sanitize_color( $value, $schema['default'] );
			default:
				return $value;
		}
	}
}
