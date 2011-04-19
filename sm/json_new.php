<?php
include_once ('../../../../wp-load.php');
include_once ('../../../../wp-includes/wp-db.php');
include_once (ABSPATH . WPINC . '/functions.php');

// for delete logs.
require_once ('../../' . WPSC_FOLDER . '/wpsc-includes/purchaselogs.class.php');

$limit = 10;
$del   = 3;
$result = array ();
$encoded = array ();

if (isset ( $_POST ['start'] ))
	$offset = $_POST ['start'];
else
	$offset = 0;

if (isset ( $_POST ['limit'] ))
	$limit = $_POST ['limit'];
	
// For pro version check if the required file exists
if (file_exists ( '../pro/sm38.php' )) {
	define ( 'SMPRO', true );
} else {
	define ( 'SMPRO', false );
}
if (SMPRO == true)
	include_once ('../pro/sm38.php');
	
// getting the active module
$active_module = $_POST ['active_module'];

// Searching a product in the grid
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getData') {
	global $wpdb;
	$view_columns = json_decode ( stripslashes ( $_POST ['viewCols'] ) );
	
	if ($active_module == 'Products') { // <-products
		$select = "SELECT p.`id`,
		             post_title,
		             post_content,
		             post_excerpt,
		             post_status,
		             prod_meta_key,
		             prod_meta_value,
		             category";
		
		$from = "FROM wp_posts p 
		
					LEFT JOIN
					(SELECT pm.post_id,
					GROUP_CONCAT(meta_key order by meta_id) as prod_meta_key,
					cast(GROUP_CONCAT(meta_value order by meta_id) as char(4000)) as prod_meta_value
					FROM `wp_postmeta` pm
					WHERE meta_key IN ('_wpsc_price', '_wpsc_special_price', '_wpsc_sku', '_wpsc_stock', '_wpsc_product_metadata')
					GROUP BY post_id) as products_meta ON products_meta.post_id = p.id 
					
					LEFT JOIN
					(SELECT `object_id` as post_id, GROUP_CONCAT( `wp_terms`.name ) AS category
					FROM `wp_term_taxonomy` , `wp_term_relationships` , `wp_terms`
					WHERE `wp_term_taxonomy`.`term_taxonomy_id` = `wp_term_relationships`.`term_taxonomy_id`
					AND taxonomy = 'wpsc_product_category'
					AND `wp_term_taxonomy`.term_id = `wp_terms`.term_id
					GROUP BY `object_id`) AS products_categories ON products_meta.post_id = products_categories.post_id";
		
		$where = "WHERE post_status IN ('publish', 'draft')
                 	    AND post_type    = 'wpsc-product'";		

		$order_by = " ORDER BY p.id desc ";
		
		$limit_query = " LIMIT " . $offset . "," . $limit . "";
		
		if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_on = mysql_escape_string ( trim ( $_POST ['searchText'] ) );
			$where .= " AND ( concat(' ',post_title) LIKE '% $search_on%'
                            OR post_content LIKE '%$search_on%'
                            OR post_excerpt LIKE '%$search_on%'
                            OR if(post_status = 'publish','Published','Draft') LIKE '%$search_on%'
                            OR prod_meta_value LIKE '%$search_on%'
                            )";
		}
		
		$query    = "$select $from $where $order_by $limit_query";
		$records  = $wpdb->get_results ( $query );
		$num_rows = $wpdb->num_rows;
		
		$recordcount_query = "SELECT COUNT( DISTINCT `products_meta`.`post_id` ) as count $from  $where ";
		$recordcount_result = $wpdb->get_results ( $recordcount_query, 'ARRAY_A' );		
		$num_records = $recordcount_result[0]['count'];
		
		if ($num_rows <= 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = 'No Records Found';
		} else {
			foreach ( $records as &$record ) {
				$prod_meta_values = explode ( ',', $record->prod_meta_value );
				$prod_meta_key = explode ( ',', $record->prod_meta_key );
				$prod_meta_key_values = array_combine ( $prod_meta_key, $prod_meta_values );
				
				foreach ( $prod_meta_key_values as $key => $value ) {
					if (is_serialized ( $value )) {
						$unsez_data = unserialize ( $value );
						
						foreach ( $unsez_data as $meta_key => $meta_value ) {
							if (is_array ( $meta_value )) {
								
								foreach ( $meta_value as $sub_metakey => $sub_metavalue )
									(in_array ( $sub_metakey, $view_columns )) ? $record->$sub_metakey = $sub_metavalue : '';
							
							} else {
								(in_array ( $meta_key, $view_columns )) ? $record->$meta_key = $meta_value : '';
							}
						}
					
					} else {
						(in_array ( $key, $view_columns )) ? $record->$key = $value : '';
					}
				
				}
				unset ( $record->prod_meta_value );
				unset ( $record->prod_meta_key );
			}
		}
	
	} //products ->
