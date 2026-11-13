<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\Exceptions\ValidationException;
use SignalHouse\SDK\HttpClient;

/**
 * Carrier phone-number checks under /verify: SIM Swap and Number Verify. Every check the carrier answers is billed to
 * the group's wallet (402 on insufficient funds); a check the carrier cannot run for the number is a 422 and is not charged.
 */
class Verify
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Check whether a number's SIM card changed recently, as reported by its carrier. Billed once when the check
     * answers; 422 when the check is not available for the number (not charged). Roles: api, admin, developer.
     *
     * @param string $phoneNumber The number to check, in international format (e.g. "+12025550123")
     * @param int|null $maxAge Lookback window in hours, 1-2400; null uses the carrier's default
     * @param bool|null $includeSwapDate Also return the latest SIM change date (no extra charge); null uses the API default (false)
     * @param array $options Additional request options
     * @return array The response; data is {checkId, phoneNumber, swapped, maxAge, latestSimChange?, cost}
     * @throws ValidationException If phoneNumber is missing
     */
    public function checkSimSwap(string $phoneNumber, ?int $maxAge = null, ?bool $includeSwapDate = null, array $options = []): array
    {
        $this->client->require(['phoneNumber' => $phoneNumber]);
        $body = array_filter(
            ['phoneNumber' => $phoneNumber, 'maxAge' => $maxAge, 'includeSwapDate' => $includeSwapDate],
            fn ($value) => $value !== null
        );
        return $this->client->request('/verify/sim-swap', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Start a Number Verify check. The end user's phone must open the returned authorizationUrl on mobile data (Wi-Fi
     * off) within 10 minutes; read the result with getNumberVerification. Billed only when the check is answered
     * (VERIFIED or NOT_VERIFIED). Roles: api, admin, developer.
     *
     * @param string $phoneNumber The number to verify, in international format (e.g. "+12025550123")
     * @param string|null $devicePhoneNumber The phone's own number, sent to the carrier as a login hint
     * @param string|null $redirectUrl An https URL the end user's browser is sent to after the check, with ?checkId=…
     * @param array $options Additional request options
     * @return array The response; data is {checkId, phoneNumber, status: "PENDING", authorizationUrl, expiresAt}
     * @throws ValidationException If phoneNumber is missing
     */
    public function startNumberVerification(string $phoneNumber, ?string $devicePhoneNumber = null, ?string $redirectUrl = null, array $options = []): array
    {
        $this->client->require(['phoneNumber' => $phoneNumber]);
        $body = array_filter(
            ['phoneNumber' => $phoneNumber, 'devicePhoneNumber' => $devicePhoneNumber, 'redirectUrl' => $redirectUrl],
            fn ($value) => $value !== null
        );
        return $this->client->request('/verify/number', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Get a Number Verify check's status and result. A PENDING check past its expiresAt reads as EXPIRED.
     * Roles: api, admin, developer, billing, user.
     *
     * @param string $checkId The check ID (e.g. "NVC…")
     * @param array $options Additional request options
     * @return array The response; data is {checkId, phoneNumber, status, devicePhoneNumberVerified, failureReason, createdAt, expiresAt, completedAt, cost}
     * @throws ValidationException If checkId is missing
     */
    public function getNumberVerification(string $checkId, array $options = []): array
    {
        $this->client->require(['checkId' => $checkId]);
        $safeCheckId = rawurlencode($checkId);
        return $this->client->request("/verify/number/{$safeCheckId}", array_merge([
            'method' => 'GET',
        ], $options));
    }
}
