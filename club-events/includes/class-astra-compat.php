<?php
defined( 'ABSPATH' ) || exit;

/**
 * Astra (Free + Premium) theme compatibility layer.
 *
 * Bridges every Astra design token into --ce-* custom properties so the
 * plugin automatically inherits the active palette, typography, button
 * styles, input styles, spacing and border-radius — zero manual config.
 */
class CE_Astra_Compat {

    private static ?bool $is_astra = null;

    public static function is_active(): bool {
        if ( null === self::$is_astra ) {
            $theme          = wp_get_theme();
            $text_domain    = strtolower( (string) $theme->get( 'TextDomain' ) );
            $parent_domain  = strtolower( (string) $theme->get( 'Template' ) );
            self::$is_astra = in_array( 'astra', [ $text_domain, $parent_domain ], true );
        }
        return self::$is_astra;
    }

    public function __construct() {
        if ( ! self::is_active() ) {
            return;
        }

        // The bridge rides on the plugin stylesheet as inline CSS, so it prints
        // after it (and wins the cascade) and follows it into the block-editor
        // iframe and the Elementor preview.
        add_action( 'wp_enqueue_scripts',   CE_Safe::action( 'wp_enqueue_scripts', [ $this, 'attach_bridge' ] ), 5 );
        add_action( 'enqueue_block_assets', CE_Safe::action( 'enqueue_block_assets', [ $this, 'attach_bridge' ] ), 5 );

        add_action( 'wp_head',            CE_Safe::action( 'wp_head', [ $this, 'output_event_schema' ] ),      20 );
        add_filter( 'body_class',         CE_Safe::filter( 'body_class', [ $this, 'body_classes' ] )                );
        add_filter( 'astra_page_layout',  CE_Safe::filter( 'astra_page_layout', [ $this, 'single_event_layout' ] )         );
        add_filter( 'astra_content_width',CE_Safe::filter( 'astra_content_width', [ $this, 'archive_content_width' ] )       );

        add_filter( 'astra_banner_visibility',      CE_Safe::filter( 'astra_banner_visibility', [ $this, 'hide_title_bar_on_single' ] ) );
        add_filter( 'astra_the_title_enabled',      CE_Safe::filter( 'astra_the_title_enabled', [ $this, 'hide_title_bar_on_single' ] ) );
        add_filter( 'astra_title_bar_enabled',      CE_Safe::filter( 'astra_title_bar_enabled', [ $this, 'hide_title_bar_on_single' ] ) );
        add_filter( 'astra_addon_banner_visibility',CE_Safe::filter( 'astra_addon_banner_visibility', [ $this, 'hide_title_bar_on_single' ] ) );

        add_filter( 'astra_breadcrumb_trail_items', CE_Safe::filter( 'astra_breadcrumb_trail_items', [ $this, 'event_breadcrumbs' ] ), 10, 2 );

        add_filter( 'astra_metabox_page_types', CE_Safe::filter( 'astra_metabox_page_types', [ $this, 'register_for_metabox' ] ) );

        add_action( 'astra_single_post_before_content', CE_Safe::action( 'astra_single_post_before_content', [ $this, 'maybe_suppress_entry_header' ] ) );
    }

    // ─── CSS Variable Bridge ──────────────────────────────────────────────

    public function attach_bridge(): void {
        static $attached = false;
        if ( $attached || ! wp_style_is( 'club-events', 'registered' ) ) {
            return;
        }
        $attached = true;
        wp_add_inline_style( 'club-events', self::bridge_css() );
    }

    /** Read an Astra customizer setting, or $default outside Astra. */
    private static function opt( string $key, $default = '' ) {
        if ( ! function_exists( 'astra_get_option' ) ) {
            return $default;
        }
        $value = astra_get_option( $key, $default );
        return null === $value ? $default : $value;
    }

