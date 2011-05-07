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
$str_products_url  = htmlspecialchars_decode ( (wpsc_edit_the_product_link ( '', '', '', $id = '' )) );
$regex_pattern     = "/<a class=\'(.*)\' href=\'(.*)\'>(.*)<\/a>/";
preg_match_all ( $regex_pattern, $str_products_url, $matches );
$products_details_url = "{$matches[2][0]}";

// getting orders fieldnames START
$query  = "SELECT processed,track_id,notes FROM " . WPSC_TABLE_PURCHASE_LOGS;
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
	if (IS_WPSC38 ) {
		$query = "SELECT id,name,unique_name
		 		FROM " . WPSC_TABLE_CHECKOUT_FORMS . " 
				WHERE unique_name IN ('shippingfirstname', 'shippinglastname', 'shippingaddress', 'shippingcity', 'shippingstate','shippingcountry', 'shippingpostcode')";
	}elseif (IS_WPSC37){
		$query = "SELECT id,name,unique_name
		 		FROM " . WPSC_TABLE_CHECKOUT_FORMS . " 
				WHERE unique_name IN ('shippingfirstname', 'shippinglastname', 'shippingaddress', 'shippingcity','shippingcountry', 'shippingpostcode')";
	}
	$res = mysql_query ( $query );	
	$cnt = count($ordersfield_names ['items']);
	while ( $data = mysql_fetch_assoc ( $res ) ) {
		$ordersfield_names ['items'] [$cnt] ['id']    = $cnt;
		$ordersfield_names ['items'] [$cnt] ['name']  = "Shipping" . ' ' . $data ['name'];
		$ordersfield_names ['items'] [$cnt] ['type']  = 'blob';
		$ordersfield_names ['items'] [$cnt] ['value'] = 'value' . ',' . WPSC_TABLE_SUBMITED_FORM_DATA . ',' . $data ['id'];
		$ordersfield_names ['totalCount'] = $cnt ++;
	}	
	$encodedOrdersFields = json_encode ( $ordersfield_names );
}else
	$encodedOrdersFields = 0;
	
	if (IS_WPSC37){
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
	}elseif (IS_WPSC38){		
		$order_status = array('items'=> array(
		0 => array('id' => 1, 'name' => 'Incomplete Sale',  'value' => '1'),
		1 => array('id' => 2, 'name' => 'Order Received',   'value' => '2'),		
		2 => array('id' => 3, 'name' => 'Accepted Payment', 'value' => '3'),
		3 => array('id' => 4, 'name' => 'Job Dispatched',   'value' => '4'),
		4 => array('id' => 5, 'name' => 'Closed Order',   	'value' => '5'),
		5 => array('id' => 6, 'name' => 'Payment Declined', 'value' => '6')
		));		
		$order_status ['totalCount'] = count($order_status['items']);
	}
	$encodedOrderStatus = json_encode ( $order_status );
	//getting orders fieldnames END


//getting customers fieldnames START
$form_data_query  = "SELECT id,name,unique_name FROM " . WPSC_TABLE_CHECKOUT_FORMS . " WHERE unique_name in ('billingfirstname', 'billinglastname', 'billingaddress', 'billingcity', 'billingstate', 'billingcountry', 'billingpostcode', 'billingphone', 'billingemail')";
$form_data_result = mysql_query ( $form_data_query );
while ( $data = mysql_fetch_assoc ( $form_data_result ) ) {
	if (IS_WPSC37 ) {
		if ($data ['unique_name'] != 'billingstate')
		$form_data [$data ['id']] = $data ['name'];
	}elseif (IS_WPSC38)
	$form_data [$data ['id']] = $data ['name'];
}

