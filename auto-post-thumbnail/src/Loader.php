<?php
/**
 * Loader class for the plugin.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail;

use AutoPostThumbnail\Modules\Base;
use AutoPostThumbnail\Modules\Api;
use AutoPostThumbnail\Modules\Options;
use AutoPostThumbnail\Modules\Admin\Main as Admin_Main;
use AutoPostThumbnail\Modules\Admin\Posts_List_Table as Admin_Posts_List_Table;
use AutoPostThumbnail\Modules\Auto_Generate;

/**
 * Loader class for the plugin.
 */
class Loader {
	/**
	 * Modules to load.
	 *
	 * @var array {
	 *   @type string $module_name => class-string<Base> $module_class
	 * }
	 */
	private $modules = [
		'api'                    => Api::class,
		'options'                => Options::class,
		'admin'                  => Admin_Main::class,
		'admin_posts_list_table' => Admin_Posts_List_Table::class,
		'auto_generate'          => Auto_Generate::class,
	];

	/**
	 * Module instances.
	 *
	 * @var array
	 */
	private $instances = [];

	/**
	 * Run the loader.
	 *
	 * @return void
	 */
	final public function run() {
		$modules = $this->get_modules();

		foreach ( $modules as $module_name => $module_class ) {
			$this->instances[ $module_name ] = new $module_class( $this );

			if ( ! $this->instances[ $module_name ] instanceof Base ) {
				continue;
			}

			$this->instances[ $module_name ]->run_hooks();
		}

		$this->run_hooks();
	}

	/**
	 * Run loader hooks.
	 *
	 * @return void
	 */
	private function run_hooks() {
		add_filter( 'themeisle_sdk_products', [ __CLASS__, 'register_sdk' ] );

		add_action( 'admin_init', [ $this, 'maybe_deactivate_free' ] );

		$activator = new IO();

		register_activation_hook( WAPT_PLUGIN_FILE, [ $activator, 'on_activate' ] );
		register_deactivation_hook( WAPT_PLUGIN_FILE, [ $activator, 'on_deactivate' ] );
	}

	/**
	 * Register product into SDK.
	 *
	 * @param array $products All products.
	 *
	 * @return array Registered product.
	 */
	public static function register_sdk( $products ) {
		$products[] = WAPT_PLUGIN_FILE;

		return $products;
	}

	/**
	 * Get the modules to load.
	 *
	 * @return array The modules to load.
	 */
	private function get_modules() {
		return apply_filters( 'wpapt_modules', $this->modules );
	}

	/**
	 * If both free & pro are active, we attempt to deactivate the free version.
	 *
	 * @return void
	 */
	public function maybe_deactivate_free() {
		if ( ! defined( 'WAPT_PRO_PATH' ) || ! defined( 'WAPT_FREE_PATH' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		deactivate_plugins( WAPT_FREE_PATH );

		add_action(
			'admin_notices',
			function () {
				echo wp_kses_post(
					sprintf(
						'<div class="notice notice-warning"><p><strong>%s</strong><br>%s</p></div>',
						sprintf(
						/* translators: %s: Name of deactivated plugin */
							__( '%s plugin deactivated.', 'auto-post-thumbnail' ),
							'Auto Featured Image (Free)'
						),
						__( 'The Premium version of Auto Featured Image does not require the Free version to be installed.', 'auto-post-thumbnail' )
					)
				);
			}
		);
	}


	/**
	 * Get the product key.
	 *
	 * @return string The product key.
	 */
	public static function get_product_key() {
		return str_replace( '-', '_', WAPT_PRODUCT_SLUG );
	}

	/**
	 * Do the internal page.
	 *
	 * @param string $hook The hook.
	 * @param string $page The page.
	 *
	 * @return void
	 */
	public static function do_internal_page( $hook, $page ) {
		add_action(
			'load-' . $hook,
			function () use ( $page ) {
				do_action( 'themeisle_internal_page', WAPT_PRODUCT_SLUG, $page );
			}
		);
	}
}
