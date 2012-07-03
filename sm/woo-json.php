<?php 
if ( ! defined('ABSPATH') ) {
	include_once ('../../../../wp-load.php');
}

$mem_limit = ini_get('memory_limit');
if(intval(substr($mem_limit,0,strlen($mem_limit)-1)) < 64 ){
	ini_set('memory_limit','128M'); 
}

$result = array ();
$encoded = array ();

$offset = (isset ( $_POST ['start'] )) ? $_POST ['start'] : 0;
$limit = (isset ( $_POST ['limit'] )) ? $_POST ['limit'] : 100;

// For pro version check if the required file exists
if (file_exists ( WP_CONTENT_DIR . '/plugins/smart-manager-for-wp-e-commerce/pro/woo.php' )) {
	define ( 'SMPRO', true );
	include_once (WP_CONTENT_DIR . '/plugins/smart-manager-for-wp-e-commerce/pro/woo.php');
} else {
	define ( 'SMPRO', false );
}

// getting the active module
$active_module = $_POST ['active_module'];

function get_data_woo ( $_POST, $offset, $limit, $is_export = false ) {
	global $wpdb, $woocommerce, $post_status, $parent_sort_id, $order_by, $post_type, $variation_name, $from_variation, $parent_name;
	
	// getting the active module
	$active_module = $_POST ['active_module'];
	
	if ( SMPRO == true ) variation_query_params ();
	
	// Restricting LIMIT for export CSV
	if ( $is_export === true ) {
		$limit_string = "";
	} else {
		$limit_string = "LIMIT $offset,$limit";
	}
	
	$view_columns = json_decode ( stripslashes ( $_POST ['viewCols'] ) );
	if ($active_module == 'Products') { // <-products
		$woo_default_image = WP_PLUGIN_URL . '/smart-reporter-for-wp-e-commerce/resources/themes/images/woo_default_image.png';
		if (isset ( $_POST ['incVariation'] ) && $_POST ['incVariation'] === 'true' && SMPRO == true) {
			$show_variation = true;
		} else {
			$from_variation = '';
			$variation_name = '';
			$parent_name = '';
			$post_status = "('publish', 'draft')";
			$post_type = "('product')";
			$parent_sort_id = '';
			$order_by = " ORDER BY products.id desc";
			$show_variation = false;
		}
		
		// if max-join-size issue occurs
		$query = "SET SQL_BIG_SELECTS=1;";
		$wpdb->query ( $query );

		$select = "SELECT SQL_CALC_FOUND_ROWS products.id,
					products.post_title,
					products.post_content,
					products.post_excerpt,
					products.post_status,
					products.post_parent,
					category,
					$variation_name
					$parent_name
					GROUP_CONCAT(prod_othermeta.meta_key order by prod_othermeta.meta_id SEPARATOR '###') AS prod_othermeta_key,
					GROUP_CONCAT(prod_othermeta.meta_value order by prod_othermeta.meta_id SEPARATOR '###') AS prod_othermeta_value,
					prod_meta.meta_value as prod_meta
					$parent_sort_id";

		if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_on = $wpdb->_real_escape ( trim ( $_POST ['searchText'] ) );
			$search_ons = explode( ' ', $search_on );
			if ( is_array( $search_ons ) ) {	
				$search_condn = " HAVING ";
				foreach ( $search_ons as $search_on ) {
					$search_condn .= " concat(' ',REPLACE(REPLACE(post_title,'(',''),')','')) LIKE '%$search_on%'
						               OR post_content LIKE '%$search_on%'
						               OR post_excerpt LIKE '%$search_on%'
						               OR if(post_status = 'publish','Published',post_status) LIKE '$search_on%'
									   OR prod_othermeta_value LIKE '%$search_on%'
									   OR category LIKE '%$search_on%'
							           ";
					
					( $show_variation == true ) ? $search_condn .= " OR variation_name LIKE '%$search_on%' " : '';
					$search_condn .= " OR";
				}
				$search_condn = substr( $search_condn, 0, -2 );
			} else {
				$search_condn = " HAVING concat(' ',REPLACE(REPLACE(post_title,'(',''),')','')) LIKE '%$search_on%'
					               OR post_content LIKE '%$search_on%'
					               OR post_excerpt LIKE '%$search_on%'
					               OR if(post_status = 'publish','Published',post_status) LIKE '$search_on%'
								   OR prod_othermeta_value LIKE '%$search_on%'
								   OR category LIKE '%$search_on%'
						           ";
					
				( $show_variation == true ) ? $search_condn .= " OR variation_name LIKE '%$search_on%' " : '';
			}
		}

		$from_where = "FROM {$wpdb->prefix}posts as products
						LEFT JOIN {$wpdb->prefix}postmeta as prod_othermeta ON (prod_othermeta.post_id = products.id and
						prod_othermeta.meta_key IN ('_regular_price','_sale_price','_sale_price_dates_from','_sale_price_dates_to','_sku','_stock','_weight','_height','_length','_width','_price','_thumbnail_id') )
						
						LEFT JOIN {$wpdb->prefix}postmeta as prod_meta ON (prod_meta.post_id = products.id and
						prod_meta.meta_key = '_product_attributes')
						
						$from_variation
						
						LEFT JOIN 
						(SELECT GROUP_CONCAT(wt.name) as category, wtr.object_id
						FROM  {$wpdb->prefix}term_relationships AS wtr  	 
						JOIN {$wpdb->prefix}term_taxonomy AS wtt ON (wtr.term_taxonomy_id = wtt.term_taxonomy_id and taxonomy = 'product_cat')
												
						JOIN {$wpdb->prefix}terms AS wt ON (wtt.term_id = wt.term_id)
						group by wtr.object_id) as prod_categories on (products.id = prod_categories.object_id OR products.post_parent = prod_categories.object_id)
						
						WHERE products.post_status IN $post_status
						AND products.post_type IN $post_type";

		$group_by = " GROUP BY products.id ";
		
		$query = "$select $from_where $group_by $search_condn $order_by $limit_string;";
		$records = $wpdb->get_results ( $query );
		$num_rows = $wpdb->num_rows;
		
		$recordcount_query = "SELECT FOUND_ROWS() AS count;";
		$recordcount_result = $wpdb->get_results ( $recordcount_query, 'ARRAY_A' );
		$num_records = $recordcount_result [0] ['count'];
		
		if ($num_rows <= 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = 'No Records Found';
		} else {
			
			for ($i = 0; $i < $num_rows; $i++){
				$prod_meta_values = explode ( '###', $records[$i]->prod_othermeta_value );
				$prod_meta_key    = explode ( '###', $records[$i]->prod_othermeta_key);
				if ( count($prod_meta_values) != count($prod_meta_key) ) continue;
				unset ( $records[$i]->prod_othermeta_value );
				unset ( $records[$i]->prod_othermeta_key );
				$prod_meta_key_values = array_combine ( $prod_meta_key, $prod_meta_values );
				$records[$i]->category = ( $records[$i]->post_parent == 0 ) ? $records[$i]->category : '';			// To hide category name from Product's variations
				
				if(isset($prod_meta_key_values['_sale_price_dates_from']) && !empty($prod_meta_key_values['_sale_price_dates_from']))
					$prod_meta_key_values['_sale_price_dates_from'] = date('Y-m-d',(int)$prod_meta_key_values['_sale_price_dates_from']);
				if(isset($prod_meta_key_values['_sale_price_dates_to']) && !empty($prod_meta_key_values['_sale_price_dates_to']))
					$prod_meta_key_values['_sale_price_dates_to'] = date('Y-m-d',(int)$prod_meta_key_values['_sale_price_dates_to']);
				
				if (is_serialized($records[$i]->prod_meta)){
					$unsez_data[$i] = unserialize($records[$i]->prod_meta);
					$records[$i]    = array_merge((array)$records[$i],(array)$unsez_data[$i]);
				}
				$records[$i] = array_merge((array)$records[$i],$prod_meta_key_values);
				$thumbnail = isset( $records[$i]['_thumbnail_id'] ) ? wp_get_attachment_image_src( $records[$i]['_thumbnail_id'], 'admin-product-thumbnails' ) : '';
				$records[$i]['thumbnail'] = ( $thumbnail[0] != '' ) ? $thumbnail[0] : '';
				if ( $show_variation === true && $records[$i]['post_parent'] != 0 ) {
					$records[$i]['_regular_price'] = $records[$i]['_price'];
					$records[$i]['post_title'] = $records[$i]['parent_name'] . " - " . $records[$i]['variation_name'];
				}
				unset ( $records[$i]->prod_othermeta_value );
				unset ( $records[$i]->prod_meta );
				unset ( $records[$i]->prod_othermeta_key );
			}
		}
	} elseif ($active_module == 'Customers') {
		//BOF Customer's module
			if (SMPRO == true) {
				$search_condn = customers_query ( $_POST ['searchText'] );
			}
			$customers_query = "SELECT SQL_CALC_FOUND_ROWS posts.ID as id,
									date_format(posts.post_date,'%b %e %Y, %r') AS date,
									GROUP_CONCAT( postmeta.meta_value 
										ORDER BY postmeta.meta_id
										SEPARATOR '###' ) AS meta_value,
									GROUP_CONCAT(distinct postmeta.meta_key
										ORDER BY postmeta.meta_id 
										SEPARATOR '###' ) AS meta_key
								
								FROM {$wpdb->prefix}posts AS posts 
										RIGHT JOIN {$wpdb->prefix}postmeta AS postmeta 
												ON (posts.ID = postmeta.post_id AND postmeta.meta_key IN 
																					('_billing_first_name' , '_billing_last_name' , '_billing_email',
																					'_billing_address_1', '_billing_address_2', '_billing_city', '_billing_state',
																					'_billing_country','_billing_postcode', '_billing_phone', '_order_total'))";
				
	
			$where = " WHERE posts.post_type LIKE 'shop_order' 
								AND posts.post_status IN ('publish')";
			
			$group_by    = " GROUP BY posts.ID";
					
			$limit_query = " ORDER BY posts.ID DESC $limit_string ;";
			
		$query    	 = "$customers_query $where $group_by $search_condn $limit_query;";
		$result   	 =  $wpdb->get_results ( $query, 'ARRAY_A' );
		$num_rows 	 =  $wpdb->num_rows;
		
		//To get Total count
		$customers_count_result = $wpdb->get_results ( 'SELECT FOUND_ROWS() as count;','ARRAY_A');
		$num_records = $customers_count_result[0]['count'];

		if ($num_records == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = 'No Records Found';
		} else {
			foreach ( ( array ) $result as $data ) {
				$meta_value = explode ( '###', $data ['meta_value'] );
				$meta_key = explode ( '###', $data ['meta_key'] );
				
				//note: while merging the array, $data as to be the second arg
				if (count ( $meta_key ) == count ( $meta_value )) {
					$postmeta = array_combine ( $meta_key, $meta_value );
					
					if (SMPRO == true) {
						$data ['last_order'] = $data ['date']/* . ', ' . $data ['Last_Order_Amt']*/;
					}else{
						$data ['_order_total'] = 'Pro only';
						$data ['last_order'] = 'Pro only';
					}
					$data ['_billing_address'] = isset($postmeta ['_billing_address_1']) ? $postmeta ['_billing_address_1'].', '.$postmeta ['_billing_address_2'] : $postmeta ['_billing_address_2'];
					$postmeta ['_billing_state'] = isset($woocommerce->countries->states[$postmeta ['_billing_country']][$postmeta ['_billing_state']]) ? $woocommerce->countries->states[$postmeta ['_billing_country']][$postmeta ['_billing_state']] : $postmeta ['_billing_state'];
					$postmeta ['_billing_country'] = isset($woocommerce->countries->countries[$postmeta ['_billing_country']]) ? $woocommerce->countries->countries[$postmeta ['_billing_country']] : $postmeta ['_billing_country'];
					unset($data ['date']);
					unset($data ['meta_key']);
					unset($data ['meta_value']);
					unset($postmeta ['_billing_address_1']);
					unset($postmeta ['_billing_address_2']);
					//NOTE: storing old email id in an extra column in record so useful to indentify record with emailid during updates.
					if ($postmeta['_billing_email'] != '' || $postmeta['_billing_email'] != null) {
						$records [] = array_merge ( $postmeta, $data );	
					}
					
				}
			}
			
			unset($result);
			unset($meta_value);
			unset($meta_key);
			unset($postmeta);
		}
		
	} elseif ($active_module == 'Orders') {
		$select_query = "SELECT SQL_CALC_FOUND_ROWS posts.ID as id,
								date_format(posts.post_date,'%b %e %Y, %r') AS date,
								GROUP_CONCAT( postmeta.meta_value 
								ORDER BY postmeta.meta_id
								SEPARATOR '###' ) AS meta_value,
								GROUP_CONCAT(distinct postmeta.meta_key
								ORDER BY postmeta.meta_id 
								SEPARATOR '###' ) AS meta_key,
								terms.name AS order_status
							
							FROM {$wpdb->prefix}posts AS posts 
									JOIN {$wpdb->prefix}term_relationships AS term_relationships 
											ON term_relationships.object_id = posts.ID 
									JOIN {$wpdb->prefix}term_taxonomy AS term_taxonomy 
											ON term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id 
									JOIN {$wpdb->prefix}terms AS terms 
											ON term_taxonomy.term_id = terms.term_id 
									RIGHT JOIN {$wpdb->prefix}postmeta AS postmeta 
											ON (posts.ID = postmeta.post_id AND postmeta.meta_key IN 
																				('_billing_first_name' , '_billing_last_name' , '_billing_email',
																				'_shipping_first_name', '_shipping_last_name', '_shipping_address_1', '_shipping_address_2',
																				'_shipping_city', '_shipping_state', '_shipping_country','_shipping_postcode',
																				'_shipping_method', '_payment_method', '_order_items', '_order_total',
																				'_shipping_method_title', '_payment_method_title'))";
			
	
			$group_by    = " GROUP BY posts.ID";		
			$limit_query = " ORDER BY posts.ID DESC $limit_string ;";
			
			$where = " WHERE posts.post_type LIKE 'shop_order' 
							AND posts.post_status IN ('publish','draft','auto-draft')";
			
			if (isset ( $_POST ['fromDate'] )) {
				$from_date = date('Y-m-d H:i:s',(int)strtotime($_POST ['fromDate']));
				$to_date = isset($_POST ['toDate']) ? date('Y-m-d H:i:s',((int)strtotime($_POST ['toDate']))+86399) : date('Y-m-d H:i:s',((int)strtotime($_POST ['toDate']))+86399);

				if (SMPRO == true) {
					$where .= " AND posts.post_date BETWEEN '$from_date' AND '$to_date'";
				}
			}
			
			if (SMPRO == true && isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
				$search_on = $wpdb->_real_escape ( trim ( $_POST ['searchText'] ) );
				$search_condn = " HAVING id like '$search_on%'
								  OR date like '%$search_on%'
								  OR order_status like '%$search_on%'
								 OR meta_value like '%$search_on%'";
			}
			
			
			//get the state id if the shipping state is numeric or blank
			$query    = "$select_query $where $group_by $search_condn $limit_query;";
			$results  = $wpdb->get_results ( $query,'ARRAY_A');
			//To get the total count
			$orders_count_result = $wpdb->get_results ( 'SELECT FOUND_ROWS() as count;','ARRAY_A');
			$num_records = $orders_count_result[0]['count'];
					
			if ($num_records == 0) {
				$encoded ['totalCount'] = '';
				$encoded ['items'] = '';
				$encoded ['msg'] = 'No Records Found';
			} else {			
				foreach ( $results as $data) {
					$meta_key = explode ( '###', $data ['meta_key'] );
					$meta_value = explode ( '###', $data ['meta_value'] );
					
					if(count($meta_key) == count($meta_value)){
						$postmeta = array_combine ( $meta_key, $meta_value);
						if (is_serialized($postmeta['_order_items'])) {
							$order_items = unserialize(trim($postmeta['_order_items']));
							foreach ( (array)$order_items as $order_item) {
								$data['details'] += $order_item['qty'];
								$data['products_name'] .= $order_item['name'].'('.$order_item['qty'].'), ';
							}
							isset($data['details']) ? $data['details'] .= ' items' : $data['details'] = ''; 
							$data['products_name'] = substr($data['products_name'], 0, -2);	//To remove extra comma ', ' from returned string
						} else {
							$data['details'] = 'Details';
						}
						$name_emailid [0] = "<font class=blue>". $postmeta['_billing_first_name']."</font>";
						$name_emailid [1] = "<font class=blue>". $postmeta['_billing_last_name']."</font>";
						$name_emailid [2] = "(".$postmeta['_billing_email'].")"; //email comes at 7th position.
						$data['name'] 	  = implode ( ' ', $name_emailid ); //in front end,splitting is done with this space.
	
						$data ['_shipping_address'] = $postmeta['_shipping_address_1'].', '.$postmeta['_shipping_address_2'];
						unset($data ['meta_value']);
						$postmeta ['_shipping_method'] = isset($postmeta ['_shipping_method_title']) ? $postmeta ['_shipping_method_title'] : $postmeta ['_shipping_method'];
						$postmeta ['_payment_method'] = isset($postmeta ['_payment_method_title']) ? $postmeta ['_payment_method_title'] : $postmeta ['_payment_method'];
						$postmeta ['_shipping_state'] = isset($woocommerce->countries->states[$postmeta ['_shipping_country']][$postmeta ['_shipping_state']]) ? $woocommerce->countries->states[$postmeta ['_shipping_country']][$postmeta ['_shipping_state']] : $postmeta ['_shipping_state'];
						$postmeta ['_shipping_country'] = isset($woocommerce->countries->countries[$postmeta ['_shipping_country']]) ? $woocommerce->countries->countries[$postmeta ['_shipping_country']] : $postmeta ['_shipping_country'];
						if ($postmeta['_payment_method'] != '' || $postmeta['_payment_method'] != null) {
							$records [] = array_merge ( $postmeta, $data );	
						}
					}
				}
				
				unset($meta_value);
				unset($meta_key);
				unset($postmeta);
				unset($results);
			}
	}
	$encoded ['items'] = $records;
	$encoded ['totalCount'] = $num_records;
	unset($records);
	return $encoded;
}

