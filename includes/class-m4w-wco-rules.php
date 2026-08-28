<?php
/**
 * Rule model, storage and shared sanitization/validation helpers
 * for the M4W Woo Conditional Offers plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class M4W_WCO_Rules {

	/**
	 * Read and normalize all rules from the options table.
	 *
	 * @return array
	 */
	public static function get_rules() {
		$rules = get_option( M4W_WCO_OPTION_KEY, array() );
		if ( ! is_array( $rules ) ) {
			return array();
		}

		$migrated = false;
		foreach ( $rules as &$rule ) {
			$original = $rule;
			$rule = self::sanitize_rule( $rule );
			if ( $rule !== $original ) {
				$migrated = true;
			}
		}
		unset( $rule );

		$count_before_filter = count( $rules );
		$rules = array_values( array_filter( $rules, function( $rule ) {
			return ! empty( $rule['id'] );
		} ) );
		if ( count( $rules ) !== $count_before_filter ) {
			$migrated = true;
		}

		if ( $migrated ) {
			self::save_rules( $rules );
		}
		return $rules;
	}

	/**
	 * Normalize a single rule array (backwards compatible migration).
	 *
	 * @param array $rule
	 * @return array
	 */
	public static function sanitize_rule( array $rule ) {
		$rule['id'] = isset( $rule['id'] ) ? intval( $rule['id'] ) : 0;
		if ( ! isset( $rule['trigger_product_ids'] ) && isset( $rule['trigger_product_id'] ) ) {
			$rule['trigger_product_ids'] = array( intval( $rule['trigger_product_id'] ) );
		}
		if ( ! isset( $rule['trigger_product_ids'] ) ) {
			$rule['trigger_product_ids'] = array();
		}
		$rule['trigger_product_ids'] = self::sanitize_product_id_list( $rule['trigger_product_ids'] );
		$rule['offer_product_id'] = isset( $rule['offer_product_id'] ) ? intval( $rule['offer_product_id'] ) : 0;
		$rule['label'] = isset( $rule['label'] ) ? sanitize_text_field( $rule['label'] ) : '';
		$rule['custom_content'] = isset( $rule['custom_content'] ) ? (string) $rule['custom_content'] : '';
		// Display targets (checkboxes). Derive from a legacy display_mode if the
		// individual flags are not present yet. The "inline" target is the
		// [m4w_wco_offer] shortcode, which always works and needs no flag.
		$display_mode = isset( $rule['display_mode'] ) ? self::sanitize_display_mode( $rule['display_mode'] ) : '';
		$rule['show_popup'] = ! empty( $rule['show_popup'] ) || in_array( $display_mode, array( 'popup', 'both' ), true );
		$rule['show_toast'] = ! empty( $rule['show_toast'] ) || in_array( $display_mode, array( 'toast', 'both' ), true );
		if ( ! isset( $rule['popup_delay'] ) ) {
			$rule['popup_delay'] = 1;
		}
		$rule['popup_delay'] = self::sanitize_seconds( $rule['popup_delay'], 1, 0, 60 );
		if ( ! isset( $rule['toast_timeout'] ) ) {
			$rule['toast_timeout'] = 8;
		}
		$rule['toast_timeout'] = self::sanitize_seconds( $rule['toast_timeout'], 8, 3, 60, true );
		$rule['popup_once_per_session'] = ! empty( $rule['popup_once_per_session'] );
		$rule['toast_css'] = isset( $rule['toast_css'] ) ? self::sanitize_css( $rule['toast_css'] ) : '';
		if ( ! isset( $rule['discount_enabled'] ) ) {
			$rule['discount_enabled'] = false;
		}
		if ( ! isset( $rule['discount_type'] ) ) {
			$rule['discount_type'] = 'percentage';
		}
		if ( ! isset( $rule['discount_value'] ) ) {
			$rule['discount_value'] = 0;
		}
		if ( ! isset( $rule['discount_apply_to'] ) ) {
			$rule['discount_apply_to'] = 'offer_only';
		}
		return $rule;
	}

	/**
	 * Validate a rule's data. Returns a WP_Error on failure, null when valid.
	 *
	 * @param array $rule
	 * @return \WP_Error|null
	 */
	public static function validate_rule( array $rule ) {
		if ( empty( $rule['trigger_product_ids'] ) ) {
			return new WP_Error( 'missing_trigger_products', __( 'Choose at least one trigger product.', 'm4w-wco' ) );
		}

		if ( empty( $rule['offer_product_id'] ) ) {
			return new WP_Error( 'missing_offer_product', __( 'Choose an offer product.', 'm4w-wco' ) );
		}

		if ( in_array( intval( $rule['offer_product_id'] ), $rule['trigger_product_ids'], true ) ) {
			return new WP_Error( 'offer_matches_trigger', __( 'The offer product cannot also be a trigger product for the same rule.', 'm4w-wco' ) );
		}

		foreach ( $rule['trigger_product_ids'] as $product_id ) {
			if ( ! wc_get_product( $product_id ) ) {
				return new WP_Error( 'invalid_trigger_product', sprintf( __( 'Trigger product #%d was not found.', 'm4w-wco' ), $product_id ) );
			}
		}

		if ( ! wc_get_product( $rule['offer_product_id'] ) ) {
			return new WP_Error( 'invalid_offer_product', sprintf( __( 'Offer product #%d was not found.', 'm4w-wco' ), $rule['offer_product_id'] ) );
		}

		if ( ! empty( $rule['discount_enabled'] ) ) {
			$discount_value = floatval( $rule['discount_value'] ?? 0 );
			if ( $discount_value <= 0 ) {
				return new WP_Error( 'invalid_discount_value', __( 'Discount value must be greater than 0 when discount is enabled.', 'm4w-wco' ) );
			}
			if ( $rule['discount_type'] === 'percentage' && $discount_value > 100 ) {
				return new WP_Error( 'invalid_discount_value', __( 'Percentage discount cannot exceed 100%.', 'm4w-wco' ) );
			}
		}

		return null;
	}

	public static function save_rules( array $rules ) {
		return update_option( M4W_WCO_OPTION_KEY, array_values( $rules ) );
	}

	public static function get_next_id( array $rules ) {
		$max_id = 0;
		foreach ( $rules as $rule ) {
			if ( isset( $rule['id'] ) && $rule['id'] > $max_id ) {
				$max_id = $rule['id'];
			}
		}
		return $max_id + 1;
	}

	public static function find_rule_index( array $rules, int $id ) {
		foreach ( $rules as $index => $rule ) {
			if ( isset( $rule['id'] ) && intval( $rule['id'] ) === intval( $id ) ) {
				return $index;
			}
		}
		return null;
	}

	public static function get_rule( array $rules, int $id ) {
		$index = self::find_rule_index( $rules, $id );
		return null === $index ? null : $rules[ $index ];
	}

	public static function is_rule_active( array $rule, array $cart_product_ids = null ) {
		$cart_product_ids = null === $cart_product_ids ? self::get_cart_product_ids() : $cart_product_ids;
		$trigger_ids = self::sanitize_product_id_list( $rule['trigger_product_ids'] ?? array() );
		$offer_id = intval( $rule['offer_product_id'] ?? 0 );

		if ( empty( $trigger_ids ) || $offer_id <= 0 || empty( $cart_product_ids ) ) {
			return false;
		}

		if ( in_array( $offer_id, $cart_product_ids, true ) ) {
			return false;
		}

		return (bool) array_intersect( $trigger_ids, $cart_product_ids );
	}

	public static function get_cart_product_ids() {
		if ( ! function_exists( 'WC' ) ) {
			return array();
		}

		if ( function_exists( 'wc_load_cart' ) && null === WC()->cart ) {
			wc_load_cart();
		}

		if ( ! WC()->cart ) {
			return array();
		}

		$product_ids = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['product_id'] ) ) {
				$product_ids[] = intval( $cart_item['product_id'] );
			}
			if ( ! empty( $cart_item['variation_id'] ) ) {
				$product_ids[] = intval( $cart_item['variation_id'] );
			}
		}

		return array_values( array_unique( array_filter( $product_ids ) ) );
	}

	public static function is_any_trigger_in_cart( array $trigger_ids ) {
		if ( empty( $trigger_ids ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$pid = $cart_item['product_id'] ?? 0;
			$vid = $cart_item['variation_id'] ?? 0;
			if ( in_array( intval( $pid ), $trigger_ids, true ) || in_array( intval( $vid ), $trigger_ids, true ) ) {
				return true;
			}
		}
		return false;
	}

	public static function is_offer_in_cart( int $offer_product_id ) {
		if ( $offer_product_id <= 0 || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$pid = $cart_item['product_id'] ?? 0;
			$vid = $cart_item['variation_id'] ?? 0;
			if ( intval( $pid ) === $offer_product_id || intval( $vid ) === $offer_product_id ) {
				return true;
			}
		}
		return false;
	}

	/* --------------------------------------------------------------------- */
	/* Sanitization helpers                                                  */
	/* --------------------------------------------------------------------- */

	public static function sanitize_product_id_list( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			$value = array( $value );
		}

		$ids = array();
		foreach ( $value as $id ) {
			$id = intval( $id );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	public static function sanitize_display_mode( $display_mode ) {
		$display_mode = sanitize_key( $display_mode );
		$allowed = array( 'popup', 'toast', 'inline', 'both' );

		return in_array( $display_mode, $allowed, true ) ? $display_mode : 'popup';
	}

	public static function sanitize_seconds( $value, $default, $min, $max, $integer = false ) {
		$value = is_numeric( $value ) ? (float) $value : (float) $default;
		$value = max( $min, min( $max, $value ) );

		return $integer ? intval( round( $value ) ) : $value;
	}

	public static function sanitize_custom_content( $content ) {
		$content = (string) $content;

		return current_user_can( 'unfiltered_html' )
			? wp_kses( $content, self::get_allowed_html() )
			: wp_kses_post( $content );
	}

	public static function sanitize_css( $css ) {
		$css = (string) $css;

		$css = wp_strip_all_tags( $css );
		$css = preg_replace( '|/\*.*?\*/|s', '', $css );
		$css = str_replace( array( '<?', '?>', '<%', '%>' ), '', $css );
		$css = preg_replace( '/expression\s*\(/i', '', $css );
		$css = preg_replace( '/url\s*\(\s*["\']?\s*javascript:/i', '', $css );

		return trim( $css );
	}

	public static function sanitize_discount_type( $type ) {
		$type = sanitize_key( $type );
		$allowed = array( 'percentage', 'fixed' );

		return in_array( $type, $allowed, true ) ? $type : 'percentage';
	}

	public static function sanitize_discount_value( $value ) {
		$value = is_numeric( $value ) ? (float) $value : 0;
		return max( 0, $value );
	}

	public static function sanitize_discount_apply_to( $apply_to ) {
		$apply_to = sanitize_key( $apply_to );
		$allowed = array( 'offer_only', 'both', 'trigger_only' );

		return in_array( $apply_to, $allowed, true ) ? $apply_to : 'offer_only';
	}

	public static function get_allowed_html() {
		return array(
			'div'    => array( 'class' => true, 'id' => true, 'style' => true, 'data-*' => true ),
			'span'   => array( 'class' => true, 'style' => true ),
			'a'      => array( 'href' => true, 'class' => true, 'id' => true, 'target' => true, 'rel' => true, 'data-*' => true ),
			'button' => array( 'class' => true, 'id' => true, 'type' => true, 'data-*' => true, 'disabled' => true ),
			'img'    => array( 'src' => true, 'alt' => true, 'class' => true, 'width' => true, 'height' => true ),
			'h1'     => array( 'class' => true ),
			'h2'     => array( 'class' => true ),
			'h3'     => array( 'class' => true ),
			'h4'     => array( 'class' => true ),
			'h5'     => array( 'class' => true ),
			'h6'     => array( 'class' => true ),
			'p'      => array( 'class' => true, 'style' => true ),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'i'      => array(),
			'u'      => array(),
			'ul'     => array( 'class' => true ),
			'ol'     => array( 'class' => true ),
			'li'     => array( 'class' => true ),
			'table'  => array( 'class' => true ),
			'tr'     => array( 'class' => true ),
			'td'     => array( 'class' => true, 'colspan' => true, 'rowspan' => true ),
			'th'     => array( 'class' => true ),
			'form'   => array( 'action' => true, 'method' => true, 'class' => true ),
			'input'  => array( 'type' => true, 'name' => true, 'value' => true, 'class' => true, 'placeholder' => true, 'data-*' => true ),
			'textarea' => array( 'name' => true, 'class' => true, 'rows' => true, 'cols' => true ),
			'select' => array( 'name' => true, 'class' => true ),
			'option' => array( 'value' => true, 'selected' => true ),
		);
	}

	public static function get_post_value( $key, $default = '' ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}

		return wp_unslash( $_POST[ $key ] );
	}
}
