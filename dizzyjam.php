<?php
/**
 * PHP client for the Dizzyjam API.
 * Requires cURL
 */

/**
 * API client class
 */
class Dizzyjam {

	/**
	 * Default API endpoint URL.
	 */
	const API_URL = 'http://www.dizzyjam.com/api/v1/';

	/**
	 * API endpoint URL.
	 * @var string
	 */
	protected $api_url;

	/**
	 * API ID from authenticated requests.
	 * @var string
	 */
	protected $auth_id;

	/**
	 * API key for authenticated requests.
	 * @var string
	 */
	protected $api_key;

	/**
	 * Create a new Dizzyjam API client.
	 * @param string|null $api_url		URL of the API (optional)
	 */
	public function __construct($api_url = null) {
		$this->api_url = isset($api_url)? rtrim($api_url, '/').'/': Dizzyjam::API_URL;
	}

	/**
	 * Set API credentials for authenticated requests.
	 * @param string $auth_id			API ID
	 * @param string $api_key			API key
	 * @return self						fluent interface
	 */
	public function set_credentials($auth_id, $api_key) {
		$this->auth_id = $auth_id;
		$this->api_key = $api_key;
		return $this;
	}

	/**
	 * Sign a request.
	 * @param string $method			method to call
	 * @param array $params				parameters for the method
	 * @return array					parameters with added authentication fields
	 */
	protected function sign($method, array $params) {
		if (!(strlen($this->auth_id) && strlen($this->api_key))) {
			$details = array(
				'method' => $method,
				'params' => $params,
			);
			throw new Dizzyjam_Exception('API credentials not configured', 401, $details);
		}
		$params['auth_id'] = $this->auth_id;
		$params['auth_ts'] = time();
		unset($params['auth_sig']);
		ksort($params);
		$base = 'v1/'.$method.'?'.urldecode(http_build_query($params));
		$params['auth_sig'] = hash_hmac('sha256', $base, $this->api_key);
		return $params;
	}

	/**
	 * Perform a generic API request.
	 * @param string $method			method to call
	 * @param array|null $params		parameters for the method
	 * @param boolean $signed			sign the request
	 * @return array					response
	 * @throws Dizzyjam_Exception		in case of failure
	 */
	public function request($method, array $params = array(), $signed = false) {
		$uploads = array();
		foreach ($params as $name => $value) {
			if ($value instanceof Dizzyjam_File) {
				$uploads[$name] = '@'.$value->get_path();
				unset($params[$name]);
			}
		}
		if ($signed) {
			$params = $this->sign($method, $params);
		}

		$ch = curl_init($this->api_url.$method.'.json?'.http_build_query($params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if (count($uploads)) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $uploads);
		}
		$json = curl_exec($ch);
		$info = curl_getinfo($ch);
		curl_close($ch);

		if (!is_null($json)) {
			$response = json_decode($json, true);
			if (is_null($response)) {
				throw new Dizzyjam_Exception('Unparsable API response', 500, array('response' => $json));
			}
			if ($response['success']) {
				return $response;
			}
			throw new Dizzyjam_Exception($response['error'], $response['errorCode'], $response['errorDetails']);
		}

		$details = array(
			'url' => $info['url'],
			'http_status' => $info['http_code'],
		);
		throw new Dizzyjam_Exception('HTTP request failed', $details);
	}

	/**
	 * Return an API method group.
	 * @param string $group				API method group name
	 * @return Dizzyjam_Group			instance of the API method group
	 */
	public function __get($group) {
		static $instances = array();
		if (isset($instances[$group])) {
			return $instances[$group];
		}
		$class = 'Dizzyjam_Group_'.$group;
		if (!class_exists($class)) {
			$details = array(
				'group' => $group,
			);
			throw new Dizzyjam_Exception('Unsupported API method group', 400, $details);
		}
		$instances[$group] = new $class($this);
		return $instances[$group];
	}

}

/**
 * Abstract API method group class.
 */
abstract class Dizzyjam_Group {

	/**
	 * Dizzyjam API client instance.
	 * @var Dizzyjam
	 */
	protected $api;

