<?php
// Request-contract coverage for the rcs.agents namespace and the registration draft methods.
// Run from sdks/php: php tests/rcs.php
require __DIR__ . '/../src/Exceptions/SignalHouseException.php';
require __DIR__ . '/../src/Exceptions/ValidationException.php';
require __DIR__ . '/../src/HttpClient.php';
require __DIR__ . '/../src/Domains/Rcs/Agents.php';
require __DIR__ . '/../src/Domains/Rcs/Messages.php';
require __DIR__ . '/../src/Domains/Messages.php';
require __DIR__ . '/../src/Domains/Rcs.php';
require __DIR__ . '/../src/Domains/Registrations.php';
require __DIR__ . '/../src/Domains/Webhooks.php';
require __DIR__ . '/../src/Domains/Billing.php';

use SignalHouse\SDK\Domains\Billing;
use SignalHouse\SDK\Domains\Rcs;
use SignalHouse\SDK\Domains\Messages;
use SignalHouse\SDK\Domains\Rcs\Agents;
use SignalHouse\SDK\Domains\Rcs\Messages as RcsMessages;
use SignalHouse\SDK\Domains\Registrations;
use SignalHouse\SDK\Domains\Webhooks;
use SignalHouse\SDK\Exceptions\ValidationException;

/** Capture serialized requests without sending them; inherits the real query and validation rules. */
class RcsSpyClient extends \SignalHouse\SDK\HttpClient
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

const AGENT = 'RCSA7K2M1QZ8RP';

$client = new RcsSpyClient();
$multipart = new RcsSpyClient();
$rcs = new Rcs($client, $multipart);
check($rcs->agents instanceof Agents, 'rcs exposes agents');
$agents = $rcs->agents;

$agents->create([
    'brandId' => 'BXYZ123', 'campaignId' => 'CABC123', 'agent' => ['name' => 'Acme'], 'useCase' => 'TRANSACTIONAL',
    'consent' => null, 'groupId' => 'G1',
], ['idempotencyKey' => 'k1']);
[$url, $options] = end($client->calls);
check($url === '/rcs/agent' && $options['method'] === 'POST' && $options['idempotencyKey'] === 'k1', 'create');
check($options['body'] === ['brandId' => 'BXYZ123', 'campaignId' => 'CABC123', 'agent' => ['name' => 'Acme'], 'useCase' => 'TRANSACTIONAL'], 'create body omits unset, null and unknown keys');
check(throws(ValidationException::class, fn () => $agents->create(['agent' => ['name' => 'Acme']])), 'create requires brandId');

$agents->list(['brandId' => 'BXYZ123', 'campaignId' => 'CABC123', 'status' => 'DRAFT', 'page' => 2, 'limit' => 50]);
check(end($client->calls)[0] === '/rcs/agent?status=DRAFT&brandId=BXYZ123&campaignId=CABC123&page=2&limit=50', 'list filters');
$agents->list(['status' => ['DRAFT', 'REJECTED']]);
check(end($client->calls)[0] === '/rcs/agent?status=DRAFT&status=REJECTED', 'list repeats status');
$agents->list();
check(end($client->calls)[0] === '/rcs/agent', 'list without filters');