// Searching a product in the grid
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getData') {
	$encoded = get_data_woo ( $_POST, $offset, $limit );
	echo json_encode ( $encoded );
	unset($encoded);
}


if (isset ( $_GET ['cmd'] ) && $_GET ['cmd'] == 'exportCsvWoo') {

	$encoded = get_data_woo ( $_GET, $offset, $limit, true );
	$data = $encoded ['items'];
	unset($encoded);
	$columns_header = array();
	$active_module = $_GET ['active_module'];
	switch ( $active_module ) {
		
		case 'Products':
				$columns_header['id'] 						= 'Post ID';
				$columns_header['thumbnail'] 				= 'Product Image';
				$columns_header['post_title'] 				= 'Product Name';
				$columns_header['_regular_price'] 			= 'Price';
				$columns_header['_sale_price'] 				= 'Sale Price';
				$columns_header['_sale_price_dates_from'] 	= 'Sale Price Dates (From)';
				$columns_header['_sale_price_dates_to'] 	= 'Sale Price Dates (To)';
				$columns_header['_stock'] 					= 'Inventory / Stock';
				$columns_header['_sku'] 					= 'SKU';
				$columns_header['category'] 				= 'Category / Group';
				$columns_header['post_content'] 			= 'Product Description';
				$columns_header['post_excerpt'] 			= 'Additional Description';
				$columns_header['_weight'] 					= 'Weight';
				$columns_header['_height'] 					= 'Height';
				$columns_header['_width'] 					= 'Width';
				$columns_header['_length'] 					= 'Length';
			break;
			
		case 'Customers':
				$columns_header['id'] 					= 'User ID';
				$columns_header['_billing_first_name'] 	= 'First Name';
				$columns_header['_billing_last_name'] 	= 'Last Name';
				$columns_header['_billing_email'] 		= 'E-mail ID';
				$columns_header['_billing_address'] 	= 'Address';
				$columns_header['_billing_postcode'] 	= 'Postcode';
				$columns_header['_billing_city'] 		= 'City';
				$columns_header['_billing_state'] 		= 'State / Region';
				$columns_header['_billing_country'] 	= 'Country';
				$columns_header['last_order'] 			= 'Last Order Date';
				$columns_header['_order_total'] 		= 'Order Total';
				$columns_header['_billing_phone'] 		= 'Phone / Mobile';
			break;
			
		case 'Orders':
				$columns_header['id'] 						= 'Order ID';
				$columns_header['date'] 					= 'Order Date';
				$columns_header['_billing_first_name'] 		= 'Billing First Name';
				$columns_header['_billing_last_name'] 		= 'Billing Last Name';
				$columns_header['_billing_email'] 			= 'Billing E-mail ID';
				$columns_header['_order_total'] 			= 'Order Total';
				$columns_header['products_name'] 			= 'Order Items (Product Name(Qty))';
				$columns_header['_payment_method_title'] 	= 'Payment Method';
				$columns_header['order_status'] 			= 'Order Status';
				$columns_header['_shipping_method_title'] 	= 'Shipping Method';
				$columns_header['_shipping_first_name'] 	= 'Shipping First Name';
				$columns_header['_shipping_last_name'] 		= 'Shipping Last Name';
				$columns_header['_shipping_address'] 		= 'Shipping Address';
				$columns_header['_shipping_postcode'] 		= 'Shipping Postcode';
				$columns_header['_shipping_city'] 			= 'Shipping City';
				$columns_header['_shipping_state'] 			= 'Shipping State / Region';
				$columns_header['_shipping_country'] 		= 'Shippping Country';
			break;
	}
	
	$file_data = export_csv_woo ( $active_module, $columns_header, $data );
	
	header("Content-type: text/x-csv; charset=UTF-8"); 
	header("Content-Transfer-Encoding: binary");
	header("Content-Disposition: attachment; filename=".$file_data['file_name']); 
	header("Pragma: no-cache");
	header("Expires: 0");
		
	echo $file_data['file_content'];
		
	exit;
}

