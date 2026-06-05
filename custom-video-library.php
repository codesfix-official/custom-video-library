<?php
/**
 * Custom Video Library
 *
 * @package           CustomVideoLibrary
 * @author            Your Company
 * @copyright         2024 Your Company
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Custom Video Library
 * Description:       Video library with WooCommerce monetization (subscriptions, individual purchases)
 * Version:           1.0.0
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       custom-video-library
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce,woocommerce-subscriptions,woocommerce-memberships
 */

// Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'CVL_PLUGIN_FILE', __FILE__ );
define( 'CVL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CVL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CVL_VERSION', '1.0.0' );
define( 'CVL_TEXT_DOMAIN', 'custom-video-library' );

/**
 * The main plugin class loader.
 *
 * Loads the plugin's main class and initializes the plugin.
 */
function cvl_load_plugin() {
	// Check minimum requirements.
	if ( ! cvl_check_requirements() ) {
		return;
	}

	// Load the autoloader.
	require_once CVL_PLUGIN_DIR . 'includes/autoloader.php';

	// Load the main plugin class.
	CustomVideoLibrary\Plugin::get_instance();
}

/**
 * Check if all minimum requirements are met.
 *
 * @return bool True if all requirements are met, false otherwise.
 */
function cvl_check_requirements() {
	// Ensure is_plugin_active() is available on every page, not just in wp-admin.
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	// Check WordPress version.
	if ( version_compare( get_bloginfo( 'version' ), '6.0', '<' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: %s: Required WordPress version */
				esc_html__( 'Custom Video Library requires WordPress 6.0 or higher. You are running version %s.', 'custom-video-library' ),
				esc_html( get_bloginfo( 'version' ) )
			);
			echo '</p></div>';
		} );
		return false;
	}

	// Check PHP version.
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: %s: Required PHP version */
				esc_html__( 'Custom Video Library requires PHP 7.4 or higher. You are running version %s.', 'custom-video-library' ),
				esc_html( PHP_VERSION )
			);
			echo '</p></div>';
		} );
		return false;
	}

	// Check for required plugins.
	$required_plugins = array(
		'woocommerce/woocommerce.php',
		'woocommerce-subscriptions/woocommerce-subscriptions.php',
		'woocommerce-memberships/woocommerce-memberships.php',
	);

	$missing_plugins = array();
	foreach ( $required_plugins as $plugin ) {
		if ( ! is_plugin_active( $plugin ) ) {
			$missing_plugins[] = $plugin;
		}
	}

	if ( ! empty( $missing_plugins ) ) {
		add_action( 'admin_notices', function() use ( $missing_plugins ) {
			echo '<div class="notice notice-error"><p>';
			printf(
				/* translators: %s: Missing plugins */
				esc_html__( 'Custom Video Library requires the following plugins to be active: %s', 'custom-video-library' ),
				esc_html( implode( ', ', $missing_plugins ) )
			);
			echo '</p></div>';
		} );
		return false;
	}

	return true;
}

/**
 * Hook: On plugin activation.
 *
 * @return void
 */
function cvl_plugin_activation() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( file_exists( CVL_PLUGIN_DIR . 'includes/autoloader.php' ) ) {
		require_once CVL_PLUGIN_DIR . 'includes/autoloader.php';
	}

	if ( ! cvl_check_requirements() ) {
		deactivate_plugins( CVL_PLUGIN_FILE );
		exit;
	}

	// Call activation hooks from the plugin class.
	if ( class_exists( 'CustomVideoLibrary\Plugin' ) ) {
		CustomVideoLibrary\Plugin::get_instance()->activate();
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}

/**
 * Hook: On plugin deactivation.
 *
 * @return void
 */
function cvl_plugin_deactivation() {
	if ( file_exists( CVL_PLUGIN_DIR . 'includes/autoloader.php' ) ) {
		require_once CVL_PLUGIN_DIR . 'includes/autoloader.php';
	}

	// Call deactivation hooks from the plugin class.
	if ( class_exists( 'CustomVideoLibrary\Plugin' ) ) {
		CustomVideoLibrary\Plugin::get_instance()->deactivate();
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}

// Register activation and deactivation hooks.
register_activation_hook( CVL_PLUGIN_FILE, 'cvl_plugin_activation' );
register_deactivation_hook( CVL_PLUGIN_FILE, 'cvl_plugin_deactivation' );

// Load the plugin on 'plugins_loaded' hook.
add_action( 'plugins_loaded', 'cvl_load_plugin' );
