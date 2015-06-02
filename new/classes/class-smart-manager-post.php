<?php

if ( !defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Smart_Manager_Post' ) ) {
	class Smart_Manager_Post extends Smart_Manager_Base {
		function __construct($dashboard_key) {
			$this->dashboard_key = $dashboard_key;
			$this->req_params  	= (!empty($_REQUEST)) ? $_REQUEST : array();

			// delete_transient( 'sm_dashboard_model_'.$this->dashboard_key );
			
			add_filter('sm_active_dashboards',array(&$this,'dashboards_override'),10,1);
		}

		public function dashboards_override ($dashboards) {

			// unset($dashboards['revision']);
			return $dashboards;
		}

	}
}

// $smart_manager_posts = new Smart_Manager_Posts();

?>