<?php
include_once ('../../../../wp-load.php');
include_once ('../../../../wp-includes/wp-db.php');
include_once (ABSPATH . WPINC . '/functions.php');

// for delete logs.
require_once ('../../' . WPSC_FOLDER . '/wpsc-includes/purchaselogs.class.php');

//checking the memory limit allocated
$mem_limit = ini_get('memory_limit');

if(intval(substr($mem_limit,0,strlen($mem_limit)-1)) < 64 ){
	ini_set('memory_limit','128M'); 
}

$result = array ();
$encoded = array ();

$offset = (isset ( $_POST ['start'] )) ? $_POST ['start'] : 0;
$limit = (isset ( $_POST ['limit'] )) ? $_POST ['limit'] : 100;
	
// For pro version check if the required file exists
if (file_exists ( '../pro/sm38.php' )) {
	define ( 'SMPRO', true );
	include_once ('../pro/sm38.php');
} else {
	define ( 'SMPRO', false );
}

function get_regions_ids(){ //getting the region ids
	global $wpdb;
	$query   	 = "SELECT id,name FROM " . WPSC_TABLE_REGION_TAX;
	$reg_results = $wpdb->get_results ( $query,'ARRAY_A');

	foreach($reg_results as $reg_result){
		$regions_ids[$reg_result['id']] = $reg_result['name'];
	}
	return $regions_ids;
}
		
// getting the active module
$active_module = $_POST ['active_module'];

// Searching a product in the grid
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getData') {
	global $wpdb;
	$show_variation = true;	
	
	if (SMPRO == true && function_exists ( 'variation_query_params' ))
		variation_query_params ();	

	$view_columns = json_decode ( stripslashes ( $_POST ['viewCols'] ) );
	if ($active_module == 'Products') { // <-products
		
		if (isset ( $_POST ['incVariation'] ) && $_POST ['incVariation'] == 'true') {
			if (SMPRO == false)
			$show_variation = false;
		} else {
			$show_variation = false;
		}
		
		if ($show_variation === false) { // query params for non-variation products
			$post_status = "('publish', 'draft')";
			$parent_sort_id = '';
			$order_by = " ORDER BY products.id desc";
		}

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
							   OR prod_othermeta_value LIKE '$search_on%'
							   OR category LIKE '%$search_on%'
					           ";
		}
				
		$from_where = "FROM {$wpdb->prefix}posts as products
						LEFT JOIN {$wpdb->prefix}postmeta as prod_othermeta ON (prod_othermeta.post_id = products.id and
						prod_othermeta.meta_key IN ('_wpsc_price', '_wpsc_special_price', '_wpsc_sku', '_wpsc_stock') )
						
						LEFT JOIN {$wpdb->prefix}postmeta as prod_meta ON (prod_meta.post_id = products.id and
						prod_meta.meta_key = '_wpsc_product_metadata')
						
						LEFT JOIN 
						(SELECT GROUP_CONCAT(wt.name) as category,wtr.object_id
						FROM  {$wpdb->prefix}term_relationships AS wtr  	 
						JOIN {$wpdb->prefix}term_taxonomy AS wtt ON (wtr.term_taxonomy_id = wtt.term_taxonomy_id and taxonomy = 'wpsc_product_category')
												
						JOIN {$wpdb->prefix}terms AS wt ON (wtt.term_id = wt.term_id)
						group by wtr.object_id) as prod_categories on (products.id = prod_categories.object_id)
						
						WHERE products.post_status IN  $post_status
						AND products.post_type    = 'wpsc-product'";
		
		$group_by = " GROUP BY products.id ";
		
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
			foreach ( $records as &$record ) {
				$prod_meta_values = explode ( '###', $record->prod_othermeta_value );
				$prod_meta_key    = explode ( '###', $record->prod_othermeta_key);
				
				$prod_meta_key_values = array_combine ( $prod_meta_key, $prod_meta_values );
				$prod_meta_key_values ['prod_meta'] = $record->prod_meta;
				
				foreach ( $prod_meta_key_values as $key => $value ) {
					if (is_serialized ( $value )) {
						
						$unsez_data = unserialize ( $value );
						$unsez_data ['weight'] = wpsc_convert_weight ( $unsez_data ['weight'], "pound", $unsez_data ['weight_unit']); // get the weight by converting it to repsective unit
						
						foreach ( $unsez_data as $meta_key => $meta_value ) {
							if (is_array ( $meta_value )) {
								foreach ( $meta_value as $sub_metakey => $sub_metavalue )
									(in_array ( $sub_metakey, $view_columns )) ? $record->$sub_metakey = $sub_metavalue : '';
							} else {
								(in_array ( $meta_key, $view_columns )) ? $record->$meta_key = $meta_value : '';
							}
						}
						unset($prod_meta_key_values[$value]);
					} else {
						(in_array ( $key, $view_columns )) ? $record->$key = $value : '';
					}
				}
				unset ( $record->prod_othermeta_value );
				unset ( $record->prod_meta );
				unset ( $record->prod_othermeta_key );
			}
		}
		
	} //products ->
