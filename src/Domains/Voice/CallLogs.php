<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * Call Logs — the account-scoped voice call history (Voice service
 * /voice/api/v1/call-logs). Distinct from voice.calls, which is the
 * Twilio-compatible programmatic call-control surface; this is the richer log
 * read surface with cost, recording, and voice-config attribution used by the
 * portal Call Logs view.
 *
 * List filters (camelCase): page, limit, direction, status, callSource,
 * subgroupId, sipTrunkId, from, to, q, sentiment, voicemail, dateFrom, dateTo,
 * sort, order.
 *
 * Routes are mounted under /voice/api/v1/call-logs on the Voice service.
 */
class CallLogs
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * List call logs for the current account, paginated and filterable.
     *
     * Optional filters: page, limit, direction, status, callSource, subgroupId,
     * sipTrunkId, from, to, q, sentiment, voicemail, dateFrom, dateTo, sort, order.
     *
     * GET /voice/api/v1/call-logs
     */
    public function list(array $filters = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($filters);
        return $this->client->request("/voice/api/v1/call-logs{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a single call log by its UUID or callId.
     * GET /voice/api/v1/call-logs/:id
     */
    public function get(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/call-logs/{$safeId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a short-TTL presigned playback URL for a call's recording. Account-scoped
     * — only the owning account can presign. 404 if the call has no recording.
     * GET /voice/api/v1/call-logs/:id/recording
     */
    public function getRecording(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/call-logs/{$safeId}/recording", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Mark a voicemail (a call log with a recording) read or unread. Account-scoped.
     * PATCH /voice/api/v1/call-logs/:id/voicemail-read
     */
    public function markVoicemailRead(string $id, bool $read = true, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/call-logs/{$safeId}/voicemail-read", array_merge([
            'method' => 'PATCH',
            'body' => ['read' => $read],
        ], $options));
    }
}
