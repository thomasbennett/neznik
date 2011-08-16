<?php
/*
Plugin Name: MarketPress
Version: 2.1.4
Plugin URI: http://premium.wpmudev.org/project/e-commerce
Description: The complete WordPress ecommerce plugin - works perfectly with BuddyPress and Multisite too to create a social marketplace, where you can take a percentage!
Author: Aaron Edwards (Incsub)
Author URI: http://uglyrobot.com
WDP ID: 144

Copyright 2009-2011 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

class MarketPress {

  var $version = '2.1.4';
  var $location;
  var $plugin_dir = '';
  var $plugin_url = '';
  var $product_template;
  var $product_taxonomy_template;
  var $product_list_template;
  var $store_template;
  var $checkout_template;
  var $orderstatus_template;
  var $language = '';
  var $checkout_error = false;
  var $cart_cache = false;
  var $is_shop_page = false;
  var $global_cart = false;

  function MarketPress() {
    $this->__construct();
  }

  function __construct() {
    //setup our variables
    $this->init_vars();

    //install plugin
    register_activation_hook( __FILE__, array($this, 'install') );

    //load template functions
    require_once( $this->plugin_dir . 'template-functions.php' );

    //load shortcodes
    include_once( $this->plugin_dir . 'marketpress-shortcodes.php' );

    //load sitewide features if WPMU
    if (is_multisite()) {
      include_once( $this->plugin_dir . 'marketpress-ms.php' );
      $network_settings = get_site_option( 'mp_network_settings' );
	    if ( $network_settings['global_cart'] )
	    	$this->global_cart = true;
    }

    $settings = get_option('mp_settings');

    //load APIs and plugins
		add_action( 'plugins_loaded', array(&$this, 'load_plugins') );

    //load importers
		add_action( 'plugins_loaded', array(&$this, 'load_importers') );

		//localize the plugin
		add_action( 'plugins_loaded', array(&$this, 'localization') );

		//custom post type
    add_action( 'init', array(&$this, 'register_custom_posts'), 0 ); //super high priority
		add_filter( 'request', array(&$this, 'handle_edit_screen_filter') );

		//edit products page
		add_filter( 'manage_product_posts_columns', array(&$this, 'edit_products_columns') );
		add_action( 'manage_posts_custom_column', array(&$this, 'edit_products_custom_columns') );
		add_action( 'restrict_manage_posts', array(&$this, 'edit_products_filter') );

		//manage orders page
		add_filter( 'manage_product_page_marketpress-orders_columns', array(&$this, 'manage_orders_columns') );
		add_action( 'manage_posts_custom_column', array(&$this, 'manage_orders_custom_columns') );

		//Plug admin pages
		add_action( 'admin_menu', array(&$this, 'add_menu_items') );
		add_action( 'admin_print_styles', array(&$this, 'admin_css') );
		add_action( 'admin_print_scripts', array(&$this, 'admin_script_post') );
    add_action( 'admin_notices', array(&$this, 'admin_nopermalink_warning') );

		//Meta boxes
		add_action( 'add_meta_boxes_product', array(&$this, 'meta_boxes') );
		add_action( 'wp_insert_post', array(&$this, 'save_product_meta'), 10, 2 );

		//Templates and Rewrites
		add_action( 'wp', array(&$this, 'load_store_templates') );
		add_action( 'template_redirect', array(&$this, 'load_store_theme') );
	  add_action( 'pre_get_posts', array(&$this, 'remove_canonical') );
		add_filter( 'rewrite_rules_array', array(&$this, 'add_rewrite_rules') );
  	add_filter( 'query_vars', array(&$this, 'add_queryvars') );
		add_filter( 'wp_list_pages', array(&$this, 'filter_list_pages'), 10, 2 );
		add_filter( 'wp_nav_menu_objects', array(&$this, 'filter_nav_menu'), 10, 2 );
		add_action( 'option_rewrite_rules', array(&$this, 'check_rewrite_rules') );

		//Payment gateway returns
	  add_action( 'pre_get_posts', array(&$this, 'handle_gateway_returns'), 1 );

		//Store cart handling
    add_action( 'template_redirect', array(&$this, 'store_script') ); //only on front pages
    /* use both actions so logged in and not logged in users can send this AJAX request */
    add_action( 'wp_ajax_nopriv_mp-update-cart', array(&$this, 'update_cart') );
    add_action( 'wp_ajax_mp-update-cart', array(&$this, 'update_cart') );

		//Relies on post thumbnails for products
		add_action( 'after_setup_theme', array(&$this, 'post_thumbnails'), 9999 );

		//Add widgets
		if (!$settings['disable_cart'])
		  add_action( 'widgets_init', create_function('', 'return register_widget("MarketPress_Shopping_Cart");') );

		add_action( 'widgets_init', create_function('', 'return register_widget("MarketPress_Product_List");') );
		add_action( 'widgets_init', create_function('', 'return register_widget("MarketPress_Categories_Widget");') );
		add_action( 'widgets_init', create_function('', 'return register_widget("MarketPress_Tag_Cloud_Widget");') );

		// Edit profile
		add_action( 'profile_update', array(&$this, 'user_profile_update') );
		add_action( 'edit_user_profile', array(&$this, 'user_profile_fields') );
		add_action( 'show_user_profile', array(&$this, 'user_profile_fields') );

		//update install script if necessary
		if ($settings['mp_version'] != $this->version) {
			$this->install();
		}
	}

  function install() {
    $old_settings = get_option('mp_settings');
		$old_version = get_option('mp_version');

    //our default settings
    $default_settings = array (
      'base_country' => 'US',
      'tax' => array (
        'rate' => 0,
        'tax_shipping' => 1,
        'tax_inclusive' => 0
      ),
      'currency' => 'USD',
      'curr_symbol_position' => 1,
      'curr_decimal' => 1,
      'disable_cart' => 0,
      'inventory_threshhold' => 3,
      'max_downloads' => 5,
      'force_login' => 0,
      'ga_ecommerce' => 'none',
      'store_theme' => 'icons',
      'product_img_height' => 150,
      'product_img_width' => 150,
      'list_img_height' => 150,
      'list_img_width' => 150,
      'per_page' => 20,
      'order_by' => 'title',
      /* Translators: change default slugs here */
      'slugs' => array (
        'store' => __('store', 'mp'),
        'products' => __('products', 'mp'),
        'cart' => __('shopping-cart', 'mp'),
        'orderstatus' => __('order-status', 'mp'),
        'category' => __('category', 'mp'),
        'tag' => __('tag', 'mp')
      ),
      'product_button_type' => 'addcart',
      'show_quantity' => 1,
      'product_img_size' => 'medium',
      'show_lightbox' => 1,
      'list_view' => 'list',
      'list_button_type' => 'addcart',
      'show_thumbnail' => 1,
      'list_img_size' => 'thumbnail',
      'paginate' => 1,
      'order' => 'DESC',
      'shipping' => array (
        'allowed_countries' => array ('CA', 'US'),
        'method' => 'flat-rate'
      ),
      'gateways' => array (
        'paypal-express' => array (
          'locale' => 'US',
          'currency' => 'USD',
          'mode' => 'sandbox'
        ),
        'paypal-chained' => array (
          'currency' => 'USD',
          'mode' => 'sandbox'
        )
      ),
      'msg' => array (
        'product_list' => '',
        'order_status' => __('<p>If you have any questions about your order please do not hesitate to contact us.</p>', 'mp'),
        'cart' => '',
        'shipping' => __('<p>Please enter your shipping information in the form below to proceed with your order.</p>', 'mp'),
        'checkout' => '',
        'confirm_checkout' => __('<p>You are almost done! Please do a final review of your order to make sure everything is correct then click the "Confirm Payment" button.</p>', 'mp'),
        'success' => __('<p>Thank you for your order! We appreciate your business, and please come back often to check out our new products.</p>', 'mp')
      ),
      'store_email' => get_option("admin_email"),
      'email' => array (
        'new_order_subject' => __('Your Order Confirmation (ORDERID)', 'mp'),
        'new_order_txt' => __("Thank you for your order CUSTOMERNAME!

Your order has been received, and any items to be shipped will be processed as soon as possible. Please refer to your Order ID (ORDERID) whenever contacting us.
Here is a confirmation of your order details:

Order Information:
ORDERINFO

Shipping Information:
SHIPPINGINFO

Payment Information:
PAYMENTINFO

You can track the latest status of your order here: TRACKINGURL

Thanks again!", 'mp'),
        'shipped_order_subject' => __('Your Order Has Been Shipped! (ORDERID)', 'mp'),
        'shipped_order_txt' => __("Dear CUSTOMERNAME,

Your order has been shipped! Depending on the shipping method and your location it should be arriving shortly. Please refer to your Order ID (ORDERID) whenever contacting us.
Here is a confirmation of your order details:

Order Information:
ORDERINFO

Shipping Information:
SHIPPINGINFO

Payment Information:
PAYMENTINFO

You can track the latest status of your order here: TRACKINGURL

Thanks again!", 'mp')
      )
    );

    //filter default settings
    $default_settings = apply_filters( 'mp_default_settings', $default_settings );
    $settings = wp_parse_args( (array)$old_settings, $default_settings );
    update_option( 'mp_settings', $settings );

		//2.1.4 update
		if ( version_compare($old_version, '2.1.4', '<') )
			$this->update_214();

    //only run these on first install
    if ( empty($old_settings) ) {

			//define settings that don't need to autoload for efficiency
			add_option( 'mp_coupons', '', '', 'no' );
			add_option( 'mp_store_page', '', '', 'no' );

			//create store page
			add_action( 'init', array(&$this, 'create_store_page') );

			//add cart widget to first sidebar
			add_action( 'widgets_init', array(&$this, 'add_default_widget'), 11 );
   	}

    //add action to flush rewrite rules after we've added them for the first time
    add_action( 'init', array(&$this, 'flush_rewrite'), 999 );

    update_option( 'mp_version', $this->version );
  }

	//run on 2.1.4 update to fix price sorts
	function update_214() {
		global $wpdb;

		$posts = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product'");

		foreach ($posts as $post_id) {
			$meta = get_post_custom($post_id);
			//unserialize
			foreach ($meta as $key => $val) {
				$meta[$key] = maybe_unserialize($val[0]);
				if (!is_array($meta[$key]) && $key != "mp_is_sale" && $key != "mp_track_inventory" && $key != "mp_product_link" && $key != "mp_file" && $key != "mp_price_sort")
					$meta[$key] = array($meta[$key]);
			}

			//fix price sort field if missing
			if ( empty($meta["mp_price_sort"]) && is_array($meta["mp_price"]) ) {
				if ( $meta["mp_is_sale"] && $meta["mp_sale_price"][0] )
					$sort_price = $meta["mp_sale_price"][0];
				else
					$sort_price = $meta["mp_price"][0];
				update_post_meta($post_id, 'mp_price_sort', $sort_price);
			}
		}
	}

  function localization() {
    // Load up the localization file if we're using WordPress in a different language
  	// Place it in this plugin's "languages" folder and name it "mp-[value in wp-config].mo"
  	if ($this->location == 'mu-plugins')
      load_muplugin_textdomain( 'mp', '/marketpress-includes/languages/' );
  	else if ($this->location == 'subfolder-plugins')
      load_plugin_textdomain( 'mp', false, '/marketpress/marketpress-includes/languages/' );
    else if ($this->location == 'plugins')
      load_plugin_textdomain( 'mp', false, '/marketpress-includes/languages/' );

  	//setup language code for jquery datepicker translation
  	$temp_locales = explode('_', get_locale());
    $this->language = ($temp_locales[0]) ? $temp_locales[0] : 'en';
  }

  function init_vars() {
    //setup proper directories
    if (defined('WP_PLUGIN_URL') && defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/marketpress/' . basename(__FILE__))) {
      $this->location = 'subfolder-plugins';
      $this->plugin_dir = WP_PLUGIN_DIR . '/marketpress/marketpress-includes/';
      $this->plugin_url = WP_PLUGIN_URL . '/marketpress/marketpress-includes/';
  	} else if (defined('WP_PLUGIN_URL') && defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . '/' . basename(__FILE__))) {
      $this->location = 'plugins';
      $this->plugin_dir = WP_PLUGIN_DIR . '/marketpress-includes/';
      $this->plugin_url = WP_PLUGIN_URL . '/marketpress-includes/';
  	} else if (is_multisite() && defined('WPMU_PLUGIN_URL') && defined('WPMU_PLUGIN_DIR') && file_exists(WPMU_PLUGIN_DIR . '/' . basename(__FILE__))) {
      $this->location = 'mu-plugins';
      $this->plugin_dir = WPMU_PLUGIN_DIR . '/marketpress-includes/';
      $this->plugin_url = WPMU_PLUGIN_URL . '/marketpress-includes/';
  	} else {
      wp_die(__('There was an issue determining where MarketPress is installed. Please reinstall.', 'mp'));
    }

    //load data structures
		require_once( $this->plugin_dir . 'marketpress-data.php' );

  }

  /* Only load code that needs BuddyPress to run once BP is loaded and initialized. */
  function load_bp_features() {
    include_once( $this->plugin_dir . 'marketpress-bp.php' );
  }

  function load_importers() {
    include_once( $this->plugin_dir . 'marketpress-importers.php' );
  }

  function load_plugins() {
    $settings = get_option('mp_settings');

    if (!$settings['disable_cart']) {
      //load shipping plugin API
      require_once( $this->plugin_dir . 'marketpress-shipping.php' );
      $this->load_shipping_plugins();

      //load gateway plugin API
      require_once( $this->plugin_dir . 'marketpress-gateways.php' );
      $this->load_gateway_plugins();
    }
  }

  function load_shipping_plugins() {

    //save settings from screen. Put here to be before plugin is loaded
    if (isset($_POST['shipping_settings'])) {
      $settings = get_option('mp_settings');
      //allow plugins to verify settings before saving
      $settings = array_merge($settings, apply_filters('mp_shipping_settings_filter', $_POST['mp']));
      update_option('mp_settings', $settings);
    }

    //get shipping plugins dir
    $dir = $this->plugin_dir . 'plugins-shipping/';

    //search the dir for files
    $shipping_plugins = array();
  	if ( !is_dir( $dir ) )
  		return;
  	if ( ! $dh = opendir( $dir ) )
  		return;
  	while ( ( $plugin = readdir( $dh ) ) !== false ) {
  		if ( substr( $plugin, -4 ) == '.php' )
  			$shipping_plugins[] = $dir . $plugin;
  	}
  	closedir( $dh );
  	sort( $shipping_plugins );

  	//include them suppressing errors
  	foreach ($shipping_plugins as $file)
      @include_once( $file );

		//allow plugins from an external location to register themselves
		do_action('mp_load_shipping_plugins');

    //load chosen plugin class
    global $mp_shipping_plugins, $mp_shipping_active_plugin;
    $settings = get_option('mp_settings');

    $class = $mp_shipping_plugins[$settings['shipping']['method']][0];
    if (class_exists($class))
      $mp_shipping_active_plugin = new $class;
  }

  function load_gateway_plugins() {

    //save settings from screen. Put here to be before plugin is loaded
    if (isset($_POST['gateway_settings'])) {
      $settings = get_option('mp_settings');

      //see if there are checkboxes checked
      if ( isset( $_POST['mp'] ) ) {

        //clear allowed array as it will be refilled
        unset( $settings['gateways']['allowed'] );

        //allow plugins to verify settings before saving
        $settings = array_merge($settings, apply_filters('mp_gateway_settings_filter', $_POST['mp']));
      } else {
        //blank array if no checkboxes
        $settings['gateways']['allowed'] = array();
      }

      update_option('mp_settings', $settings);
    }

    //get gateway plugins dir
    $dir = $this->plugin_dir . 'plugins-gateway/';

    //search the dir for files
    $gateway_plugins = array();
  	if ( !is_dir( $dir ) )
  		return;
  	if ( ! $dh = opendir( $dir ) )
  		return;
  	while ( ( $plugin = readdir( $dh ) ) !== false ) {
  		if ( substr( $plugin, -4 ) == '.php' )
  			$gateway_plugins[] = $dir . '/' . $plugin;
  	}
  	closedir( $dh );
  	sort( $gateway_plugins );

  	//include them suppressing errors
  	foreach ($gateway_plugins as $file)
      include( $file );

    //allow plugins from an external location to register themselves
		do_action('mp_load_gateway_plugins');

    //load chosen plugin classes
    global $mp_gateway_plugins, $mp_gateway_active_plugins;
    $settings = get_option('mp_settings');
    $network_settings = get_site_option( 'mp_network_settings' );

    foreach ((array)$mp_gateway_plugins as $code => $plugin) {
      $class = $plugin[0];
			//if global cart is enabled force it
      if ( $this->global_cart ) {
        if ( $code == $network_settings['global_gateway'] && class_exists($class) ) {
          $mp_gateway_active_plugins[] = new $class;
          break;
				}
      } else {
	      if ( in_array($code, (array)$settings['gateways']['allowed']) && class_exists($class) && !$plugin[3] )
	        $mp_gateway_active_plugins[] = new $class;
			}
    }
  }

  function handle_gateway_returns($wp_query) {
    //listen for gateway IPN returns and tie them in to proper gateway plugin
		if(!empty($wp_query->query_vars['paymentgateway'])) {
			do_action( 'mp_handle_payment_return_' . $wp_query->query_vars['paymentgateway'] );
			// exit();
		}

		//stop canonical problems with virtual pages
  	$page = get_query_var('pagename');
  	if ($page == 'cart' || $page == 'orderstatus' || $page == 'product_list') {
			remove_action('template_redirect', 'redirect_canonical');
		}
	}

  function remove_canonical($wp_query) {
		//stop canonical problems with virtual pages redirecting
  	$page = get_query_var('pagename');
  	if ($page == 'cart' || $page == 'orderstatus' || $page == 'product_list') {
			remove_action('template_redirect', 'redirect_canonical');
		}
	}

  function admin_nopermalink_warning() {
    //warns admins if permalinks are not enabled on the blog
    if ( current_user_can('manage_options') && !get_option('permalink_structure') )
      echo '<div class="error"><p>'.__('You must <a href="options-permalink.php">enable Pretty Permalinks</a> to use MarketPress!', 'mp').'</p></div>';
	}

  function add_menu_items() {
    $settings = get_option('mp_settings');

    //only process the manage orders page for editors and above and if orders hasn't been disabled
    if (current_user_can('edit_others_posts') && !$settings['disable_cart']) {
      $num_posts = wp_count_posts('mp_order'); //get pending order count
      $count = $num_posts->order_received + $num_posts->order_paid;
      if ( $count > 0 )
  			$count_output = '&nbsp;<span class="update-plugins"><span class="updates-count count-' . $count . '">' . $count . '</span></span>';
  		else
  			$count_output = '';
      $orders_page = add_submenu_page('edit.php?post_type=product', __('Manage Orders', 'mp'), __('Manage Orders', 'mp') . $count_output, 'edit_others_posts', 'marketpress-orders', array(&$this, 'orders_page'));
    }

    $page = add_submenu_page('edit.php?post_type=product', __('Store Settings', 'mp'), __('Store Settings', 'mp'), 'manage_options', 'marketpress', array(&$this, 'admin_page'));
    add_action( 'admin_print_scripts-' . $page, array(&$this, 'admin_script_settings') );
    add_action( 'admin_print_styles-' . $page, array(&$this, 'admin_css_settings') );
    add_contextual_help($page, '<iframe src="http://premium.wpmudev.org/wdp-un.php?action=help&id=144" width="100%" height="600px"></iframe>');
  }

  function admin_css() {
    wp_enqueue_style( 'mp-admin-css', $this->plugin_url . 'css/marketpress.css', false, $this->version);
  }

  //enqeue js on custom post edit screen
  function admin_script_post() {
    global $current_screen;
    if ($current_screen->id == 'product')
      wp_enqueue_script( 'mp-post', $this->plugin_url . 'js/post-screen.js', array('jquery'), $this->version);
  }

  //enqeue css on product settings screen
  function admin_css_settings() {
    wp_enqueue_style( 'jquery-datepicker-css', $this->plugin_url . 'datepicker/css/ui-lightness/datepicker.css', false, $this->version);
    wp_enqueue_style( 'jquery-colorpicker-css', $this->plugin_url . 'colorpicker/css/colorpicker.css', false, $this->version);
  }

  //enqeue js on product settings screen
  function admin_script_settings() {
    wp_enqueue_script( 'jquery-colorpicker', $this->plugin_url . 'colorpicker/js/colorpicker.js', array('jquery'), $this->version);
    wp_enqueue_script( 'jquery-datepicker', $this->plugin_url . 'datepicker/js/datepicker.min.js', array('jquery', 'jquery-ui-core'), $this->version);

    //only load languages for datepicker if not english (or it will show Chinese!)
    if ($this->language != 'en')
      wp_enqueue_script( 'jquery-datepicker-i18n', $this->plugin_url . 'datepicker/js/datepicker-i18n.min.js', array('jquery', 'jquery-ui-core', 'jquery-datepicker'), $this->version);
  }

  //ajax cart handling for store frontend
  function store_script() {
		//disable ajax cart if incompatible by domain mapping plugin settings
		if (is_multisite() && class_exists('domain_map') && 'original' == get_site_option('map_admindomain'))
		  return;

    //setup shopping cart javascript
    wp_enqueue_script( 'mp-store-js', $this->plugin_url . 'js/store.js', array('jquery'), $this->version );

    // declare the variables we need to access in js
    wp_localize_script( 'mp-store-js', 'MP_Ajax', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'emptyCartMsg' => __('Are you sure you want to remove all items from your cart?', 'mp'), 'successMsg' => __('Item(s) Added!', 'mp'), 'imgUrl' => $this->plugin_url.'images/loading.gif', 'addingMsg' => __('Adding to your cart...', 'mp'), 'outMsg' => __('Out of Stock', 'mp') ) );
  }

  function load_tiny_mce($selector) {
    wp_tiny_mce(false, array("editor_selector" => $selector, 'plugins' => 'inlinepopups,spellchecker,tabfocus,paste,link'));
	}


  //loads the jquery lightbox plugin
  function enqueue_lightbox() {

    $settings = get_option('mp_settings');
    if (!$settings['show_lightbox'])
      return;

    wp_enqueue_style( 'jquery-lightbox', $this->plugin_url . 'lightbox/css/jquery.lightbox-0.5.css', false, $this->version );
    wp_enqueue_script( 'jquery-lightbox', $this->plugin_url . 'lightbox/js/jquery.lightbox-0.5.pack.js', array('jquery'), $this->version );

    // declare the variables we need to access in js
    $js_vars = array( 'imageLoading' => $this->plugin_url . 'lightbox/images/lightbox-ico-loading.gif',
                      'imageBtnClose' => $this->plugin_url . 'lightbox/images/lightbox-btn-close.gif',
                      'imageBtnPrev' => $this->plugin_url . 'lightbox/images/lightbox-btn-prev.gif',
                      'imageBtnNext' => $this->plugin_url . 'lightbox/images/lightbox-btn-next.gif',
                      /* For lightbox Product # of # display */
                      'txtImage' => __('Product', 'mp'),
                      'txtOf' => __('of', 'mp')
                    );
    wp_localize_script( 'jquery-lightbox', 'MP_Lightbox', $js_vars );
  }

	//if cart widget is not in a sidebar, add it to the top of the first sidebar. Only runs at initial install
	function add_default_widget() {
    if (!is_active_widget(false, false, 'mp_cart_widget')) {
      $sidebars_widgets = wp_get_sidebars_widgets();
      if ( is_array($sidebars_widgets) ) {
				foreach ( $sidebars_widgets as $sidebar => $widgets ) {
					if ( 'wp_inactive_widgets' == $sidebar )
						continue;

					if ( is_array($widgets) ) {
					  array_unshift($widgets, 'mp_cart_widget-1');
					  $sidebars_widgets[$sidebar] = $widgets;
						wp_set_sidebars_widgets( $sidebars_widgets );
            $settings = array();
						$settings[1] = array( 'title' => __('Shopping Cart', 'mp'), 'custom_text' => '', 'show_thumbnail' => 1, 'size' => 25 );
						$settings['_multiwidget'] = 1;
						update_option( 'widget_mp_cart_widget', $settings );
            return true;
					}
				}
			}
    }
	}

  //creates the store page on install and updates
  function create_store_page($old_slug = false) {
  	global $wpdb, $user_ID;
    $settings = get_option('mp_settings');

    //remove old page if updating
    if ($old_slug && $old_slug != $settings['slugs']['store']) {
      $old_post_id = $wpdb->get_var("SELECT ID FROM " . $wpdb->posts . " WHERE post_name = '$old_slug' AND post_type = 'page'");
      $old_post = get_post($old_post_id);

      $old_post->post_name = $settings['slugs']['store'];
      wp_update_post($old_post);
    }

    //insert new page if not existing
		$page_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->posts . " WHERE post_name = '" . $settings['slugs']['store'] . "' AND post_type = 'page'");
		if ( !$page_count ) {

		  //default page content
      $content  = '<p>' . __('Welcome to our online store! Feel free to browse around:', 'mp') . '</p>';
      $content .= '[mp_store_navigation]';
      $content .= '<p>' . __('Check out our most popular products:', 'mp') . '</p>';
      $content .= '[mp_popular_products]';
      $content .= '<p>' . __('Browse by category:', 'mp') . '</p>';
      $content .= '[mp_list_categories]';
      $content .= '<p>' . __('Browse by tag:', 'mp') . '</p>';
      $content .= '[mp_tag_cloud]';

      $id = wp_insert_post( array('post_title' => __('Store', 'mp'), 'post_name' => $settings['slugs']['store'], 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => $content ) );
			update_option('mp_store_page', $id);
    }
  }

  function register_custom_posts() {
    ob_start();

    $settings = get_option('mp_settings');

    // Register custom taxonomy
		register_taxonomy( 'product_category', 'product', apply_filters( 'mp_register_product_category', array("hierarchical" => true, 'label' => __('Product Categories', 'mp'), 'singular_label' => __('Product Category', 'mp'), 'rewrite' => array('slug' => $settings['slugs']['store'] . '/' . $settings['slugs']['products'] . '/' . $settings['slugs']['category'])) ) );
		register_taxonomy( 'product_tag', 'product', apply_filters( 'mp_register_product_tag', array("hierarchical" => false, 'label' => __('Product Tags', 'mp'), 'singular_label' => __('Product Tag', 'mp'), 'rewrite' => array('slug' => $settings['slugs']['store'] . '/' . $settings['slugs']['products'] . '/' . $settings['slugs']['tag'])) ) );

    // Register custom product post type
    $supports = array( 'title', 'editor', 'author', 'excerpt', 'revisions', 'thumbnail' );
    $args = array (
        'labels' => array('name' => __('Products', 'mp'),
                      		'singular_name' => __('Products', 'mp'),
                      		'add_new' => __('Create New', 'mp'),
                      		'add_new_item' => __('Create New Product', 'mp'),
                      		'edit_item' => __('Edit Products', 'mp'),
                      		'edit' => __('Edit', 'mp'),
                      		'new_item' => __('New Product', 'mp'),
                      		'view_item' => __('View Product', 'mp'),
                      		'search_items' => __('Search Products', 'mp'),
                      		'not_found' => __('No Products Found', 'mp'),
                      		'not_found_in_trash' => __('No Products found in Trash', 'mp'),
                      		'view' => __('View Product', 'mp')
                      	),
        'description' => __('Products for your MarketPress store.', 'mp'),
        'menu_icon' => $this->plugin_url . 'images/marketpress-icon.png',
        'public' => true,
        'show_ui' => true,
        'publicly_queryable' => true,
        'capability_type' => 'page',
        'hierarchical' => false,
        'rewrite' => array('slug' => $settings['slugs']['store'] . '/' . $settings['slugs']['products']), // Permalinks format
        'query_var' => true,
        'supports' => $supports
    );
    register_post_type( 'product' , apply_filters( 'mp_register_post_type', $args ) );

    //register the orders post type
    register_post_type( 'mp_order', array(
      'labels' => array('name' => __('Orders', 'mp'),
                      		'singular_name' => __('Order', 'mp'),
                      		'edit' => __('Edit', 'mp'),
                      		'view_item' => __('View Order', 'mp'),
                      		'search_items' => __('Search Orders', 'mp'),
                      		'not_found' => __('No Orders Found', 'mp')
                      	),
      'description' => __('Orders from your MarketPress store.', 'mp'),
  		'public' => false,
  		'show_ui' => false,
  		'capability_type' => apply_filters( 'mp_orders_capability', 'page' ),
  		'hierarchical' => false,
  		'rewrite' => false,
  		'query_var' => false,
      'supports' => array()
  	) );

    //register custom post statuses for our orders
    register_post_status( 'order_received', array(
  		'label'       => __('Received', 'mp'),
  		'label_count' => array( __('Received <span class="count">(%s)</span>', 'mp'), __('Received <span class="count">(%s)</span>', 'mp') ),
  		'post_type'   => 'mp_order',
  		'public'      => false
  	) );
  	register_post_status( 'order_paid', array(
  		'label'       => __('Paid', 'mp'),
  		'label_count' => array( __('Paid <span class="count">(%s)</span>', 'mp'), __('Paid <span class="count">(%s)</span>', 'mp') ),
  		'post_type'   => 'mp_order',
  		'public'      => false
  	) );
  	register_post_status( 'order_shipped', array(
  		'label'       => __('Shipped', 'mp'),
  		'label_count' => array( __('Shipped <span class="count">(%s)</span>', 'mp'), __('Shipped <span class="count">(%s)</span>', 'mp') ),
  		'post_type'   => 'mp_order',
  		'public'      => false
  	) );
  	register_post_status( 'order_closed', array(
  		'label'       => __('Closed', 'mp'),
  		'label_count' => array( __('Closed <span class="count">(%s)</span>', 'mp'), __('Closed <span class="count">(%s)</span>', 'mp') ),
  		'post_type'   => 'mp_order',
  		'public'      => false
  	) );
  }

  //necessary to mod array directly rather than with add_theme_support() to play nice with other themes. See http://www.wptavern.com/forum/plugins-hacks/1751-need-help-enabling-post-thumbnails-custom-post-type.html
  function post_thumbnails() {
    global $_wp_theme_features;

    if( !isset( $_wp_theme_features['post-thumbnails'] ) )
        $_wp_theme_features['post-thumbnails'] = array( array( 'product' ) );
    else if ( is_array( $_wp_theme_features['post-thumbnails'] ) )
        $_wp_theme_features['post-thumbnails'][0][] = 'product';
  }

  // This function clears the rewrite rules and forces them to be regenerated
  function flush_rewrite() {
  	global $wp_rewrite;
  	$wp_rewrite->flush_rules();
  }

  function add_rewrite_rules($rules){
    $settings = get_option('mp_settings');

    $new_rules = array();

    //product list
    $new_rules[$settings['slugs']['store'] . '/' . $settings['slugs']['products'] . '/?$'] = 'index.php?pagename=product_list';
  	$new_rules[$settings['slugs']['store'] . '/' . $settings['slugs']['products'] . '/page/?([0-9]{1,})/?$'] = 'index.php?pagename=product_list&paged=$matches[1]';

    //checkout page
    $new_rules[$settings['slugs']['store'] . '/' . $settings['slugs']['cart'] . '/?$'] = 'index.php?pagename=cart';
    $new_rules[$settings['slugs']['store'] . '/' . $settings['slugs']['cart'] . '/([^/]+)/?$'] = 'index.php?pagename=cart&checkoutstep=$matches[1]';

    //order status page
    $new_rules[$settings['slugs']['store'] . '/' . $settings['slugs']['orderstatus'] . '/?$'] = 'index.php?pagename=orderstatus';
    $new_rules[$settings['slugs']['store'] . '/' . $settings['slugs']['orderstatus'] . '/([^/]+)/?$'] = 'index.php?pagename=orderstatus&order_id=$matches[1]';

    //ipn handling for payment gateways
    $new_rules[$settings['slugs']['store'] . '/payment-return/(.+)'] = 'index.php?paymentgateway=$matches[1]';

  	return array_merge($new_rules, $rules);
  }

  //unfortunately some plugins flush rewrites before the init hook so they kill custom post type rewrites. This function verifies they are in the final array and flushes if not
  function check_rewrite_rules($value) {
    $settings = get_option('mp_settings');

    //prevent an infinite loop by only
    if ( ! post_type_exists( 'product' ) )
      return $value;

	if ( is_array($value) && !in_array('index.php?product=$matches[1]&paged=$matches[2]', $value) ) {
		$this->flush_rewrite();
    } else {
        return $value;
    }
  }

  function add_queryvars($vars) {
  	// This function add the checkout queryvars to the list that WordPress is looking for.
  	if(!in_array('checkoutstep', $vars))
      $vars[] = 'checkoutstep';

    if(!in_array('order_id', $vars))
      $vars[] = 'order_id';

    if(!in_array('paymentgateway', $vars))
      $vars[] = 'paymentgateway';

  	return $vars;
  }

  function start_session() {
    //start the sessions for cart handling
    if (session_id() == "")
      session_start();
  }

  //scans post type at template_redirect to apply custom themeing to products
  function load_store_templates() {
    global $wp_query, $mp_wpmu, $mp_gateway_active_plugins;
    $settings = get_option('mp_settings');

    //load proper theme for single product page display
    if ($wp_query->is_single && $wp_query->query_vars['post_type'] == 'product') {

      //check for custom theme templates
      $product_name = get_query_var('product');
      $product_id = (int) $wp_query->get_queried_object_id();

      //serve download if it exists
      $this->serve_download($product_id);

      $templates = array();
    	if ( $product_name )
    		$templates[] = "mp_product-$product_name.php";
    	if ( $product_id )
    		$templates[] = "mp_product-$product_id.php";
    	$templates[] = "mp_product.php";

      //if custom template exists load it
      if ($this->product_template = locate_template($templates)) {
        add_filter( 'template_include', array(&$this, 'custom_product_template') );
      } else {
        //otherwise load the page template and use our own theme
        $wp_query->is_single = null;
        $wp_query->is_page = 1;
        add_filter( 'the_content', array(&$this, 'product_theme'), 99 );
      }

      $this->is_shop_page = true;

      //enqueue lightbox on single product page
      $this->enqueue_lightbox();

    }

    //load proper theme for main store page
    if ($wp_query->query_vars['pagename'] == $settings['slugs']['store']) {

      //check for custom theme template
      $templates = array("mp_store.php");

      //if custom template exists load it
      if ($this->store_template = locate_template($templates)) {
        add_filter( 'template_include', array(&$this, 'custom_store_template') );
      } else {
        //otherwise load the page template and use our own theme
        add_filter( 'the_content', array(&$this, 'store_theme'), 99 );
      }

      $this->is_shop_page = true;
    }

    //load proper theme for checkout page
    if ($wp_query->query_vars['pagename'] == 'cart') {

      //init session for store pages
      $this->start_session();

      //process cart updates
      $this->update_cart();

			//if global cart is on forward to main site checkout
			if ( $this->global_cart && is_object($mp_wpmu) && !$mp_wpmu->is_main_site() ) {
				wp_redirect( mp_cart_link(false, true) );
				exit;
			}

			// Redirect to https if forced to use SSL by a payment gateway
			if (get_query_var('checkoutstep')) {
				foreach ((array)$mp_gateway_active_plugins as $plugin) {
					if ($plugin->force_ssl) {
					  if ( !is_ssl() ) {
							wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
							exit();
					  }
		      }
				}
			}

			//force login if required
			if (!is_user_logged_in() && $settings['force_login'] && get_query_var('checkoutstep')) {
        wp_redirect( wp_login_url( mp_checkout_step_url( get_query_var('checkoutstep') ) ) );
				exit();
			}

      //check for custom theme template
      $templates = array("mp_cart.php");

      //if custom template exists load it
      if ($this->checkout_template = locate_template($templates)) {
        add_filter( 'template_include', array(&$this, 'custom_checkout_template') );
        add_filter( 'single_post_title', array(&$this, 'page_title_output'), 99 );
				add_filter( 'bp_page_title', array(&$this, 'page_title_output'), 99 );
				add_filter( 'wp_title', array(&$this, 'wp_title_output'), 99, 3 );
      } else {
        //otherwise load the page template and use our own theme
        add_filter( 'single_post_title', array(&$this, 'page_title_output'), 99 );
        add_filter( 'the_title', array(&$this, 'page_title_output'), 99 );
				add_filter( 'bp_page_title', array(&$this, 'page_title_output'), 99 );
				add_filter( 'wp_title', array(&$this, 'wp_title_output'), 99, 3 );
        add_filter( 'the_content', array(&$this, 'checkout_theme'), 99 );
      }

      $wp_query->is_page = 1;
      $wp_query->is_singular = 1;
      $wp_query->is_404 = null;
      $wp_query->post_count = 1;

      $this->is_shop_page = true;
    }

    //load proper theme for order status page
    if ($wp_query->query_vars['pagename'] == 'orderstatus') {

      //check for custom theme template
      $templates = array("mp_orderstatus.php");

      //if custom template exists load it
      if ($this->orderstatus_template = locate_template($templates)) {
        add_filter( 'template_include', array(&$this, 'custom_orderstatus_template') );
        add_filter( 'single_post_title', array(&$this, 'page_title_output'), 99 );
				add_filter( 'bp_page_title', array(&$this, 'page_title_output'), 99 );
				add_filter( 'wp_title', array(&$this, 'wp_title_output'), 99, 3 );
      } else {
        //otherwise load the page template and use our own theme
        add_filter( 'single_post_title', array(&$this, 'page_title_output'), 99 );
        add_filter( 'the_title', array(&$this, 'page_title_output'), 99 );
				add_filter( 'bp_page_title', array(&$this, 'page_title_output'), 99 );
				add_filter( 'wp_title', array(&$this, 'wp_title_output'), 99, 3 );
        add_filter( 'the_content', array(&$this, 'orderstatus_theme'), 99 );
      }

      $wp_query->is_page = 1;
      $wp_query->is_singular = 1;
      $wp_query->is_404 = false;
      $wp_query->post_count = 1;

      $this->is_shop_page = true;
    }

    //load proper theme for product listings
    if ($wp_query->query_vars['pagename'] == 'product_list') {

      //check for custom theme template
      $templates = array("mp_productlist.php");

      //if custom template exists load it
      if ($this->product_list_template = locate_template($templates)) {

        //call a custom query posts for this listing
        //setup pagination
        if ($settings['paginate']) {
          //figure out perpage
          $paginate_query = '&posts_per_page='.$settings['per_page'];

          //figure out page
          if ($wp_query->query_vars['paged'])
            $paginate_query .= '&paged='.intval($wp_query->query_vars['paged']);
        } else {
          $paginate_query = '&nopaging=true';
        }

        //get order by
        if ($settings['order_by'] == 'price')
          $order_by_query = '&meta_key=mp_price&orderby=mp_price';
        else if ($settings['order_by'] == 'sales')
          $order_by_query = '&meta_key=mp_sales_count&orderby=mp_sales_count';
        else
          $order_by_query = '&orderby='.$settings['order_by'];

        //get order direction
        $order_query = '&order='.$settings['order'];

        //The Query
        query_posts('post_type=product' . $paginate_query . $order_by_query . $order_query);

        add_filter( 'template_include', array(&$this, 'custom_product_list_template') );
        add_filter( 'single_post_title', array(&$this, 'page_title_output'), 99 );
      } else {
        //otherwise load the page template and use our own theme
        add_filter( 'single_post_title', array(&$this, 'page_title_output'), 99 );
        add_filter( 'the_title', array(&$this, 'page_title_output'), 99 );
        add_filter( 'the_content', array(&$this, 'product_list_theme'), 99 );
        add_filter( 'the_excerpt', array(&$this, 'product_list_theme'), 99 );
      }

      $wp_query->is_page = 1;
      //$wp_query->is_singular = 1;
      $wp_query->is_404 = null;
      $wp_query->post_count = 1;

      $this->is_shop_page = true;
    }

    //load proper theme for product category or tag listings
    if ($wp_query->query_vars['taxonomy'] == 'product_category' || $wp_query->query_vars['taxonomy'] == 'product_tag') {
      $templates = array();

      if ($wp_query->query_vars['taxonomy'] == 'product_category') {

        $cat_name = get_query_var('product_category');
        $cat_id = absint( $wp_query->get_queried_object_id() );
      	if ( $cat_name )
      		$templates[] = "mp_category-$cat_name.php";
      	if ( $cat_id )
      		$templates[] = "mp_category-$cat_id.php";
      	$templates[] = "mp_category.php";

      } else if ($wp_query->query_vars['taxonomy'] == 'product_tag') {

        $tag_name = get_query_var('product_tag');
        $tag_id = absint( $wp_query->get_queried_object_id() );
      	if ( $tag_name )
      		$templates[] = "mp_tag-$tag_name.php";
      	if ( $tag_id )
      		$templates[] = "mp_tag-$tag_id.php";
      	$templates[] = "mp_tag.php";

      }

      //defaults
      $templates[] = "mp_taxonomy.php";
      $templates[] = "mp_productlist.php";

      //if custom template exists load it
      if ($this->product_taxonomy_template = locate_template($templates)) {

        //call a custom query posts for this listing
        $taxonomy_query = '&' . $wp_query->query_vars['taxonomy'] . '=' . get_query_var($wp_query->query_vars['taxonomy']);

        //setup pagination
        if ($settings['paginate']) {
          //figure out perpage
          $paginate_query = '&posts_per_page='.$settings['per_page'];

          //figure out page
          if ($wp_query->query_vars['paged'])
            $paginate_query .= '&paged='.intval($wp_query->query_vars['paged']);
        } else {
          $paginate_query = '&nopaging=true';
        }

        //get order by
        if ($settings['order_by'] == 'price')
          $order_by_query = '&meta_key=mp_price&orderby=mp_price';
        else if ($settings['order_by'] == 'sales')
          $order_by_query = '&meta_key=mp_sales_count&orderby=mp_sales_count';
        else
          $order_by_query = '&orderby='.$settings['order_by'];

        //get order direction
        $order_query = '&order='.$settings['order'];

        //The Query
        query_posts('post_type=product' . $taxonomy_query . $paginate_query . $order_by_query . $order_query);

        add_filter( 'template_include', array(&$this, 'custom_product_taxonomy_template'));
        add_filter( 'single_post_title', array(&$this, 'page_title_output'), 99 );
      } else {
        //otherwise load the page template and use our own list theme. We don't use theme's taxonomy as not enough control
        $wp_query->is_page = 1;
        //$wp_query->is_singular = 1;
        $wp_query->is_404 = null;
        $wp_query->post_count = 1;
        add_filter( 'the_title', array(&$this, 'page_title_output'), 99, 2 );
        add_filter( 'the_content', array(&$this, 'product_taxonomy_list_theme'), 99 );
        add_filter( 'the_excerpt', array(&$this, 'product_taxonomy_list_theme'), 99 );
      }

      $this->is_shop_page = true;
    }

    //load shop specific items
    if ($this->is_shop_page) {
      //fixes a nasty bug in BP theme's functions.php file which always loads the activity stream if not a normal page
      remove_all_filters('page_template');

      //prevents 404 for virtual pages
      status_header( 200 );
    }
  }

  //loads the selected theme css files
  function load_store_theme() {
    $settings = get_option('mp_settings');

    if ( $settings['store_theme'] == 'none' || current_theme_supports('mp_style') )
      return;
    else
      wp_enqueue_style( 'mp-store-theme', $this->plugin_url . 'themes/' . $settings['store_theme'] . '.css', false, $this->version );

  }

  //list store themes in dropdown
  function store_themes_select() {
    $settings = get_option('mp_settings');

    //get theme dir
    $theme_dir = $this->plugin_dir . 'themes/';

    //scan directory for theme css files
    $theme_list = array();
    if ($handle = @opendir($theme_dir)) {
      while (false !== ($file = readdir($handle))) {
        if (($pos = strrpos($file, '.css')) !== false) {
          $value = substr($file, 0, $pos);
          if (is_readable("$theme_dir/$file")) {
            $theme_data = get_file_data( "$theme_dir/$file", array('name' => 'MarketPress Theme') );
            if (is_array($theme_data))
              $theme_list[$value] = $theme_data['name'];
          }
        }
      }

      @closedir($handle);
    }

    //sort the themes
    asort($theme_list);

    //check network permissions
    if (is_multisite()) {
      $allowed_list = array();
      $network_settings = get_site_option( 'mp_network_settings' );

      foreach ($theme_list as $value => $name) {
        if ($network_settings['allowed_themes'][$value] == 'full')
          $allowed_list[$value] = $name;
        else if (function_exists('is_supporter') && is_supporter() && $network_settings['allowed_themes'][$value] == 'supporter')
          $allowed_list[$value] = $name;
        else if (is_super_admin()) //super admins can access all installed themes
          $allowed_list[$value] = $name;
      }
      $theme_list = $allowed_list;
    }

    echo '<select name="mp[store_theme]">';
    foreach ($theme_list as $value => $name) {
      ?><option value="<?php echo $value ?>"<?php selected($settings['store_theme'], $value) ?>><?php echo $name ?></option><?php
		}
    ?>
      <option value="none"<?php selected($settings['store_theme'], 'none') ?>><?php _e('None - Custom theme template', 'mp') ?></option>
    </select>
    <?php
  }

  //filter the custom single product template
  function custom_product_template($template) {
    return $this->product_template;
  }

  //filter the custom store template
  function custom_store_template($template) {
    return $this->store_template;
  }

  //filter the custom checkout template
  function custom_checkout_template($template) {
    return $this->checkout_template;
  }

  //filter the custom orderstatus template
  function custom_orderstatus_template($template) {
    return $this->orderstatus_template;
  }

  //filter the custom product taxonomy template
  function custom_product_taxonomy_template($template) {
    return $this->product_taxonomy_template;
  }

  //filter the custom product list template
  function custom_product_list_template($template) {
    return $this->product_list_template;
  }

  //adds our links to theme nav menus using wp_list_pages()
  function filter_list_pages($list, $args) {

    if ($args['depth'] == 1)
      return $list;

    $settings = get_option('mp_settings');

    $temp_break = strpos($list, mp_store_link(false, true) . '"');

    //if we can't find the page for some reason skip
    if ($temp_break === false)
      return $list;

    $break = strpos($list, '</a>', $temp_break) + 4;

    $nav = substr($list, 0, $break);

    if ( !$settings['disable_cart'] ) {
      $nav .= '<ul class="children"><li class="page_item'. ((get_query_var('pagename') == 'product_list') ? ' current_page_item' : '') . '"><a href="' . mp_products_link(false, true) . '" title="' . __('Products', 'mp') . '">' . __('Products', 'mp') . '</a></li>';
			$nav .= '<li class="page_item'. ((get_query_var('pagename') == 'cart') ? ' current_page_item' : '') . '"><a href="' . mp_cart_link(false, true) . '" title="' . __('Shopping Cart', 'mp') . '">' . __('Shopping Cart', 'mp') . '</a></li>';
      $nav .= '<li class="page_item'. ((get_query_var('pagename') == 'orderstatus') ? ' current_page_item' : '') . '"><a href="' . mp_orderstatus_link(false, true) . '" title="' . __('Order Status', 'mp') . '">' . __('Order Status', 'mp') . '</a></li>
</ul>
';
    } else {
      $nav .= '
<ul>
	<li class="page_item'. ((get_query_var('pagename') == 'product_list') ? ' current_page_item' : '') . '"><a href="' . mp_products_link(false, true) . '" title="' . __('Products', 'mp') . '">' . __('Products', 'mp') . '</a></li>
</ul>
';
    }
    $nav .= substr($list, $break);

    return $nav;
  }

  //adds our links to custom theme nav menus using wp_nav_menu()
  function filter_nav_menu($list, $args = array()) {
    $settings = get_option('mp_settings');

    if ($args->depth == 1)
      return $list;

		//find store page
		$store_url = mp_store_link(false, true);
		$store_page = get_option('mp_store_page');
		foreach($list as $menu_item) {
			if ($menu_item->object_id == $store_page || $menu_item->url == $store_url) {
				$store_object = $menu_item;
				break;
			}
		}

		if ($store_object) {
		  $obj_products = clone $store_object;
			$obj_products->title = __('Products', 'mp');
			$obj_products->menu_item_parent = $store_object->ID;
			$obj_products->ID = '99999999999';
			$obj_products->db_id = '99999999999';
			$obj_products->post_name = '99999999999';
			$obj_products->url = mp_products_link(false, true);
			$obj_products->current = (get_query_var('pagename') == 'product_list') ? true : false;
			$obj_products->current_item_ancestor = (get_query_var('pagename') == 'product_list') ? true : false;
			$list[] = $obj_products;

		  //if cart disabled return only the products menu item
			if ($settings['disable_cart'])
			  return $list;

		  $obj_cart = clone $store_object;
			$obj_cart->title = __('Shopping Cart', 'mp');
			$obj_cart->menu_item_parent = $store_object->ID;
			$obj_cart->ID = '99999999999';
			$obj_cart->db_id = '99999999999';
			$obj_cart->post_name = '99999999999';
			$obj_cart->url = mp_cart_link(false, true);
			$obj_cart->current = (get_query_var('pagename') == 'cart') ? true : false;
			$obj_cart->current_item_ancestor = (get_query_var('pagename') == 'cart') ? true : false;
			$list[] = $obj_cart;

			$obj_order = clone $store_object;
			$obj_order->title = __('Order Status', 'mp');
			$obj_order->menu_item_parent = $store_object->ID;
			$obj_order->ID = '99999999999';
			$obj_order->db_id = '99999999999';
			$obj_order->post_name = '99999999999';
			$obj_order->url = mp_orderstatus_link(false, true);
			$obj_order->current = (get_query_var('pagename') == 'orderstatus') ? true : false;
			$obj_order->current_item_ancestor = (get_query_var('pagename') == 'orderstatus') ? true : false;
			$list[] = $obj_order;
		}

		return $list;
  }

  function wp_title_output($title, $sep, $seplocation) {
    // Determines position of the separator and direction of the breadcrumb
		if ( 'right' == $seplocation )
			return $this->page_title_output($title, true) . " $sep ";
		else
		  return " $sep " . $this->page_title_output($title, true);
  }

  //filters the titles for our custom pages
  function page_title_output($title, $id = false) {
    global $wp_query;

    //filter out nav titles
    if (!empty($title) && $id === false)
      return $title;

    //taxonomy pages
    if (($wp_query->query_vars['taxonomy'] == 'product_category' || $wp_query->query_vars['taxonomy'] == 'product_tag') && $wp_query->post->ID == $id) {
      if ($wp_query->query_vars['taxonomy'] == 'product_category') {
        $term = get_term_by('slug', get_query_var('product_category'), 'product_category');
        return sprintf( __('Product Category: %s', 'mp'), $term->name );
      } else if ($wp_query->query_vars['taxonomy'] == 'product_tag') {
        $term = get_term_by('slug', get_query_var('product_tag'), 'product_tag');
        return sprintf( __('Product Tag: %s', 'mp'), $term->name );
      }
    }

    switch ($wp_query->query_vars['pagename']) {
      case 'cart':
        if ($wp_query->query_vars['checkoutstep'] == 'shipping')
          return __('Shipping Information', 'mp');
        else if ($wp_query->query_vars['checkoutstep'] == 'checkout')
          return __('Payment Information', 'mp');
        else if ($wp_query->query_vars['checkoutstep'] == 'confirm-checkout')
          return __('Confirm Your Purchase', 'mp');
        else if ($wp_query->query_vars['checkoutstep'] == 'confirmation')
          return __('Order Confirmation', 'mp');
        else
          return __('Your Shopping Cart', 'mp');
        break;

      case 'orderstatus':
        return __('Track Your Order', 'mp');
        break;

      case 'product_list':
        return __('Products', 'mp');
        break;

      default:
        return $title;
    }
  }

  //this is the default theme added to single product listings
  function product_theme($content) {
    global $post;

    //don't filter outside of the loop
  	if ( !in_the_loop() )
		  return $content;

    //add thumbnail
    $content = mp_product_image( false, 'single' ) . $content;


    $content .= '<div class="mp_product_meta">';
    $content .= mp_product_price(false);
    $content .= mp_buy_button(false, 'single');
    $content .= '</div>';

	$content .= mp_category_list($post->ID, '<div class="mp_product_categories">' . __( 'Categorized in ', 'mp' ), ', ', '</div>');

    //$content .= mp_tag_list($post->ID, '<div class="mp_product_tags">', ', ', '</div>');

    return $content;
  }

  //this is the default theme added to the checkout page
  function store_theme($content) {
    //don't filter outside of the loop
  	if ( !in_the_loop() )
		  return $content;

    return $content;
  }

  //this is the default theme added to the checkout page
  function checkout_theme($content) {
    global $wp_query;

   	//don't filter outside of the loop
  	if ( !in_the_loop() )
		  return $content;

    $content = mp_show_cart('checkout', $wp_query->query_vars['checkoutstep'], false);

    return $content;
  }

  //this is the default theme added to the order status page
  function orderstatus_theme($content) {
    //don't filter outside of the loop
  	if ( !in_the_loop() )
		  return $content;

    mp_order_status();
    return $content;
  }

  //this is the default theme added to product listings
  function product_list_theme($content) {
		//don't filter outside of the loop
  	if ( !in_the_loop() )
		  return $content;

		$settings = get_option('mp_settings');
    $content .= $settings['msg']['product_list'];
    $content .= mp_list_products(false);
    $content .= get_posts_nav_link();

    return $content;
  }

  //this is the default theme added to product taxonomies
  function product_taxonomy_list_theme($content) {
   	//don't filter outside of the loop
  	if ( !in_the_loop() )
		  return $content;

		$settings = get_option('mp_settings');
    $content = $settings['msg']['product_list'];
    $content .= mp_list_products(false);
    $content .= get_posts_nav_link();

    return $content;
  }

  //adds the "filter by product category" to the edit products screen
  function edit_products_filter() {
    global $current_screen;

    if ( $current_screen->id == 'edit-product' ) {
    	$dropdown_options = array('taxonomy' => 'product_category', 'show_option_all' => __('View all categories'), 'hide_empty' => 0, 'hierarchical' => 1,
    		'show_count' => 0, 'orderby' => 'name', 'name' => 'product_category', 'selected' => $_GET['product_category']);
    	wp_dropdown_categories($dropdown_options);
    }
  }

  //adjusts the query vars on the products/order management screens.
  function handle_edit_screen_filter($request) {
    global $current_screen;

    if ( $current_screen->id == 'edit-product' ) {
      //Switches the product_category ids to slugs as you can't query custom taxonomys with ids
      $cat = get_term_by('id', $request['product_category'], 'product_category');
      $request['product_category'] = $cat->slug;
    } else if ( $current_screen->id == 'product_page_marketpress-orders' && !isset($_GET['post_status']) ) {
      //set the post status when on "All" to everything but closed
      $request['post_status'] = 'order_received,order_paid,order_shipped';
    }

    return $request;
  }

  //adds our custom column headers to edit products screen
  function edit_products_columns($old_columns)	{
    global $post_status;

		$columns['cb'] = '<input type="checkbox" />';
		$columns['thumbnail'] = __('Thumbnail', 'mp');
		$columns['title'] = __('Product Name', 'mp');
		$columns['variations'] = __('Variations', 'mp');
		$columns['sku'] = __('SKU', 'mp');
		$columns['pricing'] = __('Price', 'mp');
		if (!$settings['disable_cart']) {
  		$columns['stock'] = __('Stock', 'mp');
  		$columns['sales'] = __('Sales', 'mp');
    }
		$columns['product_categories'] = __('Product Categories', 'mp');
		$columns['product_tags'] = __('Product Tags', 'mp');


    /*
    if ( !in_array( $post_status, array('pending', 'draft', 'future') ) )
		  $columns['reviews'] = __('Reviews', 'mp');
    //*/

		return $columns;
	}

  //adds our custom column content
	function edit_products_custom_columns($column) {
		global $post;
		$settings = get_option('mp_settings');
		$meta = get_post_custom();
    //unserialize
    foreach ($meta as $key => $val) {
		  $meta[$key] = maybe_unserialize($val[0]);
		  if (!is_array($meta[$key]) && $key != "mp_is_sale" && $key != "mp_track_inventory" && $key != "mp_product_link")
		    $meta[$key] = array($meta[$key]);
		}

		switch ($column) {
			case "thumbnail":
        echo '<a href="' . get_edit_post_link() . '" title="' . __('Edit &raquo;') . '">';
				the_post_thumbnail(array(50,50), array('title' => ''));
				echo '</a>';
				break;

			case "variations":
			  if (is_array($meta["mp_var_name"]) && count($meta["mp_var_name"]) > 1) {
					foreach ($meta["mp_var_name"] as $value) {
            echo esc_attr($value) . '<br />';
					}
				} else {
					_e('N/A', 'mp');
				}
			  break;

      case "sku":
			  if (is_array($meta["mp_var_name"])) {
					foreach ((array)$meta["mp_sku"] as $value) {
	          echo esc_attr($value) . '<br />';
					}
        } else {
					_e('N/A', 'mp');
				}
				break;

      case "pricing":
        if (is_array($meta["mp_price"])) {
	        foreach ($meta["mp_price"] as $key => $value) {
						if ($meta["mp_is_sale"] && $meta["mp_sale_price"][$key]) {
		          echo '<del>'.$this->format_currency('', $value).'</del> ';
		          echo $this->format_currency('', $meta["mp_sale_price"][$key]) . '<br />';
		        } else {
		          echo $this->format_currency('', $value) . '<br />';
		        }
	        }
        } else {
					echo $this->format_currency('', 0);
				}
				break;

      case "sales":
				echo number_format_i18n(($meta["mp_sales_count"][0]) ? $meta["mp_sales_count"][0] : 0);
				break;

      case "stock":
				if ($meta["mp_track_inventory"]) {
				  foreach ((array)$meta["mp_inventory"] as $value) {
	          $inventory = ($value) ? $value : 0;
	          if ($inventory == 0)
	            $class = 'mp-inv-out';
	          else if ($inventory <= $settings['inventory_threshhold'])
	            $class = 'mp-inv-warn';
	          else
	            $class = 'mp-inv-full';

	          echo '<span class="' . $class . '">' . number_format_i18n($inventory) . '</span><br />';
          }
        } else {
          _e('N/A', 'mp');
        }
				break;

			case "product_categories":
        echo mp_category_list();
				break;

      case "product_tags":
        echo mp_tag_list();
				break;

      case "reviews":
        echo '<div class="post-com-count-wrapper">
		          <a href="edit-comments.php?p=913" title="0 pending" class="post-com-count"><span class="comment-count">0</span></a>
              </div>';
        break;
		}
	}

	//adds our custom column headers
  function manage_orders_columns($old_columns)	{
    global $post_status;

		$columns['cb'] = '<input type="checkbox" />';
		$columns['mp_orders_status'] = __('Status', 'mp');
		$columns['mp_orders_id'] = __('Order ID', 'mp');
		$columns['mp_orders_date'] = __('Order Date', 'mp');
		$columns['mp_orders_name'] = __('From', 'mp');
		$columns['mp_orders_items'] = __('Items', 'mp');
		$columns['mp_orders_shipping'] = __('Shipping', 'mp');
		$columns['mp_orders_tax'] = __('Tax', 'mp');
		$columns['mp_orders_discount'] = __('Discount', 'mp');
		$columns['mp_orders_total'] = __('Total', 'mp');

		return $columns;
	}

  //adds our custom column content
	function manage_orders_custom_columns($column) {
		global $post;
		$settings = get_option('mp_settings');
		$meta = get_post_custom();
    //unserialize
    foreach ($meta as $key => $val)
		  $meta[$key] = array_map('maybe_unserialize', $val);

		switch ($column) {

      case "mp_orders_status":
				if ($post->post_status == 'order_received')
          $text = __('Received', 'mp');
        else if ($post->post_status == 'order_paid')
          $text = __('Paid', 'mp');
        else if ($post->post_status == 'order_shipped')
          $text = __('Shipped', 'mp');
        else if ($post->post_status == 'order_closed')
          $text = __('Closed', 'mp');

        ?><a class="mp_order_status" href="edit.php?post_type=product&page=marketpress-orders&order_id=<?php echo $post->ID; ?>" title="<?php echo __('View Order Details', 'mp'); ?>"><?php echo $text ?></a><?php
				break;

      case "mp_orders_date":
        $t_time = get_the_time(__('Y/m/d g:i:s A'));
				$m_time = $post->post_date;
				$time = get_post_time('G', true, $post);

				$time_diff = time() - $time;

				if ( $time_diff > 0 && $time_diff < 24*60*60 )
					$h_time = sprintf( __('%s ago'), human_time_diff( $time ) );
				else
					$h_time = mysql2date(__('Y/m/d'), $m_time);
        echo '<abbr title="' . $t_time . '">' . $h_time . '</abbr>';
				break;

      case "mp_orders_id":
        $title = _draft_or_post_title();
        ?>
        <strong><a class="row-title" href="edit.php?post_type=product&page=marketpress-orders&order_id=<?php echo $post->ID; ?>" title="<?php echo esc_attr(sprintf(__('View &#8220;%s&#8221;', 'mp'), $title)); ?>"><?php echo $title ?></a></strong>
        <?php
        $actions = array();
        if ($post->post_status == 'order_received') {
          $actions['paid'] = "<a title='" . esc_attr(__('Mark as Paid', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=paid&amp;post=' . $post->ID), 'update-order-status' ) . "'>" . __('Paid', 'mp') . "</a>";
          $actions['shipped'] = "<a title='" . esc_attr(__('Mark as Shipped', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=shipped&amp;post=' . $post->ID), 'update-order-status' ) . "'>" . __('Shipped', 'mp') . "</a>";
          $actions['closed'] = "<a title='" . esc_attr(__('Mark as Closed', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=closed&amp;post=' . $post->ID), 'update-order-status' ) . "'>" . __('Closed', 'mp') . "</a>";
        } else if ($post->post_status == 'order_paid') {
          $actions['shipped'] = "<a title='" . esc_attr(__('Mark as Shipped', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=shipped&amp;post=' . $post->ID), 'update-order-status' ) . "'>" . __('Shipped', 'mp') . "</a>";
          $actions['closed'] = "<a title='" . esc_attr(__('Mark as Closed', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=closed&amp;post=' . $post->ID), 'update-order-status' ) . "'>" . __('Closed', 'mp') . "</a>";
        } else if ($post->post_status == 'order_shipped') {
          $actions['closed'] = "<a title='" . esc_attr(__('Mark as Closed', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=closed&amp;post=' . $post->ID), 'update-order-status' ) . "'>" . __('Closed', 'mp') . "</a>";
        } else if ($post->post_status == 'order_closed') {
          $actions['received'] = "<a title='" . esc_attr(__('Mark as Received', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=received&amp;post=' . $post->ID), 'update-order-status' ) . "'>" . __('Received', 'mp') . "</a>";
          $actions['paid'] = "<a title='" . esc_attr(__('Mark as Paid', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=paid&amp;post=' . $post->ID), 'update-order-status' ) . "'>" . __('Paid', 'mp') . "</a>";
          $actions['shipped'] = "<a title='" . esc_attr(__('Mark as Shipped', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=shipped&amp;post=' . $post->ID), 'update-order-status' ) . "'>" . __('Shipped', 'mp') . "</a>";
        }

        $action_count = count($actions);
  			$i = 0;
  			echo '<div class="row-actions">';
  			foreach ( $actions as $action => $link ) {
  				++$i;
  				( $i == $action_count ) ? $sep = '' : $sep = ' | ';
  				echo "<span class='$action'>$link$sep</span>";
  			}
  			echo '</div>';
        break;

      case "mp_orders_name":
				echo esc_attr($meta["mp_shipping_info"][0]['name']) . ' (<a href="mailto:' . urlencode($meta["mp_shipping_info"][0]['name']) . ' &lt;' . esc_attr($meta["mp_shipping_info"][0]['email']) . '&gt;?subject=' . urlencode(sprintf(__('Regarding Your Order (%s)', 'mp'), $post->post_title)) . '">' . esc_attr($meta["mp_shipping_info"][0]['email']) . '</a>)';
				break;

      case "mp_orders_items":
				echo number_format_i18n($meta["mp_order_items"][0]);
				break;

      case "mp_orders_shipping":
				echo $this->format_currency('', $meta["mp_shipping_total"][0]);
				break;

      case "mp_orders_tax":
				echo $this->format_currency('', $meta["mp_tax_total"][0]);
				break;

      case "mp_orders_discount":
        if ($meta["mp_discount_info"][0])
				  echo $meta["mp_discount_info"][0]['discount'];
        else
          _e('N/A', 'mp');
				break;

      case "mp_orders_total":
				echo $this->format_currency('', $meta["mp_order_total"][0]);
				break;

		}
	}

  //adds our custom meta boxes the the product edit screen
  function meta_boxes() {
    $settings = get_option('mp_settings');

    add_meta_box('mp-meta-details', __('Product Details', 'mp'), array(&$this, 'meta_details'), 'product', 'normal', 'high');

    //only add these boxes if orders are enabled
    if (!$settings['disable_cart']) {

      //only display metabox if shipping plugin ties into it
      if ( has_action('mp_shipping_metabox') )
        add_meta_box('mp-meta-shipping', __('Shipping', 'mp'), array(&$this, 'meta_shipping'), 'product', 'normal', 'high');

			//for product downloads
      add_meta_box('mp-meta-download', __('Product Download', 'mp'), array(&$this, 'meta_download'), 'product', 'normal', 'high');
    }
  }

  //Save our post meta when a product is created or updated
	function save_product_meta($post_id, $post = null) {
    //skip quick edit
    if ( defined('DOING_AJAX') )
      return;

		if ( $post->post_type == "product" && isset( $_POST['mp_product_meta'] ) ) {
      $meta = get_post_custom($post_id);
      foreach ($meta as $key => $val) {
			  $meta[$key] = maybe_unserialize($val[0]);
			  if (!is_array($meta[$key]) && $key != "mp_is_sale" && $key != "mp_track_inventory" && $key != "mp_product_link")
			    $meta[$key] = array($meta[$key]);
			}

      //price function
      $func_curr = '$price = round($price, 2);return ($price) ? $price : 0;';

      //sku function
      $func_sku = 'return preg_replace("/[^a-zA-Z0-9_-]/", "", $value);';

      update_post_meta($post_id, 'mp_var_name', $_POST['mp_var_name']);
      update_post_meta($post_id, 'mp_sku', array_map(create_function('$value', $func_sku), $_POST['mp_sku']));
      update_post_meta($post_id, 'mp_price', array_map(create_function('$price', $func_curr), $_POST['mp_price']));
      update_post_meta($post_id, 'mp_is_sale', isset($_POST['mp_is_sale']) ? 1 : 0);
      update_post_meta($post_id, 'mp_sale_price', array_map(create_function('$price', $func_curr), $_POST['mp_sale_price']));
      update_post_meta($post_id, 'mp_track_inventory', isset($_POST['mp_track_inventory']) ? 1 : 0);
      update_post_meta($post_id, 'mp_inventory', array_map('intval', (array)$_POST['mp_inventory']));

			//save true first variation price for sorting
			if ( isset($_POST['mp_is_sale']) && round($_POST['mp_sale_price'][0], 2) )
				$sort_price = round($_POST['mp_sale_price'][0], 2);
			else
				$sort_price = round($_POST['mp_price'][0], 2);
      update_post_meta($post_id, 'mp_price_sort', $sort_price);

			//if changing delete flag so emails will be sent again
      if ( $_POST['mp_inventory'] != $meta['mp_inventory'] )
        delete_post_meta($product_id, 'mp_stock_email_sent');

      update_post_meta( $post_id, 'mp_product_link', esc_url_raw($_POST['mp_product_link']) );

      //set sales count to zero if none set
      $sale_count = ($meta["mp_sales_count"][0]) ? $meta["mp_sales_count"][0] : 0;
      update_post_meta($post_id, 'mp_sales_count', $sale_count);

      //for shipping plugins to save their meta values
      $mp_shipping = maybe_unserialize($meta["mp_shipping"][0]);
      if ( !is_array($mp_shipping) )
        $mp_shipping = array();

      update_post_meta( $post_id, 'mp_shipping', apply_filters('mp_save_shipping_meta', $mp_shipping) );

      //download url
      update_post_meta( $post_id, 'mp_file', esc_url_raw($_POST['mp_file']) );

      //for any other plugin to hook into
      do_action( 'mp_save_product_meta', $post_id, $meta );
		}
	}

  //The Product Details meta box
  function meta_details() {
    global $post;
    $settings = get_option('mp_settings');
		$meta = get_post_custom($post->ID);
  	//unserialize
    foreach ($meta as $key => $val) {
		  $meta[$key] = maybe_unserialize($val[0]);
		  if (!is_array($meta[$key]) && $key != "mp_is_sale" && $key != "mp_track_inventory" && $key != "mp_product_link" && $key != "mp_file")
		    $meta[$key] = array($meta[$key]);
		}
    ?>
    <input type="hidden" name="mp_product_meta" value="1" />
    <table class="widefat" id="mp_product_variations_table">
			<thead>
				<tr>
					<th scope="col" class="mp_var_col"><?php _e('Variation Name', 'mp') ?></th>
					<th scope="col" class="mp_sku_col" title="<?php _e('Stock Keeping Unit - Your custom Product ID number', 'mp'); ?>"><?php _e('SKU', 'mp') ?></th>
					<th scope="col" class="mp_price_col"><?php _e('Price', 'mp') ?></th>
					<th scope="col" class="mp_sale_col"><label title="<?php _e('When checked these override the normal price.', 'mp'); ?>"><input type="checkbox" id="mp_is_sale" name="mp_is_sale" value="1"<?php checked($meta["mp_is_sale"], '1'); ?> /> <?php _e('Sale Price', 'mp') ?></label></th>
					<th scope="col" class="mp_inv_col"><label title="<?php _e('When checked inventory tracking will be enabled.', 'mp'); ?>"><input type="checkbox" id="mp_track_inventory" name="mp_track_inventory" value="1"<?php checked($meta["mp_track_inventory"], '1'); ?> /> <?php _e('Inventory', 'mp') ?></label></th>
					<th scope="col" class="mp_var_remove"></th>
				</tr>
			</thead>
			<tbody>
			<?php
			  if ($meta["mp_price"]) {
			    //if download enabled only show first variation
			    $meta["mp_price"] = (empty($meta["mp_file"]) && empty($meta["mp_product_link"])) ? $meta["mp_price"] : array($meta["mp_price"][0]);
			    $count = 1;
			    $last = count($meta["mp_price"]);
	        foreach ($meta["mp_price"] as $key => $price) {
		        ?>
						<tr class="variation">
							<td class="mp_var_col"><input type="text" name="mp_var_name[]" value="<?php echo esc_attr($meta["mp_var_name"][$key]); ?>" /></td>
							<td class="mp_sku_col"><input type="text" name="mp_sku[]" value="<?php echo esc_attr($meta["mp_sku"][$key]); ?>" /></td>
							<td class="mp_price_col"><?php echo $this->format_currency(); ?><input type="text" name="mp_price[]" value="<?php echo ($meta["mp_price"][$key]) ? $this->display_currency($meta["mp_price"][$key]) : '0.00'; ?>" /></td>
							<td class="mp_sale_col"><?php echo $this->format_currency(); ?><input type="text" name="mp_sale_price[]" value="<?php echo ($meta["mp_sale_price"][$key]) ? $this->display_currency($meta["mp_sale_price"][$key]) : $this->display_currency($meta["mp_price"][$key]); ?>" disabled="disabled" /></td>
              <td class="mp_inv_col"><input type="text" name="mp_inventory[]" value="<?php echo intval($meta["mp_inventory"][$key]); ?>" disabled="disabled" /></td>
							<td class="mp_var_remove">
							<?php if ($count == $last) { ?><a href="#mp_product_variations_table" title="<?php _e('Remove Variation', 'mp'); ?>">x</a><?php } ?>
							</td>
						</tr>
						<?php
						$count++;
					}
	      } else {
       		?>
					<tr class="variation">
						<td class="mp_var_col"><input type="text" name="mp_var_name[]" value="" /></td>
						<td class="mp_sku_col"><input type="text" name="mp_sku[]" value="" /></td>
						<td class="mp_price_col"><?php echo $this->format_currency(); ?><input type="text" name="mp_price[]" value="0.00" /></td>
						<td class="mp_sale_col"><?php echo $this->format_currency(); ?><input type="text" name="mp_sale_price[]" value="0.00" disabled="disabled" /></td>
            <td class="mp_inv_col"><input type="text" name="mp_inventory[]" value="0" disabled="disabled" /></td>
						<td class="mp_var_remove"><a href="#mp_product_variations_table" title="<?php _e('Remove Variation', 'mp'); ?>">x</a></td>
					</tr>
					<?php
	      }
			?>
			</tbody>
		</table>
		<?php if (empty($meta["mp_file"]) && empty($meta["mp_product_link"])) { ?>
		<div id="mp_add_vars"><a href="#mp_product_variations_table"><?php _e('Add Variation', 'mp'); ?></a></div>
		<?php } else { ?>
    <span class="description" id="mp_variation_message"><?php _e('Product variations are not allowed for Downloadable or Externally Linked products.', 'mp') ?></span>
    <?php } ?>

    <div id="mp_product_link_div">
      <label title="<?php _e('Some examples are linking to a song/album in iTunes, or linking to a product on another site with your own affiliate link.', 'mp'); ?>"><?php _e('External Link', 'mp'); ?>:<br /><small><?php _e('When set this overrides the purchase button with a link to this URL.', 'mp'); ?></small><br />
      <input type="text" size="100" id="mp_product_link" name="mp_product_link" value="<?php echo esc_url($meta["mp_product_link"]); ?>" /></label>
    </div>

    <?php do_action( 'mp_details_metabox' ); ?>
    <div class="clear"></div>
    <?php
  }

  //The Shipping meta box
  function meta_shipping() {
    global $post;
    $settings = get_option('mp_settings');
		$meta = get_post_custom($post->ID);
		$mp_shipping = maybe_unserialize($meta["mp_shipping"][0]);

		//tie in for shipping plugins
    do_action( 'mp_shipping_metabox', $mp_shipping, $settings );
  }

  //The Product Download meta box
  function meta_download() {
    global $post;
    $settings = get_option('mp_settings');
		$meta = get_post_custom($post->ID);
    ?>
    <label><?php _e('File URL', 'mp'); ?>:<br /><input type="text" size="50" id="mp_file" class="mp_file" name="mp_file" value="<?php echo esc_attr($meta["mp_file"][0]); ?>" /></label>
    <input id="mp_upload_button" type="button" value="<?php _e('Upload File', 'mp'); ?>" /><br />
    <?php
    //display allowed filetypes if WPMU
    if (is_multisite()) {
      echo '<span class="description">Allowed Filetypes: '.implode(', ', explode(' ', get_site_option('upload_filetypes'))).'</span>';
      if (is_super_admin()) {
        echo '<p>Super Admin: You can change allowed filetypes for your network <a href="' . network_admin_url('settings.php#upload_filetypes') . '">here &raquo;</a></p>';
      }
    }

    do_action( 'mp_download_metabox' );
  }

  //returns the calculated price adjusted for sales, formatted or not
  function product_price($product_id, $variation = 0, $format = false) {

  	$meta = get_post_custom($product_id);
    //unserialize
    foreach ($meta as $key => $val) {
		  $meta[$key] = maybe_unserialize($val[0]);
		  if (!is_array($meta[$key]) && $key != "mp_is_sale" && $key != "mp_track_inventory" && $key != "mp_product_link")
		    $meta[$key] = array($meta[$key]);
		}

    if (is_array($meta["mp_price"])) {
			if ($meta["mp_is_sale"] && $meta["mp_sale_price"][$variation]) {
        $price = $meta["mp_sale_price"][$variation];
      } else {
        $price = $meta["mp_price"][$variation];
      }
    }

    $price = ($price) ? $price : 0;
    $price = $this->display_currency($price);

    $price = apply_filters( 'mp_product_price', $price, $product_id );

    if ($format)
      return $this->format_currency('', $price);
    else
      return $price;
  }

  //returns the calculated price for shipping. Returns False if shipping address is not available
  function shipping_price($format = false, $cart = false) {

		//grab cart for just this blog
		if (!$cart)
			$cart = $this->get_cart_contents();

    //get total after any coupons
    $totals = array();
    foreach ($cart as $product_id => $variations) {
			foreach ($variations as $variation => $data) {
			    $totals[] = $data['price'] * $data['quantity'];
			}
    }

    $total = array_sum($totals);

    $coupon_code = $this->get_coupon_code();
    if ( $coupon = $this->coupon_value($coupon_code, $total) )
      $total = $coupon['new_total'];

    //get address
    $meta = get_user_meta(get_current_user_id(), 'mp_shipping_info');
    $address1 = ($_SESSION['mp_shipping_info']['address1']) ? $_SESSION['mp_shipping_info']['address1'] : $meta['address1'];
    $address2 = ($_SESSION['mp_shipping_info']['address2']) ? $_SESSION['mp_shipping_info']['address2'] : $meta['address2'];
    $city = ($_SESSION['mp_shipping_info']['city']) ? $_SESSION['mp_shipping_info']['city'] : $meta['city'];
    $state = ($_SESSION['mp_shipping_info']['state']) ? $_SESSION['mp_shipping_info']['state'] : $meta['state'];
    $zip = ($_SESSION['mp_shipping_info']['zip']) ? $_SESSION['mp_shipping_info']['zip'] : $meta['zip'];
    $country = ($_SESSION['mp_shipping_info']['country']) ? $_SESSION['mp_shipping_info']['country'] : $meta['country'];

    //check required fields
    if ( empty($address1) || empty($city) || empty($zip) || empty($country) || !(is_array($cart) && count($cart)) )
      return false;

    //shipping plugins tie into this to calculate their shipping cost
    $price = apply_filters( 'mp_calculate_shipping', 0, $total, $cart, $address1, $address2, $city, $state, $zip, $country );

    //boot if shipping plugin didn't return at least 0
    if (empty($price))
      return false;

    if ($format)
      return $this->format_currency('', $price);
    else
      return $price;
  }

  //returns the calculated price for taxes based on a bunch of foreign tax laws.
  function tax_price($format = false, $cart = false) {
		$settings = get_option('mp_settings');

    //grab cart for just this blog
    if (!$cart)
			$cart = $this->get_cart_contents();

    //get address
    $meta = get_user_meta(get_current_user_id(), 'mp_shipping_info');

    if (!isset($meta['state'])) {
      $meta['state'] = '';
    }
    if (!isset($meta['country'])) {
      $meta['country'] = '';
    }

    $state = isset($_SESSION['mp_shipping_info']['state']) ? $_SESSION['mp_shipping_info']['state'] : $meta['state'];
    $country = isset($_SESSION['mp_shipping_info']['country']) ? $_SESSION['mp_shipping_info']['country'] : $meta['country'];

    //get total after any coupons
    $totals = array();
    foreach ($cart as $product_id => $variations) {
			foreach ($variations as $variation => $data) {
			  $totals[] = $data['price'] * $data['quantity'];
			}
    }

    $total = array_sum($totals);

    $coupon_code = $this->get_coupon_code();
    if ( $coupon = $this->coupon_value($coupon_code, $total) )
			$total = $coupon['new_total'];

    //add in shipping?
    if ( $settings['tax']['tax_shipping'] && ($shipping_price = $this->shipping_price()) )
			$total = $total + $shipping_price;

    //check required fields
    if ( empty($country) || !(is_array($cart) && count($cart)) || $total <= 0 ) {
      return false;
    }

    switch ($settings['base_country']) {
			case 'US':
			  //USA taxes are only for orders delivered inside the state
			  if ($country == 'US' && $state == $settings['base_province'])
			    $price = round($total * $settings['tax']['rate'], 2);
			  break;

			case 'CA':
			  //Canada tax is for all orders in country. We're assuming the rate is a combination of GST/PST/etc.
			  if ($country == 'CA')
			    $price = round($total * $settings['tax']['rate'], 2);
			  break;

			case 'AU':
			  //Australia taxes orders in country
			  if ($country == 'AU')
			    $price = round($total * $settings['tax']['rate'], 2);
			  break;

			default:
			  //EU countries charge VAT within the EU
			  if ( in_array($settings['base_country'], $this->eu_countries) ) {
			    if (in_array($country, $this->eu_countries))
			      $price = round($total * $settings['tax']['rate'], 2);
			  } else {
			    //all other countries use the tax outside preference
			    if ($settings['tax']['tax_outside'] || (!$settings['tax']['tax_outside'] && $country = $settings['base_country']))
			      $price = round($total * $settings['tax']['rate'], 2);
			  }
			  break;
    }
    if (empty($price))
			$price = 0;

    $price = apply_filters( 'mp_tax_price', $price, $total, $cart );

    if ($format)
      return $this->format_currency('', $price);
    else
      return $price;
  }

  //returns contents of shopping cart cookie
  function get_cart_cookie($global = false) {
    global $blog_id;
    $blog_id = (is_multisite()) ? $blog_id : 1;

    $cookie_id = 'mp_globalcart_' . COOKIEHASH;

    if (isset($_COOKIE[$cookie_id])) {
      $global_cart = unserialize($_COOKIE[$cookie_id]);
    } else {
      $global_cart = array($blog_id => array());
    }

    if ($global) {
      return $global_cart;
    } else {
	    if (isset($global_cart[$blog_id])) {
	      return $global_cart[$blog_id];
	    } else {
	      return array();
	    }
		}
  }

  //saves global cart array to cookie
  function set_global_cart_cookie($global_cart) {
    $cookie_id = 'mp_globalcart_' . COOKIEHASH;

    //set cookie
    $expire = time() + 2592000; //1 month expire
    setcookie($cookie_id, serialize($global_cart), $expire, COOKIEPATH, COOKIE_DOMAIN);

    // Set the cookie variable as well, sometimes updating the cache doesn't work
    $_COOKIE[$cookie_id] = serialize($global_cart);

    //mark cache for updating
    $this->cart_cache = false;
  }

  //saves cart array to cookie
  function set_cart_cookie($cart) {
    global $blog_id, $mp_gateway_active_plugins;
    $blog_id = (is_multisite()) ? $blog_id : 1;

    $global_cart = $this->get_cart_cookie(true);

    if ($this->global_cart && count($global_cart = $this->get_cart_cookie(true)) >= $mp_gateway_active_plugins[0]->max_stores && !isset($global_cart[$blog_id])) {
      $this->cart_checkout_error(sprintf(__("Sorry, currently it's not possible to checkout with items from more than %s stores.", 'mp'), $mp_gateway_active_plugins[0]->max_stores));
    } else {
      $global_cart[$blog_id] = $cart;
		}

    //update cache
    $this->set_global_cart_cookie($global_cart);
  }

  //returns the full array of cart contents
  function get_cart_contents($global = false) {
    global $blog_id;
    $blog_id = (is_multisite()) ? $blog_id : 1;
    $current_blog_id = $blog_id;

    //check cache
    if ($this->cart_cache) {
      if ($global) {
	      return $this->cart_cache;
	    } else {
		    if (isset($this->cart_cache[$blog_id])) {
		      return $this->cart_cache[$blog_id];
		    } else {
		      return array();
		    }
			}
    }

    $global_cart = $this->get_cart_cookie(true);
    if (!is_array($global_cart))
      return array();

    $full_cart = array();
    foreach ($global_cart as $bid => $cart) {

			if (is_multisite())
				switch_to_blog($bid);

      $full_cart[$bid] = array();
      foreach ($cart as $product_id => $variations) {
				$product = get_post($product_id);

				if ( empty($product) ) {
				  continue;
				}

        $full_cart[$bid][$product_id] = array();
				foreach ($variations as $variation => $quantity) {
					//check stock
          if (get_post_meta($product_id, 'mp_track_inventory', true)) {
						$stock = maybe_unserialize(get_post_meta($product_id, 'mp_inventory', true));
						if (!is_array($stock))
					  	$stock[0] = $stock;
	        	if ($stock[$variation] < $quantity) {
	        	  $this->cart_checkout_error( sprintf(__("Sorry, we don't have enough of %1$s in stock. Your cart quantity has been changed to %2$s.", 'mp'), $product->post_title, number_format_i18n($stock[$variation])) );
              $quantity = $stock[$variation];
						}
					}

        	//check limit if tracking on or downloadable
    			if (get_post_meta($product_id, 'mp_track_limit', true) || $file = get_post_meta($product_id, 'mp_file', true)) {
						$limit = empty($file) ? maybe_unserialize(get_post_meta($product_id, 'mp_limit', true)) : array($variation => 1);
			      if ($limit[$variation] && $limit[$variation] < $quantity) {
           		$this->cart_checkout_error( sprintf(__('Sorry, there is a per order limit of %1$s for "%2$s". Your cart quantity has been changed to %3$s.', 'mp'), number_format_i18n($limit[$variation]), $product->post_title, number_format_i18n($limit[$variation])) );
              $quantity = $limit[$variation];
			      }
		      }

				  $skus = maybe_unserialize(get_post_meta($product_id, 'mp_sku', true));
				  if (!is_array($skus))
						$skus[0] = $skus;
				  $var_names = maybe_unserialize(get_post_meta($product_id, 'mp_var_name', true));
				  if (is_array($var_names) && count($var_names) > 1)
				    $name = $product->post_title . ': ' . $var_names[$variation];
					else
					  $name = $product->post_title;

					//get if downloadable
          if ( $download_url = get_post_meta($product_id, 'mp_file', true) )
            $download = array('url' => $download_url, 'downloaded' => 0);
					else
					  $download = false;

					$full_cart[$bid][$product_id][$variation] = array('SKU' => $skus[$variation], 'name' => $name, 'url' => get_permalink($product_id), 'price' => $this->product_price($product_id, $variation), 'quantity' => $quantity, 'download' => $download);
				}
      }
    }

    if (is_multisite())
    	switch_to_blog($current_blog_id);

    //save to cache
    $this->cart_cache = $full_cart;

    if ($global) {
      return $full_cart;
    } else {
	    if (isset($full_cart[$blog_id])) {
	      return $full_cart[$blog_id];
	    } else {
	      return array();
	    }
		}
  }

  //receives a post and updates cookie variables for cart
  function update_cart($no_ajax = true) {
		global $blog_id, $mp_gateway_active_plugins;
		$blog_id = (is_multisite()) ? $blog_id : 1;
		$current_blog_id = $blog_id;
		$settings = get_option('mp_settings');

    $cart = $this->get_cart_cookie();

    if (isset($_POST['empty_cart'])) { //empty cart contents

			//clear all blog products only if global checkout enabled
			if ($this->global_cart)
				$this->set_global_cart_cookie(array());
			else
			  $this->set_cart_cookie(array());

      if ($no_ajax !== true) {
        ?>
    	<div class="mp_cart_empty">
    	  <?php _e('There are no items in your cart.', 'mp') ?>
    	</div>
    	<div id="mp_cart_actions_widget">
    	  <a class="mp_store_link" href="<?php mp_store_link(true, true); ?>"><?php _e('Browse Products &raquo;', 'mp') ?></a>
    	</div>
        <?php
        exit;
      }

    } else if (isset($_POST['product_id'])) { //add a product to cart

			//if not valid product_id return
      $product_id = apply_filters('mp_product_id_add_to_cart', intval($_POST['product_id']));
      $product = get_post($product_id);
      if (!$product)
        return false;

			//get quantity
      $quantity = (isset($_POST['quantity'])) ? intval($_POST['quantity']) : 1;

      //get variation
      $variation = (isset($_POST['variation'])) ? intval($_POST['variation']) : 0;

      //check max stores
      if ($this->global_cart && count($global_cart = $this->get_cart_cookie(true)) >= $mp_gateway_active_plugins[0]->max_stores && !isset($global_cart[$blog_id])) {
        if ($no_ajax !== true) {
	  			echo 'error||' . sprintf(__("Sorry, currently it's not possible to checkout with items from more than %s stores.", 'mp'), $mp_gateway_active_plugins[0]->max_stores);
          exit;
        } else {
          $this->cart_checkout_error(sprintf(__("Sorry, currently it's not possible to checkout with items from more than %s stores.", 'mp'), $mp_gateway_active_plugins[0]->max_stores));
          return false;
      	}
    	}

      //calculate new quantity
      $new_quantity = $cart[$product_id][$variation] + $quantity;

      //check stock
      if (get_post_meta($product_id, 'mp_track_inventory', true)) {
        $stock = maybe_unserialize(get_post_meta($product_id, 'mp_inventory', true));
        if (!is_array($stock))
					$stock[0] = $stock;
        if ($stock[$variation] < $new_quantity) {
          if ($no_ajax !== true) {
            echo 'error||' . sprintf(__("Sorry, we don't have enough of this item in stock. (%s remaining)", 'mp'), number_format_i18n($stock[$variation]-$cart[$product_id][$variation]));
            exit;
          } else {
            $this->cart_checkout_error( sprintf(__("Sorry, we don't have enough of this item in stock. (%s remaining)", 'mp'), number_format_i18n($stock[$variation]-$cart[$product_id][$variation])) );
            return false;
          }
        }
        //send ajax leftover stock
        if ($no_ajax !== true) {
          $return = $stock[$variation]-$new_quantity . '||';
        }
      } else {
        //send ajax always stock if stock checking turned off
        if ($no_ajax !== true) {
          $return = 1 . '||';
        }
      }

      //check limit if tracking on or downloadable
    	if (get_post_meta($product_id, 'mp_track_limit', true) || $file = get_post_meta($product_id, 'mp_file', true)) {
				$limit = empty($file) ? maybe_unserialize(get_post_meta($product_id, 'mp_limit', true)) : array($variation => 1);
	      if ($limit[$variation] && $limit[$variation] < $new_quantity) {
	        if ($no_ajax !== true) {
		  			echo 'error||' . sprintf(__('Sorry, there is a per order limit of %1$s for "%2$s".', 'mp'), number_format_i18n($limit[$variation]), $product->post_title);
	          exit;
	        } else {
	          $this->cart_checkout_error( sprintf(__('Sorry, there is a per order limit of %1$s for "%2$s".', 'mp'), number_format_i18n($limit[$variation]), $product->post_title) );
	          return false;
	        }
	      }
      }

      $cart[$product_id][$variation] = $new_quantity;

      //save items to cookie
      $this->set_cart_cookie($cart);

      //if running via ajax return updated cart and die
      if ($no_ajax !== true) {
        $return .= mp_show_cart('widget', false, false);
        echo $return;
				exit;
      }
    } else if (isset($_POST['update_cart_submit'])) { //update cart contents
      $global_cart = $this->get_cart_cookie(true);

      //process quantity updates
      if (is_array($_POST['quant'])) {
        foreach ($_POST['quant'] as $pbid => $quant) {
				  list($bid, $product_id, $variation) = split(':', $pbid);

					if (is_multisite())
				    switch_to_blog($bid);

          if (intval($quant)) {
            //check stock
            if (get_post_meta($product_id, 'mp_track_inventory', true)) {
              $stock = maybe_unserialize(get_post_meta($product_id, 'mp_inventory', true));
              if (!is_array($stock))
								$stock[0] = $stock;
              if ($stock[$variation] < intval($quant)) {
                $left = (($stock[$variation]-intval($global_cart[$bid][$product_id][$variation])) < 0) ? 0 : ($stock[$variation]-intval($global_cart[$bid][$product_id][$variation]));
                $this->cart_checkout_error( sprintf(__('Sorry, there is not enough stock for "%s". (%s remaining)', 'mp'), get_the_title($product_id), number_format_i18n($left)) );
                continue;
              }
            }
          	//check limit if tracking on or downloadable
    				if (get_post_meta($product_id, 'mp_track_limit', true) || $file = get_post_meta($product_id, 'mp_file', true)) {
							$limit = empty($file) ? maybe_unserialize(get_post_meta($product_id, 'mp_limit', true)) : array($variation => 1);
							if ($limit[$variation] && $limit[$variation] < intval($quant)) {
					      $this->cart_checkout_error( sprintf(__('Sorry, there is a per order limit of %1$s for "%2$s".', 'mp'), number_format_i18n($limit[$variation]), get_the_title($product_id)) );
	          		continue;
					    }
	          }

            $global_cart[$blog_id][$product_id][$variation] = intval($quant);
          } else {
            unset($global_cart[$blog_id][$product_id][$variation]);
          }
        }

	    	if (is_multisite())
    			switch_to_blog($current_blog_id);

        //save items to cookie
        $this->set_global_cart_cookie($global_cart);
      }

      //remove items
      if (is_array($_POST['remove'])) {
        foreach ($_POST['remove'] as $pbid) {
				  list($bid, $product_id, $variation) = split(':', $pbid);
          unset($global_cart[$blog_id][$product_id][$variation]);
        }

        //save items to cookie
        $this->set_global_cart_cookie($global_cart);
        $this->cart_update_message( __('Item(s) Removed', 'mp') );
      }

      //add coupon code
      if (!empty($_POST['coupon_code'])) {
        if ($this->check_coupon($_POST['coupon_code'])) {
          //get coupon code
          if (is_multisite()) {
            global $blog_id;
            $_SESSION['mp_cart_coupon_' . $blog_id] = $_POST['coupon_code'];
          } else {
            $_SESSION['mp_cart_coupon'] = $_POST['coupon_code'];
          }
          $this->cart_update_message( __('Coupon Successfully Applied', 'mp') );
        } else {
          $this->cart_checkout_error( __('Invalid Coupon Code', 'mp') );
        }
      }

    } else if (isset($_GET['remove_coupon'])) {

      //remove coupon code
      if (is_multisite()) {
        global $blog_id;
        unset($_SESSION['mp_cart_coupon_' . $blog_id]);
      } else {
        unset($_SESSION['mp_cart_coupon']);
      }
      $this->cart_update_message( __('Coupon Removed', 'mp') );

    } else if (isset($_POST['mp_shipping_submit'])) { //save shipping info

      //check checkout info
      if (!is_email($_POST['email']))
    		$this->cart_checkout_error( __('Please enter a valid Email Address.', 'mp'), 'email');

      if (empty($_POST['name']))
    		$this->cart_checkout_error( __('Please enter your Full Name.', 'mp'), 'name');

      if (empty($_POST['address1']))
    		$this->cart_checkout_error( __('Please enter your Street Address.', 'mp'), 'address1');

      if (empty($_POST['city']))
    		$this->cart_checkout_error( __('Please enter your City.', 'mp'), 'city');

      if (($_POST['country'] == 'US' || $_POST['country'] == 'CA') && empty($_POST['state']))
        $this->cart_checkout_error( __('Please enter your State/Province/Region.', 'mp'), 'state');

      if ($_POST['country'] == 'US' && !array_key_exists(strtoupper($_POST['state']), $this->usa_states))
        $this->cart_checkout_error( __('Please enter a valid two-letter State abbreviation.', 'mp'), 'state');
			else
			  $_POST['state'] = strtoupper($_POST['state']);

      if (empty($_POST['zip']))
    		$this->cart_checkout_error( __('Please enter your Zip/Postal Code.', 'mp'), 'zip');

      if (empty($_POST['country']) || strlen($_POST['country']) != 2)
    		$this->cart_checkout_error( __('Please enter your Country.', 'mp'), 'country');

      //save to session
      global $current_user;
      $meta = get_user_meta($current_user->ID, 'mp_shipping_info', true);
      $_SESSION['mp_shipping_info']['email'] = ($_POST['email']) ? trim(stripslashes($_POST['email'])) : (isset($meta['email']) ? $meta['email']: $current_user->user_email);
      $_SESSION['mp_shipping_info']['name'] = ($_POST['name']) ? trim(stripslashes($_POST['name'])) : (isset($meta['name']) ? $meta['name'] : $current_user->user_firstname . ' ' . $current_user->user_lastname);
      $_SESSION['mp_shipping_info']['address1'] = ($_POST['address1']) ? trim(stripslashes($_POST['address1'])) : $meta['address1'];
      $_SESSION['mp_shipping_info']['address2'] = ($_POST['address2']) ? trim(stripslashes($_POST['address2'])) : $meta['address2'];
      $_SESSION['mp_shipping_info']['city'] = ($_POST['city']) ? trim(stripslashes($_POST['city'])) : $meta['city'];
      $_SESSION['mp_shipping_info']['state'] = ($_POST['state']) ? trim(stripslashes($_POST['state'])) : $meta['state'];
      $_SESSION['mp_shipping_info']['zip'] = ($_POST['zip']) ? trim(stripslashes($_POST['zip'])) : $meta['zip'];
      $_SESSION['mp_shipping_info']['country'] = ($_POST['country']) ? trim($_POST['country']) : $meta['country'];
      $_SESSION['mp_shipping_info']['phone'] = ($_POST['phone']) ? preg_replace('/[^0-9-\(\) ]/', '', trim($_POST['phone'])) : $meta['phone'];

      //for checkout plugins
      do_action( 'mp_shipping_process' );

      //save to user meta
      if ($current_user->ID)
        update_user_meta($current_user->ID, 'mp_shipping_info', $_SESSION['mp_shipping_info']);

      //if no errors send to next checkout step
      if ($this->checkout_error == false) {

        //check for $0 checkout to skip gateways

        //loop through cart items
        $global_cart = $this->get_cart_contents(true);
			  if (!$this->global_cart)  //get subset if needed
			  	$selected_cart[$blog_id] = $global_cart[$blog_id];
			  else
			    $selected_cart = $global_cart;

				$totals = array();
		    $shipping_prices = array();
		    $tax_prices = array();
        foreach ($selected_cart as $bid => $cart) {

          if (is_multisite())
        		switch_to_blog($bid);

				  foreach ($cart as $product_id => $variations) {
				    foreach ($variations as $data) {
							$totals[] = $data['price'] * $data['quantity'];
				    }
				  }
		      if ( ($shipping_price = $this->shipping_price()) !== false )
		        $shipping_prices[] = $shipping_price;

		      if ( ($tax_price = $this->tax_price()) !== false )
		        $tax_prices[] = $tax_price;
        }

        //go back to original blog
		    if (is_multisite())
		      switch_to_blog($current_blog_id);

	    	$total = array_sum($totals);

        //coupon line
        if ( $coupon = $this->coupon_value($this->get_coupon_code(), $total) )
          $total = $coupon['new_total'];

				//shipping
        if ( $shipping_price = array_sum($shipping_prices) )
		      $total = $total + $shipping_price;

		    //tax line
		    if ( $tax_price = array_sum($tax_prices) )
		      $total = $total + $tax_price;

        if ($total > 0) {
          //can we skip the payment form page?
					if ( $this->global_cart ) {
        		$skip = apply_filters('mp_payment_form_skip_' . $network_settings['global_gateway'], false);
					} else {
					  $skip = apply_filters('mp_payment_form_skip_' . $settings['gateways']['allowed'][0], false);
					}
			    if ( (!$this->global_cart && count((array)$settings['gateways']['allowed']) > 1) || !$skip ) {
			      wp_safe_redirect(mp_checkout_step_url('checkout'));
			      exit;
			    } else {
			      if ( $this->global_cart )
			      	$_SESSION['mp_payment_method'] = $network_settings['global_gateway'];
						else
			      	$_SESSION['mp_payment_method'] = $settings['gateways']['allowed'][0];
			      do_action( 'mp_payment_submit_' . $_SESSION['mp_payment_method'], $this->get_cart_contents($this->global_cart), $_SESSION['mp_shipping_info'] );
			      //if no errors send to next checkout step
			      if ($this->checkout_error == false) {
							wp_safe_redirect(mp_checkout_step_url('confirm-checkout'));
							exit;
			      } else {
              wp_safe_redirect(mp_checkout_step_url('checkout'));
			      	exit;
						}
			    }
        } else { //empty price, create order already
					//loop through and create orders
	        foreach ($selected_cart as $bid => $cart) {
            $totals = array();
	          if (is_multisite())
	        		switch_to_blog($bid);

					  foreach ($cart as $product_id => $variations) {
					    foreach ($variations as $data) {
								$totals[] = $data['price'] * $data['quantity'];
					    }
					  }
            $total = array_sum($totals);

		        //coupon line
		        if ( $coupon = $this->coupon_value($this->get_coupon_code(), $total) )
		          $total = $coupon['new_total'];

						//shipping
          	if ( ($shipping_price = $this->shipping_price()) !== false )
				      $total = $total + $shipping_price;

				    //tax line
        		if ( ($tax_price = $this->tax_price()) !== false )
				      $total = $total + $tax_price;

            //setup our payment details
	          $timestamp = time();
	          $settings = get_option('mp_settings');
	    	  	$payment_info['gateway_public_name'] = __('Manual Checkout', 'mp');
	          $payment_info['gateway_private_name'] = __('Manual Checkout', 'mp');
	      	  $payment_info['method'][] = __('N/A - Free order', 'mp');
	      	  $payment_info['transaction_id'][] = __('N/A', 'mp');
	      	  $payment_info['status'][$timestamp] = __('Completed', 'mp');
	      	  $payment_info['total'] = $total;
	      	  $payment_info['currency'] = $settings['currency'];
		  			$this->create_order(false, $cart, $_SESSION['mp_shipping_info'], $payment_info, true);
	        }

	        //go back to original blog
			    if (is_multisite())
			      switch_to_blog($current_blog_id);

      	  $_SESSION['mp_payment_method'] = 'manual'; //so we don't get an error message on confirmation page

          //redirect to final page
	  			wp_safe_redirect(mp_checkout_step_url('confirmation'));
          exit;
        }
      }

    } else if (isset($_POST['mp_choose_gateway'])) { //check and save payment info
      $_SESSION['mp_payment_method'] = $_POST['mp_choose_gateway'];
      //processing script is only for selected gateway plugin
      do_action( 'mp_payment_submit_' . $_SESSION['mp_payment_method'], $this->get_cart_contents($this->global_cart), $_SESSION['mp_shipping_info'] );
      //if no errors send to next checkout step
      if ($this->checkout_error == false) {
        wp_safe_redirect(mp_checkout_step_url('confirm-checkout'));
        exit;
      }
    } else if (isset($_POST['mp_payment_confirm'])) { //create order and process payment
      do_action( 'mp_payment_confirm_' . $_SESSION['mp_payment_method'], $this->get_cart_contents($this->global_cart), $_SESSION['mp_shipping_info'] );

      //if no errors send to next checkout step
      if ($this->checkout_error == false) {
        wp_safe_redirect(mp_checkout_step_url('confirmation'));
        exit;
      }
    }
  }

  function cart_update_message($msg) {
    $content = 'return "<div id=\"mp_cart_updated_msg\">' . $msg . '</div>";';
    add_filter( 'mp_cart_updated_msg', create_function('', $content) );
  }

  function cart_checkout_error($msg, $context = 'checkout') {
    $msg = str_replace('"', '\"', $msg); //prevent double quotes from causing errors.
    $content = 'echo "<div class=\"mp_checkout_error\">' . $msg . '</div>";';
    add_action( 'mp_checkout_error_' . $context, create_function('', $content) );
    $this->checkout_error = true;
  }

  //returns any coupon code saved in $_SESSION. Will only reliably work on checkout pages
  function get_coupon_code() {
    //get coupon code
    if (is_multisite()) {
      global $blog_id;
      $coupon_code = $_SESSION['mp_cart_coupon_' . $blog_id];
    } else {
      $coupon_code = $_SESSION['mp_cart_coupon'];
    }

    return $coupon_code;
  }

  //checks a coupon code for validity. Return boolean
  function check_coupon($code) {
    $coupon_code = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($code));

    //empty code
    if (!$coupon_code)
      return false;

    $coupons = get_option('mp_coupons');

    //no record for code
    if (!is_array($coupons[$coupon_code]))
      return false;

    //start date not valid yet
    if (time() < $coupons[$coupon_code]['start'])
      return false;

    //if end date and expired
    if ($coupons[$coupon_code]['end'] && time() > $coupons[$coupon_code]['end'])
      return false;

    //check remaining uses
    if ($coupons[$coupon_code]['uses'] && (intval($coupons[$coupon_code]['uses']) - intval($coupons[$coupon_code]['used'])) <= 0)
      return false;

    //everything passed so it's valid
    return true;
  }

  //get coupon value. Returns array(discount, new_total) or false for invalid code
  function coupon_value($code, $total) {
    if ($this->check_coupon($code)) {
      $coupons = get_option('mp_coupons');
      $coupon_code = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($code));
      if ($coupons[$coupon_code]['discount_type'] == 'amt') {
        $settings = get_option('mp_settings');
        $new_total = round($total - $coupons[$coupon_code]['discount'], 2);
        $new_total = ($new_total < 0) ? 0.00 : $new_total;
        $discount = '-' . $this->format_currency('', $coupons[$coupon_code]['discount']);
        return array('discount' => $discount, 'new_total' => $new_total);
      } else {
        $new_total = round($total - ($total * ($coupons[$coupon_code]['discount'] * 0.01)), 2);
        $new_total = ($new_total < 0) ? 0.00 : $new_total;
        $discount = '-' . $coupons[$coupon_code]['discount'] . '%';
        return array('discount' => $discount, 'new_total' => $new_total);
      }

    } else {
      return false;
    }
  }

  //record coupon use. Returns boolean successful
  function use_coupon($code) {
    if ($this->check_coupon($code)) {
      $coupons = get_option('mp_coupons');
      $coupon_code = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($code));

      //increment count
      $coupons[$coupon_code]['used']++;
      update_option('mp_coupons', $coupons);

      return true;
    } else {
      return false;
    }
  }

  //returns a new unique order id.
  function generate_order_id() {
    global $wpdb;

    $count = true;
    while ($count) { //make sure it's unique
      $order_id = substr(sha1(uniqid('')), rand(1, 24), 12);
      $count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->posts . " WHERE post_title = '" . $order_id . "' AND post_type = 'mp_order'");
    }

    $order_id = apply_filters( 'mp_order_id', $order_id ); //Very important to make sure order numbers are unique and not sequential if filtering

    //save it to session
    $_SESSION['mp_order'] = $order_id;

    return $order_id;
  }

  //called on checkout to create a new order
  function create_order($order_id, $cart, $shipping_info, $payment_info, $paid, $user_id = false) {
    $settings = get_option('mp_settings');

    //order id can be null
    if (empty($order_id))
      $order_id = $this->generate_order_id();
		else if ($this->get_order($order_id)) //don't continue if the order exists
		  return false;

    //insert post type
    $order = array();
    $order['post_title'] = $order_id;
    $order['post_name'] = $order_id;
    $order['post_content'] = serialize($cart); //this is purely so you can search by cart contents
    $order['post_status'] = ($paid) ? 'order_paid' : 'order_received';
    $order['post_type'] = 'mp_order';
    $post_id = wp_insert_post($order);

    /* add post meta */
    //cart info
    add_post_meta($post_id, 'mp_cart_info', $cart, true);
    //shipping info
    add_post_meta($post_id, 'mp_shipping_info', $shipping_info, true);
    //payment info
    add_post_meta($post_id, 'mp_payment_info', $payment_info, true);

    //loop through cart items
    foreach ($cart as $product_id => $variations) {
			foreach ($variations as $variation => $data) {
	      $items[] = $data['quantity'];

	      //adjust product stock quantities
	      if (get_post_meta($product_id, 'mp_track_inventory', true)) {
	        $stock = maybe_unserialize(get_post_meta($product_id, 'mp_inventory', true));
					if (!is_array($stock))
					  $stock[0] = $stock;
					$stock[$variation] = $stock[$variation] - $data['quantity'];
	        update_post_meta($product_id, 'mp_inventory', $stock);

	        if ($stock[$variation] <= $settings['inventory_threshold']) {
	          $this->low_stock_notification($product_id, $variation, $stock[$variation]);
	        }
	      }//check stock

	      //update sales count
	      $count = get_post_meta($product_id, 'mp_sales_count', true);
	      $count = $count + $data['quantity'];
	      update_post_meta($product_id, 'mp_sales_count', $count);

	      //for plugins into product sales
	      do_action( 'mp_product_sale', $product_id, $variation, $data, $paid );
			}
    }
		$item_count = array_sum($items);

    //coupon info
    $code = $this->get_coupon_code();
    if ( $coupon = $this->coupon_value($code, 9999999999) ) {
      add_post_meta($post_id, 'mp_discount_info', array('code' => $code, 'discount' => $coupon['discount']), true);

      //mark coupon as used
      $this->use_coupon($code);
    }

    //payment info
    add_post_meta($post_id, 'mp_order_total', $payment_info['total'], true);
    add_post_meta($post_id, 'mp_shipping_total', $this->shipping_price(false, $cart), true);
    add_post_meta($post_id, 'mp_tax_total', $this->tax_price(false, $cart), true);
    add_post_meta($post_id, 'mp_order_items', $item_count, true);

    $timestamp = time();
    add_post_meta($post_id, 'mp_received_time', $timestamp, true);

    //set paid time if we already have a confirmed payment
    if ($paid) {
      add_post_meta($post_id, 'mp_paid_time', $timestamp, true);
      do_action( 'mp_order_paid', $this->get_order($order_id) );
		}

    //empty cart cookie
    $this->set_cart_cookie(array());

    //clear coupon code
    if (is_multisite()) {
      global $blog_id;
      unset($_SESSION['mp_cart_coupon_' . $blog_id]);
    } else {
      unset($_SESSION['mp_cart_coupon']);
    }

    //save order history
    if (!$user_id)
    	$user_id = get_current_user_id();

    if ($user_id) { //save to user_meta if logged in

      if (is_multisite()) {
        global $blog_id;
        $meta_id = 'mp_order_history_' . $blog_id;
      } else {
        $meta_id = 'mp_order_history';
      }

      $orders = get_user_meta($user_id, $meta_id, true);
      $timestamp = time();
      $orders[$timestamp] = array('id' => $order_id, 'total' => $payment_info['total']);
      update_user_meta($user_id, $meta_id, $orders);

    } else { //save to cookie instead

      if (is_multisite()) {
        global $blog_id;
        $cookie_id = 'mp_order_history_' . $blog_id . '_' . COOKIEHASH;
      } else {
        $cookie_id = 'mp_order_history_' . COOKIEHASH;
      }

      if (isset($_COOKIE[$cookie_id]))
        $orders = unserialize($_COOKIE[$cookie_id]);

      $timestamp = time();
      $orders[$timestamp] = array('id' => $order_id, 'total' => $payment_info['total']);

      //set cookie
      $expire = time() + 31536000; //1 year expire
      setcookie($cookie_id, serialize($orders), $expire, COOKIEPATH, COOKIEDOMAIN);
    }

    //send new order email
    $this->order_notification($order_id);

    //hook for new orders
    do_action( 'mp_new_order', $this->get_order($order_id) );

		//if paid and the cart is only digital products mark it shipped
		if ($paid && $this->download_only_cart($cart))
		  $this->update_order_status($order_id, 'shipped');

    return $order_id;
  }

  //returns the full order details as an object
  function get_order($order_id) {
    $id = (is_int($order_id)) ? $order_id : $this->order_to_post_id($order_id);

    if (empty($id))
      return false;

		$order = get_post($id);
    if (!$order)
      return false;

    $meta = get_post_custom($id);

		//unserialize a and add to object
		foreach ($meta as $key => $val)
      $order->$key = maybe_unserialize($meta[$key][0]);

    return $order;
  }

  //converts the pretty order id to an actual post ID
  function order_to_post_id($order_id) {
    global $wpdb;
    return $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'mp_order'", $order_id));
  }

  //$new_status can be 'received', 'paid', 'shipped', 'closed'
  function update_order_status($order_id, $new_status) {
    global $wpdb;

    $statuses = array('received' => 'order_received', 'paid' => 'order_paid', 'shipped' => 'order_shipped', 'closed' => 'order_closed');
    if (!array_key_exists($new_status, $statuses))
      return false;

    //get the order
    $order = $this->get_order($order_id);
    if (!$order)
      return false;

    switch ($new_status) {

      case 'paid':
        //update paid time, can't be adjusted as we don't want to loose gateway info
        if (!get_post_meta($order->ID, 'mp_paid_time', true))
          update_post_meta($order->ID, 'mp_paid_time', time());
        break;

      case 'shipped':
        //update paid time if paid step was skipped
        if (!get_post_meta($order->ID, 'mp_paid_time', true))
          update_post_meta($order->ID, 'mp_paid_time', time());
        //update shipped time, can be adjusted
        update_post_meta($order->ID, 'mp_shipped_time', time());

        //send email
        $this->order_shipped_notification($order->ID);
        break;

      case 'closed':
        //update paid time if paid step was skipped
        if (!get_post_meta($order->ID, 'mp_paid_time', true))
          update_post_meta($order->ID, 'mp_paid_time', time());
        //update shipped time if shipped step was skipped
        if (!get_post_meta($order->ID, 'mp_shipped_time', true))
          update_post_meta($order->ID, 'mp_shipped_time', time());
        //update closed
        update_post_meta($order->ID, 'mp_closed_time', time());
        break;

    }

    if ( $statuses[$new_status] == $order->post_status )
    	return;

    $wpdb->update( $wpdb->posts, array( 'post_status' => $statuses[$new_status] ), array( 'ID' => $order->ID ) );

    $old_status = $order->post_status;
    $order->post_status = $statuses[$new_status];
    wp_transition_post_status($statuses[$new_status], $old_status, $order);
  }

	//checks if a given cart is only downloadable products
	function download_only_cart($cart) {
		foreach ((array)$cart as $product_id => $variations) {
			foreach ((array)$variations as $variation => $data) {
				if (!is_array($data['download']))
      		return false;
			}
		}
		return true;
	}

  //returns formatted download url for a given product. Returns false if no download
	function get_download_url($product_id, $order_id) {
    $url = get_post_meta($product_id, 'mp_file', true);
    if (!$url)
      return false;

		return get_permalink($product_id) . "?orderid=$order_id";
	}

  //serves a downloadble product file
  function serve_download($product_id) {
    $settings = get_option('mp_settings');

		if (!isset($_GET['orderid']))
      return false;

    //get the order
    $order = $this->get_order($_GET['orderid']);
		if (!$order)
      return false;

		//check that order is paid
    if ($order->post_status == 'order_received')
      return false;

		$url = get_post_meta($product_id, 'mp_file', true);

		//get cart count
		if (isset($order->mp_cart_info[$product_id][0]['download']))
		  $download = $order->mp_cart_info[$product_id][0]['download'];

		//if new url is not set try to grab it from the order history
    if (!$url && isset($download['url']))
      $url = $download['url'];
		else if (!$url)
			return false;

		//check for too many downloads
		$max_downloads = intval($settings['max_downloads']) ? intval($settings['max_downloads']) : 5;
		if (intval($download['downloaded']) >= $max_downloads)
		  return false;

		//for plugins to hook into the download script. Don't forget to increment the download count, then exit!
		do_action('mp_serve_download', $url, $order, $download);

		//allows you to simply filter the url
		$url = apply_filters('mp_download_url', $url, $order, $download);

		//if your getting out of memory errors with large downloads, you can use a redirect instead, it's not so secure though
		if ( defined('MP_LARGE_DOWNLOADS') && MP_LARGE_DOWNLOADS ) {
		  //attempt to record a download attempt
			if (isset($download['downloaded'])) {
	    	$order->mp_cart_info[$product_id][0]['download']['downloaded'] = $download['downloaded'] + 1;
      	update_post_meta($order->ID, 'mp_cart_info', $order->mp_cart_info);
			}
			wp_redirect($url);
			exit;
		} else {

			//create unique filename
			$ext = ltrim(strrchr(basename($url), '.'), '.');
			$filename = sanitize_file_name( strtolower( get_the_title($product_id) ) . '.' . $ext );

			// Determine if this file is in our server
			$dirs = wp_upload_dir();
			$location = str_replace($dirs['baseurl'], $dirs['basedir'], $url);
			if ( file_exists($location) ) {
			  $tmp = $location;
			  $not_delete = true;
			} else {
				require_once(ABSPATH . '/wp-admin/includes/file.php');

		    $tmp = download_url($url); //we download the url so we can serve it via php, completely obfuscating original source

				if ( is_wp_error($tmp) ) {
					@unlink($tmp);
					return false;
				}
	    }

		  if (file_exists($tmp)) {
		    ob_end_clean(); //kills any buffers set by other plugins
				header('Content-Description: File Transfer');
		    header('Content-Type: application/octet-stream');
		    header('Content-Disposition: attachment; filename="'.$filename.'"');
		    header('Content-Transfer-Encoding: binary');
		    header('Expires: 0');
		    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		    header('Pragma: public');
		    header('Content-Length: ' . filesize($tmp));
		    //readfile($tmp); //seems readfile chokes on large files
				$chunksize = 1 * (1024 * 1024); // how many bytes per chunk
				$buffer = '';
				$cnt = 0;
				$handle = fopen( $tmp, 'rb' );
				if ( $handle === false ) {
					return false;
				}
				while ( !feof( $handle ) ) {
					$buffer = fread( $handle, $chunksize );
					echo $buffer;
					ob_flush();
					flush();
					if ( $retbytes ) {
						$cnt += strlen( $buffer );
					}
				}
				fclose( $handle );

				if (!$not_delete)
		    	@unlink($tmp);
			}

	    //attempt to record a download attempt
			if (isset($download['downloaded'])) {
	    	$order->mp_cart_info[$product_id][0]['download']['downloaded'] = $download['downloaded'] + 1;
      	update_post_meta($order->ID, 'mp_cart_info', $order->mp_cart_info);
			}
	    exit;
		}

		return false;
	}

  // Update profile fields
  function user_profile_update() {
    $user_id =  $_REQUEST['user_id'];

    // Billing Info
    $meta = get_user_meta($user_id, 'mp_billing_info');

    if (!isset($_POST['mp_billing_info']['email'])) {
      $meta['email'] = '';
    }
    if (!isset($_POST['mp_billing_info']['name'])) {
      $meta['name'] = '';
    }
    if (!isset($_POST['mp_billing_info']['address1'])) {
      $meta['address1'] = '';
    }
    if (!isset($_POST['mp_billing_info']['address2'])) {
      $meta['address2'] = '';
    }
    if (!isset($_POST['mp_billing_info']['city'])) {
      $meta['city'] = '';
    }
    if (!isset($_POST['mp_billing_info']['state'])) {
      $meta['state'] = '';
    }
    if (!isset($_POST['mp_billing_info']['zip'])) {
      $meta['zip'] = '';
    }
    if (!isset($_POST['mp_billing_info']['country'])) {
      $meta['country'] = '';
    }
    if (!isset($_POST['mp_billing_info']['phone'])) {
      $meta['phone'] = '';
    }

    $email = isset($_POST['mp_billing_info']['email']) ? $_POST['mp_billing_info']['email'] : $meta['email'];
    $name = isset($_POST['mp_billing_info']['name']) ? $_POST['mp_billing_info']['name'] : $meta['name'];
    $address1 = isset($_POST['mp_billing_info']['address1']) ? $_POST['mp_billing_info']['address1'] : $meta['address1'];
    $address2 = isset($_POST['mp_billing_info']['address2']) ? $_POST['mp_billing_info']['address2'] : $meta['address2'];
    $city = isset($_POST['mp_billing_info']['city']) ? $_POST['mp_billing_info']['city'] : $meta['city'];
    $state = isset($_POST['mp_billing_info']['state']) ? $_POST['mp_billing_info']['state'] : $meta['state'];
    $zip = isset($_POST['mp_billing_info']['zip']) ? $_POST['mp_billing_info']['zip'] : $meta['zip'];
    $country = isset($_POST['mp_billing_info']['country']) ? $_POST['mp_billing_info']['country'] : $meta['country'];
    $phone = isset($_POST['mp_billing_info']['phone']) ? $_POST['mp_billing_info']['phone'] : $meta['phone'];

    $billing_meta = array('email' => $email,
			  'name' => $name,
			  'address1' => $address1,
			  'address2' => $address2,
			  'city' => $city,
			  'state' => $state,
			  'zip' => $zip,
		    'country' => $country,
			  'phone' => $phone);

    update_user_meta($user_id, 'mp_billing_info', $billing_meta);

    // Shipping Info
    $meta = get_user_meta($user_id, 'mp_shipping_info');

    if (!isset($_POST['mp_shipping_info']['email'])) {
      $meta['email'] = '';
    }
    if (!isset($_POST['mp_shipping_info']['name'])) {
      $meta['name'] = '';
    }
    if (!isset($_POST['mp_shipping_info']['address1'])) {
      $meta['address1'] = '';
    }
    if (!isset($_POST['mp_shipping_info']['address2'])) {
      $meta['address2'] = '';
    }
    if (!isset($_POST['mp_shipping_info']['city'])) {
      $meta['city'] = '';
    }
    if (!isset($_POST['mp_shipping_info']['state'])) {
      $meta['state'] = '';
    }
    if (!isset($_POST['mp_shipping_info']['zip'])) {
      $meta['zip'] = '';
    }
    if (!isset($_POST['mp_shipping_info']['country'])) {
      $meta['country'] = '';
    }

    $email = isset($_POST['mp_shipping_info']['email']) ? $_POST['mp_shipping_info']['email'] : $meta['email'];
    $name = isset($_POST['mp_shipping_info']['name']) ? $_POST['mp_shipping_info']['name'] : $meta['name'];
    $address1 = isset($_POST['mp_shipping_info']['address1']) ? $_POST['mp_shipping_info']['address1'] : $meta['address1'];
    $address2 = isset($_POST['mp_shipping_info']['address2']) ? $_POST['mp_shipping_info']['address2'] : $meta['address2'];
    $city = isset($_POST['mp_shipping_info']['city']) ? $_POST['mp_shipping_info']['city'] : $meta['city'];
    $state = isset($_POST['mp_shipping_info']['state']) ? $_POST['mp_shipping_info']['state'] : $meta['state'];
    $zip = isset($_POST['mp_shipping_info']['zip']) ? $_POST['mp_shipping_info']['zip'] : $meta['zip'];
    $country = isset($_POST['mp_shipping_info']['country']) ? $_POST['mp_shipping_info']['country'] : $meta['country'];
    $phone = isset($_POST['mp_shipping_info']['phone']) ? $_POST['mp_shipping_info']['phone'] : $meta['phone'];

    $shipping_meta = array('email' => $email,
			   'name' => $name,
			   'address1' => $address1,
			   'address2' => $address2,
			   'city' => $city,
			   'state' => $state,
			   'zip' => $zip,
			   'country' => $country,
			   'phone' => $phone);

    update_user_meta($user_id, 'mp_shipping_info', $shipping_meta);
  }

  function user_profile_fields() {
    global $current_user;

    if (isset($_REQUEST['user_id'])) {
      $user_id = $_REQUEST['user_id'];
    } else {
      $user_id = $current_user->ID;
    }

    $settings = get_option('mp_settings');

    $meta = get_user_meta($user_id, 'mp_billing_info', true);
    $email = (!empty($_SESSION['mp_billing_info']['email'])) ? $_SESSION['mp_billing_info']['email'] : $meta['email'];
    $name = (!empty($_SESSION['mp_billing_info']['name'])) ? $_SESSION['mp_billing_info']['name'] : $meta['name'];
    $address1 = (!empty($_SESSION['mp_billing_info']['address1'])) ? $_SESSION['mp_billing_info']['address1'] : $meta['address1'];
    $address2 = (!empty($_SESSION['mp_billing_info']['address2'])) ? $_SESSION['mp_billing_info']['address2'] : $meta['address2'];
    $city = (!empty($_SESSION['mp_billing_info']['city'])) ? $_SESSION['mp_billing_info']['city'] : $meta['city'];
    $state = (!empty($_SESSION['mp_billing_info']['state'])) ? $_SESSION['mp_billing_info']['state'] : $meta['state'];
    $zip = (!empty($_SESSION['mp_billing_info']['zip'])) ? $_SESSION['mp_billing_info']['zip'] : $meta['zip'];
    $country = (!empty($_SESSION['mp_billing_info']['country'])) ? $_SESSION['mp_billing_info']['country'] : $meta['country'];
    if (!$country)
      $country = $settings['base_country'];
    $phone = (!empty($_SESSION['mp_billing_info']['phone'])) ? $_SESSION['mp_billing_info']['phone'] : $meta['phone'];

    ?>
    <h3><?php _e('Billing Info', 'mp'); ?></h3>
    <a name="mp_billing_info"></a>
    <table class="form-table">
      <tr>
        <th align="right"><label for="mp_billing_info_email"><?php _e('Email:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_billing_info_error_email', ''); ?>
        <input size="35" id="mp_billing_info_email" name="mp_billing_info[email]" type="text" value="<?php echo esc_attr($email); ?>" /></td>
      </tr>
      <tr>
        <th align="right"><label for="mp_billing_info_name"><?php _e('Full Name:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_billing_info_error_name', ''); ?>
        <input size="35" id="mp_billing_info_name" name="mp_billing_info[name]" type="text" value="<?php echo esc_attr($name); ?>" /> </td>
      </tr>
      <tr>
        <th align="right"><label for="mp_billing_info_address1"><?php _e('Address:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_billing_info_error_address1', ''); ?>
        <input size="45" id="mp_billing_info_address1" name="mp_billing_info[address1]" type="text" value="<?php echo esc_attr($address1); ?>" /><br />
        <small><em><?php _e('Street address, P.O. box, company name, c/o', 'mp'); ?></em></small>
        </td>
      </tr>
      <tr>
        <th align="right"><label for="mp_billing_info_address2"><?php _e('Address 2:', 'mp'); ?>&nbsp;</label></th><td>
  			<?php echo apply_filters( 'mp_billing_info_error_address2', ''); ?>
        <input size="45" id="mp_billing_info_address2" name="mp_billing_info[address2]" type="text" value="<?php echo esc_attr($address2); ?>" /><br />
        <small><em><?php _e('Apartment, suite, unit, building, floor, etc.', 'mp'); ?></em></small>
        </td>
      </tr>
      <tr>
        <th align="right"><label for="mp_billing_info_city"><?php _e('City:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_billing_info_error_city', ''); ?>
        <input size="25" id="mp_billing_info_city" name="mp_billing_info[city]" type="text" value="<?php echo esc_attr($city); ?>" /></td>
      </tr>
      <tr>
        <th align="right"><label for="mp_billing_info_state"><?php _e('State/Province/Region:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_billing_info_error_state', ''); ?>
        <input size="15" id="mp_billing_info_state" name="mp_billing_info[state]" type="text" value="<?php echo esc_attr($state); ?>" /></td>
      </tr>
      <tr>
        <th align="right"><label for="mp_billing_info_zip"><?php _e('Postal/Zip Code:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_billing_info_error_zip', ''); ?>
        <input size="10" id="mp_billing_info_zip" name="mp_billing_info[zip]" type="text" value="<?php echo esc_attr($zip); ?>" /></td>
      </tr>
      <tr>
        <th align="right"><label for="mp_billing_info_country"><?php _e('Country:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_billing_info_error_country', ''); ?>
        <select id="mp_billing_info_country" name="mp_billing_info[country]">
          <?php
          foreach ($settings['shipping']['allowed_countries'] as $code) {
            ?><option value="<?php echo $code; ?>"<?php selected($country, $code); ?>><?php echo esc_attr($this->countries[$code]); ?></option><?php
          }
          ?>
        </select>
        </td>
      </tr>
      <tr>
        <th align="right"><label for="mp_billing_info_phone"><?php _e('Phone Number:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_billing_info_error_phone', ''); ?>
  			<input size="20" id="mp_billing_info_phone" name="mp_billing_info[phone]" type="text" value="<?php echo esc_attr($phone); ?>" /></td>
      </tr>
    </table>
    <?php
    $meta = get_user_meta($user_id, 'mp_shipping_info', true);

    $email = (!empty($_SESSION['mp_shipping_info']['email'])) ? $_SESSION['mp_shipping_info']['email'] : (!empty($meta['email'])?$meta['email']:$_SESSION['mp_shipping_info']['email']);
    $name = (!empty($_SESSION['mp_shipping_info']['name'])) ? $_SESSION['mp_shipping_info']['name'] : (!empty($meta['name'])?$meta['name']:$_SESSION['mp_shipping_info']['name']);
    $address1 = (!empty($_SESSION['mp_shipping_info']['address1'])) ? $_SESSION['mp_shipping_info']['address1'] : (!empty($meta['address1'])?$meta['address1']:$_SESSION['mp_shipping_info']['address1']);
    $address2 = (!empty($_SESSION['mp_shipping_info']['address2'])) ? $_SESSION['mp_shipping_info']['address2'] : (!empty($meta['address2'])?$meta['address2']:$_SESSION['mp_shipping_info']['address2']);
    $city = (!empty($_SESSION['mp_shipping_info']['city'])) ? $_SESSION['mp_shipping_info']['city'] : (!empty($meta['city'])?$meta['city']:$_SESSION['mp_shipping_info']['city']);
    $state = (!empty($_SESSION['mp_shipping_info']['state'])) ? $_SESSION['mp_shipping_info']['state'] : (!empty($meta['state'])?$meta['state']:$_SESSION['mp_shipping_info']['state']);
    $zip = (!empty($_SESSION['mp_shipping_info']['zip'])) ? $_SESSION['mp_shipping_info']['zip'] : (!empty($meta['zip'])?$meta['zip']:$_SESSION['mp_shipping_info']['zip']);
    $country = (!empty($_SESSION['mp_shipping_info']['country'])) ? $_SESSION['mp_shipping_info']['country'] : (!empty($meta['country'])?$meta['country']:$_SESSION['mp_shipping_info']['country']);
    if (!$country)
      $country = $settings['base_country'];
    $phone = (!empty($_SESSION['mp_shipping_info']['phone'])) ? $_SESSION['mp_shipping_info']['phone'] : (!empty($meta['phone'])?$meta['phone']:$_SESSION['mp_shipping_info']['phone']);

    ?>
    <h3><?php _e('Shipping Info', 'mp'); ?></h3>
    <a name="mp_shipping_info"></a>
    <span class="mp_action" ><a href="javascript:mp_copy_billing('mp_shipping_info');"><?php _e('Same as Billing', 'mp'); ?></a></span>
    <table class="form-table">
			<tr>
        <th align="right"><label for="mp_shipping_info_email"><?php _e('Email:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_shipping_info_error_email', ''); ?>
        <input size="35" id="mp_shipping_info_email" name="mp_shipping_info[email]" type="text" value="<?php echo esc_attr($email); ?>" /></td>
      </tr>
      <tr>
        <th align="right"><label for="mp_shipping_info_name"><?php _e('Full Name:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_checkout_error_name', ''); ?>
        <input size="35" id="mp_shipping_info_name" name="mp_shipping_info[name]" type="text" value="<?php echo esc_attr($name); ?>" /> </td>
      </tr>
      <tr>
        <th align="right"><label for="mp_shipping_info_address1"><?php _e('Address:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_shipping_info_error_address1', ''); ?>
        <input size="45" id="mp_shipping_info_address1" name="mp_shipping_info[address1]" type="text" value="<?php echo esc_attr($address1); ?>" /><br />
        <small><em><?php _e('Street address, P.O. box, company name, c/o', 'mp'); ?></em></small>
        </td>
      </tr>
      <tr>
        <th align="right"><label for="mp_shipping_info_address2"><?php _e('Address 2:', 'mp'); ?>&nbsp;</label></th><td>
  			<?php echo apply_filters( 'mp_shipping_info_error_address2', ''); ?>
        <input size="45" id="mp_shipping_info_address2" name="mp_shipping_info[address2]" type="text" value="<?php echo esc_attr($address2); ?>" /><br />
        <small><em><?php _e('Apartment, suite, unit, building, floor, etc.', 'mp'); ?></em></small>
        </td>
      </tr>
      <tr>
        <th align="right"><label for="mp_shipping_info_city"><?php _e('City:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_shipping_info_error_city', ''); ?>
        <input size="25" id="mp_shipping_info_city" name="mp_shipping_info[city]" type="text" value="<?php echo esc_attr($city); ?>" /></td>
      </tr>
      <tr>
        <th align="right"><label for="mp_shipping_info_state"><?php _e('State/Province/Region:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_shipping_info_error_state', ''); ?>
        <input size="15" id="mp_shipping_info_state" name="mp_shipping_info[state]" type="text" value="<?php echo esc_attr($state); ?>" /></td>
      </tr>
      <tr>
        <th align="right"><label for="mp_shipping_info_zip"><?php _e('Postal/Zip Code:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_shipping_info_error_zip', ''); ?>
        <input size="10" id="mp_shipping_info_zip" name="mp_shipping_info[zip]" type="text" value="<?php echo esc_attr($zip); ?>" /></td>
      </tr>
      <tr>
        <th align="right"><label for="mp_shipping_info_country"><?php _e('Country:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_shipping_info_error_country', ''); ?>
        <select id="mp_shipping_info_country" name="mp_shipping_info[country]">
          <?php
          foreach ($settings['shipping']['allowed_countries'] as $code) {
            ?><option value="<?php echo $code; ?>"<?php selected($country, $code); ?>><?php echo esc_attr($this->countries[$code]); ?></option><?php
          }
          ?>
        </select>
        </td>
      </tr>
      <tr>
        <th align="right"><label for="mp_shipping_info_phone"><?php _e('Phone Number:', 'mp'); ?>&nbsp;</label></th><td>
        <?php echo apply_filters( 'mp_shipping_info_error_phone', ''); ?>
  			<input size="20" id="mp_shipping_info_phone" name="mp_shipping_info[phone]" type="text" value="<?php echo esc_attr($phone); ?>" /></td>
      </tr>
    </table>
    <script type="text/javascript">
    function mp_copy_billing(prefix) {
      _mp_profile_billing_fields = ['emal', 'name', 'address1', 'address2', 'city', 'state', 'zip', 'country', 'phone'];

      for (_i=0; _i<_mp_profile_billing_fields.length; _i++) {
        jQuery('form #'+prefix+'_'+_mp_profile_billing_fields[_i]).val(jQuery('form #mp_billing_info_'+_mp_profile_billing_fields[_i]).val());
      }
    }
    </script>
    <?php
  }

  //called by payment gateways to update order statuses
  function update_order_payment_status($order_id, $status, $paid) {
    //get the order
    $order = $this->get_order($order_id);
    if (!$order)
      return false;

    //get old status
    $payment_info = $order->mp_payment_info;
    $timestamp = time();
    $payment_info['status'][$timestamp] = $status;
    //update post meta
    update_post_meta($order->ID, 'mp_payment_info', $payment_info);

    if ($paid) {
      if ($order->post_status == 'order_received') {
        $this->update_order_status($order->ID, 'paid');
        do_action( 'mp_order_paid', $order );

        //if paid and the cart is only digital products mark it shipped
				if ($this->download_only_cart($cart))
				  $this->update_order_status($order->ID, 'shipped');
      } else {
        //update payment time if somehow it was skipped
        if (!get_post_meta($order->ID, 'mp_paid_time', true))
          update_post_meta($order->ID, 'mp_paid_time', time());
      }
    } else {
      $this->update_order_status($order->ID, 'received');
    }

    //return merged payment info
    return $payment_info;
  }

	//filters wp_mail headers
	function mail($to, $subject, $msg) {
    $settings = get_option('mp_settings');

    //remove any other filters
    remove_all_filters( 'wp_mail_from' );
		remove_all_filters( 'wp_mail_from_name' );

		//add our own filters
		add_filter( 'wp_mail_from_name', create_function('', 'return get_bloginfo("name");') );
		add_filter( 'wp_mail_from', create_function('', '$settings = get_option("mp_settings");return isset($settings["store_email"]) ? $settings["store_email"] : get_option("admin_email");') );

		return wp_mail($to, $subject, $msg);
	}

  //replaces shortcodes in email msgs with dynamic content
  function filter_email($order, $text) {
    $settings = get_option('mp_settings');

    //// order info
    if (is_array($order->mp_cart_info) && count($order->mp_cart_info)) {
      $order_info = __('Items:', 'mp') . "\n";
      foreach ($order->mp_cart_info as $product_id => $variations) {
				foreach ($variations as $variation => $data) {
	        $order_info .= "\t" . $data['name'] . ': ' . number_format_i18n($data['quantity']) . ' * ' . number_format_i18n($data['price'], 2) . ' = '. number_format_i18n($data['price'] * $data['quantity'], 2) . ' ' . $order->mp_payment_info['currency'] . "\n";

					//show download link if set
					if ($order->post_status != 'order_received' && $download_url = $this->get_download_url($product_id, $order->post_title))
	        	$order_info .= "\t\t" . __('Download: ', 'mp') . $download_url . "\n";
				}
			}
      $order_info .= "\n";
    }
    //coupon line
    if ( $order->mp_discount_info ) {
      $order_info .= "\n" . __('Coupon Discount:', 'mp') . ' ' . $order->mp_discount_info['discount'];
    }
    //shipping line
    if ( $order->mp_shipping_total ) {
      $order_info .= "\n" . __('Shipping:', 'mp') . ' ' . number_format_i18n($order->mp_shipping_total, 2) . ' ' . $order->mp_payment_info['currency'];
    }
    //tax line
    if ( $order->mp_tax_total ) {
      $order_info .= "\n" . __('Taxes:', 'mp') . ' ' . number_format_i18n($order->mp_tax_total, 2) . ' ' . $order->mp_payment_info['currency'];
    }
    //total line
    $order_info .= "\n" . __('Order Total:', 'mp') . ' ' . number_format_i18n($order->mp_order_total, 2) . ' ' . $order->mp_payment_info['currency'];

    //// Shipping Info
    $shipping_info = __('Full Name:', 'mp') . ' ' . $order->mp_shipping_info['name'];
    $shipping_info .= "\n" . __('Address:', 'mp') . ' ' . $order->mp_shipping_info['address1'];
    if ($order->mp_shipping_info['address2'])
      $shipping_info .= "\n" . __('Address 2:', 'mp') . ' ' . $order->mp_shipping_info['address2'];
    $shipping_info .= "\n" . __('City:', 'mp') . ' ' . $order->mp_shipping_info['city'];
    if ($order->mp_shipping_info['state'])
      $shipping_info .= "\n" . __('State/Province/Region:', 'mp') . ' ' . $order->mp_shipping_info['state'];
    $shipping_info .= "\n" . __('Postal/Zip Code:', 'mp') . ' ' . $order->mp_shipping_info['zip'];
    $shipping_info .= "\n" . __('Country:', 'mp') . ' ' . $order->mp_shipping_info['country'];
    if ($order->mp_shipping_info['phone'])
      $shipping_info .= "\n" . __('Phone Number:', 'mp') . ' ' . $order->mp_shipping_info['phone'];

    //// Payment Info
    $payment_info = __('Payment Method:', 'mp') . ' ' . $order->mp_payment_info['gateway_public_name'];

		if ($order->mp_payment_info['method'])
    	$payment_info .= "\n" . __('Payment Type:', 'mp') . ' ' . $order->mp_payment_info['method'];

		if ($order->mp_payment_info['transaction_id'])
			$payment_info .= "\n" . __('Transaction ID:', 'mp') . ' ' . $order->mp_payment_info['transaction_id'];

		$payment_info .= "\n" . __('Payment Total:', 'mp') . ' ' . number_format_i18n($order->mp_payment_info['total'], 2) . ' ' . $order->mp_payment_info['currency'];
    $payment_info .= "\n\n";
    if ($order->post_status == 'order_received') {
      $payment_info .= __('Your payment for this order is not yet complete. Here is the latest status:', 'mp') . "\n";
      $statuses = $order->mp_payment_info['status'];
      krsort($statuses); //sort with latest status at the top
      $status = reset($statuses);
      $timestamp = key($statuses);
      $payment_info .= date(get_option('date_format') . ' - ' . get_option('time_format'), $timestamp) . ': ' . $status;
    } else {
      $payment_info .= __('Your payment for this order is complete.', 'mp');
    }

		//total
		$order_total = number_format_i18n($order->mp_payment_info['total'], 2) . ' ' . $order->mp_payment_info['currency'];

    //tracking URL
    $tracking_url = mp_orderstatus_link(false, true) . $order->post_title . '/';

    //setup filters
    $search = array('CUSTOMERNAME', 'ORDERID', 'ORDERINFO', 'SHIPPINGINFO', 'PAYMENTINFO', 'TOTAL', 'TRACKINGURL');
    $replace = array($order->mp_shipping_info['name'], $order->post_title, $order_info, $shipping_info, $payment_info, $order_total, $tracking_url);

    //replace
    $text = str_replace($search, $replace, $text);

    return $text;
  }

  //sends email for new orders
  function order_notification($order_id) {
    $settings = get_option('mp_settings');

    //get the order
    $order = $this->get_order($order_id);
    if (!$order)
      return false;

    $subject = $this->filter_email($order, $settings['email']['new_order_subject']);
    $msg = $this->filter_email($order, $settings['email']['new_order_txt']);
    $msg = apply_filters( 'mp_order_notification_' . $_SESSION['mp_payment_method'], $msg, $order );

    $this->mail($order->mp_shipping_info['email'], $subject, $msg);

    //send message to admin
    $subject = __('New Order Notification: ORDERID', 'mp');
    $msg = __("A new order (ORDERID) was created in your store:

Order Information:
ORDERINFO

Shipping Information:
SHIPPINGINFO

Email: %s

Payment Information:
PAYMENTINFO

You can manage this order here: %s", 'mp');

    $subject = $this->filter_email($order, $subject);
    $msg = $this->filter_email($order, $msg);
		$msg = sprintf($msg, $order->mp_shipping_info['email'], admin_url('edit.php?post_type=product&page=marketpress-orders&order_id=') . $order->ID);
    $store_email = isset($settings['store_email']) ? $settings['store_email'] : get_option("admin_email");
    $this->mail($store_email, $subject, $msg);
  }

  //sends email for orders marked as shipped
  function order_shipped_notification($order_id) {
    $settings = get_option('mp_settings');

    //get the order
    $order = $this->get_order($order_id);
    if (!$order)
      return false;

    //if the cart is only digital products skip notification
		if ($this->download_only_cart($order->mp_cart_info))
    	return false;

    $settings['email']['shipped_order_subject'] = apply_filters('mp_shipped_order_notification_subject', $settings['email']['shipped_order_subject'], $order);
    $subject = $this->filter_email($order, $settings['email']['shipped_order_subject']);
    $settings['email']['shipped_order_txt'] = apply_filters( 'mp_shipped_order_notification_body', $settings['email']['shipped_order_txt'], $order );
    $msg = $this->filter_email($order, $settings['email']['shipped_order_txt']);
    $msg = apply_filters( 'mp_shipped_order_notification', $msg, $order );

    $this->mail($order->mp_shipping_info['email'], $subject, $msg);

  }

  //sends email to admin for low stock notification
  function low_stock_notification($product_id, $variation, $stock) {
    $settings = get_option('mp_settings');

    //skip if sent already and not 0
    if ( get_post_meta($product_id, 'mp_stock_email_sent', true) && $stock > 0 )
      return;

    $var_names = maybe_unserialize(get_post_meta($product_id, 'mp_var_name', true));
	  if (is_array($var_names) && count($var_names) > 1)
	    $name = get_the_title($product_id) . ': ' . $var_names[$variation];
		else
		  $name = get_the_title($product_id);

    $subject = __('Low Product Inventory Notification', 'mp');
    $msg = __('This message is being sent to notify you of low stock of a product in your online store according to your preferences.

Product: %s
Current Inventory: %s
Link: %s

Edit Product: %s
Notification Preferences: %s', 'mp');
    $msg = sprintf($msg, $name, number_format_i18n($stock), get_permalink($product_id), get_edit_post_link($product_id), admin_url('edit.php?post_type=product&page=marketpress#mp-inventory-setting'));
    $msg = apply_filters( 'mp_low_stock_notification', $msg, $product_id );
    $store_email = isset($settings['store_email']) ? $settings['store_email'] : get_option("admin_email");
    $this->mail($store_email, $subject, $msg);

    //save so we don't send an email every time
    update_post_meta($product_id, 'mp_stock_email_sent', 1);
  }

  //round and display currency with padded zeros
  function display_currency( $amount ) {
    $settings = get_option('mp_settings');

    if ( $settings['curr_decimal'] === '0' )
      return number_format( round( $amount ) );
    else
      return number_format( round( $amount, 2 ), 2, '.', '');
  }

  //display currency symbol
  function format_currency($currency = '', $amount = false) {
    $settings = get_option('mp_settings');

    if (!$currency)
      $currency = $settings['currency'];

    // get the currency symbol
    $symbol = $this->currencies[$currency][1];
    // if many symbols are found, rebuild the full symbol
    $symbols = explode(', ', $symbol);
    if (is_array($symbols)) {
      $symbol = "";
      foreach ($symbols as $temp) {
        $symbol .= '&#x'.$temp.';';
      }
    } else {
      $symbol = '&#x'.$symbol.';';
    }

		//check decimal option
    if ( $settings['curr_decimal'] === '0' ) {
      $decimal_place = 0;
      $zero = '0';
		} else {
      $decimal_place = 2;
      $zero = '0.00';
		}

    //format currency amount according to preference
    if ($amount) {

      if ($settings['curr_symbol_position'] == 1 || !$settings['curr_symbol_position'])
        return $symbol . number_format_i18n($amount, $decimal_place);
      else if ($settings['curr_symbol_position'] == 2)
        return $symbol . ' ' . number_format_i18n($amount, $decimal_place);
      else if ($settings['curr_symbol_position'] == 3)
        return number_format_i18n($amount, $decimal_place) . $symbol;
      else if ($settings['curr_symbol_position'] == 4)
        return number_format_i18n($amount, $decimal_place) . ' ' . $symbol;

    } else if ($amount === false) {
      return $symbol;
    } else {
      if ($settings['curr_symbol_position'] == 1 || !$settings['curr_symbol_position'])
        return $symbol . $zero;
      else if ($settings['curr_symbol_position'] == 2)
        return $symbol . ' ' . $zero;
      else if ($settings['curr_symbol_position'] == 3)
        return $zero . $symbol;
      else if ($settings['curr_symbol_position'] == 4)
        return $zero . ' ' . $symbol;
    }
  }

  //replaces wp_trim_excerpt in our custom loops
  function product_excerpt($excerpt, $content, $product_id) {
    $excerpt_more = ' <a class="mp_product_more_link" href="' . get_permalink($product_id) . '">' .  __('More Info &raquo;', 'mp') . '</a>';
    if ($excerpt) {
      return $excerpt . $excerpt_more;
    } else {
  		$text = strip_shortcodes( $content );
  		//$text = apply_filters('the_content', $text);
  		$text = str_replace(']]>', ']]&gt;', $text);
  		$text = strip_tags($text);
  		$excerpt_length = apply_filters('excerpt_length', 55);
  		$words = preg_split("/[\n\r\t ]+/", $text, $excerpt_length + 1, PREG_SPLIT_NO_EMPTY);
  		if ( count($words) > $excerpt_length ) {
  			array_pop($words);
  			$text = implode(' ', $words);
  			$text = $text . $excerpt_more;
  		} else {
  			$text = implode(' ', $words);
  		}
  	}
  	return $text;
  }

  //returns the js needed to record ecommerce transactions. $project should be an array of id, title
	function create_ga_ecommerce($order) {
  	$settings = get_option('mp_settings');

		if (!is_object($order))
		  return false;

    if ($settings['ga_ecommerce'] == 'old') {

			$js = '<script type="text/javascript">
			try{
		  pageTracker._addTrans(
		      "'.esc_js($order->post_title).'",                  // order ID - required
		      "'.esc_js(get_bloginfo('blogname')).'",            // affiliation or store name
		      "'.$order->mp_order_total.'",                        // total - required
		      "'.$order->mp_tax_total.'",                          // tax
		      "'.$order->mp_shipping_total.'",                     // shipping
		      "'.esc_js($order->mp_shipping_info['city']).'",    // city
		      "'.esc_js($order->mp_shipping_info['state']).'",   // state or province
		      "'.esc_js($order->mp_shipping_info['country']).'"  // country
		    );';

			if (is_array($order->mp_cart_info) && count($order->mp_cart_info)) {
				foreach ($order->mp_cart_info as $product_id => $variations) {
					foreach ($variations as $variation => $data) {
						$sku = !empty($data['SKU']) ? esc_js($data['SKU']) : $product_id;
						$js .= 'pageTracker._addItem(
						  "'.esc_js($order->post_title).'", // order ID - necessary to associate item with transaction
						  "'.$sku.'",                         // SKU/code - required
						  "'.esc_js($data['name']).'",      // product name
						  "'.$data['price'].'",               // unit price - required
						  "'.$data['quantity'].'"             // quantity - required
						);';
					}
				}
			}
		  $js .= 'pageTracker._trackTrans(); //submits transaction to the Analytics servers
			} catch(err) {}
			</script>
			';

	  } else if ($settings['ga_ecommerce'] == 'new') {

      $js = '<script type="text/javascript">
   			_gaq.push(["_addTrans",
		      "'.esc_attr($order->post_title).'",                  // order ID - required
		      "'.esc_attr(get_bloginfo('blogname')).'",            // affiliation or store name
		      "'.$order->mp_order_total.'",                        // total - required
		      "'.$order->mp_tax_total.'",                          // tax
		      "'.$order->mp_shipping_total.'",                     // shipping
		      "'.esc_attr($order->mp_shipping_info['city']).'",    // city
		      "'.esc_attr($order->mp_shipping_info['state']).'",   // state or province
		      "'.esc_attr($order->mp_shipping_info['country']).'"  // country
		    ]);';

			if (is_array($order->mp_cart_info) && count($order->mp_cart_info)) {
				foreach ($order->mp_cart_info as $product_id => $variations) {
					foreach ($variations as $variation => $data) {
						$sku = !empty($data['SKU']) ? esc_attr($data['SKU']) : $product_id;
						$js .= '_gaq.push(["_addItem",
						  "'.esc_attr($order->post_title).'", // order ID - necessary to associate item with transaction
						  "'.$sku.'",                         // SKU/code - required
						  "'.esc_attr($data['name']).'",      // product name
						  "",                                 // category
						  "'.$data['price'].'",               // unit price - required
						  "'.$data['quantity'].'"             // quantity - required
						]);';
					}
				}
			}
		  $js .= '_gaq.push(["_trackTrans"]);
			</script>
			';

		}

		//add to footer
		if ( !empty($js) ) {
		  $function = "echo '$js';";
      add_action( 'wp_footer', create_function('', $function), 99999 );
		}
	}

  //displays the detail page of an order
  function single_order_page() {
    $order = $this->get_order((int)$_GET['order_id']);

    if ( !$order )
      wp_die(__('Invalid Order ID', 'mp'));

    $settings = get_option('mp_settings');
		$max_downloads = intval($settings['max_downloads']) ? intval($settings['max_downloads']) : 5;
    ?>
    <div class="wrap">
    <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/shopping-cart.png'; ?>" /></div>
    <h2><?php echo sprintf(__('Order Details (%s)', 'mp'), esc_attr($order->post_title)); ?></h2>

    <form id="mp-single-order-form" action="<?php echo admin_url('edit.php'); ?>" method="get">
    <div id="poststuff" class="metabox-holder mp-settings has-right-sidebar">

    <div id="side-info-column" class="inner-sidebar">
    <div id='side-sortables' class='meta-box-sortables'>

      <div id="submitdiv" class="postbox mp-order-actions">
        <h3 class='hndle'><span><?php _e('Order Actions', 'mp'); ?></span></h3>
        <div class="inside">
        <div id="submitpost" class="submitbox">
        <div class="misc-pub-section"><strong><?php _e('Change Order Status:', 'mp'); ?></strong></div>
        <?php
        $actions = array();
        if ($order->post_status == 'order_received') {
          $actions['received current'] = __('Received', 'mp');
          $actions['paid'] = "<a title='" . esc_attr(__('Mark as Paid', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=paid&amp;post=' . $order->ID), 'update-order-status' ) . "'>" . __('Paid', 'mp') . "</a>";
          $actions['shipped'] = "<a title='" . esc_attr(__('Mark as Shipped', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=shipped&amp;post=' . $order->ID), 'update-order-status' ) . "'>" . __('Shipped', 'mp') . "</a>";
          $actions['closed'] = "<a title='" . esc_attr(__('Mark as Closed', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=closed&amp;post=' . $order->ID), 'update-order-status' ) . "'>" . __('Closed', 'mp') . "</a>";
        } else if ($order->post_status == 'order_paid') {
          $actions['received'] = __('Received', 'mp');
          $actions['paid current'] = __('Paid', 'mp');
          $actions['shipped'] = "<a title='" . esc_attr(__('Mark as Shipped', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=shipped&amp;post=' . $order->ID), 'update-order-status' ) . "'>" . __('Shipped', 'mp') . "</a>";
          $actions['closed'] = "<a title='" . esc_attr(__('Mark as Closed', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=closed&amp;post=' . $order->ID), 'update-order-status' ) . "'>" . __('Closed', 'mp') . "</a>";
        } else if ($order->post_status == 'order_shipped') {
          $actions['received'] = __('Received', 'mp');
          $actions['paid'] = __('Paid', 'mp');
          $actions['shipped current'] = __('Shipped', 'mp');
          $actions['closed'] = "<a title='" . esc_attr(__('Mark as Closed', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=closed&amp;post=' . $order->ID), 'update-order-status' ) . "'>" . __('Closed', 'mp') . "</a>";
        } else if ($order->post_status == 'order_closed') {
          $actions['received'] = "<a title='" . esc_attr(__('Mark as Received', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=received&amp;post=' . $order->ID), 'update-order-status' ) . "'>" . __('Received', 'mp') . "</a>";
          $actions['paid'] = "<a title='" . esc_attr(__('Mark as Paid', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=paid&amp;post=' . $order->ID), 'update-order-status' ) . "'>" . __('Paid', 'mp') . "</a>";
          $actions['shipped'] = "<a title='" . esc_attr(__('Mark as Shipped', 'mp')) . "' href='" . wp_nonce_url( admin_url( 'edit.php?post_type=product&amp;page=marketpress-orders&amp;action=shipped&amp;post=' . $order->ID), 'update-order-status' ) . "'>" . __('Shipped', 'mp') . "</a>";
          $actions['closed current'] = __('Closed', 'mp');
        }

        $action_count = count($actions);
        $i = 0;
  			echo '<div id="mp-single-statuses" class="misc-pub-section">';
  			foreach ( $actions as $action => $link ) {
  				++$i;
  				( $i == $action_count ) ? $sep = '' : $sep = ' &raquo; ';
  				echo "<span class='$action'>$link</span>$sep";
  			}
  			echo '</div>';
        ?>

          <div id="major-publishing-actions">

            <div id="mp-single-order-buttons">
              <input type="hidden" name="post_type" class="post_status_page" value="product" />
              <input type="hidden" name="page" class="post_status_page" value="marketpress-orders" />
              <input name="save" class="button-primary" id="publish" tabindex="1" value="<?php _e('&laquo; Back', 'mp'); ?>" type="submit" />
            </div>
            <div class="clear"></div>
          </div>
        </div>
        </div>
      </div>

      <div id="mp-order-status" class="postbox">
        <h3 class='hndle'><span><?php _e('Current Status', 'mp'); ?></span></h3>
        <div class="inside">
          <?php
          //get times
          $received = date(get_option('date_format') . ' - ' . get_option('time_format'), $order->mp_received_time);
          if ($order->mp_paid_time)
            $paid = date(get_option('date_format') . ' - ' . get_option('time_format'), $order->mp_paid_time);
          if ($order->mp_shipped_time)
            $shipped = date(get_option('date_format') . ' - ' . get_option('time_format'), $order->mp_shipped_time);
          if ($order->mp_closed_time)
            $closed = date(get_option('date_format') . ' - ' . get_option('time_format'), $order->mp_closed_time);

          if ($order->post_status == 'order_received') {
            echo '<div id="major-publishing-actions" class="misc-pub-section">' . __('Received:', 'mp') . ' <strong>' . $received . '</strong></div>';
          } else if ($order->post_status == 'order_paid') {
            echo '<div id="major-publishing-actions" class="misc-pub-section">' . __('Paid:', 'mp') . ' <strong>' . $paid . '</strong></div>';
            echo '<div class="misc-pub-section">' . __('Received:', 'mp') . ' <strong>' . $received . '</strong></div>';
          } else if ($order->post_status == 'order_shipped') {
            echo '<div id="major-publishing-actions" class="misc-pub-section">' . __('Shipped:', 'mp') . ' <strong>' . $shipped . '</strong></div>';
            echo '<div class="misc-pub-section">' . __('Paid:', 'mp') . ' <strong>' . $paid . '</strong></div>';
            echo '<div class="misc-pub-section">' . __('Received:', 'mp') . ' <strong>' . $received . '</strong></div>';
          } else if ($order->post_status == 'order_closed') {
            echo '<div id="major-publishing-actions" class="misc-pub-section">' . __('Closed:', 'mp') . ' <strong>' . $closed . '</strong></div>';
            echo '<div class="misc-pub-section">' . __('Shipped:', 'mp') . ' <strong>' . $shipped . '</strong></div>';
            echo '<div class="misc-pub-section">' . __('Paid:', 'mp') . ' <strong>' . $paid . '</strong></div>';
            echo '<div class="misc-pub-section">' . __('Received:', 'mp') . ' <strong>' . $received . '</strong></div>';
          }
          ?>
        </div>
      </div>

      <div id="mp-order-payment" class="postbox">
        <h3 class='hndle'><span><?php _e('Payment Information', 'mp'); ?></span></h3>
        <div class="inside">
          <div id="mp_payment_gateway" class="misc-pub-section">
            <?php _e('Payment Gateway:', 'mp'); ?>
            <strong><?php echo $order->mp_payment_info['gateway_private_name']; ?></strong>
          </div>
					<?php if ($order->mp_payment_info['method']) { ?>
          <div id="mp_payment_method" class="misc-pub-section">
            <?php _e('Payment Type:', 'mp'); ?>
            <strong><?php echo $order->mp_payment_info['method']; ?></strong>
          </div>
          <?php } ?>
          <?php if ($order->mp_payment_info['transaction_id']) { ?>
          <div id="mp_transaction" class="misc-pub-section">
            <?php _e('Transaction ID:', 'mp'); ?>
            <strong><?php echo $order->mp_payment_info['transaction_id']; ?></strong>
          </div>
          <?php } ?>
          <div id="major-publishing-actions" class="misc-pub-section">
            <?php _e('Payment Total:', 'mp'); ?>
            <strong><?php echo $this->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total']) . ' ' . $order->mp_payment_info['currency']; ?></strong>
          </div>
        </div>
      </div>

      <?php if (is_array($order->mp_payment_info['status']) && count($order->mp_payment_info['status'])) { ?>
      <div id="mp-order-payment-history" class="postbox">
        <h3 class='hndle'><span><?php _e('Payment Transaction History', 'mp'); ?></span></h3>
        <div class="inside">
        <?php
        $statuses = $order->mp_payment_info['status'];
        krsort($statuses); //sort with latest status at the top
        $first = true;
        foreach ($statuses as $timestamp => $status) {
          if ($first) {
            echo '<div id="major-publishing-actions" class="misc-pub-section">';
            $first = false;
          } else {
            echo '<div id="mp_payment_gateway" class="misc-pub-section">';
          }
          ?>
            <strong><?php echo date(get_option('date_format') . ' - ' . get_option('time_format'), $timestamp); ?>:</strong>
            <?php echo htmlentities($status); ?>
          </div>
        <?php } ?>

        </div>
      </div>
      <?php } ?>

    </div></div>

    <div id="post-body">
    <div id="post-body-content">

    <div id='normal-sortables' class='meta-box-sortables'>

      <div id="mp-order-products" class="postbox">
        <h3 class='hndle'><span><?php _e('Order Information', 'mp'); ?></span></h3>
        <div class="inside">

        <table id="mp-order-product-table" class="widefat">
          <thead><tr>
            <th class="mp_cart_col_thumb">&nbsp;</th>
            <th class="mp_cart_col_sku"><?php _e('SKU', 'mp'); ?></th>
            <th class="mp_cart_col_product"><?php _e('Item', 'mp'); ?></th>
            <th class="mp_cart_col_quant"><?php _e('Quantity', 'mp'); ?></th>
            <th class="mp_cart_col_price"><?php _e('Price', 'mp'); ?></th>
            <th class="mp_cart_col_subtotal"><?php _e('Subtotal', 'mp'); ?></th>
            <th class="mp_cart_col_downloads"><?php _e('Downloads', 'mp'); ?></th>
          </tr></thead>
          <tbody>
          <?php
          if (is_array($order->mp_cart_info) && count($order->mp_cart_info)) {
            foreach ($order->mp_cart_info as $product_id => $variations) {
							//for compatibility for old orders from MP 1.0
							if (isset($variations['name'])) {
              	$data = $variations;
                echo '<tr>';
	              echo '  <td class="mp_cart_col_thumb">' . mp_product_image( false, 'widget', $product_id ) . '</td>';
	              echo '  <td class="mp_cart_col_sku">' . esc_attr($data['SKU']) . '</td>';
	              echo '  <td class="mp_cart_col_product"><a href="' . get_permalink($product_id) . '">' . esc_attr($data['name']) . '</a></td>';
	              echo '  <td class="mp_cart_col_quant">' . number_format_i18n($data['quantity']) . '</td>';
	              echo '  <td class="mp_cart_col_price">' . $this->format_currency('', $data['price']) . '</td>';
	              echo '  <td class="mp_cart_col_subtotal">' . $this->format_currency('', $data['price'] * $data['quantity']) . '</td>';
	              echo '  <td class="mp_cart_col_downloads">' . __('N/A', 'mp') . '</td>';
	              echo '</tr>';
							} else {
								foreach ($variations as $variation => $data) {
		              echo '<tr>';
		              echo '  <td class="mp_cart_col_thumb">' . mp_product_image( false, 'widget', $product_id ) . '</td>';
		              echo '  <td class="mp_cart_col_sku">' . esc_attr($data['SKU']) . '</td>';
		              echo '  <td class="mp_cart_col_product"><a href="' . get_permalink($product_id) . '">' . esc_attr($data['name']) . '</a></td>';
		              echo '  <td class="mp_cart_col_quant">' . number_format_i18n($data['quantity']) . '</td>';
		              echo '  <td class="mp_cart_col_price">' . $this->format_currency('', $data['price']) . '</td>';
		              echo '  <td class="mp_cart_col_subtotal">' . $this->format_currency('', $data['price'] * $data['quantity']) . '</td>';
									if (is_array($data['download']))
									  echo '  <td class="mp_cart_col_downloads">' . number_format_i18n($data['download']['downloaded']) . (($data['download']['downloaded'] >= $max_downloads) ? __(' (Limit Reached)', 'mp') : '')  . '</td>';
									else
										echo '  <td class="mp_cart_col_downloads">' . __('N/A', 'mp') . '</td>';
		              echo '</tr>';
								}
							}
            }
          } else {
            echo '<tr><td colspan="7">' . __('No products could be found for this order', 'mp') . '</td></tr>';
          }
          ?>
          </tbody>
        </table><br />

        <?php //coupon line
        if ( $order->mp_discount_info ) { ?>
        <h3><?php _e('Coupon Discount:', 'mp'); ?></h3>
        <p><?php echo $order->mp_discount_info['discount']; ?> (<?php echo $order->mp_discount_info['code']; ?>)</p>
        <?php } ?>

        <?php //shipping line
        if ( $order->mp_shipping_total ) { ?>
        <h3><?php _e('Shipping:', 'mp'); ?></h3>
        <p><?php echo $this->format_currency('', $order->mp_shipping_total); ?></p>
        <?php } ?>

        <?php //tax line
        if ( $order->mp_tax_total ) { ?>
        <h3><?php _e('Taxes:', 'mp'); ?></h3>
        <p><?php echo $this->format_currency('', $order->mp_tax_total); ?></p>
        <?php } ?>

        <h3><?php _e('Cart Total:', 'mp'); ?></h3>
        <p><?php echo $this->format_currency('', $order->mp_order_total); ?></p>

        </div>
      </div>

      <div id="mp-order-shipping-info" class="postbox">
        <h3 class='hndle'><span><?php _e('Shipping Information', 'mp'); ?></span></h3>
        <div class="inside">
          <h3><?php _e('Address:', 'mp'); ?></h3>
          <table>
            <tr>
          	<td align="right"><?php _e('Full Name:', 'mp'); ?></td><td>
            <?php echo esc_attr($order->mp_shipping_info['name']); ?></td>
          	</tr>

            <tr>
          	<td align="right"><?php _e('Email:', 'mp'); ?></td><td>
            <?php echo esc_attr($order->mp_shipping_info['email']); ?></td>
          	</tr>

          	<tr>
          	<td align="right"><?php _e('Address:', 'mp'); ?></td>
            <td><?php echo esc_attr($order->mp_shipping_info['address1']); ?></td>
          	</tr>

            <?php if ($order->mp_shipping_info['address2']) { ?>
          	<tr>
          	<td align="right"><?php _e('Address 2:', 'mp'); ?></td>
            <td><?php echo esc_attr($order->mp_shipping_info['address2']); ?></td>
          	</tr>
            <?php } ?>

          	<tr>
          	<td align="right"><?php _e('City:', 'mp'); ?></td>
            <td><?php echo esc_attr($order->mp_shipping_info['city']); ?></td>
          	</tr>

          	<?php if ($order->mp_shipping_info['state']) { ?>
          	<tr>
          	<td align="right"><?php _e('State/Province/Region:', 'mp'); ?></td>
            <td><?php echo esc_attr($order->mp_shipping_info['state']); ?></td>
          	</tr>
            <?php } ?>

          	<tr>
          	<td align="right"><?php _e('Postal/Zip Code:', 'mp'); ?></td>
            <td><?php echo esc_attr($order->mp_shipping_info['zip']); ?></td>
          	</tr>

          	<tr>
          	<td align="right"><?php _e('Country:', 'mp'); ?></td>
            <td><?php echo $this->countries[$order->mp_shipping_info['country']]; ?></td>
          	</tr>

            <?php if ($order->mp_shipping_info['phone']) { ?>
          	<tr>
          	<td align="right"><?php _e('Phone Number:', 'mp'); ?></td>
            <td><?php echo esc_attr($order->mp_shipping_info['phone']); ?></td>
          	</tr>
            <?php } ?>
          </table>

          <h3><?php _e('Cost:', 'mp'); ?></h3>
          <p><?php echo $this->format_currency('', $order->mp_shipping_total); ?></p>

          <?php //note line if set by gateway
          if ( $order->mp_payment_info['note'] ) { ?>
          <h3><?php _e('Special Note:', 'mp'); ?></h3>
          <p><?php echo htmlentities($order->mp_payment_info['note']); ?></p>
          <?php } ?>

          <?php do_action('mp_single_order_display_shipping', $order); ?>

        </div>
      </div>

      <?php do_action('mp_single_order_display_box', $order); ?>

    </div>

    <div id='advanced-sortables' class='meta-box-sortables'>
    </div>

    </div>
    </div>
    <br class="clear" />
    </div><!-- /poststuff -->
    </form>
    </div><!-- /wrap -->
    <?php
  }

  function orders_page() {

    //load single order view if id is set
    if (isset($_GET['order_id'])) {
      $this->single_order_page();
      return;
    }

    //force post type
    global $wpdb, $post_type, $wp_query, $wp_locale, $current_screen;
    $post_type = 'mp_order';
    $_GET['post_type'] = $post_type;

    $post_type_object = get_post_type_object($post_type);

    if ( !current_user_can($post_type_object->cap->edit_posts) )
    	wp_die(__('Cheatin&#8217; uh?'));

    $pagenum = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0;
    if ( empty($pagenum) )
    	$pagenum = 1;
    $per_page = 'edit_' . $post_type . '_per_page';
    $per_page = (int) get_user_option( $per_page );
    if ( empty( $per_page ) || $per_page < 1 )
    	$per_page = 15;
    // @todo filter based on type
    $per_page = apply_filters( 'edit_posts_per_page', $per_page );

    // Handle bulk actions
    if ( isset($_GET['doaction']) || isset($_GET['doaction2']) || isset($_GET['bulk_edit']) || isset($_GET['action']) ) {
    	check_admin_referer('update-order-status');
    	$sendback = remove_query_arg( array('received', 'paid', 'shipped', 'closed', 'ids'), wp_get_referer() );

    	if ( ( $_GET['action'] != -1 || $_GET['action2'] != -1 ) && ( isset($_GET['post']) || isset($_GET['ids']) ) ) {
    		$post_ids = isset($_GET['post']) ? array_map( 'intval', (array) $_GET['post'] ) : explode(',', $_GET['ids']);
    		$doaction = ($_GET['action'] != -1) ? $_GET['action'] : $_GET['action2'];
    	}

    	switch ( $doaction ) {
    		case 'received':
    			$received = 0;
    			foreach( (array) $post_ids as $post_id ) {
    				$this->update_order_status($post_id, 'received');
    				$received++;
    			}
    			$msg = sprintf( _n( '%s order marked as Received.', '%s orders marked as Received.', $received, 'mp' ), number_format_i18n( $received ) );
    			break;
        case 'paid':
    			$paid = 0;
    			foreach( (array) $post_ids as $post_id ) {
    				$this->update_order_status($post_id, 'paid');
    				$paid++;
    			}
    			$msg = sprintf( _n( '%s order marked as Paid.', '%s orders marked as Paid.', $paid, 'mp' ), number_format_i18n( $paid ) );
    			break;
        case 'shipped':
    			$shipped = 0;
    			foreach( (array) $post_ids as $post_id ) {
    				$this->update_order_status($post_id, 'shipped');
    				$shipped++;
    			}
    			$msg = sprintf( _n( '%s order marked as Shipped.', '%s orders marked as Shipped.', $shipped, 'mp' ), number_format_i18n( $shipped ) );
          break;
        case 'closed':
    			$closed = 0;
    			foreach( (array) $post_ids as $post_id ) {
    				$this->update_order_status($post_id, 'closed');
    				$closed++;
    			}
    			$msg = sprintf( _n( '%s order Closed.', '%s orders Closed.', $closed, 'mp' ), number_format_i18n( $closed ) );
    			break;

    	}

    }

    $avail_post_stati = wp_edit_posts_query();

    $num_pages = $wp_query->max_num_pages;

    $mode = 'list';
    ?>

    <div class="wrap">
    <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/shopping-cart.png'; ?>" /></div>
    <h2><?php _e('Manage Orders', 'mp');
    if ( isset($_GET['s']) && $_GET['s'] )
    	printf( '<span class="subtitle">' . __('Search results for &#8220;%s&#8221;') . '</span>', get_search_query() ); ?>
    </h2>

    <?php if ( isset($msg) ) { ?>
    <div class="updated fade"><p>
    <?php echo $msg; ?>
    </p></div>
    <?php } ?>

    <form id="posts-filter" action="<?php echo admin_url('edit.php'); ?>" method="get">

    <ul class="subsubsub">
    <?php
    if ( empty($locked_post_status) ) :
      $status_links = array();
      $num_posts = wp_count_posts( $post_type, 'readable' );
      $class = '';
      $allposts = '';

      $total_posts = array_sum( (array) $num_posts );

      // Subtract post types that are not included in the admin all list.
      foreach ( get_post_stati( array('show_in_admin_all_list' => false) ) as $state )
      	$total_posts -= $num_posts->$state;

      $class = empty($class) && empty($_GET['post_status']) ? ' class="current"' : '';
      $status_links[] = "<li><a href='edit.php?page=marketpress-orders&post_type=product{$allposts}'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_posts, 'posts' ), number_format_i18n( $total_posts ) ) . '</a>';

      foreach ( get_post_stati(array('post_type' => 'mp_order'), 'objects') as $status ) {
      	$class = '';

      	$status_name = $status->name;

      	if ( !in_array( $status_name, $avail_post_stati ) )
      		continue;

      	if ( empty( $num_posts->$status_name ) )
      		continue;

      	if ( isset($_GET['post_status']) && $status_name == $_GET['post_status'] )
      		$class = ' class="current"';

      	$status_links[] = "<li><a href='edit.php?page=marketpress-orders&amp;post_status=$status_name&amp;post_type=product'$class>" . sprintf( _n( $status->label_count[0], $status->label_count[1], $num_posts->$status_name ), number_format_i18n( $num_posts->$status_name ) ) . '</a>';
      }
      echo implode( " |</li>\n", $status_links ) . '</li>';
      unset( $status_links );
    endif;
    ?>
    </ul>

      <p class="search-box">
      	<label class="screen-reader-text" for="post-search-input"><?php _e('Search Orders', 'mp'); ?>:</label>
      	<input type="text" id="post-search-input" name="s" value="<?php the_search_query(); ?>" />
      	<input type="submit" value="<?php _e('Search Orders', 'mp'); ?>" class="button" />
      </p>

      <input type="hidden" name="post_type" class="post_status_page" value="product" />
      <input type="hidden" name="page" class="post_status_page" value="marketpress-orders" />
      <?php if (!empty($_GET['post_status'])) { ?>
      <input type="hidden" name="post_status" class="post_status_page" value="<?php echo esc_attr($_GET['post_status']); ?>" />
      <?php } ?>

      <?php if ( have_posts() ) { ?>

      <div class="tablenav">
      <?php
      $page_links = paginate_links( array(
      	'base' => add_query_arg( 'paged', '%#%' ),
      	'format' => '',
      	'prev_text' => __('&laquo;'),
      	'next_text' => __('&raquo;'),
      	'total' => $num_pages,
      	'current' => $pagenum
      ));

      ?>

      <div class="alignleft actions">
      <select name="action">
      <option value="-1" selected="selected"><?php _e('Change Status', 'mp'); ?></option>
      <option value="received"><?php _e('Received', 'mp'); ?></option>
      <option value="paid"><?php _e('Paid', 'mp'); ?></option>
      <option value="shipped"><?php _e('Shipped', 'mp'); ?></option>
      <option value="closed"><?php _e('Closed', 'mp'); ?></option>
      </select>
      <input type="submit" value="<?php esc_attr_e('Apply'); ?>" name="doaction" id="doaction" class="button-secondary action" />
      <?php wp_nonce_field('update-order-status'); ?>

      <?php // view filters
      if ( !is_singular() ) {
      $arc_query = $wpdb->prepare("SELECT DISTINCT YEAR(post_date) AS yyear, MONTH(post_date) AS mmonth FROM $wpdb->posts WHERE post_type = %s ORDER BY post_date DESC", $post_type);

      $arc_result = $wpdb->get_results( $arc_query );

      $month_count = count($arc_result);

      if ( $month_count && !( 1 == $month_count && 0 == $arc_result[0]->mmonth ) ) {
      $m = isset($_GET['m']) ? (int)$_GET['m'] : 0;
      ?>
      <select name='m'>
      <option<?php selected( $m, 0 ); ?> value='0'><?php _e('Show all dates'); ?></option>
      <?php
      foreach ($arc_result as $arc_row) {
      	if ( $arc_row->yyear == 0 )
      		continue;
      	$arc_row->mmonth = zeroise( $arc_row->mmonth, 2 );

      	if ( $arc_row->yyear . $arc_row->mmonth == $m )
      		$default = ' selected="selected"';
      	else
      		$default = '';

      	echo "<option$default value='" . esc_attr("$arc_row->yyear$arc_row->mmonth") . "'>";
      	echo $wp_locale->get_month($arc_row->mmonth) . " $arc_row->yyear";
      	echo "</option>\n";
      }
      ?>
      </select>
      <?php } ?>

      <input type="submit" id="post-query-submit" value="<?php esc_attr_e('Filter'); ?>" class="button-secondary" />
      <?php } ?>
      </div>

      <?php if ( $page_links ) { ?>
      <div class="tablenav-pages"><?php
      	$count_posts = $post_type_object->hierarchical ? $wp_query->post_count : $wp_query->found_posts;
      	$page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
      						number_format_i18n( ( $pagenum - 1 ) * $per_page + 1 ),
      						number_format_i18n( min( $pagenum * $per_page, $count_posts ) ),
      						number_format_i18n( $count_posts ),
      						$page_links
      						);
      	echo $page_links_text;
      	?></div>
      <?php } ?>

      <div class="clear"></div>
      </div>

      <div class="clear"></div>

      <table class="widefat <?php echo $post_type_object->hierarchical ? 'page' : 'post'; ?> fixed" cellspacing="0">
      	<thead>
      	<tr>
      <?php print_column_headers( $current_screen ); ?>
      	</tr>
      	</thead>

      	<tfoot>
      	<tr>
      <?php print_column_headers($current_screen, false); ?>
      	</tr>
      	</tfoot>

      	<tbody>
      <?php
        if ( function_exists('post_rows') ) {
          post_rows();
        } else {
          $wp_list_table = _get_list_table('WP_Posts_List_Table');
          $wp_list_table->display_rows();
        }
       ?>
      	</tbody>
      </table>

      <div class="tablenav">

      <?php
      if ( $page_links )
      	echo "<div class='tablenav-pages'>$page_links_text</div>";
      ?>

      <div class="alignleft actions">
      <select name="action2">
      <option value="-1" selected="selected"><?php _e('Change Status', 'mp'); ?></option>
      <option value="received"><?php _e('Received', 'mp'); ?></option>
      <option value="paid"><?php _e('Paid', 'mp'); ?></option>
      <option value="shipped"><?php _e('Shipped', 'mp'); ?></option>
      <option value="closed"><?php _e('Closed', 'mp'); ?></option>
      </select>
      <input type="submit" value="<?php esc_attr_e('Apply'); ?>" name="doaction2" id="doaction2" class="button-secondary action" />
      <br class="clear" />
      </div>
      <br class="clear" />
      </div>

      <?php } else { // have_posts() ?>
      <div class="clear"></div>
      <p><?php _e('No Orders Yet', 'mp'); ?></p>
      <?php } ?>

      </form>

      <br class="clear">
    </div>
    <?php
  }

  function admin_page() {
    global $wpdb;

    //double-check rights
    if(!current_user_can('manage_options')) {
  		echo "<p>" . __('Nice Try...', 'mp') . "</p>";  //If accessed properly, this message doesn't appear.
  		return;
  	}

    $settings = get_option('mp_settings');
    ?>
    <div class="wrap">
    <h3 class="nav-tab-wrapper">
    <?php
    $tab = ( !empty($_GET['tab']) ) ? $_GET['tab'] : 'main';

    if (!$settings['disable_cart']) {
    	$tabs = array(
        'coupons'       => __('Coupons', 'mp'),
    		'presentation'  => __('Presentation', 'mp'),
    		'messages'      => __('Messages', 'mp'),
    		'shipping'      => __('Shipping', 'mp'),
    		'gateways'      => __('Payments', 'mp'),
    		'shortcodes'    => __('Shortcodes', 'mp')
    	);
    } else {
      $tabs = array( 'presentation'  => __('Presentation', 'mp') );
    }
  	$tabhtml = array();

    // If someone wants to remove or add a tab
  	$tabs = apply_filters( 'marketpress_tabs', $tabs );

  	$class = ( 'main' == $tab ) ? ' nav-tab-active' : '';
  	$tabhtml[] = '	<a href="' . admin_url( 'edit.php?post_type=product&amp;page=marketpress' ) . '" class="nav-tab'.$class.'">' . __('General', 'mp') . '</a>';

  	foreach ( $tabs as $stub => $title ) {
  		$class = ( $stub == $tab ) ? ' nav-tab-active' : '';
  		$tabhtml[] = '	<a href="' . admin_url( 'edit.php?post_type=product&amp;page=marketpress&amp;tab=' . $stub ) . '" class="nav-tab'.$class.'">'.$title.'</a>';
  	}

  	echo implode( "\n", $tabhtml );
    ?>
  	</h3>
  	<div class="clear"></div>

  	<?php
  	switch( $tab ) {
  		//---------------------------------------------------//
  		case "main":

        //save settings
        if (isset($_POST['marketplace_settings'])) {

          //allow plugins to verify settings before saving
          $tax_rate = $_POST['mp']['tax']['rate'] * .01;
          $_POST['mp']['tax']['rate'] = ($tax_rate < 1 && $tax_rate >= 0) ? $tax_rate : 0;
          $settings = array_merge($settings, apply_filters('mp_main_settings_filter', $_POST['mp']));
          update_option('mp_settings', $settings);

          echo '<div class="updated fade"><p>'.__('Settings saved.', 'mp').'</p></div>';
        }
        ?>
        <script type="text/javascript">
      	  jQuery(document).ready(function($) {
            $("#mp-country-select, #mp-currency-select").change(function() {
              $("#mp-main-form").submit();
        		});
          });
      	</script>
        <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/settings.png'; ?>" /></div>
        <h2><?php _e('General Settings', 'mp'); ?></h2>
        <div id="poststuff" class="metabox-holder mp-settings">

        <form id="mp-main-form" method="post" action="edit.php?post_type=product&amp;page=marketpress&amp;tab=main">
          <input type="hidden" name="marketplace_settings" value="1" />

          <div class="postbox">
            <h3 class='hndle'><span><?php _e('Location Settings', 'mp') ?></span></h3>
            <div class="inside">
              <span class="description"><?php _e('This is the base location that shipping and tax rates will be calculated from.', 'mp') ?></span>
              <table class="form-table">
                <tr>
        				<th scope="row"><?php _e('Base Country', 'mp') ?></th>
        				<td>
                  <select id="mp-country-select" name="mp[base_country]">
                    <?php
                    foreach ($this->countries as $key => $value) {
                      ?><option value="<?php echo $key; ?>"<?php selected($settings['base_country'], $key); ?>><?php echo esc_attr($value); ?></option><?php
                    }
                    ?>
                  </select>
          				</td>
                </tr>

              <?php
              switch ($settings['base_country']) {
                case 'US':
                  $list = $this->usa_states;
                  break;

                case 'CA':
                  $list = $this->canadian_provinces;
                  break;

                case 'GB':
                  $list = $this->uk_counties;
                  break;

                case 'AU':
                  $list = $this->australian_states;
                  break;

                default:
                  $list = false;
              }

              //only show if correct country
              if (is_array($list)) {
              ?>
                <tr>
        				<th scope="row"><?php _e('Base State/Province/Region', 'mp') ?></th>
        				<td>
                  <select name="mp[base_province]">
                    <?php
                    foreach ($list as $key => $value) {
                      ?><option value="<?php echo esc_attr($key); ?>"<?php selected($settings['base_province'], $key); ?>><?php echo esc_attr($value); ?></option><?php
                    }
                    ?>
                  </select>
          				</td>
                </tr>
              <?php } ?>
              </table>
            </div>
          </div>

          <div class="postbox">
            <h3 class='hndle'><span><?php _e('Tax Settings', 'mp') ?></span></h3>
            <div class="inside">
              <table class="form-table">
              <?php
              switch ($settings['base_country']) {
                case 'US':
                  ?>
                  <tr>
          				<th scope="row"><?php echo sprintf(__('%s Tax Rate', 'mp'), esc_attr($this->usa_states[$settings['base_province']])); ?></th>
          				<td>
                  <input value="<?php echo $settings['tax']['rate'] * 100; ?>" size="3" name="mp[tax][rate]" type="text" style="text-align:right;" />%
            			</td>
                  </tr>
                  <?php
                  break;

                case 'CA':
                  ?>
                  <tr>
          				<th scope="row"><?php echo sprintf(__('%s Total Tax Rate (VAT,GST,PST,HST)', 'mp'), esc_attr($this->canadian_provinces[$settings['base_province']])); ?></th>
          				<td>
                  <input value="<?php echo $settings['tax']['rate'] * 100; ?>" size="3" name="mp[tax][rate]" type="text" style="text-align:right;" />%
            			</td>
                  </tr>
                  <?php
                  break;

                case 'GB':
                  ?>
                  <tr>
          				<th scope="row"><?php _e('VAT Tax Rate', 'mp') ?></th>
          				<td>
                  <input value="<?php echo $settings['tax']['rate'] * 100; ?>" size="3" name="mp[tax][rate]" type="text" style="text-align:right;" />%
            			</td>
                  </tr>
                  <?php
                  break;

                case 'AU':
                  ?>
                  <tr>
          				<th scope="row"><?php _e('GST Tax Rate', 'mp') ?></th>
          				<td>
                  <input value="<?php echo $settings['tax']['rate'] * 100; ?>" size="3" name="mp[tax][rate]" type="text" style="text-align:right;" />%
            			</td>
                  </tr>
                  <?php
                  break;

                default:
                  //in european union
                  if ( in_array($settings['base_country'], $this->eu_countries) ) {
                    ?>
                    <tr>
            				<th scope="row"><?php _e('VAT Tax Rate', 'mp') ?></th>
            				<td>
                    <input value="<?php echo $settings['tax']['rate'] * 100; ?>" size="3" name="mp[tax][rate]" type="text" style="text-align:right;" />%
                    </td>
                    </tr>
                    <?php
                  } else { //all other countries
                    ?>
                    <tr>
            				<th scope="row"><?php _e('Country Total Tax Rate (VAT, GST, Etc.)', 'mp') ?></th>
            				<td>
                    <input value="<?php echo $settings['tax']['rate'] * 100; ?>" size="3" name="mp[tax][rate]" type="text" style="text-align:right;" />%
                    </td>
                    </tr>
                    <tr>
            				<th scope="row"><?php _e('Tax Orders Outside Your Base Country?', 'mp'); ?></th>
            				<td>
            				<label><input value="1" name="mp[tax][tax_outside]" type="radio"<?php checked($settings['tax']['tax_outside'], 1) ?> /> <?php _e('Yes', 'mp') ?></label>
                    <label><input value="0" name="mp[tax][tax_outside]" type="radio"<?php checked($settings['tax']['tax_outside'], 0) ?> /> <?php _e('No', 'mp') ?></label>
              			</td>
                    </tr>
                    <?php
                  }
                  break;
              }
              ?>
                <tr>
        				<th scope="row"><?php _e('Apply Tax To Shipping Fees?', 'mp') ?></th>
                <td>
        				<label><input value="1" name="mp[tax][tax_shipping]" type="radio"<?php checked($settings['tax']['tax_shipping'], 1) ?> /> <?php _e('Yes', 'mp') ?></label>
                <label><input value="0" name="mp[tax][tax_shipping]" type="radio"<?php checked($settings['tax']['tax_shipping'], 0) ?> /> <?php _e('No', 'mp') ?></label>
                <br /><span class="description"><?php _e('Please see your local tax laws. Most areas charge tax on shipping fees.', 'mp') ?></span>
          			</td>
                </tr>
                <?php /* ?>
                <tr>
        				<th scope="row"><?php _e('Show Prices Inclusive of Tax?', 'mp') ?></th>
                <td>
                <?php $tax_inclusive = isset($settings['tax']['tax_inclusive']) ? $settings['tax']['tax_inclusive'] : 0; ?>
        				<label><input value="1" name="mp[tax][tax_inclusive]" type="radio"<?php checked($tax_inclusive, 1) ?> /> <?php _e('Yes', 'mp') ?></label>
                <label><input value="0" name="mp[tax][tax_inclusive]" type="radio"<?php checked($tax_inclusive, 0) ?> /> <?php _e('No', 'mp') ?></label>
                <br /><span class="description"><?php _e('Please see your local tax laws.', 'mp') ?></span>
          			</td>
                </tr>
                <?php */ ?>
              </table>
            </div>
          </div>

          <div class="postbox">
            <h3 class='hndle'><span><?php _e('Currency Settings', 'mp') ?></span></h3>
            <div class="inside">
              <span class="description"><?php _e('These preferences affect display only. Your payment gateway of choice may not support every currency listed here.', 'mp') ?></span>
              <table class="form-table">
        				<tr valign="top">
                <th scope="row"><?php _e('Store Currency', 'mp') ?></th>
        				<td>
                  <select id="mp-currency-select" name="mp[currency]">
                    <?php
                    foreach ($this->currencies as $key => $value) {
                      ?><option value="<?php echo $key; ?>"<?php selected($settings['currency'], $key); ?>><?php echo esc_attr($value[0]) . ' - ' . $this->format_currency($key); ?></option><?php
                    }
                    ?>
                  </select>
          				</td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Currency Symbol Position', 'mp') ?></th>
                <td>
                <label><input value="1" name="mp[curr_symbol_position]" type="radio"<?php checked($settings['curr_symbol_position'], 1); ?>>
        				<?php echo $this->format_currency($settings['currency']); ?>100</label><br />
        				<label><input value="2" name="mp[curr_symbol_position]" type="radio"<?php checked($settings['curr_symbol_position'], 2); ?>>
        				<?php echo $this->format_currency($settings['currency']); ?> 100</label><br />
        				<label><input value="3" name="mp[curr_symbol_position]" type="radio"<?php checked($settings['curr_symbol_position'], 3); ?>>
        				100<?php echo $this->format_currency($settings['currency']); ?></label><br />
        				<label><input value="4" name="mp[curr_symbol_position]" type="radio"<?php checked($settings['curr_symbol_position'], 4); ?>>
        				100 <?php echo $this->format_currency($settings['currency']); ?></label>
                </td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Show Decimal in Prices', 'mp') ?></th>
                <td>
                <label><input value="1" name="mp[curr_decimal]" type="radio"<?php checked( ( ($settings['curr_decimal'] !== 0) ? 1 : 0 ), 1); ?>>
        				<?php _e('Yes', 'mp') ?></label>
        				<label><input value="0" name="mp[curr_decimal]" type="radio"<?php checked($settings['curr_decimal'], 0); ?>>
        				<?php _e('No', 'mp') ?></label>
                </td>
                </tr>
              </table>
            </div>
          </div>

          <div class="postbox">
            <h3 class='hndle'><span><?php _e('Miscellaneous Settings', 'mp') ?></span></h3>
            <div class="inside">
              <table class="form-table">

                <tr id="mp-downloads-setting">
                <th scope="row"><?php _e('Maximum Downloads', 'mp') ?></th>
        				<td>
        				<span class="description"><?php _e('How many times may a customer download a file they have purchased? (It\'s best to set this higher than one in case they have any problems downloading)', 'mp') ?></span><br />
                <select name="mp[max_downloads]">
								<?php
								$max_downloads = intval($settings['max_downloads']) ? intval($settings['max_downloads']) : 5;
								for ($i=1; $i<=100; $i++) {
                  $selected = ($max_downloads == $i) ? ' selected="selected"' : '';
			            echo '<option value="' . $i . '"' . $selected . '">' . $i . '</option>';
			    			}
								?>
								</select>
								</td>
                </tr>
                <tr>
                <th scope="row"><?php _e('Force Login', 'mp') ?></th>
        				<td>
        				<?php $force_login = ($settings['force_login']) ? 1 : 0; ?>
        				<label><input value="1" name="mp[force_login]" type="radio"<?php checked($force_login, 1) ?> /> <?php _e('Yes', 'mp') ?></label>
                <label><input value="0" name="mp[force_login]" type="radio"<?php checked($force_login, 0) ?> /> <?php _e('No', 'mp') ?></label>
                <br /><span class="description"><?php _e('Whether or not customers must be registered and logged in to checkout. (Not recommended: Enabling this can lower conversions)', 'mp') ?></span>
          			</td>
                </tr>
                <tr>
                <th scope="row"><?php _e('Product Listings Only', 'mp') ?></th>
        				<td>
        				<label><input value="1" name="mp[disable_cart]" type="radio"<?php checked($settings['disable_cart'], 1) ?> /> <?php _e('Yes', 'mp') ?></label>
                <label><input value="0" name="mp[disable_cart]" type="radio"<?php checked($settings['disable_cart'], 0) ?> /> <?php _e('No', 'mp') ?></label>
                <br /><span class="description"><?php _e('This option turns MarketPress into more of a product listing plugin, disabling shopping carts, checkout, and order management. This is useful if you simply want to list items you can buy in a store somewhere else, optionally linking the "Buy Now" buttons to an external site. Some examples are a car dealership, or linking to songs/albums in itunes, or linking to products on another site with your own affiliate links.', 'mp') ?></span>
          			</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Google Analytics Ecommerce Tracking', 'mp') ?></th>
                <td>
                <select name="mp[ga_ecommerce]">
									<option value="none"<?php selected($settings['ga_ecommerce'], 'none') ?>><?php _e('None', 'mp') ?></option>
									<option value="new"<?php selected($settings['ga_ecommerce'], 'new') ?>><?php _e('Asynchronous Tracking Code', 'mp') ?></option>
									<option value="old"<?php selected($settings['ga_ecommerce'], 'old') ?>><?php _e('Old Tracking Code', 'mp') ?></option>
								</select>
        				<br /><span class="description"><?php _e('If you already use Google Analytics for your website, you can track detailed ecommerce information by enabling this setting. Choose whether you are using the new asynchronous or old tracking code. Before Google Analytics can report ecommerce activity for your website, you must enable ecommerce tracking on the profile settings page for your website. Also keep in mind that some gateways do not reliably show the receipt page, so tracking may not be accurate in those cases. It is recommended to use the PayPal gateway for the most accurate data. <a href="http://analytics.blogspot.com/2009/05/how-to-use-ecommerce-tracking-in-google.html" target="_blank">More information &raquo;</a>', 'mp') ?></span>
          			</td>
                </tr>
              </table>
            </div>
          </div>

          <?php
          //for adding additional settings for a shipping module
          do_action('mp_general_settings');
          ?>

          <p class="submit">
            <input type="submit" name="submit_settings" value="<?php _e('Save Changes', 'mp') ?>" />
          </p>
        </form>
        </div>
        <?php
        break;


  		//---------------------------------------------------//
  		case "coupons":

        $coupons = get_option('mp_coupons');

        //delete checked coupons
      	if (isset($_POST['allcoupon_delete'])) {
          //check nonce
          check_admin_referer('mp_coupons');

          if (is_array($_POST['coupons_checks'])) {
            //loop through and delete
            foreach ($_POST['coupons_checks'] as $del_code)
              unset($coupons[$del_code]);

            update_option('mp_coupons', $coupons);
            //display message confirmation
            echo '<div class="updated fade"><p>'.__('Coupon(s) succesfully deleted.', 'mp').'</p></div>';
          }
        }

        //save or add coupon
        if (isset($_POST['submit_settings'])) {
          //check nonce
          check_admin_referer('mp_coupons');

          $error = false;

          $new_coupon_code = preg_replace('/[^A-Z0-9_-]/', '', strtoupper($_POST['coupon_code']));
          if (!$new_coupon_code)
            $error[] = __('Please enter a valid Coupon Code', 'mp');

          $coupons[$new_coupon_code]['discount'] = round($_POST['discount'], 2);
          if ($coupons[$new_coupon_code]['discount'] <= 0)
            $error[] = __('Please enter a valid Discount Amount', 'mp');

          $coupons[$new_coupon_code]['discount_type'] = $_POST['discount_type'];
          if ($coupons[$new_coupon_code]['discount_type'] != 'amt' && $coupons[$new_coupon_code]['discount_type'] != 'pct')
            $error[] = __('Please choose a valid Discount Type', 'mp');

          $coupons[$new_coupon_code]['start'] = strtotime($_POST['start']);
          if ($coupons[$new_coupon_code]['start'] === false)
            $error[] = __('Please enter a valid Start Date', 'mp');

          $coupons[$new_coupon_code]['end'] = strtotime($_POST['end']);
          if ($coupons[$new_coupon_code]['end'] && $coupons[$new_coupon_code]['end'] < $coupons[$new_coupon_code]['start'])
            $error[] = __('Please enter a valid End Date not earlier than the Start Date', 'mp');

          $coupons[$new_coupon_code]['uses'] = (is_numeric($_POST['uses'])) ? (int)$_POST['uses'] : '';

          if (!$error) {
            update_option('mp_coupons', $coupons);
            $new_coupon_code = '';
            echo '<div class="updated fade"><p>'.__('Coupon succesfully saved.', 'mp').'</p></div>';
          }
        }

        //if editing a coupon
        if (isset($_GET['code'])) {
          $new_coupon_code = $_GET['code'];
        }

        ?>
        <script type="text/javascript">
      	  jQuery(document).ready(function ($) {
      	    jQuery.datepicker.setDefaults(jQuery.datepicker.regional['<?php echo $this->language; ?>']);
      		  jQuery('.pickdate').datepicker({dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true, minDate: 0, firstDay: <?php echo (get_option('start_of_week')=='0') ? 7 : get_option('start_of_week'); ?>});
      		});
      	</script>
        <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/service.png'; ?>" /></div>
        <h2><?php _e('Coupons', 'mp') ?></h2>
        <p><?php _e('You can create, delete, or update coupon codes for your store here.', 'mp') ?></p>

        <?php
        $apage = isset( $_GET['apage'] ) ? intval( $_GET['apage'] ) : 1;
    		$num = isset( $_GET['num'] ) ? intval( $_GET['num'] ) : 10;

    		$coupon_list = get_option('mp_coupons');
    		$total = (is_array($coupon_list)) ? count($coupon_list) : 0;

        if ($total)
          $coupon_list = array_slice($coupon_list, intval(($apage-1) * $num), intval($num));

    		$coupon_navigation = paginate_links( array(
    			'base' => add_query_arg( 'apage', '%#%' ).$url2,
    			'format' => '',
    			'total' => ceil($total / $num),
    			'current' => $apage
    		));
    		$page_link = ($apage > 1) ? '&amp;apage='.$apage : '';
    		?>

    		<form id="form-coupon-list" action="edit.php?post_type=product&amp;page=marketpress&amp;tab=coupons<?php echo $page_link; ?>" method="post">
        <?php wp_nonce_field('mp_coupons') ?>
    		<div class="tablenav">
    			<?php if ( $coupon_navigation ) echo "<div class='tablenav-pages'>$coupon_navigation</div>"; ?>

    			<div class="alignleft">
    				<input type="submit" value="<?php _e('Delete', 'mp') ?>" name="allcoupon_delete" class="button-secondary delete" />
    				<br class="clear" />
    			</div>
    		</div>

    		<br class="clear" />

    		<?php
    		// define the columns to display, the syntax is 'internal name' => 'display name'
    		$posts_columns = array(
    			'code'         => __('Coupon Code', 'mp'),
    			'discount'     => __('Discount', 'mp'),
    			'start'        => __('Start Date', 'mp'),
    			'end'          => __('Expire Date', 'mp'),
          'used'         => __('Used', 'mp'),
          'remaining'    => __('Remaining Uses', 'mp'),
    			'edit'         => __('Edit', 'mp')
    		);
    		?>

    		<table width="100%" cellpadding="3" cellspacing="3" class="widefat">
    			<thead>
    				<tr>
    				<th scope="col" class="check-column"><input type="checkbox" /></th>
    				<?php foreach($posts_columns as $column_id => $column_display_name) {
    					$col_url = $column_display_name;
    					?>
    					<th scope="col"><?php echo $col_url ?></th>
    				<?php } ?>
    				</tr>
    			</thead>
    			<tbody id="the-list">
    			<?php
    			if ( is_array($coupon_list) && count($coupon_list) ) {
    				$bgcolor = $class = '';
    				foreach ($coupon_list as $coupon_code => $coupon) {
    					$class = ('alternate' == $class) ? '' : 'alternate';

              //assign classes based on coupon availability
              $class = ($this->check_coupon($coupon_code)) ? $class . ' coupon-active' : $class . ' coupon-inactive';

    					echo '<tr class="'.$class.' blog-row">
                      <th scope="row" class="check-column">
    									<input type="checkbox" name="coupons_checks[]"" value="'.$coupon_code.'" />
    								  </th>';

    					foreach( $posts_columns as $column_name=>$column_display_name ) {
    						switch($column_name) {
    							case 'code': ?>
    								<th scope="row">
    									<?php echo $coupon_code; ?>
    								</th>
    							<?php
    							break;

    							case 'discount': ?>
    								<th scope="row">
    									<?php
    									if ($coupon['discount_type'] == 'pct') {
                        echo $coupon['discount'].'%';
                      } else if ($coupon['discount_type'] == 'amt') {
                        echo $this->format_currency('', $coupon['discount']);
                      }
                      ?>
    								</th>
    							<?php
    							break;

    							case 'start': ?>
    								<th scope="row">
                      <?php echo date_i18n( get_option('date_format'), $coupon['start'] ); ?>
    								</th>
    							<?php
    							break;

    							case 'end': ?>
    								<th scope="row">
    									<?php echo ($coupon['end']) ? date_i18n( get_option('date_format'), $coupon['end'] ) : __('No End', 'mp'); ?>
    								</th>
    							<?php
    							break;

    							case 'used': ?>
    								<th scope="row">
    									<?php echo ($coupon['used']) ? number_format_i18n($coupon['used']) : 0; ?>
    								</th>
    							<?php
    							break;

    							case 'remaining': ?>
    								<th scope="row">
    									<?php
                      if ($coupon['uses'])
                        echo number_format_i18n(intval($coupon['uses']) - intval($coupon['used']));
                      else
                        _e('Unlimited', 'mp');
                      ?>
    								</th>
    							<?php
    							break;

                  case 'edit': ?>
    								<th scope="row">
    									<a href="edit.php?post_type=product&amp;page=marketpress&amp;tab=coupons<?php echo $page_link; ?>&amp;code=<?php echo $coupon_code; ?>#add_coupon"><?php _e('Edit', 'mp') ?>&raquo;</a>
    								</th>
    							<?php
    							break;

    						}
    					}
    					?>
    					</tr>
    					<?php
    				}
    			} else { ?>
    				<tr style='background-color: <?php echo $bgcolor; ?>'>
    					<td colspan="7"><?php _e('No coupons yet.', 'mp') ?></td>
    				</tr>
    			<?php
    			} // end if coupons
    			?>

    			</tbody>
    			<tfoot>
    				<tr>
    				<th scope="col" class="check-column"><input type="checkbox" /></th>
    				<?php foreach($posts_columns as $column_id => $column_display_name) {
    					$col_url = $column_display_name;
    					?>
    					<th scope="col"><?php echo $col_url ?></th>
    				<?php } ?>
    				</tr>
    			</tfoot>
    		</table>

    		<div class="tablenav">
    			<?php if ( $coupon_navigation ) echo "<div class='tablenav-pages'>$coupon_navigation</div>"; ?>
    		</div>

    		<div id="poststuff" class="metabox-holder mp-settings">

    		<div class="postbox">
          <h3 class='hndle'><span>
          <?php
          if ( isset($_GET['code']) || $error ) {
            _e('Edit Coupon', 'mp');
          } else {
            _e('Add Coupon', 'mp');
          }
          ?></span></h3>
          <div class="inside">
            <?php
            //display error message if it exists
            if ($error) {
          		?><div class="error"><p><?php echo $error[0]; ?></p></div><?php
          	}

          	//setup defaults
          	if ($new_coupon_code) {
              $discount = ($coupons[$new_coupon_code]['discount'] && $coupons[$new_coupon_code]['discount_type'] == 'amt') ? round($coupons[$new_coupon_code]['discount'], 2) : $coupons[$new_coupon_code]['discount'];
              $discount_type = $coupons[$new_coupon_code]['discount_type'];
              $start = ($coupons[$new_coupon_code]['start']) ? date('Y-m-d', $coupons[$new_coupon_code]['start']) : date('Y-m-d');
              $end = ($coupons[$new_coupon_code]['end']) ? date('Y-m-d', $coupons[$new_coupon_code]['end']) : '';
              $uses = $coupons[$new_coupon_code]['uses'];
            }
          	?>
            <table id="add_coupon">
            <thead>
            <tr>
              <th>
              <?php _e('Coupon Code', 'mp') ?><br />
                <small style="font-weight: normal;"><?php _e('Letters and Numbers only', 'mp') ?></small>
                </th>
              <th><?php _e('Discount', 'mp') ?></th>
              <th><?php _e('Start Date', 'mp') ?></th>
              <th>
                <?php _e('Expire Date', 'mp') ?><br />
                <small style="font-weight: normal;"><?php _e('No end if blank', 'mp') ?></small>
              </th>
              <th>
                <?php _e('Allowed Uses', 'mp') ?><br />
                <small style="font-weight: normal;"><?php _e('Unlimited if blank', 'mp') ?></small>
              </th>
            </tr>
            </thead>
            <tbody>
            <tr>
              <td>
                <input value="<?php echo $new_coupon_code ?>" name="coupon_code" type="text" style="text-transform: uppercase;" />
              </td>
              <td>
                <input value="<?php echo $discount; ?>" size="3" name="discount" type="text" />
                <select name="discount_type">
                 <option value="amt"<?php selected($discount_type, 'amt') ?>><?php echo $this->format_currency(); ?></option>
                 <option value="pct"<?php selected($discount_type, 'pct') ?>>%</option>
                </select>
              </td>
              <td>
                <input value="<?php echo $start; ?>" class="pickdate" size="11" name="start" type="text" />
              </td>
              <td>
                <input value="<?php echo $end; ?>" class="pickdate" size="11" name="end" type="text" />
              </td>
              <td>
                <input value="<?php echo $uses; ?>" size="4" name="uses" type="text" />
              </td>
            </tr>
            </tbody>
            </table>

            <p class="submit">
              <input type="submit" name="submit_settings" value="<?php _e('Save Coupon', 'mp') ?>" />
            </p>
          </div>
        </div>

        </div>
    		</form>
        <?php
        break;


  		//---------------------------------------------------//
  		case "presentation":

        //save settings
        if (isset($_POST['marketplace_settings'])) {
	        //get old store slug
		  		$old_slug = $settings['slugs']['store'];

	        //filter slugs
	        $_POST['mp']['slugs'] = array_map('sanitize_title', $_POST['mp']['slugs']);

				  // Fixing http://premium.wpmudev.org/forums/topic/store-page-content-overwritten
				  $new_slug = $_POST['mp']['slugs']['store'];
				  $new_post_id = $wpdb->get_var("SELECT ID FROM " . $wpdb->posts . " WHERE post_name = '$new_slug' AND post_type = 'page'");

				  if ($new_slug != $old_slug && $new_post_id != 0) {
				    echo '<div class="error fade"><p>'.__('Store base URL conflicts with another page', 'mp').'</p></div>';
				  } else {
				    $settings = array_merge($settings, apply_filters('mp_presentation_settings_filter', $_POST['mp']));
				    update_option('mp_settings', $settings);

				    $this->create_store_page($old_slug);

				    //flush rewrite rules due to product slugs
				    $this->flush_rewrite();

				    echo '<div class="updated fade"><p>'.__('Settings saved.', 'mp').'</p></div>';
				  }
        }
        ?>
        <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/my_work.png'; ?>" /></div>
        <h2><?php _e('Presentation Settings', 'mp'); ?></h2>
        <div id="poststuff" class="metabox-holder mp-settings">

        <form method="post" action="edit.php?post_type=product&amp;page=marketpress&amp;tab=presentation">
          <input type="hidden" name="marketplace_settings" value="1" />

          <div class="postbox">
            <h3 class='hndle'><span><?php _e('General Settings', 'mp') ?></span></h3>
            <div class="inside">
              <table class="form-table">
        				<th scope="row"><?php _e('Store Theme', 'mp') ?></th>
        				<td>
                  <?php $this->store_themes_select(); ?>
                  <br /><span class="description"><?php _e('This option changes the built-in css styles for store pages.', 'mp') ?>
                  <?php if ((is_multisite() && is_super_admin()) || !is_multisite()) { ?>
                  <br /><?php _e('For a custom css theme, save your css file with the "MarketPress Theme: NAME" header in the "/marketpress/css/themes/" folder and it will appear in this list so you may select it. You can also select "None" and create custom theme templates and css to make your own completely unique store design. More information on that <a href="' . $this->plugin_url . 'themes/Themeing_MarketPress.txt">here &raquo;</a>', 'mp') ?>
                  <?php } ?></span>
                 </td>
                </tr>
              </table>
            </div>
          </div>

          <div class="postbox">
            <h3 class='hndle'><span><?php _e('Single Product Settings', 'mp') ?></span></h3>
            <div class="inside">
              <table class="form-table">
                <tr>
        				<th scope="row"><?php _e('Checkout Button Type', 'mp') ?></th>
        				<td>
                  <label><input value="addcart" name="mp[product_button_type]" type="radio"<?php checked($settings['product_button_type'], 'addcart') ?> /> <?php _e('Add To Cart', 'mp') ?></label><br />
                  <label><input value="buynow" name="mp[product_button_type]" type="radio"<?php checked($settings['product_button_type'], 'buynow') ?> /> <?php _e('Buy Now', 'mp') ?></label>
        				</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Show Quantity Option', 'mp') ?></th>
        				<td>
                  <label><input value="1" name="mp[show_quantity]" type="radio"<?php checked($settings['show_quantity'], 1) ?> /> <?php _e('Yes', 'mp') ?></label>
                  <label><input value="0" name="mp[show_quantity]" type="radio"<?php checked($settings['show_quantity'], 0) ?> /> <?php _e('No', 'mp') ?></label>
        				</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Product Image Size', 'mp') ?></th>
        				<td>
                  <label><input value="thumbnail" name="mp[product_img_size]" type="radio"<?php checked($settings['product_img_size'], 'thumbnail') ?> /> <a href="options-media.php"><?php _e('WP Thumbnail size', 'mp') ?></a></label><br />
                  <label><input value="medium" name="mp[product_img_size]" type="radio"<?php checked($settings['product_img_size'], 'medium') ?> /> <a href="options-media.php"><?php _e('WP Medium size', 'mp') ?></a></label><br />
                  <label><input value="large" name="mp[product_img_size]" type="radio"<?php checked($settings['product_img_size'], 'large') ?> /> <a href="options-media.php"><?php _e('WP Large size', 'mp') ?></a></label><br />
                  <label><input value="custom" name="mp[product_img_size]" type="radio"<?php checked($settings['product_img_size'], 'custom') ?> /> <?php _e('Custom', 'mp') ?></label>:&nbsp;&nbsp;
                  <label><?php _e('Height', 'mp') ?><input size="3" name="mp[product_img_height]" value="<?php echo esc_attr($settings['product_img_height']) ?>" type="text" /></label>&nbsp;
                  <label><?php _e('Width', 'mp') ?><input size="3" name="mp[product_img_width]" value="<?php echo esc_attr($settings['product_img_width']) ?>" type="text" /></label>
                </td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Show Image Lightbox', 'mp') ?></th>
        				<td>
                  <label><input value="1" name="mp[show_lightbox]" type="radio"<?php checked($settings['show_lightbox'], 1) ?> /> <?php _e('Yes', 'mp') ?></label>
                  <label><input value="0" name="mp[show_lightbox]" type="radio"<?php checked($settings['show_lightbox'], 0) ?> /> <?php _e('No', 'mp') ?></label>
                  <br /><span class="description"><?php _e('Makes clicking the single product image open an instant zoomed preview.', 'mp') ?></span>
                </td>
                </tr>
              </table>
            </div>
          </div>

          <div class="postbox">
            <h3 class='hndle'><span><?php _e('Product List Settings', 'mp') ?></span></h3>
            <div class="inside">
              <table class="form-table">
                <?php /* ?>
                <tr>
        				<th scope="row"><?php _e('Product List View', 'mp') ?></th>
        				<td>
                  <label><input value="list" name="mp[list_view]" type="radio"<?php checked($settings['list_view'], 'list') ?> /> <?php _e('List View', 'mp') ?></label><br />
                  <label><input value="grid" name="mp[list_view]" type="radio"<?php checked($settings['list_view'], 'grid') ?> /> <?php _e('Grid View', 'mp') ?></label>
        				</td>
                </tr>
                <?php */ ?>
                <tr>
        				<th scope="row"><?php _e('Checkout Button Type', 'mp') ?></th>
        				<td>
                  <label><input value="addcart" name="mp[list_button_type]" type="radio"<?php checked($settings['list_button_type'], 'addcart') ?> /> <?php _e('Add To Cart', 'mp') ?></label><br />
                  <label><input value="buynow" name="mp[list_button_type]" type="radio"<?php checked($settings['list_button_type'], 'buynow') ?> /> <?php _e('Buy Now', 'mp') ?></label>
        				</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Show Product Thumbnail', 'mp') ?></th>
        				<td>
                  <label><input value="1" name="mp[show_thumbnail]" type="radio"<?php checked($settings['show_thumbnail'], 1) ?> /> <?php _e('Yes', 'mp') ?></label>
                  <label><input value="0" name="mp[show_thumbnail]" type="radio"<?php checked($settings['show_thumbnail'], 0) ?> /> <?php _e('No', 'mp') ?></label>
        				</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Product Thumbnail Size', 'mp') ?></th>
        				<td>
                  <label><input value="thumbnail" name="mp[list_img_size]" type="radio"<?php checked($settings['list_img_size'], 'thumbnail') ?> /> <a href="options-media.php"><?php _e('WP Thumbnail size', 'mp') ?></a></label><br />
                  <label><input value="medium" name="mp[list_img_size]" type="radio"<?php checked($settings['list_img_size'], 'medium') ?> /> <a href="options-media.php"><?php _e('WP Medium size', 'mp') ?></a></label><br />
                  <label><input value="large" name="mp[list_img_size]" type="radio"<?php checked($settings['list_img_size'], 'large') ?> /> <a href="options-media.php"><?php _e('WP Large size', 'mp') ?></a></label><br />
                  <label><input value="custom" name="mp[list_img_size]" type="radio"<?php checked($settings['list_img_size'], 'custom') ?> /> <?php _e('Custom', 'mp') ?></label>:&nbsp;&nbsp;
                  <label><?php _e('Height', 'mp') ?><input size="3" name="mp[list_img_height]" value="<?php echo esc_attr($settings['list_img_height']) ?>" type="text" /></label>&nbsp;
                  <label><?php _e('Width', 'mp') ?><input size="3" name="mp[list_img_width]" value="<?php echo esc_attr($settings['list_img_width']) ?>" type="text" /></label>
                </td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Paginate Products', 'mp') ?></th>
        				<td>
                  <label><input value="1" name="mp[paginate]" type="radio"<?php checked($settings['paginate'], 1) ?> /> <?php _e('Yes', 'mp') ?></label>
                  <label><input value="0" name="mp[paginate]" type="radio"<?php checked($settings['paginate'], 0) ?> /> <?php _e('No', 'mp') ?></label>&nbsp;&nbsp;
                  <label><input value="<?php echo esc_attr($settings['per_page']) ?>" name="mp[per_page]" type="text" size="2" /> <?php _e('Products per page', 'mp') ?></label>
        				</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Order Products By', 'mp') ?></th>
        				<td>
                  <select name="mp[order_by]">
                    <option value="title"<?php selected($settings['order_by'], 'title') ?>><?php _e('Product Name', 'mp') ?></option>
                    <option value="date"<?php selected($settings['order_by'], 'date') ?>><?php _e('Publish Date', 'mp') ?></option>
                    <option value="ID"<?php selected($settings['order_by'], 'ID') ?>><?php _e('Product ID', 'mp') ?></option>
                    <option value="author"<?php selected($settings['order_by'], 'author') ?>><?php _e('Product Author', 'mp') ?></option>
                    <option value="sales"<?php selected($settings['order_by'], 'sales') ?>><?php _e('Number of Sales', 'mp') ?></option>
                    <option value="price"<?php selected($settings['order_by'], 'price') ?>><?php _e('Product Price', 'mp') ?></option>
                    <option value="rand"<?php selected($settings['order_by'], 'rand') ?>><?php _e('Random', 'mp') ?></option>
                  </select>
                  <label><input value="DESC" name="mp[order]" type="radio"<?php checked($settings['order'], 'DESC') ?> /> <?php _e('Descending', 'mp') ?></label>
                  <label><input value="ASC" name="mp[order]" type="radio"<?php checked($settings['order'], 'ASC') ?> /> <?php _e('Ascending', 'mp') ?></label>
         	  </td>
                </tr>
              </table>
            </div>
          </div>

          <div class="postbox">
            <h3 class='hndle'><span><?php _e('Store URL Slugs', 'mp') ?></span></h3>
            <div class="inside">
              <span class="description"><?php _e('Customizes the url structure of your store', 'mp') ?></span>
              <table class="form-table">
                <tr valign="top">
                <th scope="row"><?php _e('Store Base', 'mp') ?></th>
                <td>/<input type="text" name="mp[slugs][store]" value="<?php echo esc_attr($settings['slugs']['store']); ?>" size="20" maxlength="50" />/<br />
                <span class="description"><?php _e('This page will be created so you can change it\'s content and the order in which it appears in navigation menus if your theme supports it.', 'mp') ?></span></td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Products List', 'mp') ?></th>
                <td>/<?php echo esc_attr($settings['slugs']['store']); ?>/<input type="text" name="mp[slugs][products]" value="<?php echo esc_attr($settings['slugs']['products']); ?>" size="20" maxlength="50" />/</td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Shopping Cart Page', 'mp') ?></th>
                <td>/<?php echo esc_attr($settings['slugs']['store']); ?>/<input type="text" name="mp[slugs][cart]" value="<?php echo esc_attr($settings['slugs']['cart']); ?>" size="20" maxlength="50" />/</td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Order Status Page', 'mp') ?></th>
                <td>/<?php echo esc_attr($settings['slugs']['store']); ?>/<input type="text" name="mp[slugs][orderstatus]" value="<?php echo esc_attr($settings['slugs']['orderstatus']); ?>" size="20" maxlength="50" />/</td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Product Category', 'mp') ?></th>
                <td>/<?php echo esc_attr($settings['slugs']['store']); ?>/<?php echo esc_attr($settings['slugs']['products']); ?>/<input type="text" name="mp[slugs][category]" value="<?php echo esc_attr($settings['slugs']['category']); ?>" size="20" maxlength="50" />/</td>
                </tr>
                <tr valign="top">
                <th scope="row"><?php _e('Product Tag', 'mp') ?></th>
                <td>/<?php echo esc_attr($settings['slugs']['store']); ?>/<?php echo esc_attr($settings['slugs']['products']); ?>/<input type="text" name="mp[slugs][tag]" value="<?php echo esc_attr($settings['slugs']['tag']); ?>" size="20" maxlength="50" />/</td>
                </tr>
              </table>
            </div>
          </div>

          <?php do_action('mp_presentation_settings'); ?>

          <p class="submit">
            <input type="submit" name="submit_settings" value="<?php _e('Save Changes', 'mp') ?>" />
          </p>
        </form>
        </div>
        <?php
         break;


  		//---------------------------------------------------//
  		case "messages":
        //save settings
        if (isset($_POST['messages_settings'])) {

          //strip slashes
          $_POST['mp']['msg'] = array_map('stripslashes', $_POST['mp']['msg']);

          //remove html from emails
          $_POST['mp']['email'] = array_map('wp_filter_nohtml_kses', $_POST['mp']['email']);

          //filter msg inputs if necessary
          if (!current_user_can('unfiltered_html')) {
            $_POST['mp']['msg'] = array_map('wp_kses_post', $_POST['mp']['msg']);
          }

          $settings = array_merge($settings, apply_filters('mp_messages_settings_filter', $_POST['mp']));
          update_option('mp_settings', $settings);

          echo '<div class="updated fade"><p>'.__('Settings saved.', 'mp').'</p></div>';
        }

        //strip slashes
        $settings['email'] = array_map('stripslashes', $settings['email']);

        //enqueue visual editor
        if (get_user_option('rich_editing') == 'true')
        	$this->load_tiny_mce("mp_msgs_txt");
        ?>
        <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/messages.png'; ?>" /></div>
        <h2><?php _e('Messages Settings', 'mp'); ?></h2>
        <div id="poststuff" class="metabox-holder mp-settings">

        <form id="mp-messages-form" method="post" action="edit.php?post_type=product&amp;page=marketpress&amp;tab=messages">
          <input type="hidden" name="messages_settings" value="1" />

          <div class="postbox">
            <h3 class='hndle'><span><?php _e('Email Notifications', 'mp') ?></span></h3>
            <div class="inside">
              <table class="form-table">
							<tr>
        				<th scope="row"><?php _e('Store Admin Email', 'mp'); ?></th>
        				<td>
								<?php $store_email = isset($settings['store_email']) ? $settings['store_email'] : get_option("admin_email"); ?>
        				<span class="description"><?php _e('The email address that new order notifications are sent to and received from.', 'mp') ?></span><br />
                <input name="mp[store_email]" value="<?php echo esc_attr($store_email); ?>" maxlength="150" size="50" />
                </td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('New Order', 'mp'); ?></th>
        				<td>
        				<span class="description"><?php _e('The email text sent to your customer to confirm a new order. These codes will be replaced with order details: CUSTOMERNAME, ORDERID, ORDERINFO, SHIPPINGINFO, PAYMENTINFO, TOTAL, TRACKINGURL. No HTML allowed.', 'mp') ?></span><br />
                <label><?php _e('Subject:', 'mp'); ?><br />
                <input class="mp_emails_sub" name="mp[email][new_order_subject]" value="<?php echo esc_attr($settings['email']['new_order_subject']); ?>" maxlength="150" /></label><br />
                <label><?php _e('Text:', 'mp'); ?><br />
                <textarea class="mp_emails_txt" name="mp[email][new_order_txt]"><?php echo esc_textarea($settings['email']['new_order_txt']); ?></textarea>
                </label>
                </td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Order Shipped', 'mp'); ?></th>
        				<td>
        				<span class="description"><?php _e('The email text sent to your customer when you mark an order as "Shipped". These codes will be replaced with order details: CUSTOMERNAME, ORDERID, ORDERINFO, SHIPPINGINFO, PAYMENTINFO, TOTAL, TRACKINGURL. No HTML allowed.', 'mp') ?></span><br />
                <label><?php _e('Subject:', 'mp'); ?><br />
                <input class="mp_emails_sub" name="mp[email][shipped_order_subject]" value="<?php echo esc_attr($settings['email']['shipped_order_subject']); ?>" maxlength="150" /></label><br />
                <label><?php _e('Text:', 'mp'); ?><br />
                <textarea class="mp_emails_txt" name="mp[email][shipped_order_txt]"><?php echo esc_textarea($settings['email']['shipped_order_txt']); ?></textarea>
                </label>
                </td>
                </tr>
              </table>
            </div>
          </div>

          <div class="postbox mp-pages-msgs">
            <h3 class='hndle'><span><?php _e('Store Pages', 'mp') ?></span></h3>
            <div class="inside">
              <table class="form-table">
                <tr>
        				<th scope="row"><?php _e('Store Page', 'mp'); ?></th>
        				<td>
        				<span class="description"><?php _e('The main store page is an actual page on your site. You can edit it here:', 'mp') ?></span>
        				<?php
                $post_id = get_option('mp_store_page');
                edit_post_link(__('Edit Page &raquo;', 'mp'), '', '', $post_id);
                ?>
        				</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Product Listing Pages', 'mp'); ?></th>
        				<td>
        				<span class="description"><?php _e('Displayed at the top of the product listing pages. Optional, HTML allowed.', 'mp') ?></span><br />
                <textarea class="mp_msgs_txt" name="mp[msg][product_list]"><?php echo $settings['msg']['product_list']; ?></textarea>
        				</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Order Status Page', 'mp'); ?></th>
        				<td>
        				<span class="description"><?php _e('Displayed at the top of the Order Status page. Optional, HTML allowed.', 'mp') ?></span><br />
                <textarea class="mp_msgs_txt" name="mp[msg][order_status]"><?php echo $settings['msg']['order_status']; ?></textarea>
        				</td>
                </tr>
              </table>
            </div>
          </div>

          <div class="postbox mp-pages-msgs">
            <h3 class='hndle'><span><?php _e('Shopping Cart Pages', 'mp') ?></span></h3>
            <div class="inside">
              <table class="form-table">
                <tr>
        				<th scope="row"><?php _e('Shopping Cart Page', 'mp'); ?></th>
        				<td>
        				<span class="description"><?php _e('Displayed at the top of the Shopping Cart page. Optional, HTML allowed.', 'mp') ?></span><br />
                <textarea class="mp_msgs_txt" name="mp[msg][cart]"><?php echo $settings['msg']['cart']; ?></textarea>
        				</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Shipping Form Page', 'mp'); ?></th>
        				<td>
        				<span class="description"><?php _e('Displayed at the top of the Shipping Form page. Optional, HTML allowed.', 'mp') ?></span><br />
                <textarea class="mp_msgs_txt" name="mp[msg][shipping]"><?php echo $settings['msg']['shipping']; ?></textarea>
        				</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Payment Form Page', 'mp'); ?></th>
        				<td>
        				<span class="description"><?php _e('Displayed at the top of the Payment Form page. Optional, HTML allowed.', 'mp') ?></span><br />
                <textarea class="mp_msgs_txt" name="mp[msg][checkout]"><?php echo $settings['msg']['checkout']; ?></textarea>
        				</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Order Confirmation Page', 'mp'); ?></th>
        				<td>
        				<span class="description"><?php _e('Displayed at the top of the final Order Confirmation page. HTML allowed.', 'mp') ?></span><br />
                <textarea class="mp_msgs_txt" name="mp[msg][confirm_checkout]"><?php echo $settings['msg']['confirm_checkout']; ?></textarea>
        				</td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Order Complete Page', 'mp'); ?></th>
        				<td>
        				<span class="description"><?php _e('Displayed at the top of the page notifying customers of a successful order. HTML allowed.', 'mp') ?></span><br />
                <textarea class="mp_msgs_txt" name="mp[msg][success]"><?php echo $settings['msg']['success']; ?></textarea>
        				</td>
                </tr>
              </table>
            </div>
          </div>

          <?php
          //for adding additional messages
          do_action('mp_messages_settings', $settings);
          ?>

          <p class="submit">
            <input type="submit" name="submit_settings" value="<?php _e('Save Changes', 'mp') ?>" />
          </p>
        </form>
        </div>
        <?php
        break;


      //---------------------------------------------------//
  		case "shipping":
  		  global $mp_shipping_plugins;

        //save settings
        if (isset($_POST['shipping_settings'])) {
          echo '<div class="updated fade"><p>'.__('Settings saved.', 'mp').'</p></div>';
        }
        ?>
        <script type="text/javascript">
      	  jQuery(document).ready(function ($) {
            $("#mp-select-all").click(function() {
              $("#mp-target-countries input[type='checkbox']").attr('checked', true);
              return false;
            });
            $("#mp-select-eu").click(function() {
              $("#mp-target-countries input[type='checkbox'].eu").attr('checked', true);
              return false;
            });
            $("#mp-select-none").click(function() {
              $("#mp-target-countries input[type='checkbox']").attr('checked', false);
              return false;
            });
            $("#mp-shipping-method").change(function() {
              $("#mp-shipping-form").submit();
        		});
      		});
      	</script>
        <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/delivery.png'; ?>" /></div>
        <h2><?php _e('Shipping Settings', 'mp'); ?></h2>
        <div id="poststuff" class="metabox-holder mp-settings">

        <form id="mp-shipping-form" method="post" action="edit.php?post_type=product&amp;page=marketpress&amp;tab=shipping">
          <input type="hidden" name="shipping_settings" value="1" />

          <div id="mp_flat_rate" class="postbox">
            <h3 class='hndle'><span><?php _e('General Settings', 'mp') ?></span></h3>
            <div class="inside">
              <table class="form-table">

                <tr>
        				<th scope="row"><?php _e('Choose Target Countries', 'mp') ?></th>
        				<td>
                  <div><?php _e('Select:', 'mp') ?> <a id="mp-select-all" href="#"><?php _e('All', 'mp') ?></a>&nbsp; <a id="mp-select-eu" href="#"><?php _e('EU', 'mp') ?></a>&nbsp; <a id="mp-select-none" href="#"><?php _e('None', 'mp') ?></a></div>
                  <div id="mp-target-countries">
                  <?php
                    foreach ($this->countries as $code => $name) {
                      ?><label><input type="checkbox"<?php echo (in_array($code, $this->eu_countries)) ? ' class="eu"' : ''; ?> name="mp[shipping][allowed_countries][]" value="<?php echo $code; ?>"<?php echo (in_array($code, (array)$settings['shipping']['allowed_countries'])) ? ' checked="checked"' : ''; ?> /> <?php echo esc_attr($name); ?></label><br /><?php
                    }
                  ?>
                  </div><br />
                  <span class="description"><?php _e('These are the countries you will sell and ship to.', 'mp') ?></span>
          			</td>
                </tr>

                <tr>
        				<th scope="row"><?php _e('Select Shipping Method', 'mp') ?></th>
        				<td>
                  <select name="mp[shipping][method]" id="mp-shipping-method">
                    <option value="none"<?php selected($settings['shipping']['method'], 'none'); ?>><?php _e('No Shipping', 'mp'); ?></option>
                    <?php
                    foreach ((array)$mp_shipping_plugins as $code => $plugin) {
                      ?><option value="<?php echo $code; ?>"<?php selected($settings['shipping']['method'], $code); ?>><?php echo $plugin[1]; ?></option><?php
                    }
                    ?>
                  </select>
          				</td>
                </tr>

              </table>
            </div>
          </div>

          <?php
          //for adding additional settings for a shipping module
          do_action('mp_shipping_settings', $settings);
          ?>

          <p class="submit">
            <input type="submit" name="submit_settings" value="<?php _e('Save Changes', 'mp') ?>" />
          </p>
        </form>
        </div>
        <?php
        break;


      //---------------------------------------------------//
  		case "gateways":
        global $mp_gateway_plugins;

        //save settings
        if (isset($_POST['gateway_settings'])) {
          echo '<div class="updated fade"><p>'.__('Settings saved.', 'mp').'</p></div>';
        }
        ?>
        <script type="text/javascript">
      	  jQuery(document).ready(function ($) {
            $("input.mp_allowed_gateways").change(function() {
              $("#mp-gateways-form").submit();
        		});
          });
      	</script>
        <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/credit-cards.png'; ?>" /></div>
        <h2><?php _e('Payment Settings', 'mp'); ?></h2>
        <div id="poststuff" class="metabox-holder mp-settings">

        <form id="mp-gateways-form" method="post" action="edit.php?post_type=product&amp;page=marketpress&amp;tab=gateways">
          <input type="hidden" name="gateway_settings" value="1" />

					<?php if (!$this->global_cart) { ?>
          <div id="mp_gateways" class="postbox">
            <h3 class='hndle'><span><?php _e('General Settings', 'mp') ?></span></h3>
            <div class="inside">
              <table class="form-table">
                <tr>
        				<th scope="row"><?php _e('Select Payment Gateway(s)', 'mp') ?></th>
        				<td>
                <?php
                //check network permissions
                if (is_multisite() && !is_main_site()) {
                  $network_settings = get_site_option( 'mp_network_settings' );
                  foreach ((array)$mp_gateway_plugins as $code => $plugin) {
                    if ($network_settings['allowed_gateways'][$code] == 'full') {
                      $allowed_plugins[$code] = $plugin;
                    } else if (function_exists('is_supporter') && is_supporter() && $network_settings['allowed_gateways'][$code] == 'supporter') {
                      $allowed_plugins[$code] = $plugin;
                    }
                  }
                  $mp_gateway_plugins = $allowed_plugins;
                }

                foreach ((array)$mp_gateway_plugins as $code => $plugin) {
                  if ($plugin[3]) { //if demo
                  	?><label><input type="checkbox" class="mp_allowed_gateways" name="mp[gateways][allowed][]" value="<?php echo $code; ?>" disabled="disabled" /> <?php echo esc_attr($plugin[1]); ?></label> <a class="mp-pro-update" href="http://premium.wpmudev.org/project/e-commerce" title="<?php _e('Upgrade', 'mp'); ?> &raquo;"><?php _e('Pro Only &raquo;', 'mp'); ?></a><br /><?php
									} else {
                    ?><label><input type="checkbox" class="mp_allowed_gateways" name="mp[gateways][allowed][]" value="<?php echo $code; ?>"<?php echo (in_array($code, (array)$settings['gateways']['allowed'])) ? ' checked="checked"' : ''; ?> /> <?php echo esc_attr($plugin[1]); ?></label><br /><?php
									}
								}
                ?>
        				</td>
                </tr>
              </table>
            </div>
          </div>
          <?php } ?>

          <?php
          //for adding additional settings for a payment gateway plugin
          do_action('mp_gateway_settings', $settings);
          ?>

          <p class="submit">
            <input type="submit" name="submit_settings" value="<?php _e('Save Changes', 'mp') ?>" />
          </p>
        </form>
        </div>
        <?php
        break;


  		//---------------------------------------------------//
  		case "shortcodes":
        ?>
        <div class="icon32"><img src="<?php echo $this->plugin_url . 'images/help.png'; ?>" /></div>
        <h2><?php _e('MarketPress Shortcodes', 'mp'); ?></h2>
        <div id="poststuff" class="metabox-holder mp-settings">

          <!--
          <div class="postbox">
            <h3 class='hndle'><span><?php _e('General Information', 'mp') ?></span></h3>
            <div class="inside">
              <iframe src="http://premium.wpmudev.org/wdp-un.php?action=help&id=144" width="100%" height="400px"></iframe>
            </div>
          </div>
          -->

          <div class="postbox">
            <h3 class='hndle'><span><?php _e('Shortcodes', 'mp') ?></span></h3>
            <div class="inside">
              <p><?php _e('Shortcodes allow you to include dynamic store content in posts and pages on your site. Simply type or paste them into your post or page content where you would like them to appear. Optional attributes can be added in a format like <em>[shortcode attr1="value" attr2="value"]</em>.', 'mp') ?></p>
              <table class="form-table">
                <tr>
        				<th scope="row"><?php _e('Product Tag Cloud', 'mp') ?></th>
        				<td>
                  <strong>[mp_tag_cloud]</strong> -
                  <span class="description"><?php _e('Displays a cloud or list of your product tags.', 'mp') ?></span>
                  <a href="http://codex.wordpress.org/Template_Tags/wp_tag_cloud"><?php _e('Optional Attributes &raquo;', 'mp') ?></a>
                </td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Product Categories List', 'mp') ?></th>
        				<td>
                  <strong>[mp_list_categories]</strong> -
                  <span class="description"><?php _e('Displays an HTML list of your product categories.', 'mp') ?></span>
                  <a href="http://codex.wordpress.org/Template_Tags/wp_list_categories"><?php _e('Optional Attributes &raquo;', 'mp') ?></a>
                </td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Product Categories Dropdown', 'mp') ?></th>
        				<td>
                  <strong>[mp_dropdown_categories]</strong> -
                  <span class="description"><?php _e('Displays an HTML dropdown of your product categories.', 'mp') ?></span>
                  <a href="http://codex.wordpress.org/Template_Tags/wp_dropdown_categories"><?php _e('Optional Attributes &raquo;', 'mp') ?></a>
                </td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Popular Products List', 'mp') ?></th>
        				<td>
                  <strong>[mp_popular_products]</strong> -
                  <span class="description"><?php _e('Displays a list of popular products ordered by sales.', 'mp') ?></span>
                  <p>
                  <strong><?php _e('Optional Attributes:', 'mp') ?></strong>
                  <ul class="mp-shortcode-options">
                    <li><?php _e('"number" - max number of products to display. Defaults to 5.', 'mp') ?></li>
                    <li><?php _e('Example:', 'mp') ?> <em>[mp_popular_products number="5"]</em></li>
                  </ul></p>
                </td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Products List', 'mp') ?></th>
        				<td>
                  <strong>[mp_list_products]</strong> -
                  <span class="description"><?php _e('Displays a list of products according to preference. Optional attributes default to the values in Presentation Settings -> Product List.', 'mp') ?></span>
                  <p>
                  <strong><?php _e('Optional Attributes:', 'mp') ?></strong>
                  <ul class="mp-shortcode-options">
                    <li><?php _e('"paginate" - Whether to paginate the product list. This is useful to only show a subset.', 'mp') ?></li>
                    <li><?php _e('"page" - The page number to display in the product list if "paginate" is set to true.', 'mp') ?></li>
                    <li><?php _e('"per_page" - How many products to display in the product list if "paginate" is set to true.', 'mp') ?></li>
                    <li><?php _e('"order_by" - What field to order products by. Can be: title, date, ID, author, price, sales, rand (random).', 'mp') ?></li>
                    <li><?php _e('"order" - Direction to order products by. Can be: DESC, ASC', 'mp') ?></li>
                    <li><?php _e('"category" - Limits list to a specific product category. Use the category Slug', 'mp') ?></li>
                    <li><?php _e('"tag" - Limits list to a specific product tag. Use the tag Slug', 'mp') ?></li>
                    <li><?php _e('Example:', 'mp') ?> <em>[mp_list_products paginate="true" page="1" per_page="10" order_by="price" order="DESC" category="downloads"]</em></li>
                  </ul></p>
                </td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Store Links', 'mp') ?></th>
        				<td>
                  <strong>[mp_cart_link]</strong> -
                  <span class="description"><?php _e('Displays a link or url to the current shopping cart page.', 'mp') ?></span><br />
                  <strong>[mp_store_link]</strong> -
                  <span class="description"><?php _e('Displays a link or url to the current store page.', 'mp') ?></span><br />
                  <strong>[mp_products_link]</strong> -
                  <span class="description"><?php _e('Displays a link or url to the current products list page.', 'mp') ?></span><br />
                  <strong>[mp_orderstatus_link]</strong> -
                  <span class="description"><?php _e('Displays a link or url to the order status page.', 'mp') ?></span><br />
                  <p>
                  <strong><?php _e('Optional Attributes:', 'mp') ?></strong>
                  <ul class="mp-shortcode-options">
                    <li><?php _e('"url" - Whether to return a clickable link or url. Can be: true, false. Defaults to showing link.', 'mp') ?></li>
                    <li><?php _e('"link_text" - The text to show in the link.', 'mp') ?></li>
                    <li><?php _e('Example:', 'mp') ?> <em>[mp_cart_link link_text="Go here!"]</em></li>
                  </ul></p>
                </td>
                </tr>
                <tr>
        				<th scope="row"><?php _e('Store Navigation List', 'mp') ?></th>
        				<td>
                  <strong>[mp_store_navigation]</strong> -
                  <span class="description"><?php _e('Displays a list of links to your store pages.', 'mp') ?></span>
                </td>
                </tr>
              </table>
            </div>
          </div>

          <?php
          //for adding additional help content boxes
          do_action('mp_help_page', $settings);
          ?>
        </div>
        <?php
        break;
  	} //end switch

  	//hook to create a new admin screen.
  	do_action('marketpress_add_screen', $tab);

  	echo '</div>';

  }

} //end class

global $mp;
$mp = new MarketPress();


//Shopping cart widget
class MarketPress_Shopping_Cart extends WP_Widget {

	function MarketPress_Shopping_Cart() {
		$widget_ops = array('classname' => 'mp_cart_widget', 'description' => __('Shows dynamic shopping cart contents along with a checkout button for your MarketPress store.', 'mp') );
		$this->WP_Widget('mp_cart_widget', __('Shopping Cart', 'mp'), $widget_ops);
	}

	function widget($args, $instance) {
		global $mp;
		$settings = get_option('mp_settings');

    if ( get_query_var('pagename') == 'cart' )
      return;

		extract( $args );

		echo $before_widget;
	  $title = $instance['title'];
		if ( !empty( $title ) ) { echo $before_title . apply_filters('widget_title', $title) . $after_title; };

    if ( !empty($instance['custom_text']) )
      echo '<div class="custom_text">' . $instance['custom_text'] . '</div>';

    echo '<div class="mp_cart_widget">';
    mp_show_cart('widget');
    echo '</div>';

    echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = wp_filter_nohtml_kses( $new_instance['title'] );
		$instance['custom_text'] = wp_filter_kses( $new_instance['custom_text'] );
		$instance['show_thumbnail'] = !empty($new_instance['show_thumbnail']) ? 1 : 0;
    $instance['size'] = !empty($new_instance['size']) ? intval($new_instance['size']) : 25;

		return $instance;
	}

	function form( $instance ) {
    $instance = wp_parse_args( (array) $instance, array( 'title' => __('Shopping Cart', 'mp'), 'custom_text' => '', 'show_thumbnail' => 1, 'size' => 25 ) );
		$title = $instance['title'];
		$custom_text = $instance['custom_text'];
		$show_thumbnail = isset( $instance['show_thumbnail'] ) ? (bool) $instance['show_thumbnail'] : false;
		$size = !empty($instance['size']) ? intval($instance['size']) : 25;
  ?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'mp') ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>" /></label></p>
		<p><label for="<?php echo $this->get_field_id('custom_text'); ?>"><?php _e('Custom Text:', 'mp') ?><br />
    <textarea class="widefat" id="<?php echo $this->get_field_id('custom_text'); ?>" name="<?php echo $this->get_field_name('custom_text'); ?>"><?php echo esc_attr($custom_text); ?></textarea></label>
    </p>
  <?php
		/* Disable untill we can mod the cart
		<p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('show_thumbnail'); ?>" name="<?php echo $this->get_field_name('show_thumbnail'); ?>"<?php checked( $show_thumbnail ); ?> />
		<label for="<?php echo $this->get_field_id('show_thumbnail'); ?>"><?php _e( 'Show Thumbnail', 'mp' ); ?></label><br />
		<label for="<?php echo $this->get_field_id('size'); ?>"><?php _e('Thumbnail Size:', 'mp') ?> <input id="<?php echo $this->get_field_id('size'); ?>" name="<?php echo $this->get_field_name('size'); ?>" type="text" size="3" value="<?php echo $size; ?>" /></label></p>
		*/
	}
}

//Product listing widget
class MarketPress_Product_List extends WP_Widget {

	function MarketPress_Product_List() {
		$widget_ops = array('classname' => 'mp_product_list_widget', 'description' => __('Shows a customizable list of products from your MarketPress store.', 'mp') );
		$this->WP_Widget('mp_product_list_widget', __('Product List', 'mp'), $widget_ops);
	}

	function widget($args, $instance) {
    global $mp;
		$settings = get_option('mp_settings');

		extract( $args );

		echo $before_widget;
	  $title = $instance['title'];
		if ( !empty( $title ) ) { echo $before_title . apply_filters('widget_title', $title) . $after_title; };

    if ( !empty($instance['custom_text']) )
      echo '<div id="custom_text">' . $instance['custom_text'] . '</div>';

    /* setup our custom query */

    //setup taxonomy if applicable
    if ($instance['taxonomy_type'] == 'category') {
      $taxonomy_query = '&product_category=' . $instance['taxonomy'];
    } else if ($instance['taxonomy_type'] == 'tag') {
      $taxonomy_query = '&product_tag=' . $instance['taxonomy'];
    }

    //figure out perpage
    if (isset($instance['num_products']) && intval($instance['num_products']) > 0) {
      $paginate_query = '&posts_per_page='.intval($instance['num_products']).'&paged=1';
    } else {
      $paginate_query = '&posts_per_page=10&paged=1';
    }

    //get order by
    if ($instance['order_by']) {
      if ($instance['order_by'] == 'price')
        $order_by_query = '&meta_key=mp_price&orderby=mp_price';
      else if ($instance['order_by'] == 'sales')
        $order_by_query = '&meta_key=mp_sales_count&orderby=mp_sales_count';
      else
        $order_by_query = '&orderby='.$instance['order_by'];
    } else {
      $order_by_query = '&orderby=title';
    }

    //get order direction
    if ($instance['order']) {
      $order_query = '&order='.$instance['order'];
    } else {
      $order_query = '&orderby=DESC';
    }

    //The Query
    $custom_query = new WP_Query('post_type=product' . $taxonomy_query . $paginate_query . $order_by_query . $order_query);

    //do we have products?
    if (count($custom_query->posts)) {
      echo '<ul id="mp_product_list">';
      foreach ($custom_query->posts as $post) {

        echo '<li '.mp_product_class(false, 'mp_product', $post->ID).'>';
        echo '<h3 class="mp_product_name"><a href="' . get_permalink( $post->ID ) . '">' . esc_attr($post->post_title) . '</a></h3>';
        if ($instance['show_thumbnail'])
          mp_product_image( true, 'widget', $post->ID, $instance['size'] );

        if ($instance['show_excerpt'])
          echo '<div class="mp_product_content">' . $mp->product_excerpt($post->post_excerpt, $post->post_content, $post->ID) . '</div>';

        if ($instance['show_price'] || $instance['show_button']) {
          echo '<div class="mp_product_meta">';

          if ($instance['show_price'])
            echo mp_product_price(false, $post->ID, '');

          if ($instance['show_button'])
            echo mp_buy_button(false, 'list', $post->ID);

          echo '</div>';
        }
        $content .= '</li>';
      }
      echo '</ul>';
    } else {
      ?>
      <div class="widget-error">
  			<?php _e('No Products', 'mp') ?>
  		</div>
  		<?php
    }

    echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = wp_filter_nohtml_kses( $new_instance['title'] );
		$instance['custom_text'] = wp_filter_kses( $new_instance['custom_text'] );

		$instance['num_products'] = intval($new_instance['num_products']);
		$instance['order_by'] = $new_instance['order_by'];
		$instance['order'] = $new_instance['order'];
		$instance['taxonomy_type'] = $new_instance['taxonomy_type'];
    $instance['taxonomy'] = ($new_instance['taxonomy_type']) ? sanitize_title($new_instance['taxonomy']) : '';

    $instance['show_thumbnail'] = !empty($new_instance['show_thumbnail']) ? 1 : 0;
    $instance['size'] = !empty($new_instance['size']) ? intval($new_instance['size']) : 50;
    $instance['show_excerpt'] = !empty($new_instance['show_excerpt']) ? 1 : 0;
    $instance['show_price'] = !empty($new_instance['show_price']) ? 1 : 0;
    $instance['show_button'] = !empty($new_instance['show_button']) ? 1 : 0;

		return $instance;
	}

	function form( $instance ) {
    $instance = wp_parse_args( (array) $instance, array( 'title' => __('Our Products', 'mp'), 'custom_text' => '', 'num_products' => 10, 'order_by' => 'title', 'order' => 'DESC', 'show_thumbnail' => 1, 'size' => 50 ) );
		$title = $instance['title'];
		$custom_text = $instance['custom_text'];

		$num_products = intval($instance['num_products']);
		$order_by = $instance['order_by'];
		$order = $instance['order'];
    $taxonomy_type = $instance['taxonomy_type'];
    $taxonomy = $instance['taxonomy'];

		$show_thumbnail = isset( $instance['show_thumbnail'] ) ? (bool) $instance['show_thumbnail'] : false;
		$size = !empty($instance['size']) ? intval($instance['size']) : 50;
		$show_excerpt = isset( $instance['show_excerpt'] ) ? (bool) $instance['show_excerpt'] : false;
		$show_price = isset( $instance['show_price'] ) ? (bool) $instance['show_price'] : false;
		$show_button = isset( $instance['show_button'] ) ? (bool) $instance['show_button'] : false;
  ?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'mp') ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo attribute_escape($title); ?>" /></label></p>
		<p><label for="<?php echo $this->get_field_id('custom_text'); ?>"><?php _e('Custom Text:', 'mp') ?><br />
    <textarea class="widefat" id="<?php echo $this->get_field_id('custom_text'); ?>" name="<?php echo $this->get_field_name('custom_text'); ?>"><?php echo esc_attr($custom_text); ?></textarea></label>
    </p>

    <h3><?php _e('List Settings', 'mp'); ?></h3>
    <p>
    <label for="<?php echo $this->get_field_id('num_products'); ?>"><?php _e('Number of Products:', 'mp') ?> <input id="<?php echo $this->get_field_id('num_products'); ?>" name="<?php echo $this->get_field_name('num_products'); ?>" type="text" size="3" value="<?php echo $num_products; ?>" /></label><br />
    </p>
    <p>
    <label for="<?php echo $this->get_field_id('order_by'); ?>"><?php _e('Order Products By:', 'mp') ?></label><br />
    <select id="<?php echo $this->get_field_id('order_by'); ?>" name="<?php echo $this->get_field_name('order_by'); ?>">
      <option value="title"<?php selected($order_by, 'title') ?>><?php _e('Product Name', 'mp') ?></option>
      <option value="date"<?php selected($order_by, 'date') ?>><?php _e('Publish Date', 'mp') ?></option>
      <option value="ID"<?php selected($order_by, 'ID') ?>><?php _e('Product ID', 'mp') ?></option>
      <option value="author"<?php selected($order_by, 'author') ?>><?php _e('Product Author', 'mp') ?></option>
      <option value="sales"<?php selected($order_by, 'sales') ?>><?php _e('Number of Sales', 'mp') ?></option>
      <option value="price"<?php selected($order_by, 'price') ?>><?php _e('Product Price', 'mp') ?></option>
      <option value="rand"<?php selected($order_by, 'rand') ?>><?php _e('Random', 'mp') ?></option>
    </select><br />
    <label><input value="DESC" name="<?php echo $this->get_field_name('order'); ?>" type="radio"<?php checked($order, 'DESC') ?> /> <?php _e('Descending', 'mp') ?></label>
    <label><input value="ASC" name="<?php echo $this->get_field_name('order'); ?>" type="radio"<?php checked($order, 'ASC') ?> /> <?php _e('Ascending', 'mp') ?></label>
    </p>
    <p>
    <label><?php _e('Taxonomy Filter:', 'mp') ?></label><br />
    <select id="<?php echo $this->get_field_id('taxonomy_type'); ?>" name="<?php echo $this->get_field_name('taxonomy_type'); ?>">
      <option value=""<?php selected($taxonomy_type, '') ?>><?php _e('No Filter', 'mp') ?></option>
      <option value="category"<?php selected($taxonomy_type, 'category') ?>><?php _e('Category', 'mp') ?></option>
      <option value="tag"<?php selected($taxonomy_type, 'tag') ?>><?php _e('Tag', 'mp') ?></option>
    </select>
    <input id="<?php echo $this->get_field_id('taxonomy'); ?>" name="<?php echo $this->get_field_name('taxonomy'); ?>" type="text" size="17" value="<?php echo $taxonomy; ?>" title="<?php _e('Enter the Slug', 'mp'); ?>" />
    </p>

    <h3><?php _e('Display Settings', 'mp'); ?></h3>
    <p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('show_thumbnail'); ?>" name="<?php echo $this->get_field_name('show_thumbnail'); ?>"<?php checked( $show_thumbnail ); ?> />
		<label for="<?php echo $this->get_field_id('show_thumbnail'); ?>"><?php _e( 'Show Thumbnail', 'mp' ); ?></label><br />
		<label for="<?php echo $this->get_field_id('size'); ?>"><?php _e('Thumbnail Size:', 'mp') ?> <input id="<?php echo $this->get_field_id('size'); ?>" name="<?php echo $this->get_field_name('size'); ?>" type="text" size="3" value="<?php echo $size; ?>" /></label></p>

    <p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('show_excerpt'); ?>" name="<?php echo $this->get_field_name('show_excerpt'); ?>"<?php checked( $show_excerpt ); ?> />
    <label for="<?php echo $this->get_field_id('show_excerpt'); ?>"><?php _e( 'Show Excerpt', 'mp' ); ?></label><br />
    <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('show_price'); ?>" name="<?php echo $this->get_field_name('show_price'); ?>"<?php checked( $show_price ); ?> />
		<label for="<?php echo $this->get_field_id('show_price'); ?>"><?php _e( 'Show Price', 'mp' ); ?></label><br />
    <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('show_button'); ?>" name="<?php echo $this->get_field_name('show_button'); ?>"<?php checked( $show_button ); ?> />
		<label for="<?php echo $this->get_field_id('show_button'); ?>"><?php _e( 'Show Buy Button', 'mp' ); ?></label></p>

	<?php
	}
}

//Product categories widget
class MarketPress_Categories_Widget extends WP_Widget {

	function MarketPress_Categories_Widget() {
		$widget_ops = array( 'classname' => 'mp_categories_widget', 'description' => __( "A list or dropdown of product categories from your MarketPress store.", 'mp' ) );
		$this->WP_Widget('mp_categories_widget', __('Product Categories', 'mp'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract( $args );

		$title = apply_filters('widget_title', empty( $instance['title'] ) ? __('Product Categories', 'mp') : $instance['title'], $instance, $this->id_base);
		$c = $instance['count'] ? '1' : '0';
		$h = $instance['hierarchical'] ? '1' : '0';
		$d = $instance['dropdown'] ? '1' : '0';

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;

		$cat_args = array('orderby' => 'name', 'show_count' => $c, 'hierarchical' => $h);

		if ( $d ) {
			$cat_args['show_option_none'] = __('Select Category');
			$cat_args['taxonomy'] = 'product_category';
      $cat_args['id'] = 'mp_category_dropdown';
			wp_dropdown_categories( $cat_args );
?>

<script type='text/javascript'>
/* <![CDATA[ */
	var dropdown = document.getElementById("mp_category_dropdown");
	function onCatChange() {
		if ( dropdown.options[dropdown.selectedIndex].value > 0 ) {
			location.href = "<?php echo home_url(); ?>/?product_category="+dropdown.options[dropdown.selectedIndex].value;
		}
	}
	dropdown.onchange = onCatChange;
/* ]]> */
</script>

<?php
		} else {
?>
<ul id="mp_category_list">
<?php
		$cat_args['title_li'] = '';
		$cat_args['taxonomy'] = 'product_category';
		wp_list_categories( $cat_args );
?>
</ul>
<?php
		}

		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['count'] = !empty($new_instance['count']) ? 1 : 0;
		$instance['hierarchical'] = !empty($new_instance['hierarchical']) ? 1 : 0;
		$instance['dropdown'] = !empty($new_instance['dropdown']) ? 1 : 0;

		return $instance;
	}

	function form( $instance ) {
		//Defaults
		$instance = wp_parse_args( (array) $instance, array( 'title' => '') );
		$title = esc_attr( $instance['title'] );
		$count = isset($instance['count']) ? (bool) $instance['count'] :false;
		$hierarchical = isset( $instance['hierarchical'] ) ? (bool) $instance['hierarchical'] : false;
		$dropdown = isset( $instance['dropdown'] ) ? (bool) $instance['dropdown'] : false;
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e( 'Title:' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('dropdown'); ?>" name="<?php echo $this->get_field_name('dropdown'); ?>"<?php checked( $dropdown ); ?> />
		<label for="<?php echo $this->get_field_id('dropdown'); ?>"><?php _e( 'Show as dropdown' ); ?></label><br />

		<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>"<?php checked( $count ); ?> />
		<label for="<?php echo $this->get_field_id('count'); ?>"><?php _e( 'Show product counts', 'mp' ); ?></label><br />

		<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('hierarchical'); ?>" name="<?php echo $this->get_field_name('hierarchical'); ?>"<?php checked( $hierarchical ); ?> />
		<label for="<?php echo $this->get_field_id('hierarchical'); ?>"><?php _e( 'Show hierarchy' ); ?></label></p>
<?php
	}
}

//Product tags cloud
class MarketPress_Tag_Cloud_Widget extends WP_Widget {

	function MarketPress_Tag_Cloud_Widget() {
		$widget_ops = array( 'classname' => 'mp_tag_cloud_widget', 'description' => __( "Your most used product tags in cloud format from your MarketPress store.") );
		$this->WP_Widget('mp_tag_cloud_widget', __('Product Tag Cloud', 'mp'), $widget_ops);
	}

	function widget( $args, $instance ) {
		extract($args);
		$current_taxonomy = 'product_tag';
		if ( !empty($instance['title']) ) {
			$title = $instance['title'];
		}
		$title = apply_filters('widget_title', $title, $instance, $this->id_base);

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;
		echo '<div>';
		wp_tag_cloud( apply_filters('widget_tag_cloud_args', array('taxonomy' => $current_taxonomy) ) );
		echo "</div>\n";
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance['title'] = strip_tags(stripslashes($new_instance['title']));
		return $instance;
	}

	function form( $instance ) {
    $instance = wp_parse_args( (array) $instance, array( 'title' => __('Product Tags', 'mp') ) );
?>
	<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:') ?></label>
	<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php if (isset ( $instance['title'])) {echo esc_attr( $instance['title'] );} ?>" /></p>
	<?php
	}
}

///////////////////////////////////////////////////////////////////////////
/* -------------------- Update Notifications Notice -------------------- */
if ( !function_exists( 'wdp_un_check' ) ) {
  add_action( 'admin_notices', 'wdp_un_check', 5 );
  add_action( 'network_admin_notices', 'wdp_un_check', 5 );
  function wdp_un_check() {
    if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'install_plugins' ) )
      echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
  }
}
/* --------------------------------------------------------------------- */
?>