<?php
/**
 * Cron Job Handler
 *
 * Manages scheduled rate updates with smart checking logic:
 * - Checks every weekday (Monday-Friday) starting at 12:00 (midday)
 * - Skips weekends (Saturday and Sunday) - rates don't change
 * - Checks every 5 minutes until rates are updated
 * - Once rates match today's date, stops checking until next business day
 *
 * @package BoA_Exchange_Rates
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class BoA_Cron {

    /**
     * Hook names
     */
    const DAILY_HOOK = 'boa_rates_daily_check';
    const INTERVAL_HOOK = 'boa_rates_interval_check';

    /**
     * Constructor
     */
    public function __construct() {
        // Register cron hooks
        add_action(self::DAILY_HOOK, array($this, 'daily_check'));
        add_action(self::INTERVAL_HOOK, array($this, 'interval_check'));

        // Register custom cron interval
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }

    /**
     * Add custom cron intervals
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_cron_intervals($schedules) {
        // Every 5 minutes
        $schedules['boa_five_minutes'] = array(
            'interval' => 5 * 60, // 5 minutes in seconds
            'display' => __('Every 5 Minutes', 'boa-exchange-rates'),
        );

        return $schedules;
    }

    /**
     * Check if a given date is a weekend
     *
     * @param int|null $timestamp Unix timestamp (null for current time)
     * @return bool True if Saturday or Sunday
     */
    private static function is_weekend($timestamp = null) {
        if ($timestamp === null) {
            $day_of_week = intval(current_time('w'));
        } else {
            $day_of_week = intval(date('w', $timestamp));
        }

        // 0 = Sunday, 6 = Saturday
        return ($day_of_week === 0 || $day_of_week === 6);
    }

    /**
     * Get the next weekday (Monday-Friday) at noon
     *
     * @param int|null $from_timestamp Starting timestamp (null for now)
     * @return int Unix timestamp of next weekday at 12:00
     */
    private static function get_next_weekday_noon($from_timestamp = null) {
        if ($from_timestamp === null) {
            $from_timestamp = current_time('timestamp');
        }

        // Start with today at noon
        $noon_today = strtotime('today 12:00:00', $from_timestamp);

        // If it's past noon, start from tomorrow
        if ($from_timestamp > $noon_today) {
            $check_date = strtotime('tomorrow 12:00:00', $from_timestamp);
        } else {
            $check_date = $noon_today;
        }

        // Keep advancing until we find a weekday
        while (self::is_weekend($check_date)) {
            $check_date = strtotime('+1 day', $check_date);
        }

        return $check_date;
    }

    /**
     * Schedule cron events (called on plugin activation)
     */
    public static function schedule_events() {
        // Clear any existing events first
        self::clear_events();

        // Get next weekday at noon
        $next_check = self::get_next_weekday_noon();

        wp_schedule_event($next_check, 'daily', self::DAILY_HOOK);

        // Log scheduling
        error_log('[BoA Rates] Scheduled next check at: ' . date('Y-m-d H:i:s (l)', $next_check));
    }

    /**
     * Clear all scheduled events (called on plugin deactivation)
     */
    public static function clear_events() {
        wp_clear_scheduled_hook(self::DAILY_HOOK);
        wp_clear_scheduled_hook(self::INTERVAL_HOOK);
    }

    /**
     * Daily check - runs at noon each day
     * Starts the interval checking process
     */
    public function daily_check() {
        error_log('[BoA Rates] Daily check triggered at: ' . current_time('mysql'));

        // Skip weekends - rates don't change
        if (self::is_weekend()) {
            error_log('[BoA Rates] Weekend detected, skipping check and rescheduling for Monday');
            $this->reschedule_for_next_weekday();
            return;
        }

        // Check if rates need update
        $scraper = new BoA_Scraper();

        if ($scraper->rates_need_update()) {
            // Rates not updated yet, start interval checking
            $this->start_interval_checking();

            // Try to fetch immediately
            $result = $scraper->fetch_and_save_rates();

            if (!is_wp_error($result) && $this->is_rate_current($result)) {
                // Success! Stop interval checking
                $this->stop_interval_checking();
                error_log('[BoA Rates] Rates updated successfully on first try');

                // Reschedule for next weekday
                $this->reschedule_for_next_weekday();
            }
        } else {
            error_log('[BoA Rates] Rates already current, skipping update');
            // Reschedule for next weekday
            $this->reschedule_for_next_weekday();
        }
    }

    /**
     * Interval check - runs every 5 minutes during update window
     */
    public function interval_check() {
        error_log('[BoA Rates] Interval check at: ' . current_time('mysql'));

        // Skip weekends
        if (self::is_weekend()) {
            $this->stop_interval_checking();
            error_log('[BoA Rates] Weekend detected during interval check, stopping');
            return;
        }

        $scraper = new BoA_Scraper();

        // Check if we're still in the update window (12:00 - 14:00)
        $current_hour = intval(current_time('G'));

        if ($current_hour >= 14) {
            // Past the update window, stop checking
            $this->stop_interval_checking();
            error_log('[BoA Rates] Past update window, stopping interval checks');
            return;
        }

        // Check if rates need update
        if (!$scraper->rates_need_update()) {
            // Rates are current, stop checking
            $this->stop_interval_checking();
            error_log('[BoA Rates] Rates are current, stopping interval checks');
            return;
        }

        // Try to fetch rates
        $result = $scraper->fetch_and_save_rates();

        if (!is_wp_error($result)) {
            // Check if the BoA date matches today
            if ($this->is_rate_current($result)) {
                // Success! Stop interval checking
                $this->stop_interval_checking();
                error_log('[BoA Rates] Rates updated to today\'s date, stopping interval checks');
            } else {
                error_log('[BoA Rates] Rates fetched but still showing yesterday\'s date, will retry');
            }
        } else {
            error_log('[BoA Rates] Error fetching rates: ' . $result->get_error_message());
        }
    }

    /**
     * Reschedule the daily check for the next weekday
     */
    private function reschedule_for_next_weekday() {
        // Clear existing daily schedule
        wp_clear_scheduled_hook(self::DAILY_HOOK);

        // Get tomorrow's timestamp
        $tomorrow = strtotime('tomorrow 12:00:00');

        // Find next weekday
        $next_weekday = self::get_next_weekday_noon($tomorrow);

        wp_schedule_event($next_weekday, 'daily', self::DAILY_HOOK);

        error_log('[BoA Rates] Rescheduled next check for: ' . date('Y-m-d H:i:s (l)', $next_weekday));
    }

    /**
     * Start interval checking (every 5 minutes)
     */
    private function start_interval_checking() {
        // Don't schedule if already scheduled
        if (wp_next_scheduled(self::INTERVAL_HOOK)) {
            return;
        }

        // Schedule to run every 5 minutes starting now
        wp_schedule_event(time(), 'boa_five_minutes', self::INTERVAL_HOOK);
        error_log('[BoA Rates] Started 5-minute interval checking');
    }

    /**
     * Stop interval checking
     */
    private function stop_interval_checking() {
        wp_clear_scheduled_hook(self::INTERVAL_HOOK);
        error_log('[BoA Rates] Stopped interval checking');
    }

    /**
     * Check if rate data is current (matches today's date)
     * On weekends, check if it matches Friday's date
     *
     * @param array $result Fetch result
     * @return bool True if current
     */
    private function is_rate_current($result) {
        if (empty($result['boa_date'])) {
            return false;
        }

        $today = current_time('Y-m-d');
        $day_of_week = intval(current_time('w'));

        // On Saturday or Sunday, rates should match Friday
        if ($day_of_week === 0) { // Sunday
            $expected_date = date('Y-m-d', strtotime('last Friday'));
        } elseif ($day_of_week === 6) { // Saturday
            $expected_date = date('Y-m-d', strtotime('yesterday')); // Friday
        } else {
            $expected_date = $today;
        }

        return ($result['boa_date'] === $expected_date || $result['boa_date'] === $today);
    }

    /**
     * Get next scheduled check time
     *
     * @return string|false Next scheduled time or false
     */
    public static function get_next_check() {
        $daily = wp_next_scheduled(self::DAILY_HOOK);
        $interval = wp_next_scheduled(self::INTERVAL_HOOK);

        if ($interval) {
            return date('Y-m-d H:i:s (l)', $interval) . ' (interval check)';
        }

        if ($daily) {
            return date('Y-m-d H:i:s (l)', $daily) . ' (daily check)';
        }

        return false;
    }

    /**
     * Check if interval checking is active
     *
     * @return bool
     */
    public static function is_interval_checking_active() {
        return (bool) wp_next_scheduled(self::INTERVAL_HOOK);
    }

    /**
     * Force start a check (for manual refresh)
     *
     * @return array|WP_Error Result
     */
    public static function force_check() {
        $scraper = new BoA_Scraper();
        return $scraper->fetch_and_save_rates();
    }

    /**
     * Get human-readable schedule info
     *
     * @return array Schedule information
     */
    public static function get_schedule_info() {
        $next_daily = wp_next_scheduled(self::DAILY_HOOK);
        $next_interval = wp_next_scheduled(self::INTERVAL_HOOK);

        return array(
            'next_check' => $next_daily ? date('Y-m-d H:i:s', $next_daily) : 'Not scheduled',
            'next_check_day' => $next_daily ? date('l', $next_daily) : '-',
            'interval_active' => $next_interval ? true : false,
            'is_weekend' => self::is_weekend(),
            'current_day' => current_time('l'),
        );
    }
}
