<?php
/**
 * Date & timezone tests — no WordPress required.
 *
 * Event dates are stored in site-local time. These checks prove that
 * "upcoming" keeps today's and in-progress events, that all-day ICS
 * exports use an exclusive DTEND, that schema.org dates carry the site's
 * offset, and that Google Calendar values are converted to local time.
 *
 * Run: php club-events/tests/dates.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
date_default_timezone_set( 'UTC' ); // WordPress always runs PHP in UTC.

$root   = dirname( __DIR__ );
$passed = 0;
$failed = 0;

function check( string $label, bool $ok, string $detail = '' ): void {
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ( $ok ? '  PASS  ' : '  FAIL  ' ) . $label . ( ! $ok && $detail ? " — $detail" : '' ) . "\n";
}

/* ── Minimal WordPress stubs ─────────────────────────────────────────────── */
$GLOBALS['now_local'] = '2026-11-24 14:30:00'; // Europe/Zurich, UTC+1.
$GLOBALS['posts_query'] = null;
$GLOBALS['meta'] = [];
function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function wp_timezone() { return new DateTimeZone( 'Europe/Zurich' ); }
function current_time( $type ) {
    $ts = strtotime( $GLOBALS['now_local'] );
    if ( 'mysql' === $type ) { return gmdate( 'Y-m-d H:i:s', $ts ); }
    if ( 'timestamp' === $type ) { return $ts; }
    return gmdate( $type, $ts );
}
function wp_parse_args( $a, $d ) { return array_merge( $d, $a ); }
function get_posts( $q ) { $GLOBALS['posts_query'] = $q; return []; }
function get_option( $k, $d = false ) {
    $o = [ 'timezone_string' => 'Europe/Zurich', 'date_format' => 'j. F Y', 'time_format' => 'G:i' ];
    return $o[ $k ] ?? $d;
}
function date_i18n( $f, $ts ) { return gmdate( $f, $ts ); }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function home_url() { return 'https://example.test'; }
class CE_Safe {
    public static function action( $n, $cb ) { return $cb; }
    public static function filter( $n, $cb ) { return $cb; }
}

require $root . '/includes/class-cpt.php';
require $root . '/includes/class-ics-export.php';
require $root . '/includes/class-google-calendar.php';

/* ── Upcoming filter ─────────────────────────────────────────────────────── */
echo "Upcoming events\n";
CE_CPT::get_events( [ 'upcoming' => true ] );
$q  = $GLOBALS['posts_query'];
$mq = $q['meta_query'][0] ?? [];
check( 'upcoming is not passed through to WP_Query', ! isset( $q['upcoming'] ) );
check( 'upcoming is an OR of end/start', 'OR' === ( $mq['relation'] ?? '' ) );
check( 'event stays listed until it ends (local now)',
    '_ce_end_date' === $mq[0]['key'] && '>=' === $mq[0]['compare'] && '2026-11-24 14:30:00' === $mq[0]['value'],
    json_encode( $mq[0] ?? null ) );
check( 'events without an end stay listed for their whole day',
    '_ce_start_date' === $mq[1][0]['key'] && '2026-11-24 00:00:00' === $mq[1][0]['value']
    && 'NOT EXISTS' === $mq[1][1][0]['compare'] && '' === $mq[1][1][1]['value'],
    json_encode( $mq[1] ?? null ) );

// Simulate the meta query against real events (what MySQL would do).
$matches = function ( $start, $end ) use ( $mq ) {
    return ( $end && $end >= $mq[0]['value'] ) || ( ! $end && $start >= $mq[1][0]['value'] );
};
check( 'all-day event today (GV Frauenriege) is listed', $matches( '2026-11-24 00:00:00', '2026-11-24 23:59:59' ) );
check( 'multi-day event in progress is listed', $matches( '2026-11-23 00:00:00', '2026-11-25 23:59:59' ) );
check( 'event that ended this morning is hidden', ! $matches( '2026-11-24 08:00:00', '2026-11-24 10:00:00' ) );
check( 'event without end, today, is listed', $matches( '2026-11-24 08:00:00', '' ) );
check( 'yesterday is hidden', ! $matches( '2026-11-23 00:00:00', '2026-11-23 23:59:59' ) );

// 00:30 local is still the previous day in UTC: "today" must be local.
$GLOBALS['now_local'] = '2026-11-25 00:30:00';
check( 'today() uses site-local date', '2026-11-25' === CE_CPT::today() );
$GLOBALS['now_local'] = '2026-11-24 14:30:00';

/* ── schema.org dates ────────────────────────────────────────────────────── */
echo "schema.org dates\n";
check( 'winter time carries +01:00', '2026-11-24T19:30:00+01:00' === CE_CPT::to_iso8601( '2026-11-24 19:30:00' ) );
check( 'summer time carries +02:00', '2026-06-26T09:00:00+02:00' === CE_CPT::to_iso8601( '2026-06-26 09:00:00' ) );
check( 'all-day uses a plain date', '2026-06-26' === CE_CPT::to_iso8601( '2026-06-26 00:00:00', true ) );

