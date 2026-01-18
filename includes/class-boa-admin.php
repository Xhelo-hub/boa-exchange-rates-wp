<?php
/**
 * Admin Settings Page
 *
 * Manages the plugin settings and admin interface
 *
 * @package BoA_Exchange_Rates
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BoA_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_boa_refresh_rates', array($this, 'ajax_refresh_rates'));
        add_action('wp_ajax_boa_get_preview', array($this, 'ajax_get_preview'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('BoA Exchange Rates', 'boa-exchange-rates'),
            __('BoA Rates', 'boa-exchange-rates'),
            'manage_options',
            'boa-exchange-rates',
            array($this, 'render_settings_page'),
            'dashicons-money-alt',
            30
        );

        add_submenu_page(
            'boa-exchange-rates',
            __('Settings', 'boa-exchange-rates'),
            __('Settings', 'boa-exchange-rates'),
            'manage_options',
            'boa-exchange-rates',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('boa_rates_options_group', 'boa_rates_options', array(
            'sanitize_callback' => array($this, 'sanitize_options'),
        ));
    }

    /**
     * Sanitize options
     *
     * @param array $input Input options
     * @return array Sanitized options
     */
    public function sanitize_options($input) {
        $sanitized = array();

        // Preserve existing data
        $existing = get_option('boa_rates_options', array());

        // Selected currencies
        if (isset($input['selected_currencies']) && is_array($input['selected_currencies'])) {
            $sanitized['selected_currencies'] = array_map('sanitize_text_field', $input['selected_currencies']);
        } else {
            $sanitized['selected_currencies'] = isset($existing['selected_currencies'])
                ? $existing['selected_currencies']
                : array('USD', 'EUR', 'GBP', 'CHF');
        }

        // Display mode
        $sanitized['display_mode'] = isset($input['display_mode'])
            ? sanitize_text_field($input['display_mode'])
            : 'table';

        // Show icons
        $sanitized['show_icons'] = isset($input['show_icons']) ? 1 : 0;

        // Show date
        $sanitized['show_date'] = isset($input['show_date']) ? 1 : 0;

        // Table style
        $sanitized['table_style'] = isset($input['table_style'])
            ? sanitize_text_field($input['table_style'])
            : 'default';

        // Decimal places
        $sanitized['decimal_places'] = isset($input['decimal_places'])
            ? absint($input['decimal_places'])
            : 2;

        // Icon settings
        $sanitized['icon_color'] = isset($input['icon_color'])
            ? sanitize_hex_color($input['icon_color'])
            : '#333333';

        $sanitized['icon_size'] = isset($input['icon_size'])
            ? absint($input['icon_size'])
            : 24;

        $sanitized['icon_style'] = isset($input['icon_style'])
            ? sanitize_text_field($input['icon_style'])
            : 'circle-flags';

        // Preserve rate data
        $sanitized['rates_data'] = isset($existing['rates_data']) ? $existing['rates_data'] : array();
        $sanitized['last_update'] = isset($existing['last_update']) ? $existing['last_update'] : '';
        $sanitized['last_boa_date'] = isset($existing['last_boa_date']) ? $existing['last_boa_date'] : '';
        $sanitized['last_boa_time'] = isset($existing['last_boa_time']) ? $existing['last_boa_time'] : '';

        return $sanitized;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        $options = get_option('boa_rates_options', array());
        $scraper = new BoA_Scraper();
        $all_currencies = $scraper->get_supported_currencies();

        $selected_currencies = isset($options['selected_currencies'])
            ? $options['selected_currencies']
            : array('USD', 'EUR', 'GBP', 'CHF');

        $display_mode = isset($options['display_mode']) ? $options['display_mode'] : 'table';
        $show_icons = isset($options['show_icons']) ? $options['show_icons'] : 1;
        $show_date = isset($options['show_date']) ? $options['show_date'] : 1;
        $table_style = isset($options['table_style']) ? $options['table_style'] : 'default';
        $decimal_places = isset($options['decimal_places']) ? $options['decimal_places'] : 2;
        $icon_color = isset($options['icon_color']) ? $options['icon_color'] : '#333333';
        $icon_size = isset($options['icon_size']) ? $options['icon_size'] : 24;
        $icon_style = isset($options['icon_style']) ? $options['icon_style'] : 'circle-flags';
        $rates_data = isset($options['rates_data']) ? $options['rates_data'] : array();
        $last_update = isset($options['last_update']) ? $options['last_update'] : '';
        $last_boa_date = isset($options['last_boa_date']) ? $options['last_boa_date'] : '';
        $last_boa_time = isset($options['last_boa_time']) ? $options['last_boa_time'] : '';
        ?>
        <div class="wrap boa-rates-admin">
            <h1><?php _e('BoA Exchange Rates Settings', 'boa-exchange-rates'); ?></h1>

            <!-- Status Box -->
            <div class="boa-status-box">
                <h2><?php _e('Current Status', 'boa-exchange-rates'); ?></h2>
                <div class="boa-status-grid">
                    <div class="boa-status-item">
                        <span class="label"><?php _e('Last BoA Update:', 'boa-exchange-rates'); ?></span>
                        <span class="value" id="boa-last-update">
                            <?php
                            if ($last_boa_date && $last_boa_time) {
                                echo esc_html($last_boa_date . ' ' . $last_boa_time);
                            } else {
                                _e('Never', 'boa-exchange-rates');
                            }
                            ?>
                        </span>
                    </div>
                    <div class="boa-status-item">
                        <span class="label"><?php _e('Last Fetched:', 'boa-exchange-rates'); ?></span>
                        <span class="value" id="boa-last-fetched">
                            <?php echo $last_update ? esc_html($last_update) : __('Never', 'boa-exchange-rates'); ?>
                        </span>
                    </div>
                    <div class="boa-status-item">
                        <span class="label"><?php _e('Rates Count:', 'boa-exchange-rates'); ?></span>
                        <span class="value"><?php echo count($rates_data); ?></span>
                    </div>
                    <div class="boa-status-item">
                        <button type="button" id="boa-refresh-btn" class="button button-primary">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Refresh Rates Now', 'boa-exchange-rates'); ?>
                        </button>
                        <span id="boa-refresh-status"></span>
                    </div>
                </div>
            </div>

            <form method="post" action="options.php" id="boa-settings-form">
                <?php settings_fields('boa_rates_options_group'); ?>

                <div class="boa-settings-container">
                    <!-- Currency Selection -->
                    <div class="boa-settings-section">
                        <h2><?php _e('Select Currencies to Display', 'boa-exchange-rates'); ?></h2>
                        <p class="description"><?php _e('Choose which currencies to show on your website.', 'boa-exchange-rates'); ?></p>

                        <div class="boa-currency-grid">
                            <?php foreach ($all_currencies as $code => $currency) : ?>
                                <label class="boa-currency-checkbox">
                                    <input type="checkbox"
                                           name="boa_rates_options[selected_currencies][]"
                                           value="<?php echo esc_attr($code); ?>"
                                           <?php checked(in_array($code, $selected_currencies)); ?>>
                                    <span class="boa-currency-icon">
                                        <iconify-icon icon="<?php echo esc_attr($currency['icon']); ?>" width="<?php echo esc_attr($icon_size); ?>" height="<?php echo esc_attr($icon_size); ?>" style="color: <?php echo esc_attr($icon_color); ?>;"></iconify-icon>
                                    </span>
                                    <span class="boa-currency-code"><?php echo esc_html($code); ?></span>
                                    <span class="boa-currency-name"><?php echo esc_html($currency['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="boa-select-buttons">
                            <button type="button" id="boa-select-all" class="button">
                                <?php _e('Select All', 'boa-exchange-rates'); ?>
                            </button>
                            <button type="button" id="boa-select-none" class="button">
                                <?php _e('Deselect All', 'boa-exchange-rates'); ?>
                            </button>
                            <button type="button" id="boa-select-popular" class="button">
                                <?php _e('Select Popular (USD, EUR, GBP, CHF)', 'boa-exchange-rates'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Display Settings -->
                    <div class="boa-settings-section">
                        <h2><?php _e('Display Settings', 'boa-exchange-rates'); ?></h2>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Display Mode', 'boa-exchange-rates'); ?></th>
                                <td>
                                    <select name="boa_rates_options[display_mode]">
                                        <option value="table" <?php selected($display_mode, 'table'); ?>>
                                            <?php _e('Table', 'boa-exchange-rates'); ?>
                                        </option>
                                        <option value="cards" <?php selected($display_mode, 'cards'); ?>>
                                            <?php _e('Cards', 'boa-exchange-rates'); ?>
                                        </option>
                                        <option value="compact" <?php selected($display_mode, 'compact'); ?>>
                                            <?php _e('Compact List', 'boa-exchange-rates'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Table Style', 'boa-exchange-rates'); ?></th>
                                <td>
                                    <select name="boa_rates_options[table_style]">
                                        <option value="default" <?php selected($table_style, 'default'); ?>>
                                            <?php _e('Default', 'boa-exchange-rates'); ?>
                                        </option>
                                        <option value="striped" <?php selected($table_style, 'striped'); ?>>
                                            <?php _e('Striped', 'boa-exchange-rates'); ?>
                                        </option>
                                        <option value="bordered" <?php selected($table_style, 'bordered'); ?>>
                                            <?php _e('Bordered', 'boa-exchange-rates'); ?>
                                        </option>
                                        <option value="minimal" <?php selected($table_style, 'minimal'); ?>>
                                            <?php _e('Minimal', 'boa-exchange-rates'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Show Icons', 'boa-exchange-rates'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="boa_rates_options[show_icons]"
                                               value="1"
                                               <?php checked($show_icons, 1); ?>>
                                        <?php _e('Display currency icons next to currencies', 'boa-exchange-rates'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Show Last Update', 'boa-exchange-rates'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="boa_rates_options[show_date]"
                                               value="1"
                                               <?php checked($show_date, 1); ?>>
                                        <?php _e('Display last update date and time', 'boa-exchange-rates'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Decimal Places', 'boa-exchange-rates'); ?></th>
                                <td>
                                    <select name="boa_rates_options[decimal_places]">
                                        <?php for ($i = 0; $i <= 4; $i++) : ?>
                                            <option value="<?php echo $i; ?>" <?php selected($decimal_places, $i); ?>>
                                                <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Icon Settings -->
                    <div class="boa-settings-section">
                        <h2><?php _e('Icon Settings', 'boa-exchange-rates'); ?></h2>
                        <p class="description"><?php _e('Customize the appearance of currency icons.', 'boa-exchange-rates'); ?></p>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Icon Style', 'boa-exchange-rates'); ?></th>
                                <td>
                                    <select name="boa_rates_options[icon_style]" id="boa-icon-style">
                                        <option value="circle-flags" <?php selected($icon_style, 'circle-flags'); ?>>
                                            <?php _e('Circle Flags (colored)', 'boa-exchange-rates'); ?>
                                        </option>
                                        <option value="flag" <?php selected($icon_style, 'flag'); ?>>
                                            <?php _e('Rectangular Flags (colored)', 'boa-exchange-rates'); ?>
                                        </option>
                                        <option value="mono" <?php selected($icon_style, 'mono'); ?>>
                                            <?php _e('Monochrome Icons', 'boa-exchange-rates'); ?>
                                        </option>
                                    </select>
                                    <p class="description"><?php _e('Choose the icon style. Monochrome icons can be styled with your custom color.', 'boa-exchange-rates'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Icon Color', 'boa-exchange-rates'); ?></th>
                                <td>
                                    <input type="color"
                                           name="boa_rates_options[icon_color]"
                                           id="boa-icon-color"
                                           value="<?php echo esc_attr($icon_color); ?>">
                                    <input type="text"
                                           id="boa-icon-color-text"
                                           value="<?php echo esc_attr($icon_color); ?>"
                                           class="small-text"
                                           pattern="^#[0-9A-Fa-f]{6}$">
                                    <p class="description"><?php _e('Color for monochrome icons. Has no effect on colored flag icons.', 'boa-exchange-rates'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Icon Size', 'boa-exchange-rates'); ?></th>
                                <td>
                                    <input type="range"
                                           name="boa_rates_options[icon_size]"
                                           id="boa-icon-size"
                                           min="16"
                                           max="48"
                                           value="<?php echo esc_attr($icon_size); ?>">
                                    <span id="boa-icon-size-value"><?php echo esc_html($icon_size); ?>px</span>
                                    <p class="description"><?php _e('Size of currency icons (16px - 48px).', 'boa-exchange-rates'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Preview', 'boa-exchange-rates'); ?></th>
                                <td>
                                    <div class="boa-icon-preview" id="boa-icon-preview">
                                        <iconify-icon icon="circle-flags:us" width="<?php echo esc_attr($icon_size); ?>" height="<?php echo esc_attr($icon_size); ?>" style="color: <?php echo esc_attr($icon_color); ?>;"></iconify-icon>
                                        <iconify-icon icon="circle-flags:eu" width="<?php echo esc_attr($icon_size); ?>" height="<?php echo esc_attr($icon_size); ?>" style="color: <?php echo esc_attr($icon_color); ?>;"></iconify-icon>
                                        <iconify-icon icon="circle-flags:gb" width="<?php echo esc_attr($icon_size); ?>" height="<?php echo esc_attr($icon_size); ?>" style="color: <?php echo esc_attr($icon_color); ?>;"></iconify-icon>
                                        <iconify-icon icon="circle-flags:ch" width="<?php echo esc_attr($icon_size); ?>" height="<?php echo esc_attr($icon_size); ?>" style="color: <?php echo esc_attr($icon_color); ?>;"></iconify-icon>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Shortcode Info -->
                    <div class="boa-settings-section">
                        <h2><?php _e('Usage', 'boa-exchange-rates'); ?></h2>
                        <p><?php _e('Use the following shortcode to display exchange rates on your pages or posts:', 'boa-exchange-rates'); ?></p>

                        <div class="boa-shortcode-box">
                            <code>[boa_exchange_rates]</code>
                            <button type="button" class="button button-small boa-copy-btn" data-copy="[boa_exchange_rates]">
                                <?php _e('Copy', 'boa-exchange-rates'); ?>
                            </button>
                        </div>

                        <h3><?php _e('Shortcode Options', 'boa-exchange-rates'); ?></h3>
                        <table class="widefat">
                            <thead>
                                <tr>
                                    <th><?php _e('Attribute', 'boa-exchange-rates'); ?></th>
                                    <th><?php _e('Description', 'boa-exchange-rates'); ?></th>
                                    <th><?php _e('Example', 'boa-exchange-rates'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>currencies</code></td>
                                    <td><?php _e('Comma-separated list of currencies to display', 'boa-exchange-rates'); ?></td>
                                    <td><code>[boa_exchange_rates currencies="USD,EUR,GBP"]</code></td>
                                </tr>
                                <tr>
                                    <td><code>mode</code></td>
                                    <td><?php _e('Display mode: table, cards, or compact', 'boa-exchange-rates'); ?></td>
                                    <td><code>[boa_exchange_rates mode="cards"]</code></td>
                                </tr>
                                <tr>
                                    <td><code>show_icons</code></td>
                                    <td><?php _e('Show or hide icons: yes/no', 'boa-exchange-rates'); ?></td>
                                    <td><code>[boa_exchange_rates show_icons="no"]</code></td>
                                </tr>
                                <tr>
                                    <td><code>show_date</code></td>
                                    <td><?php _e('Show or hide update date: yes/no', 'boa-exchange-rates'); ?></td>
                                    <td><code>[boa_exchange_rates show_date="yes"]</code></td>
                                </tr>
                                <tr>
                                    <td><code>icon_color</code></td>
                                    <td><?php _e('Custom icon color (hex)', 'boa-exchange-rates'); ?></td>
                                    <td><code>[boa_exchange_rates icon_color="#0066cc"]</code></td>
                                </tr>
                                <tr>
                                    <td><code>icon_size</code></td>
                                    <td><?php _e('Icon size in pixels', 'boa-exchange-rates'); ?></td>
                                    <td><code>[boa_exchange_rates icon_size="32"]</code></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Preview -->
                    <div class="boa-settings-section">
                        <h2><?php _e('Preview', 'boa-exchange-rates'); ?></h2>
                        <div id="boa-preview-container">
                            <?php echo do_shortcode('[boa_exchange_rates]'); ?>
                        </div>
                    </div>
                </div>

                <?php submit_button(__('Save Settings', 'boa-exchange-rates')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX handler for refreshing rates
     */
    public function ajax_refresh_rates() {
        check_ajax_referer('boa_rates_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'boa-exchange-rates')));
        }

        $scraper = new BoA_Scraper();
        $result = $scraper->fetch_and_save_rates();

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Rates updated successfully!', 'boa-exchange-rates'),
            'boa_date' => $result['boa_date'],
            'boa_time' => $result['boa_time'],
            'fetched_at' => $result['fetched_at'],
            'rates_count' => count($result['rates']),
        ));
    }

    /**
     * AJAX handler for preview
     */
    public function ajax_get_preview() {
        check_ajax_referer('boa_rates_nonce', 'nonce');

        $shortcode = new BoA_Shortcode();
        $html = $shortcode->render_shortcode(array());

        wp_send_json_success(array('html' => $html));
    }
}
