<?php
/**
 * Agents screen.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

use VinkiusConnector\Links;

defined( 'ABSPATH' ) || exit;

/**
 * Agent fleet, read live from the Vinkius API.
 */
class ScreenAgents extends ScreenBase {

	protected function slug() {
		return 'agents';
	}

	protected function prepare() {
		return array(
			'state' => \VinkiusConnector\Auth\AuthStore::status()['state'],
			'links' => Links::all(),
		);
	}
}
