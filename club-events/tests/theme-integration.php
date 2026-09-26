<?php
/**
 * Theme & builder integration test — no WordPress required.
 *
 * Covers the Astra design-token bridge, the colour sanitiser shared by blocks
 * and Elementor, the block wrapper, and static guarantees for the block editor
 * (API v3, iframe-ready assets) and Elementor (style controls, re-init hook).
 *
 * Run: php club-events/tests/theme-integration.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$root   = dirname( __DIR__ );
$passed = 0;
$failed = 0;

function check( string $label, bool $ok, string $detail = '' ): void {
    global $passed, $failed;
    if ( $ok ) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label" . ( $detail ? " — $detail" : '' ) . "\n";
    }
}

function section( string $title ): void {
    echo "\n=== $title ===\n";
}

/* ── Minimal WordPress stubs ─────────────────────────────────────────────── */
$GLOBALS['astra_options'] = [];
$GLOBALS['inline_styles'] = [];

function astra_get_option( $key, $default = '' ) {
    return $GLOBALS['astra_options'][ $key ] ?? $default;
}
function apply_filters( $hook, $value ) { return $value; }
function add_action() {}
function add_filter() {}
function wp_style_is( $handle, $list = 'enqueued' ) { return 'club-events' === $handle; }
function wp_add_inline_style( $handle, $css ) { $GLOBALS['inline_styles'][ $handle ][] = $css; }
function sanitize_html_class( $class ) { return preg_replace( '/[^A-Za-z0-9_-]/', '', $class ); }
function get_block_wrapper_attributes( $extra = [] ) {
    $class = trim( 'wp-block-club-events-hub alignwide ' . ( $extra['class'] ?? '' ) );
    $out   = 'class="' . $class . '"';
    if ( ! empty( $extra['style'] ) ) {
        $out .= ' style="' . $extra['style'] . '"';
    }
    if ( ! empty( $extra['id'] ) ) {
        $out .= ' id="' . $extra['id'] . '"';
    }
    return $out;
}
class WP_Block_Supports { public static $block_to_render = [ 'blockName' => 'club-events/hub' ]; }

require $root . '/includes/class-safe.php';
require $root . '/includes/class-style.php';
require $root . '/includes/class-astra-compat.php';

/* ── 1. Colour sanitiser ─────────────────────────────────────────────────── */
section( '1. Colour Sanitiser' );

$valid = [ '#fff', '#1e293b', '#1e293b80', 'rgb(10, 20, 30)', 'rgba(10,20,30,.5)', 'hsl(210 40% 20%)',
    'var(--ast-global-color-0)', 'var(--e-global-color-primary)', 'var(--wp--preset--color--primary)' ];
foreach ( $valid as $c ) {
    check( "accepts $c", $c === CE_Style::sanitize_color( $c ) );
}
$invalid = [ 'red;background:url(x)', 'var(--x);}body{', 'expression(alert(1))', '#12', 'url(javascript:1)', 'var(--a, red)' ];
foreach ( $invalid as $c ) {
    check( 'rejects ' . $c, '' === CE_Style::sanitize_color( $c ) );
}
check( 'no accent → no declarations', '' === CE_Style::accent_declarations( '' ) );
$decl = CE_Style::accent_declarations( 'var(--ast-global-color-0)' );
foreach ( [ '--ce-primary:', '--ce-primary-dk:', '--ce-primary-lt:', '--ce-btn-bg:', '--ce-btn-bg-hover:', '--ce-link:', '--ce-input-focus:' ] as $prop ) {
    check( "accent sets $prop", false !== strpos( $decl, $prop . 'var(--ast-global-color-0)' ) || false !== strpos( $decl, $prop . 'color-mix(in srgb, var(--ast-global-color-0)' ) );
}

/* ── 2. Astra token bridge ───────────────────────────────────────────────── */
section( '2. Astra Token Bridge' );

$css = CE_Astra_Compat::bridge_css();
check( 'brand → --ce-primary', false !== strpos( $css, '--ce-primary:        var(--ast-global-color-0' ) );
check( 'headings colour → palette slot 2', false !== strpos( $css, '--ce-heading-color:  var(--ast-global-color-2' ) );
check( 'body text → palette slot 3', false !== strpos( $css, '--ce-text:           var(--ast-global-color-3' ) );
check( 'card surface → palette slot 5 (secondary background)', false !== strpos( $css, '--ce-white:          var(--ast-global-color-5' ) );
check( 'subtle background → palette slot 4 (primary background)', false !== strpos( $css, '--ce-bg:             var(--ast-global-color-4' ) );
check( 'muted text is mixed from text, never a background slot', (bool) preg_match( '/--ce-text-muted:\s+color-mix\(in srgb, var\(--ast-global-color-3/', $css ) );
check( 'no palette slot 7 or 8 used as a surface', ! preg_match( '/--ce-(bg|white):\s+var\(--ast-global-color-[678]/', $css ) );