	/**
	 * Instantiate the API method group.
	 * @param Dizzyjam $api
	 */
	public function __construct(Dizzyjam $api) {
		$this->api = $api;
	}

}

/**
 * Catalogue API methods.
 */
class Dizzyjam_Group_Catalogue extends Dizzyjam_Group {

	/**
	 * List all stores.
	 * @param int|null $count 		number of stores to list (optional; default: all stores)
	 * @param int|null $start		offset to start listing stores from (optional; 0-based, default: 0)
	 * @return array				API response
	 */
	public function stores($count = null, $start = null) {
		$params = array(
			'count'	=> $count,
			'start'	=> $start,
		);
		return $this->api->request('catalogue/stores', $params);
	}

	/**
	 * Get details for a store.
	 * @param string $store_id				alphanumeric ID of the store (required)
	 * @param string|null $country			2-char country code for shipping costs (optional; default: 'gb')
	 * @param string|null $embed_settings	show embed shop settings in the response (optional; default: 'no')
	 * @param int|null $count 				number of store products to list (optional; default: all products)
	 * @param int|null $start				offset to start listing products from (optional; 0-based, default: 0)
	 * @return array						API response
	 */
	public function store_info($store_id, $country = null, $embed_settings = null, $count = null, $start = null) {
		$params = array(
			'store_id'		 => $store_id,
			'country'		 => $country,
			'embed_settings' => $embed_settings,
			'count'			 => $count,
			'start'			 => $start,
		);
		return $this->api->request('catalogue/store_info', $params);
	}

	/**
	 * Get details for a product.
	 * @param int $product_id		numeric ID of the product (required)
	 * @param string|null $country	2-char country code for shipping costs (optional; default: 'gb')
	 * @return array				API response
	 */
	public function product_info($product_id, $country = null) {
		$params = array(
			'product_id'	=> $product_id,
			'country'		=> $country,
		);
		return $this->api->request('catalogue/product_info', $params);
	}

}

/**
 * Order API methods.
 */
class Dizzyjam_Group_Order extends Dizzyjam_Group {

	/**
	 * Calculate prices, shipping fees and totals for an order.
	 * @param Dizzyjam_Cart $cart	shopping cart with items to order (required)
	 * @param string|null $country	2-char country code for shipping costs (optional; default: 'gb')
	 */
	public function calculate(Dizzyjam_Cart $cart, $country = null) {
		$params = $cart->item_params();
		$params['country'] = $country;
		return $this->api->request('order/calculate', $params);
	}

	/**
	 * Test checkout (order will not be placed).
	 * @param Dizzyjam_Order $order order details (required)
	 * @return array				API response
	 */
	public function test_checkout(Dizzyjam_Order $order) {
		$item_params = $order->item_params();
		$order_params = $order->order_params();
		$params = array_merge($item_params, $order_params);
		$params['checkout'] = 0;
		return $this->api->request('order/checkout', $params, true);
	}

	/**
	 * Real checkout (order WILL be placed).
	 * @param Dizzyjam_Order $order order details (required)
	 * @param string $return_url	URL to return to after order is placed (required)
	 * @param boolean $mobile		request payment on Paypal's mobile site (optional)
	 * @return array				API response
	 */
	public function checkout(Dizzyjam_Order $order, $return_url, $mobile = false) {
		$item_params = $order->item_params();
		$order_params = $order->order_params();
		$params = array_merge($item_params, $order_params);
		$params['mobile'] = $mobile;
		$params['return_url'] = $return_url;
		$params['checkout'] = 1;
		return $this->api->request('order/checkout', $params, true);
	}

}

/**
 * Shopping cart class, holding the items for an order.
 */
class Dizzyjam_Cart implements Countable {

	/**
	 * Number of items in cart.
	 * @var int
	 */
	protected $item_count = 0;

	/**
	 * API parameters for the cart items.
	 * @var array
	 */
	protected $items = array();

