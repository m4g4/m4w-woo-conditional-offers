<?php
/**
 * Frontend behavior for the M4W Woo Conditional Offers plugin:
 * asset enqueues, the [m4w_wco_offer] shortcode and the public AJAX handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class M4W_WCO_Frontend {

	public function __construct() {
		add_shortcode( 'm4w_wco_offer', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_ajax_' . 'm4w_wco_check_offers', array( $this, 'ajax_check_offers' ) );
		add_action( 'wp_ajax_nopriv_' . 'm4w_wco_check_offers', array( $this, 'ajax_check_offers' ) );
		add_action( 'wp_ajax_' . 'm4w_wco_get_offer_product', array( $this, 'ajax_get_offer_product' ) );
		add_action( 'wp_ajax_nopriv_' . 'm4w_wco_get_offer_product', array( $this, 'ajax_get_offer_product' ) );
		add_action( 'wp_ajax_' . 'm4w_wco_get_offer_popup_content', array( $this, 'ajax_get_offer_popup_content' ) );
		add_action( 'wp_ajax_nopriv_' . 'm4w_wco_get_offer_popup_content', array( $this, 'ajax_get_offer_popup_content' ) );
	}

	public function enqueue_frontend_assets() {
		if ( ! is_admin() ) {
			wp_enqueue_script(
				'm4w-wco',
				M4W_WCO_URL . '/js/m4w-wco.js',
				array( 'jquery', 'wc-cart-fragments' ),
				M4W_WCO_VERSION,
				true
			);

			wp_localize_script( 'm4w-wco', 'm4wWco', array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( M4W_WCO_CHECK_NONCE_ACTION ),
				'rules'            => $this->get_rules_for_js(),
				'popupCookieName'  => 'm4w_wco_offer_popup_shown',
				'i18n'             => array(
					'close' => __( 'Close', 'm4w-wco' ),
				),
			) );

			wp_enqueue_style(
				'm4w-wco',
				M4W_WCO_URL . '/css/m4w-wco.css',
				array(),
				M4W_WCO_VERSION
			);
		}
	}

	public function get_rules_for_js() {
		$rules = M4W_WCO_Rules::get_rules();
		$js_rules = array();
		foreach ( $rules as $rule ) {
			$js_rules[] = array(
				'id'                   => $rule['id'],
				'trigger_product_ids'  => $rule['trigger_product_ids'] ?? array( $rule['trigger_product_id'] ?? 0 ),
				'offer_product_id'     => $rule['offer_product_id'] ?? 0,
				'label'                => $rule['label'] ?? '',
				'show_popup'           => ! empty( $rule['show_popup'] ),
				'show_toast'           => ! empty( $rule['show_toast'] ),
				'popup_delay'          => $rule['popup_delay'] ?? 1,
				'toast_timeout'        => $rule['toast_timeout'] ?? 8,
				'popup_once_per_session' => ! empty( $rule['popup_once_per_session'] ),
				'toast_css'            => $rule['toast_css'] ?? '',
				'discount_enabled'     => ! empty( $rule['discount_enabled'] ),
				'discount_type'        => $rule['discount_type'] ?? 'percentage',
				'discount_value'       => floatval( $rule['discount_value'] ?? 0 ),
				'discount_apply_to'    => $rule['discount_apply_to'] ?? 'offer_only',
			);
		}
		return $js_rules;
	}

	public function render_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'rule_id' => 0,
		), $atts, 'm4w_wco_offer' );

		$rule_id = intval( $atts['rule_id'] );
		if ( $rule_id <= 0 ) {
			return '';
		}

		$rules = M4W_WCO_Rules::get_rules();
		$rule = M4W_WCO_Rules::get_rule( $rules, $rule_id );

		if ( ! $rule ) {
			return '';
		}

		$is_active = M4W_WCO_Rules::is_rule_active( $rule );
		$content = $is_active ? $this->render_rule_content( $rule ) : '';
		$style = $is_active ? '' : ' style="display:none;"';

		return '<div class="conditional-offer-inline" data-rule-id="' . esc_attr( $rule_id ) . '"' . $style . '>' . $content . '</div>';
	}

	public function ajax_check_offers() {
		check_ajax_referer( M4W_WCO_CHECK_NONCE_ACTION, 'nonce' );

		$cart_product_ids = M4W_WCO_Rules::get_cart_product_ids();
		$rules = M4W_WCO_Rules::get_rules();

		$active_rules = array();
		foreach ( $rules as $rule ) {
			if ( M4W_WCO_Rules::is_rule_active( $rule, $cart_product_ids ) ) {
				$active_rules[] = array(
					'id'                   => $rule['id'],
					'content'              => $this->render_rule_content( $rule ),
					'show_popup'           => ! empty( $rule['show_popup'] ),
					'show_toast'           => ! empty( $rule['show_toast'] ),
					'trigger_product_ids'  => $rule['trigger_product_ids'] ?? array(),
					'popup_once_per_session' => ! empty( $rule['popup_once_per_session'] ),
					'popup_delay'          => $rule['popup_delay'] ?? 1,
					'toast_timeout'        => $rule['toast_timeout'] ?? 8,
					'toast_css'            => $rule['toast_css'] ?? '',
				);
			}
		}

		wp_send_json_success( array( 'active_rules' => $active_rules ) );
	}

	public function ajax_get_offer_product() {
		check_ajax_referer( M4W_WCO_CHECK_NONCE_ACTION, 'nonce' );

		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json_error( __( 'Product not found.', 'm4w-wco' ) );
		}

		$button = do_shortcode( sprintf( '[ajax_add_to_cart id="%d" text="%s"]', $product_id, esc_attr__( 'Add to Cart', 'm4w-wco' ) ) );

		wp_send_json_success( array(
			'name'       => $product->get_name(),
			'price_html' => $product->get_price_html(),
			'image'      => $product->get_image( 'thumbnail' ),
			'button'     => $button,
		) );
	}

	public function ajax_get_offer_popup_content() {
		check_ajax_referer( M4W_WCO_CHECK_NONCE_ACTION, 'nonce' );

		$rule_id = isset( $_POST['rule_id'] ) ? intval( $_POST['rule_id'] ) : 0;
		$rules = M4W_WCO_Rules::get_rules();
		$rule = M4W_WCO_Rules::get_rule( $rules, $rule_id );

		if ( ! $rule ) {
			wp_send_json_error( __( 'Rule not found.', 'm4w-wco' ) );
		}

		if ( ! M4W_WCO_Rules::is_rule_active( $rule ) ) {
			wp_send_json_error( __( 'Offer is not active for the current cart.', 'm4w-wco' ) );
		}

		wp_send_json_success( array( 'content' => $this->render_rule_content( $rule ) ) );
	}

	public function render_rule_content( array $rule ) {
		if ( ! empty( $rule['custom_content'] ) ) {
			return do_shortcode( $rule['custom_content'] );
		}

		return $this->render_default_offer( intval( $rule['offer_product_id'] ?? 0 ), intval( $rule['id'] ?? 0 ) );
	}

	public function render_default_offer( int $product_id, int $rule_id = 0 ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '';
		}

		$image = $product->get_image( 'thumbnail', array( 'class' => 'conditional-offer-image' ) );
		$name  = esc_html( $product->get_name() );
		$price = $product->get_price_html();

		$button = do_shortcode( sprintf( '[ajax_add_to_cart id="%d" text="%s"]', $product_id, esc_attr__( 'Add to Cart', 'm4w-wco' ) ) );

		ob_start();
		?>
		<div class="conditional-offer-wrapper" data-rule-id="<?php echo esc_attr( $rule_id ); ?>">
			<div class="conditional-offer-image"><?php echo $image; ?></div>
			<div class="conditional-offer-content">
				<h4 class="conditional-offer-title"><?php echo $name; ?></h4>
				<div class="conditional-offer-price"><?php echo $price; ?></div>
				<div class="conditional-offer-action"><?php echo $button; ?></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
