<?php
global $purchlogs;
$allstatuses = $purchlogs->the_purch_item_statuses();
?>
<select id="order_status" name="order_status" style="display: none;">
<?php 

$cur_status = $cnt;
while($cur_status < count($allstatuses)) :  ?>
<option name="<?php $allstatuses[$cur_status]->id; ?>" value="<?php echo $allstatuses[$cur_status]->name; ?>"><?php echo $allstatuses[$cur_status]->name; ?> </option>
<?php
$cur_status++;
endwhile;
?>
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
$productListTbl          = WPSC_TABLE_PRODUCT_LIST;
$productMetaTbl          = WPSC_TABLE_PRODUCTMETA;
$itemCategoryTbl         = WPSC_TABLE_ITEM_CATEGORY_ASSOC;
$productCategoriesTbl    = WPSC_TABLE_PRODUCT_CATEGORIES;
$categorisationTbl       = WPSC_TABLE_CATEGORISATION_GROUPS;
$variationPropertiesTbl  = WPSC_TABLE_VARIATION_PROPERTIES;
$purchaseLogsTbl         = WPSC_TABLE_PURCHASE_LOGS;
$limit = 2;

$jsonURL    = plugins_url('/json.php', __FILE__);
$pluginURL  = dirname(dirname($jsonURL));
$imgURL     = $pluginURL.'/images/';
$smFilePath = dirname(dirname(__FILE__)).'/pro/sm.js';

// for full version check if the required file exists
if (SMPRO == true)
$fileExists = 1;
else
$fileExists = 0;

// to fetch Product categories START
(isset ( $_POST ['start'] )) ? $offset = $_POST ['start'] : $offset = 0;
$query ="SELECT pc.id   as category_id, 
				cg.name as group_name, 
				pc.name as category_name, 
				group_id
				
          FROM  $productCategoriesTbl AS pc, 
          		$categorisationTbl AS cg
          		
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
			 $query = "SELECT $productListTbl.name,
							  $productListTbl.description, 
							  $productListTbl.additional_description, 
							  $productListTbl.price, 
							  $productListTbl.special_price,							  
							  $productListTbl.quantity,
							  $productListTbl.weight,							  
							  $productListTbl.publish,
							  $productMetaTbl.meta_value,
							  $productMetaTbl.meta_key,
							  $productListTbl.quantity_limited,
							  $variationPropertiesTbl.price as variation_price,
							  $variationPropertiesTbl.weight as variation_weight
							  
					     FROM $productMetaTbl,$productListTbl 
					     LEFT JOIN $variationPropertiesTbl 
					     		ON $variationPropertiesTbl.product_id = $productListTbl.id
					          
					    WHERE $productMetaTbl.meta_key in ('unpublish_oos','sku') AND
					    	  $productListTbl.id = $productMetaTbl.product_id
					 ORDER BY $productListTbl.id LIMIT 0,$limit";
			$result = mysql_query ( $query );
					
			while ($data = mysql_fetch_assoc($result)){
           	$fields_data[] = $data;
       	}
       
       //combine the values of meta_key from two diff arrays in one.
		$final = array();
		if ((array)($fields_data)){
			$final = array_merge($fields_data[0],$fields_data[1]);
			$final['meta_value'] = array($fields_data[0]['meta_key'],$fields_data[1]['meta_key']);
		}
		unset($final['meta_key']);
			
		$field_names = array ();
		$i = 0;
		if ((array)($final)){
			foreach ($final as $field_name => $field_value){
				$field_names ['items'] [$i] ['id'] = $i;
				$field_names ['items'] [$i] ['type']  = mysql_field_type ( $result, $i );
				$field_names ['items'] [$i] ['value'] = mysql_field_name ( $result, $i ).','. mysql_field_table ( $result, $i );
				if ($field_name == 'special_price')
				$field_names ['items'] [$i] ['name'] = 'Sale Price';
				elseif ($field_name == 'quantity')
				$field_names ['items'] [$i] ['name'] = 'Inventory';
				elseif ($field_name == 'quantity_limited')
				$field_names ['items'] [$i] ['name'] = 'Stock: Quantity Limited';
				elseif ($field_name == 'additional_description')
				$field_names ['items'] [$i] ['name'] = 'Add. Description';
				elseif ($field_name == 'variation_price'){
					$field_names ['items'] [$i] ['name'] = 'Variations: Price';
					$field_names ['items'] [$i] ['value'] = 'price,'.mysql_field_table ( $result, $i );
				}elseif ($field_name == 'variation_weight'){
					if (mysql_field_type ( $result, $i ) == 'real')
					$field_names ['items'] [$i] ['type']  = mysql_field_type ( $result, $i );
					else
					$field_names ['items'] [$i] ['type']  = 'real'; //to use the action array of real
					$field_names ['items'] [$i] ['name']  = 'Variations: Weight';
					$field_names ['items'] [$i] ['value'] = 'weight,'.mysql_field_table ( $result, $i );
				}else
				$field_names ['items'] [$i] ['name'] = ucfirst($field_name);

				if ($field_name == 'meta_value'){
					$field_values = $field_value;
					$field_values_len = count($field_values);
					for ($len = 0; $len < $field_values_len ; $len++){
						$i = $i+$len;
						$field_names ['items'] [$i] ['id'] = $i;
						if ($field_values[$len] == 'unpublish_oos'){
							$field_names ['items'] [$i] ['name'] = 'Stock: Inform When Out Of Stock';
							$field_names ['items'] [$i] ['type'] = 'string'; //to use the action array of string
							$field_names ['items'] [$i] ['value'] = mysql_field_name ( $result, $i-$len ) . ', ' . mysql_field_table ( $result, $i-$len ).','.'OOS';
						}else{
							$field_names ['items'] [$i] ['name'] = strtoupper($field_values[$len]);
							$field_names ['items'] [$i] ['type'] = mysql_field_type ( $result, $i-$len );
	$field_names ['items'] [$i] ['value'] = mysql_field_name ( $result, $i-$len ) . ', ' . mysql_field_table ( $result, $i-$len );
						}
					}}
					$i++;
			}}
			$field_names ['totalCount'] = count($field_names ['items']);

			//appending categories's groups
			foreach((array) $groups as $id => $group_name ) {
				$field_names ['items'][$i]['id']    = $i;
				$field_names ['items'][$i]['name']  = 'Group: '.$group_name ;
				$field_names ['items'][$i]['type']  = 'category';
				$field_names ['items'][$i]['value'] = $id;
				$i ++;
			}
			if ($field_names ['totalCount'] >= 1)
			$encodedProductsFields = json_encode ( $field_names ); 
			else 
			$encodedProductsFields = 0;
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
			$query  = "SELECT processed,track_id,notes FROM $purchaseLogsTbl";
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
			// getting orders fieldnames END
			
			echo '<script type="text/javascript">
			 var productsFields = ' . $encodedProductsFields . ';
			 var ordersFields = ' . $encodedOrdersFields . ';
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
		