<?php

// Exit if accessed directly
use WBCR\Factory_479\Premium\Interfaces\License;
use WBCR\Factory_479\Premium\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WAPT_License_Page is used as template to display form to active premium functionality.
 *
 * @since 2.0.7
 */
class WAPT_License extends WBCR\Factory_Templates_132\Pages\License {

	/**
	 * The id of the page in the admin menu.
	 *
	 * Mainly used to navigate between pages.
	 *
	 * @since 1.0.0
	 * @see   FactoryPages479_AdminPage
	 *
	 * @var string
	 */
	public $id = "apt_license";

	/**
	 * @var string
	 */
	public $custom_target = 'admin.php';

	/**
	 * @var int
	 */
	public $page_menu_position = 10;

	/**
	 * {@inheritdoc}
	 */
	public $type = 'page';

	/**
	 * {@inheritdoc}
	 */
	public $page_menu_dashicon = 'dashicons-admin-network';

	/**
	 * {@inheritdoc}
	 */
	public $available_for_multisite = true;

	/**
	 * {@inheritdoc}
	 */
	public $show_menu_tab = false;


	/**
	 * {@inheritdoc}
	 *
	 * @param WAPT_Plugin $plugin
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		$this->id                          = 'apt_license';
		$this->menu_title                  = '<span style="color:#f18500">' . __( 'License', 'apt' ) . '</span>';
		$this->page_title                  = __( 'License', 'apt' );
		$this->plan_name                   = __( 'Auto Featured Image Pro', 'ai-image-generator' );
		$this->page_menu_short_description = __( 'Activate pro version', 'apt' );

		$this->menu_target = $plugin->getPrefix() . 'generate-' . $plugin->getPluginName();

		if ( defined( 'WAIG_PLUGIN_ACTIVE' ) ) {
			$this->page_parent_page = 'none';
		}

		parent::__construct( $plugin );

		/**
		 * Adds a new plugin card to license components page
		 *
		 * @author Alexander Kovalev <alex.kovalevv@gmail.com>
		 * @since  1.6.2
		 */
		add_filter( 'wbcr/apt/license/list_components', function ( $components ) {
			$title = 'Free';
			//$icon  = 'apt-icon-256x256--lock.png';
			$icon = 'apt-icon-256x256--default.gif';

			if ( $this->is_premium ) {
				$title = 'Premium';
				$icon  = 'apt-icon-256x256--default.gif';
			}

			$components[] = [
				'name'            => 'apt',
				'title'           => sprintf( __( 'Auto Featured Image [%s]', 'apt' ), $title ),
				'type'            => 'internal',
				'build'           => $this->is_premium ? 'premium' : 'free',
				'key'             => $this->get_hidden_license_key(),
				'plan'            => $this->get_plan(),
				'expiration_days' => $this->get_expiration_days(),
				'quota'           => $this->is_premium ? $this->premium_license->get_count_active_sites() . ' ' . __( 'of', 'clearfy' ) . ' ' . $this->premium_license->get_sites_quota() : null,
				'subscription'    => $this->is_premium && $this->premium_has_subscription ? sprintf( __( 'Automatic renewal, every %s', '' ), esc_attr( $this->get_billing_cycle_readable() ) ) : null,
				'url'             => 'https://cm-wp.com/apt/',
				'icon'            => WAPT_PLUGIN_URL . '/admin/assets/img/' . $icon,
				'description'     => __( 'Auto Featured Image is installed but not yet activated. Go to the license activation page to complete the add-on installation.', 'clearfy' ),
				'license_page_id' => 'apt_license'
			];

			return $components;
		} );
	}

}
