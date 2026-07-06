<?php
/**
 * Oma tili -hallintapaneeli (teeman override, #496).
 *
 * Perustuu WooCommercen myaccount/dashboard.php-templateen (@version 4.4.0).
 * Korvaa oletustekstikappaleet tervehdyksellä, pikakorteilla (Tilaukset,
 * Osoitteet, Tilin tiedot) ja tuoreimpien tilausten listalla. WooCommercen
 * omat hookit (woocommerce_account_dashboard ym.) säilyvät ennallaan.
 * Ulkoasu: account.css; apufunktiot: inc/woocommerce-my-account.php.
 *
 * @package Rytkoset_Theme
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rytkoset_first_name     = trim( (string) $current_user->first_name );
$rytkoset_greeting_name  = '' !== $rytkoset_first_name ? $rytkoset_first_name : $current_user->display_name;
$rytkoset_orders_summary = rytkoset_theme_get_account_dashboard_orders_summary();
$rytkoset_recent_orders  = rytkoset_theme_get_account_recent_orders( 3 );
$rytkoset_orders_url     = wc_get_account_endpoint_url( 'orders' );
$rytkoset_addresses_url  = wc_get_account_endpoint_url( 'edit-address' );
$rytkoset_account_url    = wc_get_account_endpoint_url( 'edit-account' );

if ( $rytkoset_orders_summary['count'] > 0 ) {
	if ( '' !== $rytkoset_orders_summary['latest'] ) {
		$rytkoset_orders_meta = sprintf(
			/* translators: 1: tilausten määrä, 2: uusimman tilauksen päiväys. */
			_n( '%1$d tilaus · uusin %2$s', '%1$d tilausta · uusin %2$s', $rytkoset_orders_summary['count'], 'rytkoset-theme' ),
			$rytkoset_orders_summary['count'],
			$rytkoset_orders_summary['latest']
		);
	} else {
		$rytkoset_orders_meta = sprintf(
			/* translators: %d: tilausten määrä. */
			_n( '%d tilaus', '%d tilausta', $rytkoset_orders_summary['count'], 'rytkoset-theme' ),
			$rytkoset_orders_summary['count']
		);
	}
} else {
	$rytkoset_orders_meta = __( 'Ei vielä tilauksia', 'rytkoset-theme' );
}
?>

<h2 class="rytkoset-account-h2">
	<?php
	/* translators: %s: käyttäjän etunimi tai näyttönimi. */
	printf( esc_html__( 'Hei %s!', 'rytkoset-theme' ), esc_html( $rytkoset_greeting_name ) );
	?>
</h2>

<p class="rytkoset-account-lede">
	<?php
	printf(
		wp_kses(
			/* translators: 1: tilaukset-URL, 2: osoitteet-URL, 3: tilin tiedot -URL. */
			__( 'Täältä näet <a href="%1$s">tuoreimmat tilauksesi</a>, hallinnoit <a href="%2$s">toimitus- ja laskutusosoitteita</a> ja muokkaat <a href="%3$s">salasanaa ja tilin tietoja</a>.', 'rytkoset-theme' ),
			array( 'a' => array( 'href' => array() ) )
		),
		esc_url( $rytkoset_orders_url ),
		esc_url( $rytkoset_addresses_url ),
		esc_url( $rytkoset_account_url )
	);
	?>
</p>

