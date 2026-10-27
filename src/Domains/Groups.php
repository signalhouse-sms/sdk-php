<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Groups
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
                 * Get resource counts grouped by group ID. Only signalhouse_admin can query all groups;
                 * every other allowed role is restricted to its JWT group scope.
                 */
                public function getGroupCounts(array $params = [], array $options = []): array
                {
                    $queryString = $this->client->getQueryString($params);
                    return $this->client->request("/group/counts{$queryString}", array_merge([
                        'method' => 'GET',
                    ], $options));
                }

                /**
                 * Get a list of all groups with optional pagination
                 */
                public function getGroups(array $params = [], array $options = []): array
                {
                    $queryString = $this->client->getQueryString($params);
                    return $this->client->request("/group{$queryString}", array_merge([
                        'method' => 'GET',
                    ], $options));
                }

                /**
                 * Create a new group
                 *
                 * $groupData optionally accepts psPartnerKey and ownerEmail to credit a referring partner.
                 */
                public function createGroup(array $groupData, array $options = []): array
                {
                    $this->client->require(['groupData' => $groupData]);
                    return $this->client->request('/group', array_merge([
                        'method' => 'POST',
                        'body' => $groupData,
                    ], $options));
                }

                /**
                 * Delete a group
                 */
                public function deleteGroup(string $groupId, array $options = []): array
                {
                    $this->client->require(['groupId' => $groupId]);
                    $safeGroupId = rawurlencode($groupId);
                    return $this->client->request("/group/{$safeGroupId}", array_merge([
                        'method' => 'DELETE',
                    ], $options));
                }

                /**
                 * Set a group's KYC complianceStatus. Only flips the field — it does
                 * not trigger any lockout side effects (halted sends, revoked API keys, etc.) for
                 * "rejected".
                 *
                 * @param string $groupId The ID of the group to update
                 * @param string $complianceStatus One of "unverified", "pending", "verified", "rejected"
                 * @param array $options Additional request options
                 * @return array The updated group object returned from the server
                 */
                public function updateComplianceStatus(string $groupId, string $complianceStatus, array $options = []): array
                {
                    $this->client->require(['groupId' => $groupId, 'complianceStatus' => $complianceStatus]);
                    $safeGroupId = rawurlencode($groupId);
                    return $this->client->request("/group/{$safeGroupId}/compliance-status", array_merge([
                        'method' => 'PUT',
                        'body' => ['complianceStatus' => $complianceStatus],
                    ], $options));
                }

                /**
                 * Link an external tenant (GHL/Shopify) to a V2 group (server-to-server).
                 * Exchanges a single-use link token for a canonical group, adopting an empty
                 * portal group, repointing to an existing group, or flagging for manual review.
                 *
                 * @param string $linkToken The single-use external-link token minted by the portal user
                 * @param string $externalSystem The external system ("ghl" or "shopify")
                 * @param string $externalId The external tenant identifier
                 * @param string|null $existingGroupId An existing V2 group ID to repoint to, if any
                 * @param array $options Additional request options
                 * @return array The link outcome (['status' => ..., 'canonicalGroupId' => ..., ...])
                 */
                public function linkExternal(string $linkToken, string $externalSystem, string $externalId, ?string $existingGroupId = null, array $options = []): array
                {
                    $this->client->require([
                        'linkToken' => $linkToken,
                        'externalSystem' => $externalSystem,
                        'externalId' => $externalId,
                    ]);
                    $body = [
                        'linkToken' => $linkToken,
                        'externalSystem' => $externalSystem,
                        'externalId' => $externalId,
                    ];
                    if ($existingGroupId !== null) {
                        $body['existingGroupId'] = $existingGroupId;
                    }
                    return $this->client->request('/group/link-external', array_merge([
                        'method' => 'POST',
                        'body' => $body,
                    ], $options));
                }
            };
        }
    }

    /**
     * Get details of a group by its ID
     *
     * @param string $id The ID of the group
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getGroup(string $id, array $options = []): array
    {
        $queryString = $this->client->getQueryString(['id' => $id]);
        return $this->client->request("/group{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Update a group with the specified data
     *
     * @param string $id The ID of the group
     * @param array $groupData The data to update. Optional CNP fields: cspId (string|null), defaultCnpSubgroupId (string|null)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateGroup(string $id, array $groupData, array $options = []): array
    {
        $this->client->require(['id' => $id, 'groupData' => $groupData]);
        $safeId = rawurlencode($id);
        return $this->client->request("/group/{$safeId}", array_merge([
            'method' => 'PUT',
            'body' => $groupData,
        ], $options));
    }
}
