<?php
include_once ('../../../../wp-load.php');
include_once ('../../../../wp-includes/wp-db.php');

$limit = 10;
$del = 3;
$result = array ();
$encoded = array ();

if (isset ( $_POST ['start'] ))
	$offset = $_POST ['start'];
else
	$offset = 0;

if (isset ( $_POST ['limit'] ))
	$limit = $_POST ['limit'];
	
// for pro version check if the required file exists
if (file_exists ( '../pro/sm.php' )) {
	define ( 'SMPRO', true );
} else {
	define ( 'SMPRO', false );
}
if (SMPRO == true)
	include_once ('../pro/sm.php');

// getting the active module
$active_module = $_POST ['active_module'];

// Searching a product in the grid
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getData') {
	if ($active_module == 'Products') { // <-products
	   $select_query = "SELECT pl.id,
	   						   pl.name,
	                           pl.description,
	                           pl.additional_description,
	                           pl.price,
	                        if(pl.quantity_limited = 1,pl.quantity,-1 ) as quantity,	                               
	                           pl.weight as weight,
	                        if(pl.publish = 1,'Published','Draft') as status,
	                           pl.price - pl.special_price as sale_price,
	                           pm.meta_value as sku,
	                           pm.meta_key,
	      	      			   group_concat(pc.name separator ', ') as category,
	                           CASE  pl.weight_unit
	                           WHEN 'pound' THEN 'Pounds'
	                           WHEN 'ounce' THEN 'Ounces'
	                           WHEN 'gram' THEN 'Grams'
	                           WHEN 'kilogram' THEN 'Kilograms'                                       
	                           ELSE 'Pounds'
	                           END as weight_unit";
		
		$from = " FROM ".WPSC_TABLE_PRODUCT_LIST." AS pl
            		       LEFT OUTER JOIN (".WPSC_TABLE_ITEM_CATEGORY_ASSOC." AS ic  
                           LEFT OUTER JOIN ".WPSC_TABLE_PRODUCT_CATEGORIES." AS pc 
                     	   ON (ic.category_id = pc.id) 
                     	   AND pc.active = 1)	ON ( pl.id = ic.product_id )
                     	   LEFT OUTER JOIN ".WPSC_TABLE_PRODUCTMETA." AS pm ON ( pl.id = pm.product_id AND meta_key = 'sku')";
		
		$where = " WHERE pl.active = 1 ";
		$group_by = " GROUP BY pl.id ";
		$limit_query = " LIMIT " . $offset . "," . $limit . "";
		
		if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_on = mysql_escape_string ( trim ( $_POST ['searchText'] ) );
			$where .= " AND ( concat(' ',pl.name) LIKE '% $search_on%' OR
										price LIKE '%$search_on%'  OR
                           	       	 quantity LIKE '%$search_on%'  OR
                              		   weight LIKE '%$search_on%'  OR
                              			CASE  weight_unit
                               			WHEN 'pound' THEN 'Pounds'
		                                WHEN 'ounce' THEN 'Ounces'
		                                WHEN 'gram' THEN 'Grams'
		                                WHEN 'kilogram' THEN 'Kilograms'
		                                ELSE 'Pounds'
		                                END LIKE '%$search_on%'
                               		OR if(pl.publish = 1,'Published','Draft') LIKE '%$search_on%'
                              	    OR special_price LIKE '%$search_on%'                                                                                            
                              	    OR concat(' ',pm.meta_value) LIKE '% $search_on%'
                              	    OR concat(' ',pc.name) LIKE '% $search_on%' )";
		}
		
		$recordcount_query = "SELECT COUNT( DISTINCT pl.id ) as count" . $from . "" . $where;
		$query = $select_query . "" . $from . "" . $where . "" . $group_by . "" . $limit_query;
		$result = mysql_query ( $query );
		$num_rows = mysql_num_rows ( $result );
		$recordcount_result = mysql_query ( $recordcount_query );
		$no_of_records = mysql_fetch_assoc ( $recordcount_result );
		$num_records = $no_of_records ['count'];
		if ($num_rows == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = 'No Records Found';
		} else {
			while ( $data = mysql_fetch_assoc ( $result ) )
				$records [] = $data;
		}
	} //products ->
