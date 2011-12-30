<?php
/*
Plugin Name: Smart Manager for WP e-Commerce
Plugin URI: http://www.storeapps.org/smart-manager-for-wp-e-commerce/
Description: <strong>Lite Version Installed</strong> 10x productivity gains with WP e-Commerce store administration. Quickly find and update products, orders and customers.
Version: 1.9
Author: Store Apps
Author URI: http://www.storeapps.org/about/
Copyright (c) 2010, 2011 Store Apps All rights reserved.
*/

//Hooks

register_activation_hook ( __FILE__, 'smart_activate' );
register_deactivation_hook ( __FILE__, 'smart_deactivate' );

/**
 * Registers a plugin function to be run when the plugin is activated.
 */

function smart_activate() {
}

/**
 * Registers a plugin function to be run when the plugin is deactivated.
 */
function smart_deactivate() {
}

function smart_get_latest_version() {
	$sm_plugin_info = get_site_transient ( 'update_plugins' );
	$latest_version = $sm_plugin_info->response [SM_PLUGIN_FILE]->new_version;
	return $latest_version;
}

function smart_get_user_sm_version() {
	$sm_plugin_info = get_plugins ();
	$user_version = $sm_plugin_info [SM_PLUGIN_FILE] ['Version'];
	return $user_version;
}

function smart_is_pro_updated() {
	$user_version = smart_get_user_sm_version ();
	$latest_version = smart_get_latest_version ();
	return version_compare ( $user_version, $latest_version, '>=' );
}

/**
 * Throw an error on admin page when WP e-Commerece plugin is not activated.
 */

