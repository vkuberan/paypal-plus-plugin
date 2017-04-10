<?php
/**
 * Created by PhpStorm.
 * User: biont
 * Date: 16.01.17
 * Time: 15:22
 */

namespace WCPayPalPlus\WC\PUI;

/**
 * Class PaymentInstructionRenderer
 *
 * @package WCPayPalPlus\WC\PUI
 */
class PaymentInstructionRenderer {

	/**
	 * Setup required hooks.
	 */
	public function register() {

		add_action( 'woocommerce_thankyou_paypal_plus', [ $this, 'delegate_thankyou' ], 10, 1 );
		add_action( 'woocommerce_email_before_order_table', [ $this, 'delegate_email' ], 10, 3 );
		add_action( 'woocommerce_view_order', [ $this, 'delegate_view_order' ], 10, 1 );
	}

	/**
	 * Gather needed data and then render the view, if possible
	 */
	public function delegate_thankyou() {

		$order_key = filter_input( INPUT_GET, 'key' );

		$order    = wc_get_order( wc_get_order_id_by_order_key( $order_key ) );
		$pui_data = new PaymentInstructionData( $order );
		if ( ! $pui_data->has_payment_instructions() ) {
			return;
		}
		$pui_view = new PaymentInstructionView( $pui_data );
		$pui_view->thankyou_page();

	}

	/**
	 * Gather needed data and then render the view, if possible
	 *
	 * @param \WC_Order $order         WooCommerce order.
	 * @param bool      $sent_to_admin Will the eMail be sent to the site admin?.
	 * @param bool      $plain_text    Should we render as plain text?.
	 */
	public function delegate_email( \WC_Order $order, $sent_to_admin, $plain_text = false ) {

		$pui_data = new PaymentInstructionData( $order );
		if ( ! $pui_data->has_payment_instructions() ) {
			return;
		}
		$pui_view = new PaymentInstructionView( $pui_data );
		$pui_view->email_instructions();
	}

	/**
	 * Gather needed data and then render the view, if possible.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function delegate_view_order( $order_id ) {

		$order    = wc_get_order( $order_id );
		$pui_data = new PaymentInstructionData( $order );
		if ( ! $pui_data->has_payment_instructions() ) {
			return;
		}
		$pui_view = new PaymentInstructionView( $pui_data );
		$pui_view->thankyou_page();

	}

}