$before = count($client->calls);
$agents->get(AGENT);
$agents->update(AGENT, ['campaignId' => null, 'agent' => ['description' => 'Alerts'], 'contact' => ['firstName' => 'Ada']]);
$agents->validate(AGENT);
$agents->submit(AGENT);
$agents->launch(AGENT, [], true);
$agents->archive(AGENT);
$agents->resendTesterInvite(AGENT, '+15125550199');
$agents->removeTester(AGENT, '+15125550199');
$sent = array_map(fn ($call) => [$call[1]['method'], $call[0]], array_slice($client->calls, $before));
check($sent === [
    ['GET', '/rcs/agent/' . AGENT], ['PUT', '/rcs/agent/' . AGENT], ['POST', '/rcs/agent/' . AGENT . '/validate'],
    ['POST', '/rcs/agent/' . AGENT . '/submit'], ['POST', '/registration/' . AGENT . '/launch'], ['POST', '/rcs/agent/' . AGENT . '/archive'],
    ['POST', '/rcs/agent/' . AGENT . '/testers/%2B15125550199/resend'], ['DELETE', '/rcs/agent/' . AGENT . '/testers/%2B15125550199'],
], 'per-agent paths and methods');
$update = $client->calls[$before + 1][1]['body'];
check($update === ['campaignId' => null, 'agent' => ['description' => 'Alerts'], 'contact' => ['firstName' => 'Ada']], 'update keeps explicit null and the contact');
check(json_encode($update) === '{"campaignId":null,"agent":{"description":"Alerts"},"contact":{"firstName":"Ada"}}', 'campaignId serializes as null');
check(!isset($client->calls[$before + 2][1]['body']), 'validate sends no body');
$agents->update(AGENT, ['agent' => [], 'contact' => [], 'testers' => []]);
check(json_encode(end($client->calls)[1]['body']) === '{"agent":{},"contact":{},"testers":[]}', 'an empty profile or contact is sent as an object, an empty list as a list');
$agents->create(['brandId' => 'BXYZ123', 'consent' => []]);
check(json_encode(end($client->calls)[1]['body']) === '{"brandId":"BXYZ123","consent":{}}', 'create sends an empty consent as an object');
$agents->update('RCSA/1', []);
[$url, $options] = end($client->calls);
check($url === '/rcs/agent/RCSA%2F1' && json_encode($options['body']) === '{}', 'update escapes id and sends an empty object');
check(throws(ValidationException::class, fn () => $agents->submit('')), 'submit requires agentId');
check($client->calls[$before + 4][1]['body'] === ['acknowledgeFees' => true], 'launch sends the acknowledgement');
check(throws(ValidationException::class, fn () => $agents->launch('')), 'launch requires agentId');

(new Billing($client, false))->getTransactionHistory(['groupId' => 'G1', 'category' => 'RCS_INBOUND,RCS_CARRIER_PASSTHROUGH', 'limit' => 1000]);
check(str_contains(end($client->calls)[0], 'category=RCS_INBOUND%2CRCS_CARRIER_PASSTHROUGH'), 'history forwards the category');
check(throws(ValidationException::class, fn () => $agents->removeTester(AGENT, '')), 'removeTester requires phoneNumber');

$stream = fopen('php://memory', 'r+');
fwrite($stream, "\x89PNG");
$jsonCalls = count($client->calls);
$agents->uploadAsset(AGENT, ['kind' => 'logo', 'file' => $stream, 'fileName' => 'logo.png']);
[$url, $options] = end($multipart->calls);
check(count($client->calls) === $jsonCalls, 'upload uses the multipart client');
check($url === '/rcs/agent/' . AGENT . '/asset' && $options['method'] === 'POST' && !isset($options['body']), 'upload path');
check($options['multipart'] === [
    ['name' => 'kind', 'contents' => 'logo'],
    ['name' => 'file', 'contents' => $stream, 'filename' => 'logo.png'],
], 'upload parts');
foreach ([
    fn () => $agents->uploadAsset('', ['kind' => 'logo', 'file' => $stream]),
    fn () => $agents->uploadAsset(AGENT, ['file' => $stream]),
    fn () => $agents->uploadAsset(AGENT, ['kind' => 'hero']),
] as $index => $call) {
    check(throws(ValidationException::class, $call), "upload required parameter $index");
}

