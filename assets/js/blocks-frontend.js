/**
 * Blocks checkout frontend for Simple Cart Fees.
 *
 * Handles optional fee checkboxes in the WooCommerce blocks checkout.
 *
 * @package Simple_Cart_Fees
 */

/* global wc */

( function() {
	'use strict';

	var registerPlugin = wp.plugins.registerPlugin;
	var createElement = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var CheckboxControl = wp.components.CheckboxControl;
	var ExperimentalOrderMeta = wc.blocksCheckout.ExperimentalOrderMeta;
	var extensionCartUpdate = wc.blocksCheckout.extensionCartUpdate;
	var getSetting = wc.wcSettings.getSetting;
	var decodeEntities = wp.htmlEntities.decodeEntities;
	var __ = wp.i18n.__;

	// Get plugin settings.
	var settings = getSetting( 'simple-cart-fees_data', {} );
	var optionalFees = settings.optionalFees || [];

	/**
	 * Optional Fee Checkbox Component.
	 *
	 * @param {Object} props Component props.
	 * @return {Object} React element.
	 */
	function OptionalFeeCheckbox( props ) {
		var fee = props.fee;
		var checked = useState( false );
		var isChecked = checked[ 0 ];
		var setIsChecked = checked[ 1 ];
		var isLoading = useState( false );
		var loading = isLoading[ 0 ];
		var setLoading = isLoading[ 1 ];

		// Handle checkbox change.
		var handleChange = function( newValue ) {
			setIsChecked( newValue );
			setLoading( true );

			extensionCartUpdate( {
				namespace: 'simple-cart-fees',
				data: {
					fee_id: fee.id,
					checked: newValue
				}
			} ).finally( function() {
				setLoading( false );
			} );
		};

		var label = decodeEntities( fee.checkbox_text ) + ' (' + decodeEntities( fee.price_html ) + ')';

		return createElement(
			'div',
			{
				className: 'scf-blocks-fee',
				style: {
					marginBottom: '12px'
				}
			},
			createElement(
				CheckboxControl,
				{
					label: label,
					checked: isChecked,
					onChange: handleChange,
					disabled: loading
				}
			),
			fee.help_text ? createElement(
				'p',
				{
					className: 'scf-blocks-fee-help',
					style: {
						marginLeft: '28px',
						marginTop: '4px',
						fontSize: '0.9em',
						color: '#666'
					}
				},
				decodeEntities( fee.help_text )
			) : null
		);
	}

	/**
	 * Optional Fees Block Component.
	 *
	 * @return {Object|null} React element or null.
	 */
	function OptionalFeesBlock() {
		if ( ! optionalFees.length ) {
			return null;
		}

		return createElement(
			ExperimentalOrderMeta,
			null,
			createElement(
				'div',
				{
					className: 'scf-blocks-optional-fees',
					style: {
						margin: '20px 0',
						padding: '15px',
						border: '1px solid #e0e0e0',
						borderRadius: '4px',
						background: '#f9f9f9'
					}
				},
				createElement(
					'h3',
					{
						style: {
							margin: '0 0 15px',
							fontSize: '1.1em'
						}
					},
					__( 'Additional Options', 'simple-cart-fees' )
				),
				optionalFees.map( function( fee ) {
					return createElement(
						OptionalFeeCheckbox,
						{
							key: fee.id,
							fee: fee
						}
					);
				} )
			)
		);
	}

	// Register the plugin if we have optional fees.
	if ( optionalFees.length ) {
		registerPlugin( 'simple-cart-fees', {
			render: OptionalFeesBlock,
			scope: 'woocommerce-checkout'
		} );
	}

} )();
