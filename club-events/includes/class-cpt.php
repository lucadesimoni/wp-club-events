<?php
defined( 'ABSPATH' ) || exit;

class CE_CPT {

    public function __construct() {
        add_action( 'init', CE_Safe::action( 'init', [ $this, 'register_post_type' ] ) );
        add_action( 'init', CE_Safe::action( 'init', [ $this, 'register_taxonomies' ] ) );
        add_action( 'add_meta_boxes', CE_Safe::action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] ) );
        add_action( 'save_post_club_event', CE_Safe::action( 'save_post_club_event', [ $this, 'save_meta' ] ), 10, 2 );
        add_filter( 'manage_club_event_posts_columns', CE_Safe::filter( 'manage_club_event_posts_columns', [ $this, 'admin_columns' ] ) );
        add_action( 'manage_club_event_posts_custom_column', CE_Safe::action( 'manage_club_event_posts_custom_column', [ $this, 'admin_column_content' ] ), 10, 2 );
        add_filter( 'manage_edit-club_event_sortable_columns', CE_Safe::filter( 'manage_edit-club_event_sortable_columns', [ $this, 'sortable_columns' ] ) );
        add_action( 'pre_get_posts', CE_Safe::action( 'pre_get_posts', [ $this, 'default_sort' ] ) );
        add_filter( 'template_include', CE_Safe::filter( 'template_include', [ $this, 'load_template' ] ) );
        add_action( 'template_redirect', CE_Safe::action( 'template_redirect', [ $this, 'maybe_redirect_archive' ] ) );
    }

    /**
     * URL of the events index. Uses the admin-selected "Events page" (e.g. an
     * existing "Anlässe" page) when configured, otherwise the built-in
     * post-type archive.
     */
    public static function archive_url() {
        $page_id = (int) get_option( 'ce_events_page', 0 );
        if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
            return get_permalink( $page_id );
        }
        return get_post_type_archive_link( 'club_event' );
    }

    /**
     * When the built-in archive is hidden and an Events page is set, redirect
     * the /events archive to that page so there is a single events index.
     */
    public function maybe_redirect_archive() {
        if ( ! is_post_type_archive( 'club_event' ) ) {
            return;
        }
        if ( '1' !== get_option( 'ce_hide_archive', '0' ) ) {
            return;
        }
        $page_id = (int) get_option( 'ce_events_page', 0 );
        if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
            wp_safe_redirect( get_permalink( $page_id ), 302 );
            exit;
        }
    }

    public function register_post_type() {
        register_post_type( 'club_event', [
            'labels' => [
                'name'               => __( 'Events', 'club-events' ),
                'singular_name'      => __( 'Event', 'club-events' ),
                'add_new'            => __( 'Add Event', 'club-events' ),
                'add_new_item'       => __( 'Add New Event', 'club-events' ),
                'edit_item'          => __( 'Edit Event', 'club-events' ),
                'new_item'           => __( 'New Event', 'club-events' ),
                'view_item'          => __( 'View Event', 'club-events' ),
                'search_items'       => __( 'Search Events', 'club-events' ),
                'not_found'          => __( 'No events found.', 'club-events' ),
                'not_found_in_trash' => __( 'No events in trash.', 'club-events' ),
                'all_items'          => __( 'All Events', 'club-events' ),
                'menu_name'          => __( 'Events', 'club-events' ),
            ],
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'events', 'with_front' => false ],
            'capability_type'    => 'post',
            'has_archive'        => 'events',
            'hierarchical'       => false,
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ],
        ] );
    }

    public function register_taxonomies() {
        register_taxonomy( 'event_category', 'club_event', [
            'labels' => [
                'name'              => __( 'Categories', 'club-events' ),
                'singular_name'     => __( 'Category', 'club-events' ),
                'search_items'      => __( 'Search Categories', 'club-events' ),
                'all_items'         => __( 'All Categories', 'club-events' ),
                'edit_item'         => __( 'Edit Category', 'club-events' ),
                'add_new_item'      => __( 'Add New Category', 'club-events' ),
                'not_found'         => __( 'No categories found.', 'club-events' ),
                'menu_name'         => __( 'Categories', 'club-events' ),
            ],
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'event-category' ],
        ] );

        register_taxonomy( 'event_type', 'club_event', [
            'labels' => [
                'name'              => __( 'Event Types', 'club-events' ),
                'singular_name'     => __( 'Event Type', 'club-events' ),
                'search_items'      => __( 'Search Types', 'club-events' ),
                'all_items'         => __( 'All Types', 'club-events' ),
                'edit_item'         => __( 'Edit Type', 'club-events' ),
                'add_new_item'      => __( 'Add New Type', 'club-events' ),
                'not_found'         => __( 'No types found.', 'club-events' ),
                'menu_name'         => __( 'Event Types', 'club-events' ),
            ],
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'event-type' ],
        ] );

        register_taxonomy( 'event_tag', 'club_event', [
            'labels' => [
                'name'          => __( 'Tags', 'club-events' ),
                'singular_name' => __( 'Tag', 'club-events' ),
                'not_found'     => __( 'No tags found.', 'club-events' ),
                'menu_name'     => __( 'Tags', 'club-events' ),
            ],
            'hierarchical'  => false,
            'show_ui'       => true,
            'show_in_rest'  => true,
            'rewrite'       => [ 'slug' => 'event-tag' ],
        ] );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'ce_event_details',
            __( 'Event Details', 'club-events' ),
            [ $this, 'render_details_meta_box' ],
            'club_event',
            'normal',
            'high'
        );
    }

    public function render_details_meta_box( $post ) {
        wp_nonce_field( 'ce_save_event_meta', 'ce_event_nonce' );

        $start    = get_post_meta( $post->ID, '_ce_start_date', true );
        $end      = get_post_meta( $post->ID, '_ce_end_date', true );
        $all_day  = get_post_meta( $post->ID, '_ce_all_day', true );
        $location = get_post_meta( $post->ID, '_ce_location', true );
        $loc_url  = get_post_meta( $post->ID, '_ce_location_url', true );
        $ext_url  = get_post_meta( $post->ID, '_ce_external_url', true );
        $color    = get_post_meta( $post->ID, '_ce_color', true ) ?: '#3b82f6';
        $source   = get_post_meta( $post->ID, '_ce_source', true ) ?: 'manual';
        ?>
        <div class="ce-meta-box">
            <?php if ( 'google' === $source ) : ?>
            <div class="ce-sync-notice">
                <span class="dashicons dashicons-google"></span>
                <?php esc_html_e( 'Synced from Google Calendar — some fields are read-only.', 'club-events' ); ?>
            </div>
            <?php endif; ?>

            <div class="ce-meta-row">
                <label>
                    <input type="checkbox" name="ce_all_day" value="1" <?php checked( $all_day, '1' ); ?>>
                    <?php esc_html_e( 'All-day event', 'club-events' ); ?>
                </label>
            </div>

            <div class="ce-meta-row ce-meta-half">
                <div>
                    <label for="ce_start_date"><?php esc_html_e( 'Start', 'club-events' ); ?></label>
                    <input type="datetime-local" id="ce_start_date" name="ce_start_date"
                           value="<?php echo esc_attr( $start ? gmdate( 'Y-m-d\TH:i', strtotime( $start ) ) : '' ); ?>"
                           class="widefat">
                </div>
                <div>
                    <label for="ce_end_date"><?php esc_html_e( 'End', 'club-events' ); ?></label>
                    <input type="datetime-local" id="ce_end_date" name="ce_end_date"
                           value="<?php echo esc_attr( $end ? gmdate( 'Y-m-d\TH:i', strtotime( $end ) ) : '' ); ?>"
                           class="widefat">
                </div>
            </div>

            <div class="ce-meta-row ce-meta-half">
                <div>
                    <label for="ce_location"><?php esc_html_e( 'Location', 'club-events' ); ?></label>
                    <input type="text" id="ce_location" name="ce_location"
                           value="<?php echo esc_attr( $location ); ?>"
                           placeholder="<?php esc_attr_e( 'Venue or address', 'club-events' ); ?>"
                           class="widefat">
                </div>
                <div>
                    <label for="ce_location_url"><?php esc_html_e( 'Maps URL', 'club-events' ); ?></label>
                    <input type="url" id="ce_location_url" name="ce_location_url"
                           value="<?php echo esc_attr( $loc_url ); ?>"
                           placeholder="https://maps.google.com/…"
                           class="widefat">
                </div>
            </div>

            <div class="ce-meta-row ce-meta-half">
                <div>
                    <label for="ce_external_url"><?php esc_html_e( 'External Link', 'club-events' ); ?></label>
                    <input type="url" id="ce_external_url" name="ce_external_url"
                           value="<?php echo esc_attr( $ext_url ); ?>"
                           placeholder="https://…"
                           class="widefat">
                </div>
                <div>
                    <label for="ce_color"><?php esc_html_e( 'Color', 'club-events' ); ?></label>
                    <input type="color" id="ce_color" name="ce_color"
                           value="<?php echo esc_attr( $color ); ?>">
                </div>
            </div>
        </div>

        <style>
        .ce-meta-box { padding: 4px 0; }
        .ce-meta-row { margin-bottom: 14px; }
        .ce-meta-half { display: flex; gap: 16px; }
        .ce-meta-half > div { flex: 1; }
        .ce-meta-row label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; color: #666; }
        .ce-sync-notice { background: #f0f7ff; border-left: 3px solid #3b82f6; padding: 8px 12px; margin-bottom: 16px; border-radius: 0 4px 4px 0; font-size: 13px; }
        </style>
        <?php
    }

    public function save_meta( $post_id, $post ) {
        if (
            ! isset( $_POST['ce_event_nonce'] ) ||
            ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ce_event_nonce'] ) ), 'ce_save_event_meta' ) ||
            defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ||
            ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        $fields = [
            '_ce_all_day'       => [ 'sanitize_callback' => fn( $v ) => $v ? '1' : '0', 'key' => 'ce_all_day' ],
            '_ce_start_date'    => [ 'sanitize_callback' => 'sanitize_text_field', 'key' => 'ce_start_date' ],
            '_ce_end_date'      => [ 'sanitize_callback' => 'sanitize_text_field', 'key' => 'ce_end_date' ],
            '_ce_location'      => [ 'sanitize_callback' => 'sanitize_text_field', 'key' => 'ce_location' ],
            '_ce_location_url'  => [ 'sanitize_callback' => 'esc_url_raw', 'key' => 'ce_location_url' ],
            '_ce_external_url'  => [ 'sanitize_callback' => 'esc_url_raw', 'key' => 'ce_external_url' ],
            '_ce_color'         => [ 'sanitize_callback' => 'sanitize_hex_color', 'key' => 'ce_color' ],
        ];

        foreach ( $fields as $meta_key => $config ) {
            // Sanitised by the field's callback on the next line.
            $raw = isset( $_POST[ $config['key'] ] ) ? wp_unslash( $_POST[ $config['key'] ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $value = call_user_func( $config['sanitize_callback'], $raw );

            if ( '_ce_start_date' === $meta_key || '_ce_end_date' === $meta_key ) {
                $value = $value ? gmdate( 'Y-m-d H:i:s', strtotime( str_replace( 'T', ' ', $value ) ) ) : '';
            }

            update_post_meta( $post_id, $meta_key, $value );
        }

        if ( ! get_post_meta( $post_id, '_ce_source', true ) ) {
            update_post_meta( $post_id, '_ce_source', 'manual' );
        }
    }

    public function admin_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'title' === $key ) {
                $new['ce_start_date'] = __( 'Start', 'club-events' );
                $new['ce_end_date']   = __( 'End', 'club-events' );
                $new['ce_location']   = __( 'Location', 'club-events' );
                $new['ce_source']     = __( 'Source', 'club-events' );
            }
        }
        return $new;
    }

    public function admin_column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'ce_start_date':
                $v = get_post_meta( $post_id, '_ce_start_date', true );
                echo $v ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $v ) ) ) : '—';
                break;
            case 'ce_end_date':
                $v = get_post_meta( $post_id, '_ce_end_date', true );
                echo $v ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $v ) ) ) : '—';
                break;
            case 'ce_location':
                echo esc_html( get_post_meta( $post_id, '_ce_location', true ) ?: '—' );
                break;
            case 'ce_source':
                $src = get_post_meta( $post_id, '_ce_source', true ) ?: 'manual';
                $label = 'google' === $src ? '<span style="color:#3b82f6">● Google</span>' : '<span style="color:#6b7280">● Manual</span>';
                echo wp_kses( $label, [ 'span' => [ 'style' => [] ] ] );
                break;
        }
    }

    public function sortable_columns( $columns ) {
        $columns['ce_start_date'] = 'ce_start_date';
        return $columns;
    }

    public function default_sort( $query ) {
        if ( is_admin() && $query->is_main_query() && 'club_event' === $query->get( 'post_type' ) ) {
            if ( ! $query->get( 'orderby' ) ) {
                $query->set( 'meta_key', '_ce_start_date' );
                $query->set( 'orderby', 'meta_value' );
                $query->set( 'order', 'ASC' );
            }
        }
    }

    public function load_template( $template ) {
        if ( is_singular( 'club_event' ) ) {
            $custom = CE_PLUGIN_DIR . 'templates/single-club-event.php';
            if ( '' === locate_template( 'single-club-event.php' ) ) {
                return $custom;
            }
        }
        if ( is_post_type_archive( 'club_event' ) || is_tax( [ 'event_category', 'event_type', 'event_tag' ] ) ) {
            $custom = CE_PLUGIN_DIR . 'templates/archive-club-event.php';
            if ( '' === locate_template( 'archive-club-event.php' ) ) {
                return $custom;
            }
        }
        return $template;
    }

    /**
     * Current site-local time as `Y-m-d H:i:s`.
     *
     * Event dates are stored in the site's timezone (Settings → General), so
     * comparisons must use local time, not UTC.
     */
    public static function now() {
        return current_time( 'mysql' );
    }

    /** Current site-local date as `Y-m-d`. */
    public static function today() {
        return current_time( 'Y-m-d' );
    }

    /**
     * Convert a stored site-local `Y-m-d H:i:s` value to ISO 8601 with the
     * site's UTC offset (e.g. `2026-11-24T19:30:00+01:00`).
     */
    public static function to_iso8601( $local, $date_only = false ) {
        try {
            $dt = new DateTimeImmutable( $local, wp_timezone() );
        } catch ( Exception $e ) {
            return '';
        }
        return $date_only ? $dt->format( 'Y-m-d' ) : $dt->format( 'c' );
    }

    /**
     * Human date range for an event: "27. November 2026" (one day),
     * "26. Juni 2026 – 28. Juni 2026" (several days) or
     * "24. November 2026 19:30 – 21:00" (timed). A one-day all-day event no
     * longer reads "27. November 2026 – 27. November 2026".
     *
     * Timestamps are "WordPress timestamps" (local time read as UTC), as
     * produced by strtotime() on the stored values.
     */
    public static function date_range( $start_ts, $end_ts, $all_day ) {
        if ( ! $start_ts ) {
            return '';
        }
        $date_fmt = get_option( 'date_format' );
        $time_fmt = get_option( 'time_format' );
        $same_day = ! $end_ts || gmdate( 'Ymd', $start_ts ) === gmdate( 'Ymd', $end_ts );

        if ( $all_day ) {
            $out = date_i18n( $date_fmt, $start_ts );
            return $same_day ? $out : $out . ' – ' . date_i18n( $date_fmt, $end_ts );
        }
        $out = date_i18n( $date_fmt, $start_ts ) . ' ' . date_i18n( $time_fmt, $start_ts );
        if ( ! $end_ts || $end_ts === $start_ts ) {
            return $out;
        }
        return $out . ' – ' . ( $same_day ? '' : date_i18n( $date_fmt, $end_ts ) . ' ' ) . date_i18n( $time_fmt, $end_ts );
    }

    public static function get_events( $args = [] ) {
        $defaults = [
            'post_type'      => 'club_event',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'meta_key'       => '_ce_start_date',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
            'meta_query'     => [],
        ];

        // "Upcoming" means "not over yet": an event stays listed until it
        // ends, so today's events and multi-day events in progress are kept.
        // Dates are stored in site-local time, so compare with local "now".
        if ( ! empty( $args['upcoming'] ) ) {
            $defaults['meta_query'][] = [
                'relation' => 'OR',
                [
                    'key'     => '_ce_end_date',
                    'value'   => self::now(),
                    'compare' => '>=',
                    'type'    => 'DATETIME',
                ],
                // Without an end date an event is kept for its whole day.
                [
                    'relation' => 'AND',
                    [
                        'key'     => '_ce_start_date',
                        'value'   => self::today() . ' 00:00:00',
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ],
                    [
                        'relation' => 'OR',
                        [ 'key' => '_ce_end_date', 'compare' => 'NOT EXISTS' ],
                        [ 'key' => '_ce_end_date', 'value' => '', 'compare' => '=' ],
                    ],
                ],
            ];
        }
        unset( $args['upcoming'] );

        if ( ! empty( $args['from'] ) ) {
            $defaults['meta_query'][] = [
                'key'     => '_ce_start_date',
                'value'   => $args['from'],
                'compare' => '>=',
                'type'    => 'DATETIME',
            ];
            unset( $args['from'] );
        }

        if ( ! empty( $args['to'] ) ) {
            $defaults['meta_query'][] = [
                'key'     => '_ce_start_date',
                'value'   => $args['to'],
                'compare' => '<=',
                'type'    => 'DATETIME',
            ];
            unset( $args['to'] );
        }

        if ( ! empty( $args['event_type'] ) ) {
            $defaults['tax_query'][] = [
                'taxonomy' => 'event_type',
                'field'    => 'slug',
                'terms'    => (array) $args['event_type'],
            ];
            unset( $args['event_type'] );
        }

        $query_args = wp_parse_args( $args, $defaults );
        return get_posts( $query_args );
    }

    /**
     * Resolve an event type's colour as a CSS-usable value.
     *
     * Returns the stored `_ce_color` term meta when set; otherwise falls back
     * to the Astra-bridged theme primary (`var(--ce-primary)`), which itself
     * resolves to the plugin default when Astra is not active. This guarantees
     * every event type always renders with a colour.
     */
    public static function type_color( $term_id ) {
        $color = get_term_meta( (int) $term_id, '_ce_color', true );
        return $color ? $color : 'var(--ce-primary)';
    }

    public static function format_event( $post_id ) {
        $post     = get_post( $post_id );
        $start    = get_post_meta( $post_id, '_ce_start_date', true );
        $end      = get_post_meta( $post_id, '_ce_end_date', true );
        $all_day  = get_post_meta( $post_id, '_ce_all_day', true );
        $color    = get_post_meta( $post_id, '_ce_color', true );
        $location = get_post_meta( $post_id, '_ce_location', true );
        $loc_url  = get_post_meta( $post_id, '_ce_location_url', true );
        $ext_url  = get_post_meta( $post_id, '_ce_external_url', true );

        $cats  = wp_get_post_terms( $post_id, 'event_category', [ 'fields' => 'all' ] );
        $types = wp_get_post_terms( $post_id, 'event_type', [ 'fields' => 'all' ] );
        $types = is_wp_error( $types ) ? [] : $types;

        // Colour resolution: explicit event colour → first type colour →
        // Astra theme primary. Every event therefore always has a colour.
        if ( ! $color ) {
            $color = ! empty( $types ) ? self::type_color( $types[0]->term_id ) : 'var(--ce-primary)';
        }

        return [
            'id'          => $post_id,
            'title'       => $post->post_title,
            'excerpt'     => wp_strip_all_tags( $post->post_excerpt ?: wp_trim_words( $post->post_content, 20 ) ),
            'url'         => get_permalink( $post_id ),
            'ics'         => class_exists( 'CE_ICS_Export' ) ? CE_ICS_Export::get_single_url( $post_id ) : '',
            'thumbnail'   => get_the_post_thumbnail_url( $post_id, 'medium' ),
            'start'       => $start,
            'end'         => $end,
            'allDay'      => (bool) $all_day,
            'color'       => $color,
            'location'    => $location,
            'locationUrl' => $loc_url,
            'externalUrl' => $ext_url,
            'categories'  => array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ], is_wp_error( $cats ) ? [] : $cats ),
            'types'       => array_map( fn( $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'color' => self::type_color( $t->term_id ) ], $types ),
        ];
    }
}
