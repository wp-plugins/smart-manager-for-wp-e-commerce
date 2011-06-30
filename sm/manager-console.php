<div id="editor-grid"></div>
<?php
global $wpdb;
$limit = 2;
// to set javascript variable of file exists
$fileExists = (SMPRO === true) ? 1 : 0;

$weight_unit ['items'] = array (array ('id' => 0, 'name' => 'Pounds', 'value' => 'pound' ), array ('id' => 1, 'name' => 'Ounces', 'value' => 'ounce' ), array ('id' => 2, 'name' => 'Grams', 'value' => 'gram' ), array ('id' => 3, 'name' => 'Kilograms', 'value' => 'kilogram' ) );
$weight_unit ['totalCount'] = count ( $weight_unit ['items'] );
$encodedWeightUnits = json_encode ( $weight_unit );

//creating the order links
$blog_info = get_bloginfo ( 'url' );
$orders_details_url = ADMIN_URL . "/index.php?page=wpsc-sales-logs&purchaselog_id=";

//creating the products links
$str_products_url = htmlspecialchars_decode ( (wpsc_edit_the_product_link ( '', '', '', $id = '' )) );
$regex_pattern = "/<a class=\'(.*)\' href=\'(.*)\'>(.*)<\/a>/";
preg_match_all ( $regex_pattern, $str_products_url, $matches );
$products_details_url = "{$matches[2][0]}";

// getting orders fieldnames START
$query = "SELECT processed,track_id,notes FROM " . WPSC_TABLE_PURCHASE_LOGS;
$result = mysql_query ( $query );

//@todo work on mysql_num_fields instead of data
if (mysql_num_rows ( $result ) >= 1) {
	while ( $data = mysql_fetch_assoc ( $result ) )
		$ordersfield_data [] = $data;
	$ordersfield_result = $ordersfield_data [0];
}

$ordersfield_names = array ();
$cnt = 0;
foreach ( ( array ) $ordersfield_result as $ordersfield_name => $ordersfield_value ) {
	$ordersfield_names ['items'] [$cnt] ['id'] = $cnt;
	$ordersfield_names ['items'] [$cnt] ['name'] = ucfirst ( mysql_field_name ( $result, $cnt ) );
	if ($ordersfield_names ['items'] [$cnt] ['name'] == 'Processed')
		$ordersfield_names ['items'] [$cnt] ['name'] = 'Orders Status';
	if ($ordersfield_names ['items'] [$cnt] ['name'] == 'Track_id')
		$ordersfield_names ['items'] [$cnt] ['name'] = 'Track Id';
	
	$ordersfield_names ['items'] [$cnt] ['type'] = mysql_field_type ( $result, $cnt );
	if ($ordersfield_names ['items'] [$cnt] ['type'] == 'int' && $ordersfield_names ['items'] [$cnt] ['name'] == 'Orders Status')
		$ordersfield_names ['items'] [$cnt] ['type'] = 'bigint';
	
	if ($ordersfield_names ['items'] [$cnt] ['type'] == 'string' && $ordersfield_names ['items'] [$cnt] ['name'] == 'Track Id')
		$ordersfield_names ['items'] [$cnt] ['type'] = 'blob';
	$ordersfield_names ['items'] [$cnt] ['value'] = mysql_field_name ( $result, $cnt ) . ', ' . mysql_field_table ( $result, $cnt );
	$cnt ++;
}

