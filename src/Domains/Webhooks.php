<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Webhooks
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Get a list of webhooks with optional filters. Each returned endpoint carries
     * `hasSigningSecret` (bool) instead of the signing secret itself — see createWebhook.
     *
     * @param array $params Filter parameters (id, groupId, endpointType, phoneNumber, page, limit)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getWebhooks(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/webhook{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create a new webhook. The response includes a `signingSecret` (plaintext HMAC-SHA256 secret)
     * used to sign every delivery to this endpoint via the X-SignalHouse-Signature header. It is
     * returned exactly once, here — store it now, it is never exposed again by any read.
     *
     * `authType` in $webhookData is one of `NONE` (default), `BASIC`, `API_KEY`, `OAUTH2` or `HMAC`.
     * `HMAC` sends no credentials (the receiver authenticates by the signature alone) and must be sent
     * without `credentials` or `apiHeaderPrefix`.
     *
     * `eventSettings` in $webhookData holds per-event options keyed by event name:
     * `NUMBER_HEALTH_BELOW_THRESHOLD` and `CAMPAIGN_HEALTH_BELOW_THRESHOLD` each take `['threshold' => n]`,
     * the 7-day health score (1.5–10, in 0.5 steps) below which the event fires. A subscribed health event
     * without a threshold is stored with the default, 6.
     *
     * @param array $webhookData The webhook data
     * @param array $options Additional request options
     * @return array The response from the server, including the plaintext `signingSecret` (this one time only)
     */
    public function createWebhook(array $webhookData, array $options = []): array
    {
        $this->client->require(['webhookData' => $webhookData]);
        return $this->client->request('/webhook', array_merge([
            'method' => 'POST',
            'body' => $webhookData,
        ], $options));
    }

    /**
     * Update an existing webhook. Like every other read of a webhook, the response carries
     * `hasSigningSecret` (bool), never the signing secret itself — see createWebhook.
     *
     * Switching `authType` to `NONE` or `HMAC` clears the stored credentials. `HMAC` is rejected for an
     * endpoint with no signing secret; call rotateWebhookSecret first.
     *
     * `eventSettings` in $updateData changes per-event options by event name (`NUMBER_HEALTH_BELOW_THRESHOLD`,
     * `CAMPAIGN_HEALTH_BELOW_THRESHOLD`, each `['threshold' => n]`, 1.5–10 in 0.5 steps); events you omit keep
     * their stored settings.
     *
     * @param string $id The webhook ID
     * @param array $updateData The data to update
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateWebhook(string $id, array $updateData, array $options = []): array
    {
        $this->client->require(['id' => $id, 'updateData' => $updateData]);
        $safeId = rawurlencode($id);
        return $this->client->request("/webhook/{$safeId}", array_merge([
            'method' => 'PUT',
            'body' => $updateData,
        ], $options));
    }

    /**
     * Rotate a webhook's signing secret, or give a webhook created before signing its first one.
     * The response includes the new plaintext signingSecret, returned this one time only. The replaced
     * secret keeps working for $graceSeconds (default 86400, max 604800, 0 = stop now).
     *
     * @param string $id The webhook ID
     * @param int|null $graceSeconds How long the replaced secret stays valid
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function rotateWebhookSecret(string $id, ?int $graceSeconds = null, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/webhook/{$safeId}/rotate-secret", array_merge([
            'method' => 'POST',
            'body' => $graceSeconds === null ? new \stdClass() : ['graceSeconds' => $graceSeconds],
        ], $options));
    }

    /**
     * Delete a webhook (mark as inactive)
     *
     * @param string $id The webhook ID
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteWebhook(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/webhook/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }
}
