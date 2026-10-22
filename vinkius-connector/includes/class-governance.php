<?php
/**
 * Governance: composes Vinkius API reads into the panel.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Observation and response layer. Every value is a live API read —
 * nothing is stored in WordPress. Prepared states render as CTA/empty.
 */
final class Governance {

	/** @var VinkiusApi */
	private $api;

	public function __construct( ?VinkiusApi $api = null ) {
		$this->api = null !== $api ? $api : new VinkiusApi();
	}

	/**
	 * Incidents: errors + security posture + circuit + AI briefing.
	 *
	 * @return Result
	 */
	public function incidents() {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		$errors    = $this->api->guardian_errors( 'today' );
		$security  = $this->api->guardian_security_posture( 'today' );
		$circuit   = $this->api->circuit_breaker();
		$briefing  = $this->api->guardian_ai_briefing( 'errors' );

		$ekpi = $errors->is_ok() ? ( $errors->data()['kpi'] ?? [] ) : array();
		$skpi = $security->is_ok() ? ( $security->data()['kpi'] ?? [] ) : array();

		return Result::ok(
			array(
				'errors_kpi'    => $ekpi,
				'security_kpi'  => $skpi,
				'circuit'       => $circuit->is_ok() ? ( $circuit->data()['data'] ?? $circuit->data() ) : array(),
				'briefing'      => $briefing->is_ok() ? ( $briefing->data()['summary'] ?? $briefing->data() ) : null,
				'errors_by_server' => $errors->is_ok() ? ( $errors->data()['by_server'] ?? array() ) : array(),
				'quarantine'    => $this->quarantine_statuses(),
			)
		);
	}

	/**
	 * SOAR quarantine status of the fleet's visible connectors.
	 *
	 * The cloud keeps the live quarantine flag in Redis with a 1h TTL and
	 * exposes GET /servers/{id}/soar/quarantine/status — but there is still
	 * no org-level listing, so the panel walks the fleet's first page.
	 * Bounded: at most 12 connectors per compose (missions care about the
	 * hot few; the full list lives in the cloud).
	 *
	 * @return array[] Each: {id, name, quarantined, reason, expires_at, ttl_seconds}.
	 */
	private function quarantine_statuses() {
		$servers = $this->api->servers();

		if ( ! $servers->is_ok() ) {
			return array();
		}

		$raw = $servers->data()['data'] ?? $servers->data();
		$raw = is_array( $raw ) ? ( $raw['servers'] ?? $raw ) : array();

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$rows = array();
		$i    = 0;

		foreach ( $raw as $server ) {
			if ( ++ $i > 12 ) {
				break;
			}

			$server = is_array( $server ) ? $server : array();
			$id     = (string) ( $server['id'] ?? $server['uuid'] ?? '' );

			if ( '' === $id ) {
				continue;
			}

			$status = $this->api->soar_status( $id );

			if ( ! $status->is_ok() ) {
				continue;
			}

			$data = $status->data();

			$rows[] = array(
				'id'           => $id,
				'name'         => (string) ( $server['name'] ?? $id ),
				'quarantined'  => (bool) ( $data['quarantined'] ?? false ),
				'reason'       => isset( $data['reason'] ) ? (string) $data['reason'] : null,
				'expires_at'   => isset( $data['expires_at'] ) ? (string) $data['expires_at'] : null,
				'ttl_seconds'  => isset( $data['ttl_seconds'] ) ? (int) $data['ttl_seconds'] : null,
			);
		}

		return $rows;
	}

	/**
	 * Drill-down: per-entity request lists behind a Guardian section row.
	 *
	 * Mirrors the cloud drill routes the WP rows deep-link into; the same
	 * reads happen through the plugin's session, no page hop needed.
	 *
	 * @param string $section guardians subsection the drill came from.
	 * @param string $id      Entity id (server/client/tool/token/log).
	 * @param string $sub     Optional second id (tool drill = server/tool).
	 * @param string $period  Period filter.
	 * @param int    $page    Table page.
	 * @return Result
	 */
	public function section_drill( $section, $id, $sub = '', $period = 'today', $page = 1 ) {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		// Ids and period stay raw: the API client encodes when building
		// the upstream path (avoids double-encoding names with spaces).
		$page = max( 1, (int) $page );

		switch ( $section ) {
			case 'requests':
				$result = $this->api->guardian_request_detail( $id, $period );
				break;
			case 'tool-performance':
				$result = '' !== (string) $sub
					? $this->api->guardian_tool_drill( $id, $sub, $period, $page )
					: Result::error( 'vc_drill_missing_tool' );
				break;
			case 'connectors':
			case 'cost':
			case 'security':
			case 'errors':
				$endpoint = array(
					'connectors' => 'connectors',
					'cost'       => 'cost',
					'security'   => 'security-posture',
					'errors'     => 'errors',
				)[ $section ];

				$result = $this->api->guardian_section_drill( $endpoint, $id, $period, $page );
				break;
			case 'clients':
				$result = $this->api->guardian_client_drill( $id, $period, $page );
				break;
			case 'token-fleet':
				$result = $this->api->guardian_token_drill( $id, $period, $page );
				break;
			default:
				return Result::error( 'vc_drill_unknown_section' );
		}

		return $result;
	}

