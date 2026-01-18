<?php
/**
 * Bank of Albania Exchange Rate Scraper
 *
 * Scrapes official exchange rates from Bank of Albania website
 * https://www.bankofalbania.org/Tregjet/Kursi_zyrtar_i_kembimit/
 *
 * @package BoA_Exchange_Rates
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BoA_Scraper {

    /**
     * BoA exchange rates URL
     */
    const BOA_URL = 'https://www.bankofalbania.org/Tregjet/Kursi_zyrtar_i_kembimit/';

    /**
     * All supported currencies with their details
     * Icons use Iconify format for monochrome styling
     */
    private $currencies = array(
        'USD' => array(
            'name' => 'US Dollar',
            'albanian_names' => array('Dollar Amerikan', 'Dollari Amerikan'),
            'icon' => 'circle-flags:us',
            'unit' => 1,
        ),
        'EUR' => array(
            'name' => 'Euro',
            'albanian_names' => array('Euro'),
            'icon' => 'circle-flags:eu',
            'unit' => 1,
        ),
        'GBP' => array(
            'name' => 'British Pound',
            'albanian_names' => array('Poundi Britanik', 'Paundi Britanik'),
            'icon' => 'circle-flags:gb',
            'unit' => 1,
        ),
        'CHF' => array(
            'name' => 'Swiss Franc',
            'albanian_names' => array('Franga Zvicerane'),
            'icon' => 'circle-flags:ch',
            'unit' => 1,
        ),
        'JPY' => array(
            'name' => 'Japanese Yen',
            'albanian_names' => array('Jeni Japonez'),
            'icon' => 'circle-flags:jp',
            'unit' => 100,
        ),
        'AUD' => array(
            'name' => 'Australian Dollar',
            'albanian_names' => array('Dollari Australiane'),
            'icon' => 'circle-flags:au',
            'unit' => 1,
        ),
        'CAD' => array(
            'name' => 'Canadian Dollar',
            'albanian_names' => array('Dollari Kanadez'),
            'icon' => 'circle-flags:ca',
            'unit' => 1,
        ),
        'SEK' => array(
            'name' => 'Swedish Krona',
            'albanian_names' => array('Korona Suedeze'),
            'icon' => 'circle-flags:se',
            'unit' => 1,
        ),
        'NOK' => array(
            'name' => 'Norwegian Krone',
            'albanian_names' => array('Korona Norvegjeze'),
            'icon' => 'circle-flags:no',
            'unit' => 1,
        ),
        'DKK' => array(
            'name' => 'Danish Krone',
            'albanian_names' => array('Korona Daneze'),
            'icon' => 'circle-flags:dk',
            'unit' => 1,
        ),
        'TRY' => array(
            'name' => 'Turkish Lira',
            'albanian_names' => array('Lira Turke'),
            'icon' => 'circle-flags:tr',
            'unit' => 1,
        ),
        'CNY' => array(
            'name' => 'Chinese Yuan',
            'albanian_names' => array('Juani Kinez'),
            'icon' => 'circle-flags:cn',
            'unit' => 1,
        ),
        'BGN' => array(
            'name' => 'Bulgarian Lev',
            'albanian_names' => array('Leva Bullgare'),
            'icon' => 'circle-flags:bg',
            'unit' => 1,
        ),
        'HUF' => array(
            'name' => 'Hungarian Forint',
            'albanian_names' => array('Forinta Hungareze'),
            'icon' => 'circle-flags:hu',
            'unit' => 100,
        ),
        'RUB' => array(
            'name' => 'Russian Ruble',
            'albanian_names' => array('Rubla Ruse'),
            'icon' => 'circle-flags:ru',
            'unit' => 100,
        ),
        'CZK' => array(
            'name' => 'Czech Koruna',
            'albanian_names' => array('Korona Çeke'),
            'icon' => 'circle-flags:cz',
            'unit' => 1,
        ),
        'PLN' => array(
            'name' => 'Polish Zloty',
            'albanian_names' => array('Zlota Polake'),
            'icon' => 'circle-flags:pl',
            'unit' => 1,
        ),
        'RON' => array(
            'name' => 'Romanian Leu',
            'albanian_names' => array('Leu Rumun'),
            'icon' => 'circle-flags:ro',
            'unit' => 1,
        ),
        'MKD' => array(
            'name' => 'Macedonian Denar',
            'albanian_names' => array('Dinari Maqedonas'),
            'icon' => 'circle-flags:mk',
            'unit' => 1,
        ),
        'XAU' => array(
            'name' => 'Gold (per oz)',
            'albanian_names' => array('Ari'),
            'icon' => 'mdi:gold',
            'unit' => 1,
        ),
        'XAG' => array(
            'name' => 'Silver (per oz)',
            'albanian_names' => array('Argjendi', 'Argjend'),
            'icon' => 'mdi:silver',
            'unit' => 1,
        ),
        'SDR' => array(
            'name' => 'Special Drawing Rights',
            'albanian_names' => array('SDR', 'Të drejtat speciale të tërheqjes'),
            'icon' => 'mdi:bank',
            'unit' => 1,
        ),
    );

    /**
     * Fetch and save rates from BoA website
     *
     * @return array|WP_Error Result array or WP_Error on failure
     */
    public function fetch_and_save_rates() {
        $result = $this->fetch_rates();

        if (is_wp_error($result)) {
            return $result;
        }

        // Save to options
        $options = get_option('boa_rates_options', array());
        $options['rates_data'] = $result['rates'];
        $options['last_update'] = current_time('mysql');
        $options['last_boa_date'] = $result['boa_date'];
        $options['last_boa_time'] = $result['boa_time'];
        update_option('boa_rates_options', $options);

        return $result;
    }

    /**
     * Fetch rates from BoA website
     *
     * @return array|WP_Error Rates array or WP_Error on failure
     */
    public function fetch_rates() {
        // Make request to BoA website
        $response = wp_remote_get(self::BOA_URL, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('http_error', sprintf(__('HTTP Error: %d', 'boa-exchange-rates'), $status_code));
        }

        $html = wp_remote_retrieve_body($response);
        if (empty($html)) {
            return new WP_Error('empty_response', __('Empty response from BoA website', 'boa-exchange-rates'));
        }

        // Parse the HTML
        return $this->parse_html($html);
    }

    /**
     * Parse HTML and extract exchange rates
     *
     * @param string $html HTML content
     * @return array|WP_Error Parsed rates or WP_Error
     */
    private function parse_html($html) {
        // Suppress HTML parsing errors
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Extract last update timestamp
        $boa_datetime = $this->extract_datetime($dom, $xpath, $html);

        // Find the main rates table
        $rates = $this->extract_rates_from_table($dom, $xpath);

        if (empty($rates)) {
            // Try alternative parsing
            $rates = $this->extract_rates_alternative($html);
        }

        if (empty($rates)) {
            return new WP_Error('no_rates', __('Could not find exchange rates in the page', 'boa-exchange-rates'));
        }

        return array(
            'rates' => $rates,
            'boa_date' => $boa_datetime['date'],
            'boa_time' => $boa_datetime['time'],
            'fetched_at' => current_time('mysql'),
        );
    }

    /**
     * Extract datetime from page
     *
     * @param DOMDocument $dom
     * @param DOMXPath $xpath
     * @param string $html
     * @return array Date and time
     */
    private function extract_datetime($dom, $xpath, $html) {
        $date = '';
        $time = '';

        // Try to find timestamp pattern: DD.MM.YYYY HH:MM:SS
        if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}:\d{2}:\d{2})/', $html, $matches)) {
            $date = sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
            $time = $matches[4];
        }

        return array(
            'date' => $date,
            'time' => $time,
        );
    }

    /**
     * Extract rates from HTML table
     *
     * @param DOMDocument $dom
     * @param DOMXPath $xpath
     * @return array Rates array
     */
    private function extract_rates_from_table($dom, $xpath) {
        $rates = array();

        // Find all tables
        $tables = $xpath->query('//table');

        foreach ($tables as $table) {
            $rows = $xpath->query('.//tr', $table);

            foreach ($rows as $row) {
                $cells = $xpath->query('.//td|.//th', $row);

                if ($cells->length < 2) {
                    continue;
                }

                $rate_data = $this->parse_table_row($cells);

                if ($rate_data) {
                    $rates[$rate_data['code']] = $rate_data;
                }
            }
        }

        return $rates;
    }

    /**
     * Parse a table row to extract rate data
     *
     * @param DOMNodeList $cells Table cells
     * @return array|null Rate data or null
     */
    private function parse_table_row($cells) {
        $cell_values = array();

        foreach ($cells as $cell) {
            $cell_values[] = trim($cell->textContent);
        }

        // Try to identify which cell contains what
        $currency_name = '';
        $currency_code = '';
        $rate_value = null;

        foreach ($cell_values as $index => $value) {
            // Check if it's a rate (numeric value)
            $cleaned = preg_replace('/[^\d.,]/', '', $value);
            $cleaned = str_replace(',', '.', $cleaned);

            if (is_numeric($cleaned) && floatval($cleaned) > 0) {
                $rate_value = floatval($cleaned);
                continue;
            }

            // Check if it's a currency code (3 uppercase letters)
            if (preg_match('/^[A-Z]{3}$/', $value)) {
                $currency_code = $value;
                continue;
            }

            // Otherwise it might be a currency name
            if (strlen($value) > 3 && !$currency_name) {
                $currency_name = $value;
            }
        }

        // If we don't have a code, try to get it from the name
        if (!$currency_code && $currency_name) {
            $currency_code = $this->get_code_from_name($currency_name);
        }

        // Validate we have required data
        if (!$currency_code || $rate_value === null) {
            return null;
        }

        // Get currency details
        $currency_info = isset($this->currencies[$currency_code])
            ? $this->currencies[$currency_code]
            : null;

        if (!$currency_info) {
            return null;
        }

        return array(
            'code' => $currency_code,
            'name' => $currency_info['name'],
            'rate' => $rate_value,
            'icon' => $currency_info['icon'],
            'unit' => $currency_info['unit'],
        );
    }

    /**
     * Get currency code from Albanian name
     *
     * @param string $name Albanian currency name
     * @return string|null Currency code or null
     */
    private function get_code_from_name($name) {
        $name_lower = mb_strtolower($name, 'UTF-8');

        foreach ($this->currencies as $code => $info) {
            foreach ($info['albanian_names'] as $alb_name) {
                if (mb_strpos($name_lower, mb_strtolower($alb_name, 'UTF-8')) !== false) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * Alternative rate extraction using regex
     *
     * @param string $html HTML content
     * @return array Rates array
     */
    private function extract_rates_alternative($html) {
        $rates = array();

        // Pattern to match currency and rate
        $patterns = array(
            'USD' => '/(?:USD|Dollar\s*Amerikan)[^\d]*(\d+[.,]\d+)/i',
            'EUR' => '/(?:EUR|Euro)[^\d]*(\d+[.,]\d+)/i',
            'GBP' => '/(?:GBP|Pound|Paund)[^\d]*(\d+[.,]\d+)/i',
            'CHF' => '/(?:CHF|Frang)[^\d]*(\d+[.,]\d+)/i',
            'JPY' => '/(?:JPY|Jen)[^\d]*(\d+[.,]\d+)/i',
            'AUD' => '/(?:AUD|Dollari?\s*Australi)/i',
            'CAD' => '/(?:CAD|Dollari?\s*Kanadez)/i',
            'SEK' => '/(?:SEK|Korona\s*Suedeze)/i',
            'NOK' => '/(?:NOK|Korona\s*Norvegjeze)/i',
            'DKK' => '/(?:DKK|Korona\s*Daneze)/i',
        );

        foreach ($patterns as $code => $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $rate_str = isset($matches[1]) ? $matches[1] : '';
                $rate_str = str_replace(',', '.', $rate_str);

                if (is_numeric($rate_str)) {
                    $currency_info = $this->currencies[$code];
                    $rates[$code] = array(
                        'code' => $code,
                        'name' => $currency_info['name'],
                        'rate' => floatval($rate_str),
                        'icon' => $currency_info['icon'],
                        'unit' => $currency_info['unit'],
                    );
                }
            }
        }

        return $rates;
    }

    /**
     * Get all supported currencies
     *
     * @return array Currencies array
     */
    public function get_supported_currencies() {
        return $this->currencies;
    }

    /**
     * Check if rates need update (BoA date matches today)
     *
     * @return bool True if rates need update
     */
    public function rates_need_update() {
        $options = get_option('boa_rates_options', array());
        $last_boa_date = isset($options['last_boa_date']) ? $options['last_boa_date'] : '';

        // Get today's date
        $today = current_time('Y-m-d');

        // If last BoA date is today, no update needed
        if ($last_boa_date === $today) {
            return false;
        }

        return true;
    }
}
