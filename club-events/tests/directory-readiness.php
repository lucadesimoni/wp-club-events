<?php
/**
 * WordPress.org directory readiness — static checks, no WordPress required.
 *
 * Locks in the fixes made for the plugin review: no inline scripts, no
 * locale-forcing gettext filter, no site-specific code, unslashed input, and
 * the readme/header fields the directory requires.
 *
 * Run: php club-events/tests/directory-readiness.php
 */

$root   = dirname( __DIR__ );
$passed = 0;
$failed = 0;

function check( string $label, bool $ok, string $detail = '' ): void {
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ( $ok ? '  PASS  ' : '  FAIL  ' ) . $label . ( ! $ok && $detail ? " — $detail" : '' ) . "\n";
}

$php = [];
$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root ) );
foreach ( $it as $file ) {
    $path = $file->getPathname();
    if ( substr( $path, -4 ) === '.php' && false === strpos( $path, '/tests/' ) ) {
        $php[ substr( $path, strlen( $root ) + 1 ) ] = file_get_contents( $path );
    }
}

$hits = function ( string $regex ) use ( $php ): array {
    $out = [];
    foreach ( $php as $name => $src ) {
        foreach ( explode( "\n", $src ) as $i => $line ) {
            if ( preg_match( $regex, $line ) ) {
                $out[] = $name . ':' . ( $i + 1 );
            }
        }
    }
    return $out;
};

echo "\n=== Code ===\n";
$inline = array_filter( $hits( '/<script(?![^>]*application\/ld\+json)/i' ) );
check( 'no inline <script> tags (JSON-LD data excepted)', ! $inline, implode( ', ', $inline ) );
$date = $hits( '/(?<![\w>:$])date\(/' );
check( 'no date() — use gmdate()/wp_date()', ! $date, implode( ', ', array_slice( $date, 0, 5 ) ) );
$gettext = $hits( "/add_filter\(\s*'gettext'/" );
check( 'no gettext filter forcing a language', ! $gettext, implode( ', ', $gettext ) );
$raw = $hits( '/(sanitize_\w+|absint|esc_url_raw)\(\s*\$_(POST|GET|REQUEST)\[/' );
check( 'request data is unslashed before sanitising', ! $raw, implode( ', ', $raw ) );
check( 'no site-specific tools shipped', ! is_dir( $root . '/tools' ) );
$site = $hits( '/Aktivriege|STV Malters/i' );
check( 'no club-specific references in the plugin', ! $site, implode( ', ', $site ) );
$ine = $hits( '/CREATE TABLE IF NOT EXISTS/' );
check( 'dbDelta-compatible CREATE TABLE', ! $ine, implode( ', ', $ine ) );
check( 'German ships as translation files', is_file( $root . '/languages/club-events-de_DE.mo' ) && is_file( $root . '/languages/club-events-de_CH.mo' ) );

echo "\n=== Headers & readme ===\n";
$main   = $php['club-events.php'];
$readme = file_get_contents( $root . '/readme.txt' );
foreach ( [ 'Plugin Name', 'Version', 'Requires at least', 'Requires PHP', 'Author', 'License', 'License URI', 'Text Domain', 'Domain Path' ] as $h ) {
    check( "plugin header: $h", (bool) preg_match( '/^\s*\*\s*' . preg_quote( $h, '/' ) . ':\s*\S/m', $main ) );
}
check( 'author is not a placeholder', ! preg_match( '/Author:\s*Club Events Manager/', $main ) );
foreach ( [ 'Contributors', 'Tags', 'Requires at least', 'Tested up to', 'Requires PHP', 'Stable tag', 'License', 'License URI' ] as $h ) {
    check( "readme header: $h", (bool) preg_match( '/^' . preg_quote( $h, '/' ) . ':\s*\S/m', $readme ) );
}
preg_match( '/^Tags:\s*(.*)$/m', $readme, $m );
check( 'at most 5 tags', count( array_filter( array_map( 'trim', explode( ',', $m[1] ?? '' ) ) ) ) <= 5 );
$lines = preg_split( '/\R/', $readme );
$short = '';
foreach ( $lines as $i => $l ) {
    if ( $i > 0 && '' === trim( $lines[ $i - 1 ] ) && '' !== trim( $l ) && 0 !== strpos( $l, '==' ) && ! preg_match( '/^[A-Z][\w ]+:/', $l ) ) {
        $short = $l;
        break;
    }
}
check( 'short description ≤ 150 characters', '' !== $short && mb_strlen( $short ) <= 150, mb_strlen( $short ) . ' chars' );
foreach ( [ 'Description', 'Installation', 'Frequently Asked Questions', 'External services', 'Changelog' ] as $sec ) {
    check( "readme section: $sec", false !== strpos( $readme, "== $sec ==" ) );
}

echo "\n=== Summary ===\n";
echo "  $passed / " . ( $passed + $failed ) . " passed\n";
exit( $failed ? 1 : 0 );
