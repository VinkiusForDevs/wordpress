<?php
/**
 * Device-flow login (RFC 8628) against the Vinkius API.
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Auth;

use VinkiusConnector\HttpClient;
use VinkiusConnector\Options;
use VinkiusConnector\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates the owner's login from wp-admin:
 *
	 *  1. init()  -> POST {api}/auth/device       (public)
	 *                -> {device_code, user_code, verification_url, expires_in, interval}
	 *     The admin sees the user_code and opens the approval page on the
	 *     Vinkius frontend, where their existing session approves the device.
	 *  2. poll()  -> POST {api}/auth/device/poll  (public, device_code)
	 *                -> tokenData on success; 400 error=authorization_pending|
	 *                 slow_down keeps polling; expired_token|invalid_grant is terminal.
 *
 * The pending bundle lives in a short-lived transient; nothing sensitive
 * is kept in the admin browser.
 */
final class DeviceFlow {

	/**
	 * Transient key for the pending device bundle.
	 *
	 * @var string
	 */
	const BUNDLE_KEY = 'vc_device_bundle';

	/**
	 * Start a device login.
	 *
	 * @return Result Data: {user_code, verification_url, expires_in, interval}
	 */
	public function init() {
		$client = new HttpClient();
		$result = $client->request( 'POST', '/auth/device', array( 'client_name' => 'WordPress' ) );

		if ( ! $result->is_ok() ) {
			return $result;
		}

		$data = $result->data();

		$bundle = array(
			'device_code'      => isset( $data['device_code'] ) ? (string) $data['device_code'] : '',
			'user_code'        => isset( $data['user_code'] ) ? (string) $data['user_code'] : '',
			'expires_in'       => isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 900,
			'interval'         => isset( $data['interval'] ) ? (int) $data['interval'] : 5,
			'created_at'       => time(),
		);

		// Approval URL comes from the API (code pre-filled when available);
		// fall back to the platform frontend.
		$verification_url = $data['verification_uri_complete'] ?? $data['verification_url'] ?? null;

		if ( empty( $verification_url ) ) {
			$verification_url = rtrim( VC_FRONTEND_BASE_URL, '/' ) . '/device';
		}

		$bundle['verification_url'] = $verification_url;
		set_transient( self::BUNDLE_KEY, $bundle, $bundle['expires_in'] + 60 );

		unset( $bundle['device_code'], $bundle['created_at'] );

		return Result::ok( $bundle );
	}

	/**
	 * Poll for approval.
	 *
	 * @return Result Data: {status: pending|success|expired|denied|no_session}
	 */
	public function poll() {
		$bundle = get_transient( self::BUNDLE_KEY );

		if ( ! is_array( $bundle ) || empty( $bundle['device_code'] ) ) {
			return Result::error( 'vc_device_no_session' );
		}

		if ( time() - (int) $bundle['created_at'] > $bundle['expires_in'] ) {
			delete_transient( self::BUNDLE_KEY );

			return Result::error( 'vc_device_expired' );
		}

		$client = new HttpClient();
		$result = $client->request( 'POST', '/auth/device/poll', array( 'device_code' => $bundle['device_code'] ) );

		if ( ! $result->is_ok() ) {
			$payload = $result->data();
			$upstream = isset( $payload['error'] ) ? (string) $payload['error'] : '';

			if ( in_array( $upstream, array( 'authorization_pending', 'slow_down' ), true ) ) {
				return Result::ok( array( 'status' => 'pending' ) );
			}

			if ( in_array( $upstream, array( 'expired_token', 'invalid_grant' ), true ) ) {
				delete_transient( self::BUNDLE_KEY );

				return Result::error( 'vc_device_expired' );
			}

			// Other transport/5xx errors keep the poll loop alive.
			return Result::ok( array( 'status' => 'pending' ) );
		}

		$data = $result->data();

		if ( ! empty( $data['access_token'] ) ) {
			AuthStore::store( $data );
			delete_transient( self::BUNDLE_KEY );

			/*
			 * The tokens were persisted server-side (AuthStore::store). The
			 * browser only needs the status — echoing the access/refresh
			 * pair into an admin REST response would defeat the contract in
			 * AuthStore ("never expose these values to the browser").
			 */
			return Result::ok( array( 'status' => 'success' ) );
		}

		return Result::ok( array( 'status' => 'pending' ) );
	}

	/**
	 * Abandon a pending login.
	 *
	 * @return void
	 */
	public function abort() {
		delete_transient( self::BUNDLE_KEY );
	}

	/**
	 * Whether a device login is in flight (for the settings UI state).
	 *
	 * @return bool
	 */
	public function is_pending() {
		$bundle = get_transient( self::BUNDLE_KEY );

		return is_array( $bundle ) && ! empty( $bundle['device_code'] );
	}
}
