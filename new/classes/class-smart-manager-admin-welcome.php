<?php
/**
 * Welcome Page Class
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


/**
 * SC_Admin_Welcome class
 */
class Smart_Manager_Admin_Welcome {

	/**
	 * Hook in tabs.
	 */

	public $sm_redirect_url,
			$plugin_url;

	public function __construct() {

		add_action( 'admin_menu', array( $this, 'admin_menus') );
		add_action( 'admin_head', array( $this, 'admin_head' ) );
		add_action( 'admin_init', array( $this, 'smart_manager_welcome' ),11 );

		$this->plugin_url = plugins_url( '', __FILE__ );
	}

	/**
	 * Add admin menus/screens.
	 */
	public function admin_menus() {

		if ( empty( $_GET['page'] ) ) {
			return;
		}

		$welcome_page_name  = __( 'About Smart Manager', 'smart-manager' );
		$welcome_page_title = __( 'Welcome to Smart Manager', 'smart-manager' );



		switch ( $_GET['page'] ) {
			case 'sm-about' :
				$page = add_dashboard_page( $welcome_page_title, $welcome_page_name, 'edit_pages', 'sm-about', array( $this, 'about_screen' ) );
				break;
			case 'sm-faqs' :
			 	$page = add_dashboard_page( $welcome_page_title, $welcome_page_name, 'edit_pages', 'sm-faqs', array( $this, 'faqs_screen' ) );
				break;
			case 'sm-beta' :
			 	$page = add_dashboard_page( $welcome_page_title, $welcome_page_name, 'edit_pages', 'sm-beta', array( $this, 'sm_beta_screen' ) );
				break;
		}
	}

	/**
	 * Add styles just for this page, and remove dashboard page links.
	 */
	public function admin_head() {
		remove_submenu_page( 'index.php', 'sm-about' );
		remove_submenu_page( 'index.php', 'sm-faqs' );
		remove_submenu_page( 'index.php', 'sm-beta' );

		?>
		<style type="text/css">
			/*<![CDATA[*/
			.about-wrap h3 {
				margin-top: 1em;
				margin-right: 0em;
				margin-bottom: 0.1em;
				font-size: 1.25em;
				line-height: 1.3em;
			}
			.about-wrap p {
				margin-top: 0.6em;
				margin-bottom: 0.8em;
				line-height: 1.6em;
				font-size: 14px;
			}
			.about-wrap .feature-section {
				padding-bottom: 5px;
			}
			/*]]>*/
		</style>
		<?php
	}