if (count ( $ordersfield_names ) >= 1) {
	if (IS_WPSC38) {
		$query = "SELECT id,name,unique_name
		 		FROM " . WPSC_TABLE_CHECKOUT_FORMS . " 
				WHERE unique_name IN ('shippingfirstname', 'shippinglastname', 'shippingaddress', 'shippingcity', 'shippingstate','shippingcountry', 'shippingpostcode')";
	} elseif (IS_WPSC37) {
		$query = "SELECT id,name,unique_name
		 		FROM " . WPSC_TABLE_CHECKOUT_FORMS . " 
				WHERE unique_name IN ('shippingfirstname', 'shippinglastname', 'shippingaddress', 'shippingcity','shippingcountry', 'shippingpostcode')";
	}
	$res = mysql_query ( $query );
	$cnt = count ( $ordersfield_names ['items'] );
	while ( $data = mysql_fetch_assoc ( $res ) ) {
		$ordersfield_names ['items'] [$cnt] ['id'] = $cnt;
		$ordersfield_names ['items'] [$cnt] ['name'] = "Shipping" . ' ' . $data ['name'];
		$ordersfield_names ['items'] [$cnt] ['type'] = 'blob';
		$ordersfield_names ['items'] [$cnt] ['value'] = 'value' . ',' . WPSC_TABLE_SUBMITED_FORM_DATA . ',' . $data ['id'];
		$ordersfield_names ['totalCount'] = $cnt ++;
	}
	$encodedOrdersFields = json_encode ( $ordersfield_names );
} else
	$encodedOrdersFields = 0;

if (IS_WPSC37) {
	global $purchlogs;
	$allstatuses = $purchlogs->the_purch_item_statuses ();
	foreach ( $allstatuses as $status )
		$order_status [$status->id] = $status->name;
	
	$orderstatus_id = 0;
	foreach ( ( array ) $order_status as $status_value => $status_name ) {
		$order_status ['items'] [$orderstatus_id] ['id'] = $orderstatus_id;
		$order_status ['items'] [$orderstatus_id] ['name'] = $status_name;
		$order_status ['items'] [$orderstatus_id] ['value'] = $status_value;
		$order_status ['totalCount'] = $orderstatus_id ++;
	}
} elseif (IS_WPSC38) {
	$order_status = array ('items' => array (0 => array ('id' => 1, 'name' => 'Incomplete Sale', 'value' => '1' ), 1 => array ('id' => 2, 'name' => 'Order Received', 'value' => '2' ), 2 => array ('id' => 3, 'name' => 'Accepted Payment', 'value' => '3' ), 3 => array ('id' => 4, 'name' => 'Job Dispatched', 'value' => '4' ), 4 => array ('id' => 5, 'name' => 'Closed Order', 'value' => '5' ), 5 => array ('id' => 6, 'name' => 'Payment Declined', 'value' => '6' ) ) );
	$order_status ['totalCount'] = count ( $order_status ['items'] );
}
$encodedOrderStatus = json_encode ( $order_status );
//getting orders fieldnames END


//getting customers fieldnames START
$form_data_query = "SELECT id,name,unique_name FROM " . WPSC_TABLE_CHECKOUT_FORMS . " WHERE unique_name in ('billingfirstname', 'billinglastname', 'billingaddress', 'billingcity', 'billingstate', 'billingcountry', 'billingpostcode', 'billingphone', 'billingemail')";
$form_data_result = mysql_query ( $form_data_query );
while ( $data = mysql_fetch_assoc ( $form_data_result ) ) {
	if (IS_WPSC37) {
		if ($data ['unique_name'] != 'billingstate')
			$form_data [$data ['id']] = $data ['name'];
	} elseif (IS_WPSC38)
		$form_data [$data ['id']] = $data ['name'];
}

$cnt = 0;
foreach ( ( array ) $form_data as $form_data_key => $form_data_value ) {
	$customerFields ['items'] [$cnt] ['id'] = $cnt;
	
	if ($form_data_value == 'Country' || strstr ( $form_data_value, 'Country' )) {
		$customerFields ['items'] [$cnt] ['type'] = 'bigint';
	} else {
		$customerFields ['items'] [$cnt] ['type'] = 'blob';
	}
	
	$customerFields ['items'] [$cnt] ['name'] = $form_data_value;
	$customerFields ['items'] [$cnt] ['value'] = 'value' . ', ' . WPSC_TABLE_SUBMITED_FORM_DATA . ', ' . $form_data_key;
	$customerFields ['totalCount'] = $cnt ++;
}
if (count ( $customerFields ) >= 1)
	$encodedCustomersFields = json_encode ( $customerFields );
else
	$encodedCustomersFields = 0;

