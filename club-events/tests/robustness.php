<?php
/**
 * "Never break the site" tests — no WordPress required.
 *
 * Proves that errors inside the plugin are contained: a crashing renderer or
 * hook cannot take a page down, and the plugin refuses to load (with a
 * notice) instead of fataling when a second copy or a clashing class exists.
 *
 * Run: php club-events/tests/robustness.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$root   = dirname( __DIR__ );
$passed = 0;
$failed = 0;

function check( string $label, bool $ok, string $detail = '' ): void {
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ( $ok ? '  PASS  ' : '  FAIL  ' ) . $label . ( ! $ok && $detail ? " — $detail" : '' ) . "\n";
}

/* ── Minimal WordPress stubs ─────────────────────────────────────────────── */
$GLOBALS['can_edit'] = false;
$GLOBALS['actions']  = [];
$GLOBALS['reported'] = [];
function current_user_can( $cap ) { return $GLOBALS['can_edit']; }
function esc_html__( $s, $d = null ) { return $s; }
function __( $s, $d = null ) { return $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function do_action( $hook, ...$args ) { if ( 'ce_render_error' === $hook ) { $GLOBALS['reported'][] = $args[0]; } }
function add_action( $hook, $cb, $p = 10, $a = 1 ) { $GLOBALS['actions'][ $hook ][] = $cb; }
function add_filter( ...$a ) {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'http://example.test/'; }
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function register_activation_hook( ...$a ) {}
function register_deactivation_hook( ...$a ) {}

require $root . '/includes/class-safe.php';

echo "\n=== Renders ===\n";
$boom = function () { echo '<div class="half'; throw new RuntimeException( 'boom' ); };
$lvl  = ob_get_level();
$out  = CE_Safe::render( 'test', $boom );
check( 'a crashing renderer returns an empty string to visitors', '' === $out, var_export( $out, true ) );
check( 'partial output of the crash is discarded', ob_get_level() === $lvl );
check( 'the crash is reported', [ 'test' ] === $GLOBALS['reported'] );
$GLOBALS['can_edit'] = true;
check( 'editors see a short note instead', false !== strpos( CE_Safe::render( 'test', $boom ), 'ce-render-error' ) );
$GLOBALS['can_edit'] = false;
check( 'a TypeError is contained too', '' === CE_Safe::render( 'test', function ( array $a ) { return 'x'; }, [ 'not-an-array' ] ) );
check( 'undefined functions are contained', '' === CE_Safe::render( 'test', function () { return ce_does_not_exist(); } ) );
check( 'working renderers are untouched', '<b>ok</b>' === CE_Safe::render( 'test', function ( $a ) { return '<b>' . $a . '</b>'; }, [ 'ok' ] ) );
$sc = CE_Safe::renderer( '[x]', function ( $atts ) { return 'atts:' . count( (array) $atts ); } );
check( 'shortcode wrapper forwards arguments', 'atts:2' === $sc( [ 'a' => 1, 'b' => 2 ], '' ) );

echo "\n=== Hooks ===\n";
$filter = CE_Safe::filter( 'body_class', function ( array $classes ) { throw new Exception( 'bad' ); } );
check( 'a failing filter returns the unfiltered value', [ 'home', 'page' ] === $filter( [ 'home', 'page' ] ) );
$typed = CE_Safe::filter( 'astra_page_layout', function ( array $x ) { return $x; } );
check( 'a filter given an unexpected type returns it unchanged', 'no-sidebar' === $typed( 'no-sidebar' ) );
$action = CE_Safe::action( 'wp_head', function () { echo '<script>half'; throw new Exception( 'x' ); } );
ob_start();
$action();
check( 'a failing action prints nothing', '' === ob_get_clean() );

echo "\n=== Every entry point is guarded ===\n";
$src = [];
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/includes' ) ) as $f ) {
    if ( '.php' === substr( $f, -4 ) ) {
        $src[ basename( $f ) ] = file_get_contents( $f );
    }
}
$all = implode( "\n", $src );
preg_match_all( "/add_shortcode\(\s*'([a-z_]+)',\s*(.{0,20})/", $all, $m, PREG_SET_ORDER );
$unguarded = array_filter( $m, function ( $x ) { return 0 !== strpos( $x[2], 'CE_Safe::renderer' ); } );
check( 'all ' . count( $m ) . ' shortcodes are guarded', count( $m ) >= 11 && ! $unguarded, implode( ', ', array_column( $unguarded, 1 ) ) );
check( 'block renders are guarded', false !== strpos( $src['class-shortcodes.php'], "CE_Safe::render( 'block', \$render" ) );
foreach ( [ 'class-astra-compat.php', 'class-cpt.php', 'class-elementor.php', 'class-patterns.php', 'class-ics-export.php' ] as $file ) {
    preg_match_all( "/add_(action|filter)\(\s*'[^']+',\s*(.{0,12})/", $src[ $file ], $h, PREG_SET_ORDER );
    $raw = array_filter( $h, function ( $x ) { return 0 !== strpos( $x[2], 'CE_Safe::' ); } );
    check( "$file: all " . count( $h ) . ' hooks guarded', ! $raw );
}

echo "\n=== Loading ===\n";
$main = file_get_contents( $root . '/club-events.php' );
preg_match( '/\$ce_classes\s*=\s*\[(.*?)\];/s', $main, $cm );
preg_match_all( "/'(CE_\w+)'/", $cm[1] ?? '', $listed );
preg_match_all( '/^\s*(?:final\s+|abstract\s+)?class\s+(CE_\w+)/m', $all, $declared );
$missing = array_diff( $declared[1], $listed[1] );
check( 'the clash check covers every class the plugin declares', ! $missing, 'missing: ' . implode( ', ', $missing ) );
check( 'no translation is loaded before init (messages built in the notice)', ! preg_match( '/^\$ce_load_problem\s*=\s*.*__\(/m', $main ) );

// A clashing class from "another plugin": the file must return, not fatal.
$code = <<<'PHP'
define( 'ABSPATH', '/x/' );
function add_action( $h, $cb ) { $GLOBALS['a'][] = $h; }
function add_filter( ...$a ) {}
class CE_Style {}
include $argv[1];
echo defined( 'CE_VERSION' ) ? 'LOADED' : 'SKIPPED:' . implode( ',', $GLOBALS['a'] ?? [] );
PHP;
$out = shell_exec( 'php -r ' . escapeshellarg( $code ) . ' ' . escapeshellarg( $root . '/club-events.php' ) . ' 2>&1' );
check( 'a class clash skips loading with an admin notice (no fatal)', 'SKIPPED:admin_notices' === trim( (string) $out ), trim( (string) $out ) );

// A second copy (e.g. an old folder): same outcome.
$code2 = str_replace( 'class CE_Style {}', "define( 'CE_PLUGIN_FILE', '/other/club-events.php' );", $code );
$out2  = shell_exec( 'php -r ' . escapeshellarg( $code2 ) . ' ' . escapeshellarg( $root . '/club-events.php' ) . ' 2>&1' );
check( 'a second copy skips loading with an admin notice (no fatal)', 'SKIPPED:admin_notices' === trim( (string) $out2 ), trim( (string) $out2 ) );

echo "\n=== Summary ===\n";
echo "  $passed / " . ( $passed + $failed ) . " passed\n";
exit( $failed ? 1 : 0 );