    /** A CSS length from a number and unit, or '' when the number is unset. */
    private static function length( $size, $unit = 'px' ): string {
        if ( ! is_numeric( $size ) ) {
            return '';
        }
        $unit = in_array( $unit, [ 'px', 'em', 'rem', '%', 'vw' ], true ) ? $unit : 'px';
        return ( 0 + $size ) . $unit;
    }

    /** Desktop value of an Astra responsive setting (a size or a slider). */
    private static function responsive_length( $value ): string {
        if ( is_array( $value ) ) {
            return self::length( $value['desktop'] ?? '', $value['desktop-unit'] ?? 'px' );
        }
        return self::length( $value );
    }

    /** A font-family stack from Astra, or '' when it inherits the body font. */
    private static function font_family( $value ): string {
        if ( ! is_string( $value ) || '' === trim( $value ) || 'inherit' === $value ) {
            return '';
        }
        return preg_replace( '/[^\w\s,\'"\-]/', '', $value );
    }

    private static function font_weight( $value ): string {
        return preg_match( '/^(?:[1-9]00|normal|bold)$/', (string) $value ) ? (string) $value : '';
    }

    /**
     * Button corner radius. Astra 4 stores four corners per device
     * (`button-radius-fields`); older versions a single `button-radius`.
     */
    private static function button_radius(): string {
        $fields = self::opt( 'button-radius-fields' );
        if ( is_array( $fields ) && isset( $fields['desktop'] ) && is_array( $fields['desktop'] ) ) {
            $unit    = $fields['desktop-unit'] ?? 'px';
            $corners = [];
            foreach ( [ 'top', 'right', 'bottom', 'left' ] as $side ) {
                $corners[] = self::length( $fields['desktop'][ $side ] ?? '', $unit );
            }
            if ( ! in_array( '', $corners, true ) ) {
                return implode( ' ', $corners );
            }
        }
        return self::length( self::opt( 'button-radius' ) );
    }

    /**
     * Design tokens read from the Astra customizer. Anything Astra leaves
     * unset is omitted so the palette-based defaults in bridge_css() apply.
     */
    public static function tokens(): array {
        $t = [];

        $t['--ce-heading-font-family'] = self::font_family( self::opt( 'headings-font-family' ) );
        $t['--ce-heading-font-weight'] = self::font_weight( self::opt( 'headings-font-weight' ) );
        foreach ( [ 1, 2, 3, 4 ] as $level ) {
            $t[ '--ce-h' . $level . '-size' ] = self::responsive_length( self::opt( 'font-size-h' . $level ) );
        }

        $t['--ce-btn-bg']          = CE_Style::sanitize_color( self::opt( 'button-bg-color' ) );
        $t['--ce-btn-bg-hover']    = CE_Style::sanitize_color( self::opt( 'button-bg-h-color' ) );
        $t['--ce-btn-color']       = CE_Style::sanitize_color( self::opt( 'button-color' ) );
        $t['--ce-btn-color-hover'] = CE_Style::sanitize_color( self::opt( 'button-h-color' ) );
        $t['--ce-btn-radius']      = self::button_radius();
        $t['--ce-btn-font-family'] = self::font_family( self::opt( 'font-family-button' ) );
        $t['--ce-btn-font-size']   = self::responsive_length( self::opt( 'font-size-button' ) );
        $t['--ce-btn-font-weight'] = self::font_weight( self::opt( 'font-weight-button' ) );

        $extras    = self::opt( 'font-extras-button' );
        $transform = is_array( $extras ) ? ( $extras['text-transform'] ?? '' ) : self::opt( 'text-transform-button' );
        $t['--ce-btn-text-transform'] = in_array( $transform, [ 'none', 'uppercase', 'lowercase', 'capitalize' ], true ) ? $transform : '';
        if ( is_array( $extras ) ) {
            $t['--ce-btn-letter-spacing'] = self::length( $extras['letter-spacing'] ?? '', $extras['letter-spacing-unit'] ?? 'px' );
        }

        $padding = self::opt( 'theme-button-padding' );
        if ( is_array( $padding ) && isset( $padding['desktop'] ) && is_array( $padding['desktop'] ) ) {
            $unit = $padding['desktop-unit'] ?? 'px';
            $t['--ce-btn-padding-v'] = self::length( $padding['desktop']['top'] ?? '', $unit );
            $t['--ce-btn-padding-h'] = self::length( $padding['desktop']['right'] ?? '', $unit );
        }

        /**
         * Filter the design tokens the Astra bridge derives from the customizer.
         *
         * @param array $tokens Custom property => CSS value ('' = use the default).
         */
        return array_filter( (array) apply_filters( 'ce_astra_tokens', $t ), 'strlen' );
    }

