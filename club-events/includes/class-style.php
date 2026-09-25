<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shared styling helpers for the block, Elementor and Astra integrations.
 *
 * Every surface styles the plugin the same way: by overriding --ce-* custom
 * properties on a wrapper, so one colour choice re-tints buttons, links,
 * badges, focus rings and the filter bar together.
 */
class CE_Style {

    /**
     * Accept a CSS colour from an editor colour picker, or return ''.
     *
     * Theme palettes hand out CSS variables rather than hex values — Astra's
     * global palette is `var(--ast-global-color-0)`, Elementor's globals are
     * `var(--e-global-color-primary)` — so those are allowed alongside hex,
     * rgb() and hsl(). Picking a palette colour then keeps following the theme
     * when the palette is changed later.
     */
    public static function sanitize_color( $color ): string {
        $color = trim( (string) $color );
        if ( '' === $color ) {
            return '';
        }
        if ( preg_match( '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color ) ) {
            return $color;
        }
        if ( preg_match( '/^(?:rgb|hsl)a?\(\s*[0-9.,%\s\/deg]+\)$/i', $color ) ) {
            return $color;
        }
        if ( preg_match( '/^var\(\s*--[a-z0-9_-]+\s*\)$/i', $color ) ) {
            return $color;
        }
        return '';
    }

    /**
     * Custom-property declarations that re-tint a component with one accent.
     *
     * The derived shades use color-mix() so they also work when the accent is
     * a palette variable whose value is only known in the browser.
     */
    public static function accent_declarations( string $color ): string {
        $color = self::sanitize_color( $color );
        return '' === $color ? '' : self::accent_template( $color );
    }

    /**
     * The same declarations for an unvalidated placeholder, such as
     * Elementor's `{{VALUE}}` (Elementor validates the colour itself).
     */
    public static function accent_template( string $color ): string {
        $dark  = 'color-mix(in srgb, ' . $color . ' 82%, #000)';
        $light = 'color-mix(in srgb, ' . $color . ' 10%, #fff)';

        return '--ce-primary:' . $color . ';'
            . '--ce-primary-dk:' . $dark . ';'
            . '--ce-primary-lt:' . $light . ';'
            . '--ce-accent:' . $color . ';'
            . '--ce-link:' . $color . ';'
            . '--ce-link-hover:' . $dark . ';'
            . '--ce-btn-bg:' . $color . ';'
            . '--ce-btn-bg-hover:' . $dark . ';'
            . '--ce-input-focus:' . $color . ';';
    }
}
