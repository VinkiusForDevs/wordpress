<?php
/**
 * Evidence screen.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="vc-card">
	<div class="vc-card-head">
		<h2><?php esc_html_e( 'Execution evidence', 'vinkius-connector' ); ?></h2>
		<a class="button" href="<?php echo esc_url( $links['governance'] ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Full evidence in Vinkius', 'vinkius-connector' ); ?>
		</a>
	</div>
	<div class="vc-card-body">
		<p>
			<label class="vc-label" for="vc-evidence-picker"><?php esc_html_e( 'Connector', 'vinkius-connector' ); ?></label>
			<select id="vc-evidence-picker" class="vc-input">
				<option value=""><?php esc_html_e( 'Select a connector', 'vinkius-connector' ); ?></option>
			</select>
		</p>
	</div>
	<table class="vc-table" id="vc-evidence" hidden>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'vinkius-connector' ); ?></th>
				<th><?php esc_html_e( 'Tool', 'vinkius-connector' ); ?></th>
				<th><?php esc_html_e( 'Agent', 'vinkius-connector' ); ?></th>
				<th><?php esc_html_e( 'Status', 'vinkius-connector' ); ?></th>
				<th><?php esc_html_e( 'Latency', 'vinkius-connector' ); ?></th>
				<th><?php esc_html_e( 'DLP', 'vinkius-connector' ); ?></th>
			</tr>
		</thead>
		<tbody id="vc-evidence-rows"></tbody>
	</table>
</div>
