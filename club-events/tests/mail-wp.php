<?php
/**
 * E-mail integration tests: subscription confirmation, new-event notice
 * (category filter, .ics attachment, List-Unsubscribe, plain-text part),
 * unsubscribe confirmation step and admin notification. Needs a real
 * WordPress site with the plugin active and an administrator user. Mails
 * are captured, nothing is sent. Creates one event and one subscriber and
 * removes them again.
 *
 * Run: wp eval-file club-events/tests/mail-wp.php [output-dir]
 * With an output directory, the rendered HTML of each mail is saved there.
 */
$GLOBALS['ce_t']    = [ 0, 0 ];
$GLOBALS['ce_mail'] = [];
function t( $label, $ok, $detail = '' ) {
	$GLOBALS['ce_t'][ $ok ? 0 : 1 ]++;
	echo ( $ok ? '  PASS  ' : '  FAIL  ' ) . $label . ( $ok ? '' : ' — ' . ( is_string( $detail ) ? $detail : wp_json_encode( $detail ) ) ) . "\n";
}
$out_dir = $args[0] ?? '';

// Capture instead of sending; record the alt body PHPMailer would get.
add_filter( 'pre_wp_mail', function ( $null, $atts ) {
	$fake = new stdClass();
	do_action( 'phpmailer_init', $fake );
	$atts['alt']                = $fake->AltBody ?? '';
	$atts['attachment_content'] = array_map( static function ( $f ) { return is_file( $f ) ? file_get_contents( $f ) : ''; }, (array) $atts['attachments'] );
	$GLOBALS['ce_mail'][]       = $atts;
	return true;
}, 10, 2 );

$admin = get_users( [ 'role' => 'administrator', 'number' => 1 ] )[0];
wp_set_current_user( $admin->ID );
update_option( 'ce_subscription_from_name', "Club \"News\"\r\nBcc: evil@example.com" );
update_option( 'ce_subscription_from_email', 'news@example.com' );

global $wpdb;
$table = $wpdb->prefix . 'ce_subscribers';
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE email LIKE %s', $table, '%@mailtest.invalid' ) );
$token_all = str_repeat( 'a', 64 );
$token_cat = str_repeat( 'b', 64 );
$wpdb->insert( $table, [ 'email' => 'all@mailtest.invalid', 'name' => 'Anna', 'token' => $token_all, 'categories' => '', 'confirmed' => 1, 'created_at' => current_time( 'mysql' ) ] );
$wpdb->insert( $table, [ 'email' => 'other@mailtest.invalid', 'name' => '', 'token' => $token_cat, 'categories' => 'nur-andere-kategorie', 'confirmed' => 1, 'created_at' => current_time( 'mysql' ) ] );

echo "Confirmation mail\n";
$sub = ( new ReflectionClass( 'CE_Subscription' ) )->newInstanceWithoutConstructor();
$m   = new ReflectionMethod( 'CE_Subscription', 'send_confirmation_email' );
$m->setAccessible( true );
$m->invoke( $sub, 'new@mailtest.invalid', 'Ben', str_repeat( 'c', 64 ) );
$mail = end( $GLOBALS['ce_mail'] );
$headers = implode( "\n", (array) $mail['headers'] );
t( 'is HTML', false !== stripos( $headers, 'Content-Type: text/html' ) );
t( 'From header cannot be split (no injected Bcc line)', false === stripos( $headers, "\nBcc:" ) && false !== strpos( $headers, 'From: "Club News' ) && false !== strpos( $headers, '<news@example.com>' ), $headers );
t( 'has a confirm button with the token link', false !== strpos( $mail['message'], 'ce_confirm=' . str_repeat( 'c', 64 ) ) );
t( 'has a plain-text alternative', false !== strpos( $mail['alt'], 'ce_confirm=' ) && false === strpos( $mail['alt'], '<' ), $mail['alt'] );
if ( $out_dir ) { file_put_contents( $out_dir . '/mail-confirm.html', $mail['message'] ); }

