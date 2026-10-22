<?php
/**
 * Request Failures screen (errors).
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Error breakdown by server: requests, errors, error rate.
 */
final class ScreenRequestFailures extends ScreenSection {

	/**
	 * Screen slug.
	 *
	 * @return string
	 */
	protected function slug() {
		return 'request-failures';
	}

	/**
	 * Governance section key.
	 *
	 * @return string
	 */
	protected function section_key() {
		return 'errors';
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	protected function page_title() {
		return __( 'Request Failures', 'vinkius-connector' );
	}
}
