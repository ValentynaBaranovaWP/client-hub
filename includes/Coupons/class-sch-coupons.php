<?php
/**
 * Auto-issue coupons after successful payment + checkout validation helpers.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Coupons
 */
class SCH_Coupons {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_payment_complete' ), 20 );
		add_filter( 'woocommerce_coupon_error', array( __CLASS__, 'friendlier_errors' ), 10, 3 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'ensure_coupon_form' ), 5 );
	}

	/**
	 * On WC payment complete.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_payment_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::maybe_issue_after_payment( $order );
		}
	}

	/**
	 * Issue coupon once per order if enabled.
	 *
	 * @param WC_Order $order Order.
	 * @return string|false Coupon code.
	 */
	public static function maybe_issue_after_payment( WC_Order $order ) {
		if ( 'yes' !== get_option( 'sch_coupon_on_payment', 'yes' ) ) {
			return false;
		}
		if ( $order->get_meta( '_sch_reward_coupon' ) ) {
			return $order->get_meta( '_sch_reward_coupon' );
		}

		$code = self::create_reward_coupon( $order );
		if ( ! $code ) {
			return false;
		}

		$order->update_meta_data( '_sch_reward_coupon', $code );
		$order->add_order_note(
			sprintf(
				/* translators: %s: coupon code */
				__( 'Auto-issued coupon: %s', 'single-client-hub' ),
				$code
			)
		);
		$order->save();

		$user_id = $order->get_user_id();
		if ( $user_id ) {
			$codes = get_user_meta( $user_id, 'sch_reward_coupons', true );
			if ( ! is_array( $codes ) ) {
				$codes = array();
			}
			$codes[] = array(
				'code'     => $code,
				'order_id' => $order->get_id(),
				'issued'   => current_time( 'mysql' ),
			);
			update_user_meta( $user_id, 'sch_reward_coupons', $codes );
		}

		SCH_Plugin::log_event(
			'coupon_issued',
			__( 'Coupon issued after payment', 'single-client-hub' ),
			array(
				'user_id'  => $user_id,
				'order_id' => $order->get_id(),
				'payload'  => array( 'code' => $code ),
			)
		);

		SCH_Emails::send_coupon_issued( $order, $code );

		return $code;
	}

	/**
	 * Create WC coupon.
	 *
	 * @param WC_Order $order Order.
	 * @return string|false
	 */
	public static function create_reward_coupon( WC_Order $order ) {
		$type    = get_option( 'sch_coupon_type', 'percent' );
		$amount  = (float) get_option( 'sch_coupon_amount', 10 );
		$days    = max( 1, (int) get_option( 'sch_coupon_expiry_days', 30 ) );
		$limit   = max( 1, (int) get_option( 'sch_coupon_usage_limit', 1 ) );
		$code    = strtoupper( 'SCH' . $order->get_id() . wp_generate_password( 4, false ) );

		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $type );
		$coupon->set_amount( $amount );
		$coupon->set_individual_use( true );
		$coupon->set_usage_limit( $limit );
		$coupon->set_usage_limit_per_user( 1 );
		$coupon->set_date_expires( strtotime( '+' . $days . ' days' ) );
		if ( $order->get_billing_email() ) {
			$coupon->set_email_restrictions( array( $order->get_billing_email() ) );
		}
		$coupon->set_description(
			sprintf(
				/* translators: %d: order id */
				__( 'SCH auto-coupon for order #%d', 'single-client-hub' ),
				$order->get_id()
			)
		);
		$coupon->update_meta_data( '_sch_auto_issued', 'yes' );
		$coupon->update_meta_data( '_sch_source_order', $order->get_id() );
		$coupon->save();

		return $code;
	}

	/**
	 * Friendlier coupon error messages.
	 *
	 * @param string    $err     Error.
	 * @param int       $err_code Code.
	 * @param WC_Coupon $coupon  Coupon.
	 * @return string
	 */
	public static function friendlier_errors( $err, $err_code, $coupon ) {
		$map = array(
			100 => __( 'Coupon not found. Check the code spelling.', 'single-client-hub' ),
			101 => __( 'This coupon has already been used the maximum number of times.', 'single-client-hub' ),
			102 => __( 'This coupon has expired.', 'single-client-hub' ),
			103 => __( 'This coupon is not active yet.', 'single-client-hub' ),
			104 => __( 'This coupon cannot be applied to items in your cart.', 'single-client-hub' ),
			105 => __( 'Order total does not meet the coupon minimum.', 'single-client-hub' ),
			106 => __( 'Order total exceeds the coupon maximum.', 'single-client-hub' ),
			107 => __( 'This coupon is not available for your account or email.', 'single-client-hub' ),
			109 => __( 'This coupon cannot be combined with other discounts.', 'single-client-hub' ),
		);
		if ( isset( $map[ (int) $err_code ] ) ) {
			return $map[ (int) $err_code ];
		}
		return $err;
	}

	/**
	 * Ensure classic coupon form notice on checkout.
	 */
	public static function ensure_coupon_form() {
		if ( ! wc_coupons_enabled() ) {
			return;
		}
		// WC already shows coupon form; we add a short hint for SCH reward coupons.
		echo '<div class="sch-checkout-coupon-hint"><p class="woocommerce-info">' .
			esc_html__( 'Have a discount coupon? Enter the code below. Validation errors are shown in plain language.', 'single-client-hub' ) .
			'</p></div>';
	}

	/**
	 * Reward coupons visible to user (for mini-cart).
	 *
	 * @param int $user_id User.
	 * @return array
	 */
	public static function get_user_reward_codes( $user_id ) {
		$codes = get_user_meta( $user_id, 'sch_reward_coupons', true );
		if ( ! is_array( $codes ) ) {
			return array();
		}
		$out = array();
		foreach ( $codes as $row ) {
			$code = is_array( $row ) ? ( $row['code'] ?? '' ) : (string) $row;
			if ( ! $code ) {
				continue;
			}
			$coupon = new WC_Coupon( $code );
			if ( ! $coupon->get_id() ) {
				continue;
			}
			if ( $coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time() ) {
				continue;
			}
			$out[] = array(
				'code'   => $code,
				'amount' => $coupon->get_amount(),
				'type'   => $coupon->get_discount_type(),
			);
		}
		return $out;
	}
}