elseif ($active_module == 'Orders') {
		$query = "SELECT id,country_id, name, code FROM " . WPSC_TABLE_REGION_TAX;
		$results = $wpdb->get_results ( $query, 'ARRAY_A' );
		
		if ($wpdb->num_rows > 0) {
			foreach ( $results as $result )
				$regions [$result ['id']] = $result ['name'];
		}
		
		$query = "SELECT isocode,country FROM `" . WPSC_TABLE_CURRENCY_LIST . "` ORDER BY `country` ASC";
		$results = $wpdb->get_results ( $query, 'ARRAY_A' );
		
		if ($wpdb->num_rows > 0) {
			foreach ( $results as $result )
				$countries [$result ['isocode']] = $result ['country'];
		}

		$select_query = "SELECT ".WPSC_TABLE_PURCHASE_LOGS.".id,
						       GROUP_CONCAT( ".WPSC_TABLE_SUBMITED_FORM_DATA.".value 
							   ORDER BY ".WPSC_TABLE_SUBMITED_FORM_DATA.".`form_id` 
							   SEPARATOR '#' ) AS order_details,
							    
							   GROUP_CONCAT( CAST(form_id AS CHAR)
						       ORDER BY form_id  
							   SEPARATOR '#' ) AS shipping_ids,
							   
							   GROUP_CONCAT(".WPSC_TABLE_CHECKOUT_FORMS.".unique_name
						       ORDER BY ".WPSC_TABLE_CHECKOUT_FORMS.".`id` 
							   SEPARATOR '#' ) AS shipping_unique_names,
						       details,
						       
					  	       date_format(FROM_UNIXTIME(".WPSC_TABLE_PURCHASE_LOGS.".date),'%b %e %Y, %r') date,
						  	   ".WPSC_TABLE_PURCHASE_LOGS.".date as unixdate,
							   ".WPSC_TABLE_PURCHASE_LOGS.".totalprice amount,
							   ".WPSC_TABLE_PURCHASE_LOGS.".track_id, 			                 
							   ".WPSC_TABLE_PURCHASE_LOGS.".processed order_status,
                               sessionid,
                               ".WPSC_TABLE_PURCHASE_LOGS.".notes,
                               country_info.shippingcountry
                            
						       FROM ".WPSC_TABLE_PURCHASE_LOGS." 
						       LEFT JOIN ".WPSC_TABLE_SUBMITED_FORM_DATA." ON (".WPSC_TABLE_PURCHASE_LOGS.".id = ".WPSC_TABLE_SUBMITED_FORM_DATA.".log_id)
						       LEFT JOIN ".WPSC_TABLE_CHECKOUT_FORMS."    ON (".WPSC_TABLE_SUBMITED_FORM_DATA.".form_id = ".WPSC_TABLE_CHECKOUT_FORMS.".id)
						       
						       LEFT JOIN 
						       (SELECT CONCAT(CAST(sum(quantity) AS CHAR) , ' items') details,
						       	GROUP_CONCAT(name) products_name,
						    	purchaseid
						    	FROM ".WPSC_TABLE_CART_CONTENTS."
						    	GROUP BY " . WPSC_TABLE_CART_CONTENTS.".purchaseid) as quantity_details
						        ON (".WPSC_TABLE_PURCHASE_LOGS.".id = quantity_details.purchaseid)
						        
						       LEFT JOIN 
						       (
						        SELECT ".WPSC_TABLE_SUBMITED_FORM_DATA.".log_id,country AS shippingcountry
						        from   ".WPSC_TABLE_SUBMITED_FORM_DATA.",".WPSC_TABLE_CURRENCY_LIST.",".WPSC_TABLE_CHECKOUT_FORMS."
						        where unique_name = 'shippingcountry'
						        and   ".WPSC_TABLE_CHECKOUT_FORMS.".id = form_id  
						        and   ".WPSC_TABLE_SUBMITED_FORM_DATA.".value = isocode
						       ) as country_info on (country_info.log_id = ".WPSC_TABLE_PURCHASE_LOGS.".id)";
		
		$group_by = "GROUP BY ".WPSC_TABLE_SUBMITED_FORM_DATA.".log_id
							   ORDER BY form_id DESC ";
		
		$limit_query = " LIMIT " . $offset . "," . $limit . "";
		$where = ' WHERE 1 ';
		
		if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_on = mysql_escape_string ( trim ( $_POST ['searchText'] ) );
			$where .= " AND (".WPSC_TABLE_PURCHASE_LOGS.".id in ('$search_on')
							   OR sessionid like '%$search_on%'
							   OR date_format(FROM_UNIXTIME(".WPSC_TABLE_PURCHASE_LOGS.".date),'%b %e %Y, %r') like '%$search_on%'
							   
							   OR ".WPSC_TABLE_PURCHASE_LOGS.".totalprice like '$search_on%'
							   OR ".WPSC_TABLE_PURCHASE_LOGS.".track_id like '%$search_on%' OR							    
							   CASE ".WPSC_TABLE_PURCHASE_LOGS.".processed
							   	  WHEN 1 THEN 'Incomplete Sale'
								  WHEN 2 THEN 'Order Received'
								  WHEN 3 THEN 'Accepted Payment'
								  WHEN 4 THEN 'Job Dispatched'
								  WHEN 5 THEN 'Closed Order'
								  ELSE 'Payment Declined'
								  END like '%$search_on%'
									
							   OR ".WPSC_TABLE_PURCHASE_LOGS.".notes like '%$search_on%'
							   OR quantity_details.details like '%$search_on%'
							   
							   OR quantity_details.products_name like '%$search_on%' 
							   OR country_info.shippingcountry like '%$search_on%'
							   OR ".WPSC_TABLE_PURCHASE_LOGS.".id in (SELECT distinct log_id 
							    							   FROM `".WPSC_TABLE_SUBMITED_FORM_DATA."`
							                                   WHERE value like '%$search_on%')
					 )";
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
			$where .= " AND (".WPSC_TABLE_PURCHASE_LOGS.".date between '$from_date' and '$to_date') ";
		}
		$query    = "$select_query $from $where $group_by $limit_query";
		$results  = $wpdb->get_results($query, 'ARRAY_A');
		$num_rows = $wpdb->num_rows;
		
		//To get the total count
		$orders_count_query  = "$select_query $from $where $group_by";
		$orders_count_result = $wpdb->get_results($orders_count_query, 'ARRAY_A');		
		$num_records         = $wpdb->num_rows;
		
		if ($num_rows == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = 'No Records Found';
		} else {
			$count = 0;
			foreach ($results as $result) {
				foreach ($result as $key => $value ) {
					if ($key == 'order_details' || $key == 'shipping_ids' || $key == 'shipping_unique_names') {
						$order_details = explode ( '#', $result ['order_details'] );
						$shipping_ids = explode ( '#', $result ['shipping_ids'] );
						$shipping_unique_names = explode ( '#', $result ['shipping_unique_names'] );
						
						$name_emailid [0] = "<font class=blue> $order_details[0]</font>";
						$name_emailid [1] = "<font class=blue> $order_details[1]</font>";
						$name_emailid [2] = "($order_details[7])"; //email comes at 7th position.
						$records [$count] ['name'] = implode ( ' ', $name_emailid ); //in front end,splitting is done with this space.
						

						for($i = 3; $i < count ( $order_details ); $i ++)
							$records [$count] [$shipping_unique_names [$i]] = $order_details [$i];
					
					} else
						$records [$count] [$key] = $value;
				}
				$count ++;
			}
		}
	} else {
		//BOF Customer's module		

		//BOF getting the form data
		$form_data_query = "SELECT id,name FROM " . WPSC_TABLE_CHECKOUT_FORMS . "
							WHERE id BETWEEN 2 AND 9
							OR    id = 18";
		$form_data_result = $wpdb->get_results ( $form_data_query, 'ARRAY_A' );
		
		foreach ($form_data_result as $data){
			$form_data [] = $data ['id'] . "B_" . implode ( '_', explode ( ' ', $data ['name'] ) );
		}
		// EOF		

		if (SMPRO == true) {
			$customers_query = customers_query ( $_POST ['searchText'] );
		} else {
			$customers_query = " SELECT user_details,country
				             	  FROM   (SELECT ord_emailid.log_id,
													   user_details, 
															country
															 
									FROM    (SELECT log_id, value email
											FROM " . WPSC_TABLE_SUBMITED_FORM_DATA . " wwsfd1
											WHERE form_id = 9
											) AS ord_emailid
											
											INNER JOIN 
											(SELECT log_id, group_concat( wwsfd2.value
											ORDER BY form_id 
											SEPARATOR '#') user_details
											FROM " . WPSC_TABLE_SUBMITED_FORM_DATA . " wwsfd2
											WHERE form_id BETWEEN 2 AND 9
											OR    form_id = 18
											GROUP BY log_id
											) AS ord_all_user_details
											ON ( ord_emailid.log_id = ord_all_user_details.log_id )
											
											INNER JOIN
											(SELECT wwsfd.log_id,country
											 FROM  " . WPSC_TABLE_SUBMITED_FORM_DATA . " as wwsfd,
													" . WPSC_TABLE_CURRENCY_LIST . "
											
											 where wwsfd.value = isocode
											 and  wwsfd.form_id = (select id 
																	 from " . WPSC_TABLE_CHECKOUT_FORMS . "
																	 where unique_name = 'billingcountry'
																   )
                                             ) AS users_countries
                                             ON ( ord_emailid.log_id = users_countries.log_id)
                                           
										     GROUP BY email ) AS customers_info \n";
			
			if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
				$search_text = mysql_real_escape_string ( $_POST ['searchText'] );
				$customers_query .= "WHERE user_details LIKE '%$search_text%'
	    					         OR country   LIKE '$search_text%'";
			}
		}
		
		$limit_query = " LIMIT " . $offset . "," . $limit . "";
		$query = $customers_query . "" . $limit_query;
		$result   = $wpdb->get_results ( $query, 'ARRAY_A' );
		$num_rows = $wpdb->num_rows;
		
		//To get Total count
		$customers_count_query  = $customers_query;
		$customers_count_result = $wpdb->get_results ( $customers_count_query, 'ARRAY_A' );
		$num_records = $wpdb->num_rows;
		
		if ($num_rows == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = 'No Records Found';
		} else {			
			foreach ((array)$result as $data){
				$user_details = explode ( '#', $data ['user_details'] );
				//note: while merging the array, $data as to be the second arg
				if (count ( $form_data ) == count ( $user_details ))
					$records [] = array_merge ( array_combine ( $form_data, $user_details ), $data );
				else
					die ( 'ERROR: Array count mismatch' );
			}
			
			//getting records
			foreach ( $records as &$record ) {
				// change the orginal array
				$record ['7B_Country'] = $record ['country'];
				$record ['6B_Region'] = $record ['6B_State'];
				$record ['Last_Order'] = $record ['Last_Order_Date'] . ', ' . $record ['Last_Order_Amt'];
		//create an extra array for email and merge it with the actual array because if we allow user to edit email addresses
		//then we cannot fire a query using email in the where clause since in the backend we will get a modified email address.
				$record ['Old_Email_Id'] = $record ['9B_Email'];
				
				if (SMPRO == false) {
					$record ['Total_Purchased'] = 'Pro only';
					$record ['Last_Order'] = 'Pro only';
				}
			}
		}
	}
	
	$encoded ['items'] = $records;
	$encoded ['totalCount'] = $num_records;
	echo json_encode ( $encoded );
}


