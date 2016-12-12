<?php 
	// TODO: handling request errors

	$params = require(__DIR__ . '/config.php');

	$curl = curl_init();
	$SID = getSID();

	$categories = getCategories();
	$neededCategories = getNeededCategories($params['neededСategoriesFile']);

	// write first line with column names to csv
	// TODO: remove when format will be approved
	file_put_contents($params['resultFile'], "name^ID^category^price^available^brief_description^description^options^image"."\n"  , FILE_APPEND | LOCK_EX);


	// go through all categories, if it needed get all category products and go through them
	// then get product by ID`s and write data into csv file
	foreach ($categories as $category){
		$need = in_array($category['categoryID'], $neededCategories);
		if($need) {
			$shortProducts = getProductsByCategory($category['categoryID']);
			$categoryPath = getCategoryPath($category, null);

			foreach ($shortProducts as $shortProduct) {
				$product = getProductByID($shortProduct['productID']);
				writeProductIntoFile($product, $categoryPath);
			}
			echo ':';
		}

	}

	echo 'Done! Products data waiting for you';
	curl_close($curl); 

	/*
	 * Read needed categories from file
	 *
	 * @param string $fileName
	 * @return array
	 */
	function getNeededCategories($fileName){
		return file($fileName, FILE_IGNORE_NEW_LINES);
	}

	/*
	 * Recursive function which returns breadcrumbs of category 
	 * 
	 * @param array $category 
	 * @param string $path 
	 * @return string
	 */
	function getCategoryPath($category, $path){
		global $categories;

		if($path == null){ //if call first time
			$path = $category['name'];

			if($category['parentID'] == '1'){ //if higher order category 
				return $path;
			}else{
				foreach ($categories as $key => $val) {
					if($val['categoryID'] == $category['parentID']){
						return getCategoryPath($categories[$key], $path);
					}
				}
			}
		}else{
			if($category['parentID'] == '1'){
				return $category['name'].'/'.$path;
			}else{
				foreach ($categories as $key => $val) {
					if($val['categoryID'] == $category['parentID']){
						return getCategoryPath($categories[$key], $category['name'].'/'.$path);
					}
				}
			}
		}	
	}


	/*
	 * Write product data into file line by line 
	 *
	 * @param array $product 
	 * @param string $categoryPath 
	 * @return void
	 */
	function writeProductIntoFile($product, $categoryPath){
		global $params;

		$sep = $params['csvSeparator'];

		$available = json_encode(!!count($product["available"])); //available if array not empty
		$options = json_encode($product["options"], JSON_UNESCAPED_UNICODE);

		$str = $product["name"].$sep.
		$product["productID"].$sep.
		$categoryPath.$sep.
		$product["retail_price_uah"].$sep.
		$available.$sep.
		str_replace(chr(10),'<br>',$product["brief_description"]).$sep. //replace line breaks
		str_replace(chr(10),'<br>',$product["description"]).$sep.
		$options.$sep.
		$product["large_image"];

		file_put_contents($params['resultFile'],  $str."\n", FILE_APPEND | LOCK_EX);
	}

	/*
	 * Get product data by id
	 *
	 * @param integer $productID
	 * @return array
	 */
	function getProductByID($productID){
		global $curl;
		global $SID;

		$URL = 'http://api.brain.com.ua/product/'.$productID.'/'.$SID;

		curl_setopt($curl, CURLOPT_URL, $URL); 
		curl_setopt($curl, CURLOPT_FAILONERROR, 1);  
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = json_decode(curl_exec($curl), true);
		curl_reset($curl);

		return $result['result'];
	}

	/*
	 * Get all categories
	 *
	 * @return array
	 */
  	function getCategories(){
  		global $curl;
  		global $SID;

		$URL = 'http://api.brain.com.ua/categories/'.$SID;

		curl_setopt($curl, CURLOPT_URL, $URL); 
		curl_setopt($curl, CURLOPT_FAILONERROR, 1);  
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = json_decode(curl_exec($curl), true);
		curl_reset($curl);

		if($result["status"] == 1){ 
			return $result["result"];
		}else return false;
  	}

	/*
	 * Get all products with category id
	 *
	 * @param integer $categoryID
	 * @return array
	 */
	function getProductsByCategory($categoryID){
  		global $curl;
  		global $SID;

		$URL = 'http://api.brain.com.ua/products/'.$categoryID.'/'.$SID;

		curl_setopt($curl, CURLOPT_URL, $URL); 
		curl_setopt($curl, CURLOPT_FAILONERROR, 1);  
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

		$result = json_decode(curl_exec($curl),true); 

		return $result['result']['list'];
	}


	/*
	 * Get session ID
	 *
	 * @return string
	 */
  	function getSID(){
  		global $params;
  		global $curl;

		$URL = 'http://api.brain.com.ua/auth';

		$authParams = [
		    'login' => $params['login'],
		    'password' => md5($params['pass']) //Brain requires transmitting passwords in md5
		];

		curl_setopt($curl, CURLOPT_URL, $URL);
		curl_setopt($curl, CURLOPT_FAILONERROR, 1);  
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $authParams);

		$result = json_decode(curl_exec($curl), true);
		curl_reset($curl);

		if($result["status"] == 1){
			return $result["result"];
		}
  	}


	/*
	 * Utility function which write all categories data into file line by line
	 * Not used
	 *
	 * @return void
	 */
	function writeCategoriesIntoFile($categories){
		foreach ($categories["result"] as $category) {
			$file = 'categories.txt';
			$shortStr = "(categoryID): ".$category["categoryID"]."   (name): ".$category["name"]."\n";
			$fullStr = "(categoryID): ".$category["categoryID"]."   (name): ".$category["name"]."   (parentID): ".$category["parentID"]."   (realcat): ".$category["parentID"]."\n";
			file_put_contents($file, $shortStr, FILE_APPEND | LOCK_EX);
		}
	}
?>

