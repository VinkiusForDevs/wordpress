<?php
/**
 * Vinkius API client — observability reads + control triggers.
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Architecture law: the plugin is the owner's observation-and-response
 * surface inside wp-admin. It does NOT manage connectors, credentials,
 * policies, webhooks or provisioning — that stays in the Vinkius cloud.
 *
 * Lawful surfaces, and nothing else:
 *   - owner's device-flow login (public) + Passport session (AuthStore);
 *   - READ-ONLY observability of what the runtime executed (fleet, live
 *     feed, logs, incidents, cost, usage);
 *   - TRIGGERING EXISTING control mechanisms (SOAR kill, emergency and
 *     global halt, circuit-breaker reset) — the enforcement itself
 *     happens in the runtime;
 *   - the embeddable trust badge (CORS *, client-rendered).
 */
final class VinkiusApi {

	/**
	 * Shared HTTP client.
	 *
	 * @var HttpClient
	 */
	private $http;

	/**
	 * Constructor.
	 *
	 * @param HttpClient|null $http Injectable for tests.
	 */
	public function __construct( HttpClient $http = null ) {
		$this->http = null !== $http ? $http : new HttpClient();
	}

	/* ---------------------------------------------------------------------
	 | Login (public) + badge
	 * ------------------------------------------------------------------- */

	/**
	 * Start the owner's device login.
	 *
	 * @return Result
	 */
	public function device() {
		return $this->http->request( 'POST', '/auth/device', array( 'client_name' => 'WordPress' ) );
	}

	/**
	 * Poll a pending device login.
	 *
	 * @param string $device_code Device code.
	 * @return Result
	 */
	public function device_poll( $device_code ) {
		return $this->http->request( 'POST', '/auth/device/poll', array( 'device_code' => $device_code ) );
	}

	/**
	 * Embeddable trust badge SVG URL (served by Vinkius, CORS *, client-rendered).
	 *
	 * @param string $scan_id Debugger scan id.
	 * @return string
	 */
	public function badge_url( $scan_id ) {
		return rtrim( VC_API_BASE_URL, '/' )
			. '/debugger/scan/' . rawurlencode( $scan_id ) . '/badge.svg';
	}

	/* ---------------------------------------------------------------------
	 | Read-only observability (the runtime's own data, never re-implemented)
	 * ------------------------------------------------------------------- */

	/**
	 * Bearer-carrying call (Passport session; auto-refreshed locally).
	 *
	 * @param string     $method Verb.
	 * @param string     $path   Route.
	 * @param array|null $body   Optional JSON body.
	 * @return Result
	 */
	private function read( $method, $path, $body = null ) {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		$token = \VinkiusConnector\Auth\AuthStore::access_token();

		if ( ! $token->is_ok() || empty( $token->data()['token'] ) ) {
			return $token;
		}

		return $this->http->request( $method, $path, $body, $token->data()['token'] );
	}

	/**
	 * Fleet: every connector (server) in this org.
	 *
	 * @return Result
	 */
	public function servers() {
		return $this->read( 'GET', '/servers' );
	}

	/**
	 * Latest executions of a connector.
	 *
	 * @param string $server_id Connector id.
	 * @return Result
	 */
	public function live_feed( $server_id ) {
		return $this->read( 'GET', '/servers/' . rawurlencode( $server_id ) . '/live-feed' );
	}

	/**
	 * Execution log of a connector (immutable audit source).
	 *
	 * @param string $server_id Connector id.
	 * @param int    $page      Page.
	 * @return Result
	 */
	public function request_logs( $server_id, $page = 1 ) {
		return $this->read( 'GET', '/servers/' . rawurlencode( $server_id ) . '/logs?page=' . (int) $page . '&per_page=40' );
	}

	/**
	 * Audit log: per-agent summary or flat timeline.
	 *
	 * @param string $server_id Connector id.
	 * @param string $view      summary|timeline.
	 * @return Result
	 */
	public function audit_log( $server_id, $view = 'summary' ) {
		return $this->read( 'GET', '/servers/' . rawurlencode( $server_id ) . '/audit-log?view=' . rawurlencode( $view ) );
	}

	/**
	 * Visitor connection sessions of a connector.
	 *
	 * @param string $server_id Connector id.
	 * @param int    $page      Page.
	 * @return Result
	 */
	public function connection_logs( $server_id, $page = 1 ) {
		return $this->read( 'GET', '/servers/' . rawurlencode( $server_id ) . '/connection-logs?page=' . (int) $page . '&per_page=40' );
	}

	/**
	 * Pre-aggregated stats (24h/7d/30d, by tool).
	 *
	 * @param string $server_id Connector id.
	 * @param string $period    24h|7d|30d.
	 * @return Result
	 */
	public function stats( $server_id, $period = '30d' ) {
		return $this->read( 'GET', '/servers/' . rawurlencode( $server_id ) . '/stats?period=' . rawurlencode( $period ) );
	}

