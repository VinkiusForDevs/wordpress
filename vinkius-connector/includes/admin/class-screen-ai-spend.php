<?php
/**
 * AI Spend screen (cost).
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * FinOps: tokens, cost and savings by server.
 */
final class ScreenAiSpend extends ScreenSection {

	/**
	 * Screen slug.
	 *
	 * @return string
	 */
	protected function slug() {
		return 'ai-spend';
	}

	/**
	 * Governance section key.
	 *
	 * @return string
	 */
	protected function section_key() {
		return 'cost';
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	protected function page_title() {
		return __( 'AI Spend', 'vinkius-connector' );
	}
}
