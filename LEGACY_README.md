> **This package has moved.** `signalhouse/sdk` is now [`signalhouse-sms/sdk`](https://packagist.org/packages/signalhouse-sms/sdk), developed at
> https://github.com/signalhouse-sms/sdk-php. This package is a thin shim that installs `signalhouse-sms/sdk` at the
> same version and re-exports it. It receives every release until **2027-05-01**, then stops.
>
> **Migrate:** `composer remove signalhouse/sdk && composer require signalhouse-sms/sdk`. The `SignalHouse\SDK\` namespace is unchanged.

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

// List a group's business verifications; when required is true, a new toll-free brand names a
// VERIFIED one as tollFree.businessVerificationId (createBusinessVerification adds one)
$verifications = $sdk->brands->getBusinessVerifications('G12345678');

// A group that verifies each subgroup's business separately names the subgroup, for both
// getBusinessVerifications and createBusinessVerification
$subgroupVerifications = $sdk->brands->getBusinessVerifications('G12345678', subgroupId: 'S1234567');

// Check wallet balance
$wallet = $sdk->billing->getWallet('G12345678');
```

## Available Domains

| Domain | Description |
|--------|-------------|
| `$sdk->auth` | Authentication and login history |
| `$sdk->billing` | Wallet, payment methods, transactions, invoices, fees |
| `$sdk->brands` | 10DLC brand registration and management, toll-free business verification |
| `$sdk->campaigns` | 10DLC campaign management |
| `$sdk->groups` | Group management |
| `$sdk->landings` | Landing page management (with file uploads) |
| `$sdk->templates` | Versioned message templates: drafts, publish, archive, validate, capabilities, media upload |
| `$sdk->messages` | Send SMS/MMS/Group MMS, message logs, analytics |
| `$sdk->notifications` | Notification management |
| `$sdk->numbers` | Phone number search, purchase, assign, release, transfer |
| `$sdk->shortlinks` | URL shortener |
| `$sdk->subgroups` | Subgroup management |
| `$sdk->subscriptions` | Subscription and plan management |
| `$sdk->users` | User management and notification preferences |
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

Staff-only brand methods sit on `$sdk->brands` itself, and the API checks the caller's role:

```php
// Staff: read a group's toll-free ISV account (null when it has none; the API key secret is never returned)
$isvAccount = $sdk->brands->getTollFreeIsvAccount('G12345678');

// signalhouse_admin only: register it before setting the group's toll-free provider
// (once the group has records with that provider, only the API key can be replaced)
$sdk->brands->setTollFreeIsvAccount(
    groupId: 'G12345678',
    accountSid: 'AC...',
    apiKeySid: 'SK...',
    apiKeySecret: 'your-api-key-secret'
);
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
