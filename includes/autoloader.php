<?php
/**
 * Autoloader for Custom Video Library Plugin
 *
 * @package CustomVideoLibrary
 */

// Prevent direct access to the file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PSR-4 Autoloader for CustomVideoLibrary namespace.
 *
 * @param string $class The fully-qualified class name.
 *
 * @return void
 */
spl_autoload_register( function( $class ) {
	// Check if the class is in the CustomVideoLibrary namespace.
	$prefix = 'CustomVideoLibrary\\';

	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}

	// Remove the prefix from the class name.
	$relative_class = substr( $class, strlen( $prefix ) );

	// Map namespace/class to WordPress-style file naming.
	$parts      = explode( '\\', $relative_class );
	$class_name = array_pop( $parts );

	$subdir = '';
	if ( ! empty( $parts ) ) {
		$subdir = implode(
			'/',
			array_map(
				static function( $segment ) {
					return strtolower( str_replace( '_', '-', $segment ) );
				},
				$parts
			)
		) . '/';
	}

	$file = CVL_PLUGIN_DIR . 'includes/' . $subdir . 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

	// Check if the file exists and require it.
	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

// Load helper functions.
require_once CVL_PLUGIN_DIR . 'includes/helpers.php';
