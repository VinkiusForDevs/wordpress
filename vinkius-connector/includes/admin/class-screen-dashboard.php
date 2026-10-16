<?php
/**
 * Governance dashboard.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

use VinkiusConnector\Links;
use VinkiusConnector\Options;

defined( 'ABSPATH' ) || exit;

/**
 * The main panel: KPIs, live activity, emergency halt, and management
 * deep-links. Values are read live from the Vinkius API by the panel JS.
 */
class ScreenDashboard extends ScreenBase {

	protected function slug() {
		return 'dashboard';
	}

	protected function prepare() {
		$bundle = get_transient( \VinkiusConnector\Auth\DeviceFlow::BUNDLE_KEY );

		return array(
			'state'          => \VinkiusConnector\Auth\AuthStore::status()['state'],
			'links'          => Links::all(),
			'halted'         => Options::is_halted(),
			'device_pending' => is_array( $bundle ) && ! empty( $bundle['device_code'] ),
		);
	}
}
