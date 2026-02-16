<?php
/**
 * Generate result model.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Models;

use AutoPostThumbnail\Constants\Options_Schema;
use AutoPostThumbnail\Settings;
use AutoPostThumbnail\Utils\Sanitization;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Result class
 */
class Generate_Result {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Thumbnail attachment ID.
	 *
	 * @var int
	 */
	public $thumbnail_id;

	/**
	 * Generation method label.
	 *
	 * @var string
	 */
	private $generate_method;

	/**
	 * Result status.
	 *
	 * @var string
	 */
	public $status;

	/**
	 * Result message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Available generation methods.
	 *
	 * @var array
	 */
	private $methods;

	/**
	 * GenerateResult constructor.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $generate_method Generate method.
	 */
	public function __construct( $post_id, $generate_method = '' ) {
		$this->methods = [
			'find'        => __( 'Find in post', 'auto-post-thumbnail' ),
			'generate'    => __( 'Generate from title', 'auto-post-thumbnail' ),
			'both'        => __( 'Find or generate', 'auto-post-thumbnail' ),
			'google'      => __( 'Google', 'auto-post-thumbnail' ),
			'find_google' => __( 'Find or Google', 'auto-post-thumbnail' ),
			'use_default' => __( 'Find or use default image', 'auto-post-thumbnail' ),
		];

		$this->post_id         = $post_id;
		$this->generate_method = $this->get_method( $generate_method );
	}

	/**
	 * Set the result data.
	 *
	 * @param string $message Message.
	 * @param int    $thumbnail_id Thumbnail ID.
	 * @param string $status Status.
	 */
	public function set_result( $message = '', $thumbnail_id = 0, $status = '' ) {

		$this->thumbnail_id = $thumbnail_id;
		$this->status       = ! empty( $status ) ? $status : __( 'Done', 'auto-post-thumbnail' );
		$this->message      = $message;
	}

	/**
	 * Return self with result data.
	 *
	 * @param string $message Message.
	 * @param int    $thumbnail_id Thumbnail ID.
	 * @param string $status Status.
	 *
	 * @return self
	 */
	public function result( $message = '', $thumbnail_id = 0, $status = '' ) {
		$this->set_result( $message, $thumbnail_id, $status );
		$this->write_to_log();

		return $this;
	}

	/**
	 * Get the generation method label.
	 *
	 * @param string $method Method.
	 *
	 * @return string
	 */
	private function get_method( $method ) {
		return $this->methods[ $method ] ?? '';
	}

	/**
	 * Get the generation method string.
	 *
	 * @return string
	 */
	public function get_generate_method() {
		return $this->generate_method;
	}

	/**
	 * Get formatted file size from URL.
	 *
	 * @param string $url File URL.
	 *
	 * @return string
	 */
	private function get_file_size( $url ) {
		$path       = '';
		$parsed_url = wp_parse_url( $url );
		if ( empty( $parsed_url['path'] ) ) {
			return '';
		}
		$file = ABSPATH . ltrim( $parsed_url['path'], '/' );
		if ( file_exists( $file ) ) {
			$bytes = filesize( $file );
			$s     = [ 'b', 'Kb', 'Mb', 'Gb' ];
			$e     = (int) ( floor( log( $bytes ) / log( 1024 ) ) );

			return sprintf( '%d ' . $s[ $e ], ( $bytes / pow( 1024, floor( $e ) ) ) );
		}

		return '';
	}

	/**
	 * Get the result data array.
	 *
	 * @return array
	 */
	public function get_data() {
		$data = [
			'post_id' => $this->post_id,
			'url'     => get_permalink( $this->post_id ),
			'title'   => Sanitization::sanitize_post_title_for_image_generation( get_post( $this->post_id )->post_title ),
			'type'    => $this->get_generate_method(),
			'status'  => $this->status,
			'uid'     => substr( wp_generate_password( 8, false ), 0, 6 ),
		];

		if ( $this->thumbnail_id ) {
			$data['thumbnail_url'] = wp_get_attachment_image_url( $this->thumbnail_id, 'thumbnail' );
			$data['image_size']    = $this->get_file_size( wp_get_attachment_image_url( $this->thumbnail_id, 'full' ) );
			$data['thumbnail_id']  = $this->thumbnail_id;
		} else {
			$data['error_msg'] = $this->message;
		}

		return $data;
	}

	/**
	 * Write the generation result to the log.
	 *
	 * @return void
	 */
	public function write_to_log() {
		$data = $this->get_data();

		$log = Settings::instance()->get( Options_Schema::GENERATION_LOG );
		if ( count( $log ) > 100 ) {
			$log = array_slice( $log, 0, 100 );
		}

		array_unshift( $log, $data );

		Settings::instance()->update_option( Options_Schema::GENERATION_LOG, $log );
	}
}
