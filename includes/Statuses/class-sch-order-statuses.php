<?php
/**
 * Custom WooCommerce order statuses for business process.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

class SCH_Order_Statuses {

	public static function definitions() {
		return array(
			'sch-assembling'   => array(
				'label'       => _x( 'Assembling', 'Order status', 'single-client-hub' ),
				'label_count' => _n_noop( 'Assembling <span class="count">(%s)</span>', 'Assembling <span class="count">(%s)</span>', 'single-client-hub' ),
				'public'      => true,
				'paid'        => true,
			),
			'sch-await-docs'   => array(
				'label'       => _x( 'Awaiting documents', 'Order status', 'single-client-hub' ),
				'label_count' => _n_noop( 'Awaiting documents <span class="count">(%s)</span>', 'Awaiting documents <span class="count">(%s)</span>', 'single-client-hub' ),
				'public'      => true,
				'paid'        => false,
			),
			'sch-sub-active'   => array(
				'label'       => _x( 'Active subscription', 'Order status', 'single-client-hub' ),
				'label_count' => _n_noop( 'Active subscription <span class="count">(%s)</span>', 'Active subscription <span class="count">(%s)</span>', 'single-client-hub' ),
				'public'      => true,
				'paid'        => true,
			),
			'sch-invoice'      => array(
				'label'       => _x( 'Awaiting payment (invoice)', 'Order status', 'single-client-hub' ),
				'label_count' => _n_noop( 'Awaiting payment (invoice) <span class="count">(%s)</span>', 'Awaiting payment (invoice) <span class="count">(%s)</span>', 'single-client-hub' ),
				'public'      => true,
				'paid'        => false,
			),
			'sch-hold'         => array(
				'label'       => _x( 'Authorization (hold)', 'Order status', 'single-client-hub' ),
				'label_count' => _n_noop( 'Authorization (hold) <span class="count">(%s)</span>', 'Authorization (hold) <span class="count">(%s)</span>', 'single-client-hub' ),
				'public'      => true,
				'paid'        => false,
			),
			'sch-renewal-fail' => array(
				'label'       => _x( 'Renewal failed', 'Order status', 'single-client-hub' ),
				'label_count' => _n_noop( 'Renewal failed <span class="count">(%s)</span>', 'Renewal failed <span class="count">(%s)</span>', 'single-client-hub' ),
				'public'      => true,
				'paid'        => false,
			),
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'add_to_list' ) );
		add_filter( 'woocommerce_reports_order_statuses', array( __CLASS__, 'report_statuses' ) );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( __CLASS__, 'valid_for_payment' ) );
		add_filter( 'woocommerce_order_is_paid_statuses', array( __CLASS__, 'paid_statuses' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 4 );
	}

	public static function register() {
		foreach ( self::definitions() as $slug => $def ) {
			register_post_status(
				'wc-' . $slug,
				array(
					'label'                     => $def['label'],
					'public'                    => false,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => $def['label_count'],
				)
			);
		}
	}

	public static function add_to_list( $statuses ) {
		$new = array();
		foreach ( $statuses as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'wc-processing' === $key ) {
				foreach ( self::definitions() as $slug => $def ) {
					$new[ 'wc-' . $slug ] = $def['label'];
				}
			}
		}
		foreach ( self::definitions() as $slug => $def ) {
			if ( ! isset( $new[ 'wc-' . $slug ] ) ) {
				$new[ 'wc-' . $slug ] = $def['label'];
			}
		}
		return $new;
	}

	public static function report_statuses( $statuses ) {
		foreach ( self::definitions() as $slug => $def ) {
			if ( ! empty( $def['paid'] ) ) {
				$statuses[] = $slug;
			}
		}
		return $statuses;
	}

	public static function valid_for_payment( $statuses ) {
		$statuses[] = 'sch-invoice';
		$statuses[] = 'sch-hold';
		$statuses[] = 'sch-await-docs';
		$statuses[] = 'sch-renewal-fail';
		return $statuses;
	}

	public static function paid_statuses( $statuses ) {
		foreach ( self::definitions() as $slug => $def ) {
			if ( ! empty( $def['paid'] ) ) {
				$statuses[] = $slug;
			}
		}
		return $statuses;
	}

	public static function get_label( $status ) {
		$status = str_replace( 'wc-', '', $status );
		$defs   = self::definitions();
		if ( isset( $defs[ $status ] ) ) {
			return $defs[ $status ]['label'];
		}
		return wc_get_order_status_name( $status );
	}

	public static function on_status_changed( $order_id, $from, $to, $order ) {
		SCH_Plugin::log_event(
			'order_status_changed',
			sprintf(
				/* translators: 1: from 2: to */
				__( 'Order status: %1$s → %2$s', 'single-client-hub' ),
				self::get_label( $from ),
				self::get_label( $to )
			),
			array(
				'user_id'  => $order->get_user_id(),
				'order_id' => $order_id,
				'payload'  => array(
					'from' => $from,
					'to'   => $to,
				),
			)
		);

		SCH_Emails::send_status_change( $order, $from, $to );
	}
}
