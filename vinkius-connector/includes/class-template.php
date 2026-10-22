<?php
/**
 * Template renderer.
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Loads templates/ views with a strict variable allow-list, so screens can
 * never leak arbitrary globals into the render scope.
 */
final class Template {

	/**
	 * Render a template into the output buffer.
	 *
	 * Parameter names must not collide with any chrome/screen key, because
	 * templates are included in this function scope and extracted vars skip
	 * already-declared names.
	 *
	 * @param string $render_name Template slug (templates/{render_name}.php).
	 * @param array  $render_args Local variables.
	 * @return void
	 */
	public static function render( $render_name, array $render_args = array() ) {
		$file = VC_PLUGIN_DIR . 'templates/' . $render_name . '.php';

		if ( ! file_exists( $file ) ) {
			return;
		}

		// Templates receive exactly the declared vars, nothing global.
		extract( $render_args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract

		include $file;
	}
}
