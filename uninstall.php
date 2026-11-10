<?php
/**
 * Runs when the plugin is deleted from the Plugins screen: removes everything it stored.
 *
 * @package GoliveAudit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$golive_audit_options = array(
	'golive_audit_waivers',
	'golive_audit_checklist',
	'golive_audit_settings',
);

if ( is_multisite() ) {
	// Options are per site, so clean every site in the network.
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $golive_audit_site_id ) {
		switch_to_blog( (int) $golive_audit_site_id );

		foreach ( $golive_audit_options as $golive_audit_option ) {
			delete_option( $golive_audit_option );
		}

		restore_current_blog();
	}
} else {
	foreach ( $golive_audit_options as $golive_audit_option ) {
		delete_option( $golive_audit_option );
	}
}

delete_transient( 'golive_audit_running' );
