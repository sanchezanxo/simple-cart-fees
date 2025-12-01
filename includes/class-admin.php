<?php
/**
 * Admin functionality.
 *
 * @package Simple_Cart_Fees
 */

defined( 'ABSPATH' ) || exit;

/**
 * SCF_Admin class.
 *
 * Handles the admin settings page and CRUD operations for fees.
 */
class SCF_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_scf_save_fees', array( $this, 'ajax_save_fees' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_order_column' ), 10, 2 );
		// HPOS compatibility.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_order_column_hpos' ), 10, 2 );
	}

	/**
	 * Add submenu page under WooCommerce.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Simple Cart Fees', 'simple-cart-fees' ),
			__( 'Simple Cart Fees', 'simple-cart-fees' ),
			'manage_woocommerce',
			'simple-cart-fees',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_simple-cart-fees' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'scf-admin',
			SCF_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			SCF_VERSION
		);

		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script(
			'scf-admin',
			SCF_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			SCF_VERSION,
			true
		);

		wp_localize_script(
			'scf-admin',
			'scfAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'scf_admin_nonce' ),
				'taxClasses'     => Simple_Cart_Fees::get_tax_classes(),
				'currencySymbol' => get_woocommerce_currency_symbol(),
				'currencyPos'    => get_option( 'woocommerce_currency_pos', 'left' ),
				'decimals'       => wc_get_price_decimals(),
				'decimalSep'     => wc_get_price_decimal_separator(),
				'thousandSep'    => wc_get_price_thousand_separator(),
				'strings'        => array(
					'confirmDelete'        => __( 'Are you sure you want to delete this fee?', 'simple-cart-fees' ),
					'saved'                => __( 'Fees saved successfully.', 'simple-cart-fees' ),
					'error'                => __( 'An error occurred. Please try again.', 'simple-cart-fees' ),
					'requiredInternalName' => __( 'Internal name is required.', 'simple-cart-fees' ),
					'requiredPublicName'   => __( 'Public name is required.', 'simple-cart-fees' ),
					'requiredPrice'        => __( 'Price must be a valid positive number.', 'simple-cart-fees' ),
				),
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		$fees = Simple_Cart_Fees::get_fees();
		$tax_classes = Simple_Cart_Fees::get_tax_classes();
		?>
		<div class="wrap scf-admin-wrap">
			<h1><?php esc_html_e( 'Simple Cart Fees', 'simple-cart-fees' ); ?></h1>

			<div class="scf-admin-container">
				<div id="scf-notices"></div>

				<form id="scf-fees-form" method="post">
					<table class="wp-list-table widefat fixed striped" id="scf-fees-table">
						<thead>
							<tr>
								<th class="scf-col-drag"></th>
								<th class="scf-col-name"><?php esc_html_e( 'Name', 'simple-cart-fees' ); ?></th>
								<th class="scf-col-price"><?php esc_html_e( 'Price', 'simple-cart-fees' ); ?></th>
								<th class="scf-col-type"><?php esc_html_e( 'Type', 'simple-cart-fees' ); ?></th>
								<th class="scf-col-condition"><?php esc_html_e( 'Condition', 'simple-cart-fees' ); ?></th>
								<th class="scf-col-active"><?php esc_html_e( 'Active', 'simple-cart-fees' ); ?></th>
								<th class="scf-col-actions"><?php esc_html_e( 'Actions', 'simple-cart-fees' ); ?></th>
							</tr>
						</thead>
						<tbody id="scf-fees-list">
							<?php if ( empty( $fees ) ) : ?>
								<tr class="scf-no-fees">
									<td colspan="7"><?php esc_html_e( 'No fees configured. Click "Add Fee" to create one.', 'simple-cart-fees' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $fees as $index => $fee ) : ?>
									<?php $this->render_fee_row( $fee, $index ); ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>

					<p class="scf-actions">
						<button type="button" class="button button-secondary" id="scf-add-fee">
							<?php esc_html_e( 'Add Fee', 'simple-cart-fees' ); ?>
						</button>
						<button type="submit" class="button button-primary" id="scf-save-fees">
							<?php esc_html_e( 'Save Changes', 'simple-cart-fees' ); ?>
						</button>
						<span class="spinner"></span>
					</p>
				</form>
			</div>

			<!-- Fee edit modal -->
			<div id="scf-fee-modal" class="scf-modal" style="display:none;">
				<div class="scf-modal-content">
					<span class="scf-modal-close">&times;</span>
					<h2 id="scf-modal-title"><?php esc_html_e( 'Edit Fee', 'simple-cart-fees' ); ?></h2>

					<div class="scf-modal-body">
						<input type="hidden" id="scf-edit-index" value="">
						<input type="hidden" id="scf-edit-id" value="">

						<div class="scf-field">
							<label for="scf-internal-name"><?php esc_html_e( 'Internal Name', 'simple-cart-fees' ); ?></label>
							<input type="text" id="scf-internal-name" class="regular-text">
							<p class="description"><?php esc_html_e( 'For admin identification only.', 'simple-cart-fees' ); ?></p>
						</div>

						<div class="scf-field">
							<label for="scf-public-name"><?php esc_html_e( 'Public Name', 'simple-cart-fees' ); ?></label>
							<input type="text" id="scf-public-name" class="regular-text">
							<p class="description"><?php esc_html_e( 'Displayed to customers at checkout and in emails.', 'simple-cart-fees' ); ?></p>
						</div>

						<div class="scf-field-row">
							<div class="scf-field scf-field-half">
								<label for="scf-price"><?php esc_html_e( 'Price (incl. tax)', 'simple-cart-fees' ); ?></label>
								<input type="number" id="scf-price" step="0.01" min="0" class="small-text">
								<p class="description"><?php esc_html_e( 'Enter the price including tax.', 'simple-cart-fees' ); ?></p>
							</div>

							<div class="scf-field scf-field-half">
								<label for="scf-tax-class"><?php esc_html_e( 'Tax Class', 'simple-cart-fees' ); ?></label>
								<select id="scf-tax-class">
									<?php foreach ( $tax_classes as $slug => $name ) : ?>
										<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="scf-field">
							<label for="scf-type"><?php esc_html_e( 'Type', 'simple-cart-fees' ); ?></label>
							<select id="scf-type">
								<option value="required"><?php esc_html_e( 'Required', 'simple-cart-fees' ); ?></option>
								<option value="optional"><?php esc_html_e( 'Optional', 'simple-cart-fees' ); ?></option>
							</select>
						</div>

						<div class="scf-field scf-optional-fields" style="display:none;">
							<label for="scf-checkbox-text"><?php esc_html_e( 'Checkbox Text', 'simple-cart-fees' ); ?></label>
							<input type="text" id="scf-checkbox-text" class="regular-text">
							<p class="description"><?php esc_html_e( 'Text shown next to the checkbox.', 'simple-cart-fees' ); ?></p>
						</div>

						<div class="scf-field scf-optional-fields" style="display:none;">
							<label for="scf-help-text"><?php esc_html_e( 'Help Text', 'simple-cart-fees' ); ?></label>
							<input type="text" id="scf-help-text" class="regular-text">
							<p class="description"><?php esc_html_e( 'Small description below the checkbox.', 'simple-cart-fees' ); ?></p>
						</div>

						<div class="scf-field">
							<label for="scf-condition"><?php esc_html_e( 'Condition', 'simple-cart-fees' ); ?></label>
							<select id="scf-condition">
								<option value="always"><?php esc_html_e( 'Always', 'simple-cart-fees' ); ?></option>
								<option value="minimum"><?php esc_html_e( 'Cart minimum', 'simple-cart-fees' ); ?></option>
							</select>
						</div>

						<div class="scf-field scf-minimum-field" style="display:none;">
							<label for="scf-condition-minimum"><?php esc_html_e( 'Minimum Cart Subtotal', 'simple-cart-fees' ); ?></label>
							<input type="number" id="scf-condition-minimum" step="0.01" min="0" class="small-text">
						</div>

						<div class="scf-field">
							<label>
								<input type="checkbox" id="scf-active" checked>
								<?php esc_html_e( 'Active', 'simple-cart-fees' ); ?>
							</label>
						</div>
					</div>

					<div class="scf-modal-footer">
						<button type="button" class="button button-secondary scf-modal-cancel"><?php esc_html_e( 'Cancel', 'simple-cart-fees' ); ?></button>
						<button type="button" class="button button-primary" id="scf-modal-save"><?php esc_html_e( 'Apply', 'simple-cart-fees' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<!-- Row template for new fees -->
		<script type="text/template" id="scf-fee-row-template">
			<?php $this->render_fee_row( array(), '{{INDEX}}' ); ?>
		</script>
		<?php
	}

	/**
	 * Render a single fee row.
	 *
	 * @param array      $fee   The fee data.
	 * @param int|string $index The row index.
	 */
	private function render_fee_row( $fee, $index ) {
		$defaults = array(
			'id'                => '',
			'internal_name'     => '',
			'public_name'       => '',
			'price'             => 0,
			'tax_class'         => '',
			'type'              => 'required',
			'checkbox_text'     => '',
			'help_text'         => '',
			'condition'         => 'always',
			'condition_minimum' => 0,
			'order'             => 0,
			'active'            => true,
		);
		$fee = wp_parse_args( $fee, $defaults );

		$prefix = "scf_fees[{$index}]";
		?>
		<tr class="scf-fee-row" data-index="<?php echo esc_attr( $index ); ?>">
			<td class="scf-col-drag">
				<span class="scf-drag-handle dashicons dashicons-menu"></span>
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[id]" value="<?php echo esc_attr( $fee['id'] ); ?>" class="scf-field-id">
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[internal_name]" value="<?php echo esc_attr( $fee['internal_name'] ); ?>" class="scf-field-internal_name">
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[public_name]" value="<?php echo esc_attr( $fee['public_name'] ); ?>" class="scf-field-public_name">
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[price]" value="<?php echo esc_attr( $fee['price'] ); ?>" class="scf-field-price">
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[tax_class]" value="<?php echo esc_attr( $fee['tax_class'] ); ?>" class="scf-field-tax_class">
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[type]" value="<?php echo esc_attr( $fee['type'] ); ?>" class="scf-field-type">
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[checkbox_text]" value="<?php echo esc_attr( $fee['checkbox_text'] ); ?>" class="scf-field-checkbox_text">
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[help_text]" value="<?php echo esc_attr( $fee['help_text'] ); ?>" class="scf-field-help_text">
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[condition]" value="<?php echo esc_attr( $fee['condition'] ); ?>" class="scf-field-condition">
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[condition_minimum]" value="<?php echo esc_attr( $fee['condition_minimum'] ); ?>" class="scf-field-condition_minimum">
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[order]" value="<?php echo esc_attr( $fee['order'] ); ?>" class="scf-field-order">
				<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[active]" value="<?php echo $fee['active'] ? '1' : '0'; ?>" class="scf-field-active">
			</td>
			<td class="scf-col-name">
				<span class="scf-display-name"><?php echo esc_html( $fee['internal_name'] ); ?></span>
			</td>
			<td class="scf-col-price">
				<span class="scf-display-price"><?php echo esc_html( wc_price( $fee['price'] ) ); ?></span>
			</td>
			<td class="scf-col-type">
				<span class="scf-display-type"><?php echo 'optional' === $fee['type'] ? esc_html__( 'Optional', 'simple-cart-fees' ) : esc_html__( 'Required', 'simple-cart-fees' ); ?></span>
			</td>
			<td class="scf-col-condition">
				<span class="scf-display-condition">
					<?php
					if ( 'minimum' === $fee['condition'] ) {
						printf(
							/* translators: %s: minimum cart amount */
							esc_html__( 'Min. %s', 'simple-cart-fees' ),
							wp_kses_post( wc_price( $fee['condition_minimum'] ) )
						);
					} else {
						esc_html_e( 'Always', 'simple-cart-fees' );
					}
					?>
				</span>
			</td>
			<td class="scf-col-active">
				<span class="scf-display-active <?php echo $fee['active'] ? 'scf-active-yes' : 'scf-active-no'; ?>">
					<?php echo $fee['active'] ? esc_html__( 'Yes', 'simple-cart-fees' ) : esc_html__( 'No', 'simple-cart-fees' ); ?>
				</span>
			</td>
			<td class="scf-col-actions">
				<button type="button" class="button button-small scf-edit-fee"><?php esc_html_e( 'Edit', 'simple-cart-fees' ); ?></button>
				<button type="button" class="button button-small scf-delete-fee"><?php esc_html_e( 'Delete', 'simple-cart-fees' ); ?></button>
			</td>
		</tr>
		<?php
	}

	/**
	 * AJAX handler for saving fees.
	 */
	public function ajax_save_fees() {
		check_ajax_referer( 'scf_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'simple-cart-fees' ) ) );
		}

		$fees_data = isset( $_POST['fees'] ) ? wp_unslash( $_POST['fees'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$fees = array();

		if ( is_array( $fees_data ) ) {
			$order = 0;
			foreach ( $fees_data as $fee_data ) {
				$fee = array(
					'id'                => ! empty( $fee_data['id'] ) ? sanitize_text_field( $fee_data['id'] ) : Simple_Cart_Fees::generate_fee_id(),
					'internal_name'     => sanitize_text_field( $fee_data['internal_name'] ?? '' ),
					'public_name'       => sanitize_text_field( $fee_data['public_name'] ?? '' ),
					'price'             => floatval( $fee_data['price'] ?? 0 ),
					'tax_class'         => sanitize_text_field( $fee_data['tax_class'] ?? '' ),
					'type'              => in_array( $fee_data['type'] ?? '', array( 'required', 'optional' ), true ) ? $fee_data['type'] : 'required',
					'checkbox_text'     => sanitize_text_field( $fee_data['checkbox_text'] ?? '' ),
					'help_text'         => sanitize_text_field( $fee_data['help_text'] ?? '' ),
					'condition'         => in_array( $fee_data['condition'] ?? '', array( 'always', 'minimum' ), true ) ? $fee_data['condition'] : 'always',
					'condition_minimum' => floatval( $fee_data['condition_minimum'] ?? 0 ),
					'order'             => $order,
					'active'            => ! empty( $fee_data['active'] ) && '0' !== $fee_data['active'],
				);
				$fees[] = $fee;
				++$order;
			}
		}

		Simple_Cart_Fees::save_fees( $fees );

		wp_send_json_success( array( 'message' => __( 'Fees saved successfully.', 'simple-cart-fees' ) ) );
	}

	/**
	 * Add meta box to order edit page.
	 */
	public function add_order_meta_box() {
		$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' )
			&& wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? wc_get_page_screen_id( 'shop-order' )
			: 'shop_order';

		add_meta_box(
			'scf-order-fees',
			__( 'Cart Fees', 'simple-cart-fees' ),
			array( $this, 'render_order_meta_box' ),
			$screen,
			'side',
			'default'
		);
	}

	/**
	 * Render order meta box content.
	 *
	 * @param WP_Post|WC_Order $post_or_order The post or order object.
	 */
	public function render_order_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}

		$applied_fees = $this->get_order_applied_fees( $order );

		if ( empty( $applied_fees ) ) {
			echo '<p>' . esc_html__( 'No fees applied to this order.', 'simple-cart-fees' ) . '</p>';
			return;
		}

		echo '<ul class="scf-order-fees-list">';
		foreach ( $applied_fees as $fee_id => $fee_name ) {
			echo '<li>' . esc_html( $fee_name ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Add fees column to orders list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_order_column( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'order_total' === $key ) {
				$new_columns['scf_fees'] = __( 'Fees', 'simple-cart-fees' );
			}
		}
		return $new_columns;
	}

	/**
	 * Render fees column for traditional orders.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_order_column( $column, $post_id ) {
		if ( 'scf_fees' !== $column ) {
			return;
		}

		$order = wc_get_order( $post_id );
		$this->output_fees_column_content( $order );
	}

	/**
	 * Render fees column for HPOS orders.
	 *
	 * @param string   $column Column name.
	 * @param WC_Order $order  The order object.
	 */
	public function render_order_column_hpos( $column, $order ) {
		if ( 'scf_fees' !== $column ) {
			return;
		}

		$this->output_fees_column_content( $order );
	}

	/**
	 * Output the fees column content.
	 *
	 * @param WC_Order|false $order The order object.
	 */
	private function output_fees_column_content( $order ) {
		if ( ! $order ) {
			echo '—';
			return;
		}

		$applied_fees = $this->get_order_applied_fees( $order );

		if ( empty( $applied_fees ) ) {
			echo '—';
			return;
		}

		echo esc_html( implode( ', ', $applied_fees ) );
	}

	/**
	 * Get applied fees for an order.
	 *
	 * @param WC_Order $order The order object.
	 * @return array Fee names indexed by ID.
	 */
	private function get_order_applied_fees( $order ) {
		$applied_fees = array();
		$all_fees = Simple_Cart_Fees::get_fees();

		foreach ( $all_fees as $fee ) {
			$meta_key = '_simple_cart_fee_' . $fee['id'];
			$meta_value = $order->get_meta( $meta_key );
			if ( 'yes' === $meta_value ) {
				$applied_fees[ $fee['id'] ] = $fee['internal_name'];
			}
		}

		return $applied_fees;
	}
}
