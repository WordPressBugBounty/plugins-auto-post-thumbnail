<?php
/**
 * Image generator service.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Services;

use AutoPostThumbnail\Constants\Options_Schema;
use AutoPostThumbnail\Models\Image;
use AutoPostThumbnail\Settings;
use AutoPostThumbnail\Utils\Helpers;

/**
 * Class Image_Generator
 *
 * @package AutoPostThumbnail\Services
 */
class Image_Generator {
	/**
	 * Font file path.
	 *
	 * @var string
	 */
	private $font;

	/**
	 * Font size.
	 *
	 * @var int
	 */
	private $font_size;

	/**
	 * Font color.
	 *
	 * @var string
	 */
	private $font_color;

	/**
	 * Image width.
	 *
	 * @var int
	 */
	private $width;

	/**
	 * Image height.
	 *
	 * @var int
	 */
	private $height;

	/**
	 * Text to prepend.
	 *
	 * @var string
	 */
	private $before_text;

	/**
	 * Text to append.
	 *
	 * @var string
	 */
	private $after_text;

	/**
	 * Text transformation type.
	 *
	 * @var string
	 */
	private $text_transform;

	/**
	 * Maximum text length.
	 *
	 * @var int
	 */
	private $text_crop;

	/**
	 * Background type.
	 *
	 * @var string
	 */
	private $background_type;

	/**
	 * Background color or image ID.
	 *
	 * @var string|int
	 */
	private $background;

	/**
	 * Gradient start color.
	 *
	 * @var string
	 */
	private $gradient_start = '';

	/**
	 * Gradient end color.
	 *
	 * @var string
	 */
	private $gradient_end = '';

	/**
	 * Gradient angle.
	 *
	 * @var int
	 */
	private $gradient_angle = 180;

	/**
	 * Whether to show text shadow.
	 *
	 * @var bool
	 */
	private $shadow;

	/**
	 * Shadow color.
	 *
	 * @var string
	 */
	private $shadow_color;

	/**
	 * Horizontal text alignment.
	 *
	 * @var string
	 */
	private $align;

	/**
	 * Vertical text alignment.
	 *
	 * @var string
	 */
	private $valign;

	/**
	 * Top and bottom padding.
	 *
	 * @var int
	 */
	private $padding_tb;

	/**
	 * Left and right padding.
	 *
	 * @var int
	 */
	private $padding_lr;

	/**
	 * Line spacing multiplier.
	 *
	 * @var float
	 */
	private $line_spacing;

	/**
	 * Constructor - sets defaults, can be overridden via filters
	 */
	public function __construct() {
		$this->set_defaults();
	}

	/**
	 * Set default values - can be filtered
	 *
	 * @return void
	 */
	private function set_defaults() {
		// Get font name and resolve to path
		$font_name = Settings::instance()->get( Options_Schema::FONT );

		$font = Helpers::resolve_font_path( $font_name );

		$defaults = [
			// Font
			'font'            => $font,
			'font_color'      => Settings::instance()->get( Options_Schema::FONT_COLOR ),
			'font_size'       => (int) Settings::instance()->get( Options_Schema::FONT_SIZE ),
			'line_spacing'    => (float) Settings::instance()->get( Options_Schema::TEXT_LINE_SPACING ),
			// Dimensions
			'width'           => (int) Settings::instance()->get( Options_Schema::IMAGE_WIDTH ),
			'height'          => (int) Settings::instance()->get( Options_Schema::IMAGE_HEIGHT ),
			// Text settings
			'text_crop'       => (int) Settings::instance()->get( Options_Schema::TEXT_LENGTH ),
			'text_transform'  => Settings::instance()->get( Options_Schema::TEXT_TRANSFORM ),
			// Background
			'background'      => Settings::instance()->get( Options_Schema::BACKGROUND_COLOR ),
			// Shadow
			'shadow'          => (bool) Settings::instance()->get( Options_Schema::TEXT_SHADOW ),
			'shadow_color'    => Settings::instance()->get( Options_Schema::TEXT_SHADOW_COLOR ),

			// Static values (pro-only settings - will be overridden by pro filter)
			'before_text'     => '',
			'after_text'      => '',
			'background_type' => 'color',
			'align'           => 'center',
			'valign'          => 'center',
			'padding_tb'      => 15,
			'padding_lr'      => 15,
		];

		// Allow filtering of defaults
		$defaults = apply_filters( 'wapt/image_generator_defaults', $defaults );

		// Set properties
		foreach ( $defaults as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}
	}

