<?php
/**
 * Visitor-consent repository.
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Stores per-visitor agent consents (the Consent Hub).
 *
 * Identity is a stable `external_id` — the WP user id when the visitor is
 * logged in, otherwise an anonymous visitor pseudonym supplied by the
 * client. Raw IPs are never stored; only a keyed hash.
 */
final class ConsentRepo {

	/**
	 * Consent scopes the plugin offers.
	 *
	 * @var string[]
	 */
	const SCOPES = array(
		'content.read',
		'content.write',
		'users.subscribe',
		'media.upload',
	);

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'vc_consents';
	}

	/**
	 * Record (or re-record) a consent.
	 *
	 * @param string      $external_id Stable visitor id.
	 * @param int         $wp_user_id  WP user id, 0 when anonymous.
	 * @param string[]    $scopes      Consent scope list.
	 * @param string      $purpose     Purpose key.
	 * @param string      $ip          Raw visitor IP (hashed before storage).
	 * @return int Row id.
	 */
	public static function record( $external_id, $wp_user_id, array $scopes, $purpose = 'agent_action', $ip = '' ) {
		global $wpdb;

		$external_id = sanitize_text_field( (string) $external_id );

		if ( '' === $external_id ) {
			return 0;
		}

		$valid_scopes = array_values( array_intersect( $scopes, self::SCOPES ) );
		$valid_scopes = empty( $valid_scopes ) ? array( 'content.read' ) : $valid_scopes;

		$ip_hash = self::hash_ip( $ip );

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE external_id = %s LIMIT 1",
				self::table(),
				$external_id
			)
		);

		$now = current_time( 'mysql', true );

		if ( $existing ) {
			$wpdb->update(
				self::table(),
				array(
					'wp_user_id' => (int) $wp_user_id,
					'scopes'     => wp_json_encode( $valid_scopes ),
					'purpose'    => sanitize_text_field( (string) $purpose ),
					'ip_hash'    => $ip_hash,
					'revoked_at' => null,
					'created_at' => $now,
				),
				array( 'id' => (int) $existing ),
				array( '%d', '%s', '%s', '%s', null, '%s' )
			);

			return (int) $existing;
		}

		return (int) $wpdb->insert(
			self::table(),
			array(
				'external_id' => $external_id,
				'wp_user_id'  => (int) $wp_user_id,
				'scopes'      => wp_json_encode( $valid_scopes ),
				'purpose'     => sanitize_text_field( (string) $purpose ),
				'ip_hash'     => $ip_hash,
				'created_at'  => $now,
				'revoked_at'  => null,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', null )
		);
	}

	/**
	 * Revoke by row id.
	 *
	 * @param int $row_id Consent row id.
	 * @return bool
	 */
	public static function revoke( $row_id ) {
		global $wpdb;

		return false !== $wpdb->update(
			self::table(),
			array( 'revoked_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $row_id ),
			array( '%s' )
		);
	}

	/**
	 * Find a consent row by external id.
	 *
	 * @param string $external_id Visitor id.
	 * @return array|null
	 */
	public static function find( $external_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE external_id = %s LIMIT 1",
				self::table(),
				sanitize_text_field( (string) $external_id )
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * All consent rows for the admin table.
	 *
	 * @param int $limit Row cap.
	 * @return array
	 */
	public static function list_all( $limit = 100 ) {
		global $wpdb;

		$limit = max( 1, min( (int) $limit, 500 ) );

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i ORDER BY created_at DESC LIMIT %d",
				self::table(),
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Whether a visitor currently holds an active consent with the given scope.
	 *
	 * @param string $external_id Visitor id.
	 * @param string $scope       Scope key.
	 * @return bool
	 */
	public static function is_active( $external_id, $scope ) {
		$row = self::find( $external_id );

		if ( ! $row || ! empty( $row['revoked_at'] ) ) {
			return false;
		}

		$scopes = json_decode( (string) $row['scopes'], true );

		return is_array( $scopes ) && in_array( $scope, $scopes, true );
	}

	/**
	 * Stable revocation token (HMAC of row id, so links can be shared safely).
	 *
	 * @param int $row_id Consent row id.
	 * @return string
	 */
	public static function revoke_token( $row_id ) {
		$secret = (string) \VinkiusConnector\Options::get( 'site_secret' );

		return substr( hash_hmac( 'sha256', 'revoke:' . (int) $row_id, $secret ), 0, 32 );
	}

	/**
	 * Resolve a revocation token back to a row id.
	 *
	 * Scans every consent row in id-ordered batches (never capped), so
	 * tokens for old rows keep working no matter how many consents exist.
	 *
	 * @param string $token 32-char token.
	 * @return int Row id, 0 when invalid.
	 */
	public static function resolve_revoke_token( $token ) {
		global $wpdb;

		$token = substr( (string) $token, 0, 32 );

		if ( '' === $token ) {
			return 0;
		}

		$secret = (string) \VinkiusConnector\Options::get( 'site_secret' );
		$cursor = 0;

		do {
			$batch = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM %i WHERE id > %d ORDER BY id ASC LIMIT 500",
					self::table(),
					$cursor
				)
			);

			if ( empty( $batch ) ) {
				break;
			}

			foreach ( $batch as $row_id ) {
				$row_token = substr( hash_hmac( 'sha256', 'revoke:' . (int) $row_id, $secret ), 0, 32 );

				if ( hash_equals( $row_token, $token ) ) {
					return (int) $row_id;
				}
			}

			$cursor = (int) end( $batch );
		} while ( 500 === count( $batch ) );

		return 0;
	}

	/**
	 * Keyed IP hash (per-site salt, no raw PII at rest).
	 *
	 * @param string $ip Raw IP.
	 * @return string
	 */
	private static function hash_ip( $ip ) {
		$ip = trim( (string) $ip );

		if ( '' === $ip ) {
			return '';
		}

		return substr( hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) . get_bloginfo( 'url' ) ), 0, 64 );
	}
}