elseif ($active_module == 'Orders') {
		$select_query = "SELECT SQL_CALC_FOUND_ROWS wtpl.id as id,
						       GROUP_CONCAT( wtsfd.value 
							   ORDER BY wtsfd.form_id
							   SEPARATOR '###' ) AS order_details,

							   GROUP_CONCAT(distinct wtcf.unique_name
						       ORDER BY wtcf.id 
							   SEPARATOR '###' ) AS shipping_unique_names,
							   
						       details,
                               products_name,
						       
					  	       date_format(FROM_UNIXTIME(wtpl.date),'%b %e %Y, %r') AS date,
						  	   wtpl.date AS unixdate,
							   wtpl.totalprice AS amount,
							   wtpl.track_id, 			                 
							   wtpl.processed  AS order_status,
                               sessionid,
                               wtpl.notes,
                               country AS shippingcountry,
                               wprt.name AS shippingstate
                            
						       FROM " . WPSC_TABLE_PURCHASE_LOGS . " AS wtpl
						       INNER JOIN " . WPSC_TABLE_SUBMITED_FORM_DATA . " as wtsfd 
						       ON (wtpl.id = wtsfd.log_id) 
						       
						       INNER JOIN " . WPSC_TABLE_CHECKOUT_FORMS . " as wtcf   
						       ON (wtsfd.form_id = wtcf.id AND wtcf.active = 1 
						       AND unique_name IN ('billingfirstname' , 'billinglastname' , 'billingemail',
						       			 		   'shippingfirstname', 'shippinglastname', 'shippingaddress',
					                     		   'shippingcity'	  , 'shippingstate'	  , 'shippingcountry','shippingpostcode'))
						       
							   LEFT JOIN 
						       (SELECT CONCAT(CAST(sum(quantity) AS CHAR) , ' items') AS details,
						       	GROUP_CONCAT(name SEPARATOR '#') AS products_name,
						    	purchaseid
						    	FROM " . WPSC_TABLE_CART_CONTENTS . "
						    	GROUP BY " . WPSC_TABLE_CART_CONTENTS . ".purchaseid) as quantity_details
						        ON (wtpl.id = quantity_details.purchaseid)
						       LEFT JOIN " . WPSC_TABLE_CURRENCY_LIST . " AS wpcc ON (wtpl.shipping_country = wpcc.isocode)
		                       LEFT JOIN " . WPSC_TABLE_REGION_TAX." AS wprt ON (wtpl.shipping_region = wprt.id)";
		

		$group_by    = "GROUP BY wtpl.id";		
		$limit_query = "ORDER BY id desc 
		                LIMIT " . $offset . "," . $limit . "";

		if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_on = mysql_escape_string ( trim ( $_POST ['searchText'] ) );
			$search_condn = " HAVING id like '$search_on%'
					          or sessionid like '$search_on%'
							  OR date like '%$search_on%'
							  OR amount like '$search_on%'
							  OR wtpl.track_id like '%$search_on%' 
							  OR CASE order_status
								  WHEN 1 THEN 'Incomplete Sale'
								  WHEN 2 THEN 'Order Received'
								  WHEN 3 THEN 'Accepted Payment'
								  WHEN 4 THEN 'Job Dispatched'
								  WHEN 5 THEN 'Closed Order'
								  ELSE 'Payment Declined'
							     END like '%$search_on%'
							 OR wtpl.notes like '%$search_on%'
							 OR details like '%$search_on%'
							 OR products_name like '%$search_on%' 
							 OR shippingcountry like '%$search_on%'
							 OR shippingstate like '%$search_on%'
							 OR order_details LIKE '%$search_on%'";
		}
		
		if (isset ( $_POST ['fromDate'] )) {
			$from_date = strtotime ( $_POST ['fromDate'] );
			$to_date = strtotime ( $_POST ['toDate'] );
			if ($to_date == 0) {
				$to_date = strtotime ( 'today' );
			}
			// move it forward till the end of day
			$to_date += 86399;
			
			// Swap the two dates if to_date is less than from_date
			if ($to_date < $from_date) {
				$temp = $to_date;
				$to_date = $from_date;
				$from_date = $temp;
			}
			$where = " WHERE wtpl.date BETWEEN '$from_date' AND '$to_date'";
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
			$regions_ids = get_regions_ids();		
			foreach ( $results as $data) {
				$order_details = explode ( '###', $data ['order_details'] );
				$shipping_unique_names = explode ( '###', $data ['shipping_unique_names'] );
				
				if(count($order_details) == count($shipping_unique_names)){
					$shipping_order_details = array_combine ( $shipping_unique_names, $order_details );

					$name_emailid [0] = "<font class=blue>". $shipping_order_details['billingfirstname']."</font>";
					$name_emailid [1] = "<font class=blue>". $shipping_order_details['billinglastname']."</font>";
					$name_emailid [2] = "(".$shipping_order_details['billingemail'].")"; //email comes at 7th position.
					$data['name'] 	  = implode ( ' ', $name_emailid ); //in front end,splitting is done with this space.

					//if purchase log doesn't have state code pick up the state from submitted form data
					if($data['shippingstate'] == ''){
						$ship_state = $shipping_order_details['shippingstate'];
						$data['shippingstate'] = (is_numeric($ship_state)) ? $regions_ids[$ship_state] : $ship_state;
					}
					unset($data ['order_details']);
					$records [] = array_merge ( $shipping_order_details, $data );
				}
			}
			unset($order_details);
			unset($shipping_unique_names);
			unset($shipping_order_details);
			unset($results);
		}
	} else {
		//BOF Customer's module
		if (SMPRO == true) {
			$customers_query = customers_query ( $_POST ['searchText'] );
		} else {
			$customers_query = "SELECT SQl_CALC_FOUND_ROWS purchlog_info.id, 
					  GROUP_CONCAT(wwsfd.value ORDER BY form_id SEPARATOR  '###' ) AS user_details, 
					  GROUP_CONCAT( wwcf.unique_name ORDER BY wwcf.id SEPARATOR  '###' ) AS unique_names,	
					  email,
					  country AS billingcountry,
					  wprt.name as billingstate
			 	   
                      FROM (SELECT wwpl.id,
					             billing_country,
					             billing_region,
					             value as email
					             
					             FROM ". WPSC_TABLE_PURCHASE_LOGS ." AS wwpl
					             INNER JOIN ". WPSC_TABLE_SUBMITED_FORM_DATA ."  AS emails 
					             on (wwpl.id = emails.log_id) 
					             
					             INNER JOIN ". WPSC_TABLE_CHECKOUT_FORMS ." AS email_name 
					             on (email_name.id = emails.form_id 
					     			 AND unique_name = 'billingemail'
					                 AND email_name.active = 1)
					             
					             GROUP BY email
					             ORDER BY DATE DESC) AS purchlog_info 
                      
                     INNER JOIN ". WPSC_TABLE_SUBMITED_FORM_DATA ." as wwsfd on (purchlog_info.id = wwsfd.log_id)

                     INNER JOIN ". WPSC_TABLE_CHECKOUT_FORMS ." AS wwcf 
					 ON ( wwsfd.form_id = wwcf.id  
					      AND unique_name IN ('billingfirstname','billinglastname','billingaddress',
					                     'billingcity','billingstate','billingcountry','billingpostcode',
									     'billingemail','billingphone')
						  AND wwcf.active = 1 ) 					

					 LEFT JOIN  ". WPSC_TABLE_CURRENCY_LIST ."  ON (purchlog_info.billing_country = isocode)
					 LEFT JOIN " . WPSC_TABLE_REGION_TAX." AS wprt ON (purchlog_info.billing_region = wprt.id) 
					 GROUP BY purchlog_info.id \n";
			
			if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
				$search_text = mysql_real_escape_string ( $_POST ['searchText'] );
				$customers_query .= " HAVING id  LIKE '$search_text%'
				          OR email LIKE '%$search_text%'
				          OR user_details LIKE '%$search_text%'
	    				  OR billingcountry   LIKE '$search_text%'
	    				  OR billingstate LIKE '$search_text%'";
			}
		}
		
		$limit_query = " ORDER BY purchlog_info.id 
						 LIMIT $offset , $limit";
		$query    	 = "$customers_query  $limit_query";
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
			$regions_ids = get_regions_ids();
			foreach ( ( array ) $result as $data ) {
				$user_details = explode ( '###', $data ['user_details'] );
				$unique_names = explode ( '###', $data ['unique_names'] );
				
				//note: while merging the array, $data as to be the second arg
				if (count ( $unique_names ) == count ( $user_details )) {
					$billing_user_details = array_combine ( $unique_names, $user_details );

					if($data['billingstate'] == ''){
						$bill_state = $shipping_order_details['billingstate'];
						$data['billingstate'] = (is_numeric($bill_state)) ? $regions_ids[$bill_state] : $bill_state;
					}
					
					if (SMPRO == true) {
						$data['Last_Order'] = $data ['Last_Order_Date'] . ', ' . $data ['Last_Order_Amt'];
					}else{
						$data ['Total_Purchased'] = 'Pro only';
						$data ['Last_Order'] = 'Pro only';
					}
					//NOTE: storing old email id in an extra column in record so useful to indentify record with emailid during updates.
					$data ['Old_Email_Id'] = $billing_user_details ['billingemail'];
					$records [] = array_merge ( $billing_user_details, $data );
				}
			}
			unset($result);
			unset($user_details);
			unset($unique_names);
			unset($billing_user_details);
		}
	}
	$encoded ['items'] = $records;
	$encoded ['totalCount'] = $num_records;
	unset($records);
	echo json_encode ( $encoded );
	unset($encoded);
}