elseif ($active_module == 'Orders') {
		global $purchlogs;
		$select_query = "SELECT id,date,name,amount,track_id,order_status,details,notes";		
		
		$from = " FROM (SELECT GROUP_CONCAT( ".WPSC_TABLE_SUBMITED_FORM_DATA.".value 
											ORDER BY ".WPSC_TABLE_SUBMITED_FORM_DATA.".`form_id` 
							SEPARATOR '#' ) AS name, ".WPSC_TABLE_PURCHASE_LOGS.".id, 
					  	   date_format(FROM_UNIXTIME(".WPSC_TABLE_PURCHASE_LOGS.".date),'%b %e %Y, %r') date,
					  	                             ".WPSC_TABLE_PURCHASE_LOGS.".date as unixdate,
					  	                             ".WPSC_TABLE_PURCHASE_LOGS.".date order_time,
						   						     ".WPSC_TABLE_PURCHASE_LOGS.".totalprice amount,
						   							 ".WPSC_TABLE_PURCHASE_LOGS.".track_id, 			                 
												CASE ".WPSC_TABLE_PURCHASE_LOGS.".processed 
												WHEN 1 THEN 'Order Received'
												WHEN 2 THEN 'Accepted Payment'
												WHEN 3 THEN 'Job Dispatched'
												ELSE 'Closed Order'
												END order_status,
                            sessionid,
                            ".WPSC_TABLE_PURCHASE_LOGS.".notes
						    FROM ".WPSC_TABLE_SUBMITED_FORM_DATA.", 
						    	 ".WPSC_TABLE_PURCHASE_LOGS."
						    	 
							WHERE ".WPSC_TABLE_SUBMITED_FORM_DATA.".log_id = ".WPSC_TABLE_PURCHASE_LOGS.".id
							 AND  ".WPSC_TABLE_SUBMITED_FORM_DATA.".form_id IN ( 2, 3, 8  ) 
							 
							GROUP BY ".WPSC_TABLE_SUBMITED_FORM_DATA.".log_id
							ORDER BY form_id DESC) as purchlog_info 
							
							INNER JOIN (SELECT CONCAT(CAST(sum(quantity) AS CHAR) , ' items') details,
							GROUP_CONCAT(name) product_name,purchaseid
							
							FROM ".WPSC_TABLE_CART_CONTENTS."
							GROUP BY ".WPSC_TABLE_CART_CONTENTS.".purchaseid) as quantity_details 
							ON (purchlog_info.id = quantity_details.purchaseid)";
		
		$limit_query = " LIMIT " . $offset . "," . $limit . "";
		$where = ' WHERE 1 ';
		
		if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_on = mysql_escape_string ( trim ( $_POST ['searchText'] ) );
			$where .= " AND (purchlog_info.id in ('$search_on')
						  OR purchlog_info.sessionid like '%$search_on%'
						  OR purchlog_info.date like '%$search_on%'
						  OR purchlog_info.name like '%$search_on%'
						  OR purchlog_info.amount like '$search_on%'
						  OR purchlog_info.track_id like '%$search_on%'
						  OR purchlog_info.order_status like '%$search_on%'
						  OR purchlog_info.notes like '%$search_on%'
						  OR quantity_details.details like '%$search_on%' 
						  OR quantity_details.product_name like '%$search_on%') ";
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
			$where .= " AND (purchlog_info.unixdate between '$from_date' and '$to_date') ";
		}
		
		$query = $select_query . " " . $from . " " . $where . " " . $limit_query;
		$result = mysql_query ( $query );
		$num_rows = mysql_num_rows ( $result );
		
		//To get the total count
		$orders_count_query  = $select_query . " " . $from . " " . $where ;
		$orders_count_result = mysql_query($orders_count_query);		
		$num_records  = mysql_num_rows ( $orders_count_result );
		
		if ($num_rows == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = 'No Records Found';
		} else {
			$count = 0;
			while ( $data = mysql_fetch_assoc ( $result ) ) {
				foreach ( $data as $key => $value ) {
					if ($key == 'name') {
						$name_emailid    = explode ( '#', $data ['name'] );
						$name_emailid[0] = "<font class=blue> $name_emailid[0]</font>";
						$name_emailid[1] = "<font class=blue> $name_emailid[1]</font>";
						$name_emailid[2] = "($name_emailid[2])";
						$records [$count]['name'] = implode (' ',$name_emailid);//in front end,splitting is done with this space.
					}
					 else
						$records [$count][$key] = $value;
				}
				$count ++;
			}
		}
	} else {
		//BOF Customer's module
		
		//BOF getting the form data
		$form_data_query = "SELECT id,name FROM ".WPSC_TABLE_CHECKOUT_FORMS."
							where id not in (1,9)";		
		$form_data_result = mysql_query ( $form_data_query );
		while ( $data = mysql_fetch_assoc ( $form_data_result ) )
			($data ['id'] <= 8) ? $form_data [] = $data ['id'] . "B_" . implode ( '_', explode ( ' ', $data ['name'] ) ) : $form_data [] = $data ['id'] . "S_" . implode ( '_', explode ( ' ', $data ['name'] ) );
		// EOF
		 
		if (SMPRO == true) {
			$customers_query = customers_query($_POST ['searchText']);
		}else {
		$customers_query = " select user_details,bns_countries
				             FROM   (SELECT ord_emailid.log_id,
									user_details, 
									bns_countries
									FROM    (SELECT log_id, value email
											FROM ".WPSC_TABLE_SUBMITED_FORM_DATA." wwsfd1
											WHERE form_id =8
											) AS ord_emailid
											
											INNER JOIN 
											(SELECT log_id, group_concat( wwsfd2.value
											ORDER BY form_id ) user_details
											FROM ".WPSC_TABLE_SUBMITED_FORM_DATA." wwsfd2
											GROUP BY log_id
											) AS ord_all_user_details
											ON ( ord_emailid.log_id = ord_all_user_details.log_id )
											
											INNER JOIN
											(select log_id,group_concat(country order by form_id) bns_countries
											FROM ".WPSC_TABLE_SUBMITED_FORM_DATA.",".WPSC_TABLE_CURRENCY_LIST."
											where form_id in (6,15) 
											and left(substring_index(value,'\"',-2),2) = isocode 
											group by log_id) as user_country
											ON ( ord_emailid.log_id = user_country.log_id)
											GROUP BY email ) as customers_info \n";
		
		if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_text = mysql_real_escape_string ( $_POST ['searchText'] );
			$customers_query .= "WHERE user_details like '%$search_text%'
	    					     OR bns_countries   like '$search_text%'";
		}
		}
		$limit_query = " LIMIT " . $offset . "," . $limit . "";
		$query    = $customers_query."".$limit_query;
		$result   = mysql_query ( $query );
		$num_rows = mysql_num_rows ( $result );
		
		//To get Total count
		$customers_count_query  = $customers_query;
		$customers_count_result = mysql_query ( $customers_count_query );
		$num_records = mysql_num_rows ( $customers_count_result );
		
		if ($num_rows == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = 'No Records Found';
		} else {
			while ( $data = mysql_fetch_assoc ( $result ) ) {
				$user_details = explode ( ',', $data ['user_details'] );
				
				//note: while merging the array, $data as to be the second arg
				$records[] = array_merge ( array_combine ( $form_data, $user_details ), $data );				
			}			
			//getting records
			foreach ( $records as &$record ) {
				// change the orginal arr
				$bns_countries = explode ( ',', $record ['bns_countries'] );
				$record ['6B_Country'] = $bns_countries [0];
				$record ['Last_Order'] = $record ['Last_Order_Date']. ', '.$record ['Last_Order_Amt'] ;				
				
				//create an extra array for email and merge it with the actual array because if we allow user to edit email addresses
				//then we cannot fire a query using email in the where clause since in the backend we will get a modified email address.
				$record['Old_Email_Id'] = $record['8B_Email'];

				//shipping details
				$bns_countries = explode(',',$record['bns_countries']);
				$record['15S_Country']  = $bns_countries[1];
				
				if(SMPRO == false) {
					$record ['Total_Purchased']  = 'Pro only';
					$record ['Last_Order']       = 'Pro only';    
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
	
	$delCnt = 0;
	if ($active_module == 'Products') {
		$data = json_decode ( stripslashes ( $_POST ['data'] ) );
		$data = implode ( ',', $data );
		$query = "UPDATE ".WPSC_TABLE_PRODUCT_LIST." SET active = 0 WHERE id in ($data)";
		$result = mysql_query ( $query );
		$delCnt = mysql_affected_rows ();
		if ($result) {
			if ($delCnt == 1) {
				$encoded ['msg'] = $delCnt . " Product deleted Successfully";
				$encoded ['delCnt'] = $delCnt;
			} else {
				$encoded ['msg'] = $delCnt . " Products deleted Successfully";
				$encoded ['delCnt'] = $delCnt;
			}
		} else
			$encoded ['msg'] = "Products removed from the grid";
	} else if ($active_module == 'Orders') {
		global $purchlogs;
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
	global $purchlogs, $wpsc_purchase_log_statuses;
	$all_status_info = wpsc_the_purch_item_statuses ();
	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$_POST = array ();
	
	$status_count = 0;
	while ( $status_count < count ( $all_status_info ) ) :
		$status_id_pair [$all_status_info [$status_count]->id] = $all_status_info [$status_count]->name;
		$status_count ++;
	endwhile;
	
	$ordersCnt = 1;
	foreach ( $edited_object as $obj ) {
		//setting the status id
		$obj->order_status = array_search ( $obj->order_status, $status_id_pair );
		$query = "UPDATE `" .WPSC_TABLE_PURCHASE_LOGS. "`
				                    SET processed ='{$obj->order_status}',notes='{$obj->notes}',track_id ='{$obj->track_id}'
				                    WHERE id='{$obj->id}'";
		$update_result = mysql_query ( $query );
		$result ['updateCnt'] = $ordersCnt ++;
	}
	$result ['result'] = true;
	$result ['updated'] = 1;
	return $result;
}

function update_products($_POST) {
	global $table_prefix, $result;
	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$updateCnt = 1;
	foreach ( $edited_object as $obj ) {
		$query = "UPDATE ".WPSC_TABLE_PRODUCT_LIST." SET name = '$obj->name',
                                         	price = $obj->price
                                      	 WHERE id = $obj->id";
		$update_productListTbl = mysql_query ( $query );
		$result ['updateCnt'] = $updateCnt ++;
	}
	if ($update_productListTbl && $result ['updateCnt'] >= 1) {
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
		$result = update_customers($_POST);
		
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
				if ($result ['updateCnt'] == 1)
					$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> Record Updated Successfully";
				else
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