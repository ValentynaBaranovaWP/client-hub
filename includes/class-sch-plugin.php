<?php
/**
 * Core plugin singleton — wires modules.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Plugin
 */
class SCH_Plugin {

	/**
	 * @var SCH_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @return SCH_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot modules.
	 */
	public function init() {
		load_plugin_textdomain( 'single-client-hub', false, dirname( SCH_PLUGIN_BASENAME ) . '/languages' );

		SCH_Order_Statuses::init();
		SCH_Payment_Logger::init();
		SCH_Webhooks::init();
		SCH_Saved_Methods::init();
		SCH_Subscriptions::init();
		SCH_Subscription_Cron::init();
		SCH_Coupons::init();
		SCH_Emails::init();
		SCH_Hub::init();
		SCH_Account_Actions::init();
		SCH_REST::init();
		SCH_Admin::init();

		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateways' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_create_subscription_from_checkout' ), 20, 3 );
		add_action( 'woocommerce_checkout_before_order_review', array( $this, 'checkout_subscription_checkbox' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_subscription_flag' ) );
	}

	/**
	 * Register WC payment gateways (modules).
	 *
	 * @param array $gateways Gateways.
	 * @return array
	 */
	public function register_gateways( $gateways ) {
		$gateways[] = 'SCH_Gateway_Bank_Invoice';
		$gateways[] = 'SCH_Gateway_Installment';
		return $gateways;
	}

	/**
	 * Checkbox on classic checkout.
	 */
	public function checkout_subscription_checkbox() {
		$checked = ( WC()->session && 'yes' === WC()->session->get( 'sch_create_subscription' ) );
		woocommerce_form_field(
			'sch_create_subscription',
			array(
				'type'  => 'checkbox',
				'class' => array( 'form-row-wide', 'sch-create-subscription' ),
				'label' => __( 'Check out as subscription (auto-renewal)', 'single-client-hub' ),
			),
			$checked ? 1 : 0
		);
	}

	/**
	 * Persist flag on order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_subscription_flag( $order_id ) {
		$flag = ! empty( $_POST['sch_create_subscription'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( WC()->session && 'yes' === WC()->session->get( 'sch_create_subscription' ) ) {
			$flag = 'yes';
		}
		update_post_meta( $order_id, '_sch_create_subscription', $flag );
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->update_meta_data( '_sch_create_subscription', $flag );
			$order->save();
		}
	}

	/**
	 * After checkout — create subscription if flagged.
	 *
	 * @param int      $order_id Order ID.
	 * @param array    $posted   Posted data.
	 * @param WC_Order $order    Order.
	 */
	public function maybe_create_subscription_from_checkout( $order_id, $posted, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}
		if ( 'yes' !== $order->get_meta( '_sch_create_subscription' ) ) {
			return;
		}
		if ( $order->get_meta( '_sch_subscription_id' ) ) {
			return;
		}

		$sub_id = SCH_Subscriptions::create_from_order( $order );
		if ( $sub_id ) {
			$order->update_meta_data( '_sch_subscription_id', $sub_id );
			$order->save();
			if ( WC()->session ) {
				WC()->session->set( 'sch_create_subscription', 'no' );
			}
		}
	}

	/**
	 * Test mode enabled?
	 *
	 * @return bool
	 */
	public static function is_test_mode() {
		return 'yes' === get_option( 'sch_test_mode', 'yes' );
	}

	/**
	 * Mask sensitive data in logs?
	 *
	 * @return bool
	 */
	public static function should_mask() {
		return 'yes' === get_option( 'sch_mask_sensitive', 'yes' );
	}

	/**
	 * Log business event into sch_events.
	 *
	 * @param string $code    Event code.
	 * @param string $label   Human label.
	 * @param array  $context Context (user_id, order_id, subscription_id, payload…).
	 * @return int|false
	 */
	public static function log_event( $code, $label, array $context = array() ) {
		global $wpdb;

		$payload = isset( $context['payload'] ) ? $context['payload'] : $context;
		unset( $payload['user_id'], $payload['order_id'], $payload['subscription_id'], $payload['payload'] );

		$ok = $wpdb->insert(
			SCH_Schema::table( 'events' ),
			array(
				'user_id'         => isset( $context['user_id'] ) ? (int) $context['user_id'] : null,
				'order_id'        => isset( $context['order_id'] ) ? (int) $context['order_id'] : null,
				'subscription_id' => isset( $context['subscription_id'] ) ? (int) $context['subscription_id'] : null,
				'event_code'      => sanitize_key( $code ),
				'event_label'     => sanitize_text_field( $label ),
				'payload'         => wp_json_encode( SCH_Payment_Masker::mask( $payload ) ),
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}
}
