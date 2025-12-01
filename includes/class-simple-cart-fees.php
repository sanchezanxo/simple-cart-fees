<?php
/**
 * Main plugin class.
 *
 * @package Simple_Cart_Fees
 */

defined( 'ABSPATH' ) || exit;

/**
 * Simple_Cart_Fees class.
 *
 * Singleton that initializes and coordinates all plugin components.
 */
class Simple_Cart_Fees {

	/**
	 * Single instance of the class.
	 *
	 * @var Simple_Cart_Fees|null
	 */
	private static $instance = null;

	/**
	 * Admin instance.
	 *
	 * @var SCF_Admin|null
	 */
	public $admin = null;

	/**
	 * Frontend instance.
	 *
	 * @var SCF_Frontend|null
	 */
	public $frontend = null;

	/**
	 * Blocks instance.
	 *
	 * @var SCF_Blocks|null
	 */
	public $blocks = null;

	/**
	 * Get the single instance of the class.
	 *
	 * @return Simple_Cart_Fees
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin components.
	 */
	private function init() {
		// Initialize admin.
		if ( is_admin() ) {
			$this->admin = new SCF_Admin();
		}

		// Initialize frontend.
		$this->frontend = new SCF_Frontend();

		// Initialize blocks integration.
		$this->blocks = new SCF_Blocks();
	}

	/**
	 * Get all configured fees.
	 *
	 * @return array
	 */
	public static function get_fees() {
		$fees = get_option( SCF_OPTION_NAME, array() );
		if ( ! is_array( $fees ) ) {
			return array();
		}
		// Sort by order.
		usort(
			$fees,
			function ( $a, $b ) {
				$order_a = isset( $a['order'] ) ? (int) $a['order'] : 0;
				$order_b = isset( $b['order'] ) ? (int) $b['order'] : 0;
				return $order_a - $order_b;
			}
		);
		return $fees;
	}

	/**
	 * Get active fees only.
	 *
	 * @return array
	 */
	public static function get_active_fees() {
		$fees = self::get_fees();
		return array_filter(
			$fees,
			function ( $fee ) {
				return ! empty( $fee['active'] );
			}
		);
	}

	/**
	 * Get a single fee by ID.
	 *
	 * @param string $fee_id The fee ID.
	 * @return array|null
	 */
	public static function get_fee( $fee_id ) {
		$fees = self::get_fees();
		foreach ( $fees as $fee ) {
			if ( isset( $fee['id'] ) && $fee['id'] === $fee_id ) {
				return $fee;
			}
		}
		return null;
	}

	/**
	 * Save all fees.
	 *
	 * @param array $fees The fees array.
	 * @return bool
	 */
	public static function save_fees( $fees ) {
		return update_option( SCF_OPTION_NAME, $fees );
	}

	/**
	 * Generate a unique fee ID.
	 *
	 * @return string
	 */
	public static function generate_fee_id() {
		return 'fee_' . wp_generate_password( 8, false, false );
	}

	/**
	 * Check if a fee's condition is met.
	 *
	 * @param array $fee The fee configuration.
	 * @return bool
	 */
	public static function is_condition_met( $fee ) {
		if ( ! isset( $fee['condition'] ) || 'always' === $fee['condition'] ) {
			return true;
		}

		if ( 'minimum' === $fee['condition'] && WC()->cart ) {
			$minimum = isset( $fee['condition_minimum'] ) ? floatval( $fee['condition_minimum'] ) : 0;
			$subtotal = WC()->cart->get_subtotal();
			return $subtotal >= $minimum;
		}

		return true;
	}

	/**
	 * Calculate the net amount (without tax) from a gross price.
	 *
	 * European stores typically enter prices with VAT included.
	 * This extracts the base amount for proper tax calculation.
	 *
	 * @param float  $gross_price Price including tax.
	 * @param string $tax_class   The tax class to use.
	 * @return float Net price (base taxable amount).
	 */
	public static function get_net_from_gross( $gross_price, $tax_class = '' ) {
		if ( ! wc_tax_enabled() ) {
			return $gross_price;
		}

		$tax_rates = WC_Tax::get_rates( $tax_class );
		if ( empty( $tax_rates ) ) {
			return $gross_price;
		}

		// Calculate total tax rate.
		$total_rate = 0;
		foreach ( $tax_rates as $rate ) {
			$total_rate += floatval( $rate['rate'] );
		}

		// Extract net from gross: net = gross / (1 + rate/100).
		$net_price = $gross_price / ( 1 + ( $total_rate / 100 ) );

		return $net_price;
	}

	/**
	 * Get WooCommerce tax classes for admin select.
	 *
	 * @return array
	 */
	public static function get_tax_classes() {
		$classes = array(
			'' => __( 'Standard', 'simple-cart-fees' ),
		);

		$tax_classes = WC_Tax::get_tax_classes();
		foreach ( $tax_classes as $class ) {
			$classes[ sanitize_title( $class ) ] = $class;
		}

		return $classes;
	}
}
