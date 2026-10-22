<?php
/**
 * Admin: menu, assets, dashboard widget.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Top-level "Vinkius" menu:
 *
 *   AI Connections · ─ · Mission Control · Agent Activity · Connector Traffic ·
 *   Access Tokens · Agent Passport · AI Spend · Capability Reliability ·
 *   Security Posture · Request Failures · Compliance & Evidence ·
 *   ─ · Incidents · Consent · ─ · Settings
 */
final class Admin {

	/**
	 * Register.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_head', array( $this, 'submenu_separators' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ) );
	}

	/**
	 * Menu.
	 *
	 * @return void
	 */
	public function menu() {
		$capability = 'manage_options';
		$title      = __( 'Vinkius AI Governance', 'vinkius-connector' );

		add_menu_page(
			$title,
			__( 'Vinkius', 'vinkius-connector' ),
			$capability,
			'vinkius-connector',
			array( ScreenConnect::class, 'render_page' ),
			'dashicons-shield',
			26
		);

		/*
		 * Ordered submenu. WordPress renders the top-level title as the first
		 * submenu entry, so the landing page reuses the parent slug and names
		 * itself "AI Connections". 'sep' rows become hairline dividers (see
		 * submenu_separators()).
		 */
		$items = array(
			array( 'page', 'connect', __( 'AI Connections', 'vinkius-connector' ), array( ScreenConnect::class, 'render_page' ) ),
			array( 'sep' ),
			array( 'page', 'mission-control', __( 'Mission Control', 'vinkius-connector' ), array( ScreenMissionControl::class, 'render_page' ) ),
			array( 'page', 'agent-activity', __( 'Agent Activity', 'vinkius-connector' ), array( ScreenAgentActivity::class, 'render_page' ) ),
			array( 'page', 'connector-traffic', __( 'Connector Traffic', 'vinkius-connector' ), array( ScreenConnectorTraffic::class, 'render_page' ) ),
			array( 'page', 'access-tokens', __( 'Access Tokens', 'vinkius-connector' ), array( ScreenAccessTokens::class, 'render_page' ) ),
			array( 'page', 'agent-passport', __( 'Agent Passport', 'vinkius-connector' ), array( ScreenAgentPassport::class, 'render_page' ) ),
			array( 'page', 'ai-spend', __( 'AI Spend', 'vinkius-connector' ), array( ScreenAiSpend::class, 'render_page' ) ),
			array( 'page', 'capability-reliability', __( 'Capability Reliability', 'vinkius-connector' ), array( ScreenCapabilityReliability::class, 'render_page' ) ),
			array( 'page', 'security-posture', __( 'Security Posture', 'vinkius-connector' ), array( ScreenSecurityPosture::class, 'render_page' ) ),
			array( 'page', 'request-failures', __( 'Request Failures', 'vinkius-connector' ), array( ScreenRequestFailures::class, 'render_page' ) ),
			array( 'page', 'compliance', __( 'Compliance & Evidence', 'vinkius-connector' ), array( ScreenCompliance::class, 'render_page' ) ),
			array( 'sep' ),
			array( 'page', 'incidents', __( 'Incidents', 'vinkius-connector' ), array( ScreenIncidents::class, 'render_page' ) ),
			array( 'page', 'consent', __( 'Consent', 'vinkius-connector' ), array( ScreenConsent::class, 'render_page' ) ),
			array( 'sep' ),
			array( 'page', 'settings', __( 'Settings', 'vinkius-connector' ), array( ScreenSettings::class, 'render_page' ) ),
		);

		$sep = 0;

		foreach ( $items as $item ) {
			if ( 'sep' === $item[0] ) {
				$sep++;
				add_submenu_page( 'vinkius-connector', '', '', $capability, 'vc-sep-' . $sep, '__return_false' );
				continue;
			}

			list( , $slug, $label, $callback ) = $item;

			add_submenu_page(
				'vinkius-connector',
				$title . ' — ' . $label,
				$label,
				$capability,
				'connect' === $slug ? 'vinkius-connector' : 'vinkius-connector-' . $slug,
				$callback
			);
		}
	}

