<?php
/**
 * Seven-day USCCB readings cache.
 *
 * @package USCCB_Todays_Readings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the request headers used by background refreshes.
 *
 * @return array
 */
function usccb_todays_readings_request_headers() {
	return array(
		'Accept'          => 'text/html,application/xhtml+xml,application/rss+xml,application/xml;q=0.9,*/*;q=0.8',
		'Accept-Language' => 'en-US,en;q=0.8',
		'User-Agent'      => 'ModernCatholicReadings/' . USCCB_TODAYS_READINGS_VERSION . '; ' . home_url( '/' ),
	);
}

/**
 * Fetches an approved USCCB URL.
 *
 * @param string $url URL to fetch.
 * @return string|WP_Error
 */
function usccb_todays_readings_fetch_url( $url ) {
	$url  = esc_url_raw( trim( $url ) );
	$host = wp_parse_url( $url, PHP_URL_HOST );

	if ( 'bible.usccb.org' !== $host && 'www.usccb.org' !== $host ) {
		return new WP_Error( 'invalid_usccb_host', __( 'The readings URL is not an approved USCCB address.', 'usccb-todays-readings' ) );
	}

	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'     => 20,
			'redirection' => 5,
			'headers'     => usccb_todays_readings_request_headers(),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$status = wp_remote_retrieve_response_code( $response );
	if ( 200 !== $status ) {
		return new WP_Error(
			'usccb_http_error',
			sprintf(
				/* translators: %d: HTTP status code. */
				__( 'USCCB returned HTTP %d.', 'usccb-todays-readings' ),
				$status
			)
		);
	}

	return wp_remote_retrieve_body( $response );
}

/**
 * Creates a DOM document without emitting warnings for imperfect source HTML.
 *
 * @param string $html Source HTML.
 * @return DOMDocument|WP_Error
 */
function usccb_todays_readings_load_html( $html ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return new WP_Error( 'dom_unavailable', __( 'The PHP DOM extension is required.', 'usccb-todays-readings' ) );
	}

	$previous = libxml_use_internal_errors( true );
	$document = new DOMDocument();
	$loaded   = $document->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded ) {
		return new WP_Error( 'invalid_usccb_html', __( 'USCCB returned unreadable HTML.', 'usccb-todays-readings' ) );
	}

	return $document;
}

/**
 * Returns normalized text for a DOM node.
 *
 * @param DOMNode|null $node DOM node.
 * @return string
 */
function usccb_todays_readings_node_text( $node ) {
	return $node ? trim( preg_replace( '/\s+/u', ' ', $node->textContent ) ) : '';
}

/**
 * Resolves a relative USCCB link.
 *
 * @param string $url Link from the page.
 * @return string
 */
function usccb_todays_readings_absolute_url( $url ) {
	$url = trim( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );

	if ( 0 === strpos( $url, 'https://' ) ) {
		return esc_url_raw( $url );
	}

	return esc_url_raw( 'https://bible.usccb.org/' . ltrim( $url, '/' ) );
}

/**
 * Determines what kind of calendar record a URL represents.
 *
 * @param string $url Entry URL.
 * @return string
 */
function usccb_todays_readings_entry_kind( $url ) {
	if ( preg_match( '/-Vigil(?:\.cfm)?\/?$/i', $url ) ) {
		return 'vigil';
	}

	if ( preg_match( '/-Night(?:\.cfm)?\/?$/i', $url ) ) {
		return 'night';
	}

	if ( preg_match( '/-Dawn(?:\.cfm)?\/?$/i', $url ) ) {
		return 'dawn';
	}

	if ( preg_match( '/-Day(?:\.cfm)?\/?$/i', $url ) ) {
		return 'day';
	}

	return 'readings';
}

/**
 * Parses all entries from one USCCB calendar month.
 *
 * @param string $html Calendar HTML.
 * @return array|WP_Error
 */
