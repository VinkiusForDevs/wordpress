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
		register_rest_route( self::NAMESPACE, '/panel/section', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_section' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/capabilities', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_capabilities' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/incidents', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_incidents' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/briefing', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_briefing' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/evidence-packs', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_evidence_packs' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/agents-export', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_agents_export' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/tokens', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_tokens' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/halt', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'panel_halt' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/resume', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'panel_resume' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/circuit-reset', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'panel_circuit_reset' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/regen-secret', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'panel_regen_secret' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/connector', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'panel_connector' ), $admin ) );

		register_rest_route( self::NAMESPACE, '/panel/evidence-pack/generate', array( 'methods' => 'POST', 'callback' => array( __CLASS__, 'panel_evidence_pack_generate' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/evidence-pack', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_evidence_pack' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/audit', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_audit' ), $admin ) );
		register_rest_route( self::NAMESPACE, '/panel/settings', array( 'methods' => 'GET', 'callback' => array( __CLASS__, 'panel_settings' ), $admin ) );

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
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( ! ConsentService::revoke_throttle_ok( $ip ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'rate_limited', 'message' => __( 'Too many requests. Please try again later.', 'vinkius-connector' ) ),
				429
			);
		}

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
				'halted_at'      => Options::get( 'halted_at', null ),
			)
		);
	}

	/**
	 * One governance section page (live read).
	 *
	 * @param \WP_REST_Request $request {section, period, page}.
	 * @return \WP_REST_Response
	 */
	public static function panel_section( $request ) {
		$section = (string) $request->get_param( 'section' );
		$period  = (string) $request->get_param( 'period' );
		$page    = max( 1, (int) $request->get_param( 'page' ) );

		if ( '' === $period ) {
			$period = 'today';
		}

		// Drill mode: an entity id turns the read into the per-entity
		// request log behind the clicked row (same route family upstream).
		$drill_id  = trim( (string) $request->get_param( 'drill_id' ) );

		if ( '' !== $drill_id ) {
			return rest_ensure_response(
				( new Governance() )->section_drill(
					$section,
					$drill_id,
					(string) $request->get_param( 'drill_sub' ),
					$period,
					$page
				)->to_view()
			);
		}

		return rest_ensure_response( ( new Governance() )->governance_section( $section, $period, $page )->to_view() );
	}

	/**
	 * AI-narrated briefing of one scope (the panel's briefing card).
	 *
	 * @param \WP_REST_Request $request {scope, period}.
	 * @return \WP_REST_Response
	 */
	public static function panel_briefing( $request ) {
		$scope  = (string) $request->get_param( 'scope' );
		$period = (string) $request->get_param( 'period' );

		if ( '' === $period ) {
			$period = 'today';
		}

		return rest_ensure_response( ( new Governance() )->briefing( $scope, $period )->to_view() );
	}

	/**
	 * Generated evidence packs (Compliance & Evidence section).
	 *
	 * @return \WP_REST_Response
	 */
	public static function panel_evidence_packs() {
		return rest_ensure_response( ( new Governance() )->evidence_packs()->to_view() );
	}

	/**
	 * Full agent registry export (one read, whole registry).
	 *
	 * @param \WP_REST_Request $request {period}.
	 * @return \WP_REST_Response
	 */
	public static function panel_agents_export( $request ) {
		$period = (string) $request->get_param( 'period' );

		if ( '' === $period ) {
			$period = 'today';
		}

		return rest_ensure_response( ( new Governance() )->agent_registry_export( $period )->to_view() );
	}

	/**
	 * Execution tokens of one connector (Access Tokens accordion, lazy load).
	 *
	 * @param \WP_REST_Request $request {server_id}.
	 * @return \WP_REST_Response
	 */
	public static function panel_tokens( $request ) {
		$server_id = (string) $request->get_param( 'server_id' );

		if ( '' === $server_id ) {
			return rest_ensure_response( \VinkiusConnector\Result::error( 'vc_missing_server' )->to_view() );
		}

		return rest_ensure_response( ( new \VinkiusConnector\VinkiusApi() )->tokens( $server_id )->to_view() );
	}

	/**
	 * Capabilities catalog: search or browse (+ stats, categories).
	 *
	 * @param \WP_REST_Request $request {q, page, sort, category}.
	 * @return \WP_REST_Response
	 */
	public static function panel_capabilities( $request ) {
		$q        = (string) $request->get_param( 'q' );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$sort     = 'newest' === $request->get_param( 'sort' ) ? 'newest' : 'popular';
		$category = sanitize_title( (string) $request->get_param( 'category' ) );

		return rest_ensure_response( ( new Governance() )->capabilities( $q, $page, $sort, $category )->to_view() );
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
	 * Halt: local and/or runtime triggers.
	 *
	 * @param \WP_REST_Request $request {scope: local|connector|all, server_id?, reason?}.
	 * @return \WP_REST_Response
	 */
	public static function panel_halt( $request ) {
		$governance = new Governance();
		$scope      = (string) $request->get_param( 'scope' );
		$reason     = (string) $request->get_param( 'reason' );

		if ( 'connector' === $scope ) {
			$server_id = (string) $request->get_param( 'server_id' );

			/*
			 * scope=connector without a server id is a client error —
			 * never let it fall through to the org-wide halt.
			 */
			if ( '' === $server_id ) {
				return rest_ensure_response( \VinkiusConnector\Result::error( 'vc_missing_server' )->to_view() );
			}

			$result = $governance->halt_connector( $server_id, $reason );

			return rest_ensure_response( array_merge( array( 'scope' => $scope ), $result->to_view() ) );
		}

		/*
		 * scope=local: the Settings "Local kill switch". It must only flip
		 * this site's halt flag — never trigger the runtime's org-wide
		 * global halt (that is reserved for the deliberate "Halt all"
		 * action, which sends scope=all).
		 */
		if ( 'local' === $scope ) {
			return rest_ensure_response( array_merge( array( 'scope' => $scope ), $governance->halt_local()->to_view() ) );
		}

		$result = $governance->halt_all();

		return rest_ensure_response( array_merge( array( 'scope' => 'all' ), $result->to_view() ) );
	}

	/**
	 * Resume local and runtime halts.
	 *
	 * @param \WP_REST_Request $request {scope: local|all}.
	 * @return \WP_REST_Response
	 */
	public static function panel_resume( $request = null ) {
		$scope = 'all';

		if ( $request ) {
			$requested = (string) $request->get_param( 'scope' );

			if ( 'local' === $requested || 'all' === $requested ) {
				$scope = $requested;
			}
		}

		$governance = new Governance();

		// Local resume only flips this site's flag — no upstream restore.
		if ( 'local' === $scope ) {
			return rest_ensure_response( array_merge( array( 'scope' => $scope ), $governance->resume_local()->to_view() ) );
		}

		return rest_ensure_response( array_merge( array( 'scope' => $scope ), $governance->resume_all()->to_view() ) );
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
	 * Per-connector SOAR response action.
	 *
	 * @param \WP_REST_Request $request {action: status|lift|extend|kill, server_id, reason?}.
	 * @return \WP_REST_Response
	 */
	public static function panel_connector( $request ) {
		$api       = new \VinkiusConnector\VinkiusApi();
		$governance = new Governance();
		$action    = (string) $request->get_param( 'action' );
		$server_id = (string) $request->get_param( 'server_id' );

		if ( '' === $server_id ) {
			return rest_ensure_response( \VinkiusConnector\Result::error( 'vc_missing_server' )->to_view() );
		}

		switch ( $action ) {
			case 'lift':
				$result = $governance->resume_connector( $server_id );
				break;
			case 'extend':
				$result = $governance->extend_connector( $server_id );
				break;
			case 'kill':
				$result = $governance->halt_connector( $server_id, (string) $request->get_param( 'reason' ) );
				break;
			case 'status':
				$result = $api->soar_status( $server_id );
				break;
			default:
				$result = \VinkiusConnector\Result::error( 'vc_unknown_action' );
				break;
		}

		return rest_ensure_response( $result->to_view() );
	}

	/**
	 * Generate a new evidence pack now (paid-only upstream).
	 *
	 * @return \WP_REST_Response
	 */
	public static function panel_evidence_pack_generate() {
		return rest_ensure_response( ( new Governance() )->evidence_pack_generate()->to_view() );
	}

	/**
	 * One evidence pack for in-panel verification.
	 *
	 * @param \WP_REST_Request $request {id}.
	 * @return \WP_REST_Response
	 */
	public static function panel_evidence_pack( $request ) {
		$id = (string) $request->get_param( 'id' );

		if ( '' === $id ) {
			return rest_ensure_response( \VinkiusConnector\Result::error( 'vc_missing_pack' )->to_view() );
		}

		return rest_ensure_response( ( new Governance() )->evidence_pack_view( $id )->to_view() );
	}

	/**
	 * Control/activity feeds: {scope: org|activity}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function panel_audit( $request ) {
		$scope = 'activity' === $request->get_param( 'scope' ) ? 'activity' : 'org';
		$page  = max( 1, (int) $request->get_param( 'page' ) );

		return rest_ensure_response( ( new Governance() )->audit_feed( $scope, $page )->to_view() );
	}

	/**
	 * Enforcement settings mirror: {scope: dlp|finops}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function panel_settings( $request ) {
		$scope = 'finops' === $request->get_param( 'scope' ) ? 'finops' : 'dlp';

		return rest_ensure_response( ( new Governance() )->enforcement_settings( $scope )->to_view() );
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
