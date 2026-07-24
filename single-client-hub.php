<?php
/**
 * Plugin Name:       Single Client Hub
 * Plugin URI:        https://github.com/ValentynaBaranovaWP/client-hub
 * Description:       Unified client hub: mini-cart, orders, custom statuses, payment modules, subscriptions, coupons, and notifications.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Valentyna Baranova
 * Text Domain:       single-client-hub
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.0
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

define( 'SCH_VERSION', '1.0.0' );
define( 'SCH_DB_VERSION', '1.0.0' );
define( 'SCH_PLUGIN_FILE', __FILE__ );
define( 'SCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SCH_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once SCH_PLUGIN_DIR . 'includes/class-sch-autoloader.php';
SCH_Autoloader::register();

/**
 * Activation: migrations + defaults + endpoints flush.
 */
function sch_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( SCH_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'Single Client Hub requires an active WooCommerce installation.', 'single-client-hub' ),
			esc_html__( 'Plugin deactivated', 'single-client-hub' ),
			array( 'back_link' => true )
		);
	}

	SCH_Schema::install();

	$defaults = array(
		'sch_test_mode'              => 'yes',
		'sch_mask_sensitive'         => 'yes',
		'sch_log_retention_days'     => 90,
		'sch_coupon_on_payment'      => 'yes',
		'sch_coupon_type'            => 'percent',
		'sch_coupon_amount'          => 10,
		'sch_coupon_expiry_days'     => 30,
		'sch_coupon_usage_limit'     => 1,
		'sch_bank_invoice_details'   => "Payee: Demo LLC\nIBAN: UA00 ACCT-000003 0000 000\nReference: Payment for order #{order_number} (ID {order_id}), amount {amount}",
		'sch_bank_webhook_secret'    => wp_generate_password( 32, false ),
		'sch_installment_hold_days'  => 7,
		'sch_subscription_grace_days'=> 3,
		'sch_email_status_change'    => 'yes',
		'sch_email_coupon'           => 'yes',
		'sch_email_failed_renewal'   => 'yes',
		'sch_email_webhook'          => 'yes',
		'sch_color_main'             => '#1c1917',
		'sch_color_accent'           => '#0d9488',
		'sch_color_text'             => '#fafaf9',
	);

	foreach ( $defaults as $key => $value ) {
		if ( false === get_option( $key ) ) {
			add_option( $key, $value );
		}
	}

	update_option( 'sch_db_version', SCH_DB_VERSION );

	if ( class_exists( 'SCH_Order_Statuses' ) ) {
		SCH_Order_Statuses::register();
	}
	if ( class_exists( 'SCH_Saved_Methods' ) ) {
		SCH_Saved_Methods::add_endpoint();
	}
	if ( class_exists( 'SCH_Subscriptions' ) ) {
		SCH_Subscriptions::add_endpoint();
	}

	SCH_Subscription_Cron::schedule();

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'sch_activate' );

/**
 * Deactivation: clear cron, keep data.
 */
function sch_deactivate() {
	SCH_Subscription_Cron::unschedule();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'sch_deactivate' );

/**
 * Boot after plugins loaded (WC must exist).
 */
function sch_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Single Client Hub requires WooCommerce.', 'single-client-hub' ) . '</p></div>';
			}
		);
		return;
	}

	$installed = get_option( 'sch_db_version' );
	if ( SCH_DB_VERSION !== $installed ) {
		SCH_Schema::install();
		update_option( 'sch_db_version', SCH_DB_VERSION );
	}

	SCH_Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'sch_bootstrap', 20 );

/**
 * HPOS / cart-checkout blocks compatibility declarations.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SCH_PLUGIN_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SCH_PLUGIN_FILE, false );
		}
	}
);
