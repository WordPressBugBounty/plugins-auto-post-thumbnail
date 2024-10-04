<?php

/**
 * Class of plugin page. Must be registered in file admin/class-prefix-page.php
 *
 * @author        Artem Prihodko <webtemyk@yandex.ru>
 * @copyright (c) 2021, Webcraftic
 * @see           ImpressiveLite
 *
 * @version       1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WAPT_Page extends WBCR\Factory_Templates_132\Pages\PageBase {

	/**
	 * Show on the page a search form for search options of plugin?
	 *
	 * @since  2.2.0 - Added
	 * @var bool - true show, false hide
	 */
	public $show_search_options_form = false;

	/**
	 * Render and return content of the template.
	 * /admin/views/tab-{$template_name}.php
	 *
	 * @return mixed Content of the page
	 */
	public function render( $name = '', $data = [] ) {

		ob_start();
		if ( strpos( $name, DIRECTORY_SEPARATOR ) !== false && ( is_file( $name ) || is_file( $name . '.php' ) ) ) {
			if ( is_file( $name ) ) {
				$path = $name;
			} else {
				$path = $name . '.php';
			}
		} else {
			$path = WAPT_PLUGIN_DIR . "/admin/views/tab-{$name}.php";
		}
		if ( ! is_file( $path ) ) {
			return '';
		}
		include $path;
		$content = ob_get_contents();
		ob_end_clean();

		return $content;
	}

	/**
	 * @param string $position
	 *
	 * @return mixed|void
	 */
	protected function getPageWidgets($position = 'bottom')
	{
		$widgets = parent::getPageWidgets($position);

		unset($widgets['info_widget']);
		unset($widgets['business_suggetion']);
		unset($widgets['subscribe']);

		return $widgets;
	}
}


