<?php
defined( 'ABSPATH' ) || exit;

/**
 * Ready-made block patterns for event pages.
 *
 * Built from core blocks only (group, heading, paragraph, buttons), so they
 * work in plain Gutenberg, inside Spectra containers and with any theme; the
 * headings and buttons pick up Astra's typography and palette on their own.
 */
class CE_Patterns {

    public function __construct() {
        add_action( 'init', [ $this, 'register' ], 20 );
    }

    public function register(): void {
        if ( ! function_exists( 'register_block_pattern' ) ) {
            return;
        }

        register_block_pattern_category( 'club-events', [
            'label' => __( 'Club Events', 'club-events' ),
        ] );

        $archive = esc_url( CE_CPT::archive_url() );

        register_block_pattern( 'club-events/events-page', [
            'title'       => __( 'Events page', 'club-events' ),
            'description' => __( 'Heading, intro and the complete Events Hub with search, views and calendar subscription.', 'club-events' ),
            'categories'  => [ 'club-events' ],
            'keywords'    => [ 'events', 'calendar', 'agenda' ],
            'content'     => '<!-- wp:group {"align":"wide","layout":{"type":"constrained","wideSize":"1200px"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">' . esc_html__( 'Events', 'club-events' ) . '</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'Everything coming up at the club — search, filter by category and add events straight to your calendar.', 'club-events' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:club-events/hub {"align":"wide"} /--></div>
<!-- /wp:group -->',
        ] );

        register_block_pattern( 'club-events/upcoming-teaser', [
            'title'       => __( 'Upcoming events teaser', 'club-events' ),
            'description' => __( 'Three event tiles with a heading and a link to all events — for the home page.', 'club-events' ),
            'categories'  => [ 'club-events' ],
            'keywords'    => [ 'events', 'upcoming', 'home', 'tiles' ],
            'content'     => '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html__( 'Upcoming events', 'club-events' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:club-events/tiles {"limit":3,"columns":3,"align":"wide"} /-->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . $archive . '">' . esc_html__( 'All events', 'club-events' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
        ] );

        register_block_pattern( 'club-events/calendar-subscribe', [
            'title'       => __( 'Calendar with subscribe form', 'club-events' ),
            'description' => __( 'Monthly calendar next to the email subscription form.', 'club-events' ),
            'categories'  => [ 'club-events' ],
            'keywords'    => [ 'events', 'calendar', 'subscribe', 'newsletter' ],
            'content'     => '<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"width":"66.66%"} -->
<div class="wp-block-column" style="flex-basis:66.66%"><!-- wp:club-events/overview /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"33.33%"} -->
<div class="wp-block-column" style="flex-basis:33.33%"><!-- wp:club-events/subscribe /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->',
        ] );
    }
}
