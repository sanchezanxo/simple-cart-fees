<?php
/**
 * Frontend functionality for classic checkout.
 *
 * @package Simple_Cart_Fees
 */

defined( 'ABSPATH' ) || exit;

/**
 * SCF_Frontend class.
 *
 * Handles fee display and application on the classic checkout.
 */
class SCF_Frontend {

	/**
	 * Session key for storing selected optional fees.
	 */
	const SESSION_KEY = 'scf_selected_fees';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'apply_fees' ) );
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'display_optional_fees' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_scf_toggle_fee', array( $this, 'ajax_toggle_fee' ) );
		add_action( 'wp_ajax_nopriv_scf_toggle_fee', array( $this, 'ajax_toggle_fee' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_fees_to_order' ), 10, 2 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'add_fees_to_email' ), 10, 4 );
		// Initialize session for storing selections.
		add_action( 'woocommerce_init', array( $this, 'init_session' ) );
	}

	/**
	 * Initialize WooCommerce session if needed.
	 */
	public function init_session() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}

	/**
	 * Enqueue frontend scripts.
	 */
	public function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'scf-frontend',
			SCF_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			SCF_VERSION
		);

		wp_enqueue_script(
			'scf-frontend',
			SCF_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			SCF_VERSION,
			true
		);

		wp_localize_script(
			'scf-frontend',
			'scfFrontend',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'scf_frontend_nonce' ),
			)
		);
	}

	/**
	 * Apply fees to cart.
	 *
	 * @param WC_Cart $cart The cart object.
	 */
	public function apply_fees( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$fees = Simple_Cart_Fees::get_active_fees();
		$selected_fees = $this->get_selected_fees();

		foreach ( $fees as $fee ) {
			// Check if condition is met.
			if ( ! Simple_Cart_Fees::is_condition_met( $fee ) ) {
				continue;
			}

			// For optional fees, check if selected.
			if ( 'optional' === $fee['type'] && ! in_array( $fee['id'], $selected_fees, true ) ) {
				continue;
			}

			// Calculate net price from gross.
			$net_price = Simple_Cart_Fees::get_net_from_gross( $fee['price'], $fee['tax_class'] );

			$cart->add_fee(
				$fee['public_name'],
				$net_price,
				true, // Taxable.
				$fee['tax_class']
			);
		}
	}

	/**
	 * Display optional fees checkboxes on checkout.
	 */
	public function display_optional_fees() {
		$fees = Simple_Cart_Fees::get_active_fees();
		$selected_fees = $this->get_selected_fees();
		$has_optional = false;

		foreach ( $fees as $fee ) {
			if ( 'optional' !== $fee['type'] ) {
				continue;
			}

			if ( ! Simple_Cart_Fees::is_condition_met( $fee ) ) {
				continue;
			}

			if ( ! $has_optional ) {
				$has_optional = true;
				echo '<div class="scf-optional-fees">';
				echo '<h3>' . esc_html__( 'Additional Options', 'simple-cart-fees' ) . '</h3>';
			}

			$is_selected = in_array( $fee['id'], $selected_fees, true );
			$checkbox_text = ! empty( $fee['checkbox_text'] ) ? $fee['checkbox_text'] : $fee['public_name'];
			?>
			<div class="scf-optional-fee">
				<label>
					<input
						type="checkbox"
						class="scf-fee-checkbox"
						name="scf_fee_<?php echo esc_attr( $fee['id'] ); ?>"
						value="<?php echo esc_attr( $fee['id'] ); ?>"
						data-fee-id="<?php echo esc_attr( $fee['id'] ); ?>"
						<?php checked( $is_selected ); ?>
					>
					<?php echo esc_html( $checkbox_text ); ?>
					<span class="scf-fee-price">(<?php echo wp_kses_post( wc_price( $fee['price'] ) ); ?>)</span>
				</label>
				<?php if ( ! empty( $fee['help_text'] ) ) : ?>
					<p class="scf-fee-help"><?php echo esc_html( $fee['help_text'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php
		}

		if ( $has_optional ) {
			echo '</div>';
		}
	}

	/**
	 * AJAX handler for toggling optional fees.
	 */
	public function ajax_toggle_fee() {
		check_ajax_referer( 'scf_frontend_nonce', 'nonce' );

		$fee_id = isset( $_POST['fee_id'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_id'] ) ) : '';
		$checked = isset( $_POST['checked'] ) && 'true' === $_POST['checked'];

		if ( empty( $fee_id ) ) {
			wp_send_json_error();
		}

		// Verify this is a valid optional fee.
		$fee = Simple_Cart_Fees::get_fee( $fee_id );
		if ( ! $fee || 'optional' !== $fee['type'] ) {
			wp_send_json_error();
		}

		$selected_fees = $this->get_selected_fees();

		if ( $checked && ! in_array( $fee_id, $selected_fees, true ) ) {
			$selected_fees[] = $fee_id;
		} elseif ( ! $checked ) {
			$selected_fees = array_diff( $selected_fees, array( $fee_id ) );
		}

		$this->set_selected_fees( array_values( $selected_fees ) );

		wp_send_json_success();
	}

	/**
	 * Get selected optional fees from session.
	 *
	 * @return array
	 */
	private function get_selected_fees() {
		if ( ! WC()->session ) {
			return array();
		}
		$selected = WC()->session->get( self::SESSION_KEY );
		return is_array( $selected ) ? $selected : array();
	}

	/**
	 * Set selected optional fees in session.
	 *
	 * @param array $fees Array of fee IDs.
	 */
	private function set_selected_fees( $fees ) {
		if ( ! WC()->session ) {
			return;
		}
		WC()->session->set( self::SESSION_KEY, $fees );
	}

	/**
	 * Save applied fees to order meta.
	 *
	 * @param WC_Order $order The order object.
	 * @param array    $data  Posted checkout data.
	 */
	public function save_fees_to_order( $order, $data ) {
		$fees = Simple_Cart_Fees::get_active_fees();
		$selected_fees = $this->get_selected_fees();

		foreach ( $fees as $fee ) {
			if ( ! Simple_Cart_Fees::is_condition_met( $fee ) ) {
				continue;
			}

			$should_save = false;

			if ( 'required' === $fee['type'] ) {
				$should_save = true;
			} elseif ( 'optional' === $fee['type'] && in_array( $fee['id'], $selected_fees, true ) ) {
				$should_save = true;
			}

			if ( $should_save ) {
				$order->update_meta_data( '_simple_cart_fee_' . $fee['id'], 'yes' );
			}
		}

		// Clear selected fees from session after order is placed.
		$this->set_selected_fees( array() );
	}

	/**
	 * Add fees information to order emails.
	 *
	 * @param WC_Order $order         The order object.
	 * @param bool     $sent_to_admin Whether email is for admin.
	 * @param bool     $plain_text    Whether email is plain text.
	 * @param WC_Email $email         The email object.
	 */
	public function add_fees_to_email( $order, $sent_to_admin, $plain_text, $email ) {
		$all_fees = Simple_Cart_Fees::get_fees();
		$applied_fees = array();

		foreach ( $all_fees as $fee ) {
			$meta_key = '_simple_cart_fee_' . $fee['id'];
			$meta_value = $order->get_meta( $meta_key );
			if ( 'yes' === $meta_value ) {
				$applied_fees[] = $fee;
			}
		}

		if ( empty( $applied_fees ) ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Applied fees:', 'simple-cart-fees' ) . "\n";
			foreach ( $applied_fees as $fee ) {
				echo '- ' . esc_html( $fee['public_name'] ) . "\n";
			}
			echo "\n";
		} else {
			?>
			<div class="scf-email-fees" style="margin-bottom: 20px;">
				<h3 style="margin: 0 0 10px;"><?php esc_html_e( 'Applied fees:', 'simple-cart-fees' ); ?></h3>
				<ul style="margin: 0; padding-left: 20px;">
					<?php foreach ( $applied_fees as $fee ) : ?>
						<li><?php echo esc_html( $fee['public_name'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		}
	}
}
