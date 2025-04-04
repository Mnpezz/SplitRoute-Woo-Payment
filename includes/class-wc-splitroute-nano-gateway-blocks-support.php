<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_SplitRoute_Nano_Gateway_Blocks_Support extends AbstractPaymentMethodType {
    private $gateway;
    protected $name = 'splitroute_nano';

    public function initialize() {
        $this->settings = get_option("woocommerce_{$this->name}_settings", []);
        
        // Initialize the gateway
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset($gateways[$this->name]) ? $gateways[$this->name] : null;
        
        error_log('SplitRoute Nano Gateway blocks initialize called');
        if ($this->gateway) {
            error_log('Gateway instance found');
        } else {
            error_log('Gateway instance NOT found');
        }
    }

    public function is_active() {
        $is_active = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
        error_log('SplitRoute Nano Gateway blocks is_active: ' . ($is_active ? 'true' : 'false'));
        error_log('Settings: ' . print_r($this->settings, true));
        return $is_active;
    }

    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-splitroute-nano-blocks-integration',
            WC_SPLITROUTE_NANO_PLUGIN_URL . 'build/index.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            WC_SPLITROUTE_NANO_VERSION,
            true
        );
        
        return ['wc-splitroute-nano-blocks-integration'];
    }

    public function get_payment_method_data() {
        return [
            'title' => isset($this->settings['title']) ? $this->settings['title'] : __('Nano (XNO) via SplitRoute', 'wc-splitroute-nano'),
            'description' => isset($this->settings['description']) ? $this->settings['description'] : '',
            'supports' => ['products'],
            'icon' => WC_SPLITROUTE_NANO_PLUGIN_URL . 'assets/images/nano-logo.png',
            'placeOrderButtonLabel' => __('Proceed to Nano Payment', 'wc-splitroute-nano'),
        ];
    }
} 