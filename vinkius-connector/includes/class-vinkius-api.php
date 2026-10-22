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
	public function __construct( ?HttpClient $http = null ) {
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
	 * @param string $period   today|3d|7d|14d|30d.
	 * @param int    $page    Table page.
	 * @param int    $per_page Rows per page.
	 * @return Result
	 */
	public function guardian_errors( $period = '30d', $page = 1, $per_page = 25 ) {
		return $this->read( 'GET', '/guardian/errors?period=' . rawurlencode( $period ) . '&page=' . (int) $page . '&per_page=' . (int) $per_page );
	}

	/**
	 * Per-connector security posture (DLP/FinOps/quota state).
	 *
	 * @param string $period   today|3d|7d|14d|30d.
	 * @param int    $page     Table page.
	 * @param int    $per_page Rows per page.
	 * @return Result
	 */
	public function guardian_security_posture( $period = '30d', $page = 1, $per_page = 25 ) {
		return $this->read( 'GET', '/guardian/security-posture?period=' . rawurlencode( $period ) . '&page=' . (int) $page . '&per_page=' . (int) $per_page );
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
	 * @param string $period   today|3d|7d|14d|30d.
	 * @param int    $page     Table page.
	 * @param int    $per_page Rows per page.
	 * @return Result
	 */
	public function guardian_cost( $period = '30d', $page = 1, $per_page = 25 ) {
		return $this->read( 'GET', '/guardian/cost?period=' . rawurlencode( $period ) . '&page=' . (int) $page . '&per_page=' . (int) $per_page );
	}

	/**
	 * Agent Passport registry (identity chain, permissions, usage).
	 *
	 * Server-paginated: KPIs aggregate every agent, `agents` is the page.
	 * `per_page` is 0 only on the export call (whole registry).
	 *
	 * @param string $period   today|3d|7d|14d|30d.
	 * @param int    $page     Page.
	 * @param int    $per_page Rows per page (0 = full registry export).
	 * @return Result
	 */
	public function guardian_agents( $period = 'today', $page = 1, $per_page = 25 ) {
		return $this->read( 'GET', '/guardian/agents?period=' . rawurlencode( $period ) . '&page=' . (int) $page . '&per_page=' . (int) $per_page );
	}

	/**
	 * Compliance scorecard: readiness grade, health questions, gaps,
	 * framework control maps and the ledger evidence counters.
	 *
	 * @return Result
	 */
	public function guardian_compliance() {
		return $this->read( 'GET', '/guardian/compliance' );
	}

	/**
	 * Freeze the current scorecard + evidence into an immutable pack
	 * (POST /guardian/evidence-pack). Paid-only upstream (403 on Free).
	 *
	 * @return Result
	 */
	public function evidence_pack_generate() {
		return $this->read( 'POST', '/guardian/evidence-pack' );
	}

	/**
	 * One evidence pack digest + manifest (GET /guardian/evidence-packs/{id}).
	 *
	 * @param string $pack_id Pack id.
	 * @return Result
	 */
	public function evidence_pack( $pack_id ) {
		return $this->read( 'GET', '/guardian/evidence-packs/' . rawurlencode( $pack_id ) );
	}

	/**
	 * Latency timeline (org-scope, period filter).
	 *
	 * @param string $period today|3d|7d|14d|30d.
	 * @return Result
	 */
	public function guardian_latency( $period = '30d' ) {
		return $this->read( 'GET', '/guardian/latency?period=' . rawurlencode( $period ) );
	}

	/**
	 * Drill-down: request log rows behind one entity (org guard on cloud).
	 *
	 * @param string $endpoint guardian subsection name (connectors|cost|security-posture|errors).
	 * @param string $id       Server id.
	 * @param string $period   Period filter.
	 * @param int    $page     Table page.
	 * @return Result
	 */
	public function guardian_section_drill( $endpoint, $id, $period = 'today', $page = 1 ) {
		return $this->read( 'GET', '/guardian/' . rawurlencode( $endpoint ) . '/' . rawurlencode( $id ) . '?period=' . rawurlencode( $period ) . '&page=' . (int) $page );
	}

	/**
	 * Drill-down: request log rows behind one agent client.
	 *
	 * @param string $client Client key.
	 * @param string $period Period filter.
	 * @param int    $page   Table page.
	 * @return Result
	 */
	public function guardian_client_drill( $client, $period = 'today', $page = 1 ) {
		return $this->read( 'GET', '/guardian/clients/' . rawurlencode( $client ) . '?period=' . rawurlencode( $period ) . '&page=' . (int) $page );
	}

	/**
	 * Drill-down: request log rows behind one token (Access Tokens accordion).
	 *
	 * @param string $token  Token id.
	 * @param string $period Period filter.
	 * @param int    $page   Table page.
	 * @return Result
	 */
	public function guardian_token_drill( $token, $period = 'today', $page = 1 ) {
		return $this->read( 'GET', '/guardian/token-fleet/' . rawurlencode( $token ) . '?period=' . rawurlencode( $period ) . '&page=' . (int) $page );
	}

	/**
	 * Drill-down: request log rows behind one capability (server/tool pair).
	 *
	 * @param string $server  Server id.
	 * @param string $tool    Tool id.
	 * @param string $period  Period filter.
	 * @param int    $page    Table page.
	 * @return Result
	 */
	public function guardian_tool_drill( $server, $tool, $period = 'today', $page = 1 ) {
		return $this->read( 'GET', '/guardian/tool-performance/' . rawurlencode( $server ) . '/' . rawurlencode( $tool ) . '?period=' . rawurlencode( $period ) . '&page=' . (int) $page );
	}

	/**
	 * Deepest drill: the single immutable request log row.
	 *
	 * @param string $id     Request log id (UUID).
	 * @param string $period Period filter.
	 * @return Result
	 */
	public function guardian_request_detail( $id, $period = 'today' ) {
		return $this->read( 'GET', '/guardian/requests/' . rawurlencode( $id ) . '?period=' . rawurlencode( $period ) );
	}

	/**
	 * Generated evidence packs (frozen, re-verifiable snapshots).
	 *
	 * @return Result
	 */
	public function guardian_evidence_packs() {
		return $this->read( 'GET', '/guardian/evidence-packs' );
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
	 * @param string $period             today|3d|7d|14d|30d.
	 * @param int    $slowest_page       Slowest table page.
	 * @param int    $slowest_per_page   Slowest rows per page.
	 * @param int    $failing_page       Most-failing table page.
	 * @param int    $failing_per_page   Most-failing rows per page.
	 * @return Result
	 */
	public function guardian_tool_performance( $period = '30d', $slowest_page = 1, $slowest_per_page = 25, $failing_page = 1, $failing_per_page = 25 ) {
		$path = '/guardian/tool-performance?period=' . rawurlencode( $period )
			. '&slowest_page=' . (int) $slowest_page
			. '&slowest_per_page=' . (int) $slowest_per_page
			. '&failing_page=' . (int) $failing_page
			. '&failing_per_page=' . (int) $failing_per_page;

		return $this->read( 'GET', $path );
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
	 * @param string $sort      popular|newest.
	 * @param int    $page      Page.
	 * @param string $category  Optional category slug filter.
	 * @return Result
	 */
	public function marketplace_listings( $sort = 'popular', $page = 1, $category = '' ) {
		$path = '/marketplace/listings?sort=' . rawurlencode( $sort ) . '&page=' . (int) $page . '&locale=en';

		if ( '' !== $category ) {
			$path .= '&category=' . rawurlencode( $category );
		}

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
	 * @param string $scope  overview|clients|connectors|cost|tool-performance|security-posture|errors|token-fleet.
	 * @param string $period today|3d|7d|14d|30d.
	 * @return Result
	 */
	public function guardian_ai_briefing( $scope = 'overview', $period = 'today' ) {
		return $this->read( 'POST', '/guardian/ai-briefing/' . rawurlencode( $scope ), array( 'period' => $period ) );
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
	 * Extend a SOAR quarantine (another 1h TTL on top of the remaining one).
	 *
	 * @param string $server_id Connector id.
	 * @return Result
	 */
	public function soar_extend( $server_id ) {
		return $this->read( 'POST', '/servers/' . rawurlencode( $server_id ) . '/soar/quarantine/extend' );
	}

	/**
	 * DLP enforcement settings of the org (read-only mirror).
	 *
	 * @return Result
	 */
	public function dlp() {
		return $this->read( 'GET', '/settings/dlp' );
	}

	/**
	 * FinOps enforcement settings of the org (read-only mirror).
	 *
	 * @return Result
	 */
	public function finops() {
		return $this->read( 'GET', '/settings/finops' );
	}

	/**
	 * Org audit log — the IAM/operator audit trail (who did what).
	 *
	 * @param int $page Page.
	 * @return Result
	 */
	public function org_audit_log( $page = 1 ) {
		return $this->read( 'GET', '/org/audit-log?page=' . (int) $page );
	}

	/**
	 * Deploy/edge processing activity log — what the runtime did.
	 *
	 * @param int $page Page.
	 * @return Result
	 */
	public function activity_log( $page = 1 ) {
		return $this->read( 'GET', '/settings/activity-log?page=' . (int) $page );
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