if (is_admin ()) {
	include ABSPATH . 'wp-includes/pluggable.php';
	$plugin = plugin_basename ( __FILE__ );
	define ( 'SM_PLUGIN_DIR',dirname($plugin));
	define ( 'SM_PLUGIN_FILE', $plugin );
	define ( 'STORE_APPS_URL', 'http://www.storeapps.org/' );
	
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	include_once (ABSPATH . WPINC . '/functions.php');
	$old_plugin = 'smart-manager/smart-manager.php';	

	if (is_plugin_active ( $old_plugin )) {
		deactivate_plugins ( $old_plugin );
		$action_url = "plugins.php?action=activate&plugin=$plugin&plugin_status=all&paged=1";
		$url = wp_nonce_url ( $action_url, 'activate-plugin_' . $plugin );
		update_option ( 'recently_activated', array ($plugin => time () ) + ( array ) get_option ( 'recently_activated' ) );
		
		if (headers_sent ())
			echo "<meta http-equiv='refresh' content='" . esc_attr ( "0;url=plugins.php?deactivate=true&plugin_status=$status&paged=$page" ) . "' />";
		else{
			wp_redirect ( str_replace('&amp;','&', $url ) );
			exit ();
		}
	}
	
	add_action ( 'admin_notices', 'smart_admin_notices' );
	//	admin_init is triggered before any other hook when a user access the admin area. 
	// This hook doesn't provide any parameters, so it can only be used to callback a specified function.
	add_action ( 'admin_init', 'smart_admin_init' );
	
	function smart_admin_init() {
		$plugin_info = get_plugins ();
		$sm_plugin_info = $plugin_info [SM_PLUGIN_FILE];
		$ext_version = '3.3.1';
		if (is_plugin_active ( 'woocommerce/woocommerce.php' ) && is_plugin_active ( basename(WPSC_URL).'/wp-shopping-cart.php' )) {
			define('WPEC_WOO_ACTIVATED',true);
		}
		wp_register_script ( 'sm_ext_base', plugins_url ( '/ext/ext-base.js', __FILE__ ), array (), $ext_version );
		wp_register_script ( 'sm_ext_all', plugins_url ( '/ext/ext-all.js', __FILE__ ), array ('sm_ext_base' ), $ext_version );
		if ($_GET['post_type'] == 'wpsc-product') {
			wp_register_script ( 'sm_main', plugins_url ( '/sm/smart-manager.js', __FILE__ ), array ('sm_ext_all' ), $sm_plugin_info ['Version'] );
			define('WPSC_RUNNING', true);
			define('WOO_RUNNING', false);
		} else if ($_GET['post_type'] == 'product') {
			wp_register_script ( 'sm_main', plugins_url ( '/sm/smart-manager-woo.js', __FILE__ ), array ('sm_ext_all' ), $sm_plugin_info ['Version'] );
			define('WPSC_RUNNING', false);
			define('WOO_RUNNING', true);
		}
		wp_register_style ( 'sm_ext_all', plugins_url ( '/ext/ext-all.css', __FILE__ ), array (), $ext_version );
		wp_register_style ( 'sm_main', plugins_url ( '/sm/smart-manager.css', __FILE__ ), array ('sm_ext_all' ), $sm_plugin_info ['Version'] );
		
		if (file_exists ( (dirname ( __FILE__ )) . '/pro/sm.js' )) {
			wp_register_script ( 'sm_functions', plugins_url ( '/pro/sm.js', __FILE__ ), array ('sm_main' ), $sm_plugin_info ['Version'] );
			define ( 'SMPRO', true );
		} else {
			define ( 'SMPRO', false );
		}
		
		if (SMPRO === true) {
			include ('pro/upgrade.php');
			// this allows you to add something to the end of the row of information displayed for your plugin - 
			// like the existing after_plugin_row filter, but specific to your plugin, 
			// so it only runs once instead of after each row of the plugin display
			add_action ( 'after_plugin_row_' . plugin_basename ( __FILE__ ), 'smart_plugin_row', '', 1 );
			add_action ( 'in_plugin_update_message-' . plugin_basename ( __FILE__ ), 'smart_update_notice' );
		}
	}
	
	function smart_admin_notices() {
		if (! is_plugin_active ( 'woocommerce/woocommerce.php' ) && ! is_plugin_active ( basename(WPSC_URL).'/wp-shopping-cart.php' )) {
			echo '<div id="notice" class="error"><p>';
			_e ( '<b>Smart Manager</b> add-on requires <a href="http://getshopped.org/">WP e-Commerce</a> plugin or <a href="http://www.woothemes.com/woocommerce/">WooCommerce</a> plugin. Please install and activate it.' );
			echo '</p></div>', "\n";
		}
	}
	
	function smart_admin_scripts() {
		if (file_exists ( (dirname ( __FILE__ )) . '/pro/sm.js' )) {
			wp_enqueue_script ( 'sm_functions' );
		}
		wp_enqueue_script ( 'sm_main' );
	}
	
	function smart_admin_styles() {
		wp_enqueue_style ( 'sm_main' );
	}
	
	function smart_woo_add_modules_admin_pages() {
		$page = add_submenu_page ('edit.php?post_type=product', 'Smart Manager', 'Smart Manager', 'manage_woocommerce', 'smart-manager-woo', 'smart_show_console' );
	    
		if ($_GET ['action'] != 'sm-settings') { // not be include for settings page
			add_action ( 'admin_print_scripts-' . $page, 'smart_admin_scripts' );
		}
		add_action ( 'admin_print_styles-' . $page, 'smart_admin_styles' );
	}
	add_action ('admin_menu', 'smart_woo_add_modules_admin_pages');
	
	function smart_wpsc_add_modules_admin_pages($page_hooks, $base_page) {
		$page = add_submenu_page ( $base_page, 'Smart Manager', 'Smart Manager', 'manage_options', 'smart-manager-wpsc', 'smart_show_console' );
		
		if ($_GET ['action'] != 'sm-settings') { // not be include for settings page
			add_action ( 'admin_print_scripts-' . $page, 'smart_admin_scripts' );
		}
		
		add_action ( 'admin_print_styles-' . $page, 'smart_admin_styles' );
		$page_hooks [] = $page;
		return $page_hooks;
	}
	add_filter ( 'wpsc_additional_pages', 'smart_wpsc_add_modules_admin_pages', 10, 2 );
	function smart_show_console() {
		
		define ( 'PLUGINS_FILE_PATH', dirname(dirname( __FILE__ )) );
		define ( 'SM_PLUGIN_DIRNAME', plugins_url ( '', __FILE__ ) );
		define ( 'IMG_URL', SM_PLUGIN_DIRNAME . '/images/' );		
		
		if (WPSC_RUNNING === true) {
			// checking the version for WPSC plugin
			define ( 'IS_WPSC37', version_compare ( WPSC_VERSION, '3.8', '<' ) );
			define ( 'IS_WPSC38', version_compare ( WPSC_VERSION, '3.8', '>=' ) );
			$json_filename = (IS_WPSC37) ? 'json37' : 'json38';
		} else if (WOO_RUNNING === true) {
			$json_filename = 'woo-json';
		}
		define ( 'JSON_URL', SM_PLUGIN_DIRNAME . "/sm/$json_filename.php" );		
		define ( 'ADMIN_URL', get_admin_url () ); //defining the admin url
		define ('ABS_WPSC_URL',WP_PLUGIN_DIR.'/'.basename(WPSC_URL));
		define ('WPSC_NAME',basename(WPSC_URL));
		
		$latest_version = smart_get_latest_version ();
		$is_pro_updated = smart_is_pro_updated ();
		
		if ($_GET ['action'] == 'sm-settings') {
			smart_settings_page ();
		} else {
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
   		<p class="wrap" style="font-size: 12px"><span style="float: right"> <?php
			if (SMPRO === true) {
				printf ( __ ( '<a href="admin.php?page=smart-manager-wpsc&action=sm-settings">Settings</a> |
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
				$admin_url = ADMIN_URL . "plugins.php";
				$update_link = "An upgrade for Smart Manager Pro  $latest_version is available. <a align='right' href=$admin_url> Click to upgrade. </a>";
				smart_display_notice ( $update_link );
			}
			?>

</h6>
<h6 align="right"> 
<?php
if (SMPRO === true) {		
		$license_key = smart_get_license_key();
		if( $license_key == '' ) {
		  smart_display_notice('Please enter your license key for automatic upgrades and support to get activated. <a href="admin.php?page=smart-manager-wpsc&action=sm-settings">Enter License Key</a>');
		}
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
			$error_message = '';
			if (file_exists ( WPSC_FILE_PATH . '/wp-shopping-cart.php' )) {
				if (is_plugin_active ( WPSC_FOLDER.'/wp-shopping-cart.php' )) {
					require_once (WPSC_FILE_PATH . '/wp-shopping-cart.php');
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
			if (is_plugin_active ( basename(WPSC_URL).'/wp-shopping-cart.php' ) || is_plugin_active ( WOOCOMMERCE_TEMPLATE_URL.'woocommerce.php' )) {
				if (is_plugin_active ( WPSC_FOLDER.'/wp-shopping-cart.php' )) {
					require_once (WPSC_FILE_PATH . '/wp-shopping-cart.php');
					if (!(IS_WPSC37 || IS_WPSC38)) {
						$error_message = 'Smart Manager currently works only with WP e-Commerce 3.7 or above.';
					}
				}

				if (file_exists ( $base_path . 'manager-console.php' )) {
					include_once ($base_path . 'manager-console.php');
					return;
				} else {
					$error_message = 'A required Smart Manager file is missing. Can\'t continue.';
				}
			} else {
				$error_message = 'Smart Manager</b> add-on requires <a href="http://getshopped.org/">WP e-Commerce</a> plugin or <a href="http://www.woothemes.com/woocommerce/">WooCommerce</a> plugin. Please install and activate it.';
			}
				
			if ($error_message != '') {
				smart_display_err ( $error_message );
				?>
</p>
</div>
<?php
			}
		}
	}
	
	function smart_update_notice() {
		$plugins = get_site_transient ( 'update_plugins' );
		$link = $plugins->response [SM_PLUGIN_FILE]->package;
		
		echo $man_download_link = " Or <a href='$link'>click here to download the latest version.</a>";
	
	}
	
	function smart_display_err($error_message) {
		echo "<div id='notice' class='error'>";
		echo _e ( '<b>Error: </b>' . $error_message );
		echo "</div>";
	}
	
	function smart_display_notice($notice) {
		echo "<div id='message' class='updated fade'>
             <p>";
		echo _e ( $notice );
		echo "</p></div>";
	}
	
// EOF auto upgrade code
}
?>
