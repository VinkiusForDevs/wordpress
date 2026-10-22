<?php
/**
 * Notifications.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Push notifications: daily digest built live from the Vinkius API,
 * delivered by email to the owner.
 */
final class Notifications {

	/**
	 * Register.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'vc_daily_digest', array( $this, 'send_digest' ) );
	}

	/**
	 * Build and send the daily digest (only when connected and enabled).
	 *
	 * @return void
	 */
	public function send_digest() {
		if ( ! Options::get( 'digest_enabled' ) || ! Options::is_configured() ) {
			return;
		}

		$to = (string) Options::get( 'digest_email' );

		if ( '' === $to ) {
			$to = get_option( 'admin_email' );
		}

		// The digest is only meaningful with a live Passport session — every
		// KPI below is a live API read, so skip the mail when disconnected.
		if ( 'connected' !== \VinkiusConnector\Auth\AuthStore::status()['state'] ) {
			return;
		}

		$governance = new Governance();
		$incidents  = $governance->incidents();

		if ( ! $incidents->is_ok() ) {
			return;
		}

		$data    = $incidents->data();
		$ekpi    = $data['errors_kpi'];
		$skpi    = $data['security_kpi'];
		$circuit = $data['circuit'];
		$name    = get_bloginfo( 'name' );

		/*
		 * incidents() still returns ok when individual upstreams degrade
		 * (each KPI just falls back to empty). Do not mail a digest full of
		 * misleading zeros — only send when at least one signal came back.
		 */
		if ( empty( $ekpi ) && empty( $skpi ) && empty( $circuit ) && empty( $data['briefing'] ) && empty( $data['errors_by_server'] ) ) {
			return;
		}

		$lines = array(
			sprintf(
				/* translators: 1: errors today, 2: DLP events today, 3: circuit breaker state */
				__( 'Errors today: %1$d · DLP events: %2$d · Circuit breaker: %3$s', 'vinkius-connector' ),
				isset( $ekpi['total_errors'] ) ? (int) $ekpi['total_errors'] : ( isset( $ekpi['errors'] ) ? (int) $ekpi['errors'] : 0 ),
				isset( $skpi['dlp_events'] ) ? (int) $skpi['dlp_events'] : 0,
				! empty( $circuit['is_tripped'] ) ? __( 'Tripped', 'vinkius-connector' ) : __( 'Open', 'vinkius-connector' )
			),
		);

		if ( ! empty( $data['briefing'] ) ) {
			$lines[] = (string) $data['briefing'];
		}

		$subject = sprintf( /* translators: %s: site name */ __( 'Vinkius AI Governance — daily digest for %s', 'vinkius-connector' ), $name );
		$body    = $name . "\n" . implode( "\n", $lines ) . "\n\n" . esc_url( admin_url( 'admin.php?page=vinkius-connector' ) );

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Ensure the digest schedule exists.
	 *
	 * @return void
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'vc_daily_digest' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'vc_daily_digest' );
		}
	}
}
