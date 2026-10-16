<?php
/**
 * Deep links to the Vinkius cloud frontend.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Cloud frontend URLs.
 */
final class Links {

	/**
	 * Cloud frontend screens (paths on the configured frontend base URL).
	 *
	 * @var array<string, string>
	 */
	const SCREENS = array(
		'servers'     => '/servers',
		'governance'  => '/governance',
		'studio'      => '/mcp-studio/published',
		'marketplace' => '/marketplace',
		'seller'      => '/settings/earnings',
		'settings'    => '/settings',
		'upgrade'     => '/upgrade',
		'device'      => '/device',
	);

	/**
	 * Absolute URL for a cloud screen.
	 *
	 * @param string $screen Screen key (self::SCREENS).
	 * @return string
	 */
	public static function cloud( $screen ) {
		$base = rtrim( VC_FRONTEND_BASE_URL, '/' );
		$path = isset( self::SCREENS[ $screen ] ) ? self::SCREENS[ $screen ] : '';

		return $base . $path;
	}

	/**
	 * All link cards for the UI (label keys stay in the templates).
	 *
	 * @return array<string, string>
	 */
	public static function all() {
		$urls = array();

		foreach ( array_keys( self::SCREENS ) as $screen ) {
			$urls[ $screen ] = self::cloud( $screen );
		}

		return $urls;
	}

	/**
	 * Deep link to a capability in the Vinkius cloud (opens in a new tab).
	 *
	 * @param string $slug Listing slug.
	 * @return string
	 */
	public static function capability( $slug ) {
		return rtrim( VC_FRONTEND_BASE_URL, '/' ) . '/ai-agent-connect/' . rawurlencode( (string) $slug );
	}
}
