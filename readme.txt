=== Smart Manager for e-Commerce ===
Contributors: niravmehta, ratnakar.dubey, rupali, tarun
Donate link: http://www.storeapps.org/
Tags: administration, wp e-commerce, woo commerce, woocommerce, wpec, wpsc, jigoshop, ecommerce, management, batch update, bulk edit, mass update, wp ecommerce, productivity, products, variations, orders, customers
Requires at least: 2.0.2
Tested up to: 3.5.1
Stable tag: 3.1.1

10x productivity gains with WP e-Commerce & WooCommerce store administration. 
Manage products, variations, orders and customers reliably using a fast and familiar interface.

== Description ==
Smart Manager is a unique, revolutionary tool that gives you the **power to efficiently manage products, 
product variations, customers and orders** using the popular WordPress e-Commerce plugin 
from GetShopped / Instinct or WooCommerce from WooThemes.

As a business owner, **your time is better spent on marketing and customer service** than on store administration. 
Unfortunately store administration with WordPress e-Commerce can take significant time. Day after day.

Smart Manager will **boost your productivity 10x** in managing products, customers and orders. 
You can browse, search, and manage multiple products, customers or orders using a **familiar, single page, 
spreadsheet like** interface. 

Paid versions also allow managing product variations (for WPeC 3.8+ & WooCommerce 1.4+), inline editing for all fields and adding items. 
It also provides **Smart Batch Update** to change multiple fields on multiple items - all 
**at once with zero errors**. 
 
You will discover a new way of store administration with this add-on. 

It frees you up from mundane housekeeping tasks. So you can spend that time on anything that makes you more 
money. Use that time to leverage social media for higher sales, or develop deeper customer relationships. 
Use that time to develop a new product, or simply present it to your family.

Smart Manager works with WooCommerce latest versions and both WP e-Commerce 3.7 and 3.8.

Highlights:

* Convenient Browsing & Searching - Eliminate Extra Clicks And Long Wait Times
* Inline Product/Order/Customer Management - Get Productive Right Away
* Product Variations management in Pro version
* Quick, In-page detail view for Products/Orders/Customers
* Print multiple packing slips in one go (WPeC)
* Fast And Reliable Batch Updates With Intelligent Update Actions
* Note: Some of these features are only in Pro version

Customer Feedback:

* Just bought this Genius Add-on... It's going to save me hundreds of hours a year...
* I find it extremely useful and very well done. 
* Great looking plugin! :D I would recommend your plugin to my customers and readers.
* I just took a look at Smart Manager and it looks way awesome!

Thousands of customers are extremely happy with Smart Manager! Checkout our website for more details.


== Installation ==

