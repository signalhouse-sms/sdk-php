<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Numbers
{
    private HttpClient $client;
    private HttpClient $multipartClient;
    private bool $enableAdmin;
    public ?object $admin = null;

    public function __construct(HttpClient $client, HttpClient $multipartClient, bool $enableAdmin)
    {
        $this->client = $client;
        $this->multipartClient = $multipartClient;
        $this->enableAdmin = $enableAdmin;

        if ($enableAdmin) {
            $this->admin = new class($client) {
                private HttpClient $client;

                public function __construct(HttpClient $client)
                {
                    $this->client = $client;
                }

                /**
                 * Change the status of a port-in request. Infobip exposes no porting-status API
                 * or webhook, so ON_HOLD and its reason are always staff-entered here.
                 *
                 * @param string $portingId The ID of the port-in request
                 * @param string $status The new status to set (PENDING, IN_PROGRESS, ON_HOLD, REJECTED)
                 * @param string|null $description Optional description for the status change
                 * @param array $options Additional request options
                 * @param string|null $onHoldReason Required when status is ON_HOLD (DOCUMENTATION_MISMATCH, ACCOUNT_INFO_INCORRECT, CARRIER_REJECTED, PENDING_CUSTOMER_ACTION, OTHER). Appended last, after $options, so existing positional callers (e.g. changePortingStatus($id, $status, $description, ['token' => $token])) are unaffected.
                 * @return array The response from the server
                 */
                public function changePortingStatus(string $portingId, string $status, ?string $description = null, array $options = [], ?string $onHoldReason = null): array
                {
                    $this->client->require(['portingId' => $portingId, 'status' => $status]);
                    $safePortingId = rawurlencode($portingId);
                    $body = ['status' => $status];
                    if ($description !== null) {
                        $body['description'] = $description;
                    }
                    if ($onHoldReason !== null) {
                        $body['onHoldReason'] = $onHoldReason;
                    }
                    return $this->client->request("/number/portin/{$safePortingId}/status", array_merge([
                        'method' => 'PUT',
                        'body' => $body,
                    ], $options));
                }

                /**
                 * Approve a port-in request
                 *
                 * @param string $portingId The ID of the port-in request
                 * @param array $options Additional request options
                 * @return array The response from the server
                 */
                public function approvePortRequest(string $portingId, array $options = []): array
                {
                    $this->client->require(['portingId' => $portingId]);
                    $safePortingId = rawurlencode($portingId);
                    return $this->client->request("/number/portin/{$safePortingId}/approve", array_merge([
                        'method' => 'POST',
                    ], $options));
                }

                /** List campaign-bound Short Code acquisition requests. */
                public function getShortCodeAcquisitionRequests(array $params = [], array $options = []): array
                {
                    $queryString = $this->client->getQueryString($params);
                    return $this->client->request("/number/short-code/requests{$queryString}", array_merge(['method' => 'GET'], $options));
                }
            };
        }
    }

    /**
     * Get a list of phone numbers with optional filters
     *
     * @param array $params Filter parameters (phoneNumber, campaignId, registrationId, brandId, subgroupId, groupId, page, limit, currentTollFreeCarrier).
     *     registrationId filters to numbers provisioned under this registration.
     *     currentTollFreeCarrier (bool), with groupId or subgroupId: when true, return only the toll-free numbers a new
     *     toll-free campaign in that group can use.
     * @param array $options Additional request options
     * @return array The response from the server
     *
     * Every record carries a `sentiment` object -- trailing 7-day and 30-day inbound reply sentiment,
     * refreshed periodically from analytics rather than computed per request: `{ sevenDay, thirtyDay,
     * updatedAt }`, each window being `{ score, label, scoredCount, positive, neutral, negative }`. The
     * object is always present and fully populated, so it can be read without a presence check.
     * `score` is a volume-weighted average in -100..100 and is null when nothing was scored in the
     * window, which is not the same as a score of 0 -- null means nobody replied, 0 means replies
     * averaged neutral. `updatedAt` is null until the first rollup writes the record, and it tracks when
     * the figures last CHANGED, not when the job last ran.
     *
     * Every record also carries a `health` object -- trailing 7-day and 30-day delivery/opt-out health from
     * the same periodic refresh: `{ sevenDay, thirtyDay, updatedAt }`, each window being `{ score,
     * deliveryRate, optOutRate, deliveryScore, optOutScore, messagesSent, messagesDelivered, messagesFailed,
     * optOuts }`. `score` is 1.0..10.0 (the mean of the two bucket scores) and is null when nothing was sent
     * in the window; the volumes say how much traffic sits behind it. A campaign's figure is scored from the
     * campaign's own summed counters, never averaged from its numbers. For a live figure use GET /number/health
     * or GET /campaign/health.
     */
    public function getPhoneNumbers(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/number{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get the health score, the same one the portal shows, (1–10) for an owned phone number
     *
     * @param string $phoneNumber Owned phone number to score (11 digits, no + prefix)
     * @param array $options Additional request options
     * @return array Health score payload with sevenDay and thirtyDay breakdowns
     */
    public function getNumberHealth(string $phoneNumber, array $options = []): array
    {
        $this->client->require(['phoneNumber' => $phoneNumber]);
        $queryString = $this->client->getQueryString(['phoneNumber' => $phoneNumber]);
        return $this->client->request("/number/health{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get available phone numbers for purchase
     *
     * @param array $params Filter parameters (smsEnabled, mmsEnabled, voiceEnabled, country, state, city, npa, nxx, phoneNumber, limit, page). City is a prefix requiring country US and state.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAvailablePhoneNumbers(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/number/available{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Purchase phone numbers.
     *
     * Each requested number is queued and processed independently. Per-number success or failure
     * is delivered via NUMBER_PURCHASE_SUCCESSFUL / NUMBER_PURCHASE_FAILED webhooks — not in this response.
     *
     * @param array $phoneNumbers List of phone numbers to purchase
     * @param string $subgroupId The subgroup ID to assign them to
     * @param array $options Additional request options
     * @return array Response containing a `message` field confirming the request was queued
     */
    public function purchasePhoneNumber(array $phoneNumbers, string $subgroupId, array $options = [], string $country = 'US'): array
    {
        $this->client->require(['phoneNumbers' => $phoneNumbers, 'subgroupId' => $subgroupId]);
        return $this->client->request('/number', array_merge([
            'method' => 'POST',
            'body' => ['phoneNumbers' => $phoneNumbers, 'subgroupId' => $subgroupId, 'country' => $country],
        ], $options));
    }

    /**
     * Purchase one or more Toll-Free numbers via the asynchronous resource-request flow.
     *
     * Toll-Free numbers are ordered by quantity (1-10, not picked individually) and provisioned
     * asynchronously; per-number completion is delivered via webhook/polling, not in this response.
     * Poll the returned orderId with getTollFreeOrderStatus.
     *
     * @param int $quantity The number of Toll-Free numbers to purchase (1-10)
     * @param string $subgroupId The subgroup the purchased numbers are assigned to
     * @param array $options Additional request options
     * @return array Response resolving to { message, orderId } once the request is queued
     */
    public function purchaseTollFreeNumbers(int $quantity, string $subgroupId, array $options = [], string $country = 'US'): array
    {
        $this->client->require(['subgroupId' => $subgroupId]);
        return $this->client->request('/number/toll-free', array_merge([
            'method' => 'POST',
            'body' => ['quantity' => $quantity, 'subgroupId' => $subgroupId, 'country' => $country],
        ], $options));
    }

    /**
     * Request a campaign-bound Short Code acquisition or register a customer-owned Registry lease.
    * RANDOM and VANITY requests are fulfilled by Signal House staff. EXTERNAL_LEASE creates a pending Short Code
    * number immediately; it becomes READY only when its campaign becomes ACTIVE. A YYYY-MM-DD leaseEndDate
    * expires at 23:59:59 UTC.
     *
    * @param array $acquisitionData subgroupId, brandId, campaignId, requestType, and source-specific fields
     * @param array $options Additional request options
    * @param string|resource|null $leaseReceiptFile Required PNG, JPEG, or PDF receipt for EXTERNAL_LEASE
     * @return array Pending acquisition request or pending external-lease number
     */
    public function requestShortCodeAcquisition(array $acquisitionData, array $options = [], mixed $leaseReceiptFile = null): array
    {
        $this->client->require([
            'subgroupId' => $acquisitionData['subgroupId'] ?? null,
            'brandId' => $acquisitionData['brandId'] ?? null,
            'campaignId' => $acquisitionData['campaignId'] ?? null,
            'requestType' => $acquisitionData['requestType'] ?? null,
        ]);
        $multipart = [
            ['name' => 'acquisitionData', 'contents' => json_encode($acquisitionData)],
        ];
        if ($leaseReceiptFile !== null) {
            $multipart[] = [
                'name' => 'leaseReceipt',
                'contents' => is_string($leaseReceiptFile) ? fopen($leaseReceiptFile, 'r') : $leaseReceiptFile,
            ];
        }
        return $this->multipartClient->request('/number/short-code', array_merge([
            'method' => 'POST',
            'multipart' => $multipart,
        ], $options));
    }

    /**
     * Read the outcome of a Toll-Free number purchase by its order ID.
     *
     * Reports per-number ready/provisioning/failed counts (plus failure reasons) and the
     * provisioned numbers; numbers fills in as the order completes, so poll until the order
     * reaches a terminal state.
     *
     * @param string $orderId The order id returned by purchaseTollFreeNumbers
     * @param array $options Additional request options
     * @return array Response resolving to { orderId, counts, failures, numbers }
     */
    public function getTollFreeOrderStatus(string $orderId, array $options = []): array
    {
        $this->client->require(['orderId' => $orderId]);
        $safeOrderId = rawurlencode($orderId);
        return $this->client->request("/number/toll-free/order/{$safeOrderId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Update a phone number's details
     *
     * @param string $phoneNumber The phone number to update
     * @param array $updateData The data to update (e.g., friendlyName)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updatePhoneNumber(string $phoneNumber, array $updateData, array $options = []): array
    {
        $this->client->require(['phoneNumber' => $phoneNumber, 'updateData' => $updateData]);
        $safePhoneNumber = rawurlencode($phoneNumber);
        return $this->client->request("/number/{$safePhoneNumber}", array_merge([
            'method' => 'PUT',
            'body' => $updateData,
        ], $options));
    }

    /**
     * Get a phone number's outbound caller name (CNAM) - the name US carriers show for calls from it
     *
     * Roles: api, admin, developer, billing, user
     *
     * @param string $phoneNumber The phone number (E.164, leading + optional)
     * @param array $options Additional request options
     * @return array Response resolving to { phoneNumber, cnam, autoApply, eligible, ineligibleReason }
     */
    public function getCallerName(string $phoneNumber, array $options = []): array
    {
        $this->client->require(['phoneNumber' => $phoneNumber]);
        $safePhoneNumber = rawurlencode($phoneNumber);
        return $this->client->request("/number/{$safePhoneNumber}/cnam", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Set a phone number's outbound caller name (CNAM). The name may only remove characters from the
     * registered name of the number's brand (its campaign's brand, else $brandId, else the subgroup's
     * default caller-ID brand), and must be 1-15 letters, digits, spaces, - or _.
     *
     * Roles: api, admin, developer
     *
     * @param string $phoneNumber The phone number (E.164, leading + optional)
     * @param string $name The caller name to show
     * @param string|null $brandId The verified brand the name comes from; only for numbers with no campaign (sent only when provided)
     * @param array $options Additional request options
     * @return array Response resolving to { phoneNumber, cnam, autoApply, eligible, ineligibleReason }
     */
    public function setCallerName(string $phoneNumber, string $name, ?string $brandId = null, array $options = []): array
    {
        $this->client->require(['phoneNumber' => $phoneNumber, 'name' => $name]);
        $safePhoneNumber = rawurlencode($phoneNumber);
        $body = ['name' => $name];
        if ($brandId !== null && $brandId !== '') {
            $body['brandId'] = $brandId;
        }
        return $this->client->request("/number/{$safePhoneNumber}/cnam", array_merge([
            'method' => 'PUT',
            'body' => $body,
        ], $options));
    }

    /**
     * Remove a phone number's own caller name (CNAM). If its subgroup has caller name auto-apply on,
     * the number goes back to the automatic name.
     *
     * Roles: api, admin, developer
     *
     * @param string $phoneNumber The phone number (E.164, leading + optional)
     * @param array $options Additional request options
     * @return array Response resolving to { phoneNumber, cnam, autoApply, eligible, ineligibleReason }
     */
    public function deleteCallerName(string $phoneNumber, array $options = []): array
    {
        $this->client->require(['phoneNumber' => $phoneNumber]);
        $safePhoneNumber = rawurlencode($phoneNumber);
        return $this->client->request("/number/{$safePhoneNumber}/cnam", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Submit a port-in request for one or more phone numbers from another provider
     *
     * @param array $numberData The port-in request data including owner info, address, phone numbers, and signature
     * @param array $options Additional request options
     * @return array The created port-in request
     */
    public function portInPhoneNumber(array $numberData, array $options = []): array
    {
        $this->client->require(['numberData' => $numberData]);
        return $this->client->request('/number/portin', array_merge([
            'method' => 'POST',
            'body' => $numberData,
        ], $options));
    }

    /**
     * Get port-in requests with optional filters.
     * Signal House staff can view all requests; regular users see only their group's requests.
     *
     * @param string|null $groupId Filter by group ID
     * @param string|null $phoneNumber Filter by phone number
     * @param string|null $status Filter by status (PENDING, IN_PROGRESS, ON_HOLD, REJECTED, SUCCESS)
     * @param int|null $page Page number for pagination (default: 1)
     * @param int|null $limit Items per page (default: 20)
     * @param array $options Additional request options
     * @param bool|null $excludeDocuments When true, leave signatureUrl and billingUrl out of each request (the inline LOA signature and billing statement). Last so existing positional callers are unaffected.
     * @return array Paginated port-in requests
     */
    public function getPortRequests(?string $groupId = null, ?string $phoneNumber = null, ?string $status = null, ?int $page = null, ?int $limit = null, array $options = [], ?bool $excludeDocuments = null): array
    {
        $queryString = $this->client->getQueryString([
            'groupId' => $groupId,
            'phoneNumber' => $phoneNumber,
            'status' => $status,
            'page' => $page,
            'limit' => $limit,
            'excludeDocuments' => $excludeDocuments,
        ]);
        return $this->client->request("/number/portin{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a single port-in request by its ID
     *
     * @param string $id The ID of the port-in request
     * @param array $options Additional request options
     * @return array The port-in request details
     */
    public function getPortRequestById(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/number/portin/{$safeId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get porting requests. If 'portingId' key is provided in params,
     * fetches a single request by ID. Otherwise, fetches a filtered list.
     *
     * @param array $params Filter parameters. Include 'portingId' to fetch a single request,
     *                      or use groupId, phoneNumber, status, page, limit for filtered list.
     * @param array $options Additional request options
     * @return array The port-in request(s)
     */
    public function getPortingRequests(array $params = [], array $options = []): array
    {
        if (isset($params['portingId'])) {
            return $this->getPortRequestById($params['portingId'], $options);
        }

        return $this->getPortRequests(
            $params['groupId'] ?? null,
            $params['phoneNumber'] ?? null,
            $params['status'] ?? null,
            $params['page'] ?? null,
            $params['limit'] ?? null,
            $options,
            $params['excludeDocuments'] ?? null
        );
    }

    /**
     * Assign phone numbers to a campaign. Numbers that are not found, already RELEASED, already assigned
     * to a different campaign, on a mismatched DCA, or (for a 10DLC campaign without a number pool) beyond
     * the remaining 49-number-per-campaign limit are not queued - each is reported back individually in
     * skipped instead of failing the whole request. A Toll-Free campaign whose brand reports
     * tollFree.maxNumbersPerCampaign as 1 holds one number: a request that would give it a second is
     * refused with a 400 and nothing is queued.
     *
     * @param string $campaignId The campaign ID
     * @param array $phoneNumbers The phone numbers to assign
     * @param array $options Additional request options
     * @return array The response from the server, resolving to ['Message' => string, 'queued' => array, 'skipped' => array]
     */
    public function assignPhoneNumberToCampaign(string $campaignId, array $phoneNumbers, array $options = []): array
    {
        $this->client->require(['campaignId' => $campaignId, 'phoneNumbers' => $phoneNumbers]);
        $safeCampaignId = rawurlencode($campaignId);
        return $this->client->request("/number/assign/{$safeCampaignId}", array_merge([
            'method' => 'POST',
            'body' => ['phoneNumbers' => $phoneNumbers],
        ], $options));
    }

    /**
     * Unassign phone numbers from campaigns
     *
     * @param array $phoneNumbers The phone numbers to unassign
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function unassignPhoneNumbersFromCampaigns(array $phoneNumbers, array $options = []): array
    {
        $this->client->require(['phoneNumbers' => $phoneNumbers]);
        return $this->client->request('/number/unassign', array_merge([
            'method' => 'POST',
            'body' => ['phoneNumbers' => $phoneNumbers],
        ], $options));
    }

    /**
     * Delete (release) phone numbers
     *
     * @param array $phoneNumbers The phone numbers to release
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deletePhoneNumbers(array $phoneNumbers, array $options = []): array
    {
        $this->client->require(['phoneNumbers' => $phoneNumbers]);
        return $this->client->request('/number', array_merge([
            'method' => 'DELETE',
            'body' => ['phoneNumbers' => $phoneNumbers],
        ], $options));
    }

    /**
     * Deactivate one or more phone numbers. Numbers must be in READY status.
     *
     * @param array $phoneNumbers The phone numbers to deactivate
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deactivatePhoneNumbers(array $phoneNumbers, array $options = []): array
    {
        $this->client->require(['phoneNumbers' => $phoneNumbers]);
        return $this->client->request('/number/deactivate', array_merge([
            'method' => 'POST',
            'body' => ['phoneNumbers' => $phoneNumbers],
        ], $options));
    }

    /**
     * Reactivate one or more previously deactivated phone numbers.
     *
     * @param array $phoneNumbers The phone numbers to reactivate
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function reactivatePhoneNumbers(array $phoneNumbers, array $options = []): array
    {
        $this->client->require(['phoneNumbers' => $phoneNumbers]);
        return $this->client->request('/number/reactivate', array_merge([
            'method' => 'POST',
            'body' => ['phoneNumbers' => $phoneNumbers],
        ], $options));
    }

    /**
     * Transfer unassigned phone numbers to another subgroup
     *
     * @param array $phoneNumbers The phone numbers to transfer
     * @param string $newSubgroupId The target subgroup ID
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function transferPhoneNumbers(array $phoneNumbers, string $newSubgroupId, array $options = []): array
    {
        $this->client->require(['phoneNumbers' => $phoneNumbers, 'newSubgroupId' => $newSubgroupId]);
        return $this->client->request('/number/transfer', array_merge([
            'method' => 'POST',
            'body' => ['phoneNumbers' => $phoneNumbers, 'newSubgroupId' => $newSubgroupId],
        ], $options));
    }

    /**
     * Search NPA/NXX lookup data with optional filters
     *
     * At least one search parameter is required.
     * Country may scope NPA/NXX filters; state and city cannot be combined with them.
     *
     * @param array $params Filter parameters (country, state, city, npa, nxx)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function searchNpaNxx(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/number/lookup{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Batch lookup city/state for NPA/NXX pairs
     *
     * @param array $entries NPA/NXX pairs to look up (max 50), each with 'npa' and 'nxx' keys
     * @param array $options Additional request options
     * @return array The response with an array of {npa, nxx, city, state} objects
     */
    public function lookupLocations(array $entries, array $options = []): array
    {
        return $this->client->request('/number/lookup/location', array_merge([
            'method' => 'POST',
            'body' => ['entries' => $entries],
        ], $options));
    }

    /**
     * Migrate a phone number (or all numbers in a group) from v1 to v2.
     *
     * @param string|null $phoneNumber Phone number to migrate. If provided, groupId is ignored.
     * @param string|null $campaignId Campaign ID to migrate all numbers for.
     * @param string|null $groupId Group ID to migrate all non-released numbers for.
     * @param array $options Additional request options.
     * @return array API response.
     */
    public function migratePhoneNumberToV2(?string $phoneNumber = null, ?string $campaignId = null, ?string $groupId = null, array $options = []): array
    {
        $body = array_filter([
            'phoneNumber' => $phoneNumber,
            'campaignId' => $campaignId,
            'groupId' => $groupId,
        ], fn($v) => $v !== null);

        return $this->client->request('/number/migrate', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }
}
