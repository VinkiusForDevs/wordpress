<?php
/**
 * Vinkius Connector uninstall.
 *
 * Removes all plugin data on uninstall. Data is only wiped here —
 * deactivation keeps everything intact.
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

// Abort unless WordPress itself is performing the uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'vc_audit_events',
	$wpdb->prefix . 'vc_agent_sessions',
	$wpdb->prefix . 'vc_consents',
	$wpdb->prefix . 'vc_connectors',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedSQL
}

delete_option( 'vc_settings' );
delete_option( 'vc_auth' );

delete_transient( 'vc_device_bundle' );
