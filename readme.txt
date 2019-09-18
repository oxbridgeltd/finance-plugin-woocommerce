=== Plugin Name ===
Contributors:      Divido Financial Services Ltd
Plugin Name:       Finance Gateway for WooCommerce
Plugin URI:        integrations.divido.com/woocommerce
Tags:              woothemes,woocommerce,payment gateway,payment,module,ecommerce,online payments,
Author URI:        integrations.divido.com
Author:            Divido Financial Services Ltd
Requires at least: 3.0.2 
Tested up to:      5.2.1
Stable tag:        2.0.2
Version:           2.0.2

License: GPLv2 or later

== Description ==
The Finance plugin for WooCommerce allows you to accept finance payments in your WooCommerce store. To get started download the Finance plugin, configure it in settings with your api-key and you’re good to go. 

== Installation ==
<strong>Simply follow these steps to install:</strong><br>
1. Unzip the file.<br>
2. Upload the "woocommerce-finance-gateway" folder to your WordPress Plugins directory.<br>
3. Login to your WordPress Admin, then go to Plugins and activate the "Finance Gateway for WooCommerce" plugin<br>
4. Within the WordPress Admin, go to WooCommerce >> Settings, then click on the Payment Gateways tab, then click on the Finance link.<br>
5. Enter the proper information and you are ready to start using the plugin<br>

== Features ==

Shared Secret: Allows you to verify webhooks calls.
Checkout Title: Displays the name of the payment option in the checkout.
Checkout Description: Description of the payment option in the checkout. 
Display Plans: Allows you to display all plans or only selected plans.
Plans: Allows you to select the plans that you want to display.
Cart Threshold: Minimum amount that needs to be reached for Finance to be available in the checkout. 
Product Selection: Allows you to pick the products on which finance will be available. There are 3 possible options: All Products, Selected Products or Products above Defined Price
Show Product Widget: Allows you to turn on/off the small widget which appears underneath the price on product pages.
Show Calculator Widget: Allows you to turn on/off the Calculator widget which appears at the bottom of product pages.
Widget Threshold: Allows you to set the minimum amount for the "Product Widget" to show.
Widget Prefix: Allows you to add a prefix to the "Product Widget".
Widget Suffix: Allows you to add a suffix to the "Product Widget".
Enable/Disable Automatic Fulfillment: Allows you to select if an "Activation" call should be made automatically to the lender once the order goes to "Completed" 
Enable/Disable Automatic Refunds: Allows you to select if a "Refund" call should be made automatically to the lender once the order goes to "Refunded" 
Enable/Disable Automatic Cancellation: Allows you to select if an "Cancellation" call should be made automatically to the lender once the order goes to "Cancelled" 

 == Changelog ==
Version 2.0.2
Added Environment Testing
Bugfix for Settings link
Add ES as accepted country

Version 2.0.1
Bugfix for Deposit Percentage

Version 2.0.0
Updated Calculator Widget
Replaces prepend and append strings with button text and footnote controls
Updated shortcode helper

Version 1.0.2 
Fix to Shared Secret Functionality
Minor Formatting
Added a shortcode helper finance_code - to allow users to place the calculator on non-product pages
Added a settings link to plugins page - quick jump to finance settings.
Added version from @ to comments

Version 1.0.1 
Added the ability to Cancel and Refund applications through WooCommerce 
Added Transient (caching values) on WooCommerce to prevent unnecessary API calls

Version 1.0.0 Initial Release





