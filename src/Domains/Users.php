<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Users
{
    private HttpClient $client;
    private bool $enableAdmin;
    public ?object $admin = null;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;

        if ($enableAdmin) {
            $this->admin = new class($client) {
                private HttpClient $client;

                public function __construct(HttpClient $client)
                {
                    $this->client = $client;
                }

                /**
                 * Get a list of users with optional filters (admin)
                 */
                public function getUsers(array $params = [], array $options = []): array
                {
                    $queryString = $this->client->getQueryString($params);
                    return $this->client->request("/user{$queryString}", array_merge([
                        'method' => 'GET',
                    ], $options));
                }

                /**
                 * Create a new internal user
                 */
                public function createInternalUser(array $data, array $options = []): array
                {
                    $this->client->require(['data' => $data]);
                    return $this->client->request('/user/signalhouse', array_merge([
                        'method' => 'POST',
                        'body' => $data,
                    ], $options));
                }
            };
        }
    }

    /**
     * Get a list of users with optional filters
     *
     * @param array $params Filter parameters (id, groupId, userType)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getUsers(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/user{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create a new user
     *
     * @param array $data User data (groupId, role, password, email, etc.)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createUser(array $data, array $options = []): array
    {
        $this->client->require(['data' => $data]);
        return $this->client->request('/user', array_merge([
            'method' => 'POST',
            'body' => $data,
        ], $options));
    }

    /**
     * Create a new service user (API key)
     *
     * @param array $data Service user data (groupId, name, role). Customer API keys (role "api") may also set
     *                    allowedIps (IPv4/IPv6 addresses or CIDR ranges, max 100) and allowedCountries
     *                    (ISO 3166-1 alpha-2 codes such as "US", max 250); both must match when both are set.
     *                    allowedDestinationCountries (ISO 3166-1 alpha-2, max 250) limits the countries the key
     *                    can send messages to.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createServiceUser(array $data, array $options = []): array
    {
        $this->client->require(['data' => $data]);
        return $this->client->request('/user/serviceuser', array_merge([
            'method' => 'POST',
            'body' => $data,
        ], $options));
    }

    /**
     * Update a user's information
     *
     * @param string $id The user ID
     * @param array $data The data to update. For a customer API key, allowedIps / allowedCountries /
     *                    allowedDestinationCountries replace its restrictions ([] removes one); this needs an
     *                    interactive admin or developer session.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateUser(string $id, array $data, array $options = []): array
    {
        $this->client->require(['id' => $id, 'data' => $data]);
        $safeId = rawurlencode($id);
        return $this->client->request("/user/{$safeId}", array_merge([
            'method' => 'PUT',
            'body' => $data,
        ], $options));
    }

    /**
     * Delete a user (mark as inactive)
     *
     * @param string $id The user ID
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteUser(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/user/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Get notification preferences for a user
     *
     * @param string $id The user ID
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getNotificationPreferences(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/user/{$safeId}/notification-preferences", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Update notification preferences for a user
     *
     * @param string $id The user ID
     * @param array $notificationPreferences Array of preference objects [{name, web, email}]
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateNotificationPreferences(string $id, array $notificationPreferences, array $options = []): array
    {
        $this->client->require(['id' => $id, 'notificationPreferences' => $notificationPreferences]);
        $safeId = rawurlencode($id);
        return $this->client->request("/user/{$safeId}/notification-preferences", array_merge([
            'method' => 'PUT',
            'body' => ['notificationPreferences' => $notificationPreferences],
        ], $options));
    }
}
