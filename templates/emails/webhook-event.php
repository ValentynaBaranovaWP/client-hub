<?php
/**
 * Email: webhook payment event.
 *
 * @var WC_Order $order
 * @var string   $gateway
 * @var string   $event
 * @var array    $body
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;
?>
<p><?php printf( esc_html__( 'Payment update for order #%s', 'single-client-hub' ), esc_html( $order->get_order_number() ) ); ?></p>
<ul>
	<li><strong><?php esc_html_e( 'Gateway', 'single-client-hub' ); ?>:</strong> <?php echo esc_html( $gateway ); ?></li>
	<li><strong><?php esc_html_e( 'Event', 'single-client-hub' ); ?>:</strong> <?php echo esc_html( $event ); ?></li>
	<li><strong><?php esc_html_e( 'Current status', 'single-client-hub' ); ?>:</strong> <?php echo esc_html( SCH_Order_Statuses::get_label( $order->get_status() ) ); ?></li>
</ul>
<p><a href="<?php echo esc_url( $order->get_view_order_url() ); ?>"><?php esc_html_e( 'Order details', 'single-client-hub' ); ?></a></p>
