<?php
/**
 * Post images model.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Models;

use AutoPostThumbnail\Logger;
use WP_Post;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post images class
 */
class Post_Images {
	/**
	 * WordPress post object.
	 *
	 * @var WP_Post
	 */
	public $post;

	/**
	 * Found images array.
	 *
	 * @var array
	 */
	private $images = [];

	/**
	 * Found videos array.
	 *
	 * @var array
	 */
	private $videos = [];

	/**
	 * Post Images constructor.
	 *
	 * @param WP_Post|int|string $post Post object or post ID or post content.
	 * @param bool               $skip_videos Skip videos.
	 */
	public function __construct( $post = null, $skip_videos = false ) {
		if ( is_numeric( $post ) ) {
			$post       = get_post( $post, 'OBJECT' );
			$this->post = $post;
		} elseif ( is_object( $post ) ) {
			$this->post = $post;
		} elseif ( is_string( $post ) ) {
			$new_post               = new \stdClass();
			$new_post->post_content = $post;

			$this->post = new WP_Post( $new_post );
		}

		$this->find_images( $skip_videos );
	}

	/**
	 * Get an array of images url, contained in the post.
	 *
	 * @param bool $skip_videos Skip videos.
	 *
	 * @return void
	 */
	private function find_images( $skip_videos = false ) {
		$post_content = do_shortcode( (string) $this->post->post_content );

		$this->images = $this->find_img_tags( $post_content );

		if ( empty( $this->images ) && ! $skip_videos ) {
			$this->images = $this->find_video_thumbnails( $post_content );
		}

		// @phpcs:disable
		Logger::instance()->debug( 'Found images: ' . print_r( $this->images, true ) );
		// @phpcs:enable
	}

	/**
	 * Find <img> tags in post content.
	 *
	 * @param string $content Post content.
	 *
	 * @return array
	 */
	private function find_img_tags( $content ) {
		$images = [];

		preg_match_all( '/<\s*img .*?src\s*=\s*[\"\']?([^\"\'> ]*).*?>/i', $content, $matches );

		if ( count( $matches[0] ) ) {
			foreach ( $matches[0] as $key => $image ) {
				$title = '';
				preg_match_all( '/<\s*img [^\>]*title\s*=\s*[\"\']?([^\"\'> ]*)/i', $image, $matches_title );

				if ( count( $matches_title[0] ) && isset( $matches_title[1][ $key ] ) ) {
					$title = $matches_title[1][ $key ];
				}

				$original_image_url = preg_replace( '/-\d+x\d+\./', '.', $matches[1][ $key ] );
				$original_image_id  = attachment_url_to_postid( $original_image_url );

				if ( 0 === $original_image_id ) {
					// retry with -scaled suffix
					$original_image_url = preg_replace( '/-\d+x\d+\./', '-scaled.', $matches[1][ $key ] );
					$original_image_id  = attachment_url_to_postid( $original_image_url );
				}

				$images[] = [
					'tag'   => $image,
					'id'    => $original_image_id,
					'url'   => $matches[1][ $key ],
					'title' => $title,
				];
			}
		}

		return $images;
	}

	/**
	 * Find video thumbnails in post content (YouTube, Vimeo).
	 *
	 * @param string $content Post content.
	 *
	 * @return array
	 */
	private function find_video_thumbnails( $content ) {
		$images = [];

		$youtube = $this->find_youtube_thumbnails( $content );
		$vimeo   = $this->find_vimeo_thumbnails( $content );

		$images       = array_merge( $youtube, $vimeo );
		$this->videos = $images;

		return $images;
	}

	/**
	 * Find YouTube video thumbnails in post content.
	 *
	 * @param string $content Post content.
	 *
	 * @return array
	 */
	private function find_youtube_thumbnails( $content ) {
		$images = [];

		preg_match_all( "/(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>\s]+)/", $content, $matches );

		if ( ! count( $matches[0] ) ) {
			return $images;
		}

		$seen = [];
		foreach ( $matches[1] as $video_id ) {
			if ( isset( $seen[ $video_id ] ) ) {
				continue;
			}
			$seen[ $video_id ] = true;

			$thumbnail_url = 'https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg';

			$images[] = [
				'tag'   => '<img src="' . esc_url( $thumbnail_url ) . '">',
				'url'   => $thumbnail_url,
				'title' => (string) $this->post->post_title,
			];
		}

		return $images;
	}