$query = "SELECT * FROM `" . WPSC_TABLE_CURRENCY_LIST . "` ORDER BY `country` ASC";
$result = mysql_query ( $query );
$count = 0;
if (mysql_num_rows ( $result ) >= 1) {
	while ( $data = mysql_fetch_assoc ( $result ) ) {
		$countries ['items'] [$count] ['id'] = $count;
		$countries ['items'] [$count] ['name'] = $data ['country'];
		$countries ['items'] [$count] ['value'] = $data ['isocode'];
		$countries ['items'] [$count] ['country_id'] = $data ['id'];
		$countries ['totalCount'] = $count ++;
	}
}
$encodedCountries = json_encode ( $countries );

$query = "SELECT id,country_id, name, code FROM " . WPSC_TABLE_REGION_TAX;
$result = mysql_query ( $query );
$count = 0;
if (mysql_num_rows ( $result ) >= 1) {
	while ( $data = mysql_fetch_assoc ( $result ) ) {
		if ($old_country_id != $data ['country_id'])
			$count = 0;
		$regions [$data ['country_id']] ['items'] [] = array ('id' => $count, 'name' => $data ['name'], 'value' => $data ['name'], 'region_id' => $data ['id'] );
		$regions ['no_regions'] ['items'] [] = array ('id' => 0, 'name' => '', 'value' => '' );
		$old_country_id = $data ['country_id'];
		$count ++;
	}
}
$encodedRegions = json_encode ( $regions );

//BOF Products Fields
$products_cols['id']['name']='id';
$products_cols['id']['actionType']='';
$products_cols['id']['colName']='id';
$products_cols['id']['tableName']="{$wpdb->prefix}posts";

$products_cols['name']['name']='Name';
$products_cols['name']['actionType']='modStrActions';
$products_cols['name']['colName']='post_title';
$products_cols['name']['tableName']="{$wpdb->prefix}posts";

$products_cols['price']['name']='Price';
$products_cols['price']['actionType']='modIntPercentActions';
$products_cols['price']['colName']='_wpsc_price';
$products_cols['price']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['price']['colFilter']='meta_key:_wpsc_price';
$products_cols['price']['updateColName']='meta_value';

$products_cols['salePrice']['name']='Sale Price';
$products_cols['salePrice']['actionType']='modIntPercentActions';
$products_cols['salePrice']['colName']='_wpsc_special_price';
$products_cols['salePrice']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['salePrice']['colFilter']='meta_key:_wpsc_special_price';
$products_cols['salePrice']['updateColName']='meta_value';

$products_cols['inventory']['name']='Inventory';
$products_cols['inventory']['actionType']='modIntActions';
$products_cols['inventory']['colName']='_wpsc_stock';
$products_cols['inventory']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['inventory']['colFilter']='meta_key:_wpsc_stock';
$products_cols['inventory']['updateColName']='meta_value';

$products_cols['sku']['name']='SKU';
$products_cols['sku']['actionType']='modStrActions';
$products_cols['sku']['colName']='_wpsc_sku';
$products_cols['sku']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['sku']['colFilter']='meta_key:_wpsc_sku';
$products_cols['sku']['updateColName']='meta_value';

$products_cols['group']['name']='Group';
$products_cols['group']['actionType']='setAdDelActions';
$products_cols['group']['colName']='category';
$products_cols['group']['tableName']="{$wpdb->prefix}term_relationships";
$products_cols['group']['updateColName']='term_taxonomy_id';

$products_cols['weight']['name']='Weight';
$products_cols['weight']['actionType']='modIntPercentActions';
$products_cols['weight']['colName']='weight';
$products_cols['weight']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['weight']['colFilter']='meta_key:_wpsc_product_metadata';

$products_cols['weightUnit']['name']='Unit';
$products_cols['weightUnit']['actionType']='';
$products_cols['weightUnit']['colName']='weight_unit';
$products_cols['weightUnit']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['weightUnit']['colFilter']='meta_key:_wpsc_product_metadata';

