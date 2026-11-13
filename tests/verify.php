<?php
// Request-contract coverage for the verify namespace (SIM Swap and Number Verify).
// Run from sdks/php: php tests/verify.php
require __DIR__ . '/../src/Exceptions/SignalHouseException.php';
require __DIR__ . '/../src/Exceptions/ValidationException.php';
require __DIR__ . '/../src/HttpClient.php';
require __DIR__ . '/../src/Domains/Verify.php';

use SignalHouse\SDK\Domains\Verify;
use SignalHouse\SDK\Exceptions\ValidationException;

/** Capture serialized requests without sending them; inherits the real validation rules. */
class VerifySpyClient extends \SignalHouse\SDK\HttpClient
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

const PHONE = '+12025550123';

$client = new VerifySpyClient();
$verify = new Verify($client, false);

$verify->checkSimSwap(PHONE, 240, true, ['idempotencyKey' => 'k1']);
[$url, $options] = end($client->calls);
check($url === '/verify/sim-swap' && $options['method'] === 'POST' && $options['idempotencyKey'] === 'k1', 'checkSimSwap');
check($options['body'] === ['phoneNumber' => PHONE, 'maxAge' => 240, 'includeSwapDate' => true], 'checkSimSwap body');
$verify->checkSimSwap(PHONE, null, false);
check(end($client->calls)[1]['body'] === ['phoneNumber' => PHONE, 'includeSwapDate' => false], 'checkSimSwap keeps an explicit false');
$verify->checkSimSwap(PHONE);
check(end($client->calls)[1]['body'] === ['phoneNumber' => PHONE], 'checkSimSwap body omits unset fields');
check(throws(ValidationException::class, fn () => $verify->checkSimSwap('')), 'checkSimSwap requires phoneNumber');

$verify->startNumberVerification(PHONE, PHONE, 'https://example.com/done');
[$url, $options] = end($client->calls);
check($url === '/verify/number' && $options['method'] === 'POST', 'startNumberVerification');
check($options['body'] === ['phoneNumber' => PHONE, 'devicePhoneNumber' => PHONE, 'redirectUrl' => 'https://example.com/done'], 'startNumberVerification body');
$verify->startNumberVerification(PHONE, options: ['token' => 'session']);
[$url, $options] = end($client->calls);
check($options['body'] === ['phoneNumber' => PHONE] && $options['token'] === 'session', 'startNumberVerification body omits unset fields');
check(throws(ValidationException::class, fn () => $verify->startNumberVerification('')), 'startNumberVerification requires phoneNumber');

$verify->getNumberVerification('NVC1/2');
[$url, $options] = end($client->calls);
check($url === '/verify/number/NVC1%2F2' && $options['method'] === 'GET' && !array_key_exists('body', $options), 'getNumberVerification');
check(throws(ValidationException::class, fn () => $verify->getNumberVerification('')), 'getNumberVerification requires checkId');

echo "Verify PHP contracts passed (checkSimSwap, startNumberVerification, getNumberVerification, validation)\n";
