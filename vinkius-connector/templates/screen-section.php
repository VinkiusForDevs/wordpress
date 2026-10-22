<?php
/**
 * AI Governance section page (shared by the 8 governance screens).
 *
 * @license GPLv2 or later
 *
 * @var string $section Governance section key.
 * @var string $title   Page title.
 * @var string $status  Connection state.
 * @var string $slug    Screen slug.
 * @var array  $links   Vinkius cloud deep links.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="vc-gov" class="vc-gov" data-section="<?php echo esc_attr( $section ); ?>" data-status="<?php echo esc_attr( $status ); ?>">

	<h2 class="vc-section-title"><?php echo esc_html( $title ); ?></h2>

	<nav class="nav-tab-wrapper vc-periods" id="vc-gov-periods" aria-label="<?php esc_attr_e( 'Reporting period', 'vinkius-connector' ); ?>"></nav>

	<div id="vc-gov-body" class="vc-section-body">
		<p class="vc-loading"><span class="spinner is-active"></span></p>
	</div>
</div>