// Delete product.
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'delData') {
	global $purchlogs;
	$purchlogs = new wpsc_purchaselogs ();
	$delCnt = 0;
	
	if ($active_module == 'Products') {
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
	} else if ($active_module == 'Orders') {
		$data = json_decode ( stripslashes ( $_POST ['data'] ) );
		foreach ( $data as $key => $id ) {
			$output = $purchlogs->deletelog ( $id );
			$delCnt ++;
		}
		
		if ($output) {
			//			$encoded ['msg'] = strip_tags($output);
			if ($delCnt == 1) {
				$encoded ['msg'] = $delCnt . " Purchase Log deleted Successfully";
				$encoded ['delCnt'] = $delCnt;
			} else {
				$encoded ['msg'] = $delCnt . " Purchase Logs deleted Successfully";
				$encoded ['delCnt'] = $delCnt;
			}
		} else
			$encoded ['msg'] = "Purchase Logs removed from the grid";
	}
	echo json_encode ( $encoded );
}

function data_for_update_orders($_POST) {
	global $wpdb; // to use as global
	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$_POST = array ();
	
	$query = "SELECT id,unique_name
  	 		  FROM " . WPSC_TABLE_CHECKOUT_FORMS . " 
			  WHERE unique_name IN ('shippingfirstname', 'shippinglastname', 'shippingaddress', 'shippingcity', 'shippingpostcode')
			  AND active = 1 
			  GROUP BY unique_name 
			  ORDER BY id";
	$result = $wpdb->get_results ( $query, 'ARRAY_A' );
	
	if (count ( $result ) >= 1) {
		foreach ( $result as $key => $arr_value )
			$id_uniquename [$arr_value ['unique_name']] = $arr_value ['id'];
	}
		
	$ordersCnt = 1;
	foreach ( $edited_object as $obj ) {
		foreach ( $id_uniquename as $uniquename => $form_id ) {
			$update_value = $obj->$uniquename;			
			
			$query = "UPDATE `". WPSC_TABLE_PURCHASE_LOGS . "` 
						SET 	processed ='$obj->order_status',
								    notes ='$obj->notes',
								 track_id ='$obj->track_id'
				   				 WHERE id ='$obj->id'";
			$update_result = $wpdb->query ( $query );

			//$key contains unique name
			$update_value = mysql_escape_string($update_value);
			$query = "UPDATE `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`
				         SET value   = '" . $update_value . "'
				       WHERE form_id = $form_id
				         AND log_id  = '{$obj->id}'";
			$update_result = $wpdb->query ( $query );
		}
		$result ['updateCnt'] = $ordersCnt ++;
		unset ( $ship_country_info ); // unsetting the $ship_country_info
	}
	$result ['result'] = true;
	$result ['updated'] = 1;
	return $result;
}

