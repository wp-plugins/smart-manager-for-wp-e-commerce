<?php
ob_start();

if ( ! defined('ABSPATH') ) {
    include_once ('../../../../wp-load.php');
}

include_once (ABSPATH . 'wp-includes/wp-db.php');
include_once (ABSPATH . 'wp-includes/functions.php');
include_once (ABSPATH . 'wp-admin/includes/screen.php'); // Fix to handle the WPeC 3.8.10 and Higher versions
require_once( WP_PLUGIN_DIR . '/wp-e-commerce/wpsc-admin/includes/product-functions.php' );     // Fix for undefined function 'wpsc_product_has_children'
include_once (WP_PLUGIN_DIR . '/wp-e-commerce/wpsc-core/wpsc-functions.php');
include_once (WP_PLUGIN_DIR . '/wp-e-commerce/wpsc-includes/purchaselogs.class.php');
load_textdomain( 'smart-manager', WP_PLUGIN_DIR . '/smart-manager-for-wp-e-commerce/languages/smart-manager-' . WPLANG . '.mo' );

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
if (file_exists ( WP_PLUGIN_DIR . '/smart-manager-for-wp-e-commerce/pro/sm38.php' )) {
	define ( 'SMPRO', true );
	include_once ( WP_PLUGIN_DIR . '/smart-manager-for-wp-e-commerce/pro/sm38.php' );
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

$active_module = (isset($_POST ['active_module']) ? $_POST ['active_module'] : 'Products');
//$active_module = $_POST ['active_module'];

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
function get_data_wpsc_38 ( $post, $offset, $limit, $is_export = false ) {
	global $wpdb,$post_status,$parent_sort_id,$order_by;
	$_POST = $post;     // Fix: PHP 5.4
        $regions_ids = get_regions_ids();		
	$country_results = $wpdb->get_results( "SELECT isocode, country FROM " . WPSC_TABLE_CURRENCY_LIST, 'ARRAY_A' );
        $country_data = array();
        foreach ( $country_results as $country_result ) {
            $country_data[$country_result['isocode']] = $country_result['country'];
        }

        
	// getting the active module
	// $active_module = $_POST ['active_module'];
        $active_module = (isset($_POST ['active_module']) ? $_POST ['active_module'] : 'Products');

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

		$wpsc_default_image = WP_PLUGIN_URL . '/wp-e-commerce/wpsc-theme/wpsc-images/noimage.png';
		if (isset ( $_POST ['incVariation'] ) && $_POST ['incVariation'] == 'true' && SMPRO == true) {
			$show_variation = true;
		} else { // query params for non-variation products
			$show_variation = false;
			$post_status = "('publish', 'draft')";
			$parent_sort_id = '';
			$order_by = " ORDER BY products.id desc";
		}

                $query_ids = "SELECT `ID` FROM {$wpdb->prefix}posts 
                            WHERE `post_type` = 'wpsc-product' 
                                AND `post_status` = 'publish' 
                                AND `post_parent`=0 
                                AND `ID` NOT IN ( SELECT distinct `post_parent` 
                                                  FROM {$wpdb->prefix}posts WHERE `post_parent`>0)";
                
                $result_ids = $wpdb->get_col ( $query_ids );
                $num_ids = $wpdb->num_rows;

                if ($num_ids > 0) {
                    for ($i=0;$i<sizeof($result_ids);$i++) {
                        $simple_ids [$result_ids[$i]] = 0;
                    }
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

                        //Code for searching using modified post title
                        $query_title = "SELECT ID FROM {$wpdb->prefix}posts
                                        WHERE post_title LIKE '%$search_on%'
                                            AND post_type = 'wpsc-product'";
                        $records_title = $wpdb->get_col ( $query_title );
                        $rows_title = $wpdb->num_rows;

                        if ($rows_title > 0) {
                            $search_title = "OR products.post_parent IN (
                                                    SELECT ID FROM {$wpdb->prefix}posts
                                                    WHERE post_title LIKE '%$search_on%'
                                                        AND post_type = 'wpsc-product')";
                        }
                        else {
                            $search_title = " ";
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
                                                                            $search_title
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
                                                                               $search_title
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

				$record->post_content = str_replace('"','\'',$record->post_content);
				$record->post_excerpt = str_replace('"','\'',$record->post_excerpt);

				if ( intval($record->post_parent) == 0 ) {
                                    $category_terms = wp_get_object_terms($record->id, 'wpsc_product_category', array( 'fields' => 'names', 'orderby' => 'name', 'order' => 'ASC' ));
                                    $record->category = implode( ', ', $category_terms );			// To hide category name from Product's variations
                                }
                            
                                $prod_meta_values = explode ( '###', $record->prod_othermeta_value );
				$prod_meta_key    = explode ( '###', $record->prod_othermeta_key);
				if ( count( $prod_meta_key ) != count( $prod_meta_values ) ) continue;
				$prod_meta_key_values = array_combine ( $prod_meta_key, $prod_meta_values );
                
                                if ( intval($record->post_parent) > 0 ) {
                                    $title = get_post_field( 'post_title', $record->post_parent, 'raw' );
                                    $variation_terms = wp_get_object_terms($record->id, 'wpsc-variation', array( 'fields' => 'names', 'orderby' => 'name', 'order' => 'ASC' ));
                                    $record->post_title = $title . ' - (' . implode( ', ', $variation_terms ) . ')';
                                }
		
//				$thumbnail = isset( $prod_meta_key_values['_thumbnail_id'] ) ? wp_get_attachment_image_src( $prod_meta_key_values['_thumbnail_id'], $image_size ) : '';
//				$record->thumbnail    = ( $thumbnail[0] != '' ) ? $thumbnail[0] : false;

                $thumbnail = wpsc_the_product_thumbnail( '', '', $record->id, '' );
                $record->thumbnail    = ( $thumbnail != '' ) ? $thumbnail : false;

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
                                                                $record->_wpsc_price = $record->_wpsc_special_price = ' ';
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
                                            CAST( CONCAT( if( products.post_parent > 0, SUBSTRING_INDEX( products.post_title, '(', 1 ), products.post_title ), if( products.post_parent > 0, CONCAT( if( terms.variations IS NULL, '', '(' ), 
                                                terms.variations, 
                                                if( terms.variations IS NULL, '', ')' ) ), '' ),
                                                if( postmeta.meta_value != '',' [', ' '),
                                                postmeta.meta_value,
                                                if( postmeta.meta_value != '',']', ' ' ) )
                                             AS CHAR(1000000) ) AS product_details,
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
                    
                    //Query for searching for Shipping_Country
                    $search_condn_country_query = "SELECT DISTINCT wtcl.isocode
                                                                FROM " .  WPSC_TABLE_CURRENCY_LIST. " AS wtcl
                                                                WHERE wtcl.country LIKE '%$search_on%'
                                                                    OR wtcl.continent LIKE '%$search_on%'
                                                        ";
                    
                    $country_search_ons = $wpdb->get_col( $search_condn_country_query );
                    
                    //Query for searching for Shipping_Region
                    $search_condn_region_query = "SELECT DISTINCT wtrt.id
                                                                FROM " . WPSC_TABLE_REGION_TAX . " AS wtrt
                                                                WHERE wtrt.name LIKE '%$search_on%'
                                                        ";
                    
                    $region_search_ons = $wpdb->get_col( $search_condn_region_query );
                    
                    //Code for handling the search using user email id
                    $email_query = "SELECT ID FROM $wpdb->users 
                                        WHERE user_email LIKE '%$search_on%'";
                    $email_result = $wpdb->get_col($email_query);
                    $email_rows = $wpdb->num_rows;
                    
                    if ($email_rows > 0) {
                        $email_query1 = "SELECT ID FROM {$wpdb->prefix}wpsc_purchase_logs 
                                        WHERE user_ID IN (". implode(",",$email_result) .")";
                        $email_result1 = $wpdb->get_col($email_query1);
                        
                        $email_search = ( !empty( $email_result1 ) ) ? " OR wtsfd.log_id IN ( " . implode( ',', $email_result1 ) . " )" : '';
                    }
                    
                    //Code for handling search using shipping_county OR shipping_Region
                    if ( !(empty( $country_search_ons )) || !(empty( $region_search_ons ))) {
                        $search_on_region_country .= " (";
                        foreach ( $country_search_ons as $country_search_on ) {
                            $search_on_region_country .= "meta_values LIKE '%###$country_search_on###%' OR "; 
                        }
                    
                        for ($j=0;$j<sizeof($region_search_ons);$j++) {
                            $search_on_region_country .= "meta_values LIKE '%###$region_search_ons[$j]###%' OR "; 
                        }
                    
                        $search_on_region_country = trim( $search_on_region_country , ' OR ' );
                        $search_on_region_country .= " )";
		} else {			
                        $search_condn_checkout_form_details_query = " meta_values LIKE '%$search_on%' 
                                                                      $email_search";
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
                                                                                         'shippingcity', 'shippingstate', 'shippingcountry', 'shippingpostcode','billingphone')
                                                ";
                
                
                $results = $wpdb->get_results( $checkout_form_details_select_query . $checkout_form_details_from_query . " GROUP BY purchase_log_id" . $having, 'ARRAY_A' );
                $result_shipping = $results;
               
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
                    
                    
                    $email_query = "SELECT ID FROM $wpdb->users 
                                        WHERE user_email LIKE '%$search_on%'";
                    $email_result = $wpdb->get_col($email_query);
                    
                    $email_search = ( !empty( $email_result ) ) ? " OR wtpl.user_ID IN ( " . implode( ',', $email_result ) . " )" : '';
                    
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
                                                          $email_search
                                                         )
                                                    $search_condn_purchase_log_ids
                                                    ";
                
                } else {
                    $search_condn_purchase_logs = '';
                                    }
                
                $query = $purchase_logs_select_query . $purchase_logs_from_query . $where . $search_condn_purchase_logs . " GROUP BY wtpl.id ORDER BY wtpl.id DESC $limit_string";
                $results = $wpdb->get_results( $query, 'ARRAY_A' );
                
                if ( empty( $results ) ) {
                    
                    for ($i=0;$i<sizeof($result_shipping);$i++) {
                        $log_id[$i] = $result_shipping[$i]['purchase_log_id'];
                    }
                    
                    if (!(is_null($log_id))) {
                        $where .= "AND wtpl.id IN(" . implode(",",$log_id) .")";
                        $query = $purchase_logs_select_query . $purchase_logs_from_query . $where . " GROUP BY wtpl.id ORDER BY wtpl.id DESC $limit_string";
                        $results = $wpdb->get_results( $query, 'ARRAY_A' );
                    }
                    
                    
                }
                
                if ( !$is_export ) {
                    $orders_count_result = $wpdb->get_results ( substr( $query, 0, strpos( $query, 'LIMIT' ) ),'ARRAY_A');
                    $num_records = count( $orders_count_result ); 
                } else {
                    $num_records = count( $results ); 
                                }
                                
                    $query = "SELECT ID,user_email FROM $wpdb->users";
                    $reg_user = $wpdb->get_results ($query ,'ARRAY_A');
                    
                    for ($i=0;$i<sizeof($reg_user);$i++) {
                        $user_email[$reg_user[$i]['ID']] = $reg_user[$i]['user_email'];
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

                                if ($data['customer_id'] > 0) {
                                    $data['reg_email'] = $user_email[$data['customer_id']];
                                }
                                else {
                                    $data['reg_email'] = "";
                                }
                                    
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
		} else{
                    $search_on = "";
                }
                
                $email_form_id = $wpdb->get_var("SELECT id FROM " .WPSC_TABLE_CHECKOUT_FORMS . " WHERE unique_name = 'billingemail'");
                
                $query_max_users_ids = "SELECT GROUP_CONCAT(wtpl.id ORDER BY wtpl.date DESC SEPARATOR ',' ) AS last_order_id,
                                        GROUP_CONCAT(wtpl.totalprice ORDER BY wtpl.date DESC SEPARATOR ',' ) AS _order_total,
                                        DATE_FORMAT( MAX(FROM_UNIXTIME( wtpl.date )),'%b %e %Y' ) AS Last_Order,
                                        COUNT(wtpl.id) AS count_orders,
                                        SUM(wtpl.totalprice) AS total_orders
                    
                                        FROM " . WPSC_TABLE_PURCHASE_LOGS . " AS wtpl
                                                 LEFT JOIN " . WPSC_TABLE_SUBMITED_FORM_DATA . " AS customer_email ON ( customer_email.log_id = wtpl.id AND customer_email.form_id = $email_form_id )
                                        WHERE wtpl.user_ID > 0
                                        Group by wtpl.user_ID";
                $result_max_users_ids = $wpdb -> get_results($query_max_users_ids, 'ARRAY_A' );
                
                $query_max_guest_ids = "SELECT GROUP_CONCAT(wtpl.id ORDER BY wtpl.date DESC SEPARATOR ',' ) AS last_order_id,
                                        GROUP_CONCAT(wtpl.totalprice ORDER BY wtpl.date DESC SEPARATOR ',' ) AS _order_total,
                                        DATE_FORMAT( MAX(FROM_UNIXTIME( wtpl.date )),'%b %e %Y' ) AS Last_Order,
                                        COUNT(wtpl.id) AS count_orders,
                                        SUM(wtpl.totalprice) AS total_orders
                                        
                                        FROM " . WPSC_TABLE_PURCHASE_LOGS . " AS wtpl
                                                 LEFT JOIN " . WPSC_TABLE_SUBMITED_FORM_DATA . " AS customer_email ON ( customer_email.log_id = wtpl.id AND customer_email.form_id = $email_form_id )
                                        WHERE wtpl.user_ID = 0
                                        GROUP BY customer_email.value
                                        ORDER BY Last_Order DESC";
                $result_max_guest_ids = $wpdb -> get_results($query_max_guest_ids, 'ARRAY_A' );
                
                for ($i=0;$i<sizeof($result_max_guest_ids);$i++) {
                    $temp_id =  explode(",",$result_max_guest_ids[$i]['last_order_id']);
                    $max_id[$i] = $temp_id[0];
                    
                    $count_orders[$max_id[$i]] = $result_max_guest_ids[$i]['count_orders'];
                    $total_orders[$max_id[$i]] = $result_max_guest_ids[$i]['total_orders'];
                    $order_date[$max_id[$i]] = $result_max_guest_ids[$i]['Last_Order'];
                    
                    $temp_tot =  explode(",",$result_max_guest_ids[$i]['_order_total']);
                    $last_order_total[$max_id[$i]] = $temp_tot[0];
                    
                }
                
                $j=sizeof($max_id);
                
                for ($i=0;$i<sizeof($result_max_users_ids);$i++,$j++) {
                    $temp_id =  explode(",",$result_max_users_ids[$i]['last_order_id']);
                    $max_id[$j] = $temp_id[0];
                    
                    $count_orders[$max_id[$j]] = $result_max_users_ids[$i]['count_orders'];
                    $total_orders[$max_id[$j]] = $result_max_users_ids[$i]['total_orders'];
                    $order_date[$max_id[$j]] = $result_max_users_ids[$i]['Last_Order'];
                    
                    $temp_tot =  explode(",",$result_max_users_ids[$i]['_order_total']);
                    $last_order_total[$max_id[$j]] = $temp_tot[0];
                    
                }
                
                
                $total_search = "";
                
                if ( !empty( $search_on ) ) {
                    $searched_region = $wpdb->get_col( "SELECT code FROM " . WPSC_TABLE_REGION_TAX . " WHERE name LIKE '%$search_on%'" );
                    $searched_country = $wpdb->get_col( "SELECT isocode FROM " . WPSC_TABLE_CURRENCY_LIST . " WHERE country LIKE '%$search_on%' OR continent LIKE '%$search_on%'" );
                    $found_country_region = array_merge( $searched_region, $searched_country );
                    $found_country_region_having = '';
                    foreach ( $found_country_region as $country_region ) {
                        $found_country_region_having .= " OR meta_values LIKE '%$country_region%'";
                    }
                    
                        $email_query = "SELECT ID FROM $wpdb->users 
                                        WHERE user_email LIKE '%$search_on%'";
                    $email_result = $wpdb->get_col($email_query);
                    $email_rows = $wpdb->num_rows;
                    
                        //Query to get the user ids of the rows whose content matches the search text
                        $user_detail_query = "SELECT DISTINCT user_id FROM $wpdb->usermeta 
                                            WHERE meta_key IN ('first_name','last_name','wpshpcrt_usr_profile') 
                                                AND meta_value LIKE '%$search_on%'";
                        $user_detail_result = $wpdb->get_col($user_detail_query);
                        $user_detail_rows = $wpdb->num_rows;

                        //Code to merge all the user ids into a single array
                        if ($user_detail_rows > 0) {
                            for ($i=0,$j=sizeof($email_result);$i<sizeof($user_detail_result);$i++,$j++) {
                                $email_result[$j] = $user_detail_result[$i];
                            }
                        }

                        if ($email_rows > 0 || $user_detail_rows > 0) {
                        $email_query1 = "SELECT ID FROM {$wpdb->prefix}wpsc_purchase_logs 
                                        WHERE user_ID IN (". implode(",",$email_result) .")";
                        $email_result1 = $wpdb->get_col($email_query1);
                        
                        $email_search = ( !empty( $email_result1 ) ) ? " OR wtsfd.log_id IN ( " . implode( ',', $email_result1 ) . " )" : '';
                }
                
                }
                
                $customer_details_query_select = "SELECT wtsfd.log_id AS log_id,
                                                            GROUP_CONCAT( wtcf.unique_name ORDER BY wtcf.id SEPARATOR '###' ) AS meta_keys,
                                                            GROUP_CONCAT( wtsfd.value ORDER BY wtsfd.form_id SEPARATOR '###' ) AS meta_values

                                                        FROM " . WPSC_TABLE_SUBMITED_FORM_DATA . " AS wtsfd
                                                                 JOIN " . WPSC_TABLE_CHECKOUT_FORMS . " AS wtcf ON ( wtcf.id = wtsfd.form_id AND wtcf.active = 1 AND wtcf.unique_name IN ('billingfirstname','billinglastname','billingaddress',
                                                                                                    'billingcity','billingstate','billingcountry','billingpostcode',
                                                                                                    'billingemail','billingphone') )
                                                            WHERE log_id IN (". implode(",",$max_id).")
                                                        GROUP BY log_id";
                
                if ( !empty( $search_on ) ) {
                    $customer_details_query_having = " HAVING meta_values LIKE '%$search_on%'
                                                              $found_country_region_having
                                                              $email_search
                                                                  $total_search    
                                                     ";
                } else {
                    $customer_details_query_having = '';
                }
                
                    $order_by = " ORDER BY FIND_IN_SET(log_id,'". implode(",",$max_id)."') $limit_string";
                    
                    $full_customer_details_query = $customer_details_query_select . $customer_details_query_having . $order_by;
                $customer_details_results = $wpdb->get_results( $full_customer_details_query, 'ARRAY_A' );
                                                 
                    if (is_null($customer_details_results)) {
                        $full_customer_details_query = $customer_details_query_select . $order_by;
                        $customer_details_results = $wpdb->get_results( $full_customer_details_query, 'ARRAY_A' );
                    }
                
                if ( !$is_export ) {
                    $customers_count_result = $wpdb->get_results ( substr( $full_customer_details_query, 0, strpos( $full_customer_details_query, 'LIMIT' ) ),'ARRAY_A');
                    $num_records = count( $customers_count_result ); 
                } else {
                    $num_records = count( $customer_details_results );
                }

                //Code to get all the users along with their id and email in an array
                $query = "SELECT users.ID,users.user_email, GROUP_CONCAT(usermeta.meta_value 
                                         ORDER BY usermeta.umeta_id SEPARATOR '###' ) AS name
                            FROM $wpdb->users AS users
                                JOIN $wpdb->usermeta  AS usermeta ON usermeta.user_id = users.id
                            WHERE usermeta.meta_key IN ('first_name','last_name','wpshpcrt_usr_profile')
                            GROUP BY users.id DESC";
                $reg_user = $wpdb->get_results ($query ,'ARRAY_A');
                       
                for ($i=0;$i<sizeof($reg_user);$i++) {
                    $user_email[$reg_user[$i]['ID']] = $reg_user[$i]['user_email'];
                    $name = explode ("###",$reg_user[$i]['name']);
                    $user_fname[$reg_user[$i]['ID']] = $name[0];
                    $user_lname[$reg_user[$i]['ID']] = $name[1];
                    
                    if (!(is_null($name[2]))) {
                        $unserialized_detail = unserialize($name[2]); 
                        
                        $user_add[$reg_user[$i]['ID']]      = $unserialized_detail[4];
                        $user_city[$reg_user[$i]['ID']]     = $unserialized_detail[5];
                        $user_region[$reg_user[$i]['ID']]   = $unserialized_detail[6];
                        $user_country[$reg_user[$i]['ID']]  = $unserialized_detail[7][0];
                        $user_pcode[$reg_user[$i]['ID']]    = $unserialized_detail[8];
                        $user_phone[$reg_user[$i]['ID']]    = $unserialized_detail[18];
                }
                    
                }
                
                $country_result = $wpdb->get_results( "SELECT isocode,country FROM " . WPSC_TABLE_CURRENCY_LIST ,'ARRAY_A');
                $country_rows = $wpdb->num_rows;
                
                if ($country_rows > 0) {
                    for ($i=0;$i<sizeof($country_result);$i++) {
                        $country[$country_result[$i]['isocode']] = $country_result[$i]['country'];
                    }
                }
                
                if ($num_records == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = __( 'No Records Found', 'smart-manager' );
		} else {
				
			foreach ( $customer_details_results as $result ) {

                                        $meta_keys = explode( '###', $result['meta_keys'] );
                                        $meta_values = explode( '###', $result['meta_values'] );
                                        if ( count( $meta_keys ) == count( $meta_values ) ) {
                                            $customer_detail_data[$result['log_id']] = array_combine( $meta_keys, $meta_values );
                                        }

                                        $result['last_order_id'] =  $result['log_id'];
                                        $result['Last_Order'] = $order_date[$result['log_id']];
                                        $result['_order_total'] = $last_order_total[$result['log_id']];
                                        $result['count_orders']= $count_orders[$result['log_id']];
                                        $result['total_orders']= $total_orders[$result['log_id']];
                            
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
                                        
                                        //Code to get the email for reg users from wp_users table
                                        if ($result['id'] > 0) {
                                            $result['email']  = $user_email[$result['id']];
                                            $billing_user_details ['billingemail']      = $user_email[$result['id']];
                                            
                                            if(!(empty($user_fname[$result['id']]))) {
                                            $billing_user_details ['billingfirstname']  = $user_fname[$result['id']];
                                            }
                                            if(!(empty($user_lname[$result['id']]))) {
                                            $billing_user_details ['billinglastname']   = $user_lname[$result['id']];
                                            }
                                            
                                            $billing_user_details ['billingaddress']    = $user_add[$result['id']];
                                            $billing_user_details ['billingcity']       = $user_city[$result['id']];
                                            $billing_user_details ['billingstate']      = ( !empty( $regions_ids[$user_region[$result['id']]] ) ) ? $regions_ids[$user_region[$result['id']]] : $user_region[$result['id']];
                                            $billing_user_details ['billingcountry']    = $country[$user_country[$result['id']]];
                                            $billing_user_details ['billingpostcode']   = $user_pcode[$result['id']];
                                            $billing_user_details ['billingphone']      = $user_phone[$result['id']];
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

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'state') {

        global $current_user , $wpdb;

        $state_nm = array("dashboardcombobox", "Products", "Customers", "Orders","incVariation");
        
        for ($i=0;$i<sizeof($state_nm);$i++) {
            $stateid = "_sm_wpsc_".$current_user->user_email."_".$state_nm[$i];
        
            $query_state  = "SELECT option_value FROM {$wpdb->prefix}options WHERE option_name like '$stateid'";
            $result_state =  $wpdb->get_col ( $query_state );
            $rows_state   = $wpdb->num_rows;
            
            if ($rows_state > 0) {
            
                if ($_POST ['op'] == 'get' ) {
                    $state[$state_nm[$i]] = $result_state[0];
                }
                elseif ($_POST ['op'] == 'set') {
                    $state_apply = $_POST[$state_nm[$i]];
                    $query_state = "UPDATE {$wpdb->prefix}options SET option_value = '$state_apply' WHERE option_name = '$stateid'";
                    $result_state =  $wpdb->query ( $query_state );
//                    $state = $_POST['state'];
                }

            }
            else {
                
                $state_apply = $_POST[$state_nm[$i]];
                
                $query_state = "INSERT INTO {$wpdb->prefix}options (option_name,option_value) values ('$stateid','$state_apply')";
                $result_state =  $wpdb->query ( $query_state );
                
                $state[$state_nm[$i]] = $state_apply;
            }
        }
        if ($_POST ['op'] == 'get' ) {   
            echo json_encode ($state);
        }
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
                                $columns_header['billingphone'] 			= __('Billing Phone Number', $sm_domain);
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

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'dupData') {
    
    require_once (WP_PLUGIN_DIR . '/wp-e-commerce/wpsc-admin/admin.php');

    $dupCnt = 0;
    $activeModule = substr( $_POST ['active_module'], 0, -1 );
    $data_temp = json_decode ( stripslashes ( $_POST ['data'] ) );

    // Function to Duplicate the Product
    function duplicate_product ($strtCnt, $dupCnt, $data, $msg, $count, $per, $perval) {
        $post_data = array();

        for ($i = $strtCnt; $i < $dupCnt; $i ++) {
            $post_id = $data [$i];
            $post = get_post ( $post_id );
            if ($post->post_parent == 0) {
                $post_data [] = wpsc_duplicate_product_process($post);
            }
            else{
                $post_data [] = $data [$i];
            }
        }
        $duplicate_count = count ( $post_data );

        if ($duplicate_count == $count) {
            $result = true;
        }
        else{
            $result = false;
        }
        
        if ($result == true) {
                $encoded ['msg'] = $msg;
                $encoded ['dupCnt'] = $dupCnt;
                $encoded ['nxtreq'] = $_POST ['part'];
                $encoded ['per'] = $per;
                $encoded ['val'] = $perval;
        }
        elseif ($result == false) {
                $encoded ['msg'] = $activeModule . __('s were not duplicated','smart-manager');
        }
        echo json_encode ( $encoded );
    }

    /*Code to handle the First AJAX request used to calculate the 
        number of ajax request that needs to be prepared based on the 
        number of selected products*/
    if (isset ( $_POST ['part'] ) && $_POST ['part'] == 'initial') {

        //Code for getting the number of parent products for the dulplication of entire store
        if ( $_POST ['menu'] == 'store') {
            $query="SELECT id from {$wpdb->prefix}posts WHERE post_type='wpsc-product' AND post_status IN ('publish', 'draft')";
            $data_dup = $wpdb->get_col ( $query );
        }
        else{
            if ($_POST ['incvariation'] == true) {
                $query="SELECT id from {$wpdb->prefix}posts WHERE post_type='wpsc-product' AND post_status IN ('publish', 'draft')";
                $parent_ids = $wpdb->get_col ( $query );

                for ($i=0;$i<sizeof($parent_ids);$i++) {
                    $id[$parent_ids[$i]] = 'simple';
                }

                for ($i=0,$j=0;$i<sizeof($data_temp);$i++) {
                    if (isset($id[$data_temp[$i]])) {
                       $data_dup[$j] = $data_temp[$i];
                       $j++;
                    }
                }
            }
            else{
                $data_dup = $data_temp;
            }
        }
        $dupCnt = count ( $data_dup );

        if ($dupCnt > 20) {
            for ($i=0;$i<$dupCnt;) {
                $count_dup ++;
                $i = $i+20;
            }
        }
        else{
            $count_dup = 1;
        }

        $data_dup = json_encode ( $data_dup );
        $encoded['count'] = $count_dup;
        $encoded['dupCnt'] = $dupCnt;
        $encoded['data_dup'] = $data_dup;
        
        echo json_encode ( $encoded );
    }

    /*Code for handling the remmaing ajax request which actully calls the 
     function for duplicating the products */
    else {

        $count = $_POST ['count'];
        $data = json_decode ( stripslashes ( $_POST ['dup_data'] ) );

        $data_count = $_POST ['fdupcnt'] - $_POST ['dupcnt'];

        for ($i=1;$i<=$count;$i++) {
            if (isset ( $_POST ['part'] ) && $_POST ['part'] == $i) {
                $per = intval(($_POST ['part']/$count)*100); // Calculating the percentage for the display purpose
                $perval = $per/100;

                if ($per == 100) {
                    $dupCnt = $_POST['total_records'];
                    if ($data_count == 1) {
                        $msg = $dupCnt . " " . $activeModule . __(' Duplicated Successfully','smart-manager');
                    }
                    else if ($data_count == 0) {
                        $msg = "Sorry! Variations Cannot be Duplicated";
                    }
                    else if ($_POST ['menu'] == 'store') {
                        $msg = "Store Duplicated Successfully";
                    }
                    else{
                        $msg = $dupCnt . " " . $activeModule . __('s Duplicated Successfully','smart-manager');
                    }
                }
                else{
                    $msg = $per . "% Duplication Completed";
                }
                duplicate_product ($_POST ['dupcnt'], $_POST ['fdupcnt'], $data, $msg, $data_count, $per,$perval);
                break;
            }
        }
    }
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
				$encoded ['msg'] = "<b>" . $delCnt . "</b> " . __( 'Product Deleted Successfully', 'smart-manager' ); 
				$encoded ['delCnt'] = $delCnt;
			} else {
				$encoded ['msg'] = "<b>" . $delCnt . "</b> " . __( 'Products Deleted Successfully', 'smart-manager' );
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
function update_products($post) {
	global $result, $wpdb;
        $_POST = $post;     // Fix: PHP 5.4
	$edited_object = json_decode ( ( $_POST ['edited'] ) );
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
function update_orders($post) {
    global $wpdb; // to use as global
    $_POST = $post;     // Fix: PHP 5.4
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
    
        //For encoding the string in UTF-8 Format
//        $charset = "EUC-JP, ASCII, UTF-8, ISO-8859-1, JIS, SJIS";
        $charset = ( get_bloginfo('charset') === 'UTF-8' ) ? null : get_bloginfo('charset');
        
        if (!(is_null($charset))) {
            $_POST['edited'] = mb_convert_encoding(stripslashes($_POST['edited']),"UTF-8",$charset);
        }
        else {
            $_POST['edited'] = stripslashes($_POST['edited']);
        }
    
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
				} else {
					$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __( 'Records Updated Successfully', 'smart-manager' );
                                }
			}
			
			if ($result ['inserted'] == 1) {
				if ($result ['insertCnt'] == 1) {
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> " . __( 'New Record Inserted Successfully', 'smart-manager' ); 
                                } else {
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> " . __(' New Records Inserted Successfully', 'smart-manager' );
                                }
			}
			
		}
	}
	ob_clean();
        echo json_encode ( $encoded );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getRolesDashboard') {
	global $wpdb, $current_user;

	$current_user = wp_get_current_user();
        if ( !isset( $current_user->roles[0] ) ) {
            $roles = array_values( $current_user->roles );
        } else {
            $roles = $current_user->roles;
        }
	if ( SMPRO != true || $roles[0] == 'administrator') {
		$results = array( 'Products', 'Customers_Orders' );
	} else {
		$results = get_dashboard_combo_store();
	}
	ob_clean();
        echo json_encode ( $results );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'editImage') {
	$wpsc_default_image = WP_PLUGIN_URL . '/wp-e-commerce/wpsc-theme/wpsc-images/noimage.png';

//	$post_thumbnail_id = get_post_thumbnail_id( $_POST ['id'] );
//	$image = isset( $post_thumbnail_id ) ? wp_get_attachment_image_src( $post_thumbnail_id, 'admin-product-thumbnails' ) : '';
//	$thumbnail = ( $image[0] != '' ) ? $image[0] : '';
        $image = wpsc_the_product_thumbnail( '','', $_POST ['id'], '' );
        $thumbnail    = ( $image != '' ) ? $image : '';
	ob_clean();
        echo json_encode ( $thumbnail );
}
//ob_end_flush();
?>