<?php
/**
 * Incidents screen.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

use VinkiusConnector\Links;

defined( 'ABSPATH' ) || exit;

/**
 * Errors, DLP enforcement and circuit-breaker state, read live from the API.
 */
class ScreenIncidents extends ScreenBase {

	protected function slug() {
		return 'incidents';
	}

	protected function prepare() {
		return array(
			'state' => \VinkiusConnector\Auth\AuthStore::status()['state'],
			'links' => Links::all(),
		);
	}
}
