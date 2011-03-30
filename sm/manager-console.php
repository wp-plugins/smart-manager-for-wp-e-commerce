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

<select name="disregard_shipping" id="disregard_shipping" style="display: none;">
	<option value="Yes"><?php _e('Yes')?></option>
	<option value="No"><? _e('No')?></option>
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
//defining the admin url
define('ADMIN_URL',get_admin_url());

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
		$product_field_names = array
		(
		0 => 'name',
		1 => 'description',
		2 => 'additional_description',
		3 => 'price',
		4 => 'special_price',
		5 => 'quantity',
		6 => 'publish',
		7 => 'pnp',
		8 => 'international_pnp',
		9 => 'no_shipping',
		10 => 'weight',
		11 => 'sku',
		12 => 'unpublish_oos',
		13 => 'quantity_limited',
		14 => 'variation_price',
		15 => 'variation_weight'
		);

		$i = 0;
		foreach ($product_field_names as $product_field_name){
			$field_names ['items'] [$i] ['id']    = $i;
			$field_names ['items'] [$i] ['name']  = ucfirst($product_field_name);
			$field_names ['items'] [$i] ['type']  = 'real';

			//			 product list table
			if ($i <= 10 || $i == 13){
				if ($i <=2 ){
					if ($i ==2 )
					$field_names ['items'] [$i] ['name']  = 'Add. Description';
					$field_names ['items'] [$i] ['type']  = 'blob';
				}elseif ($i == 5 ){
					$field_names ['items'] [$i] ['name']  = 'Inventory';
					$field_names ['items'] [$i] ['type']  = 'int';
				}elseif ($i == 4){
					$field_names ['items'] [$i] ['name']  = 'Sale Price';
				}elseif ($i == 7){
					$field_names ['items'] [$i] ['name']  = 'Local Shipping Fee';
				}elseif ($i == 8){
					$field_names ['items'] [$i] ['name']  = 'International Shipping Fee';
				}elseif ($i == 9){
					$field_names ['items'] [$i] ['name']  = 'Disregard Shipping';
					$field_names ['items'] [$i] ['type']  = 'string';
				}elseif (($i == 6 || $i == 13 )){
					if ($i == 13)
					$field_names ['items'] [$i] ['name']  = 'Stock: Quantity Limited';
					$field_names ['items'] [$i] ['type']  = 'string';
				}
				$field_names ['items'] [$i] ['value'] = $product_field_name.','. WPSC_TABLE_PRODUCT_LIST;
			}

			//			 product meta table
			elseif ($i == 11 || $i == 12){
				$field_names ['items'] [$i] ['value'] = 'meta_value,'.WPSC_TABLE_PRODUCTMETA.','.$product_field_name;
				if ($i == 11){
					$field_names ['items'] [$i] ['name'] = 'SKU';
					$field_names ['items'] [$i] ['type']  = 'blob';
				}elseif ($i == 12){
					$field_names ['items'] [$i] ['name'] = 'Stock: Inform When Out Of Stock';
					$field_names ['items'] [$i] ['type'] = 'string';
					//					changed the named from OOS to actual field name
				}
			}

			//			 variations properties table
			elseif ($i == 14 || $i == 15){
				if ($i == 14){
					$field_names ['items'] [$i] ['name'] = 'Variations: Price';
					$field_names ['items'] [$i] ['value'] = 'price,'. WPSC_TABLE_VARIATION_PROPERTIES;
				}elseif ($i == 15){
					$field_names ['items'] [$i] ['name'] = 'Variations: Weight';
					$field_names ['items'] [$i] ['value'] = 'weight,'. WPSC_TABLE_VARIATION_PROPERTIES;
				}
				$field_names ['items'] [$i] ['type']  = 'real';
			}
			$i++;
		}
		
		//appending categories's groups
		foreach((array) $groups as $id => $group_name ) {
			$field_names ['items'][$i]['id']    = $i;
			$field_names ['items'][$i]['name']  = 'Group: '.$group_name ;
			$field_names ['items'][$i]['type']  = 'category';
			$field_names ['items'][$i]['value'] = $id;
			$i ++;
		}		
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
			$orders_details_url   = ADMIN_URL."/index.php?page=wpsc-sales-logs&purchaselog_id=";
			
			//creating the products links
			$str_products_url = htmlspecialchars_decode ((wpsc_edit_the_product_link ( '', '', '', $id = '')));
			$regex_pattern = "/<a class=\'(.*)\' href=\'(.*)\'>(.*)<\/a>/";
			preg_match_all ( $regex_pattern, $str_products_url, $matches );			
			$products_details_url = "{$matches[2][0]}";			
			
			// getting orders fieldnames START
			$query  = "SELECT processed,track_id,notes FROM ".WPSC_TABLE_PURCHASE_LOGS." LIMIT 1,1";
			$result = mysql_query($query);
			
