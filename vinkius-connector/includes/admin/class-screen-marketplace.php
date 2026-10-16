<?php
/**
 * Marketplace screen.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

use VinkiusConnector\Links;

defined( 'ABSPATH' ) || exit;

/**
 * Publishing, earnings and catalog live in the Vinkius cloud. This screen
 * only deep-links there.
 */
class ScreenMarketplace extends ScreenBase {

	protected function slug() {
		return 'marketplace';
	}

	protected function prepare() {
		return array(
			'state' => \VinkiusConnector\Auth\AuthStore::status()['state'],
			'links' => Links::all(),
		);
	}
}
