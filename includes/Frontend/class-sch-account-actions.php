<?php
/**
 * Account actions: reorder service / switch to subscription flow.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Account_Actions
 */
class SCH_Account_Actions {

	/**
	 * Init hooks on My Account order list.
	 */
	public static function init() {
		add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'order_actions' ), 10, 2 );
		add_action( 'woocommerce_view_order', array( __CLASS__, 'view_order_buttons' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'handle_query_actions' ) );
	}

	/**
	 * Add actions to orders table.
	 *
	 * @param array    $actions Actions.
	 * @param WC_Order $order   Order.
	 * @return array
	 */
	public static function order_actions( $actions, $order ) {
		$actions['sch_reorder'] = array(
			'url'  => wp_nonce_url(
				add_query_arg(
					array(
						'sch_action' => 'reorder',
						'order_id'   => $order->get_id(),
					),
					wc_get_page_permalink( 'myaccount' )
				),
				'sch_account_action'
			),
			'name' => __( 'Reorder service', 'single-client-hub' ),
		);

		if ( ! $order->get_meta( '_sch_subscription_id' ) ) {
			$actions['sch_subscribe'] = array(
				'url'  => wp_nonce_url(
					add_query_arg(
						array(
							'sch_action' => 'subscribe',
							'order_id'   => $order->get_id(),
						),
						wc_get_page_permalink( 'myaccount' )
					),
					'sch_account_action'
				),
				'name' => __( 'Subscribe', 'single-client-hub' ),
			);
		}

		return $actions;
	}

	/**
	 * Buttons on single order view.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function view_order_buttons( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		echo '<p class="sch-order-actions">';
		printf(
			'<a class="button" href="%s">%s</a> ',
			esc_url(
				wp_nonce_url(
					add_query_arg(
						array(
							'sch_action' => 'reorder',
							'order_id'   => $order_id,
						),
						wc_get_cart_url()
					),
					'sch_account_action'
				)
			),
			esc_html__( 'Reorder service', 'single-client-hub' )
		);
		if ( ! $order->get_meta( '_sch_subscription_id' ) ) {
			printf(
				'<a class="button" href="%s">%s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'sch_action' => 'subscribe',
								'order_id'   => $order_id,
							),
							wc_get_checkout_url()
						),
						'sch_account_action'
					)
				),
				esc_html__( 'Check out as subscription', 'single-client-hub' )
			);
		}
		echo '</p>';
	}

	/**
	 * Handle GET actions.
	 */
	public static function handle_query_actions() {
		if ( empty( $_GET['sch_action'] ) || empty( $_GET['order_id'] ) ) { // phpcs:ignore
			return;
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sch_account_action' ) ) { // phpcs:ignore
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}

		$action   = sanitize_key( wp_unslash( $_GET['sch_action'] ) ); // phpcs:ignore
		$order_id = (int) $_GET['order_id']; // phpcs:ignore
		$user_id  = get_current_user_id();

		if ( 'reorder' === $action ) {
			$result = self::reorder( $order_id, $user_id );
			if ( ! is_wp_error( $result ) ) {
				wp_safe_redirect( wc_get_cart_url() );
				exit;
			}
			wc_add_notice( $result->get_error_message(), 'error' );
		}

		if ( 'subscribe' === $action ) {
			$result = self::convert_to_subscription_cart( $order_id, $user_id );
			if ( ! is_wp_error( $result ) ) {
				wp_safe_redirect( wc_get_checkout_url() );
				exit;
			}
			wc_add_notice( $result->get_error_message(), 'error' );
		}
	}

	/**
	 * Fill cart from order (draft reorder).
	 *
	 * @param int $order_id Order.
	 * @param int $user_id  User.
	 * @return array|WP_Error
	 */
	public static function reorder( $order_id, $user_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || (int) $order->get_user_id() !== (int) $user_id ) {
			return new WP_Error( 'sch_forbidden', __( 'Order not found', 'single-client-hub' ) );
		}

		if ( ! WC()->cart ) {
			wc_load_cart();
		}
		if ( ! WC()->cart ) {
			return new WP_Error( 'sch_cart', __( 'Cart unavailable', 'single-client-hub' ) );
		}

		WC()->cart->empty_cart();
		foreach ( $order->get_items() as $item ) {
			$product_id   = $item->get_product_id();
			$variation_id = $item->get_variation_id();
			$qty          = $item->get_quantity();
			$variation    = array();
			if ( $variation_id ) {
				$product = wc_get_product( $variation_id );
				if ( $product ) {
					$variation = $product->get_variation_attributes();
				}
			}
			WC()->cart->add_to_cart( $product_id, $qty, $variation_id, $variation );
		}

		WC()->session->set( 'sch_create_subscription', 'no' );

		SCH_Plugin::log_event(
			'order_reordered',
			__( 'Service reordered from a past order', 'single-client-hub' ),
			array(
				'user_id'  => $user_id,
				'order_id' => $order_id,
			)
		);

		return array(
			'redirect' => wc_get_cart_url(),
			'message'  => __( 'Items added to cart', 'single-client-hub' ),
		);
	}

	/**
	 * Same as reorder but flag subscription at checkout.
	 *
	 * @param int $order_id Order.
	 * @param int $user_id  User.
	 * @return array|WP_Error
	 */
	public static function convert_to_subscription_cart( $order_id, $user_id ) {
		$result = self::reorder( $order_id, $user_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		WC()->session->set( 'sch_create_subscription', 'yes' );
		add_filter(
			'woocommerce_checkout_get_value',
			static function ( $value, $input ) {
				if ( 'sch_create_subscription' === $input ) {
					return 1;
				}
				return $value;
			},
			10,
			2
		);

		SCH_Plugin::log_event(
			'order_to_subscription',
			__( 'Switched to subscription checkout', 'single-client-hub' ),
			array(
				'user_id'  => $user_id,
				'order_id' => $order_id,
			)
		);

		return array(
			'redirect' => wc_get_checkout_url(),
			'message'  => __( 'Subscription checkout: confirm on the checkout page', 'single-client-hub' ),
		);
	}
}
