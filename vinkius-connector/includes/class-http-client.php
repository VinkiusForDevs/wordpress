<?php
/**
 * Minimal HTTP client for the Vinkius API.
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
 * wp_remote_request wrapper with JSON handling, bearer auth and a
 * deterministic error mapping. Every Vinkius call goes through here, so
 * the "prepared" layer can be tested and swapped without touching UI code.
 */
final class HttpClient {

	/**
	 * Request timeout (seconds).
	 *
	 * Longer governance windows (e.g. /guardian/* at 30d) can be slow; a 15s
	 * cap turned a valid slow response into a spurious "session expired."
	 *
	 * @var int
	 */
	const TIMEOUT = 30;

	/**
	 * Perform a JSON request against the configured Vinkius API.
	 *
	 * @param string     $method  GET|POST|PUT|PATCH|DELETE.
	 * @param string     $path    API path (e.g. /auth/device).
	 * @param array|null $body    JSON body (null for GET).
	 * @param string|null $bearer Bearer token, or null for public routes.
	 * @return Result
	 */
	public function request( $method, $path, $body = null, $bearer = null ) {
		$base = rtrim( VC_API_BASE_URL, '/' );
		$url  = $base . ( 0 === strpos( $path, '/' ) ? $path : '/' . $path );

		$args = array(
			'method'     => strtoupper( $method ),
			'timeout'    => self::TIMEOUT,
			'headers'    => array(
				'Content-Type' => 'application/json; charset=utf-8',
				'Accept'       => 'application/json',
			),
			'redirection' => 0,
		);

		if ( null !== $bearer && '' !== $bearer ) {
			$args['headers']['Authorization'] = 'Bearer ' . $bearer;
		}

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			// Transport-level failure (DNS, timeout, TLS).
			return Result::error( 'vc_http_' . $response->get_error_code(), array( 'message' => $response->get_error_message() ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status >= 200 && $status < 300 ) {
			// 204/empty bodies are valid.
			return Result::ok( is_array( $decoded ) ? $decoded : array( 'raw' => $decoded ) );
		}

		$code = 'vc_http_' . $status;
		$data = is_array( $decoded ) ? $decoded : array( 'message' => wp_remote_retrieve_body( $response ) );

		// A 401 means our stored token needs a refresh (handled by AuthStore).
		return Result::error( $code, $data );
	}
}