1. Ensure you have latest version of [WP e-Commerce](http://www.getshopped.org) or [WooCommerce](http://www.woothemes.com/woocommerce/) plugin installed
2. Unzip and upload contents of the plugin to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Click on 'Smart Manager' option within WordPress admin sidebar menu

== Frequently Asked Questions ==

= Can I import / export data using CSV? =

You can export most important fields from Product / Customer / Order as CSV in paid versions. Smart Manager does not currently support importing data.

= Can I customize Smart Manager by adding/removing columns? =

No, Smart Manager is not customizable. You can not add/remove columns in Smart Manager

= Which browser to choose for best performance? =

Smart Manager works best in Firefox, Chrome. Currently it is not compatible with IE.

= What do I get in this free version? =

Spreadsheet like view of products, customers and orders. Search and navigation, 
limited inline editing for products, ability to delete multiple items at 
once, a quick edit link to WP e-Commerce / WooCommerce editing screen.

= What do the paid versions give me? =

Paid versions (Pro, Multisite and Developer) allow managing product variations 
(WPeC 3.8+ or WooCommerce 1.4+ required), inline editing on all fields right within the spreadsheet 
view and a powerful Batch Update screen where you can apply multiple smart update 
actions to selected / all items. For Customers, you can see total sales for each 
customer and their last order details. You can also export data into CSV.

It also gives the warm feeling of supporting further development of the plugin.


== Screenshots ==

1. Products Dashboard

2. Orders Dashboard

3. In-page Order Details

4. Column selection for display

== Changelog ==

= 3.1 =
* New: Compatibility with WooCommerce 2.0+
* New: Support for Grouped products
* New: Image Batch Update Functionality
* New: Create/post support ticket from plugin 
* Update: Minor update in auto-update process
* Update: Compatible with 'WP_DEBUG' enabled mode
* Fix: Other minor fixes (rounding of prices, order handling for different time-zones)

= 3.0 =
* New: Additional columns (discounts, taxes, coupon codes, order notes etc.) introduced in Order's dashboard (WooCommerce)
* New: Included above fields in CSV export (Woocommerce)
* Update: Storing the state of Smart Manager into the database instead of cookies - enabling same view from anywhere
* Update: Can set price and sales price to blank using batch update
* Update: Smart Manager's Plugin URI
* Update: Compatibility for PHP 5.4
* Update: Additional information about products in exported orders list (WooCommerce)
* Fix: Add attribute changing the product type (WooCommerce)
* Fix: 'Visible on the product page' and 'Used for variations' for attributes getting unchecked on save (WooCommerce)
* Fix: Duplicate action in Batch update, when increasing price and Sales price
* Fix: Product Image not getting updated when using WordPress 3.5
* Fix: TextBox appearing in batch update window while assigning the products to some categories
* Fix: Search for variation works in few cases only (WooCommerce)
* Fix: Fixes related to attribute management.

= 2.9 =
* New: Feature to set "Regular Price equal to Sale Price" & vice-versa in Batch Update
* New: Progress Bar for Batch Update
* Update: Retain values in Batch Update window after Batch Update
* Update: Stay on the same page of records list after Batch Update
* Update: Improved search functionality for Products (WooCommerce)
* Update: Removed the Description and Additional Description columns in Products
* Fix: Scrollbar not appearing from 2nd page onwards in Smart Manager editor grid
* Fix: Variations getting displayed even if the parent product got deleted
* Fix: Message not appearing in the confirm dialog box for deletion of products
* Fix: Batch Update not working for products added from Smart Manager (WooCommerce)
* Fix: Encoding of special characters while saving
* Fix: Batch Printing not working (WPeC)
* Fix: Price Updation through Inline Editing & Batch Update (WooCommerce)
* Fix: Customer List not showing all customers

= 2.8 =
* New: Feature to Duplicate Products
* Update: Additional column to display & export Phone Number from Orders dashboard
* Fix: Handling ISO 8859-1 Symbols ( for e.g. symbol for Registered & TradeMark )
* Fix: Products & Orders list empty
* Fix: Product details: Deletion of Product & Batch Update for Post Status not working (WooCommerce)
* Fix: Order details: Deletion of Order & Batch Update for Order Status not working (WooCommerce)
* Fix: Exported file for Orders doesn't contain SKU for simple products (WPeC)
* Fix: Customer details: Incorrect count for Total Orders in filtered result

= 2.7.2 =
* Fixed: "Remote Connection Error" message on admin pages of WordPress

= 2.7.1 =
* Updated: Massive speed boost for loading 1000s of customers & orders (up by more than 99%) (WPeC)
* Fixed: Price not showing in Smart Manager (WPeC)
* Fixed: Updation of Price & Sale Price (WooCommerce)
* Fixed: Auto-loading of records after Batch update (WooCommerce)
* Fixed: Small fixes like - comma between variation's term name & extra space between categories (WooCommerce)

= 2.7 =
* Updated: Massive speed boost for stores with 1000s of products
* Updated: Improved product search. Can enclose keywords in double quotes for exact match now
* Added: SKU in Orders export CSV
* Added: Portuguese Brazilian translations
* Added: 'Visibility' & 'Tax Status' fields in Products dashboard, with inline editing & Batch update (WooCommerce)
* Added: 'Total No. of Orders' & 'Total Purchased' fields in Customers dashboard
* Updated: Customers dashboard, will now display customers list based on WordPress User ID if available
* Fixed: Auto-update from Network Admin in Wordpress Multisite
* Fixed: Incorrect creation & removal of Product variation (WPeC)
* Fixed: "Batch Update not Successful" when batch updating price (WPeC)
* Fixed: Price Not Updating using inline editing (WooCommerce)
* Fixed: Batch Update->Category: 'Add To' & 'Remove From' not working (WooCommerce)

= 2.6 =
* Added: Multilingual support for Danish & French languages
* Updated: "Batch Update" of Customers & Orders for WPec - added extra field for region
* Updated: Un-wanted changes in products when updating from Smart Manager
* Updated: Automatic update of Smart Manager in WordPress Multisite
* Updated: Reduced number of request to Database
* Fixed: Smart Manager creation & removal of variation in WPeC
* Fixed: Batch Updation of Country & Region in Customer & Order dashboard
* Fixed: Publish date set to current date in WPeC
* Fixed: Export CSV showing PHP Warnings
* Fixed: Export CSV exporting thumbnail's URL instead of original image's URL

= 2.5 =
* Added: Support for "Attribute Management" for WooCommerce
* Added: Support for adding product variation for WPeC
* Added: Search using multiple keywords
* Updated: Export CSV
* Updated: Displaying "Please wait..." message when loading & unloading variation
* Updated: Showing default image thumbnail instead of actual image
* Fixed: Smart Manager shows price as zero by default
* Fixed: Publish date set to current date

= 2.4 =
* Fixed: Customers & Orders list showing only 100 records ( WooCommerce )
* Fixed: 'Export CSV' not exporting values containing comma
* Fixed: Export CSV not working in PHP versions below 5.3
* Added: Can filter products list based on attribute names for WooCommerce
* Updated: Added 'Site Title' in the name of Exported CSV file
* Fixed: State restore - Smart Manager will open the dashboard you left it on
* Fixed: Order detail permission issue in WPeC 3.8.8
* Updated: Minimize, Maximize and Close buttons will now be always visible in popup windows (Product Edit / Billing Details / Image Management)
* Updated: Enabled Inline Editing of product names for WPeC
* Updated: Product Description & Additional Description text fields now grow with content
* Updated: Added SKU field in Packing Slip for WPeC
* Updated: Disabled "Edit Link" for product variation for WPeC & WooCommerce
* Updated: Only those postmeta will be updated whose value is greater than 0
* Updated: Smart Manager License Key setting & displaying messages for WordPress Multisite

= 2.3 =
* New: "Export CSV" support for WooCommerce 1.4+ and WP e-Commerce 3.7+
* Fix: Update & Batch Update of Price & Sale Price
* Fix: Products not displaying for WP e-Commerce 3.7
* Fix: Deletion of product variation from WooCommerce
* Updated: Disabled fields not required for product variation for WooCommerce
* Updated: Hide product variation whose parent product is not a variable product
* Updated: Changed inline editable field in LITE version

= 2.2 =
* New: Product Variation support for WooCommerce 1.4+
* Fix: Minor fix for retrieving License Key for WordPress Multisite
* Fix: Hide variations in Smart Manager, if parent product is deleted
* Fix: Better security by preventing SQL injection
* Fix: Minor fix for loading products using page navigation
* Updated: "Search Text Field" trigger time changed from half second to one second
* Updated: Show product variations while searching for category

= 2.1 =
* Fixed: Asking to install WooCommerce when WooCommerce is already installed
* Updated: Smart Manager for WooCommerce 1.4.3
* New: Manage Image Thumbnail of Products & its Variations for WPeC 3.8
* New: Manage Image Thumbnail of Products for WooCommerce 1.4.3
* New: Show Product Image Thumbnail for WPeC 3.7
* Updated: Automatic upgrade for Wordpress Multisite
* Updated: License Key setting for Wordpress Multisite
* Fixed: Incomplete change in Order Status when changing it using Smart Manager in WooCommerce

= 2.0 =
* New: All important fields of 'Customers' & 'Orders' for WooCommerce are now editable inline
* New: 'Batch Update' on 'Customers' & 'Orders' for WooCommerce
* Fixed: MySQL error in case of WP e-Commerce 3.7
* New: Smart Manager Access Control for WP e-Commerce 3.7
* Updated: Any modified value will be saved before 'Batch Update' for WP e-Commerce 3.7 & 3.8 both
* Updated: Position of Notification that comes after any updation or deletion

= 1.9.1 =
* New: Access Privileges - give controlled access to non Admin users in WordPress
* Fixed: Problems accessing Settings page
* Updated: Improved auto upgrade process

= 1.9 =
* New: View customers and orders for WooCommerce from Smart Manager
* Fixed: Clicking on Settings / Register gave permission error

= 1.8 =
* New: Product batch update can now update the entire store - not just selected items
* New: (beta) Support for Woo Commerce product management
* New: Can search on product's SKU too now
* New: Added "please wait" notification for batch update
* Fixed: Minor compatibility issues with PHP 5.3
* Misc improvements and fixes 

= 1.7 =
* New: Batch Packing Slips Printing
* New: All popup windows are now collapsible and have better shadow effect ;-)
* Fixed: Grid jumped to first page on saving records
* Fixed: Product search not showing any results in certain cases

