<?php
/*
Plugin Name: Smart Manager for WP e-Commerce
Plugin URI: http://www.storeapps.org/smart-manager-for-wp-e-commerce/
Description: <strong>Lite Version Installed</strong> 10x productivity gains with WP e-Commerce store administration. Quickly find and update products, orders and customers.
Version: 1.1
Author: Store Apps
Author URI: http://www.storeapps.org/about/
Copyright (c) 2010, 2011 Store Apps All rights reserved.
*/

//Hooks

register_activation_hook ( __FILE__, 'sm_activate' );
register_deactivation_hook ( __FILE__, 'sm_deactivate' );

/**
 * Registers a plugin function to be run when the plugin is activated.
 */
function sm_activate() {
}

/**
 * Registers a plugin function to be run when the plugin is deactivated.
 */
function sm_deactivate() {
}

function get_latest_version() {
	$plugin_info = get_site_transient ( 'update_plugins' );
	$latest_version = $plugin_info->response [SM_PLUGIN_FILE]->new_version;
	return $latest_version;
}

function get_user_sm_version() {
	$plugin_info = get_plugins ();
	$user_version = $plugin_info [SM_PLUGIN_FILE] ['Version'];
	
	return $user_version;
}

function is_pro_updated() {
	$user_version = get_user_sm_version ();
	$latest_version = get_latest_version ();
	return version_compare ( $user_version, $latest_version, '>=' );
}

/**
 * Throw an error on admin page when WP e-Commerece plugin is not activated.
 */
