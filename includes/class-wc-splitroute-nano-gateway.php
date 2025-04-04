<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce SplitRoute Nano Gateway
 *
 * @class WC_SplitRoute_Nano_Gateway
 * @extends WC_Payment_Gateway
 */
class WC_SplitRoute_Nano_Gateway extends WC_Payment_Gateway {
    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id                 = 'splitroute_nano';
        $this->icon               = WC_SPLITROUTE_NANO_PLUGIN_URL . 'assets/images/nano-logo.png';
        $this->has_fields         = false;
        $this->method_title       = __('Nano (XNO) via SplitRoute', 'wc-splitroute-nano');
        $this->method_description = __('Accept Nano cryptocurrency payments using SplitRoute API with payment splitting', 'wc-splitroute-nano');
        $this->supports           = array(
            'products',
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->enabled      = $this->get_option('enabled');
        $this->testmode     = 'yes' === $this->get_option('testmode');
        $this->debug        = 'yes' === $this->get_option('debug');
        $this->api_key      = $this->get_option('api_key');
        $this->primary_account = $this->get_option('primary_account');
        $this->split_payments = 'yes' === $this->get_option('split_payments');
        $this->split_destinations = $this->get_option('split_destinations');
        $this->api_base_url = 'https://api.splitroute.com/api/v1';

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_splitroute_nano_gateway', array($this, 'webhook_handler'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

        // Add AJAX actions
        add_action('wp_ajax_check_splitroute_payment', array($this, 'ajax_check_payment'));
        add_action('wp_ajax_nopriv_check_splitroute_payment', array($this, 'ajax_check_payment'));
        add_action('wp_ajax_splitroute_confirm_payment', array($this, 'ajax_confirm_payment'));
        add_action('wp_ajax_nopriv_splitroute_confirm_payment', array($this, 'ajax_confirm_payment'));
        add_action('wp_ajax_splitroute_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_nopriv_splitroute_process_payment', array($this, 'ajax_process_payment'));
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'wc-splitroute-nano'),
                'type'        => 'checkbox',
                'label'       => __('Enable Nano Payments', 'wc-splitroute-nano'),
                'default'     => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'wc-splitroute-nano'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'wc-splitroute-nano'),
                'default'     => __('Nano (XNO) via SplitRoute', 'wc-splitroute-nano'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'wc-splitroute-nano'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'wc-splitroute-nano'),
                'default'     => __('Pay with Nano cryptocurrency - fast, feeless, and eco-friendly.', 'wc-splitroute-nano'),
                'desc_tip'    => true,
            ),
            'instructions' => array(
                'title'       => __('Instructions', 'wc-splitroute-nano'),
                'type'        => 'textarea',
                'description' => __('Instructions that will be added to the thank you page.', 'wc-splitroute-nano'),
                'default'     => __('Please send the exact amount of Nano to the address provided to complete your payment.', 'wc-splitroute-nano'),
                'desc_tip'    => true,
            ),
            'api_settings' => array(
                'title'       => __('API Settings', 'wc-splitroute-nano'),
                'type'        => 'title',
                'description' => '',
            ),
            'api_key' => array(
                'title'       => __('SplitRoute API Key', 'wc-splitroute-nano'),
                'type'        => 'text',
                'description' => __('Enter your SplitRoute API key. You can get one at https://api.splitroute.com/api/v1/api-keys/register', 'wc-splitroute-nano'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'primary_account' => array(
                'title'       => __('Primary Nano Account', 'wc-splitroute-nano'),
                'type'        => 'text',
                'description' => __('Your primary Nano account address where payments will be sent.', 'wc-splitroute-nano'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'split_payments' => array(
                'title'       => __('Enable Split Payments', 'wc-splitroute-nano'),
                'type'        => 'checkbox',
                'label'       => __('Enable payment splitting', 'wc-splitroute-nano'),
                'description' => __('Split payments between multiple Nano accounts.', 'wc-splitroute-nano'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'split_destinations' => array(
                'title'       => __('Split Payment Destinations', 'wc-splitroute-nano'),
                'type'        => 'textarea',
                'description' => __('Enter additional payment destinations in JSON format. Example: [{"account":"nano_address1","percentage":10,"description":"Partner Fee"},{"account":"nano_address2","nominal_amount":5,"description":"Fixed Fee"}]', 'wc-splitroute-nano'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'advanced_settings' => array(
                'title'       => __('Advanced Settings', 'wc-splitroute-nano'),
                'type'        => 'title',
                'description' => '',
            ),
            'debug' => array(
                'title'       => __('Debug Log', 'wc-splitroute-nano'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'wc-splitroute-nano'),
                'default'     => 'no',
                'description' => __('Log SplitRoute API events inside WC_SPLITROUTE_NANO_PLUGIN_DIR/logs/', 'wc-splitroute-nano'),
            ),
            'testmode' => array(
                'title'       => __('Test Mode', 'wc-splitroute-nano'),
                'type'        => 'checkbox',
                'label'       => __('Enable Test Mode', 'wc-splitroute-nano'),
                'default'     => 'no',
                'description' => __('Simulates the payment process without creating actual invoices.', 'wc-splitroute-nano'),
            ),
        );
    }

    /**
     * Logging method
     *
     * @param string $message
     */
    public function log($message) {
        if ($this->debug) {
            if (empty($this->logger)) {
                $this->logger = new WC_Logger();
            }
            $this->logger->add('splitroute_nano', $message);
        }
    }

    /**
     * Load payment scripts
     */
    public function payment_scripts() {
        if (!is_checkout()) {
            return;
        }
        
        // Enqueue the modal script
        wp_enqueue_script(
            'wc-splitroute-nano-modal-js',
            WC_SPLITROUTE_NANO_PLUGIN_URL . 'assets/js/wc-splitroute-nano-modal.js',
            array('jquery'),
            WC_SPLITROUTE_NANO_VERSION,
            true
        );
        
        // Pass data to JavaScript
        wp_localize_script(
            'wc-splitroute-nano-modal-js',
            'wc_splitroute_params',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'i18n_waiting' => __('Waiting for payment...', 'wc-splitroute-nano'),
                'i18n_received' => __('Payment received! Processing...', 'wc-splitroute-nano'),
                'i18n_completed' => __('Payment completed!', 'wc-splitroute-nano'),
                'i18n_expired' => __('Payment request expired.', 'wc-splitroute-nano'),
            )
        );
    }

    /**
     * Process the payment
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        // Log for debugging
        $this->log('Processing payment for order #' . $order_id);
        
        try {
            // Create invoice via SplitRoute API
            $invoice = $this->create_invoice($order);
            
            if (!$invoice || !isset($invoice['invoice_id'])) {
                throw new Exception(__('Failed to create payment request.', 'wc-splitroute-nano'));
            }
            
            // Store invoice data in order meta
            $order->update_meta_data('_splitroute_invoice_id', $invoice['invoice_id']);
            $order->update_meta_data('_splitroute_secret_id', $invoice['secret_id']);
            $order->update_meta_data('_splitroute_account_address', $invoice['account_address']);
            $order->update_meta_data('_splitroute_required_amount', $invoice['required']['formatted_amount']);
            $order->update_meta_data('_splitroute_expires_at', $invoice['expires_at']);
            
            // Store QR code if available
            if (isset($invoice['qr_code'])) {
                $order->update_meta_data('_splitroute_qr_code', $invoice['qr_code']);
            }
            
            // Create payment data for the receipt page
            $payment_data = array(
                'invoice_id' => $invoice['invoice_id'],
                'account_address' => $invoice['account_address'],
                'amount' => $invoice['required']['formatted_amount'],
                'currency' => 'XNO',
                'expires_at' => $invoice['expires_at'],
                'qr_code' => isset($invoice['qr_code']) ? $invoice['qr_code'] : '',
                'uri_nano' => isset($invoice['uri_nano']) ? $invoice['uri_nano'] : ''
            );
            
            // Store the payment data as JSON
            $order->update_meta_data('_splitroute_payment_data', json_encode($payment_data));
            
            $order->save();
            
            // Add order note
            $order->add_order_note(
                sprintf(
                    __('Awaiting Nano payment of %s to address %s. Invoice ID: %s', 'wc-splitroute-nano'),
                    $invoice['required']['formatted_amount'] . ' XNO',
                    $invoice['account_address'],
                    $invoice['invoice_id']
                )
            );
            
            // Mark as pending (awaiting payment)
            $order->update_status('pending', __('Awaiting Nano payment.', 'wc-splitroute-nano'));
            
            // Return success and redirect to payment page
            return array(
                'result'   => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            );
        } catch (Exception $e) {
            $this->log('Error processing payment: ' . $e->getMessage());
            wc_add_notice($e->getMessage(), 'error');
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }
    }

    /**
     * Create an invoice via SplitRoute API
     *
     * @param WC_Order $order
     * @return array
     */
    public function create_invoice($order) {
        // Prepare the destinations array
        $destinations = array(
            array(
                'account' => $this->primary_account,
                'primary' => true
            )
        );
        
        // Add split payment destinations if enabled
        if ($this->split_payments && !empty($this->split_destinations)) {
            $split_destinations = json_decode($this->split_destinations, true);
            if (is_array($split_destinations)) {
                foreach ($split_destinations as $destination) {
                    if (!empty($destination['account']) && (!empty($destination['percentage']) || !empty($destination['amount']))) {
                        $dest = array(
                            'account' => $destination['account'],
                            'description' => !empty($destination['description']) ? $destination['description'] : ''
                        );
                        
                        if (!empty($destination['percentage'])) {
                            $dest['percentage'] = floatval($destination['percentage']);
                        } elseif (!empty($destination['amount'])) {
                            $dest['nominal_amount'] = floatval($destination['amount']);
                        }
                        
                        $destinations[] = $dest;
                    }
                }
            }
        }
        
        // Prepare the invoice data
        $invoice_data = array(
            'nominal_amount' => $order->get_total(),
            'nominal_currency' => get_woocommerce_currency(),
            'destinations' => $destinations,
            'show_qr' => true,
            'reference' => $order->get_order_number()
        );
        
        // Add webhook URL if configured
        if (!empty($this->get_option('webhook_url'))) {
            $invoice_data['webhook_url'] = $this->get_option('webhook_url');
            
            if (!empty($this->get_option('webhook_secret'))) {
                $invoice_data['webhook_secret'] = $this->get_option('webhook_secret');
            }
        }
        
        $this->log('Creating invoice with data: ' . json_encode($invoice_data));
        
        // Make API request
        $response = wp_remote_post($this->api_base_url . '/invoices', array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->api_key
            ),
            'body' => json_encode($invoice_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $this->log('API request error: ' . $response->get_error_message());
            throw new Exception(__('Payment service connection error.', 'wc-splitroute-nano'));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $invoice = json_decode($response_body, true);
        
        $this->log('API response code: ' . $response_code);
        $this->log('API response: ' . $response_body);
        
        // Check if the invoice was created successfully
        if (!$invoice || !isset($invoice['invoice_id'])) {
            $this->log('Invalid API response: ' . $response_body);
            throw new Exception(__('Invalid response from payment service.', 'wc-splitroute-nano'));
        }
        
        return $invoice;
    }

    /**
     * Output for the order received page.
     *
     * @param int $order_id
     */
    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);
        
        if ($order->get_payment_method() !== $this->id) {
            return;
        }
        
        // Get payment data
        $payment_data = $order->get_meta('_splitroute_payment_data');
        
        if (empty($payment_data)) {
            return;
        }
        
        // Decode payment data
        $payment_data = json_decode($payment_data, true);
        
        // Check if payment is already completed
        if ($order->is_paid()) {
            echo '<div class="woocommerce-notice woocommerce-notice--success">';
            echo '<p>' . __('Payment has been received and confirmed. Thank you!', 'wc-splitroute-nano') . '</p>';
            echo '</div>';
            return;
        }
        
        // Enqueue modal script
        wp_enqueue_script(
            'wc-splitroute-nano-modal-js',
            WC_SPLITROUTE_NANO_PLUGIN_URL . 'assets/js/wc-splitroute-nano-modal.js',
            array('jquery'),
            WC_SPLITROUTE_NANO_VERSION,
            true
        );
        
        // Pass data to JavaScript
        wp_localize_script(
            'wc-splitroute-nano-modal-js',
            'wc_splitroute_params',
            array(
                'payment_data' => json_encode($payment_data),
                'thank_you_url' => $this->get_return_url($order),
                'i18n_waiting' => __('Waiting for payment...', 'wc-splitroute-nano'),
                'i18n_received' => __('Payment received! Processing...', 'wc-splitroute-nano'),
                'i18n_completed' => __('Payment completed!', 'wc-splitroute-nano'),
                'i18n_expired' => __('Payment request expired.', 'wc-splitroute-nano'),
            )
        );
        
        // Add a button to manually open the payment modal
        echo '<div class="wc-splitroute-nano-payment-button">';
        echo '<button type="button" id="open-splitroute-modal" class="button alt">' . __('Pay with Nano', 'wc-splitroute-nano') . '</button>';
        echo '</div>';
        
        // Add script to open modal on button click
        echo '<script>
            jQuery(document).ready(function($) {
                $("#open-splitroute-modal").on("click", function() {
                    if (typeof window.splitrouteNano !== "undefined") {
                        const paymentData = JSON.parse(wc_splitroute_params.payment_data);
                        window.splitrouteNano.openPaymentModal(paymentData);
                    }
                });
                
                // Auto-open the modal
                setTimeout(function() {
                    $("#open-splitroute-modal").trigger("click");
                }, 500);
            });
        </script>';
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false) {
        if ($sent_to_admin || $order->get_payment_method() !== $this->id || $order->get_status() === 'completed') {
            return;
        }
        
        $account_address = $order->get_meta('_splitroute_account_address');
        $required_amount = $order->get_meta('_splitroute_required_amount');
        
        if (!$account_address || !$required_amount) {
            return;
        }
        
        if ($plain_text) {
            echo "\n\n" . $this->instructions . "\n\n";
            echo __('Amount:', 'wc-splitroute-nano') . ' ' . $required_amount . " XNO\n";
            echo __('Address:', 'wc-splitroute-nano') . ' ' . $account_address . "\n";
        } else {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
            echo '<p><strong>' . __('Amount:', 'wc-splitroute-nano') . '</strong> ' . $required_amount . ' XNO</p>';
            echo '<p><strong>' . __('Address:', 'wc-splitroute-nano') . '</strong> ' . $account_address . '</p>';
        }
    }

    /**
     * Handle webhook requests from SplitRoute API
     */
    public function webhook_handler() {
        $this->log('Webhook received');
        
        // Get the request body
        $request_body = file_get_contents('php://input');
        $headers = getallheaders();
        
        // Verify webhook signature
        $signature = isset($headers['X-Webhook-Signature']) ? $headers['X-Webhook-Signature'] : '';
        $timestamp = isset($headers['X-Webhook-Timestamp']) ? $headers['X-Webhook-Timestamp'] : '';
        
        if (empty($signature) || empty($timestamp)) {
            $this->log('Missing webhook signature or timestamp');
            status_header(400);
            exit('Missing signature or timestamp');
        }
        
        // Calculate expected signature
        $expected_signature = hash_hmac('sha256', $timestamp . $request_body, wp_hash($this->api_key));
        
        if (!hash_equals($expected_signature, $signature)) {
            $this->log('Invalid webhook signature');
            status_header(401);
            exit('Invalid signature');
        }
        
        // Parse the webhook data
        $webhook_data = json_decode($request_body, true);
        
        if (!$webhook_data || !isset($webhook_data['event']) || !isset($webhook_data['data']['invoice_id'])) {
            $this->log('Invalid webhook data');
            status_header(400);
            exit('Invalid webhook data');
        }
        
        $invoice_id = $webhook_data['data']['invoice_id'];
        $event = $webhook_data['event'];
        
        // Find the order by invoice ID
        $orders = wc_get_orders(array(
            'meta_key' => '_splitroute_invoice_id',
            'meta_value' => $invoice_id,
            'limit' => 1,
        ));
        
        if (empty($orders)) {
            $this->log('Order not found for invoice ID: ' . $invoice_id);
            status_header(404);
            exit('Order not found');
        }
        
        $order = $orders[0];
        
        // Process the webhook event
        switch ($event) {
            case 'invoice.paid':
                $this->log('Payment received for order #' . $order->get_id());
                $order->add_order_note(__('Nano payment received and confirmed.', 'wc-splitroute-nano'));
                break;
                
            case 'invoice.forwarded':
                $this->log('Payment forwarded for order #' . $order->get_id());
                $order->add_order_note(__('Nano payment forwarded to recipients.', 'wc-splitroute-nano'));
                break;
                
            case 'invoice.done':
                $this->log('Payment completed for order #' . $order->get_id());
                $order->add_order_note(__('Nano payment completed.', 'wc-splitroute-nano'));
                $order->payment_complete();
                
                // Clear the cart now that payment is confirmed
                if (WC()->cart) {
                    WC()->cart->empty_cart();
                }
                break;
                
            case 'invoice.expired':
                $this->log('Payment expired for order #' . $order->get_id());
                $order->add_order_note(__('Nano payment request expired.', 'wc-splitroute-nano'));
                $order->update_status('failed', __('Payment request expired.', 'wc-splitroute-nano'));
                break;
                
            default:
                $this->log('Unhandled webhook event: ' . $event);
                break;
        }
        
        // Return success
        status_header(200);
        exit('Webhook processed');
    }

    /**
     * AJAX handler to check payment status
     */
    public function ajax_check_payment() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'splitroute_payment_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Get order ID
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
            return;
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
            return;
        }
        
        // Check if order is paid
        if ($order->is_paid()) {
            wp_send_json_success(array(
                'status' => 'paid',
                'redirect' => $this->get_return_url($order)
            ));
            return;
        }
        
        // Get invoice ID
        $invoice_id = $order->get_meta('_splitroute_invoice_id');
        if (!$invoice_id) {
            wp_send_json_error('Invoice not found');
            return;
        }
        
        // Check invoice status via API
        try {
            $response = wp_remote_get($this->api_base_url . '/invoices/' . $invoice_id, array(
                'headers' => array(
                    'X-API-Key' => $this->api_key
                ),
                'timeout' => 15
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('API request failed: ' . $response->get_error_message());
                return;
            }
            
            $body = wp_remote_retrieve_body($response);
            $invoice = json_decode($body, true);
            
            if (!$invoice) {
                wp_send_json_error('Invalid API response');
                return;
            }
            
            // Return invoice status
            wp_send_json_success(array(
                'status' => $invoice['is_paid'] ? 'paid' : ($invoice['is_pending'] ? 'pending' : 'waiting'),
                'is_expired' => $invoice['is_expired'],
                'received' => $invoice['received']['formatted_amount'],
                'required' => $invoice['required']['formatted_amount']
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error checking payment: ' . $e->getMessage());
        }
        
        wp_die();
    }

    /**
     * Output for the order receipt page.
     *
     * @param int $order_id
     */
    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_payment_method() !== $this->id) {
            $this->log('Receipt page: Invalid order or payment method mismatch for order #' . $order_id);
            return;
        }
        
        // Get payment data
        $payment_data = $order->get_meta('_splitroute_payment_data');
        
        // If payment data doesn't exist, construct it from individual meta fields
        if (empty($payment_data)) {
            $this->log('Receipt page: Constructing payment data from meta fields for order #' . $order_id);
            
            $invoice_id = $order->get_meta('_splitroute_invoice_id');
            $account_address = $order->get_meta('_splitroute_account_address');
            $amount = $order->get_meta('_splitroute_required_amount');
            $expires_at = $order->get_meta('_splitroute_expires_at');
            $qr_code = $order->get_meta('_splitroute_qr_code');
            $uri_nano = 'nano:' . $account_address . '?amount=' . $amount;
            
            if ($invoice_id && $account_address && $amount) {
                $payment_data = array(
                    'invoice_id' => $invoice_id,
                    'account_address' => $account_address,
                    'amount' => $amount,
                    'currency' => 'XNO',
                    'expires_at' => $expires_at,
                    'qr_code' => $qr_code,
                    'uri_nano' => $uri_nano
                );
                
                // Store this for future use
                $order->update_meta_data('_splitroute_payment_data', json_encode($payment_data));
                $order->save();
            }
        } else {
            // If payment data exists but is a JSON string, decode it
            $payment_data = json_decode($payment_data, true);
        }
        
        if (empty($payment_data)) {
            $this->log('Receipt page: Payment data not found for order #' . $order_id);
            echo '<p>' . __('Payment information not available. Please contact support.', 'wc-splitroute-nano') . '</p>';
            
            // Debug output for admins
            if (current_user_can('manage_options')) {
                echo '<h3>' . __('Debug Info (visible to admins only):', 'wc-splitroute-nano') . '</h3>';
                echo '<p><strong>' . __('Order ID:', 'wc-splitroute-nano') . '</strong> ' . $order_id . '</p>';
                echo '<p><strong>' . __('Payment Method:', 'wc-splitroute-nano') . '</strong> ' . $order->get_payment_method() . '</p>';
                echo '<p><strong>' . __('Order Meta:', 'wc-splitroute-nano') . '</strong> ' . print_r($order->get_meta_data(), true) . '</p>';
            }
            
            return;
        }
        
        // Display payment container
        echo '<div id="splitroute-payment-container"></div>';
        
        // Enqueue scripts and styles
        wp_enqueue_style(
            'wc-splitroute-nano-modal-css',
            WC_SPLITROUTE_NANO_PLUGIN_URL . 'assets/css/wc-splitroute-nano-modal.css',
            array(),
            WC_SPLITROUTE_NANO_VERSION
        );
        
        wp_enqueue_script(
            'wc-splitroute-nano-modal-js',
            WC_SPLITROUTE_NANO_PLUGIN_URL . 'assets/js/wc-splitroute-nano-modal.js',
            array('jquery'),
            WC_SPLITROUTE_NANO_VERSION,
            true
        );
        
        // Pass data to JavaScript
        wp_localize_script(
            'wc-splitroute-nano-modal-js',
            'wc_splitroute_params',
            array(
                'payment_data' => json_encode($payment_data),
                'thank_you_url' => $this->get_return_url($order),
                'ajax_url' => admin_url('admin-ajax.php'),
                'order_id' => $order_id,
                'nonce' => wp_create_nonce('splitroute_payment_nonce'),
                'i18n_waiting' => __('Waiting for payment...', 'wc-splitroute-nano'),
                'i18n_received' => __('Payment received! Processing...', 'wc-splitroute-nano'),
                'i18n_completed' => __('Payment completed!', 'wc-splitroute-nano'),
                'i18n_expired' => __('Payment request expired.', 'wc-splitroute-nano'),
            )
        );
        
        // Add cancel button
        echo '<p class="cancel-button-container">';
        echo '<a class="button cancel" href="' . esc_url(add_query_arg(array(
            'cancel_order' => 'true',
            'order_id' => $order_id,
            '_wpnonce' => wp_create_nonce('woocommerce-cancel_order')
        ), wc_get_cart_url())) . '">' . __('Cancel order', 'wc-splitroute-nano') . '</a>';
        echo '</p>';
    }

    /**
     * AJAX handler to confirm payment
     */
    public function ajax_confirm_payment() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'splitroute_payment_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Get order ID
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
            return;
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
            return;
        }
        
        // If order is already processing or completed, just return success
        if ($order->has_status(array('processing', 'completed'))) {
            wp_send_json_success(array(
                'redirect_url' => $this->get_return_url($order)
            ));
            return;
        }
        
        // Get invoice ID
        $invoice_id = $order->get_meta('_splitroute_invoice_id');
        if (!$invoice_id) {
            wp_send_json_error('Invoice not found');
            return;
        }
        
        // Check invoice status via API
        try {
            $response = wp_remote_get($this->api_base_url . '/invoices/' . $invoice_id, array(
                'headers' => array(
                    'X-API-Key' => $this->api_key
                ),
                'timeout' => 15
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('API request failed: ' . $response->get_error_message());
                return;
            }
            
            $body = wp_remote_retrieve_body($response);
            $invoice = json_decode($body, true);
            
            if (!$invoice) {
                wp_send_json_error('Invalid API response');
                return;
            }
            
            $this->log('Checking payment for invoice: ' . $invoice_id . ', Response: ' . $body);
            
            // Check if payment is detected (either confirmed or fully paid)
            if ($invoice['is_paid'] || 
                (isset($invoice['payments']) && !empty($invoice['payments'])) || 
                (isset($invoice['status']) && $invoice['status'] === 'paid')) {
                
                // Mark order as processing
                $order->update_status('processing', __('Payment confirmed via SplitRoute.', 'wc-splitroute-nano'));
                
                // Add order note
                $order->add_order_note(__('Nano payment completed via SplitRoute.', 'wc-splitroute-nano'));
                
                // Empty cart
                WC()->cart->empty_cart();
                
                // Return success with redirect URL
                wp_send_json_success(array(
                    'redirect_url' => $this->get_return_url($order)
                ));
            } else {
                wp_send_json_error('Payment not yet confirmed');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error checking payment: ' . $e->getMessage());
        }
        
        wp_die();
    }

    /**
     * AJAX handler to process payment directly from WebSocket notification
     */
    public function ajax_process_payment() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'splitroute_payment_nonce')) {
            wp_send_json_error('Invalid security token');
            return;
        }
        
        // Get order ID
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error('Invalid order ID');
            return;
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Order not found');
            return;
        }
        
        // If order is already processing or completed, just return success
        if ($order->has_status(array('processing', 'completed'))) {
            wp_send_json_success(array(
                'redirect_url' => $this->get_return_url($order)
            ));
            return;
        }
        
        // Get payment details from WebSocket
        $payment_details = isset($_POST['payment_details']) ? json_decode(stripslashes($_POST['payment_details']), true) : null;
        if (!$payment_details || !isset($payment_details['invoice_id'])) {
            wp_send_json_error('Invalid payment details');
            return;
        }
        
        // Log the payment details
        $this->log('Processing payment from WebSocket notification: ' . json_encode($payment_details));
        
        // Verify the invoice ID matches
        $stored_invoice_id = $order->get_meta('_splitroute_invoice_id');
        if ($stored_invoice_id !== $payment_details['invoice_id']) {
            wp_send_json_error('Invoice ID mismatch');
            return;
        }
        
        // Process the order
        $order->update_status('processing', __('Payment confirmed via SplitRoute WebSocket notification.', 'wc-splitroute-nano'));
        $order->add_order_note(sprintf(
            __('Nano payment of %s XNO received at %s.', 'wc-splitroute-nano'),
            $payment_details['amount'],
            date('Y-m-d H:i:s', strtotime($payment_details['timestamp']))
        ));
        
        // Empty cart
        WC()->cart->empty_cart();
        
        // Return success with redirect URL
        wp_send_json_success(array(
            'redirect_url' => $this->get_return_url($order)
        ));
        
        wp_die();
    }
} 