function usccb_todays_readings_parse_calendar( $html ) {
	$document = usccb_todays_readings_load_html( $html );
	if ( is_wp_error( $document ) ) {
		return $document;
	}

	$xpath   = new DOMXPath( $document );
	$nodes   = $xpath->query( '//a[@data-colors]' );
	$entries = array();

	foreach ( $nodes as $node ) {
		$date_node = $xpath->query( 'ancestor::td[@data-date][1]', $node )->item( 0 );
		$date      = $date_node ? $date_node->getAttribute( 'data-date' ) : '';
		$url       = usccb_todays_readings_absolute_url( $node->getAttribute( 'href' ) );
		$title     = usccb_todays_readings_node_text( $node );
		$color     = sanitize_key( explode( ',', $node->getAttribute( 'data-colors' ) )[0] );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! $url || ! $title ) {
			continue;
		}

		$kind      = usccb_todays_readings_entry_kind( $url );
		$mass_date = $date;

		if ( 'vigil' === $kind ) {
			$celebration = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
			if ( $celebration ) {
				$mass_date = $celebration->modify( '-1 day' )->format( 'Y-m-d' );
			}
		}

		$entries[] = array(
			'id'               => md5( strtolower( untrailingslashit( $url ) ) ),
			'celebration_date' => $date,
			'mass_date'        => $mass_date,
			'title'            => sanitize_text_field( $title ),
			'url'              => $url,
			'kind'             => $kind,
			'liturgical_color' => $color ? $color : 'unknown',
			'lectionary'       => '',
			'readings'         => array(),
			'description_html' => '',
		);
	}

	$group_counts = array();
	foreach ( $entries as $entry ) {
		$group_key = $entry['celebration_date'] . '|' . strtolower( $entry['title'] );
		if ( ! isset( $group_counts[ $group_key ] ) ) {
			$group_counts[ $group_key ] = 0;
		}
		++$group_counts[ $group_key ];
	}

	foreach ( $entries as &$entry ) {
		$group_key = $entry['celebration_date'] . '|' . strtolower( $entry['title'] );
		if ( 'readings' === $entry['kind'] && $group_counts[ $group_key ] > 1 ) {
			$entry['kind'] = 'options';
		}
	}
	unset( $entry );

	return $entries;
}

/**
 * Parses lectionary metadata and citations without copying copyrighted bodies.
 *
 * @param string $html Reading page HTML.
 * @return array|WP_Error
 */
function usccb_todays_readings_parse_reading_page( $html ) {
	$document = usccb_todays_readings_load_html( $html );
	if ( is_wp_error( $document ) ) {
		return $document;
	}

	$xpath      = new DOMXPath( $document );
	$lectionary = '';
	$readings   = array();
	$heading    = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " b-lectionary ")]//h2' )->item( 0 );
	$paragraph  = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " b-lectionary ")]//p' )->item( 0 );

	if ( $paragraph && preg_match( '/Lectionary:\s*(.+)$/i', usccb_todays_readings_node_text( $paragraph ), $matches ) ) {
		$lectionary = sanitize_text_field( $matches[1] );
	}

	$verse_nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " b-verse ")]' );
	foreach ( $verse_nodes as $verse ) {
		$label_node    = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " name ")][1]', $verse )->item( 0 );
		$citation_node = $xpath->query( './/*[contains(concat(" ", normalize-space(@class), " "), " address ")]//a[1]', $verse )->item( 0 );
		$label         = usccb_todays_readings_node_text( $label_node );
		$citation      = usccb_todays_readings_node_text( $citation_node );

		if ( ! $label || ! $citation ) {
			continue;
		}

		$readings[] = array(
			'label'    => sanitize_text_field( $label ),
			'citation' => sanitize_text_field( $citation ),
			'url'      => usccb_todays_readings_absolute_url( $citation_node->getAttribute( 'href' ) ),
		);
	}

	return array(
		'title'      => usccb_todays_readings_node_text( $heading ),
		'lectionary' => $lectionary,
		'readings'   => $readings,
	);
}

/**
 * Gets permitted descriptions from the published RSS feed, keyed by URL.
 *
 * @return array
 */
