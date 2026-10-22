<?php
/**
 * Public shortcodes (visitor consent + status).
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

use VinkiusConnector\Models\ConsentRepo;

defined( 'ABSPATH' ) || exit;

/**
 * [vinkius_consent] — the visitor opt-in form for agent actions. This is
 * the explicit-consent primitive the wordpress.org "phone home" policy
 * requires: nothing agent-facing runs for a visitor until they opt in.
 */
final class Shortcodes {

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'vinkius_consent', array( $this, 'consent_form' ) );
	}

	/**
	 * Render the consent form (styled by the admin stylesheet only in
	 * admin screens; on front pages the plugin enqueues a minimal style).
	 *
	 * @return string
	 */
	public function consent_form() {
		if ( Options::is_halted() ) {
			return '<p class="vc-notice">' . esc_html__( 'Agent actions are currently halted on this site.', 'vinkius-connector' ) . '</p>';
		}

		$scope_options = array();

		foreach ( ConsentRepo::SCOPES as $scope ) {
			$scope_options[] = sprintf(
				'<label class="vc-consent-scope"><input type="checkbox" name="vc_scopes[]" value="%1$s" checked="checked" /> %2$s</label>',
				esc_attr( $scope ),
				esc_html( self::scope_label( $scope ) )
			);
		}

		$intro = esc_html__( 'By continuing you allow Vinkius agents to act on your behalf on this site with the selected scopes. You can revoke at any time.', 'vinkius-connector' );

		$ok_msg  = __( 'Consent recorded. You can revoke it anytime.', 'vinkius-connector' );
		$err_msg = __( 'Something went wrong. Please try again.', 'vinkius-connector' );

		ob_start();
		?>
		<div class="vc-consent-box" data-endpoint="<?php echo esc_url( rest_url( 'vinkius/v1/consent' ) ); ?>">
			<p class="vc-consent-intro"><?php echo esc_html( $intro ); ?></p>
			<div class="vc-consent-scopes"><?php echo implode( "\n", $scope_options ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<input type="hidden" name="vc_hp" class="vc-hp" value="" tabindex="-1" autocomplete="off" />
			<p><button type="button" class="button vc-consent-submit"><?php esc_html_e( 'Allow AI agents to act on my behalf on this site', 'vinkius-connector' ); ?></button></p>
			<p class="vc-consent-result" hidden></p>
		</div>
		<script>
		(function () {
			var okMsg = <?php echo wp_json_encode( $ok_msg ); ?>;
			var errMsg = <?php echo wp_json_encode( $err_msg ); ?>;
			var box = document.currentScript.previousElementSibling;
			if (!box || box.tagName !== 'DIV' || !box.classList.contains('vc-consent-box')) {
				box = document.querySelector('.vc-consent-box');
			}
			if (!box) { return; }
			var btn = box.querySelector('.vc-consent-submit');
			var out = box.querySelector('.vc-consent-result');
			if (!btn) { return; }
			btn.addEventListener('click', function () {
				var scopes = Array.prototype.map.call(box.querySelectorAll('input[name="vc_scopes[]"]:checked'), function (el) { return el.value; });
				var anon;
				try {
					/*
					 * Stable per-browser pseudonym so the same visitor lands on
					 * the same consent row across visits (the Consent Hub relies
					 * on it). Random fallback keeps it working with storage blocked.
					 */
					anon = localStorage.getItem('vc_anon');
					if (!anon) {
						anon = 'v_' + Math.random().toString(36).slice(2, 26);
						localStorage.setItem('vc_anon', anon);
					}
				} catch (e) {
					anon = 'v_' + Math.random().toString(36).slice(2, 26);
				}
				fetch(box.getAttribute('data-endpoint'), {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ external_id: anon, scopes: scopes, hp: box.querySelector('.vc-hp').value }),
				}).then(function (r) { return r.json(); }).then(function (res) {
					out.hidden = false;
					out.textContent = res.ok ? okMsg : errMsg;
					btn.disabled = true;
				}).catch(function () {
					out.hidden = false;
					out.textContent = errMsg;
				});
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Human labels for scopes.
	 *
	 * @param string $scope Scope key.
	 * @return string
	 */
	private static function scope_label( $scope ) {
		$labels = array(
			'content.read'      => __( 'Read site content', 'vinkius-connector' ),
			'content.write'     => __( 'Draft site content', 'vinkius-connector' ),
			'users.subscribe'   => __( 'Subscribe me as a user', 'vinkius-connector' ),
			'media.upload'      => __( 'Upload media on my behalf', 'vinkius-connector' ),
		);

		return isset( $labels[ $scope ] ) ? $labels[ $scope ] : $scope;
	}
}
