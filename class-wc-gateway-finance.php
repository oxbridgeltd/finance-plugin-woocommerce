<?php
/**
 * TODO: Change Name for to a specific lender
 *
 *  Finance
 *
 * @package   WordPress
 * @author    "Enter Author Here"
 * @copyright "Enter copyright info here"
 * @license   MIT
 *
 * Plugin Name: Finance Gateway for WooCommerce
 * Plugin URI: "Enter URI Here"
 * Description: The Finance Payment Gateway plugin for WooCommerce.
 * Version: 1.0.0
 * Author: "Enter Name here"
 * Author URI: "Enter Author URI here"
 * WC tested up to: 3.5.0
 */

/**
 * Load the woocommerce plugin.
 */
add_action( 'plugins_loaded', 'woocommerce_finance_init', 0 );
/**
 * Inititalize script for finance plugin.
 *
 * @return void
 */
function woocommerce_finance_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}
	include_once WP_PLUGIN_DIR . '/' . plugin_basename( dirname( __FILE__ ) ) . '/vendor/autoload.php';
	/**
	 * Finance Payment Gateway class
	 **/
	class WC_Gateway_Finance extends WC_Payment_Gateway {
		/**
		 * Available countries
		 *
		 * @var array  $avaiable_countries A hardcoded array of countries.
		 */
		public $avaiable_countries = array( 'GB', 'SE', 'NO', 'DK' );
		/**
		 * Api Key
		 *
		 * @var string $api_key The Finance Api Key.
		 */
		public $api_key;
		/**
		 * Plugin Class Constructor
		 *
		 * Initialise the finance plugin.
		 *
		 * @return void
		 */
		function __construct() {
			$this->id           = 'finance';
			$this->method_title = __( 'Finance', 'woothemes' );
			$this->icon         = WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/images/finance-small.png';
			$this->has_fields   = true;

			// Load the settings.
			$this->init_settings();
			// Get setting values.
			$this->title            = ( ! empty( $this->settings['title'] ) ) ? $this->settings['title'] : 'Pay in instalments ';
			$this->calculator_theme = ( ! empty( $this->settings['calculatorTheme'] ) ) ? $this->settings['calculatorTheme'] : 'enabled';
			$this->show_widget      = ( ! empty( $this->settings['showWidget'] ) ) ? $this->settings['showWidget'] : true;
			$this->description      = ( ! empty( $this->settings['description'] ) ) ? $this->settings['description'] : '';
			$this->enabled          = ( ! empty( $this->settings['enabled'] ) ) ? $this->settings['enabled'] : false;
			$this->api_key          = ( ! empty( $this->settings['apiKey'] ) ) ? $this->settings['apiKey'] : '';
			$this->prepend_price    = ( ! empty( $this->settings['prependPrice'] ) ) ? $this->settings['prependPrice'] : ' ';
			$this->append_price     = ( ! empty( $this->settings['appendPrice'] ) ) ? $this->settings['appendPrice'] : ' ';
			$this->cart_threshold   = ( ! empty( $this->settings['cartThreshold'] ) ) ? $this->settings['cartThreshold'] : 250;
			$this->auto_fulfillment = ( ! empty( $this->settings['autoFulfillment'] ) ) ? $this->settings['autoFulfillment'] : false;
			$this->widget_threshold = ( ! empty( $this->settings['widgetThreshold'] ) ) ? $this->settings['widgetThreshold'] : 250;
			$this->secret           = ( ! empty( $this->settings['secret'] ) ) ? $this->settings['secret'] : '';
			$this->product_select   = ( ! empty( $this->settings['productSelect'] ) ) ? $this->settings['productSelect'] : '';

				// Load logger.
			if ( version_compare( WC_VERSION, '2.7', '<' ) ) {
					$this->logger = new WC_Logger();
			} else {
					$this->logger = wc_get_logger();
			}

			if ( is_admin() ) {
				// Load the form fields.
				$this->init_form_fields();
			}
			$this->woo_version = $this->get_woo_version();

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) ); // Version 2.0 Hook.
			// product settings.
			add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'product_write_panel_tab' ) );
			if ( version_compare( WC_VERSION, '2.7', '<' ) ) {
				add_action( 'woocommerce_product_write_panels', array( $this, 'product_write_panel' ) );
			} else {
				add_action( 'woocommerce_product_data_panels', array( $this, 'product_write_panel' ) );
			}
			add_action( 'woocommerce_process_product_meta', array( $this, 'product_save_data' ), 10, 2 );
			// product page.
			if ( 'disabled' !== $this->calculator_theme ) {
				add_action( 'woocommerce_after_single_product_summary', array( $this, 'product_calculator' ) );
			}
			if ( 'disabled' !== $this->show_widget ) {
				add_action( 'woocommerce_single_product_summary', array( $this, 'product_widget' ), 15 );
			}
			// order admin page (making sure it only adds once).
			global $finances_set_admin_order_display;
			if ( ! isset( $finances_set_admin_order_display ) ) {
				add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_order_data_in_admin' ) );
				$finances_set_admin_order_display = true;
			}
			// checkout.
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_method' ) );
			// ajax callback.
			add_action( 'wp_ajax_nopriv_woocommerce_finance_callback', array( $this, 'callback' ) );
			add_action( 'wp_ajax_woocommerce_finance_callback', array( $this, 'callback' ) );
			add_action( 'wp_head', array( $this, 'add_api_to_head' ) );
			add_action( 'woocommerce_order_status_completed', array( $this, 'send_finance_fulfillment_request' ), 10, 1 );
			// scripts.
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'wpdocs_enqueue_custom_admin_style' ) );
		}
		/**
		 * Get  Finances Wrapper
		 *
		 * Calls Finance endpoint to return all finances for merchant
		 *
		 * @param  [string] $api_key The Finance Api Key.
		 * @param  boolean  $reload  An optional parameter to say if the finances endpoint should be called again.
		 * @return array
		 */
		function get_all_finances( $api_key, $reload = true ) {
			$env            = $this->environments( $api_key );
			$sdk            = new \Divido\MerchantSDK\Client( $api_key, $env );
			$finances       = false;
			$transient_name = 'finances';
			if ( ! $reload ) {
				$finances = get_transient( $transient_name );
			}
			if ( false === $finances ) {
				$request_options = ( new \Divido\MerchantSDK\Handlers\ApiRequestOptions() );
				// Retrieve all finance plans for the merchant.
				try {
					$plans = $sdk->getAllPlans( $request_options );
					$plans = $plans->getResources();
					set_transient( $transient_name, $plans, 1 * HOUR_IN_SECONDS );
					return $plans;
				} catch ( Exception $e ) {
					return [];
				}
			}
		}
		/**
		 * Enque Add Finance styles and scripts
		 *
		 * @return void
		 */
		function enqueue() {
			if ( $this->api_key && is_product() || is_checkout() ) {
				$key      = preg_split( '/\./', $this->api_key );
				$protocol = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http'; // Input var okay.
				// TODO: Change the endpoint for the calculator once ready.
				wp_register_script( 'woocommerce-finance-gateway-calculator', $protocol . '://cdn.divido.com/calculator/v2.1/production/js/template.divido.js', false, 1.0, true );
				wp_register_script( 'woocoomerce-finance-gateway-calculator_price_update', plugins_url( '', __FILE__ ) . '/js/widget_price_update.js', false, 1.0, true );
				wp_register_style( 'woocommerce-finance-gateway-style', plugins_url( '', __FILE__ ) . '/css/style.css', false, 1.0 );
				wp_enqueue_style( 'woocommerce-finance-gateway-style' );
				wp_enqueue_script( 'woocommerce-finance-gateway-calculator' );
				wp_enqueue_script( 'woocoomerce-finance-gateway-calculator_price_update' );
			}
		}
		/**
		 * Add Finance Javascript
		 *
		 * We need to add some specific js to the head of the page to ensure the element reloads
		 *
		 * @return void
		 *
		 * TODO: change the name of dividoKey
		 */
		function add_api_to_head() {
			if ( $this->api_key ) {
				$key = preg_split( '/\./', $this->api_key );
				?>
			<script type='text/javascript'> var dividoKey = '<?php echo esc_attr( strtolower( $key[0] ) ); ?>' </script>
			<script>// <![CDATA[
					function waitForElementToDisplay(selector, time) {
				if(document.querySelector(selector)!== null) {
					TemplateCalculator.reload();
					return;
				}
				else {
					setTimeout(function() {
						waitForElementToDisplay(selector, time);
					}, time);
				}
				}
			
				jQuery( document ).ready(function() {
					waitForElementToDisplay('#financeWidget', 1000); 
				});
				
					// ]]>
			</script>

				<?php
			}
		}
		/**
		 * Helper function to display save data in Admin
		 *
		 * Display the extra data in the order admin panel.
		 *
		 * @param  [object] $order The Order view.
		 * @return void
		 */
		function display_order_data_in_admin( $order ) {
			$ref_and_finance = $this->get_ref_finance( $order );
			if ( $ref_and_finance['ref'] ) {
				echo '<p class="form-field form-field-wide"><strong>' . esc_attr( __( 'Finance Reference' ) ) . ':</strong><br />' . esc_html( $ref_and_finance['ref'] ) . '</p>';
			}
			if ( $ref_and_finance['finance'] ) {
				echo '<p class="form-field form-field-wide"><strong>' . esc_attr( __( 'Finance Finance' ) ) . ':</strong><br />' . esc_html( $ref_and_finance['finance'] ) . '</p>';
			}
		}
		/**
		 * Callback The callback function listens to calls from Finance
		 *
		 * @return void
		 */
		function callback() {
			if ( isset( $_SERVER['HTTP_RAW_POST_DATA'] ) && wp_unslash( $_SERVER['HTTP_RAW_POST_DATA'] ) ) { // Input var okay.
				$data = file_get_contents( wp_unslash( $_SERVER['HTTP_RAW_POST_DATA'] ) ); // Input var okay.
			} else {
				$data = file_get_contents( 'php://input' );
			}
			// If secret is set, check against http header.
			// TODO: Change from DIVIDO_HMAC to FINANCE_HMAC.
			if ( '' !== $this->secret ) {
				$callback_sign = isset( $_SERVER['HTTP_X_DIVIDO_HMAC_SHA256'] ) ?  $_SERVER['HTTP_X_DIVIDO_HMAC_SHA256']  : ''; // Input var okay.
				$sign          = $this->create_signature( $data, $this->secret );
				if ( $callback_sign !== $sign ) {
					$this->logger->debug( 'FINANCE', 'ERROR: Hash error' );
					$this->send_json( 'error', 'Hash error.' );
					return;
				}
			}
			// Use $data as JSON object.
			$data_json = json_decode( $data );
			if ( is_object( $data_json ) ) {
				if ( $data_json->metadata->order_number ) {
					$finance_reference = get_post_meta( $data_json->metadata->order_number, '_finance_reference' );
					if ( isset( $finance_reference[0] ) && $finance_reference[0] === $data_json->proposal ) {
						$order          = new WC_Order( $data_json->metadata->order_number );
						$finance_amount = get_post_meta( $data_json->metadata->order_number, '_finance_amount' );
						// Check if the requested amount matched order amount.
						if ( $finance_amount[0] !== $order->get_total() ) {
							// Amount mismatch, hold.
							$order->update_status( 'on-hold' );
							$order->add_order_note( 'Finance error: The requested credit of £' . $finance_amount[0] . ' did not match order sum, putting order on hold.' );
							$this->logger->debug( 'Finance', 'ERROR: The requested credit of £' . $finance_amount[0] . ' did not match order sum, putting order on hold. Status: ' . $data_json->status . ' Order: ' . $data_json->metadata->order_number . ' Finance Reference: ' . $finance_reference[0] );
							$this->send_json();
						} else {
							// Amount matches, update status.
							// TODO: Review Logging error.
							if ( 'DECLINED' === $data_json->status ) {
									$order->update_status( 'failed' );
									$this->send_json();
							} elseif ( 'SIGNED' === $data_json->status ) {
									$this->logger->error( 'Finance', 'processing' );
									$order->update_status( 'processing', $data_json->application );
									$this->send_json();
							} elseif ( 'READY' === $data_json->status ) {
									$order->add_order_note( 'Finance status: ' . $data_json->status );
									$order->payment_complete();
									$this->send_json();
							}
						}
						// Log status to order.
						$order->add_order_note( 'Finance status: ' . $data_json->status );
						$this->logger->debug( 'Finance', 'STATUS UPDATE: ' . $data_json->status . ' Order: ' . $data_json->metadata->order_number . ' Finance Reference: ' . $finance_reference[0] );
					}
				}
			}
		}
		/**
		 * Add Financec payment methods using filter woocommerce_payment_gateways
		 *
		 * @param  array $methods Array of payment methods.
		 * @return array
		 */
		public function add_method( $methods ) {
			if ( is_admin() ) {
				$methods[] = 'WC_Gateway_Finance';
			} else {
				$is_available = $this->is_available();
				if ( $is_available ) {
					$methods[] = 'WC_Gateway_Finance';
				}
			}
			return $methods;
		}
		/**
		 * Provides a way to support both 2.6 and 2.7 since get_price_including_tax
		 * gets deprecated in 2.7, and wc_get_price_including_tax gets introduced in
		 * 2.7.
		 *
		 * @since  1.2
		 * @param  WC_Product $product Product instance.
		 * @param  array      $args    Args array.
		 * @return float
		 */
		private function get_price_including_tax( $product, $args ) {
			if ( version_compare( WC_VERSION, '2.7', '<' ) ) {
				$args = wp_parse_args(
					$args,
					array(
						'qty'   => '1',
						'price' => '',
					)
				);
				return $product->get_price_including_tax( $args['qty'], $args['price'] );
			} else {
				return wc_get_price_including_tax( $product, $args );
			}
		}
		/**
		 * Check if this gateway is enabled and available in the user's country.
		 *
		 * @param  boolean $product Product Instace.
		 * @return float
		 */
		public function is_available( $product = false ) {
			if ( 'yes' !== $this->enabled || '' === $this->api_key ) {
				return false;
			}
			if ( is_object( $product ) ) {
				if ( version_compare( $this->woo_version, '3.0.0' ) >= 0 ) {
					$data = maybe_unserialize( get_post_meta( $product->get_id(), 'woo_finance_product_tab', true ) );
				} else {
					$data = maybe_unserialize( get_post_meta( $product->id, 'woo_finance_product_tab', true ) );
				}
				if ( isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['active'] ) && 'selected' === $data[0]['active'] ) {
					if ( is_array( $data[0]['finances'] ) && count( $data[0]['finances'] ) > 0 ) {
						return true;
					} else {
						return false;
					}
				} elseif ( 'price' === $this->settings['productSelect'] ) {
					$limit = $this->settings['priceSelection'];
					if ( $this->get_price_including_tax( $product, '' ) > 0 && $this->get_price_including_tax( $product, '' ) >= $limit ) {
						return true;
					} else {
						return false;
					}
				} elseif ( 'selection' === $this->settings['productSelect'] ) {
					return false;
				} elseif ( 'all' === $this->settings['productSelect'] ) {
					return true;
				}
				return false;
			}
			// In Cart.
			global $woocommerce;
			$settings  = $this->settings;
			$threshold = $this->cart_threshold;
			$cart      = $woocommerce->cart;
			if ( empty( $cart ) ) {
				return false;
			}
			if ( $threshold > $cart->subtotal ) {
				return false;
			}
			if ( 'all' === $settings['productSelect'] ) {
				return true;
			}
			if ( 'price' === $settings['productSelect'] ) {
				if ( $cart->subtotal < $settings['priceSelection'] ) {
					return false;
				}
			}
			$checkout_finance_options = $this->get_checkout_plans();
			if ( ! $checkout_finance_options ) {
				return false;
			}
			return true;
		}
		/**
		 * Get any finance options set for the checkout
		 *
		 * @return array
		 */
		public function getCheckoutFinanceOptions() {
			global $woocommerce;
			if ( 'yes' !== $this->enabled ) {
				return false;
			}
			$finance_options = array();
			foreach ( $woocommerce->cart->get_cart() as $item ) {
				$product  = $item['data'];
				$finances = $this->getProductFinanceOptions( $product );
				if ( ! $finances && ! is_array( $finances ) ) {
					return false;
				}
				foreach ( $finances as $finance ) {
					$finance_options[ $finance ] = $finance;
				}
			}
			return ( count( $finance_options ) > 0 ) ? $finance_options : array();
		}
		/**
		 * Get Product specific finance optitons.
		 *
		 * @param  object $product Product Instance.
		 * @return array|false
		 */
		public function getProductFinanceOptions( $product ) {
			if ( 'yes' !== $this->enabled ) {
				return false;
			}
			$data;
			if ( version_compare( $this->woo_version, '3.0.0' ) >= 0 ) {
				if ( $product->get_type() === 'variation' ) {
					$data = maybe_unserialize( get_post_meta( $product->get_parent_id(), 'woo_finance_product_tab', true ) );
				} else {
					$data = maybe_unserialize( get_post_meta( $product->get_id(), 'woo_finance_product_tab', true ) );
				}
			} else {
				$data = maybe_unserialize( get_post_meta( $product->id, 'woo_finance_product_tab', true ) );
			}
			if ( isset( $data[0] ) && is_array( $data[0] ) && isset( $data[0]['active'] ) && 'selected' === $data[0]['active'] ) {
				$finances = array();
				return ( is_array( $data[0]['finances'] ) && count( $data[0]['finances'] ) > 0 ) ? $data[0]['finances'] : array();
			} elseif ( 'selection' === $this->settings['showFinanceOptions'] ) {
				return $this->settings['showFinanceOptionSelection'];
			} elseif ( 'all' === $this->settings['showFinanceOptions'] ) {
				return false;
			}
			return false;
		}
		/**
		 * Get Checkout specific finance plans.
		 *
		 * @return string|false
		 */
		public function get_checkout_plans() {
			$finances = $this->get_finances( $this->getCheckoutFinanceOptions() );
			if ( is_array( $finances ) ) {
				$plans = array_keys( $finances );
			}
			return ( is_array( $plans ) ) ? implode( ',', $plans ) : false;
		}
		/**
		 * Get specific product plans.
		 *
		 * @param  object $product WC product instance.
		 * @return string|false
		 */
		public function get_product_plans( $product ) {
			$finances = $this->getProductFinanceOptions( $product );
			return ( is_array( $finances ) ) ? implode( ',', $finances ) : false;
		}
		/**
		 * Product calculator helper.
		 *
		 * @param  object $product The current product.
		 * @return void
		 */
		public function product_calculator( $product ) {
			global $product;
			if ( $this->is_available( $product ) ) {
				$plans = $this->get_product_plans( $product );
				$price = $this->get_price_including_tax( $product, '' );
				include_once WP_PLUGIN_DIR . '/' . plugin_basename( dirname( __FILE__ ) ) . '/includes/calculator.php';
			}
		}
		/**
		 * Product widget helper.
		 *
		 * @param  object $product The current product.
		 * @return void
		 */
		public function product_widget( $product ) {
			global $product;
			$price = $this->get_price_including_tax( $product, '' );
			$plans = $this->get_product_plans( $product );
			if ( $this->is_available( $product ) && $price > ( $this->widget_threshold ) ) {
				$append_price = '';
				if ( ! empty( $this->append_price ) ) {
					$append_price = 'data-divido-suffix="' . $this->append_price . '" ';
				}
				$prepend_price = '';
				if ( ! empty( $this->prepend_price ) ) {
					$prepend_price = ' data-divido-prefix="' . $this->prepend_price . '" ';
				}
				$plans = $this->get_product_plans( $product );
				include_once WP_PLUGIN_DIR . '/' . plugin_basename( dirname( __FILE__ ) ) . '/includes/widget.php';
			}
		}
		/**
		 * Function to add finance product into admin view this add tabs
		 *
		 * @return false
		 */
		public function product_write_panel_tab() {
			if ( 'yes' !== $this->enabled ) {
				return false;
			}
			$tab_icon = WP_PLUGIN_URL . '/' . plugin_basename( dirname( __FILE__ ) ) . '/images/finance-icon.png';
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0' ) >= 0 ) {
				$style        = 'content:"";padding:5px 5px 5px 22px; background-image:url(' . $tab_icon . '); background-repeat:no-repeat;background-size: 15px 15px;background-position:8px 8px;';
				$active_style = '';
			} else {
				$style        = 'content:"";padding:5px 5px 5px 22px; line-height:16px; border-bottom:1px solid #d5d5d5; text-shadow:0 1px 1px #fff; color:#555555;background-size: 15px 15px; background-image:url(' . $tab_icon . '); background-repeat:no-repeat; background-position:8px 8px;';
				$active_style = '#woocommerce-product-data ul.product_data_tabs li.my_plugin_tab.active a { border-bottom: 1px solid #F8F8F8; }';
			}
			?>
			<style type="text/css">
			#woocommerce-product-data ul.product_data_tabs li.finance_tab a { <?php echo esc_attr( $style ); ?> }
			#woocommerce-product-data ul.product_data_tabs li.finance_tab a:before { content:''!important; }
			<?php echo esc_attr( $active_style ); ?>
			</style>
			<?php
			// TODO: Change href name to finance_tab.
			echo '<li class="finance_tab"><a href="#finance_tab"><span>' . esc_attr( __( 'Finance', 'wc_finance_product_tab' ) ) . '</span></a></li>';
		}
		/**
		 * Function to add the product panel
		 *
		 * @return false
		 */
		public function product_write_panel() {
			if ( 'yes' !== $this->enabled ) {		
				return false;
			}
			global $post;
			// Pull the video tab data out of the database.
			$tab_data = maybe_unserialize( get_post_meta( $post->ID, 'woo_finance_product_tab', true ) );
			if ( empty( $tab_data ) ) {
				$tab_data   = array();
				$tab_data[] = array(
					'active'   => 'default',
					'finances' => array(),
				);
			}
			if ( empty( $tab_data[0]['finances'] ) ) {
				$tab_data[0]['finances'] = array();
			}
			$finances = $this->get_finances();

			?>
			<div id="finance_tab" class="panel woocommerce_options_panel">
				<p class="form-field _hide_title_field ">
					<label for="_available"><?php esc_html_e( 'Available on finance', 'woothemes' ); ?></label>

					<input type="radio" class="checkbox" name="_tab_finance_active" id="finance_active_default" value="default" <?php print ( 'default' === $tab_data[0]['active'] ) ? 'checked' : ''; ?> > <?php esc_html_e( 'Default settings', 'woothemes' ); ?><br style="clear:both;" />
					<input type="radio" class="checkbox" name="_tab_finance_active" id="finance_active_selected" value="selected" <?php print ( 'selected' === $tab_data[0]['active'] ) ? 'checked' : ''; ?> > <?php esc_html_e( 'Selected plans', 'woothemes' ); ?><br  style="clear:both;" />
					</p>
				<p class="form-field _hide_title_field" id="selectedFinance" style="display:none;">
					<label for="_hide_title"><?php esc_html_e( 'Selected plans', 'woothemes' ); ?></label>

			<?php
	
			foreach ( $finances as $finance => $value ) {

				?>
					<input type="checkbox" class="checkbox" name="_tab_finances[]" id="finances_<?php print esc_attr( $finance ); ?>" value="<?php print esc_attr( $finance ); ?>" <?php print ( in_array( $finance, $tab_data[0]['finances'], true ) ) ? 'checked' : ''; ?>> &nbsp;<?php print esc_attr( $value['description'] ); ?><br  style="clear:both;" />
			<?php } ?>
				</p>
			</div>
			<script type="text/javascript">
				function checkActive()
				{
					jQuery("#selectedFinance").hide();
					if(jQuery("input[name=_tab_finance_active]:checked").val() === 'selected') {
						jQuery("#selectedFinance").show();
					}
				}
				jQuery(document).ready(function() {
					checkActive();
				});
				jQuery("input[name=_tab_finance_active]").change(function() {
					checkActive();
				});
			</script>
			<?php
		}
		/**
		 * A function to save metadata per product
		 *
		 * @param  [type] $post_id The product Post Id.
		 * @param  [type] $post    The Post.
		 * @return void
		 */
		public function product_save_data( $post_id, $post ) {
			$active   = isset( $_POST['_tab_finance_active'] ) ? sanitize_text_field( wp_unslash( $_POST['_tab_finance_active'] ) ) : ''; // Input var okay.
			$finances = isset( $_POST['_tab_finances'] ) ? wp_unslash( $_POST['_tab_finances'] ) : ''; // Input var okay.
			if ( ( empty( $active ) || 'default' === $active ) && get_post_meta( $post_id, 'woo_finance_product_tab', true ) ) {
				delete_post_meta( $post_id, 'woo_finance_product_tab' );
			} else {
				$tab_data  = array();
				$tab_title = isset( $tab_title ) ? $tab_title : '';
				$tab_id    = '';
				// convert the tab title into an id string.
				$tab_id = strtolower( $tab_title );
				$tab_id = preg_replace( '/[^\w\s]/', '', $tab_id ); // remove non-alphas, numbers, underscores or whitespace.
				$tab_id = preg_replace( '/_+/', ' ', $tab_id ); // replace all underscores with single spaces.
				$tab_id = preg_replace( '/\s+/', '-', $tab_id ); // replace all multiple spaces with single dashes.
				$tab_id = 'tab-' . $tab_id; // prepend with 'tab-' string.
				// save the data to the database.
				$tab_data[] = array(
					'active'   => $active,
					'finances' => $finances,
					'id'       => $tab_id,
				);
				update_post_meta( $post_id, 'woo_finance_product_tab', $tab_data );
			}
		}
		/**
		 * Initialize Gateway Settings Form Fields.
		 */
		function init_form_fields() {
			$this->init_settings();
			$this->form_fields = array(
				'apiKey' => array(
					'title'       => __( 'API Key', 'woothemes' ),
					'type'        => 'text',
					'description' => __( 'Provided by Finance provider.', 'woothemes' ),
					'default'     => '',
				),
			);
			if ( isset( $this->api_key ) && $this->api_key ) {
				$response = $this->get_all_finances( $this->api_key );
				$finance  = [];
				foreach ( $response as $finances ) {
					$finance[ $finances->id ] = $finances->description;
				}
				$options = array();
				// TODO: Condition to return true.
				try {
					foreach ( $finance as $key => $descriptions ) {
						$options[ $key ] = $descriptions;
					}
					$this->form_fields                       = array_merge(
						$this->form_fields,
						array(
							'secret'           => array(
								'title'       => __( 'Shared Secret', 'woothemes' ),
								'type'        => 'text',
								'description' => __( 'Optional key - may be used to verify webhooks.', 'woothemes' ),
								'default'     => '',
							),
							'enabled'          => array(
								'title'       => __( 'Activated', 'woothemes' ),
								'label'       => __( 'Enable Finance', 'woothemes' ),
								'type'        => 'checkbox',
								'description' => '',
								'default'     => 'no',
							),
							'title'            => array(
								'title'       => __( 'Checkout Title', 'woothemes' ),
								'type'        => 'text',
								'description' => __( 'The name of the payment option during checkout.', 'woothemes' ),
								'default'     => __( 'Finance', 'woothemes' ),
							),
							'description'      => array(
								'title'       => __( 'Checkout Description', 'woothemes' ),
								'type'        => 'text',
								'description' => __( 'The description of the payment option during checkout.', 'woothemes' ),
								'default'     => 'Pay in instalments',
							),
							'General Settings' => array(
								'title' => __( 'Finance/Product Settings', 'woothemes' ),
								'type'  => 'title',
								'class' => 'border',
							),
						)
					);
					$this->form_fields['showFinanceOptions'] = array(
						'title'       => __( 'Display Plans', 'woothemes' ),
						'type'        => 'select',
						'description' => __( 'You can always override this setting from the product card', 'woothemes' ),
						'default'     => 'all',
						'options'     => array(
							'all' => __( 'Display all plans', 'woothemes' ),
						),
					);
					$this->form_fields['showFinanceOptions']['options']['selection'] = __( 'Display selected plans', 'woothemes' );
					$this->form_fields['showFinanceOptionSelection']                 = array(
						'title'       => __( 'Plans', 'woothemes' ),
						'type'        => 'multiselect',
						'options'     => $options,
						'description' => __( 'Shift-click or Control-click to select multiple items in the list', 'woothemes' ),
						'default'     => 'all',
						'class'       => 'border_height',
					);
					$this->form_fields = array_merge(
						$this->form_fields,
						array(
							'cartThreshold'   => array(
								'title'       => __( 'Cart Threshold', 'woothemes' ),
								'type'        => 'text',
								'description' => __( 'Under this amount, Finance is not available as a payment option.', 'woothemes' ),
								'default'     => '250',
							),
							'productSelect'   => array(
								'title'   => __( 'Product Selection', 'woothemes' ),
								'type'    => 'select',
								'default' => 'All',
								'options' => array(
									'all'      => __( 'All products', 'woothemes' ),
									'selected' => __( 'Selected products', 'woothemes' ),
									'price'    => __( 'All products above a defined price.', 'woothemes' ),
								),
							),
							'priceSelection'  => array(
								'title'       => __( 'Price', 'woothemes' ),
								'type'        => 'text',
								'description' => __( 'Finance payment method will be available on all products above this price.', 'woothemes' ),
								'default'     => '350',
							),
							'Widget Settings' => array(
								'title' => __( 'Widget Settings', 'woothemes' ),
								'type'  => 'title',
								'class' => 'border',
							),
							'showWidget'      => array(
								'title'   => __( 'Show Product Widget', 'woothemes' ),
								'type'    => 'select',
								'default' => 'show',
								'options' => array(
									'show'     => __( 'Yes', 'woothemes' ),
									'disabled' => __( 'No', 'woothemes' ),
								),
							),
							'calculatorTheme' => array(
								'title'   => __( 'Show Calculator Widget', 'woothemes' ),
								'type'    => 'select',
								'default' => 'enabled',
								'options' => array(
									'enabled'  => __( 'Yes', 'woothemes' ),
									'disabled' => __( 'No', 'woothemes' ),
								),
							),
							'widgetThreshold' => array(
								'title'       => __( 'Widget threshold', 'woothemes' ),
								'type'        => 'text',
								'description' => __( 'Product widget will only appear on products above this value' ),
								'default'     => '250',
							),
							'prependPrice'    => array(
								'title'       => __( 'Widget Prefix', 'woothemes' ),
								'type'        => 'text',
								'description' => __( 'Eg. "Available on instalments"', 'woothemes' ),
								'default'     => '',
							),
							'appendPrice'     => array(
								'title'       => __( 'Widget Suffix', 'woothemes' ),
								'type'        => 'text',
								'description' => __( 'Eg. "Available on instalments"', 'woothemes' ),
								'default'     => '',
							),
							'Order Settings'  => array(
								'title' => __( 'Order Settings', 'woothemes' ),
								'type'  => 'title',
								'class' => 'border',
							),
							'autoFulfillment' => array(
								'title'       => __( 'Enable/Disable Automatic Fulfillment', 'woothemes' ),
								'label'       => __( 'Automatic Fulfillment', 'woothemes' ),
								'type'        => 'checkbox',
								'description' => __( 'Automatically Send Fulfillment request on order completion', 'woothemes' ),
								'default'     => false,
							),
						)
					);
				} catch ( Exception $e ) {
					return [];
				}
			}
		}
		/**
		 * Admin Panel Options
		 * - Payment options
		 */
		function admin_options() {

			?>
			<h3><?php esc_html_e( 'Finance', 'woothemes' ); ?></h3>
			<p><?php esc_html_e( 'This plugin allows you to accept finance payments in your WooCommerce store.', 'woothemes' ); ?></p>
			<table class="form-table">
			<?php
			$this->init_settings();
			?>
				<h3 style="border-bottom:1px solid"><?php esc_html_e( 'General Settings', 'woothemes' ); ?></h3>
			<?php
			if ( isset( $this->api_key ) && $this->api_key ) {

					$response = $this->get_all_finances( $this->api_key, 1 );
					$options  = array();
				if ( [] === $response ) {
					?>
						<div style="border:1px solid red;color:red;padding:20px;">
							<b><?php esc_html_e( 'Wrong or invalid API key', 'woothemes' ); ?></b>
							<p><?php esc_html_e( 'Contact Finance provider for more information', 'woothemes' ); ?></p>
						</div>
					<?php
				}
			}

			$this->generate_settings_html();
			?>
			</table><!--/.form-table-->

			<script type="text/javascript">
				jQuery(document).ready(function($) {
					function checkFinanceSettings()
					{
						$("#woocommerce_finance_priceSelection").parent().parent().parent().hide();
						if ($("#woocommerce_finance_productSelect").val() === 'price') {
							$("#woocommerce_finance_priceSelection").parent().parent().parent().show();
						}
						$("#woocommerce_finance_showFinanceOptionSelection").parent().parent().parent().hide();
						if ($("#woocommerce_finance_showFinanceOptions").val() === 'selection') {
							$("#woocommerce_finance_showFinanceOptionSelection").parent().parent().parent().show();
						}
					}
					$("#woocommerce_finance_productSelect,#woocommerce_finance_showFinanceOptions").on('change',function() {checkFinanceSettings();});
					checkFinanceSettings();
				});
			</script>
			<?php
		}
		/**
		 * Get the users country either from their order, or from their customer data.
		 */
		function get_country_code() {
			global $woocommerce;
			if ( isset( $_GET['order_id'] ) ) { // Input var okay.
				$order = new WC_Order( sanitize_text_field( wp_unslash( $_GET['order_id'] ) ) ); // Input var okay.
				return $order->billing_country;
			} elseif ( version_compare( $this->woo_version, '3.0.0' ) >= 0 && $woocommerce->customer->get_billing_country() ) {
				return $woocommerce->customer->get_billing_country(); // Version 3.0+.
			} elseif ( $woocommerce->customer->get_country() ) {
				return $woocommerce->customer->get_country(); // Version ~2.0.
			}
			return null;
		}
		/**
		 * Payment form on checkout page.
		 */
		function payment_fields() {
			$finances = $this->get_finances( $this->getCheckoutFinanceOptions() );
			if ( $finances ) {
				$user_country = $this->get_country_code();
				if ( empty( $user_country ) ) :
					esc_html_e( 'Select a country to see the payment form', 'woothemes' );
					return;
				endif;
				if ( ! in_array( $user_country, $this->avaiable_countries, true ) ) :
					esc_html_e( 'Finance payment method is not available in your country.', 'woothemes' );
					return;
				endif;
				$amount = WC()->cart->total;
				$plans  = $this->get_checkout_plans();
				include_once WP_PLUGIN_DIR . '/' . plugin_basename( dirname( __FILE__ ) ) . '/includes/checkout.php';
			}
		}
		/**
		 * Process the payment.
		 *
		 * @param  int $order_id The order id integer.
		 * @return array
		 */
		function process_payment( $order_id ) {
			global $woocommerce;
			$order = new WC_Order( $order_id );
			if ( isset( $_POST['submit-payment-form-nonce'] ) ) { // Input var okay.
				if ( ! wp_verify_nonce( sanitize_key( $_POST['submit-payment-form-nonce'] ), 'submit-payment-form' ) ) { // Input var okay.
					return;
				}
			}
			// TODO: Checking for divido_plan and divido_deposit.
			$finances = $this->get_finances( $this->getCheckoutFinanceOptions() );
			foreach ( $finances as $_finance => $value ) {
				if ( isset( $_POST['divido_plan'] ) && $_finance === $_POST['divido_plan'] ) { // Input var okay.
					$finance     = $_finance;
					$description = $value['description'];
					$min_deposit = $value['min_deposit'];
					$max_deposit = $value['max_deposit'];
				}
			}
			if ( isset( $_finance ) ) {
				$deposit     = ( isset( $_POST['divido_deposit'] ) && intval( $_POST['divido_deposit'] ) > 0 ) ? sanitize_text_field( wp_unslash( $_POST['divido_deposit'] ) ) : $min_deposit; // Input var okay.
				$products    = array();
				$order_total = 0;
				foreach ( $woocommerce->cart->get_cart() as $item ) {
					if ( version_compare( $this->get_woo_version(), '3.0.0' ) >= 0 ) {
						$_product = wc_get_product( $item['data']->get_id() );
						$name     = $_product->get_title();
					} else {
						$_product = $item['data']->post;
						$name     = $_product->post_title;
					}
					$quantity     = $item['quantity'];
					$price        = $item['line_subtotal'] / $quantity * 100;
					$order_total += $item['line_subtotal'];
					$products[]   = array(
						'name'     => $name,
						'quantity' => $quantity,
						'price'    => $price,
					);
				}
				$deposit = ( isset( $_POST['divido_deposit'] ) && intval( $_POST['divido_deposit'] ) > 0 ) ? sanitize_text_field( wp_unslash( $_POST['divido_deposit'] ) ) : $min_deposit; // Input var okay.
				if ( $woocommerce->cart->needs_shipping() ) {
					$shipping   = $order->get_total_shipping();
					$shipping   = (int) $shipping;
					$products[] = array(
						'name'     => 'Shipping and handling',
						'quantity' => 1,
						'price'    => $shipping * 100,
					);
					// Add shipping to ordertotal.
					$order_total += $shipping;
				}
				foreach ( $woocommerce->cart->get_taxes() as $tax ) {
					$products[] = array(
						'name'     => 'Taxes',
						'quantity' => 1,
						'price'    => $tax * 100,
					);
					// Add tax to ordertotal.
					$order_total += $tax;
				}
				foreach ( $woocommerce->cart->get_fees() as $fee ) {
					$products[] = array(
						'name'     => 'Fees',
						'quantity' => 1,
						'price'    => $fee->amount * 100,
					);
					if ( $fee->taxable ) {
						$products[]   = array(
							'name'     => 'Fees-tax',
							'quantity' => 1,
							'price'    => $fee->tax * 100,
						);
						$order_total += $fee->tax;
					}
					// Add Fee to ordertotal.
					$order_total += $fee->amount;
				}
				// Gets the total discount amount(including coupons) - both Taxed and untaxed.
				if ( $woocommerce->cart->get_cart_discount_total() ) {
					$products[] = array(
						'name'     => 'Discount',
						'quantity' => 1,
						'price'    => -$woocommerce->cart->get_cart_discount_total() * 100,
					);
					// Deduct total discount.
					$order_total -= $woocommerce->cart->get_cart_discount_total();
				}
				$other = $order->get_total() - $order_total;
				if ( 0 !== $other ) {
					$products[] = array(
						'name'     => 'Other',
						'quantity' => 1,
						'price'    => $other,
					);
				}
				if ( '' !== $this->secret ) {
					$secret = $this->create_signature($this->secret);
				}
				// Version 3.0+.
				// Create an appication model with the application data.
				if ( version_compare( $this->get_woo_version(), '3.0.0' ) >= 0 ) {
					$env                       = $this->environments( $this->api_key );
					$sdk                       = new \Divido\MerchantSDK\Client( $this->api_key, $env );
					$application               = ( new \Divido\MerchantSDK\Models\Application() )
						->withCountryId( $order->get_billing_country() )
						->withCurrencyId( 'GBP' )
						->withLanguageId( 'en' )
						->withFinancePlanId( $finance )
						->withApplicants(
							[
								[
									'firstName'   => $order->get_billing_first_name(),
									'lastName'    => $order->get_billing_last_name(),
									'phoneNumber' => $order->get_billing_phone(),
									'email'       => $order->get_billing_email(),
									'addresses'   => array(
										[
											'postcode' => $order->get_billing_postcode(),
											'street'   => $order->get_billing_address_1(),
											'town'     => $order->get_billing_city(),
										],
									),
								],
							]
						)
						->withOrderItems( $products )
						->withDepositPercentage($deposit/100)
						->withFinalisationRequired( false )
						->withMerchantReference( '' )
						->withUrls(
							[
								'merchant_redirect_url' => $order->get_checkout_order_received_url(),
								'merchant_checkout_url' => wc_get_checkout_url(),
								'merchant_response_url' => admin_url( 'admin-ajax.php' ) . '?action=woocommerce_finance_callback',
							]
						)
						->withMetadata(
							[
								'order_number' => $order_id,
							]
						);
					$response                  = $sdk->applications()->createApplication( $application, [], [ 'X-Divido-Hmac-Sha256' => $secret ] );
					$application_response_body = $response->getBody()->getContents();
					$decode                    = json_decode( $application_response_body );
					$result_id                 = $decode->data->id;
					$result_redirect           = $decode->data->urls->application_url;
				} else {
					//
					// Version ~2.0.
					//
					$env                       = $this->environments( $this->api_key );
					$sdk                       = new \Divido\MerchantSDK\Client( $this->api_key, $env );
					$application               = ( new \Divido\MerchantSDK\Models\Application() )
						->withCountryId( $order->billing_country )
						->withCurrencyId( 'GBP' )
						->withLanguageId( 'en' )
						->withFinancePlanId( $finance )
						->withApplicants(
							[
								[
									'firstName'   => $order->billing_first_name,
									'lastName'    => $order->billing_last_name,
									'phoneNumber' => $order->billing_phone,
									'email'       => $order->billing_email,
									'addresses'   => array(
										[
											'postcode' => $order->get_billing_postcode,
											'street'   => $order->get_billing_address_1,
											'town'     => $order->get_billing_city,
										],
									),
								],
							]
						)
						->withOrderItems( $products )
						->withDepositPercentage($deposit/100)
						->withFinalisationRequired( false )
						->withMerchantReference( '' )
						->withUrls(
							[
								'merchant_redirect_url' => $order->get_checkout_order_received_url(),
								'merchant_checkout_url' => wc_get_checkout_url(),
								'merchant_response_url' => admin_url( 'admin-ajax.php' ) . '?action=woocommerce_finance_callback',
							]
						)
						->withMetadata(
							[
								'order_number' => $order_id,
							]
						);
					$response                  = $sdk->applications()->createApplication( $application, [], [ 'X-Divido-Hmac-Sha256' => $secret ] );
					$application_response_body = $response->getBody()->getContents();
					$decode                    = json_decode( $application_response_body );
					$result_id                 = $decode->data->id;
					$result_redirect           = $decode->data->urls->application_url;
				}
			}
			// TODO condition to return true.
			try {

				update_post_meta( $order_id, '_finance_reference', $result_id );
				update_post_meta( $order_id, '_divido_finance', $description );
				update_post_meta( $order_id, '_finance_amount', number_format( $order->get_total(), 2, '.', '' ) );
				return array(
					'result'   => 'success',
					'redirect' => $result_redirect,
				);
			} catch ( Exception $e ) {
				$cancel_note = __( 'Finance Payment failed', 'woothemes' ) . ' (Transaction ID: ' . $order_id . '). ' . __( 'Payment was rejected due to an error', 'woothemes' ) . ': "' . $response->error . '". ';
				$order->add_order_note( $cancel_note );
				if ( version_compare( $this->get_woo_version(), '2.1.0' ) >= 0 ) {
					wc_add_notice( __( 'Payment error', 'woothemes' ) . ': ' . $decode->data->error . '' );
				} else {
					$woocommerce->add_error( __( 'Payment error', 'woothemes' ) . ': ' . $decode->data->error . '' );
				}
			}
		}
		/**
		 * Get Finances helper function
		 *
		 * @param  boolean $selection true or false depending on checkout or widget use.
		 * @return array Array of finances.
		 */
		function get_finances( $selection = false ) {
			if ( ! isset( $this->finance_options ) ) {
				$this->finance_options = $this->get_all_finances( $this->api_key );
			}
			$response = $this->finance_options; // array.
			$finances = array();
			// TODO Condition to return true.
			try {
				foreach ( $response as $_finance ) {
					if ( ( ! $selection && ! is_array( $selection ) ) || in_array( $_finance->id, $selection, true ) ) {
						$finances[ $_finance->id ] = $_finance->description;
						$finances[ $_finance->id ] = array(
							'description' => $_finance->description,
							'min_deposit' => $_finance->deposit->minimum_percentage,
							'max_deposit' => $_finance->deposit->maximum_percentage,
						);
					}
				}
			} catch ( Exception $e ) {
				return [];
			} finally {
				return $finances;
			}

		}
		/**
		 * Define environment function
		 *
		 *  @param [string] $key   - The Divido API key.
		 */
		function environments( $key ) {
			$array       = explode( '_', $key );
			$environment = strtoupper( $array[0] );
			if ( constant( "Divido\MerchantSDK\Environment::$environment" ) !== null ) {
				return constant( "Divido\MerchantSDK\Environment::$environment" );
			} else {
				return false;
			}
		}

		function wpdocs_enqueue_custom_admin_style( $hook_suffix ) {
			// Check if it's the ?page=yourpagename. If not, just empty return before executing the folowing scripts.
			if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
				return;
			}
			wp_register_style( 'woocommerce-finance-gateway-style', plugins_url( '', __FILE__ ) . '/css/style.css', false, 1.0 );
			wp_enqueue_style( 'woocommerce-finance-gateway-style' );
		}

		/**
		 * Validate the payment form.
		 *
		 * @return true
		 */
		function validate_fields() {
			return true;
		}
		/**
		 * Validate plugin settings.
		 *
		 * @return true
		 */
		function validate_settings() {
			return true;
		}
		/**
		 * Create HMAC SIGNATURE.
		 *
		 * @param  [string] $payload Payload value.
		 * @param  [string] $secret  The secret value saved on Finance portal and WordPress.
		 * @return string Returns a base64 encoded string.
		 */
		public function create_signature( $payload, $secret ) {
			$hmac      = hash_hmac( 'sha256', $payload, $secret, true );
			$signature = base64_encode( $hmac );
			return $signature;
		}
		/**
		 * Wrapper function for sending JSON.
		 *
		 * @param  [string] $status  The status to send - defaults ok.
		 * @param  [string] $message The message to send in the json.
		 * @return void
		 */
		function send_json( $status = 'ok', $message = '' ) {
			$plugindata = get_plugin_data( __FILE__ );
			$response   = array(
				'status'         => $status,
				'message'        => $message,
				'platform'       => 'Woocommerce',
				'plugin_version' => $plugindata['Version'],
			);
			wp_send_json( $response );
		}
		/**
		 * Check WooCommerce version.
		 *
		 * @return string WooCommerce version.
		 */
		function get_woo_version() {
			if ( ! function_exists( 'get_plugins' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin_folder = get_plugins( '/woocommerce' );
			$plugin_file   = 'woocommerce.php';
			if ( isset( $plugin_folder[ $plugin_file ]['Version'] ) ) {
				return $plugin_folder[ $plugin_file ]['Version'];
			} else {
				return null;
			}
		}
		/**
		 * Access stored variables in post meta
		 *
		 * @param  [object] $order Instance of wc_get_order.
		 * @return array An array containing the finance reference number and the finance id.
		 */
		function get_ref_finance( $order ) {
			$result = array(
				'ref'     => false,
				'finance' => false,
			);
			if ( version_compare( $this->woo_version, '3.0.0' ) >= 0 ) {
				$ref     = get_post_meta( $order->get_id(), '_finance_reference', true );
				$finance = get_post_meta( $order->get_id(), '_finance', true );
			} else {
				$ref     = get_post_meta( $order->id, '_finance_reference', true );
				$finance = get_post_meta( $order->id, '_finance', true );
			}
			$result['ref']     = $ref;
			$result['finance'] = $finance;
			return $result;
		}
		/**
		 * A wrapper to determine if autofulfilment is on whether to send fulfillments.
		 *
		 * @param  [int] $order_id - The woocommerce order id.
		 */
		function send_finance_fulfillment_request( $order_id ) {
			$wc_order_id = (string) $order_id;
			$name        = get_post_meta( $order_id, '_payment_method', true );
			$order       = wc_get_order( $order_id );
			$order_total = $order->get_total();
			if ( 'finance' === $name ) {
				if ( 'no' !== $this->auto_fulfillment ) {
					$ref_and_finance = $this->get_ref_finance( $order );
					$this->logger->debug( 'Finance', 'Autofullfillment selected' . $ref_and_finance['ref'] );
					$this->set_fulfilled( $ref_and_finance['ref'], $order_total, $wc_order_id, $product_name, $product_quantity );
					$order->add_order_note( 'Finance - Autofulfillment Request Sents.' );
				} else {
					$this->logger->debug( 'Finance', 'Autofulfillment not set' );
				}
			} else {
				return false;
			}
		}
		/**
		 * Function that will activate an application or set to fulfilled on dividio.
		 *
		 * @param [string] $application_id   - The Divido Application ID - fea4dcb7-e474-4fba-b1a4-123.....
		 * @param [string] $order_total  - Total amount of the order.
		 * @param [string] $order_id  - The Order ID from WooCommerce.
		 * @param [string] $shipping_method - If the shipping method is set we can apply it here.
		 * @param [string] $tracking_numbers - If there are any tracking numbers to attach we apply here.
		 * @return void
		 */
		function set_fulfilled( $application_id, $order_total, $order_id, $shipping_method = null, $tracking_numbers = null ) {
			// First get the application you wish to create an activation for.
			$application = ( new \Divido\MerchantSDK\Models\Application() )
			->withId( $application_id );
			$items       = [
				[
					'name'     => "Order id: $order_id",
					'quantity' => 1,
					'price'    => $order_total * 100,
				],
			];
			// Create a new application activation model.
			$application_activation = ( new \Divido\MerchantSDK\Models\ApplicationActivation() )
				->withOrderItems( $items )
				->withDeliveryMethod( $shipping_method )
				->withTrackingNumber( $tracking_numbers );
			// Create a new activation for the application.
			$env                      = $this->environments( $this->api_key );
			$sdk                      = new \Divido\MerchantSDK\Client( $this->api_key, $env );
			$response                 = $sdk->applicationActivations()->createApplicationActivation( $application, $application_activation );
			$activation_response_body = $response->getBody()->getContents();
		}
	} // end woocommerce_finance.
	global $woocommerce_finance;
	$woocommerce_finance = new WC_Gateway_Finance();
}
