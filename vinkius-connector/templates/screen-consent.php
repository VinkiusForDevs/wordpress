<?php
/**
 * Consent screen (Consent Hub).
 *
 * @license GPLv2 or later
 *
 * @var array $rows      Consent rows.
 * @var array $scopes    Available scopes.
 * @var string $shortcode
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="vc-card">
	<div class="vc-card-head">
		<h2><?php esc_html_e( 'Visitor Consent', 'vinkius-connector' ); ?></h2>
		<span class="vc-muted"><?php echo esc_html( $shortcode ); ?></span>
	</div>
	<p class="vc-muted"><?php esc_html_e( 'Agents only act on behalf of a visitor after they opt in with the shortcode below. Revoke any consent at any time.', 'vinkius-connector' ); ?></p>
	<?php if ( empty( $rows ) ) : ?>
		<p><?php esc_html_e( 'No consent records yet.', 'vinkius-connector' ); ?></p>
	<?php else : ?>
		<table class="vc-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Visitor', 'vinkius-connector' ); ?></th>
					<th><?php esc_html_e( 'Scopes', 'vinkius-connector' ); ?></th>
					<th><?php esc_html_e( 'Purpose', 'vinkius-connector' ); ?></th>
					<th><?php esc_html_e( 'Granted', 'vinkius-connector' ); ?></th>
					<th><?php esc_html_e( 'Status', 'vinkius-connector' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php $revoked = ! empty( $row['revoked_at'] ); ?>
					<tr class="<?php echo $revoked ? 'vc-row-revoked' : ''; ?>">
						<td><?php echo esc_html( $row['external_id'] ); ?></td>
						<td><?php echo esc_html( implode( ', ', (array) $row['scopes'] ) ); ?></td>
						<td><?php echo esc_html( $row['purpose'] ); ?></td>
						<td><?php echo esc_html( $row['created_at'] ); ?></td>
						<td>
							<?php if ( $revoked ) : ?>
								<span class="pill pill-warn"><?php esc_html_e( 'Revoked', 'vinkius-connector' ); ?></span>
							<?php else : ?>
								<span class="pill pill-ok"><?php esc_html_e( 'Active', 'vinkius-connector' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
