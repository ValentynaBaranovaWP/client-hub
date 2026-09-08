<?php
/**
 * Admin tables: payment logs & events.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

class SCH_Admin_Payments {

	public static function render_logs() {
		$gateway = isset( $_GET['gateway'] ) ? sanitize_key( wp_unslash( $_GET['gateway'] ) ) : ''; // phpcs:ignore
		$order_id = isset( $_GET['order_id'] ) ? (int) $_GET['order_id'] : 0; // phpcs:ignore

		$logs = SCH_Payment_Logger::get_logs(
			100,
			array_filter(
				array(
					'gateway'  => $gateway,
					'order_id' => $order_id,
				)
			)
		);

		echo '<form method="get" class="sch-filters" style="margin:1em 0">';
		echo '<input type="hidden" name="page" value="sch-hub"><input type="hidden" name="tab" value="payments">';
		echo '<label>Gateway <input type="text" name="gateway" value="' . esc_attr( $gateway ) . '" class="regular-text"></label> ';
		echo '<label>Order ID <input type="number" name="order_id" value="' . esc_attr( (string) $order_id ) . '" class="small-text"></label> ';
		submit_button( __( 'Filter', 'single-client-hub' ), 'secondary', '', false );
		echo '</form>';

		echo '<p>' . esc_html__( 'Test mode:', 'single-client-hub' ) . ' <strong>' . ( SCH_Plugin::is_test_mode() ? 'ON' : 'OFF' ) . '</strong> · ';
		echo esc_html__( 'Masking:', 'single-client-hub' ) . ' <strong>' . ( SCH_Plugin::should_mask() ? 'ON' : 'OFF' ) . '</strong></p>';

		echo '<table class="widefat striped sch-logs-table"><thead><tr>';
		$heads = array( 'ID', 'Order', 'Gateway', 'Event', 'Dir', 'Status', 'HTTP', 'Test', 'Created', 'Payloads' );
		foreach ( $heads as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( ! $logs ) {
			echo '<tr><td colspan="10">' . esc_html__( 'No records', 'single-client-hub' ) . '</td></tr>';
		}

		foreach ( $logs as $row ) {
			echo '<tr>';
			echo '<td>' . (int) $row['id'] . '</td>';
			echo '<td>' . ( $row['order_id'] ? (int) $row['order_id'] : '—' ) . '</td>';
			echo '<td><code>' . esc_html( $row['gateway'] ) . '</code></td>';
			echo '<td>' . esc_html( $row['event_type'] ) . '</td>';
			echo '<td>' . esc_html( $row['direction'] ) . '</td>';
			echo '<td>' . esc_html( $row['status'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['http_code'] ) . '</td>';
			echo '<td>' . ( ! empty( $row['test_mode'] ) ? 'yes' : 'no' ) . '</td>';
			echo '<td>' . esc_html( $row['created_at'] ) . '</td>';
			echo '<td><details><summary>' . esc_html__( 'Show', 'single-client-hub' ) . '</summary>';
			echo '<p><strong>Request</strong></p><pre class="sch-payload">' . esc_html( (string) $row['request_payload'] ) . '</pre>';
			echo '<p><strong>Response</strong></p><pre class="sch-payload">' . esc_html( (string) $row['response_payload'] ) . '</pre>';
			echo '</details></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public static function render_events() {
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT * FROM ' . SCH_Schema::table( 'events' ) . ' ORDER BY id DESC LIMIT 100', // phpcs:ignore
			ARRAY_A
		);

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'ID', 'User', 'Order', 'Sub', 'Code', 'Label', 'Created', 'Payload' ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( ! $rows ) {
			echo '<tr><td colspan="8">' . esc_html__( 'No events', 'single-client-hub' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td>' . (int) $row['id'] . '</td>';
			echo '<td>' . ( $row['user_id'] ? (int) $row['user_id'] : '—' ) . '</td>';
			echo '<td>' . ( $row['order_id'] ? (int) $row['order_id'] : '—' ) . '</td>';
			echo '<td>' . ( $row['subscription_id'] ? (int) $row['subscription_id'] : '—' ) . '</td>';
			echo '<td><code>' . esc_html( $row['event_code'] ) . '</code></td>';
			echo '<td>' . esc_html( $row['event_label'] ) . '</td>';
			echo '<td>' . esc_html( $row['created_at'] ) . '</td>';
			echo '<td><details><summary>JSON</summary><pre class="sch-payload">' . esc_html( (string) $row['payload'] ) . '</pre></details></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
