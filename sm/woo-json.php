<?php 
include_once ('../../../../wp-load.php');

$mem_limit = ini_get('memory_limit');
if(intval(substr($mem_limit,0,strlen($mem_limit)-1)) < 64 ){
	ini_set('memory_limit','128M'); 
}

$result = array ();
$encoded = array ();

$offset = (isset ( $_POST ['start'] )) ? $_POST ['start'] : 0;
$limit = (isset ( $_POST ['limit'] )) ? $_POST ['limit'] : 100;

// For pro version check if the required file exists
if (file_exists ( '../pro/woo.php' )) {
	define ( 'SMPRO', true );
	include_once ('../pro/woo.php');
} else {
	define ( 'SMPRO', false );
}

// getting the active module
$active_module = $_POST ['active_module'];

// Searching a product in the grid
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getData') {
	global $wpdb, $woocommerce;
	$view_columns = json_decode ( stripslashes ( $_POST ['viewCols'] ) );
	if ($active_module == 'Products') { // <-products
	
		$post_status = "('publish', 'draft')";
	
		// if max-join-size issue occurs
		$query = "SET SQL_BIG_SELECTS=1;";
		$wpdb->query ( $query );

		$select = "SELECT SQL_CALC_FOUND_ROWS products.id,
					post_title,
					post_content,
					post_excerpt,
					post_status,
					post_parent,
					category,
					GROUP_CONCAT(prod_othermeta.meta_key order by prod_othermeta.meta_id SEPARATOR '###') AS prod_othermeta_key,
					GROUP_CONCAT(prod_othermeta.meta_value order by prod_othermeta.meta_id SEPARATOR '###') AS prod_othermeta_value,
					prod_meta.meta_value as prod_meta
					$parent_sort_id";

		if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_on = mysql_escape_string ( trim ( $_POST ['searchText'] ) );

			$search_condn = " HAVING concat(' ',REPLACE(REPLACE(post_title,'(',''),')','')) LIKE '% $search_on%'
				               OR post_content LIKE '%$search_on%'
				               OR post_excerpt LIKE '%$search_on%'
				               OR if(post_status = 'publish','Published',post_status) LIKE '$search_on%'
							   OR prod_othermeta_value LIKE '%$search_on%'
							   OR category LIKE '%$search_on%'
					           ";
		}

		$from_where = "FROM {$wpdb->prefix}posts as products
						LEFT JOIN {$wpdb->prefix}postmeta as prod_othermeta ON (prod_othermeta.post_id = products.id and
						prod_othermeta.meta_key IN ('regular_price','sale_price','sale_price_dates_from','sale_price_dates_to','sku','stock','weight','height','length','width') )
						
						LEFT JOIN {$wpdb->prefix}postmeta as prod_meta ON (prod_meta.post_id = products.id and
						prod_meta.meta_key = 'product_attributes')
						
						LEFT JOIN 
						(SELECT GROUP_CONCAT(wt.name) as category,wtr.object_id
						FROM  {$wpdb->prefix}term_relationships AS wtr  	 
						JOIN {$wpdb->prefix}term_taxonomy AS wtt ON (wtr.term_taxonomy_id = wtt.term_taxonomy_id and taxonomy = 'product_cat')
												
						JOIN {$wpdb->prefix}terms AS wt ON (wtt.term_id = wt.term_id)
						group by wtr.object_id) as prod_categories on (products.id = prod_categories.object_id)
						
						WHERE products.post_status IN  $post_status
						AND products.post_type    = 'product'";

		$group_by = " GROUP BY products.id ";
		
		$order_by = " ORDER BY products.id desc";

		$query = "$select  $from_where $group_by $search_condn $order_by LIMIT $offset,$limit;";
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
				$prod_meta_key_values = array_combine ( $prod_meta_key, $prod_meta_values );
				
				if(isset($prod_meta_key_values['sale_price_dates_from']) && !empty($prod_meta_key_values['sale_price_dates_from']))
					$prod_meta_key_values['sale_price_dates_from'] = date('Y-m-d',(int)$prod_meta_key_values['sale_price_dates_from']);
				if(isset($prod_meta_key_values['sale_price_dates_to']) && !empty($prod_meta_key_values['sale_price_dates_to']))
					$prod_meta_key_values['sale_price_dates_to'] = date('Y-m-d',(int)$prod_meta_key_values['sale_price_dates_to']);
				
				if (is_serialized($records[$i]->prod_meta)){
					$unsez_data[$i] = unserialize($records[$i]->prod_meta);
					$records[$i]    = array_merge((array)$records[$i],$unsez_data[$i]);
				}
				$records[$i] = array_merge((array)$records[$i],$prod_meta_key_values);
			}
			
			unset ( $records[$i]->prod_othermeta_value );
			unset ( $records[$i]->prod_meta );
			unset ( $records[$i]->prod_othermeta_key );			
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
					
			$limit_query = " ORDER BY posts.ID DESC 
			                LIMIT " . $offset . "," . $limit . "";
			
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
			$num_records = count($records);
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
			$limit_query = " ORDER BY posts.ID DESC 
			                LIMIT " . $offset . "," . $limit . "";
			
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
				$search_on = mysql_escape_string ( trim ( $_POST ['searchText'] ) );
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
							foreach ($order_items as $order_item) {
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
				$num_records = count($records);
				unset($meta_value);
				unset($meta_key);
				unset($postmeta);
				unset($results);
			}
	}
	$encoded ['items'] = $records;
	$encoded ['totalCount'] = $num_records;
	unset($records);
	echo json_encode ( $encoded );
	unset($encoded);
}


//update products for lite version.
function update_products_woo($_POST) {
	global $result, $wpdb;
	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$updateCnt = 1;
	foreach ( $edited_object as $obj ) {
		
		$update_name = $wpdb->query ( "UPDATE $wpdb->posts SET `post_title`= '$obj->post_title' WHERE ID = $obj->id" );
		$update_price = $wpdb->query ( "UPDATE $wpdb->postmeta SET `meta_value`= $obj->regular_price WHERE meta_key = 'regular_price' AND post_id = $obj->id" );
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
			$result = woo_insert_update_data($_POST);
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
	
		$data = json_decode ( stripslashes ( $_POST ['data'] ) );
		$delCnt = count ( $data );
		
		for($i = 0; $i < $delCnt; $i ++) {
			$post_id = $data [$i];
			$post_data [] = wp_trash_post ( $post_id );
		}
		
		$deleted_count = count ( $post_data );
		if ($deleted_count == $delCnt)
			$result = true;
		else
			$result = false;
		
		if ($result == true) {
			if ($delCnt == 1) {
				$encoded ['msg'] = $delCnt . " Product deleted Successfully";
				$encoded ['delCnt'] = $delCnt;
			} else {
				$encoded ['msg'] = $delCnt . " Products deleted Successfully";
				$encoded ['delCnt'] = $delCnt;
			}
		} elseif ($result == false) {
			$encoded ['msg'] = "Products were not deleted ";
		} else {
			$encoded ['msg'] = "Products removed from the grid";
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

?>