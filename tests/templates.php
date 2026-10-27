<?php
// Request-contract coverage for the templates namespace.
// Run from sdks/php: php tests/templates.php
require __DIR__ . '/../src/Exceptions/SignalHouseException.php';
require __DIR__ . '/../src/Exceptions/ValidationException.php';
require __DIR__ . '/../src/HttpClient.php';
require __DIR__ . '/../src/Domains/TemplatePayloads.php';
require __DIR__ . '/../src/Domains/Templates.php';

use SignalHouse\SDK\Domains\Templates;
use SignalHouse\SDK\Exceptions\SignalHouseException;
use SignalHouse\SDK\Exceptions\ValidationException;

/** Capture serialized requests without sending them; inherits the real query and validation rules. */
class TemplateSpyClient extends \SignalHouse\SDK\HttpClient
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

function throws(string $class, callable $call): bool
{
    try {
        $call();
    } catch (\Throwable $error) {
        return $error instanceof $class;
    }
    return false;
}

$body = [
    'channel' => 'rcs', 'contentFormat' => 'rcs.blocks.v1',
    'content' => ['blocks' => [['id' => 'b1', 'kind' => 'text', 'text' => 'Hi {{first_name}}']]],
    'variables' => [['key' => 'first_name', 'type' => 'text', 'required' => true]],
    'defaults' => (object) [], 'fallback' => (object) [],
];

$client = new TemplateSpyClient();
$multipart = new TemplateSpyClient();
$templates = new Templates($client, $multipart, false);

$templates->create([
    'groupId' => 'G1', 'name' => 'Welcome', 'body' => $body, 'tag' => 'Promo',
], ['idempotencyKey' => 'key-1']);
[$url, $options] = end($client->calls);
check($url === '/template' && $options['method'] === 'POST' && $options['idempotencyKey'] === 'key-1', 'create');
check($options['body'] === ['groupId' => 'G1', 'name' => 'Welcome', 'body' => $body, 'tag' => 'Promo'], 'create body');
$templates->create(['groupId' => 'G1', 'name' => 'Welcome', 'body' => $body, 'channel' => 'sms']);
check(array_keys(end($client->calls)[1]['body']) === ['groupId', 'name', 'body'], 'create sends only contract fields');

$templates->list([
    'groupId' => 'G1', 'state' => 'PUBLISHED', 'channel' => 'rcs', 'tag' => 'Promo', 'search' => 'wel',
    'sortBy' => 'name', 'sortOrder' => 'asc', 'page' => 2, 'limit' => 50,
], ['token' => 'session']);
[$url, $options] = end($client->calls);
check($url === '/template?groupId=G1&state=PUBLISHED&channel=rcs&tag=Promo&search=wel&sortBy=name&sortOrder=asc&page=2&limit=50'
    && $options['method'] === 'GET' && $options['token'] === 'session', 'list');

$templates->get('TPL/1', ['groupId' => 'G1', 'version' => 'draft']);
check(end($client->calls)[0] === '/template/TPL%2F1?groupId=G1&version=draft', 'get escapes id');

$templates->update('TPL1', ['groupId' => 'G1']);
[$url, $options] = end($client->calls);
check($url === '/template/TPL1' && $options['method'] === 'PUT', 'update path');
check($options['body'] === ['groupId' => 'G1'], 'update sends only groupId and keeps tag');
$templates->update('TPL1', ['groupId' => 'G1', 'tag' => null]);
check(end($client->calls)[1]['body'] === ['groupId' => 'G1', 'tag' => null], 'update clears tag');
check(str_contains(json_encode(end($client->calls)[1]['body']), '"tag":null'), 'tag serializes as null');
$templates->update('TPL1', ['groupId' => 'G1', 'name' => 'New', 'tag' => 'Promo', 'body' => $body]);
check(end($client->calls)[1]['body'] === ['groupId' => 'G1', 'name' => 'New', 'tag' => 'Promo', 'body' => $body], 'update full');
$templates->update('TPL1', ['groupId' => 'G1', 'revision' => 1, 'name' => 'New']);
check(end($client->calls)[1]['body'] === ['groupId' => 'G1', 'name' => 'New'], 'update sends only contract fields');

