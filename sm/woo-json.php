<?php 

ob_start();

if (!function_exists('find_wp_load_path')) {
    function find_wp_load_path() { // function to find the wordpress root directory path
        $dir = dirname(__FILE__);
        do {
            if( file_exists($dir."/wp-load.php") ) {
                return $dir;
            }
        } while( $dir = realpath("$dir/..") );
        return null;
    }    
}


if ( ! defined('ABSPATH') ) {
    include_once (find_wp_load_path()  . '/wp-load.php');
}

include_once (WP_PLUGIN_DIR . '/woocommerce/admin/includes/duplicate_product.php');
load_textdomain( 'smart-manager', WP_PLUGIN_DIR . '/' . dirname(dirname(plugin_basename( __FILE__ ))) . '/languages/smart-manager-' . WPLANG . '.mo' );

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
if (file_exists ( WP_PLUGIN_DIR . '/' . dirname(dirname(plugin_basename( __FILE__ ))) . '/pro/woo.php' )) {
	if ( !defined( 'SMPRO' ) ) define ( 'SMPRO', true );
	include_once (WP_PLUGIN_DIR . '/' . dirname(dirname(plugin_basename( __FILE__ ))) . '/pro/woo.php');
} else {
	if ( !defined( 'SMPRO' ) ) define ( 'SMPRO', false );
}

function values( $arr ) {
    return $arr['id'];
}

// getting the active module
$active_module = (isset($_POST ['active_module']) ? $_POST ['active_module'] : 'Products');
//$active_module = $_POST ['active_module'];

function variation_query_params(){
    global $wpdb, $post_status, $parent_sort_id, $order_by, $post_type, $variation_name, $from_variation, $parent_name;
    $from_variation = "LEFT JOIN ( SELECT GROUP_CONCAT(terms.name ORDER BY terms.term_id SEPARATOR ', ') as variation_name, postmeta.post_id as post_id
                        FROM {$wpdb->prefix}postmeta AS postmeta
                        JOIN {$wpdb->prefix}terms AS terms ON ( postmeta.meta_value = terms.slug ) GROUP BY postmeta.post_id )
                        AS prod_variation ON ( products.id = prod_variation.post_id )";

    $variation_name = "variation_name,";
    $parent_name    = "parent_name,";
    $post_status    = "('publish', 'draft')";
    $post_type      = "('product', 'product_variation')";
    $parent_sort_id = " ,if(products.post_parent = 0,products.id,products.post_parent - 1 + (products.id)/pow(10,char_length(cast(products.id as char)))) as parent_sort_id";
    $order_by       = " ORDER BY parent_sort_id desc";
}

