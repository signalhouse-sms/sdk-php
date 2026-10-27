<?php
// Request-contract coverage for registrations and regional reporting filters.
// Run from sdks/php: php tests/registrations.php
require __DIR__ . '/../src/Exceptions/SignalHouseException.php';
require __DIR__ . '/../src/Exceptions/ValidationException.php';
require __DIR__ . '/../src/HttpClient.php';
require __DIR__ . '/../src/Domains/Registrations.php';
require __DIR__ . '/../src/Domains/Messages.php';
require __DIR__ . '/../src/Domains/Numbers.php';
require __DIR__ . '/../src/Domains/Shortlinks.php';
require __DIR__ . '/../src/Domains/Brands.php';

/** Capture serialized requests without sending them; inherits the real query and validation rules. */
class RegistrationSpyClient extends \SignalHouse\SDK\HttpClient
{
    public array $calls = [];
    public function __construct() {}
    public function request(string $url, array $options = []): array
    {
        $this->calls[] = [$url, $options];
        return ['success' => true, 'data' => []];
    }
}

function check($condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function queryOf(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    return $query;
}

$client = new RegistrationSpyClient();
$messages = new \SignalHouse\SDK\Domains\Messages($client, $client, false);
$scopes = [['region' => 'GB', 'channels' => ['virtualLongCode'], 'phoneNumber' => ['447400123456']]];
$reads = [
    'getMessages' => '/message', 'getAnalytics' => '/message/analytics', 'getAnalyticsDetail' => '/message/analytics/detail',
    'getAnalyticsBySubgroup' => '/message/analytics/by-subgroup', 'getAnalyticsByErrorCode' => '/message/analytics/by-error-code',
    'getDncAnalytics' => '/message/dnc/analytics', 'getDncRecords' => '/message/dnc',
];
foreach ($reads as $method => $expectedPath) {
    $messages->$method(['groupId' => 'G1', 'region' => 'GB', 'regionScopes' => $scopes], ['token' => 'test-token']);
    [$url, $options] = end($client->calls);
    check(parse_url($url, PHP_URL_PATH) === $expectedPath, "$method path");
    $query = queryOf($url);
    check($query['region'] === 'GB' && json_decode($query['regionScopes'], true) === $scopes, "$method JSON scopes");
    check($options['token'] === 'test-token', "$method token");
    $messages->$method(['groupId' => 'G1', 'region' => ['US', 'GB'], 'regionScopes' => []]);
    $url = end($client->calls)[0];
    check(str_contains($url, 'region=US&region=GB') && queryOf($url)['regionScopes'] === '[]', "$method repeated region and empty scopes");
    $messages->$method(['groupId' => 'G1', 'regionScopes' => '[{"region":"US","channels":["tenDLC"]}]']);
    check(queryOf(end($client->calls)[0])['regionScopes'] === '[{"region":"US","channels":["tenDLC"]}]', "$method string scopes pass through");
    $messages->$method(['groupId' => 'G1']);
    check(!str_contains(end($client->calls)[0], 'region'), "$method omits region by default");
}
$messages->getAnalyticsFilterOptions(['groupId' => 'G1', 'region' => 'GB']);
check(end($client->calls)[0] === '/message/analytics/filter-options?groupId=G1&region=GB', 'filter options region');
$registrationReads = [
    'getAnalytics' => '/message/analytics', 'getAnalyticsDetail' => '/message/analytics/detail',
    'getAnalyticsThroughput' => '/message/analytics/throughput', 'getDncAnalytics' => '/message/dnc/analytics',
    'getAnalyticsBySubgroup' => '/message/analytics/by-subgroup', 'getAnalyticsByErrorCode' => '/message/analytics/by-error-code',
    'getDncRecords' => '/message/dnc', 'getMessages' => '/message',
];
foreach ($registrationReads as $method => $expectedPath) {
    $messages->$method(['groupId' => 'G1', 'campaignId' => 'C1', 'registrationId' => ['REG1', 'REG2']]);
    $url = end($client->calls)[0];
    check($url === "$expectedPath?groupId=G1&campaignId=C1&registrationId=REG1&registrationId=REG2", "$method repeats registrationId");
}
$numbers = new \SignalHouse\SDK\Domains\Numbers($client, $client, false);
$numbers->getPhoneNumbers(['campaignId' => 'C1', 'registrationId' => 'REG1']);
check(end($client->calls)[0] === '/number?campaignId=C1&registrationId=REG1', 'phone numbers registrationId');

$domain = new \SignalHouse\SDK\Domains\Registrations($client, true);
$domain->getCatalog('GB', ['token' => 'test-token']);
[$url, $options] = end($client->calls);
check($url === '/registration/catalog?region=GB' && $options['method'] === 'GET' && $options['token'] === 'test-token', 'catalog');
$domain->getQuotes(['region' => 'GB', 'type' => 'VIRTUAL_LONG_CODE', 'quantity' => 2]);
[$url, $options] = end($client->calls);
check($url === '/registration/quotes?region=GB&type=VIRTUAL_LONG_CODE&quantity=2' && $options['method'] === 'GET', 'quotes');
$domain->getQuotes(['region' => 'UK', 'type' => 'VIRTUAL_LONG_CODE', 'groupId' => 'G1']);
check(end($client->calls)[0] === '/registration/quotes?region=UK&type=VIRTUAL_LONG_CODE&groupId=G1', 'staff quotes');
$domain->getRegistrations([
    'groupId' => 'G1', 'subgroupId' => 'S1', 'region' => ['GB', 'CA'], 'type' => 'VIRTUAL_LONG_CODE', 'kind' => 'sender',
    'status' => ['PENDING_PROVIDER', 'APPROVED'], 'parentRegistrationId' => 'REG0', 'phoneNumber' => '15551234567',
    'sortBy' => 'status', 'sortOrder' => 'asc', 'page' => 2, 'limit' => 10,
]);
check(end($client->calls)[0] === '/registration?groupId=G1&subgroupId=S1&region=GB&region=CA&type=VIRTUAL_LONG_CODE&kind=sender&status=PENDING_PROVIDER&status=APPROVED&parentRegistrationId=REG0&phoneNumber=15551234567&sortBy=status&sortOrder=asc&page=2&limit=10', 'list');
$domain->getRegistrations();
check(end($client->calls)[0] === '/registration', 'list without filters');
$domain->getRegistration('REG 1');
check(end($client->calls)[0] === '/registration/REG%201', 'read escapes id');
$domain->cancelRegistration('GB8T9GTD5AZY');
[$url, $options] = end($client->calls);
check($url === '/registration/GB8T9GTD5AZY' && $options['method'] === 'DELETE', 'cancel');
$data = [
    'region' => 'GB', 'type' => 'VIRTUAL_LONG_CODE', 'subgroupId' => 'S1',
    'capabilities' => ['SMS'], 'data' => ['quantity' => 1, 'useCases' => ['Support']],
];
$domain->createRegistration($data);
[$url, $options] = end($client->calls);
check($url === '/registration' && $options['method'] === 'POST' && $options['body'] === $data, 'create');
foreach (['quoteVersion', 'version', 'country', 'quantity'] as $legacy) {
    check(!array_key_exists($legacy, $options['body']), "no $legacy");
}

$domain->admin->reviewRegistration('REG1', 'APPROVE');
[$url, $options] = end($client->calls);
check($url === '/registration/REG1/review' && $options['method'] === 'POST' && $options['body'] === ['action' => 'APPROVE'], 'approve');
$domain->admin->reviewRegistration('REG1', 'REJECT', 'Not a business use case');
check(end($client->calls)[1]['body'] === ['action' => 'REJECT', 'reason' => 'Not a business use case'], 'reject reason');
$domain->admin->correctRegistration('REG1', 'Provider asked', ['useCases' => ['Support']]);
[$url, $options] = end($client->calls);
check($url === '/registration/REG1/corrections' && $options['body'] === ['reason' => 'Provider asked', 'data' => ['useCases' => ['Support']]], 'corrections');
$domain->admin->addNumber('REG1', '447400123456');
[$url, $options] = end($client->calls);
check($url === '/registration/REG1/numbers' && $options['body'] === ['phoneNumber' => '447400123456'], 'add number');
$domain->admin->reconcile('REG1', 'ORDER-123');
[$url, $options] = end($client->calls);
check($url === '/registration/REG1/reconcile' && $options['body'] === ['externalId' => 'ORDER-123'], 'reconcile');
$domain->admin->getApplication('REG1');
[$url, $options] = end($client->calls);
check($url === '/registration/REG1/application?format=json' && $options['method'] === 'GET', 'application json envelope');

check((new \SignalHouse\SDK\Domains\Registrations($client, false))->admin === null, 'staff gate');
try {
    $domain->getRegistration('');
    throw new RuntimeException('missing ID accepted');
} catch (\SignalHouse\SDK\Exceptions\ValidationException $error) {
}
try {
    $domain->cancelRegistration('');
    throw new RuntimeException('missing cancel ID accepted');
} catch (\SignalHouse\SDK\Exceptions\ValidationException $error) {
}
try {
    $domain->getCatalog('');
    throw new RuntimeException('missing region accepted');
} catch (\SignalHouse\SDK\Exceptions\ValidationException $error) {
}
try {
    $domain->getQuotes(['region' => 'GB']);
    throw new RuntimeException('missing type accepted');
} catch (\SignalHouse\SDK\Exceptions\ValidationException $error) {
}

$messages->estimateMessage('447400123456', ['447911123456'], 'Hello');
[$url, $options] = end($client->calls);
check($url === '/message/estimate' && $options['body']['messageType'] === 'SMS', 'UK estimate shares the payload');

$alphanumeric = [
    'region' => 'GB', 'type' => 'ALPHANUMERIC_SENDER_ID', 'subgroupId' => 'S1',
    'capabilities' => ['SMS'],
    'data' => [
        'requestedSenderId' => 'ACME', 'trafficOrigin' => 'LOCAL', 'trafficType' => 'TRANSACTIONAL',
        'companyName' => 'Acme Ltd', 'companyCountry' => 'United Kingdom', 'companyWebsite' => 'https://acme.example',
        'industry' => 'Retail', 'senderRelationship' => 'Trading name of Acme Ltd', 'messageExample' => 'Your Acme order has shipped.',
    ],
];
$domain->createRegistration($alphanumeric);
[$url, $options] = end($client->calls);
check($url === '/registration' && $options['method'] === 'POST' && $options['body'] === $alphanumeric, 'alphanumeric submission posts data unchanged');

$shortlinks = new \SignalHouse\SDK\Domains\Shortlinks($client, false);
$shortlinks->getOptOut('ABC/1', ['token' => 'session']);
[$url, $options] = end($client->calls);
check($url === '/shortlink/optout/ABC%2F1' && $options['method'] === 'GET' && !isset($options['body']) && $options['token'] === 'session', 'get opt-out contract');
$shortlinks->confirmOptOut('ABC/1');
[$url, $options] = end($client->calls);
check($url === '/shortlink/optout/ABC%2F1' && $options['method'] === 'POST' && !isset($options['body']), 'confirm opt-out contract');
foreach (['getOptOut', 'confirmOptOut'] as $method) {
    try {
        $shortlinks->$method('');
        throw new RuntimeException("$method accepted a missing code");
    } catch (\SignalHouse\SDK\Exceptions\ValidationException $error) {
    }
}

// The toll-free carrier filter on the number list is sent as a true/false query value.
$numbers->getPhoneNumbers(['subgroupId' => 'S1', 'currentTollFreeCarrier' => true]);
check(end($client->calls)[0] === '/number?subgroupId=S1&currentTollFreeCarrier=true', 'phone numbers currentTollFreeCarrier');
$numbers->getPhoneNumbers(['groupId' => 'G1', 'currentTollFreeCarrier' => false]);
check(end($client->calls)[0] === '/number?groupId=G1&currentTollFreeCarrier=false', 'phone numbers currentTollFreeCarrier false');
$numbers->getPhoneNumbers(['groupId' => 'G1', 'currentTollFreeCarrier' => null]);
check(end($client->calls)[0] === '/number?groupId=G1', 'phone numbers leave out an unset currentTollFreeCarrier');

// A given subgroup goes into the business verification's subgroupId query parameter, never the body.
$brands = new \SignalHouse\SDK\Domains\Brands($client, $client, true);
$brands->getBusinessVerification('G/1', ['token' => 'test-token'], subgroupId: 'S0000001');
[$url, $options] = end($client->calls);
check($url === '/brand/business-verification/G%2F1?subgroupId=S0000001' && $options['method'] === 'GET' && !isset($options['body']) && $options['token'] === 'test-token', 'subgroup business verification read');
$details = ['businessName' => 'Acme Inc', 'registrationType' => 'EIN', 'registrationNumber' => '12-3456789'];
$brands->submitBusinessVerification('G/1', $details, ['idempotencyKey' => 'key-1'], subgroupId: 'S0000001');
[$url, $options] = end($client->calls);
check($url === '/brand/business-verification/G%2F1?subgroupId=S0000001' && $options['method'] === 'PUT' && $options['body'] === $details && $options['idempotencyKey'] === 'key-1', 'subgroup business verification submit');
$brands->getBusinessVerification('G1', subgroupId: 'S&1 2');
check(end($client->calls)[0] === '/brand/business-verification/G1?subgroupId=S%261+2', 'subgroup escaped as a query value');
// Without a subgroup both URLs stay exactly as they were.
$brands->getBusinessVerification('G1');
check(end($client->calls)[0] === '/brand/business-verification/G1', 'business verification read without a subgroup');
$brands->getBusinessVerification('G1', subgroupId: null);
check(end($client->calls)[0] === '/brand/business-verification/G1', 'business verification read with a null subgroup');
$brands->submitBusinessVerification('G1', $details);
[$url, $options] = end($client->calls);
check($url === '/brand/business-verification/G1' && $options['body'] === $details, 'business verification submit without a subgroup');

// The staff toll-free ISV account is read and set on the group's path; the body holds only the account and its key.
$brands->getTollFreeIsvAccount('G/1', ['token' => 'test-token']);
[$url, $options] = end($client->calls);
check($url === '/brand/toll-free-isv-account/G%2F1' && $options['method'] === 'GET' && !isset($options['body']) && $options['token'] === 'test-token', 'toll-free ISV account read');
$accountSid = 'AC' . str_repeat('0', 32);
$apiKeySid = 'SK' . str_repeat('1', 32);
$brands->setTollFreeIsvAccount('G/1', $accountSid, $apiKeySid, 'key-secret', ['idempotencyKey' => 'key-1']);
[$url, $options] = end($client->calls);
check($url === '/brand/toll-free-isv-account/G%2F1' && $options['method'] === 'PUT' && $options['idempotencyKey'] === 'key-1', 'toll-free ISV account set');
check($options['body'] === ['accountSid' => $accountSid, 'apiKeySid' => $apiKeySid, 'apiKeySecret' => 'key-secret'], 'toll-free ISV account body');
$sent = count($client->calls);
try {
    $brands->getTollFreeIsvAccount('');
    throw new RuntimeException('getTollFreeIsvAccount accepted a missing groupId');
} catch (\SignalHouse\SDK\Exceptions\ValidationException $error) {
}
foreach (['groupId', 'accountSid', 'apiKeySid', 'apiKeySecret'] as $index => $field) {
    $args = ['G1', $accountSid, $apiKeySid, 'key-secret'];
    $args[$index] = '';
    try {
        $brands->setTollFreeIsvAccount(...$args);
        throw new RuntimeException("setTollFreeIsvAccount accepted a missing $field");
    } catch (\SignalHouse\SDK\Exceptions\ValidationException $error) {
        check($error->getMessage() === "Missing required parameter: $field", "setTollFreeIsvAccount names the missing $field");
    }
}
check(count($client->calls) === $sent, 'refused toll-free ISV account calls send nothing');

echo "Registration PHP contracts passed (7 regional reads, filter options, catalog, quotes, registrations, alphanumeric submission, opt-out links, staff namespace, validation, subgroup business verification, toll-free ISV account)\n";
