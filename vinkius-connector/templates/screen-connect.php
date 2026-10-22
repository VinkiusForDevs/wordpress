<?php
/**
 * AI Connections page (capabilities catalog search).
 *
 * @license GPLv2 or later
 *
 * @var string $status Connection state.
 * @var array  $links  Vinkius cloud deep links.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="vc-cap-root" class="vc-cap" data-status="<?php echo esc_attr( $status ); ?>">

	<div class="postbox">
		<div class="postbox-header">
			<h2 class="hndle"><?php esc_html_e( 'AI Connections', 'vinkius-connector' ); ?></h2>
		</div>
		<div class="inside">
			<p class="description"><?php esc_html_e( 'Browse and search the Vinkius catalog of AI capabilities and connectors. Open any listing in the Vinkius cloud to connect it.', 'vinkius-connector' ); ?></p>
			<p class="vc-cap-search">
				<label class="screen-reader-text" for="vc-cap-input"><?php esc_html_e( 'Search capabilities and connectors', 'vinkius-connector' ); ?></label>
				<input type="search" id="vc-cap-input" class="regular-text" autocomplete="off"
					placeholder="<?php esc_attr_e( 'Search capabilities and connectors…', 'vinkius-connector' ); ?>" />
				<button type="button" class="button" id="vc-cap-clear"><?php esc_html_e( 'Clear', 'vinkius-connector' ); ?></button>
			</p>
			<p class="description" id="vc-cap-stats"></p>
		</div>
	</div>

	<div class="vc-cap-cats-wrap">
		<ul class="subsubsub vc-cap-cats" id="vc-cap-cats"></ul>
	</div>

	<div id="vc-cap-top"></div>

	<h2 class="vc-section-title vc-cap-catalog-title" id="vc-cap-catalog-title" hidden><?php esc_html_e( 'All connectors', 'vinkius-connector' ); ?></h2>

	<div id="vc-cap-results" class="vc-cap-results"></div>
	<div id="vc-cap-sentinel" class="vc-cap-sentinel"></div>
</div>
