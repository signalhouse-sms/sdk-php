<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * Call Flows.
 *
 * A call flow is a customer-built call tree (greeting, menu, forward, voicemail, hang up)
 * that runs when a number's route, or its Programmable Voice Profile, names it with
 * routeAction CALL_FLOW. Each flow has one working draft, edited with saveDraft(), and
 * published versions: publish() validates the draft and makes it the version calls run,
 * while the draft stays editable without affecting calls.
 *
 * Create fields:
 *  - name       (string, required) Flow name, unique within its subgroup.
 *  - subgroupId (string, required) The subgroup the flow belongs to. Fixed once created;
 *               only numbers and Programmable Voice Profiles in it can run the flow.
 *
 * Draft shape (saveDraft): ['nodes' => [...], 'edges' => [...]]. Each node is
 * ['id', 'type', 'name', 'config', 'positionX', 'positionY'] and each edge
 * ['id', 'fromNodeId', 'toNodeId', 'port']; ids are UUIDs you generate and are kept
 * across saves.
 *
 * Routes are mounted under /voice/api/v1/call-flows on the Voice service.
 */
class CallFlows
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * List the account's call flows, newest first by default.
     *
     * Optional filters: page, limit, q, subgroupId, includeDeleted (bool), sortBy
     * (createdAt|updatedAt|name), sortOrder (asc|desc).
     *
     * GET /voice/api/v1/call-flows
     */
    public function list(array $filters = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($filters);
        return $this->client->request("/voice/api/v1/call-flows{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * List the step types this service can run, with the outcomes each one offers.
     * GET /voice/api/v1/call-flows/catalog
     */
    public function catalog(array $options = []): array
    {
        return $this->client->request('/voice/api/v1/call-flows/catalog', array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a call flow with its working draft and the issues that stop it being published.
     * GET /voice/api/v1/call-flows/:id
     */
    public function get(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/call-flows/{$safeId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create a call flow, with its working draft and the Inbound Call step every flow
     * starts from.
     *
     * @param array $flowData name and subgroupId (see class docblock).
     * POST /voice/api/v1/call-flows
     */
    public function create(array $flowData, array $options = []): array
    {
        $this->client->require(['flowData' => $flowData]);
        return $this->client->request('/voice/api/v1/call-flows', array_merge([
            'method' => 'POST',
            'body' => $flowData,
        ], $options));
    }

    /**
     * Rename a call flow, or enable or disable it (partial update).
     *
     * @param array $updateData name and/or status (ACTIVE|DISABLED). A disabled flow does
     *                          not run; numbers routed to it fall through to their next
     *                          routing layer.
     * PATCH /voice/api/v1/call-flows/:id
     */
    public function update(string $id, array $updateData, array $options = []): array
    {
        $this->client->require(['id' => $id, 'updateData' => $updateData]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/call-flows/{$safeId}", array_merge([
            'method' => 'PATCH',
            'body' => $updateData,
        ], $options));
    }

    /**
     * Delete a call flow. Reversible with restore(): while deleted, numbers and profiles
     * routed to it fall through to their next routing layer.
     * DELETE /voice/api/v1/call-flows/:id
     */
    public function delete(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/call-flows/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Restore a deleted call flow.
     *
     * Sends an empty JSON object. The client always declares JSON and the Voice service
     * rejects a JSON request with no body; (object) [] encodes as {} where [] would not.
     * POST /voice/api/v1/call-flows/:id/restore
     */
    public function restore(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/call-flows/{$safeId}/restore", array_merge([
            'method' => 'POST',
            'body' => (object) [],
        ], $options));
    }

    /**
     * Copy a call flow and its working draft into a new flow in the same subgroup. The
     * copy is not published.
     *
     * @param string|null $name Name for the copy; defaults to "<name> (copy)".
     * POST /voice/api/v1/call-flows/:id/duplicate
     */
    public function duplicate(string $id, ?string $name = null, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/call-flows/{$safeId}/duplicate", array_merge([
            'method' => 'POST',
            'body' => $name !== null && $name !== '' ? ['name' => $name] : (object) [],
        ], $options));
    }

    /**
     * Replace the working draft. The whole draft is saved even when it is not finished;
     * the issues that would stop it being published come back in the response.
     *
     * @param array $draft ['nodes' => [...], 'edges' => [...]] (see class docblock).
     * PUT /voice/api/v1/call-flows/:id/draft
     */
    public function saveDraft(string $id, array $draft, array $options = []): array
    {
        $this->client->require(['id' => $id, 'draft' => $draft]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/call-flows/{$safeId}/draft", array_merge([
            'method' => 'PUT',
            'body' => $draft,
        ], $options));
    }

    /**
     * Publish the working draft, making it the version calls run. A draft that is not
     * ready returns 400 with error.issues listing each step that needs attention.
     *
     * Sends an empty JSON object, for the same reason as restore().
     * POST /voice/api/v1/call-flows/:id/publish
     */
    public function publish(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/call-flows/{$safeId}/publish", array_merge([
            'method' => 'POST',
            'body' => (object) [],
        ], $options));
    }
}
