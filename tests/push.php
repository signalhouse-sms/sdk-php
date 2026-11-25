<?php
// Request-contract coverage for the voice mobile push namespaces.
// Run from sdks/php after composer install: php tests/push.php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use SignalHouse\SDK\Domains\Voice\PushCredentials;
use SignalHouse\SDK\Domains\Voice\PushDevices;
use SignalHouse\SDK\Exceptions\ValidationException;
use SignalHouse\SDK\HttpClient;
use SignalHouse\SDK\SignalHouseSDK;

/** Capture serialized requests without sending them; inherits the real query and validation rules. */
class PushSpyClient extends HttpClient
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

function throwsValidation(callable $call): bool
{
    try {
        $call();
    } catch (ValidationException $error) {
        return true;
    }
    return false;
}

$id = '5a3c2f8e-1b7d-4c9a-9e2f-0d4b6a8c1e33';
$credBase = '/voice/api/v1/push-credentials';
$deviceBase = '/voice/api/v1/push-devices';

$sdk = new SignalHouseSDK(apiKey: 'x', baseUrl: 'https://example.invalid');
check($sdk->voice->pushCredentials instanceof PushCredentials, 'reachable as $sdk->voice->pushCredentials');
check($sdk->voice->pushDevices instanceof PushDevices, 'reachable as $sdk->voice->pushDevices');

// ---- Push credentials --------------------------------------------------------

$client = new PushSpyClient();
$creds = new PushCredentials($client, false);
$apns = ['provider' => 'apns', 'keyId' => 'ABC123DEFG', 'teamId' => 'TEAM123456', 'bundleId' => 'com.example.app', 'privateKey' => 'p8-key-contents'];
$fcm = ['provider' => 'fcm', 'serviceAccount' => '{}'];

$creds->list();
$creds->get($id);
$creds->create($apns);
$creds->update($id, $fcm);
$creds->delete($id);

[$list, $get, $create, $update, $delete] = $client->calls;

check($list[0] === $credBase && $list[1]['method'] === 'GET', 'list path');
check($get[0] === "$credBase/$id" && $get[1]['method'] === 'GET', 'get');
check($create[0] === $credBase && $create[1]['method'] === 'POST' && $create[1]['body'] === $apns, 'create posts the credential');
check($update[0] === "$credBase/$id" && $update[1]['method'] === 'PUT' && $update[1]['body'] === $fcm, 'update puts the credential');
check($delete[0] === "$credBase/$id" && $delete[1]['method'] === 'DELETE', 'delete');

check(throwsValidation(fn() => $creds->get('')), 'get requires an id');
check(throwsValidation(fn() => $creds->update('', $fcm)), 'update requires an id');
check(throwsValidation(fn() => $creds->delete('')), 'delete requires an id');

$encoded = new PushSpyClient();
(new PushCredentials($encoded, false))->get('a/b');
check($encoded->calls[0][0] === "$credBase/a%2Fb", 'credential ids are URL-encoded');

// ---- Push devices (the app, with its voice token) -----------------------------

$client = new PushSpyClient();
$devices = new PushDevices($client, false);
$voice = ['token' => 'voice-jwt'];
$deviceData = ['provider' => 'fcm', 'deviceToken' => 'fcm:tok/1'];

$devices->register($deviceData, $voice);
$devices->unregister('fcm:tok/1', $voice);
$devices->listDeliveries(5, $voice);
$devices->listDeliveries(null, $voice);

[$register, $unregister, $limited, $unlimited] = $client->calls;

check($register[0] === $deviceBase && $register[1]['method'] === 'POST' && $register[1]['body'] === $deviceData, 'register posts the device');
check($unregister[0] === "$deviceBase/fcm%3Atok%2F1" && $unregister[1]['method'] === 'DELETE', 'unregister encodes the device token: ' . $unregister[0]);
check($limited[0] === "$deviceBase/deliveries?limit=5" && $limited[1]['method'] === 'GET', 'listDeliveries sends the limit: ' . $limited[0]);
check($unlimited[0] === "$deviceBase/deliveries" && $unlimited[1]['method'] === 'GET', 'listDeliveries without a limit sends no query: ' . $unlimited[0]);
foreach ($client->calls as [$url, $options]) {
    check(($options['token'] ?? null) === 'voice-jwt', "$url carries the voice token");
}

check(throwsValidation(fn() => $devices->unregister('')), 'unregister requires a device token');

// ---- On the wire: the voice token replaces the API key as the bearer token -----

$history = [];
$stack = HandlerStack::create(new MockHandler([new Response(201, [], '{}'), new Response(200, [], '{}')]));
$stack->push(Middleware::history($history));

// The swapped-in Guzzle client keeps the default headers HttpClient's constructor sets,
// so the voice token has to override the API key rather than fill an empty slot.
$http = new HttpClient('https://example.invalid', 'api-key');
(new ReflectionProperty(HttpClient::class, 'client'))->setValue($http, new Client([
    'handler' => $stack,
    'http_errors' => false,
    'headers' => ['Authorization' => 'Bearer api-key', 'Content-Type' => 'application/json'],
]));

(new PushDevices($http, false))->register($deviceData, $voice);
(new PushCredentials($http, false))->list();
check($history[0]['request']->getHeaderLine('Authorization') === 'Bearer voice-jwt', 'register sends the voice token as the bearer token');
check($history[1]['request']->getHeaderLine('Authorization') === 'Bearer api-key', 'credentials use the API key by default');

echo "push: ok\n";
