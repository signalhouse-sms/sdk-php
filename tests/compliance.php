<?php
// Request-contract coverage for the compliance namespace (customer case methods).
// Run from sdks/php: php tests/compliance.php
require __DIR__ . '/../src/Exceptions/SignalHouseException.php';
require __DIR__ . '/../src/Exceptions/ValidationException.php';
require __DIR__ . '/../src/HttpClient.php';
require __DIR__ . '/../src/Domains/Compliance.php';

use SignalHouse\SDK\Domains\Compliance;
use SignalHouse\SDK\Exceptions\ValidationException;

/** Capture serialized requests without sending them; inherits the real validation rules. */
class ComplianceSpyClient extends \SignalHouse\SDK\HttpClient
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

$client = new ComplianceSpyClient();
$multipartClient = new ComplianceSpyClient();
$compliance = new Compliance($client, $multipartClient, false);

$compliance->getCases();
[$url, $options] = end($client->calls);
check($url === '/compliance/cases' && $options['method'] === 'GET' && !array_key_exists('body', $options), 'getCases sends no query when no filter is given');
$compliance->getCases('G0000001', 'OPEN', 'customer', 'CAMPAIGN', 'C1 2', 'S0000001', false, '2026-11-01T00:00:00+00:00', '2026-11-13T00:00:00Z', 'fixBy', 2, 50, ['token' => 'session']);
[$url, $options] = end($client->calls);
check($url === '/compliance/cases?groupId=G0000001&status=OPEN&stage=customer&objectType=CAMPAIGN&objectId=C1+2&subgroupId=S0000001&suspended=false&from=2026-11-01T00%3A00%3A00%2B00%3A00&to=2026-11-13T00%3A00%3A00Z&sort=fixBy&page=2&limit=50', 'getCases sends every filter given, encoded');
check($options['token'] === 'session', 'getCases forwards options');
$compliance->getCases(status: 'LIFT_REQUESTED', suspended: true, limit: 200);
check(end($client->calls)[0] === '/compliance/cases?status=LIFT_REQUESTED&suspended=true&limit=200', 'getCases sends only the filters given');

$compliance->getCase('CASE-12/3');
[$url, $options] = end($client->calls);
check($url === '/compliance/cases/CASE-12%2F3' && $options['method'] === 'GET' && !array_key_exists('body', $options), 'getCase');
check(throws(ValidationException::class, fn () => $compliance->getCase('')), 'getCase requires caseId');

$compliance->answerRequirement('CASE-12', 'CASE-12-1', 'Removed the number', ['CF1', 'CF2'], ['idempotencyKey' => 'k1']);
[$url, $options] = end($client->calls);
check($url === '/compliance/cases/CASE-12/requirements/CASE-12-1' && $options['method'] === 'PUT' && $options['idempotencyKey'] === 'k1', 'answerRequirement');
check($options['body'] === ['answer' => 'Removed the number', 'fileIds' => ['CF1', 'CF2']], 'answerRequirement body');
$compliance->answerRequirement('CASE-12', 'CASE-12-1', fileIds: ['CF1']);
check(end($client->calls)[1]['body'] === ['fileIds' => ['CF1']], 'answerRequirement body omits an unset answer');
$compliance->answerRequirement('CASE-12', 'CASE-12-1', 'Done');
check(end($client->calls)[1]['body'] === ['answer' => 'Done'], 'answerRequirement body omits unset fileIds');
$compliance->answerRequirement('CASE-12', 'CASE-12-1');
check(json_encode(end($client->calls)[1]['body']) === '{}', 'answerRequirement sends an empty object when nothing is given');
$compliance->answerRequirement('CASE 1/2', 'CASE 1/2-3', 'x');
check(end($client->calls)[0] === '/compliance/cases/CASE%201%2F2/requirements/CASE%201%2F2-3', 'answerRequirement encodes both path ids');
check(throws(ValidationException::class, fn () => $compliance->answerRequirement('', 'CASE-12-1', 'x')), 'answerRequirement requires caseId');
check(throws(ValidationException::class, fn () => $compliance->answerRequirement('CASE-12', '', 'x')), 'answerRequirement requires requirementId');

$file = tempnam(sys_get_temp_dir(), 'sh-sdk-');
file_put_contents($file, 'records');
$jsonCalls = count($client->calls);

$compliance->uploadFile('CASE-12', $file, 'CASE-12-1', null, ['token' => 'session']);
[$url, $options] = end($multipartClient->calls);
check($url === '/compliance/files' && $options['method'] === 'POST' && $options['token'] === 'session' && !array_key_exists('body', $options), 'uploadFile');
check(array_column($options['multipart'], 'name') === ['caseId', 'requirementId', 'file'], 'uploadFile sends caseId, requirementId and file in order');
check($options['multipart'][0]['contents'] === 'CASE-12' && $options['multipart'][1]['contents'] === 'CASE-12-1', 'uploadFile field values');
check(is_resource($options['multipart'][2]['contents']), 'uploadFile opens a file path');
fclose($options['multipart'][2]['contents']);
$handle = fopen($file, 'r');
$compliance->uploadFile('CASE-12', $handle);
$options = end($multipartClient->calls)[1];
check(array_column($options['multipart'], 'name') === ['caseId', 'file'], 'uploadFile omits an unset requirementId');
check($options['multipart'][1]['contents'] === $handle, 'uploadFile passes a resource through');
$compliance->uploadFile('CASE-12', $handle, '');
check(array_column(end($multipartClient->calls)[1]['multipart'], 'name') === ['caseId', 'file'], 'uploadFile omits an empty requirementId');
check(!array_key_exists('filename', end($multipartClient->calls)[1]['multipart'][1]), 'uploadFile sends no filename when none is given');
$compliance->uploadFile('CASE-12', $handle, null, 'optins.csv');
check(end($multipartClient->calls)[1]['multipart'][1]['filename'] === 'optins.csv', 'uploadFile sends the given filename');
fclose($handle);
check(count($client->calls) === $jsonCalls, 'uploadFile goes through the multipart client');
check(throws(ValidationException::class, fn () => $compliance->uploadFile('', $file)), 'uploadFile requires caseId');
check(throws(ValidationException::class, fn () => $compliance->uploadFile('CASE-12', null)), 'uploadFile requires file');
unlink($file);

$compliance->getFile('CF1/2');
[$url, $options] = end($client->calls);
check($url === '/compliance/files/CF1%2F2' && $options['method'] === 'GET' && !array_key_exists('body', $options), 'getFile');
check(throws(ValidationException::class, fn () => $compliance->getFile('')), 'getFile requires fileId');

echo "Compliance PHP contracts passed (getCases, getCase, answerRequirement, uploadFile, getFile, validation)\n";