= 1.6 =
* Fixed: Customer's Dashboard not showing correct Billing States.
* Fixed: "Response is not in a proper format" error.
* Fixed: Add Product button remained enabled in Lite Version.
* Fixed: Whenever the grid page is loaded everytime store loading occurred twice.

= 1.5 =
* NEW: Performance optimization for large number of records.
* Change: State / Region from Customers / Orders can be updated only via Batch Update now.
* Fixed: Issues in updating customers and orders.
* Fixed: Product weight units did not match with WPeC product edit window.

= 1.4 =
* NEW: Search, view, modify and delete variations of Products (paid versions only, requires WPeC 3.8+).
* NEW: Batch Update on variations of Products (paid versions only, requires WPeC 3.8+).
* Fixed: Upgrade problems with previous version 

= 1.3 =
* NEW: Smart Manager now remembers changes you made to grid columns, edit product window dimensions and selected dashboard
* NEW: Batch Update on Variations price and weight for WPeC version 3.7
* Fixed: Products Dashboard empty issue

= 1.2 =
* Fixed: Batch update on "Quantity Limited" did not work
* Fixed: Publish / Unpublish not working
* Fixed: Orders / Customers Dashboard blank screen issues
* Fixed: Table Rate Pricing lost issue
* Product weights are now converted to Pounds to match WPeC standard

