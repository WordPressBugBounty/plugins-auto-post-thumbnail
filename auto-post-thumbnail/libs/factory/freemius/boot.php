<?php
/**
 * Load Freemius module.
 *
 * @author        Webcraftic <wordpress.webraftic@gmail.com>, Alex Kovalev <alex.kovalevv@gmail.com>
 * @since         1.0.0
 * @package       core
 * @copyright (c) 2018, Webcraftic Ltd
 *
 */

// Exit if accessed directly
if( !defined('ABSPATH') ) {
	exit;
}

if( defined('FACTORY_FREEMIUS_169_LOADED') ) {
	return;
}

define('FACTORY_FREEMIUS_169_VERSION', '1.6.9');

define('FACTORY_FREEMIUS_169_LOADED', true);
define('FACTORY_FREEMIUS_169_DIR', dirname(__FILE__));
define('FACTORY_FREEMIUS_169_URL', plugins_url('', __FILE__));

#comp merge
// Freemius
require_once(FACTORY_FREEMIUS_169_DIR . '/includes/entities/class-freemius-entity.php');
require_once(FACTORY_FREEMIUS_169_DIR . '/includes/entities/class-freemius-scope.php');
require_once(FACTORY_FREEMIUS_169_DIR . '/includes/entities/class-freemius-user.php');
require_once(FACTORY_FREEMIUS_169_DIR . '/includes/entities/class-freemius-site.php');
require_once(FACTORY_FREEMIUS_169_DIR . '/includes/entities/class-freemius-license.php');
require_once(FACTORY_FREEMIUS_169_DIR . '/includes/licensing/class-freemius-provider.php');
require_once(FACTORY_FREEMIUS_169_DIR . '/includes/updates/class-freemius-repository.php');

if( !class_exists('WBCR\Factory_Freemius_169\Sdk\Freemius_Api_WordPress') ) {
	require_once FACTORY_FREEMIUS_169_DIR . '/includes/sdk/FreemiusWordPress.php';
}

require_once(FACTORY_FREEMIUS_169_DIR . '/includes/class-freemius-api.php');

/**
 * @param Wbcr_Factory479_Plugin $plugin
 */
add_action('wbcr_factory_freemius_169_plugin_created', function ($plugin) {
	# Устанавливаем класс провайдера лицензий для премиум менеджера
	$plugin->set_license_provider('freemius', 'WBCR\Factory_Freemius_169\Premium\Provider');
	# Устанавливаем класс репозитория обновлений для менеджера обновлений
	$plugin->set_update_repository('freemius', 'WBCR\Factory_Freemius_169\Updates\Freemius_Repository');
});
#endcomp
