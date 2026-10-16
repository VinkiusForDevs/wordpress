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

		$governance = new Governance();
		$panel      = $governance->panel();

		if ( ! $panel->is_ok() ) {
			return;
		}

		$data = $panel->data();
		$kpis = $data['kpis'];
		$name = get_bloginfo( 'name' );

		$lines = array(
			sprintf(
				/* translators: 1: executions, 2: agents, 3: blocked, 4: connectors */
				__( 'Executions today: %1$d · Active agents: %2$d · Blocked events: %3$d · Connectors in use: %4$d', 'vinkius-connector' ),
				$kpis['executions_today'],
				$kpis['active_agents'],
				$kpis['blocked_events'],
				$kpis['active_connectors']
			),
		);

		$incidents = $governance->incidents();

		if ( $incidents->is_ok() && ! empty( $incidents->data()['briefing'] ) ) {
			$lines[] = (string) $incidents->data()['briefing'];
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
