<?php
/**
 * Plugin Name: M4W Woo Conditional Offers
 * Description: Display conditional product offers based on cart contents with automatic discounts.
 * Version: 1.1.1
 * Author: m4g4
 * License: GPLv3 or later
 * Text Domain: m4w-wco
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'M4W_WCO_PATH', __DIR__ );
define( 'M4W_WCO_URL', content_url( 'mu-plugins/m4w-woo-conditional-offers' ) );
define( 'M4W_WCO_VERSION', '1.1.1' );
define( 'M4W_WCO_OPTION_KEY', 'm4w_wco_offers' );
define( 'M4W_WCO_NONCE_ACTION', 'm4w_wco_offers_save' );
define( 'M4W_WCO_NONCE_NAME', 'm4w_wco_offers_nonce' );
define( 'M4W_WCO_CHECK_NONCE_ACTION', 'm4w_wco_offer_check' );

require_once __DIR__ . '/includes/class-m4w-wco-rules.php';
require_once __DIR__ . '/includes/class-m4w-wco-settings.php';
require_once __DIR__ . '/includes/class-m4w-wco-frontend.php';
require_once __DIR__ . '/includes/class-m4w-wco-discounts.php';

function m4w_woo_conditional_offers_init() {
	load_muplugin_textdomain( 'm4w-wco', '/m4w-woo-conditional-offers/languages' );

	// Initialize the plugin components
	new M4W_WCO_Settings();
	new M4W_WCO_Frontend();
	new M4W_WCO_Discounts();
}

add_action( 'woocommerce_loaded', 'm4w_woo_conditional_offers_init', 0 );