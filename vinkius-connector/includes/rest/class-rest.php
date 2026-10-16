<?php
/**
 * REST controller.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector\Rest;

use VinkiusConnector\Governance;
use VinkiusConnector\Models\ConsentRepo;
use VinkiusConnector\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Public: agent manifest, consent.
 * Admin: governance panel proxies, halt triggers, device flow, site secret.
 */
final class Rest {

	const NAMESPACE = 'vinkius/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Route table.
	 *
	 * @return void
	 */
	public function routes() {
		$public = array( 'permission_callback' => '__return_true' );
		$admin  = array( 'permission_callback' => array( __CLASS__, 'can_admin' ) );

		register_rest_route( self::NAMESPACE, '/agent-manifest', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'agent_manifest' ), $public ) );
		register_rest_route( self::NAMESPACE, '/consent', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'consent_create' ), $public ) );
		register_rest_route( self::NAMESPACE, '/consent/revoke', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'consent_revoke' ), $public ) );

		register_rest_route( self::NAMESPACE, '/panel/status', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_status' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/operations', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_operations' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/section', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_section' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/capabilities', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_capabilities' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/agents', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_agents' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/incidents', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_incidents' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/connectors', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_connectors' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/evidence', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_evidence' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/halt', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'panel_halt' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/resume', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'panel_resume' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/circuit-reset', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'panel_circuit_reset' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/regen-secret', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'panel_regen_secret' ), $admin ) );

		register_rest_route( self::NAMESPACE, '/auth/device/init', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'device_init' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/auth/device/poll', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'device_poll' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/auth/logout', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'logout' ), $admin ) );
	}

	/**
	 * Admin guard.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public static function can_admin( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'vc_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
		}

		return true;
	}

	/* Public: agent-facing. */

	/**
	 * Agent-readable site manifest.
	 *
	 * @return \WP_REST_Response
	 */
	public static function agent_manifest( $request ) {
		if ( Options::is_halted() ) {
			return new \WP_REST_Response(
				array(
					'agent_manifest' => array(
						'halted'  => true,
						'message' => __( 'This site has emergency-halted AI agents. DO NOT RETRY. Approve Resumption first.', 'vinkius-connector' ),
					),
				),
				403
			);
		}

		$manifest = array(
			'schema_version' => '1',
			'site'           => array(
				'name'        => get_bloginfo( 'name' ),
				'url'         => get_site_url(),
				'description' => get_bloginfo( 'description' ),
				'generator'   => 'vinkius-connector/' . VC_VERSION,
			),
			'vinkius'        => array(
				'configured' => Options::is_configured(),
				'connected'  => (bool) Options::get( 'connected', false ),
				'scopes'     => ConsentRepo::SCOPES,
			),
			'consent'        => array(
				'required' => true,
				'endpoint' => rest_url( self::NAMESPACE . '/consent' ),
				'revoke'   => rest_url( self::NAMESPACE . '/consent/revoke?token={revoke_token}' ),
			),
			'generated_at'   => gmdate( 'Y-m-d H:i:s' ),
		);

		return rest_ensure_response( array( 'agent_manifest' => $manifest ) );
	}

	/**
	 * Record visitor consent.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function consent_create( $request ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return ConsentService::create( $request, $ip );
	}

	/**
	 * Revoke a consent by token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function consent_revoke( $request ) {
		$row_id = ConsentRepo::resolve_revoke_token( (string) $request->get_param( 'token' ) );

		if ( ! $row_id || ! ConsentRepo::revoke( $row_id ) ) {
			return new \WP_REST_Response( array( 'revoked' => false ), 404 );
		}

		return rest_ensure_response( array( 'revoked' => true ) );
	}

	/* Admin: governance panel. */

	/**
	 * Connection/status snapshot.
	 *
	 * @return \WP_REST_Response
	 */
	public static function panel_status() {
		$auth = \VinkiusConnector\Auth\AuthStore::status();
		$flow = new \VinkiusConnector\Auth\DeviceFlow();

		return rest_ensure_response(
			array(
				'api_configured' => Options::is_configured(),
				'auth_state'     => $auth['state'],
				'device_pending' => $flow->is_pending(),
				'halt'           => Options::is_halted(),
			)
		);
	}

	/**
	 * Governance dashboard payload.
	 *
	 * @return \WP_REST_Response
	 */
	public static function panel_operations() {
		return rest_ensure_response( ( new Governance() )->panel()->to_view() );
	}

	/**
	 * One AI Governance sidebar section (live read).
	 *
	 * @param \WP_REST_Request $request {section, period}.
	 * @return \WP_REST_Response
	 */
	public static function panel_section( $request ) {
		$section = (string) $request->get_param( 'section' );
		$period  = (string) $request->get_param( 'period' );

		if ( '' === $period ) {
			$period = '30d';
		}

		return rest_ensure_response( ( new Governance() )->governance_section( $section, $period )->to_view() );
	}

	/**
	 * Capabilities catalog: search or browse (+ stats, categories).
	 *
	 * @param \WP_REST_Request $request {q, page, sort}.
	 * @return \WP_REST_Response
	 */
	public static function panel_capabilities( $request ) {
		$q      = (string) $request->get_param( 'q' );
		$page  = max( 1, (int) $request->get_param( 'page' ) );
		$sort  = 'newest' === $request->get_param( 'sort' ) ? 'newest' : 'popular';

		return rest_ensure_response( ( new Governance() )->capabilities( $q, $page, $sort )->to_view() );
	}

	/**
	 * Agent fleet.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function panel_agents( $request ) {
		$page = max( 1, (int) $request->get_param( 'page' ) );

		return rest_ensure_response( ( new Governance() )->agents( $page )->to_view() );
	}

	/**
	 * Incidents payload.
	 *
	 * @return \WP_REST_Response
	 */
	public static function panel_incidents() {
		return rest_ensure_response( ( new Governance() )->incidents()->to_view() );
	}

	/**
	 * Connector list (evidence picker).
	 *
	 * @return \WP_REST_Response
	 */
	public static function panel_connectors() {
		return rest_ensure_response( ( new Governance() )->connectors()->to_view() );
	}

	/**
	 * Per-connector evidence.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function panel_evidence( $request ) {
		$server_id = (string) $request->get_param( 'server_id' );
		$page      = max( 1, (int) $request->get_param( 'page' ) );

		return rest_ensure_response( ( new Governance() )->evidence( $server_id, $page )->to_view() );
	}

	/**
	 * Halt: local and/or runtime triggers.
	 *
	 * @param \WP_REST_Request $request {scope: local|connector|all, server_id?, reason?}.
	 * @return \WP_REST_Response
	 */
	public static function panel_halt( $request ) {
		$governance = new Governance();
		$scope      = (string) $request->get_param( 'scope' );
		$reason     = (string) $request->get_param( 'reason' );

		if ( 'connector' === $scope && $request->get_param( 'server_id' ) ) {
			$result = $governance->halt_connector( (string) $request->get_param( 'server_id' ), $reason );

			return rest_ensure_response( array_merge( array( 'scope' => $scope ), $result->to_view() ) );
		}

		$result = $governance->halt_all();

		return rest_ensure_response( array_merge( array( 'scope' => 'all' ), $result->to_view() ) );
	}

	/**
	 * Resume local and runtime halts.
	 *
	 * @return \WP_REST_Response
	 */
	public static function panel_resume() {
		return rest_ensure_response( ( new Governance() )->resume_all()->to_view() );
	}

	/**
	 * Approve resumption after a circuit-breaker trip.
	 *
	 * @return \WP_REST_Response
	 */
	public static function panel_circuit_reset() {
		return rest_ensure_response( ( new Governance() )->circuit_reset()->to_view() );
	}

	/**
	 * Rotate the site secret.
	 *
	 * @return \WP_REST_Response
	 */
	public static function panel_regen_secret() {
		$secret = wp_generate_password( 48, true, false );

		Options::set( 'site_secret', $secret );

		return rest_ensure_response( array( 'secret' => $secret ) );
	}

	/* Admin: device flow. */

	/**
	 * Start device login.
	 *
	 * @return \WP_REST_Response
	 */
	public static function device_init() {
		return rest_ensure_response( ( new \VinkiusConnector\Auth\DeviceFlow() )->init()->to_view() );
	}

	/**
	 * Poll device login.
	 *
	 * @return \WP_REST_Response
	 */
	public static function device_poll() {
		return rest_ensure_response( ( new \VinkiusConnector\Auth\DeviceFlow() )->poll()->to_view() );
	}

	/**
	 * Clear the stored session.
	 *
	 * @return \WP_REST_Response
	 */
	public static function logout() {
		( new \VinkiusConnector\VinkiusApi() )->logout();
		\VinkiusConnector\Auth\AuthStore::clear();
		( new \VinkiusConnector\Auth\DeviceFlow() )->abort();

		return rest_ensure_response( array( 'ok' => true ) );
	}
}
