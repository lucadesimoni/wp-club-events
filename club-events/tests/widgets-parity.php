<?php
/**
 * Widget parity test — static analysis, no WordPress required.
 *
 * Every feature of this plugin is exposed three ways: a shortcode, a Gutenberg
 * block and an Elementor widget. It is easy for those three surfaces to drift
 * apart — a block registered in PHP but never in JS is silently missing from
 * the inserter, and an Elementor widget passing an attribute the shortcode does
 * not accept is silently dropped by shortcode_atts(). This test locks the three
 * surfaces together.
 *
 * Run: php club-events/tests/widgets-parity.php
 */

$root       = dirname( __DIR__ );
$shortcodes = file_get_contents( $root . '/includes/class-shortcodes.php' );
$submit     = file_get_contents( $root . '/includes/class-frontend-submit.php' );
$elementor  = file_get_contents( $root . '/includes/class-elementor.php' );
$blocks_js  = file_get_contents( $root . '/blocks/index.js' );

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

/** Extract the `'key' =>` names from a PHP array literal source fragment. */
function array_keys_in( string $src ): array {
    preg_match_all( "/'([a-z_]+)'\s*=>/", $src, $m );
    return array_values( array_unique( $m[1] ) );
}

/** Extract the top-level attribute names from a block 'attributes' literal. */
function block_attr_keys( string $src ): array {
    preg_match_all( "/'([a-z_]+)'\s*=>\s*\[\s*'type'/", $src, $m );
    return array_values( array_unique( $m[1] ) );
}

/** Extract a balanced `{...}` body starting at the first `{` after $from. */
function brace_body( string $src, int $from ): string {
    $start = strpos( $src, '{', $from );
    if ( false === $start ) {
        return '';
    }
    $depth = 0;
    for ( $i = $start, $len = strlen( $src ); $i < $len; $i++ ) {
        if ( '{' === $src[ $i ] ) {
            $depth++;
        } elseif ( '}' === $src[ $i ] ) {
            $depth--;
            if ( 0 === $depth ) {
                return substr( $src, $start + 1, $i - $start - 1 );
            }
        }
    }
    return '';
}

/* ── Collect the three surfaces ───────────────────────────────────────────── */

// 1. Shortcodes and the attributes each one accepts.
preg_match_all( "/add_shortcode\(\s*'([a-z_]+)'/", $shortcodes . $submit, $m );
$shortcode_tags = $m[1];

$shortcode_atts = [];
preg_match_all(
    "/shortcode_atts\(\s*\[(.*?)\],\s*\\\$atts,\s*'([a-z_]+)'\s*\)/s",
    $shortcodes . $submit,
    $m,
    PREG_SET_ORDER
);
foreach ( $m as $match ) {
    $shortcode_atts[ $match[2] ] = array_keys_in( $match[1] );
}

// 2. Blocks registered server-side, with their attributes.
$php_blocks = [];
preg_match_all(
    "/register_block_type\(\s*'club-events\/([a-z-]+)'.*?'attributes'\s*=>\s*\[(.*?)\],\s*\]\s*\)\s*\)/s",
    $shortcodes,
    $m,
    PREG_SET_ORDER
);
foreach ( $m as $match ) {
    $php_blocks[ $match[1] ] = block_attr_keys( $match[2] );
}

// 3. Blocks registered client-side, with their attributes.
$js_blocks = [];
preg_match_all( "/registerBlockType\('club-events\/([a-z-]+)'/", $blocks_js, $m, PREG_OFFSET_CAPTURE );
foreach ( $m[1] as $match ) {
    $name = $match[0];
    $body = brace_body( $blocks_js, $match[1] );
    $attr_pos = strpos( $body, 'attributes:' );
    $attrs    = [];
    if ( false !== $attr_pos ) {
        preg_match_all( "/([a-z_]+):\s*\{\s*type:/", brace_body( $body, $attr_pos ), $am );
        $attrs = $am[1];
    }
    $js_blocks[ $name ] = $attrs;
}

