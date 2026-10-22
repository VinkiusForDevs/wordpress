<?php
/**
 * Settings screen.
 *
 * @license GPLv2 or later
 *
 * @var array  $settings          Options row.
 * @var array  $links             Vinkius cloud deep links.
 * @var string $auth_state        Connection state.
 * @var array  $languages         Supported UI languages.
 * @var bool   $device_pending    Device flow in flight.
 * @var string $device_user_code  User code to approve.
 * @var string $device_verify_url Approval URL.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="postbox">
	<div class="postbox-header">
		<h2 class="hndle"><?php esc_html_e( 'Settings', 'vinkius-connector' ); ?></h2>
	</div>
	<div class="inside">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'vc_save_settings', 'vc_nonce' ); ?>
			<input type="hidden" name="action" value="vc_save_settings" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="vc_language"><?php esc_html_e( 'Interface Language', 'vinkius-connector' ); ?></label></th>
					<td>
						<select id="vc_language" name="vc_language">
							<option value="site" <?php echo 'site' === $settings['language'] ? 'selected' : ''; ?>><?php esc_html_e( 'Follow site language', 'vinkius-connector' ); ?></option>
							<?php foreach ( $languages as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>" <?php echo $code === $settings['language'] ? 'selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="vc_badge_scan_id"><?php esc_html_e( 'Trust badge scan ID', 'vinkius-connector' ); ?></label></th>
					<td>
						<input type="text" id="vc_badge_scan_id" name="vc_badge_scan_id" class="regular-text"
							value="<?php echo esc_attr( $settings['badge_scan_id'] ); ?>" />
						<p class="description"><?php esc_html_e( 'From your debugger scan in the Vinkius cloud.', 'vinkius-connector' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="vc_digest_email"><?php esc_html_e( 'Daily digest', 'vinkius-connector' ); ?></label></th>
					<td>
						<input type="email" id="vc_digest_email" name="vc_digest_email" class="regular-text"
							value="<?php echo esc_attr( $settings['digest_email'] ); ?>" />
						<label class="vc-check">
							<input type="checkbox" name="vc_digest_enabled" value="1" <?php echo $settings['digest_enabled'] ? 'checked' : ''; ?> />
							<?php esc_html_e( 'Send a daily digest of agent activity', 'vinkius-connector' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'vinkius-connector' ); ?></button></p>
		</form>
	</div>
</div>

<div class="postbox">
	<div class="postbox-header">
		<h2 class="hndle"><?php esc_html_e( 'Vinkius Login', 'vinkius-connector' ); ?></h2>
		<span class="vc-pill vc-pill-<?php echo esc_attr( $auth_state ); ?>"><?php
			$labels = array(
				'not_connected' => __( 'Not connected', 'vinkius-connector' ),
				'connected'     => __( 'Connected', 'vinkius-connector' ),
				'expired'       => __( 'Session expired', 'vinkius-connector' ),
			);
			echo esc_html( isset( $labels[ $auth_state ] ) ? $labels[ $auth_state ] : $auth_state );
		?></span>
	</div>
	<div class="inside">
		<div id="vc-device" data-pending="<?php echo $device_pending ? '1' : '0'; ?>">
			<?php if ( 'connected' === $auth_state ) : ?>
				<p class="description"><?php esc_html_e( 'Logged in via device flow.', 'vinkius-connector' ); ?></p>
				<p><button type="button" class="button vc-device-logout"><?php esc_html_e( 'Log out', 'vinkius-connector' ); ?></button></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Login uses the Vinkius device flow: start it here, approve on your Vinkius account, and this site connects automatically.', 'vinkius-connector' ); ?></p>
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
</div>

<div class="postbox">
	<div class="postbox-header">
		<h2 class="hndle"><?php esc_html_e( 'Security', 'vinkius-connector' ); ?></h2>
	</div>
	<div class="inside">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="vc-site-secret"><?php esc_html_e( 'Site secret', 'vinkius-connector' ); ?></label></th>
				<td>
					<input type="text" id="vc-site-secret" class="regular-text" readonly
						value="<?php echo esc_attr( $settings['site_secret'] ); ?>" />
					<p class="vc-actions">
						<button type="button" class="button vc-secret-copy"><?php esc_html_e( 'Copy', 'vinkius-connector' ); ?></button>
						<button type="button" class="button vc-secret-regen"><?php esc_html_e( 'Regenerate', 'vinkius-connector' ); ?></button>
					</p>
					<p class="description"><?php esc_html_e( 'Signs consent records and the site manifest.', 'vinkius-connector' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="vc-kill-switch"><?php esc_html_e( 'Local kill switch', 'vinkius-connector' ); ?></label></th>
				<td>
					<input type="checkbox" id="vc-kill-switch" class="vc-kill-switch" <?php echo $settings['halt'] ? 'checked' : ''; ?> />
					<span class="description"><?php esc_html_e( 'When on, agent-facing surfaces on this site return 403.', 'vinkius-connector' ); ?></span>
				</td>
			</tr>
		</table>
	</div>
</div>

<div class="postbox">
	<div class="postbox-header">
		<h2 class="hndle"><?php esc_html_e( 'Manage in Vinkius', 'vinkius-connector' ); ?></h2>
	</div>
	<div class="inside">
		<div class="vc-link-grid">
			<a class="vc-link-card" href="<?php echo esc_url( $links['servers'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Agents & connectors', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['governance'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Policies & DLP', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['settings'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Webhooks & secrets', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['upgrade'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Plans & upgrade', 'vinkius-connector' ); ?></a>
		</div>
	</div>
</div>
