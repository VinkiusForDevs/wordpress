<?php
/**
 * Access Tokens screen (token-fleet).
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Per-connector token fleet: counts, requests, latency, status.
 */
final class ScreenAccessTokens extends ScreenSection {

	/**
	 * Screen slug.
	 *
	 * @return string
	 */
	protected function slug() {
		return 'access-tokens';
	}

	/**
	 * Governance section key.
	 *
	 * @return string
	 */
	protected function section_key() {
		return 'token-fleet';
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	protected function page_title() {
		return __( 'Access Tokens', 'vinkius-connector' );
	}
}
