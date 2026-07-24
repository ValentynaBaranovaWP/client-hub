<?php
/**
 * Gateway module: bank transfer / invoice (mock API + webhook).
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Gateway_Bank_Invoice
 */
class SCH_Gateway_Bank_Invoice extends WC_Payment_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'sch_bank_invoice';
		$this->method_title       = __( 'SCH: Bank invoice', 'single-client-hub' );
		$this->method_description = __( 'Invoice with bank details. Mock API + webhook for demo.', 'single-client-hub' );
		$this->has_fields         = false;
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'Bank transfer / invoice', 'single-client-hub' ) );
		$this->description = $this->get_option( 'description', __( 'You will receive an invoice and payment details after checkout.', 'single-client-hub' ) );
		$this->enabled     = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable', 'single-client-hub' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable bank invoice', 'single-client-hub' ),
				'default' => 'yes',
			),
			'title'       => array(
				'title'   => __( 'Title', 'single-client-hub' ),
				'type'    => 'text',
				'default' => __( 'Bank transfer / invoice', 'single-client-hub' ),
			),
			'description' => array(
				'title'   => __( 'Description', 'single-client-hub' ),
				'type'    => 'textarea',
				'default' => __( 'You will receive an invoice and payment details after checkout.', 'single-client-hub' ),
			),
		);
	}

	/**
	 * Process payment — create mock invoice.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}

		$invoice = $this->create_mock_invoice( $order );

		$order->update_meta_data( '_sch_invoice_id', $invoice['invoice_id'] );
		$order->update_meta_data( '_sch_invoice_payload', wp_json_encode( $invoice ) );
		$order->update_status( 'sch-invoice', __( 'Invoice created; awaiting bank payment.', 'single-client-hub' ) );
		$order->save();

		SCH_Plugin::log_event(
			'invoice_created',
			__( 'Bank invoice created', 'single-client-hub' ),
			array(
				'user_id'  => $order->get_user_id(),
				'order_id' => $order_id,
				'payload'  => $invoice,
			)
		);

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Mock bank API call.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	private function create_mock_invoice( WC_Order $order ) {
		$request = array(
			'merchant'    => get_bloginfo( 'name' ),
			'order_id'    => $order->get_id(),
			'order_number'=> $order->get_order_number(),
			'amount'      => (float) $order->get_total(),
			'currency'    => $order->get_currency(),
			'customer'    => $order->get_billing_email(),
			'test_mode'   => SCH_Plugin::is_test_mode(),
		);

		// Simulated outbound API.
		$response = array(
			'invoice_id'  => 'INV-' . $order->get_id() . '-' . strtoupper( wp_generate_password( 6, false ) ),
			'status'      => 'pending',
			'pay_url'     => rest_url( 'sch/v1/webhook/sch_bank_invoice' ),
			'created_at'  => gmdate( 'c' ),
			'test_mode'   => SCH_Plugin::is_test_mode(),
		);

		SCH_Payment_Logger::log(
			array(
				'order_id'   => $order->get_id(),
				'gateway'    => $this->id,
				'event_type' => 'invoice_create',
				'direction'  => 'outbound',
				'status'     => 'pending',
				'http_code'  => 201,
				'request'    => $request,
				'response'   => $response,
			)
		);

		return $response;
	}

	/**
	 * Thank you instructions.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {
		$this->render_instructions( $order_id );
	}

	/**
	 * Email instructions.
	 *
	 * @param WC_Order $order         Order.
	 * @param bool     $sent_to_admin Admin.
	 * @param bool     $plain_text    Plain.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || $this->id !== $order->get_payment_method() ) {
			return;
		}
		$this->render_instructions( $order->get_id(), $plain_text );
	}

	/**
	 * @param int  $order_id   Order.
	 * @param bool $plain_text Plain.
	 */
	private function render_instructions( $order_id, $plain_text = false ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$tpl = get_option( 'sch_bank_invoice_details', '' );
		$text = str_replace(
			array( '{order_number}', '{order_id}', '{amount}' ),
			array( $order->get_order_number(), (string) $order->get_id(), $order->get_formatted_order_total() ),
			$tpl
		);
		$invoice_id = $order->get_meta( '_sch_invoice_id' );

		if ( $plain_text ) {
			echo "\n" . esc_html( $text ) . "\n";
			if ( $invoice_id ) {
				echo 'Invoice: ' . esc_html( $invoice_id ) . "\n";
			}
			return;
		}

		echo '<section class="sch-invoice-instructions"><h2>' . esc_html__( 'Payment details', 'single-client-hub' ) . '</h2>';
		echo '<pre style="white-space:pre-wrap">' . esc_html( $text ) . '</pre>';
		if ( $invoice_id ) {
			echo '<p><strong>Invoice ID:</strong> ' . esc_html( $invoice_id ) . '</p>';
		}
		if ( SCH_Plugin::is_test_mode() ) {
			echo '<p class="sch-muted">' . esc_html__( 'Test mode: confirm payment via WooCommerce → Client Hub → Payment modules (webhook simulation).', 'single-client-hub' ) . '</p>';
		}
		echo '</section>';
	}
}
