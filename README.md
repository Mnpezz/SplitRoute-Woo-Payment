# WooCommerce SplitRoute Nano Gateway

Accept Nano (XNO) cryptocurrency payments in your WooCommerce store using the SplitRoute API.

## Features

- Accept Nano (XNO) payments in your WooCommerce store
- Real-time payment notifications via WebSockets
- QR code for easy payments
- Support for splitting payments to multiple destinations
- Automatic payment verification and order status updates
- Test mode for development and testing

## Requirements

- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.2 or higher
- A SplitRoute API key
- A Nano wallet address

## Installation

1. Download the plugin zip file
2. Go to WordPress Admin > Plugins > Add New
3. Click "Upload Plugin" and select the zip file
4. Activate the plugin

## Configuration

1. Go to WooCommerce > Settings > Payments
2. Click "Manage" next to "Nano (XNO) Cryptocurrency"
3. Enable the payment method
4. Enter your SplitRoute API key and Nano wallet address
5. Configure other settings as needed
6. Save changes

## Split Payments

You can configure the plugin to split payments to multiple destinations. This is useful for:

- Paying affiliates or partners automatically
- Distributing revenue to team members
- Setting aside funds for taxes or fees

To configure split payments:

1. Enable "Split Payments" in the plugin settings
2. Enter your split payment destinations in JSON format
3. Save changes

Example split payment configuration:
json
[
{
"account": "nano_address1",
"percentage": 10,
"description": "Partner Fee"
},
{
"account": "nano_address2",
"nominal_amount": 5,
"description": "Fixed Fee"
}
]


## Support

For support, please contact [your email or website].

## License

This plugin is licensed under the GPL v2 or later.