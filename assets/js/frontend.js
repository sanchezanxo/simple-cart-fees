/**
 * Frontend JavaScript for Simple Cart Fees.
 *
 * Handles optional fee checkboxes on classic checkout.
 *
 * @package Simple_Cart_Fees
 */

/* global jQuery, scfFrontend */

( function( $ ) {
	'use strict';

	var SCFFrontend = {
		/**
		 * Initialize frontend functionality.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			$( document.body ).on( 'change', '.scf-fee-checkbox', this.onFeeToggle.bind( this ) );
		},

		/**
		 * Handle fee checkbox toggle.
		 *
		 * @param {Event} e Change event.
		 */
		onFeeToggle: function( e ) {
			var $checkbox = $( e.target );
			var feeId = $checkbox.data( 'fee-id' );
			var isChecked = $checkbox.is( ':checked' );

			// Save selection via AJAX.
			$.ajax( {
				url: scfFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'scf_toggle_fee',
					nonce: scfFrontend.nonce,
					fee_id: feeId,
					checked: isChecked ? 'true' : 'false'
				},
				success: function() {
					// Trigger WooCommerce checkout update.
					$( document.body ).trigger( 'update_checkout' );
				}
			} );
		}
	};

	$( document ).ready( function() {
		SCFFrontend.init();
	} );

} )( jQuery );
