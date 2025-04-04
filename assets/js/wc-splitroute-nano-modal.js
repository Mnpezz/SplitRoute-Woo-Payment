(function($) {
    'use strict';

    // Modal HTML template
    const modalTemplate = `
        <div id="splitroute-modal-overlay" class="splitroute-modal-overlay">
            <div id="splitroute-modal" class="splitroute-modal">
                <div class="splitroute-modal-header">
                    <h2>Pay with Nano (XNO)</h2>
                    <span class="splitroute-modal-close">&times;</span>
                </div>
                <div class="splitroute-modal-body">
                    <div class="splitroute-payment-details">
                        <p>Please send the following amount of Nano to complete your payment:</p>
                        <div class="splitroute-amount-container">
                            <p><strong>Amount:</strong> <span id="splitroute-amount"></span> XNO
                            <button id="splitroute-copy-amount-btn" class="splitroute-copy-btn">Copy Amount</button></p>
                        </div>
                        <div class="splitroute-address-container">
                            <p><strong>Address:</strong> <span id="splitroute-address" class="splitroute-address"></span>
                            <button id="splitroute-copy-btn" class="splitroute-copy-btn">Copy Address</button></p>
                        </div>
                        <div id="splitroute-qr-container" class="splitroute-qr-container">
                            <img id="splitroute-qr-code" src="" alt="QR Code" />
                            <p class="splitroute-qr-caption">Scan to pay with Nano wallet</p>
                        </div>
                        <p><strong>Expires:</strong> <span id="splitroute-expires"></span></p>
                        <div id="splitroute-payment-status" class="splitroute-payment-status">
                            <p class="splitroute-status-message">Waiting for payment...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Add modal to the page
    $('body').append(modalTemplate);

    // Modal elements
    const $modal = $('#splitroute-modal-overlay');
    const $closeBtn = $('.splitroute-modal-close');
    const $copyBtn = $('#splitroute-copy-btn');
    const $statusMessage = $('.splitroute-status-message');

    // Close modal when clicking the close button or outside the modal
    $closeBtn.on('click', closeModal);
    $modal.on('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Copy address to clipboard
    $copyBtn.on('click', function() {
        const address = $('#splitroute-address').text();
        
        // Create a temporary input element
        const tempInput = document.createElement('input');
        tempInput.value = address;
        document.body.appendChild(tempInput);
        
        // Select and copy the text
        tempInput.select();
        document.execCommand('copy');
        
        // Remove the temporary element
        document.body.removeChild(tempInput);
        
        // Update button text
        $copyBtn.text('Copied!');
        setTimeout(function() {
            $copyBtn.text('Copy');
        }, 2000);
    });

    // Copy amount to clipboard
    $('#splitroute-copy-amount-btn').on('click', function() {
        const amount = $('#splitroute-amount').text();
        
        // Create a temporary input element
        const tempInput = document.createElement('input');
        tempInput.value = amount;
        document.body.appendChild(tempInput);
        
        // Select and copy the text
        tempInput.select();
        document.execCommand('copy');
        
        // Remove the temporary element
        document.body.removeChild(tempInput);
        
        // Update button text
        $(this).text('Copied!');
        setTimeout(function() {
            $('#splitroute-copy-amount-btn').text('Copy Amount');
        }, 2000);
    });

    // Function to open the modal with payment details
    function openPaymentModal(paymentData) {
        // Set payment details in the modal
        $('#splitroute-amount').text(paymentData.amount);
        $('#splitroute-address').text(paymentData.account_address);
        
        // Format expiry date
        const expiryDate = new Date(paymentData.expires_at);
        $('#splitroute-expires').text(expiryDate.toLocaleString());
        
        // Generate QR code if not provided or invalid
        if (!paymentData.qr_code || paymentData.qr_code.includes('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQ')) {
            // Convert amount to raw value (multiply by 10^30 for Nano)
            // For QR codes, we need to use the raw amount format
            const rawAmount = BigInt(Math.round(parseFloat(paymentData.amount) * 1000000)) * BigInt(10**24);
            
            // Generate QR code for nano address with amount in raw format
            const nanoUri = 'nano:' + paymentData.account_address + '?amount=' + rawAmount.toString();
            console.log('Generated Nano URI:', nanoUri);
            
            const qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(nanoUri);
            $('#splitroute-qr-code').attr('src', qrCodeUrl);
            $('#splitroute-qr-container').show();
        } else {
            // Use provided QR code
            $('#splitroute-qr-code').attr('src', paymentData.qr_code);
            $('#splitroute-qr-container').show();
        }
        
        // Show the modal
        $modal.css('display', 'flex');
        
        // Connect to WebSocket for real-time updates
        connectWebSocket(paymentData.invoice_id);
    }

    // Function to close the modal
    function closeModal() {
        $modal.fadeOut(300);
        
        // Disconnect WebSocket if connected
        if (window.splitrouteSocket && window.splitrouteSocket.readyState === WebSocket.OPEN) {
            window.splitrouteSocket.close();
        }
    }

    // Function to handle WebSocket messages
    function handleWebSocketMessage(message) {
        console.log('WebSocket message received:', message);
        
        if (message.payload && message.payload.event_type) {
            switch(message.payload.event_type) {
                case 'payment.confirmed':
                    // Extract payment details from the WebSocket message
                    const paymentDetails = {
                        invoice_id: message.payload.id,
                        amount: message.payload.formatted_amount,
                        timestamp: message.payload.timestamp
                    };
                    
                    // Show processing message
                    $statusMessage.text('Payment detected! Processing order...');
                    $statusMessage.addClass('splitroute-status-pending');
                    
                    // Process the order directly without checking the API again
                    processOrderWithPayment(paymentDetails);
                    break;
                case 'invoice.paid':
                    $statusMessage.text(wc_splitroute_params.i18n_received || 'Payment received! Processing...');
                    $statusMessage.removeClass('splitroute-status-pending').addClass('splitroute-status-paid');
                    break;
                case 'invoice.done':
                    $statusMessage.text(wc_splitroute_params.i18n_completed || 'Payment completed!');
                    $statusMessage.removeClass('splitroute-status-pending splitroute-status-paid').addClass('splitroute-status-completed');
                    break;
                case 'invoice.expired':
                    $statusMessage.text(wc_splitroute_params.i18n_expired || 'Payment request expired.');
                    $statusMessage.addClass('splitroute-status-expired');
                    break;
            }
        }
    }

    // Function to process the order with payment details from WebSocket
    function processOrderWithPayment(paymentDetails) {
        // Process the order directly with the payment details from WebSocket
        $.ajax({
            url: wc_splitroute_params.ajax_url,
            type: 'POST',
            data: {
                action: 'splitroute_process_payment',
                order_id: wc_splitroute_params.order_id,
                nonce: wc_splitroute_params.nonce,
                payment_details: JSON.stringify(paymentDetails)
            },
            success: function(response) {
                if (response.success && response.data && response.data.redirect_url) {
                    // Show success message before redirecting
                    $statusMessage.text('Payment confirmed! Redirecting to order confirmation...');
                    $statusMessage.removeClass('splitroute-status-pending').addClass('splitroute-status-completed');
                    
                    // Redirect to thank you page after a short delay
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1500);
                } else {
                    // If there was an error in the response
                    if (response.data && typeof response.data === 'string') {
                        $statusMessage.text('Error: ' + response.data);
                    } else {
                        $statusMessage.text('Error processing payment. Please contact support.');
                    }
                    $statusMessage.removeClass('splitroute-status-pending').addClass('splitroute-status-error');
                }
            },
            error: function(xhr, status, error) {
                // Handle AJAX error
                $statusMessage.text('Error connecting to server. Please contact support.');
                $statusMessage.removeClass('splitroute-status-pending').addClass('splitroute-status-error');
                console.error('AJAX Error:', error);
            }
        });
    }

    // Function to connect to WebSocket
    function connectWebSocket(invoiceId) {
        const wsUrl = `wss://api.splitroute.com/api/v1/ws/invoices/${invoiceId}`;
        const socket = new WebSocket(wsUrl);
        
        socket.onopen = function(event) {
            console.log('Connected to SplitRoute WebSocket');
        };
        
        socket.onmessage = function(event) {
            console.log('WebSocket connection established');
            const message = JSON.parse(event.data);
            handleWebSocketMessage(message);
        };
        
        socket.onclose = function(event) {
            console.log('WebSocket connection closed');
        };
        
        socket.onerror = function(error) {
            console.error('WebSocket error:', error);
            $statusMessage.text('Error connecting to payment service. Please refresh and try again.');
            $statusMessage.addClass('splitroute-status-error');
        };
        
        // Store socket reference
        window.splitrouteSocket = socket;
    }

    // Handle checkout form submission
    if ($('form.woocommerce-checkout').length) {
        // Override the checkout process for our payment method
        $(document.body).on('checkout_place_order_splitroute_nano', function() {
            return true; // Let WooCommerce handle the form submission
        });
        
        // Listen for our payment method's response
        $(document.body).on('payment_method_selected', function() {
            if ($('#payment_method_splitroute_nano').is(':checked')) {
                // Our payment method is selected
                console.log('Nano payment method selected');
            }
        });
        
        // Listen for checkout completion with our payment method
        $(document.body).on('checkout_error', function() {
            // Handle checkout errors
        });
        
        // Handle successful checkout with our payment method
        $(document.body).on('checkout_place_order_success', function(event, result) {
            if (result.payment_data && result.result === 'success') {
                // Open the payment modal with the payment data
                openPaymentModal(result.payment_data);
                
                // Prevent the default redirect
                if (typeof result.redirect === 'boolean' && !result.redirect) {
                    return false;
                }
            }
            return true;
        });
    }

    // Initialize payment modal if we have payment data
    $(document).ready(function() {
        // Check if we're on the payment page with a payment container
        if ($('#splitroute-payment-container').length && typeof wc_splitroute_params !== 'undefined') {
            try {
                // Parse the payment data
                const paymentData = JSON.parse(wc_splitroute_params.payment_data);
                
                // Check if we have valid payment data
                if (paymentData && paymentData.account_address && paymentData.amount) {
                    openPaymentModal(paymentData);
                    
                    // Set up a polling mechanism to check payment status
                    const checkPaymentInterval = setInterval(function() {
                        $.ajax({
                            url: wc_splitroute_params.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'check_splitroute_payment',
                                order_id: wc_splitroute_params.order_id,
                                nonce: wc_splitroute_params.nonce
                            },
                            success: function(response) {
                                if (response.success && response.data.status === 'paid') {
                                    clearInterval(checkPaymentInterval);
                                    
                                    // Confirm the payment
                                    $.ajax({
                                        url: wc_splitroute_params.ajax_url,
                                        type: 'POST',
                                        data: {
                                            action: 'splitroute_confirm_payment',
                                            order_id: wc_splitroute_params.order_id,
                                            nonce: wc_splitroute_params.nonce
                                        },
                                        success: function(confirmResponse) {
                                            if (confirmResponse.success) {
                                                window.location.href = confirmResponse.data.redirect_url;
                                            }
                                        }
                                    });
                                }
                            }
                        });
                    }, 5000); // Check every 5 seconds
                } else {
                    console.error('Invalid payment data structure');
                }
            } catch (e) {
                console.error('Error parsing payment data:', e);
            }
        }
    });

    // Expose functions to global scope
    window.splitrouteNano = {
        openPaymentModal: openPaymentModal,
        closeModal: closeModal
    };

})(jQuery); 