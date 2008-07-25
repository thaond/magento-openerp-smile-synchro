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

	//Imports
	ini_set('display_errors', false); //to avoid polluting the XML/RPC response with XML/RPC deprecation warning
	require_once('../../../Mage.php');
	include("xmlrpc.inc");
	include("xmlrpcs.inc");
	ini_set('display_errors', true);
	
	//basic test method available with your browser on the following URL: http://localhost/magento/app/code/community/Smile_OpenERP_Synchro/openerp-synchro.php?test=true
	if(count($_GET) != 0) {
		echo("<h1>Hello, This is the Magento/OpenERP Connector</h1> We will now perform some basic test about your installation.<br/> You should get and no error or warning here</br></br></br>");
		echo("1) Presence of connector file ---> YES<br/><br/>");
		echo("2) Bootsrapping Magento... <br/>");
		echo("- IF YOU GET AN ERROR HERE LIKE 'Uncaught exception 'Exception' with message 'Warning: Cannot modify header information' THEN CLEAR YOUR BROWSER COOKIES AND REFRESH THE PAGE TO TEST AGAIN -<br/>");
		Mage::app();
		echo("<br/>&nbsp; &nbsp; &nbsp; &nbsp; --->OK<br/><br/>");
		echo("3) minimal (but might not be enough) testing of your PHP XML/RPC lib ");
		new xmlrpcresp(new xmlrpcval(1, "int"));
		echo("--->OK<br/><br/>");
		echo("4) Information about your PHP installation:<br/>");
		phpinfo();
		echo("Don't Pay attention to the following error :<br/><br/><br/>");
	}

	/** Debug utility function ; use it to log data if you want to investigate on what is going wrong */
	function debug($s) {
		$fp = fopen("./debug.xmlrpc.txt","a+");
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

	//bootstarp the Magento Model layer (at least)
	function initMage() {
		Mage::app();
	}
	
	
	/** Update the Magento sale order with the update date OpenERP sale order
	* @param $sale_order Array: OpenERP sale order XML/RPC hash
	* @return id int: updated Magento sale order id
	*/
	function update_sale_order($sale_order){
		initMage();
		
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
		
		return new xmlrpcresp(new xmlrpcval($order->getId(), "int"));
	}
	
	
	/** Grab the Magento sale order with the requested id
	* @param $sale_order_id int: Magento sale order id
	* @return XML/RPC hash of the Magento sale order
	*/
	function get_sale_order($sale_order_id) {
		initMage();
	
		$productArray=array();	// sale order line product wrapper
		
		// Magento required models
		$order = Mage::getModel('sales/order')->load($sale_order_id);
		$customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
		
		//	walk the sale order lines
		foreach ($order->getAllItems() as $item) {
			$productArray[] = new xmlrpcval(array(
				"product_sku" => new xmlrpcval($item->getSku(),"string"),
				"product_magento_id" => new xmlrpcval($item->getProductId(),"int"),
				"product_name" => new xmlrpcval($item->getName(),"string"),
				"product_qty" => new xmlrpcval($item->getQtyOrdered(),"int"),
				"product_price" => new xmlrpcval($item->getPrice(),"double"),
				"product_discount_amount" => new xmlrpcval($item->getDiscountAmount(),"double"),
				"product_row_price" => new xmlrpcval($item->getPrice() - $item->getDiscountAmount(),"double")
			),"struct");
		}
		
		$streetBA=$order->getBillingAddress()->getStreet();
		$streetSA=$order->getShippingAddress()->getStreet();

		$saleorder = new xmlrpcval( array(
			"id" => new xmlrpcval($order->getId(),"int"),
			"store_id" => new xmlrpcval($order->getStoreId(),"int"),
			"payment"=> new xmlrpcval($order->getPayment()->getMethod(),"string"),
			"shipping_amount" => new xmlrpcval($order->getShippingAmount(),"double"),
			"grand_total" => new xmlrpcval($order->getGrandTotal(),"double"),
			"date"=> new xmlrpcval($order->getCreatedAt(),"string"),
			"lines" => new xmlrpcval($productArray, "array"),
			"shipping_address" =>	new xmlrpcval( array(
				"firstname"=> new xmlrpcval($order->getShippingAddress()->getFirstname(),"string"),
				"lastname"=> new xmlrpcval($order->getShippingAddress()->getLastname(),"string"),
				"company"=> new xmlrpcval($order->getShippingAddress()->getCompany(),"string"),
				"street" => new xmlrpcval($streetSA[0],"string"),
				"street2" => new xmlrpcval((count($streetSA)==2)?$streetSA[1]:'',"string"),
				"city"=> new xmlrpcval($order->getShippingAddress()->getCity(),"string"),
				"postcode"=> new xmlrpcval($order->getShippingAddress()->getPostcode(),"string"),
				"country"=> new xmlrpcval($order->getShippingAddress()->getCountry(),"string"),
				"phone"=> new xmlrpcval($order->getShippingAddress()->getTelephone(),"string")
			), "struct"),
			"billing_address" =>	new xmlrpcval( array(
				"firstname"=> new xmlrpcval($order->getBillingAddress()->getFirstname(),"string"),
				"lastname"=> new xmlrpcval($order->getBillingAddress()->getLastname(),"string"),
				"company"=> new xmlrpcval($order->getBillingAddress()->getCompany(),"string"),
				"street" => new xmlrpcval($streetBA[0],"string"),
				"street2" => new xmlrpcval((count($streetBA)==2)?$streetBA[1]:'',"string"),
				"city"=> new xmlrpcval($order->getBillingAddress()->getCity(),"string"),
				"postcode"=> new xmlrpcval($order->getBillingAddress()->getPostcode(),"string"),
				"country"=> new xmlrpcval($order->getBillingAddress()->getCountry(),"string"),
				"phone"=> new xmlrpcval($order->getBillingAddress()->getTelephone(),"string")
			), "struct"),
			"customer" =>new xmlrpcval( array(
				"customer_id"=> new xmlrpcval($customer->getId(),"int"),
				"customer_name"=> new xmlrpcval($customer->getName(),"string"),
				"customer_email"=> new xmlrpcval($customer->getEmail(),"string")
			), "struct"),
			
		), "struct");
		
		return new xmlrpcresp(new xmlrpcval($saleorder, "array"));
	}
	
	

	/** Grab the Magento sale orders created after the given magento id of the last synchronised sale order
	* @param $sale_order_id int: Magento sale order id of the the synchronised sale order
	* @return XML/RPC hash of the Magento sale orders
	*/
	function sale_orders_sync($last_sale_order_id) {
		initMage();
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
				$productArray[] = new xmlrpcval(array(
					"product_sku" => new xmlrpcval($item->getSku(),"string"),
					"product_magento_id" => new xmlrpcval($item->getProductId(),"int"),
					"product_name" => new xmlrpcval($item->getName(),"string"),
					"product_qty" => new xmlrpcval($item->getQtyOrdered(),"int"),
					"product_price" => new xmlrpcval($item->getPrice(),"double"),
					"product_discount_amount" => new xmlrpcval($item->getDiscountAmount(),"double"),
					"product_row_price" => new xmlrpcval($item->getPrice() - $item->getDiscountAmount(),"double")
				),"struct");
			}
		
			$streetBA=$order->getBillingAddress()->getStreet();
			$streetSA=$order->getShippingAddress()->getStreet();
			
			$saleorders[] = new xmlrpcval( array(
				"id" => new xmlrpcval($order->getId(),"int"),
				"store_id" => new xmlrpcval($order->getStoreId(),"int"),
				"payment"=> new xmlrpcval($order->getPayment()->getMethod(),"string"),
				"shipping_amount" => new xmlrpcval($order->getShippingAmount(),"double"),
				"grand_total" => new xmlrpcval($order->getGrandTotal(),"double"),
				"date"=> new xmlrpcval($order->getCreatedAt(),"string"),
				"lines" => new xmlrpcval($productArray, "array"),
				"shipping_address" =>	new xmlrpcval( array(
					"firstname"=> new xmlrpcval($order->getShippingAddress()->getFirstname(),"string"),
					"lastname"=> new xmlrpcval($order->getShippingAddress()->getLastname(),"string"),
					"company"=> new xmlrpcval($order->getShippingAddress()->getCompany(),"string"),
					"street"=> new xmlrpcval($streetSA[0],"string"),
					"street2"=> new xmlrpcval((count($streetSA)==2)?$streetSA[1]:'',"string"),
					"city"=> new xmlrpcval($order->getShippingAddress()->getCity(),"string"),
					"postcode"=> new xmlrpcval($order->getShippingAddress()->getPostcode(),"string"),
					"country"=> new xmlrpcval($order->getShippingAddress()->getCountry(),"string"),
					"phone"=> new xmlrpcval($order->getShippingAddress()->getTelephone(),"string")
				), "struct"),
				"billing_address" =>	new xmlrpcval( array(
					"firstname"=> new xmlrpcval($order->getBillingAddress()->getFirstname(),"string"),
					"lastname"=> new xmlrpcval($order->getBillingAddress()->getLastname(),"string"),
					"company"=> new xmlrpcval($order->getBillingAddress()->getCompany(),"string"),
					"street" => new xmlrpcval($streetBA[0],"string"),
					"street2" => new xmlrpcval((count($streetBA)==2)?$streetBA[1]:'',"string"),
					"city"=> new xmlrpcval($order->getBillingAddress()->getCity(),"string"),
					"postcode"=> new xmlrpcval($order->getBillingAddress()->getPostcode(),"string"),
					"country"=> new xmlrpcval($order->getBillingAddress()->getCountry(),"string"),
					"phone"=> new xmlrpcval($order->getBillingAddress()->getTelephone(),"string")
				), "struct"),
				"customer" =>new xmlrpcval( array(
					"customer_id"=> new xmlrpcval($customer->getId(),"int"),
					"customer_name"=> new xmlrpcval($customer->getName(),"string"),
					"customer_email"=> new xmlrpcval($customer->getEmail(),"string")
				), "struct"),
			), "struct");
		}
		
		return new xmlrpcresp(new xmlrpcval($saleorders, "array"));
	}
	
	
	/** Create or update the Magento product
	* @param $openerp_product XML/RPC hash: OpenERP product
	* @return id int: updated Magento product id
	*/
	function products_sync($openerp_product){
	
		initMage();
		
		/**/
		$product = Mage::getModel('catalog/product');
		
		
		if ($openerp_product['magento_id'] != 0) { // then it's an update, no need to create a new resource
			$product = Mage::getModel('catalog/product')->load($openerp_product['magento_id']);
		}
		
		$product->setStoreId(0);
		$product->setWebsiteIds(array(Mage::app()->getStore(true)->getId()));
		
		Mage::register('product', $product); 
		Mage::register('current_product', $product);
		
		//$product->setStoreId(?);  //TODO put some id here ?
		//TODO only if Mage::app()->isSingleStoreMode()
		
		//	product attributes
		$product->setName($openerp_product['name']);
		$product->setSku("mag".$openerp_product['product_id']);
		$product->setPrice($openerp_product['price']);
		$product->setWeight($openerp_product['weight']);
		$product->setDescription($openerp_product['description']);
		$product->setShortDescription($openerp_product['sale_description']);
		
		//	Required for Magento
		$product->setEntity_type_id(10); 
		$product->setAttribute_set_id(9);
		$product->setCustomer_group_id(1);
		$product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
		$product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
		$product->setGiftMessageAvailable(2);
		$product->setThumbnail('no_selection');
		$product->setSmallImage('no_selection');
		$product->setImage('no_selection');
		
		// tax classes
		if($openerp_product['tax_class_id'] == 0){$openerp_product['tax_class_id']=1;}
		$product->setTaxClassId($openerp_product['tax_class_id']);
		
		//	categories
		$category [] = 3; //Setted by default in Root catalog
		//if($openerp_product['category_id'] != 0){$category [] = $openerp_product['category_id'];}
		//$product->setCategoryIds($category);
		
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
	
		try {
			$product->save();
			$productId = $product->getId();

		} catch (Mage_Core_Exception $e) {
			$productId=0;
			debug($e);
		}
		catch (Exception $e) {
			$productId=0;
			debug($e);
		}
		
		return new xmlrpcresp(new xmlrpcval($productId, "int"));
	}

	
	/* XML/RPC server declaration */
	$server = new xmlrpc_server( array(
		"products_sync" => array(
			"function" => "products_sync",
			"signature" => array(array($xmlrpcInt, $xmlrpcStruct))
		),
		"update_sale_order" => array(
			"function" => "update_sale_order",
			"signature" => array(array($xmlrpcInt, $xmlrpcStruct))
		),
		"sale_orders_sync" => array(
			"function" => "sale_orders_sync",
			"signature" => array(array($xmlrpcArray, $xmlrpcInt))
		),
		"get_sale_order" => array(
			"function" => "get_sale_order",
			"signature" => array(array($xmlrpcArray, $xmlrpcInt))
		)
	), false);
	
	$server->functions_parameters_type= 'phpvals';
	$server->service();	

?>