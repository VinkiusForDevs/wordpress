<?php
/**
 * Class autoloader.
 *
 * Maps VinkiusConnector classes to their kebab-case file locations.
 *
 * This file is part of the Vinkius Connector plugin.
 *
 * @license GPLv2 or later https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package VinkiusConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Autoloads VinkiusConnector classes.
 */
final class VcAutoloader {

	/**
	 * Explicit class-to-file map (relative to includes/).
	 *
	 * @var string[]
	 */
	private static $map = array(
		'Plugin'        => 'class-plugin.php',
		'Activator'     => 'class-activator.php',
		'Deactivator'   => 'class-deactivator.php',
		'Options'       => 'class-options.php',
		'Result'        => 'class-result.php',
		'HttpClient'    => 'class-http-client.php',
		'VinkiusApi'    => 'class-vinkius-api.php',
		'Governance'    => 'class-governance.php',
		'Template'      => 'class-template.php',
		'TrustBadge'    => 'class-trust-badge.php',
		'Shortcodes'    => 'class-shortcodes.php',
		'Links'         => 'class-links.php',
		'Notifications' => 'class-notifications.php',
		'Auth/AuthStore'    => 'auth/class-auth-store.php',
		'Auth/DeviceFlow'   => 'auth/class-device-flow.php',
		'I18n/I18n'         => 'i18n/class-i18n.php',
		'Rest/Rest'           => 'rest/class-rest.php',
		'Rest/ConsentService' => 'rest/class-consent-service.php',
		'Models/ConsentRepo'  => 'models/class-consent-repo.php',
		'Admin/Admin'         => 'admin/class-admin.php',
		'Admin/ScreenBase'    => 'admin/class-screen-base.php',
		'Admin/ScreenDashboard'  => 'admin/class-screen-dashboard.php',
		'Admin/ScreenAgents'     => 'admin/class-screen-agents.php',
		'Admin/ScreenConsent'    => 'admin/class-screen-consent.php',
		'Admin/ScreenIncidents'  => 'admin/class-screen-incidents.php',
		'Admin/ScreenEvidence'   => 'admin/class-screen-evidence.php',
		'Admin/ScreenMarketplace'=> 'admin/class-screen-marketplace.php',
		'Admin/ScreenSettings'   => 'admin/class-screen-settings.php',
	);

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload a single class.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	public static function autoload( $class ) {
		$prefix = 'VinkiusConnector\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );

		if ( isset( self::$map[ $relative ] ) ) {
			$file = VC_PLUGIN_DIR . 'includes/' . self::$map[ $relative ];

			if ( file_exists( $file ) ) {
				require $file;
			}
		}
	}
}

VcAutoloader::register();
