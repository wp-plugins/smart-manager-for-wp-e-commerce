<?php
if ( ! defined('ABSPATH') ) {
    include_once ('../../../../wp-load.php');
}
include_once (ABSPATH . 'wp-includes/wp-db.php');
include_once (ABSPATH . 'wp-includes/functions.php');
//include_once (ABSPATH . 'wp-content/plugins/wp-e-commerce/wpsc-admin/admin.php');
include_once (ABSPATH . 'wp-content/plugins/wp-e-commerce/wpsc-core/wpsc-functions.php');
include_once (ABSPATH . 'wp-content/plugins/wp-e-commerce/wpsc-includes/purchaselogs.class.php');
load_textdomain( 'smart-manager', ABSPATH . 'wp-content/plugins/smart-manager-for-wp-e-commerce/languages/smart-manager-' . WPLANG . '.mo' );

//checking the memory limit allocated
$mem_limit = ini_get('memory_limit');
if(intval(substr($mem_limit,0,strlen($mem_limit)-1)) < 64 ){
	ini_set('memory_limit','128M'); 
}

$result = array ();
$encoded = array ();

$offset = (isset ( $_POST ['start'] )) ? $_POST ['start'] : 0;
$limit = (isset ( $_POST ['limit'] )) ? $_POST ['limit'] : 100;
	
// For pro version check if the required file exists
if (file_exists ( WP_CONTENT_DIR . '/plugins/smart-manager-for-wp-e-commerce/pro/sm38.php' )) {
	define ( 'SMPRO', true );
	include_once ( WP_CONTENT_DIR . '/plugins/smart-manager-for-wp-e-commerce/pro/sm38.php' );
} else {
	define ( 'SMPRO', false );
}

function get_regions_ids(){ //getting the list of regions
	global $wpdb;
	$query   	 = "SELECT id,name FROM " . WPSC_TABLE_REGION_TAX;
	$reg_results = $wpdb->get_results ( $query,'ARRAY_A');

	foreach($reg_results as $reg_result){
		$regions_ids[$reg_result['id']] = $reg_result['name'];
	}
	return $regions_ids;
}
		
// getting the active module
$active_module = $_POST ['active_module'];


function variable_price_sync () {
    global $wpdb;
    $parent_ids = array();

    // To collect unique parent from all variation ids
    $query="SELECT id from {$wpdb->prefix}posts WHERE post_type='wpsc-product' AND post_parent =0";
    $parent_ids = $wpdb->get_results ( $query,'ARRAY_A' );

    // To be called only for parent product for price sync
    for( $i=0; $i<sizeof($parent_ids); $i++ ){
        $price=wpsc_product_variation_price_available($parent_ids[$i]['id']);
    }
    return $i;
}

function variable_product_price_sync($id) {
    global $wpdb;

    $parent=get_post_custom($id );

    $query="SELECT id from {$wpdb->prefix}posts WHERE post_type='wpsc-product' AND post_parent =$id";
    $children = $wpdb->get_results ( $query,'ARRAY_A' );

    if ($children) {

        $parent['_wpsc_price'] = $parent['_wpsc_special_price'] = '';

        for ( $i=0;$i<sizeof($children);$i++ ) {

            $child_price 		= get_post_meta($children[$i]['id'], '_wpsc_price', true);
            $child_sale_price 	= get_post_meta($children[$i]['id'], '_wpsc_special_price', true);

            if($parent['_wpsc_price']=='' || $parent['_wpsc_special_price'] == ''){
                $parent['_wpsc_price']=$child_price;
                $parent['_wpsc_special_price']=$child_sale_price;
            }
            else{
                if($parent['_wpsc_price']>$child_price)
                    $parent['_wpsc_price']=$child_price;

                if($parent['_wpsc_special_price']>$child_sale_price)
                    $parent['_wpsc_special_price']=$child_sale_price;

            }

        }

        if($parent['_wpsc_price']>0 && $parent['_wpsc_special_price']>0){
            if($parent['_wpsc_price']<$parent['_wpsc_special_price'])
                $parent['_wpsc_special_price']=$parent['_wpsc_price'];
            else
                $parent['_wpsc_price']=$parent['_wpsc_special_price'];
        }
    }

    update_post_meta( $id, '_wpsc_price', $parent['_wpsc_price'] );
    update_post_meta( $id, '_wpsc_special_price', $parent['_wpsc_special_price'] );

}

// function to return term_taxonomy_ids of a term name
function get_term_taxonomy_ids( $term_name ) {
    global $wpdb;
    
    $query = "SELECT DISTINCT term_taxonomy.term_taxonomy_id AS term_taxonomy_id
                    FROM {$wpdb->prefix}term_taxonomy AS term_taxonomy
                    LEFT JOIN {$wpdb->prefix}terms AS terms ON ( term_taxonomy.term_id = terms.term_id )
                    WHERE term_taxonomy.taxonomy IN ( 'wpsc_product_category', 'wpsc-variation' )
                            AND terms.name IN ( $term_name )
                    ORDER BY term_taxonomy.term_taxonomy_id";
    $term_taxonomy_ids = $wpdb->get_col( $query );

    return $term_taxonomy_ids;
}

function get_log_ids( $result ) {
    return $result['last_order_id'];
}

//
function get_all_matched_purchase_log_ids( $search_on = '' ) {
    global $wpdb;

    $purchase_log_ids = array();
    
    $search_condn_checkout_form_query = "SELECT DISTINCT log_id
                                                FROM " . WPSC_TABLE_SUBMITED_FORM_DATA . "
                                                WHERE value LIKE '%$search_on%'
                                        ";
    
    $checkout_form_purchase_log_ids = $wpdb->get_col( $search_condn_checkout_form_query );
    
    if ( !empty( $checkout_form_purchase_log_ids ) ) {
        return " OR wtpl.id IN ( " . implode( ',', $checkout_form_purchase_log_ids ) . " )";
    }
    return '';
}


