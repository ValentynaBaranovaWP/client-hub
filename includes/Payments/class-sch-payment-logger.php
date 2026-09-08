<?php
/**
 * Payment event logger (request / response / webhook).
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

class SCH_Payment_Logger {

	public static function init() {
		add_action( 'sch_daily_cleanup', array( __CLASS__, 'cleanup' ) );
		if ( ! wp_next_scheduled( 'sch_daily_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sch_daily_cleanup' );
		}
	}

	public static function log( array $args ) {
		global $wpdb;

		$request  = isset( $args['request'] ) ? $args['request'] : null;
		$response = isset( $args['response'] ) ? $args['response'] : null;

		if ( is_array( $request ) || is_object( $request ) ) {
			$request = wp_json_encode( SCH_Payment_Masker::mask( $request ) );
		} elseif ( is_string( $request ) ) {
			$request = SCH_Payment_Masker::mask( $request );
		}

		if ( is_array( $response ) || is_object( $response ) ) {
			$response = wp_json_encode( SCH_Payment_Masker::mask( $response ) );
		} elseif ( is_string( $response ) ) {
			$response = SCH_Payment_Masker::mask( $response );
		}

		$ok = $wpdb->insert(
			SCH_Schema::table( 'payment_logs' ),
			array(
				'order_id'         => isset( $args['order_id'] ) ? (int) $args['order_id'] : null,
				'gateway'          => sanitize_key( $args['gateway'] ?? '' ),
				'event_type'       => sanitize_key( $args['event_type'] ?? 'unknown' ),
				'direction'        => sanitize_key( $args['direction'] ?? 'outbound' ),
				'status'           => sanitize_text_field( $args['status'] ?? '' ),
				'http_code'        => isset( $args['http_code'] ) ? (int) $args['http_code'] : null,
				'request_payload'  => $request,
				'response_payload' => $response,
				'masked'           => SCH_Plugin::should_mask() ? 1 : 0,
				'test_mode'        => SCH_Plugin::is_test_mode() ? 1 : 0,
				'ip_address'       => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null,
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	public static function cleanup() {
		global $wpdb;
		$days = max( 1, (int) get_option( 'sch_log_retention_days', 90 ) );
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . SCH_Schema::table( 'payment_logs' ) . ' WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)', // phpcs:ignore WordPress.DB.PreparedSQL
				current_time( 'mysql' ),
				$days
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . SCH_Schema::table( 'events' ) . ' WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)', // phpcs:ignore WordPress.DB.PreparedSQL
				current_time( 'mysql' ),
				$days
			)
		);
	}

	public static function get_logs( $limit = 50, array $args = array() ) {
		global $wpdb;
		$table = SCH_Schema::table( 'payment_logs' );
		$where = array( '1=1' );
		$params = array();

		if ( ! empty( $args['gateway'] ) ) {
			$where[]  = 'gateway = %s';
			$params[] = sanitize_key( $args['gateway'] );
		}
		if ( ! empty( $args['order_id'] ) ) {
			$where[]  = 'order_id = %d';
			$params[] = (int) $args['order_id'];
		}

		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d';
		$params[] = (int) $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: array();
	}
}
