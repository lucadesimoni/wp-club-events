<?php
/**
 * Branded e-mails for every message the plugin sends.
 *
 * One template for subscription confirmations, new-event notices and admin
 * notifications: site logo (or name), the theme's brand colour, a call-to-
 * action button, a details table and a footer with the reason for the
 * e-mail and an unsubscribe link. Every message is multipart: HTML with
 * inline styles (e-mail clients ignore <style> and CSS variables) plus a
 * plain-text alternative.
 *
 * Customise with the `ce_email_args` filter (content and colours) or
 * `ce_email_html` (the finished markup).
 */
defined( 'ABSPATH' ) || exit;

class CE_Mailer {

    /** Plain-text body for the message currently being sent. */
    private static $alt_body = '';

    /**
     * Send a branded e-mail.
     *
     * @param string|string[] $to          Recipient(s).
     * @param string          $subject     Subject line.
     * @param array           $args        See defaults().
     * @param string[]        $attachments Absolute file paths.
     */
    public static function send( $to, string $subject, array $args, array $attachments = [] ): bool {
        $args = wp_parse_args( $args, self::defaults() );

        /**
         * Filter the content and look of a Club Events e-mail.
         *
         * @param array           $args    Template arguments (see CE_Mailer::defaults()).
         * @param string|string[] $to      Recipient(s).
         * @param string          $subject Subject line.
         */
        $args = (array) apply_filters( 'ce_email_args', $args, $to, $subject );

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        if ( $args['use_from'] ) {
            $from = self::from_header();
            if ( $from ) {
                $headers[] = $from;
            }
        }
        if ( $args['unsubscribe_url'] ) {
            // RFC 2369 / RFC 8058: lets Gmail, Outlook and Apple Mail show
            // their own unsubscribe button; one-click unsubscribes by POST.
            $headers[] = 'List-Unsubscribe: <' . esc_url_raw( $args['unsubscribe_url'] ) . '>';
            $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
        }

        self::$alt_body = self::render_text( $args );
        add_action( 'phpmailer_init', [ __CLASS__, 'set_alt_body' ] );
        $sent = wp_mail( $to, $subject, self::render_html( $args ), $headers, $attachments );
        remove_action( 'phpmailer_init', [ __CLASS__, 'set_alt_body' ] );
        self::$alt_body = '';

        return (bool) $sent;
    }

    /** Template arguments and their defaults. */
    public static function defaults(): array {
        return [
            'preheader'       => '',  // Preview text shown next to the subject.
            'heading'         => '',
            'paragraphs'      => [],  // Plain text; escaped on output.
            'details'         => [],  // Label => value (string) or [ 'text' => …, 'url' => … ].
            'button'          => [],  // [ 'label' => …, 'url' => … ].
            'button_fallback' => false, // Print the button URL as text below it.
            'links'           => [],  // [ [ 'label' => …, 'url' => … ], … ] below the button.
            'note'            => '',  // Small print below the content.
            'footer'          => '',  // Why the recipient gets this e-mail.
            'unsubscribe_url' => '',
            'use_from'        => true, // Use the From name/address from the settings.
        ];
    }

    /** Hooked on phpmailer_init while one of our messages is sent. */
    public static function set_alt_body( $phpmailer ): void {
        if ( self::$alt_body && is_object( $phpmailer ) ) {
            $phpmailer->AltBody = self::$alt_body; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
        }
    }

    /**
     * "From:" header from the subscription settings, or '' to keep WordPress'
     * default. Line breaks and quotes are stripped so the header cannot be
     * split or broken by a settings value.
     */
    public static function from_header(): string {
        $email = sanitize_email( (string) get_option( 'ce_subscription_from_email', '' ) );
        if ( ! $email || ! is_email( $email ) ) {
            return '';
        }
        $name = (string) get_option( 'ce_subscription_from_name', get_bloginfo( 'name' ) );
        $name = trim( str_replace( [ "\r", "\n", '"', '<', '>' ], '', wp_specialchars_decode( $name, ENT_QUOTES ) ) );
        return $name ? sprintf( 'From: "%s" <%s>', $name, $email ) : 'From: ' . $email;
    }

    /* ── Branding ─────────────────────────────────────────────────────── */

