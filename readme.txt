=== Plugin Name ===
Contributors:      Divido Financial Services Ltd
Plugin Name:       Divido Gateway for WooCommerce
Plugin URI:        http://www.divido.com
Tags:              divido,woothemes,woocommerce,payment gateway,payment,module,ecommerce,online payments,
Author URI:        http://www.divido.com
Author:            Divido Financial Services Ltd
Requires at least: 3.0.2 
Tested up to:      4.9.8
Stable tag:        2.1.0
Version:           2.1.0

License: GPLv2 or later

== Description ==
The Divido plugin for WooCommerce allows you to accept finance payments in your WooCommerce store. To get started download the Divido plugin, configure it in settings with your api-key (provided by Divido) and you’re good to go. If you’re not already a customer of Divido then visit www.divido.com to quickly get started.

<strong>Why Merchants use Divido</strong>

<b>Attract New Customers</b>
You are successful at selling to your existing customers; offering finance options will expand your potential target market and attract the Millennials.

<b>Effective Sales & Marketing Tool</b>
Offering 0% interest finance will increase customer interest, and generate visits and enquiries. It’s also an effective way to close a sale quicker as it lowers the barrier to buy today.

<b>Increase Basket Sizes</b>
In addition to financing the active basket, we can tell your customers how much more we can finance, prompting additional sales for you.

<strong>What you can offer</strong>

<b>Deferred Payment</b>
Your customer pays nothing until at the end of the period.

<b>0% Finance</b>
This is the most popular offer as it drives the most new business.

<b>Low Cost Credit</b>
Offer a more competitive APR compared to traditional sources of finance.

== Installation ==
<strong>Simply follow these steps to install:</strong><br>
1. Unzip the file.<br>
2. Upload the "woocommerce-divido-gateway" folder to your WordPress Plugins directory.<br>
3. Login to your WordPress Admin, then go to Plugins and activate the "Divido Gateway for WooCommerce" plugin<br>
4. Within the WordPress Admin, go to WooCommerce >> Settings, then click on the Payment Gateways tab, then click on the Divido link.<br>
5. Enter the proper information...<br>

== Changelog ==

 2018.10.24
 • Add ability to display widget based on a value
 • Bug fix - only trigger automatic activation request for divido orders 
 • Added JS folder with widget_price_update js file which updates the widget price on variable products. 
 • Updated Divido Js to only be called on product or checkout pages
 
 2018.08.21
 • Version 2.0 - This version has some significant changes from previous versions.
 • Rename gateway-divido to class-wc-gateway-divido
 • New Price Widget! - Updated design, improved functionality
 • New Calculator Widget! 
 • New Checkout Widget! 
 • Change widget from filter to action to counter double display bug.
 • Remove get_price_html function as now unused.
 • Improve escaping.
 • Add nonce to form.
 • Remove unused functiion get_user_ip.
 • Reformatted code.
 • Update code case - to be compliant with woo
 • B2B support!
 • Changed calculator label from Blue Theme to Default Theme

 2018.08.13
 • Bug Fix - Issue with fees

 2018.04.30
 • Bug Fix - Logging on versions greater and 2.7
 • Bug Fix - Change status from completed to processing on signed,set order as paymenr completed on ready
 • Feature - Add Auto Fulfillment feature

 2018.04.05
 • Bug Fix - Variable product bug displaying finace on checkout
 • Bug Fix - Add a default value of 250 to cart Threshold
 • Bug Fix - Do not display on products page if plugin disabled
 • Bug Fix - Fix prepend price issue
 • Feature - Add Append Price

 2018.03.22
 • Bug Fix - Issue with woocommerce 3.3.0 and small widget not displaying

 2018.02.28
 • Bug Fix - Issue with woocommerce 3.3 and small widget not displaying
 • Bug Fix - PHP Notice on undefined item

 2018.02.15
 • Bug Fix - Fix issue with calculator not displaying correct value
 
 2018.02.02
 • Bug Fix - Add Backwards compatibility for order->id

 2018.01.16
 • Bug Fix

 2018.01.05
 • Multiple Bug Fixes
 • Add composer files upgraded divido library
 • Use HTTPS for image urls
 • Added secret field
 • Add logging
 • Add logging for callback status
 • Updated customer object
 • Suppress deprecated warnings
 • Calculate item price and shipping
 • Check Values match
 • Get real price and send fees and taxes 

 2017.04.25
 • Added support for cart limit
 
 2016.11.04
 • Changes for Wordpress 4.6.1
 
  2016.09.26
 • Bug fixes
 
 2016.07.21
 • Bug fixes

 2016.06.18
 • Bug fixes

 2016.03.25
 • Bug fixes
 
 2016.03.23
 • Price widget
 
2016.01.18
 • Bug fixes

2015.12.11 - version 1.1
 * New Calculator
 * Bug fixes

2015.12.04 - version 1.0.1
 * Bug fixes

2015.06.25 - version 1.0
 * First Release
