<?php
/**
 * Plugin Name: SagePay Form Gateway for WooCommerce
 * Plugin URI: http://www.patsatech.com/
 * Description: WooCommerce Plugin for accepting payment through SagePay Form Gateway.
 * Version: 1.4.5
 * Author: PatSaTECH
 * Author URI: http://www.patsatech.com
 * Contributors: patsatech
 * Requires at least: 4.5
 * Tested up to: 5.2.3
 * WC requires at least: 3.0.0
 * WC tested up to: 3.7.0
 *
 * Text Domain: woo-sagepayform-patsatech
 * Domain Path: /lang/
 *
 * @package SagePay Form Gateway for WooCommerce
 * @author PatSaTECH
 */

add_action('plugins_loaded', 'init_woocommerce_sagepayform', 0);

function init_woocommerce_sagepayform() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	load_plugin_textdomain('woo-sagepayform-patsatech', false, dirname( plugin_basename( __FILE__ ) ) . '/lang');

	class woocommerce_sagepayform extends WC_Payment_Gateway {

		public function __construct() {
			global $woocommerce;

			$this->id			= 'sagepayform';
			$this->method_title = __( 'SagePay Form', 'woo-sagepayform-patsatech' );
			$this->icon			= apply_filters( 'woocommerce_sagepayform_icon', '' );
			$this->has_fields 	= false;

			$default_card_type_options = array(
												'VISA' => 'VISA',
												'MC' => 'MasterCard',
												'AMEX' => 'American Express',
												'DISC' => 'Discover',
												'DC' => 'Diner\'s Club',
												'JCB' => 'JCB Card'
												);

			$this->card_type_options = apply_filters( 'woocommerce_sagepayform_card_types', $default_card_type_options );

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->title 		= $this->settings['title'];
			$this->description 	= $this->settings['description'];
			$this->vendor_name  = $this->settings['vendorname'];
			$this->vendor_pass  = $this->settings['vendorpass'];
			$this->mode         = $this->settings['mode'];
			$this->apply3d      = $this->settings['apply3d'];
			$this->transtype    = $this->settings['transtype'];
			$this->vendoremail  = $this->settings['vendoremail'];
			$this->sendemails   = $this->settings['sendemails'];
			$this->emailmessage = $this->settings['emailmessage'];
			$this->send_shipping= $this->settings['send_shipping'];
			$this->cardtypes	= $this->settings['cardtypes'];
			$this->notify_url   = str_replace( 'https:', 'http:', home_url( '/wc-api/woocommerce_sagepayform' ) );

			// Actions
			add_action( 'init', array( $this, 'successful_request' ) );
			add_action( 'woocommerce_api_woocommerce_sagepayform', array( $this, 'successful_request' ) );
			add_action( 'woocommerce_receipt_sagepayform', array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

	    }

		/**
		 * get_icon function.
		 *
		 * @access public
		 * @return string
		 */
		function get_icon() {
			global $woocommerce;

			$icon = '';
			if ( $this->icon ) {
				// default behavior
				$icon = '<img src="' . $this->force_ssl( $this->icon ) . '" alt="' . $this->title . '" />';
			} elseif ( $this->cardtypes ) {
				// display icons for the selected card types
				$icon = '';
				foreach ( $this->cardtypes as $cardtype ) {
					if ( file_exists( plugin_dir_path( __FILE__ ) . '/images/card-' . strtolower( $cardtype ) . '.png' ) ) {
						$icon .= '<img src="' . $this->force_ssl( plugins_url( '/images/card-' . strtolower( $cardtype ) . '.png', __FILE__ ) ) . '" alt="' . strtolower( $cardtype ) . '" />';
					}
				}
			}

			return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}

		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @since 1.0.0
		 */
		public function admin_options() {

	    	?>
	    	<h3><?php _e('SagePay Form', 'woo-sagepayform-patsatech'); ?></h3>
	    	<p><?php _e('SagePay Form works by sending the user to SagePay to enter their payment information.' , 'woo-sagepayform-patsatech'); ?></p>
	    	<table class="form-table">
	    	<?php

	    		// Generate the HTML For the settings form.
	    		$this->generate_settings_html();

	    	?>
			</table><!--/.form-table-->
	    	<?php
	    } // End admin_options()

		/**
	     * Initialise Gateway Settings Form Fields
	     */
	    function init_form_fields() {

	    	$this->form_fields = array(
					'enabled' => array(
						'title' => __( 'Enable/Disable', 'woo-sagepayform-patsatech' ),
						'type' => 'checkbox',
						'label' => __( 'Enable SagePay Form', 'woo-sagepayform-patsatech' ),
						'default' => 'yes',
	          'desc_tip'    => true
					),
					'title' => array(
						'title' => __( 'Title', 'woo-sagepayform-patsatech' ),
						'type' => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'woo-sagepayform-patsatech' ),
						'default' => __( 'SagePay Form', 'woo-sagepayform-patsatech' ),
	          'desc_tip'    => true
					),
					'description' => array(
						'title' => __( 'Description', 'woo-sagepayform-patsatech' ),
						'type' => 'textarea',
						'description' => __( 'This controls the description which the user sees during checkout.', 'woo-sagepayform-patsatech' ),
						'default' => __("Pay via SagePay; you can pay with your Credit Card.", 'woo-sagepayform-patsatech'),
	          'desc_tip'    => true
					),
					'vendorname' => array(
						'title' => __( 'Vendor Name', 'woo-sagepayform-patsatech' ),
						'type' => 'text',
						'description' => __( 'Please enter your vendor name provided by SagePay.', 'woo-sagepayform-patsatech' ),
						'default' => '',
	          'desc_tip'    => true
					),
					'vendorpass' => array(
						'title' => __( 'Encryption Password', 'woo-sagepayform-patsatech' ),
						'type' => 'text',
						'description' => __( 'Please enter your encryption password provided by SagePay.', 'woo-sagepayform-patsatech' ),
						'default' => '',
	          'desc_tip'    => true
					),
					'vendoremail' => array(
						'title' => __( 'Vendor E-Mail', 'woo-sagepayform-patsatech' ),
						'type' => 'text',
						'description' => __( 'An e-mail address on which you can be contacted when a transaction completes.', 'woo-sagepayform-patsatech' ),
						'default' => '',
	          'desc_tip'    => true
					),
					'sendemails' => array(
						'title' => __('Send E-Mail', 'woo-sagepayform-patsatech'),
						'type' => 'select',
						'options' => array(
							'0' => 'No One',
							'1' => 'Customer and Vendor',
							'2' => 'Vendor Only'
						),
						'description' => __( 'Who to send e-mails to.', 'woo-sagepayform-patsatech' ),
						'default' => '2',
	          'desc_tip'    => true
					),
					'emailmessage' => array(
						'title' => __( 'Customer E-Mail Message', 'woo-sagepayform-patsatech' ),
						'type' => 'textarea',
						'description' => __( 'A message to the customer which is inserted into the successful transaction e-mails only.', 'woo-sagepayform-patsatech' ),
						'default' => '',
	          'desc_tip'    => true
					),
					'mode' => array(
						'title' => __('Mode Type', 'woo-sagepayform-patsatech'),
						'type' => 'select',
						'options' => array(
							'test' => 'Test',
							'live' => 'Live'
						),
						'description' => __( 'Select Simulator, Test or Live modes.', 'woo-sagepayform-patsatech' ),
						'default' => 'live',
	          'desc_tip'    => true
					),
					'apply3d' => array(
						'title' => __('Apply 3D Secure', 'woo-sagepayform-patsatech'),
						'type' => 'select',
						'options' => array(
							'1' => 'Yes',
							'0' => 'No'
						),
						'description' => __( 'Select whether you would like to do 3D Secure Check on Transactions.', 'woo-sagepayform-patsatech' ),
						'default' => '0',
	          'desc_tip'    => true
					),
					'send_shipping' => array(
						'title' => __('Select Shipping Address', 'woo-sagepayform-patsatech'),
						'type' => 'select',
						'options' => array(
							'auto' => 'Auto',
							'yes' => 'Billing Address'
						),
						'description' => __( 'Select Auto if you want the plugin to decide which address to send based on type of Product. And select Billing Address if you want the plugin to send Billing Address irrespective of the type to Product.', 'woo-sagepayform-patsatech' ),
						'default' => 'auto',
	          'desc_tip'    => true
					),
					'transtype'	=> array(
						'title' => __('Transition Type', 'woo-sagepayform-patsatech'),
						'type' => 'select',
						'options' => array(
							'PAYMENT' => __('Payment', 'woo-sagepayform-patsatech'),
							'DEFERRED' => __('Deferred', 'woo-sagepayform-patsatech'),
							'AUTHENTICATE' => __('Authenticate', 'woo-sagepayform-patsatech')
						),
						'description' => __( 'Select Payment, Deferred or Authenticated.', 'woo-sagepayform-patsatech' ),
						'default' => 'PAYMENT',
	          'desc_tip'    => true
					),
					'cardtypes'	=> array(
						'title' => __( 'Accepted Cards', 'woo-sagepayform-patsatech' ),
	          'class' => 'wc-enhanced-select',
						'type' => 'multiselect',
						'description' => __( 'Select which card types to accept.', 'woo-sagepayform-patsatech' ),
						'default' => 'VISA',
						'options' => $this->card_type_options,
	          'desc_tip'    => true
					)
				);
			} // End init_form_fields()

	    /**
		 * There are no payment fields for sagepayform, but we want to show the description if set.
		 **/
	    function payment_fields() {
	    	if ($this->description) echo wpautop(wptexturize($this->description));
	    }


		/**
		 * Generate the nochex button link
		 **/
	  public function generate_sagepayform_form( $order_id ) {

			global $woocommerce;

			$order = new WC_Order( $order_id );

			if( $this->mode == 'test' ){
				$gateway_url = 'https://test.sagepay.com/gateway/service/vspform-register.vsp';
			}else if( $this->mode == 'live' ){
				$gateway_url = 'https://live.sagepay.com/gateway/service/vspform-register.vsp';
			}

			$basket = '';

			// Cart Contents
			$item_loop = 0;

			if ( sizeof( $order->get_items() ) > 0 ) {
				foreach ( $order->get_items() as $item ) {
					if ( $item['qty'] ) {

						$item_loop++;

						$product = $order->get_product_from_item( $item );

						$item_name 	= $item['name'];

						$item_meta = new WC_Order_Item_Meta( $item['item_meta'] );
						if ( $meta = $item_meta->display( true, true ) )
							$item_name .= ' ( ' . $meta . ' )';

						$item_cost = $order->get_item_subtotal( $item, false );
						$item_total_inc_tax = $order->get_item_subtotal( $item, true )*$item['qty'];
						$item_total = $order->get_item_subtotal( $item, false )*$item['qty'];
						//$item_sub_total =

						$item_tax = number_format( (float) ($item_total_inc_tax - $item_total)/$item['qty'], 2, '.', '' );

						if($item_loop > 1){
							$basket .= ':';
						}
						$sku = '';
						if ( $product->get_sku() ) {
							$sku = '['.$product->get_sku().']';
						}

						$basket .= str_replace(':',' = ',$sku).str_replace(':',' = ',$item_name).':'.$item['qty'].':'.$item_cost.':'.$item_tax.':'.number_format( $item_cost+$item_tax, 2, '.', '' ).':'.$item_total_inc_tax;
					}
				}
			}


			// Fees
			if ( sizeof( $order->get_fees() ) > 0 ) {
				foreach ( $order->get_fees() as $item ) {
					$item_loop++;

					$basket .= ':'.str_replace(':',' = ',$item['name']).':1:'.$item['line_total'].':---:'.$item['line_total'].':'.$item['line_total'];
				}
			}

			// Shipping Cost item - paypal only allows shipping per item, we want to send shipping for the order
			if ( $order->get_total_shipping() > 0 ) {
				$item_loop++;

				$ship_exc_tax = number_format( $order->get_total_shipping(), 2, '.', '' );

				$basket .= ':'.__( 'Shipping via', 'woo-sagepayform-patsatech' ) . ' ' . str_replace(':',' = ',ucwords( $order->get_shipping_method() )).':1:'.$ship_exc_tax.':'.$order->get_shipping_tax().':'.number_format( $ship_exc_tax+$order->get_shipping_tax(), 2, '.', '' ).':'.number_format( $order->get_total_shipping()+$order->get_shipping_tax(), 2, '.', '' );
			}

			// Discount
			if ( $order->get_total_discount() > 0 ){
				$item_loop++;

				$basket .= ':Discount:---:---:---:---:-'.$order->get_total_discount();
			}
				/*
			// Tax
			if ( $order->get_total_tax() > 0 ) {
				$item_loop++;

				$basket .= ':Tax:---:---:---:---:'.$order->get_total_tax();
			}*/

			$item_loop++;

			$basket .= ':Order Total:---:---:---:---:'.$order->get_total();

			$basket = $item_loop.':'.$basket;

      $time_stamp = date("ymdHis");
      $orderid = $this->vendor_name . "-" . $time_stamp . "-" . $order_id;

      $sagepay_arg['ReferrerID'] 			= 'CC923B06-40D5-4713-85C1-700D690550BF';
      $sagepay_arg['Amount'] 				= $order->get_total();
			$sagepay_arg['CustomerName']		= substr($order->get_billing_first_name().' '.$order->get_billing_last_name(), 0, 100);
      $sagepay_arg['CustomerEMail'] 		= substr($order->get_billing_email(), 0, 255);
      $sagepay_arg['BillingSurname'] 		= substr($order->get_billing_last_name(), 0, 20);
      $sagepay_arg['BillingFirstnames'] 	= substr($order->get_billing_first_name(), 0, 20);
      $sagepay_arg['BillingAddress1'] 	= substr($order->get_billing_address_1(), 0, 100);
      $sagepay_arg['BillingAddress2'] 	= substr($order->get_billing_address_2(), 0, 100);
      $sagepay_arg['BillingCity'] 		= substr($order->get_billing_city(), 0, 40);
			if( $order->get_billing_country() == 'US' ){
	        	$sagepay_arg['BillingState'] 	= $order->get_billing_state();
			}else{
	        	$sagepay_arg['BillingState'] 	= '';
			}
      $sagepay_arg['BillingPostCode'] 	= substr($order->get_billing_postcode(), 0, 10);
      $sagepay_arg['BillingCountry'] 		= $order->get_billing_country();
      $sagepay_arg['BillingPhone'] 		= substr($order->get_billing_phone(), 0, 20);

			if( $this->cart_has_virtual_product() == true || $this->send_shipping == 'yes'){

		    $sagepay_arg['DeliverySurname'] 	= $order->get_billing_last_name();
		    $sagepay_arg['DeliveryFirstnames'] 	= $order->get_billing_first_name();
		    $sagepay_arg['DeliveryAddress1'] 	= $order->get_billing_address_1();
		    $sagepay_arg['DeliveryAddress2'] 	= $order->get_billing_address_2();
		    $sagepay_arg['DeliveryCity'] 		= $order->get_billing_city();
				if( $order->get_billing_country() == 'US' ){
		        	$sagepay_arg['DeliveryState'] 	= $order->get_billing_state();
				}else{
		        	$sagepay_arg['DeliveryState'] 	= '';
				}
		    $sagepay_arg['DeliveryPostCode'] 	= $order->get_billing_postcode();
		    $sagepay_arg['DeliveryCountry'] 	= $order->get_billing_country();

			}else{

        $sagepay_arg['DeliverySurname'] 	= $order->get_shipping_last_name();
        $sagepay_arg['DeliveryFirstnames'] 	= $order->get_shipping_first_name();
        $sagepay_arg['DeliveryAddress1'] 	= $order->get_shipping_address_1();
        $sagepay_arg['DeliveryAddress2'] 	= $order->get_shipping_address_2();
        $sagepay_arg['DeliveryCity'] 		= $order->get_shipping_city();
				if( $order->get_shipping_country() == 'US' ){
		        	$sagepay_arg['DeliveryState'] 	= $order->get_shipping_state();
				}else{
		        	$sagepay_arg['DeliveryState'] 	= '';
				}
        $sagepay_arg['DeliveryPostCode'] 	= $order->get_shipping_postcode();
        $sagepay_arg['DeliveryCountry'] 	= $order->get_shipping_country();
			}

      $sagepay_arg['DeliveryPhone'] 		= substr($order->get_billing_phone(), 0, 20);
      $sagepay_arg['FailureURL'] 			= $this->notify_url;
      $sagepay_arg['SuccessURL'] 			= $this->notify_url;
      $sagepay_arg['Description'] 		= sprintf(__('Order #%s' , 'woo-sagepayform-patsatech'), ltrim( $order->get_order_number(), '#' ));
      $sagepay_arg['Currency'] 			= get_woocommerce_currency();
      $sagepay_arg['VendorTxCode'] 		= $orderid;
      $sagepay_arg['VendorEMail'] 		= $this->vendoremail;
      $sagepay_arg['SendEMail'] 			= $this->sendemails;
			if( $order->get_shipping_state == 'US' ){
	        	$sagepay_arg['eMailMessage']	= $this->emailmessage;
			}
      $sagepay_arg['Apply3DSecure'] 		= $this->apply3d;
      $sagepay_arg['Basket'] 				= $basket;

      $post_values = "";
      foreach( $sagepay_arg as $key => $value ) {
          $post_values .= "$key=" . trim( $value ) . "&";
      }
    	$post_values = substr($post_values, 0, -1);

			$params['VPSProtocol'] = "3.00";
			$params['TxType'] = $this->transtype;
			$params['Vendor'] = $this->vendor_name;
	    $params['Crypt'] = $this->encryptAndEncode($post_values);

			$sagepay_arg_array = array();

			foreach ($params as $key => $value) {
				$sagepay_arg_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}

			wc_enqueue_js('
				jQuery("body").block({
						message: "'.__('Thank you for your order. We are now redirecting you to SagePay Form to make payment.', 'woo-sagepayform-patsatech').'",
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						css: {
					        padding:        20,
					        textAlign:      "center",
					        color:          "#555",
					        border:         "3px solid #aaa",
					        backgroundColor:"#fff",
					        cursor:         "wait",
					        lineHeight:		"32px"
					    }
					});
				jQuery("#submit_sagepay_payment_form").click();
			');

			return  '<form action="'.esc_url( $gateway_url ).'" method="post" id="sagepay_payment_form">
					' . implode('', $sagepay_arg_array) . '
					<input type="submit" class="button" id="submit_sagepay_payment_form" value="'.__('Pay via SagePay', 'woo-sagepayform-patsatech').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'woo-sagepayform-patsatech').'</a>
				</form>';

		}

		/**
		 * Process the payment and return the result
		 **/
		function process_payment( $order_id ) {

			$order = new WC_Order( $order_id );

			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);

		}

		/**
		 * receipt_page
		 **/
		function receipt_page( $order ) {

			echo '<p>'.__('Thank you for your order, please click the button below to pay with SagePay Form.', 'woo-sagepayform-patsatech').'</p>';

			echo $this->generate_sagepayform_form( $order );

		}


		/**
		 * Successful Payment!
		 **/
		function successful_request() {
			global $woocommerce;

			if ( isset($_REQUEST['crypt']) && !empty($_REQUEST['crypt']) ) {

				$transaction_response = $this->decode(str_replace(' ', '+',$_REQUEST['crypt']));

				$order_id = explode('-',$transaction_response['VendorTxCode']);

				if ( $transaction_response['Status'] == 'OK' || $transaction_response['Status'] == 'AUTHENTICATED'|| $transaction_response['Status'] == 'REGISTERED' ) {

					$order = new WC_Order( $order_id[2] );

					$order->add_order_note(sprintf(__('SagePay Form Payment Completed. The Reference Number is %s.', 'woo-sagepayform-patsatech'), $transaction_response['VPSTxId']));

					$order->payment_complete();

					wp_redirect( $this->get_return_url( $order ) ); exit;

				}else{

					wc_add_notice( sprintf(__('Transaction Failed. The Error Message was %s', 'woo-sagepayform-patsatech'), $transaction_response['StatusDetail'] ), $notice_type = 'error' );

					wp_redirect( get_permalink(get_option( 'woocommerce_checkout_page_id' )) ); exit;

				}
			}
		}

		/**
		* Check if the cart contains virtual product
		*
		* @return bool
		*/
		private function cart_has_virtual_product() {
			global $woocommerce;

			$has_virtual_products = false;

			$virtual_products = 0;

			$products = $woocommerce->cart->get_cart();

			foreach( $products as $product ) {

				$product_id = $product['product_id'];
				$is_virtual = get_post_meta( $product_id, '_virtual', true );
				// Update $has_virtual_product if product is virtual
				if( $is_virtual == 'yes' )
				$virtual_products += 1;
			}
			if( count($products) == $virtual_products ){
				$has_virtual_products = true;
			}

			return $has_virtual_products;

		}

		private function encryptAndEncode($strIn) {
			$strIn = $this->pkcs5_pad($strIn, 16);
			//return "@".bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->vendor_pass, $strIn, MCRYPT_MODE_CBC, $this->vendor_pass));
			return "@".bin2hex(openssl_encrypt($strIn, 'AES-128-CBC', $this->vendor_pass, OPENSSL_RAW_DATA, $this->vendor_pass));
		}

		private function decodeAndDecrypt($strIn) {
			$strIn = substr($strIn, 1);
			$strIn = pack('H*', $strIn);
			//return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->vendor_pass, $strIn, MCRYPT_MODE_CBC, $this->vendor_pass);
			return openssl_decrypt($strIn, 'AES-128-CBC', $this->vendor_pass, OPENSSL_RAW_DATA, $this->vendor_pass);
		}


		private function pkcs5_pad($text, $blocksize)	{
			$pad = $blocksize - (strlen($text) % $blocksize);
			return $text . str_repeat(chr($pad), $pad);
		}

		public function decode($strIn) {
			$decodedString = $this->decodeAndDecrypt($strIn);
			parse_str($decodedString, $sagePayResponse);
			return $sagePayResponse;
		}

		private function force_ssl($url){

			if ( 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) ) {
				$url = str_replace( 'http:', 'https:', $url );
			}

			return $url;
		}

	}

	/**
	 * Add the gateway to WooCommerce
	 **/
	function add_sagepayform_gateway( $methods ) {
		$methods[] = 'woocommerce_sagepayform'; return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_sagepayform_gateway' );

}
