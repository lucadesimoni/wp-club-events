<?php
/**
 * Plugin Name:       WP Club Events Simple
 * Plugin URI:        https://github.com/lucadesimoni/wp-club-events
 * Description:       Event calendar for clubs and associations: Google Calendar sync, timeline, calendar and tile views, ICS export, sharing and email subscriptions.
 * Version:           1.6.0
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

/*
 * Never break the site: if the plugin cannot run safely here, show an admin
 * notice and stop instead of causing a fatal error.
 */
$ce_load_problem = '';
$ce_classes      = [
    'CE_Plugin', 'CE_Safe', 'CE_Style', 'CE_CPT', 'CE_Google_Calendar', 'CE_ICS_Export',
    'CE_Subscription', 'CE_Shortcodes', 'CE_REST_API', 'CE_Frontend_Submit', 'CE_Astra_Compat',
    'CE_Patterns', 'CE_Admin', 'CE_Elementor', 'CE_Elementor_Hub', 'CE_Elementor_Tiles',
    'CE_Elementor_Timeline', 'CE_Elementor_Overview', 'CE_Elementor_Cards', 'CE_Elementor_List',
    'CE_Elementor_Yearly', 'CE_Elementor_Share', 'CE_Elementor_Subscribe', 'CE_Elementor_Submit',
    'CE_Elementor_MyEvents',
];
if ( defined( 'CE_PLUGIN_FILE' ) ) {
    // Two copies active (e.g. an old folder left behind after an update).
    $ce_load_problem = [ 'duplicate', '' ];
} elseif ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    $ce_load_problem = [ 'php', PHP_VERSION ];
} elseif ( isset( $GLOBALS['wp_version'] ) && version_compare( $GLOBALS['wp_version'], '6.5', '<' ) ) {
    $ce_load_problem = [ 'wp', $GLOBALS['wp_version'] ];
} elseif ( trait_exists( 'CE_Elementor_Controls', false ) ) {
    $ce_load_problem = [ 'class', 'CE_Elementor_Controls' ];
} else {
    foreach ( $ce_classes as $ce_class ) {
        if ( class_exists( $ce_class, false ) ) {
            // Another plugin uses the same class name; loading ours would be fatal.
            $ce_load_problem = [ 'class', $ce_class ];
            break;
        }
    }
}

if ( '' !== $ce_load_problem ) {
    // Translated inside the callback: admin_notices runs after init, so this
    // never loads translations too early.
    add_action( 'admin_notices', function () use ( $ce_load_problem ) {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        list( $code, $detail ) = $ce_load_problem;
        $messages = [
            'duplicate' => __( 'WP Club Events Simple is active twice (two copies of the plugin are installed). Only the first copy is running; deactivate and delete the other one.', 'club-events' ),
            /* translators: %s: PHP version. */
            'php'       => __( 'WP Club Events Simple needs PHP 7.4 or later (this site runs %s) and has not been loaded.', 'club-events' ),
            /* translators: %s: WordPress version. */
            'wp'        => __( 'WP Club Events Simple needs WordPress 6.5 or later (this site runs %s) and has not been loaded.', 'club-events' ),
            /* translators: %s: PHP class name. */
            'class'     => __( 'WP Club Events Simple has not been loaded: another active plugin already defines %s. Deactivate that plugin to use WP Club Events Simple.', 'club-events' ),
        ];
        echo '<div class="notice notice-error"><p>' . esc_html( sprintf( $messages[ $code ], $detail ) ) . '</p></div>';
    } );
    return;
}

define( 'CE_VERSION',     '1.6.0' );
define( 'CE_PLUGIN_FILE', __FILE__ );
define( 'CE_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CE_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CE_PLUGIN_BASE', plugin_basename( __FILE__ ) );

require_once CE_PLUGIN_DIR . 'includes/class-plugin.php';

if ( ! function_exists( 'ce_plugin' ) ) {
    function ce_plugin() {
        return CE_Plugin::instance();
    }
}

// A failure while setting up is reported as an admin notice instead of
// taking the site down.
add_action( 'plugins_loaded', function () {
    try {
        ce_plugin();
    } catch ( \Throwable $e ) {
        if ( class_exists( 'CE_Safe', false ) ) {
            CE_Safe::report( 'startup', $e );
        }
        add_action( 'admin_notices', function () {
            if ( current_user_can( 'activate_plugins' ) ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'WP Club Events Simple could not start and has been paused for this request; the rest of the site is unaffected. With WP_DEBUG enabled, the PHP error log shows why.', 'club-events' ) . '</p></div>';
            }
        } );
    }
} );

register_activation_hook(   __FILE__, [ 'CE_Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'CE_Plugin', 'deactivate' ] );
