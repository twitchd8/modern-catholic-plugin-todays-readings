<?php
/**
 * Plugin Name: USCCB Today’s Readings
 * Description: Caches and displays USCCB Mass readings, including multiple liturgies and their liturgical colors.
 * Version: 0.5.0
 * Author: Andrew T. Schmitt
 * License: GPL-2.0-or-later
 * Text Domain: usccb-todays-readings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'USCCB_TODAYS_READINGS_VERSION', '0.5.0' );
define( 'USCCB_TODAYS_READINGS_FEED_URL', 'https://www.usccb.org/bible/readings/rss/index.cfm' );
define( 'USCCB_TODAYS_READINGS_CALENDAR_URL', 'https://bible.usccb.org/readings/calendar/' );
define( 'USCCB_TODAYS_READINGS_CACHE_OPTION', 'usccb_todays_readings_week_cache' );
define( 'USCCB_TODAYS_READINGS_STATUS_OPTION', 'usccb_todays_readings_cache_status' );
define( 'USCCB_TODAYS_READINGS_SETTINGS_OPTION', 'usccb_todays_readings_settings' );
define( 'USCCB_TODAYS_READINGS_LOG_OPTION', 'usccb_todays_readings_log' );
define( 'USCCB_TODAYS_READINGS_CRON_HOOK', 'usccb_todays_readings_refresh_cache' );

require_once __DIR__ . '/includes/cache.php';

if ( is_admin() ) {
	require_once __DIR__ . '/includes/admin.php';
}

/**
 * Registers the Today’s Readings block.
 */
function usccb_todays_readings_register_block() {
	register_block_type( __DIR__ . '/blocks/todays-readings' );
}

/**
 * Adds the plugin's 30-day retrieval cadence to WP-Cron.
 *
 * The scheduler is realigned to the selected site-local time on normal init,
 * which also corrects the one-hour shift that daylight-saving changes can
 * otherwise introduce into interval-based WP-Cron events.
 *
 * @param array $schedules Registered schedules.
 * @return array
 */
function usccb_todays_readings_add_cron_schedule( $schedules ) {
	$schedules['usccb_every_30_days'] = array(
		'interval' => 30 * DAY_IN_SECONDS,
		'display'  => __( 'Every 30 days', 'usccb-todays-readings' ),
	);
	return $schedules;
}

/**
 * Removes every event variant used by this plugin.
 */
function usccb_todays_readings_clear_schedules() {
	$argument_sets = array( array(), array( 'daily' ), array( 'monthly' ), array( 'retry' ), array( 'warmup' ) );

	foreach ( $argument_sets as $arguments ) {
		wp_clear_scheduled_hook( USCCB_TODAYS_READINGS_CRON_HOOK, $arguments );
	}
}

/**
 * Ensures the 30-day refresh exists and remains aligned to the selected time.
 */
function usccb_todays_readings_ensure_schedule() {
	$monthly_args = array( 'monthly' );
	$settings     = usccb_todays_readings_get_settings();

	// Remove schedules created by versions earlier than 0.4.
	if ( wp_next_scheduled( USCCB_TODAYS_READINGS_CRON_HOOK ) ) {
		wp_clear_scheduled_hook( USCCB_TODAYS_READINGS_CRON_HOOK, array() );
	}
	if ( wp_next_scheduled( USCCB_TODAYS_READINGS_CRON_HOOK, array( 'daily' ) ) ) {
		wp_clear_scheduled_hook( USCCB_TODAYS_READINGS_CRON_HOOK, array( 'daily' ) );
	}

	$scheduled = wp_next_scheduled( USCCB_TODAYS_READINGS_CRON_HOOK, $monthly_args );
	if ( $scheduled && $settings['refresh_time'] === wp_date( 'H:i', $scheduled, wp_timezone() ) ) {
		return;
	}

	if ( $scheduled ) {
		wp_clear_scheduled_hook( USCCB_TODAYS_READINGS_CRON_HOOK, $monthly_args );
	}

	wp_schedule_event(
		usccb_todays_readings_next_refresh_timestamp(),
		'usccb_every_30_days',
		USCCB_TODAYS_READINGS_CRON_HOOK,
		$monthly_args
	);
}

/**
 * Replaces the retrieval event after the administrator changes its time.
 */
function usccb_todays_readings_reschedule() {
	wp_clear_scheduled_hook( USCCB_TODAYS_READINGS_CRON_HOOK, array( 'daily' ) );
	wp_clear_scheduled_hook( USCCB_TODAYS_READINGS_CRON_HOOK, array( 'monthly' ) );
	wp_schedule_event(
		usccb_todays_readings_next_refresh_timestamp(),
		'usccb_every_30_days',
		USCCB_TODAYS_READINGS_CRON_HOOK,
		array( 'monthly' )
	);
}

/**
 * Schedules an immediate warm-up and the recurring 30-day refresh.
 */
function usccb_todays_readings_activate() {
	usccb_todays_readings_ensure_schedule();

	if ( ! get_option( USCCB_TODAYS_READINGS_CACHE_OPTION ) && ! wp_next_scheduled( USCCB_TODAYS_READINGS_CRON_HOOK, array( 'warmup' ) ) ) {
		wp_schedule_single_event( time() + 5, USCCB_TODAYS_READINGS_CRON_HOOK, array( 'warmup' ) );
	}
}

/**
 * Removes scheduled refreshes. The last good cache is intentionally retained.
 */
function usccb_todays_readings_deactivate() {
	usccb_todays_readings_clear_schedules();
	delete_transient( 'usccb_todays_readings_refresh_lock' );
	usccb_todays_readings_log( 'info', 'plugin_deactivated', __( 'Scheduled refreshes were removed.', 'usccb-todays-readings' ) );
}

add_filter( 'cron_schedules', 'usccb_todays_readings_add_cron_schedule' );
add_action( 'init', 'usccb_todays_readings_register_block' );
add_action( 'init', 'usccb_todays_readings_ensure_schedule' );
add_action( USCCB_TODAYS_READINGS_CRON_HOOK, 'usccb_todays_readings_refresh_cache' );
register_activation_hook( __FILE__, 'usccb_todays_readings_activate' );
register_deactivation_hook( __FILE__, 'usccb_todays_readings_deactivate' );