$registrations = new Registrations($client, false);
$before = count($client->calls);
$registrations->updateRegistration(AGENT, ['useCase' => 'OTP']);
$registrations->validateRegistration(AGENT);
$registrations->submitRegistration(AGENT);
$registrations->launchRegistration(AGENT, [], true, true);
$registrations->getRegistrations(['type' => 'RCS_AGENT', 'brandId' => 'BXYZ123', 'campaignId' => 'CABC123']);
$sent = array_map(fn ($call) => [$call[1]['method'], $call[0]], array_slice($client->calls, $before, 4));
check($sent === [
    ['PUT', '/registration/' . AGENT], ['POST', '/registration/' . AGENT . '/validate'], ['POST', '/registration/' . AGENT . '/submit'],
    ['POST', '/registration/' . AGENT . '/launch'],
], 'registration draft paths');
check($client->calls[$before][1]['body'] === ['useCase' => 'OTP'], 'update sends data as the body');
check($client->calls[$before + 3][1]['body'] === ['acknowledgeFees' => true, 'acknowledgePhi' => true], 'launchRegistration sends both acknowledgements');
check(end($client->calls)[0] === '/registration?type=RCS_AGENT&brandId=BXYZ123&campaignId=CABC123', 'registration link filters');
check(throws(ValidationException::class, fn () => $registrations->updateRegistration('', ['useCase' => 'OTP'])), 'update requires registrationId');
check(throws(\ArgumentCountError::class, fn () => $registrations->updateRegistration(AGENT)), 'update requires data');
check(throws(ValidationException::class, fn () => $registrations->submitRegistration('')), 'submit requires registrationId');
check(throws(ValidationException::class, fn () => $registrations->launchRegistration('')), 'launch requires registrationId');

$messages = $rcs->messages;
check($messages instanceof RcsMessages, 'rcs exposes messages');
$recipients = [['phoneNumber' => '+15125550199', 'variables' => ['name' => 'Ada']]];
$fallback = ['mode' => 'auto', 'channel' => 'sms', 'from' => '15125550100', 'content' => ['text' => 'Hi'], 'validitySeconds' => 600];

$messages->send([
    'agentId' => AGENT, 'recipients' => $recipients, 'content' => ['blocks' => []], 'contentFormat' => 'content.v1',
    'trafficType' => 'TRANSACTION', 'ttlSeconds' => 900, 'fallback' => $fallback, 'statusCallbackUrl' => 'https://example.com/cb',
    'enableShortlink' => false, 'isTest' => true, 'simulate' => 'delivered', 'templateId' => null, 'groupId' => 'G1',
], ['idempotencyKey' => 'k1']);
[$url, $options] = end($client->calls);
check($url === '/message/rcs' && $options['method'] === 'POST' && $options['idempotencyKey'] === 'k1', 'send');
check($options['body'] === [
    'agentId' => AGENT, 'recipients' => $recipients, 'content' => ['blocks' => []], 'contentFormat' => 'content.v1',
    'trafficType' => 'TRANSACTION', 'ttlSeconds' => 900, 'fallback' => $fallback, 'statusCallbackUrl' => 'https://example.com/cb',
    'enableShortlink' => false, 'isTest' => true, 'simulate' => 'delivered',
], 'send body keeps false and omits null and unknown keys');
$messages->send(['agentId' => AGENT, 'recipients' => [['phoneNumber' => '+15125550199', 'variables' => [], 'metadata' => []]], 'templateId' => 'T1']);
check(json_encode(end($client->calls)[1]['body']['recipients']) === '[{"phoneNumber":"+15125550199","variables":{},"metadata":{}}]', 'a recipient\'s empty maps are sent as objects');
$messages->send(['agentId' => AGENT, 'recipients' => $recipients, 'templateId' => 'T1', 'templateVersion' => 2, 'expireTime' => '2026-11-05T00:00:00Z', 'fallback' => []]);
$body = end($client->calls)[1]['body'];
check($body['templateVersion'] === 2 && $body['expireTime'] === '2026-11-05T00:00:00Z' && json_encode($body['fallback']) === '{}', 'send template body, empty fallback is an object');
check(throws(ValidationException::class, fn () => $messages->send(['recipients' => $recipients])), 'send requires agentId');
check(throws(ValidationException::class, fn () => $messages->send(['agentId' => AGENT])), 'send requires recipients');

