<?php
/**
 * Logger class.
 *
 * @package AutoPostThumbnail
 */

namespace AutoPostThumbnail;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds ability to log application message into .log file.
 *
 * It has 4 core levels:
 * - info: generic log message
 * - warning: log possible exceptions states or unusual
 * - error: log error-related logs
 * - debug: log stack traces, big outputs, etc.
 *
 * Each level has its constant. See LEVEL_* prefix.
 *
 * Additionally it is possible to configure flush interval and file name.
 *
 * Usage examples:
 *
 * ```php
 * // Info message level
 * Logger::instance()->info('Some generic message, good to know');
 *
 * // Warning message level
 * Logger::instance()->warning('Something does not work or unusual');
 *
 * // Error message level
 * Logger::instance()->error('Something critical happened');
 *
 * // Debug message level
 * Logger::instance()->debug('Some message used for debug purposes. Could be stack trace.');
 * ```
 *
 * @version 1.0
 */
class Logger {

	const LEVEL_INFO    = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';
	const LEVEL_DEBUG   = 'debug';

	/**
	 * Singleton instance.
	 *
	 * @var Logger|null
	 */
	private static $instance = null;

	/**
	 * @var string Plugin slug.
	 */
	public $plugin_slug;

	/**
	 * Backwards-compatible plugin property.
	 * Provides getPluginVersion() for factory Log_Export compatibility.
	 *
	 * @var object
	 */
	public $plugin;

	/**
	 * @var null|string Request hash.
	 */
	public $hash = null;

	/**
	 * @var null|string Directory where log file would be saved.
	 */
	public $dir = null;

	/**
	 * @var string File log name where logs would be flushed.
	 */
	public $file = 'app.log';

	/**
	 * @var int Flushing interval. When $logs would reach this number of items they would be flushed to log file.
	 */
	public $flush_interval = 1000;

	/**
	 * @var int Rotate size in bytes. Default: 512 KB.
	 */
	public $rotate_size = 512000;

	/**
	 * @var int Number of rotated files. When size of $rotate_size matches current file, current file would be rotated.
	 * For example, there are 10 files, current file became size of $rotate_size, third file would be deleted, two first
	 * shifted and empty one created.
	 */
	public $rotate_limit = 10;

	/**
	 * @var array List of logs to be dumped.
	 */
	private $logs = [];

	/**
	 * Get singleton instance.
	 *
	 * @param array $settings Optional settings for initialization.
	 *
	 * @return Logger
	 */
	public static function instance( $settings = [] ) {
		if ( self::$instance === null ) {
			self::$instance = new self( $settings );
		}

		return self::$instance;
	}

	/**
	 * Logger constructor.
	 *
	 * @param array $settings
	 */
	public function __construct( $settings = [] ) {
		$this->plugin_slug = defined( 'WAPT_PLUGIN_SLUG' ) ? WAPT_PLUGIN_SLUG : 'auto-post-thumbnail';
		$this->plugin      = $this->create_plugin_shim();
		$this->init( $settings );
	}

	/**
	 * Create a backwards-compatible plugin shim object.
	 * Provides methods expected by factory Log_Export class.
	 *
	 * @return object
	 */
	private function create_plugin_shim() {
		$plugin_slug = $this->plugin_slug;

		return new class( $plugin_slug ) {
			public $plugin_slug;

			public function __construct( $plugin_slug ) {
				$this->plugin_slug = $plugin_slug;
			}

			public function getPluginVersion() {
				return defined( 'WAPT_PLUGIN_VERSION' ) ? WAPT_PLUGIN_VERSION : '1.0.0';
			}
		};
	}

	/**
	 * Initiate object.
	 *
	 * @param array $settings
	 */
	public function init( $settings ) {
		$this->hash = substr( uniqid(), -6, 6 );

		if ( is_array( $settings ) && ! empty( $settings ) ) {
			foreach ( $settings as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}
		}

		add_action( 'shutdown', [ $this, 'shutdown_flush' ], 9999, 0 );
	}

	/**
	 * Get directory to save collected logs.
	 *
	 * In addition to that, it manages log rotation so that it does not become too big.
	 *
	 * @return string|false false on failure, string on success.
	 */
	public function get_dir() {
		$base_dir = $this->get_base_dir();
		if ( $base_dir === null ) {
			return false;
		}

		$root_file = $base_dir . $this->file;

		// Check whether file exists and it exceeds rotate size, then should rotate it copy
		if ( file_exists( $root_file ) && filesize( $root_file ) >= $this->rotate_size ) {
			$this->rotate_logs( $base_dir, $root_file );
		}

		return $root_file;
	}