	/**
	 * Add an item to the cart.
	 * @param int $product_id		numeric ID of the product (required)
	 * @param string $colour_id		colour ID (required)
	 * @param string $size			size (required)
	 * @param int $quantity			quantity to order (required)
	 * @return self					fluent interface
	 */
	public function add_item($product_id, $colour_id, $size, $quantity) {
		$this->item_count++;
		$prefix = 'item'.$this->item_count.'_';
		$this->items[$prefix.'product_id']	= $product_id;
		$this->items[$prefix.'colour_id']	= $colour_id;
		$this->items[$prefix.'size']		= $size;
		$this->items[$prefix.'quantity']	= $quantity;
		return $this;
	}

	/**
	 * Clear items in the order.
	 * @return self					fluent interface
	 */
	public function clear_items() {
		$this->items = array();
		$this->item_count = 0;
		return $this;
	}

	/**
	 * Return number of items in the cart.
	 * @return int
	 */
	public function count() {
		return $this->item_count;
	}

	/**
	 * Return API request parameters for the cart items.
	 * @return array
	 */
	public function item_params() {
		return $this->items;
	}

	/**
	 * Calculate the details for the order via an API call.
	 * @param Dizzyjam $api			API client
	 * @param string|null			2-char country code for shipping costs (optional; default: 'gb')
	 * @return array
	 */
	public function calculate(Dizzyjam $api, $country = null) {
		return $api->order->calculate($this, $country);
	}

}

/**
 * Order details class.
 */
class Dizzyjam_Order extends Dizzyjam_Cart {

	/**
	 * API parameters for the order details.
	 * @var array
	 */
	protected $details = array();

	/**
	 * Instantiate an order
	 * @param string $name			name of the recipient (required)
	 * @param string $email			email of the recipient (required)
	 * @param string $country		2-char country code (required)
	 * @param string $postcode		postcode (required)
	 * @param string $region		region/state/province (required)
	 * @param string $city			city (required)
	 * @param string $address1		first line of street address (required)
 	 * @param string $address2		second line of street address (optional)
	 */
	public function __construct($name, $email, $country, $postcode, $region, $city, $address1, $address2 = null) {
		$this->details['name']		= $name;
		$this->details['email']		= $email;
		$this->details['country']	= $country;
		$this->details['postcode']	= $postcode;
		$this->details['region']	= $region;
		$this->details['city']		= $city;
		$this->details['address_1']	= $address1;
		$this->details['address_2']	= $address2;
	}

	/**
	 * Return API request parameters for the order details.
	 * @return array
	 */
	public function order_params() {
		return $this->details;
	}

	/**
	 * Calculate the details for the order via an API call.
	 * @param Dizzyjam $api			API client (required)
	 * @param string|null $country	2-char country code (optional)
	 * @return array
	 */
	public function calculate(Dizzyjam $api, $country = null) {
		return parent::calculate($api, is_null($country)? $this->details['country']: $country);
	}

	/**
	 * Test checkout (order will not be placed).
	 * @param Dizzyjam $api			API client (required)
	 * @return array				API response
	 */
	public function test_checkout(Dizzyjam $api) {
		return $api->order->test_checkout($this);
	}

	/**
	 * Real checkout (order WILL be placed).
	 * @param Dizzyjam $api			API client (required)
	 * @param string $return_url	URL to return to after order is placed (required)
	 * @param boolean $mobile		request payment on Paypal's mobile site (optional)
	 * @return array				API response
	 */
	public function checkout(Dizzyjam $api, $return_url, $mobile = false) {
		return $api->order->checkout($this, $return_url, $mobile);
	}

}

/**
 * Manage API methods.
 */
class Dizzyjam_Group_Manage extends Dizzyjam_Group {

	/**
	 * List sub-users of the API account.
	 * Your API key needs to have permission to manage sub-users for your account.
	 * @param int|null $count 		number of sub-users to list (optional; default: all sub-users)
	 * @param int|null $start		offset to start listing sub-users from (optional; 0-based, default: 0)
	 * @return array 				API response
	 */
	public function my_users($count = null, $start = null) {
		$params = array(
			'count'	=> $count,
			'start'	=> $start,
		);
		return $this->api->request('manage/my_users', $params, true);
	}

