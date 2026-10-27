<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * SIP Profiles / endpoints — single registerable SIP UAs.
 *
 * A SIP profile represents one device (desk phone, softphone, SIP app) that
 * registers to Signal House with a username + password. Distinct from a SIP
 * trunk, which is a peer-to-peer link to a PBX or carrier.
 *
 * Routes are mounted under /voice/sip-profiles on the Voice service.
 */
class SipProfiles
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * List all SIP profiles for the current account.
     * GET /voice/sip-profiles
     */
    public function list(array $options = []): array
    {
        return $this->client->request('/voice/sip-profiles', array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a single SIP profile by ID. Response does NOT include the password —
     * use getPassword() to retrieve it.
     * GET /voice/sip-profiles/:id
     */
    public function get(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/sip-profiles/{$safeId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Fetch the SIP password for this profile. Returns { password: "..." }.
     * GET /voice/sip-profiles/:id/password
     */
    public function getPassword(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/sip-profiles/{$safeId}/password", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * List valid SIP transports (UDP/TCP/TLS) with their address and port.
     * GET /voice/sip-profiles/transports
     */
    public function getTransports(array $options = []): array
    {
        return $this->client->request('/voice/sip-profiles/transports', array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create a SIP profile. The server generates the SIP username and password;
     * the password is returned at the TOP LEVEL of the response (not nested in
     * sipProfile) and is the only chance to surface it without an explicit
     * getPassword call.
     *
     * Required: name. Optional: recordingAllowed, transcriptionEnabled, sentimentFlag.
     *
     * POST /voice/sip-profiles
     */
    public function create(array $profileData, array $options = []): array
    {
        $this->client->require(['profileData' => $profileData]);
        return $this->client->request('/voice/sip-profiles', array_merge([
            'method' => 'POST',
            'body' => $profileData,
        ], $options));
    }

    /**
     * Update an existing SIP profile. To rotate the password, pass a new
     * `password` value — there is no dedicated regenerate endpoint.
     * PATCH /voice/sip-profiles/:id
     */
    public function update(string $id, array $updateData, array $options = []): array
    {
        $this->client->require(['id' => $id, 'updateData' => $updateData]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/sip-profiles/{$safeId}", array_merge([
            'method' => 'PATCH',
            'body' => $updateData,
        ], $options));
    }

    /**
     * Delete a SIP profile. Unassigns any linked numbers as a side effect.
     * DELETE /voice/sip-profiles/:id
     */
    public function delete(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/sip-profiles/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Assign a phone number to this SIP profile (routes inbound calls on that
     * number to the endpoint). Prefer $e164;
     * the legacy $phoneNumberId (a Voice service phone-number UUID) is still
     * accepted. Provide at least one of the two.
     * POST /voice/sip-profiles/:id/assign-number
     */
    public function assignNumber(string $id, ?string $phoneNumberId = null, ?string $e164 = null, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        if ($e164 === null && $phoneNumberId === null) {
            $this->client->require(['e164' => $e164]);
        }
        $safeId = rawurlencode($id);
        $body = $e164 !== null ? ['e164' => $e164] : ['phoneNumberId' => $phoneNumberId];
        return $this->client->request("/voice/sip-profiles/{$safeId}/assign-number", array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Unassign a phone number from this SIP profile. Prefer $e164; the legacy
     * $phoneNumberId (a Voice service phone-number UUID) is still accepted.
     * Provide at least one of the two.
     * POST /voice/sip-profiles/:id/unassign-number
     */
    public function unassignNumber(string $id, ?string $phoneNumberId = null, ?string $e164 = null, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        if ($e164 === null && $phoneNumberId === null) {
            $this->client->require(['e164' => $e164]);
        }
        $safeId = rawurlencode($id);
        $body = $e164 !== null ? ['e164' => $e164] : ['phoneNumberId' => $phoneNumberId];
        return $this->client->request("/voice/sip-profiles/{$safeId}/unassign-number", array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }
}
