<?php
/**
 * Email: failed renewal.
 *
 * @var WC_Order $order
 * @var array    $sub
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;
?>
<p><?php esc_html_e( 'Unfortunately, the automatic subscription charge failed.', 'single-client-hub' ); ?></p>
<p>
	<?php
	printf(
		/* translators: 1: subscription id 2: order id */
		esc_html__( 'Subscription #%1$s, renewal order #%2$s.', 'single-client-hub' ),
		(int) $sub['id'],
		(int) $order->get_id()
	);
	?>
</p>
<p><?php esc_html_e( 'Update your payment method in My Account or pay the order manually.', 'single-client-hub' ); ?></p>
<p><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"><?php esc_html_e( 'Go to My Account', 'single-client-hub' ); ?></a></p>
