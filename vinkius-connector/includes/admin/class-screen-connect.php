<?php
/**
 * AI Connections screen (capabilities catalog).
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Landing page: browse and search the Vinkius catalog of AI capabilities
 * and connectors. Connecting any of them happens in the Vinkius cloud.
 */
final class ScreenConnect extends ScreenBase {

	/**
	 * Screen slug.
	 *
	 * @return string
	 */
	protected function slug() {
		return 'connect';
	}

	/**
	 * Prepare view data.
	 *
	 * @return array
	 */
	protected function prepare() {
		return array(
			'links' => \VinkiusConnector\Links::all(),
		);
	}
}