	/**
	 * Intro text/links shown on all about pages.
	 */
	private function intro() {
		
		if ( function_exists('smart_manager_get_data') ) {
			$plugin_data = smart_manager_get_data();
			$version = $plugin_data['Version'];
		} else {
			$version = '';
		}

		if ( WPSC_WOO_ACTIVATED === true || WOO_ACTIVATED === true ) {
			$this->sm_redirect_url = admin_url( 'edit.php?post_type=product&page=smart-manager-woo' );
		} else if ( WPSC_ACTIVATED === true ) {
			$this->sm_redirect_url = admin_url( 'edit.php?post_type=wpsc-product&page=smart-manager-wpsc' );
		}

		?>
		<h1><?php printf( __( 'Welcome to Smart Manager %s', 'smart-manager' ), $version ); ?></h1>

		<h3><?php _e("Thanks for installing! We hope you enjoy using Smart Manager.", 'smart-manager'); ?></h3>

		<div class="feature-section col two-col"><br>
			<div class="col-1">
				<!-- <p class="woocommerce-actions"> -->
					<a href="<?php echo $this->sm_redirect_url; ?>" class="button button-primary"><?php _e( 'Get Started!', 'smart-manager' ); ?></a>
					<a href="options-general.php?page=smart-manager-settings" class="button button-primary" target="_blank"><?php _e( 'Settings', 'smart-manager' ); ?></a>
					<a href="http://www.storeapps.org/support/documentation/smart-manager" class="docs button button-primary" target="_blank"><?php _e( 'Docs', 'smart-manager' ); ?></a>
				<!-- </p> -->
			</div>
		</div>

		<h2 class="nav-tab-wrapper">
			<a class="nav-tab <?php if ( $_GET['page'] == 'sm-about' ) echo 'nav-tab-active'; ?>" href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'sm-about' ), 'index.php' ) ) ); ?>">
				<?php _e( "Know Smart Manager", 'smart-manager' ); ?>
			</a>
			<a class="nav-tab <?php if ( $_GET['page'] == 'sm-faqs' ) echo 'nav-tab-active'; ?>" href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'sm-faqs' ), 'index.php' ) ) ); ?>">
				<?php _e( "FAQ's", 'smart-manager' ); ?>
			</a>
			<a class="nav-tab <?php if ( $_GET['page'] == 'sm-beta' ) echo 'nav-tab-active'; ?>" href="<?php echo esc_url( admin_url( add_query_arg( array( 'page' => 'sm-beta' ), 'index.php' ) ) ); ?>">
				<?php _e( "Smart Manager Beta", 'smart-manager' ); ?> 
			</a>
		</h2>
		<?php
	}


	/**
	 * Output the about screen.
	 */
	public function about_screen() {
		?>
		<div class="wrap about-wrap">

			<?php $this->intro();?>
			<div>
				<p><?php echo __( 'Smart Manager is a unique, revolutionary tool that gives you the power to <b> boost your productivity by 10x </b> in managing your store by using a using a <b>familiar, single page, spreadsheet like interface</b>. ', 'smart-manager' ); ?></p>
				<!-- <div class="headline-feature feature-video">
					<?php echo $embed_code = wp_oembed_get('http://www.youtube.com/watch?v=kOiBXuUVF1U', array('width'=>5000, 'height'=>560)); ?>
				</div> -->
			</div>

			<div>
				<center><h3><?php echo __( 'What is possible', 'smart-manager' ); ?></h3></center>
				<div class="feature-section col three-col" >
					<div>
						<h4><?php echo __( 'One Stop Dashboard', 'smart-manager' ); ?></h4>
						<p>
							<?php echo __( 'You can easily and efficiently manage <b>products, product variations, customers and orders</b> from a single page.', 'smart-manager' ); ?>
						</p>
					</div>
					<div>
						<h4><?php echo __( 'Inline Editing', 'smart-manager' ); ?></h4>
						<p>
							<?php echo sprintf(__( 'You can quickly update your products, customers and orders in the grid itself. This facilitates editing of multiple rows at a time instead of editing and sacing each row separately, %s.', 'smart-manager' ), '<a href="http://www.storeapps.org/support/documentation/smart-manager/#inline-editing" target="_blank">' . __( 'see how', 'smart-manager' ) . '</a>' ); ?>
						</p>
					</div>
					<div class="last-feature">
						<h4><?php echo __( 'Filter/Search Records', 'smart-manager' ); ?></h4>
						<p>
							<?php echo sprintf(__( 'If you would like to filter the records, you can easily do the same by simply entering keyword in the “Search” field at the top of the grid (%s). If you need to have a more specific search result, then you can switch to “%s“ and then search.', 'smart-manager' ), '<a href="http://www.storeapps.org/support/documentation/smart-manager/#filter-search-records" target="_blank">' . __( 'see how', 'smart-manager' ) . '</a>', '<a href="https://www.youtube.com/watch?v=hX7CcZYo060" target="_blank">' . __( 'Advanced Search', 'smart-manager' ) . '</a>' ); ?>
						</p>
					</div>
				</div>
				<div class="feature-section col three-col" >
					<div>
						<h4>
							<?php 
								if (SMPRO === true) {
									echo __( 'Batch Update', 'smart-manager' );											
								} else {
									echo sprintf(__( 'Batch Update (only in %s)', 'smart-manager' ), '<a href="http://www.storeapps.org/product/smart-manager/" target="_blank">' . __( 'Pro', 'smart-manager' ) . '</a>' );
								}
							?>
						</h4>
						<p>
							<?php echo sprintf(__( 'You can change / update multiple fields of the entire store OR for selected items by selecting multiple records and then simply click on “Batch Update”, %s.', 'smart-manager' ), '<a href="http://www.storeapps.org/support/documentation/smart-manager/#batch-update" target="_blank">' . __( 'see how', 'smart-manager' ) . '</a>' ); ?>
						</p>
					</div>
					<div>
						<h4>
							<?php 
								if (SMPRO === true) {
									echo __( 'Duplicate Products', 'smart-manager' );											
								} else {
									echo sprintf(__( 'Duplicate Products (only in %s)', 'smart-manager' ), '<a href="http://www.storeapps.org/product/smart-manager/" target="_blank">' . __( 'Pro', 'smart-manager' ) . '</a>' );
								}
							?>
						</h4>
						<p>
							<?php echo sprintf(__( 'You can duplicate products of the entire store OR selected products by simply selecting products and then click on “Duplicate Products”, %s.', 'smart-manager' ), '<a href="http://www.storeapps.org/support/documentation/smart-manager/#duplicate-products" target="_blank">' . __( 'see how', 'smart-manager' ) . '</a>' ); ?>
						</p>
					</div>
					<div class="last-feature">
						<h4><?php 
								if (SMPRO === true) {
									echo __( 'Export CSV', 'smart-manager' );											
								} else {
									echo sprintf(__( 'Export CSV (only in %s)', 'smart-manager' ), '<a href="http://www.storeapps.org/product/smart-manager/" target="_blank">' . __( 'Pro', 'smart-manager' ) . '</a>' );
								}
							?>
						</h4>
						<p>
							<?php echo __( 'You can export all the records OR filtered records (<i>filtered using “Search” or “Advanced Search”</i>) by simply clicking on the “Export CSV” button at the bottom right of the grid.', 'smart-manager' ); ?>
						</p>
					</div>
				</div>
			</div>
			<div class="changelog" align="center">
				<h4><?php _e( 'Do check out Some of our other products!', 'smart-manager' ); ?></h4>
				<p><a target="_blank" href="<?php echo esc_url('http://www.storeapps.org/shop/'); ?>"><?php _e('Let me take to product catalog', 'smart-manager'); ?></a></p>
			</div>
		</div>

		<?php
	}

	/**
	 * Output the about screen.
	 */
	public function sm_beta_screen() {
		?>
		<div class="wrap about-wrap">

		<?php $this->intro(); ?>

			<div>
				<div class="headline-feature">
					<h2 style="text-align:center;"><?php _e( 'Introducing Smart Manager Beta' ); ?></h2>
					<div class="featured-image">
						<img src="<?php echo $this->plugin_url . '/../../images/smart-manager-beta.png'?>" />
					</div>
					<p><?php echo __( 'Smart Manager Beta is nothing but a transformed more bigger version of the previous Smart Manager. It has ton’s of functionality and only promises to be better than what Smart Manager ever was.', 'smart-manager' ); ?></p>
				</div>
				<center><h3><?php echo __( 'Digging Deeper into Smart Manager Beta...', 'smart-manager' ); ?></h3></center>
				<div class="feature-section col three-col" >
					<div>
						<h4><?php echo __( 'Everything Wordpress', 'smart-manager' ); ?></h4>
						<p>
							<?php echo sprintf(__( 'Unlike previous Smart Manager, Smart Manager gives you the power to manage %s and %s.', 'smart-manager' ),'<code>all post types</code>', '<code>any custom field</code>'); ?>
						</p>
					</div>
					<div>
						<h4><?php echo __( 'Infinite Scrolling', 'smart-manager' ); ?></h4>
						<p>
							<?php echo sprintf(__( 'Unlike the older version of Smart Manager that displayed records in various pages, with Smart Manager Beta all the records are in one single page itself enabling %s and %s.', 'smart-manager' ), '<code>one glance at all records</code>', '<code>faster loading</code>'); ?>
						</p>
					</div>
					<div class="last-feature">
						<h4><?php echo __( 'Improved Performance', 'smart-manager' ); ?></h4>
						<p>
							<?php echo __( 'We\'ve worked on every inch of the coding to make Smart Manager Beta way faster and the performance a lot smoother.', 'smart-manager' ); ?>
						</p>
					</div>
				</div>
				<a href="<?php $this->sm_redirect_url .= '&sm_beta=1'; echo $this->sm_redirect_url; ?>" class="button button-primary"><?php _e( 'Try Smart Manager Beta', 'smart-manager' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the FAQ's screen.
	 */
	public function faqs_screen() {
		?>
		<div class="wrap about-wrap">

			<?php $this->intro(); ?>
        
            <h3><?php echo __("FAQ / Common Problems", 'smart-manager'); ?></h3>

            <?php
            	$faqs = array(
            				array(
            						'que' => __( 'Smart Manager grid is empty?', 'smart-manager' ),
            						'ans' => __( 'Make sure you are using latest version of Smart Manager. If still the issue persist, deactivate all plugins except WooCommerce/WPeCommerce & Smart Manager. Recheck the issue, if the issue still persists, contact us. If the issue goes away, re-activate other plugins one-by-one & re-checking the fields, to find out which plugin is conflicting. Inform us about this issue.', 'smart-manager' )
            					),
            				array(
            						'que' => __( 'Can I import using Smart Manager?', 'smart-manager' ),
            						'ans' => __( 'Sorry! currently you cannot import using Smart Manager.', 'smart-manager' )
            					),
            				array(
            						'que' => __( 'Smart Manager search functionality not working', 'smart-manager' ),
            						'ans' => __( 'Request you to kindly deactivate and activate the Smart Manager plugin once and then have a recheck with the Smart Manager search functionality.', 'smart-manager' )
            					),
            				array(
            						'que' => __( 'How do I upgrade a Lite version to a Pro version?', 'smart-manager' ),
            						'ans' => __( 'Request you to kindly first delete and deactivate the Smart Manager Lite plugin from your site and then upload and activate the Smart Manager Pro plugin on your site once and you are done.', 'smart-manager' )
            					),
            				array(
            						'que' => __( 'Updating variation parent price/sales price not working?', 'smart-manager' ),
            						'ans' => __( 'Smart Manager is based on WooCommerce and WPeCommerce and the same e-commerce plugins sets the price/sales price of the variation parents automatically based on the price/sales price of its variations.', 'smart-manager' )
            					),
            				array(
            						'que' => __( 'Is Smart Manager, WPML compatible?', 'smart-manager' ),
            						'ans' => __( 'Smart Manager is not fully WPML compatible. In other words, Smart Manager will display and let you manage the different translations of the product, however, it will not update all the translations on updation of any one of the translations for a given product.', 'smart-manager' )
            					),
            				array(
            						'que' => __( 'How to manage any custom field of any custom plugin using Smart Manager?', 'smart-manager' ),
            						'ans' => sprintf(__( 'Smart Manager by default considers all the product custom fields that are stored in the wordpress postmeta table. So, if any plugin adds custom fields to wordpress %s table then only Smart Manager lets you manage the same else managing the same is currently not possible. Also, you can have a check whether you are able to manage the same custom field using Smart Manager Beta (a complete revamped version of Smart Manager)', 'smart-manager' ), '<code>postmeta</code>' )
            					),
            				array(
            						'que' => __( 'How can I increase the number of rows per page?', 'smart-manager' ),
            						'ans' => sprintf(__( 'You can modify number of records to be shown in the Smart Manager on one page.
														For that, you\'ll have to make manual change in your wordpress database and also maintain the same changes whenever you update your Smart Manager copy.
														%s Go to your database, open table wp_options & look for the row having \'%s\' as \'%s\'.
														%s Enter \'%s\' as number of records you want to display in the Smart Manager on one page and click on save. That\'s it!
														%s: Updating the \'%s\' option to a larger value can hamper some processes like loading & updating data.', 'smart-manager' ), '<ul><li>', '<code>option_name</code>', '<code>_sm_set_record_limit</code>', '</li><li>', '<code>option_name</code>', '</li></ul><br/><b>P.S.</b>', '<code>_sm_set_record_limit</code>'  )
            					),
            				array(
            						'que' => __( 'How to add columns to Smart Manager dashboard?', 'smart-manager' ),
            						'ans' => sprintf(__( 'To show/hide columns from the Smart Manager dashboard, click the %s next to any of the column headers and simply check/uncheck the columns from the \'%s\' sub-menu. %s.', 'smart-manager' ), '<code>down arrow</code>', '<code>Columns</code>', '<a href="http://www.storeapps.org/support/documentation/smart-manager/#add-products" target="_blank">' . __( 'See how', 'smart-manager' ) . '</a>')
            					),
            				array(
            						'que' => __( 'How to sort on the entire database in Smart Manager?', 'smart-manager' ),
            						'ans' => __( 'Currently, Smart Manager sorts only the records visible on a particular page and not on the entire database. However, we do have same functionality in the roadmap for Smart Manager Beta and would be implemented soon.', 'smart-manager' )
            					),
            				array(
            						'que' => __( 'How to reset the sort in Smart Manager?', 'smart-manager' ),
            						'ans' => sprintf(__( 'Currently, for resetting the sort, you would need to make changes at the databse level.
		            									%s Search for \'%s\' option_name in the wordpress %s table and simply delete the same and then refresh Smart Manager and the sort should be resetted.
		            									%s So, for example, if the login_email is \'%s\' and you want to reset the sort for \'%s\' dashboard then you would need to search for \'%s\' in the options table and delete the same.', 'smart-manager' ), '<br/><br/>', '<code>_sm_{login email}_{Smart Manager Dashboard}</code>', '<code>options</code>', '<br/><br/>', '<code>abc@wordpress.com</code>', '<code>Products</code>', '<code>_sm_abc@wordpress.com_Products</code>')
            					),
            				array(
            						'que' => __( 'How can I batch update entire search result spread across multiple pages?', 'smart-manager' ),
            						'ans' => sprintf(__( 'For batch updating the entire search result, you need to select the checkbox on the header row and then select the \'%s\' option in the batch update wibndow and clicking on update button will update all the records in the search result', 'smart-manager' ), '<code>All items in the store(including variations)</code>')
            					),
            				array(
            						'que' => __( 'How to get rid of smart manager pro advertising in backend?', 'smart-manager' ),
            						'ans' => sprintf(__( 'In order to get rid of the advertising, you would need to make some code level changes and also maintian the same whenever you update your copy of Smart Manager
            									%s To remove the same, please follow the below steps:
            									%s Go to your Smart Manager folder, open smart-manager.php file. 
            									%s Find the \'%s\' line of code.
            									%s Comment that particular html span element. Save the file.
            									%s Refresh your Smart Manager dashboard page. The ad won\'t be visible now.', 'smart-manager' ), '<br/><br/>', '<br/> <ul><li>', '</li><li>', '<code>span style="float:right; margin: -6px -21px -20px 0px;"</code>', '</li><li>', '</li></ul>')
            					),
            				array(
            						'que' => __( 'How to get increase the product thumbnail image size in Smart Manager?', 'smart-manager' ),
            						'ans' => sprintf(__( 'In order to increase the product thumbnail image size, you would need to make some code level changes and also maintian the same whenever you update your copy of Smart Manager
            									%s To remove the same, please follow the below steps:
            									%s Go to your Smart Manager folder, open \'smart-manager-for-wp-e-commerce/sm/smart-manager-woo.js\' file. 
            									%s Find the \'%s\' line of code.
            									%s Make changes to the \'%s\' and \'%s\' CSS property values in the line of code. Save the file.
            									%s Refresh your Smart Manager dashboard page. The ad won\'t be visible now.', 'smart-manager' ), '<br/><br/>', '<br/> <ul><li>', '</li><li>', '<code>img width=16px height=16px src="\' + record.data.thumbnail + \'"</code>', '</li><li>', '<code>width</code>', '<code>height</code>', '</li></ul>')
            					)            				
            			);

            	$index = 0;
            	foreach ( $faqs as $faq ) {
            		$index++;
            		echo '<h4>' . sprintf(__( '%s. %s', 'smart-manager' ), $index, $faq['que'] ) . '</h4>';
            		echo '<p>' . $faq['ans'] . '</p>';
            	}
            ?>

		</div>
		
		<?php
	}


	/**
	 * Sends user to the welcome page on first activation.
	 */
	public function smart_manager_welcome() {

       	if ( ! get_option( '_sm_activation_redirect' ) ) {
			return;
		}
		
		// Delete the redirect transient
		delete_option( '_sm_activation_redirect' );

		wp_redirect( admin_url( 'index.php?page=sm-about' ) );
		exit;

	}
}

new Smart_Manager_Admin_Welcome();