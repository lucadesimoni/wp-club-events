<?php
/**
 * Regression tests for the 1.2.1 fixes.
 *
 * These cover behaviour that is invisible to the Aktivriege E2E suite because
 * that suite stubs CE_CPT wholesale. Everything here loads the real plugin
 * classes against a minimal set of WordPress stubs.
 *
 * Run: php club-events/tests/regressions.php
 */

define( 'ABSPATH', '/tmp/ce-regressions/' );

/* ─── Minimal WordPress stubs ────────────────────────────────────────────── */

if ( ! function_exists( 'wp_parse_args' ) ) {
    function wp_parse_args( $args, $defaults = [] ) {
        return array_merge( $defaults, (array) $args );
    }
}

/** get_posts() stub — records the query it was handed. */
$GLOBALS['ce_last_query'] = [];
function get_posts( $args ) {
    $GLOBALS['ce_last_query'] = $args;
    return [];
}

function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function get_option( $name, $default = false ) { return $default; }
function add_shortcode( ...$a ) {}
function add_action( ...$a ) {}
function add_filter( ...$a ) {}

require_once __DIR__ . '/../includes/class-cpt.php';
require_once __DIR__ . '/../includes/class-shortcodes.php';

/* ─── Test runner ────────────────────────────────────────────────────────── */

$passed = 0;
$failed = 0;

function t( string $name, callable $fn ): void {
    global $passed, $failed;
    try {
        $fn();
        echo "  PASS  {$name}\n";
        $passed++;
    } catch ( Throwable $e ) {
        echo "  FAIL  {$name}\n        {$e->getMessage()}\n";
        $failed++;
    }
}

function assert_true( $cond, string $msg = 'expected true' ): void {
    if ( ! $cond ) {
        throw new Exception( $msg );
    }
}

function assert_same( $expected, $actual, string $msg = '' ): void {
    if ( $expected !== $actual ) {
        throw new Exception( $msg ?: sprintf(
            "expected %s, got %s",
            var_export( $expected, true ),
            var_export( $actual, true )
        ) );
    }
}

/** Call a private/protected static method. */
function call_private( string $class, string $method, array $args = [] ) {
    $m = new ReflectionMethod( $class, $method );
    $m->setAccessible( true );
    return $m->invokeArgs( null, $args );
}

echo "\n=== 1. Shortcode boolean attributes ===\n";

t( 'String "false" is falsey (show_image="false" now hides the image)', function () {
    assert_same( false, CE_Shortcodes::bool( 'false', true ) );
} );

t( 'String "0", "no" and "off" are falsey', function () {
    foreach ( [ '0', 'no', 'off', 'NO', ' Off ' ] as $v ) {
        assert_same( false, CE_Shortcodes::bool( $v, true ), "\"{$v}\" should be false" );
    }
} );

t( 'String "true", "1", "yes" are truthy', function () {
    foreach ( [ 'true', '1', 'yes', 'on' ] as $v ) {
        assert_same( true, CE_Shortcodes::bool( $v, false ), "\"{$v}\" should be true" );
    }
} );

t( 'Real booleans (block attributes) pass through unchanged', function () {
    assert_same( true, CE_Shortcodes::bool( true, false ) );
    assert_same( false, CE_Shortcodes::bool( false, true ) );
} );

t( 'Empty/unset falls back to the supplied default', function () {
    assert_same( true, CE_Shortcodes::bool( '', true ) );
    assert_same( false, CE_Shortcodes::bool( null, false ) );
} );

echo "\n=== 2. Placeholder gradient ===\n";

t( 'Hex colour still gets the alpha suffix on the second stop', function () {
    assert_same(
        'linear-gradient(135deg,#3b82f6 0%,#3b82f6aa 100%)',
        call_private( 'CE_Shortcodes', 'placeholder_gradient', [ '#3b82f6', 'aa' ] )
    );
} );

t( 'CSS var colour produces valid CSS (no "var(--ce-primary)aa")', function () {
    $css = call_private( 'CE_Shortcodes', 'placeholder_gradient', [ 'var(--ce-primary)', 'aa' ] );
    assert_true( false === strpos( $css, ')aa' ), "gradient must not append alpha to a var(): {$css}" );
    assert_same( 'linear-gradient(135deg,var(--ce-primary) 0%,var(--ce-primary) 100%)', $css );
} );

t( 'Shorthand hex is recognised', function () {
    assert_same(
        'linear-gradient(135deg,#f00 0%,#f0099 100%)',
        call_private( 'CE_Shortcodes', 'placeholder_gradient', [ '#f00', '99' ] )
    );
} );