function usccb_todays_readings_get_feed_items() {
	require_once ABSPATH . WPINC . '/feed.php';
	$feed = fetch_feed( USCCB_TODAYS_READINGS_FEED_URL );

	if ( is_wp_error( $feed ) ) {
		return array();
	}

	$items = array();
	foreach ( $feed->get_items( 0, 100 ) as $item ) {
		$url         = usccb_todays_readings_absolute_url( $item->get_permalink() );
		$description = wp_kses_post( $item->get_description() );
		if ( ! $url ) {
			continue;
		}

		$details = usccb_todays_readings_parse_reading_page( $description );
		if ( is_wp_error( $details ) ) {
			$details = array(
				'lectionary' => '',
				'readings'   => array(),
			);
		}

		$items[ strtolower( untrailingslashit( $url ) ) ] = array(
			'title'            => sanitize_text_field( $item->get_title() ),
			'description_html' => $description,
			'lectionary'       => $details['lectionary'],
			'readings'         => $details['readings'],
		);
	}

	return $items;
}

/**
 * Creates an index of older cached entries, allowing last-known-good enrichment.
 *
 * @param array $cache Existing cache.
 * @return array
 */
function usccb_todays_readings_old_entry_index( $cache ) {
	$index = array();

	foreach ( isset( $cache['days'] ) && is_array( $cache['days'] ) ? $cache['days'] : array() as $day ) {
		foreach ( isset( $day['entries'] ) && is_array( $day['entries'] ) ? $day['entries'] : array() as $entry ) {
			if ( ! empty( $entry['url'] ) ) {
				$index[ strtolower( untrailingslashit( $entry['url'] ) ) ] = $entry;
			}
		}
	}

	return $index;
}

/**
 * Refreshes today through today plus seven days, inclusive.
 *
 * Vigil entries retain the solemnity date but use the preceding civil date as
 * their Mass date. One additional celebration date is scanned so that a Vigil
 * on the final cache date is not missed.
 *
 * @return array|WP_Error
 */
function usccb_todays_readings_build_cache() {
	$timezone        = wp_timezone();
	$start           = new DateTimeImmutable( 'today', $timezone );
	$end             = $start->modify( '+7 days' );
	$scan_end        = $end->modify( '+1 day' );
	$months          = array();
	$cursor          = $start->modify( 'first day of this month' );
	$calendar_entries = array();

	while ( $cursor <= $scan_end ) {
		$months[ $cursor->format( 'Ym' ) ] = true;
		$cursor = $cursor->modify( 'first day of next month' );
	}

	foreach ( array_keys( $months ) as $month ) {
		$html = usccb_todays_readings_fetch_url( USCCB_TODAYS_READINGS_CALENDAR_URL . $month );
		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$parsed = usccb_todays_readings_parse_calendar( $html );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$calendar_entries = array_merge( $calendar_entries, $parsed );
	}

	$days = array();
	for ( $offset = 0; $offset <= 7; $offset++ ) {
		$date          = $start->modify( '+' . $offset . ' days' )->format( 'Y-m-d' );
		$days[ $date ] = array(
			'date'    => $date,
			'entries' => array(),
		);
	}

	$feed_items = usccb_todays_readings_get_feed_items();
	$old_cache  = get_option( USCCB_TODAYS_READINGS_CACHE_OPTION, array() );
	$old_index  = usccb_todays_readings_old_entry_index( $old_cache );
	$seen       = array();

	foreach ( $calendar_entries as $entry ) {
		if ( ! isset( $days[ $entry['mass_date'] ] ) || isset( $seen[ $entry['id'] ] ) ) {
			continue;
		}

		$seen[ $entry['id'] ] = true;
		$key                   = strtolower( untrailingslashit( $entry['url'] ) );

		if ( isset( $feed_items[ $key ] ) ) {
			if ( $feed_items[ $key ]['title'] ) {
				$entry['title'] = $feed_items[ $key ]['title'];
			}
			$entry['description_html'] = $feed_items[ $key ]['description_html'];
			$entry['lectionary']       = $feed_items[ $key ]['lectionary'];
			$entry['readings']         = $feed_items[ $key ]['readings'];
		}

		if ( isset( $old_index[ $key ] ) ) {
			if ( ! $entry['description_html'] ) {
				$entry['description_html'] = isset( $old_index[ $key ]['description_html'] ) ? $old_index[ $key ]['description_html'] : '';
			}
			$entry['lectionary'] = $entry['lectionary'] ? $entry['lectionary'] : ( isset( $old_index[ $key ]['lectionary'] ) ? $old_index[ $key ]['lectionary'] : '' );
			$entry['readings']   = $entry['readings'] ? $entry['readings'] : ( isset( $old_index[ $key ]['readings'] ) ? $old_index[ $key ]['readings'] : array() );
		}

		$days[ $entry['mass_date'] ]['entries'][] = $entry;
	}

	foreach ( $days as &$day ) {
		usort(
			$day['entries'],
			static function ( $left, $right ) {
				$order = array(
					'readings' => 10,
					'options'  => 10,
					'vigil'    => 20,
					'night'    => 30,
					'dawn'     => 40,
					'day'      => 50,
				);
				$comparison = ( $order[ $left['kind'] ] ?? 99 ) <=> ( $order[ $right['kind'] ] ?? 99 );
				return 0 !== $comparison ? $comparison : strcasecmp( $left['title'], $right['title'] );
			}
		);
	}
	unset( $day );

	$missing_dates = array_keys(
		array_filter(
			$days,
			static function ( $day ) {
				return empty( $day['entries'] );
			}
		)
	);

	return array(
		'schema_version' => 1,
		'generated_at'   => time(),
		'window_start'   => $start->format( 'Y-m-d' ),
		'window_end'     => $end->format( 'Y-m-d' ),
		'complete'       => empty( $missing_dates ),
		'missing_dates'  => $missing_dates,
		'days'           => $days,
	);
}

