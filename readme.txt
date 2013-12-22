=== Pasarela de pago para PayTpv ===
Contributors: m1k3lm
Tags: woocommerce, payment gateway
Requires at least: 3.0.1
Tested up to: 3.8
Stable tag: 2.0.8
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Pasarela de pago PayTpv para WooCommerce. Permite pago por tarjeta de crédito.

== Description ==

This is a payment gateway for WooCommerce to accept credit card payments using merchant accounts from https://paytpv.com

Es un módulo de pago para WooCommerce que permite el pago de los pedidos mediante tarjeta de crédito usando el servicio de tpv virtual de https://paytpv.com
En la nueva versión 2.0.1-R se ha añadido la operativa de pagos recurrentes compatible con woocommerce-suscriptions y la tokenización de tarjetas

== Installation ==

1. Upload `woocommerce-gateway-paytpv` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to WooCommerce -> Setting -> Payment Gateways -> Paytpv link and configure the data from your https://paytpv.com account.

== Frequently Asked Questions ==


== Screenshots ==

1. configuration screen

== Changelog ==
= 2.0.8 =
* Compatibildad con tpvs en modo seguro para la operativa BankStore

= 2.0.7 =
* Añadir custom field en los pedidos sucesivos con la referencia del pago en paytpv.com

= 2.0.3 =
* Corrección de errores en el empaquetado para wordpress.org

= 2.0.1-R =
* Recurring payment compatible with woocommerce-suscriptions
* Credit Card tokenization, once a payment is done no need to fill credit card data again.
* i18n
= 1.0 =
* First version. Includes iframe mode