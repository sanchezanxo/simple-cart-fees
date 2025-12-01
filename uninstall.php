<?php
/**
 * Uninstall Simple Cart Fees.
 *
 * Fired when the plugin is deleted (not deactivated).
 *
 * @package Simple_Cart_Fees
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options.
delete_option( 'simple_cart_fees_data' );

/*
 * Order meta is NOT deleted to preserve historical data.
 *
 * If you want to remove all order meta as well, uncomment the code below.
 * WARNING: This will permanently delete fee data from all orders.
 *
 * global $wpdb;
 *
 * // Delete from traditional post meta (non-HPOS).
 * $wpdb->query(
 *     "DELETE FROM {$wpdb->postmeta}
 *     WHERE meta_key LIKE '_simple_cart_fee_%'"
 * );
 *
 * // Delete from HPOS meta table if it exists.
 * $hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';
 * if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_meta_table ) ) === $hpos_meta_table ) {
 *     $wpdb->query(
 *         "DELETE FROM {$hpos_meta_table}
 *         WHERE meta_key LIKE '_simple_cart_fee_%'"
 *     );
 * }
 */