<div class="rytkoset-account-cards">
	<a href="<?php echo esc_url( $rytkoset_orders_url ); ?>" class="rytkoset-account-card">
		<span class="rytkoset-account-card__icon"><?php echo rytkoset_theme_inline_icon( 'package', 'ui' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitoitu SVG teeman omasta ikonikansiosta (inc/icons.php). ?></span>
		<span class="rytkoset-account-card__title"><?php esc_html_e( 'Tilaukset', 'rytkoset-theme' ); ?></span>
		<span class="rytkoset-account-card__meta"><?php echo esc_html( $rytkoset_orders_meta ); ?></span>
	</a>
	<a href="<?php echo esc_url( $rytkoset_addresses_url ); ?>" class="rytkoset-account-card">
		<span class="rytkoset-account-card__icon"><?php echo rytkoset_theme_inline_icon( 'map-pin', 'ui' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitoitu SVG teeman omasta ikonikansiosta (inc/icons.php). ?></span>
		<span class="rytkoset-account-card__title"><?php esc_html_e( 'Osoitteet', 'rytkoset-theme' ); ?></span>
		<span class="rytkoset-account-card__meta"><?php esc_html_e( 'Laskutus- ja toimitusosoite', 'rytkoset-theme' ); ?></span>
	</a>
	<a href="<?php echo esc_url( $rytkoset_account_url ); ?>" class="rytkoset-account-card">
		<span class="rytkoset-account-card__icon"><?php echo rytkoset_theme_inline_icon( 'user', 'ui' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitoitu SVG teeman omasta ikonikansiosta (inc/icons.php). ?></span>
		<span class="rytkoset-account-card__title"><?php esc_html_e( 'Tilin tiedot', 'rytkoset-theme' ); ?></span>
		<span class="rytkoset-account-card__meta"><?php esc_html_e( 'Nimi, sähköposti ja salasanan vaihto', 'rytkoset-theme' ); ?></span>
	</a>
</div>

<?php if ( ! empty( $rytkoset_recent_orders ) ) : ?>
	<div class="rytkoset-account-section">
		<div class="rytkoset-account-section__head">
			<h3 class="rytkoset-account-section__title"><?php esc_html_e( 'Tuoreimmat tilaukset', 'rytkoset-theme' ); ?></h3>
			<a class="rytkoset-account-section__link" href="<?php echo esc_url( $rytkoset_orders_url ); ?>"><?php esc_html_e( 'Näytä kaikki →', 'rytkoset-theme' ); ?></a>
		</div>
		<table class="woocommerce-orders-table shop_table shop_table_responsive my_account_orders">
			<thead>
				<tr>
					<th scope="col" class="woocommerce-orders-table__header"><span class="nobr"><?php esc_html_e( 'Tilaus', 'rytkoset-theme' ); ?></span></th>
					<th scope="col" class="woocommerce-orders-table__header"><span class="nobr"><?php esc_html_e( 'Päiväys', 'rytkoset-theme' ); ?></span></th>
					<th scope="col" class="woocommerce-orders-table__header"><span class="nobr"><?php esc_html_e( 'Tila', 'rytkoset-theme' ); ?></span></th>
					<th scope="col" class="woocommerce-orders-table__header"><span class="nobr"><?php esc_html_e( 'Yhteensä', 'rytkoset-theme' ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rytkoset_recent_orders as $rytkoset_order ) : ?>
					<tr class="woocommerce-orders-table__row order">
						<th class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Tilaus', 'rytkoset-theme' ); ?>" scope="row">
							<a href="<?php echo esc_url( $rytkoset_order->get_view_order_url() ); ?>">
								<?php echo esc_html( '#' . $rytkoset_order->get_order_number() ); ?>
							</a>
						</th>
						<td class="woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date" data-title="<?php esc_attr_e( 'Päiväys', 'rytkoset-theme' ); ?>">
							<?php $rytkoset_order_date = $rytkoset_order->get_date_created(); ?>
							<?php if ( $rytkoset_order_date ) : ?>
								<time datetime="<?php echo esc_attr( $rytkoset_order_date->date( 'c' ) ); ?>"><?php echo esc_html( wc_format_datetime( $rytkoset_order_date ) ); ?></time>
							<?php endif; ?>
						</td>
						<td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Tila', 'rytkoset-theme' ); ?>">
							<?php echo wp_kses_post( rytkoset_theme_get_order_status_chip( $rytkoset_order ) ); ?>
						</td>
						<td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Yhteensä', 'rytkoset-theme' ); ?>">
							<?php echo wp_kses_post( $rytkoset_order->get_formatted_order_total() ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>

<?php
/**
 * My Account dashboard.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_account_dashboard' );

/**
 * Deprecated woocommerce_before_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action( 'woocommerce_before_my_account' );

/**
 * Deprecated woocommerce_after_my_account action.
 *
 * @deprecated 2.6.0
 */
do_action( 'woocommerce_after_my_account' );

/* Omit closing PHP tag at the end of PHP files to avoid "headers already sent" issues. */
