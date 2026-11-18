<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Subgroups
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Get a list of subgroups with optional filters
     *
     * @param array $params Filter parameters (id, groupId, page, limit)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getSubgroups(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/subgroup{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
    * Get resource counts grouped by subgroup ID. Only signalhouse_admin can query all groups;
    * every other allowed role is restricted to its JWT group scope.
     *
     * @param array $params Optional filters (groupId, subgroupId, limit, offset)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getSubgroupCounts(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/subgroup/counts{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Resolve the mapping between a GoHighLevel location and a Signal House subgroup, in either
     * direction. Provide exactly one of ghlLocationId or subgroupId.
     *
     * @param array $params Filter parameters: groupId (optional - customer roles default to their own
     *                       accessible group(s) and are rejected if they name a group they don't belong
     *                       to; a subgroupId/ghlLocationId that belongs to a different group than the
     *                       caller's returns a 404, identical to a nonexistent one), ghlLocationId,
     *                       subgroupId.
     * @param array $options Additional request options
     * @return array The response from the server: groupId, subgroupId, subgroupName, ghlLocationId, ghlLocationName
     */
    public function lookupGhlLocation(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/subgroup/ghl-location-lookup{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Link (or, with ghlLocationId null, unlink) a GoHighLevel location to a subgroup.
     * Requires signalhouse_api or signalhouse_admin role. At most one subgroup may hold a given
     * ghlLocationId; linking it here steals it from any other subgroup that currently holds it.
     *
     * @param string $subgroupId The subgroup to link
     * @param string|null $ghlLocationId The GHL location ID to link, or null to unlink
     * @param string|null $ghlLocationName Display name of the GHL location. Omit (do not pass this
     *                    argument) to leave the subgroup's currently stored name untouched (only when
     *                    ghlLocationId is unchanged - otherwise it is cleared); always cleared when
     *                    unlinking.
     * @param string|\DateTimeInterface|null $claimedAt When this link was locally claimed by the
     *                    caller. Required when linking ($ghlLocationId is not null) - used to detect
     *                    and reject a delayed/out-of-order push whose claim has since been superseded
     *                    by a newer one recorded elsewhere. Ignored when unlinking.
     * @param array $options Additional request options
     * @return array The response from the server: the updated subgroup
     */
    public function linkGhlLocation(string $subgroupId, ?string $ghlLocationId, ?string $ghlLocationName = null, string|\DateTimeInterface|null $claimedAt = null, array $options = []): array
    {
        $this->client->require(['subgroupId' => $subgroupId]);
        if ($ghlLocationId !== null) {
            $this->client->require(['claimedAt' => $claimedAt]);
        }
        $body = ['subgroupId' => $subgroupId, 'ghlLocationId' => $ghlLocationId];
        if ($ghlLocationName !== null) {
            $body['ghlLocationName'] = $ghlLocationName;
        }
        if ($claimedAt !== null) {
            $body['claimedAt'] = $claimedAt instanceof \DateTimeInterface ? $claimedAt->format(\DateTimeInterface::ATOM) : $claimedAt;
        }
        return $this->client->request('/subgroup/ghl-location-link', array_merge([
            'method' => 'PUT',
            'body' => $body,
        ], $options));
    }

    /**
     * Create a new subgroup
     *
     * @param array $subgroupData The subgroup data (groupId, subgroupName, contact info, address, optional carrierIdFamily/carrierIdRegion, etc.). Setting carrierIdFamily/carrierIdRegion requires signalhouse_api, signalhouse_admin, signalhouse_user, api, admin, developer, or billing role.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createSubgroup(array $subgroupData, array $options = []): array
    {
        $this->client->require(['subgroupData' => $subgroupData]);
        return $this->client->request('/subgroup', array_merge([
            'method' => 'POST',
            'body' => $subgroupData,
        ], $options));
    }

    /**
     * Update an existing subgroup
     *
     * @param string $id The subgroup ID
     * @param array $updateData The data to update, including optional carrierIdFamily/carrierIdRegion. Setting carrierIdFamily/carrierIdRegion requires signalhouse_api, signalhouse_admin, signalhouse_user, api, admin, developer, or billing role. May also include cnamAutoApply (bool; turns caller name auto-apply on or off, so eligible numbers get their brand's registered name on outbound calls) and cnamBrandId (string|null; default caller-ID brand for numbers with no campaign, must be a verified brand in this subgroup, required when turning auto-apply on in a subgroup with more than one verified brand). Setting cnamAutoApply/cnamBrandId requires signalhouse_api, signalhouse_admin, signalhouse_user, api, admin, or developer role.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateSubgroup(string $id, array $updateData, array $options = []): array
    {
        $this->client->require(['id' => $id, 'updateData' => $updateData]);
        $safeId = rawurlencode($id);
        return $this->client->request("/subgroup/{$safeId}", array_merge([
            'method' => 'PUT',
            'body' => $updateData,
        ], $options));
    }

    /**
     * Delete a subgroup (mark as inactive)
     *
     * @param string $id The subgroup ID
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteSubgroup(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/subgroup/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }
}
