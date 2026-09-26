<?php
/**
 * Plugin Name:       WP Club Events Simple
 * Plugin URI:        https://github.com/lucadesimoni/wp-club-events
 * Description:       Event calendar for clubs and associations: Google Calendar sync, timeline, calendar and tile views, ICS export, sharing and email subscriptions.
 * Version:           1.5.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * Author:            Outthinkx Club
 * Author URI:        https://github.com/lucadesimoni
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       club-events
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>WP Club Events Simple</strong> requires PHP 7.4 or higher. You are running PHP ' . esc_html( PHP_VERSION ) . '.</p></div>';
    } );
    return;
}

define( 'CE_VERSION',     '1.5.0' );
define( 'CE_PLUGIN_FILE', __FILE__ );
define( 'CE_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CE_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CE_PLUGIN_BASE', plugin_basename( __FILE__ ) );

require_once CE_PLUGIN_DIR . 'includes/class-plugin.php';

function ce_plugin() {
    return CE_Plugin::instance();
}
add_action( 'plugins_loaded', 'ce_plugin' );

register_activation_hook(   __FILE__, [ 'CE_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'CE_Plugin', 'deactivate' ] );
