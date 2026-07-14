<?php
/**
 * Server-side rendering for the Today’s Readings block.
 *
 * @var array $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$calendar_url        = 'https://bible.usccb.org/readings/calendar';
$today_url           = sprintf(
	'https://bible.usccb.org/bible/readings/%s.cfm',
	wp_date( 'mdy', time(), wp_timezone() )
);
$today_label         = wp_date( get_option( 'date_format' ), time(), wp_timezone() );
$heading             = isset( $attributes['heading'] ) ? sanitize_text_field( $attributes['heading'] ) : __( 'Today’s Readings', 'usccb-todays-readings' );
$show_description    = ! isset( $attributes['showDescription'] ) || (bool) $attributes['showDescription'];
$wrapper_attributes  = get_block_wrapper_attributes( array( 'class' => 'usccb-todays-readings' ) );

require_once ABSPATH . WPINC . '/feed.php';

$feed = fetch_feed( USCCB_TODAYS_READINGS_FEED_URL );
$item = null;

if ( ! is_wp_error( $feed ) ) {
	$items    = $feed->get_items( 0, 10 );
	$today    = current_time( 'Y-m-d' );
	$timezone = wp_timezone();

	foreach ( $items as $candidate ) {
		$timestamp = $candidate->get_date( 'U' );
		if ( $timestamp && wp_date( 'Y-m-d', (int) $timestamp, $timezone ) === $today ) {
			$item = $candidate;
			break;
		}
	}

	if ( ! $item && ! empty( $items ) ) {
		$item = $items[0];
	}
}
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $heading ) : ?>
		<h2 class="usccb-todays-readings__heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( $item ) : ?>
		<?php
		$item_title       = $item->get_title();
		$item_url         = $item->get_permalink();
		$item_description = $item->get_description();
		$item_date        = $item->get_date( get_option( 'date_format' ) );
		?>
		<?php if ( $item_date ) : ?>
			<p class="usccb-todays-readings__date"><?php echo esc_html( $item_date ); ?></p>
		<?php endif; ?>
		<h3 class="usccb-todays-readings__title">
			<a href="<?php echo esc_url( $item_url ? $item_url : $calendar_url ); ?>"><?php echo esc_html( $item_title ); ?></a>
		</h3>
		<?php if ( $show_description && $item_description ) : ?>
			<div class="usccb-todays-readings__description"><?php echo wp_kses_post( $item_description ); ?></div>
		<?php endif; ?>
		<p class="usccb-todays-readings__source">
			<a href="<?php echo esc_url( $item_url ? $item_url : $calendar_url ); ?>"><?php esc_html_e( 'Read today’s readings at USCCB', 'usccb-todays-readings' ); ?></a>
		</p>
	<?php else : ?>
		<p><?php esc_html_e( 'Today’s readings are temporarily unavailable.', 'usccb-todays-readings' ); ?></p>
		<p class="usccb-todays-readings__source">
			<a href="<?php echo esc_url( $today_url ); ?>">
				<?php
				printf(
					/* translators: %s: Today's date. */
					esc_html__( 'Read the USCCB readings for %s', 'usccb-todays-readings' ),
					esc_html( $today_label )
				);
				?>
			</a>
		</p>
	<?php endif; ?>
</section>
