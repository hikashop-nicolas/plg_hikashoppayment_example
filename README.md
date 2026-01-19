# HikaShop Example Payment Plugin

This is a reference implementation of a **HikaShop Payment Plugin**. It is designed to serve as a starting point for developers who want to integrate a new payment gateway into HikaShop.

## Overview

In HikaShop, payment plugins are standard Joomla plugins belonging to the `hikashoppayment` group. This example demonstrates the best practices for handling the payment flow, redirecting users to a gateway, and processing server-to-server notifications (webhooks).

## Features

- **Base Class Extension**: Inherits from `hikashopPaymentPlugin` to access powerful helper methods.
- **Multiple Instances**: Supports creating multiple payment methods in the backend using the same plugin (e.g., for different accounts).
- **Environment Management**: Easily switch between **Sandbox (Test)** and **Production** API endpoints.
- **Security Signature**: Implements a robust SHA-256 signature mechanism to prevent data tampering.
- **Automated Redirects**: Uses a hidden form (`example_end.php`) for seamless redirection to the gateway.
- **Webhook Handling**: Fully functional `onPaymentNotification` method to handle asynchronous payment status updates.
- **Address Formatting**: Includes a utility method to split unified street lines into street names and house numbers.
- **Debug Logging**: Integrated logging that writes to the HikaShop Payment Log for troubleshooting.

## File Structure

- `example.php`: The main plugin class and logic.
- `example.xml`: Extension manifest for Joomla installation.
- `example_end.php`: The view layout displayed at the end of the checkout (usually for auto-redirection).
- `example_configuration.php`: (Optional) Custom HTML for more complex backend settings.

## Configuration Parameters

Navigate to **HikaShop > Configuration > Payment Methods** and create a new instance of "Example" to see these options:

| Parameter | Type | Description |
| :--- | :--- | :--- |
| **Identifier** | Input | Your Merchant ID or API User ID provided by the gateway. |
| **Password** | Input | Your Secret Key or API Password used for hashing. |
| **Environment** | List | Switch between Sandbox and Production URLs. |
| **Debug** | Boolean | Enable this to log raw gateway requests/responses. |
| **Verified Status** | Order Status | The status set on the order when payment is successful (e.g., *Confirmed*). |
| **Invalid Status** | Order Status | The status set on the order when payment fails (e.g., *Cancelled*). |

## Standard HikaShop URLs

The plugin uses the following standard URLs for the payment flow:

- **Cancel URL**: Returns the user to the checkout to choose another payment method.
- **Return URL**: The "Thank You" page shown after a successful transaction.
- **Notify URL (Webhook)**: The URL the gateway should call to notify HikaShop of a payment status change.

## Implementation Details

### Security Hash
The plugin sorts all outgoing and incoming parameters alphabetically and wraps them with a shared secret before calculating a SHA-256 hash.

### Signature Verification
During a notification (`onPaymentNotification`), the plugin validates the signature of the incoming request before updating any order data, ensuring that the request truly came from the payment gateway.

## Useful Links

- **Official Website**: [https://www.hikashop.com](https://www.hikashop.com)
- **Developer Documentation**: [HikaShop Developer Guide](https://www.hikashop.com/support/documentation/62-hikashop-developer-documentation.html)
- **Payment Marketplace**: [Browse Existing Payment Plugins](https://www.hikashop.com/marketplace/category/33-payment.html)

---
*Created by the HikaShop Team.*