= 1.1 =
* Fixed: An issue with Category batch update
* Fixed: Search was not working correctly with more than 100 products
* Fixed: Compatibility issue with Internet Explorer

= 1.0 =
* NEW: Batch Update for Categories in Products now available for WPeC 3.8 also
* Fixed: Problem with loading Products

= 0.9 =
* NEW: Auto upgrade feature for Pro version added
* Fixed: products not loading in some cases
* Optimizations for large number of products
* Misc other fixes and optimizations

= 0.8 =
* NEW: WPeC 3.8 compatible release

= 0.7.4 =
* Fixed: blank grid if you had more than 100 products

= 0.7.3 =
* Products Dashboard: added Description & Additional Description fields to the grid
* Products Dashboard: added Flat rate shipping options - Local & International Shipping Fee, Height, Weight, Length and their Units
* Products Dashboard: added Disregard Shipping field to the grid
* Products Dashboard: added Local and International Shipping Fee in Batch Update
* Products Dashboard: can now search for all products with quantity limited by typing "-1" in search field
* Shifted Shipping Details from Customer Dashboard to Orders Dashboard
* NOTE: Most of these new fields are not visible in the grid by default. Click on downward arrow in a column heading -> Columns and then select columns you want to show.
* Fixed a problem that did not show Order detail popup in some installations
* Fixed an issue with moving around Edit column in the grid
* Fixed issues with Country and State comboboxes in Customers and Orders dashboards
* Added global error handler to show a description of JS errors if any - this will help debug blank page situations
* Added "Need Help?" link

= 0.7.2 =
* Fixed a bug with customers batch update

= 0.7.1 =
* Fixed a problem with order amounts being rounded unnecessarily
* Fixed: error with no products in database
* Enhanced JS and CSS loading mechanism
* Added notification messages for no customers / no orders
* Shipping phone number renamed to Phone number
* Fixed an issue with batch update along with new product additions

= 0.7.0 =
* Added Customers dashboard - view, search, (In Pro: inline editing and batch update)
* In Pro: Total Purchased and Last Order details
* If switching dashboards with pending changes, you will now be notified if you want
  to save before switching.
* Misc optimizations and usability improvements  

= 0.6.2 =
* Fixed a critical bug that prevented product list to load

= 0.6.1 =
* Product batch update broke in last version - fixed
* If system did not have any orders, Order dashboard did not work - fixed
* Made Orders grid sortable
* On first load "product list is empty" showed up - fixed
* Other minor fixes and code cleanups

