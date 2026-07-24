<?php
/**
 * WP-Cron renewals: recreate orders/invoices and attempt charge.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Subscription_Cron
 */
class SCH_Subscription_Cron {

	const HOOK = 'sch_process_subscription_renewals';

	/**
	 * Init.
	 */
	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'process' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) );
	}

	/**
	 * Custom schedule every 15 minutes.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function schedules( $schedules ) {
		$schedules['sch_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes (SCH)', 'single-client-hub' ),
		);
		return $schedules;
	}

	/**
	 * Schedule on activate.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'sch_fifteen_minutes', self::HOOK );
		}
	}

	/**
	 * Unschedule on deactivate.
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * Process due subscriptions.
	 */
	public static function process() {
		global $wpdb;

		$now = current_time( 'mysql' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . SCH_Schema::table( 'subscriptions' ) . " WHERE status = 'active' AND next_payment IS NOT NULL AND next_payment <= %s LIMIT 20", // phpcs:ignore
				$now
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			return;
		}

		foreach ( $rows as $sub ) {
			self::renew_one( $sub );
		}
	}

	/**
	 * Renew single subscription.
	 *
	 * @param array $sub Subscription row.
	 */
	public static function renew_one( array $sub ) {
		$user_id = (int) $sub['user_id'];
		$items   = json_decode( $sub['line_items'], true );
		if ( ! is_array( $items ) || ! $items ) {
			SCH_Subscriptions::update_status( (int) $sub['id'], 'past_due' );
			return;
		}

		$order = wc_create_order(
			array(
				'customer_id' => $user_id,
				'created_via' => 'sch_subscription_renewal',
			)
		);

		if ( is_wp_error( $order ) ) {
			return;
		}

		foreach ( $items as $line ) {
			$product = wc_get_product( ! empty( $line['variation_id'] ) ? $line['variation_id'] : $line['product_id'] );
			if ( ! $product ) {
				continue;
			}
			$order->add_product( $product, max( 1, (int) ( $line['quantity'] ?? 1 ) ) );
		}

		$order->set_payment_method( $sub['payment_method'] ?: 'sch_bank_invoice' );
		$order->update_meta_data( '_sch_subscription_id', (int) $sub['id'] );
		$order->update_meta_data( '_sch_is_renewal', 'yes' );
		$order->calculate_totals();
		$order->save();

		$charged = self::attempt_charge( $order, $sub );

		if ( $charged ) {
			$order->payment_complete();
			$order->update_status( 'sch-sub-active', __( 'Auto-renewal successful.', 'single-client-hub' ) );
			$next = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) );
			SCH_Subscriptions::update_status(
				(int) $sub['id'],
				'active',
				array(
					'last_order_id' => $order->get_id(),
					'next_payment'  => get_date_from_gmt( $next ),
				)
			);
			SCH_Plugin::log_event(
				'subscription_renewed',
				__( 'Subscription renewed', 'single-client-hub' ),
				array(
					'user_id'         => $user_id,
					'order_id'        => $order->get_id(),
					'subscription_id' => (int) $sub['id'],
				)
			);
			SCH_Coupons::maybe_issue_after_payment( $order );
		} else {
			$order->update_status( 'sch-renewal-fail', __( 'Auto-charge failed.', 'single-client-hub' ) );
			SCH_Subscriptions::update_status( (int) $sub['id'], 'past_due' );
			SCH_Plugin::log_event(
				'subscription_renewal_failed',
				__( 'Failed renewal', 'single-client-hub' ),
				array(
					'user_id'         => $user_id,
					'order_id'        => $order->get_id(),
					'subscription_id' => (int) $sub['id'],
				)
			);
			SCH_Emails::send_failed_renewal( $order, $sub );
		}
	}

	/**
	 * Attempt mock charge via saved method / gateway.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $sub   Subscription.
	 * @return bool
	 */
	private static function attempt_charge( WC_Order $order, array $sub ) {
		$request = array(
			'subscription_id' => (int) $sub['id'],
			'order_id'        => $order->get_id(),
			'amount'          => (float) $order->get_total(),
			'currency'        => $order->get_currency(),
			'payment_method'  => $sub['payment_method'],
			'saved_method_id' => $sub['saved_method_id'],
			'test_mode'       => SCH_Plugin::is_test_mode(),
		);

		// Demo policy: succeed unless last4 ends with 0000 or amount is 0.
		$success = true;
		if ( SCH_Plugin::is_test_mode() ) {
			$force_fail = get_user_meta( (int) $sub['user_id'], 'sch_force_renewal_fail', true );
			if ( 'yes' === $force_fail ) {
				$success = false;
			}
		}

		$response = array(
			'status'      => $success ? 'succeeded' : 'failed',
			'charge_id'   => $success ? 'chg_' . wp_generate_password( 10, false ) : null,
			'message'     => $success ? 'ok' : 'insufficient_funds_mock',
		);

		SCH_Payment_Logger::log(
			array(
				'order_id'   => $order->get_id(),
				'gateway'    => $sub['payment_method'] ?: 'sch_renewal',
				'event_type' => 'renewal_charge',
				'direction'  => 'outbound',
				'status'     => $response['status'],
				'http_code'  => $success ? 200 : 402,
				'request'    => $request,
				'response'   => $response,
			)
		);

		return $success;
	}
}