// Searching a product in the grid
function get_data_wpsc_38 ( $_POST, $offset, $limit, $is_export = false ) {
	global $wpdb,$post_status,$parent_sort_id,$order_by;
	
        $regions_ids = get_regions_ids();		
	$country_results = $wpdb->get_results( "SELECT isocode, country FROM " . WPSC_TABLE_CURRENCY_LIST, 'ARRAY_A' );
        $country_data = array();
        foreach ( $country_results as $country_result ) {
            $country_data[$country_result['isocode']] = $country_result['country'];
        }

        
	// getting the active module
	$active_module = $_POST ['active_module'];

	if ( SMPRO == true ) variation_query_params ();
	
	if ( $is_export === true ) {
		$limit_string = "";
		$image_size = "full";
	} else {
		$limit_string = "LIMIT $offset,$limit";
		$image_size = "thumbnail";
	}

    $wpdb->query ( "SET SESSION group_concat_max_len=999999" );// To increase the max length of the Group Concat Functionality

    $view_columns = json_decode ( stripslashes ( $_POST ['viewCols'] ) );
    
	if ($active_module == 'Products') { // <-products

//        variable_price_sync ();

		$wpsc_default_image = WP_PLUGIN_URL . '/wp-e-commerce/wpsc-theme/wpsc-images/noimage.png';
		if (isset ( $_POST ['incVariation'] ) && $_POST ['incVariation'] == 'true' && SMPRO == true) {
			$show_variation = true;
		} else { // query params for non-variation products
			$show_variation = false;
			$post_status = "('publish', 'draft')";
			$parent_sort_id = '';
			$order_by = " ORDER BY products.id desc";
		}

		// if max-join-size issue occurs
		$query = "SET SQL_BIG_SELECTS=1;";
		$wpdb->query ( $query );
		
		$select = "SELECT SQL_CALC_FOUND_ROWS products.id,
					products.post_title,
					products.post_content,
					products.post_excerpt,
					products.post_status,
					products.post_parent,
                                        CAST(GROUP_CONCAT(DISTINCT term_relationships.term_taxonomy_id order by term_relationships.term_taxonomy_id SEPARATOR ',') AS CHAR(1000)) AS term_taxonomy_id,
					GROUP_CONCAT(prod_othermeta.meta_key order by prod_othermeta.meta_id SEPARATOR '###') AS prod_othermeta_key,
					GROUP_CONCAT(prod_othermeta.meta_value order by prod_othermeta.meta_id SEPARATOR '###') AS prod_othermeta_value
					$parent_sort_id";

        //Used as an alternative to the SQL_CALC_FOUND_ROWS function of MYSQL Database
        $select_count = "SELECT COUNT(*) as count"; // To get the count of the number of rows generated from the above select query

        if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_on = trim ( $_POST ['searchText'] );

			$count_all_double_quote = substr_count( $search_on, '"' );
			if ( $count_all_double_quote > 0 ) {
				$search_ons = array_filter( array_map( 'trim', explode( $wpdb->_real_escape( '"' ), $search_on ) ) );
			} else {
				$search_on = $wpdb->_real_escape( $search_on );
                $search_ons = explode( ' ', $search_on );
			}

			if ( is_array( $search_ons ) && ! empty( $search_ons ) ) {
				$term_taxonomy_ids = get_term_taxonomy_ids( '"' . implode( '","', $search_ons ) . '"' );
                                $search_condn = " HAVING ";
				foreach ( $search_ons as $search_on ) {
					$search_condn .= " concat(' ',REPLACE(REPLACE(post_title,'(',''),')','')) LIKE '%$search_on%'
						               OR post_content LIKE '%$search_on%'
						               OR post_excerpt LIKE '%$search_on%'
						               OR if(post_status = 'publish','Published',post_status) LIKE '$search_on%'
									   OR prod_othermeta_value LIKE '%$search_on%'
									   OR";
                                        
				}
                                if ( is_array( $term_taxonomy_ids ) && !empty( $term_taxonomy_ids ) ) {
                                    foreach ( $term_taxonomy_ids as $term_taxonomy_id ) {
                                        $search_condn .= " term_taxonomy_id LIKE '%$term_taxonomy_id%' OR";
                                    }
                                }
                                $search_condn = substr( $search_condn, 0, -2 );
			} else {
				$term_taxonomy_ids = get_term_taxonomy_ids( '"' . $search_on . '"' );
                                $search_condn = " HAVING concat(' ',REPLACE(REPLACE(post_title,'(',''),')','')) LIKE '%$search_on%'
						               OR post_content LIKE '%$search_on%'
						               OR post_excerpt LIKE '%$search_on%'
						               OR if(post_status = 'publish','Published',post_status) LIKE '$search_on%'
									   OR prod_othermeta_value LIKE '%$search_on%'
									   ";
                                if ( is_array( $term_taxonomy_ids ) && !empty( $term_taxonomy_ids ) ) {
                                    foreach ( $term_taxonomy_ids as $term_taxonomy_id ) {
                                        $search_condn .= " OR term_taxonomy_id LIKE '%$term_taxonomy_id%'";
                                    }
                                }
                                
			}
		} else {
			$search_condn = '';
		}

		$from_where = "FROM {$wpdb->prefix}posts as products
						LEFT JOIN {$wpdb->prefix}postmeta as prod_othermeta ON (prod_othermeta.post_id = products.id and
						prod_othermeta.meta_key IN ('_wpsc_price', '_wpsc_special_price', '_wpsc_sku', '_wpsc_stock', '_thumbnail_id','_wpsc_product_metadata') )
                                                
                                                LEFT JOIN {$wpdb->prefix}term_relationships AS term_relationships ON ( products.id = term_relationships.object_id )

						WHERE products.post_status IN  $post_status
						AND products.post_type    = 'wpsc-product'";
		
		$group_by = " GROUP BY products.id ";
		
		$query = "$select $from_where $group_by $search_condn $order_by $limit_string;";
		$records = $wpdb->get_results ( $query );
                $num_rows = $wpdb->num_rows;

        //To get the total count
        $recordcount_query = $wpdb->get_results ( 'SELECT FOUND_ROWS() as count;','ARRAY_A');
        $num_records = $recordcount_query[0]['count'];

        if ($num_rows <= 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = __( 'No Records Found', 'smart-manager' ); 
		} else {
			foreach ( $records as &$record ) {
				if ( intval($record->post_parent) == 0 ) {
                                    $category_terms = wp_get_object_terms($record->id, 'wpsc_product_category', array( 'fields' => 'names', 'orderby' => 'name', 'order' => 'ASC' ));
                                    $record->category = implode( ', ', $category_terms );			// To hide category name from Product's variations
                                }
                            
                                $prod_meta_values = explode ( '###', $record->prod_othermeta_value );
				$prod_meta_key    = explode ( '###', $record->prod_othermeta_key);
				if ( count( $prod_meta_key ) != count( $prod_meta_values ) ) continue;
				$prod_meta_key_values = array_combine ( $prod_meta_key, $prod_meta_values );
//				$prod_meta_key_values ['prod_meta'] = $record->prod_meta;
                
                                if ( intval($record->post_parent) > 0 ) {
                                    $variation_terms = wp_get_object_terms($record->id, 'wpsc-variation', array( 'fields' => 'names', 'orderby' => 'name', 'order' => 'ASC' ));
                                    $record->post_title = substr( $record->post_title, 0, strpos($record->post_title, '(') ) . '(' . implode( ', ', $variation_terms ) . ')';
                                }
		
				$thumbnail = isset( $prod_meta_key_values['_thumbnail_id'] ) ? wp_get_attachment_image_src( $prod_meta_key_values['_thumbnail_id'], $image_size ) : '';
				$record->thumbnail    = ( $thumbnail[0] != '' ) ? $thumbnail[0] : false;

				foreach ( $prod_meta_key_values as $key => $value ) {
					if (is_serialized ( $value )) {
						
						$unsez_data = unserialize ( $value );
						$unsez_data ['weight'] = wpsc_convert_weight ( $unsez_data ['weight'], "pound", $unsez_data ['weight_unit']); // get the weight by converting it to repsective unit
						
						foreach ( (array)$unsez_data as $meta_key => $meta_value ) {
							if (is_array ( $meta_value )) {
								foreach ( $meta_value as $sub_metakey => $sub_metavalue )
									(in_array ( $sub_metakey, $view_columns )) ? $record->$sub_metakey = $sub_metavalue : '';
							} else {
								(in_array ( $meta_key, $view_columns )) ? $record->$meta_key = $meta_value : '';
							}

                                                        if( $record->post_parent == 0 && wpsc_product_has_children( $record->id ) ) {
                                                            if ( $show_variation == true ) {
                                                                $record->_wpsc_price = $record->_wpsc_special_price = '';
                                                            } elseif ( $show_variation == false ) {
                                                                $parent_price = wpsc_product_variation_price_available( $record->id );
                                                                $record->_wpsc_price = substr( $parent_price, 1, strlen( $parent_price ) );
                                                                $record->_wpsc_special_price = substr( $parent_price, 1, strlen( $parent_price ) );
                                                            }
                                                        }
						}

						unset($prod_meta_key_values[$value]);
					} else {
						(in_array ( $key, $view_columns )) ? $record->$key = $value : '';
					}
				}

				unset ( $record->prod_othermeta_value );
				unset ( $record->prod_meta );
				unset ( $record->prod_othermeta_key );
			}
       		}
}//products ->
elseif ($active_module == 'Orders') {

	if (SMPRO == true && function_exists ( 'get_packing_slip' ) && $_POST['label'] == 'getPurchaseLogs'){
		$log_ids_arr = json_decode ( stripslashes ( $_POST['log_ids'] ) );
		if (is_array($log_ids_arr))
		$log_ids = implode(', ',$log_ids_arr);
		get_packing_slip( $log_ids, $log_ids_arr );
	}else{
	
		if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
			$search_on = $wpdb->_real_escape ( trim ( $_POST ['searchText'] ) );
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
			$where = " WHERE wtpl.date BETWEEN '$from_date' AND '$to_date'";
		}
                
                $product_details = "SELECT wtcc.prodid AS product_id,
                                            CAST( if( products.post_parent > 0, CONCAT( SUBSTRING_INDEX( products.post_title, '(', 1 ), if( terms.variations IS NULL, '', '(' ), 
                                                terms.variations, 
                                                if( terms.variations IS NULL, '', ')' ),
                                                if( postmeta.meta_value IS NULL, '', ' [' ),
                                                postmeta.meta_value,
                                                if( postmeta.meta_value IS NULL, '', ']' ) ), products.post_title
                                            ) AS CHAR(1000000) ) AS product_details,
                                            wtcc.name AS additional_product_name
                                            FROM " . WPSC_TABLE_CART_CONTENTS . " AS wtcc
                                                LEFT JOIN {$wpdb->prefix}posts AS products ON ( products.ID = wtcc.prodid )
                                                LEFT JOIN {$wpdb->prefix}postmeta AS postmeta ON ( postmeta.post_id = wtcc.prodid AND postmeta.meta_key = '_wpsc_sku' )
                                                LEFT JOIN {$wpdb->prefix}term_relationships AS term_relationships ON ( term_relationships.object_id = wtcc.prodid )
                                                LEFT JOIN 
                                                (SELECT term_relationships.object_id AS object_id,
                                                    GROUP_CONCAT( DISTINCT terms.name ORDER BY terms.term_id SEPARATOR ',' ) AS variations
                                                    FROM {$wpdb->prefix}term_relationships AS term_relationships
                                                        LEFT JOIN {$wpdb->prefix}term_taxonomy AS term_taxonomy ON ( term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id )
                                                        LEFT JOIN {$wpdb->prefix}terms AS terms ON ( terms.term_id = term_taxonomy.term_id )
                                                    WHERE term_taxonomy.taxonomy = 'wpsc-variation'
                                                    GROUP BY object_id
                                                ) AS terms ON ( terms.object_id = wtcc.prodid )
		
                                            GROUP BY product_id
                                    ";
                $results = $wpdb->get_results( $product_details, 'ARRAY_A' );
				
                $product_details_results = array();
                foreach ( $results as $result ) {
                    $product_details_results[$result['product_id']] = ( !empty( $result['product_details'] ) ) ? $result['product_details'] : $result['additional_product_name'];
                }
                
                if ( !empty( $search_on ) ) {
                    
                    $search_condn_region_country_query = "SELECT CONCAT(wtcl.isocode, '###', wtrt.code)
                                                                FROM " .  WPSC_TABLE_CURRENCY_LIST. " AS wtcl
                                                                    LEFT JOIN " . WPSC_TABLE_REGION_TAX . " AS wtrt ON ( wtrt.country_id = wtcl.id )
                                                                WHERE wtcl.country LIKE '%$search_on%'
                                                                    OR wtcl.continent LIKE '%$search_on%'
                                                                    OR wtrt.name LIKE '%$search_on%'
                                                        ";
                    $region_country_search_ons = $wpdb->get_col( $search_condn_region_country_query );
                    
                    if ( !empty( $region_country_search_ons ) ) {
                        $search_on_region_country .= " (";
                        foreach ( $region_country_search_ons as $region_country_search_on ) {
                            $search_on_region_country .= "meta_values LIKE '%$region_country_search_on%' OR "; 
                        }
                        $search_on_region_country = trim( $search_on_region_country , ' OR ' );
		} else {			
                        $search_condn_checkout_form_details_query = " meta_values LIKE '%$search_on%' ";
                        $search_on_region_country = '';
                    }
                } else {
                    $search_on_region_country = '';
                    $search_condn_checkout_form_details_query = '';
                }
			
                $having = ( !empty( $search_condn_checkout_form_details_query ) || !empty( $search_on_region_country ) ) ? " HAVING " . $search_condn_checkout_form_details_query . $search_on_region_country : '';
                                
                $checkout_form_details_select_query = "SELECT wtsfd.log_id AS purchase_log_id,
                                                        GROUP_CONCAT( wtcf.unique_name ORDER BY wtcf.id SEPARATOR '###' ) AS meta_keys,
                                                        GROUP_CONCAT( wtsfd.value ORDER BY wtsfd.form_id SEPARATOR '###' ) AS meta_values";


                $checkout_form_details_from_query = " FROM " . WPSC_TABLE_SUBMITED_FORM_DATA . " AS wtsfd
                                                            LEFT JOIN " . WPSC_TABLE_CHECKOUT_FORMS . " as wtcf   
                                                            ON (wtsfd.form_id = wtcf.id)
                                                            WHERE wtcf.active = 1 
                                                                AND wtcf.unique_name IN ('billingfirstname', 'billinglastname', 'billingemail',
                                                                                         'shippingfirstname', 'shippinglastname', 'shippingaddress',
                                                                                         'shippingcity', 'shippingstate', 'shippingcountry', 'shippingpostcode')
                                                ";
                
                $results = $wpdb->get_results( $checkout_form_details_select_query . $checkout_form_details_from_query . " GROUP BY purchase_log_id" . $having, 'ARRAY_A' );
                $matched_checkout_form_details = false;
                if ( empty( $results ) ) {
                    $results = $wpdb->get_results( $checkout_form_details_select_query . $checkout_form_details_from_query . " GROUP BY purchase_log_id", 'ARRAY_A' );
                } else {
                    $matched_checkout_form_details = true;
                                        }
                
                $checkout_form_details = array();
                foreach ( $results as $result ) {
                    $checkout_form_details[$result['purchase_log_id']] = array();
                    $checkout_form_details[$result['purchase_log_id']]['meta_keys'] = $result['meta_keys'];
                    $checkout_form_details[$result['purchase_log_id']]['meta_values'] = $result['meta_values'];
                                        }
                                        
                
                $purchase_logs_select_query = "SELECT wtpl.id, 
                                                wtpl.totalprice AS amount, 
                                                wtpl.processed AS order_status, 
                                                wtpl.user_ID AS customer_id, 
                                                date_format(FROM_UNIXTIME(wtpl.date),'%b %e %Y, %r') AS date,
                                                wtpl.date AS unixdate,
                                                wtpl.notes,
                                                wtpl.track_id,
                                                GROUP_CONCAT( CAST(wtcc.prodid AS CHAR) ORDER BY wtcc.id SEPARATOR ',' ) AS product_ids,
                                                CONCAT( CAST(SUM(wtcc.quantity) AS CHAR(100)), ' items') AS details";
                                                
                 $purchase_logs_from_query = " FROM " . WPSC_TABLE_PURCHASE_LOGS . " AS wtpl
                                                    LEFT JOIN " . WPSC_TABLE_CART_CONTENTS . " AS wtcc ON ( wtcc.purchaseid = wtpl.id )
                                        ";
                
                if ( !empty( $search_on ) ) {
                
                    $search_condn_purchase_log_ids = get_all_matched_purchase_log_ids( $search_on, $checkout_form_details_from_query );
                    
                    $variation_search_query = "SELECT DISTINCT tr.object_id
                                                    FROM {$wpdb->prefix}term_relationships AS tr
                                                        LEFT JOIN {$wpdb->prefix}term_taxonomy AS tt ON ( tt.term_taxonomy_id = tr.term_taxonomy_id )
                                                        LEFT JOIN {$wpdb->prefix}terms AS t ON ( t.term_id = tt.term_id )
                                                    WHERE tt.taxonomy = 'wpsc-variation'
                                                        AND t.name LIKE '%$search_on%'

                                                ";
                    $object_ids = $wpdb->get_col( $variation_search_query );
                    
                    $variation_search_ids = ( !empty( $object_ids ) ) ? " OR wtcc.prodid IN ( " . implode( ',', $object_ids ) . " )" : '';
                    
                    $search_condn_purchase_logs = " AND ( wtpl.id LIKE '%$search_on%'
                                                          OR totalprice LIKE '%$search_on%'
                                                          OR notes LIKE '%$search_on%'
                                                          OR date LIKE '%$search_on%'
                                                          OR wtpl.track_id LIKE '%$search_on%'
                                                          OR CASE wtpl.processed
								  WHEN 1 THEN 'Incomplete Sale'
								  WHEN 2 THEN 'Order Received'
								  WHEN 3 THEN 'Accepted Payment'
								  WHEN 4 THEN 'Job Dispatched'
								  WHEN 5 THEN 'Closed Order'
								  ELSE 'Payment Declined'
							     END like '%$search_on%'
                                                          OR wtcc.name LIKE '%$search_on%'
                                                          $variation_search_ids
                                                         )
                                                    $search_condn_purchase_log_ids
                                                    ";
                
                } else {
                    $search_condn_purchase_logs = '';
                                    }
                
                $query = $purchase_logs_select_query . $purchase_logs_from_query . $where . $search_condn_purchase_logs . " GROUP BY wtpl.id ORDER BY wtpl.id DESC $limit_string";
                $results = $wpdb->get_results( $query, 'ARRAY_A' );
                if ( !$is_export ) {
                    $orders_count_result = $wpdb->get_results ( substr( $query, 0, strpos( $query, 'LIMIT' ) ),'ARRAY_A');
                    $num_records = count( $orders_count_result ); 
                } else {
                    $num_records = count( $results ); 
                                }
                                
		//To get the total count
		if ($num_records == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = __( 'No Records Found', 'smart-manager' );
		} else {			
                                
                        foreach ( $results as $data ) {
                            if ( $matched_checkout_form_details && !isset( $checkout_form_details[$data['id']] ) ) continue;
				
                            $checkout_form_details_keys = explode( '###', $checkout_form_details[$data['id']]['meta_keys'] );
                            $checkout_form_details_values = explode( '###', $checkout_form_details[$data['id']]['meta_values'] );

                            if ( count( $checkout_form_details_keys ) == count( $checkout_form_details_values ) ) {
                                $checkout_form_data = array_combine( $checkout_form_details_keys, $checkout_form_details_values );
                                
                                $name_emailid [0] = "<font class=blue>". $checkout_form_data['billingfirstname']."</font>";
                                $name_emailid [1] = "<font class=blue>". $checkout_form_data['billinglastname']."</font>";
                                $name_emailid [2] = "(".$checkout_form_data['billingemail'].")"; //email comes at 7th position.
					$data['name'] 	  = implode ( ' ', $name_emailid ); //in front end,splitting is done with this space.

                                $prod_ids = explode( ',', $data['product_ids'] );
                            
                                $products_name = '';
                                foreach ( $prod_ids as $prod_id ) {
                                    $products_name .= $product_details_results[$prod_id] . ', ';
					}
                                $data['products_name'] = trim( $products_name, ', ' );
                                
                                if( !empty( $checkout_form_data['shippingstate'] ) ) {
                                    $ship_state = $checkout_form_data['shippingstate'];
                                    $checkout_form_data['shippingstate'] = ( $regions_ids[$ship_state] != '' ) ? $regions_ids[$ship_state] : $ship_state;
				}
                                
                                if( !empty( $checkout_form_data['shippingcountry'] ) ) {
                                    $ship_country = $checkout_form_data['shippingcountry'];
                                    $checkout_form_data['shippingcountry'] = ( $country_data[$ship_country] != '' ) ? $country_data[$ship_country] : $ship_country;
			}
                                
                                $records[] = ( !empty( $checkout_form_data ) ) ? array_merge ( $checkout_form_data, $data ) : $data;
                            
		}
                            
                            unset( $data );
                            unset( $checkout_form_details_keys );
                            unset( $checkout_form_details_values );
                            unset( $checkout_form_data );
                            
	}
	
                }
	}

    	} else {
    
		//BOF Customer's module
                if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
                    $search_on = $wpdb->_real_escape ( trim( $_POST ['searchText'] ) );
		}
                
                if ( !empty( $search_on ) ) {
                    $searched_region = $wpdb->get_col( "SELECT code FROM " . WPSC_TABLE_REGION_TAX . " WHERE name LIKE '%$search_on%'" );
                    $searched_country = $wpdb->get_col( "SELECT isocode FROM " . WPSC_TABLE_CURRENCY_LIST . " WHERE country LIKE '%$search_on%' OR continent LIKE '%$search_on%'" );
                    $found_country_region = array_merge( $searched_region, $searched_country );
                    $found_country_region_having = '';
                    foreach ( $found_country_region as $country_region ) {
                        $found_country_region_having .= " OR meta_values LIKE '%$country_region%'";
                    }
                }
                
                $customer_details_query_select = "SELECT wtsfd.log_id AS log_id,
                                                            GROUP_CONCAT( wtcf.unique_name ORDER BY wtcf.id SEPARATOR '###' ) AS meta_keys,
                                                            GROUP_CONCAT( wtsfd.value ORDER BY wtsfd.form_id SEPARATOR '###' ) AS meta_values

                                                        FROM " . WPSC_TABLE_SUBMITED_FORM_DATA . " AS wtsfd
                                                            RIGHT JOIN " . WPSC_TABLE_CHECKOUT_FORMS . " AS wtcf ON ( wtcf.id = wtsfd.form_id AND wtcf.active = 1 AND wtcf.unique_name IN ('billingfirstname','billinglastname','billingaddress',
                                                                                                    'billingcity','billingstate','billingcountry','billingpostcode',
                                                                                                    'billingemail','billingphone') )
                                                        GROUP BY log_id";
                
                if ( !empty( $search_on ) ) {
                    $customer_details_query_having = " HAVING meta_values LIKE '%$search_on%'
                                                              $found_country_region_having
                                                     ";
                } else {
                    $customer_details_query_having = '';
                }
                
                $full_customer_details_query = $customer_details_query_select . $customer_details_query_having;
                                                        
                $customer_details_results = $wpdb->get_results( $full_customer_details_query, 'ARRAY_A' );
                                                 
                $customer_detail_data = array();
                foreach ( $customer_details_results as $customer_details_result ) {
                    $meta_keys = explode( '###', $customer_details_result['meta_keys'] );
                    $meta_values = explode( '###', $customer_details_result['meta_values'] );
                    if ( count( $meta_keys ) == count( $meta_values ) ) {
                        $customer_detail_data[$customer_details_result['log_id']] = array_combine( $meta_keys, $meta_values );
                    }
                }
                
                $log_ids = array_keys( $customer_detail_data );
                
                $email_form_id = $wpdb->get_var("SELECT id FROM " .WPSC_TABLE_CHECKOUT_FORMS . " WHERE unique_name = 'billingemail'");
                
                $customer_query_select = "SELECT if( wtpl.user_ID = 0, '', wtpl.user_ID) AS id,
                                                    MAX(wtpl.id) AS last_order_id,
                                                    DATE_FORMAT( FROM_UNIXTIME( wtpl.date ),'%b %e %Y' ) AS Last_Order,
                                                    COUNT(wtpl.id) AS count_orders,
                                                    SUM(wtpl.totalprice) AS total_orders,
                                                    wtpl.totalprice AS _order_total,
                                                    customer_email.value AS email ";
                                                    
                $customer_query_from = " FROM " . WPSC_TABLE_PURCHASE_LOGS . " AS wtpl
                                                 LEFT JOIN " . WPSC_TABLE_SUBMITED_FORM_DATA . " AS customer_email ON ( customer_email.log_id = wtpl.id AND customer_email.form_id = $email_form_id ) ";
                
                if ( !empty( $log_ids ) ) {
                    $customer_query_where = " WHERE wtpl.id IN ( " . implode( ',', $log_ids ) . " ) ";
                    $customer_query_having = "";
                } else {
                    $customer_query_where = '';
                    $customer_query_having = " HAVING   id LIKE '$search_on%'
                                                        OR last_order_id LIKE '$search_on%'
                                                        OR Last_Order LIKE '$search_on%'
                                                        OR count_orders LIKE '$search_on%'
                                                        OR total_orders LIKE '$search_on%'
                                                        OR _order_total LIKE '$search_on%'
                                                        OR email LIKE '%$search_on%'
                                             ";
                }
                                                 
                $customer_query_group_by = " GROUP BY if( wtpl.user_ID = 0, email, wtpl.user_ID )";
                
                $customer_query_order_by = " ORDER BY wtpl.user_ID DESC $limit_string";
                
                $full_customer_query = $customer_query_select . $customer_query_from . $customer_query_where . $customer_query_group_by . $customer_query_having . $customer_query_order_by;
                
                $results = $wpdb->get_results( $full_customer_query, 'ARRAY_A' );
                
                if ( !$is_export ) {
                    $customers_count_result = $wpdb->get_results ( substr( $full_customer_query, 0, strpos( $full_customer_query, 'LIMIT' ) ),'ARRAY_A');
                    $num_records = count( $customers_count_result ); 
                } else {
                    $num_records = count( $results );
                }

                if ($num_records == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = __( 'No Records Found', 'smart-manager' );
		} else {
				
			foreach ( $results as $result ) {

					if ( empty( $customer_detail_data[$result['last_order_id']] ) ) {
                                            $num_records--;
                                            continue;
                                        }
                                        $billing_user_details = $customer_detail_data[$result['last_order_id']];
                                        $billing_user_details['billingstate'] = ( !empty( $regions_ids[$billing_user_details['billingstate']] ) ) ? $regions_ids[$billing_user_details['billingstate']] : $billing_user_details['billingstate'];
                                        $billing_user_details['billingcountry'] = ( !empty( $country_data[$billing_user_details['billingcountry']] ) ) ? $country_data[$billing_user_details['billingcountry']] : $billing_user_details['billingcountry'];
					
                                        if (SMPRO == false) {
                                            $result['Last_Order'] = 'Pro only';
                                            $result['_order_total'] = 'Pro only';
                                            $result['count_orders']= 'Pro only';
                                            $result['total_orders']= 'Pro only';
					}
					//NOTE: storing old email id in an extra column in record so useful to indentify record with emailid during updates.
                                        $result ['Old_Email_Id'] = $billing_user_details ['billingemail'];
                                        $records[] = ( !empty( $billing_user_details ) ) ? array_merge ( $billing_user_details, $result ) : $result;

                           unset($result);
                           unset($meta_keys);
                           unset($meta_values);
                           unset($billing_user_details);
                        }
                }
	
        }
	
        if (!isset($_POST['label']) && $_POST['label'] != 'getPurchaseLogs'){
		$encoded ['items'] = $records;
		$encoded ['totalCount'] = $num_records;
		unset($records);
                return $encoded;
	}
}

