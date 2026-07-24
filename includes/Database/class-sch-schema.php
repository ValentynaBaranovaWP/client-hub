<?php
/**
 * Database schema & migrations.
 *
 * Tables:
 * - {prefix}sch_payment_logs  — payment request/response/webhook traffic
 * - {prefix}sch_events        — business events (statuses, coupons, cron, …)
 * - {prefix}sch_subscriptions — customer subscriptions
 * - {prefix}sch_saved_methods — saved payment methods (tokens)
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Schema
 */
class SCH_Schema {

	/**
	 * Create / upgrade tables via dbDelta.
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix;

		$sql_logs = "CREATE TABLE {$prefix}sch_payment_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned DEFAULT NULL,
			gateway varchar(64) NOT NULL DEFAULT '',
			event_type varchar(64) NOT NULL DEFAULT '',
			direction varchar(16) NOT NULL DEFAULT 'outbound',
			status varchar(32) NOT NULL DEFAULT '',
			http_code smallint(5) unsigned DEFAULT NULL,
			request_payload longtext NULL,
			response_payload longtext NULL,
			masked tinyint(1) NOT NULL DEFAULT 1,
			test_mode tinyint(1) NOT NULL DEFAULT 0,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY gateway (gateway),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) $charset;";

		$sql_events = "CREATE TABLE {$prefix}sch_events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			order_id bigint(20) unsigned DEFAULT NULL,
			subscription_id bigint(20) unsigned DEFAULT NULL,
			event_code varchar(64) NOT NULL DEFAULT '',
			event_label varchar(191) NOT NULL DEFAULT '',
			payload longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY order_id (order_id),
			KEY event_code (event_code),
			KEY created_at (created_at)
		) $charset;";

		$sql_subs = "CREATE TABLE {$prefix}sch_subscriptions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_order_id bigint(20) unsigned DEFAULT NULL,
			last_order_id bigint(20) unsigned DEFAULT NULL,
			status varchar(32) NOT NULL DEFAULT 'active',
			billing_period varchar(16) NOT NULL DEFAULT 'month',
			billing_interval smallint(5) unsigned NOT NULL DEFAULT 1,
			amount decimal(12,2) NOT NULL DEFAULT 0.00,
			currency varchar(8) NOT NULL DEFAULT 'UAH',
			payment_method varchar(64) NOT NULL DEFAULT '',
			saved_method_id bigint(20) unsigned DEFAULT NULL,
			next_payment datetime DEFAULT NULL,
			paused_at datetime DEFAULT NULL,
			cancelled_at datetime DEFAULT NULL,
			line_items longtext NULL,
			meta longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY next_payment (next_payment)
		) $charset;";

		$sql_methods = "CREATE TABLE {$prefix}sch_saved_methods (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			provider varchar(64) NOT NULL DEFAULT 'stripe',
			token varchar(191) NOT NULL DEFAULT '',
			brand varchar(32) NOT NULL DEFAULT '',
			last4 varchar(4) NOT NULL DEFAULT '',
			exp_month tinyint(2) unsigned DEFAULT NULL,
			exp_year smallint(4) unsigned DEFAULT NULL,
			is_default tinyint(1) NOT NULL DEFAULT 0,
			meta longtext NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY provider (provider)
		) $charset;";

		dbDelta( $sql_logs );
		dbDelta( $sql_events );
		dbDelta( $sql_subs );
		dbDelta( $sql_methods );
	}

	/**
	 * Table name helper.
	 *
	 * @param string $suffix Table suffix without prefix.
	 * @return string
	 */
	public static function table( $suffix ) {
		global $wpdb;
		return $wpdb->prefix . 'sch_' . $suffix;
	}
}