echo "\n=== 3. CE_CPT::get_events() clause merging ===\n";

t( 'event_type and a caller tax_query are both applied', function () {
    CE_CPT::get_events( [
        'event_type' => 'aktivriege',
        'tax_query'  => [ [ 'taxonomy' => 'event_category', 'field' => 'slug', 'terms' => 'wettkampf' ] ],
    ] );
    $tax = $GLOBALS['ce_last_query']['tax_query'];
    assert_same( 2, count( $tax ), 'both tax clauses should survive' );
    $taxonomies = array_column( $tax, 'taxonomy' );
    assert_true( in_array( 'event_type', $taxonomies, true ), 'event_type clause missing' );
    assert_true( in_array( 'event_category', $taxonomies, true ), 'event_category clause missing' );
} );

t( 'from/to and a caller meta_query are both applied', function () {
    CE_CPT::get_events( [
        'from'       => '2026-01-01 00:00:00',
        'to'         => '2026-12-31 23:59:59',
        'meta_query' => [ [ 'key' => '_ce_location', 'value' => 'Malters' ] ],
    ] );
    $meta = $GLOBALS['ce_last_query']['meta_query'];
    assert_same( 3, count( $meta ), 'both date clauses plus the caller clause should survive' );
} );

t( 'A caller "relation" key is dropped rather than merged as a clause', function () {
    CE_CPT::get_events( [
        'event_type' => 'aktivriege',
        'tax_query'  => [
            'relation' => 'OR',
            [ 'taxonomy' => 'event_category', 'field' => 'slug', 'terms' => 'wettkampf' ],
        ],
    ] );
    $tax = $GLOBALS['ce_last_query']['tax_query'];
    assert_same( 2, count( $tax ) );
    assert_true( ! isset( $tax['relation'] ), 'relation should not be carried over' );
    foreach ( $tax as $clause ) {
        assert_true( is_array( $clause ) && isset( $clause['taxonomy'] ), 'every entry must be a clause' );
    }
} );

t( 'Empty meta_query/tax_query keys are omitted entirely', function () {
    CE_CPT::get_events( [ 'posts_per_page' => 5 ] );
    assert_true( ! isset( $GLOBALS['ce_last_query']['meta_query'] ), 'meta_query should be unset' );
    assert_true( ! isset( $GLOBALS['ce_last_query']['tax_query'] ), 'tax_query should be unset' );
} );

t( 'Shorthand keys are not leaked into the WP_Query args', function () {
    CE_CPT::get_events( [ 'from' => '2026-01-01 00:00:00', 'event_type' => 'aktivriege' ] );
    foreach ( [ 'from', 'to', 'event_type' ] as $key ) {
        assert_true( ! isset( $GLOBALS['ce_last_query'][ $key ] ), "{$key} should not reach get_posts()" );
    }
} );

echo "\n=== 4. dbDelta schema ===\n";

t( 'CREATE TABLE statements are parseable by dbDelta (one key per table)', function () {
    $source = file_get_contents( __DIR__ . '/../includes/class-plugin.php' );
    preg_match( '/\$sql = "(.*?)";/s', $source, $m );
    assert_true( ! empty( $m[1] ), 'could not locate the schema SQL' );

    // Mirror dbDelta()'s own table-name extraction.
    $tables = [];
    foreach ( array_filter( explode( ';', $m[1] ) ) as $q ) {
        if ( preg_match( '|CREATE TABLE ([^ ]*)|', $q, $t ) ) {
            $tables[ trim( $t[1], '`' ) ] = true;
        }
    }
    assert_same( 2, count( $tables ), 'each CREATE TABLE must resolve to its own table name, got: '
        . implode( ', ', array_keys( $tables ) ) );
    assert_true( ! isset( $tables['IF'] ), 'IF NOT EXISTS breaks dbDelta table detection' );
} );

t( 'PRIMARY KEY uses the two-space form dbDelta expects', function () {
    $source = file_get_contents( __DIR__ . '/../includes/class-plugin.php' );
    preg_match( '/\$sql = "(.*?)";/s', $source, $m );
    assert_true(
        ! preg_match( '/PRIMARY KEY \(/', $m[1] ),
        'dbDelta requires "PRIMARY KEY  (id)" with two spaces'
    );
} );

echo "\n=== Summary ===\n";
printf( "  %d / %d passed\n\n", $passed, $passed + $failed );

exit( $failed > 0 ? 1 : 0 );
