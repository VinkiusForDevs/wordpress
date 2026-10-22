<?php
/**
 * Agent Passport screen (agent registry: identity chain, permissions, usage).
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Credential registry with least-privilege and lifecycle analysis.
 */
final class ScreenAgentPassport extends ScreenSection {

	/**
	 * Screen slug.
	 *
	 * @return string
	 */
	protected function slug() {
		return 'agent-passport';
	}

	/**
	 * Governance section key.
	 *
	 * @return string
	 */
	protected function section_key() {
		return 'agents';
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	protected function page_title() {
		return __( 'Agent Passport', 'vinkius-connector' );
	}
}