	/**
	 * Execution tokens of a connector (which agents hold access).
	 *
	 * @param string $server_id Connector id.
	 * @return Result
	 */
	public function tokens( $server_id ) {
		return $this->read( 'GET', '/servers/' . rawurlencode( $server_id ) . '/tokens' );
	}

	/**
	 * Per-token activity ("what did agent X do").
	 *
	 * @param string $server_id Connector id.
	 * @param string $token_id  Token id.
	 * @param int    $page      Page.
	 * @return Result
	 */
	public function token_activity( $server_id, $token_id, $page = 1 ) {
		return $this->read( 'GET', '/servers/' . rawurlencode( $server_id ) . '/tokens/' . rawurlencode( $token_id ) . '/activity?page=' . (int) $page . '&per_page=40' );
	}

	/**
	 * Org-level overview (requests, clients, errors).
	 *
	 * @param string $period today|3d|7d|14d|30d.
	 * @return Result
	 */
	public function guardian_overview( $period = '30d' ) {
		return $this->read( 'GET', '/guardian/overview?period=' . rawurlencode( $period ) );
	}

	/**
	 * Error breakdown (client/upstream/platform).
	 *
	 * @param string $period today|3d|7d|14d|30d.
	 * @return Result
	 */
	public function guardian_errors( $period = '30d' ) {
		return $this->read( 'GET', '/guardian/errors?period=' . rawurlencode( $period ) );
	}

	/**
	 * Per-connector security posture (DLP/FinOps/quota state).
	 *
	 * @param string $period today|3d|7d|14d|30d.
	 * @return Result
	 */
	public function guardian_security_posture( $period = '30d' ) {
		return $this->read( 'GET', '/guardian/security-posture?period=' . rawurlencode( $period ) );
	}

	/**
	 * Per-token fleet view.
	 *
	 * @param string $period today|3d|7d|14d|30d.
	 * @param int    $page   Page.
	 * @return Result
	 */
	public function guardian_token_fleet( $period = '30d', $page = 1 ) {
		return $this->read( 'GET', '/guardian/token-fleet?period=' . rawurlencode( $period ) . '&mcps_page=' . (int) $page );
	}

	/**
	 * FinOps KPIs (tokens, cost, savings, ROI, DLP events).
	 *
	 * @param string $period today|3d|7d|14d|30d.
	 * @return Result
	 */
	public function guardian_cost( $period = '30d' ) {
		return $this->read( 'GET', '/guardian/cost?period=' . rawurlencode( $period ) );
	}

	/**
	 * Quota snapshot {plan, used, limit, resets_at}.
	 *
	 * @return Result
	 */
	public function usage() {
		return $this->read( 'GET', '/usage' );
	}

	/**
	 * Agent clients (Agent Activity section).
	 *
	 * @param string $period today|3d|7d|14d|30d.
	 * @return Result
	 */
	public function guardian_clients( $period = '30d' ) {
		return $this->read( 'GET', '/guardian/clients?period=' . rawurlencode( $period ) );
	}

	/**
	 * Per-connector traffic (Connector Traffic section).
	 *
	 * @param string $period today|3d|7d|14d|30d.
	 * @param int    $page   Page.
	 * @return Result
	 */
	public function guardian_connectors( $period = '30d', $page = 1 ) {
		return $this->read( 'GET', '/guardian/connectors?period=' . rawurlencode( $period ) . '&page=' . (int) $page );
	}

	/**
	 * Tool performance (Capability Reliability section).
	 *
	 * @param string $period today|3d|7d|14d|30d.
	 * @return Result
	 */
	public function guardian_tool_performance( $period = '30d' ) {
		return $this->read( 'GET', '/guardian/tool-performance?period=' . rawurlencode( $period ) );
	}

	/* ---------------------------------------------------------------------
	 | Public catalog (Capabilities search — read-only, no management)
	 * ------------------------------------------------------------------- */

	/**
	 * Bearer when a session exists, null when anonymous.
	 *
	 * @return string|null
	 */
	private function optional_bearer() {
		$token = \VinkiusConnector\Auth\AuthStore::access_token();

		return ( $token->is_ok() && ! empty( $token->data()['token'] ) ) ? $token->data()['token'] : null;
	}

	/**
	 * Catalog stats {catalog_count, connectors_count, capabilities_count}.
	 *
	 * @return Result
	 */
	public function marketplace_stats() {
		return $this->http->request( 'GET', '/marketplace/stats', null, $this->optional_bearer() );
	}

