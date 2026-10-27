<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * Voice calls — outbound call origination and call log queries.
 *
 * Wraps the Voice service's /voice/v1/calls surface.
 *
 * Origination modes:
 *  - Single-leg (no trunk/profile/identity): Twilio-style direct outbound —
 *    dial `to` from caller-ID `from`. Customer's `from` must be an
 *    account-owned number.
 *  - SIP trunk (`sip_trunk_id`): Route via a configured trunk; the trunk's
 *    auth/ACL governs caller-ID rules.
 *  - Two-leg-with-bridge (`sip_profile_id` OR `to_identity`): Ring the
 *    SDK-registered endpoint first, then dial `to` and bridge. `to_identity`
 *    resolves to the most recently-minted unexpired ephemeral identity for
 *    that name.
 */
class Calls
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Create an outbound call.
     *
     * Required: to, from. Optional: sip_trunk_id, sip_profile_id, to_identity,
     * answer_url, answer_method, status_callback, status_callback_method,
     * recording_enabled, transcription_enabled, ivr_flow_id, metadata.
     *
     * POST /voice/v1/calls
     */
    public function create(array $callData, array $options = []): array
    {
        $this->client->require(['callData' => $callData]);
        return $this->client->request('/voice/v1/calls', array_merge([
            'method' => 'POST',
            'body' => $callData,
        ], $options));
    }

    /**
     * List call logs for the current account, paginated and filterable.
     *
     * Optional filters: page, limit, status, direction, from, to, date_from, date_to.
     *
     * GET /voice/v1/calls
     */
    public function list(array $filters = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($filters);
        return $this->client->request("/voice/v1/calls{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a single call log by ID.
     * GET /voice/v1/calls/:id
     */
    public function get(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/v1/calls/{$safeId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Hang up an in-progress call. Optional reason: NORMAL, BUSY, NO_ANSWER,
     * REJECTED. Defaults to NORMAL on the server when omitted.
     * POST /voice/v1/calls/:id/hangup
     */
    public function hangup(string $id, ?string $reason = null, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        $body = $reason !== null ? ['reason' => $reason] : [];
        return $this->client->request("/voice/v1/calls/{$safeId}/hangup", array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }
}