	/**
	 * Rotate log files when size limit is reached.
	 *
	 * @param string $base_dir Base directory path.
	 * @param string $root_file Root log file path.
	 */
	private function rotate_logs( $base_dir, $root_file ) {
		$name_split = explode( '.', $this->file );

		if ( ! empty( $name_split ) ) {
			$name_split[0] = trim( $name_split[0] );

			for ( $i = $this->rotate_limit; $i >= 0; $i-- ) {
				$cur_name = $name_split[0] . $i;
				$cur_path = $base_dir . $cur_name . '.log';

				$next_path = $i !== 0 ? $base_dir . $name_split[0] . ( $i - 1 ) . '.log' : $root_file;

				if ( file_exists( $next_path ) ) {
					@copy( $next_path, $cur_path );
				}
			}
		}

		// Need to empty root file as it was supposed to be copied to next rotation
		@file_put_contents( $root_file, '' );
	}

	/**
	 * Get base directory, location of logs.
	 *
	 * @return null|string NULL in case of failure, string on success.
	 */
	public function get_base_dir() {
		if ( empty( $this->dir ) ) {
			$base_path = wp_normalize_path( trailingslashit( WP_CONTENT_DIR ) . 'logs/' );
		} else {
			$base_path = wp_normalize_path( trailingslashit( $this->dir ) );
		}

		if ( ! file_exists( $base_path ) ) {
			@mkdir( $base_path, 0755, true );
		}

		$this->protect_directory( $base_path );

		return $base_path;
	}

	/**
	 * Protect the log directory from direct access.
	 *
	 * @param string $path Directory path to protect.
	 */
	private function protect_directory( $path ) {
		// Create .htaccess file to protect log files
		$htaccess_path = $path . '.htaccess';

		if ( ! file_exists( $htaccess_path ) ) {
			$htaccess_content = 'deny from all';
			@file_put_contents( $htaccess_path, $htaccess_content );
		}

		// Create index.html file in case .htaccess is not supported as a fallback
		$index_path = $path . 'index.html';

		if ( ! file_exists( $index_path ) ) {
			@file_put_contents( $index_path, '' );
		}
	}

	/**
	 * Get all available log paths.
	 *
	 * @return array|bool
	 */
	public function get_all() {
		$base_dir = $this->get_base_dir();

		if ( $base_dir === null ) {
			return false;
		}

		$glob_path = $base_dir . '*.log';

		return glob( $glob_path );
	}

	/**
	 * Get total log size in bytes.
	 *
	 * @return int
	 * @see size_format() for formatting.
	 */
	public function get_total_size() {
		$logs  = $this->get_all();
		$bytes = 0;

		if ( empty( $logs ) ) {
			return $bytes;
		}

		foreach ( $logs as $log ) {
			$bytes += @filesize( $log );
		}

		return $bytes;
	}

	/**
	 * Empty all log files and delete rotated ones.
	 *
	 * @return bool
	 */
	public function clean_up() {
		$base_dir = $this->get_base_dir();

		if ( $base_dir === null ) {
			return false;
		}

		$glob_path = $base_dir . '*.log';

		$files = glob( $glob_path );

		if ( $files === false ) {
			return false;
		}

		if ( empty( $files ) ) {
			return true;
		}

		$unlinked_count = 0;

		foreach ( $files as $file ) {
			if ( @unlink( $file ) ) {
				++$unlinked_count;
			}
		}

		return count( $files ) === $unlinked_count;
	}

	/**
	 * Flush all messages.
	 *
	 * @return bool
	 */
	public function flush() {
		$messages = $this->logs;

		$this->logs = [];

		if ( empty( $messages ) ) {
			return false;
		}

		$file_content = PHP_EOL . implode( PHP_EOL, $messages );
		$is_put       = @file_put_contents( $this->get_dir(), $file_content, FILE_APPEND );

		return $is_put !== false;
	}

	/**
	 * Flush all messages on shutdown.
	 */
	public function shutdown_flush() {
		$end_line = '-------------------------------';
		if ( ! empty( $this->logs ) ) {
			$this->logs[] = $end_line;
		}

		$this->flush();
	}

