<?php

if ( !class_exists( 'nusoap_client' ) ) {
	require_once(dirname( __FILE__ ) . '/lib/nusoap.php');
}

class WS_Client {

	var $client = null;
	var $config = null;

	public function __construct( array $config = array( ), $proxyhost = '', $proxyport = '', $proxyusername = '', $proxypassword = '' ) {
		$useCURL = isset( $_POST[ 'usecurl' ] ) ? $_POST[ 'usecurl' ] : '0';
		$this->config = $config;
		$this->client = new nusoap_client( 'https://secure.paytpv.com/gateway/xml_bankstore.php', false,
						$proxyhost, $proxyport, $proxyusername, $proxypassword );
		$err = $this->client->getError();
		if ( $err ) {
			echo '<h2>Constructor error</h2><pre>' . $err . '</pre>';
			echo '<h2>Debug</h2><pre>' . htmlspecialchars( $client->getDebug(), ENT_QUOTES ) . '</pre>';
			exit();
		}
		$this->client->setUseCurl( $useCURL );
	}

	function execute_purchase( $order, $amount ) {
		$DS_MERCHANT_MERCHANTCODE = $this->config[ 'clientcode' ];
		$DS_MERCHANT_TERMINAL = $this->config[ 'term' ];
		$DS_IDUSER = get_post_meta( ( int ) $order->id, 'IdUser', true );
		$DS_TOKEN_USER = get_post_meta( ( int ) $order->id, 'TokenUser', true );
		$DS_MERCHANT_AMOUNT = $amount * 100;
		$DS_MERCHANT_ORDER = time();
		$DS_MERCHANT_CURRENCY = get_woocommerce_currency();
		$DS_MERCHANT_MERCHANTSIGNATURE = sha1( $DS_MERCHANT_MERCHANTCODE . $DS_IDUSER . $DS_TOKEN_USER . $DS_MERCHANT_TERMINAL . $DS_MERCHANT_AMOUNT . $DS_MERCHANT_ORDER . $this->config[ 'pass' ] );
		$DS_ORIGINAL_IP = get_post_meta( ( int ) $order->id, '_customer_ip_address', true );
		$p = array(
			'DS_MERCHANT_MERCHANTCODE' => $DS_MERCHANT_MERCHANTCODE,
			'DS_MERCHANT_TERMINAL' => $DS_MERCHANT_TERMINAL,
			'DS_IDUSER' => $DS_IDUSER,
			'DS_TOKEN_USER' => $DS_TOKEN_USER,
			'DS_MERCHANT_AMOUNT' => ( string ) $DS_MERCHANT_AMOUNT,
			'DS_MERCHANT_ORDER' => ( string ) $DS_MERCHANT_ORDER,
			'DS_MERCHANT_CURRENCY' => $DS_MERCHANT_CURRENCY,
			'DS_MERCHANT_MERCHANTSIGNATURE' => $DS_MERCHANT_MERCHANTSIGNATURE,
			'DS_ORIGINAL_IP' => $DS_ORIGINAL_IP,
			'DS_MERCHANT_PRODUCTDESCRIPTION' => '',
			'DS_MERCHANT_OWNER' => ''
		);
		return $this->client->call( 'execute_purchase', $p, '', '', false, true );
	}

	function info_user( $idUser, $tokeUser, $ip ) {
		$DS_MERCHANT_MERCHANTCODE = $this->config[ 'clientcode' ];
		$DS_MERCHANT_TERMINAL = $this->config[ 'term' ];
		$DS_IDUSER = $idUser;
		$DS_TOKEN_USER = $tokeUser;
		$DS_MERCHANT_MERCHANTSIGNATURE = sha1( $DS_MERCHANT_MERCHANTCODE . $DS_IDUSER . $DS_TOKEN_USER . $DS_MERCHANT_TERMINAL . $this->config[ 'pass' ] );
		$DS_ORIGINAL_IP = $ip;
		$p = array(
			'DS_MERCHANT_MERCHANTCODE' => $DS_MERCHANT_MERCHANTCODE,
			'DS_MERCHANT_TERMINAL' => $DS_MERCHANT_TERMINAL,
			'DS_IDUSER' => $DS_IDUSER,
			'DS_TOKEN_USER' => $DS_TOKEN_USER,
			'DS_MERCHANT_MERCHANTSIGNATURE' => $DS_MERCHANT_MERCHANTSIGNATURE,
			'DS_ORIGINAL_IP' => $DS_ORIGINAL_IP
		);
		return $this->client->call( 'info_user', $p, '', '', false, true );
	}

}

/**
 * @author mikel
 *
 */
class CreditCard {

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var long
	 */
	protected $pan;

	/**
	 * @var unknown_type
	 */
	protected $exp;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var int
	 */
	protected $cvv;

	public function getType() {
		return $this->type;
	}

	public function getName() {
		return $this->name;
	}

	public function getPan() {
		return $this->pan;
	}

	public function getExp() {
		return $this->exp;
	}

	public function getCvv() {
		return $this->cvv;
	}

	public function setType( $type ) {
		$this->type = $type;
		return $this;
	}

	public function setName( $name ) {
		$this->name = $name;
		return $this;
	}

	public function setPan( $pan ) {
		$this->pan = $pan;
		return $this;
	}

	public function setExp( $exp ) {
		$this->exp = $exp;
		return $this;
	}

	public function setCvv( $cvv ) {
		$this->cvv = $cvv;
		return $this;
	}

}

?>