	/**
	 * Browse listings (no query).
	 *
	 * @param string $sort    popular|newest.
	 * @param int    $page    Page.
	 * @return Result
	 */
	public function marketplace_listings( $sort = 'popular', $page = 1 ) {
		$path = '/marketplace/listings?sort=' . rawurlencode( $sort ) . '&page=' . (int) $page . '&locale=en';

		return $this->http->request( 'GET', $path, null, $this->optional_bearer() );
	}

	/**
	 * Search the catalog (q, min 2 chars upstream).
	 *
	 * @param string $q    Query.
	 * @param int    $page Page.
	 * @return Result
	 */
	public function marketplace_search( $q, $page = 1 ) {
		$path = '/marketplace/search?q=' . rawurlencode( $q ) . '&page=' . (int) $page . '&locale=en';

		return $this->http->request( 'GET', $path, null, $this->optional_bearer() );
	}

	/**
	 * Catalog categories [{slug, label, color, is_featured, listing_count}].
	 *
	 * @return Result
	 */
	public function marketplace_categories() {
		return $this->http->request( 'GET', '/marketplace/categories', null, $this->optional_bearer() );
	}

	/**
	 * Org-level paginated live activity (the Overview feed).
	 *
	 * @param string $period today|3d|7d|14d|30d.
	 * @param int    $page   Page.
	 * @return Result
	 */
	public function guardian_live_feed( $period = 'today', $page = 1 ) {
		return $this->read( 'GET', '/guardian/live-feed?period=' . rawurlencode( $period ) . '&page=' . (int) $page . '&per_page=25' );
	}

	/**
	 * AI-narrated summary of a scope (paid-only upstream; prepared otherwise).
	 *
	 * @param string $scope overview|clients|connectors|cost|tool-performance|security-posture|errors|token-fleet.
	 * @return Result
	 */
	public function guardian_ai_briefing( $scope = 'overview' ) {
		return $this->read( 'POST', '/guardian/ai-briefing/' . rawurlencode( $scope ), array( 'period' => 'today' ) );
	}

	/* ---------------------------------------------------------------------
	 | Triggering EXISTING control mechanisms (enforcement stays in runtime)
	 * ------------------------------------------------------------------- */

	/**
	 * Quarantine status of a connector.
	 *
	 * @param string $server_id Connector id.
	 * @return Result
	 */
	public function soar_status( $server_id ) {
		return $this->read( 'GET', '/servers/' . rawurlencode( $server_id ) . '/soar/quarantine/status' );
	}

	/**
	 * SOAR: quarantine one connector (kills live transports, 1h TTL).
	 *
	 * @param string $server_id Connector id.
	 * @param string $reason    Required human-readable reason.
	 * @return Result
	 */
	public function soar_kill( $server_id, $reason ) {
		return $this->read( 'POST', '/servers/' . rawurlencode( $server_id ) . '/soar/kill', array( 'reason' => $reason ) );
	}

	/**
	 * Lift a SOAR quarantine.
	 *
	 * @param string $server_id Connector id.
	 * @return Result
	 */
	public function soar_lift( $server_id ) {
		return $this->read( 'POST', '/servers/' . rawurlencode( $server_id ) . '/soar/quarantine/lift' );
	}

	/**
	 * Kill live connections of one connector.
	 *
	 * @param string $server_id Connector id.
	 * @return Result
	 */
	public function kill_connections( $server_id ) {
		return $this->read( 'POST', '/servers/' . rawurlencode( $server_id ) . '/kill-connections' );
	}

	/**
	 * Emergency halt of one connector (plan-gated upstream).
	 *
	 * @param string $server_id Connector id.
	 * @return Result
	 */
	public function emergency_halt( $server_id ) {
		return $this->read( 'POST', '/servers/' . rawurlencode( $server_id ) . '/emergency-halt' );
	}

	/**
	 * Global emergency halt for the whole org (plan-gated upstream).
	 *
	 * @return Result
	 */
	public function global_halt() {
		return $this->read( 'POST', '/settings/global-emergency-halt' );
	}

	/**
	 * Restore after a global halt.
	 *
	 * @return Result
	 */
	public function global_restore() {
		return $this->read( 'POST', '/settings/global-restore' );
	}

	/**
	 * Circuit breaker live state.
	 *
	 * @return Result
	 */
	public function circuit_breaker() {
		return $this->read( 'GET', '/settings/circuit-breaker' );
	}

	/**
	 * Approve resumption after a circuit-breaker trip.
	 *
	 * @return Result
	 */
	public function circuit_breaker_reset() {
		return $this->read( 'POST', '/settings/circuit-breaker/reset' );
	}

	/**
	 * Revoke the current session upstream (remote logout).
	 *
	 * @return Result
	 */
	public function logout() {
		return $this->read( 'POST', '/auth/logout' );
	}
}
