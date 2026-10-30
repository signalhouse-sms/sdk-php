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

    /**
     * Get a group's Enterprise SSO configuration: whether the API can sign in through an OpenID
     * Connect identity provider, the callback URL to register at the identity provider, the saved
     * provider (never its client secret), and each claimed domain with the DNS TXT record that
     * verifies it. Role: admin.
     *
     * @param string $groupId The group
     * @param array $options Additional request options
     * @return array The response from the server (['groupId' => ..., 'available' => ..., 'callbackUrl' => ..., 'connection' => ..., 'domains' => [...]])
     */
    public function getSsoConfiguration(string $groupId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $queryString = $this->client->getQueryString(['groupId' => $groupId]);
        return $this->client->request("/group/sso{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create or update a group's OpenID Connect identity provider. Requires a fresh
     * auth->confirmTwoFactorIdentity from the same session. Role: admin.
     *
     * @param string $groupId The group
     * @param string $issuer The provider's https issuer URL
     * @param string $clientId The client ID registered at the provider
     * @param string|null $clientSecret The client secret; required the first time, omit to keep the saved one
     * @param bool|null $enabled Whether sign-in through the provider is on (default true)
     * @param array $options Additional request options
     * @return array The SSO configuration
     */
    public function saveSsoConnection(string $groupId, string $issuer, string $clientId, ?string $clientSecret = null, ?bool $enabled = null, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId, 'issuer' => $issuer, 'clientId' => $clientId]);
        $body = array_filter(
            ['groupId' => $groupId, 'issuer' => $issuer, 'clientId' => $clientId, 'clientSecret' => $clientSecret, 'enabled' => $enabled],
            fn ($value) => $value !== null
        );
        return $this->client->request('/group/sso', array_merge([
            'method' => 'PUT',
            'body' => $body,
        ], $options));
    }

    /**
     * Remove a group's identity provider and its domains, and unlink every account that signed in
     * through it. Requires a fresh auth->confirmTwoFactorIdentity from the same session. Role: admin.
     *
     * @param string $groupId The group
     * @param array $options Additional request options
     * @return array The (now empty) SSO configuration
     */
    public function deleteSsoConnection(string $groupId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $queryString = $this->client->getQueryString(['groupId' => $groupId]);
        return $this->client->request("/group/sso{$queryString}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Claim an email domain for a group's SSO; the response includes the DNS TXT record that
     * verifies it. Role: admin.
     *
     * @param string $groupId The group
     * @param string $domain The domain, e.g. "acme.com"
     * @param array $options Additional request options
     * @return array The SSO configuration
     */
    public function addSsoDomain(string $groupId, string $domain, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId, 'domain' => $domain]);
        return $this->client->request('/group/sso/domains', array_merge([
            'method' => 'POST',
            'body' => ['groupId' => $groupId, 'domain' => $domain],
        ], $options));
    }

    /**
     * Verify a claimed domain by its DNS TXT record. Role: admin.
     *
     * @param string $groupId The group
     * @param string $domain The claimed domain
     * @param array $options Additional request options
     * @return array The SSO configuration
     */
    public function verifySsoDomain(string $groupId, string $domain, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId, 'domain' => $domain]);
        $safeDomain = rawurlencode($domain);
        return $this->client->request("/group/sso/domains/{$safeDomain}/verify", array_merge([
            'method' => 'POST',
            'body' => ['groupId' => $groupId],
        ], $options));
    }

    /**
     * Remove a domain from a group's SSO. Role: admin.
     *
     * @param string $groupId The group
     * @param string $domain The domain
     * @param array $options Additional request options
     * @return array The SSO configuration
     */
    public function removeSsoDomain(string $groupId, string $domain, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId, 'domain' => $domain]);
        $safeDomain = rawurlencode($domain);
        $queryString = $this->client->getQueryString(['groupId' => $groupId]);
        return $this->client->request("/group/sso/domains/{$safeDomain}{$queryString}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }
}
