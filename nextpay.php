<?php


defined('_JEXEC') or die;

if(!class_exists('DigiComSiteHelperLog'))
	require JPATH_SITE."/components/com_digicom/helpers/log.php";

class  plgDigiCom_PayNextpay extends JPlugin
{
	/**
	 * Load the language file on instantiation. Note this is only available in Joomla 3.1 and higher.
	 * If you want to support 3.0 series you must override the constructor
	 *
	 * @var    boolean
	 * @since  3.1
	 */
	protected $autoloadLanguage = true;

	/*
	* initialized response status for quickr use
	*/
	protected $responseStatus;

/*
	* Author : Mohammad Hossein Miri - Miri.Joomina@Gmail.Com
	* @JoominaMarket.Com
	* @Joomina.Ir.
*/

	function __construct($subject, $config)
	{
		parent::__construct($subject, $config);

		//Define Payment Status codes in API  And Respective Alias in Framework
		$this->responseStatus= array (
			'Completed' => 'A',
			'Pending' 	=> 'P',
			'Failed' 		=> 'P',
			'Denied' 		=> 'P',
			'Refunded'	=> 'RF'
		);
	}

	public function onDigicomSidebarMenuItem()
	{
		$pluginid = $this->getPluginId('nextpay','digicom_pay','plugin');
		$params 	= $this->params;
		$link 		= JRoute::_("index.php?option=com_plugins&client_id=0&task=plugin.edit&extension_id=".$pluginid);

		return '<a target="_blank" href="' . $link . '" title="'.JText::_("PLG_DIGICOM_NEXTPAY").'" id="plugin-'.$pluginid.'">' . JText::_("PLG_DIGICOM_NEXTPAY_NICKNAME") . '</a>';

	}

	function buildLayoutPath($layout)
	{

		if(empty($layout)) $layout = "default";

		// bootstrap2 check
		$bootstrap2 	= $this->params->get( 'bootstrap2' , 0);
		if($bootstrap2){
			$layout = "bootstrap2";
		}
		$app = JFactory::getApplication();

		// core path
		$core_file 	= dirname(__FILE__) . '/' . $this->_name . '/tmpl/' . $layout . '.php';

		// override path from site active template
		$override	= JPATH_BASE .'/templates/' . $app->getTemplate() . '/html/plugins/' . $this->_type . '/' . $this->_name . '/' . $layout . '.php';

		if(JFile::exists($override))
		{
			$file = $override;
		}
		else
		{
  		$file =  $core_file;
		}

		return $file;

	}

	function buildLayout($vars, $layout = 'default' )
	{

		// Load the layout & push variables
		ob_start();
		$layout = $this->buildLayoutPath($layout);

		// **********************
		// start bank

		$Api_Key = $vars->api_key;
		$Amount = (int)$vars->amount;
		$Amount = $Amount / 10;
		$order_id = rand(1111111,9999999);//$vars->order_id;
		$CallbackURL = $vars->return;

		$session =& JFactory::getSession();
		$session->set( 'price', $Amount);
		$session->set( 'order_id', $order_id);
		$client = new SoapClient('https://api.nextpay.org/gateway/token.wsdl', ['encoding' => 'UTF-8']);


		$result = $client->TokenGenerator(
			array(
				'api_key'  => $Api_Key,
				'amount'      => $Amount,
				'order_id' => $order_id,
				'callback_uri' => $CallbackURL,
			)
		);

		$result = $result->TokenGeneratorResult;

		//Redirect to URL You can do it also by creating a form
		if ($result->code == -1) {

			$url =  'https://api.nextpay.org/gateway/payment/'.$result->trans_id;

			include($layout);
			$html = ob_get_contents();
			ob_end_clean();
			return $html;


		} else {
			echo'ERR: '.$result->code;
		}

	}

	/*
	* method onDigicom_PayGetInfo
	* can be used Build List of Payment Gateway in the respective Components
	* for payment process its not used
	*/
	function onDigicom_PayGetInfo($config)
	{

		if(!in_array($this->_name,$config)) return;

		$obj 				= new stdClass;
		$obj->name 	=	$this->params->get( 'plugin_name' );
		$obj->id		= $this->_name;
		return $obj;
	}

	function onDigicom_PayGetHTML($vars, $pg_plugin)
	{
		if($pg_plugin != $this->_name) return;
		$params 					= $this->params;
		$api_key 					= $params->get('api_key');
		$vars->api_key 		= $api_key;

		$html = $this->buildLayout($vars);
		return $html;

	}

	function onDigicom_PayProcesspayment($data)
	{

		$processor = JFactory::getApplication()->input->get('processor','');
		if($processor != $this->_name) return;

		//$verify 		= plgDigiCom_PayPaypalHelper::validateIPN($data);
		$session =& JFactory::getSession();
		$Amount = (int)$session->get('price',0);
		$order_id = $session->get('order_id',0);
		$Trans_ID = $_POST['trans_id'];

		$params 					= $this->params;
		$Api_Key				= $params->get('api_key');



		$client = new SoapClient('https://api.nextpay.org/gateway/verify.wsdl', ['encoding' => 'UTF-8']);

		$result = $client->PaymentVerification(
			array(
				'api_key' => $Api_Key,
				'trans_id'  => $Trans_ID,
				'amount'     => $Amount,
				'order_id' => $order_id,
			)
		);

			if ($result->code == 0) {

				echo "تراکنش موفقیت آمیز بود. کد رهگیری بانک : ".$Trans_ID;
				$data['payment_status'] = "Completed";

				$info = array(
					'orderid' => $order_id,
					'data' => $data,
					'plugin' => ''
				);

				// set transaction log
				DigiComSiteHelperLog::setLog('transaction', 'cart proccessSuccess', $order_id, 'code rahgiri : '.$Trans_ID, json_encode($info), 'success');
			} else {
				echo "تراکنش با شکست مواجه شده است " . $result->code;
				$data['payment_status'] = "Failed";
			}


		$payment_status = $this->translateResponse( $data );
		$Amount = $Amount * 10;

		$result = array(
			'order_id'				=> $order_id,
			'transaction_id'	=> $Trans_ID,
			'status'					=> $payment_status,
			'total_paid_amt'	=> $data['payment_status'] == "Completed" ? $Amount : '',
			'error'						=>	'',
			'raw_data'				=> json_encode($data),
			'processor'				=> 'nextpay'
		);

		return $result;
	}

	function translateResponse($data)
	{
		$payment_status = $data['payment_status'];

		if(array_key_exists($payment_status, $this->responseStatus))
		{
			return $this->responseStatus[$payment_status];
		}
	}


	function onDigicom_PayStorelog($name, $data)
	{
		if($name != $this->_name) return;
		//plgDigiCom_PayPaypalHelper::Storelog($this->_name,$data);
	}

	function getPluginId($element,$folder, $type)
	{
	    $db = JFactory::getDBO();
	    $query = $db->getQuery(true);
	    $query
	        ->select($db->quoteName('a.extension_id'))
	        ->from($db->quoteName('#__extensions', 'a'))
	        ->where($db->quoteName('a.element').' = '.$db->quote($element))
	        ->where($db->quoteName('a.folder').' = '.$db->quote($folder))
	        ->where($db->quoteName('a.type').' = '.$db->quote($type));

	    $db->setQuery($query);
	    $db->execute();
	    if($db->getNumRows()){
	        return $db->loadResult();
	    }
	    return false;
	}

}