// Searching a product in the grid
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getData') {
	$encoded = get_data_wpsc_38 ( $_POST, $offset, $limit );
	ob_clean();
        echo json_encode ( $encoded );
	unset($encoded);
}


if (isset ( $_GET ['cmd'] ) && $_GET ['cmd'] == 'exportCsvWpsc') {

	$sm_domain = 'smart-manager';
        $columns_header = array();
	$active_module = $_GET ['active_module'];
        
	switch ( $active_module ) {
		
		case 'Products':
				$columns_header['id'] 						= __('Post ID', $sm_domain);
				$columns_header['thumbnail'] 				= __('Product Image', $sm_domain);
				$columns_header['post_title'] 				= __('Product Name', $sm_domain);
				$columns_header['_wpsc_price'] 				= __('Price', $sm_domain);
				$columns_header['_wpsc_special_price'] 		= __('Sale Price', $sm_domain);
				$columns_header['_wpsc_stock'] 				= __('Inventory / Stock', $sm_domain);
				$columns_header['_wpsc_sku'] 				= __('SKU', $sm_domain);
				$columns_header['category'] 				= __('Category / Group', $sm_domain);
				$columns_header['post_content'] 			= __('Product Description', $sm_domain);
				$columns_header['post_excerpt'] 			= __('Additional Description', $sm_domain);
				$columns_header['weight'] 					= __('Weight', $sm_domain);
				$columns_header['weight_unit'] 				= __('Weight Unit', $sm_domain);
				$columns_header['height'] 					= __('Height', $sm_domain);
				$columns_header['height_unit'] 				= __('Height Unit', $sm_domain);
				$columns_header['width'] 					= __('Width', $sm_domain);
				$columns_header['width_unit'] 				= __('Width Unit', $sm_domain);
				$columns_header['length'] 					= __('Length', $sm_domain);
				$columns_header['length_unit'] 				= __('Length Unit', $sm_domain);
				$columns_header['local'] 					= __('Local Shipping Fee', $sm_domain);
				$columns_header['international'] 			= __('International Shipping Fee', $sm_domain);
			break;
			
		case 'Customers':
				$columns_header['id'] 					= __('User ID', $sm_domain);
				$columns_header['billingfirstname'] 	= __('First Name', $sm_domain);
				$columns_header['billinglastname'] 		= __('Last Name', $sm_domain);
				$columns_header['billingemail'] 		= __('E-mail ID', $sm_domain);
				$columns_header['billingaddress'] 		= __('Address', $sm_domain);
				$columns_header['billingpostcode'] 		= __('Postcode', $sm_domain);
				$columns_header['billingcity'] 			= __('City', $sm_domain);
				$columns_header['billingstate'] 		= __('State / Region', $sm_domain);
				$columns_header['billingcountry'] 		= __('Country', $sm_domain);
				$columns_header['billingphone'] 		= __('Phone / Mobile', $sm_domain);
                                $columns_header['_order_total'] 		= __('Last Order Total', $sm_domain);
				$columns_header['Last_Order'] 		= __('Last Order Date', $sm_domain);
                $columns_header['count_orders']          = __('Total Number Of Orders', $sm_domain);
                                $columns_header['total_orders'] 		= __('Total Purchased Till Date (By Customer)', $sm_domain);
				
			break;
			
		case 'Orders':
				$columns_header['id'] 						= __('Order ID', $sm_domain);
				$columns_header['date'] 					= __('Order Date', $sm_domain);
				$columns_header['billingfirstname'] 		= __('Billing First Name', $sm_domain);
				$columns_header['billinglastname'] 			= __('Billing Last Name', $sm_domain);
				$columns_header['billingemail'] 			= __('Billing E-mail ID', $sm_domain);
				$columns_header['amount'] 					= __('Order Total', $sm_domain);
				$columns_header['details'] 					= __('Total No. of Items', $sm_domain);
				$columns_header['products_name'] 			= __('Order Items (Product Name[SKU])', $sm_domain);
				$columns_header['order_status'] 			= __('Order Status', $sm_domain);
				$columns_header['track_id'] 				= __('Track ID', $sm_domain);
				$columns_header['notes'] 					= __('Order Notes', $sm_domain);
				$columns_header['shippingfirstname'] 		= __('Shipping First Name', $sm_domain);
				$columns_header['shippinglastname'] 		= __('Shipping Last Name', $sm_domain);
				$columns_header['shippingaddress'] 			= __('Shipping Address', $sm_domain);
				$columns_header['shippingpostcode'] 		= __('Shipping Postcode', $sm_domain);
				$columns_header['shippingcity'] 			= __('Shipping City', $sm_domain);
				$columns_header['shippingstate'] 			= __('Shipping State / Region', $sm_domain);
				$columns_header['shippingcountry'] 			= __('Shippping Country', $sm_domain);
			break;
	}
	if ( $active_module == 'Products' ) {
		$_GET['viewCols'] = json_encode( array_keys( $columns_header ) );
	}
        
	$encoded = get_data_wpsc_38 ( $_GET, $offset, $limit, true );
	$data = $encoded ['items'];
	unset($encoded);
	
	$file_data = export_csv_wpsc_38 ( $active_module, $columns_header, $data );

	header("Content-type: text/x-csv; charset=UTF-8"); 
	header("Content-Transfer-Encoding: binary");
	header("Content-Disposition: attachment; filename=".$file_data['file_name']); 
	header("Pragma: no-cache");
	header("Expires: 0");
		
	ob_clean();
        echo $file_data['file_content'];
		
	exit;
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'delData') {
	global $purchlogs;
	$purchlogs = new wpsc_purchaselogs ();
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
				$encoded ['msg'] = "<b>" . $delCnt . "</b> " . __( 'Product deleted Successfully', 'smart-manager' ); 
				$encoded ['delCnt'] = $delCnt;
			} else {
				$encoded ['msg'] = "<b>" . $delCnt . "</b> " . __( 'Products deleted Successfully', 'smart-manager' );
				$encoded ['delCnt'] = $delCnt;
			}
		} elseif ($result == false) {
			$encoded ['msg'] = __("Products were not deleted", 'smart-manager' );
		} else {
			$encoded ['msg'] = __( "Products removed from the grid", 'smart-manager' );
		}
	} else if ($active_module == 'Orders') {
		$data = json_decode ( stripslashes ( $_POST ['data'] ) );
		foreach ( $data as $key => $id ) {
			$output = $purchlogs->deletelog ( $id );
			$delCnt ++;
		}
		
		if ($output) {
			//			$encoded ['msg'] = strip_tags($output);
			if ($delCnt == 1) {
				$encoded ['msg'] = "<b>" . $delCnt . "</b> " . __( 'Purchase Log deleted Successfully', 'smart-manager' ) ;
				$encoded ['delCnt'] = $delCnt;
			} else {
				$encoded ['msg'] = "<b>" . $delCnt . "</b> " . __( 'Purchase Logs deleted Successfully', 'smart-manager' ) ;
				$encoded ['delCnt'] = $delCnt;
			}
		} else
			$encoded ['msg'] = __( "Purchase Logs removed from the grid", 'smart-manager' ); 
	}
	ob_clean();
        echo json_encode ( $encoded );
}

