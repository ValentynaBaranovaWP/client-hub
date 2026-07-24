<?php
/**
 * Gateway module: installment / authorization hold.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Gateway_Installment
 */
class SCH_Gateway_Installment extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'sch_installment';
		$this->method_title       = __( 'SCH: Installment / Hold', 'single-client-hub' );
		$this->method_description = __( 'Authorize funds (hold) with later capture or installment (mock).', 'single-client-hub' );
		$this->has_fields         = true;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'Installment / authorization hold', 'single-client-hub' ) );
		$this->description = $this->get_option( 'description', __( 'Funds will be held on the card; charged after confirmation.', 'single-client-hub' ) );
		$this->enabled     = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Settings.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable', 'single-client-hub' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable installment / hold', 'single-client-hub' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Title', 'single-client-hub' ),
				'type'    => 'text',
				'default' => __( 'Installment / authorization hold', 'single-client-hub' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'single-client-hub' ),
				'type'    => 'textarea',
				'default' => __( 'Funds will be held on the card; charged after confirmation.', 'single-client-hub' ),
			),
		);
	}

	/**
	 * Checkout fields: choose saved method or mock card last4.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}

		$user_id = get_current_user_id();
		$methods = $user_id ? SCH_Saved_Methods::get_for_user( $user_id ) : array();

		echo '<fieldset class="sch-installment-fields">';
		if ( $methods ) {
			echo '<p><label for="sch_saved_method">' . esc_html__( 'Saved card', 'single-client-hub' ) . '</label>';
			echo '<select name="sch_saved_method" id="sch_saved_method">';
			echo '<option value="">' . esc_html__( '— new / mock —', 'single-client-hub' ) . '</option>';
			foreach ( $methods as $m ) {
				$label = sprintf(
					'%s •••• %s',
					esc_html( $m['brand'] ),
					esc_html( $m['last4'] )
				);
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr( (string) $m['id'] ),
					selected( ! empty( $m['is_default'] ), true, false ),
					$label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				);
			}
			echo '</select></p>';
		}

		echo '<p><label for="sch_hold_last4">' . esc_html__( 'Last 4 digits (demo)', 'single-client-hub' ) . '</label> ';
		echo '<input type="text" name="sch_hold_last4" id="sch_hold_last4" maxlength="4" pattern="[0-9]{4}" placeholder="4242" /></p>';

		$days = (int) get_option( 'sch_installment_hold_days', 7 );
		printf(
			'<p class="sch-muted">%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: days */
					__( 'Hold is valid for up to %d days (Client Hub settings).', 'single-client-hub' ),
					$days
				)
			)
		);
		echo '</fieldset>';
	}

	/**
	 * Process hold authorization.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}

		$saved_id = isset( $_POST['sch_saved_method'] ) ? sanitize_text_field( wp_unslash( $_POST['sch_saved_method'] ) ) : ''; // phpcs:ignore
		$last4    = isset( $_POST['sch_hold_last4'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['sch_hold_last4'] ) ) : ''; // phpcs:ignore
		$last4    = substr( $last4, -4 );
		if ( ! $last4 && $saved_id && is_numeric( $saved_id ) ) {
			global $wpdb;
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT last4 FROM ' . SCH_Schema::table( 'saved_methods' ) . ' WHERE id = %d AND user_id = %d', // phpcs:ignore
					(int) $saved_id,
					get_current_user_id()
				),
				ARRAY_A
			);
			$last4 = $row['last4'] ?? '0000';
		}
		if ( ! $last4 ) {
			$last4 = '4242';
		}

		$auth = $this->authorize_hold( $order, $last4, $saved_id );

		$order->update_meta_data( '_sch_hold_id', $auth['hold_id'] );
		$order->update_meta_data( '_sch_hold_last4', $last4 );
		$order->update_meta_data( '_sch_saved_method_id', $saved_id );
		$order->update_status( 'sch-hold', __( 'Funds authorized (hold). Awaiting capture.', 'single-client-hub' ) );
		$order->save();

		if ( $order->get_user_id() && empty( $saved_id ) && SCH_Plugin::is_test_mode() ) {
			SCH_Saved_Methods::add(
				$order->get_user_id(),
				array(
					'provider'   => 'sch_mock',
					'token'      => $auth['token'],
					'brand'      => 'visa',
					'last4'      => $last4,
					'exp_month'  => 12,
					'exp_year'   => (int) gmdate( 'Y' ) + 3,
					'is_default' => 1,
				)
			);
		}

		SCH_Plugin::log_event(
			'hold_authorized',
			__( 'Hold authorized', 'single-client-hub' ),
			array(
				'user_id'  => $order->get_user_id(),
				'order_id' => $order_id,
				'payload'  => $auth,
			)
		);

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Mock authorize.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $last4    Last4.
	 * @param string   $saved_id Saved method.
	 * @return array
	 */
	private function authorize_hold( WC_Order $order, $last4, $saved_id ) {
		$request = array(
			'order_id'         => $order->get_id(),
			'amount'           => (float) $order->get_total(),
			'currency'         => $order->get_currency(),
			'card_last4'       => $last4,
			'saved_method_id'  => $saved_id,
			'hold_days'        => (int) get_option( 'sch_installment_hold_days', 7 ),
			'test_mode'        => SCH_Plugin::is_test_mode(),
		);

		$response = array(
			'hold_id'   => 'HOLD-' . $order->get_id() . '-' . strtoupper( wp_generate_password( 6, false ) ),
			'status'    => 'authorized',
			'token'     => 'tok_hold_' . wp_generate_password( 16, false ),
			'expires'   => gmdate( 'c', time() + ( (int) get_option( 'sch_installment_hold_days', 7 ) * DAY_IN_SECONDS ) ),
			'test_mode' => SCH_Plugin::is_test_mode(),
		);

		SCH_Payment_Logger::log(
			array(
				'order_id'   => $order->get_id(),
				'gateway'    => $this->id,
				'event_type' => 'hold_authorize',
				'direction'  => 'outbound',
				'status'     => 'authorized',
				'http_code'  => 201,
				'request'    => $request,
				'response'   => $response,
			)
		);

		return $response;
	}
}
