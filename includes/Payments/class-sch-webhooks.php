<?php
/**
 * REST webhooks for payment gateways + simulate endpoint.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Webhooks
 */
class SCH_Webhooks {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'sch/v1',
			'/webhook/(?P<gateway>[a-z0-9_]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'sch/v1',
			'/webhook/(?P<gateway>[a-z0-9_]+)/simulate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'simulate' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_woocommerce' );
				},
			)
		);
	}

	/**
	 * Incoming webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_webhook( WP_REST_Request $request ) {
		$gateway = sanitize_key( $request['gateway'] );
		$body    = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_body_params();
		}
		if ( ! is_array( $body ) ) {
			$raw  = $request->get_body();
			$body = json_decode( $raw, true );
			if ( ! is_array( $body ) ) {
				$body = array( 'raw' => $raw );
			}
		}

		$secret_header = $request->get_header( 'x-sch-secret' );
		$expected      = get_option( 'sch_bank_webhook_secret', '' );
		$test_mode     = SCH_Plugin::is_test_mode();

		if ( ! $test_mode && $expected && ! hash_equals( (string) $expected, (string) $secret_header ) ) {
			SCH_Payment_Logger::log(
				array(
					'gateway'    => $gateway,
					'event_type' => 'webhook_rejected',
					'direction'  => 'inbound',
					'status'     => 'unauthorized',
					'http_code'  => 401,
					'request'    => $body,
				)
			);
			return new WP_Error( 'sch_unauthorized', __( 'Invalid webhook secret', 'single-client-hub' ), array( 'status' => 401 ) );
		}

		$result = self::process_event( $gateway, $body, false );

		SCH_Payment_Logger::log(
			array(
				'order_id'   => isset( $body['order_id'] ) ? (int) $body['order_id'] : null,
				'gateway'    => $gateway,
				'event_type' => 'webhook_' . sanitize_key( $body['event'] ?? 'received' ),
				'direction'  => 'inbound',
				'status'     => is_wp_error( $result ) ? 'error' : 'ok',
				'http_code'  => is_wp_error( $result ) ? 400 : 200,
				'request'    => $body,
				'response'   => is_wp_error( $result ) ? array( 'error' => $result->get_error_message() ) : $result,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Admin simulate.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function simulate( WP_REST_Request $request ) {
		if ( ! SCH_Plugin::is_test_mode() ) {
			return new WP_Error( 'sch_test_off', __( 'Enable test mode', 'single-client-hub' ), array( 'status' => 400 ) );
		}

		$gateway  = sanitize_key( $request['gateway'] );
		$order_id = (int) $request->get_param( 'order_id' );
		$event    = sanitize_key( $request->get_param( 'event' ) ?: 'payment_succeeded' );

		$body = array(
			'order_id'   => $order_id,
			'event'      => $event,
			'simulated'  => true,
			'amount'     => null,
			'test_mode'  => true,
		);

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$body['amount'] = (float) $order->get_total();
		}

		$result = self::process_event( $gateway, $body, true );

		SCH_Payment_Logger::log(
			array(
				'order_id'   => $order_id,
				'gateway'    => $gateway,
				'event_type' => 'webhook_simulate_' . $event,
				'direction'  => 'inbound',
				'status'     => is_wp_error( $result ) ? 'error' : 'ok',
				'http_code'  => 200,
				'request'    => $body,
				'response'   => is_wp_error( $result ) ? array( 'error' => $result->get_error_message() ) : $result,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Apply business logic for webhook event.
	 *
	 * @param string $gateway Gateway id.
	 * @param array  $body    Payload.
	 * @param bool   $sim     Simulated.
	 * @return array|WP_Error
	 */
	public static function process_event( $gateway, array $body, $sim = false ) {
		$order_id = isset( $body['order_id'] ) ? (int) $body['order_id'] : 0;
		$event    = sanitize_key( $body['event'] ?? '' );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order ) {
			return new WP_Error( 'sch_no_order', __( 'Order not found', 'single-client-hub' ), array( 'status' => 404 ) );
		}

		$note_prefix = $sim ? '[SCH simulate] ' : '[SCH webhook] ';

		switch ( $event ) {
			case 'payment_succeeded':
			case 'invoice_paid':
				$order->payment_complete();
				$order->update_status( 'sch-assembling', $note_prefix . __( 'Payment confirmed.', 'single-client-hub' ) );
				SCH_Coupons::maybe_issue_after_payment( $order );
				break;

			case 'payment_failed':
				$order->update_status( 'failed', $note_prefix . __( 'Payment failed.', 'single-client-hub' ) );
				break;

			case 'hold_authorized':
				$order->update_status( 'sch-hold', $note_prefix . __( 'Funds held (authorization).', 'single-client-hub' ) );
				break;

			case 'hold_captured':
				$order->payment_complete();
				$order->update_status( 'processing', $note_prefix . __( 'Hold captured.', 'single-client-hub' ) );
				SCH_Coupons::maybe_issue_after_payment( $order );
				break;

			case 'hold_released':
				$order->update_status( 'cancelled', $note_prefix . __( 'Hold released.', 'single-client-hub' ) );
				break;

			default:
				$order->add_order_note( $note_prefix . sprintf( 'Event: %s', $event ) );
		}

		SCH_Plugin::log_event(
			'webhook_' . $event,
			sprintf( __( 'Webhook %1$s / %2$s', 'single-client-hub' ), $gateway, $event ),
			array(
				'user_id'  => $order->get_user_id(),
				'order_id' => $order_id,
				'payload'  => $body,
			)
		);

		SCH_Emails::send_webhook_event( $order, $gateway, $event, $body );

		return array(
			'success'  => true,
			'order_id' => $order_id,
			'gateway'  => $gateway,
			'event'    => $event,
			'status'   => $order->get_status(),
		);
	}
}