	/**
	 * Create a new sub-user under the API account.
	 * Your API key needs to have permission to manage sub-users for your account.
	 * @param string $email			email address of the sub-user (required; used for logging in)
	 * @param string|null $name		full name of the sub-user (optional)
	 * @param string|null $password password for the sub-user (optional; a random password will be generated and returned in the response if not provided)
	 * @return array				API response
	 */
	public function create_user($email, $name = null, $password = null) {
		$params = array(
			'email'		=> $email,
			'name'		=> $name,
			'password'	=> $password,
		);
		return $this->api->request('manage/create_user', $params, true);
	}

	/**
	 * List stores that can be managed by the API account.
	 * @param int|null $count 		number of stores to list (optional; default: all stores)
	 * @param int|null $start		offset to start listing stores from (optional; 0-based, default: 0)
	 * @param int|null $user_id		list only stores owned by this sub-user (optional; default: list all stores; if 0, list only stores NOT belonging to sub-users)
	 * @return array				API response
	 */
	public function my_stores($count = null, $start = null, $user_id = null) {
		$params = array(
			'count'		=> $count,
			'start'		=> $start,
			'user_id'	=> $user_id,
		);
		return $this->api->request('manage/my_stores', $params, true);
	}

	/**
	 * Return metadata necessary for creating a new store.
	 * @return array				API response
	 */
	public function store_options() {
		return $this->api->request('manage/store_options', array(), true);
	}

	/**
	 * Create a new store.
	 * Your API key must not be a single-store key. It also must have permission to manage sub-users, if you
	 * wish to provide a $user_id parameter to assign the store to a sub-user account.
	 * @param string $store_id			alphanumeric unique identifier for the store (required; new store will be http://$store_id.dizzyjam.com)
	 * @param string $name				name of the store (required)
	 * @param string $description		description of the store (required)
	 * @param string|null $logo_file	path to image file to upload as store logo (optional)
	 * @param string|null $embed_shop	prepare and create an embed store (optional, values: "yes" or "no")
	 * @param array $genres				list of numeric genre IDs (optional; valid values available via $api->manage->store_options())
	 * @param string|null $website		URL of the store's website (optional, but see note below)
	 * @param string|null $myspace_url	URL of the store's MySpace page (optional, but see note below)
	 * @param string|null $facebook_url	URL of the store's Facebook page (optional, but see note below)
	 * @param string|null $twitter_id	twitter username (optional, but see note below)
	 * @param string|null $rss_feed_url	URL of a RSS feed (optional)
	 * @param int|null $user_id			sub-user who will be owner of the store (optional)
	 * @return array					API response
	 * At least one of the $website, $myspace_url, $facebook_url or $twitter_id parameters is required.
	 */
	public function create_store($store_id, $name, $description, $logo = null, $embed_shop = null, array $genres = array(), $website = null, $myspace_url = null, $facebook_url = null, $twitter_id = null, $rss_feed_url = null, $user_id = null) {
		$params = array(
			'store_id'		=> $store_id,
			'name'			=> $name,
			'description'	=> $description,
			'logo_file'		=> strlen($logo)? new Dizzyjam_File($logo): null,
			'embed_shop'	=> $embed_shop,
			'genres'		=> count($genres)? join(',', $genres): null,
			'website'		=> $website,
			'myspace_url'	=> $myspace_url,
			'facebook_url'	=> $facebook_url,
			'twitter_id'	=> $twitter_id,
			'rss_feed_url'	=> $rss_feed_url,
			'user_id'		=> $user_id,
		);
		return $this->api->request('manage/create_store', $params, true);
	}

