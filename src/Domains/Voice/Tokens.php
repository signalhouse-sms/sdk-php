<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * WebRTC voice tokens — mints ephemeral SIP identities the browser/mobile
 * voice SDK uses to register against the SignalHouse voice fabric.
 *
 * Wraps the Voice service's /voice/tokens endpoint.
 *
 * Customer flow:
 *  1. Backend calls $sdk->voice->tokens->create(['identity' => 'alice', 'ttl' => 1800])
 *     and returns the resulting `token` to the customer's browser/app.
 *  2. Browser SDK registers using the embedded `sip_credentials`.
 *  3. Outbound calls from the SDK go through OpenSIPS → Asterisk → carrier;
 *     the server-side $sdk->voice->calls->create(['to_identity' => 'alice', ...])
 *     flow can also ring this identity for click-to-call patterns.
 */
class Tokens
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Mint an ephemeral voice token + SIP credentials.
     *
     * Optional: identity (defaults to "anonymous"), ttl (clamped to server max),
     * grants (capability grants attached to the token).
     *
     * POST /voice/tokens
     */
    public function create(array $tokenData = [], array $options = []): array
    {
        return $this->client->request('/voice/tokens', array_merge([
            'method' => 'POST',
            'body' => $tokenData,
        ], $options));
    }
}
