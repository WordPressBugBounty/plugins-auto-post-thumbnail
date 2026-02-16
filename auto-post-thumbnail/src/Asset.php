<?php
/**
 * Asset class for handling registering, enqueuing and localizing singular assets.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail;

/**
 * Asset class.
 *
 * @example
 * ```php
 * ( new Asset( 'dashboard', true ) )
 * ->load_associated_css()
 * ->localize( 'APTData', $this->get_localization_data() )
 * ->enqueue();
 * ```
 *
 * @package AutoPostThumbnail
 */
class Asset {
	/**
	 * Path to the build directory.
	 *
	 * @var string
	 */
	const BUILD_PATH = 'assets/build/';

	/**
	 * Handle of the asset.
	 *
	 * @var string
	 */
	public $handle = '';

	/**
	 * Filename of the asset.
	 *
	 * @var string
	 */
	public $filename = '';

	/**
	 * Source of the asset.
	 *
	 * @var string
	 */
	public $src = '';

	/**
	 * Dependencies of the asset.
	 *
	 * @var array
	 */
	public $deps = [];

	/**
	 * Version of the asset.
	 *
	 * @var string
	 */
	public $version = WAPT_PLUGIN_VERSION;

	/**
	 * Whether to enqueue the asset in the footer.
	 *
	 * @var bool
	 */
	public $in_footer = true;

	/**
	 * Whether the runtime has been loaded.
	 *
	 * @var bool
	 */
	public static $runtime_loaded = false;

	/**
	 * Constructor.
	 *
	 * @param string $handle The handle of the asset.
	 * @param bool   $in_footer Whether to enqueue the asset in the footer.
	 */
	public function __construct( string $handle, bool $in_footer = true ) {
		$this->filename  = $handle;
		$this->handle    = 'wapt-' . $handle;
		$this->in_footer = $in_footer;

		$this->inject_asset_file();
		$this->register_script();
		$this->maybe_load_runtime();
	}

	/**
	 * Register the script.
	 *
	 * @return void
	 */
	private function register_script() {
		wp_register_script( $this->handle, $this->src, $this->deps, $this->version, $this->in_footer );
	}

	/**
	 * Inject the asset file.
	 *
	 * @return void
	 */
	private function inject_asset_file() {
		$file_path = WAPT_PLUGIN_DIR . '/' . self::BUILD_PATH . $this->filename . '.asset.php';

		if ( ! is_file( $file_path ) ) {
			return;
		}

		$asset_file    = include $file_path;
		$this->deps    = $asset_file['dependencies'];
		$this->version = $asset_file['version'];
		$this->src     = WAPT_PLUGIN_URL . '/' . self::BUILD_PATH . $this->filename . '.js';
	}

	/**
	 * Maybe load the runtime.
	 *
	 * @return void
	 */
	private function maybe_load_runtime() {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		if ( self::$runtime_loaded ) {
			return;
		}
		self::$runtime_loaded = true;

		( new Asset( 'runtime', true ) )->enqueue();
	}

	/**
	 * Set the dependencies of the asset.
	 *
	 * @param array $deps The dependencies of the asset.
	 * @return $this
	 */
	public function set_deps( array $deps ) {
		$this->deps = array_merge( $this->deps, $deps );
		return $this;
	}

	/**
	 * Localize the asset.
	 *
	 * @param string $name The name of the variable.
	 * @param array  $data The data to localize.
	 * @return $this
	 */
	public function localize( string $name, array $data ) {
		wp_localize_script( $this->handle, $name, apply_filters( 'wpapt_localize_script', $data, $this->handle ) );

		return $this;
	}

	/**
	 * Enqueue the asset.
	 *
	 * @return $this
	 */
	public function enqueue() {
		wp_enqueue_script( $this->handle );

		return $this;
	}

	/**
	 * Load the associated CSS file.
	 *
	 * @return $this
	 */
	public function load_associated_css() {
		$css_file = $this->filename . '.css';
		if ( ! file_exists( WAPT_PLUGIN_DIR . '/' . self::BUILD_PATH . $css_file ) ) {
			return $this;
		}

		wp_enqueue_style( $this->handle, WAPT_PLUGIN_URL . '/' . self::BUILD_PATH . $css_file, [], $this->version );

		return $this;
	}

	/**
	 * Add inline style to the asset.
	 *
	 * @param string $style The style to add.
	 * @return $this
	 */
	public function add_inline_style( string $style ) {
		wp_add_inline_style( $this->handle, $style );

		return $this;
	}
}