	/**
	 * Generate an evidence pack (paid-only upstream; Free arrives as a 403).
	 *
	 * @return Result
	 */
	public function evidence_pack_generate() {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		return $this->api->evidence_pack_generate();
	}

	/**
	 * One evidence pack (manifest digest, in-panel verification).
	 *
	 * @param string $pack_id Pack id.
	 * @return Result
	 */
	public function evidence_pack_view( $pack_id ) {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		return $this->api->evidence_pack( $pack_id );
	}

	/**
	 * AI Governance section payload (live API read).
	 *
	 * Sections mirror the Vinkius cloud governance navigation. Tabulated
	 * sections carry their table page server-side; agents is the
	 * paginated registry (25 rows/page).
	 *
	 * @param string $section overview|clients|connectors|token-fleet|agents|cost|tool-performance|security|errors|compliance.
	 * @param string $period  Period filter.
	 * @param int    $page    Table/registry page.
	 * @return Result
	 */
	public function governance_section( $section, $period = 'today', $page = 1 ) {
		$sections = array(
			'overview'           => array( $this->api, 'guardian_overview' ),
			'clients'            => array( $this->api, 'guardian_clients' ),
			'connectors'         => array( $this->api, 'guardian_connectors' ),
			'token-fleet'        => array( $this->api, 'guardian_token_fleet' ),
			'agents'             => array( $this->api, 'guardian_agents' ),
			'cost'               => array( $this->api, 'guardian_cost' ),
			'tool-performance'   => array( $this->api, 'guardian_tool_performance' ),
			'security'           => array( $this->api, 'guardian_security_posture' ),
			'errors'             => array( $this->api, 'guardian_errors' ),
			'compliance'         => array( $this->api, 'guardian_compliance' ),
		);

		if ( ! isset( $sections[ $section ] ) ) {
			return Result::error( 'vc_unknown_section' );
		}

		switch ( $section ) {
			case 'token-fleet':
			case 'connectors':
			case 'cost':
			case 'security':
			case 'errors':
				$result = call_user_func( $sections[ $section ], $period, max( 1, (int) $page ) );
				break;
			case 'tool-performance':
				// Both tables page in lockstep; the API keeps independent pagers.
				$page = max( 1, (int) $page );
				$result = call_user_func( $sections[ $section ], $period, $page, 25, $page, 25 );
				break;
			case 'compliance':
				// The scorecard is period-independent upstream.
				$result = $this->api->guardian_compliance();
				break;
			case 'agents':
				// Server-paginated registry (25 rows/page, KPIs aggregate all).
				$result = $this->api->guardian_agents( $period, max( 1, (int) $page ), 25 );
				break;
			default:
				$result = call_user_func( $sections[ $section ], $period );
				break;
		}

		if ( ! $result->is_ok() ) {
			return $result;
		}

		$usage       = 'overview' === $section ? $this->api->usage() : null;
		$circuit     = 'overview' === $section ? $this->api->circuit_breaker() : null;
		$live        = 'overview' === $section ? $this->api->guardian_live_feed( 'today', 1 ) : null;
		$usage_data  = ( $usage && $usage->is_ok() ) ? $usage->data() : array();
		$circuit_data = ( $circuit && $circuit->is_ok() ) ? ( $circuit->data()['data'] ?? $circuit->data() ) : array();
		$live_data   = ( $live && $live->is_ok() ) ? $live->data() : array();

		return Result::ok(
			array(
				'section'   => $section,
				'data'      => $result->data(),
				'period'    => $period,
				'live'      => is_array( $live_data ) ? $live_data : array(),
				'usage'     => $usage_data,
				'plan'      => isset( $usage_data['plan'] ) ? (string) $usage_data['plan'] : ( $usage_data ? 'free' : null ),
				'halted'    => Options::is_halted(),
				'circuit'   => $circuit_data,
			)
		);
	}

