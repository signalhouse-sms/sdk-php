<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * SIP Trunks — peer-to-peer SIP connections to a customer PBX or carrier.
 * Routes are mounted under /voice/sip-trunks on the Voice service.
 */
class SipTrunks
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * List all SIP trunks for the current account.
     * GET /voice/sip-trunks
     */
    public function list(array $options = []): array
    {
        return $this->client->request('/voice/sip-trunks', array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a single SIP trunk by ID.
     * GET /voice/sip-trunks/:id
     */
    public function get(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/sip-trunks/{$safeId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create a new SIP trunk. Registration trunks return a one-time-visible
     * password on the response.
     *
     * Required: name, region, connectionType (IP_AUTH or REGISTRATION).
     * Optional: allowedIps, transport, maxSpendPerMinute, destinationHosts, sourceHosts.
     *
     * POST /voice/sip-trunks
     */
    public function create(array $trunkData, array $options = []): array
    {
        $this->client->require(['trunkData' => $trunkData]);
        return $this->client->request('/voice/sip-trunks', array_merge([
            'method' => 'POST',
            'body' => $trunkData,
        ], $options));
    }

    /**
     * Update an existing SIP trunk.
     * PATCH /voice/sip-trunks/:id
     */
    public function update(string $id, array $updateData, array $options = []): array
    {
        $this->client->require(['id' => $id, 'updateData' => $updateData]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/sip-trunks/{$safeId}", array_merge([
            'method' => 'PATCH',
            'body' => $updateData,
        ], $options));
    }

    /**
     * Delete a SIP trunk.
     * DELETE /voice/sip-trunks/:id
     */
    public function delete(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/sip-trunks/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Toggle a trunk's active/inactive status.
     * POST /voice/sip-trunks/:id/toggle-active
     */
    public function toggleActive(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/sip-trunks/{$safeId}/toggle-active", array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Regenerate the SIP password for a REGISTRATION-type trunk. The old
     * password stops working immediately. Returns { password: "..." }.
     * POST /voice/sip-trunks/:id/regenerate-password
     */
    public function regeneratePassword(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/sip-trunks/{$safeId}/regenerate-password", array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Assign phone numbers to a SIP trunk so inbound calls route over it.
     * POST /voice/sip-trunks/:id/assign-numbers
     */
    public function assignNumbers(string $id, array $phoneNumbers, array $options = []): array
    {
        $this->client->require(['id' => $id, 'phoneNumbers' => $phoneNumbers]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/sip-trunks/{$safeId}/assign-numbers", array_merge([
            'method' => 'POST',
            'body' => ['phoneNumbers' => $phoneNumbers],
        ], $options));
    }

    /**
     * Unassign phone numbers from a SIP trunk.
     * POST /voice/sip-trunks/:id/unassign-numbers
     */
    public function unassignNumbers(string $id, array $phoneNumbers, array $options = []): array
    {
        $this->client->require(['id' => $id, 'phoneNumbers' => $phoneNumbers]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/sip-trunks/{$safeId}/unassign-numbers", array_merge([
            'method' => 'POST',
            'body' => ['phoneNumbers' => $phoneNumbers],
        ], $options));
    }

    /**
     * List available SIP Points of Presence.
     * GET /voice/sip-trunks/pops
     */
    public function getPops(array $options = []): array
    {
        return $this->client->request('/voice/sip-trunks/pops', array_merge([
            'method' => 'GET',
        ], $options));
    }
}
