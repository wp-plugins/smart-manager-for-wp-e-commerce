<?php

if ( ! class_exists( 'Smart_Manager' ) ) {
	class Smart_Manager {

		public  $text_domain 	= '',
				$plugin_path 	= '',
				$plugin_url 	= '',
				$plugin_info 	= '',
				$version 		= '',
				$error_message 	= '';

		public function __construct() {

			require_once (ABSPATH . WPINC . '/default-constants.php');
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			include_once (ABSPATH . WPINC . '/functions.php');

			$this->text_domain = 'smart_manager';
        	$this->plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) );
			$this->plugin_url   = untrailingslashit( plugins_url( '/', __FILE__ ) );

			$plugin_info = get_plugins ();
			$this->plugin_info = $plugin_info [SM_PLUGIN_FILE];
            $sm_plugin_data = get_plugin_data(__FILE__);
            $this->version = $sm_plugin_data['Version'];

			// $this->define_constants(); //for defining all the constatnts

			include_once $this->plugin_path . '/classes/class-smart-manager-controller.php';
			new Smart_Manager_Controller();

			// add_action ( 'admin_notices', array(&$this,'smart_admin_notices') );
			add_action ( 'admin_head', array(&$this,'remove_help_tab') ); // For removing the help tab
			// add_action( 'admin_menu', array(&$this,'smart_add_menu_access'), 9 ); // for adding menu
		}

        //Function for defining constants
		function define_constants() {

			global $wp_version;

			define ( 'SM_VERSION', $this->version );

			if (version_compare ( $wp_version, '3.5', '>=' )) {
				define ( 'IS_WP35', true);
			}

			//Flag for handling changes since WP 4.0+
	        if (version_compare ( $wp_version, '4.0', '>=' )) {
	        	define ( 'IS_WP40', true);
	        }


	        if (file_exists ( $this->plugin_path . '/pro/sm.js' )) {
				define ( 'SMPRO', true );
			} else {
				define ( 'SMPRO', false );
			}

			define( 'PLUGINS_FILE_PATH', dirname( dirname( __FILE__ ) ) );
			define( 'IMG_URL', $this->plugin_path . '/assets/images/' );
			define( 'ADMIN_URL', get_admin_url() ); //defining the admin url

		} 

		function enqueue_admin_scripts() {

			global $wp_version, $wpdb;

			if ( !wp_script_is( 'jquery' ) ) {
	            wp_enqueue_script( 'jquery' );
	        }

	        
	        //Registering scripts for jqgrid lib.
	        wp_register_script ( 'sm_jquery_ui_custom', plugins_url ( '/assets/js/jqgrid/jquery-ui-1.9.2.custom.js', __FILE__ ), array ('jquery'), '1.10.2' );
	        wp_register_script ( 'sm_jquery_ui_multiselect', plugins_url ( '/assets/js/jqgrid/ui.multiselect.js', __FILE__ ), array ('sm_jquery_ui_custom'), '1.10.2' );
			wp_register_script ( 'sm_jqgrid_locale', plugins_url ( '/assets/js/jqgrid/grid.locale-en.js', __FILE__ ), array ('sm_jquery_ui_multiselect'), '1.10.2' );
			wp_register_script ( 'sm_jqgrid_main', plugins_url ( '/assets/js/jqgrid/jquery.jqGrid.min.js', __FILE__ ), array ('sm_jqgrid_locale'), '1.10.2' );
			wp_register_script ( 'sm_chosen', plugins_url ( '/assets/js/chosen/chosen.jquery.min.js', __FILE__ ), array ('sm_jqgrid_main'), '1.3.0' );
			wp_register_script ( 'sm_custom_smart_manager_js', plugins_url ( '/assets/js/smart-manager.js', __FILE__ ), array ('sm_chosen'));


			// Code for loading custom js automatically
			$custom_js = glob( $this->plugin_path .'/assets/js/*.js' );

	        foreach ( $custom_js as $file ) {

	        	$file_nm = 'sm_custom_'.preg_replace('/[\s-.]/','_',substr($file, (strrpos($file, '/', -3) + 1)));

	        	if ( $file_nm == 'sm_custom_smart_manager_js' ) {
	        		continue;
	        	}

	        	if ($index == 0) {
	        		wp_register_script ( $file_nm, plugins_url ( '/assets/js/'.substr($file, (strrpos($file, '/', -3) + 1)), __FILE__ ), array ('sm_custom_smart_manager_js') );
	        	} else {	        		
	        		wp_register_script ( $file_nm, plugins_url ( '/assets/js/'.substr($file, (strrpos($file, '/', -3) + 1)), __FILE__ ), array ($last_reg_script) );
	        	}

	        	$last_reg_script = $file_nm;
	        	$index++;
	        }

	        //Code to get all the custom post types as dashboards
			$query_post_types = "SELECT DISTINCT post_type as post_type FROM {$wpdb->prefix}posts WHERE post_type NOT IN ('post','page','revision')";
			$sm_dashboards = $wpdb->get_col($query_post_types);

			$sm_dashboards_final = array();
			$sm_dashboards_final ['post'] = __(ucwords('post'));
			$sm_dashboards_final ['page'] = __(ucwords('page'));

			$exclude_from_dashboards = array('product_variation');

			$dashboard_names = array( 'shop_order' => 'Order' );

			if (!empty($sm_dashboards)) {
				foreach ($sm_dashboards as $sm_dashboard) {

					if (in_array($sm_dashboard, $exclude_from_dashboards)) continue;

					$sm_dashboards_final [$sm_dashboard] = (!empty($dashboard_names[$sm_dashboard])) ? $dashboard_names[$sm_dashboard] : __(ucwords(str_replace('_', ' ', $sm_dashboard)));
				}	
			}
			
			// add_filter('sm_active_dashboards','sm_dashboards_override',10,1); // filter for modifying the custom_post_type array

			$sm_dashboards = apply_filters('sm_active_dashboards', $sm_dashboards_final);

			$sm_dashboard_keys = array_keys($sm_dashboards);
			$sm_dashboards ['default'] = $sm_dashboard_keys[0];

			$sm_dashboards ['sm_nonce'] = wp_create_nonce( 'smart-manager-security' );

	        wp_localize_script( 'sm_custom_smart_manager_js', 'sm_dashboards', array(json_encode($sm_dashboards)) );

			wp_enqueue_script( $last_reg_script );

			// Including Scripts for using the wordpress new media manager
	        if (version_compare ( $wp_version, '3.5', '>=' )) {
	            if ( isset($_GET['page']) && ($_GET['page'] == "smart-manager" || $_GET['page'] == "smart-manager-settings")) {
	                wp_enqueue_media();
	                wp_enqueue_script( 'custom-header' );
	            }
	        }

			do_action('smart_manager_enqueue_scripts'); //action for hooking any scripts
		}

		function enqueue_admin_styles() {
			
			// Registering styles for jqgrid
			wp_register_style ( 'sm_jqgrid_ui_custom', plugins_url ( '/assets/css/jqgrid/jquery-ui-1.9.2.custom.min.css', __FILE__ ), array (), '1.10.2' );
			wp_register_style ( 'sm_jqgrid_ui_multiselect', plugins_url ( '/assets/css/jqgrid/ui.multiselect.css', __FILE__ ), array ('sm_jqgrid_ui_custom'), '1.10.2' );
			wp_register_style ( 'sm_jqgrid_main', plugins_url ( '/assets/css/jqgrid/ui.jqgrid.css', __FILE__ ), array ('sm_jqgrid_ui_multiselect'), '1.10.2' );

			wp_register_style ( 'sm_chosen_style', plugins_url ( '/assets/css/chosen/chosen.min.css', __FILE__ ), array ('sm_jqgrid_main'), '1.3.0' );

			wp_register_style ( 'sm_main_style', plugins_url ( '/assets/css/smart-manager.css', __FILE__ ), array ('sm_chosen_style' ), $this->plugin_info ['Version'] );
			
			wp_enqueue_style( 'sm_main_style' );

			do_action('smart_manager_enqueue_scripts');	//action for hooking any styles
		}

		function get_latest_version() {
			$sm_plugin_info = get_site_transient( 'update_plugins' );
			$latest_version = isset( $sm_plugin_info->response [SM_PLUGIN_FILE]->new_version ) ? $sm_plugin_info->response [SM_PLUGIN_FILE]->new_version : '';
			return $latest_version;
		}

		function get_user_sm_version() {
			$sm_plugin_info = get_plugins();
			$user_version = $sm_plugin_info [SM_PLUGIN_FILE] ['Version'];
			return $user_version;
		}

		function is_pro_updated() {
			$user_version = $this->get_user_sm_version();
			$latest_version = $this->get_latest_version();
			return version_compare( $user_version, $latest_version, '>=' );
		}

		// function for removing the Help Tab
		function remove_help_tab(){
			//condition to remove the help tab only from SM pages
			if ( isset($_GET['page']) && ($_GET['page'] == "smart-manager-beta")) {
				$screen = get_current_screen();
		    	$screen->remove_help_tabs();
			}
		}

		//function for showing the sm page
		function show_console_beta() {
		
			global $wpdb;

			$latest_version = $this->get_latest_version();
			$is_pro_updated = $this->is_pro_updated();

			?>
			<div class="wrap">
			<div id="icon-smart-manager" class="icon32"><br />
			</div>
			<style>
			    div#TB_window {
			        background: lightgrey;
			    }
			</style>    
			<?php if ( SMPRO === true && function_exists( 'smart_support_ticket_content' ) ) smart_support_ticket_content();  ?>    
			    
			<h2><?php
		                echo 'Smart Manager <sup style="vertical-align:super;color:red;font-size:small;">Beta</sup>';
						// echo (SMPRO === true) ? 'Pro' : 'Lite';
		                $plug_page = '';
		                $sm_promo_img_url = "http://www.storeapps.org/ads/sm-in-app.png?d=". date("Ymd");
				?>
				<span style="float:right; margin: -6px -21px -20px 0px;">
						<a href="http://www.storeapps.org/sm-in-app-promo" target="_blank"> <img src="<?php echo $sm_promo_img_url ?>" alt=""> </a>
				</span>
		   		<p class="wrap" style="font-size: 12px; margin: 18px -21px 0px 5px;"><span style="float: right; line-height: 17px;"> <?php
					if ( SMPRO === true && ! is_multisite() ) {
                		$plug_page .= '<a href="admin.php?page=smart-manager&action=sm-settings">Settings</a> | ';
					} else {
						$plug_page = '';
					}
		            
					$sm_old = '';

		            if ( isset($_GET['page']) && ($_GET['page'] == "smart-manager-woo" || $_GET['page'] == "smart-manager-wpsc")) {
						$sm_old = '<a href="'. admin_url('edit.php?post_type=product&page='.$_GET['page']) .'" title="'. __( 'Switch back to Smart Manager', 'smart-manager' ) .'"> ' . __( 'Switch back to Smart Manager', 'smart-manager' ) .'</a> | ';
		            }       

                    if ( SMPRO === true ) {
	                    if ( !wp_script_is( 'thickbox' ) ) {
	                        if ( !function_exists( 'add_thickbox' ) ) {
	                            require_once ABSPATH . 'wp-includes/general-template.php';
	                        }
	                        add_thickbox();
	                    }
	                    $before_plug_page = '<a href="edit.php#TB_inline?max-height=420px&inlineId=smart_manager_post_query_form" title="Send your query" class="thickbox" id="support_link">Need Help?</a> | ';
	                    $before_plug_page = apply_filters( 'sm_before_plug_page', $before_plug_page );
	                    if (is_super_admin()) {
	                        $before_plug_page .= '<a href="options-general.php?page=smart-manager-settings">Settings</a> | ';
	                    }
	                    
	                }
						//			printf ( __ ( '%1s%2s%3s<a href="%4s" target=_storeapps>Docs</a>' , 'smart-manager'), $before_plug_page, $plug_page, $after_plug_page, "http://www.storeapps.org/support/documentation/" );
							printf ( __ ( '%1s%2s<a href="%3s" target="_blank">Docs</a>' , 'smart-manager'), $sm_old, $before_plug_page, "http://www.storeapps.org/support/documentation/smart-manager" );
							?>
							</span><?php
						_e( '10x productivity gains with store administration. Quickly find and update products, orders and customers', 'smart-manager' );
						?></p>
						</h2>
						<h6 align="right"><?php
								if (! $is_pro_updated) {
									$admin_url = ADMIN_URL . "plugins.php";
									$update_link = __( 'An upgrade for Smart Manager Pro', 'smart-manager' ) . " " . $latest_version . " " . __( 'is available.', 'smart-manager' ) . " " . "<a align='right' href=$admin_url>" . __( 'Click to upgrade.', 'smart-manager' ) . "</a>";
									$this->display_notice( $update_link );
								}
								?>

						</h6>
						
						<?php
							if ( SMPRO === false && (get_option('sm_in_app_promo') == 0) ) {
						?>
						<div id="message" class="updated fade">
						
						<p><?php
								printf( ('<b>' . __( 'Important:', 'smart-manager' ) . '</b> ' . __( 'Upgrade to Pro to get features like \'<i>Batch Update</i>\' , \'<i>Export CSV</i>\' , \'<i>Duplicate Products</i>\' &amp; many more...', 'smart-manager' ) . " " . '<br /><a href="%1s" target=_storeapps>' . " " .__( 'Learn more about Pro version', 'smart-manager' ) . '</a> ' . __( 'or take a', 'smart-manager' ) . " " . '<a href="%2s" target=_livedemo>' . " " . __( 'Live Demo', 'smart-manager' ) . '</a>'), 'http://www.storeapps.org/product/smart-manager', 'http://demo.storeapps.org/?demo=sm-woo' );
								?>
						</p>
						</div>
						<?php
							}
						?>
				<br />

				<div id="sm_top_bar"></div>
	            <table id="sm_editor_grid" ></table>
	            <div id="sm_pagging_bar"></div>
	            
				<div id="sm_beta_social_links" class="wrap">
					<?php echo $this->add_social_links(); ?>
				</div>

				<?php
			
		}

		//Function for showing the sm-privilege settings
		function show_privilege_page() {
			if (file_exists( $this->plugin_path . '/pro/sm-privilege.php' )) {
				include_once ($this->plugin_path . '/pro/sm-privilege.php');
				return;
			} else {
				$error_message = __( "A required Smart Manager file is missing. Can't continue. ", 'smart-manager' );
			}
		}

		//function to add social links
		function add_social_links($prefix = '') {
			$social_link = '<style type="text/css">
		                        div > iframe {
		                            vertical-align: middle;
		                            padding: 5px 2px 0px 0px;
		                        }
		                        iframe[id^="twitter-widget"] {
		                        	max-height: 1.5em;
		                            max-width: 10.3em;
		                        }
		                        iframe#fb_like_' . $prefix . ' {
		                        	max-height: 1.5em;
		                            max-width: 6em;
		                        }
		                        span > iframe {
		                            vertical-align: middle;
		                        }
		                    </style>';
		    $social_link .= '<a href="https://twitter.com/storeapps" class="twitter-follow-button" data-show-count="true" data-dnt="true" data-show-screen-name="false">Follow</a>';
		    $social_link .= "<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>";
		    $social_link .= '<iframe id="fb_like_' . $prefix . '" src="http://www.facebook.com/plugins/like.php?href=https%3A%2F%2Fwww.facebook.com%2Fpages%2FStore-Apps%2F614674921896173&width=100&layout=button_count&action=like&show_faces=false&share=false&height=21"></iframe>';
		    $social_link .= '<script src="//platform.linkedin.com/in.js" type="text/javascript">lang: en_US</script><script type="IN/FollowCompany" data-id="3758881" data-counter="right"></script>';

		    return $social_link;
		}

		//function to display notices
		function display_notice($notice) {
			echo "<div id='message' class='updated fade'>
		             <p>";
			echo _e( $notice );
			echo "</p></div>";
		}

		//function to error messages
		function display_err() {
			echo "<div id='notice' class='error'>";
			echo "<b>" . __( 'Error:', 'smart-manager' ) . "</b>" . $this->error_message;
			echo "</div>";
		}
	}
}


$GLOBALS['smart_manager_beta'] = new Smart_Manager();
