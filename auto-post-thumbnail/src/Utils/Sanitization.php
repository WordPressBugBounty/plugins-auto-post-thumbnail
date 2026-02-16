<?php
/**
 * Sanitization utilities.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Utils;

use AutoPostThumbnail\Constants\Options_Schema;

/**
 * Sanitization utility methods.
 */
class Sanitization {
	/**
	 * Sanitize an array value.
	 *
	 * @param mixed                 $value The value to sanitize.
	 * @param array <string, mixed> $default_value The default value.
	 *
	 * @return array <string, mixed>
	 */
	public static function sanitize_array( $value, $default_value = [] ) {
		$value = is_array( $value ) ? $value : $default_value;

		return $value;
	}

	/**
	 * Sanitize a string array value.
	 *
	 * @param mixed  $value The value to sanitize.
	 * @param string $default_value The default value.
	 *
	 * @return string
	 */
	public static function sanitize_string_array( $value, $default_value = '' ) {
		if ( ! is_string( $value ) ) {
			return $default_value;
		}

		$value = explode( ',', $value );

		$value = array_filter(
			$value,
			function ( $value ) {
				return boolval( trim( $value ) );
			}
		);

		$value = array_map( 'sanitize_text_field', $value );
		$value = implode( ',', $value );

		return $value;
	}

	/**
	 * Sanitize a boolean value.
	 *
	 * @param mixed $value The value to sanitize.
	 * @param bool  $default_value The default value.
	 *
	 * @return bool
	 */
	public static function sanitize_bool( $value, $default_value = false ) {
		if ( is_string( $value ) ) {
			$value = strtolower( $value );
			if ( in_array( $value, [ 'false', '0' ], true ) ) {
				$value = $default_value;
			}
		}

		return (bool) $value;
	}

	/**
	 * Sanitize an enum value.
	 *
	 * @param mixed    $value The value to sanitize.
	 * @param string[] $allowed The allowed values.
	 * @param string   $default_value The default value.
	 *
	 * @return string
	 */
	public static function sanitize_enum( $value, $allowed, $default_value = '' ) {
		if ( empty( $default_value ) ) {
			$default_value = $allowed[0];
		}

		return in_array( $value, $allowed, true ) ? $value : $default_value;
	}


	/**
	 * Sanitize a string value.
	 *
	 * @param string $value The value to sanitize.
	 * @param string $default_value The default value.
	 * @param bool   $allow_spaces Whether to allow leading/trailing spaces.
	 *
	 * @return string
	 */
	public static function sanitize_string( $value, $default_value = '', $allow_spaces = false ) {
		if ( $allow_spaces ) {
			$trailing = strlen( $value ) - strlen( rtrim( $value ) );
			$leading  = strlen( $value ) - strlen( ltrim( $value ) );

			$start = $leading > 0 ? str_repeat( ' ', $leading ) : '';
			$end   = $trailing > 0 ? str_repeat( ' ', $trailing ) : '';

			return $start . sanitize_text_field( trim( $value ) ) . $end;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize the Google limit value.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return array <string, mixed>
	 */
	public static function sanitize_google_limit( $value ) {
		$default_value = Options_Schema::get( Options_Schema::GOOGLE_LIMIT )['default'];

		return is_array( $value ) && isset( $value['expires'] ) && isset( $value['count'] ) ? $value : $default_value;
	}

	/**
	 * Sanitize a float value.
	 *
	 * @param mixed $value The value to sanitize.
	 * @param float $default_value The default value.
	 *
	 * @return float
	 */
	public static function sanitize_float( $value, $default_value = 0 ) {
		return is_numeric( $value ) ? floatval( $value ) : $default_value;
	}

	/**
	 * Sanitize an integer value.
	 *
	 * @param mixed $value The value to sanitize.
	 * @param int   $default_value The default value.
	 *
	 * @return int
	 */
	public static function sanitize_integer( $value, $default_value = 0 ) {
		return is_numeric( $value ) ? intval( $value ) : $default_value;
	}

	/**
	 * Sanitize a hex color value.
	 *
	 * @param mixed  $value The value to sanitize.
	 * @param string $default_value The default value.
	 *
	 * @return string
	 */
	public static function sanitize_color( $value, $default_value = '#ffffff' ) {
		if ( ! is_string( $value ) || empty( trim( $value ) ) ) {
			return $default_value;
		}

		$sanitized = sanitize_hex_color( $value );

		if ( empty( $sanitized ) ) {
			return $default_value;
		}

		return $sanitized;
	}

	/**
	 * Sanitize post title for image generation.
	 *
	 * @param string $value The value to sanitize.
	 *
	 * @return string
	 */
	public static function sanitize_post_title_for_image_generation( $value ) {
		$value = mb_convert_encoding( $value, 'UTF-8' );
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return $value;
	}
}
