<?php
/**
 * Admin settings screen, rule persistence and admin AJAX handlers
 * for the M4W Woo Conditional Offers plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class M4W_WCO_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_' . M4W_WCO_NONCE_ACTION, array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_' . 'm4w_wco_save_offer', array( $this, 'ajax_save_rule' ) );
		add_action( 'wp_ajax_' . 'm4w_wco_delete_offer', array( $this, 'ajax_delete_rule' ) );
		add_action( 'wp_ajax_' . 'm4w_wco_get_offer_rule', array( $this, 'ajax_get_rule' ) );
	}

	public function add_admin_menu() {
add_submenu_page(
			'woocommerce',
			__( 'Conditional Offers', 'm4w-wco' ),
			__( 'Conditional Offers', 'm4w-wco' ),
			'manage_woocommerce',
			'm4w-wco-conditional-offers',
			array( $this, 'render_admin_page' )
		);
	}

	public function handle_save() {
		check_admin_referer( M4W_WCO_NONCE_ACTION, M4W_WCO_NONCE_NAME );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'm4w-wco' ) );
		}

		$rules = M4W_WCO_Rules::get_rules();
		$rule_data = $this->get_rule_data_from_request();
		$error = M4W_WCO_Rules::validate_rule( $rule_data );
		if ( is_wp_error( $error ) ) {
			wp_die( esc_html( $error->get_error_message() ) );
		}

		if ( isset( $_POST['rule_id'] ) && $_POST['rule_id'] !== '' ) {
			$rule_id = intval( $_POST['rule_id'] );
			$rule_index = M4W_WCO_Rules::find_rule_index( $rules, $rule_id );
			if ( $rule_index !== null ) {
				$rules[ $rule_index ] = array_merge( $rules[ $rule_index ], $rule_data );
			} else {
				wp_die( esc_html__( 'Rule not found.', 'm4w-wco' ) );
			}
		} else {
			$rule = array_merge( array( 'id' => M4W_WCO_Rules::get_next_id( $rules ) ), $rule_data );
			$rules[] = $rule;
		}

		M4W_WCO_Rules::save_rules( $rules );

		wp_safe_redirect( admin_url( 'admin.php?page=m4w-wco-conditional-offers&saved=1' ) );
		exit;
	}

	public function handle_delete( $rule_id ) {
		check_admin_referer( M4W_WCO_NONCE_ACTION, M4W_WCO_NONCE_NAME );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'm4w-wco' ) );
		}

		$rules = M4W_WCO_Rules::get_rules();
		$rules = array_values( array_filter( $rules, function( $r ) use ( $rule_id ) {
			return intval( $r['id'] ) !== intval( $rule_id );
		} ) );

		M4W_WCO_Rules::save_rules( $rules );

		wp_safe_redirect( admin_url( 'admin.php?page=m4w-wco-conditional-offers&deleted=1' ) );
		exit;
	}

	public function ajax_save_rule() {
		check_ajax_referer( M4W_WCO_NONCE_ACTION, M4W_WCO_NONCE_NAME );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'm4w-wco' ) );
		}

		$rules = M4W_WCO_Rules::get_rules();
		$rule_data = $this->get_rule_data_from_request();
		$error = M4W_WCO_Rules::validate_rule( $rule_data );
		if ( is_wp_error( $error ) ) {
			wp_send_json_error( $error->get_error_message() );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? intval( $_POST['rule_id'] ) : 0;

		if ( $rule_id > 0 ) {
			$rule_index = M4W_WCO_Rules::find_rule_index( $rules, $rule_id );
			if ( $rule_index === null ) {
				wp_send_json_error( 'Rule not found' );
			}
			$rules[ $rule_index ] = array_merge( $rules[ $rule_index ], $rule_data );
		} else {
			$rule = array_merge( array( 'id' => M4W_WCO_Rules::get_next_id( $rules ) ), $rule_data );
			$rules[] = $rule;
		}

		M4W_WCO_Rules::save_rules( $rules );
		wp_send_json_success( array( 'message' => 'Rule saved' ) );
	}

	public function ajax_delete_rule() {
		check_ajax_referer( M4W_WCO_NONCE_ACTION, M4W_WCO_NONCE_NAME );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'm4w-wco' ) );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? intval( $_POST['rule_id'] ) : 0;
		if ( $rule_id <= 0 ) {
			wp_send_json_error( 'Invalid rule ID' );
		}

		$rules = M4W_WCO_Rules::get_rules();
		$rules = array_values( array_filter( $rules, function( $r ) use ( $rule_id ) {
			return intval( $r['id'] ) !== intval( $rule_id );
		} ) );

		M4W_WCO_Rules::save_rules( $rules );
		wp_send_json_success( array( 'message' => 'Rule deleted' ) );
	}

	public function ajax_get_rule() {
		check_ajax_referer( M4W_WCO_NONCE_ACTION, M4W_WCO_NONCE_NAME );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'm4w-wco' ) );
		}

		$rule_id = isset( $_POST['rule_id'] ) ? intval( $_POST['rule_id'] ) : 0;
		if ( $rule_id <= 0 ) {
			wp_send_json_error( 'Invalid rule ID' );
		}

		$rules = M4W_WCO_Rules::get_rules();
		$rule_index = M4W_WCO_Rules::find_rule_index( $rules, $rule_id );
		if ( $rule_index === null ) {
			wp_send_json_error( 'Rule not found' );
		}

		wp_send_json_success( $rules[ $rule_index ] );
	}

	private function get_rule_data_from_request() {
		$trigger_ids = M4W_WCO_Rules::sanitize_product_id_list( M4W_WCO_Rules::get_post_value( 'trigger_product_ids' ) );
		if ( empty( $trigger_ids ) ) {
			$trigger_ids = M4W_WCO_Rules::sanitize_product_id_list( M4W_WCO_Rules::get_post_value( 'trigger_product_id' ) );
		}

		return array(
			'trigger_product_ids'      => $trigger_ids,
			'offer_product_id'         => intval( M4W_WCO_Rules::get_post_value( 'offer_product_id' ) ),
			'label'                    => sanitize_text_field( M4W_WCO_Rules::get_post_value( 'label' ) ),
			'custom_content'           => M4W_WCO_Rules::sanitize_custom_content( M4W_WCO_Rules::get_post_value( 'custom_content' ) ),
			'show_popup'               => ! empty( $_POST['show_popup'] ),
			'show_toast'               => ! empty( $_POST['show_toast'] ),
			'popup_delay'              => M4W_WCO_Rules::sanitize_seconds( M4W_WCO_Rules::get_post_value( 'popup_delay', 1 ), 1, 0, 60 ),
			'toast_timeout'            => M4W_WCO_Rules::sanitize_seconds( M4W_WCO_Rules::get_post_value( 'toast_timeout', 8 ), 8, 3, 60, true ),
			'popup_once_per_session'   => ! empty( $_POST['popup_once_per_session'] ),
			'toast_css'                => M4W_WCO_Rules::sanitize_css( M4W_WCO_Rules::get_post_value( 'toast_css' ) ),
			'discount_enabled'         => ! empty( $_POST['discount_enabled'] ),
			'discount_type'            => M4W_WCO_Rules::sanitize_discount_type( M4W_WCO_Rules::get_post_value( 'discount_type', 'percentage' ) ),
			'discount_value'           => M4W_WCO_Rules::sanitize_discount_value( M4W_WCO_Rules::get_post_value( 'discount_value', 0 ) ),
			'discount_apply_to'        => M4W_WCO_Rules::sanitize_discount_apply_to( M4W_WCO_Rules::get_post_value( 'discount_apply_to', 'offer_only' ) ),
		);
	}

	public function render_admin_page() {
		$rules = M4W_WCO_Rules::get_rules();

		if ( isset( $_GET['delete'] ) ) {
			$this->handle_delete( intval( $_GET['delete'] ) );
		}

		$saved = isset( $_GET['saved'] );
		$deleted = isset( $_GET['deleted'] );

		wp_enqueue_script(
			'm4w-wco-admin',
			M4W_WCO_URL . '/js/m4w-wco-admin.js',
			array( 'jquery' ),
			M4W_WCO_VERSION,
			true
		);
		wp_enqueue_style(
			'm4w-wco-admin',
			M4W_WCO_URL . '/css/m4w-wco.css',
			array(),
			M4W_WCO_VERSION
		);
		wp_localize_script( 'm4w-wco-admin', 'm4wWcoAdmin', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( M4W_WCO_NONCE_ACTION ),
			'rules'     => $rules,
			'action'    => M4W_WCO_NONCE_ACTION,
			'nonceName' => M4W_WCO_NONCE_NAME,
			'i18n'      => array(
				'addNewRule'    => __( 'Add New Rule', 'm4w-wco' ),
				'editRule'      => __( 'Edit Rule', 'm4w-wco' ),
				'deleteConfirm' => __( 'Delete this rule?', 'm4w-wco' ),
				'saving'        => __( 'Saving...', 'm4w-wco' ),
				'errorLoading'  => __( 'Error loading rule:', 'm4w-wco' ),
				'ajaxErrorLoading' => __( 'AJAX error loading rule.', 'm4w-wco' ),
				'error'         => __( 'Error:', 'm4w-wco' ),
				'unknownError'  => __( 'Unknown error', 'm4w-wco' ),
				'ajaxError'     => __( 'AJAX error occurred.', 'm4w-wco' ),
			),
		) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Conditional Offers', 'm4w-wco' ); ?></h1>
			<p><?php echo esc_html__( 'Define rules: when specific product(s) are in the cart, show an offer for another product.', 'm4w-wco' ); ?></p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Rule saved.', 'm4w-wco' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $deleted ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Rule deleted.', 'm4w-wco' ); ?></p></div>
			<?php endif; ?>

			<div class="m4w-wco-header">
				<button type="button" class="button button-primary" id="m4w-wco-add-new"><?php echo esc_html__( 'Add New Rule', 'm4w-wco' ); ?></button>
			</div>

			<h2><?php echo esc_html__( 'Existing Rules', 'm4w-wco' ); ?></h2>
			<?php if ( empty( $rules ) ) : ?>
				<p><?php echo esc_html__( 'No rules defined yet. Click "Add New Rule" to create one.', 'm4w-wco' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped" id="m4w-wco-rules-table">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'ID', 'm4w-wco' ); ?></th>
							<th><?php echo esc_html__( 'Label', 'm4w-wco' ); ?></th>
							<th><?php echo esc_html__( 'Trigger Products (OR)', 'm4w-wco' ); ?></th>
							<th><?php echo esc_html__( 'Offer Product', 'm4w-wco' ); ?></th>
							<th><?php echo esc_html__( 'Custom', 'm4w-wco' ); ?></th>
							<th><?php echo esc_html__( 'Display Mode', 'm4w-wco' ); ?></th>
							<th><?php echo esc_html__( 'Popup/Toast Delay', 'm4w-wco' ); ?></th>
							<th><?php echo esc_html__( 'Toast Timeout', 'm4w-wco' ); ?></th>
							<th><?php echo esc_html__( 'Discount', 'm4w-wco' ); ?></th>
							<th><?php echo esc_html__( 'Shortcode', 'm4w-wco' ); ?></th>
							<th><?php echo esc_html__( 'Actions', 'm4w-wco' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rules as $rule ) : ?>
							<tr data-rule-id="<?php echo esc_attr( $rule['id'] ); ?>">
								<td><?php echo esc_html( $rule['id'] ); ?></td>
								<td><?php echo esc_html( $rule['label'] ); ?></td>
								<td>
									<?php
									$names = array();
									foreach ( $rule['trigger_product_ids'] as $tid ) {
										$tp = wc_get_product( $tid );
										$names[] = $tp ? esc_html( $tp->get_name() ) . ' (#' . esc_html( $tid ) . ')' : '#' . esc_html( $tid );
									}
									echo implode( '<br>', $names );
									?>
								</td>
								<td>
									<?php
									$op = wc_get_product( $rule['offer_product_id'] ?? 0 );
									echo esc_html( $rule['offer_product_id'] );
									if ( $op ) echo ' &mdash; ' . esc_html( $op->get_name() );
									?>
								</td>
								<td><?php echo $rule['custom_content'] ? '<span class="dashicons dashicons-yes" style="color:green"></span>' : '<span class="dashicons dashicons-no" style="color:red"></span>'; ?></td>
								<td>
								<?php
								$display_labels = array();
								if ( ! empty( $rule['show_popup'] ) ) {
									$display_labels[] = __( 'Popup', 'm4w-wco' );
								}
								if ( ! empty( $rule['show_toast'] ) ) {
									$display_labels[] = __( 'Toast', 'm4w-wco' );
								}
								echo esc_html( implode( ', ', $display_labels ) );
								?>
							</td>
								<td><?php echo esc_html( $rule['popup_delay'] ?? 1 ) . 's'; ?></td>
								<td><?php echo esc_html( $rule['toast_timeout'] ?? 8 ) . 's'; ?></td>
								<td>
									<?php if ( ! empty( $rule['discount_enabled'] ) ) : ?>
										<span class="dashicons dashicons-yes" style="color:green"></span>
										<?php
										$type = $rule['discount_type'] ?? 'percentage';
										$value = $rule['discount_value'] ?? 0;
										$apply = $rule['discount_apply_to'] ?? 'offer_only';
										$apply_labels = array(
											'offer_only'   => __( 'Offer product only', 'm4w-wco' ),
											'trigger_only' => __( 'Trigger product only', 'm4w-wco' ),
											'both'         => __( 'Both trigger and offer products', 'm4w-wco' ),
										);
										$apply_label = isset( $apply_labels[ $apply ] ) ? $apply_labels[ $apply ] : $apply;
										echo $type === 'percentage' ? esc_html( $value . '%' ) : wp_kses_post( wc_price( $value ) );
										echo ' (' . esc_html( $apply_label ) . ')';
										?>
									<?php else : ?>
										<span class="dashicons dashicons-no" style="color:red"></span>
									<?php endif; ?>
								</td>
								<td><code>[m4w_wco_offer rule_id="<?php echo esc_attr( $rule['id'] ); ?>"]</code></td>
								<td>
									<button type="button" class="button button-small m4w-wco-edit" data-rule-id="<?php echo esc_attr( $rule['id'] ); ?>"><?php echo esc_html__( 'Edit', 'm4w-wco' ); ?></button>
									<button type="button" class="button button-small button-link-delete m4w-wco-delete" data-rule-id="<?php echo esc_attr( $rule['id'] ); ?>"><?php echo esc_html__( 'Delete', 'm4w-wco' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- Modal Dialog -->
		<div id="m4w-wco-modal" class="m4w-wco-modal" style="display:none;">
			<div class="m4w-wco-modal-overlay"></div>
			<div class="m4w-wco-modal-content">
				<button type="button" class="m4w-wco-modal-close" aria-label="<?php echo esc_attr__( 'Close', 'm4w-wco' ); ?>">&times;</button>
				<h2 id="m4w-wco-modal-title"><?php echo esc_html__( 'Add New Rule', 'm4w-wco' ); ?></h2>
				<form id="m4w-wco-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( M4W_WCO_NONCE_ACTION ); ?>">
					<input type="hidden" name="<?php echo esc_attr( M4W_WCO_NONCE_NAME ); ?>" value="<?php echo esc_attr( wp_create_nonce( M4W_WCO_NONCE_ACTION ) ); ?>">
					<input type="hidden" name="rule_id" id="m4w-wco-rule-id" value="">

					<table class="form-table">
						<tr>
							<th scope="row"><label for="m4w-wco-label"><?php echo esc_html__( 'Label (admin reference)', 'm4w-wco' ); ?></label></th>
							<td>
								<input type="text" name="label" id="m4w-wco-label" class="regular-text" placeholder="<?php echo esc_attr__( 'e.g. Offer Course B when Course A or C in cart', 'm4w-wco' ); ?>">
								<p class="description"><?php echo esc_html__( 'Internal name for this rule.', 'm4w-wco' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="m4w-wco-trigger_ids"><?php echo esc_html__( 'Trigger Product IDs (comma-separated, OR condition)', 'm4w-wco' ); ?></label></th>
							<td>
								<input type="text" name="trigger_product_ids" id="m4w-wco-trigger_ids" class="regular-text" placeholder="123, 456, 789">
									<p class="description"><?php printf( __( 'One or more product IDs. Offer shows if ANY of these are in cart. <a href="%s" target="_blank">Find product IDs</a>.', 'm4w-wco' ), esc_url( admin_url( 'edit.php?post_type=product' ) ) ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="m4w-wco-offer_id"><?php echo esc_html__( 'Offer Product ID', 'm4w-wco' ); ?></label></th>
							<td>
								<input type="number" name="offer_product_id" id="m4w-wco-offer_id" class="small-text" required min="1">
								<p class="description"><?php echo esc_html__( 'Product to offer when any trigger is in cart.', 'm4w-wco' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="m4w-wco-content"><?php echo esc_html__( 'Custom Content (optional)', 'm4w-wco' ); ?></label></th>
							<td>
								<textarea name="custom_content" id="m4w-wco-content" class="large-text code" rows="5"></textarea>
								<p class="description"><?php echo esc_html__( 'Optional: Custom HTML/shortcode for the offer. If empty, a default "Add to Cart" button for the offer product will be shown. Available shortcodes: [ajax_add_to_cart], [button], etc.', 'm4w-wco' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Display Where', 'm4w-wco' ); ?></th>
							<td>
								<label><input type="checkbox" name="show_popup" id="m4w-wco-show_popup" value="1"> <?php echo esc_html__( 'Popup (modal)', 'm4w-wco' ); ?></label><br>
								<label><input type="checkbox" name="show_toast" id="m4w-wco-show_toast" value="1"> <?php echo esc_html__( 'Toast notification (auto-dismiss)', 'm4w-wco' ); ?></label>
								<p class="description"><?php echo __( 'Choose how the offer is presented automatically when the trigger product is added to the cart. The <code>[m4w_wco_offer]</code> shortcode can be placed anywhere and works independently of these options.', 'm4w-wco' ); ?></p>
							</td>
						</tr>
						<tr class="m4w-wco-cond m4w-wco-cond-toast">
							<th scope="row"><label for="m4w-wco-toast_timeout"><?php echo esc_html__( 'Toast Timeout (seconds)', 'm4w-wco' ); ?></label></th>
							<td>
								<input type="number" name="toast_timeout" id="m4w-wco-toast_timeout" class="small-text" value="8" min="3" max="60" step="1">
							<p class="description"><?php echo esc_html__( 'How long the toast stays visible before auto-dismissing. Only applies to toast mode.', 'm4w-wco' ); ?></p>
						</td>
					</tr>
						<tr class="m4w-wco-cond m4w-wco-cond-toast">
							<th scope="row"><label for="m4w-wco-toast_css"><?php echo esc_html__( 'Toast Custom CSS', 'm4w-wco' ); ?></label></th>
						<td>
							<textarea name="toast_css" id="m4w-wco-toast_css" class="large-text code" rows="6" placeholder=".conditional-offer-title { color: #fff; }"></textarea>
							<p class="description"><?php echo __( 'Optional CSS applied only to this rule\'s toast. Write selectors for the inner content (e.g. <code>.conditional-offer-title</code>, <code>img</code>, <code>button</code>). To style the toast container itself use <code>.m4w-wco-toast</code>.', 'm4w-wco' ); ?></p>
						</td>
					</tr>
						<tr class="m4w-wco-cond m4w-wco-cond-popup m4w-wco-cond-toast">
							<th scope="row"><label for="m4w-wco-popup_delay"><?php echo esc_html__( 'Popup/Toast Delay (seconds)', 'm4w-wco' ); ?></label></th>
							<td>
								<input type="number" name="popup_delay" id="m4w-wco-popup_delay" class="small-text" value="1" min="0" max="60" step="0.5">
								<p class="description"><?php echo esc_html__( 'Delay before showing toast/popup after trigger product is added to cart. Set to 0 for immediate.', 'm4w-wco' ); ?></p>
							</td>
						</tr>
						<tr class="m4w-wco-cond m4w-wco-cond-popup">
							<th scope="row"><label for="m4w-wco-popup_once"><?php echo esc_html__( 'Popup Once Per Session', 'm4w-wco' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" name="popup_once_per_session" id="m4w-wco-popup_once" value="1">
									<?php echo esc_html__( 'Only show popup once per browser session (uses session cookie)', 'm4w-wco' ); ?>
								</label>
							</td>
						</tr>
						<tr class="m4w-wco-section-header">
							<th scope="row"></th>
							<td><h3 style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;"><?php echo esc_html__( 'Automatic Discount (when both products in cart)', 'm4w-wco' ); ?></h3></td>
						</tr>
						<tr>
							<th scope="row"><label for="m4w-wco-discount_enabled"><?php echo esc_html__( 'Enable Automatic Discount', 'm4w-wco' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" name="discount_enabled" id="m4w-wco-discount_enabled" value="1">
									<?php echo esc_html__( 'Apply discount automatically when both trigger and offer products are in cart', 'm4w-wco' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="m4w-wco-discount_type"><?php echo esc_html__( 'Discount Type', 'm4w-wco' ); ?></label></th>
							<td>
								<select name="discount_type" id="m4w-wco-discount_type">
									<option value="percentage"><?php echo esc_html__( 'Percentage (%)', 'm4w-wco' ); ?></option>
									<option value="fixed"><?php echo esc_html__( 'Fixed amount', 'm4w-wco' ); ?></option>
								</select>
								<p class="description"><?php echo esc_html__( 'Type of discount to apply.', 'm4w-wco' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="m4w-wco-discount_value"><?php echo esc_html__( 'Discount Value', 'm4w-wco' ); ?></label></th>
							<td>
								<input type="number" name="discount_value" id="m4w-wco-discount_value" class="small-text" value="0" min="0" step="0.01">
								<p class="description"><?php echo esc_html__( 'For percentage: enter 10 for 10%. For fixed: enter amount in shop currency (e.g., 5.00).', 'm4w-wco' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="m4w-wco-discount_apply_to"><?php echo esc_html__( 'Apply Discount To', 'm4w-wco' ); ?></label></th>
							<td>
								<select name="discount_apply_to" id="m4w-wco-discount_apply_to">
									<option value="offer_only"><?php echo esc_html__( 'Offer product only', 'm4w-wco' ); ?></option>
									<option value="both"><?php echo esc_html__( 'Both trigger and offer products', 'm4w-wco' ); ?></option>
									<option value="trigger_only"><?php echo esc_html__( 'Trigger product only', 'm4w-wco' ); ?></option>
								</select>
								<p class="description"><?php echo esc_html__( 'Which product(s) receive the discount.', 'm4w-wco' ); ?></p>
							</td>
						</tr>
					</table>
					<div class="m4w-wco-modal-actions">
						<button type="button" class="button button-secondary m4w-wco-modal-cancel"><?php echo esc_html__( 'Cancel', 'm4w-wco' ); ?></button>
						<button type="submit" class="button button-primary"><?php echo esc_html__( 'Save Rule', 'm4w-wco' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}
}
