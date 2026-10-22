<?php
/**
 * Credential store (Passport JWT + refresh token from the device flow).
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Auth;

use VinkiusConnector\Options;
use VinkiusConnector\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the device-flow result (Passport access/refresh tokens) in the
 * dedicated `vc_auth` option, isolated from general settings so revocation
 * is atomic. Never expose these values to the browser; the admin REST
 * surface only reports status and a masked identity.
 */
final class AuthStore {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	const KEY = 'vc_auth';

	/**
	 * Persist a successful device login.
	 *
	 * @param array $payload Device-poll success payload {access_token, refresh_token, expires_in}.
	 * @return void
	 */
	public static function store( array $payload ) {
		update_option(
			self::KEY,
			array(
				'access_token'  => isset( $payload['access_token'] ) ? (string) $payload['access_token'] : '',
				'refresh_token' => isset( $payload['refresh_token'] ) ? (string) $payload['refresh_token'] : '',
				'expires_at'    => time() + ( isset( $payload['expires_in'] ) ? (int) $payload['expires_in'] : 3600 ),
			)
		);

		Options::set( 'connected', true );
	}

	/**
	 * Stored credential row.
	 *
	 * @return array
	 */
	public static function get() {
		$row = get_option( self::KEY, array() );

		return is_array( $row ) ? $row : array();
	}

	/**
	 * Current usable access token (auto-refresh prepared).
	 *
	 * Returns Result: ok(value=token) | not_connected | not_configured | error.
	 *
	 * @return Result
	 */
	public static function access_token() {
		$row = self::get();

		if ( empty( $row['access_token'] ) ) {
			return Result::not_connected();
		}

		// Refresh 30s before expiry.
		if ( (int) ( $row['expires_at'] ?? 0 ) - time() < 30 ) {
			$refresh = self::refresh();

			if ( ! $refresh->is_ok() ) {
				return $refresh;
			}

			$row = self::get();

			if ( empty( $row['access_token'] ) ) {
				return Result::not_connected();
			}
		}

		return Result::ok( array( 'token' => $row['access_token'] ) );
	}

	/**
	 * Exchange the refresh token for a new Passport pair.
	 *
	 * @return Result
	 */
	public static function refresh() {
		$row = self::get();

		if ( empty( $row['refresh_token'] ) ) {
			return Result::not_connected();
		}

		/*
		 * Concurrency guard: if the runtime rotates refresh tokens on use,
		 * two parallel callers exchanging the same refresh token would make
		 * the second exchange fail (400/401) and clear() a perfectly good
		 * session — a burst of admin page loads could log the site out.
		 * A short lock makes losers wait and reuse the winner's fresh pair.
		 */
		$lock = 'vc_refresh_lock';

		if ( false !== get_transient( $lock ) ) {
			for ( $i = 0; $i < 10; $i++ ) {
				usleep( 200000 );
				$row = self::get();

				if ( ! empty( $row['access_token'] ) && (int) ( $row['expires_at'] ?? 0 ) > time() + 30 ) {
					return Result::ok( array( 'token' => $row['access_token'] ) );
				}
			}
		}

		set_transient( $lock, time(), 30 );

		$client = new \VinkiusConnector\HttpClient();
		$result = $client->request( 'POST', '/auth/refresh', array( 'refresh_token' => $row['refresh_token'] ) );

		if ( ! $result->is_ok() ) {
			// Refresh expired/revoked: drop the session so the UI offers a
			// fresh device login instead of retrying a dead token.
			if ( 'vc_http_401' === $result->code() || 'vc_http_400' === $result->code() ) {
				self::clear();
			}

			delete_transient( $lock );

			return $result;
		}

		$data = $result->data();

		if ( ! empty( $data['access_token'] ) ) {
			self::store( $data );
		}

		delete_transient( $lock );

		return Result::ok( array( 'token' => $data['access_token'] ?? '' ) );
	}

	/**
	 * Clear the session (logout / connection reset).
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::KEY );
		Options::set( 'connected', false );
	}

	/**
	 * UI-facing status.
	 *
	 * @return array {state, has_refresh, expires_at}
	 */
	public static function status() {
		$row = self::get();

		$state = 'not_connected';

		if ( ! empty( $row['access_token'] ) ) {
			$state = ( (int) ( $row['expires_at'] ?? 0 ) > time() ) ? 'connected' : 'expired';
		}

		return array(
			'state'       => $state,
			'has_refresh' => ! empty( $row['refresh_token'] ),
			'expires_at'  => (int) ( $row['expires_at'] ?? 0 ),
		);
	}
}
