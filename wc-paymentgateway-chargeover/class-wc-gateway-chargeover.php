<?php

/*
Plugin Name:  ChargeOver Plugin
Plugin URI:   https://developer.wordpress.org/plugins/the-basics/
Description:  Basic WordPress Plugin Header Comment
Version:      20170911
Author:       ChargeOver.com
Author URI:   https://developer.wordpress.org/
*/


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function init_chargeover() {
    class WC_Gateway_Chargeover extends WC_Payment_Gateway {


    	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'chargeover';
		$this->icon               = apply_filters( 'woocommerce_cheque_icon', '' );
		$this->has_fields         = true;
		$this->method_title       = _x( 'ChargeOver payments', 'ChargeOver payment method', 'woocommerce' );
		$this->method_description = __( 'Allows you to use your ChargeOver account as the payment gateway to process payments via.', 'woocommerce' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions' );

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		//add_action( 'woocommerce_thankyou_chargeover', array( $this, 'thankyou_page' ) );

		// Customer Emails
		//add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable check payments', 'woocommerce' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default'     => _x( 'Check payments', 'Check payment method', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Please send a check to Store Name, Store Street, Store Town, Store State / County, Store Postcode.', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Output for the order received page.
	 */
	/*public function thankyou_page() {
		if ( $this->instructions ) {
			echo wpautop( wptexturize( $this->instructions ) );
		}
	}
	*/

	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 */
	/*public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $this->instructions && ! $sent_to_admin && 'cheque' === $order->get_payment_method() && $order->has_status( 'on-hold' ) ) {
			echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
		}
	}
	*/

	public function payment_fields() {
		print('Card number: <input type="text" name="card_number" value="">');
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		error_log(print_r($order, true));
		error_log('card number: ' . $_POST['card_number']);

		require_once dirname(__FILE__) . '/chargeover_php-master/ChargeOverAPI.php';

		$success = false;

		$authmode = ChargeOverAPI::AUTHMODE_HTTP_BASIC;
		$API = new ChargeOverAPI('http://dev.chargeover.com/api/v3', $authmode, 'QpmOVelYGzAL2JCaUktBE5cry9iqxDRZ', 'U3h7wCEXHltN4rD8GgsybzmTeFPjKk1d');

		$Customer = new ChargeOverAPI_Object_Customer(array(
			// Main customer data
			'company' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),

			'bill_addr1' => $order->get_billing_address_1(),
			'bill_addr2' => $order->get_billing_address_2(),
			'bill_city' => $order->get_billing_city(),
			'bill_state' => $order->get_billing_state(),
			'bill_postcode' => $order->get_billing_postcode(),
			'bill_country' => $order->get_billing_country(),
			'external_key' => 'WC' . $order->get_customer_id(),

			'superuser_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'superuser_email' => $order->get_billing_email(),
			));
		$resp = $API->create($Customer);

		if (!$API->isError($resp))
		{
			// Add a credit card
			$CreditCard = new ChargeOverAPI_Object_CreditCard(array(
				'customer_id' => $resp->response->id,
				'number' => $_POST['card_number'],
				'expdate_year' => '2018',
				'expdate_month' => '8',
				'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
				));
			$resp3 = $API->create($CreditCard);

			// Attempt payment
			$resp2 = $API->action('transaction',
				null,
				'pay', 				// This is the type of action we want to perform
				array(
					'customer_id' => $resp->response->id,
					'amount' => $order->get_total(),
					));

			if (!$API->isError($resp2))
			{
				$success = true;
			}
			else
			{
				$error_message = $resp2->code . ': ' . $resp2->message;
			}
		}
		else
		{
			$error_message = $resp->code . ': ' . $resp->message;
		}

		if ($success)
		{
			// Mark as on-hold (we're awaiting the cheque)
			//$order->update_status( 'on-hold', _x( 'Awaiting check payment', 'Check payment method', 'woocommerce' ) );
			$order->payment_complete();

			// Reduce stock levels
			//wc_reduce_stock_levels( $order_id );

			// Remove cart
			WC()->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order ),
			);
		}
		else
		{
			$order->update_status( 'failed', _x( 'Payment failed', 'ChargeOver payment method', 'woocommerce' ) );

			wc_add_notice( __('Payment error:', 'woothemes') . $error_message, 'error' );
			return;
		}
	}

    }
}

function add_chargeover( $methods ) {
    $methods[] = 'WC_Gateway_Chargeover';
    return $methods;
}

add_action( 'plugins_loaded', 'init_chargeover' );

add_filter( 'woocommerce_payment_gateways', 'add_chargeover' );

/**
 * Cheque Payment Gateway.
 *
 * Provides a Cheque Payment Gateway, mainly for testing purposes.
 *
 * @class 		WC_Gateway_Cheque
 * @extends		WC_Payment_Gateway
 * @version		2.1.0
 * @package		WooCommerce/Classes/Payment
 * @author 		WooThemes
 */
/*class WC_Gateway_Chargeover extends WC_Payment_Gateway {


}
*/
