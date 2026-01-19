<?php
/**
 * A payment plugin called "example". This is the main file of the plugin.
 *
 * In HikaShop, payment plugins are standard Joomla plugins of the "hikashoppayment" group.
 * The entry point for any payment plugin is always the class defined in this file.
 */

// We highly recommend extending the hikashopPaymentPlugin class.
// This class provides many helper methods (getOrder, loadPaymentParams, showPage, writeToLog, etc.)
// that simplify the integration with any payment gateway and ensure compatibility across Joomla versions.
class plgHikashoppaymentExample extends hikashopPaymentPlugin
{
	/**
	 * List of the plugin's accepted currencies.
	 * If the checkout currency is not in this list, the plugin will not be displayed.
	 * You can remove this property if you want the plugin to be available for all currencies.
	 */
	public $accepted_currencies = array("EUR", "USD");

	/**
	 * Whether the plugin supports multiple instances.
	 * If true, the shop owner can create multiple "example" payment methods in the backend, each with its own settings.
	 * This is usually set to true for modern plugins.
	 */
	public $multiple = true;

	/**
	 * The internal name of the plugin.
	 * CRITICAL: This MUST match the name of the PHP file (example.php -> 'example').
	 * If you copy this plugin to create 'mygateway.php', you MUST change this to 'mygateway'.
	 */
	public $name = 'example';

	/**
	 * The Joomla application object ($this->app) is inherited from the parent class.
	 * It is used for enqueuing messages to the user.
	 */

	/**
	 * This array defines the configuration fields visible in the HikaShop backend (Payment method edition).
	 * HikaShop automatically generates the form based on this array.
	 * 
	 * Each entry's key is the parameter name (e.g. 'identifier').
	 * The value is an array: [Label string, Field type, Default value]
	 * 
	 * Available types: input, boolean, list, orderstatus, textarea, html, big-textarea, etc.
	 */
	/**
	 * Example of environment-specific URLs.
	 * Many gateways provide different endpoints for testing and production.
	 */
	public $environments = array(
		'test' => 'https://sandbox.example.com/api/v1',
		'prod' => 'https://api.example.com/v1'
	);

	/**
	 * This array defines the configuration fields visible in the HikaShop backend (Payment method edition).
	 * HikaShop automatically generates the form based on this array.
	 * 
	 * Each entry's key is the parameter name (e.g. 'identifier').
	 * The value is an array: [Label string, Field type, Default value]
	 * 
	 * Available types: input, boolean, list, orderstatus, textarea, html, big-textarea, etc.
	 */
	public $pluginConfig = array(
		'identifier' => array("Identifier", 'input'), // User's ID on the payment gateway
		'password' => array("HIKA_PASSWORD", 'input'), // API Key or Secret Key
		'notification' => array('ALLOW_NOTIFICATIONS_FROM_X', 'boolean', '1'), // Toggle for the notify_url
		'environment' => array('ENVIRONNEMENT', 'list',
			array(
				'test' => 'Sandbox (Test)',
				'prod' => 'Production'
			)
		),
		'debug' => array('DEBUG', 'boolean', '0'), // Enable logging to file
		'cancel_url' => array('CANCEL_URL_DEFINE', 'html', ''), // Placeholder for displayed URL info
		'return_url_gateway' => array('RETURN_URL_DEFINE', 'html', ''), // Placeholder for displayed URL info
		'return_url' => array('RETURN_URL', 'input'), // The "Thank you" page the user finally sees
		'notify_url' => array('NOTIFY_URL_DEFINE', 'html', ''), // Placeholder for displayed URL info
		'invalid_status' => array('INVALID_STATUS', 'orderstatus'), // Status to set if payment fails
		'verified_status' => array('VERIFIED_STATUS', 'orderstatus') // Status to set if payment succeeds
	);

	/**
	 * The constructor.
	 * We use it here to initialize dynamic values in the pluginConfig, such as HikaShop specific URLs.
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		// JText::sprintf is used for multilingual labels
		$this->pluginConfig['notification'][0] = JText::sprintf('ALLOW_NOTIFICATIONS_FROM_X', 'Example');

		// HIKASHOP_LIVE provides the root URL of the website.
		// These URLs are the standard entry points for HikaShop to handle redirects back from the gateway.

		// Cancel URL: HikaShop will cancel the order and return the user to the checkout to pick another method.
		$this->pluginConfig['cancel_url'][2] = HIKASHOP_LIVE . "index.php?option=com_hikashop&ctrl=order&task=cancel_order";

		// Return URL (after_end): This cleans the cart, clears the session, and THEN redirects to the plugin's 'return_url'.
		// Gateway should redirect the user HERE upon success.
		$this->pluginConfig['return_url'][2] = HIKASHOP_LIVE . "index.php?option=com_hikashop&ctrl=checkout&task=after_end";

		// Notify URL: This is for Server-to-Server callbacks (Webhooks). 
		// HikaShop will trigger the 'onPaymentNotification' method of this plugin when this URL is hit.
		$this->pluginConfig['notify_url'][2] = HIKASHOP_LIVE . 'index.php?option=com_hikashop&ctrl=checkout&task=notify&notif_payment=' . $this->name . '&tmpl=component';
	}

	/**
	 * Example utility method: Some gateways require the street name and house number to be sent in separate fields.
	 * This method demonstrates how to split a single address line into two components.
	 */
	public static function splitStreetAndHouseNumber($streetLine)
	{
		// Simple regex to find a number at the end of a string
		$pattern = '/^(.*?)\s*(\d+[a-zA-Z]{0,1})$/';
		if (preg_match($pattern, $streetLine, $matches)) {
			return array('street' => trim($matches[1]), 'houseNumber' => trim($matches[2]));
		}
		return array('street' => $streetLine, 'houseNumber' => '');
	}

