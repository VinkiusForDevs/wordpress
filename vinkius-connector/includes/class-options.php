<?php
/**
 * Plugin options.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Site-side settings, stored in `vc_settings`.
 */
final class Options {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	const KEY = 'vc_settings';

	/**
	 * Default values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'language'          => 'site',
			'site_secret'       => wp_generate_password( 48, true, false ),
			'halt'              => false,
			'connected'         => false,
			'badge_scan_id'     => '',
			'digest_enabled'    => false,
			'digest_email'      => '',
		);
	}

	/**
	 * Full settings row (merged with defaults).
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		$stored = get_option( self::KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$merged = wp_parse_args( $stored, self::defaults() );

		if ( empty( $merged['site_secret'] ) ) {
			$merged['site_secret'] = wp_generate_password( 48, true, false );
			update_option( self::KEY, $merged );
		}

		return $merged;
	}

	/**
	 * Ensure the option exists with sane defaults.
	 *
	 * @return void
	 */
	public static function ensure_defaults() {
		if ( false === get_option( self::KEY, false ) ) {
			update_option( self::KEY, self::defaults() );
		}
	}

	/**
	 * Read one value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$row = self::all();

		return array_key_exists( $key, $row ) ? $row[ $key ] : $default;
	}

	/**
	 * Write one value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function set( $key, $value ) {
		$row = self::all();
		$row[ $key ] = $value;
		update_option( self::KEY, $row );
	}

	/**
	 * Platform endpoints are plugin constants (VC_API_BASE_URL /
	 * VC_FRONTEND_BASE_URL), so once the plugin is active it is always
	 * "configured". The live gate is the Passport session
	 * (AuthStore::access_token()) — no token, no data — and `halt` is the
	 * operator kill-switch. Kept for the call sites that guard on it.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return true;
	}

	/**
	 * Local kill-switch state.
	 *
	 * @return bool
	 */
	public static function is_halted() {
		return (bool) self::get( 'halt', false );
	}
}