function get_data_woo ( $post, $offset, $limit, $is_export = false ) {
	global $wpdb, $woocommerce, $post_status, $parent_sort_id, $order_by, $post_type, $variation_name, $from_variation, $parent_name, $attributes;
	$_POST = $post;     // Fix: PHP 5.4
        $products = array();
        
	// getting the active module
        $active_module = (isset($_POST ['active_module']) ? $_POST ['active_module'] : 'Products');
//        $active_module = $_POST ['active_module'];
	
	 variation_query_params ();
	
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
        

		if (isset ( $_POST ['incVariation'] ) && $_POST ['incVariation'] === 'true') {
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

        // Query to delete the unwanted '_regular_price' meta_key from variations
        if ($_POST['SM_IS_WOO16'] == "true") {

            $query_delete_variations = "DELETE FROM {$wpdb->prefix}postmeta 
                                        WHERE meta_key = '_regular_price'
                                            AND post_id IN (SELECT id FROM {$wpdb->prefix}posts
                                                                WHERE post_parent > 0
                                                                 AND post_type IN ('product_variation')
                                                                 AND post_status IN ('publish', 'draft'))";
            $wpdb->query ( $query_delete_variations );
        }

        //Code to get the attribute terms for all attributes
        $query_attribute_names = "SELECT terms.name AS attribute_terms,
                                        taxonomy.taxonomy as attribute_name,
                                        taxonomy.term_taxonomy_id as term_taxonomy_id
                                    FROM {$wpdb->prefix}terms as terms
                                        JOIN {$wpdb->prefix}term_taxonomy as taxonomy ON (taxonomy.term_id = terms.term_id)
                                    WHERE taxonomy.taxonomy LIKE 'pa_%'
                                    GROUP BY taxonomy.taxonomy, taxonomy.term_taxonomy_id";
        $results_attribute_names = $wpdb->get_results( $query_attribute_names, 'ARRAY_A' );

        $product_attributes = array();

        $temp_attribute_nm = "";

        foreach ($results_attribute_names as $results_attribute_name) {

            if ($results_attribute_name['attribute_name'] != $temp_attribute_nm) {
                $product_attributes[$results_attribute_name['attribute_name']] = array();               
            }

            $product_attributes[$results_attribute_name['attribute_name']][$results_attribute_name['term_taxonomy_id']] = $results_attribute_name['attribute_terms'];
            $temp_attribute_nm = $results_attribute_name['attribute_name'];
        }

        $query_attribute_label = "SELECT attribute_name, attribute_label
                                FROM {$wpdb->prefix}woocommerce_attribute_taxonomies";
        $results_attribute_label = $wpdb->get_results( $query_attribute_label, 'ARRAY_A' );                   

        $attributes_label = array();

        foreach ($results_attribute_label as $results_attribute_label1) {
            $attributes_label['pa_' . $results_attribute_label1['attribute_name']] = $results_attribute_label1['attribute_label'];
        }


        //Query to get the Category Ids

        $query_categories = "SELECT products.id as id,
                                GROUP_CONCAT(distinct wtr.term_taxonomy_id order by wtr.object_id SEPARATOR '###') AS term_taxonomy_id
                            FROM {$wpdb->prefix}posts as products
                                    JOIN {$wpdb->prefix}term_relationships as wtr ON (products.id = wtr.object_id)
                            WHERE products.post_status IN $post_status
                                    AND products.post_type IN $post_type
                                    $trash_id
                            GROUP BY id";
        $records_categories = $wpdb->get_results ( $query_categories, 'ARRAY_A' );                    

        $category_ids_all = array();

        foreach ($records_categories as $records_category) {
            $category_ids_all[$records_category['id']] = $records_category['term_taxonomy_id'];
        }


        // GROUP_CONCAT(distinct wtr.term_taxonomy_id order by wtr.object_id SEPARATOR '###') AS term_taxonomy_id,

        $select = "SELECT SQL_CALC_FOUND_ROWS products.id,
					products.post_title,
                    products.post_title as post_title_search,
					products.post_content,
					products.post_excerpt,
					products.post_status,
					products.post_parent,
					GROUP_CONCAT(prod_othermeta.meta_key order by prod_othermeta.meta_id SEPARATOR '###') AS prod_othermeta_key,
					GROUP_CONCAT(prod_othermeta.meta_value order by prod_othermeta.meta_id SEPARATOR '###') AS prod_othermeta_value
					$parent_sort_id";

        //Used as an alternative to the SQL_CALC_FOUND_ROWS function of MYSQL Database
        $select_count = "SELECT COUNT(*) as count"; // To get the count of the number of rows generated from the above select query

        $search = "";
        $search_condn = "";
        
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
                                    $search_condn .= " (concat(' ',REPLACE(REPLACE(post_title_search,'(',''),')','')) LIKE '%$search_on%'
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
                            $search_condn = " HAVING concat(' ',REPLACE(REPLACE(post_title_search,'(',''),')','')) LIKE '%$search_on%'
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

        // LEFT JOIN {$wpdb->prefix}term_relationships as wtr ON (products.id = wtr.object_id
        //                                                     )";  // Removed $term_taxonomy_id_query as it was conflicting with Attributes Column

		$from = "FROM {$wpdb->prefix}posts as products
						JOIN {$wpdb->prefix}postmeta as prod_othermeta ON (prod_othermeta.post_id = products.id and
						prod_othermeta.meta_key IN ('_regular_price','_sale_price','_sale_price_dates_from','_sale_price_dates_to','_sku','_stock','_weight','_height','_length','_width','_price','_thumbnail_id','_tax_status','_min_variation_regular_price','_min_variation_sale_price','_visibility','_product_attributes','" . implode( "','", $variation ) . "') )";
						
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
                
                if (isset($category_ids_all[$records[$i]['id']])) {

                    //$category_id = explode('###', $records[$i]['term_taxonomy_id']);
                        $category_names = "";
            //                unset($records[$i]['term_taxonomy_id']);

                    $category_id = explode('###', $category_ids_all[$records[$i]['id']]);

                      for ($j = 0; $j < sizeof($category_id); $j++) {
                            if (isset($term_taxonomy[$category_id[$j]])) {
                                $category_names .=$term_taxonomy[$category_id[$j]] . ', ';
                                }
                        }
                        if ($category_names != "") {
                            $category_names = substr($category_names, 0, -2);
                            $records[$i]['category'] = $category_names;
                        }

                } else {
                    $records[$i]['category'] = "";
                }



                $records[$i]['category'] = ( ( $records[$i]['post_parent'] > 0 && $product_type[0] == 'simple' ) || ( $records[$i]['post_parent'] == 0 ) ) ? $records[$i]['category'] : '';   // To hide category name from Product's variations

                //Attributes Column

                if (isset($prod_meta_key_values['_product_attributes']) && $prod_meta_key_values['_product_attributes'] != "") {
                    $prod_attr = unserialize($prod_meta_key_values['_product_attributes']);

                    $attributes_list = "";

                    foreach ($prod_attr as $prod_attr1) {

                        $attribute_terms = "";

                        if (isset($attributes_label[$prod_attr1['name']]) && isset($product_attributes[$prod_attr1['name']])) {
                            foreach ($category_id as $category_id1) {
                                if (isset($product_attributes[$prod_attr1['name']][$category_id1])) {
                                    $attribute_terms .= $product_attributes[$prod_attr1['name']][$category_id1] . ', ';    
                                }
                            }

                            if ($attribute_terms != "") {
                                $attribute_terms = substr($attribute_terms, 0, -2);
                            }

                            $attributes_list .= $attributes_label[$prod_attr1['name']] . ": [" . $attribute_terms . "]";
                        }
                        else {
                            $attributes_list .= $prod_attr1['name'] . ": [" . str_replace(" |", ",", $prod_attr1['value']) ."]";
                        }

                        if($attributes_list != "") {
                            $attributes_list .= "<br>";
                        }    
                    }

                    // $records[$i]['product_attributes'] = substr( $attributes_list, 0, -3);
                    $records[$i]['product_attributes'] = $attributes_list;
                } else {
                    $records[$i]['product_attributes'] = "";
                }

                

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
                


                if ($show_variation === true) {

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
                    } else {
                        $records[$i]['_regular_price'] = trim( $records[$i]['_regular_price'] );
                        if ( empty( $records[$i]['_regular_price'] ) ) {
                            $records[$i]['_regular_price'] = $records[$i]['_price'];
                        }
                    }

                    $products[$records[$i]['id']]['post_title'] = $records[$i]['post_title'];
                    $products[$records[$i]['id']]['variation'] = $variation_names;
                } elseif ($show_variation === false && SMPRO) {
                    if ($product_type[0] == 'variable') {
                        $records[$i]['_regular_price'] = $records[$i]['_min_variation_regular_price'];
                        $records[$i]['_sale_price'] = $records[$i]['_min_variation_sale_price'];
                    } else {
                        $records[$i]['_regular_price'] = trim( $records[$i]['_regular_price'] );
                        // if ( empty( $records[$i]['_regular_price'] ) ) {
                        //     $records[$i]['_regular_price'] = $records[$i]['_price'];
                        // }
                        $records[$i]['_sale_price'] = trim( $records[$i]['_sale_price'] );

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
				$search_condn = customers_query ( $_POST ['searchText'] );

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
            
          if (SMPRO == true && function_exists ( 'sm_woo_get_packing_slip' ) && $_POST['label'] == 'getPurchaseLogs'){
                    $log_ids_arr = json_decode ( stripslashes ( $_POST['log_ids'] ) );
                    if (is_array($log_ids_arr))
                    $log_ids = implode(', ',$log_ids_arr);
                    sm_woo_get_packing_slip( $log_ids, $log_ids_arr );
                }

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
            
        //Code for Sequential Orders compatibility    
        if (is_plugin_active ( 'woocommerce-sequential-order-numbers-pro/woocommerce-sequential-order-numbers.php' )) {
            $order_formatted = ", '_order_number_formatted'";
        } else {
            $order_formatted = "";
        }

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
                                                                                                                                                                '_order_shipping', '_order_discount', '_cart_discount', '_order_tax', '_order_shipping_tax', '_order_currency', 'coupons'". $order_formatted ."))";
			
			$group_by    = " GROUP BY posts.ID";
			$limit_query = " ORDER BY posts.ID DESC $limit_string ;";
			
			$where = " WHERE posts.post_type LIKE 'shop_order' 
					AND posts.post_status IN ('publish','draft','auto-draft')
                                        AND term_relationships.term_taxonomy_id IN ($terms_post)";
			
			if (isset ( $_POST ['fromDate'] )) {
                                
                $from_date = date('Y-m-d H:i:s',(int)strtotime($_POST ['fromDate']));
                
                $date_start = date('Y-m-d',(int)strtotime($_POST ['fromDate']));
                $date = date('Y-m-d',(int)strtotime($_POST ['toDate']));
                                
                if ( $date_start == $date && $date == date('Y-m-d')) {
                    $curr_time_gmt = date('H:i:s',time()- date("Z"));
                    $new_date = $date ." " . $curr_time_gmt;
                    $to_date = date('Y-m-d H:i:s',((int)strtotime($new_date)) + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS )) ;
                } else {
                    $to_date = $date . " 23:59:59";
                }

                if (SMPRO == true) {
					$where .= " AND posts.post_date BETWEEN '$from_date' AND '$to_date'";                                        
				}
			}
			
			if (isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
				$multiple_search_terms = explode( '\"', trim ( $_POST ['searchText'] ) );
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
                                // $multiple_search_terms = explode( '\"', $search_on );
                                if ( !empty( $multiple_search_terms ) ) {
                                    $query_terms .= " WHERE";
                                    foreach ( $multiple_search_terms as $search_status ) {
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

                                // Start: Query for searching product names in order 

                                if($_POST['SM_IS_WOO16'] == "false") {
                                    $query_product_names = "SELECT order_id
                                                            FROM {$wpdb->prefix}woocommerce_order_items";

                                    if ( !empty( $multiple_search_terms ) ) {
                                        $query_product_names .= " WHERE";
                                        foreach( $multiple_search_terms as $product_name ) {
                                            $product_name = trim( $product_name );
                                            if ( !empty( $product_name ) ) {
                                                $query_product_names .= " order_item_name LIKE '%$product_name%' OR";
                                            }
                                        }
                                        $query_product_names = trim( $query_product_names, ' OR' );
                                    }
                                    
                                } else {
                                    $query_product_names = "SELECT post_id
                                                            FROM {$wpdb->prefix}postmeta
                                                            WHERE meta_key LIKE '%_order_items%'";

                                    if ( !empty( $multiple_search_terms ) ) {
                                        $query_product_names .= " AND (";
                                        foreach ( $multiple_search_terms as $product_name ) {
                                            $product_name = trim( $product_name );
                                            if ( !empty( $product_name ) ) {
                                                $query_product_names .= " meta_value LIKE '%$product_name%' OR";
                                            }
                                        }
                                        $query_product_names = trim( $query_product_names, ' OR' );
                                        $query_product_names .= ")";
                                    }
                                    
                                }
                                
                                $result_product_ids = $wpdb->get_col( $query_product_names );
                                $num_product_ids = $wpdb->num_rows;

                                // End: Query for searching product names in order 
                                
                                //Query to get the post_id of the products whose sku code matches with the one type in the search text box of the Orders Module
                                $query_sku  = "SELECT post_id FROM {$wpdb->prefix}postmeta
                                              WHERE meta_key = '_sku'
                                                 AND meta_value like '%$search_on%'";
                                $result_sku = $wpdb->get_col ($query_sku);
                                $rows_sku       = $wpdb->num_rows;

                                //Code for handling the Search functionality of the Orders Module using the SKU code of the product
                                if ($rows_sku > 0) {
                                    
                                    if($_POST['SM_IS_WOO16'] == "false") {
                                        $query_order_by_sku = "SELECT order_id
                                                                    FROM {$wpdb->prefix}woocommerce_order_items AS woocommerce_order_items
                                                                    LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS woocommerce_order_itemmeta USING ( order_item_id )
                                                                    WHERE woocommerce_order_itemmeta.meta_key IN ( '_product_id', '_variation_id' )
                                                                    AND woocommerce_order_itemmeta.meta_value IN ( " . implode( ',', $result_sku ) . " )";

                                        $results_order_by_sku = $wpdb->get_col( $query_order_by_sku );
                                        $num_order_by_sku = $wpdb->num_rows;
                                        if ( $num_order_by_sku > 0 ) {
                                            $search_condn = " HAVING id IN ( ". implode( ',', $results_order_by_sku ) ." )";
                                        }
                                    } else {
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

                                } elseif ( $num_product_ids > 0 ) {

                                    $search_condn = " HAVING id IN ( ". implode( ',', $result_product_ids ) ." )";

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
				    $num_rows_order_items = $wpdb->num_rows;

                                    //code for formatting order items array

                                    if ( $num_rows_order_items > 0 ) {

                                        $order_items = array();

                                        foreach ( $results_order_items as $results_order_item ) {

                                            if ( !isset($order_items [$results_order_item['order_id']]) ) {
                                                $order_items [$results_order_item['order_id']] = array();
                                            }

                                            $order_items [$results_order_item['order_id']] [] = $results_order_item;
                                        }    
                                    }

				
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
                                    
                                    if ( count( $result_variation_ids ) > 0 ) {
                                        $query_variation_att = "SELECT postmeta.post_id AS post_id,
                                                                        GROUP_CONCAT(postmeta.meta_value
                                                                            ORDER BY postmeta.meta_id 
                                                                            SEPARATOR ',' ) AS meta_value
                                                                FROM {$wpdb->prefix}postmeta AS postmeta
                                                                WHERE postmeta.meta_key LIKE 'attribute_%'
                                                                    AND postmeta.post_id IN (". implode(",",$result_variation_ids) .")
                                                                GROUP BY postmeta.post_id";
    //                                                                                          
                                        $results_variation_att  = $wpdb->get_results ( $query_variation_att , 'ARRAY_A');
                                    }
                                    
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

                                    if ( is_array( $results_variation_att ) && count( $results_variation_att ) > 0 ) {
                                        
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
                                                        if (!empty($order_items[$data['id']])) {
                                                            foreach ( $order_items[$data['id']] as $order_item) {
                                                                $prod_meta_values = explode('###', $order_item ['meta_value'] );
                                                                $prod_meta_key = explode('###', $order_item ['meta_key'] );
                                                                if (count($prod_meta_values) != count($prod_meta_key))
                                                                    continue;
                                                                unset( $order_item ['meta_value'] );
                                                                unset( $order_item ['meta_key'] );

                                                                update_post_meta($index, $sku_detail, $meta_value);
                                                                
                                                                $prod_meta_key_values = array_combine($prod_meta_key, $prod_meta_values);

                                                                
                                                                // if ($data['id'] == $order_item['order_id']) {

                                                                    $data['details'] += $prod_meta_key_values['_qty'];
                                                                    $data['order_total_ex_tax'] += $prod_meta_key_values['_line_total'];

                                                                    $product_id = ( $prod_meta_key_values['_variation_id'] > 0 ) ? $prod_meta_key_values['_variation_id'] : $prod_meta_key_values['_product_id'];
                                                                    $sm_sku = get_post_meta( $product_id, '_sku', true );
                                                                    if ( ! empty( $sm_sku ) ) {
                                                                            $sku_detail = '[SKU: ' . $sm_sku . ']';
                                                                    } else {
                                                                            $sku_detail = '';
                                                                    }
                                                                    
                                                                    $variation_att = ( isset( $variation_att_all [$prod_meta_key_values['_variation_id']] ) && !empty( $variation_att_all [$prod_meta_key_values['_variation_id']] ) ) ? $variation_att_all [$prod_meta_key_values['_variation_id']] : '';
                                                                    
                                                                    $product_full_name = ( !empty( $variation_att ) ) ? $order_item['order_prod'] . ' (' . $variation_att . ')' : $order_item['order_prod'];
                                                                    $data['products_name'] .= $product_full_name.' '.$sku_detail.'['.__('Qty','smart-manager').': '.$prod_meta_key_values['_qty'].']['.__('Price','smart-manager').': '.($prod_meta_key_values['_line_total']/$prod_meta_key_values['_qty']).'], ';
                                                            
                                                                    $data['coupons'] = (isset($order_coupons[$order_item['order_id']])) ? $order_coupons[$order_item['order_id']] : "";

                                                                // }
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

                        $data['display_id'] = $data['id'];

                        //Code for Sequential Orders compatibility
                        if($order_formatted != "" && isset($postmeta['_order_number_formatted'])) {
                            $data['display_id'] = $postmeta['_order_number_formatted'];
                        }

							$records [] = array_merge ( $postmeta, $data );	
					}
				}

				unset($meta_value);
				unset($meta_key);
				unset($postmeta);
				unset($results);
			}
	}
	if (!isset($_POST['label']) && $_POST['label'] != 'getPurchaseLogs'){
        $encoded ['items'] = $records;
        $encoded ['totalCount'] = $num_records;
        unset($records);
        return $encoded;
    }
}

if ( !function_exists( 'get_attributes_value' ) ) {
    // Function to get specific attribute's value
    function get_attributes_value( $variation_ids, $attribute_name ) {
        if ( empty( $variation_ids ) || count( $variation_ids ) <= 0 || empty( $attribute_name ) ) {
            return array();
        }
        global $woocommerce, $wpdb;
        $results = $wpdb->get_results( "SELECT post_id AS variation_id, meta_value AS attribute_value
                                        FROM {$wpdb->prefix}postmeta WHERE post_id IN ( " . implode( ',', $variation_ids ) . " )
                                        AND meta_key LIKE 'attribute_$attribute_name'", "ARRAY_A" );
        if ( count( $results ) > 0 ) {
            $attribute_value = array();
            foreach ( $results as $result ) {
                $attribute_value[$result['variation_id']] = $result['attribute_value'];
            }
            return $attribute_value;
        }
        return array();
    }
}

// Searching a product in the grid
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getData') {

    //Code to handle get_data for Coupons dashboard
    if (isset ( $_POST ['couponFields'] ) && $_POST ['couponFields'] != '') {

        $fields = json_decode ( stripslashes ($_POST['couponFields']), true);
        $coupon_details = $fields['coupon_dashbd'];

        $args = array(
            'post_type' => 'shop_coupon',
            );

        $result_coupons = new WP_Query( $args );
        // $result_coupons = query_posts( $args );

        $temp = array();
        $cnt = 0;

        while ($result_coupons->have_posts()) {
            
            $result_coupons->the_post();
            $temp[$cnt] = array();
            $temp[$cnt] ['id'] = get_the_ID();
            
            foreach ($coupon_details['column']['items'] as $meta_key) {

                if ($meta_key['table'] == 'posts') {
                    $post = get_post($temp[$cnt] ['id'], ARRAY_A);
                    $temp[$cnt] [$meta_key['value']] = $post[$meta_key['value']];   
                } else if ($meta_key['table'] == 'postmeta') {
                    $temp[$cnt] [$meta_key['value']] = get_post_meta(get_the_ID(),$meta_key['value'],true);
                }

            }       

            $temp[$cnt] ['post_status'] = get_post_status();
            $cnt++;
            
        }

        $encoded['items'] = $temp;
        $encoded ['totalCount'] = $cnt;

    } else {
        $encoded = get_data_woo ( $_POST, $offset, $limit );

    }

     
   // ob_clean("ob_gzhandler");

    header('X-Frame-Options: GOFORIT');

    while(ob_get_contents()) {
        ob_clean();
    }

    echo json_encode ( $encoded );
	unset($encoded);
    exit;
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'state') {

        global $current_user , $wpdb;

        $state_nm = array("dashboardcombobox", "Products", "Customers", "Orders","incVariation");

        for ($i=0;$i<sizeof($state_nm);$i++) {
            $stateid = "_sm_".$current_user->user_email."_".$state_nm[$i];

            $query_state  = "SELECT option_value
                             FROM {$wpdb->prefix}options
                             WHERE option_name like '$stateid'";
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
                
                $state_apply = ( isset($_POST[$state_nm[$i]]) ) ? $_POST[$state_nm[$i]] : null; // For WP_DEBUG
                
                $query_state = "INSERT INTO {$wpdb->prefix}options (option_name,option_value) values ('$stateid','$state_apply')";
                $result_state =  $wpdb->query ( $query_state );
                
                $state[$state_nm[$i]] = $state_apply;
            }

        }

        if ($_POST ['op'] == 'get' ) {   
            echo json_encode ($state);
        }
        exit;
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
                $columns_header['category']                 = __('Category / Group', $sm_domain);
				$columns_header['product_attributes'] 		= __('Attributes', $sm_domain);
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
				$columns_header['display_id'] 				= __('Order ID', $sm_domain);
				$columns_header['date'] 					= __('Order Date', $sm_domain);
				$columns_header['_billing_first_name'] 		= __('Billing First Name', $sm_domain);
				$columns_header['_billing_last_name'] 		= __('Billing Last Name', $sm_domain);
				$columns_header['_billing_email'] 			= __('Billing E-mail ID', $sm_domain);
                $columns_header['_billing_phone'] 			= __('Billing Phone Number', $sm_domain);
                $columns_header['_order_shipping'] 			= __('Order Shipping', $sm_domain);
                $columns_header['_order_discount'] 			= __('Order Discount', $sm_domain);
                $columns_header['_cart_discount'] 			= __('Cart Discount', $sm_domain);
                $columns_header['coupons'] 			        = __('Coupons Used', $sm_domain);
                $columns_header['_order_tax'] 			    = __('Order Tax', $sm_domain);
                $columns_header['_order_shipping_tax'] 		= __('Order Shipping Tax', $sm_domain);
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
				$columns_header['order_note'] 		        = __('Order Notes', $sm_domain);
			break;
	}

	$file_data = export_csv_woo ( $active_module, $columns_header, $data );
	
	header("Content-type: text/x-csv; charset=UTF-8"); 
	header("Content-Transfer-Encoding: binary");
	header("Content-Disposition: attachment; filename=".$file_data['file_name']); 
	header("Pragma: no-cache");
	header("Expires: 0");
		
//	ob_clean();

    while(ob_get_contents()) {
        ob_clean();
    }

        echo $file_data['file_content'];
		


	exit;
}
//Pro Version
function is_foreachable( $array ) {
    if ( ( is_array( $array ) || is_object( $array ) ) && count( $array ) > 0 ) {
        return true;        
    }
    return false;
}
function variable_price_sync ($id) {
    //$ids = explode ( ',', $id_variation );
    global $wpdb;
    $parent_ids = array();

    if(is_array($id)){
        $post_id = implode(",",$id);
        $id = -1;
    }

    if($id == 0 || $id == -1){
    // To collect unique parent from all variation ids
        if ($id == 0) {
            $query = "SELECT distinct post_parent as id from {$wpdb->prefix}posts WHERE post_type='product_variation'";
            $parent_ids = $wpdb->get_col ( $query );
        }
        elseif ($id == -1) {       
            $query = "SELECT distinct post_parent as id from {$wpdb->prefix}posts WHERE post_type='product_variation' AND id IN ($post_id)";
            $parent_ids = $wpdb->get_col ( $query );
        }

    // To be called only for parent product for price sync
    for( $i=0; $i<sizeof($parent_ids); $i++ ){
            variable_product_price_sync($parent_ids[$i]);
    }
    }
    else{
        variable_product_price_sync($id);
    }
    return $i;
}
function variable_product_price_sync($id) {
    global $woocommerce,$wpdb;

    $parent=get_post_custom($id );

    $query="SELECT id from {$wpdb->prefix}posts WHERE post_type='product_variation' AND post_parent =$id ORDER by id DESC";
    $children = $wpdb->get_col ( $query );

    if ($children) {

        if ($_POST['SM_IS_WOO16'] == "true") {
            $query = "SELECT GROUP_CONCAT(meta_value order by meta_id SEPARATOR '###') AS meta_value,
                    GROUP_CONCAT(meta_key order by meta_id SEPARATOR '###') AS meta_key
                  FROM {$wpdb->prefix}postmeta WHERE meta_key IN ('_price','_sale_price')
                    AND post_id IN (".implode(",", $children).")
                    GROUP BY post_id
                    ORDER by post_id DESC";
            $result = $wpdb->get_results($query,'ARRAY_A');
        }
        else {
            $query = "SELECT GROUP_CONCAT(meta_value order by meta_id SEPARATOR '###') AS meta_value,
                    GROUP_CONCAT(meta_key order by meta_id SEPARATOR '###') AS meta_key
                  FROM {$wpdb->prefix}postmeta WHERE meta_key IN ('_regular_price','_sale_price')
                    AND post_id IN (".implode(",", $children).")
                    GROUP BY post_id
                    ORDER by post_id DESC";
            $result = $wpdb->get_results($query,'ARRAY_A');   
        }
    
        $parent['min_variation_price'] = $parent['min_variation_regular_price'] = $parent['min_variation_sale_price'] = $parent['max_variation_price'] = $parent['max_variation_regular_price'] = $parent['max_variation_sale_price'] = '';

        for ( $i=0;$i<sizeof($children);$i++ ) {

            
            $prod_meta_values = explode ( '###', $result[$i]['meta_value'] );
            $prod_meta_key    = explode ( '###', $result[$i]['meta_key']);
            
            if ( count($prod_meta_values) != count($prod_meta_key) ) continue;
            unset ( $records[$i]['prod_othermeta_value'] );
            unset ( $records[$i]['prod_othermeta_key'] );   
            $child = array_combine ( $prod_meta_key, $prod_meta_values );
            
            if ($_POST['SM_IS_WOO16'] == "true") {
                $child_price    = trim($child['_price']);
            }
            else {
                $child_price    = trim($child['_regular_price']);
            }
            
            $child_sale_price   = trim($child['_sale_price']);
            
            // Low price

            if (!is_numeric($parent['min_variation_regular_price']) || $child_price < $parent['min_variation_regular_price']) $parent['min_variation_regular_price'] = $child_price;
            if ($child_sale_price!=='' && (!is_numeric($parent['min_variation_sale_price']) || $child_sale_price < $parent['min_variation_sale_price'])) $parent['min_variation_sale_price'] = $child_sale_price;

            // High price
            if (!is_numeric($parent['max_variation_regular_price']) || $child_price > $parent['max_variation_regular_price']) $parent['max_variation_regular_price'] = $child_price;
            if ($child_sale_price!=='' && (!is_numeric($parent['max_variation_sale_price']) || $child_sale_price > $parent['max_variation_sale_price'])) $parent['max_variation_sale_price'] = $child_sale_price;

        }
        $parent['min_variation_price'] = ($parent['min_variation_sale_price']==='' || $parent['min_variation_regular_price'] < $parent['min_variation_sale_price']) ? $parent['min_variation_regular_price'] : $parent['min_variation_sale_price'];
        $parent['max_variation_price'] = ($parent['max_variation_sale_price']==='' || $parent['max_variation_regular_price'] > $parent['max_variation_sale_price']) ? $parent['max_variation_regular_price'] : $parent['max_variation_sale_price'];
    }

    update_post_meta( $id, '_price', $parent['min_variation_price'] );
    update_post_meta( $id, '_min_variation_price', $parent['min_variation_price'] );
    update_post_meta( $id, '_max_variation_price', $parent['max_variation_price'] );
    update_post_meta( $id, '_min_variation_regular_price', $parent['min_variation_regular_price'] );
    update_post_meta( $id, '_max_variation_regular_price', $parent['max_variation_regular_price'] );
    update_post_meta( $id, '_min_variation_sale_price', $parent['min_variation_sale_price'] );
    update_post_meta( $id, '_max_variation_sale_price', $parent['max_variation_sale_price'] );

    if ( $parent['min_variation_price'] !== '' ) $woocommerce->clear_product_transients( $id );
}

function get_price($regular_price, $sale_price, $sale_price_dates_from, $sale_price_dates_to) {
        // Get price if on sale
        if ($sale_price && $sale_price_dates_to == '' && $sale_price_dates_from == '') {
            $price = $sale_price;
        } else { 
            $price = $regular_price;
        }   

        if ($sale_price_dates_from && strtotime($sale_price_dates_from) < strtotime('NOW')) {
            $price = $sale_price;
        }
        
        if ($sale_price_dates_to && strtotime($sale_price_dates_to) < strtotime('NOW')) {
            $price = $regular_price;
        }
    
    return $price;
}

function woo_insert_update_data($post) {
    global $wpdb,$woocommerce;
        $_POST = $post;  
          // Fix: PHP 5.4
    $editable_fields = array(
        '_billing_first_name' , '_billing_last_name' , '_billing_email', '_billing_address_1', '_billing_address_2', '_billing_city', '_billing_state',
        '_billing_country','_billing_postcode', '_billing_phone',
        '_shipping_first_name', '_shipping_last_name', '_shipping_address_1', '_shipping_address_2',
        '_shipping_city', '_shipping_state', '_shipping_country','_shipping_postcode', 'order_status'
    );
        $new_product = json_decode($_POST['edited']);
      
        $result = array(
            'productId' => array()
        );
        // To get distinct meta_key for Simple Products. => Executed only once
        $post_meta_info = $wpdb->get_col( "SELECT distinct postmeta.meta_key FROM {$wpdb->prefix}postmeta AS postmeta INNER JOIN {$wpdb->prefix}posts AS posts on posts.ID = postmeta.post_id WHERE posts.post_type='product' AND posts.post_status IN ('publish','draft')" );
        // To get distinct meta_key for Child Products i.e. Variations. => Executed only once
        $post_meta_info_variations = $wpdb->get_col( "SELECT distinct postmeta.meta_key FROM {$wpdb->prefix}postmeta AS postmeta INNER JOIN {$wpdb->prefix}posts AS posts on posts.ID = postmeta.post_id WHERE posts.post_type='product_variation' AND posts.post_status IN ('publish','draft') AND posts.post_parent > 0" );
                
        // meta_key required for new products, that are entered through Smart Manager   
            if (count($post_meta_info) <= 0 || count($post_meta_info) < 23) {
                            $post_meta_info = array(
                    '_edit_last','_edit_lock', '_regular_price',
                    '_sale_price', '_weight', '_length', '_width' ,
                                        '_height', '_tax_status', '_tax_class',
                                        '_stock_status', '_visibility', '_featured',
                                        '_sku', '_product_attributes', '_downloadable',
                                        '_virtual', '_sale_price_dates_from',
                                        '_sale_price_dates_to', '_price',
                                        '_stock', '_manage_stock', '_backorders');
                            
            }

    if( is_foreachable( $new_product ) ) {  
        foreach ($new_product as $obj){
            if($_POST ['active_module'] == 'Products') {

                            $price = get_price($obj->_regular_price, $obj->_sale_price, $obj->_sale_price_dates_from, $obj->_sale_price_dates_to);
                            if ( isset ( $obj->id ) && $obj->id != '' ) {
                                    $product_custom_fields = get_post_custom ( $obj->id );                                          // woocommerce uses this method to load product's details before creating WooCommerce Product Object
                                    $post = get_post ( $obj->id );                                                                  // woocommerce load posts from cache
                                    $terms = wp_get_object_terms( $obj->id, 'product_type', array('fields' => 'names') );

                                    // woocommerce gets product_type using this method
                                    $product_type = (isset($terms[0])) ? sanitize_title($terms[0]) : 'simple';
                                    if ( $product_type == 'variable' ) {                                                            // To unset price fields for Parent Products having variations
                                            $obj->_regular_price = '';
                                            $obj->_sale_price    = '';
                                            $obj->_price         = $price       = ($product_custom_fields['_min_variation_sale_price'][0]==='' || $product_custom_fields['_min_variation_regular_price'][0] < $product_custom_fields['_min_variation_sale_price'][0]) ? $product_custom_fields['_min_variation_regular_price'][0] : $product_custom_fields['_min_variation_sale_price'][0];
                                    }
                            }
                            else { //to not include current date for sales price for new product
                                $obj->_sale_price_dates_from = '';
                                $obj->_sale_price_dates_to = '';
                                $price = get_price($obj->_regular_price, $obj->_sale_price, $obj->_sale_price_dates_from, $obj->_sale_price_dates_to);
                            }
                            
                            if(!(empty($obj->post_parent) || $obj->post_parent == '')){
                                $id = $obj->post_parent;
                                $product_type_parent = wp_get_object_terms($id, 'product_type', array('fields' => 'slugs'));
                            }

                            if ($_POST['SM_IS_WOO16'] == "true") {
                                if ( ($obj->post_parent > 0) && ($product_type_parent[0] != "grouped")) {
                                    $price = $obj->_regular_price;
                                }   
                            }

                            // create an array to be used for updating product's details. add modified value from Smart Manager & rest same as in original post
                            $postarr = array(
                                    'ID'                        => isset($obj->id) ? $obj->id : '',
                                    'post_author'               => isset($post->post_author) ? $post->post_author : '',
                                    'post_content'              => isset($obj->post_content) ? $obj->post_content : '',
                                    'post_title'                => isset($obj->post_title) ? $obj->post_title : '',
                                    'post_excerpt'              => isset($obj->post_excerpt) ? $obj->post_excerpt : '',
                                    'post_date'                 => isset($post->post_date) ? $post->post_date : '',
                                    'post_date_gmt'             => isset($post->post_date_gmt) ? $post->post_date_gmt : '',
                                    'post_status'               => isset($obj->post_status) ? $obj->post_status : '',
                                    'comment_status'            => isset($post->comment_status) ? $post->comment_status : 'open',
                                    'ping_status'               => isset($post->ping_status) ? $post->ping_status : 'open',
                                    'post_parent'               => isset($obj->post_parent) ? $obj->post_parent : $post->post_parent,
                                    'guid'                      => isset($post->guid) ? $post->guid : site_url().'/?post_type=product&p='.$post->ID,
                                    'menu_order'                => isset($post->menu_order) ? $post->menu_order : 0,
                                    'post_type'                 => isset($post->post_type) ? $post->post_type : 'product',
                                    'comment_count'             => isset($post->comment_count) ? $post->comment_count : 0,
                                    'ancestors'                 => isset($post->ancestors) ? $post->ancestors : Array(),
                                    'filter'                    => isset($post->filter) ? $post->filter : 'raw',
                                    '_product_attributes'       => isset($product_custom_fields['_product_attributes'][0]) ? $product_custom_fields['_product_attributes'][0] : serialize(array()),
                                    'user_ID'                   => 1,
                                    'action'                    => 'editpost',
                                    'originalaction'            => 'editpost',
                                    'original_post_status'      => 'auto-draft',
                                    'auto_draft'                => 1,
                                    'post_ID'                   => $obj->id,
                                    'hidden_post_status'        => 'draft',
                                    'hidden_post_visibility'    => 'public',
                                    '_visibility'               => isset($obj->_visibility) ? $obj->_visibility : 'visible',
                                    'original_publish'          => 'Publish',
                                    'publish'                   => 'Publish',
                                    'newproduct_cat'            => 'New Product Category Name',
                                    'newproduct_cat_parent'     => -1,
                                    'content'                   => isset($obj->post_content) ? $obj->post_content : $post->post_content,
                                    'product-type'              => isset($product_type) ? $product_type : 'simple',
                                    '_virtual'                  => isset($product_custom_fields['_virtual'][0]) ? $product_custom_fields['_virtual'][0] : 'no',
                                    '_downloadable'             => isset($product_custom_fields['_downloadable'][0]) ? $product_custom_fields['_downloadable'][0] : 'no',
                                    '_featured'                 => isset($product_custom_fields['_featured'][0]) ? $product_custom_fields['_featured'][0] : 'no',
                                    '_sku'                      => isset($obj->_sku) ? $obj->_sku : '',
//                      '_price'                    => ( ($obj->post_parent == 0 && $obj->product_type != 'variable') || ($product_type_parent[0] == "grouped") ) ? $price : $obj->_regular_price,
                                    '_price'                    =>  $price,
                                    '_regular_price'            => isset($obj->_regular_price) ? $obj->_regular_price : '',
                                    '_sale_price'               => isset($obj->_sale_price) ? $obj->_sale_price : '',
                                    '_sale_price_dates_from'    => isset($obj->_sale_price_dates_from) ? strtotime($obj->_sale_price_dates_from) : '',
                                    '_sale_price_dates_to'      => isset($obj->_sale_price_dates_to) ? strtotime($obj->_sale_price_dates_to) : '',
                                    '_weight'                   => isset($obj->_weight) ? $obj->_weight : '',
                                    '_length'                   => isset($obj->_length) ? $obj->_length : '',
                                    '_width'                    => isset($obj->_width) ? $obj->_width : '',
                                    '_height'                   => isset($obj->_height) ? $obj->_height : '',
                                    '_tax_status'               => isset($obj->_tax_status) ? $obj->_tax_status : 'taxable',
                                    '_tax_class'                => isset($product_custom_fields['_tax_class'][0]) ? $product_custom_fields['_tax_class'][0] : '',
                                    '_manage_stock'             => isset($product_custom_fields['_manage_stock'][0]) ? $product_custom_fields['_manage_stock'][0] : '',
                                    '_stock_status'             => isset($product_custom_fields['_stock_status'][0]) ? $product_custom_fields['_stock_status'][0] : '',
                                    '_stock'                    => isset($obj->_stock) ? $obj->_stock : '',
                                    '_backorders'               => isset($product_custom_fields['_backorders'][0]) ? $product_custom_fields['_backorders'][0] : 'no',
                                    'excerpt'                   => isset($obj->post_excerpt) ? $obj->post_excerpt : $post->post_excerpt,
                                    'advanced_view'             => 1
                            );

                            if ( ($obj->post_parent == 0 && $obj->product_type != 'variable') || ($product_type_parent[0] == "grouped") ) {
                                    $post_id = wp_insert_post($postarr);
                                    $post_meta_key = $post_meta_info;

                            } else {
                                    $parent_id = $postarr['post_parent'];
                                    $post_id = $postarr['ID'];
                                    $post_meta_key = $post_meta_info_variations;

                            }

                            array_push ( $result ['productId'], $post_id );
                            foreach ($post_meta_key as $object) {

                                // ================================================================================================
                                // Code for enabling negative values for inline editing
                                // ================================================================================================

                                // if ( $object == '_sale_price' || $object == '_price' || $object == '_regular_price' ) {
                                //     update_post_meta($wpdb->_real_escape($post_id), $wpdb->_real_escape($object), $wpdb->_real_escape($postarr[$object]) );
                                //     continue;
                                // }

                                // ================================================================================================

                                

                                if ( isset ( $postarr[$object] ) && $postarr[$object] > -1 ) {              // to skip query for blank value
                                    //Code to handle the condition for the attribute visibility on product page issue while save action
                                    if ($object == '_product_attributes' && isset($product_custom_fields['_product_attributes'][0])) {
                                        continue;
                                    }

                                    if(empty($obj->id) || $obj->id == ''){
                                            $query = "INSERT INTO {$wpdb->prefix}postmeta(post_id,meta_key,meta_value) values(" . $wpdb->_real_escape($post_id) . ", '" . $wpdb->_real_escape($object) . "', '".$wpdb->_real_escape($postarr[$object])."')";                                                                        
                                            $var= $wpdb->query($query);
                                            wp_set_object_terms($post_id, 'simple', 'product_type');
                                    }
                                    else {
                                            //$query = "UPDATE {$wpdb->prefix}postmeta SET meta_value = '".$wpdb->_real_escape($postarr[$object])."' WHERE post_id = " . $wpdb->_real_escape($post_id) . " AND meta_key = '" . $wpdb->_real_escape($object) . "'";
                                            update_post_meta($wpdb->_real_escape($post_id), $wpdb->_real_escape($object), $wpdb->_real_escape($postarr[$object]) );
                                    }
                                    
                                }
                            }
                            //Code For updating the parent price of the product
                            if ($parent_id > 0) {
                                variable_price_sync($parent_id);
                            }

            } 
            elseif ($_POST ['active_module'] == 'Orders') {
                foreach ( $obj as $key => $value ) {
                    if ( in_array( $key, $editable_fields ) ) {
                        if ( $key == 'order_status' ) {
                            $term_taxonomy_id = get_term_taxonomy_id ( $value );
                            $query = "UPDATE {$wpdb->prefix}term_relationships SET term_taxonomy_id = " . $wpdb->_real_escape($term_taxonomy_id) . " WHERE object_id = " . $wpdb->_real_escape($obj->id) . ";";

                            if ( $value == 'processing' || $value == 'completed' ) {
                                    $order = new WC_Order( $obj->id );
                                    $order->update_status( $value );
                            }
                        } else {
                                $query = "UPDATE {$wpdb->prefix}postmeta SET meta_value = '".$wpdb->_real_escape($value)."' WHERE post_id = " . $wpdb->_real_escape($obj->id) . " AND meta_key = '" . $wpdb->_real_escape($key) . "';";
                        }
                        $wpdb->query($query);
                    }   
                }
            }

            elseif ($_POST ['active_module'] == 'Customers') {

                //Query to get the email and customer_user for all the selected ids
                $query_email = "SELECT DISTINCT(GROUP_CONCAT( meta_value
                                     ORDER BY meta_id SEPARATOR '###' ) )AS meta_value,
                                     GROUP_CONCAT(distinct meta_key
                                     ORDER BY meta_id SEPARATOR '###' ) AS meta_key
                                FROM {$wpdb->prefix}postmeta 
                                WHERE meta_key in ('_billing_email','_customer_user') 
                                AND post_id=$wpdb->_real_escape($obj->id)";

                $result_email = $wpdb->get_results ( $query_email, 'ARRAY_A' );                     

                $email="";
                $users="";

                for ( $i=0;$i<sizeof($result_email);$i++ ) {
                    $meta_key = explode ("###",$result_email[$i]['meta_key']);
                    $meta_value = explode ("###",$result_email[$i]['meta_value']);

                    $postmeta[$i] = array_combine ($meta_key,$meta_value);

                    $email[$i] = $postmeta [$i]['_billing_email'];
                    $users[$i] = $postmeta [$i]['_customer_user'];

                    unset($meta_key);
                    unset($meta_value);
                }

                $email = "'" . implode ("','",$email) . "'";
                $users  = implode (",",$users);

                //Query for getting al the post ids using the email id
                if ($users == 0) {
                    $query_ids="SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_value = $email";        
                    $id=implode(", ",$wpdb->get_col($query_ids));
                }

                foreach ( $obj as $key => $value ) {
                    if ( in_array( $key, $editable_fields ) ) {
                        if ($users==0) {
                            $query = "UPDATE {$wpdb->prefix}postmeta SET meta_value = '".$wpdb->_real_escape($value)."' WHERE post_id IN ($id) AND meta_key = '" . $wpdb->_real_escape($key) . "';";
                           
                        }
                        elseif ($users>0) {
                            $key = substr($key,1); //removing the first underscore from the column name as the columns for p_usermeta are different from that of wp_postmeta

                            //Code for updating the email of the Customer in the wp_users Table
                            if ($key == "billing_email"){
                                $query = "UPDATE {$wpdb->prefix}users SET user_email = '".$wpdb->_real_escape($value)."' WHERE id IN ($users) ;";
                                $wpdb -> query ( $query );
                            }

                            $query = "UPDATE {$wpdb->prefix}usermeta SET meta_value = '".$wpdb->_real_escape($value)."' WHERE user_id IN ($users) AND meta_key = '" . $wpdb->_real_escape($key) . "';";
                        }
                        $wpdb->query($query);
                    }   
                }
            }
            if (empty($obj->id) || $obj->id == '') {
                if (!isset($result['inserted'])) {
                    $result['inserted'] = 1;
                    $result['insertCnt'] = 1;
                } else {
                    $result['insertCnt']++;
                }
            } else {
                if (!isset($result['updated'])) {
                    $result['updated'] = 1;
                    $result['updateCnt'] = 1;
                } else {
                    $result['updateCnt']++;
                }
            }
        }
    } else {
            $result = array('inserted' => 0, 'insertCnt' => 0, 'updated' => 0, 'updateCnt' => 0);
    }
        
        //Clearing the transients to handle the proper functioning of the widgets
        $woocommerce->clear_product_transients();
        return $result;
}