	/**
	 * Edit store details.
	 * @param string $store_id			alphanumeric ID of the store (required)
	 * @param string|null $name			name of the store (optional)
	 * @param string|null $description	description of the store (optional)
	 * @param string|null $logo_file	path to image file to upload as store logo (optional)
	 * @param string|null $embed_shop	prepare and create an embed store (optional, values: "yes" or "no")
	 * @param array|null $genres		list of numeric genre IDs (optional; valid values available via $api->manage->store_options())
	 * @param string|null $website		URL of the store's website (optional, but see note below)
	 * @param string|null $myspace_url	URL of the store's MySpace page (optional, but see note below)
	 * @param string|null $facebook_url	URL of the store's Facebook page (optional, but see note below)
	 * @param string|null $twitter_id	twitter username (optional, but see note below)
	 * @param string|null $rss_feed_url	URL of a RSS feed (optional)
	 * @return array					API response
	 * Passing a null value will preserve the current value of the corresponding field.
	 * Passing an empty string will clear the corresponding field; this is not allowed for $name and $description.
	 * You can pass either an empty string or an empty array for $genres if you wish to clear the store genres.
	 * At least one of the $website, $myspace_url, $facebook_url or $twitter_id fields must remain uncleared
	 * in the store record; i.e. if a store has only a website set, you cannot pass $website = '' to clear it,
	 * but you may pass $website = '', $myspace_url = 'http://...' to clear the website and set a MySpace URL
	 * in the same request.
	 */
	public function edit_store($store_id, $name, $description, $logo = null, $embed_shop = null, $genres = null, $website = null, $myspace_url = null, $facebook_url = null, $twitter_id = null, $rss_feed_url = null) {
		$params = array(
			'store_id'		=> $store_id,
			'name'			=> $name,
			'description'	=> $description,
			'embed_shop'	=> $embed_shop,
		);
		$clearable_params = array(
			'logo_file'		=> strlen($logo)? new Dizzyjam_File($logo): $logo,
			'genres'		=> is_array($genres)? join(',', $genres): $genres,
			'website'		=> $website,
			'myspace_url'	=> $myspace_url,
			'facebook_url'	=> $facebook_url,
			'twitter_id'	=> $twitter_id,
			'rss_feed_url'	=> $rss_feed_url,
		);
		$clear = array();
		foreach ($clearable_params as $name => $value) {
			if (!is_null($value) && ($value === '')) {
				$clear[] = ($value == 'logo_file')? 'logo': $name;
				unset($clearable_params[$name]);
			}
		}
		$params['clear'] = join(',', $clear);
		$params = array_merge($params, $clearable_params);

		return $this->api->request('manage/edit_store', $params, true);
	}

	/**
	 * Delete a store.
	 * @param string $store_id		alphanumeric ID of the store (required)
	 * @return array				API response
	 */
	public function delete_store($store_id) {
		$params = array(
			'store_id' => $store_id,
		);
		return $this->api->request('manage/delete_store', $params, true);
	}

	/**
	 * Return metadata necessary for creating a new product.
	 * @param string $store_id		alphanumeric ID of the store where the product will be added (required)
	 * @return array				API response
	 */
	public function product_options($store_id) {
		$params = array(
			'store_id' => $store_id,
		);
		return $this->api->request('manage/product_options', $params, true);
	}

	/**
	 * Upload a new design.
	 * @param string $store_id			alphanumeric ID of the store where the product will be added (required)
	 * @param string $design			an image file to upload as new design (required)
	 * @return array					API response
	 */
	public function upload_design($store_id, $design) {
		$params = array(
			'store_id'			=> $store_id,
			'design_file'       => new Dizzyjam_File($design)
		);
		return $this->api->request('manage/upload_design', $params, true);
	}

