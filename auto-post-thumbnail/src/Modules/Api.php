<?php
/**
 * Adds the API routes.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail\Modules;

use AutoPostThumbnail\Modules\Base;
use AutoPostThumbnail\Routes\Base_Route;
use AutoPostThumbnail\Routes\Fonts;
use AutoPostThumbnail\Routes\Generate as GenerateRoute;
use AutoPostThumbnail\Routes\Log;
use AutoPostThumbnail\Routes\Settings;

/**
 * API module.
 *
 * @package AutoPostThumbnail
 */
class Api extends Base {
	const NAMESPACE = 'wpapt/v1';

	/**
	 * API constructor.
	 *
	 * @var Base_Route[] $routes
	 */
	private $routes = [];

	/**
	 * API constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->routes = apply_filters(
			'wpapt_api_routes',
			[
				'log'      => Log::class,
				'generate' => GenerateRoute::class,
				'settings' => Settings::class,
				'fonts'    => Fonts::class,
			]
		);
	}

	/**
	 * Run hooks for the API.
	 *
	 * @inheritDoc Base::run_hooks()
	 */
	public function run_hooks() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the routes for the API.
	 *
	 * @return void
	 */
	public function register_routes() {
		foreach ( $this->routes as $route_name => $route_class ) {
			$route = new $route_class();

			if ( ! $route instanceof Base_Route ) {
				continue;
			}

			$route->register_routes();
		}
	}
}
