<?php
/**
 * Main Plugin Class
 *
 * @package CustomVideoLibrary
 */

namespace CustomVideoLibrary;

/**
 * Main plugin class for Custom Video Library.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Plugin classes to initialize.
	 *
	 * @var array
	 */
	private $classes = array();

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	private function __construct() {
		// Initialize plugin components.
		$this->init_classes();
		$this->init_hooks();
	}

	/**
	 * Initialize plugin classes.
	 *
	 * @return void
	 */
	private function init_classes() {
		$this->classes = array(
			'database'      => new Database(),
			'post_types'    => new Post_Types(),
			'capabilities'  => new Capabilities(),
			'audio_proxy'   => new Audio_Proxy(),
			'frontend'      => new Frontend(),
			'admin'         => new Admin\Admin(),
		);
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Load text domain for translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Enqueue assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Initialize plugin classes.
		foreach ( $this->classes as $class ) {
			if ( method_exists( $class, 'init' ) ) {
				$class->init();
			}
		}
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			CVL_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( CVL_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		$frontend_css_path = CVL_PLUGIN_DIR . 'assets/css/frontend.css';
		$frontend_js_path  = CVL_PLUGIN_DIR . 'assets/js/frontend.js';
		$frontend_css_ver  = file_exists( $frontend_css_path ) ? (string) filemtime( $frontend_css_path ) : constant( 'CVL_VERSION' );
		$frontend_js_ver   = file_exists( $frontend_js_path ) ? (string) filemtime( $frontend_js_path ) : constant( 'CVL_VERSION' );

		// Enqueue frontend CSS.
		wp_enqueue_style(
			'cvl-frontend',
			CVL_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			$frontend_css_ver
		);

		// Enqueue frontend JS.
		wp_enqueue_script(
			'cvl-frontend',
			CVL_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'wp-api-fetch' ),
			$frontend_js_ver,
			true
		);

		// Localize frontend JS with AJAX data.
		wp_localize_script(
			'cvl-frontend',
			'cvlData',
			array(
				'nonce'   => wp_create_nonce( 'cvl_nonce' ),
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url(),
			)
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @return void
	 */
	public function enqueue_admin_assets() {
		$admin_css_path = CVL_PLUGIN_DIR . 'assets/css/admin.css';
		$admin_js_path  = CVL_PLUGIN_DIR . 'assets/js/admin.js';
		$admin_css_ver  = file_exists( $admin_css_path ) ? (string) filemtime( $admin_css_path ) : constant( 'CVL_VERSION' );
		$admin_js_ver   = file_exists( $admin_js_path ) ? (string) filemtime( $admin_js_path ) : constant( 'CVL_VERSION' );

		// Enqueue admin CSS.
		wp_enqueue_style(
			'cvl-admin',
			CVL_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			$admin_css_ver
		);

		// Enqueue admin JS.
		wp_enqueue_script(
			'cvl-admin',
			CVL_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-i18n' ),
			$admin_js_ver,
			true
		);
	}

	/**
	 * Plugin activation hook.
	 *
	 * @return void
	 */
	public function activate() {
		// Create custom tables.
		if ( isset( $this->classes['database'] ) && method_exists( $this->classes['database'], 'create_tables' ) ) {
			$this->classes['database']->create_tables();
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @return void
	 */
	public function deactivate() {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Get a plugin class instance.
	 *
	 * @param string $class_name The class name to retrieve.
	 *
	 * @return mixed|null The class instance or null if not found.
	 */
	public function get_class( $class_name ) {
		return isset( $this->classes[ $class_name ] ) ? $this->classes[ $class_name ] : null;
	}
}
