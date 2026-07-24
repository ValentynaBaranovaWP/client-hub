<?php
/**
 * Email: order status changed.
 *
 * @var WC_Order $order
 * @var string   $from
 * @var string   $to
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;
?>
<p><?php printf( esc_html__( 'Hello, %s!', 'single-client-hub' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<p>
	<?php
	printf(
		/* translators: 1: order number 2: from 3: to */
		esc_html__( 'Order #%1$s status changed: %2$s → %3$s.', 'single-client-hub' ),
		esc_html( $order->get_order_number() ),
		esc_html( $from ),
		esc_html( $to )
	);
	?>
</p>
<p><a href="<?php echo esc_url( $order->get_view_order_url() ); ?>"><?php esc_html_e( 'View order', 'single-client-hub' ); ?></a></p>
