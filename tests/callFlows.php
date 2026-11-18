<?php
// Request-contract coverage for the voice call flows namespace.
// Run from sdks/php: php tests/callFlows.php
require __DIR__ . '/../src/Exceptions/SignalHouseException.php';
require __DIR__ . '/../src/Exceptions/ValidationException.php';
require __DIR__ . '/../src/HttpClient.php';
require __DIR__ . '/../src/Domains/Voice/CallFlows.php';

use SignalHouse\SDK\Domains\Voice\CallFlows;

/** Capture serialized requests without sending them; inherits the real query and validation rules. */
class CallFlowSpyClient extends \SignalHouse\SDK\HttpClient
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

function throws(callable $call): bool
{
    try {
        $call();
    } catch (\Throwable $error) {
        return true;
    }
    return false;
}

$id = '0088947b-7821-4853-bfe6-744fce926de2';
$base = '/voice/api/v1/call-flows';

$client = new CallFlowSpyClient();
$flows = new CallFlows($client, false);

$flows->list();
$flows->list(['page' => 2, 'subgroupId' => 'S0000001', 'includeDeleted' => true, 'q' => null]);
$flows->catalog();
$flows->get($id);
$flows->create(['name' => 'Main line', 'subgroupId' => 'S0000001']);
$flows->update($id, ['status' => 'DISABLED']);
$flows->saveDraft($id, ['nodes' => [], 'edges' => []]);
$flows->publish($id);
$flows->restore($id);
$flows->duplicate($id);
$flows->duplicate($id, 'Weekend');
$flows->delete($id);

[$list, $filtered, $catalog, $get, $create, $update, $draft, $publish, $restore, $dupDefault, $dupNamed, $delete] = $client->calls;

check($list[0] === $base && $list[1]['method'] === 'GET', 'list path');
check($filtered[0] === "$base?page=2&subgroupId=S0000001&includeDeleted=true", 'list sends set filters, booleans lowercase: ' . $filtered[0]);
check($catalog[0] === "$base/catalog", 'catalog path');
check($get[0] === "$base/$id" && $get[1]['method'] === 'GET', 'get');
check($create[1]['method'] === 'POST' && $create[1]['body'] === ['name' => 'Main line', 'subgroupId' => 'S0000001'], 'create body');
check($update[0] === "$base/$id" && $update[1]['method'] === 'PATCH' && $update[1]['body'] === ['status' => 'DISABLED'], 'update patches');
check($draft[0] === "$base/$id/draft" && $draft[1]['method'] === 'PUT', 'saveDraft puts');

// An empty PHP array encodes as [] — the service needs {} — so these must be objects.
check($publish[0] === "$base/$id/publish" && json_encode($publish[1]['body']) === '{}', 'publish sends {}');
check($restore[0] === "$base/$id/restore" && json_encode($restore[1]['body']) === '{}', 'restore sends {}');
check(json_encode($dupDefault[1]['body']) === '{}', 'duplicate without a name sends {}');
check($dupNamed[1]['body'] === ['name' => 'Weekend'], 'duplicate sends the name');
check($delete[0] === "$base/$id" && $delete[1]['method'] === 'DELETE', 'delete');

check(throws(fn() => $flows->get('')), 'get requires an id');

$encoded = new CallFlowSpyClient();
(new CallFlows($encoded, false))->get('a/b');
check($encoded->calls[0][0] === "$base/a%2Fb", 'ids are URL-encoded');

echo "callFlows: ok\n";
