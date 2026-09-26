<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Remove custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ce_subscribers" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ce_calendars" );

// Remove plugin options
$options = [
    'ce_db_version',
    'ce_events_page',
    'ce_future_months',
    'ce_google_api_key',
    'ce_hide_archive',
    'ce_ics_feed_enabled',
    'ce_past_months',
    'ce_self_service_auto_publish_role',
    'ce_self_service_enabled',
    'ce_self_service_role',
    'ce_subscription_enabled',
    'ce_subscription_from_email',
    'ce_subscription_from_name',
    'ce_sync_interval',
];
foreach ( $options as $opt ) {
    delete_option( $opt );
}

// Remove all club_event posts and their meta
$post_ids = get_posts( [
    'post_type'      => 'club_event',
    'post_status'    => 'any',
    'numberposts'    => -1,
    'fields'         => 'ids',
] );
foreach ( $post_ids as $id ) {
    wp_delete_post( (int) $id, true );
}

flush_rewrite_rules();