$messages->validate(['agentId' => AGENT, 'recipients' => $recipients, 'templateId' => 'T1', 'simulate' => 'read']);
[$url, $options] = end($client->calls);
check($url === '/message/rcs/validate' && $options['method'] === 'POST', 'validate');
check($options['body'] === ['agentId' => AGENT, 'recipients' => $recipients, 'templateId' => 'T1', 'simulate' => 'read'], 'validate body');
check(throws(ValidationException::class, fn () => $messages->validate(['agentId' => AGENT])), 'validate requires recipients');

$messages->estimate(['agentId' => AGENT, 'recipientCount' => 500, 'templateId' => 'T1', 'templateVersion' => 2, 'trafficType' => 'PROMOTION', 'fallback' => ['mode' => 'rcs_only']]);
[$url, $options] = end($client->calls);
check($url === '/message/estimate' && $options['method'] === 'POST', 'estimate');
check($options['body'] === ['channel' => 'rcs', 'agentId' => AGENT, 'recipientCount' => 500, 'templateId' => 'T1', 'templateVersion' => 2, 'trafficType' => 'PROMOTION', 'fallback' => ['mode' => 'rcs_only']], 'estimate adds the channel');
check(throws(ValidationException::class, fn () => $messages->estimate(['recipientCount' => 500])), 'estimate requires agentId');

$messages->capabilities(AGENT, ['+15125550199']);
[$url, $options] = end($client->calls);
check($url === '/rcs/capabilities' && $options['method'] === 'POST', 'capabilities');
check($options['body'] === ['agentId' => AGENT, 'phoneNumbers' => ['+15125550199']], 'capabilities body');
check(throws(ValidationException::class, fn () => $messages->capabilities('', ['+15125550199'])), 'capabilities requires agentId');
check(throws(ValidationException::class, fn () => $messages->capabilities(AGENT, [])), 'capabilities requires phoneNumbers');
$messages->simulateInbound(AGENT, '+15125550199', 'SUGGESTION', ['postbackData' => 'track_pkg']);
[$url, $options] = end($client->calls);
check($url === '/message/rcs/simulate-inbound' && $options['method'] === 'POST', 'simulateInbound');
check($options['body'] === ['agentId' => AGENT, 'from' => '+15125550199', 'type' => 'SUGGESTION', 'postbackData' => 'track_pkg'], 'simulateInbound body');
$messages->simulateInbound(AGENT, '+15125550199', 'LOCATION', ['latitude' => 0, 'longitude' => 0.5, 'messageId' => null]);
[, $options] = end($client->calls);
check($options['body'] === ['agentId' => AGENT, 'from' => '+15125550199', 'type' => 'LOCATION', 'latitude' => 0, 'longitude' => 0.5], 'simulateInbound drops a null and keeps a zero');
check(throws(ValidationException::class, fn () => $messages->simulateInbound(AGENT, '', 'TEXT')), 'simulateInbound requires from');

$messages->get('a/1');
[$url, $options] = end($client->calls);
check($url === '/message/a%2F1' && $options['method'] === 'GET', 'get');
check(throws(ValidationException::class, fn () => $messages->get('')), 'get requires messageId');

$allMessages = new Messages($client, $multipart, false);
$allMessages->getMessage('a/1');
[$url, $options] = end($client->calls);
check($url === '/message/a%2F1' && $options['method'] === 'GET', 'getMessage');
check(throws(ValidationException::class, fn () => $allMessages->getMessage('')), 'getMessage requires id');

