<?php
/**
 * Admin hub: single settings page for all payment methods & options.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Admin
 */
class SCH_Admin {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Menu under WooCommerce.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Client Hub', 'single-client-hub' ),
			__( 'Client Hub', 'single-client-hub' ),
			'manage_woocommerce',
			'sch-hub',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register options.
	 */
	public static function register_settings() {
		$checkboxes = array(
			'sch_test_mode',
			'sch_mask_sensitive',
			'sch_coupon_on_payment',
			'sch_email_status_change',
			'sch_email_coupon',
			'sch_email_failed_renewal',
			'sch_email_webhook',
		);
		foreach ( $checkboxes as $key ) {
			register_setting(
				'sch_settings',
				$key,
				array(
					'type'              => 'string',
					'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
					'default'           => 'no',
				)
			);
		}

		$keys = array(
			'sch_log_retention_days',
			'sch_coupon_type',
			'sch_coupon_amount',
			'sch_coupon_expiry_days',
			'sch_coupon_usage_limit',
			'sch_bank_invoice_details',
			'sch_bank_webhook_secret',
			'sch_installment_hold_days',
			'sch_subscription_grace_days',
			'sch_color_main',
			'sch_color_accent',
			'sch_color_text',
		);
		foreach ( $keys as $key ) {
			$args = array();
			if ( in_array( $key, array( 'sch_color_main', 'sch_color_accent', 'sch_color_text' ), true ) ) {
				$args['sanitize_callback'] = array( 'SCH_Colors', 'sanitize_option' );
			}
			register_setting( 'sch_settings', $key, $args );
		}

		// Unchecked boxes are absent from POST — force "no" when settings form saved.
		if ( isset( $_POST['option_page'] ) && 'sch_settings' === $_POST['option_page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ( $checkboxes as $key ) {
				if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
					$_POST[ $key ] = 'no';
				}
			}
		}
	}

	/**
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_checkbox( $value ) {
		return ( 'yes' === $value || '1' === $value || true === $value ) ? 'yes' : 'no';
	}

	/**
	 * Assets.
	 *
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'sch-hub' ) ) {
			return;
		}
		wp_enqueue_style( 'sch-admin', SCH_PLUGIN_URL . 'assets/css/sch-admin.css', array(), SCH_VERSION );
		wp_enqueue_script( 'sch-admin', SCH_PLUGIN_URL . 'assets/js/sch-admin.js', array( 'jquery' ), SCH_VERSION, true );
		wp_localize_script(
			'sch-admin',
			'schAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => esc_url_raw( rest_url( 'sch/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'i18n'    => array(
					'simulated' => __( 'Webhook simulated', 'single-client-hub' ),
					'error'     => __( 'Error', 'single-client-hub' ),
				),
			)
		);
	}

	/**
	 * Render settings + tabs.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'settings' => __( 'Settings', 'single-client-hub' ),
			'payments' => __( 'Payment logs', 'single-client-hub' ),
			'events'   => __( 'Events', 'single-client-hub' ),
			'gateways' => __( 'Payment modules', 'single-client-hub' ),
		);

		echo '<div class="wrap sch-admin-wrap"><h1>' . esc_html__( 'Single Client Hub', 'single-client-hub' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url   = admin_url( 'admin.php?page=sch-hub&tab=' . $slug );
			$class = $tab === $slug ? 'nav-tab nav-tab-active' : 'nav-tab';
			printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
		}
		echo '</nav>';

		switch ( $tab ) {
			case 'payments':
				SCH_Admin_Payments::render_logs();
				break;
			case 'events':
				SCH_Admin_Payments::render_events();
				break;
			case 'gateways':
				self::render_gateways_tab();
				break;
			default:
				self::render_settings_tab();
		}

		echo '</div>';
	}

	/**
	 * Settings form.
	 */
	private static function render_settings_tab() {
		?>
		<form method="post" action="options.php" class="sch-settings-form">
			<?php settings_fields( 'sch_settings' ); ?>

			<h2><?php esc_html_e( 'Mode & security', 'single-client-hub' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Test mode', 'single-client-hub' ); ?></th>
					<td>
						<label><input type="checkbox" name="sch_test_mode" value="yes" <?php checked( get_option( 'sch_test_mode', 'yes' ), 'yes' ); ?>>
						<?php esc_html_e( 'Mock API, webhook simulation', 'single-client-hub' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Data masking', 'single-client-hub' ); ?></th>
					<td>
						<label><input type="checkbox" name="sch_mask_sensitive" value="yes" <?php checked( get_option( 'sch_mask_sensitive', 'yes' ), 'yes' ); ?>>
						<?php esc_html_e( 'Mask cards, IBAN, and tokens in logs', 'single-client-hub' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><label for="sch_log_retention_days"><?php esc_html_e( 'Retain logs (days)', 'single-client-hub' ); ?></label></th>
					<td><input type="number" min="1" name="sch_log_retention_days" id="sch_log_retention_days" value="<?php echo esc_attr( get_option( 'sch_log_retention_days', 90 ) ); ?>" class="small-text"></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Bank invoice', 'single-client-hub' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="sch_bank_invoice_details"><?php esc_html_e( 'Bank details', 'single-client-hub' ); ?></label></th>
					<td>
						<textarea name="sch_bank_invoice_details" id="sch_bank_invoice_details" rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'sch_bank_invoice_details', '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Placeholders: {order_number}, {order_id}, {amount}', 'single-client-hub' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="sch_bank_webhook_secret"><?php esc_html_e( 'Webhook secret', 'single-client-hub' ); ?></label></th>
					<td>
						<input type="text" name="sch_bank_webhook_secret" id="sch_bank_webhook_secret" value="<?php echo esc_attr( get_option( 'sch_bank_webhook_secret', '' ) ); ?>" class="regular-text code">
						<p class="description"><?php echo esc_html( rest_url( 'sch/v1/webhook/sch_bank_invoice' ) ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Hold (days)', 'single-client-hub' ); ?></th>
					<td><input type="number" min="1" name="sch_installment_hold_days" value="<?php echo esc_attr( get_option( 'sch_installment_hold_days', 7 ) ); ?>" class="small-text"></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Coupons after payment', 'single-client-hub' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Auto-issue', 'single-client-hub' ); ?></th>
					<td><label><input type="checkbox" name="sch_coupon_on_payment" value="yes" <?php checked( get_option( 'sch_coupon_on_payment', 'yes' ), 'yes' ); ?>> <?php esc_html_e( 'Issue a coupon after successful payment', 'single-client-hub' ); ?></label></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Type', 'single-client-hub' ); ?></th>
					<td>
						<select name="sch_coupon_type">
							<option value="percent" <?php selected( get_option( 'sch_coupon_type', 'percent' ), 'percent' ); ?>><?php esc_html_e( 'Percentage', 'single-client-hub' ); ?></option>
							<option value="fixed_cart" <?php selected( get_option( 'sch_coupon_type' ), 'fixed_cart' ); ?>><?php esc_html_e( 'Fixed cart discount', 'single-client-hub' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Amount', 'single-client-hub' ); ?></th>
					<td><input type="number" step="0.01" name="sch_coupon_amount" value="<?php echo esc_attr( get_option( 'sch_coupon_amount', 10 ) ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Validity (days)', 'single-client-hub' ); ?></th>
					<td><input type="number" name="sch_coupon_expiry_days" value="<?php echo esc_attr( get_option( 'sch_coupon_expiry_days', 30 ) ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Usage limit', 'single-client-hub' ); ?></th>
					<td><input type="number" name="sch_coupon_usage_limit" value="<?php echo esc_attr( get_option( 'sch_coupon_usage_limit', 1 ) ); ?>" class="small-text"></td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Hub appearance', 'single-client-hub' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Colors used in the client hub panel and mini-cart.', 'single-client-hub' ); ?></p>
			<table class="form-table">
				<?php
				$color_defaults = SCH_Colors::defaults();
				$color_fields     = array(
					'sch_color_main'   => array(
						'label'   => __( 'Main (background)', 'single-client-hub' ),
						'default' => $color_defaults['main'],
					),
					'sch_color_accent' => array(
						'label'   => __( 'Accent', 'single-client-hub' ),
						'default' => $color_defaults['accent'],
					),
					'sch_color_text'   => array(
						'label'   => __( 'Text', 'single-client-hub' ),
						'default' => $color_defaults['text'],
					),
				);
				foreach ( $color_fields as $key => $field ) :
					?>
				<tr>
					<th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
					<td>
						<input type="color" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_option( $key, $field['default'] ) ); ?>">
						<code><?php echo esc_html( get_option( $key, $field['default'] ) ); ?></code>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>

			<h2><?php esc_html_e( 'Notifications', 'single-client-hub' ); ?></h2>
			<table class="form-table">
				<?php
				$mails = array(
					'sch_email_status_change'  => __( 'Status change', 'single-client-hub' ),
					'sch_email_coupon'         => __( 'Coupon issued', 'single-client-hub' ),
					'sch_email_failed_renewal' => __( 'Failed renewal', 'single-client-hub' ),
					'sch_email_webhook'        => __( 'Webhook events', 'single-client-hub' ),
				);
				foreach ( $mails as $key => $label ) :
					?>
					<tr>
						<th><?php echo esc_html( $label ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="yes" <?php checked( get_option( $key, 'yes' ), 'yes' ); ?>> <?php esc_html_e( 'Send email', 'single-client-hub' ); ?></label></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Gateways overview + webhook simulator.
	 */
	private static function render_gateways_tab() {
		?>
		<div class="sch-gateways-panel">
			<p><?php esc_html_e( 'Payment methods are registered as WooCommerce gateway modules.', 'single-client-hub' ); ?></p>
			<ul>
				<li><strong>sch_bank_invoice</strong> — <?php esc_html_e( 'Bank invoice (mock + webhook)', 'single-client-hub' ); ?></li>
				<li><strong>sch_installment</strong> — <?php esc_html_e( 'Installment / hold', 'single-client-hub' ); ?></li>
				<li>Stripe — <?php esc_html_e( 'when woocommerce-gateway-stripe is active; saved tokens appear in the account area', 'single-client-hub' ); ?></li>
			</ul>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>"><?php esc_html_e( 'WooCommerce Checkout settings', 'single-client-hub' ); ?></a></p>

			<hr>
			<h2><?php esc_html_e( 'Webhook simulation (test mode)', 'single-client-hub' ); ?></h2>
			<p>
				<label><?php esc_html_e( 'Order ID', 'single-client-hub' ); ?>
					<input type="number" id="sch-sim-order" class="small-text">
				</label>
				<label><?php esc_html_e( 'Gateway', 'single-client-hub' ); ?>
					<select id="sch-sim-gateway">
						<option value="sch_bank_invoice">sch_bank_invoice</option>
						<option value="sch_installment">sch_installment</option>
					</select>
				</label>
				<label><?php esc_html_e( 'Event', 'single-client-hub' ); ?>
					<select id="sch-sim-event">
						<option value="payment_succeeded">payment_succeeded</option>
						<option value="invoice_paid">invoice_paid</option>
						<option value="payment_failed">payment_failed</option>
						<option value="hold_authorized">hold_authorized</option>
						<option value="hold_captured">hold_captured</option>
						<option value="hold_released">hold_released</option>
					</select>
				</label>
				<button type="button" class="button button-primary" id="sch-sim-webhook"><?php esc_html_e( 'Simulate', 'single-client-hub' ); ?></button>
			</p>
			<pre id="sch-sim-result" class="sch-sim-result"></pre>
		</div>
		<?php
	}
}
