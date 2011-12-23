<?php 
include_once ('../../../../wp-load.php');

$mem_limit = ini_get('memory_limit');
if(intval(substr($mem_limit,0,strlen($mem_limit)-1)) < 64 ){
	ini_set('memory_limit','128M'); 
}

$result = array ();
$encoded = array ();

$offset = (isset ( $_POST ['start'] )) ? $_POST ['start'] : 0;
$limit = (isset ( $_POST ['limit'] )) ? $_POST ['limit'] : 100;

// For pro version check if the required file exists
if (file_exists ( '../pro/woo.php' )) {
	define ( 'SMPRO', true );
	include_once ('../pro/woo.php');
} else {
	define ( 'SMPRO', false );
}

// getting the active module
$active_module = $_POST ['active_module'];

// Searching a product in the grid
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getData') {
	global $wpdb;

	$view_columns = json_decode ( stripslashes ( $_POST ['viewCols'] ) );
	if ($active_module == 'Products') { // <-products
	
		$post_status = "('publish', 'draft')";
	
		// if max-join-size issue occurs
		$query = "SET SQL_BIG_SELECTS=1;";
		$wpdb->query ( $query );

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
							   OR prod_othermeta_value LIKE '%$search_on%'
							   OR category LIKE '%$search_on%'
					           ";
		}

		$from_where = "FROM {$wpdb->prefix}posts as products
						LEFT JOIN {$wpdb->prefix}postmeta as prod_othermeta ON (prod_othermeta.post_id = products.id and
						prod_othermeta.meta_key IN ('regular_price','sale_price','sale_price_dates_from','sale_price_dates_to','sku','stock','weight','height','length','width') )
						
						LEFT JOIN {$wpdb->prefix}postmeta as prod_meta ON (prod_meta.post_id = products.id and
						prod_meta.meta_key = 'product_attributes')
						
						LEFT JOIN 
						(SELECT GROUP_CONCAT(wt.name) as category,wtr.object_id
						FROM  {$wpdb->prefix}term_relationships AS wtr  	 
						JOIN {$wpdb->prefix}term_taxonomy AS wtt ON (wtr.term_taxonomy_id = wtt.term_taxonomy_id and taxonomy = 'product_cat')
												
						JOIN {$wpdb->prefix}terms AS wt ON (wtt.term_id = wt.term_id)
						group by wtr.object_id) as prod_categories on (products.id = prod_categories.object_id)
						
						WHERE products.post_status IN  $post_status
						AND products.post_type    = 'product'";

		$group_by = " GROUP BY products.id ";
		
		$order_by = " ORDER BY products.id desc";

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
			
			for ($i = 0; $i < $num_rows; $i++){
				$prod_meta_values = explode ( '###', $records[$i]->prod_othermeta_value );
				$prod_meta_key    = explode ( '###', $records[$i]->prod_othermeta_key);
				$prod_meta_key_values = array_combine ( $prod_meta_key, $prod_meta_values );
				
				if(isset($prod_meta_key_values['sale_price_dates_from']) && !empty($prod_meta_key_values['sale_price_dates_from']))
					$prod_meta_key_values['sale_price_dates_from'] = date('Y-m-d',(int)$prod_meta_key_values['sale_price_dates_from']);
				if(isset($prod_meta_key_values['sale_price_dates_to']) && !empty($prod_meta_key_values['sale_price_dates_to']))
					$prod_meta_key_values['sale_price_dates_to'] = date('Y-m-d',(int)$prod_meta_key_values['sale_price_dates_to']);
				
				if (is_serialized($records[$i]->prod_meta)){
					$unsez_data[$i] = unserialize($records[$i]->prod_meta);
					$records[$i]    = array_merge((array)$records[$i],$unsez_data[$i]);
				}
				$records[$i] = array_merge((array)$records[$i],$prod_meta_key_values);
			}
			
			unset ( $records[$i]->prod_othermeta_value );
			unset ( $records[$i]->prod_meta );
			unset ( $records[$i]->prod_othermeta_key );			
		}
	}
	
	$encoded ['items'] = $records;
	$encoded ['totalCount'] = $num_records;
	unset($records);
	echo json_encode ( $encoded );
	unset($encoded);
}


//update products for lite version.
function update_products_woo($_POST) {
	global $result, $wpdb;
	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$updateCnt = 1;
	foreach ( $edited_object as $obj ) {
		
		$update_name = $wpdb->query ( "UPDATE $wpdb->posts SET `post_title`= '$obj->post_title' WHERE ID = $obj->id" );
		$update_price = $wpdb->query ( "UPDATE $wpdb->postmeta SET `meta_value`= $obj->regular_price WHERE meta_key = 'regular_price' AND post_id = $obj->id" );
		$result ['updateCnt'] = $updateCnt ++;
	}
	
	if (($update_name >= 1 || $update_price >= 1) && $result ['updateCnt'] >= 1) {
		$result ['result'] = true;
		$result ['updated'] = 1;
	}
	return $result;
}
// For insert updating product in woo.
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'saveData') {
		if (SMPRO == true)
			$result = woo_insert_update_data($_POST);
		else
			$result = update_products_woo ( $_POST );
		
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
				if ($result ['insertCnt'] == 1)
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> New Record Inserted Successfully";
				else
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> New Records Inserted Successfully";
			}
			
		}
	echo json_encode ( $encoded );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'delData') {
	$delCnt = 0;
	
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
	echo json_encode ( $encoded );
}


?>