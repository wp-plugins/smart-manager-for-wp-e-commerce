<?php
include_once ('../../../../wp-load.php');
include_once ('../../../../wp-includes/wp-db.php');
include_once ( ABSPATH . WPINC . '/functions.php');

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
	
// For pro version check if the required file exists
if (file_exists ( '../pro/sm37.php' )) {
	define ( 'SMPRO', true );
} else {
	define ( 'SMPRO', false );
}
if (SMPRO == true)
	include_once ('../pro/sm37.php');
	
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
							   pl.no_shipping as no_shipping,
	                           pl.pnp,
	                           pl.international_pnp,
	                        if(pl.quantity_limited = 1,pl.quantity,-1 ) as quantity,
	                           pl.weight as weight,
	                        if(pl.publish = 1,'publish','draft') as publish,
	                           pl.price - pl.special_price as sale_price,
	                           sku_dimension,
	      	      			   GROUP_CONCAT(pc.name separator ', ') as category,
	                           pl.weight_unit as weight_unit";
		
		$from = " FROM ".WPSC_TABLE_PRODUCT_LIST." AS pl
            		       LEFT OUTER JOIN (".WPSC_TABLE_ITEM_CATEGORY_ASSOC." AS ic  
                           LEFT OUTER JOIN  ".WPSC_TABLE_PRODUCT_CATEGORIES." AS pc
                     	   ON (ic.category_id = pc.id) 
                     	   AND pc.active = 1)	ON ( pl.id = ic.product_id )
                     	   LEFT OUTER JOIN 
                     	   (SELECT GROUP_CONCAT(meta_value ORDER BY id) sku_dimension,product_id
                     	    FROM  ".WPSC_TABLE_PRODUCTMETA." 
                     	    WHERE meta_key = 'sku' 
                     	    OR meta_key = 'dimensions'
                     	    GROUP BY product_id) pm
                     	    ON ( pl.id = pm.product_id)";
		
		$where = " WHERE pl.active = 1 ";
		$group_by = " GROUP BY pl.id ";
		$limit_query = " LIMIT " . $offset . "," . $limit . "";
		
		if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_on = mysql_escape_string ( trim ( $_POST ['searchText'] ) );
			$where .= " AND ( concat(' ',pl.name) LIKE '% $search_on%' OR
										price LIKE '%$search_on%'  OR
                           	       	 quantity LIKE '%$search_on%'  OR
                              		   weight LIKE '%$search_on%'  OR
                               pl.weight_unit LIKE '%$search_on%'
                               		OR if(pl.publish = 1,'publish','draft') LIKE '%$search_on%'
                               		OR if(pl.no_shipping = 1,'Yes','No') LIKE '%$search_on%'
                              	    OR pl.price - pl.special_price LIKE '%$search_on%'
                              	    OR concat(' ',pc.name) LIKE '% $search_on%'
                              	    OR pl.pnp LIKE '%$search_on%'
                              	    OR sku_dimension LIKE '%$search_on%'
                              	    OR pl.international_pnp LIKE '%$search_on%'
                              	    OR pl.description LIKE '%$search_on%'
                              	    OR if(pl.quantity_limited = 1,pl.quantity,-1 ) LIKE '%$search_on%'                              	    
                              	    OR pl.additional_description LIKE '%$search_on%') ";
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
			while ($data = mysql_fetch_assoc($result))
			$records[] = $data;
		}
		
		$i = 0;
		// compare $i against $num_rows and not against $num_records
		// since $num_records gives the overall total count of the records in the database
		// whereas $num_rows gives the total count of records from current query
		while ($i < $num_rows ){
			if (is_array($records[$i])){
				foreach ($records[$i] as $record_key => $record_value){
					if ($record_key == 'sku_dimension')
					$sku_dimension_arr = explode(',',$record_value);

					$dimension_arr 				= unserialize($sku_dimension_arr[1]);
					$records[$i]['sku']         = $sku_dimension_arr[0];
					$records[$i]['height']      = $dimension_arr['height'];
					$records[$i]['height_unit'] = $dimension_arr['height_unit'];
					$records[$i]['width']       = $dimension_arr['width'];
					$records[$i]['width_unit']  = $dimension_arr['width_unit'];
					$records[$i]['length']      = $dimension_arr['length'];
					$records[$i]['length_unit'] = $dimension_arr['length_unit'];
					unset($records[$i]['sku_dimension']);
				}
			}
			$i++;
		}
	} //products ->
