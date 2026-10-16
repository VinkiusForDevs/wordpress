<?php
/**
 * Result envelope for the prepared API layer.
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable result wrapper so every prepared API call degrades gracefully
 * when the plugin is not connected yet.
 *
 * States: ok | not_configured | not_connected | error
 */
final class Result {

	/**
	 * State.
	 *
	 * @var string
	 */
	private $state;

	/**
	 * Payload (array|null).
	 *
	 * @var array|null
	 */
	private $data;

	/**
	 * Machine-readable error code, if any.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Constructor.
	 *
	 * @param string      $state 'ok'|'not_configured'|'not_connected'|'error'.
	 * @param array|null $data  Payload.
	 * @param string      $code  Error code.
	 */
	private function __construct( $state, $data = null, $code = '' ) {
		$this->state = $state;
		$this->data  = $data;
		$this->code  = $code;
	}

	/**
	 * @return Result
	 */
	public static function ok( $data = array() ) {
		return new self( 'ok', is_array( $data ) ? $data : array( 'value' => $data ) );
	}

	/**
	 * @return Result
	 */
	public static function not_configured() {
		return new self( 'not_configured', null, 'vc_not_configured' );
	}

	/**
	 * @return Result
	 */
	public static function not_connected() {
		return new self( 'not_connected', null, 'vc_not_connected' );
	}

	/**
	 * @param string $code Machine code.
	 * @param mixed  $data Payload, if useful.
	 * @return Result
	 */
	public static function error( $code, $data = null ) {
		return new self( 'error', is_array( $data ) ? $data : null, $code );
	}

	/**
	 * @return bool
	 */
	public function is_ok() {
		return 'ok' === $this->state;
	}

	/**
	 * @return string
	 */
	public function state() {
		return $this->state;
	}

	/**
	 * @return string
	 */
	public function code() {
		return $this->code;
	}

	/**
	 * @return array
	 */
	public function data() {
		return (array) $this->data;
	}

	/**
	 * @return array Shape the admin UI can render as-is.
	 */
	public function to_view() {
		return array(
			'state' => $this->state,
			'code'  => $this->code,
			'data'  => (array) $this->data,
		);
	}
}
