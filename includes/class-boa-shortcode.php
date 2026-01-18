<?php
/**
 * Shortcode Handler
 *
 * Renders exchange rates on the frontend using Iconify icons
 *
 * @package BoA_Exchange_Rates
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BoA_Shortcode {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('boa_exchange_rates', array($this, 'render_shortcode'));
    }

    /**
     * Render the shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        $options = get_option('boa_rates_options', array());

        // Default attributes
        $defaults = array(
            'currencies' => '',
            'mode' => isset($options['display_mode']) ? $options['display_mode'] : 'table',
            'show_icons' => isset($options['show_icons']) ? ($options['show_icons'] ? 'yes' : 'no') : 'yes',
            'show_date' => isset($options['show_date']) ? ($options['show_date'] ? 'yes' : 'no') : 'yes',
            'style' => isset($options['table_style']) ? $options['table_style'] : 'default',
            'decimals' => isset($options['decimal_places']) ? $options['decimal_places'] : 2,
            'icon_color' => isset($options['icon_color']) ? $options['icon_color'] : '#333333',
            'icon_size' => isset($options['icon_size']) ? $options['icon_size'] : 24,
            'icon_style' => isset($options['icon_style']) ? $options['icon_style'] : 'circle-flags',
        );

        $atts = shortcode_atts($defaults, $atts, 'boa_exchange_rates');

        // Get rates data
        $rates_data = isset($options['rates_data']) ? $options['rates_data'] : array();
        $last_boa_date = isset($options['last_boa_date']) ? $options['last_boa_date'] : '';
        $last_boa_time = isset($options['last_boa_time']) ? $options['last_boa_time'] : '';

        if (empty($rates_data)) {
            return '<p class="boa-no-rates">' . __('No exchange rates available. Please check back later.', 'boa-exchange-rates') . '</p>';
        }

        // Determine which currencies to display
        if (!empty($atts['currencies'])) {
            $selected = array_map('trim', explode(',', strtoupper($atts['currencies'])));
        } else {
            $selected = isset($options['selected_currencies'])
                ? $options['selected_currencies']
                : array('USD', 'EUR', 'GBP', 'CHF');
        }

        // Filter rates to selected currencies
        $filtered_rates = array();
        foreach ($selected as $code) {
            if (isset($rates_data[$code])) {
                $filtered_rates[$code] = $rates_data[$code];
            }
        }

        if (empty($filtered_rates)) {
            return '<p class="boa-no-rates">' . __('No rates available for selected currencies.', 'boa-exchange-rates') . '</p>';
        }

        // Parse boolean attributes
        $show_icons = ($atts['show_icons'] === 'yes' || $atts['show_icons'] === '1' || $atts['show_icons'] === true);
        $show_date = ($atts['show_date'] === 'yes' || $atts['show_date'] === '1' || $atts['show_date'] === true);
        $decimals = intval($atts['decimals']);
        $icon_color = sanitize_hex_color($atts['icon_color']) ?: '#333333';
        $icon_size = intval($atts['icon_size']) ?: 24;
        $icon_style = sanitize_text_field($atts['icon_style']) ?: 'circle-flags';

        // CSS variables for icon styling
        $css_vars = sprintf(
            '--boa-icon-color: %s; --boa-icon-size: %dpx;',
            esc_attr($icon_color),
            $icon_size
        );

        // Render based on mode
        $output = '<div class="boa-exchange-rates boa-mode-' . esc_attr($atts['mode']) . ' boa-style-' . esc_attr($atts['style']) . '" style="' . $css_vars . '">';

        switch ($atts['mode']) {
            case 'cards':
                $output .= $this->render_cards($filtered_rates, $show_icons, $decimals, $icon_color, $icon_size, $icon_style);
                break;
            case 'compact':
                $output .= $this->render_compact($filtered_rates, $show_icons, $decimals, $icon_color, $icon_size, $icon_style);
                break;
            case 'table':
            default:
                $output .= $this->render_table($filtered_rates, $show_icons, $decimals, $icon_color, $icon_size, $icon_style);
                break;
        }

        // Add last update info
        if ($show_date && ($last_boa_date || $last_boa_time)) {
            $output .= '<div class="boa-last-update">';
            $output .= '<span class="boa-update-label">' . __('Last updated:', 'boa-exchange-rates') . '</span> ';
            $output .= '<span class="boa-update-value">';
            if ($last_boa_date) {
                $output .= esc_html(date_i18n(get_option('date_format'), strtotime($last_boa_date)));
            }
            if ($last_boa_time) {
                $output .= ' ' . esc_html($last_boa_time);
            }
            $output .= '</span>';
            $output .= '</div>';
        }


        $output .= '</div>';

        return $output;
    }

    /**
     * Render table mode
     *
     * @param array $rates Rates data
     * @param bool $show_icons Show icons
     * @param int $decimals Decimal places
     * @param string $icon_color Icon color
     * @param int $icon_size Icon size
     * @param string $icon_style Icon style
     * @return string HTML
     */
    private function render_table($rates, $show_icons, $decimals, $icon_color, $icon_size, $icon_style) {
        $html = '<table class="boa-rates-table">';
        $html .= '<thead>';
        $html .= '<tr>';
        if ($show_icons) {
            $html .= '<th class="boa-col-icon">' . __('', 'boa-exchange-rates') . '</th>';
        }
        $html .= '<th class="boa-col-code">' . __('Currency', 'boa-exchange-rates') . '</th>';
        $html .= '<th class="boa-col-name">' . __('Name', 'boa-exchange-rates') . '</th>';
        $html .= '<th class="boa-col-rate">' . __('Rate (ALL)', 'boa-exchange-rates') . '</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        foreach ($rates as $code => $rate) {
            $html .= '<tr>';

            if ($show_icons) {
                $html .= '<td class="boa-col-icon">';
                $html .= $this->get_icon_html($rate['icon'], $rate['name'], $icon_color, $icon_size, $icon_style);
                $html .= '</td>';
            }

            $html .= '<td class="boa-col-code"><strong>' . esc_html($code) . '</strong></td>';
            $html .= '<td class="boa-col-name">' . esc_html($rate['name']);
            if ($rate['unit'] > 1) {
                $html .= ' <small>(' . sprintf(__('per %d units', 'boa-exchange-rates'), $rate['unit']) . ')</small>';
            }
            $html .= '</td>';
            $html .= '<td class="boa-col-rate">' . number_format($rate['rate'], $decimals, '.', ',') . '</td>';

            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Render cards mode
     *
     * @param array $rates Rates data
     * @param bool $show_icons Show icons
     * @param int $decimals Decimal places
     * @param string $icon_color Icon color
     * @param int $icon_size Icon size
     * @param string $icon_style Icon style
     * @return string HTML
     */
    private function render_cards($rates, $show_icons, $decimals, $icon_color, $icon_size, $icon_style) {
        $html = '<div class="boa-rates-cards">';

        foreach ($rates as $code => $rate) {
            $html .= '<div class="boa-rate-card">';

            if ($show_icons) {
                $html .= '<div class="boa-card-icon">';
                $html .= $this->get_icon_html($rate['icon'], $rate['name'], $icon_color, $icon_size * 1.5, $icon_style);
                $html .= '</div>';
            }

            $html .= '<div class="boa-card-code">' . esc_html($code) . '</div>';
            $html .= '<div class="boa-card-name">' . esc_html($rate['name']) . '</div>';
            $html .= '<div class="boa-card-rate">' . number_format($rate['rate'], $decimals, '.', ',') . ' ALL</div>';

            if ($rate['unit'] > 1) {
                $html .= '<div class="boa-card-unit">' . sprintf(__('per %d units', 'boa-exchange-rates'), $rate['unit']) . '</div>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render compact mode
     *
     * @param array $rates Rates data
     * @param bool $show_icons Show icons
     * @param int $decimals Decimal places
     * @param string $icon_color Icon color
     * @param int $icon_size Icon size
     * @param string $icon_style Icon style
     * @return string HTML
     */
    private function render_compact($rates, $show_icons, $decimals, $icon_color, $icon_size, $icon_style) {
        $html = '<ul class="boa-rates-compact">';

        foreach ($rates as $code => $rate) {
            $html .= '<li class="boa-rate-item">';

            if ($show_icons) {
                $html .= $this->get_icon_html($rate['icon'], $rate['name'], $icon_color, $icon_size * 0.75, $icon_style);
            }

            $html .= '<span class="boa-item-code">' . esc_html($code) . '</span>';
            $html .= '<span class="boa-item-rate">' . number_format($rate['rate'], $decimals, '.', ',') . '</span>';

            $html .= '</li>';
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Get icon HTML using Iconify
     *
     * @param string $icon Icon identifier (e.g., 'circle-flags:us')
     * @param string $alt Alt text
     * @param string $color Icon color
     * @param int $size Icon size
     * @param string $style Icon style preference
     * @return string HTML
     */
    private function get_icon_html($icon, $alt, $color, $size, $style) {
        // Transform icon based on style preference
        $final_icon = $this->transform_icon($icon, $style);

        // Build inline style
        $inline_style = sprintf('font-size: %dpx;', intval($size));
        if ($style === 'mono') {
            $inline_style .= ' color: ' . esc_attr($color) . ';';
        }

        // Use iconify-icon web component for reliable rendering
        return sprintf(
            '<iconify-icon class="boa-icon" icon="%s" width="%d" height="%d" title="%s" style="%s"></iconify-icon>',
            esc_attr($final_icon),
            intval($size),
            intval($size),
            esc_attr($alt),
            $inline_style
        );
    }

    /**
     * Transform icon identifier based on style preference
     *
     * @param string $icon Original icon (e.g., 'circle-flags:us')
     * @param string $style Target style
     * @return string Transformed icon identifier
     */
    private function transform_icon($icon, $style) {
        // Extract the country code from circle-flags:xx format
        $parts = explode(':', $icon);
        $icon_set = $parts[0];
        $icon_name = isset($parts[1]) ? $parts[1] : '';

        switch ($style) {
            case 'flag':
                // Use rectangular flags
                if ($icon_set === 'circle-flags') {
                    return 'flag:' . $icon_name . '-4x3';
                }
                return $icon;

            case 'mono':
                // Use monochrome icons
                if ($icon_set === 'circle-flags') {
                    // Use simple currency symbols or country outlines
                    return 'carbon:location-' . $icon_name;
                }
                return $icon;

            case 'circle-flags':
            default:
                return $icon;
        }
    }
}
