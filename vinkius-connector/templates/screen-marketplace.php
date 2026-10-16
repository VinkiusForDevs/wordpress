<?php
/**
 * Marketplace screen: deep links only.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="vc-card">
	<div class="vc-card-head">
		<h2><?php esc_html_e( 'Marketplace', 'vinkius-connector' ); ?></h2>
	</div>
	<div class="vc-card-body">
		<p class="vc-muted">
			<?php esc_html_e( 'Publish your connectors, reach the catalog, and track earnings — from the Vinkius cloud. This screen only links there; nothing is managed on this site.', 'vinkius-connector' ); ?>
		</p>
		<div class="vc-link-grid">
			<a class="vc-link-card" href="<?php echo esc_url( $links['studio'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Publish connectors', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['marketplace'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Browse catalog', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['seller'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Earnings', 'vinkius-connector' ); ?></a>
			<a class="vc-link-card" href="<?php echo esc_url( $links['upgrade'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Plans & upgrade', 'vinkius-connector' ); ?></a>
		</div>
	</div>
</div>
