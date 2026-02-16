<?php
/**
 * Image model.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Models;

use AutoPostThumbnail\Utils\Sanitization;
use GdImage;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for image processing
 *
 * @version       1.0
 */
class Image {

	/**
	 * Image width.
	 *
	 * @var integer
	 */
	public $width;

	/**
	 * Image height.
	 *
	 * @var integer
	 */
	public $height;

	/**
	 * Font file path.
	 *
	 * @var string
	 */
	private $font_path = WAPT_PLUGIN_DIR . '/fonts/arial.ttf';

	/**
	 * Font size.
	 *
	 * @var integer
	 */
	public $font_size;

	/**
	 * Font color.
	 *
	 * @var string|array
	 */
	public $font_color;

	/**
	 * Text to render.
	 *
	 * @var string
	 */
	public $text;

	/**
	 * Background color or image.
	 *
	 * @var string
	 */
	public $background;

	/**
	 * Reference text for font size calculations.
	 *
	 * @var string
	 */
	private $reference_text = 'abcdefghijklmnopqrstuvwxyz ABCDEFGHIJKLMNOPQRSTUVWXYZ-!?.,_"[]';

	/**
	 * GD image resource.
	 *
	 * @var GdImage|resource|bool
	 */
	private $image = false;

	/**
	 * Left padding.
	 *
	 * @var int
	 */
	public $padding_left = 0;

	/**
	 * Top padding.
	 *
	 * @var int
	 */
	public $padding_top = 0;

	/**
	 * Line spacing multiplier.
	 *
	 * @var float
	 */
	public $line_spacing = 1.5;

	/**
	 * Image parameters.
	 *
	 * @var array
	 */
	public $params = [];

	/**
	 * Get the GD image resource.
	 *
	 * @return GdImage|resource|bool
	 */
	public function get_image() {
		return $this->image;
	}

	/**
	 * Set image padding.
	 *
	 * @param int $padding_left Left padding.
	 * @param int $padding_top Top padding.
	 */
	public function set_padding( $padding_left, $padding_top ) {
		$this->padding_left = $padding_left;
		$this->padding_top  = $padding_top;
	}

	/**
	 * Set image width.
	 *
	 * @param int $width Image width.
	 */
	public function set_width( $width ) {
		$this->width = $width;
	}

	/**
	 * Set image height.
	 *
	 * @param int $height Image height.
	 */
	public function set_height( $height ) {
		$this->height = $height;
	}

	/**
	 * Set font file path.
	 *
	 * @param string $font_path Font file path.
	 */
	public function set_font_path( $font_path ) {
		if ( file_exists( $font_path ) ) {
			$this->font_path = $font_path;
		}
	}

	/**
	 * {PLUGIN_DIR}/fonts/{font}.ttf
	 *
	 * @param string $font Font name.
	 */
	public function set_font( $font ) {
		$this->font_path = WAPT_PLUGIN_DIR . "/fonts/{$font}.ttf";
	}

	/**
	 * Set font size.
	 *
	 * @param int $font_size Font size.
	 */
	public function set_font_size( $font_size ) {
		$this->font_size = $font_size;
	}

	/**
	 * Set font color.
	 *
	 * @param array|string $font_color Font color.
	 */
	public function set_font_color( $font_color ) {
		$this->font_color = $font_color;
	}

	/**
	 * Set text to render.
	 *
	 * @param string $text Text to render.
	 */
	public function set_text( $text ) {
		$this->text = $text;
	}

	/**
	 * Set background color or image.
	 *
	 * @param array|string $background Background color or image.
	 */
	public function set_background( $background ) {
		$this->background = $background;
	}

	/**
	 * Constructor.
	 *
	 * @param int          $width Image width.
	 * @param int          $height Image height.
	 * @param array|string $background Background color.
	 * @param string       $font Font path.
	 * @param int          $font_size Font size.
	 * @param string       $font_color Font color.
	 */
	public function __construct( $width, $height, $background = '#ffffff', $font = '', $font_size = 0, $font_color = '#000000' ) {

		$this->width      = $width;
		$this->height     = $height;
		$this->background = $background;
		$this->font_path  = $font;
		$this->font_size  = $font_size;
		$this->font_color = $font_color;

		$this->image = $this->create( $width, $height, $background );
	}

