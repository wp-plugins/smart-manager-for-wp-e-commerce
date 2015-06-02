<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Shop_Order' ) ) {
	class Smart_Manager_Shop_Order extends Smart_Manager_Base {
		public $dashboard_key = '',
			$default_store_model = array();

		function __construct($dashboard_key) {
			$this->dashboard_key = $dashboard_key;
			$this->req_params  	= (!empty($_REQUEST)) ? $_REQUEST : array();
			add_filter('sm_dashboard_model',array(&$this,'orders_dashboard_model'),10,1);

		}

		public function orders_dashboard_model ($dashboard_model) {
			// $dashboard_model[$this->dashboard_key]['tables']['posts']['where']['post_status'] = array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed');

			$dashboard_model[$this->dashboard_key]['tables']['posts']['where']['post_type'] = 'shop_order';

			$post_type_col_index = sm_multidimesional_array_search('posts_post_status', 'index', $dashboard_model[$this->dashboard_key]['columns']);

			$dashboard_model[$this->dashboard_key]['columns'][$post_type_col_index]['values'] = array('wc-pending' => __('Pending'),
																									'wc-processing' => __('Processing'),
																									'wc-on-hold' => __('On Hold'),
																									'wc-completed' => __('Completed'),
																									'wc-cancelled' => __('Cancelled'),
																									'wc-refunded' => __('Refunded'),
																 									'wc-failed' => __('Failed'));

			return $dashboard_model;

		}

	}

}