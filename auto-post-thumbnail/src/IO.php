<?php
/**
 * Plugin activation and deactivation handler.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail;

use AutoPostThumbnail\Constants\Options_Schema;

/**
 * Plugin activation and deactivation handler.
 */
class IO {
	/**
	 * Handle plugin activation.
	 *
	 * @return void
	 */
	public function on_activate() {
		do_action( hook_name: 'wapt/plugin_activated' );
	}

	/**
	 * Handle plugin deactivation.
	 *
	 * @return void
	 */
	public function on_deactivate() {
		$should_delete_options = Settings::instance()->get( Options_Schema::DELETE_SETTINGS_ON_DEACTIVATION );

		if ( $should_delete_options ) {
			Settings::instance()->delete_all();
		}

		do_action( hook_name: 'wapt/plugin_deactivated' );
	}
}