	/**
	 * This function is triggered by HikaShop at the very end of the checkout process,
	 * once the user has clicked on "Finish".
	 * 
	 * It should prepare the data for the gateway and display a redirection form (usually via showPage).
	 */
	public function onAfterOrderConfirm(&$order, &$methods, $method_id)
	{
		// Mandatory call to parent to load the specific instance parameters into $this->payment_params
		parent::onAfterOrderConfirm($order, $methods, $method_id);

		// Basic validation: make sure the shop owner configured the plugin
		if (empty($this->payment_params->identifier)) {
			$this->app->enqueueMessage('Please configure an identifier for the Example payment plugin.', 'error');
			return false;
		}

		// Calculate order total in cents (common for gateways)
		$amount = round($order->cart->full_total->prices[0]->price_value_with_tax, 2) * 100;

		// Example: Get the user's current language tag (e.g. en-GB) for the gateway
		$lang = JFactory::getLanguage();
		$locale = $lang->get('tag');

		// Example: Pick the target URL based on the environment setting
		$paymentUrl = $this->environments[$this->payment_params->environment];

		/**
		 * Prepare the variables to be sent to the gateway.
		 * Gateway-specific names (e.g. 'IDENTIFIER', 'ORDERID') should be used here as required by their documentation.
		 */
		$vars = array(
			'IDENTIFIER' => $this->payment_params->identifier,
			'CLIENTIDENT' => $order->order_user_id,
			'DESCRIPTION' => "Order number: " . $order->order_number,
			'ORDERID' => $order->order_id,
			'VERSION' => 2.0,
			'AMOUNT' => $amount,
			'LOCALE' => $locale
		);

		// Example: Splitting the billing street address
		if (!empty($order->cart->billing_address->address_street)) {
			$addressSplit = self::splitStreetAndHouseNumber($order->cart->billing_address->address_street);
			$vars['STREET'] = $addressSplit['street'];
			$vars['HOUSE_NUMBER'] = $addressSplit['houseNumber'];
		}

		// Generate a security hash to prevent tampering (signature)
		$vars['HASH'] = $this->example_signature($this->payment_params->password, $vars);

		// Store variables in $this->vars so they can be used in the 'end' view file (example_end.php)
		$this->vars = $vars;
		$this->payment_url = $paymentUrl; // Pass the URL to the view

		// Log the outgoing data if debug mode is on (Logs are in HikaShop > Configuration > Files > Payment log)
		if ($this->payment_params->debug) {
			$this->writeToLog($vars);
		}

		/**
		 * ADVANCED: Saving custom data to the order.
		 * If you receive a unique token/ID from the gateway BEFORE the user is redirected,
		 * you can store it in 'order_payment_params' for later use in 'onPaymentNotification'.
		 */
		/*
		$update_order = new stdClass();
		$update_order->order_id = (int)$order->order_id;
		$update_order->order_payment_params = @$order->order_payment_params;
		if(!empty($update_order->order_payment_params) && is_string($update_order->order_payment_params))
			$update_order->order_payment_params = hikashop_unserialize($update_order->order_payment_params);
		if(empty($update_order->order_payment_params))
			$update_order->order_payment_params = new stdClass();
		
		$update_order->order_payment_params->my_gateway_token = "GENERATED_TOKEN";
		
		$orderClass = hikashop_get('class.order');
		$orderClass->save($update_order);
		*/

		// Render the view file located in the plugin folder: example_end.php
		// This view usually contains a hidden HTML form that auto-submits to the gateway's payment URL.
		return $this->showPage('end');
	}