	/**
	 * Create image.
	 *
	 * @param int        $width Image width.
	 * @param int        $height Image height.
	 * @param string|int $background Background color or image ID.
	 *
	 * @return GdImage|resource|bool
	 */
	public function create( $width, $height, $background = '#ffffff' ) {
		if ( is_numeric( $background ) ) { // image
			$image = wp_get_attachment_metadata( $background );
			if ( $image ) {
				$upload_dir = wp_upload_dir();
				$file_path  = $upload_dir['basedir'] . '/' . $image['file'];
				$file_type  = wp_check_filetype( $file_path );
				switch ( $file_type['type'] ) {
					case 'image/jpeg':
						$im = imagecreatefromjpeg( $file_path );
						$this->set_width( $image['width'] );
						$this->set_height( $image['height'] );
						break;

					case 'image/png':
						$im = imagecreatefrompng( $file_path );
						imagesavealpha( $im, true );
						$this->set_width( $image['width'] );
						$this->set_height( $image['height'] );
						break;

					default:
						$im = $this->create( $width, $height );
						break;
				}
			} else {
				$im = $this->create( $width, $height );
			}
		} elseif ( is_array( $background ) && isset( $background['gradient_start'] ) ) { // gradient
			$im = imagecreatetruecolor( $width, $height );
			$this->draw_linear_gradient( $im, $background, $width, $height );
		} else { // color
			$im       = imagecreatetruecolor( $width, $height );
			$color    = $this->color_hex_to_rgb( $background );
			$bg_color = imagecolorallocate( $im, $color['r'], $color['g'], $color['b'] );
			imagefill( $im, 0, 0, $bg_color );
		}

		return $im;
	}

	/**
	 * Convert hex color to RGB.
	 *
	 * @param string $hex Hex color string.
	 *
	 * @return array RGB color array.
	 */
	private function color_hex_to_rgb( $hex = '' ) {
		if ( empty( $hex ) ) {
			$hex = $this->font_color;
		}
		[$r, $g, $b] = sscanf( $hex, '#%02x%02x%02x' );

		return [
			'r' => $r,
			'g' => $g,
			'b' => $b,
		];
	}

	/**
	 * Get width and height of a character in the font.
	 *
	 * @return array|false
	 */
	public function get_font_char_size() {
		if ( '' !== $this->font_path && 0 !== $this->font_size ) {

			$text   = ! empty( $this->text ) ? $this->text : $this->reference_text;
			$box    = imagettfbbox( $this->font_size, 0, $this->font_path, $text );
			$width  = ceil( ( $box[2] - $box[0] ) / strlen( $text ) );
			$height = $box[1] - $box[7];
			$result = [
				'width'  => $width ? $width : 1, // average width of one character
				'height' => $height ? $height : 1, // height of one character
			];

			return $result;
		} else {
			return false;
		}
	}