$messages->list([
    'agentId' => AGENT, 'from' => '2026-11-01', 'to' => '2026-11-02', 'groupId' => 'G1', 'status' => 'READ', 'page' => 2, 'limit' => 50,
    'templateId' => 'T1', 'templateVersion' => 3, 'bulkId' => 'B1', 'fallbackReason' => 'NOT_CAPABLE', 'billingClass' => 'RICH_MEDIA',
    'deliveredChannel' => 'sms', 'isTest' => false,
]);
[$url, $options] = end($client->calls);
check($options['method'] === 'GET' && $url === '/message?registrationId=' . AGENT . '&startDate=2026-11-01&endDate=2026-11-02&groupId=G1&status=READ&page=2&limit=50&templateId=T1&templateVersion=3&bulkId=B1&fallbackReason=NOT_CAPABLE&billingClass=RICH_MEDIA&deliveredChannel=sms&isTest=false&messageType=RCS&dateBounds=exact', 'list maps filters');
$before = count($client->calls);
check(throws(ValidationException::class, fn () => $messages->list(['from' => '2026-11-01', 'to' => '2026-11-02'])), 'list requires groupId');
check(throws(ValidationException::class, fn () => $messages->list(['groupId' => 'G1', 'to' => '2026-11-02'])), 'list requires from');
check(throws(ValidationException::class, fn () => $messages->list(['groupId' => 'G1', 'from' => '2026-11-01'])), 'list requires to');
check(count($client->calls) === $before, 'list without a required field sends no request');

$allMessages->getMessages(['groupId' => 'G1', 'startDate' => '2026-11-01', 'endDate' => '2026-11-02', 'bulkId' => 'B1', 'isTest' => false]);
check(end($client->calls)[0] === '/message?groupId=G1&startDate=2026-11-01&endDate=2026-11-02&bulkId=B1&isTest=false', 'getMessages forwards the RCS filters');

$allMessages->getMessageInteractions('m1');
check(end($client->calls)[0] === '/message/m1/interactions' && end($client->calls)[1]['method'] === 'GET', 'getMessageInteractions addresses the message');

$webhooks = new Webhooks($client, false);
$webhooks->getWebhooks(['groupId' => 'G1', 'endpointType' => 'Agent', 'agentId' => AGENT]);
check(end($client->calls)[0] === '/webhook?groupId=G1&endpointType=Agent&agentId=' . AGENT, 'getWebhooks filters by agent');
$webhookData = ['groupId' => 'G1', 'endpointType' => 'Agent', 'agentId' => AGENT, 'url' => 'https://a.example/rcs', 'secondaryUrl' => 'https://b.example/rcs'];
$webhooks->createWebhook($webhookData);
check(end($client->calls)[1]['body'] === $webhookData, 'createWebhook sends the agent fields');

$fixtures = __DIR__ . '/../../fixtures/rcs/requests.json';
if (is_file($fixtures)) {
    foreach (json_decode(file_get_contents($fixtures), true, 512, JSON_THROW_ON_ERROR)['cases'] as $case) {
        [$domain, $method] = explode('.', $case['operation']);
        $input = $case['input'];
        $target = $domain === 'agents' ? $agents : $messages;
        match ($case['operation']) {
            'messages.capabilities' => $target->capabilities($input['agentId'], $input['phoneNumbers']),
            'messages.get' => $target->get($input['messageId']),
            'agents.launch' => $target->launch($input['agentId'], [], $input['acknowledgeFees'] ?? null, $input['acknowledgePhi'] ?? null),
            'agents.get', 'agents.validate', 'agents.submit', 'agents.archive' => $target->$method($input['agentId']),
            'agents.update' => $target->update($input['agentId'], array_diff_key($input, ['agentId' => true])),
            'agents.resendTesterInvite', 'agents.removeTester' => $target->$method($input['agentId'], $input['phoneNumber']),
            default => $target->$method($input),
        };
        [$url, $options] = end($client->calls);
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        ksort($query);
        $request = $case['request'];
        $wanted = $request['query'] ?? [];
        ksort($wanted);
        check(strtoupper($options['method']) === $request['method'], $case['name'] . ': method');
        check(parse_url($url, PHP_URL_PATH) === $request['path'], $case['name'] . ': path');
        check($query == $wanted, $case['name'] . ': query');
        check(json_decode(json_encode($options['body'] ?? []), true) == ($request['body'] ?? []), $case['name'] . ': body');
    }
}

echo "RCS PHP contracts passed (agents create/list/get/update/validate/submit/archive, multipart asset upload, registration drafts and link filters, messages send/validate/estimate/capabilities/get/list, getMessage, getMessages)\n";