    public static function bridge_css(): string {
        $overrides = '';
        foreach ( self::tokens() as $prop => $value ) {
            $overrides .= $prop . ':' . $value . ';';
        }

        // Astra's global palette slots: 0 brand, 1 alternate brand, 2 headings,
        // 3 body text, 4 primary background, 5 secondary background (surfaces),
        // 6–8 supporting colours. Muted text and borders are mixed from text
        // and surface so they stay legible on dark palettes too.
        $css = <<<'CSS'
:root {
    --ce-primary:        var(--ast-global-color-0, #3b82f6);
    --ce-primary-dk:     var(--ast-global-color-1, #1d4ed8);
    --ce-heading-color:  var(--ast-global-color-2, #1e293b);
    --ce-text:           var(--ast-global-color-3, #334155);
    --ce-bg:             var(--ast-global-color-4, #f8fafc);
    --ce-white:          var(--ast-global-color-5, #ffffff);
    --ce-primary-lt:     color-mix(in srgb, var(--ast-global-color-0, #3b82f6) 10%, var(--ast-global-color-5, #ffffff));
    --ce-text-muted:     color-mix(in srgb, var(--ast-global-color-3, #334155) 72%, var(--ast-global-color-5, #ffffff));
    --ce-border:         var(--ast-border-color, color-mix(in srgb, var(--ast-global-color-3, #334155) 16%, var(--ast-global-color-5, #ffffff)));
    --ce-accent:         var(--ce-primary);
    --ce-link:           var(--ce-primary);
    --ce-link-hover:     var(--ce-primary-dk);
    --ce-input-focus:    var(--ce-primary);

    --ce-heading-font-family: inherit;
    --ce-heading-font-weight: 700;
    --ce-h1-size: 2rem;
    --ce-h2-size: 1.6rem;
    --ce-h3-size: 1.2rem;
    --ce-h4-size: 1rem;

    --ce-btn-bg:             var(--ce-primary);
    --ce-btn-bg-hover:       var(--ce-primary-dk);
    --ce-btn-color:          #ffffff;
    --ce-btn-color-hover:    var(--ce-btn-color);
    --ce-btn-radius:         6px;
    --ce-btn-font-family:    inherit;
    --ce-btn-font-size:      14px;
    --ce-btn-font-weight:    600;
    --ce-btn-text-transform: none;
    --ce-btn-letter-spacing: normal;
    --ce-btn-padding-v:      8px;
    --ce-btn-padding-h:      18px;

    --ce-section-spacing: 2rem;
}
:root { %OVERRIDES% }

/* ── Headings follow Astra's heading font ─────────────────────────── */
.ce-event-title, .ce-archive-title, .ce-subscribe-title, .ce-cal-title,
.ce-overview-list-title, .ce-yearly-month-title, .ce-card-title,
.ce-tile-card-title, .ce-list-title, .ce-upcoming-title, .ce-yearly-event-title {
    font-family: var(--ce-heading-font-family);
}
.ce-archive-title, .ce-subscribe-title, .ce-cal-title, .ce-overview-list-title {
    font-weight: var(--ce-heading-font-weight);
    color: var(--ce-heading-color);
}
.ce-event-hero .ce-event-title { font-size: var(--ce-h1-size); color: #fff !important; }
.ce-archive-title              { font-size: var(--ce-h1-size); }
.ce-subscribe-title            { font-size: var(--ce-h3-size); }
.ce-overview-list-title        { font-size: var(--ce-h4-size); }

.ce-event-title a, .ce-card-title, .ce-tile-card-title, .ce-list-title,
.ce-upcoming-title, .ce-yearly-event-title { color: var(--ce-heading-color); }
.ce-event-title a:hover, .ce-card-item:hover .ce-card-title,
.ce-tile-card:hover .ce-tile-card-title, .ce-list-title:hover,
.ce-upcoming-title:hover { color: var(--ce-link); }

/* ── Buttons match Astra's buttons ────────────────────────────────── */
.ce-btn {
    font-family: var(--ce-btn-font-family);
    font-weight: var(--ce-btn-font-weight);
    text-transform: var(--ce-btn-text-transform);
    letter-spacing: var(--ce-btn-letter-spacing);
    border-radius: var(--ce-btn-radius);
}
.ce-btn:not(.ce-btn-sm) {
    font-size: var(--ce-btn-font-size);
    padding: var(--ce-btn-padding-v) var(--ce-btn-padding-h);
}
.ce-btn-primary {
    background: var(--ce-btn-bg);
    border-color: var(--ce-btn-bg);
    color: var(--ce-btn-color) !important;
}
.ce-btn-primary:hover, .ce-btn-primary:focus-visible {
    background: var(--ce-btn-bg-hover);
    border-color: var(--ce-btn-bg-hover);
    color: var(--ce-btn-color-hover) !important;
}
.ce-filter-btn, .ce-view-btn, .ce-ics-link, .ce-cal-nav-btn {
    border-radius: var(--ce-btn-radius);
}
.ce-filter-btn:hover, .ce-filter-btn.active {
    background: var(--ce-btn-bg);
    border-color: var(--ce-btn-bg);
    color: var(--ce-btn-color);
}

/* ── Form inputs ──────────────────────────────────────────────────── */
.ce-subscribe-form input[type="text"], .ce-subscribe-form input[type="email"],
.ce-submit-form input[type="text"], .ce-submit-form input[type="datetime-local"],
.ce-submit-form input[type="date"], .ce-submit-form textarea, .ce-submit-form select,
.ce-hub-search-input {
    font-family: inherit;
    color: var(--ce-text);
    background: var(--ce-white);
    border-color: var(--ce-border);
}
.ce-subscribe-form input:focus, .ce-submit-form input:focus,
.ce-submit-form textarea:focus, .ce-submit-form select:focus, .ce-hub-search-input:focus {
    border-color: var(--ce-input-focus);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ce-input-focus) 15%, transparent);
    outline: none;
}

/* ── Surfaces ─────────────────────────────────────────────────────── */
.ce-cal-today .ce-cal-day-num { background: var(--ce-primary); color: var(--ce-btn-color); }
.ce-category-badge:hover { background: var(--ce-primary); color: var(--ce-btn-color); }

/* ── Full-bleed hero inside Astra's content column ────────────────── */
body.ce-full-width-event .ce-single-event .ce-event-hero {
    width:        100vw;
    margin-left:  calc(50% - 50vw);
    margin-right: calc(50% - 50vw);
    border-radius: 0;
}
@supports (scrollbar-gutter: stable) {
    body.ce-full-width-event .ce-single-event .ce-event-hero {
        width:        calc(100vw - var(--ast-scrollbar-width, 0px));
        margin-left:  calc(50% - 50vw + var(--ast-scrollbar-width, 0px) / 2);
        margin-right: calc(50% - 50vw + var(--ast-scrollbar-width, 0px) / 2);
    }
}
body.single-club_event.ast-transparent-header .ce-event-hero {
    padding-top: calc(var(--ast-transparent-header-logo-width, 80px) + 40px);
}

/* ── Astra layout ─────────────────────────────────────────────────── */
body.ce-full-width-event #primary.content-area { width: 100%; max-width: 100%; float: none; }
body.ce-full-width-event #secondary { display: none; }
body.post-type-archive-club_event .ast-container > #primary,
body.tax-event_category .ast-container > #primary,
body.tax-event_type .ast-container > #primary,
body.tax-event_tag .ast-container > #primary { flex: 1; min-width: 0; }

body.single-club_event .ast-separate-container .ast-article-single .entry-header,
body.single-club_event .ast-plain-container .ast-article-single .entry-header,
body.single-club_event .entry-header .entry-title { display: none; }

.ast-separate-container .ce-event-body-wrap,
.ast-separate-container .ce-archive-wrap { padding-left: 0; padding-right: 0; }

.ce-archive-wrap, .ce-timeline-wrap, .ce-cards-wrap, .ce-overview-wrap,
.ce-yearly-wrap, .ce-submit-wrap, .ce-my-events, .ce-event-list {
    padding-top: var(--ce-section-spacing);
}
.ce-block > .ce-timeline-wrap, .ce-block > .ce-cards-wrap, .ce-block > .ce-overview-wrap,
.ce-block > .ce-yearly-wrap, .ce-block > .ce-submit-wrap, .ce-block > .ce-my-events,
.ce-block > .ce-event-list, .elementor-widget-container > [class^="ce-"] { padding-top: 0; }
.ce-archive-wrap, .site-main > .ce-single-event { padding-bottom: var(--ce-section-spacing); }

/* ── Header stacking (event pages only: keeps Astra's header above the
 *    full-bleed hero without touching headers anywhere else) ──────── */
body.single-club_event #masthead, body.single-club_event .main-header-bar,
body.single-club_event .ast-primary-sticky-header { z-index: 1000; }
.ce-filter-bar, .ce-cal-nav { z-index: 10; }
body.single-club_event.ast-header-above-grid-enabled #content,
body.single-club_event.ast-header-below-grid-enabled #content { position: relative; z-index: 1; }

/* ── Breadcrumb ───────────────────────────────────────────────────── */
.ce-breadcrumb-wrap { padding: 10px 0 0; font-size: 13px; }
.ce-breadcrumb-wrap .astra-breadcrumbs { padding: 0; background: none; }
CSS;

        return str_replace( '%OVERRIDES%', $overrides, $css );
    }

    // ─── JSON-LD Event Schema ─────────────────────────────────────────────

    public function output_event_schema(): void {
        if ( ! is_singular( 'club_event' ) ) {
            return;
        }

        $post_id  = get_the_ID();
        $start    = get_post_meta( $post_id, '_ce_start_date', true );
        $end      = get_post_meta( $post_id, '_ce_end_date', true );
        $location = get_post_meta( $post_id, '_ce_location', true );
        $loc_url  = get_post_meta( $post_id, '_ce_location_url', true );
        $ext_url  = get_post_meta( $post_id, '_ce_external_url', true );

        if ( ! $start ) {
            return;
        }

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Event',
            'name'        => get_the_title( $post_id ),
            'description' => wp_strip_all_tags( get_post_field( 'post_excerpt', $post_id ) ?: get_post_field( 'post_content', $post_id ) ),
            'startDate'   => gmdate( 'c', strtotime( $start ) ),
            'url'         => get_permalink( $post_id ),
            'organizer'   => [
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'url'   => home_url(),
            ],
        ];

        if ( $end ) {
            $schema['endDate'] = gmdate( 'c', strtotime( $end ) );
        }

        if ( $location ) {
            $schema['location'] = [
                '@type' => 'Place',
                'name'  => $location,
            ];
            if ( $loc_url ) {
                $schema['location']['url'] = $loc_url;
            }
        }

        if ( $ext_url ) {
            $schema['url'] = $ext_url;
        }

        if ( has_post_thumbnail( $post_id ) ) {
            $schema['image'] = get_the_post_thumbnail_url( $post_id, 'large' );
        }

        $schema['eventStatus']        = 'https://schema.org/EventScheduled';
        $schema['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';

        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }

    // ─── Body Classes ─────────────────────────────────────────────────────

    public function body_classes( array $classes ): array {
        if ( is_singular( 'club_event' ) ) {
            $classes[] = 'ce-single-event-page';
            if ( ! self::has_chosen_sidebar() ) {
                $classes[] = 'ast-no-sidebar';
                $classes[] = 'ce-full-width-event';
            }
        }
        if ( is_post_type_archive( 'club_event' ) || is_tax( [ 'event_category', 'event_type', 'event_tag' ] ) ) {
            $classes[] = 'ce-archive-page';
        }
        return $classes;
    }

    // ─── Layout ───────────────────────────────────────────────────────────

    /**
     * Single events default to full width (the hero is full-bleed), unless
     * the site owner picked a sidebar for events in Astra — either on the
     * event itself (Astra Settings meta box) or for all events in the
     * Customizer — in which case their choice wins.
     */
    public function single_event_layout( $layout ) {
        if ( is_singular( 'club_event' ) && ! self::has_chosen_sidebar() ) {
            return 'no-sidebar';
        }
        return $layout;
    }

    private static function has_chosen_sidebar(): bool {
        $choices = [
            get_post_meta( get_queried_object_id(), 'site-sidebar-layout', true ),
            self::opt( 'single-club_event-sidebar-layout' ),
        ];
        foreach ( $choices as $choice ) {
            if ( is_string( $choice ) && '' !== $choice && 'default' !== $choice ) {
                return 'no-sidebar' !== $choice;
            }
        }
        return false;
    }

    public function archive_content_width( $width ) {
        if ( is_post_type_archive( 'club_event' ) || is_tax( [ 'event_category', 'event_type', 'event_tag' ] ) ) {
            return '100';
        }
        return $width;
    }

    // ─── Title Bar ────────────────────────────────────────────────────────

    public function hide_title_bar_on_single( $value ) {
        if ( is_singular( 'club_event' ) ) {
            return false;
        }
        return $value;
    }

    public function maybe_suppress_entry_header(): void {
        if ( ! is_singular( 'club_event' ) ) {
            return;
        }
        remove_action( 'astra_single_post_before_content', 'astra_entry_header_template' );
    }

    // ─── Breadcrumbs ─────────────────────────────────────────────────────

    public function event_breadcrumbs( array $items, array $args ): array {
        if ( ! is_singular( 'club_event' ) && ! is_post_type_archive( 'club_event' )
            && ! is_tax( [ 'event_category', 'event_type', 'event_tag' ] ) ) {
            return $items;
        }

        $archive_url   = CE_CPT::archive_url();
        $archive_label = __( 'Events', 'club-events' );

        $trail = [
            '<a href="' . esc_url( home_url() ) . '">' . __( 'Home', 'club-events' ) . '</a>',
            '<a href="' . esc_url( $archive_url ) . '">' . esc_html( $archive_label ) . '</a>',
        ];

        if ( is_singular( 'club_event' ) ) {
            $cats = wp_get_post_terms( get_the_ID(), 'event_category' );
            if ( ! is_wp_error( $cats ) && $cats ) {
                $trail[] = '<a href="' . esc_url( get_term_link( $cats[0] ) ) . '">' . esc_html( $cats[0]->name ) . '</a>';
            }
            $trail[] = get_the_title();
        } elseif ( is_tax() ) {
            $trail[] = single_term_title( '', false );
        }

        return $trail;
    }

    // ─── Admin ────────────────────────────────────────────────────────────

    public function register_for_metabox( array $types ): array {
        $types[] = 'club_event';
        return $types;
    }
}