	/**
	 * Find Vimeo video thumbnails in post content.
	 *
	 * @param string $content Post content.
	 *
	 * @return array
	 */
	private function find_vimeo_thumbnails( $content ) {
		$images = [];

		preg_match_all( '/(?:https?:\/\/)?(?:www\.)?(?:player\.)?vimeo\.com\/(?:video\/)?(\d+)/i', $content, $matches );

		if ( ! count( $matches[0] ) ) {
			return $images;
		}

		$seen = [];
		foreach ( $matches[1] as $video_id ) {
			if ( isset( $seen[ $video_id ] ) ) {
				continue;
			}
			$seen[ $video_id ] = true;

			$thumbnail_url = $this->get_vimeo_thumbnail( $video_id );
			if ( ! $thumbnail_url ) {
				continue;
			}

			$images[] = [
				'tag'   => '<img src="' . esc_url( $thumbnail_url ) . '">',
				'url'   => $thumbnail_url,
				'title' => (string) $this->post->post_title,
			];
		}

		return $images;
	}

	/**
	 * Get thumbnail URL for a Vimeo video via oEmbed API.
	 *
	 * @param string $video_id Vimeo video ID.
	 *
	 * @return string|false Thumbnail URL or false on failure.
	 */
	private function get_vimeo_thumbnail( $video_id ) {
		$oembed_url = 'https://vimeo.com/api/oembed.json?url=' . rawurlencode( 'https://vimeo.com/' . $video_id );

		$response = wp_remote_get(
			$oembed_url,
			[
				'timeout' => 5,
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['thumbnail_url'] ) ) {
			return false;
		}

		return $body['thumbnail_url'];
	}

	/**
	 * Get the post object
	 *
	 * @return WP_Post
	 */
	public function get_post() {
		return $this->post;
	}

	/**
	 * Get an array of images url, contained in the post
	 *
	 * @return array
	 */
	public function get_images() {
		return $this->images;
	}

	/**
	 * Get videos found in the post.
	 *
	 * @return array
	 */
	public function get_videos() {
		return $this->videos;
	}

	/**
	 * Get count of images url, contained in the post
	 *
	 * @return int
	 */
	public function count_images() {
		return count( $this->images );
	}

	/**
	 * Get count of videos found in the post.
	 *
	 * @return int
	 */
	public function count_videos() {
		return count( $this->videos );
	}

	/**
	 * If images is founded in post
	 *
	 * @return bool
	 */
	public function is_images() {
		return (bool) $this->count_images();
	}

	/**
	 * Check if videos are found in the post.
	 *
	 * @return bool
	 */
	public function is_videos() {
		return (bool) $this->count_videos();
	}

	/**
	 * Generate a unique filepath for the image.
	 *
	 * @param string  $image Image path.
	 * @param string  $suffix Slug suffix.
	 * @param WP_Post $post Post object.
	 *
	 * @return string
	 */
	public function unique_filepath( $image, $suffix = 'image', $post = null ) {
		if ( ! $post ) {
			$post = $this->get_post();
		}

		$uploads   = wp_upload_dir( current_time( 'mysql' ) );
		$extension = pathinfo( $image, PATHINFO_EXTENSION );

		$slug      = "wapt_{$suffix}";
		$file_path = wp_unique_filename( $uploads['path'], "{$slug}_{$post->post_type}_{$post->ID}.{$extension}" );
		$file_path = "{$uploads['path']}/{$file_path}";

		return $file_path;
	}

	/**
	 * Download a file from a URL to a local path.
	 *
	 * @param string $url URL.
	 * @param string $path_to Path to download.
	 *
	 * @return bool
	 */
	public function download( $url, $path_to ) {
		$response = wp_remote_get( $url );
		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			$body = wp_remote_retrieve_body( $response );

			global $wp_filesystem;
			if ( ! $wp_filesystem ) {
				if ( ! function_exists( 'WP_Filesystem' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}
				WP_Filesystem();
			}

			$downloaded = $path_to ? $wp_filesystem->put_contents( $path_to, $body ) : false;
		}

		return isset( $downloaded ) ? (bool) $downloaded : false;
	}
}