elseif ($active_module == 'Orders') {
		$query = "SELECT id,country_id, name, code FROM ".WPSC_TABLE_REGION_TAX;
			$result = mysql_query($query);

			if (mysql_num_rows($result) >= 1){
				while ($data = mysql_fetch_assoc($result))
					$regions[$data['id']] = $data['name'];
			}
			
		$query = "SELECT isocode,country FROM `".WPSC_TABLE_CURRENCY_LIST."` ORDER BY `country` ASC";
		$result = mysql_query($query);

		if (mysql_num_rows($result) >= 1){
			while ($data = mysql_fetch_assoc($result))
				$countries[$data['isocode']] = $data['country'];
		}
					
		$select_query = "SELECT id,date,order_details,shipping_ids,shipping_unique_names,amount,track_id,order_status,details,notes";
		
		$from = " FROM (SELECT GROUP_CONCAT( " . WPSC_TABLE_SUBMITED_FORM_DATA . ".value 
							   ORDER BY " . WPSC_TABLE_SUBMITED_FORM_DATA . ".`form_id` 
							   SEPARATOR '#' ) AS order_details, 
							   GROUP_CONCAT( CAST(form_id AS CHAR)
						       ORDER BY form_id  
							   SEPARATOR '#' ) AS shipping_ids,
							   GROUP_CONCAT(" . WPSC_TABLE_CHECKOUT_FORMS . ".unique_name
						       ORDER BY " . WPSC_TABLE_CHECKOUT_FORMS . ".`id` 
							   SEPARATOR '#' ) AS shipping_unique_names,
						       " . WPSC_TABLE_PURCHASE_LOGS . ".id, 
					  	       date_format(FROM_UNIXTIME(" . WPSC_TABLE_PURCHASE_LOGS . ".date),'%b %e %Y, %r') date,
						  	                             " . WPSC_TABLE_PURCHASE_LOGS . ".date as unixdate,
						  	                             " . WPSC_TABLE_PURCHASE_LOGS . ".date order_time,
							   						     " . WPSC_TABLE_PURCHASE_LOGS . ".totalprice amount,
							   							 " . WPSC_TABLE_PURCHASE_LOGS . ".track_id, 			                 
														 " . WPSC_TABLE_PURCHASE_LOGS . ".processed order_status,
                            						sessionid,
                            " . WPSC_TABLE_PURCHASE_LOGS . ".notes
						    FROM " . WPSC_TABLE_SUBMITED_FORM_DATA . ", 
						    	 " . WPSC_TABLE_PURCHASE_LOGS . ",
						    	 " . WPSC_TABLE_CHECKOUT_FORMS . "
						    	 
							WHERE " . WPSC_TABLE_SUBMITED_FORM_DATA . ".log_id = " . WPSC_TABLE_PURCHASE_LOGS . ".id
							 AND form_id = " . WPSC_TABLE_CHECKOUT_FORMS . ".id
							 AND  " . WPSC_TABLE_SUBMITED_FORM_DATA . ".form_id IN ( 2, 3, 8,9,10,11,12,13,15,16) 
							 
							GROUP BY " . WPSC_TABLE_SUBMITED_FORM_DATA . ".log_id
							ORDER BY form_id DESC) as purchlog_info 
							
							INNER JOIN (SELECT CONCAT(CAST(sum(quantity) AS CHAR) , ' items') details,
							GROUP_CONCAT(name) product_name,purchaseid
							
							FROM " . WPSC_TABLE_CART_CONTENTS . "
							GROUP BY " . WPSC_TABLE_CART_CONTENTS . ".purchaseid) as quantity_details 
							ON (purchlog_info.id = quantity_details.purchaseid)
							INNER JOIN
							(SELECT  log_id,form_id,country,".WPSC_TABLE_REGION_TAX.".name as region
											FROM
											(SELECT log_id,form_id,country,CAST(CAST(SUBSTRING_INDEX(value,'\"',-2) AS signed)AS char) AS region_id
											FROM ".WPSC_TABLE_SUBMITED_FORM_DATA.",".WPSC_TABLE_CURRENCY_LIST." wwcl WHERE form_id =15
											AND RIGHT(SUBSTRING_INDEX(value,'\"',2),2) = isocode
											) AS country_info
											LEFT OUTER JOIN ".WPSC_TABLE_REGION_TAX."  
											ON (country_info.region_id = ".WPSC_TABLE_REGION_TAX.".id)) as countries_regions 
						                    ON (purchlog_info.id = countries_regions.log_id) ";
		
		$limit_query = " LIMIT " . $offset . "," . $limit . "";
		$where = ' WHERE 1 ';
		
		if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_on = mysql_escape_string ( trim ( $_POST ['searchText'] ) );
			$where .= " AND (purchlog_info.id in ('$search_on')
						  OR purchlog_info.sessionid like '%$search_on%'
						  OR purchlog_info.date like '%$search_on%'
						  OR purchlog_info.order_details like '%$search_on%'
						  OR purchlog_info.amount like '$search_on%'
						  OR purchlog_info.track_id like '%$search_on%'
						  OR purchlog_info.order_status like '%$search_on%'
						  OR purchlog_info.notes like '%$search_on%'
						  OR quantity_details.details like '%$search_on%' 
						  OR quantity_details.product_name like '%$search_on%' 
						  OR countries_regions.region like '%$search_on%'
						  OR countries_regions.country like '%$search_on%')";
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
		
		$query = $select_query . " " . $from . "" . $where . " " . $limit_query;
		$result = mysql_query ( $query );
		$num_rows = mysql_num_rows ( $result );
		
		//To get the total count
		$orders_count_query = $select_query . " " . $from . " " . $where;
		$orders_count_result = mysql_query ( $orders_count_query );
		$num_records = mysql_num_rows ( $orders_count_result );
		
		if ($num_rows == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = 'No Records Found';
		} else {
			$count = 0;
			while ( $data = mysql_fetch_assoc ( $result ) ) {
				foreach ( $data as $key => $value ) {
					if ($key == 'order_details' || $key == 'shipping_ids' || $key == 'shipping_unique_names') {
						$order_details = explode ( '#', $data ['order_details'] );
						$shipping_ids = explode ( '#', $data ['shipping_ids'] );
						$shipping_unique_names = explode ( '#', $data ['shipping_unique_names'] );
						
						$name_emailid [0] = "<font class=blue> $order_details[0]</font>";
						$name_emailid [1] = "<font class=blue> $order_details[1]</font>";
						$name_emailid [2] = "($order_details[2])";
						$records [$count] ['name'] = implode ( ' ', $name_emailid ); //in front end,splitting is done with this space.
						//@todo confirm do u req formid in dataindex
						for($i = 3; $i < count ( $order_details ); $i ++) {
							// creating key by concat(id,unique name)
							if($shipping_unique_names [$i] == 'shippingcountry') {
								$order_details [$i]         = unserialize($order_details [$i]);
								$records [$count] [$shipping_unique_names [$i]] = $countries[$order_details[$i][0]];
								$records [$count] ['shippingstate'] = $regions[$order_details[$i][1]];
							}else {	
							    $records [$count] [$shipping_unique_names [$i]] = $order_details[$i];
							}
						}
					} else
						$records [$count] [$key] = $value;
				}
				$count ++;
			}			
		}	
	} else {
		//Customer's module
		if (SMPRO == true) {
			$customers_query = customers_query ( $_POST ['searchText'] );
		} else {
			$customers_query = "SELECT log_id AS id,user_details,Last_Order_Date,Total_Purchased,Last_Order_Amt,country,region
                            FROM   (SELECT ord_emailid.log_id,
                                   user_details,
                                   DATE_FORMAT(FROM_UNIXTIME(date), '%b %e %Y') AS Last_Order_Date,
                                   SUM( totalprice ) Total_Purchased,
                                   totalprice AS Last_Order_Amt,country,region
                                   FROM    (SELECT log_id, value email
                                           FROM " . WPSC_TABLE_SUBMITED_FORM_DATA . " wwsfd1
                                           WHERE form_id =( SELECT id
															  FROM ". WPSC_TABLE_CHECKOUT_FORMS ."
											WHERE unique_name =  'billingemail')) AS ord_emailid
											
                                            INNER JOIN
									( SELECT log_id, 
									GROUP_CONCAT( wwsfd2.value ORDER BY form_id SEPARATOR  '#' ) user_details, 
									GROUP_CONCAT( wwcf.unique_name ORDER BY wwcf.id SEPARATOR  '#' ) unique_names					
									FROM ". WPSC_TABLE_SUBMITED_FORM_DATA ." as wwsfd2 
					
									LEFT JOIN ". WPSC_TABLE_CHECKOUT_FORMS ." wwcf ON ( wwcf.id = wwsfd2.form_id) 					
									WHERE unique_name
									IN (
										'billingfirstname',  
										'billinglastname',  
										'billingaddress',  
										'billingcity',  
										'billingstate',
										'billingcountry',
										'billingpostcode',
										'billingemail',
										'billingphone'
									) 
									GROUP BY log_id
									) AS ord_all_user_details ON ( ord_emailid.log_id = ord_all_user_details.log_id ) 
                                                                                      INNER JOIN
                                           (SELECT id, date, totalprice
                                           FROM " . WPSC_TABLE_PURCHASE_LOGS . " wwpl
                                           ORDER BY date DESC
                                           ) AS purchlog_info
                                           ON ( purchlog_info.id = ord_emailid.log_id )
                                                                                      INNER JOIN
                                           (SELECT  log_id,form_id,country," . WPSC_TABLE_REGION_TAX . ".name as region
                                           FROM
                                           (SELECT log_id,form_id,country,CAST(CAST(SUBSTRING_INDEX(value,'\"',-2) AS signed)AS char) AS region_id
                                           FROM " . WPSC_TABLE_SUBMITED_FORM_DATA . "," . WPSC_TABLE_CURRENCY_LIST . " wwcl WHERE form_id = 6
                                           AND RIGHT(SUBSTRING_INDEX(value,'\"',2),2) = isocode
                                           ) AS country_info
                                           LEFT OUTER JOIN " . WPSC_TABLE_REGION_TAX . " ON (country_info.region_id = " . WPSC_TABLE_REGION_TAX . ".id)) AS user_country_regions
                                           ON ( ord_emailid.log_id = user_country_regions.log_id)
                                           GROUP BY email ) AS customers_info \n";
			
			if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
				$search_text = mysql_real_escape_string ( $_POST ['searchText'] );
				$customers_query .= "WHERE user_details LIKE '%$search_text%'
	    					         OR country   LIKE '$search_text%'
	    					         OR region   LIKE '$search_text%'";
			}
		}
		$limit_query = " LIMIT " . $offset . "," . $limit . "";
		$query = $customers_query . "" . $limit_query;
		$result = mysql_query ( $query );
		$num_rows = mysql_num_rows ( $result );
		
		//To get Total count
		$customers_count_query = $customers_query;
		$customers_count_result = mysql_query ( $customers_count_query );
		$num_records = mysql_num_rows ( $customers_count_result );
		
		if ($num_rows == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = 'No Records Found';
		} else {
			while ( $data = mysql_fetch_assoc ( $result ) ) {
				$user_details = explode ( '#', $data ['user_details'] );
				$unique_names = explode ( '#', $data ['unique_names'] );
				
				//note: while merging the array, $data as to be the second arg
				if (count ( $unique_names ) == count ( $user_details ))
					$records [] = array_merge ( array_combine($unique_names, $user_details), $data );				
			}			
			
			//getting records
			foreach ( $records as &$record ) {				
				$record ['Last_Order'] = $record ['Last_Order_Date'] . ', ' . $record ['Last_Order_Amt'];
				
//				$country_state_code  = unserialize($record ['billingcountry']);				
//				$record ['billingcountry'] = $country_state_code[0];
//				$record ['billingstate']   = $country_state_code[1];
				
				$record ['billingcountry'] = $record['country'];
				$record ['billingstate']   = $record['region'];
				
		//create an extra array for email and merge it with the actual array because if we allow user to edit email addresses
		//then we cannot fire a query using email in the where clause since in the backend we will get a modified email address.
				$record ['Old_Email_Id'] = $record ['billingemail'];
				
				//no need to send this to front end
				unset($record['unique_names']);
				unset($record['user_details']);
				unset($record ['country']);
				unset($record ['region']);				
				
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
	
	$delCnt = 0;
	if ($active_module == 'Products') {
		$data = json_decode ( stripslashes ( $_POST ['data'] ) );
		$data = implode ( ',', $data );
		$query = "UPDATE " . WPSC_TABLE_PRODUCT_LIST . " SET active = 0 WHERE id in ($data)";
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
	$query = "SELECT id,country_id, name, code FROM ".WPSC_TABLE_REGION_TAX;
	$res = mysql_query($query);

	if (mysql_num_rows($res) >= 1){
		while ($data = mysql_fetch_assoc($res))
		$regions[$data['id']] = $data['name'];
	}

	$query = "SELECT isocode,country FROM `".WPSC_TABLE_CURRENCY_LIST."` ORDER BY `country` ASC";
	$res = mysql_query($query);

	if (mysql_num_rows($res) >= 1){
		while ($data = mysql_fetch_assoc($res))
		$countries[$data['isocode']] = $data['country'];
	}

	//getting the id,uniquename
	$query  = "SELECT id,name,unique_name
		 		FROM " . WPSC_TABLE_CHECKOUT_FORMS . " 
				WHERE unique_name IN ('shippingfirstname', 'shippinglastname', 'shippingaddress', 'shippingcity', 'shippingstate','shippingcountry', 'shippingpostcode')";
	$res    = mysql_query($query);
	while($data = mysql_fetch_assoc($res))
	$id_uniquename[$data['unique_name']] = $data['id'];

	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$_POST = array ();
	$ordersCnt = 1;
	foreach ( $edited_object as $obj ) {
		$query = "UPDATE `" . WPSC_TABLE_PURCHASE_LOGS . "`
				                    SET processed ='{$obj->order_status}',notes='{$obj->notes}',track_id ='{$obj->track_id}'
				                    WHERE id='{$obj->id}'";
		$update_result = mysql_query ( $query );

		foreach ($id_uniquename as $uniquename => $form_id) {
			$update_value = $obj->$uniquename;

			if ($uniquename == 'shippingcountry' || $uniquename == 'shippingstate'){
				$b_country  = array();

				if (array_search ($obj->shippingcountry, $countries))
				$b_country [] = array_search ($obj->shippingcountry, $countries);

				if(array_search ( $obj->shippingstate, $regions ))
				$b_country [] = (string)array_search ( $obj->shippingstate, $regions );

				$update_value = serialize ( $b_country );
			}

			$query  = "UPDATE `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`
				              SET value = '".$update_value."'
				              WHERE form_id = $form_id
				              AND  log_id   = '".$obj->id."'";
			$update_result = mysql_query($query);
		}
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
		$query = "UPDATE " . WPSC_TABLE_PRODUCT_LIST . " SET name = '$obj->name',
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