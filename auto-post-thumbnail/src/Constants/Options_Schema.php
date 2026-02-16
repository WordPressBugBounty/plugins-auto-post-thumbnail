<?php
/**
 * Options schema definitions.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Constants;

/**
 * Options schema definitions.
 */
class Options_Schema {

	// Setting types
	const SETTING_TYPE_BOOL           = 'bool';
	const SETTING_TYPE_ENUM           = 'enum';
	const SETTING_TYPE_STRING         = 'string';
	const SETTING_TYPE_TEXT           = 'text';
	const SETTING_TYPE_ARRAY          = 'array';
	const SETTING_TYPE_IMPLODED_ARRAY = 'string_array';
	const SETTING_TYPE_COLOR          = 'color';
	const SETTING_TYPE_INTEGER        = 'integer';
	const SETTING_TYPE_FLOAT          = 'float';

	// General settings
	const GENERATION_METHODS              = 'generate_autoimage';
	const AUTO_GENERATION                 = 'auto_generation';
	const AUTO_POST_TYPES                 = 'auto_post_types';
	const DELETE_SETTINGS_ON_DEACTIVATION = 'delete_settings';
	const GENERATION_LOG                  = 'generation_log';

	// Image Settings
	const BACKGROUND_COLOR   = 'background-color';
	const BACKGROUND_IMAGE   = 'background-image';
	const DEFAULT_BACKGROUND = 'default-background';
	const IMAGE_FORMAT       = 'image-type';
	const IMAGE_WIDTH        = 'image-width';
	const IMAGE_HEIGHT       = 'image-height';

	// Typography
	const FONT       = 'font';
	const FONT_SIZE  = 'font-size';
	const FONT_COLOR = 'font-color';


	// Text style
	const TEXT_SHADOW       = 'shadow';
	const TEXT_SHADOW_COLOR = 'shadow-color';
	const TEXT_TRANSFORM    = 'text-transform';
	const TEXT_LENGTH       = 'text-crop';
	const TEXT_LINE_SPACING = 'text-line-spacing';


	// APIs
	const GOOGLE_API_KEY = 'google_apikey';
	const GOOGLE_CSE     = 'google_cse';
	const GOOGLE_LIMIT   = 'google_limit';

	/**
	 * Default arguments for retrieving options schema.
	 *
	 * @var array
	 */
	private static $default_args = [
		'public' => true,
	];

	/**
	 * Get the options schema definition.
	 *
	 * @param string|null $key             Optional specific key to get.
	 * @param bool        $include_private Whether to include private options.
	 *
	 * @return array
	 */
	public static function get( $key = null, $include_private = true ) {
		$schema = [
			// General settings
			self::AUTO_GENERATION                 => [
				'default' => true,
				'type'    => self::SETTING_TYPE_BOOL,
			],
			self::GENERATION_METHODS              => [
				'default' => 'find',
				'type'    => self::SETTING_TYPE_ENUM,
				'allowed' => [
					'find',
					'generate',
					'both',
					'use_default',
					'ai_generate',
				],
			],
			self::AUTO_POST_TYPES                 => [
				'default' => 'post,page',
				'type'    => self::SETTING_TYPE_IMPLODED_ARRAY,
			],
			self::DELETE_SETTINGS_ON_DEACTIVATION => [
				'default' => false,
				'type'    => self::SETTING_TYPE_BOOL,
			],
			// Image Settings
			self::BACKGROUND_COLOR                => [
				'default' => '#ff6262',
				'type'    => self::SETTING_TYPE_COLOR,
			],
			self::BACKGROUND_IMAGE                => [
				'default' => '',
				'type'    => self::SETTING_TYPE_INTEGER,
			],
			self::DEFAULT_BACKGROUND              => [
				'default' => '',
				'type'    => self::SETTING_TYPE_INTEGER,
			],
			self::IMAGE_FORMAT                    => [
				'default' => 'jpg',
				'type'    => self::SETTING_TYPE_ENUM,
				'allowed' => [
					'jpg',
					'png',
				],
			],
			self::IMAGE_WIDTH                     => [
				'default' => 800,
				'type'    => self::SETTING_TYPE_INTEGER,
			],
			self::IMAGE_HEIGHT                    => [
				'default' => 600,
				'type'    => self::SETTING_TYPE_INTEGER,
			],

			// Typography
			self::FONT                            => [
				'default' => 'arial.ttf',
				'type'    => self::SETTING_TYPE_STRING,
			],
			self::FONT_SIZE                       => [
				'default' => 25,
				'type'    => self::SETTING_TYPE_INTEGER,
			],
			self::FONT_COLOR                      => [
				'default' => '#ffffff',
				'type'    => self::SETTING_TYPE_COLOR,
			],

			// Text style
			self::TEXT_SHADOW                     => [
				'default' => false,
				'type'    => self::SETTING_TYPE_BOOL,
			],
			self::TEXT_SHADOW_COLOR               => [
				'default' => '#ffffff',
				'type'    => self::SETTING_TYPE_COLOR,
			],
			self::TEXT_TRANSFORM                  => [
				'default' => 'no',
				'type'    => self::SETTING_TYPE_ENUM,
				'allowed' => [
					'no',
					'upper',
					'lower',
				],
			],
			self::TEXT_LENGTH                     => [
				'default' => 50,
				'type'    => self::SETTING_TYPE_INTEGER,
			],
			self::TEXT_LINE_SPACING               => [
				'default' => 1.5,
				'type'    => self::SETTING_TYPE_FLOAT,
			],

			// APIs
			self::GOOGLE_API_KEY                  => [
				'default' => '',
				'type'    => self::SETTING_TYPE_STRING,
			],
			self::GOOGLE_CSE                      => [
				'default' => '',
				'type'    => self::SETTING_TYPE_STRING,
			],
			self::GOOGLE_LIMIT                    => [
				'default' => [
					'expires' => time(),
					'count'   => 10,
				],
				'type'    => self::SETTING_TYPE_ARRAY,
			],

			// Others
			self::GENERATION_LOG                  => [
				'default' => [],
				'public'  => false,
				'type'    => self::SETTING_TYPE_ARRAY,
			],
		];

		$schema = apply_filters( 'wapt_options_schema', $schema );

		foreach ( $schema as $slug => $args ) {
			$schema[ $slug ] = wp_parse_args( $args, self::$default_args );
		}

		if ( ! $include_private ) {
			$schema = array_filter(
				$schema,
				function ( $value, $slug ) { // phpcs:ignore
					return false !== $value['public'];
				},
				ARRAY_FILTER_USE_BOTH
			);
		}

		return null !== $key && isset( $schema[ $key ] ) ? $schema[ $key ] : $schema;
	}
}
