<?php
/*
  Plugin Name: Pasarela de pago para PayTpv
  Plugin URI: http://modulosdepago.es/
  Description: La pasarela de pago PayTpv para WooCommerce
  Version: 2.0.2
  Author: Mikel Martin
  Author URI: http://PayTpv.com/

  Copyright: © 2009-2013 PayTpv Online.
  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html

 */

add_action( 'plugins_loaded', 'woocommerce_paytpv_init', 100 );

class WC_PayTpv_Dependencies {

	private static $active_plugins;

	static function init() {

		self::$active_plugins = ( array ) get_option( 'active_plugins', array( ) );

		if ( is_multisite() )
			self::$active_plugins = array_merge( self::$active_plugins, get_site_option( 'active_sitewide_plugins', array( ) ) );
	}

	static function woocommerce_active_check() {

		if ( !self::$active_plugins )
			self::init();

		return in_array( 'woocommerce/woocommerce.php', self::$active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', self::$active_plugins );
	}

}

function woocommerce_paytpv_init() {

	/**
	 * Required functions
	 */
	if ( !class_exists( 'WC_Payment_Gateway' ) || !WC_PayTpv_Dependencies::woocommerce_active_check() )
		return;

	load_plugin_textdomain( 'wc_paytpv', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	/**
	 * Pasarela PayTpv Gateway Class
	 * */
	class woocommerce_paytpv extends WC_Payment_Gateway {

		private $ws_client;

		public function __construct() {
			$this->id = 'paytpv';
			$this->icon = WP_PLUGIN_URL . "/" . plugin_basename( dirname( __FILE__ ) ) . '/images/cards.png';
			$this->has_fields = false;
			$this->supports = array(
				'products',
			);
			// Load the form fields
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();
			$this->product = $this->settings[ 'product' ];
			$this->iframeurl = 'https://www.paytpv.com/gateway/ifgateway.php';
			$this->url = 'https://www.paytpv.com/gateway/fsgateway.php';
			if ( $this->product == '1' ) {//Si el producto es de tipo bank store permitimos pagos recurrentes
				$this->supports = array_merge( $this->supports, array( 'subscriptions',
					'subscription_cancellation',
					'subscription_suspension',
					'subscription_reactivation',
					'subscription_amount_changes',
					'subscription_date_changes' ) );
				$this->iframeurl = 'https://secure.paytpv.com/gateway/bnkgateway.php';
			}

			// Get setting values
			$this->enabled = $this->settings[ 'enabled' ];
			$this->title = $this->settings[ 'title' ];
			$this->description = $this->settings[ 'description' ];
			$this->usercode = $this->settings[ 'usercode' ];
			$this->clientcode = $this->settings[ 'clientcode' ];
			$this->term = $this->settings[ 'term' ];
			$this->currency = get_woocommerce_currency();
			$this->pass = $this->settings[ 'pass' ];
			$this->iframe = $this->settings[ 'iframe' ];

			// Hooks
			add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_woocommerce_' . $this->id, array( $this, 'check_' . $this->id . '_resquest' ) );
			add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 3 );
		}

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		function init_form_fields() {

			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'wc_paytpv' ),
					'label' => __( 'Enable PayTpv gateway', 'wc_paytpv' ),
					'type' => 'checkbox',
					'description' => '',
					'default' => 'no'
				),
				'title' => array(
					'title' => __( 'Title', 'wc_paytpv' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'wc_paytpv' ),
					'default' => __( 'Credit Card (by PayTpv)', 'wc_paytpv' )
				),
				'description' => array(
					'title' => __( 'Description', 'wc_paytpv' ),
					'type' => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'wc_paytpv' ),
					'default' => __( 'Pay using your credit card in a secure way', 'wc_paytpv' ),
				),
				'product' => array(
					'title' => __( 'Product type', 'wc_paytpv' ),
					'type' => 'select',
					'description' => __( 'Type of paytpv.com product being used.', 'wc_paytpv' ),
					'options' => array(
						0 => 'TPV WEB',
						1 => 'XML SOAP RECURRENTE'
					)
				),
				'usercode' => array(
					'title' => __( 'User name', 'wc_paytpv' ),
					'type' => 'text',
					'description' => '',
					'default' => ''
				),
				'clientcode' => array(
					'title' => __( 'Client code', 'wc_paytpv' ),
					'type' => 'text',
					'description' => '',
					'default' => ''
				),
				'term' => array(
					'title' => __( 'Terminal', 'wc_paytpv' ),
					'type' => 'text',
					'description' => __( 'Terminal number in PayTpv.', 'wc_paytpv' ),
					'default' => ''
				),
				'pass' => array(
					'title' => __( 'Password', 'wc_paytpv' ),
					'type' => 'text',
					'description' => __( 'Password for PayTpv product.', 'wc_paytpv' ),
					'default' => ''
				),
				'iframe' => array(
					'title' => __( 'Onsite form in embended iframe', 'wc_paytpv' ),
					'label' => __( '', 'wc_paytpv' ),
					'type' => 'checkbox',
					'default' => 'yes'
				),
			);
		}

		/**
		 * There are no payment fields for PayTpv, but we want to show the description if set.
		 * */
		function payment_fields() {
			if ( $this->description )
				echo wpautop( wptexturize( $this->description ) );
		}

		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 * */
		public function admin_options() {
			?>
			<h3><?php _e( 'PayTpv Payment Gateway', 'wc_paytpv' ); ?></h3>
			<p>
				<?php _e('<a href="https://PayTpv.com">PayTpv Online</a> payment gateway for Woocommerce enables credit card payment in your shop. Al you need is a PayTpv.com merchant account and access to <a href="https://www.paytpv.com/clientes.php">customer area</a>');?>
			</p>
			<p>
				<?php _e('There you should configure "Tipo de notificación del cobro:" as "Notificación por URL" set ther teh following URL:');?> <?php echo add_query_arg( 'tpvLstr', 'notify', add_query_arg( 'wc-api', 'woocommerce_' . $this->id, home_url( '/' ) ) ); ?></p>
			</p>
			<table class="form-table">
			<?php $this->generate_settings_html(); ?>
			</table><!--/.form-table-->
			<?php
		}

		/**
		 * Check for PayTpv IPN Response
		 * */
		function check_paytpv_resquest() {
			if ( !isset( $_GET[ 'tpvLstr' ] ) )
				return;
			if ( $this->product == 0 ) {//Notificación TPV-WEB
				if ( $_GET[ 'tpvLstr' ] == 'notify' ) {
					if ( isset( $_REQUEST[ 'i' ] ) )
						$importe = number_format( $_REQUEST[ 'i' ] / 100, 2 );
					if ( isset( $_REQUEST[ 'r' ] ) )
						$ref = $_REQUEST[ 'r' ];
					$result = 'N/A';
					if ( isset( $_REQUEST[ 'ret' ] ) )
						$result = $_REQUEST[ 'ret' ];
					if ( isset( $_REQUEST[ 'h' ] ) )
						$sign = $_REQUEST[ 'h' ];

					// Cálculo firma
					if ( $sign != md5( $this->usercode . $ref . $this->pass . $result ) )
						die( 1 );

					$order = new WC_Order( ( int ) substr( $ref, 0, 8 ) );
					// Check order not already completed
					if ( $order->status == 'completed' ) {
						if ( $this->debug == 'yes' )
							$this->log->add( 'paytpv', 'Aborting, Order #' . $posted[ 'custom' ] . ' is already complete.' );
						die( 1 );
					}

					if ( $result == '0' ) {//pago OK
						update_post_meta( ( int ) $order->id, 'Resultado', $result );
						update_post_meta( ( int ) $order->id, 'Log de operación', print_r( $_REQUEST, true ) );
						// Payment completed
						$order->add_order_note( __( 'PayTpv payment completed', 'woocommerce' ) );
						$order->payment_complete();

						if ( $this->debug == 'yes' )
							$this->log->add( 'paytpv', 'Payment complete.' );
						$url = $this->get_return_url( $order );
						wp_redirect( $url, 303 );
						exit();
					}
				}
			}else {//Notificación BANKSTORE
				$ref = $_REQUEST[ 'Order' ];
				$order = new WC_Order( ( int ) substr( $ref, 0, 8 ) );
				if ( $_GET[ 'tpvLstr' ] == 'pay' && $order->status != 'completed' ) { //PAGO CON TARJETA GUARDADA
					update_post_meta( ( int ) $order->id, 'IdUser', get_user_meta( ( int ) $order->user_id, 'IdUser', true ) );
					update_post_meta( ( int ) $order->id, 'TokenUser', get_user_meta( ( int ) $order->user_id, 'TokenUser', true ) );

					$client = $this->get_client();
					$result = $client->execute_purchase( $order, $order->get_order_total() );
					$url = $order->get_cancel_order_url();
					if ( ( int ) $result[ 'DS_RESPONSE' ] == 1 ) {
						$order->add_order_note( __( 'PayTpv payment completed', 'wc_paytpv' ) );
						$order->payment_complete();
						$url = $this->get_return_url( $order );
					}
					wp_redirect( $url, 303 );
					exit();
				}
				if ( $_GET[ 'tpvLstr' ] == 'notify' ) {//NOTIFICACIÓN
					$AMOUNT = round( $order->get_order_total() * 100 );
					$CURRENCY = get_woocommerce_currency();
					$mensaje =	$this->clientcode .
								$this->term .
								$_REQUEST[ 'TransactionType' ] .
								$_REQUEST[ 'Order' ] .
								$AMOUNT .
								$CURRENCY;
					$SIGNATURE = md5( $mensaje . md5( $this->pass ) . $_REQUEST[ 'BankDateTime' ] . $_REQUEST[ 'Response' ] );
					if ( $_REQUEST[ 'TransactionType' ] == '1' && $_REQUEST[ 'Response' ] == 'OK' && $_REQUEST['ExtendedSignature']==$SIGNATURE  ) {
						update_post_meta( ( int ) $order->id, 'IdUser', $_REQUEST[ 'IdUser' ] );
						update_post_meta( ( int ) $order->id, 'TokenUser', $_REQUEST[ 'TokenUser' ] );
						update_user_meta( ( int ) $order->user_id, 'IdUser', $_REQUEST[ 'IdUser' ] );
						update_user_meta( ( int ) $order->user_id, 'TokenUser', $_REQUEST[ 'TokenUser' ] );
						$client = $this->get_client();
						$result = $client->info_user( $_REQUEST[ 'IdUser' ], $_REQUEST[ 'TokenUser' ], get_post_meta( ( int ) $order->id, '_customer_ip_address', true ) );
						update_user_meta( ( int ) $order->user_id, 'PAN', $result[ 'DS_MERCHANT_PAN' ] );

						$order->add_order_note( __( 'PayTpv payment completed', 'woocommerce' ) );
						$order->payment_complete();
					}
				}
				if ( $_GET[ 'tpvLstr' ] == 'ok' && $order->status == 'completed' ) {
					echo '<script>window.top.location.href="' . $this->get_return_url( $order ) . '"</script>';
					exit;
				}
				if ( $_GET[ 'tpvLstr' ] == 'ko' || $order->status != 'completed' ) {
					echo $_GET[ 'tpvLstr' ] . '<script>window.top.location.href="' . $order->get_cancel_order_url() . '"</script>';
					exit;
				}
			}
		}

		/**
		 * Get PayTpv language code
		 * */
		function _getLanguange() {
			$lng = substr( get_bloginfo( 'language' ), 0, 2 );
			if ( function_exists( 'qtrans_getLanguage' ) )
				$lng = qtrans_getLanguage();
			if ( defined( 'ICL_LANGUAGE_CODE' ) )
				$lng = ICL_LANGUAGE_CODE;
			switch ( $lng ) {
				case 'en':
					return 'EN';
				case 'fr':
					return 'FR';
				case 'de':
					return 'DE';
				case 'it':
					return 'IT';
				default:
					return 'ES';
			}
			return 'ES';
		}

		/**
		 * Get PayTpv Args for passing to PP
		 * */
		function get_paytpv_args( $order ) {
			$paytpv_req_args = array( );
			$paytpv_args = array( );
			if ( $this->product == '1' ) {
				$paytpv_args = $this->get_paytpv_bankstore_args( $order );
			} else {
				$paytpv_args = $this->get_paytpv_tpvweb_args( $order );
			}
			return array_merge( $paytpv_args, $paytpv_req_args );
		}

		function get_paytpv_bankstore_args( $order ) {
			$OPERATION = '1';
			//$URLOK		= add_query_arg('tpvLstr','notify',add_query_arg( 'wc-api', 'woocommerce_'. $this->id, home_url( '/' ) ) );
			$MERCHANT_ORDER = str_pad( $order->id, 8, "0", STR_PAD_LEFT ) . date( 'is' );
			$MERCHANT_AMOUNT = round( $order->get_order_total() * 100 );
			$MERCHANT_CURRENCY = get_woocommerce_currency();
			$URLOK = $this->get_return_url( $order );
			$URLKO = $order->get_cancel_order_url();
			$paytpv_req_args = array( );
			$mensaje = $this->clientcode . $this->term . $OPERATION . $MERCHANT_ORDER . $MERCHANT_AMOUNT . $MERCHANT_CURRENCY;
			$MERCHANT_MERCHANTSIGNATURE = md5( $mensaje . md5( $this->pass ) );

			$paytpv_args = array(
				'MERCHANT_MERCHANTCODE' => $this->clientcode,
				'MERCHANT_TERMINAL' => $this->term,
				'OPERATION' => $OPERATION,
				'LANGUAGE' => $this->_getLanguange(),
				'MERCHANT_MERCHANTSIGNATURE' => $MERCHANT_MERCHANTSIGNATURE,
				'MERCHANT_ORDER' => $MERCHANT_ORDER,
				'MERCHANT_AMOUNT' => $MERCHANT_AMOUNT,
				'MERCHANT_CURRENCY' => $MERCHANT_CURRENCY,
				'URLOK' => $URLOK,
				'URLKO' => $URLKO
			);

			return array_merge( $paytpv_args, $paytpv_req_args );
		}

		function get_paytpv_tpvweb_args( $order ) {
			$OPERATION = '1';
			//$URLOK		= add_query_arg('tpvLstr','notify',add_query_arg( 'wc-api', 'woocommerce_'. $this->id, home_url( '/' ) ) );
			$URLOK = $this->get_return_url( $order );
			$URLKO = $order->get_cancel_order_url();
			$REFERENCE = str_pad( $order->id, 8, "0", STR_PAD_LEFT ) . date( 'is' );
			$AMOUNT = round( $order->get_order_total() * 100 );
			$CONCEPT = ''; //@todo set concept
			$paytpv_req_args = array( );
			$mensaje = $this->clientcode . $this->usercode . $this->term . $OPERATION . $REFERENCE . $AMOUNT . $this->currency;
			$SIGNATURE = md5( $mensaje . md5( $this->pass ) );

			$paytpv_args = array(
				'ACCOUNT' => $this->clientcode,
				'USERCODE' => $this->usercode,
				'TERMINAL' => $this->term,
				'OPERATION' => $OPERATION,
				'REFERENCE' => $REFERENCE,
				'AMOUNT' => $AMOUNT,
				'CURRENCY' => $this->currency,
				'SIGNATURE' => $SIGNATURE,
				'CONCEPT' => $CONCEPT,
				'URLOK' => $URLOK,
				'URLKO' => $URLKO,
				'LANGUAGE' => $this->_getLanguange()
			);
			//$paytpv_args = apply_filters( 'woocommerce_paytpv_args', $paytpv_args );

			return array_merge( $paytpv_args, $paytpv_req_args );
		}

		/**
		 * Generate the paytpv button link
		 * */
		function generate_paytpv_form( $order_id ) {
			global $woocommerce;

			$order = new WC_Order( $order_id );
			$paytpv_args = $this->get_paytpv_args( $order );
			$is_recurring = class_exists( 'WC_Subscriptions_Order' ) && WC_Subscriptions_Order::order_contains_subscription( $order->id ) ;
			if ( $this->iframe == 'no' && !$is_recurring):
				$paytpv_adr = $this->url;

				$paytpv_args_array = array( );

				foreach ( $paytpv_args as $key => $value ) {
					$paytpv_args_array[ ] = '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
				}

				$woocommerce->add_inline_js( '
				jQuery("body").block({
						message: "<img src=\"' . esc_url( $woocommerce->plugin_url() ) . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />' . __( 'Thank you for your order. We are now redirecting you to PayTpv to make payment.', 'woocommerce' ) . '",
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						css: {
							padding:		20,
							textAlign:	  "center",
							color:		  "#555",
							border:		 "3px solid #aaa",
							backgroundColor:"#fff",
							cursor:		 "wait",
							lineHeight:		"32px"
						}
					});
				jQuery("#submit_paytpv_payment_form").click();
			' );

				return '<form action="' . esc_url( $paytpv_adr ) . '" method="post" id="paytpv_payment_form" target="_top">
					' . implode( '', $paytpv_args_array ) . '
					<input type="submit" class="button-alt" id="submit_paytpv_payment_form" value="' . __( 'Credit Card payment', 'wc_paytpv' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce' ) . '</a>
				</form>';
			else:
				$html = '';
				$PAN = get_user_meta( ( int ) $order->user_id, 'PAN', true );
				if ( $PAN != '' ) {
					$url_pay = add_query_arg( array(
						'Order' => str_pad( $order->id, 8, "0", STR_PAD_LEFT ) . date( 'is' ),
						'tpvLstr' => 'pay',
						'wc-api' => 'woocommerce_' . $this->id ), home_url( '/' ) );
					$html .= '<div id="card_reuse">' . sprintf( __( 'Use the stored %s credit card to <a href="%s" class="button">pay</a>', 'wc_paytpv' ), $PAN, $url_pay ) . '</div>';
					$html .= __( 'Or use a different credit card instead', 'wc_paytpv' );
				}
				$html .= '<iframe src="' . $this->iframeurl . '?' . http_build_query( $paytpv_args ) . '"
	name="paytpv" style="width: 670px; border-top-width: 0px; border-right-width: 0px; border-bottom-width: 0px; border-left-width: 0px; border-style: initial; border-color: initial; border-image: initial; height: 322px; " marginheight="0" marginwidth="0" scrolling="no"></iframe>';
				return $html;
			endif;
		}

		function process_payment( $order_id ) {

			$order = new WC_Order( $order_id );
			return array(
				'result' => 'success',
				'redirect' => add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink( woocommerce_get_page_id( 'pay' ) ) ) )
			);
		}

		/**
		 * Operaciones sucesivas
		 * */
		function scheduled_subscription_payment( $amount_to_charge, $order, $product_id ) {
			$client = $this->get_client();
			$result = $client->execute_purchase( $order, $amount_to_charge );
			if ( ( int ) $result[ 'DS_RESPONSE' ] == 1 ) {
				WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
			}
		}

		/**
		 * receipt_page
		 * */
		function receipt_page( $order ) {
			echo '<p>' . __( 'Thanks for your order, please fill the data below to process the payment.', 'wc_paytpv' ) . '</p>';
			echo $this->generate_paytpv_form( $order );
		}

		function get_client() {
			if ( !isset( $this->ws_client ) ) {
				require_once(dirname( __FILE__ ) . '/ws_client.php');
				$this->ws_client = new WS_Client( $this->settings );
			}
			return $this->ws_client;
		}

	}

	/**
	 * Add the gateway to woocommerce
	 * */
	function add_paytpv_gateway( $methods ) {
		$methods[ ] = 'woocommerce_paytpv';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'add_paytpv_gateway' );
}
