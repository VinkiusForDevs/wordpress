<?php
/**
 * Base class for the AI Governance section screens.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One wp-admin page per governance section. All sections share the
 * same template; the page renders its section data live from the API.
 */
abstract class ScreenSection extends ScreenBase {

	/**
	 * Governance section key (overview|clients|…).
	 *
	 * @return string
	 */
	abstract protected function section_key();

	/**
	 * Human page title.
	 *
	 * @return string
	 */
	abstract protected function page_title();

	/**
	 * Shared section template.
	 *
	 * @return string
	 */
	protected function template() {
		return 'screen-section';
	}

	/**
	 * Prepare view data.
	 *
	 * @return array
	 */
	protected function prepare() {
		return array(
			'section' => $this->section_key(),
			'title'   => $this->page_title(),
			'links'   => \VinkiusConnector\Links::all(),
		);
	}
}
