<?php
/**
 * Magento OpenERP Smile synchro
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Mage
 * @package    Mage_OpenERP
 * @copyright  Copyright (c) 2008 Smile S.A. (http://www.smile.fr)
 * @authors	Sylvain Pamart, Raphaël Valyi
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

	/** Wrapper arround our remote functions */ 
	class OpenERPConnector {
		
		/** Update the Magento sale order with the update date OpenERP sale order
		* @param array sale_order Array: OpenERP sale order XML/RPC hash
		* @return int id: updated Magento sale order id
		*/
		function update_sale_order($sale_order){
			$sale_order = $sale_order[0];
			$order = Mage::getModel('sales/order')->load($sale_order['magento_id']);
			
			//	status matching TODO can't do better?
			if($sale_order['status'] == ''){$order->setStatus(Mage_Sales_Model_Order::STATE_NEW, true);}
			if($sale_order['status'] == 'progress'){$order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING, true);}
			if($sale_order['status'] == 'shipping_except'){$order->setStatus(Mage_Sales_Model_Order::STATE_COMPLETE, true);}
			if($sale_order['status'] == 'invoice_except'){$order->setStatus(Mage_Sales_Model_Order::STATE_COMPLETE, true);}
			if($sale_order['status'] == 'done'){$order->setStatus(Mage_Sales_Model_Order::STATE_CLOSED, true);}
			if($sale_order['status'] == 'cancel'){$order->setStatus(Mage_Sales_Model_Order::STATE_CANCELED, true);}
			if($sale_order['status'] == 'waiting_date'){$order->setStatus(Mage_Sales_Model_Order::STATE_HOLDED, true);}
			
			$order->save();
			return $order->getId();
		}
		
		
		/** Grab the Magento sale order with the requested id
		* @param int sale_order_id int: Magento sale order id
		* @return array XML/RPC hash of the Magento sale order
		*/
		function get_sale_order($sale_order_id) {		
			$productArray=array();	// sale order line product wrapper
			
			// Magento required models
			$order = Mage::getModel('sales/order')->load($sale_order_id);
			$customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
			
			//	walk the sale order lines
			foreach ($order->getAllItems() as $item) {
				$productArray[] = array(
					"product_sku" => $item->getSku(),
					"product_magento_id" => $item->getProductId(),
					"product_name" => $item->getName(),
					"product_qty" => $item->getQtyOrdered(),
					"product_price" => $item->getPrice(),
					"product_discount_amount" => $item->getDiscountAmount(),
					"product_row_price" => $item->getPrice() - $item->getDiscountAmount(),
				);
			}
			
			$streetBA=$order->getBillingAddress()->getStreet();
			$streetSA=$order->getShippingAddress()->getStreet();

			$saleorder =  array(
				"id" => $order->getId(),
				"store_id" => $order->getStoreId(),
				"payment"=> $order->getPayment()->getMethod(),
				"shipping_amount" => $order->getShippingAmount(),
				"grand_total" => $order->getGrandTotal(),
				"date"=> $order->getCreatedAt(),
				"lines" => $productArray,
				"shipping_address" =>	 array(
					"firstname"=> $order->getShippingAddress()->getFirstname(),
					"lastname"=> $order->getShippingAddress()->getLastname(),
					"company"=> $order->getShippingAddress()->getCompany(),
					"street" => $streetSA[0],
					"street2" => (count($streetSA)==2)?$streetSA[1]:'',
					"city"=> $order->getShippingAddress()->getCity(),
					"postcode"=> $order->getShippingAddress()->getPostcode(),
					"country"=> $order->getShippingAddress()->getCountry(),
					"phone"=> $order->getShippingAddress()->getTelephone()
				),
				"billing_address" =>	 array(
					"firstname"=> $order->getBillingAddress()->getFirstname(),
					"lastname"=> $order->getBillingAddress()->getLastname(),
					"company"=> $order->getBillingAddress()->getCompany(),
					"street" => $streetBA[0],
					"street2" => (count($streetBA)==2)?$streetBA[1]:'',
					"city"=> $order->getBillingAddress()->getCity(),
					"postcode"=> $order->getBillingAddress()->getPostcode(),
					"country"=> $order->getBillingAddress()->getCountry(),
					"phone"=> $order->getBillingAddress()->getTelephone()
				),
				"customer" => array(
					"customer_id"=> $customer->getId(),
					"customer_name"=> $customer->getName(),
					"customer_email"=> $customer->getEmail()
				),
				
			);
			
			return $saleorder;
		}
		
		
		/**
		* @param int last_sale_order_id
		* @return array saleorders
		*/
		function sale_orders_sync($last_sale_order_id) {
			$saleorders=array();  

			//	retrieve the next sale orders
			$order_collection = Mage::getResourceModel('sales/order_collection')
				->addAttributeToSelect('entity_id')
				->addAttributeToSort('entity_id', 'desc')
				->load();
				
			$max_order_id=$order_collection->getFirstItem()->getId();
			
			/* iterate over the sale orders */
			for ($id = $last_sale_order_id+1; $id <= $max_order_id; $id++) {
				$productArray=array();	//	sale order line product wrapper
				
				//	Magento required models
				$order = Mage::getModel('sales/order')->load($id);
				$customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
				
				//	Walk the sale order lines
				foreach ($order->getAllItems() as $item) {
					$productArray[] = array(
						"product_sku" => $item->getSku(),
						"product_magento_id" => $item->getProductId(),
						"product_name" => $item->getName(),
						"product_qty" => $item->getQtyOrdered(),
						"product_price" => $item->getPrice(),
						"product_discount_amount" => $item->getDiscountAmount(),
						"product_row_price" => $item->getPrice() - $item->getDiscountAmount()
					);
				}
				
			
				$streetBA=$order->getBillingAddress()->getStreet();
				$streetSA=$order->getShippingAddress()->getStreet();
				
				$saleorders[] =  array(
					"id" => $order->getId(),
					"store_id" => $order->getStoreId(),
					"payment"=> $order->getPayment()->getMethod(),
					"shipping_amount" => $order->getShippingAmount(),
					"grand_total" => $order->getGrandTotal(),
					"date"=> $order->getCreatedAt(),
					"lines" => $productArray,
					"shipping_address" =>	 array(
						"firstname"=> $order->getShippingAddress()->getFirstname(),
						"lastname"=> $order->getShippingAddress()->getLastname(),
						"company"=> $order->getShippingAddress()->getCompany(),
						"street"=> $streetSA[0],
						"street2"=> (count($streetSA)==2)?$streetSA[1]:'',
						"city"=> $order->getShippingAddress()->getCity(),
						"postcode"=> $order->getShippingAddress()->getPostcode(),
						"country"=> $order->getShippingAddress()->getCountry(),
						"phone"=> $order->getShippingAddress()->getTelephone()
					),
					"billing_address" =>	 array(
						"firstname"=> $order->getBillingAddress()->getFirstname(),
						"lastname"=> $order->getBillingAddress()->getLastname(),
						"company"=> $order->getBillingAddress()->getCompany(),
						"street" => $streetBA[0],
						"street2" => (count($streetBA)==2)?$streetBA[1]:'',
						"city"=> $order->getBillingAddress()->getCity(),
						"postcode"=> $order->getBillingAddress()->getPostcode(),
						"country"=> $order->getBillingAddress()->getCountry(),
						"phone"=> $order->getBillingAddress()->getTelephone()
					),
					"customer" => array(
						"customer_id"=> $customer->getId(),
						"customer_name"=> $customer->getName(),
						"customer_email"=> $customer->getEmail()
					),
				);
			}
			
			return $saleorders;
		}
		
		
		
		/** Create or update the Magento product
		* @param array openerp_product
		* @return int product_id
		*/
		function product_sync($openerp_product){
			
			$openerp_product = $openerp_product[0];
			$product = Mage::getModel('catalog/product');
			
			if ($openerp_product['magento_id'] != 0) { 
				// then it's an update, no need to create a new resource
				$product = Mage::getModel('catalog/product')->load($openerp_product['magento_id']);
			}
			
			
			
			Mage::register('product', $product); 
			Mage::register('current_product', $product);
			
			/*
			* Attirbute And Type parameters can be setted on OpenERP, if not, take default value
			* These parameters have to be synchronised manually
			*/
			//Attribute
			if($openerp_product['magento_product_attribute_set_id']==0){
				$entityType = Mage::registry('product')->getResource()->getEntityType();
				$product->setAttribute_set_id($entityType->getDefaultAttributeSetId());
			} else{
				$product->setEntity_type_id($openerp_product['magento_product_attribute_set_id']);
			}

			//Type
			if($openerp_product['magento_product_type']==0){
				$product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
			} else{
				$product->setTypeId($openerp_product['magento_product_type']);
			}
			
			/*
			*This parameters are untreated in OpenERP for the moment, 
			* Still this version handle evolution of openERP by checking sended values
			* and set default value if not.
			*/
			if(!(isset($openerp_product['store_id']))){
				$product->setStoreId(0);}
			//-Status
			if(!(isset($openerp_product['product_data']['status']))){
				$openerp_product['product_data']['status']=Mage_Catalog_Model_Product_Status::STATUS_ENABLED;}
			//-Visibility
			if(!(isset($openerp_product['product_data']['visibility']))){
				$openerp_product['product_data']['visibility']=Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH;}
			//-Store
			if(!(isset($openerp_product['product_data']['websites']))){
				$product->setWebsiteIds(array(Mage::app()->getStore(true)->getId()));}
			

			//Copying the data coming from OpenERP
			$openerp_product['gift_message_available']=2;
			$product->addData($openerp_product['product_data']);
			
			//Misc settings
			$product->setThumbnail('no_selection');
			$product->setSmallImage('no_selection');
			$product->setImage('no_selection');
		
			//Inventory conf
			$inventory = array(
				"qty"=> $openerp_product['quantity'],
				"use_config_min_qty" => 1, 
				"use_config_min_sale_qty" => 1, 
				"use_config_max_sale_qty" => 1,
				"is_qty_decimal" => 0, 
				"use_config_backorders"=>1, 
				"use_config_notify_stock_qty"=>1, 
				"is_in_stock"=> $openerp_product['quantity']
			);

			$product->setStockData($inventory);
		
			/*
			* Try to save and return the magento Id of the product
			* Logging the errors in the debug log
			*/
			try {
				$product->save();
				$productId = $product->getId();

			} catch (Mage_Core_Exception $e) {
				$productId=0;
				//echo $e;
				debug("Mage says:");
				debug($e);
			}
			catch (Exception $e) {
				$productId=0;
				//echo $e;
				debug("php says:");
				debug($e);
			}
			
			return $productId;
		}
			
	}
	

	/** Debug utility function ; use it to log data if you want to investigate on what is going wrong */
	function debug($s) {
		$fp = fopen("./debug.xmlrpc.log","a+");
		fwrite($fp, $s."\n");
		fclose($fp);
	}
	
	
	/** Debug utility function ; use it to log an arraysif you want to investigate on what is going wrong */
	function debug_arr($para_arr,$tab=''){
		if (is_array($para_arr))
		{
			foreach($para_arr as $key=>$values)
			{
				debug($tab.'Key :'.$key.' Value :'.$values);
			}
		}
	}


