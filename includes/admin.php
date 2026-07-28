<?php
/**
 * Administration screen and manual cache controls.
 *
 * @package USCCB_Todays_Readings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the plugin screen under Settings.
 */
function usccb_todays_readings_add_settings_page() {
	add_options_page(
		__( 'USCCB Readings', 'usccb-todays-readings' ),
		__( 'USCCB Readings', 'usccb-todays-readings' ),
		'manage_options',
		'usccb-todays-readings',
		'usccb_todays_readings_render_settings_page'
	);
}
add_action( 'admin_menu', 'usccb_todays_readings_add_settings_page' );

/**
 * Returns the settings screen URL.
 *
 * @param array $args Optional query arguments.
 * @return string
 */
function usccb_todays_readings_settings_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'options-general.php?page=usccb-todays-readings' ) );
}

/**
 * Redirects back to the settings screen after an admin action.
 *
 * @param string $notice Notice identifier.
 */
function usccb_todays_readings_admin_redirect( $notice ) {
	wp_safe_redirect( usccb_todays_readings_settings_url( array( 'usccb_notice' => sanitize_key( $notice ) ) ) );
	exit;
}

/**
 * Verifies administrator access and the shared admin-action nonce.
 */
function usccb_todays_readings_verify_admin_action() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to manage the readings cache.', 'usccb-todays-readings' ) );
	}

	check_admin_referer( 'usccb_todays_readings_admin_action' );
}

/**
 * Saves the selected daily refresh time.
 */
function usccb_todays_readings_save_settings() {
	usccb_todays_readings_verify_admin_action();

	$refresh_time = isset( $_POST['refresh_time'] ) ? sanitize_text_field( wp_unslash( $_POST['refresh_time'] ) ) : '';
	if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $refresh_time ) ) {
		usccb_todays_readings_admin_redirect( 'invalid_time' );
	}

	update_option(
		USCCB_TODAYS_READINGS_SETTINGS_OPTION,
		array( 'refresh_time' => $refresh_time ),
		false
	);
	usccb_todays_readings_reschedule();
	usccb_todays_readings_log(
		'info',
		'settings_updated',
		__( 'The daily refresh time was updated.', 'usccb-todays-readings' ),
		array( 'refresh_time' => $refresh_time, 'timezone' => wp_timezone_string() )
	);

	usccb_todays_readings_admin_redirect( 'settings_saved' );
}
add_action( 'admin_post_usccb_todays_readings_save_settings', 'usccb_todays_readings_save_settings' );

/**
 * Runs a manual cache or diagnostic action.
 */
function usccb_todays_readings_handle_admin_action() {
	usccb_todays_readings_verify_admin_action();

	$operation = isset( $_POST['cache_operation'] ) ? sanitize_key( wp_unslash( $_POST['cache_operation'] ) ) : '';

	if ( 'clear_log' === $operation ) {
		delete_option( USCCB_TODAYS_READINGS_LOG_OPTION );
		usccb_todays_readings_admin_redirect( 'log_cleared' );
	}

	if ( 'clear_reload' === $operation ) {
		usccb_todays_readings_clear_cache();
		usccb_todays_readings_log(
			'warning',
			'cache_cleared',
			__( 'The administrator cleared the readings cache before reloading.', 'usccb-todays-readings' )
		);
	} elseif ( 'reload' !== $operation ) {
		usccb_todays_readings_admin_redirect( 'invalid_action' );
	}

	$result = usccb_todays_readings_refresh_cache( 'manual' );
	usccb_todays_readings_admin_redirect( is_wp_error( $result ) ? 'reload_failed' : 'reload_succeeded' );
}
add_action( 'admin_post_usccb_todays_readings_cache_action', 'usccb_todays_readings_handle_admin_action' );

/**
 * Displays the result of the latest admin action.
 */
