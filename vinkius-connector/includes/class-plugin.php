<?php
/**
 * Plugin orchestrator.
 *
 * @license GPLv2 or later
 *
 * @package VinkiusConnector
 */

namespace VinkiusConnector;

defined( 'ABSPATH' ) || exit;

/**
 * Boots all subsystems.
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/**
	 * Subsystems, in load order.
	 *
	 * @var string[]
	 */
	private static $subsystems = array(
		'VinkiusConnector\\I18n\\I18n',
		'VinkiusConnector\\Rest\\Rest',
		'VinkiusConnector\\Shortcodes',
		'VinkiusConnector\\TrustBadge',
		'VinkiusConnector\\Notifications',
		'VinkiusConnector\\Admin\\Admin',
	);

	/**
	 * Singleton.
	 *
	 * @return Plugin
	 */
	public static function get() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot.
	 *
	 * @return void
	 */
	public static function boot() {
		self::get();
	}

	private function __construct() {
		add_action( 'init', array( $this, 'on_init' ) );
		add_action( 'admin_init', array( $this, 'on_admin_init' ) );
	}

	/**
	 * Load subsystems and schedule the digest.
	 *
	 * @return void
	 */
	public function on_init() {
		foreach ( self::$subsystems as $class ) {
			$instance = new $class();

			if ( method_exists( $instance, 'register' ) ) {
				$instance->register();
			}
		}

		Notifications::schedule();
	}

	/**
	 * Admin-only save handlers.
	 *
	 * @return void
	 */
	public function on_admin_init() {
		add_action( 'admin_post_vc_save_settings', array( Admin\ScreenSettings::class, 'save_settings' ) );
	}
}