    /**
     * Brand colours as hex values (e-mail clients do not resolve CSS
     * variables). Taken from Astra's global palette when available:
     * slot 0 = brand, slot 2 = headings.
     */
    public static function colors(): array {
        $colors = [
            'brand'   => '#2563eb',
            'heading' => '#0f172a',
            'text'    => '#334155',
            'muted'   => '#64748b',
            'border'  => '#e2e8f0',
            'surface' => '#f4f5f8',
        ];

        if ( function_exists( 'astra_get_option' ) ) {
            $palette = astra_get_option( 'global-color-palette' );
            $slots   = is_array( $palette ) && isset( $palette['palette'] ) ? (array) $palette['palette'] : [];
            foreach ( [ 'brand' => 0, 'heading' => 2, 'text' => 3 ] as $key => $slot ) {
                $hex = sanitize_hex_color( (string) ( $slots[ $slot ] ?? '' ) );
                if ( $hex ) {
                    $colors[ $key ] = $hex;
                }
            }
        }

        /**
         * Filter the e-mail colours (hex values).
         *
         * @param array $colors brand, heading, text, muted, border, surface.
         */
        $colors = (array) apply_filters( 'ce_email_colors', $colors );
        foreach ( $colors as $key => $value ) {
            $colors[ $key ] = sanitize_hex_color( (string) $value ) ?: '#334155';
        }
        return $colors;
    }

    /** URL of the site logo (Customizer "Site Identity"), or ''. */
    public static function logo_url(): string {
        $id  = (int) get_theme_mod( 'custom_logo' );
        $url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
        /**
         * Filter the logo shown at the top of e-mails ('' shows the site name).
         *
         * @param string $url Image URL.
         */
        return (string) apply_filters( 'ce_email_logo_url', (string) $url );
    }

    /* ── Rendering ────────────────────────────────────────────────────── */

    public static function render_html( array $args ): string {
        $args  = wp_parse_args( $args, self::defaults() );
        $c     = self::colors();
        $font  = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
        $site  = get_bloginfo( 'name' );
        $logo  = self::logo_url();
        $lang  = str_replace( '_', '-', get_locale() );
        $p     = 'margin:0 0 16px;font-family:' . $font . ';font-size:16px;line-height:1.6;color:' . $c['text'] . ';';
        $small = 'margin:0;font-family:' . $font . ';font-size:13px;line-height:1.6;color:' . $c['muted'] . ';';

        $brand = $logo
            ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $site ) . '" height="40" style="display:block;height:40px;width:auto;max-width:220px;border:0;">'
            : '<span style="font-family:' . $font . ';font-size:20px;font-weight:700;color:' . $c['heading'] . ';">' . esc_html( $site ) . '</span>';

        $body = '';
        if ( $args['heading'] ) {
            $body .= '<h1 style="margin:0 0 16px;font-family:' . $font . ';font-size:24px;line-height:1.3;font-weight:700;color:' . $c['heading'] . ';">' . esc_html( $args['heading'] ) . '</h1>';
        }
        foreach ( (array) $args['paragraphs'] as $paragraph ) {
            $body .= '<p style="' . $p . '">' . nl2br( esc_html( (string) $paragraph ) ) . '</p>';
        }

        if ( $args['details'] ) {
            $rows = '';
            foreach ( (array) $args['details'] as $label => $value ) {
                $text = is_array( $value ) ? (string) ( $value['text'] ?? '' ) : (string) $value;
                if ( '' === $text ) {
                    continue;
                }
                $cell = is_array( $value ) && ! empty( $value['url'] )
                    ? '<a href="' . esc_url( $value['url'] ) . '" style="color:' . $c['brand'] . ';text-decoration:underline;">' . esc_html( $text ) . '</a>'
                    : esc_html( $text );
                $rows .= '<tr>'
                    . '<td style="padding:10px 16px 10px 0;border-bottom:1px solid ' . $c['border'] . ';font-family:' . $font . ';font-size:14px;color:' . $c['muted'] . ';white-space:nowrap;vertical-align:top;">' . esc_html( (string) $label ) . '</td>'
                    . '<td style="padding:10px 0;border-bottom:1px solid ' . $c['border'] . ';font-family:' . $font . ';font-size:15px;font-weight:600;color:' . $c['heading'] . ';vertical-align:top;">' . $cell . '</td>'
                    . '</tr>';
            }
            if ( $rows ) {
                $body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 24px;">' . $rows . '</table>';
            }
        }

        if ( ! empty( $args['button']['url'] ) ) {
            $url   = esc_url( $args['button']['url'] );
            $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 24px;"><tr>'
                . '<td style="border-radius:8px;background:' . $c['brand'] . ';">'
                . '<a href="' . $url . '" style="display:inline-block;padding:14px 28px;font-family:' . $font . ';font-size:16px;font-weight:700;line-height:1;color:#ffffff;text-decoration:none;border-radius:8px;">'
                . esc_html( (string) ( $args['button']['label'] ?? '' ) ) . '</a></td></tr></table>';
            if ( $args['button_fallback'] ) {
                $body .= '<p style="' . $small . 'margin-bottom:24px;">'
                    . esc_html__( 'If the button does not work, copy this link into your browser:', 'club-events' )
                    . '<br><a href="' . $url . '" style="color:' . $c['brand'] . ';word-break:break-all;">' . esc_html( $args['button']['url'] ) . '</a></p>';
            }
        }

