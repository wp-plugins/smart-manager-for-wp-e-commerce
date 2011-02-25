<?php
global $purchlogs;
$allstatuses = $purchlogs->the_purch_item_statuses();
foreach ($allstatuses as $status)	
$order_status[$status->id] = $status->name;
?>
<select id="order_status" name="order_status" style="display: none;">
<?php 
foreach ($order_status as $status_id => $status_value){  ?>
<option name="<?php $status_id; ?>" value="<?php echo $status_value; ?>">
<?php echo $status_value;} ?> </option>
</select>

<div id="editor-grid"></div>
<select name="status" id="status" style="display: none;">
	<option value="Draft"><?php _e('Draft')?></option>
	<option value="Published"><? _e('Published')?></option>
</select>

<select name='weightUnit' id="weight_unit" style="display: none;">
	<option value='Pounds'><?php _e('Pounds')?></option>
	<option value='Ounces'><?php _e('Ounces')?></option>
	<option value='Grams'><?php _e('Grams')?></option>
	<option value='Kilograms'><?php _e('Kilograms')?></option>
</select>
<?php
global $wpdb;
$limit = 2;

$jsonURL    = plugins_url('/json.php', __FILE__);
$pluginURL  = dirname(dirname($jsonURL));
$imgURL     = $pluginURL.'/images/';
$smFilePath = dirname(dirname(__FILE__)).'/pro/sm.js';

// for full version check if the required file exists
if (SMPRO === true)
$fileExists = 1;
else
$fileExists = 0;

// to fetch Product categories START
(isset ( $_POST ['start'] )) ? $offset = $_POST ['start'] : $offset = 0;
$query ="SELECT pc.id   as category_id, 
				cg.name as group_name, 
				pc.name as category_name, 
				group_id
				
          FROM  ".WPSC_TABLE_PRODUCT_CATEGORIES." AS pc, 
          		".WPSC_TABLE_CATEGORISATION_GROUPS." AS cg
          		
          WHERE cg.active = 1 AND 
          		pc.active = 1 AND 
          		cg.id     = pc.group_id ";
$result = mysql_query ( $query );

while ( $data = mysql_fetch_assoc ( $result ) ) {
	$categories [$data ['group_id']][$data ['category_id']]['id']         = $data ['category_id'];
	$categories [$data ['group_id']][$data ['category_id']]['name']       = mysql_real_escape_string($data ['category_name']);
	$categories [$data ['group_id']][$data ['category_id']]['group_name'] = mysql_real_escape_string($data ['group_name']);
	$groups[$data ['group_id']]                                           = mysql_real_escape_string($data ['group_name']);
}