	/**
	 * Write text on the image.
	 *
	 * @param string $text The text to write on the image.
	 * @param string $align The alignment of the text.
	 * @param string $valign The vertical alignment of the text.
	 * @param float  $line_spacing The spacing between the lines of text.
	 * @param bool   $enable_shadow Whether to enable the shadow.
	 * @param string $shadow_color The color of the shadow.
	 *
	 * @return bool
	 */
	public function write_text( $text, $align = 'left', $valign = 'top', $line_spacing = 1.5, $enable_shadow = false, $shadow_color = '' ) {
		if ( empty( $text ) ) {
			return false;
		}

		$text = Sanitization::sanitize_post_title_for_image_generation( $text );

		$font       = $this->font_path;
		$font_size  = $this->font_size;
		$font_color = $this->font_color;

		$this->set_text( $text );
		$char_size = $this->get_font_char_size();

		$pad_left = (int) $this->padding_left;
		$pad_top  = (int) $this->padding_top;

		$color      = $this->color_hex_to_rgb( $font_color );
		$font_color = imagecolorallocate( $this->image, $color['r'], $color['g'], $color['b'] );
		if ( ! empty( $shadow_color ) ) {
			$color        = $this->color_hex_to_rgb( $shadow_color );
			$shadow_color = imagecolorallocate( $this->image, $color['r'], $color['g'], $color['b'] );
		}
		$line_spacing = (float) $line_spacing;

		$width  = $this->width - $pad_left * 2;
		$height = $this->height - $pad_top * 2;

		$chars_per_line = (int) ceil( $width / $char_size['width'] * 0.9 ); // count of chars per line
		$text2          = wordwrap( $text, $chars_per_line, "\n", false );
		$text2          = str_replace( '[br]', "\n", $text2 );
		$line_count     = count( explode( "\n", $text2 ) );
		$lines          = explode( "\n", $text2 );
		for ( $i = 0; $i < $line_count; $i++ ) {
			$box = imagettfbbox( $font_size, 0, $font, $this->commas_cut( $lines[ $i ] ) );
			$w   = $box[4] - $box[6];
			if ( $w > $width ) {
				--$font_size;
				$i = 0;
			}
		}

		$text_height = $line_count * $char_size['height'];
		while ( $text_height > $height || ( $height - $text_height <= ( 2 * $pad_left ) ) ) {
			--$this->font_size;
			--$font_size;
			$char_size = $this->get_font_char_size();
			if ( ! $char_size ) {
				break;
			}
			$line_width  = ceil( $width / $char_size['width'] * 0.9 ); // count of chars per line
			$text2       = wordwrap( $text, (int) $line_width, "\n", false );
			$text2       = str_replace( '[br]', "\n", $text2 );
			$line_count  = count( explode( "\n", $text2 ) );
			$text_height = $line_count * ( $char_size['height'] * $line_spacing );
		}
		$width  = $this->width;
		$height = $this->height;

		$lines = explode( "\n", $text2 );
		if ( 'bottom' === $valign ) {
			$lines = array_reverse( $lines );
		}

		foreach ( $lines as $key => $line ) {
			$box = imagettfbbox( $font_size, 0, $font, $this->commas_cut( $line ) );
			$h   = $char_size['height'] * count( $lines ) + ( $line_spacing - 1 ) * $char_size['height'] * count( $lines );
			$w   = $box[4] - $box[6];
			$num = $line_spacing * $key;

			$x = 0;
			$y = 0;
			switch ( $align . '-' . $valign ) {
				case 'left-top':
					$x = $pad_left;
					$y = ceil( $pad_top + $char_size['height'] + ( $char_size['height'] * $num ) );
					break;
				case 'left-center':
					$x = $pad_left;
					$y = ceil( ( $height / 2 - $h / 2 ) + $char_size['height'] + ( $char_size['height'] * $num ) );
					break;
				case 'left-bottom':
					$x = $pad_left;
					$y = ceil( ( $height - $pad_top ) - ( $char_size['height'] * $num ) );
					break;
				// -------------------------
				case 'center-top':
					$x = ceil( $width / 2 - $w / 2 );
					$y = ceil( $pad_top + $char_size['height'] + ( $char_size['height'] * $num ) );
					break;
				case 'center-center':
					$x = ceil( $width / 2 - $w / 2 );
					$y = ceil( ( $height / 2 - $h / 2 ) + $char_size['height'] + ( $char_size['height'] * $num ) );
					break;
				case 'center-bottom':
					$x = ceil( $width / 2 - $w / 2 );
					$y = ceil( ( $height - $pad_top ) - ( $char_size['height'] * $num ) );
					break;
				// -------------------------
				case 'right-top':
					$x = $width - $w - $pad_left;
					$y = ceil( $pad_top + $char_size['height'] + ( $char_size['height'] * $num ) );
					break;
				case 'right-center':
					$x = $width - $w - $pad_left;
					$y = ceil( ( $height / 2 - $h / 2 ) + $char_size['height'] + ( $char_size['height'] * $num ) );
					break;
				case 'right-bottom':
					$x = $width - $w - $pad_left;
					$y = ceil( ( $height - $pad_top ) - ( $char_size['height'] * $num ) );
					break;
			}
			// shadow
			if ( $enable_shadow ) {
				imagettftext( $this->image, $font_size, 0, $x + 2, $y + 2, $shadow_color, $font, trim( $line ) );
			}

			// text
			imagettftext( $this->image, $font_size, 0, (int) $x, (int) $y, $font_color, $font, trim( $line ) );
		}
		return true;
	}

