<?php
/**
 * WooCommerce Blocks integration.
 *
 * @package Simple_Cart_Fees
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * SCF_Blocks class.
 *
 * Handles integration with the WooCommerce blocks-based checkout.
 */
class SCF_Blocks {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_integration' ) );
	}

	/**
	 * Register the blocks integration.
	 */
	public function register_integration() {
		if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry' ) ) {
			return;
		}

		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new SCF_Blocks_Integration() );
			}
		);

		add_action(
			'woocommerce_blocks_cart_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new SCF_Blocks_Integration() );
			}
		);

		// Register Store API endpoint extension.
		$this->register_store_api_extension();
	}

	/**
	 * Register Store API extension for handling optional fees.
	 */
	private function register_store_api_extension() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
				'namespace'       => 'simple-cart-fees',
				'data_callback'   => array( $this, 'extend_checkout_data' ),
				'schema_callback' => array( $this, 'extend_checkout_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => 'simple-cart-fees',
				'callback'  => array( $this, 'handle_fee_update' ),
			)
		);
	}

	/**
	 * Extend checkout data with fee information.
	 *
	 * @return array
	 */
	public function extend_checkout_data() {
		$fees = Simple_Cart_Fees::get_active_fees();
		$selected_fees = $this->get_selected_fees();
		$optional_fees = array();

		foreach ( $fees as $fee ) {
			if ( 'optional' !== $fee['type'] ) {
				continue;
			}

			if ( ! Simple_Cart_Fees::is_condition_met( $fee ) ) {
				continue;
			}

			$checkbox_text = ! empty( $fee['checkbox_text'] ) ? $fee['checkbox_text'] : $fee['public_name'];

			$optional_fees[] = array(
				'id'            => $fee['id'],
				'checkbox_text' => $checkbox_text,
				'help_text'     => $fee['help_text'] ?? '',
				'price'         => $fee['price'],
				'price_html'    => wc_price( $fee['price'] ),
				'selected'      => in_array( $fee['id'], $selected_fees, true ),
			);
		}

		return array(
			'optional_fees' => $optional_fees,
		);
	}

	/**
	 * Schema for extended checkout data.
	 *
	 * @return array
	 */
	public function extend_checkout_schema() {
		return array(
			'optional_fees' => array(
				'description' => __( 'Optional fees available for checkout', 'simple-cart-fees' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'            => array(
							'description' => __( 'Fee ID', 'simple-cart-fees' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'checkbox_text' => array(
							'description' => __( 'Checkbox label', 'simple-cart-fees' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'help_text'     => array(
							'description' => __( 'Help text', 'simple-cart-fees' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'price'         => array(
							'description' => __( 'Fee price', 'simple-cart-fees' ),
							'type'        => 'number',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'price_html'    => array(
							'description' => __( 'Formatted price HTML', 'simple-cart-fees' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'selected'      => array(
							'description' => __( 'Whether fee is selected', 'simple-cart-fees' ),
							'type'        => 'boolean',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),
			),
		);
	}

	/**
	 * Handle fee update from Store API.
	 *
	 * @param array $data The update data.
	 */
	public function handle_fee_update( $data ) {
		if ( ! isset( $data['fee_id'] ) ) {
			return;
		}

		$fee_id = sanitize_text_field( $data['fee_id'] );
		$checked = ! empty( $data['checked'] );

		// Verify this is a valid optional fee.
		$fee = Simple_Cart_Fees::get_fee( $fee_id );
		if ( ! $fee || 'optional' !== $fee['type'] ) {
			return;
		}

		$selected_fees = $this->get_selected_fees();

		if ( $checked && ! in_array( $fee_id, $selected_fees, true ) ) {
			$selected_fees[] = $fee_id;
		} elseif ( ! $checked ) {
			$selected_fees = array_diff( $selected_fees, array( $fee_id ) );
		}

		$this->set_selected_fees( array_values( $selected_fees ) );
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
		$selected = WC()->session->get( SCF_Frontend::SESSION_KEY );
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
		WC()->session->set( SCF_Frontend::SESSION_KEY, $fees );
	}
}

/**
 * Blocks Integration class.
 */
class SCF_Blocks_Integration implements IntegrationInterface {

	/**
	 * Get the name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'simple-cart-fees';
	}

	/**
	 * Initialize the integration.
	 */
	public function initialize() {
		$this->register_block_frontend_scripts();
		$this->register_block_editor_scripts();
	}

	/**
	 * Register frontend scripts for blocks.
	 */
	private function register_block_frontend_scripts() {
		$script_url = SCF_PLUGIN_URL . 'assets/js/blocks-frontend.js';
		$script_path = SCF_PLUGIN_DIR . 'assets/js/blocks-frontend.js';

		// Only register if file exists.
		if ( ! file_exists( $script_path ) ) {
			return;
		}

		wp_register_script(
			'scf-blocks-frontend',
			$script_url,
			array(
				'wc-blocks-checkout',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
				'wp-plugins',
				'wp-components',
			),
			SCF_VERSION,
			true
		);

		wp_set_script_translations(
			'scf-blocks-frontend',
			'simple-cart-fees',
			SCF_PLUGIN_DIR . 'languages'
		);
	}

	/**
	 * Register editor scripts for blocks.
	 */
	private function register_block_editor_scripts() {
		// Not needed for this integration.
	}

	/**
	 * Returns array of scripts handles for frontend.
	 *
	 * @return array
	 */
	public function get_script_handles() {
		return array( 'scf-blocks-frontend' );
	}

	/**
	 * Returns array of scripts handles for editor.
	 *
	 * @return array
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * Get script data to be passed to the frontend.
	 *
	 * @return array
	 */
	public function get_script_data() {
		$fees = Simple_Cart_Fees::get_active_fees();
		$optional_fees = array();

		foreach ( $fees as $fee ) {
			if ( 'optional' !== $fee['type'] ) {
				continue;
			}

			if ( ! Simple_Cart_Fees::is_condition_met( $fee ) ) {
				continue;
			}

			$checkbox_text = ! empty( $fee['checkbox_text'] ) ? $fee['checkbox_text'] : $fee['public_name'];

			$optional_fees[] = array(
				'id'            => $fee['id'],
				'checkbox_text' => $checkbox_text,
				'help_text'     => $fee['help_text'] ?? '',
				'price'         => $fee['price'],
				'price_html'    => wc_price( $fee['price'] ),
			);
		}

		return array(
			'optionalFees' => $optional_fees,
		);
	}
}
