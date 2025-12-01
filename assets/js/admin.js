/**
 * Admin JavaScript for Simple Cart Fees.
 *
 * Handles the fees management interface.
 *
 * @package Simple_Cart_Fees
 */

/* global jQuery, scfAdmin */

( function( $ ) {
	'use strict';

	var SCFAdmin = {
		/**
		 * Current row index counter.
		 */
		rowIndex: 0,

		/**
		 * Initialize the admin functionality.
		 */
		init: function() {
			this.cacheElements();
			this.bindEvents();
			this.initSortable();
			this.updateRowIndex();
		},

		/**
		 * Cache DOM elements.
		 */
		cacheElements: function() {
			this.$form = $( '#scf-fees-form' );
			this.$feesList = $( '#scf-fees-list' );
			this.$addButton = $( '#scf-add-fee' );
			this.$saveButton = $( '#scf-save-fees' );
			this.$spinner = this.$form.find( '.spinner' );
			this.$notices = $( '#scf-notices' );
			this.$modal = $( '#scf-fee-modal' );
			this.$modalTitle = $( '#scf-modal-title' );
			this.$rowTemplate = $( '#scf-fee-row-template' ).html();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			this.$addButton.on( 'click', this.addNewFee.bind( this ) );
			this.$form.on( 'submit', this.saveFees.bind( this ) );
			this.$feesList.on( 'click', '.scf-edit-fee', this.openEditModal.bind( this ) );
			this.$feesList.on( 'click', '.scf-delete-fee', this.deleteFee.bind( this ) );
			this.$modal.on( 'click', '.scf-modal-close, .scf-modal-cancel', this.closeModal.bind( this ) );
			this.$modal.on( 'click', '#scf-modal-save', this.saveModal.bind( this ) );
			$( '#scf-type' ).on( 'change', this.toggleOptionalFields.bind( this ) );
			$( '#scf-condition' ).on( 'change', this.toggleMinimumField.bind( this ) );

			// Close modal on outside click.
			this.$modal.on( 'click', function( e ) {
				if ( $( e.target ).is( '.scf-modal' ) ) {
					this.closeModal();
				}
			}.bind( this ) );

			// Close modal on escape key.
			$( document ).on( 'keyup', function( e ) {
				if ( 27 === e.keyCode && this.$modal.is( ':visible' ) ) {
					this.closeModal();
				}
			}.bind( this ) );
		},

		/**
		 * Initialize sortable for fee rows.
		 */
		initSortable: function() {
			this.$feesList.sortable( {
				handle: '.scf-drag-handle',
				placeholder: 'ui-sortable-placeholder',
				axis: 'y',
				update: this.updateOrder.bind( this )
			} );
		},

		/**
		 * Update the row index counter based on existing rows.
		 */
		updateRowIndex: function() {
			var maxIndex = 0;
			this.$feesList.find( '.scf-fee-row' ).each( function() {
				var index = parseInt( $( this ).data( 'index' ), 10 );
				if ( ! isNaN( index ) && index > maxIndex ) {
					maxIndex = index;
				}
			} );
			this.rowIndex = maxIndex + 1;
		},

		/**
		 * Add a new fee row and open modal.
		 *
		 * @param {Event} e Click event.
		 */
		addNewFee: function( e ) {
			e.preventDefault();

			// Remove "no fees" row if present.
			this.$feesList.find( '.scf-no-fees' ).remove();

			// Create new row from template.
			var newRow = this.$rowTemplate.replace( /\{\{INDEX\}\}/g, this.rowIndex );
			this.$feesList.append( newRow );

			// Generate new ID for the row.
			var $newRow = this.$feesList.find( '.scf-fee-row' ).last();
			$newRow.find( '.scf-field-id' ).val( 'fee_' + this.generateId() );

			this.rowIndex++;

			// Open modal to edit the new fee.
			$newRow.find( '.scf-edit-fee' ).trigger( 'click' );
		},

		/**
		 * Generate a random ID string.
		 *
		 * @return {string} Random ID.
		 */
		generateId: function() {
			var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
			var result = '';
			for ( var i = 0; i < 8; i++ ) {
				result += chars.charAt( Math.floor( Math.random() * chars.length ) );
			}
			return result;
		},

		/**
		 * Open the edit modal for a fee.
		 *
		 * @param {Event} e Click event.
		 */
		openEditModal: function( e ) {
			e.preventDefault();

			var $row = $( e.target ).closest( '.scf-fee-row' );
			var index = $row.data( 'index' );

			// Store index for saving.
			$( '#scf-edit-index' ).val( index );
			$( '#scf-edit-id' ).val( $row.find( '.scf-field-id' ).val() );

			// Populate modal fields.
			$( '#scf-internal-name' ).val( $row.find( '.scf-field-internal_name' ).val() );
			$( '#scf-public-name' ).val( $row.find( '.scf-field-public_name' ).val() );
			$( '#scf-price' ).val( $row.find( '.scf-field-price' ).val() );
			$( '#scf-tax-class' ).val( $row.find( '.scf-field-tax_class' ).val() );
			$( '#scf-type' ).val( $row.find( '.scf-field-type' ).val() );
			$( '#scf-checkbox-text' ).val( $row.find( '.scf-field-checkbox_text' ).val() );
			$( '#scf-help-text' ).val( $row.find( '.scf-field-help_text' ).val() );
			$( '#scf-condition' ).val( $row.find( '.scf-field-condition' ).val() );
			$( '#scf-condition-minimum' ).val( $row.find( '.scf-field-condition_minimum' ).val() );
			$( '#scf-active' ).prop( 'checked', '1' === $row.find( '.scf-field-active' ).val() );

			// Update field visibility.
			this.toggleOptionalFields();
			this.toggleMinimumField();

			// Show modal.
			this.$modal.show();
			$( '#scf-internal-name' ).focus();
		},

		/**
		 * Close the modal.
		 */
		closeModal: function() {
			this.$modal.hide();
		},

		/**
		 * Save modal data to the row.
		 */
		saveModal: function() {
			var internalName = $( '#scf-internal-name' ).val().trim();
			var publicName = $( '#scf-public-name' ).val().trim();
			var price = $( '#scf-price' ).val();

			// Validate required fields.
			if ( ! internalName ) {
				$( '#scf-internal-name' ).focus();
				this.showNotice( scfAdmin.strings.requiredInternalName || 'Internal name is required.', 'error' );
				return;
			}

			if ( ! publicName ) {
				$( '#scf-public-name' ).focus();
				this.showNotice( scfAdmin.strings.requiredPublicName || 'Public name is required.', 'error' );
				return;
			}

			if ( ! price || parseFloat( price ) < 0 ) {
				$( '#scf-price' ).focus();
				this.showNotice( scfAdmin.strings.requiredPrice || 'Price must be a valid positive number.', 'error' );
				return;
			}

			var index = $( '#scf-edit-index' ).val();
			var $row = this.$feesList.find( '.scf-fee-row[data-index="' + index + '"]' );

			if ( ! $row.length ) {
				this.closeModal();
				return;
			}

			// Update hidden fields.
			$row.find( '.scf-field-internal_name' ).val( $( '#scf-internal-name' ).val() );
			$row.find( '.scf-field-public_name' ).val( $( '#scf-public-name' ).val() );
			$row.find( '.scf-field-price' ).val( $( '#scf-price' ).val() );
			$row.find( '.scf-field-tax_class' ).val( $( '#scf-tax-class' ).val() );
			$row.find( '.scf-field-type' ).val( $( '#scf-type' ).val() );
			$row.find( '.scf-field-checkbox_text' ).val( $( '#scf-checkbox-text' ).val() );
			$row.find( '.scf-field-help_text' ).val( $( '#scf-help-text' ).val() );
			$row.find( '.scf-field-condition' ).val( $( '#scf-condition' ).val() );
			$row.find( '.scf-field-condition_minimum' ).val( $( '#scf-condition-minimum' ).val() );
			$row.find( '.scf-field-active' ).val( $( '#scf-active' ).is( ':checked' ) ? '1' : '0' );

			// Update display values.
			$row.find( '.scf-display-name' ).text( $( '#scf-internal-name' ).val() );

			var price = parseFloat( $( '#scf-price' ).val() ) || 0;
			$row.find( '.scf-display-price' ).html( this.formatPrice( price ) );

			var type = $( '#scf-type' ).val();
			$row.find( '.scf-display-type' ).text( 'optional' === type ? 'Optional' : 'Required' );

			var condition = $( '#scf-condition' ).val();
			if ( 'minimum' === condition ) {
				var minimum = parseFloat( $( '#scf-condition-minimum' ).val() ) || 0;
				$row.find( '.scf-display-condition' ).html( 'Min. ' + this.formatPrice( minimum ) );
			} else {
				$row.find( '.scf-display-condition' ).text( 'Always' );
			}

			var active = $( '#scf-active' ).is( ':checked' );
			$row.find( '.scf-display-active' )
				.text( active ? 'Yes' : 'No' )
				.toggleClass( 'scf-active-yes', active )
				.toggleClass( 'scf-active-no', ! active );

			this.closeModal();
		},

		/**
		 * Format price for display using WooCommerce settings.
		 *
		 * @param {number} price The price to format.
		 * @return {string} Formatted price.
		 */
		formatPrice: function( price ) {
			var decimals = scfAdmin.decimals || 2;
			var decimalSep = scfAdmin.decimalSep || '.';
			var thousandSep = scfAdmin.thousandSep || ',';
			var symbol = scfAdmin.currencySymbol || '$';
			var position = scfAdmin.currencyPos || 'left';

			// Format number with decimals and separators.
			var parts = price.toFixed( decimals ).split( '.' );
			parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, thousandSep );
			var formatted = parts.join( decimalSep );

			// Apply currency position.
			switch ( position ) {
				case 'left':
					return symbol + formatted;
				case 'right':
					return formatted + symbol;
				case 'left_space':
					return symbol + ' ' + formatted;
				case 'right_space':
					return formatted + ' ' + symbol;
				default:
					return symbol + formatted;
			}
		},

		/**
		 * Toggle optional fee fields visibility.
		 */
		toggleOptionalFields: function() {
			var isOptional = 'optional' === $( '#scf-type' ).val();
			$( '.scf-optional-fields' ).toggle( isOptional );
		},

		/**
		 * Toggle minimum field visibility.
		 */
		toggleMinimumField: function() {
			var isMinimum = 'minimum' === $( '#scf-condition' ).val();
			$( '.scf-minimum-field' ).toggle( isMinimum );
		},

		/**
		 * Delete a fee row.
		 *
		 * @param {Event} e Click event.
		 */
		deleteFee: function( e ) {
			e.preventDefault();

			if ( ! confirm( scfAdmin.strings.confirmDelete ) ) {
				return;
			}

			var $row = $( e.target ).closest( '.scf-fee-row' );
			$row.fadeOut( 200, function() {
				$row.remove();

				// Show "no fees" message if empty.
				if ( 0 === $( '#scf-fees-list .scf-fee-row' ).length ) {
					$( '#scf-fees-list' ).append(
						'<tr class="scf-no-fees"><td colspan="7">No fees configured. Click "Add Fee" to create one.</td></tr>'
					);
				}
			} );
		},

		/**
		 * Update order values after sorting.
		 */
		updateOrder: function() {
			this.$feesList.find( '.scf-fee-row' ).each( function( index ) {
				$( this ).find( '.scf-field-order' ).val( index );
			} );
		},

		/**
		 * Save all fees via AJAX.
		 *
		 * @param {Event} e Submit event.
		 */
		saveFees: function( e ) {
			e.preventDefault();

			var fees = [];
			this.$feesList.find( '.scf-fee-row' ).each( function( index ) {
				var $row = $( this );
				fees.push( {
					id: $row.find( '.scf-field-id' ).val(),
					internal_name: $row.find( '.scf-field-internal_name' ).val(),
					public_name: $row.find( '.scf-field-public_name' ).val(),
					price: $row.find( '.scf-field-price' ).val(),
					tax_class: $row.find( '.scf-field-tax_class' ).val(),
					type: $row.find( '.scf-field-type' ).val(),
					checkbox_text: $row.find( '.scf-field-checkbox_text' ).val(),
					help_text: $row.find( '.scf-field-help_text' ).val(),
					condition: $row.find( '.scf-field-condition' ).val(),
					condition_minimum: $row.find( '.scf-field-condition_minimum' ).val(),
					order: index,
					active: $row.find( '.scf-field-active' ).val()
				} );
			} );

			this.$spinner.addClass( 'is-active' );
			this.$saveButton.prop( 'disabled', true );

			$.ajax( {
				url: scfAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'scf_save_fees',
					nonce: scfAdmin.nonce,
					fees: fees
				},
				success: function( response ) {
					this.$spinner.removeClass( 'is-active' );
					this.$saveButton.prop( 'disabled', false );

					if ( response.success ) {
						this.showNotice( scfAdmin.strings.saved, 'success' );
					} else {
						this.showNotice( response.data.message || scfAdmin.strings.error, 'error' );
					}
				}.bind( this ),
				error: function() {
					this.$spinner.removeClass( 'is-active' );
					this.$saveButton.prop( 'disabled', false );
					this.showNotice( scfAdmin.strings.error, 'error' );
				}.bind( this )
			} );
		},

		/**
		 * Show an admin notice.
		 *
		 * @param {string} message The message to display.
		 * @param {string} type    Notice type (success, error, warning, info).
		 */
		showNotice: function( message, type ) {
			var $notice = $(
				'<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p>' +
				'<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>'
			);

			this.$notices.html( $notice );

			$notice.find( '.notice-dismiss' ).on( 'click', function() {
				$notice.fadeOut( 200, function() {
					$notice.remove();
				} );
			} );

			// Auto-dismiss success notices.
			if ( 'success' === type ) {
				setTimeout( function() {
					$notice.fadeOut( 200, function() {
						$notice.remove();
					} );
				}, 3000 );
			}
		}
	};

	$( document ).ready( function() {
		SCFAdmin.init();
	} );

} )( jQuery );
