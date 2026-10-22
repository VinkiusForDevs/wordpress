<?php
/**
 * Plugin Name: Vinkius Connector
 * Description: Connect WordPress to Vinkius AI infrastructure.
 * Version: 1.0.2
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Vinkius
 * Author URI: https://vinkius.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vinkius-connector
 * Domain Path: /languages
 *
 * @package VinkiusConnector
 */

defined( 'ABSPATH' ) || exit;

/**
 * Vinkius Connector constants.
 */
define( 'VC_VERSION', '1.0.2' );
define( 'VC_PLUGIN_FILE', __FILE__ );
define( 'VC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'VC_TEXT_DOMAIN', 'vinkius-connector' );

// Vinkius platform endpoints — immutable infrastructure, never user-configured.
define( 'VC_API_BASE_URL', 'https://api.vinkius.com' );
define( 'VC_FRONTEND_BASE_URL', 'https://cloud.vinkius.com' );

require_once VC_PLUGIN_DIR . 'includes/autoload.php';

/**
 * Boot the plugin.
 */
VinkiusConnector\Plugin::boot();

register_activation_hook( __FILE__, array( 'VinkiusConnector\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'VinkiusConnector\Deactivator', 'deactivate' ) );
