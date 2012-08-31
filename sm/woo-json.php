<?php 
if ( ! defined('ABSPATH') ) {
    include_once ('../../../../wp-load.php');
}
load_textdomain( 'smart-manager', ABSPATH . 'wp-content/plugins/smart-manager-for-wp-e-commerce/languages/smart-manager-' . WPLANG . '.mo' );

$mem_limit = ini_get('memory_limit');
if(intval(substr($mem_limit,0,strlen($mem_limit)-1)) < 64 ){
	ini_set('memory_limit','128M'); 
}

$result = array ();
$encoded = array ();

$offset = (isset ( $_POST ['start'] )) ? $_POST ['start'] : 0;
$limit = (isset ( $_POST ['limit'] )) ? $_POST ['limit'] : 100;

// For pro version check if the required file exists
if (file_exists ( WP_CONTENT_DIR . '/plugins/smart-manager-for-wp-e-commerce/pro/woo.php' )) {
	define ( 'SMPRO', true );
	include_once (WP_CONTENT_DIR . '/plugins/smart-manager-for-wp-e-commerce/pro/woo.php');
} else {
	define ( 'SMPRO', false );
}

function values( $arr ) {
    return $arr['id'];
}

// getting the active module
$active_module = $_POST ['active_module'];

function get_data_woo ( $_POST, $offset, $limit, $is_export = false ) {
	global $wpdb, $woocommerce, $post_status, $parent_sort_id, $order_by, $post_type, $variation_name, $from_variation, $parent_name, $attributes;
	
	// getting the active module
	$active_module = $_POST ['active_module'];
	
	if ( SMPRO == true ) variation_query_params ();
	
	// Restricting LIMIT for export CSV
	if ( $is_export === true ) {
		$limit_string = "";
		$image_size = "full";
	} else {
		$limit_string = "LIMIT $offset,$limit";
		$image_size = "thumbnail";
	}
	
	$wpdb->query ( "SET SESSION group_concat_max_len=9999" );// To increase the max length of the Group Concat Functionality
	
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

        $select = "SELECT SQL_CALC_FOUND_ROWS products.id,
					products.post_title,
					products.post_content,
					products.post_excerpt,
					products.post_status,
					products.post_parent,
					category,
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

            $query_terms = "SELECT slug FROM {$wpdb->prefix}terms WHERE name LIKE '%$search_on%' AND name IN ('" .implode("','",$attributes) . "');";
            $records_slug = $wpdb->get_col ( $query_terms );
            $rows = $wpdb->num_rows;
            
            $search_text = $search_ons;
            
            if($rows > 0){
                    $search_ons = $records_slug;
            }
            
            if ( is_array( $search_ons ) && ! empty( $search_ons ) ) {
                
				$search_condn = " HAVING ";
				foreach ( $search_ons as $search_on ) {
                                    
                                    if( $rows == 1 && $search_text != $search_ons ) {
                                        $query_ids1 = "SELECT GROUP_CONCAT(post_id ORDER BY post_id SEPARATOR ',') as id FROM {$wpdb->prefix}postmeta WHERE meta_value = '$search_on' AND meta_key like 'attribute_%';";
                                        $records_id1 = implode(",",$wpdb->get_col ( $query_ids1 ));
                                        
                                        $search_condn .= " products.id IN ($records_id1)";
                                    }
                                    
                                    else {
					$search_condn .= " concat(' ',REPLACE(REPLACE(post_title,'(',''),')','')) LIKE '%$search_on%'
						               OR post_content LIKE '%$search_on%'
						               OR post_excerpt LIKE '%$search_on%'
						               OR if(post_status = 'publish','Published',post_status) LIKE '$search_on%'
									   OR prod_othermeta_value LIKE '%$search_on%'
									   OR category LIKE '%$search_on%'
							           ";
					
					
                                    }
					$search_condn .= " OR";
				}
                                $search_condn_count = " AND(" . substr( $search_condn_count, 0, -2 ) . ")";
				$search_condn = substr( $search_condn, 0, -2 );
			} 
                        else {

                                if( $rows == 1 && $search_text != $search_ons ) {
                                        $query_ids1 = "SELECT GROUP_CONCAT(post_id ORDER BY post_id SEPARATOR ',') as id FROM {$wpdb->prefix}postmeta WHERE meta_value = '$search_on' AND meta_key like 'attribute_%';";
                                        $records_id1 = implode(",",$wpdb->get_col ( $query_ids1 ));

                                        $search_condn .= " products.id IN ($records_id1)";
				}
                                else{
				$search_condn = " HAVING concat(' ',REPLACE(REPLACE(post_title,'(',''),')','')) LIKE '%$search_on%'
					               OR post_content LIKE '%$search_on%'
					               OR post_excerpt LIKE '%$search_on%'
					               OR if(post_status = 'publish','Published',post_status) LIKE '$search_on%'
								   OR prod_othermeta_value LIKE '%$search_on%'
								   OR category LIKE '%$search_on%'
						           ";
			}
        }
		} 

		$from = "FROM {$wpdb->prefix}posts as products
						LEFT JOIN {$wpdb->prefix}postmeta as prod_othermeta ON (prod_othermeta.post_id = products.id and
						prod_othermeta.meta_key IN ('_regular_price','_sale_price','_sale_price_dates_from','_sale_price_dates_to','_sku','_stock','_weight','_height','_length','_width','_price','_thumbnail_id','_tax_status','_min_variation_regular_price','_min_variation_sale_price','_visibility','" . implode( "','", $variation ) . "') )
						
						LEFT JOIN
						(SELECT GROUP_CONCAT(wt.name ORDER BY wt.name) as category, wtr.object_id
						FROM  {$wpdb->prefix}term_relationships AS wtr  	 
						JOIN {$wpdb->prefix}term_taxonomy AS wtt ON (wtr.term_taxonomy_id = wtt.term_taxonomy_id and taxonomy = 'product_cat')
												
						JOIN {$wpdb->prefix}terms AS wt ON (wtt.term_id = wt.term_id)
						group by wtr.object_id) as prod_categories on (products.id = prod_categories.object_id OR products.post_parent = prod_categories.object_id)";
						
		$where	= " WHERE products.post_status IN $post_status
						AND products.post_type IN $post_type";

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

			for ($i = 0; $i < $num_rows; $i++){
				$prod_meta_values = explode ( '###', $records[$i]['prod_othermeta_value'] );
				$prod_meta_key    = explode ( '###', $records[$i]['prod_othermeta_key']);
				if ( count($prod_meta_values) != count($prod_meta_key) ) continue;
				unset ( $records[$i]['prod_othermeta_value'] );
				unset ( $records[$i]['prod_othermeta_key'] );
				$prod_meta_key_values = array_combine ( $prod_meta_key, $prod_meta_values );
                $product_type = wp_get_object_terms( $records[$i]['id'], 'product_type', array('fields' => 'slugs') );
                $records[$i]['category'] = ( ( $records[$i]['post_parent'] > 0 && $product_type[0] == 'simple' ) || ( $records[$i]['post_parent'] == 0 ) ) ? $records[$i]['category'] : '';			// To hide category name from Product's variations

                if(isset($prod_meta_key_values['_sale_price_dates_from']) && !empty($prod_meta_key_values['_sale_price_dates_from']))
					$prod_meta_key_values['_sale_price_dates_from'] = date('Y-m-d',(int)$prod_meta_key_values['_sale_price_dates_from']);
				if(isset($prod_meta_key_values['_sale_price_dates_to']) && !empty($prod_meta_key_values['_sale_price_dates_to']))
					$prod_meta_key_values['_sale_price_dates_to'] = date('Y-m-d',(int)$prod_meta_key_values['_sale_price_dates_to']);

                $records[$i] = array_merge((array)$records[$i],$prod_meta_key_values);
				$thumbnail = isset( $records[$i]['_thumbnail_id'] ) ? wp_get_attachment_image_src( $records[$i]['_thumbnail_id'], $image_size ) : '';
				$records[$i]['thumbnail'] = ( $thumbnail[0] != '' ) ? $thumbnail[0] : false;
				$records[$i]['_tax_status'] = ( ! empty( $prod_meta_key_values['_tax_status'] ) ) ? $prod_meta_key_values['_tax_status'] : '';

                if ( $show_variation === true && SMPRO ) {
                    if ( $records[$i]['post_parent'] != 0 ) {
                        $records[$i]['_regular_price'] = $records[$i]['_price'];
                        $variation_names = '';
                        
                        foreach ( $variation as $slug ) {
                            $variation_names .= ( isset( $attributes[$prod_meta_key_values[$slug]] ) && !empty( $attributes[$prod_meta_key_values[$slug]] ) ) ? $attributes[$prod_meta_key_values[$slug]] : ucfirst( $prod_meta_key_values[$slug] ) . ', ';
                        }
                        
                        $records[$i]['post_title'] = get_the_title( $records[$i]['post_parent'] ) . " - " . trim( $variation_names, ", " );
                    } else {
                        $records[$i]['_regular_price'] = "";
                        $records[$i]['_sale_price'] = "";
                    }
                } elseif ( $show_variation === false && SMPRO ) {
                    $records[$i]['_regular_price'] = $records[$i]['_min_variation_regular_price'];
                    $records[$i]['_sale_price'] = $records[$i]['_min_variation_sale_price'];
                } else {
                    $records[$i]['_regular_price'] = $records[$i]['_regular_price'];
                    $records[$i]['_sale_price'] = $records[$i]['_sale_price'];
                }

                unset ( $records[$i]['prod_othermeta_value'] );
				unset ( $records[$i]['prod_othermeta_key'] );
			}
        }
	} elseif ($active_module == 'Customers') {
		//BOF Customer's module
			if (SMPRO == true) {
				$search_condn = customers_query ( $_POST ['searchText'] );
			}

                        
            //Query for getting the max of post id for all the Guest Customers          
            $query_max_id="SELECT max(post_ID) as id
                           FROM {$wpdb->prefix}postmeta
                               JOIN {$wpdb->prefix}term_relationships AS term_relationships 
                                                        ON term_relationships.object_id = post_ID 
                                        JOIN {$wpdb->prefix}term_taxonomy AS term_taxonomy 
                                                        ON term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id 
                                        JOIN {$wpdb->prefix}terms AS terms 
                                                        ON term_taxonomy.term_id = terms.term_id 

                           WHERE meta_key IN ('_billing_email')
                           AND terms.name IN ('completed','processing')
                           AND post_ID IN (SELECT post_ID FROM {$wpdb->prefix}postmeta
                            WHERE meta_key ='_customer_user' AND meta_value=0)
                           GROUp BY meta_value
                           ORDER BY id desc";

            $result_max_id   =  $wpdb->get_col ( $query_max_id );
            
            //Query for getting the max of post id for all the Registered Customers
            $query_max_user="SELECT max(post_ID) as id
                           FROM {$wpdb->prefix}postmeta
                               JOIN {$wpdb->prefix}term_relationships AS term_relationships 
                                                        ON term_relationships.object_id = post_ID 
                                        JOIN {$wpdb->prefix}term_taxonomy AS term_taxonomy 
                                                        ON term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id 
                                        JOIN {$wpdb->prefix}terms AS terms 
                                                        ON term_taxonomy.term_id = terms.term_id 
                                                        
                           WHERE meta_key IN ('_customer_user')
                           AND meta_value>0
                           AND terms.name IN ('completed','processing')
                           GROUp BY meta_value";

            $result_max_user   =  $wpdb->get_col ( $query_max_user );
            
            for ( $i=0,$j=sizeof($result_max_id);$i<sizeof($result_max_user);$i++,$j++ ){
                $result_max_id[$j] = $result_max_user[$i];
            }

            $max_id = implode(",",$result_max_id);
            
            $customers_query = "SELECT SQL_CALC_FOUND_ROWS
                                     DISTINCT(GROUP_CONCAT( postmeta.meta_value
                                     ORDER BY postmeta.meta_id SEPARATOR '###' ) )AS meta_value,
                                     GROUP_CONCAT(distinct postmeta.meta_key
                                     ORDER BY postmeta.meta_id SEPARATOR '###' ) AS meta_key,
                                     date_format(max(posts.post_date),'%b %e %Y, %r') AS date

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
                                             FROM {$wpdb->prefix}users AS users
                                                   JOIN {$wpdb->prefix}usermeta AS usermeta
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
                            $postmeta[$j]['_customer_user']=$result_users [$i]['ID'];
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

            //Query for getting all the post ids for the Registered Users using the user id
            if(!(is_null($user_id))){
                $id    = implode(",",$user_id);
                $query_id = "SELECT meta_value, GROUP_CONCAT( post_id ORDER BY meta_value
							SEPARATOR ',' ) AS id
                            FROM `{$wpdb->prefix}postmeta`
                                JOIN {$wpdb->prefix}term_relationships AS term_relationships 
                                                ON term_relationships.object_id = post_ID 
                                JOIN {$wpdb->prefix}term_taxonomy AS term_taxonomy 
                                                ON term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id 
                                JOIN {$wpdb->prefix}terms AS terms 
                                                ON term_taxonomy.term_id = terms.term_id 
                            WHERE meta_value in ($id)
                            AND meta_key='_customer_user'
                            AND terms.name IN ('completed','processing')
                            GROUP BY meta_value
                            ORDER BY post_id ";


                $result_id =  $wpdb->get_results ( $query_id, 'ARRAY_A' );
            }
            
            //Query for getting all the post ids for the Guest Users using the email id
            if ( !( is_null( $user_email ) ) ) {
                $email = implode(",",$user_email);
                
                $query_post_id = "SELECT meta_value, GROUP_CONCAT( post_id ORDER BY meta_value
							SEPARATOR ',' ) AS id
                            FROM `{$wpdb->prefix}postmeta`
                                JOIN {$wpdb->prefix}term_relationships AS term_relationships 
                                                ON term_relationships.object_id = post_ID 
                                JOIN {$wpdb->prefix}term_taxonomy AS term_taxonomy 
                                                ON term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id 
                                JOIN {$wpdb->prefix}terms AS terms 
                                                ON term_taxonomy.term_id = terms.term_id
                            WHERE meta_value in ($email)
                            AND meta_key='_billing_email'
                            AND terms.name IN ('completed','processing')
                            GROUP BY meta_value
                            ORDER BY id desc ";

            $result_post_id =  $wpdb->get_results ( $query_post_id, 'ARRAY_A' );
            }

            for ( $i=0,$j=sizeof($result_post_id);$i<sizeof($result_id);$i++,$j++ ) {
                $result_post_id[$j] = $result_id[$i];
            }
            
            $result_total = array();
            $max_post=array();

            for ( $i=0;$i<sizeof($result_post_id);$i++ ) {
                $temp_id=$result_post_id[$i]['id'];
                $query_total="SELECT max(post_id) as max_postid, count(post_id) as count_orders, sum(meta_value) as total_orders
                          FROM `{$wpdb->prefix}postmeta`
                          WHERE meta_key = '_order_total'
                          AND post_id in ($temp_id)
                          ORDER BY max_postid desc";

                $result_total[$i]=$wpdb->get_results ( $query_total, 'ARRAY_A' );
                $max_post[$i]=$result_total[$i][0]['max_postid'];
            }

            $max_post_id=implode(",",$max_post);

            $query_order_total="SELECT meta_value As order_total , post_id
                                FROM `{$wpdb->prefix}postmeta`
                                WHERE meta_key = '_order_total'
                                AND post_id in ($max_post_id)
                                GROUP BY post_id
                                ORDER BY FIND_IN_SET(post_id,'$max_post_id');";

            $result_order_total =  $wpdb->get_results ( $query_order_total, 'ARRAY_A' );


            for ( $i=0; $i<sizeof($postmeta);$i++ ) {

                $postmeta [$i] ['id']=$result_total[$i][0] ['max_postid'];
                $postmeta [$i] ['count_orders']=$result_total[$i][0] ['count_orders'];
                $postmeta [$i] ['total_orders']=$result_total[$i][0] ['total_orders'];

                $result [$i] ['_order_total']=$result_order_total[$i] ['order_total'];

                if (SMPRO == true) {
                    $result [$i] ['last_order'] = $result [$i] ['date']/* . ', ' . $data ['Last_Order_Amt']*/;
                }else{
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
            
		$select_query = "SELECT SQL_CALC_FOUND_ROWS posts.ID as id,
								date_format(posts.post_date,'%b %e %Y, %r') AS date,
								GROUP_CONCAT( postmeta.meta_value 
								ORDER BY postmeta.meta_id
								SEPARATOR '###' ) AS meta_value,
								GROUP_CONCAT(distinct postmeta.meta_key
								ORDER BY postmeta.meta_id 
								SEPARATOR '###' ) AS meta_key,
								terms.name AS order_status
							
							FROM {$wpdb->prefix}posts AS posts 
									JOIN {$wpdb->prefix}term_relationships AS term_relationships 
											ON term_relationships.object_id = posts.ID 
									JOIN {$wpdb->prefix}term_taxonomy AS term_taxonomy 
											ON term_taxonomy.term_taxonomy_id = term_relationships.term_taxonomy_id 
									JOIN {$wpdb->prefix}terms AS terms 
											ON term_taxonomy.term_id = terms.term_id 
									RIGHT JOIN {$wpdb->prefix}postmeta AS postmeta 
											ON (posts.ID = postmeta.post_id AND postmeta.meta_key IN 
																				('_billing_first_name' , '_billing_last_name' , '_billing_email',
																				'_shipping_first_name', '_shipping_last_name', '_shipping_address_1', '_shipping_address_2',
																				'_shipping_city', '_shipping_state', '_shipping_country','_shipping_postcode',
																				'_shipping_method', '_payment_method', '_order_items', '_order_total',
																				'_shipping_method_title', '_payment_method_title','_customer_user'))";
			
			$group_by    = " GROUP BY posts.ID";
			$limit_query = " ORDER BY posts.ID DESC $limit_string ;";
			
			$where = " WHERE posts.post_type LIKE 'shop_order' 
							AND posts.post_status IN ('publish','draft','auto-draft')";
			
			if (isset ( $_POST ['fromDate'] )) {
				$from_date = date('Y-m-d H:i:s',(int)strtotime($_POST ['fromDate']));
				$to_date = isset($_POST ['toDate']) ? date('Y-m-d H:i:s',((int)strtotime($_POST ['toDate']))+86399) : date('Y-m-d H:i:s',((int)strtotime($_POST ['toDate']))+86399);

				if (SMPRO == true) {
					$where .= " AND posts.post_date BETWEEN '$from_date' AND '$to_date'";
				}
			}
			
			if (SMPRO == true && isset ( $_POST ['searchText'] ) && $_POST ['searchText'] != '') {
				$search_on = $wpdb->_real_escape ( trim ( $_POST ['searchText'] ) );
                        
                                //Query for getting the user_id based on the email enetered in the Search Box
                                $query_user     = "SELECT id FROM {$wpdb->prefix}users 
                                                    WHERE user_email like '%$search_on%'";
                                $result_user    = $wpdb->get_col ( $query_user);
                                $num_rows       = $wpdb->num_rows;
                                
                                //Code for handling the Email Search condition for Registered users
                                if($num_rows > 0){
                                    $query_email = "SELECT DISTINCT(meta_value) FROM {$wpdb->prefix}postmeta 
                                                    WHERE meta_key = '_billing_email'
                                                        AND post_id IN (SELECT post_id FROM {$wpdb->prefix}postmeta 
                                                                            WHERE meta_key = '_customer_user'
                                                                                AND meta_value IN (" .implode(",",$result_user) . "))";
                                    $result_email  = $wpdb->get_col ( $query_email );
                                    
                                    $search_condn = "HAVING";
                                    
                                    for ( $i=0;$i<sizeof($result_email);$i++ ){
                                        $search_condn .= " meta_value like '%$result_email[$i]%' ";
                                        $search_condn .= "OR";
                                    }
                                    
                                    $search_condn = substr( $search_condn, 0, -2 );
                                    
                                }
                                else{
                                
				$search_condn = " HAVING id like '$search_on%'
								  OR date like '%$search_on%'
								  OR order_status like '%$search_on%'
								 OR meta_value like '%$search_on%'";
			}
			
			}
			
			//get the state id if the shipping state is numeric or blank
			$query    = "$select_query $where $group_by $search_condn $limit_query;";
			$results  = $wpdb->get_results ( $query,'ARRAY_A');
			//To get the total count
			$orders_count_result = $wpdb->get_results ( 'SELECT FOUND_ROWS() as count;','ARRAY_A');
			$num_records = $orders_count_result[0]['count'];
					
                        //Query to get the email id from the wp_users table for the Registered Customers
                        $query_users  = "SELECT ID,user_email FROM {$wpdb->prefix}users GROUP BY ID";
                        $result_users =  $wpdb->get_results ( $query_users, 'ARRAY_A' );
                        
			if ($num_records == 0) {
				$encoded ['totalCount'] = '';
				$encoded ['items'] = '';
				$encoded ['msg'] = __('No Records Found','smart-manager'); 
			} else {			
				foreach ( $results as $data) {
					$meta_key = explode ( '###', $data ['meta_key'] );
					$meta_value = explode ( '###', $data ['meta_value'] );
					
					if(count($meta_key) == count($meta_value)){
						$postmeta = array_combine ( $meta_key, $meta_value);
                                                
                                                //Code to replace the email of the Registered Customers with the one from the wp_users
                                                if ($postmeta['_customer_user'] > 0) {
                                                    for ( $index=0;$index<sizeof($result_users);$index++ ) {
                                                        if ( $postmeta['_customer_user'] == $result_users[$index]['ID'] ){
                                                            $postmeta['_billing_email'] = $result_users[$index]['user_email'];
                                                            break;
                                                        }
                                                    }
                                                }
                                                
						if (is_serialized($postmeta['_order_items'])) {
							$order_items = unserialize(trim($postmeta['_order_items']));
							foreach ( (array)$order_items as $order_item) {
								$data['details'] += $order_item['qty'];
								$product_id = ( $order_item['variation_id'] > 0 ) ? $order_item['variation_id'] : $order_item['id'];
								$sku = get_post_meta( $product_id, '_sku', true );
								if ( ! empty( $sku ) ) {
									$sku = '[' . $sku . ']';
								} else {
									$sku = '';
								}
								$data['products_name'] .= $order_item['name'].$sku.'('.$order_item['qty'].'), ';
							}
							isset($data['details']) ? $data['details'] .= ' items' : $data['details'] = ''; 
							$data['products_name'] = substr($data['products_name'], 0, -2);	//To remove extra comma ', ' from returned string
						} else {
							$data['details'] = 'Details';
						}
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
//						if ($postmeta['_payment_method'] != '' || $postmeta['_payment_method'] != null) {
							$records [] = array_merge ( $postmeta, $data );	
//						}
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
	echo json_encode ( $encoded );
	unset($encoded);
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
				$columns_header['post_content'] 			= __('Product Description', $sm_domain);
				$columns_header['post_excerpt'] 			= __('Additional Description', $sm_domain);
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
				$columns_header['_order_total'] 			= __('Order Total', $sm_domain);
				$columns_header['products_name'] 			= __('Order Items (Product Name[SKU](Qty))', $sm_domain);
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
			break;
	}
	
	$file_data = export_csv_woo ( $active_module, $columns_header, $data );
	
	header("Content-type: text/x-csv; charset=UTF-8"); 
	header("Content-Transfer-Encoding: binary");
	header("Content-Disposition: attachment; filename=".$file_data['file_name']); 
	header("Pragma: no-cache");
	header("Expires: 0");
		
	echo $file_data['file_content'];
		
	exit;
}

//update products for lite version.
function update_products_woo($_POST) {
	global $result, $wpdb;
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
	echo json_encode ( $encoded );
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
				$encoded ['msg'] = $delCnt . " " . $activeModule . __('deleted Successfully','smart-manager');
				$encoded ['delCnt'] = $delCnt;
			} else {
				$encoded ['msg'] = $delCnt . " " . $activeModule . __('s deleted Successfully','smart-manager');
				$encoded ['delCnt'] = $delCnt;
			}
		} elseif ($result == false) {
			$encoded ['msg'] = $activeModule . __('s were not deleted','smart-manager');
		} else {
			$encoded ['msg'] = $activeModule . __('s removed from the grid','smart-manager');
		}
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
	echo json_encode ( $regions );
}

if (isset ( $_POST ['cmd'] ) && $_POST ['cmd'] == 'editImage') {
	$woo_default_image = WP_PLUGIN_URL . '/smart-reporter-for-wp-e-commerce/resources/themes/images/woo_default_image.png';
	$post_thumbnail_id = get_post_thumbnail_id( $_POST ['id'] );
	$image = isset( $post_thumbnail_id ) ? wp_get_attachment_image_src( $post_thumbnail_id, 'admin-product-thumbnails' ) : '';
	$thumbnail = ( $image[0] != '' ) ? $image[0] : '';
	echo json_encode ( $thumbnail );
}

?>
