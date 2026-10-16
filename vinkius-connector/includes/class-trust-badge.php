<?php
/**
 * Trust badge.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Vinkius-verified badge on the site.
 */
final class TrustBadge {

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'vinkius_badge', array( $this, 'badge_shortcode' ) );
	}

	/**
	 * [vinkius_badge] — verified-agent-API badge.
	 *
	 * @return string
	 */
	public function badge_shortcode() {
		if ( ! Options::is_configured() ) {
			return '';
		}

		$scan_id = (string) Options::get( 'badge_scan_id', '' );

		if ( '' === $scan_id ) {
			return '';
		}

		$src   = ( new VinkiusApi() )->badge_url( $scan_id );
		$label = __( 'Agent API is Vinkius-verified', 'vinkius-connector' );

		return sprintf(
			'<span class="vc-badge" title="%1$s"><img src="%2$s" alt="%1$s" width="180" height="40" style="border:0;" loading="lazy" /></span>',
			esc_attr( $label ),
			esc_url( $src )
		);
	}
}