//			@todo work on mysql_num_fields instead of data
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
			
			$ordersfield_names ['items'][$cnt]['type'] = mysql_field_type($result,$cnt);
			if($ordersfield_names ['items'][$cnt]['type'] == 'int' && $ordersfield_names ['items'][$cnt]['name'] == 'Orders Status')
		 		$ordersfield_names ['items'][$cnt]['type'] = 'bigint';
		 	
		 	if($ordersfield_names ['items'][$cnt]['type']  == 'string' && $ordersfield_names ['items'][$cnt]['name'] == 'Track Id')
		 		$ordersfield_names ['items'][$cnt]['type']  = 'blob';		 
		 	$ordersfield_names ['items'][$cnt]['value'] = mysql_field_name($result,$cnt).', '. mysql_field_table($result,$cnt);
		 	$ordersfield_names ['totalCount'] = $cnt;
		 	$cnt++;
           	}
           	
           	if (count($ordersfield_names) >= 1){
           		
           		$query  = "SELECT id,name from ".WPSC_TABLE_CHECKOUT_FORMS." where id between 10 and 16 and id != 14";
           		$res    = mysql_query($query);

           		$cnt = $ordersfield_names['totalCount'] + 1;
           		while($data = mysql_fetch_assoc($res)) {
           			$ordersfield_names ['items'][$cnt]['id']    = $cnt;
           			$ordersfield_names ['items'][$cnt]['name']  = "Shipping".' '.$data['name'];
           			$ordersfield_names ['items'][$cnt]['type']  = 'blob';
           			$ordersfield_names ['items'][$cnt]['value'] = 'value'.','. WPSC_TABLE_SUBMITED_FORM_DATA.','.$data['id'];
           			$cnt++;
           		}
           		$encodedOrdersFields = json_encode ($ordersfield_names);
           	}else
           	$encodedOrdersFields = 0;           	
			
			$orderstatus_id = 0;
			foreach ((array)$order_status as $status_value => $status_name){
				$order_status['items'][$orderstatus_id]['id']    = $orderstatus_id;
				$order_status['items'][$orderstatus_id]['name']  = $status_name;
				$order_status['items'][$orderstatus_id]['value'] = $status_value;
				$order_status['totalCount'] = $orderstatus_id++;				
			}
			$encodedOrderStatus = json_encode($order_status);
			//getting orders fieldnames END			
			
			//getting customers fieldnames START
			$form_data_query = "SELECT id,name 
								  FROM ".WPSC_TABLE_CHECKOUT_FORMS."
								 WHERE id between 2 and 8 
								 OR    id = 17";
			$form_data_result = mysql_query ( $form_data_query );
			while ( $data = mysql_fetch_assoc ( $form_data_result ) ) {				
				$form_data[$data['id']] = $data['name'];
			}			
			$cnt = 0;
			foreach ((array)$form_data as $form_data_key => $form_data_value){				
					$customerFields['items'][$cnt]['id'] = $cnt;
					
					if($form_data_value == 'Country'){
						$customerFields['items'][$cnt]['type']  = 'bigint';
					}else{
						$customerFields['items'][$cnt]['type']  = 'blob';
					}					
					
					$customerFields['items'][$cnt]['name'] = $form_data_value;					
					$customerFields['items'][$cnt]['value'] = 'value'.', '.WPSC_TABLE_SUBMITED_FORM_DATA.', '.$form_data_key;
					$customerFields['totalCount'] = $cnt++;
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
				$countries['items'][$count]['country_id'] = $data['id'];
				$countries['totalCount'] = $count++;
			}}
			$encodedCountries = json_encode($countries);
						
			$query = "SELECT id,country_id, name, code FROM ".WPSC_TABLE_REGION_TAX;
			$result = mysql_query($query);
			$count = 0;
			if (mysql_num_rows($result) >= 1){
				while ($data = mysql_fetch_assoc($result)){
				    if($old_country_id !=$data['country_id'])
				    	$count = 0;
					$regions[$data['country_id']]['items'][] = array('id' =>$count, 'name'=>$data['name'], 'value'=>$data['name'], 'region_id'=>$data['id']);
					$regions['no_regions']['items'][] = array('id' =>0, 'name'=>'', 'value'=>'');
					$old_country_id = $data['country_id'];
					$count++;
				}}
			$encodedRegions = json_encode($regions);
			
			//getting customers fieldnames END
			echo '<script type="text/javascript">
			var productsFields       = ' . $encodedProductsFields . ';
			 var ordersFields        = ' . $encodedOrdersFields . ';
			 var customersFields     = ' . $encodedCustomersFields . ';
			 var countries           = ' . $encodedCountries . ';
			 var regions             = ' . $encodedRegions.';
			 var weightUnits         = ' . $encodedWeightUnits . ';
			 var ordersStatus        = ' . $encodedOrderStatus . ';
			 var newcat              = \'' . $cat_name . '\';
			 var fileExists          = \''.$fileExists.'\';
			 var newCatId            = \'' . $cat_id . '\';
			 var jsonURL             = \''.$jsonURL.'\';
			 var imgURL              = \''.$imgURL.'\';
			 var productsDetailsLink = \''.$products_details_url.'\';	
			 var ordersDetailsLink   = \''.$orders_details_url.'\';
			</script>';
?>
<!-- Smart Manager FB Like Button -->
<div class="wrap">
<br/>
<iframe src="http://www.facebook.com/plugins/like.php?href=http%3A%2F%2Fwww.storeapps.org%2F&amp;layout=standard&amp;show_faces=true&amp;width=450&amp;action=like&amp;colorscheme=light&amp;height=80" scrolling="no" frameborder="0" style="border:none; overflow:hidden; width:450px; height:80px;" allowTransparency="true"></iframe>
</div>