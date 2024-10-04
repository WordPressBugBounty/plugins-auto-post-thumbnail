<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WAPT_PLUGIN_DIR . '/admin/class-page.php';

/**
 * The page Settings.
 *
 * @since 1.0.0
 */
class WAPT_About extends WAPT_Page {

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
	public $id = "about";

	/**
	 * @var string
	 */
	public $custom_target = 'admin.php';

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.5 - добавлен
	 * @var bool
	 */
	public $show_right_sidebar_in_options = false;

	/**
	 * Тип страницы
	 * options - предназначена для создании страниц с набором опций и настроек.
	 * page - произвольный контент, любой html код
	 *
	 * @var string
	 */
	public $type = 'page';

	/**
	 * @var int
	 */
	public $page_menu_position = 20;

	/**
	 * Menu icon (only if a page is placed as a main menu).
	 * For example: '~/assets/img/menu-icon.png'
	 * For example dashicons: '\f321'
     *
	 * @var string
	 */
	public $menu_icon = '';

	/**
	 * @var string
	 */
	public $page_menu_dashicon = 'dashicons-info-outline';

	/**
	 * @param WAPT_Plugin $plugin
	 */
	public function __construct( $plugin ) {
		$this->id            = 'wapt_about';
		$this->menu_target   = $plugin->getPrefix() . 'generate-' . $plugin->getPluginName();
		$this->page_title    = __( 'About', 'apt' );
		$this->page_menu_short_description = __( 'Features, Updates, Pro', 'apt' );

		parent::__construct( $plugin );

		$this->plugin = $plugin;
	}

	/**
	 * Show rendered template - $template_name
	 */
	public function showPageContent() {
		echo $this->render('about'); // phpcs:ignore
	}

}
