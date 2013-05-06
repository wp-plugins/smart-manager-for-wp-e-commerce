<?php 
ob_start();
if ( ! defined('ABSPATH') ) {
    include_once ('../../../../wp-load.php');
}

include_once (WP_PLUGIN_DIR . '/woocommerce/admin/includes/duplicate_product.php');
load_textdomain( 'smart-manager', WP_PLUGIN_DIR . '/smart-manager-for-wp-e-commerce/languages/smart-manager-' . WPLANG . '.mo' );

$mem_limit = ini_get('memory_limit');
if(intval(substr($mem_limit,0,strlen($mem_limit)-1)) < 64 ){
	ini_set('memory_limit','128M'); 
}

$result = array ();
$encoded = array ();
$data_dup;
$count_dup=0;

$offset = (isset ( $_POST ['start'] )) ? $_POST ['start'] : 0;
$limit = (isset ( $_POST ['limit'] )) ? $_POST ['limit'] : 100;

// For pro version check if the required file exists
if (file_exists ( WP_PLUGIN_DIR . '/smart-manager-for-wp-e-commerce/pro/woo.php' )) {
	define ( 'SMPRO', true );
	include_once (WP_PLUGIN_DIR . '/smart-manager-for-wp-e-commerce/pro/woo.php');
} else {
	define ( 'SMPRO', false );
}

function values( $arr ) {
    return $arr['id'];
}

// getting the active module
$active_module = (isset($_POST ['active_module']) ? $_POST ['active_module'] : 'Products');
//$active_module = $_POST ['active_module'];