$cnt = 0;
foreach ( ( array ) $form_data as $form_data_key => $form_data_value ) {
	$customerFields ['items'] [$cnt] ['id'] = $cnt;
	
	if ($form_data_value == 'Country' || strstr($form_data_value,'Country')) {
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

//getting customers fieldnames END
echo "<script type='text/javascript'>
var isWPSC37            = '" . IS_WPSC37 . "';
var isWPSC38            = '" . IS_WPSC38 . "';

var ordersFields        = " . $encodedOrdersFields . ";
var customersFields     = " . $encodedCustomersFields . ";
var countries           = " . $encodedCountries . ";
var regions             = " . $encodedRegions . ";
var weightUnits         = " . $encodedWeightUnits . ";
var ordersStatus        = " . $encodedOrderStatus . ";
var newcat              = '". $cat_name . "';
var fileExists          = '" . $fileExists . "';
var newCatId            = '" . $cat_id . "';
var jsonURL             = '" . JSON_URL. "';
var imgURL              = '" . IMG_URL . "';
var productsDetailsLink = '" . $products_details_url . "';	
var ordersDetailsLink   = '" . $orders_details_url . "';

//BOF setting the product fields acc. to the WPSC version
var productsViewCols    = new Array(); //data indexes of the columns in products view
var SM = {
    productsCols: {
        id: {
            id: 0,
            name: 'id',
            actionType: '',
            colName: 'id',            
            tableName: '".$wpdb->prefix."posts',
        },
        name: {
            id: 1,
            name: 'Name',
            actionType: 'modStrActions',
            colName: 'post_title',
            tableName: '".$wpdb->prefix."posts',
        },
        price: {
            id: 2,
            name: 'Price',
            actionType: 'modIntPercentActions',
            colName: '_wpsc_price',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_price',
            updateColName: 'meta_value'
        },
        salePrice: {
            id: 3,
            name: 'Sale Price',
            actionType: 'modIntPercentActions',
            colName: '_wpsc_special_price',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_special_price',
            updateColName: 'meta_value'
        },
        inventory: {
            id: 4,
            name: 'Inventory',
            actionType: 'modIntActions',
            colName: '_wpsc_stock',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_stock',
            updateColName: 'meta_value'
        },
        sku: {
            id: 5,
            name: 'SKU',
            actionType: 'modStrActions',
            colName: '_wpsc_sku',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_sku',
            updateColName: 'meta_value'
        },
        group: {
            id: 6,
            name: 'Group',
            actionType: 'setAdDelActions',
            colName: 'category',
            tableName: '".$wpdb->prefix."term_relationships',
            updateColName: 'term_taxonomy_id'
        },
        weight: {
            id: 7,
            name: 'Weight',
            actionType: 'modIntPercentActions',
            colName: 'weight',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata'            
        },
        weightUnit: {
            id: 8,
            name: 'Unit',
            actionType: '',
            colName: 'weight_unit',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata'
        },
        publish: {
            id: 9,
            name: 'Publish',
            actionType: 'YesNoActions',
            colName: 'post_status',
            tableName: '".$wpdb->prefix."posts'
        },
        disregardShipping: {
            id: 10,
            name: 'Disregard Shipping',
            actionType: 'YesNoActions',
            colName: 'no_shipping',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata'
        },
        desc: {
            id: 11,
            name: 'Description',
            actionType: 'modStrActions',
            colName: 'post_content',
            tableName: '".$wpdb->prefix."posts',
        },
        addDesc: {
            id: 12,
            name: 'Additional Description',
            actionType: 'modStrActions',
            colName: 'post_excerpt',
            tableName: '".$wpdb->prefix."posts',
        },
        pnp: {
            id: 13,
            name: 'Local Shipping Fee',
            actionType: 'modIntPercentActions',
            colName: 'local',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata:shipping'
        },
        intPnp: {
            id: 14,
            name: 'International Shipping Fee',
            actionType: 'modIntPercentActions',
            colName: 'international',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata:shipping'
        },
        height: {
            id: 15,
            name: 'Height',
            actionType: 'modIntPercentActions',
            colName: 'height',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata'            
        },
        heightUnit: {
            id: 16,
            name: 'Unit',
            actionType: '',
            colName: 'height_unit',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata'
        },
        width: {
            id: 17,
            name: 'Width',
            actionType: 'modIntPercentActions',
            colName: 'width',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata'            
        },
        widthUnit: {
            id: 18,
            name: 'Unit',
            actionType: '',
            colName: 'width_unit',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata'
        },
        lengthCol: {
            id: 19,
            name: 'Length',
            actionType: 'modIntPercentActions',
            colName: 'length',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata'            
        },
        lengthUnit: {
            id: 20,
            name: 'Unit',
            actionType: '',
            colName: 'length_unit',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata'
        },
        qtyLimited: {
            id: 21,
            name: 'Stock: Quantity Limited',
            actionType: 'YesNoActions',
            colName: 'quantity_limited',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata'
        },
        oos: {
            id: 22,
            name: 'Stock: Inform When Out Of Stock',
            actionType: 'YesNoActions',
            colName: 'unpublish_when_none_left',
            tableName: '".$wpdb->prefix."postmeta',
            colFilter: 'meta_key:_wpsc_product_metadata'
        }
    }
};

if(isWPSC37 != ''){
	SM.productsCols.id.colName                 = 'id';
	
	SM.productsCols.name.colName               = 'name';
	SM.productsCols.name.tableName             = '".WPSC_TABLE_PRODUCT_LIST."';
	
	SM.productsCols.price.colName              = 'price';
	SM.productsCols.price.tableName            = '".WPSC_TABLE_PRODUCT_LIST."';
	SM.productsCols.price.updateColName        = '';
	 
	SM.productsCols.salePrice.colName          = 'sale_price';
	SM.productsCols.salePrice.tableName        = '".WPSC_TABLE_PRODUCT_LIST."';
	SM.productsCols.salePrice.updateColName    = 'special_price';
	
	SM.productsCols.inventory.colName          = 'quantity'; 
	SM.productsCols.inventory.tableName        = '".WPSC_TABLE_PRODUCT_LIST."';
	
	SM.productsCols.sku.colName                = 'sku';
	SM.productsCols.sku.tableName              = '".WPSC_TABLE_PRODUCTMETA."';	
	SM.productsCols.sku.updateColName    	   = 'meta_value';

	SM.productsCols.weight.tableName 		    = '".WPSC_TABLE_PRODUCT_LIST."';	
	
	SM.productsCols.publish.colName             = 'publish';	
	SM.productsCols.publish.tableName           = '".WPSC_TABLE_PRODUCT_LIST."';
	
	SM.productsCols.disregardShipping.tableName  = '".WPSC_TABLE_PRODUCT_LIST."';
	        
	SM.productsCols.desc.colName               = 'description';
	SM.productsCols.desc.tableName             = '".WPSC_TABLE_PRODUCT_LIST."';
	
	SM.productsCols.addDesc.colName            = 'additional_description';
	SM.productsCols.addDesc.tableName          = '".WPSC_TABLE_PRODUCT_LIST."';
	
	SM.productsCols.pnp.colName                = 'pnp';
	SM.productsCols.pnp.tableName              = '".WPSC_TABLE_PRODUCT_LIST."';
	
	SM.productsCols.intPnp.colName             = 'international_pnp';
	SM.productsCols.intPnp.tableName           = '".WPSC_TABLE_PRODUCT_LIST."';
	
	SM.productsCols.qtyLimited.colName         = 'quantity_limited';
	SM.productsCols.qtyLimited.tableName       = '".WPSC_TABLE_PRODUCT_LIST."';	
	
	SM.productsCols.oos.colName       		   = 'unpublish_oos';
	SM.productsCols.oos.tableName       	   = '".WPSC_TABLE_PRODUCTMETA."';
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
var cntProd = productsFields.totalCount;
var cntTotProd  = prodFieldsStoreData.totalCount;
</script>";

if (IS_WPSC37){
	// to fetch Product categories START
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
		$count = ($old_group_id != $data ['group_id']) ? 0  : ++$count ;

		$categories ["category-".$data ['group_id']][$count][0] = mysql_real_escape_string($data ['category_id']);
		$categories ["category-".$data ['group_id']][$count][1] = mysql_real_escape_string($data ['category_name']);

		$groups["group".$data ['group_id']]['name']             = "Group: ".mysql_real_escape_string($data ['group_name']);
		$groups["group".$data ['group_id']]['actionType']       = "category_actions";
		$groups["group".$data ['group_id']]['colName']          = "category_id";
		$groups["group".$data ['group_id']]['tableName']        = WPSC_TABLE_ITEM_CATEGORY_ASSOC;
		$groups["group".$data ['group_id']]['colFilter']        = mysql_real_escape_string($data ['group_id']);
		$old_group_id = $data ['group_id']; //string the group_id as old id
	}
	
	$categories = json_encode($categories);
	echo "<script type='text/javascript'>
	categories = ".$categories."; // creating categories
	</script>";	

	if (is_array($groups)){
		foreach($groups as $group_key=>$categories) {
			echo "<script type='text/javascript'>
		var obj1 = new Object;
		obj1.id         = cntProd+1;
		obj1.name       =  '".$categories['name']."';
		obj1.actionType =  '".$categories['actionType']."';
		obj1.colName    = '".$categories['colName']."';
		obj1.tableName  = '".$categories['tableName']."';
		obj1.value      = '".$group_key."';
		obj1.colFilter  = '".$categories['colFilter']."';
		
		productsFields.items.push(obj1);
		 
		var obj2 = new Object;
		obj2.id         = cntTotProd;
		obj2.name       =  '".$categories['name']."';
		obj2.actionType =  '".$categories['actionType']."';
		obj2.colName    = '".$categories['colName']."';
		obj2.tableName  = '".$categories['tableName']."';
		obj2.value      = '".$group_key."';
		obj2.colFilter  = '".$categories['colFilter']."';
		
		SM['productsCols']['".$group_key."'] = new Object;
		SM['productsCols']['".$group_key."'] = obj2;
		
		cntTotProd++;
		cntProd++;
		</script>";
		}
	}
}
?>
<!-- Smart Manager FB Like Button -->
<div class="wrap"><br />
<iframe
	src="http://www.facebook.com/plugins/like.php?href=http%3A%2F%2Fwww.storeapps.org%2F&amp;layout=standard&amp;show_faces=true&amp;width=450&amp;action=like&amp;colorscheme=light&amp;height=80"
	scrolling="no" frameborder="0"
	style="border: none; overflow: hidden; width: 450px; height: 80px;"
	allowTransparency="true"></iframe></div>