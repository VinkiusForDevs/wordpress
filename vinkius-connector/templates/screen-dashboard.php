<?php
/**
 * Governance workbench: AI Governance sidebar + section canvas.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sidebar icons (Lucide, stroke 1.75).
 *
 * @var string
 */
$vc_icons = array(
	'layout-dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>',
	'users'            => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
	'plug'             => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22v-5"/><path d="M9 8V2"/><path d="M15 8V2"/><path d="M17.32 15H15V8H9v7H6.68a2 2 0 0 0-1.97 2.3l.34 1.91A2 2 0 0 0 7 21h10a2 2 0 0 0 1.95-1.79l.34-1.91A2 2 0 0 0 17.32 15Z"/></svg>',
	'key'              => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.586 16.718A2 2 0 0 1 1 15V3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v9"/><path d="M8 12h8a4 4 0 0 1 0 8h-3"/><circle cx="10" cy="16" r="1"/></svg>',
	'dollar-sign'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
	'gauge'            => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/></svg>',
	'bar-chart'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>',
	'alert-triangle'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
	'search'           => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>',
	'arrow-up-right'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 7h10v10"/><path d="M7 17 17 7"/></svg>',
);

/**
 * Sidebar items (mirror the Vinkius cloud AI Governance navigation).
 *
 * @var array[]
 */
$vc_gov_sections = array(
	array( 'id' => 'overview',           'label' => __( 'Mission Control', 'vinkius-connector' ),        'icon' => $vc_icons['layout-dashboard'] ),
	array( 'id' => 'clients',            'label' => __( 'Agent Activity', 'vinkius-connector' ),         'icon' => $vc_icons['users'] ),
	array( 'id' => 'connectors',         'label' => __( 'Connector Traffic', 'vinkius-connector' ),      'icon' => $vc_icons['plug'] ),
	array( 'id' => 'token-fleet',        'label' => __( 'Access Tokens', 'vinkius-connector' ),          'icon' => $vc_icons['key'] ),
	array( 'id' => 'cost',               'label' => __( 'AI Spend', 'vinkius-connector' ),              'icon' => $vc_icons['dollar-sign'] ),
	array( 'id' => 'tool-performance',   'label' => __( 'Capability Reliability', 'vinkius-connector' ), 'icon' => $vc_icons['gauge'] ),
	array( 'id' => 'security',           'label' => __( 'Security Posture', 'vinkius-connector' ),       'icon' => $vc_icons['bar-chart'] ),
	array( 'id' => 'errors',             'label' => __( 'Request Failures', 'vinkius-connector' ),       'icon' => $vc_icons['alert-triangle'] ),
);
?>
<div class="vc-gov" id="vc-gov" data-status="<?php echo esc_attr( $state ); ?>">
	<aside class="vc-gov-side">
		<div class="vc-gov-side-head">
			<span class="vc-gov-side-title"><?php esc_html_e( 'AI Governance', 'vinkius-connector' ); ?></span>
			<span class="vc-gov-side-range">1&#183;8</span>
		</div>
		<div class="vc-gov-side-content">
			<div class="vc-gov-group">
				<div class="vc-gov-group-label"><?php esc_html_e( 'Reports', 'vinkius-connector' ); ?></div>
				<?php foreach ( $vc_gov_sections as $i => $section ) : ?>
					<button type="button" class="vc-gov-item<?php echo 0 === $i ? ' is-active' : ''; ?>" data-section="<?php echo esc_attr( $section['id'] ); ?>">
						<span class="vc-gov-item-icon"><?php echo $section['icon']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<span class="vc-gov-item-label"><?php echo esc_html( $section['label'] ); ?></span>
						<kbd class="vc-gov-item-kbd"><?php echo (int) ( $i + 1 ); ?></kbd>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="vc-gov-group">
				<div class="vc-gov-group-label"><?php esc_html_e( 'Catalog', 'vinkius-connector' ); ?></div>
				<button type="button" class="vc-gov-item" data-section="capabilities">
					<span class="vc-gov-item-icon"><?php echo $vc_icons['search']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="vc-gov-item-label"><?php esc_html_e( 'Capabilities', 'vinkius-connector' ); ?></span>
				</button>
			</div>
		</div>
	</aside>

	<main class="vc-gov-main" id="vc-gov-main">
		<div class="vc-gov-view-head">
			<h2 class="vc-gov-view-title" id="vc-gov-title"><?php esc_html_e( 'Mission Control', 'vinkius-connector' ); ?></h2>
			<div class="vc-gov-periods" id="vc-gov-periods"></div>
		</div>

		<div class="vc-gov-view-body" id="vc-gov-body">
			<div class="vc-gov-loading"><?php esc_html_e( 'Loading…', 'vinkius-connector' ); ?></div>
		</div>

		<?php if ( 'connected' !== $state ) : ?>
			<div class="vc-gov-banner" id="vc-gov-connect-banner">
				<strong><?php esc_html_e( 'Connect WordPress to Vinkius', 'vinkius-connector' ); ?></strong>
				<p><?php esc_html_e( 'Governance data streams live from your Vinkius account.', 'vinkius-connector' ); ?></p>
				<button type="button" class="button button-primary vc-device-connect"><?php esc_html_e( 'Connect with Vinkius', 'vinkius-connector' ); ?></button>
				<div id="vc-device" data-pending="<?php echo $device_pending ? '1' : '0'; ?>"></div>
			</div>
		<?php endif; ?>
	</main>
</div>

<div class="vc-card">
	<div class="vc-card-head">
		<h2><?php esc_html_e( 'Manage in Vinkius', 'vinkius-connector' ); ?></h2>
	</div>
	<div class="vc-card-body">
		<div class="vc-link-grid">
			<a class="vc-link-card" href="<?php echo esc_url( $links['servers'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Agents & connectors', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['governance'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Policies & DLP', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['studio'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Publish connectors', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['marketplace'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Marketplace', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['settings'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Webhooks & secrets', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['upgrade'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Plans & upgrade', 'vinkius-connector' ); ?></a>
		</div>
	</div>
</div>
