(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Check if we're on the order received page with a SplitRoute payment
        const paymentStatus = $('#wc-splitroute-payment-status');
        if (paymentStatus.length === 0) {
            return;
        }
        
        // Get the invoice ID
        const invoiceId = paymentStatus.data('invoice-id');
        if (!invoiceId) {
            return;
        }
        
        // Initialize clipboard functionality
        const copyBtn = $('.wc-splitroute-copy-btn');
        if (copyBtn.length > 0) {
            copyBtn.on('click', function(e) {
                e.preventDefault();
                const text = $(this).data('clipboard-text');
                navigator.clipboard.writeText(text).then(function() {
                    const originalText = copyBtn.text();
                    copyBtn.text('Copied!');
                    setTimeout(function() {
                        copyBtn.text(originalText);
                    }, 2000);
                });
            });
        }
        
        // Connect to WebSocket
        connectWebSocket(invoiceId);
    });
    
    /**
     * Connect to SplitRoute WebSocket for real-time payment updates
     * 
     * @param {string} invoiceId 
     */
    function connectWebSocket(invoiceId) {
        const statusMessage = $('.wc-splitroute-status-message');
        const wsUrl = `wss://api.splitroute.com/api/v1/ws/invoices/${invoiceId}`;
        
        // Create WebSocket connection
        const socket = new WebSocket(wsUrl);
        
        socket.onopen = function(e) {
            console.log('Connected to SplitRoute WebSocket');
        };
        
        socket.onmessage = function(event) {
            const message = JSON.parse(event.data);
            console.log('Received message:', message);
            
            // Process different event types
            if (message.payload && message.payload.event_type) {
                switch (message.payload.event_type) {
                    case 'invoice.paid':
                        statusMessage.html(wc_splitroute_nano_params.i18n_received);
                        statusMessage.addClass('wc-splitroute-status-received');
                        break;
                        
                    case 'invoice.done':
                        statusMessage.html(wc_splitroute_nano_params.i18n_completed);
                        statusMessage.removeClass('wc-splitroute-status-received');
                        statusMessage.addClass('wc-splitroute-status-completed');
                        
                        // Refresh the page after a short delay to show updated order status
                        setTimeout(function() {
                            window.location.reload();
                        }, 3000);
                        break;
                        
                    case 'invoice.expired':
                        statusMessage.html(wc_splitroute_nano_params.i18n_expired);
                        statusMessage.addClass('wc-splitroute-status-expired');
                        break;
                }
            }
            
            // Check for completion category
            if (message.category === 'completed') {
                console.log('Invoice processing completed');
                socket.close();
            }
        };
        
        socket.onclose = function(event) {
            if (event.wasClean) {
                console.log(`Connection closed cleanly, code=${event.code} reason=${event.reason}`);
            } else {
                console.log('Connection died');
                
                // Try to reconnect after a delay
                setTimeout(function() {
                    connectWebSocket(invoiceId);
                }, 5000);
            }
        };
        
        socket.onerror = function(error) {
            console.log(`WebSocket error: ${error.message}`);
        };
    }
    
})(jQuery); 