$GLOBALS['astra_options'] = [];
check( 'no options → no overrides', [] === CE_Astra_Compat::tokens() );

$GLOBALS['astra_options'] = [
    'headings-font-family'  => "'Poppins', sans-serif",
    'headings-font-weight'  => '600',
    'font-size-h1'          => [ 'desktop' => 44, 'desktop-unit' => 'px', 'tablet' => 36 ],
    'button-bg-color'       => 'var(--ast-global-color-1)',
    'button-color'          => '#ffffff',
    'button-h-color'        => 'bogus;}body{',
    'button-radius-fields'  => [ 'desktop' => [ 'top' => 30, 'right' => 30, 'bottom' => 30, 'left' => 30 ], 'desktop-unit' => 'px' ],
    'font-size-button'      => [ 'desktop' => 1.1, 'desktop-unit' => 'rem' ],
    'font-extras-button'    => [ 'text-transform' => 'uppercase', 'letter-spacing' => 1, 'letter-spacing-unit' => 'px' ],
    'theme-button-padding'  => [ 'desktop' => [ 'top' => 12, 'right' => 26, 'bottom' => 12, 'left' => 26 ], 'desktop-unit' => 'px' ],
];
$t = CE_Astra_Compat::tokens();
check( 'heading font family', "'Poppins', sans-serif" === ( $t['--ce-heading-font-family'] ?? '' ) );
check( 'heading font weight', '600' === ( $t['--ce-heading-font-weight'] ?? '' ) );
check( 'responsive H1 size uses the desktop value', '44px' === ( $t['--ce-h1-size'] ?? '' ) );
check( 'button background keeps the palette variable', 'var(--ast-global-color-1)' === ( $t['--ce-btn-bg'] ?? '' ) );
check( 'button text colour', '#ffffff' === ( $t['--ce-btn-color'] ?? '' ) );
check( 'invalid colour option is dropped', ! isset( $t['--ce-btn-color-hover'] ) );
check( 'four-corner button radius', '30px 30px 30px 30px' === ( $t['--ce-btn-radius'] ?? '' ) );
check( 'button font size with unit', '1.1rem' === ( $t['--ce-btn-font-size'] ?? '' ) );
check( 'button text transform', 'uppercase' === ( $t['--ce-btn-text-transform'] ?? '' ) );
check( 'button letter spacing', '1px' === ( $t['--ce-btn-letter-spacing'] ?? '' ) );
check( 'button padding', '12px' === ( $t['--ce-btn-padding-v'] ?? '' ) && '26px' === ( $t['--ce-btn-padding-h'] ?? '' ) );

$GLOBALS['astra_options'] = [ 'button-radius' => 8, 'headings-font-family' => 'inherit', 'text-transform-button' => 'capitalize' ];
$t = CE_Astra_Compat::tokens();
check( 'legacy single button radius', '8px' === ( $t['--ce-btn-radius'] ?? '' ) );
check( '"inherit" heading font is left to the default', ! isset( $t['--ce-heading-font-family'] ) );
check( 'legacy text-transform option', 'capitalize' === ( $t['--ce-btn-text-transform'] ?? '' ) );

$css = CE_Astra_Compat::bridge_css();
check( 'overrides land in the second :root block', false !== strpos( $css, ':root { --ce-btn-radius:8px;' ) );

( new ReflectionClass( 'CE_Astra_Compat' ) )->newInstanceWithoutConstructor()->attach_bridge();
check( 'bridge is attached to the plugin stylesheet as inline CSS', ! empty( $GLOBALS['inline_styles']['club-events'] ) );

/* ── 3. Block wrapper ────────────────────────────────────────────────────── */
section( '3. Block Wrapper' );

require_once $root . '/includes/class-shortcodes.php';