//update products for lite version.
function update_products($_POST) {
	global $result, $wpdb;
	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$updateCnt = 1;
	foreach ( $edited_object as $obj ) {
		
		$update_name = $wpdb->query ( "UPDATE $wpdb->posts SET `post_title`= '".$wpdb->_real_escape($obj->post_title)."' WHERE ID = " . $wpdb->_real_escape($obj->id) );
		$update_price = $wpdb->query ( "UPDATE $wpdb->postmeta SET `meta_value`= ".$wpdb->_real_escape($obj->_wpsc_price)." WHERE meta_key = '_wpsc_price' AND post_id = " . $wpdb->_real_escape($obj->id) );
		$result ['updateCnt'] = $updateCnt ++;
	}
	
	if (($update_name >= 1 || $update_price >= 1) && $result ['updateCnt'] >= 1) {
		$result ['result'] = true;
		$result ['updated'] = 1;
	}
	return $result;
}

// Update Order LITE version
function update_orders($_POST) {
    global $wpdb; // to use as global
    $edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );

    $ordersCnt = 1;
    foreach ( $edited_object as $obj ) {
        $query = "UPDATE `". WPSC_TABLE_PURCHASE_LOGS . "`
						   SET 	processed ='".$wpdb->_real_escape($obj->order_status)."'
				   				 WHERE id ='".$wpdb->_real_escape($obj->id)."'";
        $update_result = $wpdb->query ( $query );
        $result ['updateCnt'] = $ordersCnt ++;
    }
    $result ['result'] = true;
    $result ['updated'] = 1;
    return $result;
}