// 4. Elementor widgets: registered classes, and the shortcode each renders.
preg_match_all( "/\\\$widgets_manager->register\(\s*new\s+(CE_Elementor_\w+)\(\)/", $elementor, $m );
$registered_widgets = $m[1];

preg_match_all( "/class\s+(CE_Elementor_\w+)\s+extends/", $elementor, $m );
$widget_classes = $m[1];

$widget_shortcodes = [];
preg_match_all(
    "/build_shortcode\(\s*'([a-z_]+)',\s*\[(.*?)\]\s*\)/s",
    $elementor,
    $m,
    PREG_SET_ORDER
);
foreach ( $m as $match ) {
    preg_match_all( "/'([a-z_]+)'(?:\s*=>)?/", $match[2], $am, PREG_SET_ORDER );
    $atts = [];
    $raw  = $match[2];
    // Entries are either 'att' or 'att' => 'control_id'; only the left side is
    // the shortcode attribute name.
    preg_match_all( "/'([a-z_]+)'\s*=>\s*'[a-z_]+'|'([a-z_]+)'/", $raw, $am, PREG_SET_ORDER );
    foreach ( $am as $entry ) {
        $atts[] = '' !== $entry[1] ? $entry[1] : $entry[2];
    }
    $widget_shortcodes[ $match[1] ] = array_values( array_unique( $atts ) );
}

// Widgets that render a shortcode directly via do_shortcode().
preg_match_all( "/do_shortcode\(\s*'\[([a-z_]+)\]'\s*\)/", $elementor, $m );
foreach ( $m[1] as $tag ) {
    $widget_shortcodes[ $tag ] = $widget_shortcodes[ $tag ] ?? [];
}

/* ── 1. Shortcode coverage ────────────────────────────────────────────────── */
section( '1. Shortcode Coverage' );

check(
    'every registered shortcode has an Elementor widget',
    ! array_diff( $shortcode_tags, array_keys( $widget_shortcodes ) ),
    'missing: ' . implode( ', ', array_diff( $shortcode_tags, array_keys( $widget_shortcodes ) ) )
);

check(
    'no Elementor widget renders an unknown shortcode',
    ! array_diff( array_keys( $widget_shortcodes ), $shortcode_tags ),
    'unknown: ' . implode( ', ', array_diff( array_keys( $widget_shortcodes ), $shortcode_tags ) )
);

check( 'all 11 shortcodes are registered', 11 === count( $shortcode_tags ), count( $shortcode_tags ) . ' found' );

/* ── 2. Elementor widget integrity ────────────────────────────────────────── */
section( '2. Elementor Widget Integrity' );

check(
    'every registered widget class is defined in the file',
    ! array_diff( $registered_widgets, $widget_classes ),
    'undefined: ' . implode( ', ', array_diff( $registered_widgets, $widget_classes ) )
);

check(
    'every defined widget class is registered',
    ! array_diff( $widget_classes, $registered_widgets ),
    'unregistered: ' . implode( ', ', array_diff( $widget_classes, $registered_widgets ) )
);

preg_match_all( "/function get_name\(\)\s*\{\s*return\s*'([a-z-]+)'/", $elementor, $m );
check(
    'widget names are unique',
    count( $m[1] ) === count( array_unique( $m[1] ) ),
    'duplicates: ' . implode( ', ', array_diff_assoc( $m[1], array_unique( $m[1] ) ) )
);
check( 'every widget class declares a name', count( $m[1] ) === count( $widget_classes ) );

$bad_atts = [];
foreach ( $widget_shortcodes as $tag => $atts ) {
    if ( ! isset( $shortcode_atts[ $tag ] ) ) {
        continue;
    }
    foreach ( array_diff( $atts, $shortcode_atts[ $tag ] ) as $att ) {
        $bad_atts[] = "$tag.$att";
    }
}
check(
    'Elementor widgets only pass attributes the shortcode accepts',
    empty( $bad_atts ),
    implode( ', ', $bad_atts )
);

/* ── 3. Gutenberg block parity ────────────────────────────────────────────── */
section( '3. Gutenberg Block Parity' );