function get_data_woo ( $post, $offset, $limit, $is_export = false ) {
	global $wpdb, $woocommerce, $post_status, $parent_sort_id, $order_by, $post_type, $variation_name, $from_variation, $parent_name, $attributes;
	$_POST = $post;     // Fix: PHP 5.4
        $products = array();
        
	// getting the active module
        $active_module = (isset($_POST ['active_module']) ? $_POST ['active_module'] : 'Products');
//        $active_module = $_POST ['active_module'];
	
	if ( SMPRO == true ) variation_query_params ();
	
	// Restricting LIMIT for export CSV
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

        $tax_status = array(
                                    'taxable' => __('Taxable','smart-manager'),
                                    'shipping' => __('Shipping only','smart-manager'),
                                    'none' => __('None','smart-manager')
                            );
        

		if (isset ( $_POST ['incVariation'] ) && $_POST ['incVariation'] === 'true' && SMPRO == true) {
			$show_variation = true;
		} else {
			$parent_name = '';
			$post_status = "('publish', 'draft')";
			$post_type = "('product')";
			$parent_sort_id = '';
			$order_by = " ORDER BY products.id desc";
			$show_variation = false;
		}
		
		// if max-join-size issue occurs
		$query = "SET SQL_BIG_SELECTS=1;";
		$wpdb->query ( $query );

        //Query for getting all the distinct attribute meta key names
        $query_variation = "SELECT DISTINCT meta_key as variation
                            FROM {$wpdb->prefix}postmeta
                            WHERE meta_key like 'attribute_%'";
        $variation = $wpdb->get_col ($query_variation);

        //Query to get all the distinct term names along with their slug names
        $query = "SELECT terms.slug as slug, terms.name as term_name FROM {$wpdb->prefix}terms AS terms
					JOIN {$wpdb->prefix}postmeta AS postmeta ON ( postmeta.meta_value = terms.slug AND postmeta.meta_key LIKE 'attribute_%' ) GROUP BY terms.slug";
        $attributes_terms = $wpdb->get_results( $query, 'ARRAY_A' );

        $attributes = array();
        foreach ( $attributes_terms as $attributes_term ) {
            $attributes[$attributes_term['slug']] = $attributes_term['term_name'];
        }

        //Query to get the term_taxonomy_id for all the product categories
        $query_terms = "SELECT terms.name, wt.term_taxonomy_id FROM {$wpdb->prefix}term_taxonomy AS wt
                        JOIN {$wpdb->prefix}terms AS terms ON (wt.term_id = terms.term_id)
                        WHERE wt.taxonomy like 'product_cat'";
        $results = $wpdb->get_results( $query_terms, 'ARRAY_A' );
        $rows_terms = $wpdb->num_rows;
        
        if ( !empty( $results ) ) {
            for ($i=0;$i<sizeof($results);$i++) {
                $term_taxonomy_id [$i] = $results [$i]['term_taxonomy_id'];
                $term_taxonomy[$results [$i]['term_taxonomy_id']] = $results [$i]['name']; 
            }

            //Imploding the term_taxonomy_id to be used in the main query of the products module
            $term_taxonomy_id_query = "AND wtr.term_taxonomy_id IN (" . implode (",",$term_taxonomy_id) . ")";
        } else {
            $term_taxonomy_id_query = '';
        }
        $results_trash = array();
        
        //Code to get the ids of all the products whose post_status is thrash
        $query_trash = "SELECT ID FROM {$wpdb->prefix}posts 
                        WHERE post_status = 'trash'
                            AND post_type IN ('product')";
        $results_trash = $wpdb->get_col( $query_trash );
        $rows_trash = $wpdb->num_rows;
        
        $query_deleted = "SELECT distinct products.post_parent 
                            FROM {$wpdb->prefix}posts as products 
                            WHERE NOT EXISTS (SELECT * FROM {$wpdb->prefix}posts WHERE ID = products.post_parent) 
                              AND products.post_parent > 0 
                              AND products.post_type = 'product_variation'";
        $results_deleted = $wpdb->get_col( $query_deleted );
        $rows_deleted = $wpdb->num_rows;
        
        for ($i=sizeof($results_trash),$j=0;$j<sizeof($results_deleted);$i++,$j++ ) {
            $results_trash[$i] = $results_deleted[$j];
        }
        
        
        if ($rows_trash > 0 || $rows_deleted > 0) {
            $trash_id = " AND products.post_parent NOT IN (" .implode(",",$results_trash). ")";
        }
        else {
            $trash_id = "";
        }
        
        $select = "SELECT SQL_CALC_FOUND_ROWS products.id,
					products.post_title,
					products.post_content,
					products.post_excerpt,
					products.post_status,
					products.post_parent,
					GROUP_CONCAT(distinct wtr.term_taxonomy_id order by wtr.object_id SEPARATOR '###') AS term_taxonomy_id,
					GROUP_CONCAT(prod_othermeta.meta_key order by prod_othermeta.meta_id SEPARATOR '###') AS prod_othermeta_key,
					GROUP_CONCAT(prod_othermeta.meta_value order by prod_othermeta.meta_id SEPARATOR '###') AS prod_othermeta_value
					$parent_sort_id";

        //Used as an alternative to the SQL_CALC_FOUND_ROWS function of MYSQL Database
        $select_count = "SELECT COUNT(*) as count"; // To get the count of the number of rows generated from the above select query

        $search = "";
        
        if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
            $search_on = trim ( $_POST ['searchText'] );

            $search = "";

            
            $product_type = wp_get_object_terms($records[$i]['id'], 'product_type', array('fields' => 'slugs'));
            
            
            
            
            $count_all_double_quote = substr_count( $search_on, '"' );
            if ( $count_all_double_quote > 0 ) {
                $search_ons = array_filter( array_map( 'trim', explode( $wpdb->_real_escape( '"' ), $search_on ) ) );
                $search_on  = implode(",", $search_ons);
                
            } else {
                $search_on = $wpdb->_real_escape( $search_on );
                $search_ons = explode( ' ', $search_on );
            }

            //Function to prepare the conditions for the query
            function prepare_cond($search_ons,$column_nm) {
                $cond = "";
                foreach ($search_ons as $search_on) {
                    $cond .= $column_nm . " LIKE '%" . $search_on . "%'";
                    $cond .= " OR ";
                }
                return substr( $cond, 0, -3 );
            };
            
            //Query for getting the slug name for the term name typed in the search box of the products module
            $query_terms = "SELECT slug FROM {$wpdb->prefix}terms WHERE (". prepare_cond($search_ons,"name") .") AND name IN ('" .implode("','",$attributes) . "');";
            $records_slug = $wpdb->get_col ( $query_terms );
            $rows = $wpdb->num_rows;
            
            $search_text = $search_ons;
            
//            if($rows > 0){
            if($rows > 0 && (!empty($records_slug))){
                    $search_text = $records_slug;
            }
            
            //Query to get the term_taxonomy_id for the category name typed in the search text box of the products module
            $query_category = "SELECT tr.object_id FROM {$wpdb->prefix}term_relationships AS tr
                    JOIN {$wpdb->prefix}term_taxonomy AS wt ON (wt.term_taxonomy_id = tr.term_taxonomy_id)
                    JOIN {$wpdb->prefix}terms AS terms ON (wt.term_id = terms.term_id)
                    WHERE wt.taxonomy like 'product_cat'
                    AND (". prepare_cond($search_ons,"terms.name") . ")";
            $results_category = $wpdb->get_col( $query_category );
            $rows_category = $wpdb->num_rows;
                
            if ($rows_category > 0) {
                $search_category = " OR products.ID IN (" .implode(",",$results_category). ") OR products.post_parent IN (" .implode(",",$results_category). ")";
            }
            else {
                $search_category = "";
            }
            
            //Query to get the post id if title or status or content or excerpt matches
            $query_title = "SELECT ID FROM {$wpdb->prefix}posts 
                        WHERE post_type IN ('product')
                            AND (". prepare_cond($search_ons,"post_title").
                                    "OR ". prepare_cond($search_ons,"post_status"). 
                                    "OR ". prepare_cond($search_ons,"post_content"). 
                                    "OR ". prepare_cond($search_ons,"post_excerpt") .")";
            $results_title = $wpdb->get_col( $query_title );
            $rows_title = $wpdb->num_rows;
            
            if ($rows_title > 0) {
                $search_title = " OR products.ID IN (" .implode(",",$results_title). ") OR products.post_parent IN (" .implode(",",$results_title). ")";
            }
            else {
                $search_title = "";
            }
            
            $visible = stristr("Catalog & Search", $search_on);
            
            
            if ($visible === FALSE) {
                $query_tax_visible = "SELECT post_id FROM {$wpdb->prefix}postmeta 
                        WHERE meta_key IN ('_tax_status','_visibility')
                            AND meta_value LIKE '%$search_on%'";
            }
            else {
                if(count( $search_ons ) > 1) {
                    $query_tax_visible = "SELECT post_id FROM {$wpdb->prefix}postmeta 
                            WHERE meta_key IN ('_visibility')
                                AND (meta_value LIKE '%visible%')";
                }
                else {
                    $query_tax_visible = "SELECT post_id FROM {$wpdb->prefix}postmeta 
                            WHERE meta_key IN ('_visibility')
                                AND (meta_value LIKE '%visible%'
                                    OR meta_value LIKE '%$search_on%')";
                }
            }
            
            $results_tax_visible = $wpdb->get_col( $query_tax_visible );
            $rows_tax_visible = $wpdb->num_rows;
            
            
            if ($rows_tax_visible > 0) {
                $search_tax_visible = " OR products.ID IN (" .implode(",",$results_tax_visible). ") OR products.post_parent IN (" .implode(",",$results_tax_visible). ")";
            }
            else {
                $search_tax_visible = "";
            }
            
            
            if ( is_array( $search_ons ) && count( $search_ons ) >= 1 ) {
            			$search_condn = " HAVING ";                    
				foreach ( $search_ons as $search_on ) {
                                    $search_condn .= " (concat(' ',REPLACE(REPLACE(post_title,'(',''),')','')) LIKE '%$search_on%'
                                                           OR post_content LIKE '%$search_on%'
                                                           OR post_excerpt LIKE '%$search_on%'

                                                               OR prod_othermeta_value LIKE '%$search_on%')

                                                               ";
                                    $search_condn .= " OR";
				}
                                
                                if( $rows == 1 ) {
                                    $query_ids1 = "SELECT GROUP_CONCAT(post_id ORDER BY post_id SEPARATOR ',') as id FROM {$wpdb->prefix}postmeta WHERE meta_value IN ('". implode("','",$search_text) ."') AND meta_key like 'attribute_%'";
                                    $records_id1 = implode(",",$wpdb->get_col ( $query_ids1 ));

                                    $search_condn .= " products.id IN ($records_id1)";
                                    $search_condn .= " OR";
                                }
                                $search_condn_count = " AND(" . substr( $search_condn_count, 0, -2 ) . ")";
				$search_condn = substr( $search_condn, 0, -2 );
				$search_condn .= $search_title . $search_category .$search_tax_visible;
                                
			} 
                        else {
                            $search_condn = " HAVING concat(' ',REPLACE(REPLACE(post_title,'(',''),')','')) LIKE '%$search_on%'
                                                   OR post_content LIKE '%$search_on%'
                                                   OR post_excerpt LIKE '%$search_on%'

                                                               OR prod_othermeta_value LIKE '%$search_on%'

                                                        $search_title
                                                        $search_category
                                                        $search_tax_visible
                                                       ";
                            if( $rows == 1 ) {
                                $query_ids1 = "SELECT GROUP_CONCAT(post_id ORDER BY post_id SEPARATOR ',') as id FROM {$wpdb->prefix}postmeta WHERE meta_value IN ('". implode("','",$search_text) ."') AND meta_key like 'attribute_%';";
                                $records_id1 = implode(",",$wpdb->get_col ( $query_ids1 ));
                                
                                if ( !empty( $records_id1 ) ) 
                                    $search_condn .= " OR products.id IN ($records_id1)";
                            }
                        }
		} 

		$from = "FROM {$wpdb->prefix}posts as products
						JOIN {$wpdb->prefix}postmeta as prod_othermeta ON (prod_othermeta.post_id = products.id and
						prod_othermeta.meta_key IN ('_regular_price','_sale_price','_sale_price_dates_from','_sale_price_dates_to','_sku','_stock','_weight','_height','_length','_width','_price','_thumbnail_id','_tax_status','_min_variation_regular_price','_min_variation_sale_price','_visibility','" . implode( "','", $variation ) . "') )
						
						LEFT JOIN {$wpdb->prefix}term_relationships as wtr ON (products.id = wtr.object_id
                                                            $term_taxonomy_id_query)";  // Remove $term_taxonomy_id_query
												
		$where	= " WHERE products.post_status IN $post_status
						AND products.post_type IN $post_type
                                                $trash_id
                                                $search";

		$group_by = " GROUP BY products.id ";

        //Query for getting the actual data loaded into the smartManager
        $query = "$select $from $where $group_by $search_condn $order_by $limit_string;";
		$records = $wpdb->get_results ( $query, 'ARRAY_A' );
		$num_rows = $wpdb->num_rows;
                
        //Query for getting the count of the number of products loaded into the smartManager
        $recordcount_result = $wpdb->get_results ( 'SELECT FOUND_ROWS() as count;','ARRAY_A');
        $num_records = $recordcount_result[0]['count'];

        if ($num_rows <= 0) {
            $encoded ['totalCount'] = '';
            $encoded ['items'] = '';
            $encoded ['msg'] = __('No Records Found', 'smart-manager');
        } else {

            for ($i = 0; $i < $num_rows; $i++) {

                $records[$i]['post_content'] = str_replace('"','\'',$records[$i]['post_content']);
                $records[$i]['post_excerpt'] = str_replace('"','\'',$records[$i]['post_excerpt']);                

                $prod_meta_values = explode('###', $records[$i]['prod_othermeta_value']);
                $prod_meta_key = explode('###', $records[$i]['prod_othermeta_key']);
                if (count($prod_meta_values) != count($prod_meta_key))
                    continue;
                unset($records[$i]['prod_othermeta_value']);
                unset($records[$i]['prod_othermeta_key']);
                $prod_meta_key_values = array_combine($prod_meta_key, $prod_meta_values);
                $product_type = wp_get_object_terms($records[$i]['id'], 'product_type', array('fields' => 'slugs'));

                // Code to get the Category Name from the term_taxonomy_id
                $category_id = explode('###', $records[$i]['term_taxonomy_id']);
                $category_names = "";
                unset($records[$i]['term_taxonomy_id']);

                for ($j = 0; $j < sizeof($category_id); $j++) {
                    if (isset($term_taxonomy[$category_id[$j]])) {
                        $category_names .=$term_taxonomy[$category_id[$j]] . ', ';
                    }
                }
                if ($category_names != "") {
                    $category_names = substr($category_names, 0, -2);
                    $records[$i]['category'] = $category_names;
                }

                $records[$i]['category'] = ( ( $records[$i]['post_parent'] > 0 && $product_type[0] == 'simple' ) || ( $records[$i]['post_parent'] == 0 ) ) ? $records[$i]['category'] : '';   // To hide category name from Product's variations

                if (isset($prod_meta_key_values['_sale_price_dates_from']) && !empty($prod_meta_key_values['_sale_price_dates_from']))
                    $prod_meta_key_values['_sale_price_dates_from'] = date('Y-m-d', (int) $prod_meta_key_values['_sale_price_dates_from']);
                if (isset($prod_meta_key_values['_sale_price_dates_to']) && !empty($prod_meta_key_values['_sale_price_dates_to']))
                    $prod_meta_key_values['_sale_price_dates_to'] = date('Y-m-d', (int) $prod_meta_key_values['_sale_price_dates_to']);

                $records[$i] = array_merge((array) $records[$i], $prod_meta_key_values);
                $thumbnail = isset($records[$i]['_thumbnail_id']) ? wp_get_attachment_image_src($records[$i]['_thumbnail_id'], $image_size) : '';
                $records[$i]['thumbnail'] = ( $thumbnail[0] != '' ) ? $thumbnail[0] : false;
                $records[$i]['_tax_status'] = (!empty($prod_meta_key_values['_tax_status']) ) ? $prod_meta_key_values['_tax_status'] : '';

                // Setting product type for grouped products
                if ($records[$i]['post_parent'] != 0 ) {
                    $product_type_parent = wp_get_object_terms($records[$i]['post_parent'], 'product_type', array('fields' => 'slugs'));
                        
                    if ($product_type_parent[0] == "grouped") {
                        $records[$i]['product_type'] = $product_type_parent[0];
                    }
                }
                else {
                    $records[$i]['product_type'] = $product_type[0];
                }
                
                if ($show_variation === true && SMPRO) {
                    if ( $records[$i]['post_parent'] != 0 && $product_type_parent[0] != "grouped" ) {
                        
                        $records[$i]['post_status'] = get_post_status($records[$i]['post_parent']);
                        
                        if($_POST['SM_IS_WOO16'] == "true") {
                            $records[$i]['_regular_price'] = $records[$i]['_price'];
                        }
                        $variation_names = '';

                        foreach ($variation as $slug) {
                            $variation_names .= ( isset($attributes[$prod_meta_key_values[$slug]]) && !empty($attributes[$prod_meta_key_values[$slug]]) ) ? $attributes[$prod_meta_key_values[$slug]] . ', ' : ucfirst($prod_meta_key_values[$slug]) . ', ';
                        }
                        
                        $records[$i]['post_title'] = get_the_title($records[$i]['post_parent']) . " - " . trim($variation_names, ", ");
                        
                        
                    } else if ($records[$i]['post_parent'] == 0 && $product_type[0] == 'variable') {
                        $records[$i]['_regular_price'] = "";
                        $records[$i]['_sale_price'] = "";
                    }

                    $products[$records[$i]['id']]['post_title'] = $records[$i]['post_title'];
                    $products[$records[$i]['id']]['variation'] = $variation_names;
                } elseif ($show_variation === false && SMPRO) {
                    if ($product_type[0] == 'variable') {
                        $records[$i]['_regular_price'] = $records[$i]['_min_variation_regular_price'];
                        $records[$i]['_sale_price'] = $records[$i]['_min_variation_sale_price'];
                    }
                } else {
                    $records[$i]['_regular_price'] = $records[$i]['_regular_price'];
                    $records[$i]['_sale_price'] = $records[$i]['_sale_price'];
                }

                unset($records[$i]['prod_othermeta_value']);
                unset($records[$i]['prod_othermeta_key']);
                
                
            }
            
        }
	} elseif ($active_module == 'Customers') {
		//BOF Customer's module
			if (SMPRO == true) {
				$search_condn = customers_query ( $_POST ['searchText'] );
			}

                        
                        
                 $query_terms = "SELECT id FROM {$wpdb->prefix}posts AS posts
                            JOIN {$wpdb->prefix}term_relationships AS term_relationships 
                                                        ON term_relationships.object_id = posts.ID 
                                        JOIN {$wpdb->prefix}term_taxonomy AS term_taxonomy 
                                                        ON term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id 
                                        JOIN {$wpdb->prefix}terms AS terms 
                                                        ON term_taxonomy.term_id = terms.term_id
                        WHERE terms.name IN ('completed','processing','on-hold','pending')
                            AND posts.post_status IN ('publish')";
              
                $terms_post = implode(",",$wpdb->get_col($query_terms));
                
                
            //Query for getting the max of post id for all the Guest Customers          
                
                $query_post_guest = "SELECT post_ID FROM {$wpdb->prefix}postmeta
                                WHERE meta_key ='_customer_user' AND meta_value=0
                                    AND post_id IN ($terms_post)";
                $post_id_guest = $wpdb->get_col($query_post_guest); 
                $num_guest 	 =  $wpdb->num_rows;
            

              if($num_guest > 0) {
                $query_max_id="SELECT GROUP_CONCAT(distinct postmeta1.post_ID 
                                        ORDER BY posts.post_date DESC SEPARATOR ',' ) AS all_id,
                               GROUP_CONCAT(postmeta2.meta_value 
                                             ORDER BY posts.post_date DESC SEPARATOR ',' ) AS order_total,     
                                        date_format(max(posts.post_date),'%b %e %Y, %r') AS date,
                               count(postmeta1.post_id) as count,
                               sum(postmeta2.meta_value) as total
                            
                               FROM {$wpdb->prefix}postmeta AS postmeta1
                                            JOIN {$wpdb->prefix}posts AS posts ON (posts.ID = postmeta1.post_id)
                                   INNER JOIN {$wpdb->prefix}postmeta AS postmeta2
                                       ON (postmeta2.post_ID = postmeta1.post_ID AND postmeta2.meta_key IN ('_order_total'))

                               WHERE postmeta1.meta_key IN ('_billing_email')
                                        AND postmeta1.post_ID IN (". implode(",",$post_id_guest) . ")                           
                               GROUP BY postmeta1.meta_value
                                   ORDER BY date desc";

                $result_max_id   =  $wpdb->get_results ( $query_max_id, 'ARRAY_A' );
              }
            
            //Query for getting the max of post id for all the Registered Customers
            $query_post_user = "SELECT post_ID FROM {$wpdb->prefix}postmeta
                                WHERE meta_key ='_customer_user' AND meta_value>0
                                AND post_id IN ($terms_post)
                                AND meta_value IN (SELECT id FROM $wpdb->users)";
            $post_id_user = $wpdb->get_col($query_post_user);                        
            $num_user 	 =  $wpdb->num_rows;            

            
            if($num_user > 0) {
            $query_max_user="SELECT GROUP_CONCAT(distinct postmeta1.post_ID 
                                    ORDER BY posts.post_date DESC SEPARATOR ',' ) AS all_id,
                           GROUP_CONCAT(postmeta2.meta_value 
                                         ORDER BY posts.post_date DESC SEPARATOR ',' ) AS order_total,     
                                    date_format(max(posts.post_date),'%b %e %Y, %r') AS date,
                           count(postmeta1.post_id) as count,
                           sum(postmeta2.meta_value) as total
                           
                           FROM {$wpdb->prefix}postmeta AS postmeta1
                                    JOIN {$wpdb->prefix}posts AS posts ON (posts.ID = postmeta1.post_id)
                               INNER JOIN {$wpdb->prefix}postmeta AS postmeta2
                                   ON (postmeta2.post_ID = postmeta1.post_ID AND postmeta2.meta_key IN ('_order_total'))
                                                        
                           WHERE postmeta1.meta_key IN ('_customer_user')
                                     AND postmeta1.post_ID IN (" . implode(",",$post_id_user) . ")                           
                           GROUP BY postmeta1.meta_value
                                ORDER BY date";

            $result_max_user   =  $wpdb->get_results ( $query_max_user , 'ARRAY_A' );
            }
            

            //Code for generating the total orders, count of orders , max ids and last order total arrays
            for ($i=0;$i<sizeof($result_max_id);$i++) {
                
                $temp = explode (",",$result_max_id[$i]['all_id']);
                $max_ids[$i] = $temp[0];
                
                $order_count[$max_ids[$i]] = $result_max_id[$i]['count'];
                $order_total[$max_ids[$i]] = $result_max_id[$i]['total'];
                
                //Code for getting the last Order Total
                $temp = explode (",",$result_max_id[$i]['order_total']);
                $last_order_total[$max_ids[$i]] = $temp[0];
                
            }

            if (!empty($result_max_id)) {
                $j=sizeof($max_ids);
                $k=sizeof($order_count);
                $l=sizeof($order_total);
                $m=sizeof($last_order_total);    
            }
            
            
            for ( $i=0;$i<sizeof($result_max_user);$i++,$j++,$k++,$l++,$m++ ) {
                
                $temp = explode (",",$result_max_user[$i]['all_id']);
                $max_ids[$j] = $temp[0];
                $order_count[$max_ids[$j]] = $result_max_user[$i]['count'];
                $order_total[$max_ids[$j]] = $result_max_user[$i]['total'];
                
                $temp = explode (",",$result_max_user[$i]['order_total']);
                $last_order_total[$max_ids[$j]] = $temp[0];
                
            }
            
            $max_id = implode(",",$max_ids);


            $customers_query = "SELECT SQL_CALC_FOUND_ROWS
                                     DISTINCT(GROUP_CONCAT( postmeta.meta_value
                                     ORDER BY postmeta.meta_id SEPARATOR '###' ) )AS meta_value,
                                     GROUP_CONCAT(distinct postmeta.meta_key
                                     ORDER BY postmeta.meta_id SEPARATOR '###' ) AS meta_key,
                                     date_format(max(posts.post_date),'%b %e %Y, %r') AS date,
                                     posts.ID AS id

                                    FROM {$wpdb->prefix}posts AS posts
                                            RIGHT JOIN {$wpdb->prefix}postmeta AS postmeta
                                                    ON (posts.ID = postmeta.post_id AND postmeta.meta_key IN
                                                                                        ('_billing_first_name' , '_billing_last_name' , '_billing_email',
                                                                                        '_billing_address_1', '_billing_address_2', '_billing_city', '_billing_state',
                                                                                        '_billing_country','_billing_postcode', '_billing_phone','_customer_user'))";


			$where = " WHERE posts.post_type LIKE 'shop_order' 
					   AND posts.post_status IN ('publish')
					   AND posts.ID IN ($max_id)";
			
			$group_by    = " GROUP BY posts.ID";
					
			$limit_query = " ORDER BY FIND_IN_SET(posts.ID,'$max_id') $limit_string";
			
		$query    	 = "$customers_query $where $group_by $search_condn $limit_query;";
		$result   	 =  $wpdb->get_results ( $query, 'ARRAY_A' );
		$num_rows 	 =  $wpdb->num_rows;
		
		//To get Total count
		$customers_count_result = $wpdb->get_results ( 'SELECT FOUND_ROWS() as count;','ARRAY_A');
		$num_records = $customers_count_result[0]['count'];

		if ($num_records == 0) {
			$encoded ['totalCount'] = '';
			$encoded ['items'] = '';
			$encoded ['msg'] = __('No Records Found','smart-manager');
		} else {
            $postmeta = array();

                    $j=0;$k=0;
            for ( $i=0;$i<sizeof($result);$i++ ) {
                $meta_value = explode ( '###', $result [$i]['meta_value'] );
                $meta_key = explode ( '###', $result [$i]['meta_key'] );

                //note: while merging the array, $data as to be the second arg
                if (count ( $meta_key ) == count ( $meta_value )) {
                            $temp[$i] = array_combine ( $meta_key, $meta_value );
                }

                        if($temp[$i]['_customer_user'] == 0){
                            $postmeta[$j] = $temp[$i];
                            $j++;
                        }
                        elseif($temp[$i]['_customer_user'] > 0){
                            $user[$k] = $temp[$i]['_customer_user'];
                            $k++;
                        }

                unset($meta_value);
                unset($meta_key);
            }

                    //Query for getting th Registered Users data from wp_usermeta and wp_users table
                    if(!(is_null($user))){
                        $user_ids = implode(",",$user);
                        $query_users = "SELECT users.ID,users.user_email,
                                              GROUP_CONCAT( usermeta.meta_value ORDER BY usermeta.umeta_id SEPARATOR '###' ) AS meta_value,
                                             GROUP_CONCAT(distinct usermeta.meta_key
                                             ORDER BY usermeta.umeta_id SEPARATOR '###_' ) AS meta_key
                                             FROM $wpdb->users AS users
                                                   JOIN $wpdb->usermeta AS usermeta
                                                            ON (users.ID = usermeta.user_id AND usermeta.meta_key IN
                                                            ('billing_first_name' , 'billing_last_name' , 'billing_email',
                                                            'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state',
                                                            'billing_country','billing_postcode', 'billing_phone'))
                                             WHERE users.ID IN ($user_ids)
                                             GROUP BY users.ID
                                             ORDER BY FIND_IN_SET(users.ID,'$user_ids');";

                    $result_users   =  $wpdb->get_results ( $query_users, 'ARRAY_A' );
                    $num_rows_users =  $wpdb->num_rows;

                    for ( $i=0,$j=sizeof($postmeta);$i<sizeof($result_users);$i++,$j++ ) {

                        $meta_value = explode ( '###', $result_users [$i]['meta_value'] );

                        $result_users [$i]['meta_key']="_" . $result_users [$i]['meta_key'];
                        $meta_key =  explode ( '###', $result_users [$i]['meta_key'] );


                        //note: while merging the array, $data as to be the second arg
                        if (count ( $meta_key ) == count ( $meta_value )) {
                            $postmeta[$j] = array_combine ( $meta_key, $meta_value );
                            $postmeta[$j]['_customer_user'] = $result_users [$i]['ID'];
                            $postmeta[$j]['_billing_email'] = $result_users [$i]['user_email'];
                        }

                        unset($meta_value);
                        unset($meta_key);
                    }
            }

            $user_id=array();
            for ( $i=0;$i<sizeof($postmeta);$i++ ){
                if($postmeta[$i]['_customer_user'] == 0){
                    $user_email[$i]="'" . $postmeta[$i]['_billing_email'] . "'";
            }
                elseif($postmeta[$i]['_customer_user'] > 0){
                    $user_id[$i] = $postmeta[$i]['_customer_user'];
                }
            }

            for ( $i=0; $i<sizeof($postmeta);$i++ ) {

                $postmeta [$i] ['id']           = $max_ids[$i];
                

                if (SMPRO == true) {
                    $result [$i] ['_order_total']   = $last_order_total[$result [$i] ['id']];
                    $postmeta [$i] ['count_orders'] = $order_count[$result [$i] ['id']];
                    $postmeta [$i] ['total_orders'] = $order_total[$result [$i] ['id']];
                    $result [$i] ['last_order'] = $result [$i] ['date']/* . ', ' . $data ['Last_Order_Amt']*/;
                }else{
                    $postmeta [$i] ['count_orders'] = 'Pro only';
                    $postmeta [$i] ['total_orders'] = 'Pro only';
                    $result [$i] ['_order_total'] = 'Pro only';
                    $result [$i] ['last_order'] = 'Pro only';
                }
                $result [$i] ['_billing_address'] = isset($postmeta [$i] ['_billing_address_1']) ? $postmeta [$i] ['_billing_address_1'].', '.$postmeta [$i] ['_billing_address_2'] : $postmeta [$i] ['_billing_address_2'];
                $postmeta [$i] ['_billing_state'] = isset($woocommerce->countries->states[$postmeta [$i] ['_billing_country']][$postmeta [$i] ['_billing_state']]) ? $woocommerce->countries->states[$postmeta [$i] ['_billing_country']][$postmeta [$i] ['_billing_state']] : $postmeta [$i] ['_billing_state'];
                $postmeta [$i] ['_billing_country'] = isset($woocommerce->countries->countries[$postmeta [$i] ['_billing_country']]) ? $woocommerce->countries->countries[$postmeta [$i] ['_billing_country']] : $postmeta [$i] ['_billing_country'];
                unset($result [$i] ['date']);
                unset($result [$i] ['meta_key']);
                unset($result [$i] ['meta_value']);
                unset($postmeta [$i] ['_billing_address_1']);
                unset($postmeta [$i] ['_billing_address_2']);
                //NOTE: storing old email id in an extra column in record so useful to indentify record with emailid during updates.
                if ($postmeta [$i] ['_billing_email'] != '' || $postmeta [$i] ['_billing_email'] != null) {
                    $records [] = array_merge ( $postmeta [$i], $result [$i] );
                }

            }
        }

        unset($result);
        unset($postmeta);

	} elseif ($active_module == 'Orders') {
            
                //Code to get all the term_names along with the term_taxonomy_id in an array
                $query_terms = "SELECT terms.name,term_taxonomy.term_taxonomy_id 
                                FROM {$wpdb->prefix}term_taxonomy AS term_taxonomy
                                    JOIN {$wpdb->prefix}terms AS terms ON terms.term_id = term_taxonomy.term_id
                                WHERE taxonomy LIKE 'shop_order_status'";
              
                $terms = $wpdb->get_results ( $query_terms,'ARRAY_A');
                

                
                
                for ($i=0;$i<sizeof($terms);$i++) {
                    $terms_name[$terms[$i]['term_taxonomy_id']] = $terms[$i]['name'];
                    $terms_id[$i] = $terms[$i]['term_taxonomy_id'];
                }
                
                $terms_post = implode(",",$terms_id);
                
		$select_query = "SELECT SQL_CALC_FOUND_ROWS posts.ID as id,
                                                                posts.post_excerpt as order_note,
								date_format(posts.post_date,'%b %e %Y, %r') AS date,
								GROUP_CONCAT( postmeta.meta_value 
								ORDER BY postmeta.meta_id
								SEPARATOR '###' ) AS meta_value,
								GROUP_CONCAT(distinct postmeta.meta_key
								ORDER BY postmeta.meta_id 
								SEPARATOR '###' ) AS meta_key,
								term_relationships.term_taxonomy_id AS term_taxonomy_id
							
							FROM {$wpdb->prefix}posts AS posts 
									JOIN {$wpdb->prefix}term_relationships AS term_relationships 
											ON term_relationships.object_id = posts.ID 
									RIGHT JOIN {$wpdb->prefix}postmeta AS postmeta 
											ON (posts.ID = postmeta.post_id AND postmeta.meta_key IN 
																				('_billing_first_name' , '_billing_last_name' , '_billing_email',
																				'_shipping_first_name', '_shipping_last_name', '_shipping_address_1', '_shipping_address_2',
																				'_shipping_city', '_shipping_state', '_shipping_country','_shipping_postcode',
																				'_shipping_method', '_payment_method', '_order_items', '_order_total',
																				'_shipping_method_title', '_payment_method_title','_customer_user','_billing_phone',
                                                                                                                                                                '_order_shipping', '_order_discount', '_cart_discount', '_order_tax', '_order_shipping_tax', '_order_currency', 'coupons'))";
			
			$group_by    = " GROUP BY posts.ID";
			$limit_query = " ORDER BY posts.ID DESC $limit_string ;";
			
			$where = " WHERE posts.post_type LIKE 'shop_order' 
					AND posts.post_status IN ('publish','draft','auto-draft')
                                        AND term_relationships.term_taxonomy_id IN ($terms_post)";
			
			if (isset ( $_POST ['fromDate'] )) {
                                
                                $from_date = date('Y-m-d H:i:s',(int)strtotime($_POST ['fromDate']));
                                
                                $date = date('Y-m-d',(int)strtotime($_POST ['toDate']));
                                $curr_time_gmt = date('H:i:s',time()- date("Z"));
                                $new_date = $date ." " . $curr_time_gmt;
                                $to_date = date('Y-m-d H:i:s',((int)strtotime($new_date)) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )) ;
                                
                                if (SMPRO == true) {
					$where .= " AND posts.post_date BETWEEN '$from_date' AND '$to_date'";                                        
				}
			}
			
			if (SMPRO == true && isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
				$search_statuses = explode( '\"', trim ( $_POST ['searchText'] ) );
                $search_on = $wpdb->_real_escape ( trim ( $_POST ['searchText'] ) );
                        
                                //Query for getting the user_id based on the email enetered in the Search Box
                                $query_user_email     = "SELECT id FROM {$wpdb->prefix}users 
                                                    WHERE user_email like '%$search_on%'";
                                $result_user_email    = $wpdb->get_col ( $query_user_email);
                                $num_rows_email       = $wpdb->num_rows;
                                
                                if($num_rows_email == 0){
                                    $query_user_email     = "SELECT DISTINCT p2.meta_value 
                                                             FROM {$wpdb->prefix}postmeta AS p1, {$wpdb->prefix}postmeta AS p2  
                                                             WHERE p1.post_id = p2.post_id 
                                                                AND p1.meta_key = '_billing_email'
                                                                AND p2.meta_key = '_customer_user'
                                                                AND p2.meta_value > 0
                                                                AND p1.meta_value like '%$search_on%'";
                                    $result_user_email    = $wpdb->get_col ( $query_user_email);
                                    $num_rows_email1      = $wpdb->num_rows;
                                }
                                
                                
                                
                                //Query for getting the user_id based on the Customer phone number enetered in the Search Box
                                $query_user_phone     = "SELECT user_id FROM {$wpdb->prefix}usermeta 
                                                         WHERE meta_key='billing_phone' 
                                                            AND meta_value like '%$search_on%'";
                                $result_user_phone    = $wpdb->get_col ( $query_user_phone);
                                $num_rows_phone       = $wpdb->num_rows;
                                
                                if($num_rows_phone == 0){
                                    $query_user_phone     = "SELECT DISTINCT p2.meta_value 
                                                             FROM {$wpdb->prefix}postmeta AS p1, {$wpdb->prefix}postmeta AS p2  
                                                             WHERE p1.post_id = p2.post_id 
                                                                AND p1.meta_key = '_billing_phone'
                                                                AND p2.meta_key = '_customer_user'
                                                                AND p2.meta_value > 0
                                                                AND p1.meta_value like '%$search_on%'";
                                    $result_user_phone    = $wpdb->get_col ( $query_user_phone);
                                    $num_rows_phone1      = $wpdb->num_rows;
                                }
                                
                                
                                $query_terms = "SELECT term_taxonomy_id FROM {$wpdb->prefix}term_taxonomy
                                                WHERE term_id IN (SELECT term_id FROM {$wpdb->prefix}terms";
                                                                     // name like '%$search_on%')
                                // $search_statuses = explode( '\"', $search_on );
                                if ( !empty( $search_statuses ) ) {
                                    $query_terms .= " WHERE";
                                    foreach ( $search_statuses as $search_status ) {
                                        $search_status = trim( $search_status );
                                        if ( !empty( $search_status ) ) {
                                            $query_terms .= " name like '%$search_status%' OR";
                                        }
                                    }
                                    $query_terms = trim( $query_terms, ' OR' );
                                }
                                $query_terms .= ")";
                                
                                $result_terms = implode(",",$wpdb->get_col ( $query_terms ));
                                $num_terms    = $wpdb->num_rows;
                                
                                //Query to get the post_id of the products whose sku code matches with the one type in the search text box of the Orders Module
                                $query_sku  = "SELECT post_id FROM {$wpdb->prefix}postmeta
                                              WHERE meta_key = '_sku'
                                                 AND meta_value like '%$search_on%'";
                                $result_sku = $wpdb->get_col ($query_sku);
                                $rows_sku       = $wpdb->num_rows;

                                //Code for handling the Search functionality of the Orders Module using the SKU code of the product
                                if ($rows_sku > 0) {
                                    
                                    //Query for getting all the distinct attribute meta key names
                                    $query_variation = "SELECT DISTINCT meta_key as variation
                                                        FROM {$wpdb->prefix}postmeta
                                                        WHERE meta_key like 'attribute_%'";
                                    $variation = $wpdb->get_col ($query_variation);

                                    //Query to get all the product title's as displayed in the products module along wih the post_id and SKU code in an array
                                    $query_product = "SELECT posts.id, posts.post_title, posts.post_parent, 
                                                                GROUP_CONCAT( postmeta.meta_value 
                                                                    ORDER BY postmeta.meta_id
                                                                    SEPARATOR ',' ) AS meta_value
                                                      FROM {$wpdb->prefix}posts AS posts
                                                            JOIN {$wpdb->prefix}postmeta AS postmeta
                                                                ON (posts.ID = postmeta.post_id
                                                                        AND postmeta.meta_key IN ('_sku','" .implode("','",$variation) . "'))
                                                      GROUP BY posts.id";
                                    $result_product = $wpdb->get_results ($query_product , 'ARRAY_A');

                                    //Code to store all the products title in an array with the post_id as the array index
                                    for ($i=0;$i<sizeof($result_product);$i++) {
                                          $product_title[$result_product[$i]['id']]['post_title'] = $result_product[$i]['post_title'];
                                          $product_title[$result_product[$i]['id']]['variation_title'] = $result_product[$i]['meta_value'];
                                          $product_title[$result_product[$i]['id']]['post_parent'] = $result_product[$i]['post_parent'];
                                    }

                                    $post_title = array();
                                    $variation_title = array();
                                    $search_condn = "HAVING";
                                    
                                    for ($i=0;$i<sizeof($result_sku);$i++) {
                                        $product_type = wp_get_object_terms( $result_sku[$i], 'product_type', array('fields' => 'slugs') ); // Getting the type of the product
                                        
                                        //Code to prepare the search condition for the search using SKU Code
                                        if ($product_title[$result_sku[$i]]['post_parent'] == 0) {
                                            $post_title [$i] = $product_title[$result_sku[$i]]['post_title'];
                                            $search_condn .= " meta_value like '%s:4:\"name\"%\"$post_title[$i]\"%' ";
                                            $search_condn .= "OR";
                                        }
                                        elseif ($product_title[$result_sku[$i]]['post_parent'] > 0) {
                                            $temp = explode(",", $product_title[$result_sku[$i]]['variation_title']);
                                            $post_title [$i] = $product_title[$product_title[$result_sku[$i]]['post_parent']]['post_title'];
                                            $search_condn .= " meta_value like '%s:4:\"name\"%\"$post_title[$i]\"%' ";
                                            $search_condn .= "AND (";
                                                for ($j=1;$j<sizeof($temp);$j++) {
                                                    $search_condn .= " meta_value like '%s:10:\"meta_value\"%\"$temp[$j]\"%' ";
                                                    $search_condn .= "OR";
                                                }
                                            $search_condn = substr( $search_condn, 0, -2 ) . ")";
                                            $search_condn .= "OR";        
                                        }     
                                    }
                                    $variation_title = array_unique($variation_title);
                                    $search_condn = substr( $search_condn, 0, -2 );
                                }
                                
                                //Code for handling the Email Search condition for Registered users
                                elseif ($num_rows_email > 0) {
                                    
                                    // Query to bring the matching email of the Guest uers
                                    $query = "SELECT DISTINCT p1.meta_value 
                                                             FROM {$wpdb->prefix}postmeta AS p1, {$wpdb->prefix}postmeta AS p2  
                                                             WHERE p1.post_id = p2.post_id 
                                                                AND p1.meta_key = '_billing_email'
                                                                AND p2.meta_key = '_customer_user'
                                                                AND p2.meta_value = 0
                                                                AND p1.meta_value like '%$search_on%'";
                                    $result_email_guest  = $wpdb->get_col ( $query );
                                    $rows_email_guest    = $wpdb->num_rows;
                                    
                                    $query_email = "SELECT DISTINCT(p1.meta_value)
                                                    FROM {$wpdb->prefix}postmeta AS p1, {$wpdb->prefix}postmeta AS p2 
                                                    WHERE p1.post_id = p2.post_id 
                                                                AND p1.meta_key = '_billing_email'
                                                                AND p2.meta_key = '_customer_user'
                                                                AND p2.meta_value IN (" .implode(",",$result_user_email) . ")";
                                    $result_email  = $wpdb->get_col ( $query_email );
                                    
                                    if($rows_email_guest > 0) {
                                        for ($i=0,$j=sizeof($result_email);$i<sizeof($result_email_guest);$i++,$j++) {
                                            $result_email[$j] = $result_email_guest[$i];
                                        }
                                    }
                                    
                                    $search_condn = "HAVING";
                                    for ( $i=0;$i<sizeof($result_email);$i++ ) {
                                        $search_condn .= " meta_value like '%$result_email[$i]%' ";
                                        $search_condn .= "OR";
                                    }
                                    $search_condn = substr( $search_condn, 0, -2 );
                                }
                                //Code for handling the Customer Phone number Search condition for Registered users
                                elseif($num_rows_phone > 0){
                                    
                                    // Query to bring the matching Phone No. of the Guest uers
                                    $query = "SELECT DISTINCT p1.meta_value 
                                                             FROM {$wpdb->prefix}postmeta AS p1, {$wpdb->prefix}postmeta AS p2  
                                                             WHERE p1.post_id = p2.post_id 
                                                                AND p1.meta_key = '_billing_phone'
                                                                AND p2.meta_key = '_customer_user'
                                                                AND p2.meta_value = 0
                                                                AND p1.meta_value like '%$search_on%'";
                                    $result_phone_guest  = $wpdb->get_col ( $query );
                                    $rows_phone_guest    = $wpdb->num_rows;
                                    
                                    $query_phone = "SELECT DISTINCT(p1.meta_value)
                                                    FROM {$wpdb->prefix}postmeta AS p1, {$wpdb->prefix}postmeta AS p2 
                                                    WHERE p1.post_id = p2.post_id 
                                                                AND p1.meta_key = '_billing_email'
                                                                AND p2.meta_key = '_customer_user'
                                                                AND p2.meta_value IN (" .implode(",",$result_user_phone) . ")";
                                    $result_phone  = $wpdb->get_col ( $query_phone );
                                    
                                    if($rows_phone_guest > 0) {
                                        for ($i=0,$j=sizeof($result_phone);$i<sizeof($result_phone_guest);$i++,$j++) {
                                            $result_phone[$j] = $result_phone_guest[$i];
                                        }
                                    }
                                    
                                    $search_condn = "HAVING";
                                    for ( $i=0;$i<sizeof($result_phone);$i++ ){
                                        $search_condn .= " meta_value like '%$result_phone[$i]%' ";
                                        $search_condn .= "OR";
                                    }
                                    $search_condn = substr( $search_condn, 0, -2 );
                                }
                                elseif ($num_rows_email1 > 0 || $num_rows_phone1 > 0 ) {
                                    $search_condn = " HAVING id = 0";
                                }
                                elseif ($num_terms > 0) {
                                    $search_condn = " HAVING term_taxonomy_id IN ($result_terms)";
                                }
                                else{
				$search_condn = " HAVING id like '$search_on%'
								  OR date like '%$search_on%'
								 OR meta_value like '%$search_on%'";
			}
			
			}
			
			//get the state id if the shipping state is numeric or blank
			$query    = "$select_query $where $group_by $search_condn $limit_query";
			$results  = $wpdb->get_results ( $query,'ARRAY_A');
			//To get the total count
			$orders_count_result = $wpdb->get_results ( 'SELECT FOUND_ROWS() as count;','ARRAY_A');
			$num_records = $orders_count_result[0]['count'];
					
                        //Query to get the email id from the wp_users table for the Registered Customers
                        $query_users  = "SELECT users.ID,users.user_email,usermeta.meta_value
                                         FROM {$wpdb->prefix}users AS users, {$wpdb->prefix}usermeta AS usermeta
                                         WHERE usermeta.user_id = users.id 
                                            AND usermeta.meta_key = 'billing_phone'
                                         GROUP BY users.ID";
                        $result_users =  $wpdb->get_results ( $query_users, 'ARRAY_A' );
                        
                        if ($num_records == 0) {
            				$encoded ['totalCount'] = '';
            				$encoded ['items'] = '';
            				$encoded ['msg'] = __('No Records Found','smart-manager'); 
            			} else {			
                                foreach ( $results as $data ) {
                                    $order_ids[] = $data['id'];
                                }
                                
                                if($_POST['SM_IS_WOO16'] == "false") {
                                    $order_id = implode(",",$order_ids);
                                    $query_order_items = "SELECT order_items.order_item_id,
                                                            order_items.order_id    ,
                                                            order_items.order_item_name AS order_prod,
                                                            GROUP_CONCAT(order_itemmeta.meta_key
                                                                                ORDER BY order_itemmeta.meta_id 
                                                                                SEPARATOR '###' ) AS meta_key,
                                                            GROUP_CONCAT(order_itemmeta.meta_value
                                                                                ORDER BY order_itemmeta.meta_id 
                                                                                SEPARATOR '###' ) AS meta_value
                                                        FROM {$wpdb->prefix}woocommerce_order_items AS order_items 
                                                            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta 
                                                                ON (order_items.order_item_id = order_itemmeta.order_item_id)
                                                        WHERE order_items.order_id IN ($order_id)
                                                            AND order_items.order_item_type LIKE 'line_item'
                                                        GROUP BY order_items.order_item_id
                                                        ORDER BY FIND_IN_SET(order_items.order_id,'$order_id')";
                                    $results_order_items  = $wpdb->get_results ( $query_order_items , 'ARRAY_A');

                                    $query_order_coupons = "SELECT order_id,
                                                                GROUP_CONCAT(order_item_name
                                                                                    ORDER BY order_item_id 
                                                                                    SEPARATOR ', ' ) AS coupon_used
                                                            FROM {$wpdb->prefix}woocommerce_order_items
                                                            WHERE order_id IN ($order_id)
                                                                  AND order_item_type LIKE 'coupon'
                                                            GROUP BY order_id
                                                            ORDER BY FIND_IN_SET(order_id,'$order_id')";
                                    $results_order_coupons  = $wpdb->get_results ( $query_order_coupons , 'ARRAY_A');                                                            
                                    $num_rows_coupons = $wpdb->num_rows;

                                    if ($num_rows_coupons > 0) {
                                        $order_coupons = array();
                                        foreach ($results_order_coupons as $results_order_coupon) {
                                            $order_coupons[$results_order_coupon['order_id']] = $results_order_coupon['coupon_used'];
                                        }    
                                    }

                                    $query_variation_ids = "SELECT order_itemmeta.meta_value 
                                                            FROM {$wpdb->prefix}woocommerce_order_items AS order_items 
                                                               LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS order_itemmeta 
                                                                   ON (order_items.order_item_id = order_itemmeta.order_item_id)
                                                            WHERE order_itemmeta.meta_key LIKE '_variation_id'
                                                                   AND order_itemmeta.meta_value > 0
                                                                   AND order_items.order_id IN ($order_id)";
                                    $result_variation_ids  = $wpdb->get_col ( $query_variation_ids );                       
                                    
                                    $query_variation_att = "SELECT postmeta.post_id AS post_id,
                                                                    GROUP_CONCAT(postmeta.meta_value
                                                                        ORDER BY postmeta.meta_id 
                                                                        SEPARATOR ', ' ) AS meta_value
                                                            FROM {$wpdb->prefix}postmeta AS postmeta
                                                            WHERE postmeta.meta_key LIKE 'attribute_%'
                                                                AND postmeta.post_id IN (". implode(",",$result_variation_ids) .")
                                                            GROUP BY postmeta.post_id";
//                                                                                          
                                    $results_variation_att  = $wpdb->get_results ( $query_variation_att , 'ARRAY_A');
                                    
                                    $query_terms = "SELECT terms.slug as slug, terms.name as term_name
                                              FROM {$wpdb->prefix}terms AS terms
                                                JOIN {$wpdb->prefix}postmeta AS postmeta 
                                                    ON ( postmeta.meta_value = terms.slug 
                                                            AND postmeta.meta_key LIKE 'attribute_%' ) 
                                              GROUP BY terms.slug";
                                    $attributes_terms = $wpdb->get_results( $query_terms, 'ARRAY_A' );
                                    
                                    $attributes = array();
                                    foreach ( $attributes_terms as $attributes_term ) {
                                        $attributes[$attributes_term['slug']] = $attributes_term['term_name'];
                                    }
                                    
                                    $variation_att_all = array();
                                    
                                    for ($i=0;$i<sizeof($results_variation_att);$i++) {
                                        $variation_attributes = explode(", ",$results_variation_att [$i]['meta_value']);
                                        
                                        $attributes_final = array();
                                        foreach ($variation_attributes as $variation_attribute) {
                                            $attributes_final[] = (isset($attributes[$variation_attribute]) ? $attributes[$variation_attribute] : ucfirst($variation_attribute) );
                                        }
                                        
                                        $results_variation_att [$i]['meta_value'] = implode(", ",$attributes_final);
                                        $variation_att_all [$results_variation_att [$i]['post_id']] = $results_variation_att [$i]['meta_value'];
                                    }
                                }
                                
				foreach ( $results as $data ) {
					$meta_key = explode ( '###', $data ['meta_key'] );
					$meta_value = explode ( '###', $data ['meta_value'] );
					
					if(count($meta_key) == count($meta_value)){
						$postmeta = array_combine ( $meta_key, $meta_value);
                                                
                                                //Code to replace the email of the Registered Customers with the one from the wp_users
                                                if ($postmeta['_customer_user'] > 0) {
                                                    for ( $index=0;$index<sizeof($result_users);$index++ ) {
                                                        if ( $postmeta['_customer_user'] == $result_users[$index]['ID'] ){
                                                            $postmeta['_billing_email'] = $result_users[$index]['user_email'];
                                                            $postmeta['_billing_phone'] = $result_users[$index]['meta_value'];
                                                            break;
                                                        }
                                                    }
                                                }
                                                
                                                if($_POST['SM_IS_WOO16'] == "true") {
                                                    if (is_serialized($postmeta['_order_items'])) {
                                                            $order_items = unserialize(trim($postmeta['_order_items']));
                                                            foreach ( (array)$order_items as $order_item) {
                                                                    if ( isset( $order_item['item_meta'] ) && count( $order_item['item_meta'] ) > 0 ) {
                                                                        $variation_data = array();
                                                                        foreach ( $order_item['item_meta'] as $meta ) {
                                                                            $variation_data['attribute_'.$meta['meta_name']] = $meta['meta_value'];
                                                                        }
                                                                        $variation_details = woocommerce_get_formatted_variation( $variation_data, true );
                                                                    }

                                                                    $data['details'] += $order_item['qty'];
                                                                    $data['order_total_ex_tax'] += $order_item['line_total'];
                                                                    $product_id = ( $order_item['variation_id'] > 0 ) ? $order_item['variation_id'] : $order_item['id'];
                                                                    $sm_sku = get_post_meta( $product_id, '_sku', true );
                                                                    if ( ! empty( $sm_sku ) ) {
                                                                            $sku_detail = '[SKU: ' . $sm_sku . ']';
                                                                    } else {
                                                                            $sku_detail = '';
                                                                    }
                                                                    $product_full_name = ( !empty( $variation_details ) ) ? $order_item['name'] . ' (' . $variation_details . ')' : $order_item['name'];
                                                                    $data['products_name'] .= $product_full_name.' '.$sku_detail.'['.__('Qty','smart-manager').': '.$order_item['qty'].']['.__('Price','smart-manager').': '.($order_item['line_total']/$order_item['qty']).'], ';
                                                            }
                                                            isset($data['details']) ? $data['details'] .= ' items' : $data['details'] = ''; 
                                                            $data['products_name'] = substr($data['products_name'], 0, -2);	//To remove extra comma ', ' from returned string
                                                    } else {
                                                            $data['details'] = 'Details';
                                                    }
                                                    
                                                }
                                                else {
                                                        if (!empty($results_order_items)) {
                                                            foreach ( $results_order_items as $order_item) {
                                                                $prod_meta_values = explode('###', $order_item ['meta_value'] );
                                                                $prod_meta_key = explode('###', $order_item ['meta_key'] );
                                                                if (count($prod_meta_values) != count($prod_meta_key))
                                                                    continue;
                                                                unset( $order_item ['meta_value'] );
                                                                unset( $order_item ['meta_key'] );

                                                                update_post_meta($index, $sku_detail, $meta_value);
                                                                
                                                                $prod_meta_key_values = array_combine($prod_meta_key, $prod_meta_values);

                                                                
                                                                if ($data['id'] == $order_item['order_id']) {

                                                                    $data['details'] += $prod_meta_key_values['_qty'];
                                                                    $data['order_total_ex_tax'] += $prod_meta_key_values['_line_total'];

                                                                    $product_id = ( $prod_meta_key_values['_variation_id'] > 0 ) ? $prod_meta_key_values['_variation_id'] : $prod_meta_key_values['_product_id'];
                                                                    $sm_sku = get_post_meta( $product_id, '_sku', true );
                                                                    if ( ! empty( $sm_sku ) ) {
                                                                            $sku_detail = '[SKU: ' . $sm_sku . ']';
                                                                    } else {
                                                                            $sku_detail = '';
                                                                    }
                                                                    
                                                                    $variation_att = $variation_att_all [$prod_meta_key_values['_variation_id']];
                                                                    
                                                                    $product_full_name = ( !empty( $variation_att ) ) ? $order_item['order_prod'] . ' (' . $variation_att . ')' : $order_item['order_prod'];
                                                                    $data['products_name'] .= $product_full_name.' '.$sku_detail.'['.__('Qty','smart-manager').': '.$prod_meta_key_values['_qty'].']['.__('Price','smart-manager').': '.($prod_meta_key_values['_line_total']/$prod_meta_key_values['_qty']).'], ';
                                                            
                                                                    $data['coupons'] = (isset($order_coupons[$order_item['order_id']])) ? $order_coupons[$order_item['order_id']] : "";

                                                                }
                                                            }
                                                            isset($data['details']) ? $data['details'] .= ' items' : $data['details'] = '';
                                                            $data['products_name'] = substr($data['products_name'], 0, -2); //To remove extra comma ', ' from returned string                                                                              
                                                        }
                                                        

                                                }


                                                //Code to get the Order_Status using the $terms_name array
                                                $data ['order_status'] = $terms_name[$data ['term_taxonomy_id']];
                                                
						$name_emailid [0] = "<font class=blue>". $postmeta['_billing_first_name']."</font>";
						$name_emailid [1] = "<font class=blue>". $postmeta['_billing_last_name']."</font>";
						$name_emailid [2] = "(".$postmeta['_billing_email'].")"; //email comes at 7th position.
						$data['name'] 	  = implode ( ' ', $name_emailid ); //in front end,splitting is done with this space.
	
						$data ['_shipping_address'] = $postmeta['_shipping_address_1'].', '.$postmeta['_shipping_address_2'];
						unset($data ['meta_value']);
						$postmeta ['_shipping_method'] = isset($postmeta ['_shipping_method_title']) ? $postmeta ['_shipping_method_title'] : $postmeta ['_shipping_method'];
						$postmeta ['_payment_method'] = isset($postmeta ['_payment_method_title']) ? $postmeta ['_payment_method_title'] : $postmeta ['_payment_method'];
						$postmeta ['_shipping_state'] = isset($woocommerce->countries->states[$postmeta ['_shipping_country']][$postmeta ['_shipping_state']]) ? $woocommerce->countries->states[$postmeta ['_shipping_country']][$postmeta ['_shipping_state']] : $postmeta ['_shipping_state'];
						$postmeta ['_shipping_country'] = isset($woocommerce->countries->countries[$postmeta ['_shipping_country']]) ? $woocommerce->countries->countries[$postmeta ['_shipping_country']] : $postmeta ['_shipping_country'];
							$records [] = array_merge ( $postmeta, $data );	
					}
				}
				
				unset($meta_value);
				unset($meta_key);
				unset($postmeta);
				unset($results);
			}
	}
	$encoded ['items'] = $records;
	$encoded ['totalCount'] = $num_records;
	unset($records);
    return $encoded;
}