	/**
	 * Get formatted log message.
	 *
	 * @param string $level Log level.
	 * @param string $message Log message.
	 *
	 * @return string
	 */
	public function get_format( $level, $message ) {
		// Example: 17-03-2021 13:44:23 [site.com][info] Message
		$template = '%s [%s][%s] %s';
		$date     = date_i18n( 'd-m-Y H:i:s' );

		$ip = isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : '';

		return sprintf( $template, $date, $ip, $level, $message );
	}

	/**
	 * Get latest file content.
	 *
	 * @return string|null
	 */
	public function get_content() {
		if ( ! file_exists( $this->get_dir() ) ) {
			return null;
		}

		return htmlspecialchars( @file_get_contents( $this->get_dir() ) );
	}

	/**
	 * Get Export object.
	 *
	 * @return Logger_Export
	 */
	public function get_export() {
		return new Logger_Export( $this, "{$this->plugin_slug}_log_export-{datetime}.zip" );
	}

	/**
	 * Add new log message.
	 *
	 * @param string $level Log level.
	 * @param string $message Message to log.
	 *
	 * @return bool
	 */
	public function add( $level, $message ) {
		$this->logs[] = $this->get_format( $level, $message );

		if ( count( $this->logs ) >= $this->flush_interval ) {
			$this->flush();
		}

		return true;
	}

	/**
	 * Add info level log.
	 *
	 * @param string $message Message to log.
	 */
	public function info( $message ) {
		$this->add( self::LEVEL_INFO, $message );
	}

	/**
	 * Add error level log.
	 *
	 * @param string $message Message to log.
	 */
	public function error( $message ) {
		$this->add( self::LEVEL_ERROR, $message );
	}

	/**
	 * Add debug level log.
	 *
	 * @param string $message Message to log.
	 */
	public function debug( $message ) {
		$this->add( self::LEVEL_DEBUG, $message );
	}

	/**
	 * Add warning level log.
	 *
	 * @param string $message Message to log.
	 */
	public function warning( $message ) {
		$this->add( self::LEVEL_WARNING, $message );
	}

	/**
	 * Writes information to log about memory.
	 *
	 * @since 1.3.6
	 */
	public function memory_usage() {
		$memory_avail = ini_get( 'memory_limit' );
		$memory_used  = number_format( memory_get_usage( true ) / ( 1024 * 1024 ), 2 );
		$memory_peak  = number_format( memory_get_peak_usage( true ) / ( 1024 * 1024 ), 2 );

		$this->info( sprintf( 'Memory: %s (avail) / %sM (used) / %sM (peak)', $memory_avail, $memory_used, $memory_peak ) );
	}

	/**
	 * Prettify log content.
	 *
	 * Helps to convert log file content into easy-to-read HTML.
	 *
	 * @return string|null
	 */
	public function prettify() {
		$content = $this->get_content();

		if ( ! empty( $content ) ) {
			$replace = "<div class='wapt-log-row wapt_logger_level_$4'><strong>$1 $2</strong> [$3]<div class='wapt_logger_level'>$4</div>$5</div>";

			$content = str_replace( [ "\n", "\r<br>" ], [ '<br>', "\r\n" ], $content );
			$content = preg_replace( '/^(\S+)\s*(\S+)\s*\[(.+)\]\s*\[(.+)\]\s*(.*)$/m', $replace, $content );
		}

		return $content;
	}

	/**
	 * Get log entries as a structured array.
	 *
	 * @return array Array of log entries with keys: timestamp, url, type, text.
	 */
	public function as_array() {
		$content = $this->get_content();

		if ( empty( $content ) ) {
			return [];
		}

		// Decode HTML entities since get_content() uses htmlspecialchars
		$content = html_entity_decode( $content );

		$lines         = explode( "\n", $content );
		$entries       = [];
		$current_entry = null;
		$current_text  = [];

		// Pattern: 17-03-2021 13:44:23 [site.com][info] Message
		$pattern = '/^(\d{2}-\d{2}-\d{4}\s+\d{2}:\d{2}:\d{2})\s+\[([^\]]*)\]\[([^\]]+)\]\s*(.*)$/';

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Skip empty lines and separator
			if ( empty( $line ) || $line === '-------------------------------' ) {
				continue;
			}

			if ( preg_match( $pattern, $line, $matches ) ) {
				// Save previous entry if exists
				if ( $current_entry !== null ) {
					$current_entry['text'] = implode( "\n", $current_text );
					$entries[]             = $current_entry;
				}

				// Start new entry
				$current_entry = [
					'timestamp' => $matches[1],
					'url'       => $matches[2],
					'type'      => $matches[3],
				];
				$current_text  = [ $matches[4] ];
			} elseif ( $current_entry !== null ) {
				// This is a continuation line
				$current_text[] = $line;
			}
		}

