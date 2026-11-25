<?php
/**
 * Data access for other tools: REST meta fields and WordPress abilities.
 *
 * Everything an event consists of can be read and written with WordPress'
 * own interfaces, so the block editor, the WordPress apps, WP-CLI, MCP
 * adapters, AI assistants and site-management tools all work on events
 * without plugin-specific code:
 *
 * - **REST API**: the event fields (`_ce_start_date`, `_ce_end_date`,
 *   `_ce_all_day`, `_ce_location`, `_ce_location_url`, `_ce_external_url`,
 *   `_ce_color`) are registered meta and appear under `meta` in
 *   `/wp/v2/club_event`. Event type colours (`_ce_color`) are term meta of
 *   `event_type`. Writes need the same capability as the edit screen.
 * - **Abilities API** (WordPress 6.9+): `club-events/list-events`,
 *   `get-event`, `create-event`, `update-event`, `delete-event` and
 *   `list-terms`, with JSON schemas, so MCP adapters and AI tools can
 *   discover and call them.
 *
 * All paths share save_event(), which normalises dates like the edit screen
 * does (CE_CPT::normalize_date()).
 */
defined( 'ABSPATH' ) || exit;

class CE_Data_API {

    const ABILITY_CATEGORY = 'club-events';

    public function __construct() {
        add_action( 'init', CE_Safe::action( 'ce_register_meta', [ __CLASS__, 'register_meta' ] ), 20 );
        add_action( 'wp_abilities_api_categories_init', CE_Safe::action( 'ce_ability_category', [ __CLASS__, 'register_ability_category' ] ) );
        add_action( 'wp_abilities_api_init', CE_Safe::action( 'ce_abilities', [ __CLASS__, 'register_abilities' ] ) );
    }

    /* ── Meta ─────────────────────────────────────────────────────────── */

    /**
     * Event field definitions: meta key => [ JSON type, format, description ].
     */
    public static function fields(): array {
        return [
            '_ce_start_date'   => [ 'string', null, __( 'Start, site-local time (Y-m-d H:i:s). ISO 8601 with offset and plain dates are accepted.', 'club-events' ) ],
            '_ce_end_date'     => [ 'string', null, __( 'End, site-local time (Y-m-d H:i:s). A plain date means the end of that day.', 'club-events' ) ],
            '_ce_all_day'      => [ 'boolean', null, __( 'All-day event.', 'club-events' ) ],
            '_ce_location'     => [ 'string', null, __( 'Location name.', 'club-events' ) ],
            '_ce_location_url' => [ 'string', 'uri', __( 'Map or venue link.', 'club-events' ) ],
            '_ce_external_url' => [ 'string', 'uri', __( 'External link (registration, details).', 'club-events' ) ],
            '_ce_color'        => [ 'string', null, __( 'Event colour (hex). Empty uses the event type colour.', 'club-events' ) ],
        ];
    }

    /** Sanitise one event field the same way for every write path. */
    public static function sanitize_field( string $key, $value ) {
        switch ( $key ) {
            case '_ce_start_date':
                return CE_CPT::normalize_date( $value );
            case '_ce_end_date':
                return CE_CPT::normalize_date( $value, true );
            case '_ce_all_day':
                return rest_sanitize_boolean( $value ) ? '1' : '0';
            case '_ce_location_url':
            case '_ce_external_url':
                return esc_url_raw( (string) $value );
            case '_ce_color':
                return (string) sanitize_hex_color( (string) $value );
            default:
                return sanitize_text_field( (string) $value );
        }
    }

    public static function register_meta(): void {
        foreach ( self::fields() as $key => list( $type, $format, $description ) ) {
            $schema = [ 'type' => $type, 'description' => $description ];
            if ( $format ) {
                $schema['format'] = $format;
            }
            register_post_meta( 'club_event', $key, [
                'type'              => $type,
                'single'            => true,
                'description'       => $description,
                'default'           => 'boolean' === $type ? false : '',
                'show_in_rest'      => [ 'schema' => $schema ],
                'sanitize_callback' => static function ( $value ) use ( $key ) {
                    return self::sanitize_field( $key, $value );
                },
                'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
                    return current_user_can( 'edit_post', $post_id );
                },
            ] );
        }

