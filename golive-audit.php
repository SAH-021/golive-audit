<?php
/**
 * Plugin Name:       Golive Audit
 * Description:       Launch-readiness and client-handoff audit for WordPress sites: environment-aware checks, a readiness report and WP-CLI support.
 * Version:           1.0.0
 * Plugin URI:        https://github.com/SAH-021/golive-audit
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            SAH
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       golive-audit
 * Domain Path:       /languages
 *
 * @package GoliveAudit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GOLIVE_AUDIT_VERSION', '1.0.0' );
define( 'GOLIVE_AUDIT_FILE', __FILE__ );
define( 'GOLIVE_AUDIT_DIR', plugin_dir_path( __FILE__ ) );

// Minimal PSR-4 autoloader so the plugin runs without `composer install`.
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'GoliveAudit\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = GOLIVE_AUDIT_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

\GoliveAudit\Plugin::instance()->boot();
