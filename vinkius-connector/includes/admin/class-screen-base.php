<?php
/**
 * Screen base: page chrome, status pill, kill-switch banner, Free CTA.
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

use VinkiusConnector\Options;
use VinkiusConnector\Template;

defined( 'ABSPATH' ) || exit;

/**
 * Every admin screen renders inside the same chrome so the "prepared"
 * states (unconfigured / not connected / halted / free plan) are
 * consistent across the panel.
 */
abstract class ScreenBase {

	/**
	 * Slug of this screen (menu suffix + template name).
	 *
	 * @return string
	 */
	abstract protected function slug();

	/**
	 * Screen-specific template variables.
	 *
	 * @return array
	 */
	abstract protected function prepare();

	/**
	 * Render the full admin page (menu callback).
	 *
	 * @return void
	 */
	public static function render_page() {
		$instance = new static();
		$slug     = $instance->slug();
		$vars     = $instance->prepare();

		$status = \VinkiusConnector\Auth\AuthStore::status();
		$bundle = get_transient( \VinkiusConnector\Auth\DeviceFlow::BUNDLE_KEY );
		$bundle = is_array( $bundle ) ? $bundle : array();

		$chrome = array(
			'slug'             => $slug,
			'vars'             => $vars,
			'status'           => $status['state'],
			'halt'             => Options::is_halted(),
			'frontend'         => VC_FRONTEND_BASE_URL,
			'device_pending'   => ! empty( $bundle['device_code'] ),
			'device_user_code' => isset( $bundle['user_code'] ) ? $bundle['user_code'] : '',
			'device_verify_url'=> isset( $bundle['verification_url'] ) ? $bundle['verification_url'] : rtrim( VC_FRONTEND_BASE_URL, '/' ) . '/device',
		);

		Template::render( 'layout', $chrome );
	}
}