// Delete product.
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'delData') {
	global $purchlogs;
	$purchlogs = new wpsc_purchaselogs();
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
			$output = $purchlogs->deletelog( $id );
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
	global $wpdb;// to use as global
	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$_POST = array ();	
	
	$query  = "SELECT isocode,country FROM `" . WPSC_TABLE_CURRENCY_LIST . "` ORDER BY `country` ASC";
	$result = $wpdb->get_results( $query, 'ARRAY_A' );
	
	if (count($result) >= 1) {
		foreach ($result as $key => $arr_value)
		$countries [$arr_value ['isocode']] = $arr_value ['country'];
	}
		
	$query  = "SELECT id,unique_name FROM " . WPSC_TABLE_CHECKOUT_FORMS . " WHERE id NOT IN (1,10) AND id BETWEEN 11 AND 17";
	$result = $wpdb->get_results( $query, 'ARRAY_A' );
	
	if ( count($result) >= 1 ){
		foreach ($result as $key => $arr_value)
		$id_uniquename [$arr_value ['unique_name']] = $arr_value ['id'];
	}	
	
	$ordersCnt = 1;
	foreach ( $edited_object as $obj ) {
		
		$query = "UPDATE `" . WPSC_TABLE_PURCHASE_LOGS . "` 
					SET 		processed ='{$obj->order_status}',
								    notes ='{$obj->notes}',
								 track_id ='{$obj->track_id}'
				   				 WHERE id ='{$obj->id}'";
		$update_result = $wpdb->query($query);
		
		foreach ( $id_uniquename as $uniquename => $form_id ) {
			$update_value = $obj->$uniquename;
			
			if ($uniquename == 'shippingcountry') {
				$update_value = array_search ( $obj->$uniquename, $countries );
			}
			
			//$key contains unique name
			$query = "UPDATE `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`
				         SET value   = '" . $update_value . "'
				       WHERE form_id = $form_id
				         AND log_id  = '" . $obj->id . "'";
			$update_result = $wpdb->query($query);
		}
		$result ['updateCnt'] = $ordersCnt ++;
		unset ( $ship_country_info ); // unsetting the $ship_country_info
	}
	$result ['result'] = true;
	$result ['updated'] = 1;
	return $result;
}

function update_products($_POST) {
	global $result,$wpdb;
	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$updateCnt = 1;	
	foreach ( $edited_object as $obj ) {
				
		$update_name  = $wpdb->query("UPDATE $wpdb->posts SET `post_title`= '$obj->post_title' WHERE ID = $obj->id");
		$update_price = $wpdb->query("UPDATE $wpdb->postmeta SET `meta_value`= $obj->_wpsc_price WHERE meta_key = '_wpsc_price' AND post_id = $obj->id");		
		$result ['updateCnt'] = $updateCnt ++;
	}
	
	if (($update_name >= 1 || $update_price >= 1)  && $result ['updateCnt'] >= 1) {
		$result ['result'] = true;
		$result ['updated'] = 1;
	}
	return $result;
}

// For updating product and orders details.
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'saveData') {
	
	if ($active_module == 'Products') {
		if (SMPRO == true)
			$result = data_for_insert_update ( $_POST );
		else
			$result = update_products ( $_POST );
	} 

	elseif ($active_module == 'Orders')
		$result = data_for_update_orders ( $_POST );
	elseif ($_POST ['active_module'] == 'Customers')
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