function usccb_todays_readings_admin_notice() {
	if ( empty( $_GET['page'] ) || 'usccb-todays-readings' !== $_GET['page'] || empty( $_GET['usccb_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}

	$notice = sanitize_key( wp_unslash( $_GET['usccb_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$status = get_option( USCCB_TODAYS_READINGS_STATUS_OPTION, array() );
	$map    = array(
		'settings_saved'  => array( 'success', __( 'Refresh settings saved and the daily event was rescheduled.', 'usccb-todays-readings' ) ),
		'invalid_time'    => array( 'error', __( 'Enter a valid refresh time.', 'usccb-todays-readings' ) ),
		'reload_succeeded'=> array( 'success', __( 'The readings cache was reloaded successfully.', 'usccb-todays-readings' ) ),
		'reload_failed'   => array( 'error', ! empty( $status['message'] ) ? $status['message'] : __( 'The readings cache could not be reloaded.', 'usccb-todays-readings' ) ),
		'log_cleared'     => array( 'success', __( 'The diagnostic log was cleared.', 'usccb-todays-readings' ) ),
		'invalid_action'  => array( 'error', __( 'That cache action was not recognized.', 'usccb-todays-readings' ) ),
	);

	if ( ! isset( $map[ $notice ] ) ) {
		return;
	}

	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( $map[ $notice ][0] ),
		esc_html( $map[ $notice ][1] )
	);
}
add_action( 'admin_notices', 'usccb_todays_readings_admin_notice' );

/**
 * Formats a stored timestamp in the site timezone.
 *
 * @param int $timestamp Unix timestamp.
 * @return string
 */
function usccb_todays_readings_admin_date( $timestamp ) {
	return $timestamp
		? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) . ' T', (int) $timestamp, wp_timezone() )
		: __( 'Not available', 'usccb-todays-readings' );
}

/**
 * Renders the settings and diagnostics screen.
 */
function usccb_todays_readings_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings       = usccb_todays_readings_get_settings();
	$cache          = get_option( USCCB_TODAYS_READINGS_CACHE_OPTION, array() );
	$status         = get_option( USCCB_TODAYS_READINGS_STATUS_OPTION, array() );
	$log            = get_option( USCCB_TODAYS_READINGS_LOG_OPTION, array() );
	$log            = is_array( $log ) ? $log : array();
	$next_refresh   = wp_next_scheduled( USCCB_TODAYS_READINGS_CRON_HOOK, array( 'daily' ) );
	$retry_refresh  = wp_next_scheduled( USCCB_TODAYS_READINGS_CRON_HOOK, array( 'retry' ) );
	$day_count      = ! empty( $cache['days'] ) && is_array( $cache['days'] ) ? count( $cache['days'] ) : 0;
	$entry_count    = 0;

	foreach ( ! empty( $cache['days'] ) ? $cache['days'] : array() as $day ) {
		$entry_count += ! empty( $day['entries'] ) && is_array( $day['entries'] ) ? count( $day['entries'] ) : 0;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'USCCB Readings Cache', 'usccb-todays-readings' ); ?></h1>
		<p>
			<?php
			printf(
				/* translators: %s: site timezone. */
				esc_html__( 'The cache covers today through seven days from today. All times below use the WordPress site timezone: %s.', 'usccb-todays-readings' ),
				esc_html( wp_timezone_string() )
			);
			?>
		</p>

		<h2><?php esc_html_e( 'Daily refresh', 'usccb-todays-readings' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="usccb_todays_readings_save_settings">
			<?php wp_nonce_field( 'usccb_todays_readings_admin_action' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="usccb-refresh-time"><?php esc_html_e( 'Refresh time', 'usccb-todays-readings' ); ?></label></th>
					<td>
						<input id="usccb-refresh-time" name="refresh_time" type="time" value="<?php echo esc_attr( $settings['refresh_time'] ); ?>" required>
						<p class="description">
							<?php esc_html_e( 'WP-Cron runs on the first site request at or after this time; it is not an exact-to-the-minute system scheduler.', 'usccb-todays-readings' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save refresh time', 'usccb-todays-readings' ) ); ?>
		</form>

		<h2><?php esc_html_e( 'Cache status', 'usccb-todays-readings' ); ?></h2>
		<table class="widefat striped" style="max-width: 900px">
			<tbody>
				<tr><th><?php esc_html_e( 'Last checked', 'usccb-todays-readings' ); ?></th><td><?php echo esc_html( usccb_todays_readings_admin_date( $status['checked_at'] ?? 0 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Last successful cache', 'usccb-todays-readings' ); ?></th><td><?php echo esc_html( usccb_todays_readings_admin_date( $cache['generated_at'] ?? 0 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Cached window', 'usccb-todays-readings' ); ?></th><td><?php echo ! empty( $cache['window_start'] ) ? esc_html( $cache['window_start'] . ' through ' . $cache['window_end'] ) : esc_html__( 'No cache is currently stored.', 'usccb-todays-readings' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Coverage', 'usccb-todays-readings' ); ?></th><td><?php echo esc_html( sprintf( __( '%1$d date buckets, %2$d entries', 'usccb-todays-readings' ), $day_count, $entry_count ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Latest result', 'usccb-todays-readings' ); ?></th><td><?php echo ! empty( $status['ok'] ) ? esc_html__( 'Successful', 'usccb-todays-readings' ) : esc_html( $status['message'] ?? __( 'No refresh has run yet.', 'usccb-todays-readings' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Next daily refresh', 'usccb-todays-readings' ); ?></th><td><?php echo esc_html( usccb_todays_readings_admin_date( $next_refresh ) ); ?></td></tr>
				<?php if ( $retry_refresh ) : ?>
					<tr><th><?php esc_html_e( 'Automatic retry', 'usccb-todays-readings' ); ?></th><td><?php echo esc_html( usccb_todays_readings_admin_date( $retry_refresh ) ); ?></td></tr>
				<?php endif; ?>
				<tr><th><?php esc_html_e( 'Outgoing User-Agent', 'usccb-todays-readings' ); ?></th><td><code><?php echo esc_html( usccb_todays_readings_request_headers()['User-Agent'] ); ?></code></td></tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Manual controls', 'usccb-todays-readings' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:12px">
			<input type="hidden" name="action" value="usccb_todays_readings_cache_action">
			<input type="hidden" name="cache_operation" value="reload">
			<?php wp_nonce_field( 'usccb_todays_readings_admin_action' ); ?>
			<?php submit_button( __( 'Reload now', 'usccb-todays-readings' ), 'primary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block" onsubmit="return confirm('<?php echo esc_js( __( 'This removes the current cache before contacting USCCB. If USCCB fails, the readings block will use its fallback link. Continue?', 'usccb-todays-readings' ) ); ?>');">
			<input type="hidden" name="action" value="usccb_todays_readings_cache_action">
			<input type="hidden" name="cache_operation" value="clear_reload">
			<?php wp_nonce_field( 'usccb_todays_readings_admin_action' ); ?>
			<?php submit_button( __( 'Clear cache and reload', 'usccb-todays-readings' ), 'secondary', 'submit', false ); ?>
		</form>
		<p class="description"><?php esc_html_e( 'Reload now is safer because it preserves the previous cache when the remote request fails.', 'usccb-todays-readings' ); ?></p>

		<h2><?php esc_html_e( 'Diagnostic log', 'usccb-todays-readings' ); ?></h2>
		<p><?php esc_html_e( 'The plugin retains the 50 most recent non-sensitive events.', 'usccb-todays-readings' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:12px">
			<input type="hidden" name="action" value="usccb_todays_readings_cache_action">
			<input type="hidden" name="cache_operation" value="clear_log">
			<?php wp_nonce_field( 'usccb_todays_readings_admin_action' ); ?>
			<?php submit_button( __( 'Clear diagnostic log', 'usccb-todays-readings' ), 'secondary', 'submit', false ); ?>
		</form>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Time', 'usccb-todays-readings' ); ?></th><th><?php esc_html_e( 'Level', 'usccb-todays-readings' ); ?></th><th><?php esc_html_e( 'Event', 'usccb-todays-readings' ); ?></th><th><?php esc_html_e( 'Message', 'usccb-todays-readings' ); ?></th><th><?php esc_html_e( 'Context', 'usccb-todays-readings' ); ?></th></tr></thead>
			<tbody>
				<?php if ( ! $log ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No diagnostic events have been recorded.', 'usccb-todays-readings' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $log as $record ) : ?>
						<tr>
							<td><?php echo esc_html( usccb_todays_readings_admin_date( $record['timestamp'] ?? 0 ) ); ?></td>
							<td><code><?php echo esc_html( strtoupper( $record['level'] ?? 'info' ) ); ?></code></td>
							<td><code><?php echo esc_html( $record['event'] ?? '' ); ?></code></td>
							<td><?php echo esc_html( $record['message'] ?? '' ); ?></td>
							<td><code><?php echo esc_html( ! empty( $record['context'] ) ? wp_json_encode( $record['context'], JSON_UNESCAPED_SLASHES ) : '—' ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
