<?php
/**
 * Plugin Name:       Club Events – STV Malters
 * Description:       Site-specific add-on for WP Club Events Simple: the one-time import of the Aktivriege 2025/2026 annual programme.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  club-events
 * Author:            Outthinkx Club
 * Author URI:        https://github.com/lucadesimoni
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       club-events-stv-malters
 */

defined( 'ABSPATH' ) || exit;

// Until Club Events 1.4.0 this import shipped inside the plugin. It holds
// club-specific data, so it now lives in this add-on and the plugin itself
// stays generic. Run it from WP-CLI or with ?ce_run_import=aktivriege2026
// on any admin URL (see import-aktivriege-2026.php).
if ( is_admin() ) {
    require_once __DIR__ . '/import-aktivriege-2026.php';
}
