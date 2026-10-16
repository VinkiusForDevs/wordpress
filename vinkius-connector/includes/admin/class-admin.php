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
 * Top-level "Vinkius AI Governance" menu.
 */
final class Admin {

	/**
	 * Menu slug -> screen class.
	 *
	 * @var array<string, string>
	 */
	private static $screens = array(
		'dashboard'  => ScreenDashboard::class,
		'agents'     => ScreenAgents::class,
		'incidents'  => ScreenIncidents::class,
		'evidence'   => ScreenEvidence::class,
		'consent'    => ScreenConsent::class,
		'marketplace'=> ScreenMarketplace::class,
		'settings'   => ScreenSettings::class,
	);

	/**
	 * Register.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
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
			array( ScreenDashboard::class, 'render_page' ),
			'dashicons-shield',
			26
		);

		$labels = array(
			'dashboard'   => __( 'Governance', 'vinkius-connector' ),
			'agents'      => __( 'Agents', 'vinkius-connector' ),
			'incidents'   => __( 'Incidents', 'vinkius-connector' ),
			'evidence'    => __( 'Evidence', 'vinkius-connector' ),
			'consent'     => __( 'Consent', 'vinkius-connector' ),
			'marketplace' => __( 'Marketplace', 'vinkius-connector' ),
			'settings'    => __( 'Settings', 'vinkius-connector' ),
		);

		foreach ( array_keys( self::$screens ) as $slug ) {
			add_submenu_page(
				'vinkius-connector',
				$title . ' — ' . $labels[ $slug ],
				$labels[ $slug ],
				$capability,
				'dashboard' === $slug ? 'vinkius-connector' : 'vinkius-connector-' . $slug,
				array( self::$screens[ $slug ], 'render_page' )
			);
		}
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

		wp_enqueue_style( 'vc-admin', VC_PLUGIN_URL . 'admin/css/vinkius-connector.css', array(), VC_VERSION );
		wp_enqueue_style( 'vc-governance', VC_PLUGIN_URL . 'admin/css/vc-governance.css', array(), VC_VERSION );
		wp_enqueue_script( 'vc-admin', VC_PLUGIN_URL . 'admin/js/vinkius-connector.js', array(), VC_VERSION, true );
		wp_enqueue_script( 'vc-governance', VC_PLUGIN_URL . 'admin/js/vc-governance.js', array( 'vc-admin' ), VC_VERSION, true );

		$gov_titles = array(
			'overview'         => __( 'Mission Control', 'vinkius-connector' ),
			'clients'          => __( 'Agent Activity', 'vinkius-connector' ),
			'connectors'       => __( 'Connector Traffic', 'vinkius-connector' ),
			'token-fleet'      => __( 'Access Tokens', 'vinkius-connector' ),
			'cost'             => __( 'AI Spend', 'vinkius-connector' ),
			'tool-performance' => __( 'Capability Reliability', 'vinkius-connector' ),
			'security'         => __( 'Security Posture', 'vinkius-connector' ),
			'errors'           => __( 'Request Failures', 'vinkius-connector' ),
			'capabilities'     => __( 'Capabilities', 'vinkius-connector' ),
		);

		wp_localize_script(
			'vc-admin',
			'VCConfig',
			array(
				'restUrl'   => rest_url( 'vinkius/v1/' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'links'     => \VinkiusConnector\Links::all(),
				'gov'       => array(
					'titles'         => $gov_titles,
					'capabilityBase' => \VinkiusConnector\Links::capability( '' ),
					'upgrade'        => \VinkiusConnector\Links::cloud( 'upgrade' ),
				),
				'labels'    => array(
					'usage'           => __( 'Used %1$s of %2$s this cycle.', 'vinkius-connector' ),
					'session_expired' => __( 'Session expired. Log in again from Settings to see live data.', 'vinkius-connector' ),
					'live_empty'      => __( 'No activity in this period.', 'vinkius-connector' ),
					'fleet_empty'     => __( 'No connectors with activity.', 'vinkius-connector' ),
					'evidence_empty'  => __( 'No executions recorded for this connector.', 'vinkius-connector' ),
					'circuit_tripped' => __( 'Tripped', 'vinkius-connector' ),
					'circuit_open'    => __( 'Open', 'vinkius-connector' ),
					'halt_connector'  => __( 'Halt this connector', 'vinkius-connector' ),
					'confirm_halt_all' => __( 'Halt all agents on this site?', 'vinkius-connector' ),
					'device_code'     => __( 'Device code', 'vinkius-connector' ),
					'device_open'     => __( 'Open approval page', 'vinkius-connector' ),
					'device_waiting'  => __( 'Waiting for approval…', 'vinkius-connector' ),
					'device_expired'  => __( 'Session expired. Start again.', 'vinkius-connector' ),
					'device_error'    => __( 'Could not start device login. Please try again.', 'vinkius-connector' ),
					'secret_copy'     => __( 'Copy', 'vinkius-connector' ),
					'secret_copied'   => __( 'Copied', 'vinkius-connector' ),
					'no_data'         => __( 'No data in this period.', 'vinkius-connector' ),
					'live_activity'   => __( 'Live activity', 'vinkius-connector' ),
					'plan_usage'      => __( 'Plan & usage', 'vinkius-connector' ),
					'subscribe'       => __( 'Subscribe to Vinkius', 'vinkius-connector' ),
					'tripped'         => __( 'Tripped', 'vinkius-connector' ),
					'open'            => __( 'Open', 'vinkius-connector' ),
					'reset_circuit'   => __( 'Reset circuit', 'vinkius-connector' ),
					'cap_search'      => __( 'Search capabilities and connectors…', 'vinkius-connector' ),
					'cap_catalog'     => __( '%1$s connectors · %2$s capabilities', 'vinkius-connector' ),
					'cap_no_matches'  => __( 'No matches for "%s".', 'vinkius-connector' ),
					'cap_try'         => __( 'Try rephrasing your search.', 'vinkius-connector' ),
					'cap_unavailable' => __( 'Search is unavailable right now. Please try again.', 'vinkius-connector' ),
					'cap_none'        => __( 'No connectors in the catalog yet.', 'vinkius-connector' ),
					'cap_connected'   => __( 'Connected', 'vinkius-connector' ),
					'cap_open'        => __( 'Open in Vinkius', 'vinkius-connector' ),
					'cap_all'         => __( 'All', 'vinkius-connector' ),
					'cap_title'       => __( 'Capabilities', 'vinkius-connector' ),
					'cap_clear'       => __( 'Clear', 'vinkius-connector' ),
					'on'              => __( 'On', 'vinkius-connector' ),
					'off'             => __( 'Off', 'vinkius-connector' ),
					'by_client'       => __( 'By client', 'vinkius-connector' ),
					'access_tokens'   => __( 'Access tokens', 'vinkius-connector' ),
					'spend_by_server' => __( 'Spend by server', 'vinkius-connector' ),
					'events_by_server'=> __( 'Events by server', 'vinkius-connector' ),
					'failures_by_server'=> __( 'Failures by server', 'vinkius-connector' ),
					'slowest_tools'   => __( 'Slowest tools', 'vinkius-connector' ),
					'most_failing'    => __( 'Most failing', 'vinkius-connector' ),
					'halted'          => __( 'Halted', 'vinkius-connector' ),
					'running'         => __( 'Running', 'vinkius-connector' ),
					'resume_agents'   => __( 'Resume agents', 'vinkius-connector' ),
					'halt_all'        => __( 'Halt all', 'vinkius-connector' ),
					'halt_manage'     => __( 'Manage per-connector halt in Vinkius', 'vinkius-connector' ),
				),
			)
		);
	}
}
