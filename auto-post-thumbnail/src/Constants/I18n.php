<?php
/**
 * I18n constants for the plugin.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Constants;

/**
 * I18n constants for the plugin.
 */
class I18n {
	const AUTO_FEATURED_IMAGES      = 'auto-featured-images';
	const ADD_FROM_APT              = 'add-from-apt';
	const MEDIA_MODAL_TITLE         = 'media-modal-title';
	const INVALID_IMAGE_URL         = 'invalid-image-url';
	const FAILED_TO_DOWNLOAD_IMAGE  = 'failed-to-download-image';
	const DOWNLOADED_IMAGE_ERROR    = 'downloaded-image-error';
	const FAILED_TO_SAVE_IMAGE      = 'failed-to-save-image';
	const DOWNLOADED_FILE_NOT_VALID = 'downloaded-file-not-valid';
	const PLEASE_REFRESH            = 'please-refresh-and-try-again';
	const LICENSE_ACTIVATED         = 'license-activated';
	const LICENSE_DEACTIVATED       = 'license-deactivated';
	const LICENSE_NOT_ACTIVATED     = 'license-not-activated';
	const NO_FILE_UPLOADED          = 'no-file-uploaded';
	const ONLY_TTF_ALLOWED          = 'only-ttf-allowed';
	const INVALID_TRUETYPE_FONT     = 'invalid-truetype-font';
	const FONT_NOT_FOUND            = 'font-not-found';
	const INVALID_FONT_PATH         = 'invalid-font-path';
	const FAILED_TO_DELETE_FONT     = 'failed-to-delete-font';
	const COULD_NOT_DOWNLOAD_FONT   = 'could-not-download-font';
	const FAILED_TO_DOWNLOAD_FONT   = 'failed-to-download-font';

	/**
	 * Get the translations for the plugin.
	 *
	 * @return array<string, string>
	 */
	public static function strings() {
		return [
			self::AUTO_FEATURED_IMAGES      => __( 'Auto Featured Images', 'auto-post-thumbnail' ),
			self::ADD_FROM_APT              => __( 'Add from APT', 'auto-post-thumbnail' ),
			self::MEDIA_MODAL_TITLE         => __( 'Auto Featured Image', 'auto-post-thumbnail' ),
			self::INVALID_IMAGE_URL         => __( 'Invalid image URL', 'auto-post-thumbnail' ),
			self::FAILED_TO_DOWNLOAD_IMAGE  => __( 'Failed to download image', 'auto-post-thumbnail' ),
			self::DOWNLOADED_IMAGE_ERROR    => __( 'Downloaded image is empty', 'auto-post-thumbnail' ),
			self::FAILED_TO_SAVE_IMAGE      => __( 'Failed to save image to uploads directory', 'auto-post-thumbnail' ),
			self::DOWNLOADED_FILE_NOT_VALID => __( 'Downloaded file is not a valid image', 'auto-post-thumbnail' ),
			self::PLEASE_REFRESH            => __( 'Please refresh the page and try again', 'auto-post-thumbnail' ),
			self::LICENSE_ACTIVATED         => __( 'License activated', 'auto-post-thumbnail' ),
			self::LICENSE_DEACTIVATED       => __( 'License deactivated', 'auto-post-thumbnail' ),
			self::LICENSE_NOT_ACTIVATED     => __( 'License not activated', 'auto-post-thumbnail' ),
			self::NO_FILE_UPLOADED          => __( 'No file uploaded', 'auto-post-thumbnail' ),
			self::ONLY_TTF_ALLOWED          => __( 'Only TrueType (.ttf) font files are allowed', 'auto-post-thumbnail' ),
			self::INVALID_TRUETYPE_FONT     => __( 'The uploaded file is not a valid TrueType font', 'auto-post-thumbnail' ),
			self::FONT_NOT_FOUND            => __( 'The font file was not found', 'auto-post-thumbnail' ),
			self::INVALID_FONT_PATH         => __( 'Could not delete font. Invalid font path.', 'auto-post-thumbnail' ),
			self::FAILED_TO_DELETE_FONT     => __( 'Failed to delete font', 'auto-post-thumbnail' ),
		];
	}
}
