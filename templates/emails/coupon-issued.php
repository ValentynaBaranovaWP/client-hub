<?php
/**
 * Email: coupon issued.
 *
 * @var WC_Order $order
 * @var string   $code
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;
?>
<p><?php printf( esc_html__( 'Thank you for paying order #%s!', 'single-client-hub' ), esc_html( $order->get_order_number() ) ); ?></p>
<p><?php esc_html_e( 'You have received a personal coupon:', 'single-client-hub' ); ?></p>
<p style="font-size:1.4em;font-weight:700;letter-spacing:.08em"><?php echo esc_html( $code ); ?></p>
<p><?php esc_html_e( 'The code appears in the hub mini-cart and can be entered at checkout.', 'single-client-hub' ); ?></p>
