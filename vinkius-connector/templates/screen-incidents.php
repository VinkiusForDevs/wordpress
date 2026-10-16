<?php
/**
 * Incidents screen.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="vc-grid">
	<div class="vc-card vc-card-kpi">
		<div class="vc-card-body">
			<span class="vc-kpi-value" id="vc-incident-errors">—</span>
			<span class="vc-kpi-label"><?php esc_html_e( 'Errors (today)', 'vinkius-connector' ); ?></span>
		</div>
	</div>
	<div class="vc-card vc-card-kpi">
		<div class="vc-card-body">
			<span class="vc-kpi-value" id="vc-incident-dlp">—</span>
			<span class="vc-kpi-label"><?php esc_html_e( 'DLP blocks (today)', 'vinkius-connector' ); ?></span>
		</div>
	</div>
	<div class="vc-card vc-card-kpi">
		<div class="vc-card-body">
			<span class="vc-kpi-value" id="vc-incident-circuit">—</span>
			<span class="vc-kpi-label"><?php esc_html_e( 'Circuit breaker', 'vinkius-connector' ); ?></span>
		</div>
	</div>
</div>

<div class="vc-card">
	<div class="vc-card-head">
		<h2><?php esc_html_e( 'What happened', 'vinkius-connector' ); ?></h2>
		<span class="pill" id="vc-incident-circuit-pill" hidden><?php esc_html_e( 'Tripped', 'vinkius-connector' ); ?></span>
	</div>
	<div class="vc-card-body" id="vc-briefing">
		<p class="vc-muted"><?php esc_html_e( 'No incident summary available for this period.', 'vinkius-connector' ); ?></p>
	</div>
</div>

<div class="vc-card">
	<div class="vc-card-head">
		<h2><?php esc_html_e( 'Respond', 'vinkius-connector' ); ?></h2>
	</div>
	<div class="vc-card-body vc-actions">
		<button type="button" class="button vc-circuit-reset" hidden><?php esc_html_e( 'Reset circuit', 'vinkius-connector' ); ?></button>
		<button type="button" class="button button-primary vc-halt-all"><?php esc_html_e( 'Halt all', 'vinkius-connector' ); ?></button>
		<a class="button" href="<?php echo esc_url( $links['governance'] ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Open incidents in Vinkius', 'vinkius-connector' ); ?>
		</a>
	</div>
</div>