check(
    'every server-registered block is registered in blocks/index.js',
    ! array_diff( array_keys( $php_blocks ), array_keys( $js_blocks ) ),
    'missing in JS: ' . implode( ', ', array_diff( array_keys( $php_blocks ), array_keys( $js_blocks ) ) )
);

check(
    'every JS block is registered server-side',
    ! array_diff( array_keys( $js_blocks ), array_keys( $php_blocks ) ),
    'missing in PHP: ' . implode( ', ', array_diff( array_keys( $js_blocks ), array_keys( $php_blocks ) ) )
);

$attr_drift = [];
foreach ( $php_blocks as $name => $attrs ) {
    if ( ! isset( $js_blocks[ $name ] ) ) {
        continue;
    }
    foreach ( array_diff( $attrs, $js_blocks[ $name ] ) as $a ) {
        $attr_drift[] = "$name.$a missing in JS";
    }
    foreach ( array_diff( $js_blocks[ $name ], $attrs ) as $a ) {
        $attr_drift[] = "$name.$a missing in PHP";
    }
}
check( 'block attributes match between PHP and JS', empty( $attr_drift ), implode( '; ', $attr_drift ) );

$block_attr_drift = [];
$block_to_shortcode = [
    'hub'       => 'club_events',
    'tiles'     => 'club_events_tiles',
    'timeline'  => 'club_events_timeline',
    'overview'  => 'club_events_overview',
    'cards'     => 'club_events_cards',
    'list'      => 'club_events_list',
    'yearly'    => 'club_events_yearly',
    'share'     => 'club_events_share',
];
foreach ( $block_to_shortcode as $block => $tag ) {
    if ( ! isset( $php_blocks[ $block ] ) ) {
        $block_attr_drift[] = "$block has no server registration";
        continue;
    }
    if ( ! isset( $shortcode_atts[ $tag ] ) ) {
        $block_attr_drift[] = "$tag declares no shortcode_atts";
        continue;
    }
    foreach ( array_diff( $php_blocks[ $block ], $shortcode_atts[ $tag ] ) as $a ) {
        $block_attr_drift[] = "$block.$a is not a $tag attribute";
    }
}
check(
    'block attributes are valid shortcode attributes',
    empty( $block_attr_drift ),
    implode( '; ', $block_attr_drift )
);

check(
    'block editor script depends on wp-server-side-render',
    (bool) preg_match( "/'club-events-blocks'.*?wp-server-side-render/s", $shortcodes ),
    'previews silently fall back to a placeholder without it'
);

/* ── 4. Version consistency ───────────────────────────────────────────────── */
section( '4. Version Consistency' );

$bootstrap = file_get_contents( $root . '/club-events.php' );
$readme    = file_get_contents( $root . '/readme.txt' );

preg_match( "/^\s*\*\s*Version:\s*([0-9.]+)/m", $bootstrap, $m );
$header_version = $m[1] ?? '';
preg_match( "/define\(\s*'CE_VERSION',\s*'([0-9.]+)'/", $bootstrap, $m );
$const_version = $m[1] ?? '';
preg_match( "/^Stable tag:\s*([0-9.]+)/m", $readme, $m );
$stable_tag = $m[1] ?? '';

check( 'plugin header version is set', '' !== $header_version, 'not found' );
check( 'CE_VERSION matches the plugin header', $header_version === $const_version, "$header_version vs $const_version" );
check( 'readme.txt stable tag matches the plugin header', $header_version === $stable_tag, "$header_version vs $stable_tag" );
check(
    'CHANGELOG.md documents the current version',
    false !== strpos( file_get_contents( dirname( $root ) . '/CHANGELOG.md' ), $header_version ),
    "no entry for $header_version"
);
check(
    'readme.txt changelog documents the current version',
    false !== strpos( $readme, "= $header_version =" ),
    "no entry for $header_version"
);

/* ── Summary ──────────────────────────────────────────────────────────────── */
echo "\n=== Summary ===\n";
echo "  " . $passed . " / " . ( $passed + $failed ) . " passed\n";
exit( $failed > 0 ? 1 : 0 );