	/**
	 * Sets default values when saving a new payment method of this type in the backend.
	 */
	public function getPaymentDefaultValues(&$element)
	{
		$element->payment_name = 'Example';
		$element->payment_description = 'Pay securely with your credit card.';
		$element->payment_images = 'MasterCard,VISA,Credit_card,American_Express';
		$element->payment_params->address_type = "billing";
		$element->payment_params->notification = 1;
		$element->payment_params->environment = 'test';
		$element->payment_params->invalid_status = 'cancelled';
		$element->payment_params->verified_status = 'confirmed';
	}

	/**
	 * This function is triggered when the notification URL (notify_url) is called by the gateway (Webhook).
	 * It handles the server-to-server request to update the order status.
	 */
	public function onPaymentNotification(&$statuses)
	{
		// Collect received parameters
		$vars = array();
		$input = hikaInput::get();
		foreach ($_REQUEST as $key => $value) {
			$vars[$key] = $input->getString($key);
		}

		// Retrieve the Order ID from the gateway's payload
		$order_id = (int)@$vars['ORDERID'];
		
		// Load the full order object from the database
		$dbOrder = $this->getOrder($order_id);

		// Load the payment method parameters ($this->payment_params) based on the order
		$this->loadPaymentParams($dbOrder);
		if (empty($this->payment_params)) {
			return false;
		}

		// Load order breakdown (taxes, products, etc.) needed for some gateways
		$this->loadOrderData($dbOrder);

		// Recalculate the signature to verify the request is genuine
		$hash = $this->example_signature($this->payment_params->password, $vars, false, true);

		// Log everything for debugging
		if ($this->payment_params->debug) {
			$this->writeToLog($vars, 'vars'); // Received data
			$this->writeToLog($hash, 'calculated_hash');
		}

		/**
		 * VERIFICATION LOGIC
		 */
		if (strcasecmp($hash, @$vars['HASH']) !== 0) {
			if ($this->payment_params->debug) {
				$this->writeToLog('Hash mismatch. Potential tampering detected.');
			}
			return false;
		}

		// Optional: If some gateways redirect the user to this notification URL,
		// you might need to handle the redirection to the 'after_end' page here.
		$isUserReturn = $input->getInt('user_return', 0);

		if (@$vars['EXECCODE'] !== '0000') {
			// Payment failed at the gateway level
			if ($this->payment_params->debug) {
				$this->writeToLog('Payment rejected by gateway: ' . @$vars['MESSAGE']);
			}
			// Update the order to the configured "Invalid Status"
			$this->modifyOrder($order_id, $this->payment_params->invalid_status, true, true);
			
			if ($isUserReturn) {
				$this->app->redirect(HIKASHOP_LIVE . "index.php?option=com_hikashop&ctrl=order&task=cancel_order&order_id=" . $order_id);
			}
			return false;
		}

		// SUCCESS: Update the order to the configured "Verified Status"

		// To store detailed info in the order history, we can pass an object.
		$history = new stdClass();
		$history->notified = 1; // Mark as notified (displays a small icon in the backend list)
		$history->amount = ''; // You can store the received amount here
		$history->data = 'Transaction ID: ' . @$vars['TRANSACTIONID']; // Additional details for the history log

		// Check if the order status is already confirmed to avoid duplicate history lines
		if ($dbOrder->order_status !== $this->payment_params->verified_status) {
			$this->modifyOrder($order_id, $this->payment_params->verified_status, $history, true);
		}
		
		if ($isUserReturn) {
			$this->app->redirect(HIKASHOP_LIVE . "index.php?option=com_hikashop&ctrl=checkout&task=after_end&order_id=" . $order_id);
		}
		
		return true;
	}

	/**
	 * Generates a security signature based on a shared secret (password).
	 * This follows a typical gateway requirement: sorting parameters and hashing them.
	 *
	 * @param string $password The Secret Key
	 * @param array $parameters Data to be signed
	 * @param bool $debug Return clear string if true
	 * @param bool $decode Whether we are verifying an incoming request
	 */
	protected function example_signature($password, $parameters, $debug = false, $decode = false)
	{
		ksort($parameters);
		$clear_string = $password;

		// List of keys expected in the response signature
		$expectedKeys = array(
			'IDENTIFIER', 'TRANSACTIONID', 'CLIENTIDENT', 'CLIENTEMAIL', 'ORDERID',
			'VERSION', 'LANGUAGE', 'CURRENCY', 'EXTRADATA', 'CARDCODE',
			'CARDCOUNTRY', 'EXECCODE', 'MESSAGE', 'DESCRIPTOR', 'ALIAS', '3DSECURE', 'AMOUNT'
		);

		foreach ($parameters as $key => $value) {
			if ($decode) {
				// Only sign keys that the gateway actually includes in their signature
				if (in_array($key, $expectedKeys)) {
					$clear_string .= $key . '=' . $value . $password;
				}
			} else {
				$clear_string .= $key . '=' . $value . $password;
			}
		}

		if ($debug) {
			return $clear_string;
		}
		return hash('sha256', $clear_string);
	}
}