//update products for lite version.
function update_products_woo($_POST) {
	global $result, $wpdb;
	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$updateCnt = 1;
	foreach ( $edited_object as $obj ) {
		
		$update_name = $wpdb->query ( "UPDATE $wpdb->posts SET `post_title`= '".$wpdb->_real_escape($obj->post_title)."' WHERE ID = " . $wpdb->_real_escape($obj->id) );
		$update_price = $wpdb->query ( "UPDATE $wpdb->postmeta SET `meta_value`= ".$wpdb->_real_escape($obj->_regular_price)." WHERE meta_key = '_regular_price' AND post_id = " . $wpdb->_real_escape($obj->id) );
		$result ['updateCnt'] = $updateCnt ++;
	}
	
	if (($update_name >= 1 || $update_price >= 1) && $result ['updateCnt'] >= 1) {
		$result ['result'] = true;
		$result ['updated'] = 1;
	}
	return $result;
}
// For insert updating product in woo.
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'saveData') {
		
		if (SMPRO == true)
			$result = woo_insert_update_data ( $_POST );
		else
			$result = update_products_woo ( $_POST );
		
		if ($result ['updated'] && $result ['inserted']) {
			if ($result ['updateCnt'] == 1 && $result ['insertCnt'] == 1)
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> Record Updated and <br><b>" . $result ['insertCnt'] . "</b> New Record Inserted Successfully ";
			elseif ($result ['updateCnt'] == 1 && $result ['insertCnt'] != 1)
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> Record Updated and <br><b>" . $result ['insertCnt'] . "</b> New Records Inserted Successfully ";
			elseif ($result ['updateCnt'] != 1 && $result ['insertCnt'] == 1)
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> Records Updated and <br><b>" . $result ['insertCnt'] . "</b> New Record Inserted Successfully ";
			else
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> Records Updated and <br><b>" . $result ['insertCnt'] . "</b> New Records Inserted Successfully ";
		} else {
			
			if ($result ['updated'] == 1) {
				if ($result ['updateCnt'] == 1) {
					$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> Record Updated Successfully";
				} else
					$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> Records Updated Successfully";
			}
			
			if ($result ['inserted'] == 1) {
				if ($result ['insertCnt'] == 1)
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> New Record Inserted Successfully";
				else
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> New Records Inserted Successfully";
			}
			
		}
	echo json_encode ( $encoded );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'delData') {
	$delCnt = 0;
	$activeModule = substr( $_POST ['active_module'], 0, -1 );

		$data = json_decode ( stripslashes ( $_POST ['data'] ) );
		$delCnt = count ( $data );
		
		for($i = 0; $i < $delCnt; $i ++) {
			$post_id = $data [$i];
			$post = get_post ( $post_id );		// Required to get post_type for deleting variation from Smart Manager
			if ( $post->post_type == 'product_variation' ) {
				$post_data [] = wp_delete_post( $post_id );
			} else {
				$post_data [] = wp_trash_post ( $post_id );
			}
		}
		
		$deleted_count = count ( $post_data );
		if ($deleted_count == $delCnt)
			$result = true;
		else
			$result = false;
		
		if ($result == true) {
			if ($delCnt == 1) {
				$encoded ['msg'] = $delCnt . " $activeModule deleted Successfully";
				$encoded ['delCnt'] = $delCnt;
			} else {
				$encoded ['msg'] = $delCnt . " " . $activeModule . "s deleted Successfully";
				$encoded ['delCnt'] = $delCnt;
			}
		} elseif ($result == false) {
			$encoded ['msg'] = $activeModule . "s were not deleted ";
		} else {
			$encoded ['msg'] = $activeModule . "s removed from the grid";
		}
	echo json_encode ( $encoded );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getRolesDashboard') {
	global $wpdb, $current_user;
	$current_user = wp_get_current_user();
	if ( SMPRO != true || $current_user->roles[0] == 'administrator') {
		$results = array( 'Products', 'Customers_Orders' );
	} else {
		$results = get_dashboard_combo_store();
	}
	echo json_encode ( $results );
}