// For updating product,orders and customers details.
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'saveData') {
	if ($active_module == 'Products') {
		if (SMPRO == true)
			$result = data_for_insert_update ( $_POST );
		else
			$result = update_products ( $_POST );
	} elseif ($active_module == 'Orders') {
        if (SMPRO == true)
            $result = data_for_update_orders ( $_POST );
        else
            $result = update_orders ( $_POST );
    } elseif ($active_module == 'Customers') {
        $result = update_customers ( $_POST );
    }

	if ($result ['result']) {
		if ($result ['updated'] && $result ['inserted']) {
			if ($result ['updateCnt'] == 1 && $result ['insertCnt'] == 1)
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __( 'Record Updated and', 'smart-manager' ) . "<br><b>" . $result ['insertCnt'] . "</b> " . __( 'New Record Inserted Successfully', 'smart-manager' );
			elseif ($result ['updateCnt'] == 1 && $result ['insertCnt'] != 1)
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __( 'Record Updated and', 'smart-manager' ) . "<br><b>" . $result ['insertCnt'] . "</b> " . __( 'New Records Inserted Successfully', 'smart-manager' );
			elseif ($result ['updateCnt'] != 1 && $result ['insertCnt'] == 1)
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __( 'Records Updated and', 'smart-manager' ) . "<br><b>" . $result ['insertCnt'] . "</b> " . __( 'New Record Inserted Successfully', 'smart-manager' );
			else
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __( 'Records Updated and', 'smart-manager' ) . "<br><b>" . $result ['insertCnt'] . "</b> " . __( 'New Records Inserted Successfully', 'smart-manager' ); 
		} else {
			
			if ($result ['updated'] == 1) {
				if ($result ['updateCnt'] == 1) {
					$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __( 'Record Updated Successfully', 'smart-manager' );
				} else
					$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __( 'Records Updated Successfully', 'smart-manager' );
			}
			
			if ($result ['inserted'] == 1) {
				if ($result ['updateCnt'] == 1)
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> " . __( 'New Records Inserted Successfully', 'smart-manager' ); 
				else
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> " . __(' New Records Inserted Successfully', 'smart-manager' );
			}
			
		}
	}
	ob_clean();
        echo json_encode ( $encoded );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getRolesDashboard') {
	global $wpdb, $current_user;
	$current_user = wp_get_current_user();
	if ( SMPRO != true || $current_user->roles[0] == 'administrator') {
		$results = array( 'Products', 'Customers_Orders' );
	} else {
		$results = get_dashboard_combo_store();
	}
	ob_clean();
        echo json_encode ( $results );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'editImage') {
	$wpsc_default_image = WP_PLUGIN_URL . '/wp-e-commerce/wpsc-theme/wpsc-images/noimage.png';
	$post_thumbnail_id = get_post_thumbnail_id( $_POST ['id'] );
	$image = isset( $post_thumbnail_id ) ? wp_get_attachment_image_src( $post_thumbnail_id, 'admin-product-thumbnails' ) : '';
	$thumbnail = ( $image[0] != '' ) ? $image[0] : '';
	ob_clean();
        echo json_encode ( $thumbnail );
}

?>