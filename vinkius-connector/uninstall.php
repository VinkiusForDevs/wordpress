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

// Only the consent table is created by this plugin (see Activator); the
// audit/session/connector ledgers live in the Vinkius runtime, not in WP.
$tables = array(
	$wpdb->prefix . 'vc_consents',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedSQL
}

delete_option( 'vc_settings' );
delete_option( 'vc_auth' );

delete_transient( 'vc_device_bundle' );

// Rate-limit transients are sharded by (hashed) IP, so they can't be
// removed with delete_transient(); sweep them straight out of the options
// table. WordPress stores transients as _transient_<name> rows.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_vc_consent_rl_%'
		OR option_name LIKE '_transient_timeout_vc_consent_rl_%"
); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedSQL
