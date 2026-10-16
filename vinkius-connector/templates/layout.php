<?php
/**
 * Admin layout.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap vc-wrap" data-status="<?php echo esc_attr( $status ); ?>">

	<h1 class="vc-title">
		<?php esc_html_e( 'Vinkius AI Governance', 'vinkius-connector' ); ?>
		<span class="pill pill-<?php echo esc_attr( $status ); ?>" id="vc-status-pill"><?php
			$labels = array(
				'not_connected' => __( 'Not connected', 'vinkius-connector' ),
				'connected'     => __( 'Connected', 'vinkius-connector' ),
				'expired'       => __( 'Session expired', 'vinkius-connector' ),
			);
			echo esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status );
		?></span>
	</h1>

	<?php if ( $halt ) : ?>
		<div class="vc-banner vc-banner-danger" id="vc-halt-banner">
			<strong><?php esc_html_e( 'Agents are halted on this site.', 'vinkius-connector' ); ?></strong>
			<button class="button vc-halt-resume" type="button"><?php esc_html_e( 'Resume agents', 'vinkius-connector' ); ?></button>
		</div>
	<?php endif; ?>

	<?php if ( 'connected' !== $status && 'settings' !== $slug ) : ?>
		<div class="vc-card vc-card-cta" id="vc-cta-card">
			<div class="vc-card-body">
				<?php if ( 'expired' === $status ) : ?>
					<strong><?php esc_html_e( 'Your Vinkius session has ended.', 'vinkius-connector' ); ?></strong>
					<p><?php esc_html_e( 'Log in again to see live data.', 'vinkius-connector' ); ?></p>
					<button type="button" class="button button-primary vc-device-connect"><?php esc_html_e( 'Log in again', 'vinkius-connector' ); ?></button>
				<?php else : ?>
					<strong><?php esc_html_e( 'Connect WordPress to Vinkius', 'vinkius-connector' ); ?></strong>
					<p><?php esc_html_e( 'Link this site to your Vinkius account to see governed agent operations here.', 'vinkius-connector' ); ?></p>
					<button type="button" class="button button-primary vc-device-connect"><?php esc_html_e( 'Connect with Vinkius', 'vinkius-connector' ); ?></button>
				<?php endif; ?>

				<div id="vc-device" data-pending="<?php echo $device_pending ? '1' : '0'; ?>">
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
	<?php endif; ?>

	<?php
	$merged = array_merge( is_array( $vars ) ? $vars : array(), compact( 'slug', 'status', 'halt', 'frontend' ) );
	VinkiusConnector\Template::render( 'screen-' . $slug, $merged );
	?>

	<p class="vc-scope-note">
		<?php esc_html_e( 'This panel shows only operations routed through the Vinkius runtime. Reliability — credential vault, DLP, sandboxing, circuit breakers, kill-switch — is enforced by the Vinkius runtime, not by this site.', 'vinkius-connector' ); ?>
	</p>

	<footer class="vc-footer">
		<?php
		printf(
			esc_html__( 'Vinkius Connector %1$s · Open source · GPLv2 or later', 'vinkius-connector' ),
			VC_VERSION
		);
		?>
	</footer>
</div>