	/**
	 * Render the submenu separators as hairline dividers.
	 *
	 * WordPress has no native submenu separator; the sep pages registered
	 * above are empty, unreachable items turned into dividers by CSS.
	 *
	 * @return void
	 */
	public function submenu_separators() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<style>' .
			'#toplevel_page_vinkius-connector .wp-submenu li a[href*="page=vc-sep-"] {' .
			'display:block;height:0;padding:0;margin:8px 0;border:0;' .
			'border-top:1px solid rgba(240,240,241,.6);background:none;' .
			'pointer-events:none;overflow:hidden;' .
			'}' .
			'</style>';
	}

	/**
	 * Widget on the main wp-admin dashboard.
	 *
	 * @return void
	 */
	public function dashboard_widget() {
		wp_add_dashboard_widget(
			'vc_dashboard',
			__( 'Vinkius AI Governance', 'vinkius-connector' ),
			array( __CLASS__, 'render_widget' )
		);
	}

	/**
	 * Widget body.
	 *
	 * @return void
	 */
	public static function render_widget() {
		$state  = \VinkiusConnector\Auth\AuthStore::status()['state'];
		$links  = \VinkiusConnector\Links::all();

		if ( 'connected' !== $state ) {
			printf(
				'<p>%s</p>',
				esc_html__( 'Connect your site to Vinkius to see governed agent operations here.', 'vinkius-connector' )
			);
		} else {
			printf(
				'<p>%s</p>',
				esc_html__( 'Agent operations routed through Vinkius are visible in the panel below.', 'vinkius-connector' )
			);
		}

		printf(
			'<p><a class="button button-primary" href="%1$s">%2$s</a> <a class="button" href="%3$s">%4$s</a></p>',
			esc_url( admin_url( 'admin.php?page=vinkius-connector' ) ),
			esc_html__( 'Open panel', 'vinkius-connector' ),
			esc_url( $links['servers'] ),
			esc_html__( 'Manage in Vinkius', 'vinkius-connector' )
		);
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Current page hook.
	 * @return void
	 */
	public function assets( $hook ) {
		if ( false === strpos( $hook, 'vinkius-connector' ) ) {
			return;
		}

		// Versioned by mtime so every deployed change bypasses the
		// browser cache instead of shipping a stale asset.
		$asset_version = function ( $rel ) {
			$path = VC_PLUGIN_DIR . $rel;

			return (string) ( is_readable( $path ) ? filemtime( $path ) : VC_VERSION );
		};

		wp_enqueue_style( 'vc-admin', VC_PLUGIN_URL . 'admin/css/vinkius-connector.css', array(), $asset_version( 'admin/css/vinkius-connector.css' ) );
		wp_enqueue_style( 'vc-governance', VC_PLUGIN_URL . 'admin/css/vc-governance.css', array(), $asset_version( 'admin/css/vc-governance.css' ) );
		wp_enqueue_script( 'vc-admin', VC_PLUGIN_URL . 'admin/js/vinkius-connector.js', array(), $asset_version( 'admin/js/vinkius-connector.js' ), true );
		wp_enqueue_script( 'vc-echarts', VC_PLUGIN_URL . 'admin/js/vendor/echarts.min.js', array(), '6.1.0', true );
		wp_enqueue_script( 'vc-charts', VC_PLUGIN_URL . 'admin/js/vc-charts.js', array( 'vc-echarts' ), $asset_version( 'admin/js/vc-charts.js' ), true );
		wp_enqueue_script( 'vc-governance', VC_PLUGIN_URL . 'admin/js/vc-governance.js', array( 'vc-admin', 'vc-charts' ), $asset_version( 'admin/js/vc-governance.js' ), true );

		wp_localize_script(
			'vc-admin',
			'VCConfig',
			array(
				'restUrl'   => rest_url( 'vinkius/v1/' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'links'     => \VinkiusConnector\Links::all(),
				'gov'       => array(
					'capabilityBase' => \VinkiusConnector\Links::capability( '' ),
					'upgrade'        => \VinkiusConnector\Links::cloud( 'upgrade' ),
				),
				'labels'    => array(
					'session_expired'   => __( 'Session expired. Log in again from Settings to see live data.', 'vinkius-connector' ),
					'load_error'        => __( 'Data could not be loaded. Try again or use a shorter period.', 'vinkius-connector' ),
					'no_data'           => __( 'No data in this period.', 'vinkius-connector' ),
					'live_activity'     => __( 'Live activity', 'vinkius-connector' ),
					'plan_usage'        => __( 'Plan & usage', 'vinkius-connector' ),
					'emergency_controls'=> __( 'Emergency controls', 'vinkius-connector' ),
					'coverage'          => __( 'Protection coverage', 'vinkius-connector' ),
					'subscribe'         => __( 'Subscribe to Vinkius', 'vinkius-connector' ),
					'circuit_tripped'   => __( 'Tripped', 'vinkius-connector' ),
					'circuit_open'      => __( 'Open', 'vinkius-connector' ),
					'tripped'           => __( 'Tripped', 'vinkius-connector' ),
					'open'              => __( 'Open', 'vinkius-connector' ),
					'reset_circuit'     => __( 'Reset circuit', 'vinkius-connector' ),
					'halted'            => __( 'Halted', 'vinkius-connector' ),
					'running'           => __( 'Running', 'vinkius-connector' ),
					'resume_agents'     => __( 'Resume agents', 'vinkius-connector' ),
					'halt_all'          => __( 'Halt all', 'vinkius-connector' ),
					'halt_manage'       => __( 'Manage per-connector halt in Vinkius', 'vinkius-connector' ),
					'confirm_halt_all'  => __( 'Halt all agents on this site?', 'vinkius-connector' ),
					'device_code'       => __( 'Device code', 'vinkius-connector' ),
					'device_open'       => __( 'Open approval page', 'vinkius-connector' ),
					'device_waiting'    => __( 'Waiting for approval…', 'vinkius-connector' ),
					'device_expired'    => __( 'Session expired. Start again.', 'vinkius-connector' ),
					'device_error'      => __( 'Could not start device login. Please try again.', 'vinkius-connector' ),
					'secret_copy'       => __( 'Copy', 'vinkius-connector' ),
					'secret_copied'     => __( 'Copied', 'vinkius-connector' ),
					// translators: 1: connector count, 2: capability count.
					'cap_search'        => __( 'Search capabilities and connectors…', 'vinkius-connector' ),
					'cap_catalog'       => __( '%1$s connectors · %2$s capabilities', 'vinkius-connector' ),
					// translators: %s: search term the user typed.
					'cap_no_matches'    => __( 'No matches for "%s".', 'vinkius-connector' ),
					'cap_try'           => __( 'Try rephrasing your search.', 'vinkius-connector' ),
					'cap_unavailable'   => __( 'Search is unavailable right now. Please try again.', 'vinkius-connector' ),
					'cap_none'          => __( 'No connectors in the catalog yet.', 'vinkius-connector' ),
					'cap_connected'     => __( 'Connected', 'vinkius-connector' ),
					'cap_open'          => __( 'Open in Vinkius', 'vinkius-connector' ),
					'cap_all'           => __( 'All', 'vinkius-connector' ),
					'cap_top'           => __( 'Top connections', 'vinkius-connector' ),
					'cap_new'           => __( 'New', 'vinkius-connector' ),
					'cap_capabilities'  => __( 'capabilities', 'vinkius-connector' ),
					'on'                => __( 'On', 'vinkius-connector' ),
					'off'               => __( 'Off', 'vinkius-connector' ),
					'by_client'         => __( 'By client', 'vinkius-connector' ),
					'connectors'        => __( 'Connectors', 'vinkius-connector' ),
					'access_tokens'     => __( 'Access tokens', 'vinkius-connector' ),
					'spend_by_server'   => __( 'Spend by server', 'vinkius-connector' ),
					'events_by_server'  => __( 'Events by server', 'vinkius-connector' ),
					'failures_by_server'=> __( 'Failures by server', 'vinkius-connector' ),
					'slowest_tools'     => __( 'Slowest tools', 'vinkius-connector' ),
					'most_failing'      => __( 'Most failing', 'vinkius-connector' ),
					'tool'              => __( 'Tool', 'vinkius-connector' ),
					'server'            => __( 'Server', 'vinkius-connector' ),
					'requests'          => __( 'Requests', 'vinkius-connector' ),
					'avg_latency'       => __( 'Avg latency', 'vinkius-connector' ),
					'errors'            => __( 'Errors', 'vinkius-connector' ),
					'time'              => __( 'Time', 'vinkius-connector' ),
					'activity'          => __( 'Activity', 'vinkius-connector' ),
					'status'            => __( 'Status', 'vinkius-connector' ),
					'client'            => __( 'Client', 'vinkius-connector' ),
					'connector'         => __( 'Connector', 'vinkius-connector' ),
					'transport'         => __( 'Transport', 'vinkius-connector' ),
					'tool_calls'        => __( 'Tool calls', 'vinkius-connector' ),
					'tokens'            => __( 'Tokens', 'vinkius-connector' ),
					'cost'              => __( 'Cost', 'vinkius-connector' ),
					'savings'           => __( 'Savings', 'vinkius-connector' ),
					'volume'            => __( 'Volume', 'vinkius-connector' ),
					'dlp_redactions'    => __( 'DLP redactions', 'vinkius-connector' ),
					'truncations'       => __( 'Truncations', 'vinkius-connector' ),
					'error_rate'        => __( 'Error rate', 'vinkius-connector' ),
					'bytes'             => __( 'Bytes', 'vinkius-connector' ),
					'generated'         => __( 'Generated', 'vinkius-connector' ),

					/* Charts + KPIs + tables — exact cloudfrontend en.json values. */
					'legend_dlp_redactions'    => __( 'DLP Redactions', 'vinkius-connector' ),
					'legend_finops_truncations' => __( 'FinOps Truncations', 'vinkius-connector' ),
					'legend_cost'             => __( 'Cost', 'vinkius-connector' ),
					'legend_savings'          => __( 'Savings', 'vinkius-connector' ),
					'legend_errors'           => __( 'Errors', 'vinkius-connector' ),
					'legend_avg_latency'      => __( 'Avg Latency', 'vinkius-connector' ),
					'legend_max_latency'      => __( 'Max Latency', 'vinkius-connector' ),
					'axis_errors'             => __( 'Errors', 'vinkius-connector' ),
					'axis_rate_percent'       => __( 'Rate %', 'vinkius-connector' ),
					'axis_avg_latency'        => __( 'Avg Latency', 'vinkius-connector' ),
					'axis_failure_rate'       => __( 'Failure Rate (%)', 'vinkius-connector' ),
					'no_data_available'       => __( 'No data available', 'vinkius-connector' ),
					'not_available'           => __( 'Not available', 'vinkius-connector' ),
					'never'                   => __( 'Never', 'vinkius-connector' ),
					'just_now'                => __( 'just now', 'vinkius-connector' ),
					'minutes_ago'             => __( 'minutes ago', 'vinkius-connector' ),
					'hours_ago'               => __( 'hours ago', 'vinkius-connector' ),
					'days_ago'                => __( 'days ago', 'vinkius-connector' ),
					'health_healthy'          => __( 'Healthy', 'vinkius-connector' ),
					'health_moderate'         => __( 'Moderate', 'vinkius-connector' ),
					'health_slow'             => __( 'Slow', 'vinkius-connector' ),
					'health_unknown'          => __( 'Unknown', 'vinkius-connector' ),
					'latency_tooltip_total'   => __( 'Total: {total}', 'vinkius-connector' ),
					'latency_tooltip_breakdown' => __( 'API: {api} | Vinkius: {vinkius} | Total: {total}', 'vinkius-connector' ),
					'vinkius_overhead'        => __( 'Vinkius Overhead', 'vinkius-connector' ),
					'col_requests'            => __( 'Requests', 'vinkius-connector' ),
					'col_total'              => __( 'Total', 'vinkius-connector' ),
					'col_tokens'             => __( 'Tokens', 'vinkius-connector' ),
					'col_health'             => __( 'Health', 'vinkius-connector' ),
					'col_api'                => __( 'API', 'vinkius-connector' ),
					'col_overhead'           => __( 'Overhead', 'vinkius-connector' ),
					'col_connector'          => __( 'Connector', 'vinkius-connector' ),
					'col_capability'         => __( 'Capability', 'vinkius-connector' ),
					'col_errors'             => __( 'Errors', 'vinkius-connector' ),
					'col_rate'               => __( 'Rate', 'vinkius-connector' ),
					'col_avg'                => __( 'Avg', 'vinkius-connector' ),
					'col_max'                => __( 'Max', 'vinkius-connector' ),
					'col_fail_rate'          => __( 'Fail Rate', 'vinkius-connector' ),
					'col_dlp'                => __( 'DLP', 'vinkius-connector' ),
					'col_finops'             => __( 'FinOps', 'vinkius-connector' ),
					'col_avg_latency'        => __( 'Avg Latency', 'vinkius-connector' ),
					'col_capabilities'        => __( 'Capabilities', 'vinkius-connector' ),
					'total_latency'          => __( 'Total Latency', 'vinkius-connector' ),
					'api_response'           => __( 'API Response', 'vinkius-connector' ),
					'agent'                  => __( 'Agent', 'vinkius-connector' ),
					'upstream'               => __( 'Upstream', 'vinkius-connector' ),
					'vinkius'                => __( 'Vinkius', 'vinkius-connector' ),
					'chart_api_overhead'     => __( 'API: {api} · Overhead: {overhead}', 'vinkius-connector' ),
					'chart_api_vinkius'      => __( 'API: {api} · Vinkius: {vinkius}', 'vinkius-connector' ),
					'chart_agent_upstream_vinkius' => __( 'Agent: {agent} · Upstream: {upstream} · Vinkius: {vinkius}', 'vinkius-connector' ),
					'chart_requests_rate'    => __( 'Requests: {requests} · Rate: {rate}%', 'vinkius-connector' ),
					'chart_errors_of_requests' => __( 'Errors: {errors} of {requests} requests · {rate}%', 'vinkius-connector' ),
					'chart_connections'       => __( '{count} connections ({pct}%)', 'vinkius-connector' ),
					'punch_card_at'          => __( '{day} at {hour}', 'vinkius-connector' ),
					'data_protected'         => __( 'Data Protected', 'vinkius-connector' ),
					'interaction_one'        => __( '{count} interaction', 'vinkius-connector' ),
					'interaction_many'       => __( '{count} interactions', 'vinkius-connector' ),
					'high_volume'            => __( 'High Volume', 'vinkius-connector' ),
					'details'                => __( 'Details', 'vinkius-connector' ),
					'expand_chart'           => __( 'Expand chart', 'vinkius-connector' ),
					'close'                  => __( 'Close', 'vinkius-connector' ),
					'prev_page'              => __( 'Previous', 'vinkius-connector' ),
					'next_page'              => __( 'Next', 'vinkius-connector' ),
					'pag_range'              => __( '{from} to {to} of {total}', 'vinkius-connector' ),
					'period_today'           => __( 'Today', 'vinkius-connector' ),
					'period_3d'              => __( '3 days', 'vinkius-connector' ),
					'period_7d'              => __( '7 days', 'vinkius-connector' ),
					'period_14d'             => __( '14 days', 'vinkius-connector' ),
					'period_30d'             => __( '30 days', 'vinkius-connector' ),
					'day_sun'                => __( 'Sunday', 'vinkius-connector' ),
					'day_mon'                => __( 'Monday', 'vinkius-connector' ),
					'day_tue'                => __( 'Tuesday', 'vinkius-connector' ),
					'day_wed'                => __( 'Wednesday', 'vinkius-connector' ),
					'day_thu'                => __( 'Thursday', 'vinkius-connector' ),
					'day_fri'                => __( 'Friday', 'vinkius-connector' ),
					'day_sat'                => __( 'Saturday', 'vinkius-connector' ),
					'error_origin_breakdown' => __( 'Agent: {agentCount} · Upstream: {upstreamCount} · Vinkius: {vinkiusCount}', 'vinkius-connector' ),
					'api_overhead_sub'       => __( 'API: {apiValue} · Overhead: {overhead}', 'vinkius-connector' ),

					/* Mission Control (overview). */
					'ov_kpi_requests'         => __( 'Requests', 'vinkius-connector' ),
					'ov_kpi_requests_hint'    => __( 'Total in this period', 'vinkius-connector' ),
					'ov_kpi_avg_latency'      => __( 'Avg Latency', 'vinkius-connector' ),
					'ov_kpi_reliability'      => __( 'Vinkius Reliability', 'vinkius-connector' ),
					'ov_kpi_tokens'           => __( 'Tokens', 'vinkius-connector' ),
					'ov_kpi_tokens_hint'      => __( 'AI tokens processed', 'vinkius-connector' ),
					'ov_kpi_dlp_protected'   => __( 'DLP Protected', 'vinkius-connector' ),
					'ov_kpi_dlp_hint'         => __( 'Sensitive data redacted', 'vinkius-connector' ),
					'ov_kpi_cost_saved'       => __( 'Cost Saved', 'vinkius-connector' ),
					'ov_kpi_cost_hint'        => __( 'FinOps truncations applied', 'vinkius-connector' ),
					'ov_kpi_no_data'          => __( 'No data', 'vinkius-connector' ),
					'ov_cadence_hourly'       => __( 'Hourly', 'vinkius-connector' ),
					'ov_cadence_daily'        => __( 'Daily', 'vinkius-connector' ),
					'ov_chart_agents'         => __( 'AI Agents Activities', 'vinkius-connector' ),
					'ov_agents_sub_hourly'    => __( 'Interactions per hour', 'vinkius-connector' ),
					'ov_agents_sub_daily'     => __( 'Interactions per day', 'vinkius-connector' ),
					'ov_chart_volume'         => __( 'Request Volume & Latency', 'vinkius-connector' ),
					'ov_volume_sub'           => __( '{cadence} dual-axis overview', 'vinkius-connector' ),
					'ov_chart_top'            => __( 'Top Connectors', 'vinkius-connector' ),
					'ov_top_sub'              => __( 'Volume by connector · color = latency health', 'vinkius-connector' ),
					'ov_chart_firewall'       => __( 'Firewall Enforcement', 'vinkius-connector' ),
					'ov_firewall_sub'         => __( '{cadence} DLP + FinOps actions', 'vinkius-connector' ),
					'ov_chart_cost'           => __( 'Cost vs Savings', 'vinkius-connector' ),
					'ov_cost_sub'             => __( '{cadence} token cost attribution', 'vinkius-connector' ),
					'ov_chart_failures'       => __( 'Failure Breakdown', 'vinkius-connector' ),
					'ov_failures_sub'         => __( '{cadence} requests · Agent / Upstream / Vinkius failures', 'vinkius-connector' ),
					'ov_chart_matrix'         => __( 'Capability Health Matrix', 'vinkius-connector' ),
					'ov_matrix_sub'           => __( 'Latency × Failure rate · bubble size = request volume', 'vinkius-connector' ),
					'ov_feed_title'           => __( 'Live Activity', 'vinkius-connector' ),
					'ov_feed_sub'             => __( 'Latest capability executions across all connectors', 'vinkius-connector' ),
					'ov_feed_empty'           => __( 'No activity recorded in this period.', 'vinkius-connector' ),
					'ov_outcome'             => __( 'Outcome', 'vinkius-connector' ),
					'ov_ok'                   => __( 'OK', 'vinkius-connector' ),
					'ov_enforced'             => __( 'Enforced', 'vinkius-connector' ),
					'ov_agent_err'            => __( 'Agent Err', 'vinkius-connector' ),
					'ov_upstream_err'         => __( 'Upstream Err', 'vinkius-connector' ),
					'ov_vinkius_err'          => __( 'Vinkius Err', 'vinkius-connector' ),
					'ov_dlp_one'             => __( '{count} DLP redaction', 'vinkius-connector' ),
					'ov_dlp_many'            => __( '{count} DLP redactions', 'vinkius-connector' ),
					'ov_enforced_finops'     => __( 'FinOps truncated oversized payload', 'vinkius-connector' ),
					'briefing_title'         => __( 'AI Briefing', 'vinkius-connector' ),
					'briefing_unlock'        => __( 'Upgrade to unlock', 'vinkius-connector' ),
					'briefing_desc_overview' => __( 'Know exactly what needs your attention before you even look at the charts. One briefing. The full picture. The next move.', 'vinkius-connector' ),
					'briefing_desc_clients' => __( 'Stop guessing which agents matter. See who drives value, who drains resources, and what your connection patterns are really telling you.', 'vinkius-connector' ),
					'briefing_desc_connectors' => __( 'Spot the connector that is about to become a problem before your users do. Fleet health, latency trends, and traffic hotspots in one read.', 'vinkius-connector' ),
					'briefing_desc_cost'    => __( 'Find the spend you did not know you had. Pinpoint the connectors costing you the most, measure your policy savings, and know exactly where to cut next.', 'vinkius-connector' ),
					'briefing_desc_tool_performance' => __( 'See which capabilities are slowing everything down and which ones are failing silently. Ranked, diagnosed, and ready for you to act on.', 'vinkius-connector' ),
					'briefing_desc_security' => __( 'Know your blind spots before they become incidents. Threat interceptions, policy coverage, and compliance readiness scored and explained.', 'vinkius-connector' ),
					'briefing_desc_errors'  => __( 'Trace every failure back to its source. See the exact combination of connector and capability behind the noise, and whether things are getting better or worse.', 'vinkius-connector' ),
					'briefing_desc_token_fleet' => __( 'Audit every token across your fleet. See which tokens are active, which are stale, and where your security perimeter needs reinforcement.', 'vinkius-connector' ),
					'briefing_desc_agents'   => __( 'Audit every credential that can act for your organization. Identity chains, least-privilege gaps, and dormant agents — with the next cleanup step ready.', 'vinkius-connector' ),
					'briefing_desc_compliance' => __( 'See your governance posture as an auditor would. Readiness grade, open control gaps, and the evidence that backs every answer — all from your live ledgers.', 'vinkius-connector' ),

					/* Agent Activity (clients). */
					'cl_kpi_connections'      => __( 'Connections', 'vinkius-connector' ),
					'cl_kpi_connections_hint' => __( 'Active sessions in period', 'vinkius-connector' ),
					'cl_kpi_capability_requests' => __( 'Capability Requests', 'vinkius-connector' ),
					'cl_kpi_req_per_session'  => __( '{count} requests/session', 'vinkius-connector' ),
					'cl_kpi_data_transfer'    => __( 'Data Transfer', 'vinkius-connector' ),
					'cl_kpi_data_transfer_hint' => __( 'Total payload volume', 'vinkius-connector' ),
					'cl_kpi_avg_session'      => __( 'Avg Session', 'vinkius-connector' ),
					'cl_kpi_avg_session_hint' => __( 'Average connection duration', 'vinkius-connector' ),
					'cl_chart_token_title'    => __( 'Token Distribution', 'vinkius-connector' ),
					'cl_chart_token_sub'      => __( 'Connections per client, stacked by token', 'vinkius-connector' ),
					'cl_chart_cap_title'      => __( 'Capability Usage by Client', 'vinkius-connector' ),
					'cl_chart_cap_sub'        => __( 'Total capability requests · color = latency health', 'vinkius-connector' ),
					'tb_ai_client'            => __( 'AI Client', 'vinkius-connector' ),
					'tb_connections'          => __( 'Connections', 'vinkius-connector' ),
					'tb_capability_requests'  => __( 'Capability Requests', 'vinkius-connector' ),
					'tb_data_transfer'        => __( 'Data Transfer', 'vinkius-connector' ),
					'tb_avg_session'          => __( 'Avg Session', 'vinkius-connector' ),
					'tb_req_per_day'          => __( 'Req/Day', 'vinkius-connector' ),
					'clients_empty'           => __( 'No AI client connections recorded in this period.', 'vinkius-connector' ),

					/* Connector Traffic. */
					'cn_kpi_active'           => __( 'Active Connectors', 'vinkius-connector' ),
					'cn_kpi_active_hint'      => __( 'Connectors with traffic in period', 'vinkius-connector' ),
					'cn_kpi_total_requests'   => __( 'Total Requests', 'vinkius-connector' ),
					'cn_kpi_peak_hour'       => __( 'Peak Hour', 'vinkius-connector' ),
					'cn_kpi_peak_hour_hint'   => __( 'Highest traffic window', 'vinkius-connector' ),
					'cn_chart_traffic_title'  => __( 'Traffic Distribution', 'vinkius-connector' ),
					'cn_traffic_sub'          => __( 'Requests by day of week × hour', 'vinkius-connector' ),
					'cn_chart_volume_title'   => __( 'Request Volume by Connector', 'vinkius-connector' ),
					'cn_volume_sub'           => __( 'Total requests · color = latency health', 'vinkius-connector' ),
					'connectors_empty'        => __( 'No connectors recorded in this period.', 'vinkius-connector' ),

					/* Access Tokens (token-fleet). */
					'tf_kpi_connectors'       => __( 'Connectors', 'vinkius-connector' ),
					'tf_connectors_hint'      => __( 'With active tokens', 'vinkius-connector' ),
					'tf_kpi_total_tokens'     => __( 'Total Tokens', 'vinkius-connector' ),
					'tf_across_connectors'    => __( 'Across all connectors', 'vinkius-connector' ),
					'tf_kpi_total_requests'   => __( 'Total Requests', 'vinkius-connector' ),
					'tf_requests_hint'        => __( 'In selected period', 'vinkius-connector' ),
					'tf_kpi_avg_latency'      => __( 'Avg Latency', 'vinkius-connector' ),
					'tf_token_one'           => __( '{count} token', 'vinkius-connector' ),
					'tf_token_many'           => __( '{count} tokens', 'vinkius-connector' ),
					'tf_req'                 => __( '{count} req', 'vinkius-connector' ),
					'tf_avg'                 => __( '{value} avg', 'vinkius-connector' ),
					'tf_search'              => __( 'Search MCP or token name...', 'vinkius-connector' ),
					'tf_col_name'            => __( 'Token Name', 'vinkius-connector' ),
					'tf_col_hint'            => __( 'Hint', 'vinkius-connector' ),
					'tf_col_last_used'       => __( 'Last Used', 'vinkius-connector' ),
					'tf_col_status'          => __( 'Status', 'vinkius-connector' ),
					'tf_status_active'       => __( 'Active', 'vinkius-connector' ),
					'tf_status_disabled'     => __( 'Disabled', 'vinkius-connector' ),
					'tf_empty_tokens'        => __( 'No tokens found for this connector.', 'vinkius-connector' ),
					'tf_empty_connectors'    => __( 'No connectors with tokens found.', 'vinkius-connector' ),
					'tf_loading'             => __( 'Loading tokens…', 'vinkius-connector' ),
					'tf_load_failed'          => __( 'Failed to load tokens. Collapse and expand again to retry.', 'vinkius-connector' ),
					'tf_try_different'        => __( 'Try a different search term.', 'vinkius-connector' ),

					/* Agent Passport (agents). */
					'ag_registry_title'       => __( 'Credential calling overview', 'vinkius-connector' ),
					'ag_registry_hint'        => __( 'Every credential that places calls on behalf of this organization, comparing the tools it may invoke with those it actually used over the selected window.', 'vinkius-connector' ),
					'ag_kpi_agents'           => __( 'Agents', 'vinkius-connector' ),
					'ag_agents_hint'          => __( 'Credentials with a full identity chain', 'vinkius-connector' ),
					'ag_kpi_active'           => __( 'Active', 'vinkius-connector' ),
					'ag_active_hint'          => __( 'With calls in the last 7 days', 'vinkius-connector' ),
					'ag_kpi_zombie'           => __( 'Zombie', 'vinkius-connector' ),
					'ag_zombie_hint'         => __( 'Enabled without a call for over 30 days', 'vinkius-connector' ),
					'ag_kpi_over'            => __( 'Over-provisioned', 'vinkius-connector' ),
					'ag_over_hint'           => __( 'Holding granted tools that remain unused', 'vinkius-connector' ),
					'ag_kpi_destructive'     => __( 'Destructive reach', 'vinkius-connector' ),
					'ag_destructive_hint'    => __( 'Agents with access to destructive tools', 'vinkius-connector' ),
					'ag_kpi_median'          => __( 'Least-privilege median', 'vinkius-connector' ),
					'ag_kind_app_user'       => __( 'Connect SDK user', 'vinkius-connector' ),
					'ag_kind_connection'     => __( 'Org automation', 'vinkius-connector' ),
					'ag_lc_active'           => __( 'Active', 'vinkius-connector' ),
					'ag_lc_dormant'          => __( 'Dormant', 'vinkius-connector' ),
					'ag_lc_zombie'           => __( 'Zombie', 'vinkius-connector' ),
					'ag_lc_disabled'         => __( 'Disabled', 'vinkius-connector' ),
					'ag_lc_never_used'       => __( 'Never used', 'vinkius-connector' ),
					'ag_lc_hint_active'      => __( 'Placed a call within the last 7 days.', 'vinkius-connector' ),
					'ag_lc_hint_dormant'     => __( 'Last called between 8 and 30 days ago.', 'vinkius-connector' ),
					'ag_lc_hint_zombie'      => __( 'Enabled for over 30 days without a single call.', 'vinkius-connector' ),
					'ag_lc_hint_disabled'    => __( 'The credential is disabled and cannot place calls.', 'vinkius-connector' ),
					'ag_lc_hint_never_used'  => __( 'Issued, with no call on record.', 'vinkius-connector' ),
					'ag_priv_exact'          => __( 'Uses its full grant: {used} of {granted} tools, across {calls} calls in the window.', 'vinkius-connector' ),
					'ag_priv_partial'        => __( 'Holds {granted} tools but used only {used} in the window, leaving {unused} granted tools unused.', 'vinkius-connector' ),
					'ag_priv_idle'           => __( 'Holds {granted} granted tools and used none of them in the window.', 'vinkius-connector' ),
					'ag_priv_no_tools'       => __( 'The connection grants no enabled tools, so this credential cannot place any call.', 'vinkius-connector' ),
					'ag_destructive_sentence' => __( '{n} of its granted tools can destroy data.', 'vinkius-connector' ),
					'ag_identity_sdk'        => __( 'App user of {application} · on behalf of {appUser}', 'vinkius-connector' ),
					'ag_identity_connection' => __( 'Automation over {connection}', 'vinkius-connector' ),
					'ag_window'              => __( 'Window: the last {hours} hours covered by your plan.', 'vinkius-connector' ),
					'ag_median_sentence'     => __( 'Median score: {percent}% of granted tools actually used.', 'vinkius-connector' ),
					'ag_median_none'         => __( 'No agent holds enabled tools, so no least-privilege score exists yet.', 'vinkius-connector' ),
					'ag_band_identity'       => __( 'Identity chain', 'vinkius-connector' ),
					'ag_band_permissions'    => __( 'Permissions', 'vinkius-connector' ),
					'ag_band_usage'         => __( 'Usage in window', 'vinkius-connector' ),
					'ag_via_connection'      => __( 'Over this connection', 'vinkius-connector' ),
					'ag_end_user'            => __( 'End user the SDK token represents', 'vinkius-connector' ),
					'ag_granted'             => __( 'granted', 'vinkius-connector' ),
					'ag_used'                => __( 'used', 'vinkius-connector' ),
					'ag_unused'              => __( 'unused', 'vinkius-connector' ),
					'ag_calls'               => __( 'calls', 'vinkius-connector' ),
					'ag_errors'              => __( 'errors', 'vinkius-connector' ),
					'ag_last_used'           => __( 'Last call: {when}', 'vinkius-connector' ),
					'ag_disabled_chip'       => __( 'Disabled', 'vinkius-connector' ),
					'ag_empty'               => __( 'No credentials are active yet. Once a connection or an SDK token places its first call, its passport appears here.', 'vinkius-connector' ),
					'ag_export_title'        => __( 'Printable inventory', 'vinkius-connector' ),
					'ag_export_hint'         => __( 'One document with the full registry: identity, permissions and usage per credential, ready for your security review.', 'vinkius-connector' ),
					'ag_export_button'       => __( 'Export inventory', 'vinkius-connector' ),
					'ag_export_paid_only'    => __( 'Paid plans', 'vinkius-connector' ),
					'ag_export_failed'       => __( 'The registry could not be exported. Please try again in a moment.', 'vinkius-connector' ),

					/* AI Spend (cost). */
					'cost_kpi_estimated'     => __( 'Estimated Cost', 'vinkius-connector' ),
					'cost_tokens_consumed'   => __( '{count} tokens consumed', 'vinkius-connector' ),
					'cost_kpi_savings'       => __( 'FinOps Savings', 'vinkius-connector' ),
					'cost_tokens_saved'      => __( '{count} tokens saved', 'vinkius-connector' ),
					'cost_kpi_per_request'   => __( 'Cost per Request', 'vinkius-connector' ),
					'cost_kpi_roi'           => __( 'FinOps ROI', 'vinkius-connector' ),
					'cost_roi_hint'          => __( 'DLP: {dlp} · FinOps: {finops}', 'vinkius-connector' ),
					'cost_trend_title'       => __( 'Cost & Savings Trend', 'vinkius-connector' ),
					'cost_trend_sub_daily'   => __( 'Daily estimated spend and savings over time', 'vinkius-connector' ),
					'cost_trend_sub_hourly'  => __( 'Hourly estimated spend and savings over time', 'vinkius-connector' ),
					'cost_pie_title'         => __( 'Cost Distribution', 'vinkius-connector' ),
					'cost_pie_sub'           => __( 'Spend proportion by connector', 'vinkius-connector' ),
					'cost_by_connector_title' => __( 'Cost by Connector', 'vinkius-connector' ),
					'cost_by_connector_sub'  => __( 'Token consumption in USD per connector', 'vinkius-connector' ),
					'cost_table_title'       => __( 'Connector Cost Detail', 'vinkius-connector' ),
					'cost_empty'             => __( 'No cost data recorded in this period.', 'vinkius-connector' ),
					'tb_cost'                => __( 'Cost', 'vinkius-connector' ),
					'tb_saved'               => __( 'Saved', 'vinkius-connector' ),
					'tb_savings'             => __( 'Savings', 'vinkius-connector' ),
					'tb_dlp_events'          => __( 'DLP Events', 'vinkius-connector' ),
					'tb_finops_events'       => __( 'FinOps Events', 'vinkius-connector' ),

					/* Capability Reliability (tool-performance). */
					'tp_kpi_total_requests'  => __( 'Total Requests', 'vinkius-connector' ),
					// translators: %s: success percentage (the braces are a UI template token, not printf).
					'tp_success_rate'        => __( '{pct}% success rate', 'vinkius-connector' ),
					'tp_kpi_unique'          => __( 'Unique Capabilities', 'vinkius-connector' ),
					'tp_unique_hint'         => __( 'Active in this period', 'vinkius-connector' ),
					'tp_kpi_avg_latency'     => __( 'Avg Latency', 'vinkius-connector' ),
					'tp_kpi_total_errors'    => __( 'Total Errors', 'vinkius-connector' ),
					'tp_chart_slowest_title' => __( 'Slowest Capabilities', 'vinkius-connector' ),
					'tp_slowest_sub'         => __( 'Avg latency (amber) · max spike (red)', 'vinkius-connector' ),
					'tp_chart_failing_title' => __( 'Most Failing Capabilities', 'vinkius-connector' ),
					'tp_chart_matrix_title'  => __( 'Health Matrix', 'vinkius-connector' ),
					'tp_matrix_sub'          => __( 'Latency × Failure rate · bubble = request volume', 'vinkius-connector' ),
					'tp_table_slowest_title' => __( 'Slowest Capabilities Detail', 'vinkius-connector' ),
					'tp_table_failing_title' => __( 'Most Failing Capabilities Detail', 'vinkius-connector' ),
					'tb_last_seen'           => __( 'Last seen', 'vinkius-connector' ),
					'tp_empty_slowest'       => __( 'No capability performance data recorded in this period.', 'vinkius-connector' ),
					'tp_empty_failing'       => __( 'No capability error data recorded in this period.', 'vinkius-connector' ),

					/* Security Posture. */
					'sec_kpi_firewall'       => __( 'Firewall Events', 'vinkius-connector' ),
					'sec_requests_inspected'  => __( '{count} requests inspected', 'vinkius-connector' ),
					'sec_kpi_dlp'            => __( 'DLP Redactions', 'vinkius-connector' ),
					'sec_kpi_finops'          => __( 'FinOps Truncations', 'vinkius-connector' ),
					'sec_kpi_data'           => __( 'Data Protected', 'vinkius-connector' ),
					'sec_total_processed'    => __( '{count} total processed', 'vinkius-connector' ),
					'sec_of_events'          => __( '{pct}% of firewall events', 'vinkius-connector' ),
					'sec_kpi_enforcement'    => __( 'Enforcement Rate', 'vinkius-connector' ),
					'sec_enforcement_hint'   => __( 'Events per request ratio', 'vinkius-connector' ),
					'sec_kpi_inspected_card' => __( 'Requests Inspected', 'vinkius-connector' ),
					'sec_all_through'        => __( 'All traffic through firewall', 'vinkius-connector' ),
					'sec_kpi_payload'        => __( 'Payload Volume', 'vinkius-connector' ),
					'sec_payload_hint'       => __( 'Total data volume inspected', 'vinkius-connector' ),
					'sec_chart_title'        => __( 'Firewall Activity', 'vinkius-connector' ),
					'sec_chart_sub'          => __( 'Daily DLP and FinOps enforcement actions', 'vinkius-connector' ),
					'sec_coverage_title'     => __( 'Compliance Coverage', 'vinkius-connector' ),
					'sec_coverage_approval'   => __( 'Approval', 'vinkius-connector' ),
					'sec_coverage_count'     => __( '{active}/{total}', 'vinkius-connector' ),
					'sec_table_title'        => __( 'Enforcement by Connector', 'vinkius-connector' ),
					'sec_table_sub'          => __( 'Firewall activity and active policies per connector', 'vinkius-connector' ),
					'sec_empty'              => __( 'No security data recorded in this period.', 'vinkius-connector' ),
					'tb_mcp_server'          => __( 'MCP Server', 'vinkius-connector' ),
					'tb_approval'            => __( 'Approval', 'vinkius-connector' ),
					'tb_bytes_saved'          => __( 'Bytes Saved', 'vinkius-connector' ),

					/* Request Failures (errors). */
					'err_kpi_total'          => __( 'Total Errors', 'vinkius-connector' ),
					'err_of_requests'        => __( 'of {count} requests', 'vinkius-connector' ),
					'err_kpi_rate'          => __( 'Failure Rate', 'vinkius-connector' ),
					'err_kpi_worst_connector' => __( 'Worst Connector', 'vinkius-connector' ),
					'err_worst_connector_hint' => __( 'Most erroring connector', 'vinkius-connector' ),
					'err_kpi_worst_capability' => __( 'Worst Capability', 'vinkius-connector' ),
					'err_worst_capability_hint' => __( 'Most erroring capability', 'vinkius-connector' ),
					'err_timeline_title'    => __( 'Failure Timeline', 'vinkius-connector' ),
					'err_timeline_sub'       => __( 'Errors (red) · Agent / Upstream / Vinkius rates', 'vinkius-connector' ),
					'err_transport_title'    => __( 'Transport Distribution', 'vinkius-connector' ),
					'err_transport_sub'      => __( 'Connection protocol breakdown', 'vinkius-connector' ),
					'err_server_title'       => __( 'Errors by Connector', 'vinkius-connector' ),
					'err_server_sub'         => __( 'Stacked by Agent / Upstream / Vinkius', 'vinkius-connector' ),
					'err_table_title'        => __( 'Error Distribution by Connector', 'vinkius-connector' ),
					'errors_empty'           => __( 'No error data recorded in this period.', 'vinkius-connector' ),

					/* Compliance & Evidence. */
					'cp_health_label'       => __( 'Governance readiness', 'vinkius-connector' ),
					'cp_hero_sub'           => __( 'Each answer below is quoted straight from your live ledgers.', 'vinkius-connector' ),
					'cp_q_data'             => __( 'Is my data safe while the AI works?', 'vinkius-connector' ),
					'cp_q_stop'             => __( 'Can I stop it if it misbehaves?', 'vinkius-connector' ),
					'cp_q_prove'            => __( 'Can I prove what the AI did?', 'vinkius-connector' ),
					'cp_grade_ready'        => __( 'Ready, nothing blocking', 'vinkius-connector' ),
					'cp_grade_watch'        => __( 'Almost there, a few settings to flip', 'vinkius-connector' ),
					'cp_grade_attention'    => __( 'Needs attention, close the gaps below', 'vinkius-connector' ),
					'cp_gaps_title'         => __( 'What to do about it', 'vinkius-connector' ),
					'cp_gaps_sub'           => __( 'Plain-language steps: flip a policy once and it covers every connection.', 'vinkius-connector' ),
					'cp_gaps_none'          => __( 'Nothing to fix. Every control is fully in force.', 'vinkius-connector' ),
					'cp_adv_title'          => __( 'Reference: frameworks & ledgers', 'vinkius-connector' ),
					'cp_adv_hint'           => __( 'The layer an auditor wants: control-by-control mapping and the raw counters behind it.', 'vinkius-connector' ),
					'cp_evidence_title'     => __( 'Ledger evidence', 'vinkius-connector' ),
					'cp_evidence_sub'       => __( 'Counters read straight from your live ledgers, the receipts behind the scorecards above.', 'vinkius-connector' ),
					'cp_ev_breaker'        => __( 'Circuit-breaker events', 'vinkius-connector' ),
					'cp_ev_chains'         => __( 'Deploy chains valid', 'vinkius-connector' ),
					'cp_ev_connections'    => __( 'Connections', 'vinkius-connector' ),
					'cp_ev_deploy_rows'    => __( 'Deploy ledger rows', 'vinkius-connector' ),
					'cp_ev_org_audit'      => __( 'Org audit events', 'vinkius-connector' ),
					'cp_ev_sealed'         => __( 'Sealed request receipts', 'vinkius-connector' ),
					'cp_pack_title'        => __( 'Evidence pack', 'vinkius-connector' ),
					'cp_pack_desc'         => __( 'A frozen, re-verifiable snapshot of your governance evidence. Share it with a board, regulator, or auditor.', 'vinkius-connector' ),
					'cp_pack_download'     => __( 'Download evidence pack', 'vinkius-connector' ),
					'cp_status_satisfied'   => __( 'Satisfied', 'vinkius-connector' ),
					'cp_status_partial'     => __( 'Partial', 'vinkius-connector' ),
					'cp_status_missing'     => __( 'Missing', 'vinkius-connector' ),
					'cp_answer_yes'        => __( 'Yes', 'vinkius-connector' ),
					'cp_answer_partly'      => __( 'Partly', 'vinkius-connector' ),
					'cp_answer_missing'     => __( 'Not yet', 'vinkius-connector' ),
					'cp_ctrl_satisfied'    => __( '{n} satisfied', 'vinkius-connector' ),
					'cp_ctrl_partial'      => __( '{n} partial', 'vinkius-connector' ),
					'cp_ctrl_missing'      => __( '{n} missing', 'vinkius-connector' ),
					'cp_q_data_detail'     => __( 'Data masking on {dlp_on}/{connections} connections · spend limits on {finops_on}/{connections}.', 'vinkius-connector' ),
					'cp_q_stop_detail'     => __( 'Two-person approval on {approval_on}/{connections} connections · {breaker_events} automatic stops in retention.', 'vinkius-connector' ),
					'cp_q_prove_detail'    => __( '{sealed} sealed call receipts · {deploys} sealed deploys · chains {chains_valid}.', 'vinkius-connector' ),

					/* Shared table columns. */
					'tb_connector'          => __( 'Connector', 'vinkius-connector' ),
					'tb_requests'           => __( 'Requests', 'vinkius-connector' ),
					'tb_tokens'             => __( 'Tokens', 'vinkius-connector' ),
					'tb_api'               => __( 'API', 'vinkius-connector' ),
					'tb_overhead'           => __( 'Overhead', 'vinkius-connector' ),
					'tb_total'              => __( 'Total', 'vinkius-connector' ),
					'tb_rate'               => __( 'Rate', 'vinkius-connector' ),
					'tb_errors'             => __( 'Errors', 'vinkius-connector' ),
					'tb_health'             => __( 'Health', 'vinkius-connector' ),
					'tb_dlp'                => __( 'DLP', 'vinkius-connector' ),
					'tb_finops'             => __( 'FinOps', 'vinkius-connector' ),
					'tb_time'               => __( 'Time', 'vinkius-connector' ),
					'tb_token'              => __( 'Token', 'vinkius-connector' ),
					'tb_traces'             => __( 'Trace', 'vinkius-connector' ),
					'tb_outcome'            => __( 'Outcome', 'vinkius-connector' ),

					/* Completion: remaining chart/KPI/table keys. */
					'open_in_vinkius'       => __( 'Open in Vinkius', 'vinkius-connector' ),
					'ov_vinkius_err_title'  => __( 'Vinkius error {code}. Failure originated inside the Vinkius proxy.', 'vinkius-connector' ),
					'ov_enforced_title'     => __( 'Vinkius policy applied in-flight: {parts}. Upstream returned {code}. Enforcement working as designed.', 'vinkius-connector' ),
					'ov_ok_title'          => __( 'Upstream returned {code}.', 'vinkius-connector' ),
					'ov_agent_err_title'   => __( 'Client error {code}. The caller sent a bad request.', 'vinkius-connector' ),
					'ov_upstream_err_title' => __( 'Upstream error {code}. The upstream provider failed.', 'vinkius-connector' ),
					'ov_feed_more'          => __( 'Showing the latest {count} of {total} in this period.', 'vinkius-connector' ),
					'cl_col_connectors'     => __( 'Connectors', 'vinkius-connector' ),
					'time_am'               => __( 'AM', 'vinkius-connector' ),
					'time_pm'               => __( 'PM', 'vinkius-connector' ),
					'across_all_connectors' => __( 'Across all connectors', 'vinkius-connector' ),
					'cost_kpi_requests'     => __( '{count} requests', 'vinkius-connector' ),
					'cost_reqs'             => __( '{count} reqs', 'vinkius-connector' ),
					'tb_capability'         => __( 'Capability', 'vinkius-connector' ),
					'col_agent'             => __( 'Agent', 'vinkius-connector' ),
					'col_upstream'          => __( 'Upstream', 'vinkius-connector' ),
					'col_vinkius'           => __( 'Vinkius', 'vinkius-connector' ),
					'health_critical'       => __( 'Critical', 'vinkius-connector' ),
					'chart'                 => __( 'Chart', 'vinkius-connector' ),
				),
			)
		);
	}
}
