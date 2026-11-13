# SignalHouse PHP SDK

Official PHP SDK for the SignalHouse platform.

Full API reference and guides: https://app2.signalhouse.io/docs

## Requirements

- PHP 8.1 or higher
- Guzzle HTTP client 7.x

## Installation

```bash
composer require signalhouse-sms/sdk
```

## Quick Start

```php
use SignalHouse\SDK\SignalHouseSDK;

$sdk = new SignalHouseSDK(
    apiKey: 'your-api-key',
    baseUrl: 'https://v2.signalhouse.io'
);

// Login
$response = $sdk->auth->login('user@example.com', 'password');

// Search for available phone numbers
$numbers = $sdk->numbers->getAvailablePhoneNumbers([
    'country' => 'US',
    'state' => 'IL',
    'npa' => '217',
]);

// Purchase a phone number
$result = $sdk->numbers->purchasePhoneNumber(
    phoneNumbers: ['2175551234'],
    subgroupId: 'SG12345678'
);

// Send an SMS
$result = $sdk->messages->sendSMS(
    senderPhoneNumber: '2175551234',
    recipientPhoneNumbers: '3125559876',
    messageBody: 'Hello from SignalHouse!'
);

// Get brands
$brands = $sdk->brands->getBrands(['groupId' => 'G12345678']);

// Check what a group's new toll-free brands need; when reviewsBusinessOnSubmit is true,
// createTollFreeBrand also needs the business structure, industry and contact details
$requirements = $sdk->brands->getTollFreeRequirements('G12345678');

// Check wallet balance
$wallet = $sdk->billing->getWallet('G12345678');
```

## Available Domains

| Domain | Description |
|--------|-------------|
| `$sdk->auth` | Authentication and login history |
| `$sdk->billing` | Wallet, payment methods, transactions, invoices, fees |
| `$sdk->brands` | 10DLC and toll-free brand registration and management |
| `$sdk->campaigns` | 10DLC campaign management |
| `$sdk->groups` | Group management |
| `$sdk->landings` | Landing page management (with file uploads) |
| `$sdk->templates` | Versioned message templates: drafts, publish, archive, validate, capabilities, media upload |
| `$sdk->rcs->agents` | RCS agents: drafts linked to a brand and campaign, validate, submit, archive, logo/hero upload |
| `$sdk->rcs->messages` | RCS messages: send, validate, estimate, capability check, read |
| `$sdk->messages` | Send SMS/MMS/Group MMS, message logs, analytics |
| `$sdk->notifications` | Notification management |
| `$sdk->numbers` | Phone number search, purchase, assign, release, transfer |
| `$sdk->shortlinks` | URL shortener |
| `$sdk->subgroups` | Subgroup management |
| `$sdk->subscriptions` | Subscription and plan management |
| `$sdk->users` | User management and notification preferences |
| `$sdk->verify` | Carrier phone-number checks: SIM Swap and Number Verify (phone on mobile data; each answered check is billed) |
| `$sdk->webhooks` | Webhook management |

## Admin Endpoints

To access admin-only endpoints, initialize with `enableAdmin: true`:

```php
$sdk = new SignalHouseSDK(
    apiKey: 'your-admin-api-key',
    baseUrl: 'https://v2.signalhouse.io',
    enableAdmin: true
);

// Admin: approve a campaign
$sdk->campaigns->admin->approveCampaign('campaign-id');

// Admin: list all groups
$sdk->groups->admin->getGroups(['page' => 1, 'limit' => 20]);
```

## Response Format

All methods return a standardized response array:

```php
// Success
[
    'success' => true,
    'data' => [...],  // Response data
    'status' => 200,  // HTTP status code
]

// Error
[
    'success' => false,
    'error' => 'Error message',
    'status' => 400,  // HTTP status code
]
```

## License

ISC - Signal House LLC
