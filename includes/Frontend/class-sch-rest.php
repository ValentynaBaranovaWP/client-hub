<?php
/**
 * REST API for hub (cart snapshot, account, coupons).
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

class SCH_REST {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'sch/v1',
			'/hub/cart',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'cart' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'sch/v1',
			'/hub/account',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'account' ),
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
			)
		);

		register_rest_route(
			'sch/v1',
			'/hub/coupon',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'apply_coupon' ),
				'permission_callback' => static function () {
					return true;
				},
			)
		);

		register_rest_route(
			'sch/v1',
			'/hub/coupon/remove',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'remove_coupon' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'sch/v1',
			'/hub/cart/remove',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'remove_cart_item' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'cart_item_key' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'sch/v1',
			'/hub/reorder',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'reorder' ),
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
			)
		);

		register_rest_route(
			'sch/v1',
			'/hub/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'subscribe' ),
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
			)
		);
	}

	public static function cart() {
		self::load_cart();

		$items = array();
		foreach ( WC()->cart->get_cart() as $key => $cart_item ) {
			$product = $cart_item['data'];
			$items[] = array(
				'key'      => $key,
				'name'     => $product ? $product->get_name() : '',
				'quantity' => $cart_item['quantity'],
				'total'    => WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ),
			);
		}

		$coupons_applied = array();
		foreach ( WC()->cart->get_applied_coupons() as $code ) {
			$coupons_applied[] = array(
				'code'   => $code,
				'discount'=> wc_price( WC()->cart->get_coupon_discount_amount( $code, WC()->cart->display_cart_ex_tax ) ),
			);
		}

		$rewards = array();
		if ( is_user_logged_in() ) {
			$rewards = SCH_Coupons::get_user_reward_codes( get_current_user_id() );
		}

		return rest_ensure_response(
			array(
				'count'           => WC()->cart->get_cart_contents_count(),
				'items'           => $items,
				'subtotal'        => WC()->cart->get_cart_subtotal(),
				'total'           => WC()->cart->get_total(),
				'coupons_applied' => $coupons_applied,
				'reward_coupons'  => $rewards,
				'cart_url'        => wc_get_cart_url(),
				'checkout_url'    => wc_get_checkout_url(),
			)
		);
	}

	public static function account() {
		$user_id = get_current_user_id();
		$orders  = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 10,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'objects',
			)
		);

		$list = array();
		foreach ( $orders as $order ) {
			$list[] = array(
				'id'         => $order->get_id(),
				'number'     => $order->get_order_number(),
				'status'     => $order->get_status(),
				'status_label'=> SCH_Order_Statuses::get_label( $order->get_status() ),
				'total'      => $order->get_formatted_order_total(),
				'date'       => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y' ) : '',
				'view_url'   => $order->get_view_order_url(),
				'can_subscribe'=> ! $order->get_meta( '_sch_subscription_id' ),
			);
		}

		$subs = array();
		foreach ( SCH_Subscriptions::get_for_user( $user_id ) as $sub ) {
			$subs[] = array(
				'id'           => (int) $sub['id'],
				'status'       => $sub['status'],
				'status_label' => SCH_Subscriptions::status_label( $sub['status'] ),
				'amount'       => wc_price( $sub['amount'] ),
				'next_payment' => $sub['next_payment'] ? date_i18n( 'd.m.Y H:i', strtotime( $sub['next_payment'] ) ) : '—',
				'product_id'   => (int) $sub['product_id'],
			);
		}

		return rest_ensure_response(
			array(
				'orders'        => $list,
				'subscriptions' => $subs,
				'account_url'   => wc_get_page_permalink( 'myaccount' ),
			)
		);
	}

	public static function apply_coupon( WP_REST_Request $request ) {
		$code = sanitize_text_field( $request->get_param( 'code' ) );
		if ( ! $code ) {
			return new WP_Error( 'sch_coupon_empty', __( 'Enter a coupon code', 'single-client-hub' ), array( 'status' => 400 ) );
		}

		if ( null === WC()->cart ) {
			self::load_cart();
		}

		$result = WC()->cart->apply_coupon( $code );
		$notices = wc_get_notices( 'error' );
		wc_clear_notices();

		if ( ! $result ) {
			$msg = __( 'Could not apply coupon', 'single-client-hub' );
			if ( $notices ) {
				$msg = wp_strip_all_tags( $notices[0]['notice'] ?? $msg );
			}
			return new WP_Error( 'sch_coupon_invalid', $msg, array( 'status' => 400 ) );
		}

		WC()->cart->calculate_totals();
		self::persist_cart_session();

		return self::cart();
	}

	public static function remove_coupon( WP_REST_Request $request ) {
		$code = sanitize_text_field( $request->get_param( 'code' ) );
		if ( null === WC()->cart ) {
			self::load_cart();
		}
		if ( $code ) {
			WC()->cart->remove_coupon( $code );
			WC()->cart->calculate_totals();
			self::persist_cart_session();
		}
		return self::cart();
	}

	public static function remove_cart_item( WP_REST_Request $request ) {
		$cart_item_key = sanitize_text_field( $request->get_param( 'cart_item_key' ) );
		if ( ! $cart_item_key ) {
			$params = $request->get_json_params();
			if ( is_array( $params ) && ! empty( $params['cart_item_key'] ) ) {
				$cart_item_key = sanitize_text_field( $params['cart_item_key'] );
			}
		}

		if ( ! $cart_item_key ) {
			return new WP_Error( 'sch_cart_key', __( 'Invalid cart item', 'single-client-hub' ), array( 'status' => 400 ) );
		}

		self::load_cart();

		$cart = WC()->cart->get_cart();
		if ( ! isset( $cart[ $cart_item_key ] ) ) {
			return new WP_Error( 'sch_cart_missing', __( 'Item not found in cart', 'single-client-hub' ), array( 'status' => 404 ) );
		}

		$removed = WC()->cart->remove_cart_item( $cart_item_key );
		if ( ! $removed ) {
			return new WP_Error( 'sch_cart_remove', __( 'Could not remove item', 'single-client-hub' ), array( 'status' => 400 ) );
		}

		WC()->cart->calculate_totals();
		self::persist_cart_session();

		return self::cart();
	}

	private static function load_cart() {
		if ( null === WC()->cart ) {
			wc_load_cart();
		}

		if ( WC()->cart ) {
			WC()->cart->get_cart();
		}
	}

	private static function persist_cart_session() {
		if ( WC()->cart ) {
			WC()->cart->set_session();
			WC()->cart->maybe_set_cart_cookies();
		}
	}

	public static function reorder( WP_REST_Request $request ) {
		$order_id = (int) $request->get_param( 'order_id' );
		$result   = SCH_Account_Actions::reorder( $order_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public static function subscribe( WP_REST_Request $request ) {
		$order_id = (int) $request->get_param( 'order_id' );
		$result   = SCH_Account_Actions::convert_to_subscription_cart( $order_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}
}
