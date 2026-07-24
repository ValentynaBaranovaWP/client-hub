<?php
/**
 * Uninstall cleanup (optional data wipe).
 *
 * @package SingleClientHub
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$tables = array(
	$wpdb->prefix . 'sch_payment_logs',
	$wpdb->prefix . 'sch_events',
	$wpdb->prefix . 'sch_subscriptions',
	$wpdb->prefix . 'sch_saved_methods',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

$options = array(
	'sch_db_version',
	'sch_test_mode',
	'sch_mask_sensitive',
	'sch_log_retention_days',
	'sch_coupon_on_payment',
	'sch_coupon_type',
	'sch_coupon_amount',
	'sch_coupon_expiry_days',
	'sch_coupon_usage_limit',
	'sch_bank_invoice_details',
	'sch_bank_webhook_secret',
	'sch_installment_hold_days',
	'sch_subscription_grace_days',
	'sch_email_status_change',
	'sch_email_coupon',
	'sch_email_failed_renewal',
	'sch_email_webhook',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

wp_clear_scheduled_hook( 'sch_process_subscription_renewals' );
wp_clear_scheduled_hook( 'sch_daily_cleanup' );
