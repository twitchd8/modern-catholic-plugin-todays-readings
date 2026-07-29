<?php
/**
 * Server-side rendering for the Today’s Readings block.
 *
 * The saved block is only a placeholder. Current cached data is rendered here.
 *
 * @var array $attributes Block attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$today_url             = sprintf(
	'https://bible.usccb.org/bible/readings/%s.cfm',
	wp_date( 'mdy', time(), wp_timezone() )
);
$today_label           = wp_date( get_option( 'date_format' ), time(), wp_timezone() );
$heading               = isset( $attributes['heading'] ) ? sanitize_text_field( $attributes['heading'] ) : __( 'Today’s Readings', 'usccb-todays-readings' );
$layout                = isset( $attributes['layout'] ) && in_array( $attributes['layout'], array( 'compact', 'cards' ), true ) ? $attributes['layout'] : 'cards';
$show_date             = ! isset( $attributes['showDate'] ) || (bool) $attributes['showDate'];
$show_liturgical_color = ! isset( $attributes['showLiturgicalColor'] ) || (bool) $attributes['showLiturgicalColor'];
$show_lectionary       = ! isset( $attributes['showLectionary'] ) || (bool) $attributes['showLectionary'];
$show_citations        = ! isset( $attributes['showCitations'] ) || (bool) $attributes['showCitations'];
$show_description      = ! isset( $attributes['showDescription'] ) || (bool) $attributes['showDescription'];
$show_source_link      = ! isset( $attributes['showSourceLink'] ) || (bool) $attributes['showSourceLink'];
$wrapper_attributes    = get_block_wrapper_attributes(
	array( 'class' => 'usccb-todays-readings is-layout-' . $layout )
);
$day                   = usccb_todays_readings_get_today();
$entries               = $day['entries'];
?>
<section <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $heading ) : ?>
		<h2 class="usccb-todays-readings__heading"><?php echo esc_html( $heading ); ?></h2>
	<?php endif; ?>

	<?php if ( $entries ) : ?>
		<?php if ( $show_date ) : ?>
			<p class="usccb-todays-readings__date"><?php echo esc_html( $today_label ); ?></p>
		<?php endif; ?>
		<div class="usccb-todays-readings__entries">
			<?php foreach ( $entries as $entry ) : ?>
				<?php
				$color = isset( $entry['liturgical_color'] ) ? sanitize_key( $entry['liturgical_color'] ) : 'unknown';
				$kind  = isset( $entry['kind'] ) ? $entry['kind'] : 'readings';

				if ( 'vigil' === $kind ) {
					$celebration_timestamp = strtotime( $entry['celebration_date'] . ' 12:00:00' );
					$variant              = sprintf(
						/* translators: %s: celebration date. */
						__( 'Vigil Mass for %s (late afternoon or evening)', 'usccb-todays-readings' ),
						wp_date( get_option( 'date_format' ), $celebration_timestamp, wp_timezone() )
					);
				} elseif ( 'day' === $kind ) {
					$variant = __( 'Mass during the Day', 'usccb-todays-readings' );
				} elseif ( 'night' === $kind ) {
					$variant = __( 'Mass during the Night', 'usccb-todays-readings' );
				} elseif ( 'dawn' === $kind ) {
					$variant = __( 'Mass at Dawn', 'usccb-todays-readings' );
				} elseif ( 'options' === $kind ) {
					$variant = __( 'Reading options', 'usccb-todays-readings' );
				} else {
					$variant = __( 'Daily Mass', 'usccb-todays-readings' );
				}
				?>
				<article class="usccb-todays-readings__entry is-liturgical-<?php echo esc_attr( $color ); ?>">
					<div class="usccb-todays-readings__meta">
						<span class="usccb-todays-readings__variant"><?php echo esc_html( $variant ); ?></span>
						<?php if ( $show_liturgical_color && 'unknown' !== $color ) : ?>
							<span class="usccb-todays-readings__color">
								<?php
								printf(
									/* translators: %s: liturgical color. */
									esc_html__( 'Liturgical color: %s', 'usccb-todays-readings' ),
									esc_html( ucfirst( $color ) )
								);
								?>
							</span>
						<?php endif; ?>
					</div>
					<h3 class="usccb-todays-readings__title">
						<a href="<?php echo esc_url( $entry['url'] ); ?>"><?php echo esc_html( $entry['title'] ); ?></a>
					</h3>
					<?php if ( $show_lectionary && ! empty( $entry['lectionary'] ) ) : ?>
						<p class="usccb-todays-readings__lectionary">
							<?php
							printf(
								/* translators: %s: lectionary number. */
								esc_html__( 'Lectionary: %s', 'usccb-todays-readings' ),
								esc_html( $entry['lectionary'] )
							);
							?>
						</p>
					<?php endif; ?>
					<?php if ( $show_citations && ! empty( $entry['readings'] ) ) : ?>
						<ul class="usccb-todays-readings__citations">
							<?php foreach ( $entry['readings'] as $reading ) : ?>
								<li>
									<strong><?php echo esc_html( $reading['label'] ); ?>:</strong>
									<a href="<?php echo esc_url( $reading['url'] ); ?>"><?php echo esc_html( $reading['citation'] ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( $show_description && ! empty( $entry['description_html'] ) ) : ?>
						<div class="usccb-todays-readings__description"><?php echo wp_kses_post( $entry['description_html'] ); ?></div>
					<?php endif; ?>
					<?php if ( $show_source_link ) : ?>
						<p class="usccb-todays-readings__source">
							<a href="<?php echo esc_url( $entry['url'] ); ?>"><?php esc_html_e( 'Read at USCCB', 'usccb-todays-readings' ); ?></a>
						</p>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'Today’s readings are temporarily unavailable.', 'usccb-todays-readings' ); ?></p>
		<?php if ( $show_source_link ) : ?>
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
	<?php endif; ?>
</section>