= 0.6.0 =
* Order Management features added - search, navigate, inline editing
* Product dashboard now allows editing name and price in Lite version
* Can search on product names within an order / session id
* Filter orders by date range
* Batch update order status, notes and track id
* Add order notes / track id right from the grid view
* See order details without leaving the page
* Bring up product editing screen of WPeC without leaving the page
* Alert messages replaced with slide down notifications
* Improved search performance 
* Added Facebook like button. We would love to get your likes! ;-)

= 0.5.7 =
* Fixed a problem which caused blank screen on Smart Manager page
* Showing 100 items at once instead of 25 now

= 0.5.6 =
* Code reorganization
* Lite version release
* Added product logo and lite / pro indicator text in heading

= 0.5.5 =
* Special character handling added
* Category drop down now resets correctly in Batch Update
* Removed extraneous debugging code

= 0.5.4 =
* Added Product Variations Price and Weight change support
* Saving changed items on search / navigation
* Improved notification messages for better clarity    
    
= 0.5.3 =
* First public release
* Product administration features

== Upgrade Notice ==

= 3.1 =
Improved auto-update & support for grouped products. Other minor fixes. Recommended upgrade.

= 3.0 =
Many enhancements and fixes - additional fields in display, batch update and attribute management. Recommended upgrade.

= 2.9 =
New features in Batch Update, enhancements and fixes. Recommended upgrade.

= 2.8 =
New feature for Duplication of products. Performance improvements, enhancements and fixes. Recommended upgrade.

= 2.7.2 =
Must have upgrade. Fixes "Remote Connection Error" messages in WordPress admin.

= 2.7.1 =
Further performance improvements, enhancements and fixes. Recommended upgrade.

= 2.7 =
Major performance improvements, enhancements and fixes. Recommended upgrade.

= 2.6 =
Multilingual support, export CSV fixes and batch update enhancements. Recommended upgrade.

= 2.5 =
Recommended upgrade. Added support for "Adding variations", "Attribute Management" and many other small fixes and enhancements. 

= 2.4 =
Recommended upgrade. Fixed issues in CSV export and many other small fixes and enhancements. 

= 2.3 =
Recommended upgrade. Export CSV support for WooCommerce added. Fixed issue related to Sale Price for WooCommerce. Upgrade WPeC / WooCommerce before upgrading Smart Manager. 

= 2.2 =
Recommended upgrade. Variations support for WooCommerce added. Upgrade WPeC / WooCommerce before upgrading Smart Manager. 

= 2.1 =
Upgrade WPeC / WooCommerce before upgrading Smart Manager. Multi site support added. Updated to work with WooCommerce 1.4.3. Recommended upgrade.

= 2.0 = 
Important update for WooCommerce - adds customer and order management. Recommended upgrade.

= 1.9.1 =
Access control support added, improved auto upgrade. Recommended upgrade.

= 1.9 =
WooCommerce customers and orders support added.

= 1.8 =
WooCommerce support added. Batch update for entire store possible now. 

= 1.7 =
Adds Batch Packing Slips printing. Recommended upgrade.

= 1.6 =
Bug fixes. Recommended upgrade.

= 1.5 =
Performance and stability improvements. Recommended upgrade.

= 1.4 =
Adds management of product variations for paid versions, with WPeC 3.8+. IMPORTANT: Paid users: please rename plugin folder to "smart-manager-for-wp-e-commerce" before upgrading.

= 1.3 =
Feature update and bug fix release. 

= 1.2 =
Important bug fixes and overall enhancements. 

= 1.1 = 
Fixes bugs and IE compatibility issues. Recommended upgrade.

= 1.0 =
Batch update for product categories added. Milestone release 1.0!

= 0.9 =
Optimization and compatibility release. Recommended upgrade.

= 0.8 =
First WPeC 3.8 compatible release. We recommed trying this version on a test server before going live.

= 0.7.4 =
Fixed an issue that showed blank grid if you had more than 100 products.

= 0.7.3 =
Adds flat rate shipping management, and adds many minor enhancements.

= 0.7.2 =
Recommended upgrade. Fixed a bug with customers batch update.

= 0.7.1 =
Recommended upgrade. Fixes a few issues and enhances JS and CSS loading.

= 0.7.0 =
Customers dashboard added. View and search customers. Recommended upgrade.

= 0.6.0 =
Must have upgrade. Now you can manage orders too, and in a much better way than WPeC! Lite version now also supports inline editing for product name and price. Many more enhancements too.    

= 0.5.7 =
Fixes a problem that caused blank screen. 
