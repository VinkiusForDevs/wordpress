<?php
/**
 * Connector Traffic screen (connectors).
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Per-connector traffic: requests and volume.
 */
final class ScreenConnectorTraffic extends ScreenSection {

	/**
	 * Screen slug.
	 *
	 * @return string
	 */
	protected function slug() {
		return 'connector-traffic';
	}

	/**
	 * Governance section key.
	 *
	 * @return string
	 */
	protected function section_key() {
		return 'connectors';
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	protected function page_title() {
		return __( 'Connector Traffic', 'vinkius-connector' );
	}
}