$templates->publish('TPL1', ['groupId' => 'G1', 'version' => 2]);
[$url, $options] = end($client->calls);
check($url === '/template/TPL1/publish' && $options['method'] === 'POST' && $options['body'] === ['groupId' => 'G1'], 'publish sends only groupId');
$templates->archive('TPL1', ['groupId' => 'G1', 'revision' => 4]);
[$url, $options] = end($client->calls);
check($url === '/template/TPL1/archive' && $options['body'] === ['groupId' => 'G1'], 'archive sends only groupId');
$templates->versions('TPL1', ['groupId' => 'G1', 'page' => 1, 'limit' => 10]);
check(end($client->calls)[0] === '/template/TPL1/versions?groupId=G1&page=1&limit=10', 'versions');
$templates->validate('TPL1', ['groupId' => 'G1', 'body' => $body]);
[$url, $options] = end($client->calls);
check($url === '/template/TPL1/validate' && $options['body'] === ['groupId' => 'G1', 'body' => $body], 'validate body');
$templates->validate('TPL1', ['groupId' => 'G1', 'version' => 'published']);
check(end($client->calls)[1]['body'] === ['groupId' => 'G1', 'version' => 'published'], 'validate version');
check(throws(SignalHouseException::class, fn () => $templates->validate('TPL1', ['groupId' => 'G1'])), 'validate needs one');
check(throws(SignalHouseException::class, fn () => $templates->validate('TPL1', ['groupId' => 'G1', 'body' => $body, 'version' => 1])), 'validate rejects both');
$templates->capabilities(['groupId' => 'G1', 'channel' => 'rcs', 'provider' => 'sinch']);
check(end($client->calls)[0] === '/template/capabilities?groupId=G1&channel=rcs&provider=sinch', 'capabilities');

$stream = fopen('php://memory', 'r+');
fwrite($stream, "\x89PNG");
$jsonCalls = count($client->calls);
$templates->uploadMedia(['groupId' => 'G1', 'file' => $stream, 'fileName' => 'logo.png'], ['idempotencyKey' => 'key-2']);
[$url, $options] = end($multipart->calls);
check(count($client->calls) === $jsonCalls, 'upload uses the multipart client');
check($url === '/template/media' && $options['method'] === 'POST' && !isset($options['body']), 'upload path');
check($options['multipart'] === [
    ['name' => 'file', 'contents' => $stream, 'filename' => 'logo.png'],
    ['name' => 'groupId', 'contents' => 'G1'],
], 'upload parts');
check($options['idempotencyKey'] === 'key-2', 'upload idempotency');
$templates->uploadMedia(['groupId' => 'G1', 'file' => $stream]);
check(end($multipart->calls)[1]['multipart'][0] === ['name' => 'file', 'contents' => $stream, 'filename' => 'upload'], 'upload without fileName still sends a file part');

foreach ([
    fn () => $templates->list([]),
    fn () => $templates->create(['groupId' => 'G1', 'body' => $body]),
    fn () => $templates->get('', ['groupId' => 'G1']),
    fn () => $templates->update('TPL1', ['name' => 'New']),
    fn () => $templates->update('', ['groupId' => 'G1']),
    fn () => $templates->publish('TPL1', []),
    fn () => $templates->archive('TPL1', []),
    fn () => $templates->uploadMedia(['groupId' => 'G1']),
] as $index => $call) {
    check(throws(ValidationException::class, $call), "required parameter $index");
}

echo "Template PHP contracts passed (update tag null semantics, publish/archive bodies, validate, multipart upload, required params)\n";