        register_term_meta( 'event_type', '_ce_color', [
            'type'              => 'string',
            'single'            => true,
            'description'       => __( 'Colour of events of this type (hex).', 'club-events' ),
            'show_in_rest'      => true,
            'sanitize_callback' => static function ( $value ) {
                return (string) sanitize_hex_color( (string) $value );
            },
            'auth_callback'     => static function ( $allowed, $meta_key, $term_id ) {
                return current_user_can( 'edit_term', $term_id );
            },
        ] );
    }

    /* ── Shared write logic ───────────────────────────────────────────── */

    /** Input keys of create/update => meta key. */
    private static function input_map(): array {
        return [
            'start'        => '_ce_start_date',
            'end'          => '_ce_end_date',
            'all_day'      => '_ce_all_day',
            'location'     => '_ce_location',
            'location_url' => '_ce_location_url',
            'external_url' => '_ce_external_url',
            'color'        => '_ce_color',
        ];
    }

    /**
     * Create ($post_id = 0) or update an event. Only keys present in $input
     * are changed. Returns the formatted event or a WP_Error.
     */
    public static function save_event( array $input, int $post_id = 0 ) {
        if ( $post_id && 'club_event' !== get_post_type( $post_id ) ) {
            return new WP_Error( 'ce_not_found', __( 'Event not found.', 'club-events' ), [ 'status' => 404 ] );
        }

        $postarr = [ 'post_type' => 'club_event' ];
        foreach ( [ 'title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'status' => 'post_status' ] as $in => $field ) {
            if ( array_key_exists( $in, $input ) ) {
                $postarr[ $field ] = (string) $input[ $in ];
            }
        }
        if ( isset( $postarr['post_status'] ) && ! in_array( $postarr['post_status'], [ 'publish', 'draft', 'pending', 'private', 'future' ], true ) ) {
            return new WP_Error( 'ce_invalid_status', __( 'Invalid status.', 'club-events' ), [ 'status' => 400 ] );
        }

        if ( $post_id ) {
            $postarr['ID'] = $post_id;
            $result        = count( $postarr ) > 2 ? wp_update_post( wp_slash( $postarr ), true ) : $post_id;
        } else {
            if ( empty( $postarr['post_title'] ) || empty( $input['start'] ) ) {
                return new WP_Error( 'ce_missing_fields', __( 'An event needs a title and a start date.', 'club-events' ), [ 'status' => 400 ] );
            }
            $postarr['post_status'] = $postarr['post_status'] ?? 'draft';
            $result                 = wp_insert_post( wp_slash( $postarr ), true );
        }
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        $post_id = (int) $result;

        foreach ( self::input_map() as $in => $meta_key ) {
            if ( ! array_key_exists( $in, $input ) ) {
                continue;
            }
            $value = self::sanitize_field( $meta_key, $input[ $in ] );
            if ( '_ce_start_date' === $meta_key && '' === $value ) {
                return new WP_Error( 'ce_invalid_date', __( 'The start date could not be read.', 'club-events' ), [ 'status' => 400 ] );
            }
            update_post_meta( $post_id, $meta_key, $value );
        }

        $taxonomies = [ 'categories' => 'event_category', 'types' => 'event_type', 'tags' => 'event_tag' ];
        foreach ( $taxonomies as $in => $taxonomy ) {
            if ( array_key_exists( $in, $input ) ) {
                $terms = self::resolve_terms( (array) $input[ $in ], $taxonomy );
                if ( is_wp_error( $terms ) ) {
                    return $terms;
                }
                wp_set_object_terms( $post_id, $terms, $taxonomy );
            }
        }

        if ( ! get_post_meta( $post_id, '_ce_source', true ) ) {
            update_post_meta( $post_id, '_ce_source', 'manual' );
        }

        return self::event_output( $post_id );
    }

    /**
     * Term IDs from IDs, slugs or names. Unknown names create a term when
     * the user may do so (the same rule as the editor's term picker).
     */
    private static function resolve_terms( array $values, string $taxonomy ) {
        $ids = [];
        foreach ( $values as $value ) {
            if ( is_numeric( $value ) ) {
                $term = get_term( (int) $value, $taxonomy );
            } else {
                $value = sanitize_text_field( (string) $value );
                $term  = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
                $term  = $term ? $term : get_term_by( 'name', $value, $taxonomy );
            }
            if ( $term && ! is_wp_error( $term ) ) {
                $ids[] = (int) $term->term_id;
                continue;
            }
            $tax = get_taxonomy( $taxonomy );
            if ( is_numeric( $value ) || ! $tax || ! current_user_can( $tax->cap->manage_terms ) ) {
                /* translators: %s: term name or ID. */
                return new WP_Error( 'ce_unknown_term', sprintf( __( 'Unknown term: %s', 'club-events' ), $value ), [ 'status' => 400 ] );
            }
            $created = wp_insert_term( $value, $taxonomy );
            if ( is_wp_error( $created ) ) {
                return $created;
            }
            $ids[] = (int) $created['term_id'];
        }
        return $ids;
    }

    /** Formatted event plus status and edit link, for tool responses. */
    public static function event_output( int $post_id ): array {
        $event              = CE_CPT::format_event( $post_id );
        $event['status']    = get_post_status( $post_id );
        $event['content']   = (string) get_post_field( 'post_content', $post_id, 'raw' );
        $event['editUrl']   = (string) get_edit_post_link( $post_id, 'raw' );
        $event['source']    = (string) get_post_meta( $post_id, '_ce_source', true );
        return $event;
    }

    /* ── Abilities API (WordPress 6.9+) ───────────────────────────────── */

    public static function register_ability_category(): void {
        if ( ! function_exists( 'wp_register_ability_category' ) ) {
            return;
        }
        wp_register_ability_category( self::ABILITY_CATEGORY, [
            'label'       => __( 'Club Events', 'club-events' ),
            'description' => __( 'Read and manage club events, categories and event types.', 'club-events' ),
        ] );
    }

    /** JSON schema of the editable event fields. */
    private static function event_input_schema( bool $require_id ): array {
        $props = [
            'title'        => [ 'type' => 'string', 'description' => __( 'Event title.', 'club-events' ) ],
            'content'      => [ 'type' => 'string', 'description' => __( 'Description (HTML or blocks).', 'club-events' ) ],
            'excerpt'      => [ 'type' => 'string', 'description' => __( 'Short summary.', 'club-events' ) ],
            'status'       => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'private', 'future' ], 'description' => __( 'Post status. New events default to draft.', 'club-events' ) ],
            'start'        => [ 'type' => 'string', 'description' => __( 'Start: "2026-06-26", "2026-06-26 19:30" (site time) or ISO 8601 with offset.', 'club-events' ) ],
            'end'          => [ 'type' => 'string', 'description' => __( 'End, same formats. A plain date means the end of that day. Empty removes it.', 'club-events' ) ],
            'all_day'      => [ 'type' => 'boolean' ],
            'location'     => [ 'type' => 'string' ],
            'location_url' => [ 'type' => 'string', 'description' => __( 'Map or venue link.', 'club-events' ) ],
            'external_url' => [ 'type' => 'string', 'description' => __( 'Registration or details link.', 'club-events' ) ],
            'color'        => [ 'type' => 'string', 'description' => __( 'Hex colour; empty uses the event type colour.', 'club-events' ) ],
            'categories'   => [ 'type' => 'array', 'items' => [ 'type' => [ 'integer', 'string' ] ], 'description' => __( 'Event categories (IDs, slugs or names). Replaces the current ones.', 'club-events' ) ],
            'types'        => [ 'type' => 'array', 'items' => [ 'type' => [ 'integer', 'string' ] ], 'description' => __( 'Event types (IDs, slugs or names). Replaces the current ones.', 'club-events' ) ],
            'tags'         => [ 'type' => 'array', 'items' => [ 'type' => [ 'integer', 'string' ] ] ],
        ];
        if ( $require_id ) {
            $props = [ 'id' => [ 'type' => 'integer', 'description' => __( 'Event ID.', 'club-events' ) ] ] + $props;
        }
        return [
            'type'                 => 'object',
            'properties'           => $props,
            'required'             => $require_id ? [ 'id' ] : [ 'title', 'start' ],
            'additionalProperties' => false,
        ];
    }

    private static function event_output_schema(): array {
        return [
            'type'       => 'object',
            'properties' => [
                'id'          => [ 'type' => 'integer' ],
                'title'       => [ 'type' => 'string' ],
                'status'      => [ 'type' => 'string' ],
                'start'       => [ 'type' => 'string' ],
                'end'         => [ 'type' => 'string' ],
                'allDay'      => [ 'type' => 'boolean' ],
                'location'    => [ 'type' => 'string' ],
                'url'         => [ 'type' => 'string' ],
                'editUrl'     => [ 'type' => 'string' ],
                'categories'  => [ 'type' => 'array' ],
                'types'       => [ 'type' => 'array' ],
            ],
        ];
    }

    private static function meta( bool $readonly, bool $destructive, bool $idempotent ): array {
        return [
            'annotations'  => [
                'readonly'    => $readonly,
                'destructive' => $destructive,
                'idempotent'  => $idempotent,
            ],
            'show_in_rest' => true,
            'mcp'          => [ 'public' => true ],
        ];
    }

    public static function register_abilities(): void {
        if ( ! function_exists( 'wp_register_ability' ) ) {
            return;
        }
        $cat = self::ABILITY_CATEGORY;

        wp_register_ability( 'club-events/list-events', [
            'label'               => __( 'List events', 'club-events' ),
            'description'         => __( 'List club events, by default upcoming ones (still running or in the future), sorted by start date. Filter by date range, category or event type.', 'club-events' ),
            'category'            => $cat,
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'upcoming' => [ 'type' => 'boolean', 'default' => true, 'description' => __( 'Only events that have not ended. Ignored when from/to is given.', 'club-events' ) ],
                    'from'     => [ 'type' => 'string', 'description' => __( 'Start of range (site time, e.g. 2026-01-01).', 'club-events' ) ],
                    'to'       => [ 'type' => 'string', 'description' => __( 'End of range (site time).', 'club-events' ) ],
                    'category' => [ 'type' => 'string', 'description' => __( 'Event category slug.', 'club-events' ) ],
                    'type'     => [ 'type' => 'string', 'description' => __( 'Event type slug.', 'club-events' ) ],
                    'status'   => [ 'type' => 'string', 'enum' => [ 'publish', 'draft', 'pending', 'private', 'future', 'any' ], 'default' => 'publish' ],
                    'limit'    => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ],
                ],
                'additionalProperties' => false,
                'default'              => [],
            ],
            'output_schema'       => [ 'type' => 'array', 'items' => self::event_output_schema() ],
            'execute_callback'    => [ __CLASS__, 'ability_list_events' ],
            'permission_callback' => static function ( $input = [] ) {
                $status = is_array( $input ) ? ( $input['status'] ?? 'publish' ) : 'publish';
                return 'publish' === $status || current_user_can( 'edit_posts' );
            },
            'meta'                => self::meta( true, false, true ),
        ] );

        wp_register_ability( 'club-events/get-event', [
            'label'               => __( 'Get event', 'club-events' ),
            'description'         => __( 'Get one club event with all fields, including its description and edit link.', 'club-events' ),
            'category'            => $cat,
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [ 'id' => [ 'type' => 'integer' ] ],
                'required'             => [ 'id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => self::event_output_schema(),
            'execute_callback'    => static function ( $input ) {
                $id = (int) $input['id'];
                if ( 'club_event' !== get_post_type( $id ) ) {
                    return new WP_Error( 'ce_not_found', __( 'Event not found.', 'club-events' ) );
                }
                return self::event_output( $id );
            },
            'permission_callback' => static function ( $input = [] ) {
                $id = (int) ( $input['id'] ?? 0 );
                return 'publish' === get_post_status( $id ) || current_user_can( 'read_post', $id );
            },
            'meta'                => self::meta( true, false, true ),
        ] );

        wp_register_ability( 'club-events/create-event', [
            'label'               => __( 'Create event', 'club-events' ),
            'description'         => __( 'Create a club event. Needs a title and a start; saved as draft unless status is given.', 'club-events' ),
            'category'            => $cat,
            'input_schema'        => self::event_input_schema( false ),
            'output_schema'       => self::event_output_schema(),
            'execute_callback'    => static function ( $input ) {
                return self::save_event( (array) $input );
            },
            'permission_callback' => static function ( $input = [] ) {
                $status = is_array( $input ) ? ( $input['status'] ?? 'draft' ) : 'draft';
                $type   = get_post_type_object( 'club_event' );
                if ( ! $type || ! current_user_can( $type->cap->create_posts ) ) {
                    return false;
                }
                return in_array( $status, [ 'draft', 'pending' ], true ) || current_user_can( $type->cap->publish_posts );
            },
            'meta'                => self::meta( false, false, false ),
        ] );

        wp_register_ability( 'club-events/update-event', [
            'label'               => __( 'Update event', 'club-events' ),
            'description'         => __( 'Change fields of a club event. Only the fields given are changed.', 'club-events' ),
            'category'            => $cat,
            'input_schema'        => self::event_input_schema( true ),
            'output_schema'       => self::event_output_schema(),
            'execute_callback'    => static function ( $input ) {
                $input = (array) $input;
                $id    = (int) $input['id'];
                unset( $input['id'] );
                return self::save_event( $input, $id );
            },
            'permission_callback' => static function ( $input = [] ) {
                $id = (int) ( $input['id'] ?? 0 );
                if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
                    return false;
                }
                $type = get_post_type_object( 'club_event' );
                return empty( $input['status'] ) || in_array( $input['status'], [ 'draft', 'pending' ], true ) || current_user_can( $type->cap->publish_posts );
            },
            'meta'                => self::meta( false, false, true ),
        ] );

        wp_register_ability( 'club-events/delete-event', [
            'label'               => __( 'Delete event', 'club-events' ),
            'description'         => __( 'Move a club event to the trash (restorable), or delete it permanently with force.', 'club-events' ),
            'category'            => $cat,
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'id'    => [ 'type' => 'integer' ],
                    'force' => [ 'type' => 'boolean', 'default' => false, 'description' => __( 'Delete permanently instead of moving to the trash.', 'club-events' ) ],
                ],
                'required'             => [ 'id' ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [ 'id' => [ 'type' => 'integer' ], 'deleted' => [ 'type' => 'boolean' ], 'trashed' => [ 'type' => 'boolean' ] ],
            ],
            'execute_callback'    => static function ( $input ) {
                $id = (int) $input['id'];
                if ( 'club_event' !== get_post_type( $id ) ) {
                    return new WP_Error( 'ce_not_found', __( 'Event not found.', 'club-events' ) );
                }
                $force  = ! empty( $input['force'] );
                $result = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
                return [ 'id' => $id, 'deleted' => (bool) $result, 'trashed' => (bool) $result && ! $force ];
            },
            'permission_callback' => static function ( $input = [] ) {
                return current_user_can( 'delete_post', (int) ( $input['id'] ?? 0 ) );
            },
            'meta'                => self::meta( false, true, true ),
        ] );

        wp_register_ability( 'club-events/list-terms', [
            'label'               => __( 'List event categories and types', 'club-events' ),
            'description'         => __( 'List the terms of an event taxonomy: event_category, event_type (with colour) or event_tag.', 'club-events' ),
            'category'            => $cat,
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'taxonomy' => [ 'type' => 'string', 'enum' => [ 'event_category', 'event_type', 'event_tag' ], 'default' => 'event_category' ],
                ],
                'additionalProperties' => false,
                'default'              => [],
            ],
            'output_schema'       => [ 'type' => 'array' ],
            'execute_callback'    => static function ( $input = [] ) {
                $taxonomy = $input['taxonomy'] ?? 'event_category';
                $terms    = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
                if ( is_wp_error( $terms ) ) {
                    return $terms;
                }
                return array_map( static function ( $t ) use ( $taxonomy ) {
                    $row = [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug, 'count' => $t->count, 'parent' => $t->parent ];
                    if ( 'event_type' === $taxonomy ) {
                        $row['color'] = (string) get_term_meta( $t->term_id, '_ce_color', true );
                    }
                    return $row;
                }, $terms );
            },
            'permission_callback' => '__return_true',
            'meta'                => self::meta( true, false, true ),
        ] );
    }

    /** Execute callback of club-events/list-events. */
    public static function ability_list_events( $input = [] ) {
        $input = is_array( $input ) ? $input : [];
        $args  = [
            'posts_per_page' => (int) ( $input['limit'] ?? 50 ),
            'post_status'    => $input['status'] ?? 'publish',
        ];
        if ( ! empty( $input['from'] ) || ! empty( $input['to'] ) ) {
            if ( ! empty( $input['from'] ) ) {
                $args['from'] = CE_CPT::normalize_date( $input['from'] );
            }
            if ( ! empty( $input['to'] ) ) {
                $args['to'] = CE_CPT::normalize_date( $input['to'], true );
            }
        } elseif ( $input['upcoming'] ?? true ) {
            $args['upcoming'] = true;
        }
        if ( ! empty( $input['category'] ) ) {
            $args['tax_query'] = [ [ 'taxonomy' => 'event_category', 'field' => 'slug', 'terms' => sanitize_title( $input['category'] ) ] ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
        }
        if ( ! empty( $input['type'] ) ) {
            $args['event_type'] = sanitize_title( $input['type'] );
        }
        $posts = CE_CPT::get_events( $args );
        return array_map( static function ( $p ) {
            return self::event_output( (int) $p->ID );
        }, $posts );
    }
}
