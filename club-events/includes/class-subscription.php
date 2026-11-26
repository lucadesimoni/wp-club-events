<?php
defined( 'ABSPATH' ) || exit;

class CE_Subscription {

    public function __construct() {
        add_action( 'wp_ajax_nopriv_ce_subscribe', [ $this, 'handle_subscribe' ] );
        add_action( 'wp_ajax_ce_subscribe', [ $this, 'handle_subscribe' ] );
        add_action( 'init', CE_Safe::action( 'init', [ $this, 'handle_confirm' ] ) );
        add_action( 'init', CE_Safe::action( 'init', [ $this, 'handle_unsubscribe' ] ) );
        add_action( 'publish_club_event', CE_Safe::action( 'publish_club_event', [ $this, 'notify_subscribers' ] ), 20, 2 );
    }

    public function handle_subscribe() {
        check_ajax_referer( 'ce_subscribe_nonce', 'nonce' );

        if ( get_option( 'ce_subscription_enabled', '1' ) !== '1' ) {
            wp_send_json_error( __( 'Subscriptions are disabled.', 'club-events' ) );
        }

        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $cats  = sanitize_text_field( wp_unslash( $_POST['categories'] ?? '' ) );

        if ( ! is_email( $email ) ) {
            wp_send_json_error( __( 'Please enter a valid email address.', 'club-events' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ce_subscribers';

        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM %i WHERE email = %s",
            $table,
            $email
        ) );

        if ( $existing && $existing->confirmed ) {
            wp_send_json_error( __( 'You are already subscribed.', 'club-events' ) );
        }

        $token = bin2hex( random_bytes( 32 ) );

        if ( $existing ) {
            $wpdb->update( $table, [ 'token' => $token, 'name' => $name, 'categories' => $cats ], [ 'email' => $email ], [ '%s', '%s', '%s' ], [ '%s' ] );
        } else {
            $wpdb->insert( $table, [
                'email'      => $email,
                'name'       => $name,
                'token'      => $token,
                'categories' => $cats,
                'confirmed'  => 0,
                'created_at' => current_time( 'mysql' ),
            ], [ '%s', '%s', '%s', '%s', '%d', '%s' ] );
        }

        $this->send_confirmation_email( $email, $name, $token );

        wp_send_json_success( __( 'Please check your email to confirm your subscription.', 'club-events' ) );
    }

    private function send_confirmation_email( $email, $name, $token ) {
        $confirm_url = add_query_arg( [
            'ce_confirm' => rawurlencode( $token ),
        ], home_url( '/' ) );

        /* translators: %s: subscriber name. */
        $greeting = $name ? sprintf( __( 'Hi %s,', 'club-events' ), $name ) : __( 'Hi there,', 'club-events' );

        /* translators: %s: site name. */
        $subject = sprintf( __( 'Confirm your event subscription — %s', 'club-events' ), get_bloginfo( 'name' ) );

        CE_Mailer::send( $email, $subject, [
            'preheader'       => __( 'One click to confirm and you will receive new events by e-mail.', 'club-events' ),
            'heading'         => __( 'Confirm your subscription', 'club-events' ),
            'paragraphs'      => [
                $greeting,
                /* translators: %s: site name. */
                sprintf( __( 'Please confirm that you want to receive new events from %s by e-mail.', 'club-events' ), get_bloginfo( 'name' ) ),
            ],
            'button'          => [ 'label' => __( 'Confirm subscription', 'club-events' ), 'url' => $confirm_url ],
            'button_fallback' => true,
            'note'            => __( 'If you did not request this, please ignore this email.', 'club-events' ),
            'footer'          => __( 'You receive this e-mail because this address was entered in the event subscription form.', 'club-events' ),
        ] );
    }

    public function handle_confirm() {
        if ( empty( $_GET['ce_confirm'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the 64-hex token in the e-mailed link is the secret.
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['ce_confirm'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the 64-hex token in the e-mailed link is the secret.
        if ( strlen( $token ) !== 64 || ! ctype_xdigit( $token ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ce_subscribers';

        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE token = %s", $table, $token ) );

        if ( ! $row ) {
            wp_die( esc_html__( 'Invalid or expired confirmation link.', 'club-events' ) );
        }

        $wpdb->update( $table, [ 'confirmed' => 1 ], [ 'id' => $row->id ], [ '%d' ], [ '%d' ] );

        wp_safe_redirect( add_query_arg( 'ce_subscribed', '1', CE_CPT::archive_url() ) );
        exit;
    }

    /**
     * Unsubscribe links: a GET shows a confirmation button, the removal
     * happens on POST. Link scanners and mail-client prefetchers (which only
     * GET) therefore cannot unsubscribe anyone; the one-click POST from the
     * List-Unsubscribe-Post header (RFC 8058) unsubscribes directly.
     */
    public function handle_unsubscribe() {
        if ( empty( $_GET['ce_unsubscribe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the 64-hex token in the e-mailed link is the secret.
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['ce_unsubscribe'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the 64-hex token in the e-mailed link is the secret.
        if ( strlen( $token ) !== 64 || ! ctype_xdigit( $token ) ) {
            return;
        }

        $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
        if ( 'POST' !== $method ) {
            $action = add_query_arg( 'ce_unsubscribe', rawurlencode( $token ), home_url( '/' ) );
            wp_die(
                '<h1>' . esc_html__( 'Unsubscribe from event e-mails?', 'club-events' ) . '</h1>'
                . '<p>' . esc_html__( 'You will no longer receive e-mails about new events.', 'club-events' ) . '</p>'
                . '<form method="post" action="' . esc_url( $action ) . '"><p><button type="submit" class="button button-primary">'
                . esc_html__( 'Unsubscribe', 'club-events' ) . '</button></p></form>',
                esc_html__( 'Unsubscribe', 'club-events' ),
                [ 'response' => 200 ]
            );
        }

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'ce_subscribers', [ 'token' => $token ], [ '%s' ] );

        // RFC 8058 one-click requests expect a plain success response.
        if ( isset( $_POST['List-Unsubscribe'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- RFC 8058 one-click; the token is the secret.
            status_header( 200 );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'ce_unsubscribed', '1', CE_CPT::archive_url() ) );
        exit;
    }

    public function notify_subscribers( $post_id, $post ) {
        if ( 'club_event' !== $post->post_type ) {
            return;
        }

        if ( get_post_meta( $post_id, '_ce_notification_sent', true ) ) {
            return;
        }

        $event = CE_CPT::format_event( $post_id );

        // No announcements for events that are already over (e.g. archive imports).
        $last = $event['end'] ? $event['end'] : $event['start'];
        if ( $last && $last < CE_CPT::now() && ! ( empty( $event['end'] ) && substr( $last, 0, 10 ) === CE_CPT::today() ) ) {
            update_post_meta( $post_id, '_ce_notification_sent', 'skipped-past' );
            return;
        }

        global $wpdb;
        $subscribers = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE confirmed = 1', $wpdb->prefix . 'ce_subscribers' ) );

        if ( empty( $subscribers ) ) {
            return;
        }

        $event_cats = wp_list_pluck( $event['categories'], 'slug' );
        $ics_file   = self::ics_attachment( $post_id );

        $details = [
            __( 'Date', 'club-events' ) => CE_CPT::date_range(
                $event['start'] ? strtotime( $event['start'] ) : 0,
                $event['end'] ? strtotime( $event['end'] ) : null,
                $event['allDay']
            ),
        ];
        if ( $event['location'] ) {
            $details[ __( 'Location', 'club-events' ) ] = $event['locationUrl']
                ? [ 'text' => $event['location'], 'url' => $event['locationUrl'] ]
                : $event['location'];
        }
        $labels = array_merge( wp_list_pluck( $event['categories'], 'name' ), wp_list_pluck( $event['types'], 'name' ) );
        if ( $labels ) {
            $details[ __( 'Category', 'club-events' ) ] = implode( ', ', $labels );
        }

        $links = [
            [ 'label' => __( 'Add to calendar (Apple, Outlook)', 'club-events' ), 'url' => $event['ics'] ],
            [ 'label' => __( 'Google Calendar', 'club-events' ), 'url' => CE_ICS_Export::google_calendar_url( $event ) ],
        ];
        if ( $event['externalUrl'] ) {
            $links[] = [ 'label' => __( 'More information', 'club-events' ), 'url' => $event['externalUrl'] ];
        }

        /* translators: %s: event title. */
        $subject = sprintf( __( 'New event: %s', 'club-events' ), $event['title'] );

        foreach ( $subscribers as $sub ) {
            // Subscribers who chose categories only hear about those.
            $wanted = array_filter( array_map( 'trim', explode( ',', (string) $sub->categories ) ) );
            if ( $wanted && ! array_intersect( $wanted, $event_cats ) ) {
                continue;
            }

            $unsub_url = add_query_arg( [
                'ce_unsubscribe' => rawurlencode( $sub->token ),
            ], home_url( '/' ) );

            /* translators: %s: subscriber name. */
            $greeting = $sub->name ? sprintf( __( 'Hi %s,', 'club-events' ), $sub->name ) : __( 'Hi there,', 'club-events' );

            CE_Mailer::send( $sub->email, $subject, [
                'preheader'       => trim( $details[ __( 'Date', 'club-events' ) ] . ( $event['location'] ? ' · ' . $event['location'] : '' ) ),
                'heading'         => $event['title'],
                'paragraphs'      => array_filter( [ $greeting . ' ' . __( 'a new event has been published.', 'club-events' ), $event['excerpt'] ] ),
                'details'         => $details,
                'button'          => [ 'label' => __( 'View event', 'club-events' ), 'url' => $event['url'] ],
                'links'           => $links,
                'note'            => $ics_file ? __( 'The attached calendar file (.ics) adds the event to your calendar.', 'club-events' ) : '',
                'footer'          => __( 'You receive this e-mail because you subscribed to new events.', 'club-events' ),
                'unsubscribe_url' => $unsub_url,
            ], $ics_file ? [ $ics_file ] : [] );
        }

        if ( $ics_file ) {
            wp_delete_file( $ics_file );
            rmdir( dirname( $ics_file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- removes our own empty temp folder.
        }

        update_post_meta( $post_id, '_ce_notification_sent', '1' );
    }

    /** Temporary .ics file for one event (deleted after sending), or ''. */
    private static function ics_attachment( $post_id ): string {
        $ics = CE_ICS_Export::event_ics( $post_id );
        if ( '' === $ics ) {
            return '';
        }
        $slug = sanitize_file_name( (string) get_post_field( 'post_name', $post_id ) );
        $dir  = trailingslashit( get_temp_dir() ) . 'ce-' . wp_generate_password( 12, false );
        if ( ! wp_mkdir_p( $dir ) ) {
            return '';
        }
        $file = $dir . '/' . ( $slug ? $slug : 'event-' . (int) $post_id ) . '.ics';
        return false !== file_put_contents( $file, $ics ) ? $file : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary attachment in the temp dir.
    }

    public static function get_subscribers( $page = 1, $per_page = 50 ) {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;
        $table  = $wpdb->prefix . 'ce_subscribers';
        return [
            'rows'  => $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d", $table, $per_page, $offset ) ),
            'total' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table ) ),
        ];
    }

    public static function delete_subscriber( $id ) {
        global $wpdb;
        return $wpdb->delete( $wpdb->prefix . 'ce_subscribers', [ 'id' => (int) $id ], [ '%d' ] );
    }
}
