<?php

namespace PersianKit\Components;

defined('ABSPATH') || exit;

/**
 * The settings page's icons and the Persian Kit mark. The markup here is
 * fixed, so templates print it as is.
 */
class Icon
{
    /** Line icons on a 24px grid, drawn in the text colour. */
    private const PATHS = [
        'display'      => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
        'writing'      => '<path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="M13.5 6.5l4 4"/>',
        'woocommerce'  => '<circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M2.5 3.5h2.6l2.4 11.5h11l2-8H6.3"/>',
        'integrations' => '<path d="M9 3v5M15 3v5M6 8h12v3a6 6 0 0 1-12 0V8zM12 17v4"/>',
        'tools'        => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'calendar'     => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'keyboard'     => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M8 14h8"/>',
        'link'         => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'cart'         => '<circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M2.5 3.5h2.6l2.4 11.5h11l2-8H6.3"/>',
        'form'         => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'fields'       => '<rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><path d="M6.5 7h5M6.5 17h8"/>',
        'search'       => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
        'globe'        => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18a14 14 0 0 1 0-18z"/>',
        'check'        => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'sparkle'      => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/>',
        'book'         => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2V5z"/><path d="M4 19a2 2 0 0 1 2-2h13"/>',
        'chat'         => '<path d="M4 5h16v11H9l-5 4V5z"/>',
        'alert'        => '<path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17h.01"/>',
        // The eight-point star of Persian tilework, filled.
        'khatam'       => '<path fill="currentColor" stroke="none" d="M12 2.1L14.9 5H19V9.1L21.9 12L19 14.9V19H14.9L12 21.9L9.1 19H5V14.9L2.1 12L5 9.1V5H9.1Z"/>',
    ];

    /** Persian letters that stand in for an icon. */
    private const GLYPHS = [
        'digits'  => '۱۲۳',
        'letters' => 'یک',
        'font'    => 'اب',
    ];

    /**
     * The پ tile in one colour, with the letter cut out. WordPress repaints
     * the fill to match the admin colour scheme. resources/images/logo.svg
     * is the same mark in the brand colours.
     */
    private const MENU_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="black" fill-rule="evenodd" d="'
        . 'M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5z'
        . 'M5.8 8.2V11.6C5.8 13.5 7.1 14.6 9 14.6H15.6C17.2 14.6 18.2 13.6 18.2 12.2V10.1H16.6V12C16.6 12.7 16.2 13 15.5 13H9.1C8 13 7.4 12.4 7.4 11.4V8.2Z'
        . 'M11.5 16.9a1.1 1.1 0 1 1-2.2 0a1.1 1.1 0 1 1 2.2 0Z'
        . 'M14.7 16.9a1.1 1.1 0 1 1-2.2 0a1.1 1.1 0 1 1 2.2 0Z'
        . 'M13.1 19.1a1.1 1.1 0 1 1-2.2 0a1.1 1.1 0 1 1 2.2 0Z'
        . '"/></svg>';

    /**
     * An icon or glyph by name, hidden from screen readers. Unknown names
     * give an empty string.
     */
    public static function render(string $name): string
    {
        if (isset(self::GLYPHS[$name])) {
            return '<span class="persian-kit-glyph" lang="fa" aria-hidden="true">' . self::GLYPHS[$name] . '</span>';
        }

        if (!isset(self::PATHS[$name])) {
            return '';
        }

        return '<svg class="persian-kit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . self::PATHS[$name] . '</svg>';
    }

    /** The admin menu icon, as add_menu_page() takes it. */
    public static function menuIcon(): string
    {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress wants SVG menu icons as base64 data URIs.
        return 'data:image/svg+xml;base64,' . base64_encode(self::MENU_SVG);
    }

    /** The brand-colour logo, built from resources/images/logo.svg. */
    public static function logoUrl(): string
    {
        return PERSIAN_KIT_URL . 'public/images/logo.svg';
    }
}