/* ── Date ranges on the event page ───────────────────────────────────────── */
echo "Date ranges\n";
$r = function ( $a, $b, $all ) { return CE_CPT::date_range( strtotime( $a ), $b ? strtotime( $b ) : null, $all ); };
check( 'one-day all-day shows the date once', '27. November 2026' === $r( '2026-11-27 00:00:00', '2026-11-27 23:59:59', true ), $r( '2026-11-27 00:00:00', '2026-11-27 23:59:59', true ) );
check( 'multi-day all-day shows both dates', '26. June 2026 – 28. June 2026' === $r( '2026-06-26 00:00:00', '2026-06-28 23:59:59', true ) );
check( 'timed same-day shows date, start and end time', '24. November 2026 19:30 – 21:00' === $r( '2026-11-24 19:30:00', '2026-11-24 21:00:00', false ) );
check( 'timed over midnight repeats the end date', '24. November 2026 22:00 – 25. November 2026 2:00' === $r( '2026-11-24 22:00:00', '2026-11-25 02:00:00', false ) );
check( 'no end date', '24. November 2026 19:30' === $r( '2026-11-24 19:30:00', '', false ) );

/* ── ICS export ──────────────────────────────────────────────────────────── */
echo "ICS export\n";
$ics   = ( new ReflectionClass( 'CE_ICS_Export' ) )->newInstanceWithoutConstructor();
$build = new ReflectionMethod( 'CE_ICS_Export', 'build_ics' );
$build->setAccessible( true );
$ev = function ( $id, $start, $end, $all_day ) {
    return [ 'id' => $id, 'title' => 'T', 'start' => $start, 'end' => $end, 'allDay' => $all_day,
             'location' => '', 'excerpt' => '', 'url' => 'https://example.test/e' ];
};
$out = $build->invoke( $ics, [ $ev( 1, '2026-06-26 00:00:00', '2026-06-28 23:59:59', true ) ], 'Cal' );
check( 'multi-day all-day: DTSTART is the first day', false !== strpos( $out, "DTSTART;VALUE=DATE:20260626\r\n" ) );
check( 'multi-day all-day: DTEND is the day after the last day', false !== strpos( $out, "DTEND;VALUE=DATE:20260629\r\n" ), $out );
$out = $build->invoke( $ics, [ $ev( 2, '2026-11-24 00:00:00', '2026-11-24 23:59:59', true ) ], 'Cal' );
check( 'one-day all-day: DTEND is the next day', false !== strpos( $out, "DTEND;VALUE=DATE:20261125\r\n" ), $out );
$out = $build->invoke( $ics, [ $ev( 3, '2026-11-24 00:00:00', '', true ) ], 'Cal' );
check( 'all-day without end still gets a one-day DTEND', false !== strpos( $out, "DTEND;VALUE=DATE:20261125\r\n" ), $out );
$out = $build->invoke( $ics, [ $ev( 4, '2026-11-24 19:30:00', '2026-11-24 21:00:00', false ) ], 'Cal' );
check( 'timed event keeps local wall-clock time with TZID', false !== strpos( $out, "DTSTART;TZID=Europe/Zurich:20261124T193000\r\n" ), $out );

/* ── Google Calendar sync ────────────────────────────────────────────────── */
echo "Google Calendar sync\n";
$gc    = ( new ReflectionClass( 'CE_Google_Calendar' ) )->newInstanceWithoutConstructor();
$parse = new ReflectionMethod( 'CE_Google_Calendar', 'parse_date' );
$parse->setAccessible( true );
check( 'dateTime is converted to site-local time',
    '2026-11-24 19:30:00' === $parse->invoke( $gc, [ 'dateTime' => '2026-11-24T18:30:00Z' ] ),
    (string) $parse->invoke( $gc, [ 'dateTime' => '2026-11-24T18:30:00Z' ] ) );
check( 'dateTime with own offset is kept as wall-clock time',
    '2026-06-26 09:00:00' === $parse->invoke( $gc, [ 'dateTime' => '2026-06-26T09:00:00+02:00' ] ) );
check( 'all-day start is 00:00:00', '2026-06-26 00:00:00' === $parse->invoke( $gc, [ 'date' => '2026-06-26' ] ) );
check( 'all-day end (exclusive) becomes 23:59:59 of the last day',
    '2026-06-28 23:59:59' === $parse->invoke( $gc, [ 'date' => '2026-06-29' ], true ) );
check( 'garbage does not throw', '' === $parse->invoke( $gc, [ 'dateTime' => 'not a date' ] ) );

echo "\n$passed passed, $failed failed\n";
exit( $failed ? 1 : 0 );