$products_cols['publish']['name']='Publish';
$products_cols['publish']['actionType']='YesNoActions';
$products_cols['publish']['colName']='post_status';
$products_cols['publish']['tableName']="{$wpdb->prefix}posts";

$products_cols['disregardShipping']['name']='Disregard Shipping';
$products_cols['disregardShipping']['actionType']='YesNoActions';
$products_cols['disregardShipping']['colName']='no_shipping';
$products_cols['disregardShipping']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['disregardShipping']['colFilter']='meta_key:_wpsc_product_metadata';

$products_cols['desc']['name']='Description';
$products_cols['desc']['actionType']='modStrActions';
$products_cols['desc']['colName']='post_content';
$products_cols['desc']['tableName']="{$wpdb->prefix}posts";

$products_cols['addDesc']['name']='Additional Description';
$products_cols['addDesc']['actionType']='modStrActions';
$products_cols['addDesc']['colName']='post_excerpt';
$products_cols['addDesc']['tableName']="{$wpdb->prefix}posts";

$products_cols['pnp']['name']='Local Shipping Fee';
$products_cols['pnp']['actionType']='modIntPercentActions';
$products_cols['pnp']['colName']='local';
$products_cols['pnp']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['pnp']['colFilter']='meta_key:_wpsc_product_metadata:shipping';

$products_cols['intPnp']['name']='International Shipping Fee';
$products_cols['intPnp']['actionType']='modIntPercentActions';
$products_cols['intPnp']['colName']='international';
$products_cols['intPnp']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['intPnp']['colFilter']='meta_key:_wpsc_product_metadata:shipping';

$products_cols['height']['name']='Height';
$products_cols['height']['actionType']='modIntPercentActions';
$products_cols['height']['colName']='height';
$products_cols['height']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['height']['colFilter']='meta_key:_wpsc_product_metadata';

$products_cols['heightUnit']['id']=16;
$products_cols['heightUnit']['name']='Unit';
$products_cols['heightUnit']['actionType']='';
$products_cols['heightUnit']['colName']='height_unit';
$products_cols['heightUnit']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['heightUnit']['colFilter']='meta_key:_wpsc_product_metadata';

$products_cols['width']['name']='Width';
$products_cols['width']['actionType']='modIntPercentActions';
$products_cols['width']['colName']='width';
$products_cols['width']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['width']['colFilter']='meta_key:_wpsc_product_metadata';

$products_cols['widthUnit']['name']='Unit';
$products_cols['widthUnit']['actionType']='';
$products_cols['widthUnit']['colName']='width_unit';
$products_cols['widthUnit']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['widthUnit']['colFilter']='meta_key:_wpsc_product_metadata';

$products_cols['lengthCol']['name']='Length';
$products_cols['lengthCol']['actionType']='modIntPercentActions';
$products_cols['lengthCol']['colName']='length';
$products_cols['lengthCol']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['lengthCol']['colFilter']='meta_key:_wpsc_product_metadata';

$products_cols['lengthUnit']['name']='Unit';
$products_cols['lengthUnit']['actionType']='';
$products_cols['lengthUnit']['colName']='length_unit';
$products_cols['lengthUnit']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['lengthUnit']['colFilter']='meta_key:_wpsc_product_metadata';

$products_cols['qtyLimited']['name']='Stock: Quantity Limited';
$products_cols['qtyLimited']['actionType']='YesNoActions';
$products_cols['qtyLimited']['colName']='_wpsc_price';
$products_cols['qtyLimited']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['qtyLimited']['colFilter']='meta_key:_wpsc_stock';
$products_cols['qtyLimited']['updateColName']='meta_value'; //@todo qty limited

$products_cols['oos']['name']='Stock: Inform When Out Of Stock';
$products_cols['oos']['actionType']='YesNoActions';
$products_cols['oos']['colName']='unpublish_when_none_left';
$products_cols['oos']['tableName']="{$wpdb->prefix}postmeta";
$products_cols['oos']['colFilter']='meta_key:_wpsc_product_metadata';

