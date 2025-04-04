# SplitRoute Nano Payment Gateway for WooCommerce

Accept Nano (XNO) cryptocurrency payments in your WooCommerce store with the SplitRoute payment gateway. This plugin allows for fast, feeless, and eco-friendly cryptocurrency payments with optional payment splitting capabilities.

## Features

- Accept Nano (XNO) payments directly in your WooCommerce store
- Real-time payment detection via WebSocket
- QR code generation for easy mobile payments
- Optional payment splitting between multiple Nano accounts
- Detailed payment logs and transaction history
- Mobile-friendly payment interface

## Installation

1. Download the plugin ZIP file
2. Go to WordPress Admin > Plugins > Add New > Upload Plugin
3. Upload the ZIP file and activate the plugin
4. Go to WooCommerce > Settings > Payments
5. Enable "Nano (XNO) via SplitRoute" and click "Manage"
6. Configure your SplitRoute API key and Nano account address

## Configuration

### Required Settings

- **SplitRoute API Key**: Get your API key from [SplitRoute](https://api.splitroute.com/api/v1/api-keys/register)
- **Primary Nano Account**: Your main Nano account address where payments will be sent

### Optional Settings

- **Enable Split Payments**: Distribute payments between multiple Nano accounts
- **Split Payment Destinations**: Configure additional payment recipients with percentages or fixed amounts
- **Debug Log**: Enable detailed logging for troubleshooting

## Payment Splitting

To configure payment splitting, enable the option and add your split destinations in JSON format:
json
[
    {
        "account": "nano_address1",
        "percentage": 10,
        "description": "Partner Fee"
    },
]

