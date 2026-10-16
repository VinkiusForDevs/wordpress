<?php
/**
 * Evidence screen.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

use VinkiusConnector\Links;

defined( 'ABSPATH' ) || exit;

/**
 * Per-connector execution evidence, read live from the API.
 */
class ScreenEvidence extends ScreenBase {

	protected function slug() {
		return 'evidence';
	}

	protected function prepare() {
		return array(
			'state' => \VinkiusConnector\Auth\AuthStore::status()['state'],
			'links' => Links::all(),
		);
	}
}
