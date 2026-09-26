<?php
defined( 'ABSPATH' ) || exit;

/**
 * Error containment: nothing this plugin renders or hooks into may take a
 * page down. Any error or exception inside a guarded call is caught, partial
 * output is discarded, the problem is reported, and a safe value is returned.
 */
final class CE_Safe {

    /**
     * Run a renderer that returns HTML. On failure: '' for visitors, a short
     * note for users who can edit.
     */
    public static function render( string $context, callable $callback, array $args = [] ): string {
        $level = ob_get_level();
        ob_start();
        try {
            $html = (string) call_user_func_array( $callback, $args );
            // Anything the renderer echoed instead of returning stays with it.
            return (string) ob_get_clean() . $html;
        } catch ( \Throwable $e ) {
            while ( ob_get_level() > $level ) {
                ob_end_clean();
            }
            self::report( $context, $e );
            if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
                return '<p class="ce-render-error">' . esc_html__( 'Club Events could not display this element. Details are in the PHP error log (with WP_DEBUG enabled).', 'club-events' ) . '</p>';
            }
            return '';
        }
    }

    /** A shortcode / block callback wrapped with render(). */
    public static function renderer( string $context, callable $callback ): \Closure {
        return static function ( ...$args ) use ( $context, $callback ) {
            return self::render( $context, $callback, $args );
        };
    }

    /**
     * A filter callback that returns the unfiltered value if it fails, so a
     * bug can never blank a theme setting, body class or title.
     */
    public static function filter( string $context, callable $callback ): \Closure {
        return static function ( ...$args ) use ( $context, $callback ) {
            try {
                return call_user_func_array( $callback, $args );
            } catch ( \Throwable $e ) {
                self::report( $context, $e );
                return $args[0] ?? null;
            }
        };
    }

    /** An action callback (including ones that print) that fails silently. */
    public static function action( string $context, callable $callback ): \Closure {
        return static function ( ...$args ) use ( $context, $callback ) {
            $level = ob_get_level();
            ob_start();
            try {
                call_user_func_array( $callback, $args );
                ob_end_flush();
            } catch ( \Throwable $e ) {
                while ( ob_get_level() > $level ) {
                    ob_end_clean();
                }
                self::report( $context, $e );
            }
        };
    }

    /**
     * Report a caught error: to the PHP error log when WP_DEBUG is on, and to
     * the `ce_render_error` action for monitoring plugins.
     */
    public static function report( string $context, \Throwable $e ): void {
        $message = sprintf( 'Club Events: %s failed: %s in %s:%d', $context, $e->getMessage(), $e->getFile(), $e->getLine() );
        if ( function_exists( 'wp_trigger_error' ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            wp_trigger_error( '', $message, E_USER_WARNING );
        }
        if ( function_exists( 'do_action' ) ) {
            do_action( 'ce_render_error', $context, $e );
        }
    }
}
