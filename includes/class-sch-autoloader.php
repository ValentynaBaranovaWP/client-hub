<?php
/**
 * PSR-0-ish autoloader for SCH_* classes mapped to includes/.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

class SCH_Autoloader {

	private static $map = array(
		'SCH_Plugin'                => 'class-sch-plugin.php',
		'SCH_Colors'                => 'class-sch-colors.php',
		'SCH_Schema'                => 'Database/class-sch-schema.php',
		'SCH_Admin'                 => 'Admin/class-sch-admin.php',
		'SCH_Admin_Payments'        => 'Admin/class-sch-admin-payments.php',
		'SCH_Order_Statuses'        => 'Statuses/class-sch-order-statuses.php',
		'SCH_Payment_Logger'        => 'Payments/class-sch-payment-logger.php',
		'SCH_Payment_Masker'        => 'Payments/class-sch-payment-masker.php',
		'SCH_Saved_Methods'         => 'Payments/class-sch-saved-methods.php',
		'SCH_Webhooks'              => 'Payments/class-sch-webhooks.php',
		'SCH_Gateway_Bank_Invoice'  => 'Payments/Gateways/class-sch-gateway-bank-invoice.php',
		'SCH_Gateway_Installment'   => 'Payments/Gateways/class-sch-gateway-installment.php',
		'SCH_Subscriptions'         => 'Subscriptions/class-sch-subscriptions.php',
		'SCH_Subscription_Cron'     => 'Subscriptions/class-sch-subscription-cron.php',
		'SCH_Coupons'               => 'Coupons/class-sch-coupons.php',
		'SCH_Emails'                => 'Notifications/class-sch-emails.php',
		'SCH_Hub'                   => 'Frontend/class-sch-hub.php',
		'SCH_Account_Actions'       => 'Frontend/class-sch-account-actions.php',
		'SCH_REST'                  => 'Frontend/class-sch-rest.php',
	);

	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	public static function load( $class ) {
		if ( empty( self::$map[ $class ] ) ) {
			return;
		}
		$file = SCH_PLUGIN_DIR . 'includes/' . self::$map[ $class ];
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
