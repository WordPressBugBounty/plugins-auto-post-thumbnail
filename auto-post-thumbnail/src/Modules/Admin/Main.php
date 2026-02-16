<?php
/**
 * Adds the admin menu page and handles all assets enqueueing.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Modules\Admin;

use AutoPostThumbnail\Asset;
use AutoPostThumbnail\Modules\Base;
use AutoPostThumbnail\Constants\Options_Schema;
use AutoPostThumbnail\Loader;
use AutoPostThumbnail\Modules\Api;
use AutoPostThumbnail\Settings;
use AutoPostThumbnail\Utils\Helpers;

/**
 * Admin module.
 */
class Main extends Base {
	const PAGE_SLUG = 'auto-featured-image';
	/**
	 * Run hooks.
	 *
	 * @return void
	 */
	public function run_hooks() {
		add_filter( 'plugin_action_links_' . WAPT_PLUGIN_BASENAME, [ $this, 'plugin_action_link' ] );
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices', [ $this, 'check_perms' ] );

		// About us page.
		add_filter(
			Loader::get_product_key() . '_about_us_metadata',
			[ $this, 'get_about_us_metadata' ]
		);

		// Logger data.
		add_filter( Loader::get_product_key() . '_logger_data', [ $this, 'get_logger_data' ] );

		// Survey.
		add_filter( 'themeisle-sdk/survey/' . WAPT_PRODUCT_SLUG, [ $this, 'get_survey_metadata' ], 10, 2 );
	}

	/**
	 * Add menu page.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		$hook = add_menu_page(
			'Auto Featured Image',
			'Auto Featured Image',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ],
			'',
			5
		);

		Loader::do_internal_page( $hook, 'dashboard' );
	}


	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render_page() {
		add_thickbox();

		echo '<div id="apt-dashboard"></div>';
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook The hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		wp_add_inline_style( 'wp-admin', $this->get_icon_styles() );

		$full_hook = sprintf( 'toplevel_page_%s', self::PAGE_SLUG );

		if ( $hook !== $full_hook ) {
			return;
		}

		// Required for MediaUpload component.
		wp_enqueue_media();

		( new Asset( 'dashboard', true ) )
			->load_associated_css()
			->localize( 'APTData', $this->get_localization_data() )
			->enqueue();
	}

	/**
	 * Get admin menu icon styles.
	 *
	 * @return string CSS styles.
	 */
	private function get_icon_styles() {
		$icon_url = WAPT_PLUGIN_URL . '/assets/img/apt.png';

		return sprintf(
			'
      a.toplevel_page_%1$s .wp-menu-image {
        background: url("%2$s") no-repeat 10px -30px !important;
      }

      a.toplevel_page_%1$s .wp-menu-image:before {
        content: "" !important;
      }

      a.toplevel_page_%1$s:hover .wp-menu-image,
      a.toplevel_page_%1$s.wp-has-current-submenu .wp-menu-image,
      a.toplevel_page_%1$s.current .wp-menu-image {
        background-position: 10px 2px !important;
      }

      a.toplevel_page_%1$s.current:after {
       border-right-color: #fff !important;
      }
    ',
			self::PAGE_SLUG,
			$icon_url
		);
	}

	/**
	 * Get localization data.
	 *
	 * @return array<string, mixed>
	 */
	private function get_localization_data() {
		$upload_dir = wp_upload_dir();
		return [
			'pluginVersion'    => WAPT_PLUGIN_VERSION,
			'pluginUrl'        => WAPT_PLUGIN_URL,
			'uploadUrl'        => $upload_dir['baseurl'],
			'apiNamespace'     => Api::NAMESPACE,
			'apiNonce'         => wp_create_nonce( 'wp_rest' ),
			'apiRoot'          => rest_url(),
			'postTypes'        => $this->get_post_types(),
			'postStatuses'     => $this->get_post_statuses(),
			'categories'       => $this->get_categories(),
			'options'          => Settings::instance()->get_all_public(),
			'generationLog'    => Settings::instance()->get( Options_Schema::GENERATION_LOG ),
			'imagesStatus'     => $this->get_images_status(),
			'upsellURL'        => Helpers::get_upsell_url( 'replace:campaign' ),
			'storeURL'         => esc_url( tsdk_translate_link( 'https://store.themeisle.com' ) ),
			'optimizationData' => $this->get_optimization_section_content(),
			'allowTracking'    => get_option( Loader::get_product_key() . '_logger_flag', 'no' ) === 'yes',
		];
	}

	/**
	 * Check whether the required directory structure is available so that the plugin can create thumbnails if needed.
	 * If not, don't allow plugin activation.
	 *
	 * @return void
	 */
	public function check_perms() {
		$uploads = wp_upload_dir( current_time( 'mysql' ) );

		if ( $uploads['error'] ) {
			echo '<div class="updated"><p>';
			echo esc_html( $uploads['error'] );

			if ( function_exists( 'deactivate_plugins' ) ) {
				deactivate_plugins( 'auto-post-thumbnail/auto-post-thumbnail.php' );
				echo '<br /> ' . esc_html__( 'This plugin has been automatically deactivated.', 'auto-post-thumbnail' );
			}

			echo '</p></div>';
		}
	}