$seen = null;
$html = CE_Shortcodes::render_block_wrapper(
    function ( $atts ) use ( &$seen ) { $seen = $atts; return '<div class="ce-hub"></div>'; },
    [ 'limit' => 5, 'accentColor' => 'var(--ast-global-color-0)', 'align' => 'wide', 'className' => 'x',
      'anchor' => 'events', 'style' => [], 'backgroundColor' => 'base', 'textColor' => 'contrast' ]
);
check( 'shortcode receives only its own attributes', [ 'limit' => 5 ] === $seen, json_encode( $seen ) );
check( 'wrapper carries the block supports', false !== strpos( $html, 'alignwide' ) );
check( 'wrapper has the ce-block class', false !== strpos( $html, 'ce-block' ) );
check( 'wrapper applies the accent', false !== strpos( $html, '--ce-primary:var(--ast-global-color-0)' ) );
check( 'wrapper outputs the anchor id', false !== strpos( $html, 'id="events"' ) );
check( 'unsafe accent is ignored', false === strpos(
    CE_Shortcodes::render_block_wrapper( function () { return 'x'; }, [ 'accentColor' => 'red;}body{' ] ), 'body{' ) );
check( 'empty render stays empty', '' === CE_Shortcodes::render_block_wrapper( function () { return ''; }, [] ) );

/* ── 4. Block editor (static) ────────────────────────────────────────────── */
section( '4. Block Editor' );

$shortcodes = file_get_contents( $root . '/includes/class-shortcodes.php' );
$blocks_js  = file_get_contents( $root . '/blocks/index.js' );
$plugin     = file_get_contents( $root . '/includes/class-plugin.php' );

preg_match_all( "/register_block_type\(\s*'club-events\/[a-z-]+',\s*\\\$this->block_args\(/", $shortcodes, $m );
preg_match_all( "/register_block_type\(/", $shortcodes, $all );
check( 'every block goes through block_args()', count( $m[0] ) === count( $all[0] ) && count( $m[0] ) > 0, count( $m[0] ) . ' of ' . count( $all[0] ) );
check( 'blocks use API version 3 server-side', false !== strpos( $shortcodes, "'api_version']     = 3" ) );
preg_match_all( "/registerBlockType\('club-events\//", $blocks_js, $reg );
preg_match_all( "/apiVersion:\s*3/", $blocks_js, $api );
check( 'every JS block declares apiVersion 3', count( $reg[0] ) === count( $api[0] ), count( $api[0] ) . ' of ' . count( $reg[0] ) );
check( 'edit() uses useBlockProps', substr_count( $blocks_js, 'useBlockProps()' ) >= count( $reg[0] ) - 3 );
check( 'no JS block overrides the server supports', false === strpos( $blocks_js, 'supports:' ) );
check( 'previews skip block-support attributes', false !== strpos( $blocks_js, 'skipBlockSupportAttributes: true' ) );
check( 'controls use the WordPress 7.0 control styles', false !== strpos( $blocks_js, '__nextHasNoMarginBottom: true' ) && false !== strpos( $blocks_js, '__next40pxDefaultSize: true' ) );
check( 'plugin header requires WordPress 6.5', (bool) preg_match( '/Requires at least:\s*6\.5/', file_get_contents( $root . '/club-events.php' ) ) );
check( 'styles load into the editor iframe', false !== strpos( $plugin, "'enqueue_block_assets'" ) );
check( 'no reliance on the pre-iframe editor hook for styles', false === strpos( $plugin, "enqueue_block_editor_assets" ) );

/* ── 5. Elementor (static) ───────────────────────────────────────────────── */
section( '5. Elementor' );

$elementor = file_get_contents( $root . '/includes/class-elementor.php' );
$public_js = file_get_contents( $root . '/public/js/club-events-public.js' );

preg_match_all( "/class\s+CE_Elementor_\w+\s+extends/", $elementor, $w );
check( 'every widget has the Style tab', substr_count( $elementor, '$this->add_style_controls();' ) === count( $w[0] ), substr_count( $elementor, '$this->add_style_controls();' ) . ' of ' . count( $w[0] ) );
check( 'widgets declare their style/script dependencies', false !== strpos( $elementor, 'get_style_depends' ) && false !== strpos( $elementor, 'get_script_depends' ) );
check( 'no free-text slug fields left', false === strpos( $elementor, "'Category Slug'" ) );
check( 'front-end script re-initialises Elementor widgets', false !== strpos( $public_js, "frontend/element_ready/global" ) );
check( 'hub binding is idempotent', false !== strpos( $public_js, 'dataset.ceReady' ) );

echo "\n=== Summary ===\n";
echo "  " . $passed . " / " . ( $passed + $failed ) . " passed\n";
exit( $failed > 0 ? 1 : 0 );