		// Don't forget the last entry
		if ( $current_entry !== null ) {
			$current_entry['text'] = implode( '', $current_text );
			$entries[]             = $current_entry;
		}

		// reverse the entries
		$entries = array_reverse( $entries );

		return $entries;
	}
}

/**
 * Prepares export files, ZIPs them and allows to download the package.
 *
 * Usage example:
 *
 * ```php
 * $export = Logger::instance()->get_export();
 * $prepared = $export->prepare();
 *
 * if ($prepared) {
 *     // start streaming ZIP archive to be downloaded
 *     $export->download();
 * }
 * ```
 */
class Logger_Export {

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Default archive name on download. {datetime} will be replaced with current m-d-Y.
	 *
	 * @var string
	 */
	private $archive_name = 'plugin_log_export-{datetime}.zip';

	/**
	 * Archive save path.
	 *
	 * @var string|null
	 */
	private $archive_save_path;

	/**
	 * Logger_Export constructor.
	 *
	 * @param Logger      $logger Logger instance.
	 * @param string|null $archive_name Archive name.
	 */
	public function __construct( Logger $logger, $archive_name = null ) {
		$this->logger = $logger;
		if ( null !== $archive_name ) {
			$this->archive_name = $archive_name;
		}
	}

	/**
	 * Prepare export.
	 *
	 * @return bool
	 */
	public function prepare() {
		if ( ! class_exists( '\ZipArchive' ) ) {
			$this->logger->error( 'App does not have \ZipArchive class available. It is not possible to prepare export' );

			return false;
		}

		$zip = new \ZipArchive();

		$log_base_dir = $this->logger->get_base_dir();

		if ( false === $log_base_dir || null === $log_base_dir ) {
			$this->logger->error( sprintf( 'Failed to get log path %s', $log_base_dir ) );

			return false;
		}

		$uploads = wp_get_upload_dir();

		if ( isset( $uploads['error'] ) && false !== $uploads['error'] ) {
			$this->logger->error( 'Unable to get save path of ZIP archive from wp_get_upload_dir()' );

			return false;
		}

		$save_base_path   = isset( $uploads['basedir'] ) ? $uploads['basedir'] : null;
		$zip_archive_name = 'wapt_export.zip';
		$zip_save_path    = $save_base_path . DIRECTORY_SEPARATOR . $zip_archive_name;

		if ( ! $zip->open( $zip_save_path, \ZipArchive::CREATE ) ) {
			$this->logger->error( sprintf( 'Failed to create ZIP archive in path %s. Skipping export...', $zip_save_path ) );

			return false;
		}

		// Add all logs to ZIP archive
		$glob_path = $log_base_dir . '*.log';
		$log_files = glob( $glob_path );

		if ( ! empty( $log_files ) ) {
			foreach ( $log_files as $file ) {
				if ( ! $zip->addFile( $file, wp_basename( $file ) ) ) {
					$this->logger->error( sprintf( 'Failed to add %s to %s archive. Skipping it.', $file, $zip_save_path ) );

					return false;
				}
			}
		}

		$system_info = $this->prepare_system_info();

		if ( ! empty( $system_info ) ) {
			$system_info_file_name = 'system-info.txt';
			$system_info_path      = $save_base_path . DIRECTORY_SEPARATOR . $system_info_file_name;
			if ( false !== @file_put_contents( $system_info_path, $system_info ) ) { // phpcs:ignore
				if ( ! $zip->addFile( $system_info_path, $system_info_file_name ) ) {
					$this->logger->error( sprintf( 'Failed to add %s to %s archive. Skipping it.', $system_info_file_name, $system_info_path ) );
				}
			} else {
				$this->logger->error( sprintf( 'Failed to save %s in %s', $system_info_file_name, $zip_save_path ) );
			}
		}

		if ( ! $zip->close() ) {
			$this->logger->error( sprintf( 'Failed to close ZIP archive %s for unknown reason. ZipArchive::close() failed.', $zip_save_path ) );
		}

		if ( isset( $system_info_path ) ) {
			// Clean-up as this is just temp file
			@unlink( $system_info_path ); // phpcs:ignore
		}

		$this->archive_save_path = $zip_save_path;

		return true;
	}

