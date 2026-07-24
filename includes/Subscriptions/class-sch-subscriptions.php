<?php
/**
 * Subscriptions CRUD + My Account UI.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Subscriptions
 */
class SCH_Subscriptions {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_item' ) );
		add_action( 'woocommerce_account_sch-subscriptions_endpoint', array( __CLASS__, 'render_account_page' ) );
		add_action( 'wp_ajax_sch_pause_subscription', array( __CLASS__, 'ajax_pause' ) );
		add_action( 'wp_ajax_sch_resume_subscription', array( __CLASS__, 'ajax_resume' ) );
		add_action( 'wp_ajax_sch_cancel_subscription', array( __CLASS__, 'ajax_cancel' ) );
	}

	/**
	 * Endpoint.
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( 'sch-subscriptions', EP_ROOT | EP_PAGES );
	}

	/**
	 * Menu.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	public static function menu_item( $items ) {
		$new = array();
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new['sch-subscriptions'] = __( 'Subscriptions', 'single-client-hub' );
			}
		}
		if ( ! isset( $new['sch-subscriptions'] ) ) {
			$new['sch-subscriptions'] = __( 'Subscriptions', 'single-client-hub' );
		}
		return $new;
	}

	/**
	 * Create subscription from paid/checkout order.
	 *
	 * @param WC_Order $order Order.
	 * @return int|false
	 */
	public static function create_from_order( WC_Order $order ) {
		global $wpdb;

		$items = array();
		$product_id = 0;
		foreach ( $order->get_items() as $item ) {
			$product_id = $product_id ? $product_id : $item->get_product_id();
			$items[]    = array(
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'quantity'     => $item->get_quantity(),
				'name'         => $item->get_name(),
				'total'        => (float) $item->get_total(),
			);
		}

		$next = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) );

		$ok = $wpdb->insert(
			SCH_Schema::table( 'subscriptions' ),
			array(
				'user_id'         => $order->get_user_id(),
				'product_id'      => $product_id,
				'parent_order_id' => $order->get_id(),
				'last_order_id'   => $order->get_id(),
				'status'          => 'active',
				'billing_period'  => 'month',
				'billing_interval'=> 1,
				'amount'          => (float) $order->get_total(),
				'currency'        => $order->get_currency(),
				'payment_method'  => $order->get_payment_method(),
				'saved_method_id' => $order->get_meta( '_sch_saved_method_id' ) ?: null,
				'next_payment'    => get_date_from_gmt( $next ),
				'line_items'      => wp_json_encode( $items ),
				'meta'            => wp_json_encode( array() ),
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%f', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $ok ) {
			return false;
		}

		$sub_id = (int) $wpdb->insert_id;

		$order->update_status( 'sch-sub-active', __( 'Subscription activated.', 'single-client-hub' ) );

		SCH_Plugin::log_event(
			'subscription_created',
			__( 'Subscription created', 'single-client-hub' ),
			array(
				'user_id'         => $order->get_user_id(),
				'order_id'        => $order->get_id(),
				'subscription_id' => $sub_id,
			)
		);

		return $sub_id;
	}

	/**
	 * Get subscriptions for user.
	 *
	 * @param int $user_id User.
	 * @return array
	 */
	public static function get_for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . SCH_Schema::table( 'subscriptions' ) . ' WHERE user_id = %d ORDER BY id DESC', // phpcs:ignore
				$user_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Get by ID.
	 *
	 * @param int $id ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . SCH_Schema::table( 'subscriptions' ) . ' WHERE id = %d', // phpcs:ignore
				$id
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	/**
	 * Update status.
	 *
	 * @param int    $id     ID.
	 * @param string $status Status.
	 * @param array  $extra  Extra columns.
	 * @return bool
	 */
	public static function update_status( $id, $status, array $extra = array() ) {
		global $wpdb;
		$data = array_merge(
			array(
				'status'     => sanitize_key( $status ),
				'updated_at' => current_time( 'mysql' ),
			),
			$extra
		);
		$formats = array();
		foreach ( $data as $key => $value ) {
			if ( is_int( $value ) ) {
				$formats[] = '%d';
			} elseif ( is_float( $value ) ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}
		return (bool) $wpdb->update(
			SCH_Schema::table( 'subscriptions' ),
			$data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Pause.
	 *
	 * @param int $user_id User.
	 * @param int $id      Sub ID.
	 * @return bool|WP_Error
	 */
	public static function pause( $user_id, $id ) {
		$sub = self::get( $id );
		if ( ! $sub || (int) $sub['user_id'] !== (int) $user_id ) {
			return new WP_Error( 'sch_forbidden', __( 'Subscription not found', 'single-client-hub' ) );
		}
		if ( 'active' !== $sub['status'] ) {
			return new WP_Error( 'sch_state', __( 'Pause is only available for an active subscription', 'single-client-hub' ) );
		}
		self::update_status(
			$id,
			'paused',
			array( 'paused_at' => current_time( 'mysql' ) )
		);
		SCH_Plugin::log_event(
			'subscription_paused',
			__( 'Subscription paused', 'single-client-hub' ),
			array(
				'user_id'         => $user_id,
				'subscription_id' => $id,
			)
		);
		return true;
	}

	/**
	 * Resume.
	 *
	 * @param int $user_id User.
	 * @param int $id      Sub ID.
	 * @return bool|WP_Error
	 */
	public static function resume( $user_id, $id ) {
		$sub = self::get( $id );
		if ( ! $sub || (int) $sub['user_id'] !== (int) $user_id ) {
			return new WP_Error( 'sch_forbidden', __( 'Subscription not found', 'single-client-hub' ) );
		}
		if ( 'paused' !== $sub['status'] ) {
			return new WP_Error( 'sch_state', __( 'Resume is only available while paused', 'single-client-hub' ) );
		}
		$next = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) );
		self::update_status(
			$id,
			'active',
			array(
				'paused_at'    => null,
				'next_payment' => get_date_from_gmt( $next ),
			)
		);
		SCH_Plugin::log_event(
			'subscription_resumed',
			__( 'Subscription resumed', 'single-client-hub' ),
			array(
				'user_id'         => $user_id,
				'subscription_id' => $id,
			)
		);
		return true;
	}

	/**
	 * Cancel.
	 *
	 * @param int $user_id User.
	 * @param int $id      Sub ID.
	 * @return bool|WP_Error
	 */
	public static function cancel( $user_id, $id ) {
		$sub = self::get( $id );
		if ( ! $sub || (int) $sub['user_id'] !== (int) $user_id ) {
			return new WP_Error( 'sch_forbidden', __( 'Subscription not found', 'single-client-hub' ) );
		}
		if ( in_array( $sub['status'], array( 'cancelled', 'expired' ), true ) ) {
			return new WP_Error( 'sch_state', __( 'Subscription is already cancelled', 'single-client-hub' ) );
		}
		self::update_status(
			$id,
			'cancelled',
			array( 'cancelled_at' => current_time( 'mysql' ) )
		);
		SCH_Plugin::log_event(
			'subscription_cancelled',
			__( 'Subscription cancelled', 'single-client-hub' ),
			array(
				'user_id'         => $user_id,
				'subscription_id' => $id,
			)
		);
		return true;
	}

	/**
	 * Account page.
	 */
	public static function render_account_page() {
		$user_id = get_current_user_id();
		$subs    = self::get_for_user( $user_id );
		include SCH_PLUGIN_DIR . 'templates/subscriptions.php';
	}

	/**
	 * AJAX helpers.
	 */
	public static function ajax_pause() {
		check_ajax_referer( 'sch_hub', 'nonce' );
		$result = self::pause( get_current_user_id(), isset( $_POST['subscription_id'] ) ? (int) $_POST['subscription_id'] : 0 );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success();
	}

	/**
	 * Resume AJAX.
	 */
	public static function ajax_resume() {
		check_ajax_referer( 'sch_hub', 'nonce' );
		$result = self::resume( get_current_user_id(), isset( $_POST['subscription_id'] ) ? (int) $_POST['subscription_id'] : 0 );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success();
	}

	/**
	 * Cancel AJAX.
	 */
	public static function ajax_cancel() {
		check_ajax_referer( 'sch_hub', 'nonce' );
		$result = self::cancel( get_current_user_id(), isset( $_POST['subscription_id'] ) ? (int) $_POST['subscription_id'] : 0 );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		wp_send_json_success();
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$map = array(
			'active'    => __( 'Active', 'single-client-hub' ),
			'paused'    => __( 'Paused', 'single-client-hub' ),
			'cancelled' => __( 'Cancelled', 'single-client-hub' ),
			'past_due'  => __( 'Past due', 'single-client-hub' ),
			'expired'   => __( 'Expired', 'single-client-hub' ),
		);
		return $map[ $status ] ?? $status;
	}
}
