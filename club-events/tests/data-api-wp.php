<?php
/**
 * Data access integration tests: REST meta and abilities. Needs a real
 * WordPress 6.9+ site with the plugin active and an administrator user.
 * Creates one event and one event type and removes them again.
 *
 * Run: wp eval-file club-events/tests/data-api-wp.php
 */
$GLOBALS['ce_t'] = [ 0, 0 ];
function t( $label, $ok, $detail = '' ) { $GLOBALS['ce_t'][ $ok ? 0 : 1 ]++; echo ( $ok ? "  PASS  " : "  FAIL  " ) . $label . ( $ok ? '' : ' — ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) ) . "\n"; }

$admin = get_users( [ 'role' => 'administrator', 'number' => 1 ] )[0];
wp_set_current_user( 0 );

echo "Abilities\n";
$names = [ 'list-events', 'get-event', 'create-event', 'update-event', 'delete-event', 'list-terms' ];
foreach ( $names as $n ) { t( "club-events/$n registered", (bool) wp_get_ability( "club-events/$n" ) ); }

$create = wp_get_ability( 'club-events/create-event' );
$r = $create->execute( [ 'title' => 'Anon', 'start' => '2026-12-01' ] );
t( 'anonymous user cannot create', is_wp_error( $r ), $r );

wp_set_current_user( $admin->ID );
$r = $create->execute( [ 'title' => 'Turnfest Test', 'start' => '2026-06-26', 'end' => '2026-06-28', 'all_day' => true, 'location' => 'Malters', 'types' => [ 'Wettkampf Test' ], 'status' => 'publish' ] );
t( 'admin creates event', is_array( $r ) && ! empty( $r['id'] ), $r );
$id = is_array( $r ) ? $r['id'] : 0;
t( 'plain start date → 00:00:00', '2026-06-26 00:00:00' === get_post_meta( $id, '_ce_start_date', true ), get_post_meta( $id, '_ce_start_date', true ) );
t( 'plain end date → 23:59:59', '2026-06-28 23:59:59' === get_post_meta( $id, '_ce_end_date', true ), get_post_meta( $id, '_ce_end_date', true ) );
t( 'all_day stored as 1', '1' === get_post_meta( $id, '_ce_all_day', true ) );
t( 'unknown type name is created and assigned', has_term( 'wettkampf-test', 'event_type', $id ) );
t( 'source is manual', 'manual' === get_post_meta( $id, '_ce_source', true ) );

$upd = wp_get_ability( 'club-events/update-event' );
$r = $upd->execute( [ 'id' => $id, 'start' => '2026-06-26T07:00:00Z', 'all_day' => false ] );
t( 'update converts UTC to site time', '2026-06-26 09:00:00' === get_post_meta( $id, '_ce_start_date', true ), get_post_meta( $id, '_ce_start_date', true ) );
t( 'update keeps untouched fields', 'Malters' === get_post_meta( $id, '_ce_location', true ) && 'Turnfest Test' === get_the_title( $id ) );
$r = $upd->execute( [ 'id' => $id, 'start' => 'not a date' ] );
t( 'invalid start is rejected', is_wp_error( $r ), $r );

$list = wp_get_ability( 'club-events/list-events' )->execute( [ 'from' => '2026-06-01', 'to' => '2026-06-30' ] );
t( 'list-events finds it by range', is_array( $list ) && in_array( $id, wp_list_pluck( $list, 'id' ), true ), $list );
$terms = wp_get_ability( 'club-events/list-terms' )->execute( [ 'taxonomy' => 'event_type' ] );
t( 'list-terms returns types with colour key', is_array( $terms ) && array_key_exists( 'color', $terms[0] ?? [] ), $terms );

echo "REST meta\n";
$req = new WP_REST_Request( 'POST', '/wp/v2/club_event/' . $id );
$req->set_body_params( [ 'meta' => [ '_ce_location' => 'Turnhalle Oberei', '_ce_end_date' => '2026-06-27', '_ce_color' => 'not-a-colour' ] ] );
$res = rest_do_request( $req );
t( 'REST update ok', 200 === $res->get_status(), $res->get_data() );
t( 'REST writes location', 'Turnhalle Oberei' === get_post_meta( $id, '_ce_location', true ) );
t( 'REST normalises end date', '2026-06-27 23:59:59' === get_post_meta( $id, '_ce_end_date', true ), get_post_meta( $id, '_ce_end_date', true ) );
t( 'REST rejects bad colour', '' === get_post_meta( $id, '_ce_color', true ) );
$data = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/club_event/' . $id ) )->get_data();
t( 'REST read exposes meta', isset( $data['meta']['_ce_start_date'] ) && false === $data['meta']['_ce_all_day'], $data['meta'] ?? null );

wp_set_current_user( 0 );
$req = new WP_REST_Request( 'POST', '/wp/v2/club_event/' . $id );
$req->set_body_params( [ 'meta' => [ '_ce_location' => 'Hacked' ] ] );
$res = rest_do_request( $req );
t( 'anonymous REST write refused', 'Turnhalle Oberei' === get_post_meta( $id, '_ce_location', true ) && $res->get_status() >= 400 );

$abilities = rest_do_request( new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' ) );
wp_set_current_user( $admin->ID );
$abilities = rest_do_request( new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities' ) );
$listed    = array_filter( (array) $abilities->get_data(), static function ( $a ) { return 0 === strpos( $a['name'] ?? '', 'club-events/' ); } );
t( 'abilities listed in REST', 6 === count( $listed ), count( $listed ) );

$del = wp_get_ability( 'club-events/delete-event' )->execute( [ 'id' => $id ] );
t( 'delete moves to trash', 'trash' === get_post_status( $id ) && ! empty( $del['trashed'] ), $del );
wp_delete_post( $id, true );
wp_delete_term( get_term_by( 'slug', 'wettkampf-test', 'event_type' )->term_id, 'event_type' );

echo "\n" . $GLOBALS['ce_t'][0] . ' passed, ' . $GLOBALS['ce_t'][1] . " failed\n";
if ( $GLOBALS['ce_t'][1] ) { exit( 1 ); }
