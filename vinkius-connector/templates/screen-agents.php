<?php
/**
 * Agents screen.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="vc-card">
	<div class="vc-card-head">
		<h2><?php esc_html_e( 'Agent fleet', 'vinkius-connector' ); ?></h2>
		<a class="button" href="<?php echo esc_url( $links['servers'] ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Manage agents & tokens in Vinkius', 'vinkius-connector' ); ?>
		</a>
	</div>
	<p class="vc-muted"><?php esc_html_e( 'Read live from the Vinkius runtime. Nothing is stored on this site.', 'vinkius-connector' ); ?></p>
	<table class="vc-table" id="vc-fleet">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Connector', 'vinkius-connector' ); ?></th>
				<th><?php esc_html_e( 'Agents', 'vinkius-connector' ); ?></th>
				<th><?php esc_html_e( 'Requests', 'vinkius-connector' ); ?></th>
				<th><?php esc_html_e( 'Avg latency', 'vinkius-connector' ); ?></th>
				<th><?php esc_html_e( 'Status', 'vinkius-connector' ); ?></th>
			</tr>
		</thead>
		<tbody id="vc-fleet-rows">
			<tr><td colspan="5" class="vc-muted"><?php esc_html_e( 'No data loaded.', 'vinkius-connector' ); ?></td></tr>
		</tbody>
	</table>
</div>