//script when page loads, either from XML/RPC, either browser HTTP
$headers = getallheaders();
//echo stripos($headers['Accept'], 'text');
if (isset($headers['Accept'])) {
	$request_type = stripos($headers['Accept'], 'text');
	if ($request_type >=0) {
		browserTestMessage() ;
	} else {
		initRPCServer();
	}
} else {
	initRPCServer();
}


/** Init the XML/RPC server */
function initRPCServer() {
	require_once('../../../Mage.php');	//Magento import
	Mage::app();	//bootstarp the Magento Model layer (at least) and autoload function
	
	require_once('../../../../lib/Zend/XmlRpc/Server.php');
	$server = new Zend_XmlRpc_Server();
	$server->setClass('OpenERPConnector');
	echo $server->handle();
}


/** Test utility when calling the page from your HTTP browser */
function browserTestMessage() {
		echo("<h1>Hello, This is the Magento/OpenERP Connector by <a href='http://www.smile.fr'>Smile.fr</a></h1> We will now perform some basic test about your installation.<br/> You should get and no error or warning here</br></br></br>");
		echo("1) Presence of connector file ---> YES<br/><br/>");
		echo("2) Bootsrapping Magento... <br/>");
		echo("- IF YOU GET AN ERROR HERE LIKE 'Uncaught exception 'Exception' with message 'Warning: Cannot modify header information' THEN CLEAR YOUR BROWSER COOKIES AND REFRESH THE PAGE TO TEST AGAIN -<br/>");
		require_once('../../../Mage.php');	//Magento import
		Mage::app();
		echo("<br/>&nbsp; &nbsp; &nbsp; &nbsp; --->OK<br/><br/>");
		echo("3) minimal (but might not be enough) testing of your PHP XML/RPC lib ");
		require_once('../../../../lib/Zend/XmlRpc/Server.php');
		$server = new Zend_XmlRpc_Server();
		$server->setClass('OpenERPConnector');
		echo("--->OK<br/>");
		echo("4) SECURITY: DON'T FORGET TO SET UP YOUR APACHE PROPERLY (NOT TESTED HERE) TO AVOID CONNECTION TO THAT PAGE FROM ANY OTHER IP THAN YOU OPENERP!!!<br/><br/>");
		echo("5) Information about your PHP installation:<br/>");
		phpinfo();
}
?>