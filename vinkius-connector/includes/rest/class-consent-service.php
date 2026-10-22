<?php
/**
 * Visitor consent service (public POST /vinkius/v1/consent).
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Rest;

use VinkiusConnector\Models\ConsentRepo;
use VinkiusConnector\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Consent creation rules:
 *  - anonymous visitors are accepted (external_id supplied by the client);
 *  - logged-in visitors pin to their WP user id;
 *  - a honeypot field must be empty;
 *  - sites in halt state reject new consents;
 *  - each consent stores a stable revocation link.
 */
final class ConsentService {

	/**
	 * Create a consent record.
	 *
	 * @param \WP_REST_Request $request Body: {external_id?, scopes[], purpose?, hp?}.
	 * @param string           $ip      Remote IP (hashed at rest).
	 * @return \WP_REST_Response
	 */
	public static function create( $request, $ip ) {
		if ( Options::is_halted() ) {
			return new \WP_REST_Response(
				array( 'error' => 'site_halted', 'message' => __( 'Agent actions are halted on this site right now.', 'vinkius-connector' ) ),
				503
			);
		}

		/*
		 * Anti-abuse throttle: this route is public (__return_true), so a
		 * script could otherwise mint consent rows endlessly. Cap creations
		 * per source IP with a short window; the anon pseudonym is stable
		 * (localStorage), so legit visitors are never re-throttled.
		 */
		$ip = (string) $ip;

		if ( '' !== $ip ) {
			$rl_key = 'vc_consent_rl_' . substr( sha1( $ip ), 0, 16 );
			$rl_hits = (int) get_transient( $rl_key );

			if ( $rl_hits >= 25 ) {
				return new \WP_REST_Response(
					array( 'error' => 'rate_limited', 'message' => __( 'Too many requests. Please try again later.', 'vinkius-connector' ) ),
					429
				);
			}

			if ( 0 === $rl_hits && false === get_transient( $rl_key ) ) {
				set_transient( $rl_key, 1, 10 * MINUTE_IN_SECONDS );
			} else {
				set_transient( $rl_key, $rl_hits + 1, 10 * MINUTE_IN_SECONDS );
			}
		}

		$hp = (string) $request->get_param( 'hp' );

		if ( '' !== $hp ) {
			// Honeypot filled: bot detected. Reply success-with-no-op so bots learn nothing.
			return rest_ensure_response( array( 'ok' => false, 'row_id' => 0 ) );
		}

		$wp_user_id = get_current_user_id();
		$external_id = '';

		if ( $wp_user_id > 0 ) {
			$external_id = 'wp_user_' . (string) $wp_user_id;
		} else {
			$external_id = (string) $request->get_param( 'external_id' );

			/*
			 * The 'wp_user_' namespace is reserved for consents recorded while
			 * logged in. An anonymous client supplying it would forge another
			 * user's identity — drop it and mint a fresh anonymous id instead.
			 */
			if ( 0 === strpos( $external_id, 'wp_user_' ) ) {
				$external_id = '';
			}
		}

		if ( strlen( $external_id ) > 255 ) {
			$external_id = substr( $external_id, 0, 255 );
		}

		if ( '' === $external_id ) {
			// Mint an anonymous pseudonym (never the raw IP).
			$external_id = 'v_' . substr( sha1( $ip . '|' . wp_rand() ), 0, 24 );
		}

		$scopes = $request->get_param( 'scopes' );

		if ( ! is_array( $scopes ) ) {
			$scopes = array( 'content.read' );
		}

		$purpose = (string) $request->get_param( 'purpose' );
		$purpose = '' !== $purpose ? $purpose : 'agent_action';

		$row_id = ConsentRepo::record( $external_id, $wp_user_id, $scopes, $purpose, $ip );

		if ( ! $row_id ) {
			return new \WP_REST_Response( array( 'ok' => false, 'error' => 'record_failed' ), 500 );
		}

		$recorded = ConsentRepo::find( $external_id );

		return rest_ensure_response(
			array(
				'ok'        => true,
				'row_id'    => $row_id,
				'external_id' => $external_id,
				'scopes'    => json_decode( (string) ( $recorded['scopes'] ?? '[]' ), true ),
				'revoke_url' => rest_url( self::revoke_path( $row_id ) ),
			)
		);
	}

	/**
	 * Stable revocation path for the UI.
	 *
	 * @param int $row_id Consent row.
	 * @return string
	 */
	private static function revoke_path( $row_id ) {
		return 'vinkius/v1/consent/revoke?token=' . ConsentRepo::revoke_token( $row_id );
	}

	/**
	 * Revoke throttle gate for the public GET revoke route.
	 *
	 * Without a cap, a scanner could walk the token space for free, and
	 * every miss pages through the whole consent table (HMAC per row).
	 * Legit visitors click their own revoke link once, so a generous
	 * per-IP window is enough.
	 *
	 * @param string $ip Remote IP.
	 * @return bool True when the request may proceed.
	 */
	public static function revoke_throttle_ok( $ip ) {
		$ip = (string) $ip;

		if ( '' === $ip ) {
			return true;
		}

		$rl_key  = 'vc_revoke_rl_' . substr( sha1( $ip ), 0, 16 );
		$rl_hits = (int) get_transient( $rl_key );

		if ( $rl_hits >= 10 ) {
			return false;
		}

		if ( 0 === $rl_hits && false === get_transient( $rl_key ) ) {
			set_transient( $rl_key, 1, 10 * MINUTE_IN_SECONDS );
		} else {
			set_transient( $rl_key, $rl_hits + 1, 10 * MINUTE_IN_SECONDS );
		}

		return true;
	}
}