        if ( $args['links'] ) {
            $items = [];
            foreach ( (array) $args['links'] as $link ) {
                if ( empty( $link['url'] ) || empty( $link['label'] ) ) {
                    continue;
                }
                $items[] = '<a href="' . esc_url( $link['url'] ) . '" style="color:' . $c['brand'] . ';font-weight:600;text-decoration:underline;">' . esc_html( $link['label'] ) . '</a>';
            }
            if ( $items ) {
                $body .= '<p style="' . $p . 'font-size:15px;">' . implode( ' &nbsp;·&nbsp; ', $items ) . '</p>';
            }
        }

        if ( $args['note'] ) {
            $body .= '<p style="' . $small . '">' . nl2br( esc_html( $args['note'] ) ) . '</p>';
        }

        $footer = '<a href="' . esc_url( home_url( '/' ) ) . '" style="color:' . $c['muted'] . ';font-weight:600;text-decoration:none;">' . esc_html( $site ) . '</a>';
        if ( $args['footer'] ) {
            $footer .= '<br>' . esc_html( $args['footer'] );
        }
        if ( $args['unsubscribe_url'] ) {
            $footer .= '<br><a href="' . esc_url( $args['unsubscribe_url'] ) . '" style="color:' . $c['muted'] . ';text-decoration:underline;">' . esc_html__( 'Unsubscribe', 'club-events' ) . '</a>';
        }

        $preheader = $args['preheader']
            ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">' . esc_html( $args['preheader'] ) . '</div>'
            : '';

        $html = '<!DOCTYPE html><html lang="' . esc_attr( $lang ) . '"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="color-scheme" content="light"><title>' . esc_html( $args['heading'] ?: $site ) . '</title></head>'
            . '<body style="margin:0;padding:0;background:' . $c['surface'] . ';">' . $preheader
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $c['surface'] . ';"><tr><td align="center" style="padding:24px 12px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:8px;border-top:4px solid ' . $c['brand'] . ';">'
            . '<tr><td style="padding:28px 32px 8px;">' . $brand . '</td></tr>'
            . '<tr><td style="padding:16px 32px 16px;">' . $body . '</td></tr>'
            . '</table>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;"><tr><td style="padding:20px 32px;text-align:center;">'
            . '<p style="' . $small . 'font-size:12px;">' . $footer . '</p></td></tr></table>'
            . '</td></tr></table></body></html>';

        /**
         * Filter the finished HTML of a Club Events e-mail.
         *
         * @param string $html Markup.
         * @param array  $args Template arguments.
         */
        return (string) apply_filters( 'ce_email_html', $html, $args );
    }

    public static function render_text( array $args ): string {
        $args  = wp_parse_args( $args, self::defaults() );
        $lines = [];
        if ( $args['heading'] ) {
            $lines[] = $args['heading'];
            $lines[] = str_repeat( '=', min( 60, max( 3, mb_strlen( $args['heading'] ) ) ) );
            $lines[] = '';
        }
        foreach ( (array) $args['paragraphs'] as $paragraph ) {
            $lines[] = (string) $paragraph;
            $lines[] = '';
        }
        foreach ( (array) $args['details'] as $label => $value ) {
            $text = is_array( $value ) ? (string) ( $value['text'] ?? '' ) : (string) $value;
            if ( '' !== $text ) {
                $lines[] = $label . ': ' . $text;
            }
        }
        if ( $args['details'] ) {
            $lines[] = '';
        }
        if ( ! empty( $args['button']['url'] ) ) {
            $lines[] = ( $args['button']['label'] ?? '' ) . ': ' . $args['button']['url'];
            $lines[] = '';
        }
        foreach ( (array) $args['links'] as $link ) {
            if ( ! empty( $link['url'] ) && ! empty( $link['label'] ) ) {
                $lines[] = $link['label'] . ': ' . $link['url'];
            }
        }
        if ( $args['links'] ) {
            $lines[] = '';
        }
        if ( $args['note'] ) {
            $lines[] = $args['note'];
            $lines[] = '';
        }
        $lines[] = '--';
        $lines[] = get_bloginfo( 'name' ) . ' · ' . home_url( '/' );
        if ( $args['footer'] ) {
            $lines[] = $args['footer'];
        }
        if ( $args['unsubscribe_url'] ) {
            $lines[] = __( 'Unsubscribe', 'club-events' ) . ': ' . $args['unsubscribe_url'];
        }
        return wp_specialchars_decode( implode( "\n", $lines ), ENT_QUOTES );
    }
}
