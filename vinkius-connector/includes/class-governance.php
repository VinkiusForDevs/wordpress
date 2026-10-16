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

	public function __construct( VinkiusApi $api = null ) {
		$this->api = null !== $api ? $api : new VinkiusApi();
	}

	/**
	 * Dashboard payload: KPIs, live activity, usage, circuit, halt state.
	 *
	 * @return Result
	 */
	public function panel() {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		$overview = $this->api->guardian_overview( 'today' );
		$fleet    = $this->api->guardian_token_fleet( 'today' );
		$usage    = $this->api->usage();
		$circuit  = $this->api->circuit_breaker();
		$live     = $this->api->guardian_live_feed( 'today', 1 );

		if ( ! $overview->is_ok() && ! $live->is_ok() ) {
			return 'not_connected' === $overview->state() ? Result::not_connected() : $overview;
		}

		$kpi    = $overview->is_ok() ? ( $overview->data()['kpi'] ?? array() ) : array();
		$mcps   = $fleet->is_ok() ? ( $fleet->data()['mcps'] ?? array() ) : array();

		$agents        = 0;
		$active = 0;

		foreach ( $mcps as $mcp ) {
			if ( (int) ( $mcp['total_requests'] ?? 0 ) > 0 ) {
				$active++;
				$agents += (int) ( $mcp['token_count'] ?? 0 );
			}
		}

		$usage_data = $usage->is_ok() ? $usage->data() : array();

		return Result::ok(
			array(
				'kpis'     => array(
					'active_agents'       => $agents,
					'executions_today'    => isset( $kpi['total_requests'] ) ? (int) $kpi['total_requests'] : 0,
					'blocked_events'      => ( (int) ( $kpi['pii_redactions'] ?? 0 ) ) + ( (int) ( $kpi['payloads_truncated'] ?? 0 ) ),
					'active_connectors'   => $active,
					'success_rate'        => isset( $kpi['success_rate'] ) ? (float) $kpi['success_rate'] : 0.0,
					'avg_latency_ms'      => isset( $kpi['avg_latency_ms'] ) ? (int) $kpi['avg_latency_ms'] : 0,
					'reliability'         => isset( $kpi['vinkius_reliability'] ) ? (float) $kpi['vinkius_reliability'] : 100.0,
				),
				'mcps'       => $mcps,
				'live'       => $live->is_ok() ? ( $live->data()['live_feed'] ?? array() ) : array(),
				'usage'      => $usage_data,
				'plan'       => isset( $usage_data['plan'] ) ? (string) $usage_data['plan'] : 'free',
				'circuit'    => $circuit->is_ok() ? ( $circuit->data()['data'] ?? $circuit->data() ) : array(),
				'halted'     => Options::is_halted(),
				'free'       => 'free' === ( $usage_data['plan'] ?? 'free' ),
			)
		);
	}

	/**
	 * Agent fleet (tokens) with activity counts.
	 *
	 * @param int $page Page.
	 * @return Result
	 */
	public function agents( $page = 1 ) {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		return $this->api->guardian_token_fleet( '7d', $page );
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
			)
		);
	}

	/**
	 * Evidence for one connector: timeline + per-server logs.
	 *
	 * @param string $server_id Connector id.
	 * @param int    $page      Page.
	 * @return Result
	 */
	public function evidence( $server_id, $page = 1 ) {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		$timeline = $this->api->audit_log( $server_id, 'timeline' );
		$logs     = $this->api->request_logs( $server_id, $page );

		return Result::ok(
			array(
				'timeline' => $timeline->is_ok() ? $timeline->data() : array(),
				'logs'     => $logs->is_ok() ? $logs->data() : array(),
			)
		);
	}

	/**
	 * AI Governance sidebar section payload (live API read).
	 *
	 * Sections mirror the Vinkius cloud governance navigation.
	 *
	 * @param string $section overview|clients|connectors|token-fleet|cost|tool-performance|security|errors.
	 * @param string $period  Period filter.
	 * @return Result
	 */
	public function governance_section( $section, $period = '30d' ) {
		$sections = array(
			'overview'           => array( $this->api, 'guardian_overview' ),
			'clients'            => array( $this->api, 'guardian_clients' ),
			'connectors'         => array( $this->api, 'guardian_connectors' ),
			'token-fleet'        => array( $this->api, 'guardian_token_fleet' ),
			'cost'               => array( $this->api, 'guardian_cost' ),
			'tool-performance'   => array( $this->api, 'guardian_tool_performance' ),
			'security'           => array( $this->api, 'guardian_security_posture' ),
			'errors'             => array( $this->api, 'guardian_errors' ),
		);

		if ( ! isset( $sections[ $section ] ) ) {
			return Result::error( 'vc_unknown_section' );
		}

		switch ( $section ) {
			case 'token-fleet':
				$result = call_user_func( $sections[ $section ], $period, 1 );
				break;
			case 'connectors':
				$result = call_user_func( $sections[ $section ], $period, 1 );
				break;
			default:
				$result = call_user_func( $sections[ $section ], $period );
				break;
		}

		if ( ! $result->is_ok() ) {
			return $result;
		}

		$usage   = 'overview' === $section ? $this->api->usage() : null;
		$circuit = 'overview' === $section ? $this->api->circuit_breaker() : null;
		$live    = 'overview' === $section ? $this->api->guardian_live_feed( 'today', 1 ) : null;
		$usage_data = $usage->is_ok() ? $usage->data() : array();

		return Result::ok(
			array(
				'section'   => $section,
				'data'      => $result->data(),
				'period'    => $period,
				'live'      => $live,
				'usage'     => $usage_data,
				'plan'      => isset( $usage_data['plan'] ) ? (string) $usage_data['plan'] : ( $usage_data ? 'free' : null ),
				'halted'    => Options::is_halted(),
				'circuit'   => $circuit->is_ok() ? ( $circuit->data()['data'] ?? $circuit->data() ) : array(),
			)
		);
	}

	/**
	 * Capabilities catalog: search (q) or browse, plus stats and categories.
	 *
	 * @param string $q       Query (empty = browse).
	 * @param int    $page    Page.
	 * @param string $sort    popular|newest (browse only).
	 * @return Result
	 */
	public function capabilities( $q = '', $page = 1, $sort = 'popular' ) {
		$q    = trim( (string) $q );
		$page = max( 1, (int) $page );

		if ( mb_strlen( $q ) >= 2 ) {
			$result = $this->api->marketplace_search( $q, $page );
			$items  = $result->is_ok() ? ( $result->data()['results'] ?? array() ) : array();
			$more   = $result->is_ok() ? (bool) ( $result->data()['has_more'] ?? false ) : false;
		} else {
			$result = $this->api->marketplace_listings( $sort, $page );
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
	 * List of connectors for the evidence picker.
	 *
	 * @return Result
	 */
	public function connectors() {
		if ( ! Options::is_configured() ) {
			return Result::not_configured();
		}

		return $this->api->servers();
	}

	/**
	 * Trigger SOAR kill on one connector.
	 *
	 * @param string $server_id Connector id.
	 * @param string $reason    Operator reason.
	 * @return Result
	 */
	public function halt_connector( $server_id, $reason ) {
		$result = $this->api->soar_kill( $server_id, $reason );

		if ( $result->is_ok() ) {
			Options::set( 'halted_at', current_time( 'mysql', true ) );
		}

		return $result;
	}

	/**
	 * Trigger global halt.
	 *
	 * @return Result
	 */
	public function halt_all() {
		$local = true;
		Options::set( 'halt', true );

		if ( ! Options::is_configured() ) {
			return Result::ok( array( 'local' => $local, 'runtime' => 'skipped' ) );
		}

		$result = $this->api->global_halt();
		$state  = $result->is_ok() ? 'ok' : $result->code();

		return Result::ok( array( 'local' => $local, 'runtime' => $state ) );
	}

	/**
	 * Resume: clear local halt and lift runtime global halt.
	 *
	 * @return Result
	 */
	public function resume_all() {
		Options::set( 'halt', false );
		Options::set( 'halted_at', null );

		if ( ! Options::is_configured() ) {
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