// get the category name and id, to assign it to the new products added.
foreach ((array)$categories as $category){
	foreach ($category as $cat){
		$cat_name = $cat['name'];
		$cat_id = $cat['id'];
		break;
	}
}
		echo '<script type="text/javascript">';
		foreach ( (array) $categories as $group_id => $category ) {
			$sub_group_count = 0 ;			
			echo 'categories[' . $group_id . '] = new Array(); '."\r\n";
			foreach ( $category as $key => $field_pair ) {
				echo '
       			categories[' . $group_id . ']['.$sub_group_count.'] = new Array();
       			categories[' . $group_id . ']['.$sub_group_count.'][0] = ' . $field_pair ['id'] . ';
       			categories[' . $group_id . ']['.$sub_group_count.'][1] = \'' . $field_pair ['name'] . '\';
       			';
				$sub_group_count ++;
			}
		}
		echo '</script>';

		// getting the products fieldnames.
			 $query = "SELECT ".WPSC_TABLE_PRODUCT_LIST.".name,
							  ".WPSC_TABLE_PRODUCT_LIST.".description, 
							  ".WPSC_TABLE_PRODUCT_LIST.".additional_description, 
							  ".WPSC_TABLE_PRODUCT_LIST.".price, 
							  ".WPSC_TABLE_PRODUCT_LIST.".special_price,							  
							  ".WPSC_TABLE_PRODUCT_LIST.".quantity,
							  ".WPSC_TABLE_PRODUCT_LIST.".weight,							  
							  ".WPSC_TABLE_PRODUCT_LIST.".publish,
							  ".WPSC_TABLE_PRODUCTMETA.".meta_value,
							  ".WPSC_TABLE_PRODUCTMETA.".meta_key,
							  ".WPSC_TABLE_PRODUCT_LIST.".quantity_limited,
							  ".WPSC_TABLE_VARIATION_PROPERTIES.".price as variation_price,
							  ".WPSC_TABLE_VARIATION_PROPERTIES.".weight as variation_weight
							  
					     FROM ".WPSC_TABLE_PRODUCTMETA.",".WPSC_TABLE_PRODUCT_LIST." 
					     LEFT JOIN ".WPSC_TABLE_VARIATION_PROPERTIES." 
					     		ON ".WPSC_TABLE_VARIATION_PROPERTIES.".product_id = ".WPSC_TABLE_PRODUCT_LIST.".id
					          
					    WHERE ".WPSC_TABLE_PRODUCTMETA.".meta_key in ('unpublish_oos','sku') AND
					    	  ".WPSC_TABLE_PRODUCT_LIST.".id = ".WPSC_TABLE_PRODUCTMETA.".product_id
					 ORDER BY ".WPSC_TABLE_PRODUCT_LIST.".id LIMIT 0,$limit";
			$result = mysql_query ( $query );
			
			while ($data = mysql_fetch_assoc($result)){
           	$fields_data[] = $data;
       	}
       	       	
       	for ($i=0;$i<mysql_num_fields($result);$i++){
       		$field_names ['items'] [$i] ['id']    = $i;
       		$field_names ['items'] [$i] ['name']  = mysql_field_name ( $result, $i );
       		$field_names ['items'] [$i] ['type']  = mysql_field_type ( $result, $i );
       		$field_names ['items'] [$i] ['value'] = mysql_field_name ( $result, $i ).','. mysql_field_table ( $result, $i );

       		if ($field_names ['items'] [$i] ['name'] == 'special_price')
       		$field_names ['items'] [$i] ['name'] = 'Sale Price';

       		elseif ($field_names ['items'] [$i] ['name'] == 'quantity')
       		$field_names ['items'] [$i] ['name'] = 'Inventory';

       		elseif ($field_names ['items'] [$i] ['name'] == 'quantity_limited')
       		$field_names ['items'] [$i] ['name'] = 'Stock: Quantity Limited';

       		elseif ($field_names ['items'] [$i] ['name'] == 'additional_description')
       		$field_names ['items'] [$i] ['name'] = 'Add. Description';

       		elseif ($field_names ['items'] [$i] ['name'] == 'variation_price'){
       			$field_names ['items'] [$i] ['name'] = 'Variations: Price';
       			$field_names ['items'] [$i] ['value'] = 'price,'.mysql_field_table ( $result, $i );
       			
       		}elseif ($field_names ['items'] [$i] ['name'] == 'variation_weight'){
       			$field_names ['items'] [$i] ['type']  = 'real'; //to use the action array of real
       			$field_names ['items'] [$i] ['name']  = 'Variations: Weight';
       			$field_names ['items'] [$i] ['value'] = 'weight,'.mysql_field_table ( $result, $i );
       			
       		}elseif ($field_names ['items'] [$i] ['name'] == 'meta_value'){
       			$field_names ['items'] [$i] ['name']  = 'SKU';
       			$field_names ['items'] [$i] ['type']  = 'blob';
       			$field_names ['items'] [$i] ['value'] = 'meta_value' . ', ' . mysql_field_table ( $result, $i );
       			
       		}elseif ($field_names ['items'] [$i] ['name'] == 'meta_key'){
       			$field_names ['items'] [$i] ['name'] = 'Stock: Inform When Out Of Stock';
       			$field_names ['items'] [$i] ['type'] = 'string'; //to use the action array of string
       			$field_names ['items'] [$i] ['value'] = 'meta_value' . ', ' . mysql_field_table ( $result, $i).','.'OOS';
       		}
       		$field_names ['items'] [$i] ['name'] = ucfirst($field_names ['items'] [$i] ['name']);
       	}

       	//appending categories's groups
       	foreach((array) $groups as $id => $group_name ) {
       		$field_names ['items'][$i]['id']    = $i;
       		$field_names ['items'][$i]['name']  = 'Group: '.$group_name ;
       		$field_names ['items'][$i]['type']  = 'category';
       		$field_names ['items'][$i]['value'] = $id;
       		$i ++;
       	}
       	$field_names ['totalCount'] = count($field_names ['items']);       	
       	$encodedProductsFields = json_encode ( $field_names );
       	// getting products fieldnames END
       	
						
			$weight_unit['items']=array(
			array('id'=>0, 'name'=>'Pounds', 'value'=>'pound'),
			array('id'=>1, 'name'=>'Ounces', 'value'=>'ounce'),
			array('id'=>2, 'name'=>'Grams', 'value'=>'gram'),
			array('id'=>3, 'name'=>'Kilograms', 'value'=>'kilogram')			
			);
			$weight_unit['totalCount'] = count($weight_unit['items']);
			$encodedWeightUnits = json_encode($weight_unit);
			// getting the fieldnames END
			
			//creating the order links
			$blog_info            = get_bloginfo('url');
			$orders_details_url   = "$blog_info/wp-admin/index.php?page=wpsc-sales-logs&purchaselog_id=";			
			
			//creating the products links
			$str_products_url = htmlspecialchars_decode ((wpsc_edit_the_product_link ( '', '', '', $id = '')));
			$regex_pattern = "/<a class=\'(.*)\' href=\'(.*)\'>(.*)<\/a>/";
			preg_match_all ( $regex_pattern, $str_products_url, $matches );			
			$products_details_url = "{$matches[2][0]}";			
			
			// getting orders fieldnames START
			$query  = "SELECT processed,track_id,notes FROM ".WPSC_TABLE_PURCHASE_LOGS."";
			$result = mysql_query($query);
			
			if (mysql_num_rows($result) >= 1){
			while ($data = mysql_fetch_assoc($result))
           		$ordersfield_data[] = $data;           	
           		$ordersfield_result = $ordersfield_data[0];
			}
           	
           	$ordersfield_names = array();
           	$cnt = 0;
           	foreach ((array)$ordersfield_result as $ordersfield_name => $ordersfield_value){
           	$ordersfield_names ['items'][$cnt]['id']    = $cnt;           	
			$ordersfield_names ['items'][$cnt]['name'] = ucfirst(mysql_field_name($result,$cnt));
			if($ordersfield_names ['items'][$cnt]['name'] == 'Processed')
				$ordersfield_names ['items'][$cnt]['name'] = 'Orders Status';
			if($ordersfield_names ['items'][$cnt]['name'] == 'Track_id')
				$ordersfield_names ['items'][$cnt]['name'] = 'Track Id';
			
			$ordersfield_names ['items'][$cnt]['type']  = mysql_field_type($result,$cnt);
		 if($ordersfield_names ['items'][$cnt]['type'] == 'int' && $ordersfield_names ['items'][$cnt]['name'] == 'Orders Status')
		 	$ordersfield_names ['items'][$cnt]['type'] = 'bigint';
		 	
		 if($ordersfield_names ['items'][$cnt]['type'] == 'string' && $ordersfield_names ['items'][$cnt]['name'] == 'Track Id')
		 	$ordersfield_names ['items'][$cnt]['type'] = 'blob';		 
			$ordersfield_names ['items'][$cnt]['value'] = mysql_field_name($result,$cnt).', '. mysql_field_table($result,$cnt);
			$cnt++;
           	}
           	
           	if (count($ordersfield_names) >= 1)
			$encodedOrdersFields = json_encode ( $ordersfield_names );
			else 
			$encodedOrdersFields = 0;
			
			$orderstatus_id = 0;
			foreach ((array)$order_status as $status_value => $status_name){
				$order_status['items'][$orderstatus_id]['id']    = $orderstatus_id;
				$order_status['items'][$orderstatus_id]['name']  = $status_name;
				$order_status['items'][$orderstatus_id]['value'] = $status_value;
				$order_status['totalCount'] = $orderstatus_id++;				
			}
			$encodedOrderStatus = json_encode($order_status);
			// getting orders fieldnames END
			
			
			//getting customers fieldnames START
			$form_data_query = "SELECT id,name 
								  FROM ".WPSC_TABLE_CHECKOUT_FORMS."
								 WHERE id not in (1,9)";
			$form_data_result = mysql_query ( $form_data_query );
			
			while ( $data = mysql_fetch_assoc ( $form_data_result ) ) {
			($data['id'] <= 8) ? $form_data [] = $data['id']."B_" . implode('_',explode(' ',$data ['name'])) :
			$form_data [] = $data['id']."S_" . implode('_',explode(' ',$data ['name']));
			}

			$customers_query = " SELECT log_id,user_details,Last_Order_Date,Total_Purchased,Last_Order_Amt,bns_countries
				             	 FROM (SELECT ord_emailid.log_id,user_details, 
									DATE_FORMAT(FROM_UNIXTIME(date), '%b %e %Y') Last_Order_Date, 
									sum( totalprice ) Total_Purchased, 
									totalprice Last_Order_Amt,bns_countries
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
											(SELECT id, date, totalprice
											FROM ".WPSC_TABLE_PURCHASE_LOGS." wwpl
											ORDER BY date DESC
											) AS purchlog_info
											ON ( purchlog_info.id = ord_emailid.log_id )
											
											INNER JOIN
											(select log_id,group_concat(country order by log_id) bns_countries
											FROM ".WPSC_TABLE_SUBMITED_FORM_DATA.",".WPSC_TABLE_CURRENCY_LIST."
											where form_id in (6,15) 
											and left(substring_index(value,'\"',-2),2) = isocode 
											group by log_id) as user_country
											ON ( ord_emailid.log_id = user_country.log_id)
											GROUP BY email ) as customers_info \n";
			$result = mysql_query($customers_query);
			
			while ( $data = mysql_fetch_assoc ( $result ) ) {
				$user_details = explode ( ',', $data ['user_details'] );				
				$combine = array_combine ( $form_data, $user_details );
				//note: while merging the array, $data as to be the second arg
				$records = array_merge ($combine, $data );
			}
			
			$cnt = 0;
			foreach ((array)$records as $records_key => $record){
				if (intval($records_key)){
					$customerFields['items'][$cnt]['id'] = $cnt;
					
					if (intval($records_key) >= 10)
					$customerFields['items'][$cnt]['name'] = 'Shipping '.str_replace('_',' ',substr($records_key,4));
					else
					$customerFields['items'][$cnt]['name'] = str_replace('_',' ',substr($records_key,3));

					if ($customerFields['items'][$cnt]['name'] == 'Shipping Country' || $customerFields['items'][$cnt]['name'] =='Country')
					$customerFields['items'][$cnt]['type']  = 'bigint';
					else
					$customerFields['items'][$cnt]['type']  = 'blob';
					
					$customerFields['items'][$cnt]['value'] = 'value'.', '.WPSC_TABLE_SUBMITED_FORM_DATA.', '.intval($records_key);
					$customerFields['totalCount'] = $cnt++;
				}			
			}
			if (count($customerFields) >= 1)
			$encodedCustomersFields = json_encode($customerFields);
			else 
			$encodedCustomersFields = 0;
			
			$query = "SELECT * FROM `".WPSC_TABLE_CURRENCY_LIST."` ORDER BY `country` ASC";
			$result = mysql_query($query);
			$count = 0;
			if (mysql_num_rows($result) >= 1){
			while ($data = mysql_fetch_assoc($result)){
				$countries['items'][$count]['id']    = $count;
				$countries['items'][$count]['name']  = $data['country'];				
				$countries['items'][$count]['value'] = $data['isocode'];
				$countries['totalCount'] = $count++;
			}}
			$encodedCountries = json_encode($countries);
			//getting customers fieldnames END
			
			echo '<script type="text/javascript">
			 var productsFields = ' . $encodedProductsFields . ';
			 var ordersFields = ' . $encodedOrdersFields . ';
			 var customersFields = ' . $encodedCustomersFields . ';
			 var countries = ' . $encodedCountries . ';
			 var weightUnits = ' . $encodedWeightUnits . ';
			 var orderStatus = ' . $encodedOrderStatus . ';
			 var newcat = \'' . $cat_name . '\';
			 var fileExists = \''.$fileExists.'\';
			 var newCatId = \'' . $cat_id . '\';
			 var jsonURL = \''.$jsonURL.'\';
			 var imgURL  = \''.$imgURL.'\';
			 var productsDetailsLink = \''.$products_details_url.'\';	
			 var ordersDetailsLink = \''.$orders_details_url.'\';
			</script>';
?>
<!-- Smart Manager FB Like Button -->
<div class="wrap">
<br/>
<iframe src="http://www.facebook.com/plugins/like.php?href=http%3A%2F%2Fwww.storeapps.org%2F&amp;layout=standard&amp;show_faces=true&amp;width=450&amp;action=like&amp;colorscheme=light&amp;height=80" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:450px; height:80px;" allowTransparency="true"></iframe>
</div>