	/**
	 * Capabilities catalog: search (q) or browse, plus stats and categories.
	 *
	 * @param string $q        Query (empty = browse).
	 * @param int    $page     Page.
	 * @param string $sort     popular|newest (browse only).
	 * @param string $category Category slug (browse only).
	 * @return Result
	 */
	public function capabilities( $q = '', $page = 1, $sort = 'popular', $category = '' ) {
		$q        = trim( (string) $q );
		$page     = max( 1, (int) $page );
		$category = sanitize_title( (string) $category );

		if ( mb_strlen( $q ) >= 2 ) {
			$result = $this->api->marketplace_search( $q, $page );
			$items  = $result->is_ok() ? ( $result->data()['results'] ?? array() ) : array();
			$more   = $result->is_ok() ? (bool) ( $result->data()['has_more'] ?? false ) : false;
		} else {
			$result = $this->api->marketplace_listings( $sort, $page, $category );
			$raw    = $result->is_ok() ? $result->data() : array();
			$items  = $raw['data'] ?? $raw['listings'] ?? array();
			$more   = $result->is_ok()
				? ( (int) ( $raw['current_page'] ?? $page ) < (int) ( $raw['last_page'] ?? $page ) )
				: false;
		}

		$stats      = $this->api->marketplace_stats();
		$categories = $this->api->marketplace_categories();

		return Result::ok(
			array(
				'items'       => $items,
				'has_more'    => $more,
				'page'        => $page,
				'searching'   => mb_strlen( $q ) >= 2,
				'stats'       => $stats->is_ok() ? $stats->data() : array(),
				'categories'  => $categories->is_ok() ? ( $categories->data()['categories'] ?? $categories->data()['data'] ?? array() ) : array(),
			)
		);
	}

	/**
	 * AI-narrated briefing of one scope for the panel's briefing card.
	 *
	 * @param string $scope  Scope key (cloud ai-briefing scopes).
	 * @param string $period  Period filter.
	 * @return Result
	 */
	public function briefing( $scope, $period = 'today' ) {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		$scopes = array( 'overview', 'clients', 'connectors', 'cost', 'tool-performance', 'security-posture', 'errors', 'token-fleet' );

		if ( ! in_array( $scope, $scopes, true ) ) {
			return Result::error( 'vc_unknown_scope' );
		}

		$usage      = $this->api->usage();
		$usage_data = $usage->is_ok() ? $usage->data() : array();
		$plan       = isset( $usage_data['plan'] ) ? (string) $usage_data['plan'] : 'free';
		$briefing   = $this->api->guardian_ai_briefing( $scope, $period );
		$raw        = $briefing->is_ok() ? $briefing->data() : array();
		$text       = $raw['summary'] ?? $raw['text'] ?? $raw['briefing'] ?? null;

		return Result::ok(
			array(
				'scope'   => $scope,
				'period'  => $period,
				'plan'    => $plan,
				'is_free' => 'free' === $plan,
				'text'    => is_string( $text ) ? $text : null,
				'upstream'=> $briefing->is_ok() ? 'ok' : $briefing->code(),
			)
		);
	}

	/**
	 * Generated evidence packs (Compliance & Evidence section).
	 *
	 * @return Result
	 */
	public function evidence_packs() {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		$result = $this->api->guardian_evidence_packs();

		if ( ! $result->is_ok() ) {
			return $result;
		}

		$raw = $result->data();
		$packs = array();

		foreach ( array( $raw['packs'] ?? null, $raw['data'] ?? null, $raw ) as $candidate ) {
			if ( is_array( $candidate ) ) {
				$packs = $candidate;
				break;
			}
		}

		return Result::ok( array( 'packs' => $packs ) );
	}

	/**
	 * Full agent registry (export only): whole registry in one read.
	 *
	 * @param string $period today|3d|7d|14d|30d.
	 * @return Result
	 */
	public function agent_registry_export( $period = 'today' ) {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		return $this->api->guardian_agents( $period, 1, 0 );
	}