	/**
	 * Adds the plugin action link on Plugins table
	 *
	 * @param array $links links array.
	 *
	 * @return array
	 */
	public function plugin_action_link( $links ) {
		if ( ! is_array( $links ) ) {
			return $links;
		}

		$link = add_query_arg(
			'page',
			self::PAGE_SLUG,
			admin_url( 'admin.php' )
		);

		$link  = '<a href="' . esc_url( $link ) . '">';
		$link .= esc_html__( 'Generate', 'auto-post-thumbnail' );
		$link .= '</a>';

		array_unshift( $links, $link );

		return $links;
	}

	/**
	 * Get optimization section content.
	 *
	 * @return array
	 */
	private function get_optimization_section_content() {
		$cache_key = 'apt_robin_data';
		$data      = get_transient( $cache_key );

		if ( empty( $data ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

			$data = plugins_api( 'plugin_information', [ 'slug' => 'robin-image-optimizer' ] );

			if ( ! is_wp_error( $data ) ) {
				set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );
			}
		}

		if ( ! is_object( $data ) ) {
			$data                  = (object) [];
			$data->num_ratings     = 124;
			$data->rating          = 88;
			$data->active_installs = 100000;
		}

		$rating          = (int) $data->rating * 5 / 100;
		$rating          = number_format( $rating, 1 );
		$active_installs = number_format( $data->active_installs );

		$installed = file_exists( WP_PLUGIN_DIR . '/robin-image-optimizer/robin-image-optimizer.php' );

		return [
			'show'           => ( ! defined( 'WRIO_PLUGIN_VERSION' ) && ! defined( 'OPTML_VERSION' ) ),
			'logo'           => WAPT_PLUGIN_URL . '/assets/img/robin-logo.jpg',
			// translators: %1$s: rating, %2$d: number of reviews.
			'ratingByline'   => sprintf( __( '%1$s out of 5 stars (%2$d reviews)', 'auto-post-thumbnail' ), $rating, $data->num_ratings ),
			// translators: %s: number of active installations.
			'activeInstalls' => sprintf( __( '%s+ Active installations', 'auto-post-thumbnail' ), $active_installs ),
			'cta'            => $installed ? __( 'Activate Robin Image Optimizer', 'auto-post-thumbnail' ) : __( 'Install Robin Image Optimizer', 'auto-post-thumbnail' ),
			'thickboxURL'    => add_query_arg(
				[
					'tab'       => 'plugin-information',
					'plugin'    => 'robin-image-optimizer',
					'TB_iframe' => 'true',
					'width'     => '800',
					'height'    => '600',
				],
				network_admin_url( 'plugin-install.php' )
			),
		];
	}

	/**
	 * Get about us metadata.
	 *
	 * @return array
	 */
	public function get_about_us_metadata() {
		return [
			'location'         => self::PAGE_SLUG,
			'logo'             => WAPT_PLUGIN_URL . '/assets/img/logo.jpg',
			'has_upgrade_menu' => ! defined( 'WAPT_PRO_PATH' ),
			'upgrade_link'     => Helpers::get_upsell_url( 'aboutfilter' ),
			'upgrade_text'     => __( 'Upgrade to Pro', 'auto-post-thumbnail' ),
		];
	}

	/**
	 * Get the survey metadata.
	 *
	 * @param array  $data The data for survey in Formbrick format.
	 * @param string $page_slug The slug of the page.
	 *
	 * @return array The survey metadata.
	 */
	public function get_survey_metadata( $data, $page_slug ) {
		if ( ! in_array( $page_slug, [ 'dashboard', 'add-from-apt' ], true ) ) {
			return $data;
		}

		$current_time        = time();
		$install_date        = get_option( Loader::get_product_key() . '_install', $current_time );
		$install_days_number = intval( ( $current_time - $install_date ) / DAY_IN_SECONDS );

		$plugin_version = WAPT_PLUGIN_VERSION;

		return [
			'environmentId' => 'cmioopcdk4wvkad016h3ezh3g',
			'debug'         => true,
			'attributes'    => [
				'plugin_version'      => $plugin_version,
				'install_days_number' => $install_days_number,
				'license_status'      => apply_filters( 'product_apt_license_status', 'invalid' ),
				'plan'                => apply_filters( 'product_apt_license_plan', 0 ),
			],
		];
	}

	/**
	 * Get logger data.
	 *
	 * @param array $data Incoming data.
	 *
	 * @return array
	 */
	public function get_logger_data( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$settings = Settings::instance()->get_tracked();

		if ( empty( $settings ) ) {
			return $data;
		}

		$data['settings'] = $settings;

		return $data;
	}
}
