<?php
/**
 * Activation: local consent store + defaults.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the site-side consent table (the only local store) and seeds options.
 */
final class Activator {

	/**
	 * Run on activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		Options::ensure_defaults();
	}

	/**
	 * Create tables (idempotent).
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$prefix   = $wpdb->prefix;
		$charset  = $wpdb->get_charset_collate();
		$consents = $prefix . 'vc_consents';

		dbdelta(
			"CREATE TABLE $consents (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				external_id varchar(255) NOT NULL,
				wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				scopes text NULL,
				purpose varchar(64) NOT NULL DEFAULT 'agent_action',
				ip_hash char(64) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				revoked_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY external_id (external_id),
				KEY wp_user_id (wp_user_id)
			) $charset;"
		);
	}
}
