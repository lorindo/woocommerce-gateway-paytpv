<?php
/*
Plugin Name: Pasarela de pago para PayTpv
Plugin URI: http://modulosdepago.es/
Description: La pasarela de pago PayTpv para WooCommerce
Version: 1.0.0
Author: Mikel Martin
Author URI: http://PayTpv.com/

	Copyright: © 2009-2013 PayTpv Online.
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html

*/

add_action('plugins_loaded', 'woocommerce_paytpv_init', 100);

class WC_PayTpv_Dependencies {
	
	private static $active_plugins;
	
	static function init() {
		
		self::$active_plugins = (array) get_option( 'active_plugins', array() );
		
		if ( is_multisite() )
			self::$active_plugins = array_merge( self::$active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		
	}
	
	static function woocommerce_active_check() {
		
		if ( ! self::$active_plugins ) self::init();
		
		return in_array('woocommerce/woocommerce.php', self::$active_plugins) || array_key_exists('woocommerce/woocommerce.php', self::$active_plugins);
		
	}
	
}

function woocommerce_paytpv_init() {
	/**
	* Required functions
	*/
	if ( ! function_exists( 'is_woocommerce_active' ) ) require_once( 'zhenit-includes/zhenit-functions.php' );

	if ( ! class_exists( 'WC_Payment_Gateway' ) || ! WC_PayTpv_Dependencies::woocommerce_active_check() )
		return;

	load_plugin_textdomain( 'wc_paytpv', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	/**
	 * Pasarela PayTpv Gateway Class
	 * */
	class woocommerce_paytpv extends WC_Payment_Gateway {


		public function __construct() {
			global $woocommerce;

			$this->id = 'paytpv';
			$this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/cards.png';
			$this->has_fields = false;
			$this->supports = array(
				'products',
				'subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes'
				);
			
			$this->iframeurl = 'https://www.paytpv.com/gateway/ifgateway.php';
			$this->url		 = 'https://www.paytpv.com/gateway/fsgateway.php';

			// Load the form fields
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Get setting values
			$this->enabled = $this->settings['enabled'];
			$this->title = $this->settings['title'];
			$this->description = $this->settings['description'];
			$this->usercode = $this->settings['usercode'];
			$this->clientcode = $this->settings['clientcode'];			
			$this->term = $this->settings['term'];
			$this->currency = get_woocommerce_currency();
			$this->pass = $this->settings['pass'];
			$this->iframe = $this->settings['iframe'];

			// Hooks
			add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_api_woocommerce_' . $this->id, array( $this, 'check_' . $this->id . '_resquest' ) );
			add_action('scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ),10,3 );
		}

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		function init_form_fields() {

			$this->form_fields = array(
				'enabled' => array(
					'title' => __('Enable/Disable', 'woothemes'),
					'label' => __('Habilitar pasarela PayTpv', 'wc_paytpv'),
					'type' => 'checkbox',
					'description' => '',
					'default' => 'no'
				),
				'title' => array(
					'title' => __('Title', 'woothemes'),
					'type' => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'woothemes'),
					'default' => __('Tarjeta de crédito o débito (vía PayTpv)', 'wc_paytpv')
				),
				'description' => array(
					'title' => __('Description', 'woothemes'),
					'type' => 'textarea',
					'description' => __('This controls the description which the user sees during checkout.', 'woothemes'),
					'default' => 'Pague con trajeta de crédito de forma segura a través de la pasarela de PayTpv.'
				),
				'usercode' => array(
					'title' => __('Nombre de usuario', 'wc_paytpv'),
					'type' => 'text',
					'description' =>'',
					'default' => ''
				),
				'clientcode' => array(
					'title' => __('Código de cliente', 'wc_paytpv'),
					'type' => 'text',
					'description' => '',
					'default' => ''
				),
				'term' => array(
					'title' => __('Número de terminal', 'wc_paytpv'),
					'type' => 'text',
					'description' => __('Número de terminal proporcionado por PayTpv.', 'wc_paytpv'),
					'default' => ''
				),
				'pass' => array(
					'title' => __('Contraseña', 'wc_paytpv'),
					'type' => 'text',
					'description' => __('Contraseña proporcionada por PayTpv.', 'wc_paytpv'),
					'default' => ''
				),
				'iframe' => array(
					'title' => __('Formulario de pago integrado en un iframe', 'wc_paytpv'),
					'label' => __('', 'wc_paytpv'),
					'type' => 'checkbox',
					'default' => 'yes'
				),
			);
		}

		/**
		 * There are no payment fields for PayTpv, but we want to show the description if set.
		 * */
		function payment_fields() {
			if ($this->description)
				echo wpautop(wptexturize($this->description));
		}

		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 * */
		public function admin_options() {
			?>
			<h3><?php _e('Pasarela PayTpv', 'wc_paytpv'); ?></h3>
			<p>
				La pasarela <a href="https://PayTpv.com">PayTpv Online</a> para Woocommerce le permitirá dar la opción de pago por tarjeta de crédito o débito en su comercio. Para ello necesitará un tpv virtual de PayTpv. Conviene que disponga también de acceso al <a href="https://www.paytpv.com/clientes.php">Área de clientes</a>
			</p>
			<p>
				Allí deberá configurar "Tipo de notificación del cobro:" como "Notificación por URL" y configurar ahí la URL: <?php echo add_query_arg('tpvLstr','notify',add_query_arg( 'wc-api', 'woocommerce_'. $this->id, home_url( '/' ) ) );?></p>
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
			if (!isset($_GET['tpvLstr']))
				return;
			if ($_GET['tpvLstr'] == 'notify'){
				if(isset($_REQUEST['i']))
					$importe  = number_format($_REQUEST['i'] / 100, 2);
				if(isset($_REQUEST['r']))
					$ref = $_REQUEST['r'];
				$result = 'N/A';
				if(isset($_REQUEST['ret']))
					$result = $_REQUEST['ret'];
				if(isset($_REQUEST['h']))
					$sign = $_REQUEST['h'];
				
				// Cálculo firma
				if($sign != md5($this->usercode.$ref.$this->pass.$result))
					die(1);

				$order = new WC_Order((int) substr($ref,0,8));
				// Check order not already completed
				if ($order->status == 'completed'){
						if ($this->debug=='yes') $this->log->add( 'paytpv', 'Aborting, Order #' . $posted['custom'] . ' is already complete.' );
						die(1);
				}

				if ( $result == '0'){//pago OK
					update_post_meta( (int) $order->id, 'Resultado', $result);
					update_post_meta( (int) $order->id, 'Log de operación', print_r($_REQUEST,true));
					// Payment completed
					$order->add_order_note( __('PayTpv payment completed', 'woocommerce') );
					$order->payment_complete();

					if ($this->debug=='yes') $this->log->add( 'paytpv', 'Payment complete.' );
					$url = $this->get_return_url( $order );
					wp_redirect($url, 303);
					exit();
				}
			}
		}
		/**
		 * Get PayTpv language code
		 * */
		 /*@TODO: Implementar los idiomas*/
		function _getLanguange() {
			$lng = substr(get_bloginfo('language'),0,2);
			if(function_exists('qtrans_getLanguage')) $lng = qtrans_getLanguage();
			if(defined('ICL_LANGUAGE_CODE'))  $lng = ICL_LANGUAGE_CODE;
			switch ($lng){
				case 'en':
					return '002';
				case 'ca':
					return '003';
				case 'fr':
					return '004';
				case 'de':
					return '005';
				case 'dk':
					return '006';
				case 'it':
					return '007';
				case 'sk':
					return '008';
				case 'pt':
					return '009';
				case 'va':
					return '010';
				case 'po':
					return '011';
				case 'gl':
					return '012';
				case 'eu':
					return '013';
				default:
					return '001';
			}
			return '001';
		}

		/**
		 * Get PayTpv Args for passing to PP
		 * */
		function get_paytpv_args($order) {
			global $woocommerce;

			$OPERATION	= '1';
			$URLOK		= add_query_arg('tpvLstr','notify',add_query_arg( 'wc-api', 'woocommerce_'. $this->id, home_url( '/' ) ) );
			/*@TODO: Mejor usar la url de notificación pero parece que no admite parámetros*/
			//$URLOK		= $this->get_return_url( $order );
			$URLKO		= $order->get_cancel_order_url();
			$REFERENCE	= str_pad($order->id, 8, "0", STR_PAD_LEFT) . date('is');
			$AMOUNT		= round($order->get_order_total()*100);
			
			$paytpv_req_args = array();/*@todo: */
			
			$mensaje = $this->clientcode . $this->usercode . $this->term . $OPERATION . $REFERENCE. $AMOUNT.$this->currency;
			$SIGNATURE = md5($mensaje.md5($this->pass));

			$paytpv_args = array(
				"ACCOUNT"	=> $this->clientcode, 
				"USERCODE"	=> $this->usercode, 
				"TERMINAL"	=> $this->term, 
				"OPERATION" => $OPERATION, 
				"REFERENCE" => $REFERENCE,
				"AMOUNT"	=> $AMOUNT,
				"CURRENCY"	=> $this->currency, 
				"SIGNATURE" => $SIGNATURE, 
				"CONCEPT"	=> $CONCEPT,
				'URLOK'		=> $URLOK,
				'URLKO'		=> $URLKO
			);
			//$paytpv_args = apply_filters( 'woocommerce_paytpv_args', $paytpv_args );

			return array_merge($paytpv_args,$paytpv_req_args);
		}

		/**
		 * Generate the paytpv button link
		 * */
		function generate_paytpv_form($order_id) {
			global $woocommerce;

			$order = new WC_Order($order_id);
			$paytpv_args = $this->get_paytpv_args($order);
			
			if ($this->iframe == 'no'):
				$paytpv_adr = $this->url;

				$paytpv_args_array = array();

				foreach ($paytpv_args as $key => $value) {
					$paytpv_args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
				}

				$woocommerce->add_inline_js('
				jQuery("body").block({
						message: "<img src=\"' . esc_url($woocommerce->plugin_url()) . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />' . __('Thank you for your order. We are now redirecting you to PayTpv to make payment.', 'woocommerce') . '",
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
			');

				return '<form action="' . esc_url($paytpv_adr) . '" method="post" id="paytpv_payment_form" target="_top">
					' . implode('', $paytpv_args_array) . '
					<input type="submit" class="button-alt" id="submit_paytpv_payment_form" value="' . __('Pagar con trajeta de crédito', 'woocommerce') . '" /> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">' . __('Cancel order &amp; restore cart', 'woocommerce') . '</a>
				</form>';
			else:
				return '<iframe src="https://www.paytpv.com/gateway/ifgateway.php?'.http_build_query($paytpv_args).'"
	name="paytpv" style="width: 670px; border-top-width: 0px; border-right-width: 0px; border-bottom-width: 0px; border-left-width: 0px; border-style: initial; border-color: initial; border-image: initial; height: 322px; " marginheight="0" marginwidth="0" scrolling="no"></iframe>';
			endif;

		}

		function process_payment( $order_id ) {

			$order = new WC_Order( $order_id );
			return array(
				'result'	=> 'success',
				'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
			);
		}
		
		/**
		 * receipt_page
		 * */
		function receipt_page($order) {
			echo '<p>' . __('Gracias por el pedido, por favor rellene los siguientes datos para completar el pago.', 'paytpv') . '</p>';
			echo $this->generate_paytpv_form($order);
		}
	}

	/**
	 * Add the gateway to woocommerce
	 * */
	function add_paytpv_gateway($methods) {
		$methods[] = 'woocommerce_paytpv';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_paytpv_gateway');
}