//update products for lite version.
function update_products($_POST) {
	global $result, $wpdb;
	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$updateCnt = 1;
	foreach ( $edited_object as $obj ) {
		
		$update_name = $wpdb->query ( "UPDATE $wpdb->posts SET `post_title`= '$obj->post_title' WHERE ID = $obj->id" );
		$update_price = $wpdb->query ( "UPDATE $wpdb->postmeta SET `meta_value`= $obj->_wpsc_price WHERE meta_key = '_wpsc_price' AND post_id = $obj->id" );
		$result ['updateCnt'] = $updateCnt ++;
	}
	
	if (($update_name >= 1 || $update_price >= 1) && $result ['updateCnt'] >= 1) {
		$result ['result'] = true;
		$result ['updated'] = 1;
	}
	return $result;
}

// For updating product,orders and customers details.
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'saveData') {
	
	if ($active_module == 'Products') {
		if (SMPRO == true)
			$result = data_for_insert_update ( $_POST );
		else
			$result = update_products ( $_POST );
	}

	elseif ($active_module == 'Orders')
		$result = data_for_update_orders ( $_POST );
	elseif ($active_module == 'Customers')
		$result = update_customers ( $_POST );
	
	if ($result ['result']) {
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
				if ($result ['updateCnt'] == 1)
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> New Records Inserted Successfully";
				else
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> New Records Inserted Successfully";
			}
			
		}
	}	
	echo json_encode ( $encoded );
}
?>