// For insert updating product in woo.
if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'saveData') {

        //For encoding the string in UTF-8 Format
//                $charset = "EUC-JP, ASCII, UTF-8, ISO-8859-1, JIS, SJIS";
        $charset = ( get_bloginfo('charset') === 'UTF-8' ) ? null : get_bloginfo('charset');
        if (!(is_null($charset))) {
            $_POST['edited'] = mb_convert_encoding(stripslashes($_POST['edited']),"UTF-8",$charset);

            if ( $_POST['active_module'] == "Coupons" ) {
                $_POST['table'] = mb_convert_encoding(stripslashes($_POST['table']),"UTF-8",$charset);
                $_POST['edited_ids'] = mb_convert_encoding(stripslashes($_POST['edited_ids']),"UTF-8",$charset);
            }

        }
        else {
            $_POST['edited'] = stripslashes($_POST['edited']);

            if ( $_POST['active_module'] == "Coupons" ) {
                $_POST['table'] = stripslashes($_POST['table']);                        
                $_POST['edited_ids'] = stripslashes($_POST['edited_ids']);                        
            }
        }

        if ( $_POST['active_module'] == "Coupons" ) {
            $edit_fields = json_decode($_POST['edited'], true);
            $field_update_table = json_decode($_POST['table'], true);
            $edited_ids = json_decode($_POST['edited_ids'], true);

            $i = 0;

            foreach ( $edit_fields as $edit_field ) {
                $id = $edited_ids[$i];

                foreach ( $edit_field as $key => $value ) {

                    if ($field_update_table[$key] == "posts") {

                        $post = array(
                                'ID' => $id,
                                $key => $value
                            );

                        wp_update_post( $post );

                    } elseif ($field_update_table[$key] == "postmeta") {
                        update_post_meta( $id, $key, $value );
                    }
                }
                $i++;    
            }

            $result ['updated'] = 1;
            $result ['updateCnt'] = sizeof($edited_ids);

        } else {
            $result = woo_insert_update_data ( $_POST );
        }
			
		
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
//	ob_clean();

        while(ob_get_contents()) {
            ob_clean();
        }

        echo json_encode ( $encoded );

        exit;
}



