<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Product' ) ) {
	class Smart_Manager_Product extends Smart_Manager_Base {
		public $dashboard_key = '',
			$default_store_model = array(),
			$prod_sort = false;

		function __construct($dashboard_key) {
			$this->dashboard_key = $dashboard_key;
			$this->post_type = array('product', 'product_variation');
			$this->req_params  	= (!empty($_REQUEST)) ? $_REQUEST : array();

			add_filter('sm_dashboard_model',array(&$this,'products_dashboard_model'),10,1);
			add_filter('sm_data_model',array(&$this,'products_data_model'),10,1);

			add_filter('sm_inline_update_pre',array(&$this,'products_inline_update_pre'),10,1);
			add_action('sm_inline_update_post',array(&$this,'products_inline_update'),10,1);

			// add_filter('posts_orderby',array(&$this,'sm_product_query_order_by'),10,2);

			add_filter('posts_fields',array(&$this,'sm_product_query_post_fields'),10,2);
			add_filter('posts_where',array(&$this,'sm_product_query_post_where_cond'),10,2);
			add_filter('posts_orderby',array(&$this,'sm_product_query_order_by'),10,2);

			add_filter('posts_join_paged',array(&$this,'sm_query_join'),10,2);

			// add_action('admin_footer',array(&$this,'attribute_handling'));
		}

		public function sm_product_query_post_fields ($fields, $wp_query_obj) {
			
			global $wpdb;

			$fields .= ',if('.$wpdb->prefix.'posts.post_parent = 0,'.$wpdb->prefix.'posts.id,'.$wpdb->prefix.'posts.post_parent - 1 + ('.$wpdb->prefix.'posts.id)/pow(10,char_length(cast('.$wpdb->prefix.'posts.id as char)))) as parent_sort_id';

			return $fields;
		}

		public function sm_product_query_post_where_cond ($where, $wp_query_obj) {
			
			global $wpdb;

			//Code to get the ids of all the products whose post_status is thrash
	        $query_trash = "SELECT ID FROM {$wpdb->prefix}posts 
	                        WHERE post_status = 'trash'
	                            AND post_type IN ('product')";
	        $results_trash = $wpdb->get_col( $query_trash );
	        $rows_trash = $wpdb->num_rows;
	        
	        // Code to get all the variable parent ids whose type is set to 'simple'

	        //Code to get the taxonomy id for 'simple' product_type
	        $query_taxonomy_id = "SELECT taxonomy.term_taxonomy_id as term_taxonomy_id
	                                    FROM {$wpdb->prefix}terms as terms
	                                        JOIN {$wpdb->prefix}term_taxonomy as taxonomy ON (taxonomy.term_id = terms.term_id)
	                                    WHERE taxonomy.taxonomy = 'product_type'
	                                    	AND terms.slug = 'variable'";
	        $variable_taxonomy_id = $wpdb->get_var( $query_taxonomy_id );

	        if ( !empty($variable_taxonomy_id) ) {
	        	$query_post_parent_not_variable = "SELECT distinct products.post_parent 
				                            FROM {$wpdb->prefix}posts as products 
				                            WHERE NOT EXISTS (SELECT * 
				                            					FROM {$wpdb->prefix}term_relationships 
				                            					WHERE object_id = products.post_parent
				                            						AND term_taxonomy_id = ".$variable_taxonomy_id.") 
				                              AND products.post_parent > 0 
				                              AND products.post_type = 'product_variation'";
		        $results_post_parent_not_variable = $wpdb->get_col( $query_post_parent_not_variable );
		        $rows_post_parent_not_variable = $wpdb->num_rows;	

		        for ($i=sizeof($results_trash),$j=0;$j<sizeof($results_post_parent_not_variable);$i++,$j++ ) {
		            $results_trash[$i] = $results_post_parent_not_variable[$j];
		        }
	        }

	        if ($rows_trash > 0 || $rows_post_parent_not_variable > 0) {
	            $where .= " AND {$wpdb->prefix}posts.post_parent NOT IN (" .implode(",",$results_trash). ")";
	        }

			return $where;
		}

		public function sm_product_query_order_by ($order_by, $wp_query_obj) {
	
			global $wpdb;

			// $order_by = 'CASE
			// 				WHEN post_parent > 0 THEN id
			// 				WHEN post_parent = 0 THEN post_title
			// 			END DESC';

			if ( !empty($this->req_params['sidx']) && ( ($this->req_params['sidx'] != 'posts_id') || ($this->req_params['sidx'] == 'posts_id' && $this->req_params['sord'] == 'asc') )) {

				$order = ( empty($this->req_params['sord']) ) ? ' ASC' : ' '.strtoupper($this->req_params['sord']);

				if ( strpos($this->req_params['sidx'],'posts_') !== false ) {
					$order_by = substr($this->req_params['sidx'], strlen('posts_')) . $order;
				} else if ( strpos($this->req_params['sidx'],'terms_') !== false && $this->terms_sort_join === true ) {
					$order_by = $wpdb->prefix. 'term_relationships.term_taxonomy_id '.$order ;
				}

				$this->prod_sort = true;

			} else {
				$order_by = 'parent_sort_id DESC';
				$this->prod_sort = false;
			}
			return $order_by;
		}

		public function products_dashboard_model ($dashboard_model) {

			global $wpdb;

			$visible_columns = array('ID', 'post_title', '_sku', '_regular_price', '_sale_price', '_sale_price_dates_from', '_sale_price_dates_to', 
									'_stock','post_status', 'post_content','product_cat','product_attributes', '_length', '_width', '_height', 
									'_visibility', '_tax_status','product_type');

			$column_model = &$dashboard_model[$this->dashboard_key]['columns'];

			$dashboard_model[$this->dashboard_key]['tables']['posts']['where']['post_type'] = array('product', 'product_variation');

			$dashboard_model[$this->dashboard_key]['treegrid'] = true; //for setting the treegrid

			$attr_col_index = sm_multidimesional_array_search ('custom/product_attributes', 'src', $column_model);

			$attributes_val = array();
			$attributes_label = array();
			

			if (empty($attr_col_index)) {
				//Query to get the attribute name
				$query_attribute_label = "SELECT attribute_name, attribute_label, attribute_type
		                                FROM {$wpdb->prefix}woocommerce_attribute_taxonomies";
		        $results_attribute_label = $wpdb->get_results( $query_attribute_label, 'ARRAY_A' );
		        $attribute_label_count = $wpdb->num_rows;

		        if($attribute_label_count > 0) {
			        foreach ($results_attribute_label as $results_attribute_label1) {
			            $attributes_label['pa_' . $results_attribute_label1['attribute_name']]['lbl'] = $results_attribute_label1['attribute_label'];
			            $attributes_label['pa_' . $results_attribute_label1['attribute_name']]['type'] = $results_attribute_label1['attribute_type'];
			        }	
		        }
			}

			foreach ($column_model as $key => &$column) {
				if (empty($column['src'])) continue;

				$src_exploded = explode("/",$column['src']);

				if (empty($src_exploded)) {
					$src = $column['src'];
				}

				if ( sizeof($src_exploded) > 2) {
					$cond = explode("=",$src_exploded[1]);

					if (sizeof($cond) == 2) {
						$src = $cond[1];
					}
				} else {
					$src = $src_exploded[1];
				}

				//Code for unsetting the position for hidden columns
				if (!empty($column['position'])) {
					unset($column['position']);
				}

				$position = array_search($src, $visible_columns);

				if ($position !== false) {
					$column['position'] = $position;
					$column['hidden'] = false;
				} else {
					$column['hidden'] = true;
				}
				

				// key:true

				if (!empty($src)) {
					if (substr($src,0,3)=='pa_') {
						$attributes_val [$src] = array();
						$attributes_val [$src]['lbl'] = (!empty($attributes_label[$src]['lbl'])) ? $attributes_label[$src]['lbl'] : $src;
						$attributes_val [$src]['val'] = $column['values'];
						$attributes_val [$src]['type'] = (!empty($attributes_label[$src]['type'])) ? $attributes_label[$src]['type'] : $src;
						
						unset($column_model[$key]);	
					} else if ($src == 'product_cat') {
						$column['type'] = 'multilist';
						$column['editable']	= false;
					} else if ($src == 'ID') {
						$column['key'] = true; //for tree grid
					} else if ( $src == '_sale_price_dates_from' || $src == '_sale_price_dates_to' ) {
						$column['type'] = 'datetime';
					} else if ($src == '_visibility') {
						$column ['values'] = array('visible' => __('Catalog & Search'),
												   'catalog' => __('Catalog'),
												   'search' => __('Search'),
												   'hidden' => __('Hidden'));
					} else if ($src == '_tax_status') {
						$column ['values'] = array('taxable' => __('Taxable'),
												   'shipping' => __('Shipping only'),
												   'none' => __('None'));
					} else if ($src == '_stock_status') {
						$column ['values'] = array('instock' => __('In stock'),
												   'outofstock' => __('Out of stock'));
					} else if ($src == '_tax_class') {
						$column ['values'] = array('' => __('Standard'),
												   'reduced-rate' => __('Reduced Rate'),
												   'zero-rate' => __('Zero Rate'));
					} else if ($src == '_backorders') {
						$column ['values'] = array('no' => __('Do Not Allow'),
												   'notify' => __('Allow, but notify customer'),
												   'yes' => __('Allow'));
					}
				}
			}

			if (empty($attr_col_index)) {
				$index = sizeof($column_model);

				//Code for including custom columns for product dashboard
				$column_model [$index] = array();
				$column_model [$index]['src'] = 'custom/product_attributes';
				$column_model [$index]['index'] = sanitize_title(str_replace('/', '_', $column_model [$index]['src'])); // generate slug using the wordpress function if not given 
				$column_model [$index]['name'] = __(ucwords(str_replace('_', ' ', 'attributes')));
				$column_model [$index]['type'] = 'serialized';
				$column_model [$index]['hidden']	= true;
				$column_model [$index]['editable']	= false;

				$column_model [$index]['width'] = 100;

				$position = array_search('product_attributes', $visible_columns);

				if ($position !== false) {
					$column_model [$index]['position'] = $position;
					$column_model [$index]['hidden'] = false;
				} else {
					$column_model [$index]['hidden'] = true;
				}

				//Code for assigning attr. values
				$column_model [$index]['values'] = $attributes_val;
			}

			// Load from cache
			$dashboard_model_saved = get_transient( 'sm_dashboard_model_'.$this->dashboard_key );

			if (!empty($dashboard_model_saved)) {
				$col_model_diff = sm_array_recursive_diff($dashboard_model_saved,$dashboard_model);	
			}

			//clearing the transients before return
			if (!empty($col_model_diff)) {
				delete_transient('sm_dashboard_model_'.$this->dashboard_key);	
			}		

			return $dashboard_model;
		}

		public function products_data_model ($data_model) {

			global $wpdb;

			//Code for loading the data for the attributes column

			if(empty($data_model) || empty($data_model['items'])) return;

			$current_store_model = get_transient( 'sm_dashboard_model_'.$this->dashboard_key );

			$col_model = (!empty($current_store_model[$this->dashboard_key]['columns'])) ? $current_store_model[$this->dashboard_key]['columns'] : array();

			if (!empty($col_model)) {

				//Code to get attr values by slug name
				$attr_val_by_slug = array();
				$attr_taxonomy_nm = get_object_taxonomies($this->post_type);

				if ( !empty($attr_taxonomy_nm) ) {
					foreach ( $attr_taxonomy_nm as $key => $attr_taxonomy ) {
						if ( substr($attr_taxonomy,0,3) != 'pa_' ) {
							unset( $attr_taxonomy_nm[$key] );
						}
					}

					$attr_terms = get_terms($attr_taxonomy_nm, array('hide_empty'=> 0,'orderby'=> 'id'));

					if ( !empty($attr_terms) ){
						foreach ( $attr_terms as $attr_term ) {
							if (empty($attr_val_by_slug[$attr_term->taxonomy])) {
								$attr_val_by_slug[$attr_term->taxonomy] = array();
							}
							$attr_val_by_slug[$attr_term->taxonomy][$attr_term->slug] = $attr_term->name;
						}
					}	
				}

				$taxonomy_nm = array();
				$term_taxonomy_ids = array();
				$post_ids = array();
				$product_attributes_postmeta = array();

				foreach ($col_model as $column) {
					if (empty($column['src'])) continue;

					$src_exploded = explode("/",$column['src']);

					if (!empty($src_exploded) && $src_exploded[1] == 'product_attributes') {
						$attr_values = $column['values'];

						if (!empty($attr_values)) {
							foreach ($attr_values as $key => $attr_value) {
								$taxonomy_nm[] = $key;
								$term_taxonomy_ids = $term_taxonomy_ids + $attr_value;
							}
						}
					}
				}				

				foreach ($data_model['items'] as $key => &$data) {

					if (empty($data['posts_id'])) continue;
					$post_ids[] = $data['posts_id'];

					$data['loaded'] = true;
					$data['expanded'] = true;

					if ( !empty($data['posts_post_parent']) ) {

						$parent_key = sm_multidimesional_array_search($data['posts_post_parent'], 'posts_id', $data_model['items']);
						// $parent_key = $data['posts_post_parent'];
						// $parent_type = '';

						// if ( !empty($data_model['items'][$parent_key]['terms_product_type']) ) {
						// 	$parent_type = $data_model['items'][$parent_key]['terms_product_type'];
						// } else if ( empty($data_model['items'][$parent_key]['terms_product_type'])) {
						// 	$parent_type = wp_get_object_terms( $parent_key, 'product_type', array('fields' => 'names') );
						// 	$parent_type = $parent_type[0];
						// }

						// if ( $parent_type != 'variable' ) {
						// 	unset($data_model['items'][$key]);
						// 	continue;
						// }

						$parent_title  = '';

						// Code for the variation title on sorting
						if ( $this->prod_sort === true ) {
							$parent_title = (!empty($data_model['items'][$parent_key]['posts_post_title'])) ? $data_model['items'][$parent_key]['posts_post_title'] : get_the_title($data['posts_post_parent']);
							$parent_title .= ( !empty($parent_title) ) ? ' - ' : '';
						}
						
						$data['parent'] = $data['posts_post_parent'];
						$data['isLeaf'] = true;
						$data['level'] = 1;

						//Code for modifying the variation name

						$variation_title = '';

						foreach ($data as $key => &$value) {
							$start_pos = strrpos($key, '_meta_value_attribute_');

							if ( $start_pos !== false ){
								
								$attr_nm = substr($key, $start_pos+22);

								$value = (empty($value)) ? 'any' : $value;

								if ( !empty($attr_values[$attr_nm]) ) {

									$attr_lbl = (!empty($attr_values[$attr_nm]['lbl'])) ? $attr_values[$attr_nm]['lbl'] : $attr_nm;
									$attr_val = ( !empty($attr_val_by_slug[$attr_nm][$value]) ) ? $attr_val_by_slug[$attr_nm][$value] : $value;
									$variation_title .= $attr_lbl . ' : ' . $attr_val;

								} else {
									$variation_title .= $attr_nm . ' : ' . $value;
								}
								$variation_title .= ', ';
							}	
						}

						$data['posts_post_title'] = $parent_title .''. substr($variation_title, 0, strlen($variation_title)-2 );

					} else if ( !empty($data['terms_product_type']) ) {
						if ( $data['terms_product_type'] == 'simple' ) {
							$data['icon_show'] = false;
						} 
						$data['parent'] = 'null';
						$data['isLeaf'] = false;
						$data['level'] = 0;							
					}

					if ( $this->prod_sort === true ) {
						$data['icon_show'] = false;
						$data['parent'] = 'null';
						$data['isLeaf'] = false;
						$data['level'] = 0;	
					}

					if (empty($data['postmeta_meta_key__product_attributes_meta_value__product_attributes'])) continue;
					$product_attributes_postmeta[$data['posts_id']] = $data['postmeta_meta_key__product_attributes_meta_value__product_attributes'];
				}

				$data_model['items'] = array_values($data_model['items']);

				$terms_objects = wp_get_object_terms( $post_ids, $taxonomy_nm, 'orderby=none&fields=all_with_object_id' );
				$attributes_val = array();
				$temp_attribute_nm = "";

				if (!empty($terms_objects)) {
					foreach ($terms_objects as $terms_object) {

						$post_id = $terms_object->object_id;
						$taxonomy = $terms_object->taxonomy;
						$term_id = $terms_object->term_id;

						if (!isset($attributes_val[$post_id])){
							$attributes_val[$post_id] = array();
						}

						if (!isset($attributes_val[$post_id][$taxonomy])){
							$attributes_val[$post_id][$taxonomy] = array();
						}

			            $attributes_val[$post_id][$taxonomy][$term_id] = $terms_object->name;
					}
				}
				
				//Query to get the attribute name
				$query_attribute_label = "SELECT attribute_name, attribute_label
		                                FROM {$wpdb->prefix}woocommerce_attribute_taxonomies";
		        $results_attribute_label = $wpdb->get_results( $query_attribute_label, 'ARRAY_A' );
		        $attribute_label_count = $wpdb->num_rows;

		        $attributes_label = array();

		        if($attribute_label_count > 0) {
			        foreach ($results_attribute_label as $results_attribute_label1) {
			            $attributes_label['pa_' . $results_attribute_label1['attribute_name']] = array();
			            $attributes_label['pa_' . $results_attribute_label1['attribute_name']] = $results_attribute_label1['attribute_label'];
			        }	
		        }
		        
				// $query_attributes = $wpdb->prepare("SELECT post_id as id,
				// 											meta_value as product_attributes
				// 										FROM {$wpdb->prefix}postmeta
				// 										WHERE meta_key = '%s'
				// 											AND meta_value <> '%s'
				// 											AND post_id IN (".implode(',', array_filter($post_ids,'is_int')).")
				// 										GROUP BY id",'_product_attributes','a:0:{}');

				// $product_attributes = $wpdb->get_results($query_attributes, 'ARRAY_A');
				// $product_attributes_count = $wpdb->num_rows;

				if (!empty($product_attributes_postmeta)) {
					foreach ($product_attributes_postmeta as $post_id => $product_attribute) {

						if (empty($product_attribute)) continue;

                    	$prod_attr = json_decode($product_attribute,true);
                    	$update_index = sm_multidimesional_array_search ($post_id, 'posts_id', $data_model['items']);
                    	$attributes_list = "";

	                    //cond added for handling blank data
	                    if (is_array($prod_attr) && !empty($prod_attr)) {

	                    	$attributes_list = "";

	                    	foreach ($prod_attr as &$prod_attr1) {
	                    		if ($prod_attr1['is_taxonomy'] == 0) {
	                    			$attributes_list .= $prod_attr1['name'] . ": [" . trim($prod_attr1['value']) ."]";
                            		$attributes_list .= "<br>";
		                    	} else {
		                    		$attributes_val_current = (!empty($attributes_val[$post_id][$prod_attr1['name']])) ? $attributes_val[$post_id][$prod_attr1['name']] : array();
		                    		$attributes_list .= $attributes_label[$prod_attr1['name']] . ": [" . implode(" | ",$attributes_val_current) . "]";
                                    $attributes_list .= "<br>";
                                    $prod_attr1['value'] = $attributes_val_current;
		                    	}
	                    	}

	                    	$data_model['items'][$update_index]['custom_product_attributes'] = $attributes_list;
	                    	$data_model['items'][$update_index]['postmeta_meta_key__product_attributes_meta_value__product_attributes'] = json_encode($prod_attr);
	                    }
					}
				}
			}
			return $data_model;
		}

		//function for modifying edited data before updating
		public function products_inline_update_pre($edited_data) {
			if (empty($edited_data)) return $edited_data;

			foreach ($edited_data as &$edited_row) {
				if (empty($edited_row['postmeta/meta_key=_product_attributes/meta_value=_product_attributes'])) continue;

				$product_attributes = json_decode($edited_row['postmeta/meta_key=_product_attributes/meta_value=_product_attributes'],true); 

				if (empty($product_attributes)) continue;

				foreach ($product_attributes as $attr => &$attr_value) {
					if ($attr_value['is_taxonomy'] == 0) continue;
					$attr_value['value'] = '';
				}

				$product_attributes = sm_multidimensional_array_sort($product_attributes, 'position', SORT_ASC);
				
				$edited_row['postmeta/meta_key=_product_attributes/meta_value=_product_attributes'] = json_encode($product_attributes);
			}

			return $edited_data;
		}

		//function for inline update of custom fields
		public function products_inline_update($edited_data) {

			if(empty($edited_data)) return;

			$attr_values = array();
			$current_store_model = get_transient( 'sm_dashboard_model_'.$this->dashboard_key );
			$col_model = (!empty($current_store_model[$this->dashboard_key]['columns'])) ? $current_store_model[$this->dashboard_key]['columns'] : array();

			if (!empty($col_model)) {

				foreach ($col_model as $column) {
					if (empty($column['src'])) continue;

					$src_exploded = explode("/",$column['src']);

					if (!empty($src_exploded) && $src_exploded[1] == 'product_attributes') {
						$col_values = $column['values'];

						if (!empty($col_values)) {
							foreach ($col_values as $key => $col_value) {
								$attr_values [$col_value['lbl']] = array();
								$attr_values [$col_value['lbl']] ['taxonomy_nm'] = $key;
								$attr_values [$col_value['lbl']] ['val'] = $col_value['val'];
								$attr_values [$col_value['lbl']] ['type'] = $col_value['type'];
							}
						}
					}
				}
			}

			foreach ($edited_data as $edited_row) {
				$id = (!empty($edited_row['posts/ID'])) ? $edited_row['posts/ID'] : '';

				if (empty($id)) continue;

				$attr_edited = (!empty($edited_row['custom/product_attributes'])) ? $edited_row['custom/product_attributes'] : '';

				$attr_edited = array_filter(explode('<br>',$attr_edited));

				if (empty($attr_edited)) continue;

				foreach ($attr_edited as $attr) {
					$attr_data = explode(':',$attr);

					if (empty($attr_data)) continue;

					$taxonomy_nm = $attr_data[0];
					$attr_editd_val = str_replace(array(':','[',']',' '),'',$attr_data[1]);

					if (!empty($attr_values[$taxonomy_nm])) {
						//Code for type=select attributes

						$attr_val = $attr_values[$taxonomy_nm]['val'];
						$attr_type = $attr_values[$taxonomy_nm]['type'];

						$taxonomy_nm = $attr_values[$taxonomy_nm]['taxonomy_nm'];
						$attr_editd_val = array_filter(explode("|",$attr_editd_val));
						
						if (empty($attr_editd_val)) continue;

						$term_ids = array();

						foreach ($attr_editd_val as $attr_editd) {

							$term_id = array_search($attr_editd, $attr_val);

							if ($term_id === false && $attr_type == 'text') {
								$new_term = wp_insert_term($attr_editd, $taxonomy_nm);

								if ( !is_wp_error( $new_term ) ) {
									$term_id = (!empty($new_term['term_id'])) ? $new_term['term_id'] : '';
								}
							}
							$term_ids [] = $term_id;
						}
						wp_set_object_terms($id, $term_ids, $taxonomy_nm);
					} 
				}
			}
		}
	} //End of Class
}