	/**
	 * Prepare generic system information, such as WordPress, PHP version, active plugins, loaded extensions, etc.
	 *
	 * @return string
	 */
	public function prepare_system_info() {
		$space = PHP_EOL . PHP_EOL;
		$nl    = PHP_EOL;

		$plugin_version = defined( 'WAPT_PLUGIN_VERSION' ) ? WAPT_PLUGIN_VERSION : 'unknown';
		$report         = 'Plugin version: ' . $plugin_version . $nl;

		global $wp_version;

		$report .= 'WordPress Version: ' . $wp_version . $nl;
		$report .= 'PHP Version: ' . PHP_VERSION . $nl;
		$report .= 'Locale: ' . get_locale() . $nl;
		$report .= 'HTTP Accept: ' . ( isset( $_SERVER['HTTP_ACCEPT'] ) ? $_SERVER['HTTP_ACCEPT'] : '*empty*' ) . $nl;
		$report .= 'HTTP User Agent: ' . ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '*empty*' ) . $nl;
		$report .= 'Server software: ' . ( isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '*empty*' ) . $nl;

		$report .= $space;

		$active_plugins = get_option( 'active_plugins', null );

		if ( null !== $active_plugins ) {
			$prepared_plugins = [];

			$all_plugins = get_plugins();

			foreach ( $active_plugins as $active_plugin ) {
				if ( isset( $all_plugins[ $active_plugin ] ) ) {
					$advanced_info      = $all_plugins[ $active_plugin ];
					$name               = isset( $advanced_info['Name'] ) ? $advanced_info['Name'] : '';
					$version            = isset( $advanced_info['Version'] ) ? $advanced_info['Version'] : '';
					$prepared_plugins[] = sprintf( '%s (%s)', $name, $version );
				}
			}

			$report .= 'Active plugins:' . PHP_EOL;
			$report .= implode( PHP_EOL, $prepared_plugins );
		}

		if ( function_exists( 'get_loaded_extensions' ) ) {
			$report .= PHP_EOL . PHP_EOL;
			$report .= 'Active extensions: ' . $nl;
			$report .= implode( ', ', get_loaded_extensions() );
		}

		$report .= $space;

		$report .= 'Generated at: ' . gmdate( 'c' );

		return $report;
	}

	/**
	 * Download saved ZIP archive.
	 *
	 * It sets download headers, which streams content of the ZIP archive.
	 *
	 * Additionally it cleans-up by deleting the archive if `$should_clean_up` set to true.
	 *
	 * @param bool $should_clean_up Allows to delete temp ZIP archive if required.
	 *
	 * @return bool
	 */
	public function download( $should_clean_up = true ) {
		$zip_save_path = $this->archive_save_path;

		if ( empty( $zip_save_path ) ) {
			return false;
		}

		$zip_content = @file_get_contents( $zip_save_path ); // phpcs:ignore

		if ( false === $zip_content ) {
			$this->logger->error( sprintf( 'Failed to get ZIP %s content as file_get_contents() returned false', $zip_save_path ) );

			return false;
		}

		if ( $should_clean_up ) {
			@unlink( $zip_save_path ); // phpcs:ignore
		}

		$archive_name = str_replace( '{datetime}', gmdate( 'c' ), $this->archive_name );

		// Set-up headers to download export file
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename=' . $archive_name );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Connection: Keep-Alive' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . strlen( $zip_content ) );

		echo $zip_content; // phpcs:ignore
		exit();
	}

	/**
	 * Get temporary stored archive path.
	 *
	 * @return string|null
	 */
	public function get_temp_archive_path() {
		return $this->archive_save_path;
	}

	/**
	 * Delete temporary stored archive path.
	 *
	 * @return bool
	 */
	public function delete_temp_archive() {
		return @unlink( $this->get_temp_archive_path() ); // phpcs:ignore
	}
}