function get_term_taxonomy_id($term_name) {					// for woocommerce orders
	global $wpdb;
	$select_query = "SELECT term_taxonomy_id FROM {$wpdb->prefix}term_taxonomy AS term_taxonomy JOIN {$wpdb->prefix}terms AS terms ON terms.term_id = term_taxonomy.term_id WHERE terms.name = '$term_name'";
	$result = $wpdb->get_results ($select_query, 'ARRAY_A');
	if (isset($result[0])) {
		return (int)$result[0]['term_taxonomy_id'];	
	} else {
		$insert_term_query = "INSERT INTO {$wpdb->prefix}terms ( name, slug ) VALUES ( '" . $wpdb->_real_escape($term_name) . "', '" . $wpdb->_real_escape($term_name) . "' )";
		$result = $wpdb->query ($insert_term_query);
		if ($result > 0) {
			$insert_taxonomy_query = "INSERT INTO {$wpdb->prefix}term_taxonomy ( term_id, taxonomy ) VALUES ( " . $wpdb->_real_escape($wpdb->insert_id) . ", 'shop_order_status' )";
			$result = $wpdb->query ($insert_taxonomy_query);
			return (int)$wpdb->insert_id;
		} else {
			return -1;
		}
	}
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getTerms'){
	global $wpdb;
	$action_name =  $_POST['action_name'];
	$attribute_name = $_POST ['attribute_name'];
	$attribute_suffix = "pa_" . $attribute_name;
	$query = "SELECT tt.term_taxonomy_id, t.name FROM {$wpdb->prefix}terms as t join {$wpdb->prefix}term_taxonomy as tt on (t.term_id = tt.term_id) where tt.taxonomy = '$attribute_suffix' ";
	$results = $wpdb->get_results ($query, 'ARRAY_A');
	$terms_combo_store = array();
	$term_count = 0;
	$terms_combo_store [$term_count] [] = 'all';
	$terms_combo_store [$term_count] [] = 'All';
	$term_count++;
	foreach ( $results as $result ) {
		$terms_combo_store [$term_count] [] = $result['term_taxonomy_id'];
		$terms_combo_store [$term_count] [] = $result['name'];
		$term_count++;
	}
	
	echo json_encode ( $terms_combo_store );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getRegion') {
	global $wpdb, $woocommerce;
	$cnt = 0;
	if ( !empty ( $woocommerce->countries->states[$_POST['country_id']] ) ) {
		foreach ( $woocommerce->countries->states[$_POST['country_id']] as $key => $value) {
			$regions ['items'] [$cnt] ['id'] = $key;
			$regions ['items'] [$cnt] ['name'] = $value;
			$cnt++;
		}
	} else {
		$regions = '';
	}
	echo json_encode ( $regions );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'editImage') {
	$woo_default_image = WP_PLUGIN_URL . '/smart-reporter-for-wp-e-commerce/resources/themes/images/woo_default_image.png';
	$post_thumbnail_id = get_post_thumbnail_id( $_POST ['id'] );
	$image = isset( $post_thumbnail_id ) ? wp_get_attachment_image_src( $post_thumbnail_id, 'admin-product-thumbnails' ) : '';
	$thumbnail = ( $image[0] != '' ) ? $image[0] : '';
	echo json_encode ( $thumbnail );
}


?>