if (is_admin ()) {
	// BOF automatic upgrades
	include ABSPATH . 'wp-includes/pluggable.php';
	$plugin = plugin_basename ( __FILE__ );
	define ( 'SM_PLUGIN_FILE', $plugin );
	define ( 'STORE_APPS_URL', 'http://www.storeapps.org/' );
	// EOF
	
	add_action ( 'admin_notices', 'sm_admin_notices' );
	add_action ( 'admin_init', 'sm_admin_init' );
	
	function sm_admin_init() {
		
		$plugin_info = get_plugins ( '/smart-manager' );
		$ext_version = '3.3.1';
		wp_register_script ( 'sm_ext_base', plugins_url ( '/ext/ext-base.js', __FILE__ ), array (), $ext_version );
		wp_register_script ( 'sm_ext_all', plugins_url ( '/ext/ext-all.js', __FILE__ ), array ('sm_ext_base' ), $ext_version );
		wp_register_script ( 'sm_main', plugins_url ( '/sm/smart-manager.js', __FILE__ ), array ('sm_ext_all' ), $plugin_info ['Version'] );
		wp_register_style ( 'sm_ext_all', plugins_url ( '/ext/ext-all.css', __FILE__ ), array (), $ext_version );
		wp_register_style ( 'sm_main', plugins_url ( '/sm/smart-manager.css', __FILE__ ), array ('sm_ext_all' ), $plugin_info ['Version'] );
		
		if (file_exists ( (dirname ( __FILE__ )) . '/pro/sm.js' )) {
			wp_register_script ( 'sm_functions', plugins_url ( '/pro/sm.js', __FILE__ ), array ('sm_main' ), $plugin_info ['Version'] );
			define ( 'SMPRO', true );
		} else {
			define ( 'SMPRO', false );
		}
		
		if (SMPRO === true) {
			include ('pro/upgrade.php');
			add_action ( 'after_plugin_row_' . plugin_basename ( __FILE__ ), 'plugin_row', '', 1 );
			add_action ( 'in_plugin_update_message-' . plugin_basename ( __FILE__ ), 'sm_update_notice');
		}
	}
	
	function sm_admin_notices() {
		if (! is_plugin_active ( 'wp-e-commerce/wp-shopping-cart.php' )) {
			echo '<div id="notice" class="error"><p>';
			_e ( '<b>Smart Manager</b> add-on requires <a href="http://getshopped.org/">WP e-Commerce</a> plugin. Please install and activate it.' );
			echo '</p></div>', "\n";
		}
	}
	
	function sm_admin_scripts() {
		if (file_exists ( (dirname ( __FILE__ )) . '/pro/sm.js' )) {
			wp_enqueue_script ( 'sm_functions' );
		}
		wp_enqueue_script ( 'sm_main' );
	}
	
	function sm_admin_styles() {
		wp_enqueue_style ( 'sm_main' );
	}
	
	function wpsc_add_modules_admin_pages($page_hooks, $base_page) {
		$page = add_submenu_page ( $base_page, 'Smart Manager', 'Smart Manager', 'manage_options', 'smart-manager', 'sm_show_console' );
		
		if ($_GET ['action'] != 'sm-settings') {// not be include for settings page
			add_action ( 'admin_print_scripts-' . $page, 'sm_admin_scripts' );
		}
		
		add_action ( 'admin_print_styles-' . $page, 'sm_admin_styles' );
		$page_hooks [] = $page;
		return $page_hooks;
	}
	add_filter ( 'wpsc_additional_pages', 'wpsc_add_modules_admin_pages', 10, 2 );
	
	function sm_show_console() {
		$latest_version = get_latest_version ();
		$is_pro_updated = is_pro_updated ();
				
		if ($_GET ['action'] == 'sm-settings') {
			sm_settings_page ();
		} else {
			$wp_ecom_path = WP_PLUGIN_DIR . '/wp-e-commerce/';
			$base_path = WP_PLUGIN_DIR . '/' . str_replace ( basename ( __FILE__ ), "", plugin_basename ( __FILE__ ) ) . 'sm/';
			?>
<div class="wrap">
<div id="icon-smart-manager" class="icon32"><br />
</div>

<h2><?php
			echo _e ( 'Smart Manager' );
			echo ' ';
			if (SMPRO === true) {
				echo _e ( 'Pro' );
			} else {
				echo _e ( 'Lite' );
			}
			?>
   		<p class="wrap"><span style="float: right"> <?php
			if (SMPRO === true) {
				printf ( __ ( '<a href="admin.php?page=smart-manager&action=sm-settings">Settings</a> |
                           <a href="%1s" target=_storeapps>Need Help?</a>' ), "http://www.storeapps.org/support" );
			} else {
				printf ( __ ( '<a href="%1s" target=_storeapps>Need Help?</a>' ), "http://www.storeapps.org/support" );
			}
			?>
			</span><?php
			echo __ ( '10x productivity gains with store administration. Quickly find and update products, orders and customers' );
			?></p>
</h2>
<h6 align="right"><?php
			if (! $is_pro_updated) {
				$update_link = "An upgrade for Smart Manager Pro  $latest_version is available. <a align='right' href='admin.php/plugins.php'> Click to upgrade. </a>";
				display_notice ( $update_link );
			}
			?>

</h6>
</div>

<?php
			if (SMPRO === false) {
				?>
<div id="message" class="updated fade">
<p><?php
				printf ( __ ( '<b>Important:</b> Upgrading to Pro gives you powerful features and helps us continue innovating. <a href="%1s" target=_storeapps>Learn more about Pro version here</a> or take a <a href="%2s" target=_livedemo>Live Demo here</a>.' ), 'http://storeapps.org/', 'http://demo.storeapps.org/' );
				?></p>
</div>
<?php
			}
			?>
		
		<?php
			// checking the version for WPSC plugin
			define ( 'IS_WPSC37', version_compare ( WPSC_VERSION, '3.8', '<' ) );
			define ( 'IS_WPSC38', version_compare ( WPSC_VERSION, '3.8', '>=' ) );
			
			define ( 'ADMIN_URL', get_admin_url () ); //defining the admin url
			define ( 'SM_PLUGIN_DIRNAME', plugins_url ( '', __FILE__ ) );
			define ( 'IMG_URL', SM_PLUGIN_DIRNAME . '/images/' );
			
			$json_filename = (IS_WPSC37) ? 'json37' : 'json38';
			define ( 'JSON_URL', SM_PLUGIN_DIRNAME . "/sm/$json_filename.php" );
			
			$error_message = '';
			if (file_exists ( $wp_ecom_path . 'wp-shopping-cart.php' )) {
				if (is_plugin_active ( 'wp-e-commerce/wp-shopping-cart.php' )) {
					require_once ($wp_ecom_path . 'wp-shopping-cart.php');
					if (IS_WPSC37 || IS_WPSC38) {
						if (file_exists ( $base_path . 'manager-console.php' )) {
							include_once ($base_path . 'manager-console.php');
							return;
						} else {
							$error_message = 'A required Smart Manager file is missing. Can\'t continue.';
						}
					
					} else {
						$error_message = 'Smart Manager currently works only with WP e-Commerce 3.7 or above.';
					}
				} else {
					$error_message = 'WP e-Commerce plugin is not activated. <br /><br />Smart Manager add-on requires WP e-Commerce plugin.';
				}
			} else {
				$error_message = '<b>Smart Manager</b> add-on requires <a href="http://getshopped.org/">WP e-Commerce</a> plugin to do its job. Please install and activate it.';
			}
			if ($error_message != '') {
				display_err ( $error_message );
				?>
                </p>
               </div>
               <?php
			}
		}
	}
	
	function sm_update_notice(){
		$plugins  = get_site_transient ( 'update_plugins' );
		$link     = $plugins->response [SM_PLUGIN_FILE]->package;
		
		echo $man_download_link = " Or <a href='$link'>click here to download the latest version.</a>";
		
	}
	
	function display_err($error_message) {
		echo "<div id='notice' class='error'>";
		echo _e ( '<b>Error: </b>' . $error_message );
		echo "</div>";
	}
	
	function display_notice($notice) {
		echo "<div id='message' class='updated fade'>
             <p>";
		echo _e ( $notice );
		echo "</p></div>";
	}
	
// EOF auto upgrade code
}
?>