echo "New-event notice\n";
$GLOBALS['ce_mail'] = [];
$cat = wp_insert_term( 'Mailtest Kategorie', 'event_category' );
$cat_id = is_wp_error( $cat ) ? (int) $cat->get_error_data() : (int) $cat['term_id'];
$start = gmdate( 'Y-m-d', strtotime( '+10 days' ) );
$id = CE_Data_API::save_event( [
	'title' => 'Mailtest Turnfest', 'start' => $start . ' 19:30', 'end' => $start . ' 22:00',
	'location' => 'Turnhalle Oberei', 'location_url' => 'https://maps.example/oberei',
	'excerpt' => 'Gemeinsamer Abend mit allen Riegen.', 'categories' => [ $cat_id ], 'status' => 'draft',
] )['id'];
wp_publish_post( $id );
$to = wp_list_pluck( $GLOBALS['ce_mail'], 'to' );
t( 'subscriber without categories gets it', in_array( 'all@mailtest.invalid', $to, true ), $to );
t( 'subscriber of another category does not', ! in_array( 'other@mailtest.invalid', $to, true ), $to );
$mail = $GLOBALS['ce_mail'][0] ?? [];
$headers = implode( "\n", (array) ( $mail['headers'] ?? [] ) );
t( 'List-Unsubscribe header with token', false !== strpos( $headers, 'List-Unsubscribe: <' ) && false !== strpos( $headers, $token_all ), $headers );
t( 'List-Unsubscribe-Post one-click header', false !== strpos( $headers, 'List-Unsubscribe-Post: List-Unsubscribe=One-Click' ) );
t( '.ics attached', 1 === count( (array) $mail['attachments'] ) && '.ics' === substr( $mail['attachments'][0], -4 ), $mail['attachments'] ?? null );
t( 'attachment is a VEVENT for this event', false !== strpos( $mail['attachment_content'][0] ?? '', 'SUMMARY:Mailtest Turnfest' ) );
t( 'temporary .ics file removed after sending', ! file_exists( $mail['attachments'][0] ?? '/nonexistent' ) );
t( 'shows date, location link and category', false !== strpos( $mail['message'], '19:30' ) && false !== strpos( $mail['message'], 'https://maps.example/oberei' ) && false !== strpos( $mail['message'], 'Mailtest Kategorie' ) );
t( 'has Google Calendar and .ics links', false !== strpos( $mail['message'], 'calendar.google.com/calendar/render' ) && false !== strpos( $mail['message'], '/events/' . $id . '.ics' ) );
t( 'site name or logo in the header', false !== strpos( $mail['message'], esc_html( get_bloginfo( 'name' ) ) ) );
t( 'plain-text part lists the unsubscribe URL', false !== strpos( $mail['alt'], 'ce_unsubscribe=' . $token_all ) );
t( 'notification is sent only once', '1' === get_post_meta( $id, '_ce_notification_sent', true ) );
if ( $out_dir ) { file_put_contents( $out_dir . '/mail-event.html', $mail['message'] ); }

echo "Past events\n";
$GLOBALS['ce_mail'] = [];
$past = CE_Data_API::save_event( [ 'title' => 'Mailtest Vergangen', 'start' => '2020-01-01 10:00', 'status' => 'draft' ] )['id'];
wp_publish_post( $past );
t( 'no mail for an event that is already over', 0 === count( $GLOBALS['ce_mail'] ) );

echo "Admin notification\n";
$GLOBALS['ce_mail'] = [];
$fs = ( new ReflectionClass( 'CE_Frontend_Submit' ) )->newInstanceWithoutConstructor();
$n  = new ReflectionMethod( 'CE_Frontend_Submit', 'notify_admin_new_submission' );
$n->setAccessible( true );
$n->invoke( $fs, $id, 'Mailtest Turnfest', 'pending' );
$mail = end( $GLOBALS['ce_mail'] );
t( 'admin submission mail uses the template with a review button', $mail && false !== strpos( $mail['message'], 'post.php?post=' . $id ) && false !== stripos( implode( "\n", (array) $mail['headers'] ), 'text/html' ) );

echo "Unsubscribe\n";
$_GET['ce_unsubscribe']    = $token_all;
$_SERVER['REQUEST_METHOD'] = 'GET';
$died = '';
add_filter( 'wp_die_handler', function () { return function ( $msg ) { throw new Exception( (string) $msg ); }; } );
try { ( new ReflectionClass( 'CE_Subscription' ) )->newInstanceWithoutConstructor()->handle_unsubscribe(); } catch ( Exception $e ) { $died = $e->getMessage(); }
t( 'GET shows a confirmation form instead of unsubscribing', false !== strpos( $died, '<form method="post"' ) && (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE token = %s', $table, $token_all ) ) === 1, $died );
unset( $_GET['ce_unsubscribe'] );

// Clean up.
wp_delete_post( $id, true );
wp_delete_post( $past, true );
wp_delete_term( $cat_id, 'event_category' );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE email LIKE %s', $table, '%@mailtest.invalid' ) );
delete_option( 'ce_subscription_from_name' );
delete_option( 'ce_subscription_from_email' );

echo "\n" . $GLOBALS['ce_t'][0] . ' passed, ' . $GLOBALS['ce_t'][1] . " failed\n";
if ( $GLOBALS['ce_t'][1] ) {
	exit( 1 );
}