	/**
	 * Create a new product.
	 * @param string $store_id			alphanumeric ID of the store where the product will be added (required)
	 * @param string $name				name for the product (required)
	 * @param int|string $design		numeric ID of an existing design, or image file to upload as new design (required)
	 * @param int|null $product_type_id	numeric ID of the product type (optional; products of all types will be created if null)
	 * @param string|null $process		print process for the product (optional; products for all print processes will be created if null)
	 * @param array $colours			list of numeric colour IDs to make available for the product (optional; all colours will be created if null OR if either $product_type_id or $process is null)
	 * @param int|null $featured_colour	numeric ID of the colour which is displayed by default (required if all of $product_type_id, $process and $colours are provided; if any of them is null, the first available colour will be made featured)
	 * @param int|null $scale			scale of the design in percentage of the maximum possible print size (optional; default: 100; min: 10, max: 100)
	 * @param int|null $angle			angle of the design within the available print area (optional: default: 0 (= normal); min: -180, max: 180)
	 * @param int|null $horiz			horizontal position of the design within the available print area (optional: default: 0 (= center); min: -100 (= flush left), max: 100 (= flush right))
	 * @param int|null $vert			vertical position of the design within the available print area (optional: default: 0 (= center); min: -100 (= flush top), max: 100 (= flush bottom))
	 * @return array					API response
	 */
	public function create_product($store_id, $name, $design, $product_type_id, $process, array $colours, $featured_colour = null, $scale = null, $angle = null, $horiz = null, $vert = null) {
		$params = array(
			'store_id'			=> $store_id,
			'name'				=> $name,
			'product_type_id'	=> is_null($product_type_id)? 'all': $product_type_id,
			'process'			=> is_null($process)? 'all': $process,
			'colours'			=> count($colours)? join(',', $colours): 'all',
			'featured_colour'	=> $featured_colour,
			'scale'				=> $scale,
			'angle'				=> $angle,
			'horiz'				=> $horiz,
			'vert'				=> $vert,
		);
		if (is_int($design)) {
			$params['design_id'] = $design;
		} else {
			$params['design_file'] = new Dizzyjam_File($design);
		}
		return $this->api->request('manage/create_product', $params, true);
	}

	/**
	 * Edit product details.
	 * @param int $product_id			numeric ID of the product (required)
	 * @param string|null $name			new name for the product (optional; will not change name if null or empty string is passed)
	 * @param array $colours			list of numeric colour IDs to make available for the product (optional; will not change colours if empty array is passed)
	 * @param int|null $featured_colour	numeric ID of the colour which is displayed by deafult (optional; will not change featured colour if null or empty string id passed)
	 * @return array					API response
	 */
	public function edit_product($product_id, $name = null, array $colours = array(), $featured_colour = null) {
		$params = array(
			'product_id'		=> $product_id,
			'name'				=> $name,
			'colours'			=> join(',', $colours),
			'featured_colour'	=> $featured_colour,
		);
		return $this->api->request('manage/edit_product', $params, true);
	}

	/**
	 * Delete a product.
	 * @param int $product_id			numeric ID of the product (required)
	 * @return array					API response
	 */
	public function delete_product($product_id) {
		$params = array(
			'product_id' => $product_id,
		);
		return $this->api->request('manage/delete_product', $params, true);
	}

	/**
	 * Delete a product design.
	 * @param int $design_id			numeric ID of the design (required)
	 * @return array					API response
	 */
	public function delete_design($design_id) {
		$params = array(
			'design_id' => $design_id,
		);
		return $this->api->request('manage/delete_design', $params, true);
	}

}

/**
 * File upload wrapper class.
 */
class Dizzyjam_File {

	/**
	 * Path to the file to upload.
	 * @var string
	 */
	protected $path;

	/**
	 * Wrap a file in an instance.
	 * @param string $path			path to the file to upload
	 * @throws Dizzyjam_Excepiton	if file does not exist, or is not an actual file, or is unreadable
	 */
	public function __construct($path) {
		if (!strlen($path)) {
			throw new Dizzyjam_Exception('Missing filename', 400);
		}
		$details = array(
			'path' => $path,
		);
		if (!file_exists($path)) {
			throw new Dizzyjam_Exception('File does not exist', 400, $details);
		}
		if (!is_file($path)) {
			throw new Dizzyjam_Exception('Not a file', 400, $details);
		}
		if (!is_readable($path)) {
			throw new Dizzyjam_Exception('File is not readable', 400, $details);
		}
		$this->path = $path;
	}

	/**
	 * Get the path to the file to upload.
	 * @return string
	 */
	public function get_path() {
		return $this->path;
	}

}

/**
 * Exception class.
 */
class Dizzyjam_Exception extends Exception {

	/**
	 * Error details.
	 * @var mixed
	 */
	protected $details;

	/**
	 * Construct a new exception.
	 * @param string $error
	 * @param int $code
	 * @param mixed $details
	 */
	public function __construct($error, $code, $details) {
		parent::__construct($error, $code);
		$this->details = $details;
	}

	/**
	 * Return error details.
	 * @return mixed
	 */
	public function getDetails() {
		return $this->details;
	}

}
