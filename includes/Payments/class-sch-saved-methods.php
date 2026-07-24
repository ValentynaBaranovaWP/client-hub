<?php
/**
 * Saved payment methods (tokenization layer + Stripe bridge when available).
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Saved_Methods
 */
class SCH_Saved_Methods {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'woocommerce_account_sch-payment-methods_endpoint', array( __CLASS__, 'render_account_page' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_item' ) );
		add_action( 'init', array( __CLASS__, 'add_endpoint' ) );
		add_action( 'wp_ajax_sch_delete_saved_method', array( __CLASS__, 'ajax_delete' ) );
		add_action( 'wp_ajax_sch_set_default_method', array( __CLASS__, 'ajax_set_default' ) );
		add_action( 'wp_ajax_sch_add_saved_method', array( __CLASS__, 'ajax_add_mock' ) );
	}

	/**
	 * Rewrite endpoint.
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( 'sch-payment-methods', EP_ROOT | EP_PAGES );
	}

	/**
	 * My Account menu.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	public static function menu_item( $items ) {
		$new = array();
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new['sch-payment-methods'] = __( 'Payment methods', 'single-client-hub' );
			}
		}
		if ( ! isset( $new['sch-payment-methods'] ) ) {
			$new['sch-payment-methods'] = __( 'Payment methods', 'single-client-hub' );
		}
		return $new;
	}

	/**
	 * Get methods for user.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_for_user( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . SCH_Schema::table( 'saved_methods' ) . ' WHERE user_id = %d ORDER BY is_default DESC, id DESC', // phpcs:ignore WordPress.DB.PreparedSQL
				$user_id
			),
			ARRAY_A
		);

		// Bridge: WooCommerce Stripe tokens if plugin present.
		if ( class_exists( 'WC_Payment_Tokens' ) ) {
			$tokens = WC_Payment_Tokens::get_customer_tokens( $user_id, 'stripe' );
			foreach ( $tokens as $token ) {
				$rows[] = array(
					'id'         => 'stripe_' . $token->get_id(),
					'user_id'    => $user_id,
					'provider'   => 'stripe',
					'token'      => $token->get_token(),
					'brand'      => method_exists( $token, 'get_card_type' ) ? $token->get_card_type() : 'card',
					'last4'      => method_exists( $token, 'get_last4' ) ? $token->get_last4() : '****',
					'exp_month'  => method_exists( $token, 'get_expiry_month' ) ? $token->get_expiry_month() : null,
					'exp_year'   => method_exists( $token, 'get_expiry_year' ) ? $token->get_expiry_year() : null,
					'is_default' => $token->is_default() ? 1 : 0,
					'external'   => true,
				);
			}
		}

		return $rows ? $rows : array();
	}

	/**
	 * Add saved method.
	 *
	 * @param int   $user_id User.
	 * @param array $data    Data.
	 * @return int|false
	 */
	public static function add( $user_id, array $data ) {
		global $wpdb;

		if ( ! empty( $data['is_default'] ) ) {
			$wpdb->update(
				SCH_Schema::table( 'saved_methods' ),
				array( 'is_default' => 0 ),
				array( 'user_id' => $user_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		$ok = $wpdb->insert(
			SCH_Schema::table( 'saved_methods' ),
			array(
				'user_id'    => $user_id,
				'provider'   => sanitize_key( $data['provider'] ?? 'sch_mock' ),
				'token'      => sanitize_text_field( $data['token'] ?? '' ),
				'brand'      => sanitize_text_field( $data['brand'] ?? 'card' ),
				'last4'      => substr( preg_replace( '/\D/', '', (string) ( $data['last4'] ?? '' ) ), -4 ),
				'exp_month'  => isset( $data['exp_month'] ) ? (int) $data['exp_month'] : null,
				'exp_year'   => isset( $data['exp_year'] ) ? (int) $data['exp_year'] : null,
				'is_default' => ! empty( $data['is_default'] ) ? 1 : 0,
				'meta'       => isset( $data['meta'] ) ? wp_json_encode( $data['meta'] ) : null,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Delete method.
	 *
	 * @param int $user_id User.
	 * @param int $id      Method ID.
	 * @return bool
	 */
	public static function delete( $user_id, $id ) {
		global $wpdb;
		return (bool) $wpdb->delete(
			SCH_Schema::table( 'saved_methods' ),
			array(
				'id'      => $id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Set default.
	 *
	 * @param int $user_id User.
	 * @param int $id      Method ID.
	 */
	public static function set_default( $user_id, $id ) {
		global $wpdb;
		$wpdb->update(
			SCH_Schema::table( 'saved_methods' ),
			array( 'is_default' => 0 ),
			array( 'user_id' => $user_id ),
			array( '%d' ),
			array( '%d' )
		);
		$wpdb->update(
			SCH_Schema::table( 'saved_methods' ),
			array( 'is_default' => 1 ),
			array(
				'id'      => $id,
				'user_id' => $user_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * Account page UI.
	 */
	public static function render_account_page() {
		$user_id = get_current_user_id();
		$methods = self::get_for_user( $user_id );
		include SCH_PLUGIN_DIR . 'templates/payment-methods.php';
	}

	/**
	 * AJAX delete.
	 */
	public static function ajax_delete() {
		check_ajax_referer( 'sch_hub', 'nonce' );
		$user_id = get_current_user_id();
		$id      = isset( $_POST['method_id'] ) ? (int) $_POST['method_id'] : 0;
		if ( ! $user_id || ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'single-client-hub' ) ), 400 );
		}
		self::delete( $user_id, $id );
		wp_send_json_success();
	}

	/**
	 * AJAX set default.
	 */
	public static function ajax_set_default() {
		check_ajax_referer( 'sch_hub', 'nonce' );
		$user_id = get_current_user_id();
		$id      = isset( $_POST['method_id'] ) ? (int) $_POST['method_id'] : 0;
		if ( ! $user_id || ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request', 'single-client-hub' ) ), 400 );
		}
		self::set_default( $user_id, $id );
		wp_send_json_success();
	}

	/**
	 * AJAX add mock card.
	 */
	public static function ajax_add_mock() {
		check_ajax_referer( 'sch_hub', 'nonce' );
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Please log in', 'single-client-hub' ) ), 401 );
		}
		$last4 = isset( $_POST['last4'] ) ? substr( preg_replace( '/\D/', '', wp_unslash( $_POST['last4'] ) ), -4 ) : '4242';
		$id    = self::add(
			$user_id,
			array(
				'provider'   => 'sch_mock',
				'token'      => 'tok_user_' . wp_generate_password( 12, false ),
				'brand'      => isset( $_POST['brand'] ) ? sanitize_text_field( wp_unslash( $_POST['brand'] ) ) : 'visa',
				'last4'      => $last4,
				'exp_month'  => isset( $_POST['exp_month'] ) ? (int) $_POST['exp_month'] : 12,
				'exp_year'   => isset( $_POST['exp_year'] ) ? (int) $_POST['exp_year'] : ( (int) gmdate( 'Y' ) + 3 ),
				'is_default' => 1,
			)
		);
		wp_send_json_success( array( 'id' => $id ) );
	}
}
