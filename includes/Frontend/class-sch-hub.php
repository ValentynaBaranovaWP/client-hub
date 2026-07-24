<?php
/**
 * Client hub UI: header trigger (replaces mini-cart) + panel.
 *
 * @package SingleClientHub
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SCH_Hub
 */
class SCH_Hub {

	/**
	 * Whether the trigger button was already printed this request.
	 *
	 * @var bool
	 */
	private static $trigger_rendered = false;

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_panel' ) );

		add_shortcode( 'sch_hub_trigger', array( __CLASS__, 'shortcode_trigger' ) );

		add_filter( 'render_block', array( __CLASS__, 'replace_mini_cart_block' ), 10, 2 );
		add_filter( 'widget_display_callback', array( __CLASS__, 'replace_cart_widget' ), 10, 3 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'cart_fragments' ) );

		// Classic Storefront header cart slot.
		add_action( 'after_setup_theme', array( __CLASS__, 'replace_storefront_cart' ), 20 );
	}

	/**
	 * Assets.
	 */
	public static function assets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style( 'sch-frontend', SCH_PLUGIN_URL . 'assets/css/sch-frontend.css', array(), SCH_VERSION );
		wp_add_inline_style( 'sch-frontend', SCH_Colors::inline_css() );
		wp_enqueue_script( 'sch-frontend', SCH_PLUGIN_URL . 'assets/js/sch-frontend.js', array(), SCH_VERSION, true );

		$count = ( WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;

		wp_localize_script(
			'sch-frontend',
			'schHub',
			array(
				'restUrl'    => esc_url_raw( rest_url( 'sch/v1/' ) ),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'ajaxNonce'  => wp_create_nonce( 'sch_hub' ),
				'cartCount'  => (int) $count,
				'isLoggedIn' => is_user_logged_in(),
				'accountUrl' => wc_get_page_permalink( 'myaccount' ),
				'cartUrl'    => wc_get_cart_url(),
				'checkoutUrl'=> wc_get_checkout_url(),
				'i18n'       => array(
					'hub'        => __( 'Client hub', 'single-client-hub' ),
					'cart'       => __( 'Cart', 'single-client-hub' ),
					'account'    => __( 'Account', 'single-client-hub' ),
					'emptyCart'  => __( 'Cart is empty', 'single-client-hub' ),
					'login'      => __( 'Log in to see your orders', 'single-client-hub' ),
					'apply'      => __( 'Apply', 'single-client-hub' ),
					'coupon'     => __( 'Coupon code', 'single-client-hub' ),
					'total'      => __( 'Total', 'single-client-hub' ),
					'checkout'   => __( 'Checkout', 'single-client-hub' ),
					'viewCart'   => __( 'View cart', 'single-client-hub' ),
					'reorder'    => __( 'Reorder', 'single-client-hub' ),
					'subscribe'  => __( 'Subscription', 'single-client-hub' ),
					'pause'      => __( 'Pause', 'single-client-hub' ),
					'resume'     => __( 'Resume', 'single-client-hub' ),
					'cancel'     => __( 'Cancel', 'single-client-hub' ),
					'loading'    => __( 'Loading…', 'single-client-hub' ),
					'error'      => __( 'Something went wrong', 'single-client-hub' ),
					'noOrders'   => __( 'No orders yet', 'single-client-hub' ),
					'coupons'    => __( 'Your coupons', 'single-client-hub' ),
					'applied'    => __( 'Applied', 'single-client-hub' ),
					'nextCharge' => __( 'Next charge', 'single-client-hub' ),
					'removeItem' => __( 'Remove item', 'single-client-hub' ),
				),
			)
		);
	}

	/**
	 * Shortcode [sch_hub_trigger] — place in theme header instead of mini-cart.
	 *
	 * @return string
	 */
	public static function shortcode_trigger() {
		return self::get_trigger_html();
	}

	/**
	 * Replace WooCommerce Mini Cart block with hub trigger.
	 *
	 * @param string $content Block HTML.
	 * @param array  $block   Parsed block.
	 * @return string
	 */
	public static function replace_mini_cart_block( $content, $block ) {
		if ( empty( $block['blockName'] ) || 'woocommerce/mini-cart' !== $block['blockName'] ) {
			return $content;
		}
		if ( ! apply_filters( 'sch_replace_mini_cart', true ) ) {
			return $content;
		}
		$html = self::get_trigger_html();
		return $html ? $html : $content;
	}

	/**
	 * Replace classic WooCommerce Cart widget with hub trigger.
	 *
	 * @param array     $instance Widget settings.
	 * @param WP_Widget $widget   Widget instance.
	 * @param array     $args     Sidebar args.
	 * @return array|false
	 */
	public static function replace_cart_widget( $instance, $widget, $args ) {
		if ( ! $instance || ! is_object( $widget ) ) {
			return $instance;
		}
		if ( ! apply_filters( 'sch_replace_mini_cart', true ) ) {
			return $instance;
		}
		$is_cart = ( class_exists( 'WC_Widget_Cart', false ) && $widget instanceof WC_Widget_Cart )
			|| ( isset( $widget->id_base ) && 'woocommerce_widget_cart' === $widget->id_base );
		if ( ! $is_cart ) {
			return $instance;
		}

		$html = self::get_trigger_html();
		if ( ! $html ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup built in template.
		echo isset( $args['before_widget'] ) ? $args['before_widget'] : '';
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo isset( $args['after_widget'] ) ? $args['after_widget'] : '';

		return false;
	}

	/**
	 * Swap Storefront header cart for hub trigger when that theme is active.
	 */
	public static function replace_storefront_cart() {
		if ( ! function_exists( 'storefront_header_cart' ) ) {
			return;
		}
		if ( ! apply_filters( 'sch_replace_mini_cart', true ) ) {
			return;
		}
		remove_action( 'storefront_header', 'storefront_header_cart', 60 );
		add_action( 'storefront_header', array( __CLASS__, 'print_trigger' ), 60 );
	}

	/**
	 * Echo trigger (for theme action hooks).
	 */
	public static function print_trigger() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_trigger_html();
	}

	/**
	 * Build trigger HTML once per request.
	 *
	 * @param array $args Optional. `floating` => bool.
	 * @return string
	 */
	public static function get_trigger_html( $args = array() ) {
		if ( self::$trigger_rendered ) {
			return '';
		}
		if ( is_admin() && ! wp_doing_ajax() ) {
			return '';
		}

		self::$trigger_rendered = true;
		$sch_hub_trigger_floating = ! empty( $args['floating'] );

		ob_start();
		include SCH_PLUGIN_DIR . 'templates/hub-trigger.php';
		return (string) ob_get_clean();
	}

	/**
	 * Keep badge in sync with WooCommerce cart fragments (classic AJAX add-to-cart).
	 *
	 * @param array $fragments Fragments.
	 * @return array
	 */
	public static function cart_fragments( $fragments ) {
		$count = ( WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
		ob_start();
		?>
		<span class="sch-hub-badge" id="sch-hub-badge" <?php echo $count ? '' : 'hidden'; ?>>
			<?php echo esc_html( (string) $count ); ?>
		</span>
		<?php
		$fragments['#sch-hub-badge'] = ob_get_clean();
		return $fragments;
	}

	/**
	 * Panel (+ floating trigger fallback) in footer.
	 */
	public static function render_panel() {
		if ( is_admin() ) {
			return;
		}

		$sch_hub_floating = ! self::$trigger_rendered;
		if ( $sch_hub_floating ) {
			self::$trigger_rendered = true;
		}

		include SCH_PLUGIN_DIR . 'templates/hub.php';
	}
}
