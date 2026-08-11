<?php
defined( 'ABSPATH' ) || exit;

class CE_Elementor {

    public function __construct() {
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_categories' ] );
        add_action( 'elementor/frontend/after_enqueue_styles', [ $this, 'frontend_styles' ] );
    }

    public function register_categories( $elements_manager ) {
        $elements_manager->add_category( 'club-events', [
            'title' => __( 'Club Events', 'club-events' ),
            'icon'  => 'eicon-calendar',
        ] );
    }

    public function register_widgets( $widgets_manager ) {
        $widgets_manager->register( new CE_Elementor_Hub() );
        $widgets_manager->register( new CE_Elementor_Tiles() );
        $widgets_manager->register( new CE_Elementor_Timeline() );
        $widgets_manager->register( new CE_Elementor_Overview() );
        $widgets_manager->register( new CE_Elementor_Cards() );
        $widgets_manager->register( new CE_Elementor_List() );
        $widgets_manager->register( new CE_Elementor_Yearly() );
        $widgets_manager->register( new CE_Elementor_Share() );
        $widgets_manager->register( new CE_Elementor_Subscribe() );
        $widgets_manager->register( new CE_Elementor_Submit() );
        $widgets_manager->register( new CE_Elementor_MyEvents() );
    }

    public function frontend_styles() {
        wp_enqueue_style( 'club-events' );
        wp_enqueue_script( 'club-events' );
    }
}

/* ─── Shared helpers for widget controls ──────────────────────────────── */
trait CE_Elementor_Controls {

