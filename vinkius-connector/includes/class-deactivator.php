<?php
/**
 * Deactivation.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Clears scheduled hooks on deactivation. Data is removed only on uninstall.
 */
final class Deactivator {

	/**
	 * Run on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'vc_daily_digest' );
	}
}