if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'dupData') {

    global $wpdb;

    $dupCnt = 0;
    $activeModule = substr( $_POST ['active_module'], 0, -1 );
    $data_temp = (isset($_POST ['data'])) ? json_decode ( stripslashes ( $_POST ['data'] ) ) : '';

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

        exit;
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

            if ($_POST ['incvariation'] == "true") {

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
        exit;
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
	// ob_clean();

        while(ob_get_contents()) {
            ob_clean();
        }

        echo json_encode ( $encoded );

        exit;
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'getRolesDashboard') {
	global $wpdb, $current_user;

    if (!function_exists('wp_get_current_user')) {
        require_once (ABSPATH . 'wp-includes/pluggable.php'); // Sometimes conflict with SB-Welcome Email Editor
    }

	$current_user = wp_get_current_user();
	if ( SMPRO != true || $current_user->roles[0] == 'administrator') {
		$results = array( 'Products', 'Customers_Orders' );
	} else {
		$results = get_dashboard_combo_store();
	}
	// ob_clean("ob_gzhandler");

    while(ob_get_contents()) {
        ob_clean();
    }
     
    echo json_encode ( $results );

    exit;
}

function customers_query($search_text = '') {
    global $wpdb;
    if ($search_text) {

        $query_users = "SELECT id FROM $wpdb->users WHERE user_email LIKE '$search_text%'";
        $result_users   =  $wpdb->get_col ( $query_users );
        $num_rows_users =  $wpdb->num_rows;

        if ($num_rows_users > 0) {
            $query = "SELECT post_id FROM {$wpdb->prefix}postmeta
                        WHERE  meta_key LIKE  '_customer_user'
                            AND meta_value IN (" . implode(",",$result_users) . ")";
            $result =  $wpdb->get_col ( $query );

            $codn_user = "OR posts.ID IN (" . implode(",",$result) . ")";
        }
        else {
            $codn_user = "";
        }

        $search_text = $wpdb->_real_escape ( $_POST ['searchText'] );
        $search_condn = " HAVING id  LIKE '$search_text%'
                  OR meta_value LIKE '%$search_text%'
                  $codn_user";
    }
    return $search_condn;
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
	$query = "SELECT tt.term_taxonomy_id, t.name, wat.attribute_type 
                FROM {$wpdb->prefix}terms as t 
                    JOIN {$wpdb->prefix}term_taxonomy as tt on (t.term_id = tt.term_id) 
                    LEFT JOIN {$wpdb->prefix}woocommerce_attribute_taxonomies as wat on (concat('pa_',wat.attribute_name) = tt.taxonomy) 
                WHERE tt.taxonomy = '$attribute_suffix' ";
	$results = $wpdb->get_results ($query, 'ARRAY_A');
    $rows = $wpdb->num_rows;

    if($rows == 0) {
        $query_attribute_text = "SELECT attribute_type 
                                FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
                                WHERE attribute_name LIKE '$attribute_name'";
        $results_attribute_text = $wpdb->get_col ($query_attribute_text); 
        $rows_attribute_text = $wpdb->num_rows;           
    }


	$terms_combo_store = array();
	$term_count = 0;
    if ( isset( $results[0]['attribute_type'] ) && ( ($results[0]['attribute_type'] != 'text' && $_POST['action_name'] == 'groupAttributeAdd') || $_POST['action_name'] == 'groupAttributeRemove') ) {
        $terms_combo_store [$term_count] [] = 'all';
        $terms_combo_store [$term_count] [] = 'All';
        $terms_combo_store [$term_count] [] = 'select';
        $term_count++;
    }
	foreach ( $results as $result ) {
		$terms_combo_store [$term_count] [] = $result['term_taxonomy_id'];
        $terms_combo_store [$term_count] [] = $result['name'];
		$terms_combo_store [$term_count] [] = $result['attribute_type'];
		$term_count++;
	}
	
    if ( $rows_attribute_text > 0 ) {
        $term_count = 0;
        $terms_combo_store [$term_count] [] = 'all';
        $terms_combo_store [$term_count] [] = 'All';
        $terms_combo_store [$term_count] [] = 'text';        
    }

	// ob_clean();

    while(ob_get_contents()) {
        ob_clean();
    }

        echo json_encode ( $terms_combo_store );

        exit;
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
	// ob_clean();

    while(ob_get_contents()) {
        ob_clean();
    }

        echo json_encode ( $regions );

        exit;
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'editImage') {
	$woo_default_image = WP_PLUGIN_URL . '/smart-reporter-for-wp-e-commerce/resources/themes/images/woo_default_image.png';
	$post_thumbnail_id = get_post_thumbnail_id( $_POST ['id'] );
	$image = isset( $post_thumbnail_id ) ? wp_get_attachment_image_src( $post_thumbnail_id, 'admin-product-thumbnails' ) : '';
	$thumbnail = ( $image[0] != '' ) ? $image[0] : '';
	// ob_clean();

    while(ob_get_contents()) {
        ob_clean();
    }
    
        echo json_encode ( $thumbnail );

        exit;
}
ob_end_flush();
?>