// BOF Product category
if (IS_WPSC37) {
	// to fetch Product categories START
	$query = "SELECT pc.id   as category_id,
					cg.name as group_name, 
					pc.name as category_name, 
					group_id
				
          FROM  " . WPSC_TABLE_PRODUCT_CATEGORIES . " AS pc, 
          		" . WPSC_TABLE_CATEGORISATION_GROUPS . " AS cg
          		
          WHERE cg.active = 1 AND 
          		pc.active = 1 AND 
          		cg.id     = pc.group_id ";

} else { // is_wpc38
	$query = "SELECT {$wpdb->prefix}term_taxonomy.term_taxonomy_id as category_id,
		          {$wpdb->prefix}terms.name as category_name,
		          {$wpdb->prefix}term_taxonomy.parent as group_id,
		          IFNULL(parent_terms.name,'Categories') as group_name
		          
				FROM {$wpdb->prefix}term_taxonomy join  {$wpdb->prefix}terms on ({$wpdb->prefix}terms.term_id = {$wpdb->prefix}term_taxonomy.term_id)
				left join {$wpdb->prefix}terms as parent_terms on (parent_terms.term_id = {$wpdb->prefix}term_taxonomy.parent)
				where taxonomy = 'wpsc_product_category'
		        ";
}

$result = mysql_query ( $query );
while ( $data = mysql_fetch_assoc ( $result ) ) {
	$count = ($old_group_id != $data ['group_id']) ? 0 : ++ $count;
	
	$categories ["category-" . $data ['group_id']] [$count] [0] = mysql_real_escape_string ( $data ['category_id'] );
	$categories ["category-" . $data ['group_id']] [$count] [1] = mysql_real_escape_string ( $data ['category_name'] );
	
	$products_cols ["group" . $data ['group_id']] ['name'] = "Group: " . mysql_real_escape_string ( $data ['group_name'] );
	$products_cols ["group" . $data ['group_id']] ['actionType'] = "category_actions";
	$products_cols ["group" . $data ['group_id']] ['colName'] = (IS_WPSC37) ? "category_id" : "term_taxonomy_id";
	$products_cols ["group" . $data ['group_id']] ['tableName'] = (IS_WPSC37) ? WPSC_TABLE_ITEM_CATEGORY_ASSOC : "{$wpdb->prefix}term_relationships";
	$products_cols ["group" . $data ['group_id']] ['colFilter'] = mysql_real_escape_string ( $data ['group_id'] );
	$old_group_id = $data ['group_id']; //string the group_id as old id
}

$categories = json_encode ( $categories );
$products_cols = json_encode($products_cols);
// EOF Product category
// BOF Products Fields

//getting customers fieldnames END
echo "<script type='text/javascript'>
var isWPSC37            = '" . IS_WPSC37 . "';
var isWPSC38            = '" . IS_WPSC38 . "';

var ordersFields        = " . $encodedOrdersFields . ";
var customersFields     = " . $encodedCustomersFields . ";
categories = " . $categories . ";
var countries           = " . $encodedCountries . ";
var regions             = " . $encodedRegions . ";
var weightUnits         = " . $encodedWeightUnits . ";
var ordersStatus        = " . $encodedOrderStatus . ";
var newcat              = '" . $cat_name . "';
var fileExists          = '" . $fileExists . "';
var newCatId            = '" . $cat_id . "';
var jsonURL             = '" . JSON_URL . "';
var imgURL              = '" . IMG_URL . "';
var productsDetailsLink = '" . $products_details_url . "';	
var ordersDetailsLink   = '" . $orders_details_url . "';

//BOF setting the product fields acc. to the WPSC version
var productsViewCols    = new Array(); //data indexes of the columns in products view

var SM = new Object;
    SM.productsCols = ".$products_cols.";