	/**
	 * Generate image with text.
	 *
	 * @param string $text Text to render.
	 * @param string $path_to_save Optional path to save the image.
	 * @param string $format Image format (jpg, png, etc.).
	 * @param int    $width Optional width override.
	 * @param int    $height Optional height override.
	 *
	 * @return Image
	 */
	public function generate( $text, $path_to_save = '', $format = 'jpg', $width = 0, $height = 0 ) {
		// Ensure text is provided
		if ( empty( $text ) && empty( $this->before_text ) && empty( $this->after_text ) ) {
			$text = ' '; // Use space to ensure image is generated
		}

		// Use override dimensions if provided
		$final_width  = $width > 0 ? $width : $this->width;
		$final_height = $height > 0 ? $height : $this->height;

		// Build generation parameters
		$params = [
			'text'            => $text,
			'pathToSave'      => $path_to_save,
			'format'          => $format,
			'width'           => $final_width,
			'height'          => $final_height,
			'font'            => $this->font,
			'font_size'       => $this->font_size,
			'font_color'      => $this->font_color,
			'before_text'     => $this->before_text,
			'after_text'      => $this->after_text,
			'text_transform'  => $this->text_transform,
			'text_crop'       => $this->text_crop,
			'background_type' => $this->background_type,
			'background'      => $this->background,
			'gradient_start'  => $this->gradient_start,
			'gradient_end'    => $this->gradient_end,
			'gradient_angle'  => $this->gradient_angle,
			'shadow'          => $this->shadow,
			'shadow_color'    => $this->shadow_color,
			'align'           => $this->align,
			'valign'          => $this->valign,
			'padding_tb'      => $this->padding_tb,
			'padding_lr'      => $this->padding_lr,
			'line_spacing'    => $this->line_spacing,
		];

		// Allow filtering of generation parameters
		$params = apply_filters( 'wapt/image_generator_params', $params, $text );

		// Extract filtered values back
		$final_width  = $params['width'];
		$final_height = $params['height'];

		// Apply text transformations
		$processed_text = $this->transform_text( $params['text'], $params['text_transform'] );
		$processed_text = $this->crop_text( $processed_text, $params['text_crop'] );

		// Combine before_text, text, and after_text, then process [br] tags
		$full_text = $params['before_text'] . $processed_text . $params['after_text'];
		$full_text = str_replace( '[br]', "\n", $full_text );

		// Ensure we have some text to render
		if ( empty( trim( $full_text ) ) ) {
			$full_text = ' '; // Use space to ensure text is rendered
		}

		// Allow filtering of final text
		$full_text = apply_filters( 'image_generator_final_text', $full_text, $text, $params );

		// Resolve background value — for gradient, pass an array to the Image model
		$background = $params['background'];
		if ( 'gradient' === $params['background_type'] && ! empty( $params['gradient_start'] ) ) {
			$background = [
				'gradient_start' => $params['gradient_start'],
				'gradient_end'   => $params['gradient_end'],
				'gradient_angle' => (int) $params['gradient_angle'],
			];
		}

		// Create image
		$image = new Image(
			$final_width,
			$final_height,
			$background,
			$params['font'],
			$params['font_size'],
			$params['font_color']
		);

		$image->params = [
			'text'       => $text,
			'pathToSave' => $path_to_save,
			'format'     => $format,
			'width'      => $final_width,
			'height'     => $final_height,
		];

		$image->set_padding( $params['padding_lr'], $params['padding_tb'] );
		$image->write_text(
			$full_text,
			$params['align'],
			$params['valign'],
			$params['line_spacing'],
			$params['shadow'],
			$params['shadow_color']
		);

		if ( ! empty( $path_to_save ) ) {
			$image->save( $path_to_save, 100, $format );
		}

		// Allow filtering of final image object
		$image = apply_filters( 'image_generator_image', $image, $params );

		return $image;
	}

	/**
	 * Apply text transformation.
	 *
	 * @param string $text The text to transform.
	 * @param string $transform The transformation to apply.
	 *
	 * @return string
	 */
	private function transform_text( $text, $transform ) {
		switch ( $transform ) {
			case 'upper':
				return mb_strtoupper( $text );
			case 'lower':
				return mb_strtolower( $text );
			default:
				return $text;
		}
	}

	/**
	 * Crop text to specified length.
	 *
	 * @param string $text The text to crop.
	 * @param int    $max_length Maximum text length.
	 *
	 * @return string
	 */
	private function crop_text( $text, $max_length ) {
		if ( $max_length > 0 && strlen( $text ) > $max_length ) {
			$temp       = substr( $text, 0, $max_length );
			$last_space = strrpos( $temp, ' ' );

			if ( false !== $last_space ) {
				return substr( $temp, 0, $last_space );
			}

			return $temp;
		}

		return $text;
	}
}
