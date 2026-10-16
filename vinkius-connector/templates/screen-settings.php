<?php
/**
 * Settings screen.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="vc-card">
	<div class="vc-card-head">
		<h2><?php esc_html_e( 'Settings', 'vinkius-connector' ); ?></h2>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'vc_save_settings', 'vc_nonce' ); ?>
		<input type="hidden" name="action" value="vc_save_settings" />

		<p>
			<label class="vc-label" for="vc_language"><?php esc_html_e( 'Interface Language', 'vinkius-connector' ); ?></label>
			<select id="vc_language" name="vc_language" class="vc-input">
				<option value="site" <?php echo 'site' === $settings['language'] ? 'selected' : ''; ?>><?php esc_html_e( 'Follow site language', 'vinkius-connector' ); ?></option>
				<?php foreach ( $languages as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php echo $code === $settings['language'] ? 'selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label class="vc-label" for="vc_badge_scan_id"><?php esc_html_e( 'Trust badge scan ID', 'vinkius-connector' ); ?></label>
			<input type="text" id="vc_badge_scan_id" name="vc_badge_scan_id" class="vc-input"
				value="<?php echo esc_attr( $settings['badge_scan_id'] ); ?>" />
			<span class="vc-muted"><?php esc_html_e( 'From your debugger scan in the Vinkius cloud.', 'vinkius-connector' ); ?></span>
		</p>
		<p>
			<label class="vc-label" for="vc_digest_email"><?php esc_html_e( 'Daily digest', 'vinkius-connector' ); ?></label>
			<input type="email" id="vc_digest_email" name="vc_digest_email" class="vc-input"
				value="<?php echo esc_attr( $settings['digest_email'] ); ?>" />
			<label class="vc-check">
				<input type="checkbox" name="vc_digest_enabled" value="1" <?php echo $settings['digest_enabled'] ? 'checked' : ''; ?> />
				<?php esc_html_e( 'Send a daily digest of agent activity', 'vinkius-connector' ); ?>
			</label>
		</p>
		<p>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'vinkius-connector' ); ?></button>
		</p>
	</form>
</div>

<div class="vc-card">
	<div class="vc-card-head">
		<h2><?php esc_html_e( 'Vinkius Login', 'vinkius-connector' ); ?></h2>
	<span class="pill pill-<?php echo esc_attr( $auth_state ); ?>"><?php
		$labels = array(
			'not_connected' => __( 'Not connected', 'vinkius-connector' ),
			'connected'     => __( 'Connected', 'vinkius-connector' ),
			'expired'       => __( 'Session expired', 'vinkius-connector' ),
		);
		echo esc_html( isset( $labels[ $auth_state ] ) ? $labels[ $auth_state ] : $auth_state );
	?></span>
	</div>

	<div id="vc-device" data-pending="<?php echo $device_pending ? '1' : '0'; ?>">
		<?php if ( 'connected' === $auth_state ) : ?>
			<p class="vc-muted"><?php esc_html_e( 'Logged in via device flow.', 'vinkius-connector' ); ?></p>
			<p><button type="button" class="button vc-device-logout"><?php esc_html_e( 'Log out', 'vinkius-connector' ); ?></button></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Login uses the Vinkius device flow: start it here, approve on your Vinkius account, and this site connects automatically.', 'vinkius-connector' ); ?></p>
			<p><button type="button" class="button button-primary vc-device-connect"><?php esc_html_e( 'Connect with Vinkius', 'vinkius-connector' ); ?></button></p>
		<?php endif; ?>

		<?php if ( $device_pending ) : ?>
			<div class="vc-device-box">
				<p><?php esc_html_e( 'Device code', 'vinkius-connector' ); ?></p>
				<code class="vc-device-code"><?php echo esc_html( $device_user_code ); ?></code>
				<p>
					<a class="button" href="<?php echo esc_url( $device_verify_url ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( 'Open approval page', 'vinkius-connector' ); ?>
					</a>
					<span class="vc-muted vc-device-waiting"><?php esc_html_e( 'Waiting for approval…', 'vinkius-connector' ); ?></span>
				</p>
			</div>
		<?php endif; ?>
	</div>
</div>

<div class="vc-card">
	<div class="vc-card-head">
		<h2><?php esc_html_e( 'Security', 'vinkius-connector' ); ?></h2>
	</div>
	<p>
		<label class="vc-label" for="vc-site-secret"><?php esc_html_e( 'Site secret', 'vinkius-connector' ); ?></label>
		<input type="text" id="vc-site-secret" class="vc-input" readonly
			value="<?php echo esc_attr( $settings['site_secret'] ); ?>" />
		<p class="vc-actions">
			<button type="button" class="button vc-secret-copy"><?php esc_html_e( 'Copy', 'vinkius-connector' ); ?></button>
			<button type="button" class="button vc-secret-regen"><?php esc_html_e( 'Regenerate', 'vinkius-connector' ); ?></button>
		</p>
		<span class="vc-muted"><?php esc_html_e( 'Signs consent records and the site manifest.', 'vinkius-connector' ); ?></span>
	</p>
	<p>
		<label class="vc-label" for="vc-kill-switch"><?php esc_html_e( 'Local kill switch', 'vinkius-connector' ); ?></label>
		<input type="checkbox" id="vc-kill-switch" class="vc-kill-switch" <?php echo $settings['halt'] ? 'checked' : ''; ?> />
		<span class="vc-muted"><?php esc_html_e( 'When on, agent-facing surfaces on this site return 403.', 'vinkius-connector' ); ?></span>
	</p>
</div>

<div class="vc-card">
	<div class="vc-card-head">
		<h2><?php esc_html_e( 'Manage in Vinkius', 'vinkius-connector' ); ?></h2>
	</div>
	<div class="vc-card-body">
		<div class="vc-link-grid">
			<a class="vc-link-card" href="<?php echo esc_url( $links['servers'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Agents & connectors', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['governance'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Policies & DLP', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['settings'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Webhooks & secrets', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['upgrade'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Plans & upgrade', 'vinkius-connector' ); ?></a>
		</div>
	</div>
</div>