    protected function add_content_controls( array $opts = [] ) {
        $this->start_controls_section( 'section_content', [
            'label' => __( 'Content', 'club-events' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'category', [
            'label'       => __( 'Category Slug', 'club-events' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => '',
            'description' => __( 'Filter by event category slug. Leave empty for all.', 'club-events' ),
        ] );

        $this->add_control( 'event_type', [
            'label'       => __( 'Event Type Slug', 'club-events' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => '',
            'description' => __( 'Filter by event type slug. Leave empty for all.', 'club-events' ),
        ] );

        if ( ! empty( $opts['filter_by'] ) ) {
            $this->add_control( 'filter_by', [
                'label'   => __( 'Filter Bar Shows', 'club-events' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'category',
                'options' => [
                    'category'   => __( 'Categories', 'club-events' ),
                    'event_type' => __( 'Event Types', 'club-events' ),
                ],
            ] );
        }

        if ( ! empty( $opts['limit'] ) ) {
            $this->add_control( 'limit', [
                'label'   => __( 'Max Events', 'club-events' ),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'default' => $opts['limit'],
                'min'     => 1,
                'max'     => 200,
            ] );
        }

        if ( ! empty( $opts['columns'] ) ) {
            $this->add_control( 'columns', [
                'label'   => __( 'Columns', 'club-events' ),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'default' => 3,
                'min'     => 1,
                'max'     => 4,
            ] );
        }

        if ( ! empty( $opts['show_past'] ) ) {
            $this->add_control( 'show_past', [
                'label'        => __( 'Show Past Events', 'club-events' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'default'      => '',
                'return_value' => 'yes',
            ] );
        }

        if ( ! empty( $opts['show_filter'] ) ) {
            $this->add_control( 'show_filter', [
                'label'        => __( 'Show Filter Bar', 'club-events' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ] );
        }

        if ( ! empty( $opts['show_image'] ) ) {
            $this->add_control( 'show_image', [
                'label'        => __( 'Show Image', 'club-events' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ] );
        }

        if ( ! empty( $opts['layout'] ) ) {
            $this->add_control( 'layout', [
                'label'   => __( 'Layout', 'club-events' ),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => 'default',
                'options' => [
                    'default' => __( 'Default (left rail)', 'club-events' ),
                    'center'  => __( 'Centred (alternating)', 'club-events' ),
                ],
            ] );
        }

        $this->end_controls_section();
    }

    /** A SWITCHER that maps to a shortcode boolean, defaulting to off. */
    protected function add_switcher( string $id, string $label, string $default = '' ) {
        $this->add_control( $id, [
            'label'        => $label,
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => $default,
            'return_value' => 'yes',
        ] );
    }

    /**
     * Build and run the shortcode behind a widget.
     *
     * Every `show_*` control is a SWITCHER, so its value is either 'yes' or ''
     * — both are forwarded explicitly as 1/0 so an unchecked switch actually
     * overrides a shortcode default of true. Remaining empty values are
     * dropped so the shortcode's own default applies.
     *
     * `$keys` entries are control IDs; use `'shortcode_att' => 'control_id'`
     * when the two names differ.
     */
    protected function build_shortcode( string $tag, array $keys ): string {
        $s    = $this->get_settings_for_display();
        $atts = '';
        foreach ( $keys as $att => $key ) {
            if ( is_int( $att ) ) {
                $att = $key;
            }
            $val = $s[ $key ] ?? '';
            if ( 0 === strpos( $att, 'show_' ) ) {
                $val = ( 'yes' === $val ) ? '1' : '0';
            }
            if ( '' === $val || null === $val ) {
                continue;
            }
            // Shortcode attributes are parsed from a raw string, so a quote in
            // a user-supplied value would terminate the attribute early.
            $atts .= ' ' . $att . '="' . str_replace( [ '"', ']', '[' ], '', (string) $val ) . '"';
        }
        return do_shortcode( '[' . $tag . $atts . ']' );
    }
}

/* ═══════════════════════════════════════════════════════════════════════ */
/*  Widget: Events Timeline                                              */
/* ═══════════════════════════════════════════════════════════════════════ */
class CE_Elementor_Timeline extends \Elementor\Widget_Base {
    use CE_Elementor_Controls;

    public function get_name()       { return 'club-events-timeline'; }
    public function get_title()      { return __( 'Events Timeline', 'club-events' ); }
    public function get_icon()       { return 'eicon-time-line'; }
    public function get_categories() { return [ 'club-events' ]; }
    public function get_keywords()   { return [ 'events', 'timeline', 'calendar', 'club' ]; }

    protected function register_controls() {
        $this->add_content_controls( [
            'filter_by'   => true,
            'limit'       => 20,
            'show_past'   => true,
            'show_filter' => true,
            'layout'      => true,
        ] );
    }

    protected function render() {
        echo $this->build_shortcode( 'club_events_timeline', [
            'category', 'event_type', 'filter_by', 'limit', 'show_past', 'show_filter', 'layout',
        ] );
    }
}

/* ═══════════════════════════════════════════════════════════════════════ */
/*  Widget: Events Hub                                                   */
/* ═══════════════════════════════════════════════════════════════════════ */
class CE_Elementor_Hub extends \Elementor\Widget_Base {
    use CE_Elementor_Controls;

    public function get_name()       { return 'club-events-hub'; }
    public function get_title()      { return __( 'Events Hub', 'club-events' ); }
    public function get_icon()       { return 'eicon-gallery-grid'; }
    public function get_categories() { return [ 'club-events' ]; }
    public function get_keywords()   { return [ 'events', 'hub', 'calendar', 'search', 'club' ]; }

    protected function register_controls() {
        $this->add_content_controls( [
            'filter_by'   => true,
            'limit'       => 60,
            'columns'     => true,
            'show_past'   => true,
            'show_filter' => true,
        ] );

        $this->start_controls_section( 'section_views', [
            'label' => __( 'Views', 'club-events' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'views', [
            'label'       => __( 'Enabled Views', 'club-events' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => 'tiles,list,timeline,calendar',
            'description' => __( 'Comma-separated, in order: tiles, list, timeline, calendar.', 'club-events' ),
        ] );

        $this->add_control( 'default_view', [
            'label'       => __( 'Default View', 'club-events' ),
            'type'        => \Elementor\Controls_Manager::SELECT,
            'default'     => '',
            'options'     => [
                ''         => __( 'First enabled view', 'club-events' ),
                'tiles'    => __( 'Tiles', 'club-events' ),
                'list'     => __( 'List', 'club-events' ),
                'timeline' => __( 'Timeline', 'club-events' ),
                'calendar' => __( 'Calendar', 'club-events' ),
            ],
        ] );

        $this->add_switcher( 'show_search', __( 'Show Search Box', 'club-events' ), 'yes' );
        $this->add_switcher( 'show_subscribe', __( 'Show Subscribe (ICS) Button', 'club-events' ), 'yes' );

        $this->end_controls_section();
    }

    protected function render() {
        echo $this->build_shortcode( 'club_events', [
            'category', 'event_type', 'filter_by', 'limit', 'columns',
            'show_past', 'show_filter', 'show_search', 'show_subscribe',
            'views',
            'default' => 'default_view',
        ] );
    }
}

/* ═══════════════════════════════════════════════════════════════════════ */
/*  Widget: Event Tiles                                                  */
/* ═══════════════════════════════════════════════════════════════════════ */
class CE_Elementor_Tiles extends \Elementor\Widget_Base {
    use CE_Elementor_Controls;

    public function get_name()       { return 'club-events-tiles'; }
    public function get_title()      { return __( 'Event Tiles', 'club-events' ); }
    public function get_icon()       { return 'eicon-gallery-masonry'; }
    public function get_categories() { return [ 'club-events' ]; }
    public function get_keywords()   { return [ 'events', 'tiles', 'cards', 'preview', 'club' ]; }

    protected function register_controls() {
        $this->add_content_controls( [
            'limit'      => 6,
            'columns'    => true,
            'show_image' => true,
        ] );

        $this->start_controls_section( 'section_tile_display', [
            'label' => __( 'Tile Display', 'club-events' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_switcher( 'show_excerpt',  __( 'Show Excerpt', 'club-events' ), 'yes' );
        $this->add_switcher( 'show_location', __( 'Show Location', 'club-events' ) );
        $this->add_switcher( 'show_time',     __( 'Show Time', 'club-events' ) );
        $this->add_switcher( 'show_types',    __( 'Show Event Type Badges', 'club-events' ) );
        $this->add_switcher( 'show_share',    __( 'Show Share Button', 'club-events' ) );
        $this->add_switcher( 'show_ics',      __( 'Show "Add to Calendar" Button', 'club-events' ) );

        $this->add_control( 'cta', [
            'label'   => __( 'Call to Action Label', 'club-events' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => __( 'Weiterlesen', 'club-events' ),
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        echo $this->build_shortcode( 'club_events_tiles', [
            'category', 'event_type', 'limit', 'columns', 'show_image',
            'show_excerpt', 'show_location', 'show_time', 'show_types',
            'show_share', 'show_ics', 'cta',
        ] );
    }
}

/* ═══════════════════════════════════════════════════════════════════════ */
/*  Widget: Events Calendar / Overview                                   */
/* ═══════════════════════════════════════════════════════════════════════ */
class CE_Elementor_Overview extends \Elementor\Widget_Base {
    use CE_Elementor_Controls;

    public function get_name()       { return 'club-events-overview'; }
    public function get_title()      { return __( 'Events Calendar', 'club-events' ); }
    public function get_icon()       { return 'eicon-calendar'; }
    public function get_categories() { return [ 'club-events' ]; }
    public function get_keywords()   { return [ 'events', 'calendar', 'overview', 'monthly' ]; }

    protected function register_controls() {
        $this->add_content_controls( [
            'filter_by'   => true,
            'show_filter' => true,
        ] );
    }

    protected function render() {
        echo $this->build_shortcode( 'club_events_overview', [
            'category', 'event_type', 'filter_by', 'show_filter',
        ] );
    }
}

/* ═══════════════════════════════════════════════════════════════════════ */
/*  Widget: Events Cards                                                 */
/* ═══════════════════════════════════════════════════════════════════════ */
class CE_Elementor_Cards extends \Elementor\Widget_Base {
    use CE_Elementor_Controls;

    public function get_name()       { return 'club-events-cards'; }
    public function get_title()      { return __( 'Events Cards', 'club-events' ); }
    public function get_icon()       { return 'eicon-posts-grid'; }
    public function get_categories() { return [ 'club-events' ]; }
    public function get_keywords()   { return [ 'events', 'cards', 'grid', 'club' ]; }

    protected function register_controls() {
        $this->add_content_controls( [
            'filter_by'   => true,
            'limit'       => 6,
            'columns'     => true,
            'show_past'   => true,
            'show_filter' => true,
            'show_image'  => true,
        ] );
    }

    protected function render() {
        echo $this->build_shortcode( 'club_events_cards', [
            'category', 'event_type', 'filter_by', 'limit', 'columns',
            'show_past', 'show_filter', 'show_image',
        ] );
    }
}

/* ═══════════════════════════════════════════════════════════════════════ */
/*  Widget: Events List                                                  */
/* ═══════════════════════════════════════════════════════════════════════ */
class CE_Elementor_List extends \Elementor\Widget_Base {
    use CE_Elementor_Controls;

    public function get_name()       { return 'club-events-list'; }
    public function get_title()      { return __( 'Events List', 'club-events' ); }
    public function get_icon()       { return 'eicon-editor-list-ul'; }
    public function get_categories() { return [ 'club-events' ]; }
    public function get_keywords()   { return [ 'events', 'list', 'upcoming', 'club' ]; }

    protected function register_controls() {
        $this->add_content_controls( [
            'limit'     => 5,
            'show_past' => true,
        ] );
    }

    protected function render() {
        echo $this->build_shortcode( 'club_events_list', [
            'category', 'event_type', 'limit', 'show_past',
        ] );
    }
}

/* ═══════════════════════════════════════════════════════════════════════ */
/*  Widget: Yearly Agenda                                                */
/* ═══════════════════════════════════════════════════════════════════════ */
class CE_Elementor_Yearly extends \Elementor\Widget_Base {
    use CE_Elementor_Controls;

    public function get_name()       { return 'club-events-yearly'; }
    public function get_title()      { return __( 'Yearly Agenda', 'club-events' ); }
    public function get_icon()       { return 'eicon-date'; }
    public function get_categories() { return [ 'club-events' ]; }
    public function get_keywords()   { return [ 'events', 'yearly', 'agenda', 'annual' ]; }

    protected function register_controls() {
        $this->start_controls_section( 'section_content', [
            'label' => __( 'Content', 'club-events' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'category', [
            'label'   => __( 'Category Slug', 'club-events' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ] );

        $this->add_control( 'event_type', [
            'label'   => __( 'Event Type Slug', 'club-events' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ] );

        $this->add_control( 'year', [
            'label'   => __( 'Year (0 = current)', 'club-events' ),
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 0,
            'min'     => 0,
            'max'     => 2099,
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        echo $this->build_shortcode( 'club_events_yearly', [
            'category', 'event_type', 'year',
        ] );
    }
}

/* ═══════════════════════════════════════════════════════════════════════ */
/*  Widget: Event Share Actions                                          */
/* ═══════════════════════════════════════════════════════════════════════ */
class CE_Elementor_Share extends \Elementor\Widget_Base {
    use CE_Elementor_Controls;

    public function get_name()       { return 'club-events-share'; }
    public function get_title()      { return __( 'Event Share Actions', 'club-events' ); }
    public function get_icon()       { return 'eicon-share'; }
    public function get_categories() { return [ 'club-events' ]; }
    public function get_keywords()   { return [ 'events', 'share', 'ics', 'calendar', 'club' ]; }

    protected function register_controls() {
        $this->start_controls_section( 'section_content', [
            'label' => __( 'Share Target', 'club-events' ),
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'url', [
            'label'       => __( 'URL', 'club-events' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => '',
            'description' => __( 'Leave empty to share the current event or page.', 'club-events' ),
        ] );

        $this->add_control( 'title', [
            'label'       => __( 'Title', 'club-events' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => '',
            'description' => __( 'Leave empty to use the current event or site title.', 'club-events' ),
        ] );

        $this->add_control( 'ics', [
            'label'       => __( 'ICS URL', 'club-events' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'default'     => '',
            'description' => __( 'Leave empty to use the current event\'s .ics file.', 'club-events' ),
        ] );

        $this->add_control( 'labels', [
            'label'   => __( 'Button Labels', 'club-events' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'true',
            'options' => [
                'true'  => __( 'Show labels', 'club-events' ),
                'false' => __( 'Icons only', 'club-events' ),
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        echo $this->build_shortcode( 'club_events_share', [
            'url', 'title', 'ics', 'labels',
        ] );
    }
}

/* ═══════════════════════════════════════════════════════════════════════ */
/*  Widget: Events Subscribe Form                                        */
/* ═══════════════════════════════════════════════════════════════════════ */
class CE_Elementor_Subscribe extends \Elementor\Widget_Base {

    public function get_name()       { return 'club-events-subscribe'; }
    public function get_title()      { return __( 'Events Subscribe Form', 'club-events' ); }
    public function get_icon()       { return 'eicon-email-field'; }
    public function get_categories() { return [ 'club-events' ]; }
    public function get_keywords()   { return [ 'events', 'subscribe', 'email', 'newsletter' ]; }

    protected function register_controls() {}

    protected function render() {
        echo do_shortcode( '[club_events_subscribe]' );
    }
}

/* ═══════════════════════════════════════════════════════════════════════ */
/*  Widget: Event Submit Form                                            */
/* ═══════════════════════════════════════════════════════════════════════ */
class CE_Elementor_Submit extends \Elementor\Widget_Base {

    public function get_name()       { return 'club-events-submit'; }
    public function get_title()      { return __( 'Event Submit Form', 'club-events' ); }
    public function get_icon()       { return 'eicon-form-horizontal'; }
    public function get_categories() { return [ 'club-events' ]; }
    public function get_keywords()   { return [ 'events', 'submit', 'form', 'self-service' ]; }

    protected function register_controls() {}

    protected function render() {
        echo do_shortcode( '[club_events_submit]' );
    }
}

/* ═══════════════════════════════════════════════════════════════════════ */
/*  Widget: My Events                                                    */
/* ═══════════════════════════════════════════════════════════════════════ */
class CE_Elementor_MyEvents extends \Elementor\Widget_Base {

    public function get_name()       { return 'club-events-my-events'; }
    public function get_title()      { return __( 'My Events', 'club-events' ); }
    public function get_icon()       { return 'eicon-table'; }
    public function get_categories() { return [ 'club-events' ]; }
    public function get_keywords()   { return [ 'events', 'my', 'submitted', 'user', 'self-service' ]; }

    protected function register_controls() {}

    protected function render() {
        echo do_shortcode( '[club_events_my_events]' );
    }
}
