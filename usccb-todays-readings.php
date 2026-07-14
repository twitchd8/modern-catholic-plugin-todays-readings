<?php
/**
 * Plugin Name: USCCB Today’s Readings
 * Description: Provides a dynamic block that displays the current daily Mass readings from the USCCB RSS feed.
 * Version: 0.1.0
 * Author: Andrew T. Schmitt
 * License: GPL-2.0-or-later
 * Text Domain: usccb-todays-readings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'USCCB_TODAYS_READINGS_FEED_URL', 'https://www.usccb.org/bible/readings/rss/index.cfm' );

/**
 * Registers the Today’s Readings block.
 */
function usccb_todays_readings_register_block() {
	register_block_type( __DIR__ . '/blocks/todays-readings' );
}
add_action( 'init', 'usccb_todays_readings_register_block' );

/**
 * Refreshes only the USCCB feed hourly instead of every 12 hours.
 *
 * @param int    $lifetime Cache lifetime in seconds.
 * @param string $url      Feed URL.
 * @return int
 */
function usccb_todays_readings_cache_lifetime( $lifetime, $url ) {
	if ( USCCB_TODAYS_READINGS_FEED_URL === $url ) {
		return HOUR_IN_SECONDS;
	}

	return $lifetime;
}
add_filter( 'wp_feed_cache_transient_lifetime', 'usccb_todays_readings_cache_lifetime', 10, 2 );

