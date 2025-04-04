<?php
/**
 * Plugin Name: WooCommerce SplitRoute Nano Gateway
 * Plugin URI: https://yourwebsite.com/
 * Description: Accept Nano (XNO) payments in your WooCommerce store using SplitRoute API with payment splitting
 * Version: 1.1.0
 * Author: mnpezz
 * API URI: https://splitroute.com/
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_SPLITROUTE_NANO_VERSION', '1.0.0');
define('WC_SPLITROUTE_NANO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_SPLITROUTE_NANO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Add the gateway to WooCommerce
add_filter('woocommerce_payment_gateways', 'wc_splitroute_nano_add_gateway');
function wc_splitroute_nano_add_gateway($gateways) {
    $gateways[] = 'WC_SplitRoute_Nano_Gateway';
    return $gateways;
}

// Initialize the plugin
add_action('plugins_loaded', 'wc_splitroute_nano_init', 11);
function wc_splitroute_nano_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>WooCommerce is not active. The WooCommerce SplitRoute Nano Gateway plugin requires WooCommerce to be active.</p></div>';
        });
        return;
    }
    
    // Include required files
    require_once WC_SPLITROUTE_NANO_PLUGIN_DIR . 'includes/class-wc-splitroute-nano-gateway.php';
}

// Add thank you page hook - this is the correct way to do it
add_action('woocommerce_thankyou_splitroute_nano', 'wc_splitroute_nano_thankyou_page', 10, 1);
function wc_splitroute_nano_thankyou_page($order_id) {
    $gateways = WC()->payment_gateways->payment_gateways();
    if (isset($gateways['splitroute_nano'])) {
        $gateway = $gateways['splitroute_nano'];
        $gateway->thankyou_page($order_id);
    }
}

// Add settings link on plugin page
function wc_splitroute_nano_settings_link($links) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=splitroute_nano">' . __('Settings', 'wc-splitroute-nano') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'wc_splitroute_nano_settings_link');

// Enqueue styles
function wc_splitroute_nano_enqueue_styles() {
    if (!is_checkout() && !is_wc_endpoint_url('order-received')) {
        return;
    }
    
    wp_enqueue_style('wc-splitroute-nano-css', WC_SPLITROUTE_NANO_PLUGIN_URL . 'assets/css/wc-splitroute-nano.css', array(), WC_SPLITROUTE_NANO_VERSION);
}
add_action('wp_enqueue_scripts', 'wc_splitroute_nano_enqueue_styles');

// Enqueue scripts
function wc_splitroute_nano_enqueue_scripts() {
    if (!is_checkout() && !is_wc_endpoint_url('order-received')) {
        return;
    }
    
    wp_enqueue_style('wc-splitroute-nano-css', WC_SPLITROUTE_NANO_PLUGIN_URL . 'assets/css/wc-splitroute-nano.css', array(), WC_SPLITROUTE_NANO_VERSION);
    
    // Add nonce for AJAX requests
    wp_localize_script('jquery', 'wc_splitroute_nonce', array(
        'payment_nonce' => wp_create_nonce('splitroute_payment_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'wc_splitroute_nano_enqueue_scripts');

// Block support
add_action('woocommerce_blocks_loaded', 'splitroute_nano_register_payment_method_type');
function splitroute_nano_register_payment_method_type() {
    if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        return;
    }

    require_once WC_SPLITROUTE_NANO_PLUGIN_DIR . 'includes/class-wc-splitroute-nano-gateway-blocks-support.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function(Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new WC_SplitRoute_Nano_Gateway_Blocks_Support());
        }
    );
}

// Declare compatibility
add_action('before_woocommerce_init', 'splitroute_nano_cart_checkout_blocks_compatibility');
function splitroute_nano_cart_checkout_blocks_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
}

// Add this at the end of your file
function splitroute_nano_debug_info() {
    if (is_checkout() && current_user_can('manage_options')) {
        $gateways = WC()->payment_gateways->payment_gateways();
        echo '<!-- SplitRoute Nano Gateway Debug Info: ';
        echo 'Available Gateways: ' . implode(', ', array_keys($gateways));
        echo ' -->';
    }
}
add_action('wp_footer', 'splitroute_nano_debug_info');

// Add this function outside the class
function splitroute_nano_cancel_order() {
    if (isset($_GET['cancel_order']) && isset($_GET['order_id']) && wp_verify_nonce($_GET['_wpnonce'], 'woocommerce-cancel_order')) {
        $order_id = absint($_GET['order_id']);
        $order = wc_get_order($order_id);
        
        if ($order && $order->needs_payment()) {
            // Cancel the order
            $order->update_status('cancelled', __('Order cancelled by customer.', 'wc-splitroute-nano'));
            
            // Restore cart
            WC()->cart->restore_cart();
            
            // Redirect to cart page
            wp_redirect(wc_get_cart_url());
            exit;
        }
    }
}
add_action('init', 'splitroute_nano_cancel_order'); 