// Searching a product in the grid
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getData') {
        $encoded = get_data_woo ( $_POST, $offset, $limit );
	ob_clean();
        echo json_encode ( $encoded );
	unset($encoded);
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'state') {

        global $current_user , $wpdb;

        $state_nm = array("dashboardcombobox", "Products", "Customers", "Orders","incVariation");
        
        for ($i=0;$i<sizeof($state_nm);$i++) {
            $stateid = "_sm_".$current_user->user_email."_".$state_nm[$i];
        
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



if (isset ( $_GET ['cmd'] ) && $_GET ['cmd'] == 'exportCsvWoo') {
        $sm_domain = 'smart-manager';
	$encoded = get_data_woo ( $_GET, $offset, $limit, true );
	$data = $encoded ['items'];
	unset($encoded);
	$columns_header = array();
	$active_module = $_GET ['active_module'];
	switch ( $active_module ) {
		
		case 'Products':
				$columns_header['id'] 						= __('Post ID', $sm_domain);
				$columns_header['thumbnail'] 				= __('Product Image', $sm_domain);
				$columns_header['post_title'] 				= __('Product Name', $sm_domain);
				$columns_header['_regular_price'] 			= __('Price', $sm_domain);
				$columns_header['_sale_price'] 				= __('Sale Price', $sm_domain);
				$columns_header['_sale_price_dates_from'] 	= __('Sale Price Dates (From)', $sm_domain);
				$columns_header['_sale_price_dates_to'] 	= __('Sale Price Dates (To)', $sm_domain);
				$columns_header['_stock'] 					= __('Inventory / Stock', $sm_domain);
				$columns_header['_sku'] 					= __('SKU', $sm_domain);
				$columns_header['category'] 				= __('Category / Group', $sm_domain);
				$columns_header['_weight'] 					= __('Weight', $sm_domain);
				$columns_header['_height'] 					= __('Height', $sm_domain);
				$columns_header['_width'] 					= __('Width', $sm_domain);
				$columns_header['_length'] 					= __('Length', $sm_domain);
				$columns_header['_tax_status'] 				= __('Tax Status', $sm_domain);
                                $columns_header['_visibility'] 				= __('Visibility', $sm_domain);
			break;
			
		case 'Customers':
				$columns_header['id'] 					= __('User ID', $sm_domain);
				$columns_header['_billing_first_name'] 	= __('First Name', $sm_domain);
				$columns_header['_billing_last_name'] 	= __('Last Name', $sm_domain);
				$columns_header['_billing_email'] 		= __('E-mail ID', $sm_domain);
				$columns_header['_billing_address'] 	= __('Address', $sm_domain);
				$columns_header['_billing_postcode'] 	= __('Postcode', $sm_domain);
				$columns_header['_billing_city'] 		= __('City', $sm_domain);
				$columns_header['_billing_state'] 		= __('State / Region', $sm_domain);
				$columns_header['_billing_country'] 	= __('Country', $sm_domain);
				$columns_header['last_order'] 			= __('Last Order Date', $sm_domain);
				$columns_header['_order_total'] 		= __('Order Total', $sm_domain);
				$columns_header['_billing_phone'] 		= __('Phone / Mobile', $sm_domain);
				$columns_header['count_orders'] 		= __('Total Number Of Orders', $sm_domain);
				$columns_header['total_orders'] 		= __('Total Purchased', $sm_domain);
			break;
			
		case 'Orders':
				$columns_header['id'] 						= __('Order ID', $sm_domain);
				$columns_header['date'] 					= __('Order Date', $sm_domain);
				$columns_header['_billing_first_name'] 		= __('Billing First Name', $sm_domain);
				$columns_header['_billing_last_name'] 		= __('Billing Last Name', $sm_domain);
				$columns_header['_billing_email'] 			= __('Billing E-mail ID', $sm_domain);
                                $columns_header['_billing_phone'] 			= __('Billing Phone Number', $sm_domain);
                                $columns_header['_order_shipping'] 			= __('Order Shipping', $sm_domain);
                                $columns_header['_order_discount'] 			= __('Order Discount', $sm_domain);
                                $columns_header['_cart_discount'] 			= __('Cart Discount', $sm_domain);
                                $columns_header['coupons'] 			= __('Coupons Used', $sm_domain);
                                $columns_header['_order_tax'] 			= __('Order Tax', $sm_domain);
                                $columns_header['_order_shipping_tax'] 			= __('Order Shipping Tax', $sm_domain);
                                $columns_header['_order_total'] 			= __('Order Total', $sm_domain);
				$columns_header['_order_currency'] 			= __('Order Currency', $sm_domain);
				$columns_header['products_name'] 			= __('Order Items (Product Name [SKU][Qty][Price])', $sm_domain);
				$columns_header['_payment_method_title'] 	= __('Payment Method', $sm_domain);
				$columns_header['order_status'] 			= __('Order Status', $sm_domain);
				$columns_header['_shipping_method_title'] 	= __('Shipping Method', $sm_domain);
				$columns_header['_shipping_first_name'] 	= __('Shipping First Name', $sm_domain);
				$columns_header['_shipping_last_name'] 		= __('Shipping Last Name', $sm_domain);
				$columns_header['_shipping_address'] 		= __('Shipping Address', $sm_domain);
				$columns_header['_shipping_postcode'] 		= __('Shipping Postcode', $sm_domain);
				$columns_header['_shipping_city'] 			= __('Shipping City', $sm_domain);
				$columns_header['_shipping_state'] 			= __('Shipping State / Region', $sm_domain);
				$columns_header['_shipping_country'] 		= __('Shippping Country', $sm_domain);
				$columns_header['order_note'] 		= __('Order Notes', $sm_domain);
			break;
	}
	
	$file_data = export_csv_woo ( $active_module, $columns_header, $data );
	
	header("Content-type: text/x-csv; charset=UTF-8"); 
	header("Content-Transfer-Encoding: binary");
	header("Content-Disposition: attachment; filename=".$file_data['file_name']); 
	header("Pragma: no-cache");
	header("Expires: 0");
		
	ob_clean();
        echo $file_data['file_content'];
		
	exit;
}

//update products for lite version.
function update_products_woo($post) {
	global $result, $wpdb;
        $_POST = $post;     // Fix: PHP 5.4
        //For encoding the string in UTF-8 Format
//        $charset = "EUC-JP, ASCII, UTF-8, ISO-8859-1, JIS, SJIS";
        $charset = ( get_bloginfo('charset') === 'UTF-8' ) ? null : get_bloginfo('charset');
        if (!(is_null($charset))) {
            $_POST['edited'] = mb_convert_encoding(stripslashes($_POST['edited']),"UTF-8",$charset);
        }
        else {
            $_POST['edited'] = stripslashes($_POST['edited']);
        }
        
	$edited_object = json_decode ( stripslashes ( $_POST ['edited'] ) );
	$updateCnt = 1;
	foreach ( $edited_object as $obj ) {
		$price = ( $obj->_sale_price ) ? $obj->_sale_price : $obj->_regular_price;
		$update_name = $wpdb->query ( "UPDATE $wpdb->posts SET `post_title`= '".$wpdb->_real_escape($obj->post_title)."' WHERE ID = " . $wpdb->_real_escape($obj->id) );
		update_post_meta( $obj->id, '_sale_price', $wpdb->_real_escape($obj->_sale_price) );
                update_post_meta( $obj->id, '_regular_price', $wpdb->_real_escape($obj->_regular_price) );
                update_post_meta( $obj->id, '_price', $wpdb->_real_escape($price) );
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
		
                //For encoding the string in UTF-8 Format
//                $charset = "EUC-JP, ASCII, UTF-8, ISO-8859-1, JIS, SJIS";
                $charset = ( get_bloginfo('charset') === 'UTF-8' ) ? null : get_bloginfo('charset');
                if (!(is_null($charset))) {
                    $_POST['edited'] = mb_convert_encoding(stripslashes($_POST['edited']),"UTF-8",$charset);
                }
                else {
                    $_POST['edited'] = stripslashes($_POST['edited']);
                }
                    
		if (SMPRO == true)
			$result = woo_insert_update_data ( $_POST );
		else
			$result = update_products_woo ( $_POST );
		
		if ($result ['updated'] && $result ['inserted']) {
			if ($result ['updateCnt'] == 1 && $result ['insertCnt'] == 1)
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __('Record Updated and', 'smart-manager') . "<br><b>" . $result ['insertCnt'] . "</b> " . __('New Record Inserted Successfully','smart-manager');
			elseif ($result ['updateCnt'] == 1 && $result ['insertCnt'] != 1)
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __('Record Updated and', 'smart-manager') . "<br><b>" . $result ['insertCnt'] . "</b> " . __('New Records Inserted Successfully', 'smart-manager'); 
			elseif ($result ['updateCnt'] != 1 && $result ['insertCnt'] == 1)
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __('Records Updated and', 'smart-manager') . "<br><b>" . $result ['insertCnt'] . "</b> " . __('New Record Inserted Successfully','smart-manager'); 
			else
				$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __('Records Updated and', 'smart-manager') . "<br><b>" . $result ['insertCnt'] . "</b> " . __('New Records Inserted Successfully','smart-manager');
		} else {
			
			if ($result ['updated'] == 1) {
				if ($result ['updateCnt'] == 1) {
					$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __('Record Updated Successfully', 'smart-manager') ;
				} else
					$encoded ['msg'] = "<b>" . $result ['updateCnt'] . "</b> " . __('Records Updated Successfully', 'smart-manager') ;
			}
			
			if ($result ['inserted'] == 1) {
				if ($result ['insertCnt'] == 1)
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> " . __('New Record Inserted Successfully', 'smart-manager');
				else
					$encoded ['msg'] = "<b>" . $result ['insertCnt'] . "</b> " . __('New Records Inserted Successfully','smart-manager');
			}
			
		}
	ob_clean();
        echo json_encode ( $encoded );
}



if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'dupData') {
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
                $post_data [] = woocommerce_create_duplicate_from_product($post,0,'publish');
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
    if(isset ( $_POST ['part'] ) && $_POST ['part'] == 'initial') {

        //Code for getting the number of parent products for the dulplication of entire store
        if ( $_POST ['menu'] == 'store') {
            $query="SELECT id from {$wpdb->prefix}posts WHERE post_type='product' AND post_parent =0";
            $data_dup = $wpdb->get_col ( $query );
        }
        else{
            if ($_POST ['incvariation'] == true) {
                $query="SELECT id from {$wpdb->prefix}posts WHERE post_type='product' AND post_parent =0";
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
	$delCnt = 0;
	$activeModule = substr( $_POST ['active_module'], 0, -1 );

		$data = json_decode ( stripslashes ( $_POST ['data'] ) );
		$delCnt = count ( $data );
		
		for($i = 0; $i < $delCnt; $i ++) {
			$post_id = $data [$i];
			$post = get_post ( $post_id );		// Required to get post_type for deleting variation from Smart Manager
			if ( $post->post_type == 'product_variation' ) {
				$post_data [] = wp_delete_post( $post_id );
			} else {
				$post_data [] = wp_trash_post ( $post_id );
			}
		}
		
		$deleted_count = count ( $post_data );
		if ($deleted_count == $delCnt)
			$result = true;
		else
			$result = false;
		
		if ($result == true) {
			if ($delCnt == 1) {
				$encoded ['msg'] = $delCnt . " " . $activeModule . __(' Deleted Successfully','smart-manager');
				$encoded ['delCnt'] = $delCnt;
			} else {
				$encoded ['msg'] = $delCnt . " " . $activeModule . __('s Deleted Successfully','smart-manager');
				$encoded ['delCnt'] = $delCnt;
			}
		} elseif ($result == false) {
			$encoded ['msg'] = $activeModule . __('s were not deleted','smart-manager');
		} else {
			$encoded ['msg'] = $activeModule . __('s removed from the grid','smart-manager');
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

function get_term_taxonomy_id($term_name) {					// for woocommerce orders
	global $wpdb;
	$select_query = "SELECT term_taxonomy_id FROM {$wpdb->prefix}term_taxonomy AS term_taxonomy JOIN {$wpdb->prefix}terms AS terms ON terms.term_id = term_taxonomy.term_id WHERE terms.name = '$term_name'";
	$result = $wpdb->get_results ($select_query, 'ARRAY_A');
	if (isset($result[0])) {
		return (int)$result[0]['term_taxonomy_id'];	
	} else {
		$insert_term_query = "INSERT INTO {$wpdb->prefix}terms ( name, slug ) VALUES ( '" . $wpdb->_real_escape($term_name) . "', '" . $wpdb->_real_escape($term_name) . "' )";
		$result = $wpdb->query ($insert_term_query);
		if ($result > 0) {
			$insert_taxonomy_query = "INSERT INTO {$wpdb->prefix}term_taxonomy ( term_id, taxonomy ) VALUES ( " . $wpdb->_real_escape($wpdb->insert_id) . ", 'shop_order_status' )";
			$result = $wpdb->query ($insert_taxonomy_query);
			return (int)$wpdb->insert_id;
		} else {
			return -1;
		}
	}
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getTerms'){
	global $wpdb;
	$action_name =  $_POST['action_name'];
	$attribute_name = $_POST ['attribute_name'];
	$attribute_suffix = "pa_" . $attribute_name;
	$query = "SELECT tt.term_taxonomy_id, t.name FROM {$wpdb->prefix}terms as t join {$wpdb->prefix}term_taxonomy as tt on (t.term_id = tt.term_id) where tt.taxonomy = '$attribute_suffix' ";
	$results = $wpdb->get_results ($query, 'ARRAY_A');
	$terms_combo_store = array();
	$term_count = 0;
	$terms_combo_store [$term_count] [] = 'all';
	$terms_combo_store [$term_count] [] = 'All';
	$term_count++;
	foreach ( $results as $result ) {
		$terms_combo_store [$term_count] [] = $result['term_taxonomy_id'];
		$terms_combo_store [$term_count] [] = $result['name'];
		$term_count++;
	}
	
	ob_clean();
        echo json_encode ( $terms_combo_store );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getRegion') {
	global $wpdb, $woocommerce;
	$cnt = 0;
	if ( !empty ( $woocommerce->countries->states[$_POST['country_id']] ) ) {
		foreach ( $woocommerce->countries->states[$_POST['country_id']] as $key => $value) {
			$regions ['items'] [$cnt] ['id'] = $key;
			$regions ['items'] [$cnt] ['name'] = $value;
			$cnt++;
		}
	} else {
		$regions = '';
	}
	ob_clean();
        echo json_encode ( $regions );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'editImage') {
	$woo_default_image = WP_PLUGIN_URL . '/smart-reporter-for-wp-e-commerce/resources/themes/images/woo_default_image.png';
	$post_thumbnail_id = get_post_thumbnail_id( $_POST ['id'] );
	$image = isset( $post_thumbnail_id ) ? wp_get_attachment_image_src( $post_thumbnail_id, 'admin-product-thumbnails' ) : '';
	$thumbnail = ( $image[0] != '' ) ? $image[0] : '';
	ob_clean();
        echo json_encode ( $thumbnail );
}
ob_end_flush();
?>