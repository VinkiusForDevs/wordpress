<?php
/**
 * Incidents screen.
 *
 * @license GPLv2 or later
 *
 * @var array  $links Vinkius cloud deep links.
 * @var string $status Connection state.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="vc-kpi-grid">
	<div class="postbox vc-kpi">
		<div class="inside">
			<span class="vc-kpi-value" id="vc-incident-errors">—</span>
			<span class="vc-kpi-label"><?php esc_html_e( 'Errors (today)', 'vinkius-connector' ); ?></span>
		</div>
	</div>
	<div class="postbox vc-kpi">
		<div class="inside">
			<span class="vc-kpi-value" id="vc-incident-dlp">—</span>
			<span class="vc-kpi-label"><?php esc_html_e( 'DLP blocks (today)', 'vinkius-connector' ); ?></span>
		</div>
	</div>
	<div class="postbox vc-kpi">
		<div class="inside">
			<span class="vc-kpi-value" id="vc-incident-circuit">—</span>
			<span class="vc-kpi-label"><?php esc_html_e( 'Circuit breaker', 'vinkius-connector' ); ?></span>
		</div>
	</div>
</div>

<div class="postbox">
	<div class="postbox-header">
		<h2 class="hndle"><?php esc_html_e( 'What happened', 'vinkius-connector' ); ?></h2>
		<span class="vc-pill vc-pill-warn" id="vc-incident-circuit-pill" hidden><?php esc_html_e( 'Tripped', 'vinkius-connector' ); ?></span>
	</div>
	<div class="inside" id="vc-briefing">
		<p class="description"><?php esc_html_e( 'No incident summary available for this period.', 'vinkius-connector' ); ?></p>
	</div>
</div>

<div class="postbox">
	<div class="postbox-header">
		<h2 class="hndle"><?php esc_html_e( 'Respond', 'vinkius-connector' ); ?></h2>
	</div>
	<div class="inside vc-actions">
		<button type="button" class="button vc-circuit-reset" hidden><?php esc_html_e( 'Reset circuit', 'vinkius-connector' ); ?></button>
		<button type="button" class="button button-primary vc-halt-all"><?php esc_html_e( 'Halt all', 'vinkius-connector' ); ?></button>
		<a class="button" href="<?php echo esc_url( $links['governance'] ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Open incidents in Vinkius', 'vinkius-connector' ); ?>
		</a>
	</div>
</div>

<div class="postbox">
	<div class="postbox-header">
		<h2 class="hndle"><?php esc_html_e( 'Quarantined connectors', 'vinkius-connector' ); ?></h2>
	</div>
	<div class="inside" id="vc-incident-quarantine">
		<p class="vc-loading"><span class="spinner is-active" aria-hidden="true"></span><span><?php esc_html_e( 'Reading quarantine state…', 'vinkius-connector' ); ?></span></p>
	</div>
</div>

<div class="postbox" id="vc-audit-root">
	<div class="postbox-header">
		<h2 class="hndle"><?php esc_html_e( 'Control trail', 'vinkius-connector' ); ?></h2>
		<nav class="nav-tab-wrapper" id="vc-audit-tabs">
			<button type="button" class="nav-tab" data-scope="activity"><?php esc_html_e( 'Runtime activity', 'vinkius-connector' ); ?></button>
			<button type="button" class="nav-tab" data-scope="org"><?php esc_html_e( 'Operator audit', 'vinkius-connector' ); ?></button>
		</nav>
	</div>
	<div class="inside" id="vc-audit-feed">
		<p class="description"><?php esc_html_e( 'Who acted on this governance estate, agent- and operator-side.', 'vinkius-connector' ); ?></p>
	</div>
</div>