if(isWPSC37 != ''){
	SM.productsCols.id.colName                 = 'id';
	
	SM.productsCols.name.colName               = 'name';
	SM.productsCols.name.tableName             = '" . WPSC_TABLE_PRODUCT_LIST . "';
	
	SM.productsCols.price.colName              = 'price';
	SM.productsCols.price.tableName            = '" . WPSC_TABLE_PRODUCT_LIST . "';
	SM.productsCols.price.updateColName        = '';
	 
	SM.productsCols.salePrice.colName          = 'sale_price';
	SM.productsCols.salePrice.tableName        = '" . WPSC_TABLE_PRODUCT_LIST . "';
	SM.productsCols.salePrice.updateColName    = 'special_price';
	
	SM.productsCols.inventory.colName          = 'quantity'; 
	SM.productsCols.inventory.tableName        = '" . WPSC_TABLE_PRODUCT_LIST . "';
	
	SM.productsCols.sku.colName                = 'sku';
	SM.productsCols.sku.tableName              = '" . WPSC_TABLE_PRODUCTMETA . "';	
	SM.productsCols.sku.updateColName    	   = 'meta_value';

	SM.productsCols.weight.tableName 		    = '" . WPSC_TABLE_PRODUCT_LIST . "';	
	
	SM.productsCols.publish.colName             = 'publish';	
	SM.productsCols.publish.tableName           = '" . WPSC_TABLE_PRODUCT_LIST . "';
	
	SM.productsCols.disregardShipping.tableName  = '" . WPSC_TABLE_PRODUCT_LIST . "';
	        
	SM.productsCols.desc.colName               = 'description';
	SM.productsCols.desc.tableName             = '" . WPSC_TABLE_PRODUCT_LIST . "';
	
	SM.productsCols.addDesc.colName            = 'additional_description';
	SM.productsCols.addDesc.tableName          = '" . WPSC_TABLE_PRODUCT_LIST . "';
	
	SM.productsCols.pnp.colName                = 'pnp';
	SM.productsCols.pnp.tableName              = '" . WPSC_TABLE_PRODUCT_LIST . "';
	
	SM.productsCols.intPnp.colName             = 'international_pnp';
	SM.productsCols.intPnp.tableName           = '" . WPSC_TABLE_PRODUCT_LIST . "';
	
	SM.productsCols.qtyLimited.colName         = 'quantity_limited';
	SM.productsCols.qtyLimited.tableName       = '" . WPSC_TABLE_PRODUCT_LIST . "';	
	
	SM.productsCols.oos.colName       		   = 'unpublish_oos';
	SM.productsCols.oos.tableName       	   = '" . WPSC_TABLE_PRODUCTMETA . "';
	SM.productsCols.oos.updateColName    	   = 'meta_value'; 
}

var i = 0 ;
var j = 0;

var productsFields        = new Array();
productsFields.items      = new Array();
var prodFieldsStoreData   = new Array();
prodFieldsStoreData.items = new Array();
var dontShow 			  = new Array('height', 'width', 'lengthCol');

Ext.iterate(SM.productsCols , function(key,value) { // adding values in the value field
	SM['productsCols'][key]['value'] = key;
	
	if(isWPSC37 != '' && value.actionType != ''){
		if(value.value != 'height'){
			if(value.value != 'width'){
				if(value.value != 'lengthCol'){
						if(value.value != 'group'){
							productsFields.items.push(value);
							productsFields.totalCount = ++j;
					}
				}
			}
		}
	}else if(isWPSC38 != '' && value.actionType != ''){   // dropdown without unwanted columns for
	if(value.value != 'group'){
			productsFields.items.push(value);
			productsFields.totalCount = ++j;
		}
	}
	prodFieldsStoreData.items.push(value);
	prodFieldsStoreData.totalCount = ++i;
},this);

for(var prodcol in SM.productsCols) 
	productsViewCols.push(SM.productsCols[prodcol]['colName']);

</script>";
?>
<!-- Smart Manager FB Like Button -->
<div class="wrap"><br />
<iframe
	src="http://www.facebook.com/plugins/like.php?href=http%3A%2F%2Fwww.storeapps.org%2F&amp;layout=standard&amp;show_faces=true&amp;width=450&amp;action=like&amp;colorscheme=light&amp;height=80"
	scrolling="no" frameborder="0"
	style="border: none; overflow: hidden; width: 450px; height: 80px;"
	allowTransparency="true"></iframe></div>