	/**
	 * Remove commas from text.
	 *
	 * @param string $text The text to process.
	 *
	 * @return string
	 */
	public function commas_cut( $text ) {
		return str_replace( ',', '', $text );
	}

	/**
	 * Draw a linear gradient on the image.
	 *
	 * @param GdImage|resource $im       GD image resource.
	 * @param array            $gradient Array with gradient_start, gradient_end, gradient_angle.
	 * @param int              $width    Image width.
	 * @param int              $height   Image height.
	 */
	private function draw_linear_gradient( $im, $gradient, $width, $height ) {
		$start = $this->color_hex_to_rgb( $gradient['gradient_start'] ?? '#ff6262' );
		$end   = $this->color_hex_to_rgb( $gradient['gradient_end'] ?? '#6262ff' );
		$angle = (int) ( $gradient['gradient_angle'] ?? 180 );

		switch ( $angle ) {
			case 90: // left to right
				for ( $x = 0; $x < $width; $x++ ) {
					$ratio = $x / max( $width - 1, 1 );
					$r     = (int) ( $start['r'] + ( $end['r'] - $start['r'] ) * $ratio );
					$g     = (int) ( $start['g'] + ( $end['g'] - $start['g'] ) * $ratio );
					$b     = (int) ( $start['b'] + ( $end['b'] - $start['b'] ) * $ratio );
					$color = imagecolorallocate( $im, $r, $g, $b );
					imageline( $im, $x, 0, $x, $height - 1, $color );
				}
				break;

			case 180: // top to bottom
				for ( $y = 0; $y < $height; $y++ ) {
					$ratio = $y / max( $height - 1, 1 );
					$r     = (int) ( $start['r'] + ( $end['r'] - $start['r'] ) * $ratio );
					$g     = (int) ( $start['g'] + ( $end['g'] - $start['g'] ) * $ratio );
					$b     = (int) ( $start['b'] + ( $end['b'] - $start['b'] ) * $ratio );
					$color = imagecolorallocate( $im, $r, $g, $b );
					imageline( $im, 0, $y, $width - 1, $y, $color );
				}
				break;

			case 135: // top-left to bottom-right
			case 225: // bottom-right to top-left
				$s = $start;
				$e = $end;
				if ( 225 === $angle ) {
					$s = $end;
					$e = $start;
				}
				$max_dist = $width + $height - 2;
				for ( $y = 0; $y < $height; $y++ ) {
					for ( $x = 0; $x < $width; $x++ ) {
						$ratio = ( $x + $y ) / max( $max_dist, 1 );
						$r     = (int) ( $s['r'] + ( $e['r'] - $s['r'] ) * $ratio );
						$g     = (int) ( $s['g'] + ( $e['g'] - $s['g'] ) * $ratio );
						$b     = (int) ( $s['b'] + ( $e['b'] - $s['b'] ) * $ratio );
						$color = imagecolorallocate( $im, $r, $g, $b );
						imagesetpixel( $im, $x, $y, $color );
					}
				}
				break;

			default: // fallback to top-to-bottom
				for ( $y = 0; $y < $height; $y++ ) {
					$ratio = $y / max( $height - 1, 1 );
					$r     = (int) ( $start['r'] + ( $end['r'] - $start['r'] ) * $ratio );
					$g     = (int) ( $start['g'] + ( $end['g'] - $start['g'] ) * $ratio );
					$b     = (int) ( $start['b'] + ( $end['b'] - $start['b'] ) * $ratio );
					$color = imagecolorallocate( $im, $r, $g, $b );
					imageline( $im, 0, $y, $width - 1, $y, $color );
				}
				break;
		}
	}

	/**
	 * Save image.
	 *
	 * @param string  $path File path.
	 * @param integer $quality Image quality.
	 * @param string  $format Image format.
	 */
	public function save( $path, $quality = 100, $format = 'jpg' ) {
		switch ( strtolower( $format ) ) {
			case 'jpg':
			case 'jpeg':
				imagejpeg( $this->image, $path, $quality );
				break;
			case 'png':
				imagepng( $this->image, $path );
				break;
		}
	}
}
