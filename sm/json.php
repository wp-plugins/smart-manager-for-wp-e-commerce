<?php
include_once('../../../../wp-load.php');
include_once('../../../../wp-includes/wp-db.php');

$prodID = $_GET['product_id'];
if(isset($prodID))
{
   $link = htmlspecialchars_decode((wpsc_edit_the_product_link('','','',$id = $prodID)));
   if($link == '' || empty($link)){
       wp_die(__('You do not have sufficient permissions to access this page.'));
   }else{
       $regex_pattern = "/<a class=\'(.*)\' href=\'(.*)\'>(.*)<\/a>/";
       preg_match_all($regex_pattern,$link,$matches);
       wp_redirect($matches[2][0]);
   }
}

$limit = 10;
$del = 3;

$productListTbl         = WPSC_TABLE_PRODUCT_LIST;
$productMetaTbl         = WPSC_TABLE_PRODUCTMETA;
$itemCategoryTbl        = WPSC_TABLE_ITEM_CATEGORY_ASSOC;
$productCategoriesTbl   = WPSC_TABLE_PRODUCT_CATEGORIES;
$CategorisationTbl      = WPSC_TABLE_CATEGORISATION_GROUPS;
$variationPropertiesTbl = WPSC_TABLE_VARIATION_PROPERTIES;
$variationCombination   = WPSC_TABLE_VARIATION_COMBINATIONS;
$variationValuesAssoc   = WPSC_TABLE_VARIATION_VALUES_ASSOC;

$result  = array();
$encoded = array();

if (isset ( $_POST ['start'] ))
$offset = $_POST ['start'];
else
$offset = 0;

if (isset ( $_POST ['limit'] ))
$limit = $_POST ['limit'];

// Searching a product in the grid
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getData')
{
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

			$from = " FROM $productListTbl AS pl
            		       LEFT OUTER JOIN ($itemCategoryTbl AS ic  
                           LEFT OUTER JOIN $productCategoriesTbl AS pc 
                     	   ON (ic.category_id = pc.id) 
                     	   AND pc.active = 1)ON ( pl.id = ic.product_id )
                     	   LEFT OUTER JOIN $productMetaTbl AS pm ON ( pl.id = pm.product_id AND meta_key = 'sku')";

	$where        = " WHERE pl.active = 1 ";
	$group_by     = " GROUP BY pl.id ";
	$limit_query  = " LIMIT " . $offset . "," . $limit . "";

	if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != ''){
		$searchOn = $_POST ['searchText'];
		$where .= " AND ( concat(' ',pl.name) LIKE '% $searchOn%' OR 
										price LIKE '%$searchOn%'  OR
                           	       	 quantity LIKE '%$searchOn%'  OR
                              		   weight LIKE '%$searchOn%'  OR
                              			CASE  weight_unit
                               			WHEN 'pound' THEN 'Pounds'
		                                WHEN 'ounce' THEN 'Ounces'
		                                WHEN 'gram' THEN 'Grams'
		                                WHEN 'kilogram' THEN 'Kilograms'
		                                ELSE 'Pounds'
		                                END LIKE '%$searchOn%'
                               		OR if(pl.publish = 1,'Published','Draft') LIKE '%$searchOn%'
                              	    OR special_price LIKE '%$searchOn%'                                                                                            
                              	    OR concat(' ',pm.meta_value) LIKE '% $searchOn%'
                              	    OR concat(' ',pc.name) LIKE '% $searchOn%' )";
	}

	$recordcount_query  = "SELECT COUNT( DISTINCT pl.id ) as count" . $from . "" . $where;
	$query 			    = $select_query . "" . $from . "" . $where . "" . $group_by . "" . $limit_query;
	$result 		    = mysql_query ( $query );
	$num_rows 		    = mysql_num_rows ( $result );
	$recordcount_result = mysql_query ( $recordcount_query );
	$no_of_records      = mysql_fetch_assoc ( $recordcount_result );
	$num_records        = $no_of_records ['count'];
	if ($num_rows == 0) {
		$encoded ['totalCount'] = '';
		$encoded ['items'] = '';
		$encoded ['msg'] = 'No Records Found';
	} else {
		while ( $data = mysql_fetch_assoc ( $result ) )
			$records [] = $data;
    }
    $encoded ['items'] = $records;
	$encoded ['totalCount'] = $num_records;
}

// Delete product.
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'delData') {
	$data = json_decode ( stripslashes ( $_POST ['data'] ) );
	$data = implode ( ',', $data );
	$delCnt = 0;
	$query = "UPDATE $productListTbl SET active = 0 WHERE id in ($data)";
	$result = mysql_query ( $query );
	$delCnt = mysql_affected_rows ();
	if ($result) {
		if ($delCnt == 1){
		$encoded ['msg']   = $delCnt . " Record Deleted Successfully";
		$encoded['delCnt'] = $delCnt;
		}else{
		$encoded ['msg']   = $delCnt . " Records Deleted Successfully";
		$encoded['delCnt'] = $delCnt;
	}}
}

// for pro version check if the required file exists
if (file_exists('../pro/sm.php'))
include_once('../pro/sm.php');

echo json_encode ( $encoded );
?>