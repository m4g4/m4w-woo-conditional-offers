<?php
/**
 * Automatic discount application for the M4W Woo Conditional Offers plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class M4W_WCO_Discounts {

	public function __construct() {
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_conditional_discounts' ), 20, 1 );
	}

	/**
	 * Apply automatic discounts for conditional offer rules.
	 *
	 * @param WC_Cart $cart The cart object.
	 */
	public function apply_conditional_discounts( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$rules = M4W_WCO_Rules::get_rules();

		if ( empty( $rules ) ) {
			return;
		}

		$cart_product_ids = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			$cart_product_ids[] = intval( $cart_item['product_id'] );
			if ( ! empty( $cart_item['variation_id'] ) ) {
				$cart_product_ids[] = intval( $cart_item['variation_id'] );
			}
		}

		if ( empty( $cart_product_ids ) ) {
			return;
		}

		$total_discount = 0.0;
		$applied        = false;

		foreach ( $rules as $rule ) {
			if ( empty( $rule['discount_enabled'] ) ) {
				continue;
			}

			$trigger_ids = $rule['trigger_product_ids'] ?? array();
			$offer_id = intval( $rule['offer_product_id'] ?? 0 );

			if ( empty( $trigger_ids ) || $offer_id <= 0 ) {
				continue;
			}

			$has_trigger = false;
			foreach ( $trigger_ids as $tid ) {
				if ( in_array( $tid, $cart_product_ids ) ) {
					$has_trigger = true;
					break;
				}
			}

			if ( ! $has_trigger ) {
				continue;
			}

			if ( ! in_array( $offer_id, $cart_product_ids ) ) {
				continue;
			}

			$discount_type  = $rule['discount_type'] ?? 'percentage';
			$discount_value = floatval( $rule['discount_value'] ?? 0 );
			$apply_to       = $rule['discount_apply_to'] ?? 'offer_only';

			if ( $discount_value <= 0 ) {
				continue;
			}

			foreach ( $cart->get_cart() as $cart_item ) {
				$product_id = intval( $cart_item['product_id'] );
				$variation_id = intval( $cart_item['variation_id'] ?? 0 );

				$is_trigger = in_array( $product_id, $trigger_ids ) || in_array( $variation_id, $trigger_ids );
				$is_offer = $product_id === $offer_id || $variation_id === $offer_id;

				$should_apply = false;
				if ( $apply_to === 'offer_only' && $is_offer ) {
					$should_apply = true;
				} elseif ( $apply_to === 'trigger_only' && $is_trigger ) {
					$should_apply = true;
				} elseif ( $apply_to === 'both' && ( $is_trigger || $is_offer ) ) {
					$should_apply = true;
				}

				if ( ! $should_apply ) {
					continue;
				}

				$product = $cart_item['data'];
				if ( ! $product ) {
					continue;
				}

				$quantity   = intval( $cart_item['quantity'] );
				$line_total = $product->get_price() * $quantity;

				if ( $discount_type === 'percentage' ) {
					$discount_amount = $line_total * $discount_value / 100;
				} else {
					// Fixed amount applies per item, so scale it by quantity.
					$discount_amount = min( $discount_value * $quantity, $line_total );
				}

				if ( $discount_amount > 0 ) {
					$total_discount += $discount_amount;
					$applied = true;
				}
			}
		}

		if ( $applied && $total_discount > 0 ) {
			$cart->add_fee( __( 'Conditional Offer Discount', 'm4w-wco' ), - $total_discount, true );
		}
	}
}