	/**
	 * Trigger SOAR kill on one connector.
	 *
	 * @param string $server_id Connector id.
	 * @param string $reason    Operator reason.
	 * @return Result
	 */
	public function halt_connector( $server_id, $reason ) {
		/*
		 * Per-connector SOAR kill. Deliberately does NOT stamp halted_at:
		 * its inverse is soar_lift (1h quarantine TTL), not the runtime's
		 * global restore — stamping would make resume_all() lift a global
		 * halt that was never engaged.
		 */
		return $this->api->soar_kill( $server_id, $reason );
	}

	/**
	 * Lift one connector's SOAR quarantine.
	 *
	 * @param string $server_id Connector id.
	 * @return Result
	 */
	public function resume_connector( $server_id ) {
		return $this->api->soar_lift( $server_id );
	}

	/**
	 * Extend one connector's SOAR quarantine by another TTL window.
	 *
	 * @param string $server_id Connector id.
	 * @return Result
	 */
	public function extend_connector( $server_id ) {
		return $this->api->soar_extend( $server_id );
	}

	/**
	 * Org operator audit log (who did what — IAM audit trail).
	 *
	 * @param int $page Page.
	 * @return Result
	 */
	public function audit_feed( $scope = 'org', $page = 1 ) {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		return 'activity' === $scope
			? $this->api->activity_log( $page )
			: $this->api->org_audit_log( $page );
	}

	/**
	 * Enforcement settings mirrors (DLP/FinOps) — read-only context.
	 *
	 * @param string $scope dlp|finops.
	 * @return Result
	 */
	public function enforcement_settings( $scope ) {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		return 'finops' === $scope ? $this->api->finops() : $this->api->dlp();
	}

	/**
	 * Local kill switch: block this site's agent-facing surfaces only.
	 *
	 * No upstream call — the Vinkius runtime keeps running for everyone
	 * else. This is what the Settings "Local kill switch" must trigger,
	 * so a per-site toggle can never halt the whole Vinkius account.
	 *
	 * @return Result
	 */
	public function halt_local() {
		Options::set( 'halt', true );

		return Result::ok( array( 'local' => true, 'runtime' => 'skipped' ) );
	}

	/**
	 * Trigger global halt (org-wide, via the deliberate "Halt all" action).
	 *
	 * Stamps `halted_at` so resume_all() knows the runtime side was actually
	 * engaged (and lifts it) instead of just clearing the local flag.
	 *
	 * @return Result
	 */
	public function halt_all() {
		$local = true;
		Options::set( 'halt', true );
		Options::set( 'halted_at', current_time( 'mysql', true ) );

		if ( ! Options::is_configured() ) {
			return Result::ok( array( 'local' => $local, 'runtime' => 'skipped' ) );
		}

		$result = $this->api->global_halt();
		$state  = $result->is_ok() ? 'ok' : $result->code();

		return Result::ok( array( 'local' => $local, 'runtime' => $state ) );
	}

	/**
	 * Local resume: clear this site's kill switch only.
	 *
	 * No upstream call — the mirror of halt_local(). Toggling the Settings
	 * "Local kill switch" back off must not invoke the runtime's global
	 * restore, and it must NOT clear the global-halt marker: halted_at
	 * belongs to the global pair (halt_all / resume_all), so a later
	 * resume_all can still lift a runtime halt that is actually engaged.
	 *
	 * @return Result
	 */
	public function resume_local() {
		Options::set( 'halt', false );

		return Result::ok( array( 'local' => true, 'runtime' => 'skipped' ) );
	}

	/**
	 * Resume: clear the local halt and lift the runtime global halt.
	 *
	 * The upstream restore only runs when a global halt was actually
	 * engaged (halt_all stamped halted_at). A purely local kill-switch
	 * never touched the runtime, so resuming it must not call the
	 * platform either.
	 *
	 * @return Result
	 */
	public function resume_all() {
		$global_engaged = '' !== (string) Options::get( 'halted_at', '' );
		Options::set( 'halt', false );
		Options::set( 'halted_at', null );

		if ( ! $global_engaged || ! Options::is_configured() ) {
			return Result::ok( array( 'runtime' => 'skipped' ) );
		}

		$result = $this->api->global_restore();

		return Result::ok( array( 'runtime' => $result->is_ok() ? 'ok' : $result->code() ) );
	}

	/**
	 * Reset a tripped circuit breaker.
	 *
	 * @return Result
	 */
	public function circuit_reset() {
		return $this->api->circuit_breaker_reset();
	}
}
