<?php
// The idempotencyKey option reaches the wire as the Idempotency-Key header.
// Run from sdks/php after composer install: php tests/idempotency.php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use SignalHouse\SDK\HttpClient;

function check($condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$history = [];
$stack = HandlerStack::create(new MockHandler([new Response(201, [], '{}'), new Response(201, [], '{}')]));
$stack->push(Middleware::history($history));

$http = new HttpClient('https://example.invalid', 'test');
(new ReflectionProperty(HttpClient::class, 'client'))->setValue($http, new Client(['handler' => $stack, 'http_errors' => false]));

$http->request('/registration', ['method' => 'POST', 'body' => ['region' => 'GB'], 'idempotencyKey' => 'key-1', 'headers' => ['X-Trace' => 't']]);
$sent = $history[0]['request'];
check($sent->getHeaderLine('Idempotency-Key') === 'key-1', 'idempotencyKey is sent as Idempotency-Key');
check($sent->getHeaderLine('X-Trace') === 't', 'custom headers still go out alongside it');

$http->request('/registration', ['method' => 'POST', 'body' => ['region' => 'GB']]);
check(!$history[1]['request']->hasHeader('Idempotency-Key'), 'no Idempotency-Key without the option');

echo "Idempotency PHP contract passed\n";
