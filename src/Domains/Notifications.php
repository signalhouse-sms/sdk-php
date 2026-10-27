<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Notifications
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Get notifications by ID or group ID with optional filters
     *
     * @param array $params Filter parameters (id, groupId, page, limit, status, eventTypes)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getNotifications(array $params = [], array $options = []): array
    {
        $this->client->require(['id or groupId' => $params['id'] ?? $params['groupId'] ?? null]);
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/notification{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Update the status of one or more notifications
     *
     * @param string|array $ids Notification ID or array of IDs
     * @param string $status New status ("READ" or "UNREAD")
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateNotificationStatus(string|array $ids, string $status, array $options = []): array
    {
        $this->client->require(['ids' => $ids, 'status' => $status]);
        return $this->client->request('/notification/status', array_merge([
            'method' => 'PUT',
            'body' => ['ids' => $ids, 'status' => $status],
        ], $options));
    }

    /**
     * Delete a notification by ID
     *
     * @param string $id The notification ID
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteNotification(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/notification/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }
}
