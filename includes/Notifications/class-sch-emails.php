<?php
/**
 * Customer email notifications.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Emails
 */
class SCH_Emails {

	/**
	 * Init (placeholder for future WC_Email classes).
	 */
	public static function init() {
		// Hooks are called explicitly from other modules.
	}

	/**
	 * Status change email.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $from  From.
	 * @param string   $to    To.
	 */
	public static function send_status_change( WC_Order $order, $from, $to ) {
		if ( 'yes' !== get_option( 'sch_email_status_change', 'yes' ) ) {
			return;
		}
		$email = $order->get_billing_email();
		if ( ! $email ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: site 2: order number */
			__( '[%1$s] Order #%2$s status changed', 'single-client-hub' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$order->get_order_number()
		);

		$message = self::render(
			'status-change.php',
			array(
				'order' => $order,
				'from'  => SCH_Order_Statuses::get_label( $from ),
				'to'    => SCH_Order_Statuses::get_label( $to ),
			)
		);

		self::mail( $email, $subject, $message );
	}

	/**
	 * Coupon issued.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $code  Code.
	 */
	public static function send_coupon_issued( WC_Order $order, $code ) {
		if ( 'yes' !== get_option( 'sch_email_coupon', 'yes' ) ) {
			return;
		}
		$email = $order->get_billing_email();
		if ( ! $email ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] You received a discount coupon', 'single-client-hub' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$message = self::render(
			'coupon-issued.php',
			array(
				'order' => $order,
				'code'  => $code,
			)
		);

		self::mail( $email, $subject, $message );
	}

	/**
	 * Failed renewal.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $sub   Subscription row.
	 */
	public static function send_failed_renewal( WC_Order $order, array $sub ) {
		if ( 'yes' !== get_option( 'sch_email_failed_renewal', 'yes' ) ) {
			return;
		}
		$email = $order->get_billing_email();
		if ( ! $email ) {
			$user = get_user_by( 'id', (int) $sub['user_id'] );
			$email = $user ? $user->user_email : '';
		}
		if ( ! $email ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site */
			__( '[%s] Subscription renewal failed', 'single-client-hub' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$message = self::render(
			'failed-renewal.php',
			array(
				'order' => $order,
				'sub'   => $sub,
			)
		);

		self::mail( $email, $subject, $message );
	}

	/**
	 * Webhook event notice (customer-facing for key events).
	 *
	 * @param WC_Order $order   Order.
	 * @param string   $gateway Gateway.
	 * @param string   $event   Event.
	 * @param array    $body    Body.
	 */
	public static function send_webhook_event( WC_Order $order, $gateway, $event, array $body ) {
		if ( 'yes' !== get_option( 'sch_email_webhook', 'yes' ) ) {
			return;
		}

		$notify_events = array( 'payment_succeeded', 'invoice_paid', 'payment_failed', 'hold_captured', 'hold_released' );
		if ( ! in_array( $event, $notify_events, true ) ) {
			return;
		}

		$email = $order->get_billing_email();
		if ( ! $email ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: site 2: event */
			__( '[%1$s] Payment update: %2$s', 'single-client-hub' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$event
		);

		$message = self::render(
			'webhook-event.php',
			array(
				'order'   => $order,
				'gateway' => $gateway,
				'event'   => $event,
				'body'    => $body,
			)
		);

		self::mail( $email, $subject, $message );
	}

	/**
	 * Render template to string.
	 *
	 * @param string $template Template file.
	 * @param array  $vars     Vars.
	 * @return string
	 */
	private static function render( $template, array $vars ) {
		$path = SCH_PLUGIN_DIR . 'templates/emails/' . $template;
		if ( ! is_readable( $path ) ) {
			return '';
		}
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		ob_start();
		include $path;
		return ob_get_clean();
	}

	/**
	 * wp_mail HTML wrapper.
	 *
	 * @param string $to      To.
	 * @param string $subject Subject.
	 * @param string $message HTML body.
	 */
	private static function mail( $to, $subject, $message ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		wp_mail( $to, $subject, $message, $headers );
	}
}
