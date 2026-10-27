<?php
// Every multipart call sends its form parts on the wire.
// Run from sdks/php after composer install: php tests/multipart.php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use SignalHouse\SDK\Domains\Brands;
use SignalHouse\SDK\Domains\Campaigns;
use SignalHouse\SDK\Domains\Landings;
use SignalHouse\SDK\Domains\Messages;
use SignalHouse\SDK\Domains\Numbers;
use SignalHouse\SDK\Domains\Templates;
use SignalHouse\SDK\HttpClient;

function check($condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$history = [];
$stack = HandlerStack::create(new MockHandler(array_fill(0, 20, new Response(201, [], '{}'))));
$stack->push(Middleware::history($history));

$client = new HttpClient('https://example.invalid', 'test', false);
$multipartClient = new HttpClient('https://example.invalid', 'test', true);
foreach ([$client, $multipartClient] as $http) {
    (new ReflectionProperty(HttpClient::class, 'client'))->setValue($http, new Client(['handler' => $stack, 'http_errors' => false]));
}

$image = tempnam(sys_get_temp_dir(), 'sh-sdk-');
file_put_contents($image, 'fake-image-bytes');

$cases = [
    'sendMMS' => [fn () => (new Messages($client, $multipartClient, false))->sendMMS('+15550000001', ['+15550000002'], 'hi', null, null, false, true, [$image]), '/message/mms', ['senderPhoneNumber', 'recipientPhoneNumber', 'messageBody', 'images']],
    'sendGroupMessage' => [fn () => (new Messages($client, $multipartClient, false))->sendGroupMessage('+15550000001', ['+15550000002', '+15550000003'], 'hi', null, null, false, true, [$image]), '/message/groupMessage', ['senderPhoneNumber', 'recipientPhoneNumber', 'messageBody', 'images']],
    'uploadOptInImage' => [fn () => (new Campaigns($client, $multipartClient, false))->uploadOptInImage($image), '/campaign/opt-in-image', ['file']],
    'uploadSampleMedia' => [fn () => (new Campaigns($client, $multipartClient, false))->uploadSampleMedia($image), '/campaign/sample-media', ['file']],
    'uploadSampleMediaForGroup' => [fn () => (new Campaigns($client, $multipartClient, false))->uploadSampleMedia($image, [], 'G0000001'), '/campaign/sample-media', ['groupId', 'file']],
    'createShortCodeCampaign' => [fn () => (new Campaigns($client, $multipartClient, false))->createShortCodeCampaign(['brandId' => 'B1']), '/campaign/short-code', ['campaignData']],
    'requestShortCodeAcquisition' => [fn () => (new Numbers($client, $multipartClient, false))->requestShortCodeAcquisition(['subgroupId' => 'S1', 'brandId' => 'B1', 'campaignId' => 'C1', 'requestType' => 'EXTERNAL_LEASE'], [], $image), '/number/short-code', ['acquisitionData', 'leaseReceipt']],
    'submitAppeal' => [fn () => (new Brands($client, $multipartClient, false))->submitAppeal('B1', ['VERIFY_TAX_ID'], 'why', $image), '/brand/appeal/B1', ['file', 'appealCategories', 'explanation']],
    'upsertLandingTemplate' => [fn () => (new Landings($client, $multipartClient, false))->upsertLandingTemplate('B1', ['title' => 'T'], $image), '/landing/template/B1', ['file', 'title']],
    'createLanding' => [fn () => (new Landings($client, $multipartClient, false))->createLanding(['brandId' => 'B1'], $image), '/landing', ['file', 'brandId']],
    'updateLanding' => [fn () => (new Landings($client, $multipartClient, false))->updateLanding('L1', ['title' => 'T'], $image), '/landing/L1', ['file', 'title']],
    'templatesUploadMedia' => [fn () => (new Templates($client, $multipartClient, false))->uploadMedia(['groupId' => 'G1', 'file' => $image]), '/template/media', ['file', 'groupId']],
];

foreach ($cases as $name => [$call, $path, $fields]) {
    $before = count($history);
    $call();
    check(count($history) === $before + 1, "$name sends one request");
    $sent = $history[$before]['request'];
    $body = (string) $sent->getBody();
    check($sent->getUri()->getPath() === $path, "$name posts to $path, got {$sent->getUri()->getPath()}");
    check(str_starts_with($sent->getHeaderLine('Content-Type'), 'multipart/form-data; boundary='), "$name sends multipart/form-data, got '{$sent->getHeaderLine('Content-Type')}'");
    check($body !== '', "$name sends a non-empty body");
    foreach ($fields as $field) {
        check(str_contains($body, "name=\"$field\""), "$name sends the '$field' part");
    }
}

// JSON calls are unchanged.
$client->request('/registration', ['method' => 'POST', 'body' => ['region' => 'GB']]);
$json = end($history)['request'];
check($json->getHeaderLine('Content-Type') === 'application/json', 'JSON calls still send application/json');
check((string) $json->getBody() === '{"region":"GB"}', 'JSON calls still send the JSON body');

unlink($image);
echo "Multipart PHP contract passed (" . count($cases) . " calls)\n";
