<?php
/**
 * Settings screen: connection, language, security, device login.
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

use VinkiusConnector\I18n\I18n;
use VinkiusConnector\Options;

defined( 'ABSPATH' ) || exit;

/**
 * The setup surface: API/Frontend URLs, user-selectable UI language,
 * webhook secret, connection state and the device-flow login box.
 */
class ScreenSettings extends ScreenBase {

	/**
	 * Screen slug.
	 *
	 * @return string
	 */
	protected function slug() {
		return 'settings';
	}

	/**
	 * Prepare view data.
	 *
	 * @return array
	 */
	protected function prepare() {
		$auth = \VinkiusConnector\Auth\AuthStore::status();
		$flow = new \VinkiusConnector\Auth\DeviceFlow();

		$pending  = get_transient( \VinkiusConnector\Auth\DeviceFlow::BUNDLE_KEY );
		$pending  = is_array( $pending ) ? $pending : array();

		return array(
			'settings'          => Options::all(),
			'links'             => \VinkiusConnector\Links::all(),
			'auth_state'        => $auth['state'],
			'languages'         => I18n::supported(),
			'device_pending'    => $flow->is_pending(),
			'device_user_code'  => isset( $pending['user_code'] ) ? $pending['user_code'] : '',
			'device_verify_url' => isset( $pending['verification_url'] ) ? $pending['verification_url'] : rtrim( VC_FRONTEND_BASE_URL, '/' ) . '/device',
		);
	}

	/**
	 * Save handler (admin_post_vc_save_settings).
	 *
	 * @return void
	 */
	public static function save_settings() {
		$nonce = isset( $_POST['vc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['vc_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'vc_save_settings' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'vinkius-connector' ) );
		}

		$saved = (array) Options::all();

		$language = isset( $_POST['vc_language'] ) ? sanitize_text_field( wp_unslash( $_POST['vc_language'] ) ) : 'site';
		$saved['language'] = in_array( $language, array_merge( array_keys( I18n::supported() ), array( 'site' ) ), true )
			? $language
			: 'site';

		$saved['badge_scan_id'] = isset( $_POST['vc_badge_scan_id'] )
			? sanitize_text_field( wp_unslash( $_POST['vc_badge_scan_id'] ) )
			: '';

		$saved['digest_enabled'] = isset( $_POST['vc_digest_enabled'] ) ? true : false;
		$saved['digest_email']   = isset( $_POST['vc_digest_email'] )
			? sanitize_email( wp_unslash( $_POST['vc_digest_email'] ) )
			: '';

		update_option( Options::KEY, $saved );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'vinkius-connector-settings',
					'vc-saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
