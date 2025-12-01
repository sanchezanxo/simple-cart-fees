<?php
/**
 * Plugin Name: Simple Cart Fees
 * Plugin URI: https://wordpress.org/plugins/simple-cart-fees/
 * Description: Add configurable fees to WooCommerce cart and checkout. Supports required and optional fees with conditions.
 * Version: 1.0.0
 * Author: Anxo Sánchez García
 * Author URI: https://www.anxosanchez.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-cart-fees
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 *
 * @package Simple_Cart_Fees
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'SCF_VERSION', '1.0.0' );
define( 'SCF_PLUGIN_FILE', __FILE__ );
define( 'SCF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SCF_OPTION_NAME', 'simple_cart_fees_data' );

/**
 * Declare HPOS compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Check if WooCommerce is active before loading the plugin.
 *
 * @return bool
 */
function simple_cart_fees_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active.
 */
function simple_cart_fees_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: WooCommerce plugin name */
					__( '%s requires WooCommerce to be installed and active.', 'simple-cart-fees' ),
					'<strong>Simple Cart Fees</strong>'
				),
				array( 'strong' => array() )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function simple_cart_fees_init() {
	if ( ! simple_cart_fees_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'simple_cart_fees_woocommerce_missing_notice' );
		return;
	}

	// Load classes.
	require_once SCF_PLUGIN_DIR . 'includes/class-simple-cart-fees.php';
	require_once SCF_PLUGIN_DIR . 'includes/class-admin.php';
	require_once SCF_PLUGIN_DIR . 'includes/class-frontend.php';
	require_once SCF_PLUGIN_DIR . 'includes/class-blocks.php';

	// Initialize main class.
	Simple_Cart_Fees::get_instance();
}
add_action( 'plugins_loaded', 'simple_cart_fees_init' );

/**
 * Activation hook.
 */
function simple_cart_fees_activate() {
	// Initialize default option if not exists.
	if ( false === get_option( SCF_OPTION_NAME ) ) {
		add_option( SCF_OPTION_NAME, array() );
	}
}
register_activation_hook( __FILE__, 'simple_cart_fees_activate' );