/**
 * Runs the locked background refresh and keeps the previous cache on failure.
 *
 * @return true|WP_Error
 */
function usccb_todays_readings_refresh_cache() {
	if ( get_transient( 'usccb_todays_readings_refresh_lock' ) ) {
		return new WP_Error( 'refresh_locked', __( 'A readings refresh is already running.', 'usccb-todays-readings' ) );
	}

	set_transient( 'usccb_todays_readings_refresh_lock', 1, 5 * MINUTE_IN_SECONDS );
	$cache = usccb_todays_readings_build_cache();
	delete_transient( 'usccb_todays_readings_refresh_lock' );

	if ( is_wp_error( $cache ) ) {
		update_option(
			USCCB_TODAYS_READINGS_STATUS_OPTION,
			array(
				'checked_at' => time(),
				'ok'         => false,
				'message'    => $cache->get_error_message(),
			),
			false
		);
		return $cache;
	}

	update_option( USCCB_TODAYS_READINGS_CACHE_OPTION, $cache, false );
	update_option(
		USCCB_TODAYS_READINGS_STATUS_OPTION,
		array(
			'checked_at' => time(),
			'ok'         => $cache['complete'],
			'message'    => $cache['complete'] ? '' : sprintf(
				/* translators: %s: comma-separated dates. */
				__( 'USCCB did not return entries for: %s', 'usccb-todays-readings' ),
				implode( ', ', $cache['missing_dates'] )
			),
		),
		false
	);

	if ( ! $cache['complete'] && ! wp_next_scheduled( USCCB_TODAYS_READINGS_CRON_HOOK, array( 'retry' ) ) ) {
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, USCCB_TODAYS_READINGS_CRON_HOOK, array( 'retry' ) );
	}

	return true;
}

/**
 * Returns all cached entries whose actual Mass date is today.
 *
 * @return array
 */
function usccb_todays_readings_get_today() {
	$cache = get_option( USCCB_TODAYS_READINGS_CACHE_OPTION, array() );
	$today = current_time( 'Y-m-d' );

	return isset( $cache['days'][ $today ] ) && is_array( $cache['days'][ $today ] )
		? $cache['days'][ $today ]
		: array(
			'date'    => $today,
			'entries' => array(),
		);
}
