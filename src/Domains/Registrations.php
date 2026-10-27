<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

/**
 * Sender registrations: the catalog, quotes, submission and status tracking for every jurisdiction
 */
class Registrations
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
                 * Approve or reject a registration awaiting Signal House review
                 *
                 * @param string $registrationId The ID of the registration to review
                 * @param string $action "APPROVE" (submits the registration to the provider) or "REJECT"
                 * @param string|null $reason Why the registration was rejected; required for "REJECT"
                 * @param array $options Additional request options
                 * @return array The response from the server
                 */
                public function reviewRegistration(string $registrationId, string $action, ?string $reason = null, array $options = []): array
                {
                    $this->client->require(['registrationId' => $registrationId, 'action' => $action]);
                    $safeRegistrationId = rawurlencode($registrationId);
                    $body = ['action' => $action];
                    if ($reason !== null) {
                        $body['reason'] = $reason;
                    }
                    return $this->client->request("/registration/{$safeRegistrationId}/review", array_merge([
                        'method' => 'POST',
                        'body' => $body,
                    ], $options));
                }

                /**
                 * Save corrections for a registration the provider sent back for more information
                 *
                 * @param string $registrationId The ID of the registration to correct
                 * @param string $reason Why the correction is being made
                 * @param array $data The type's correctable fields (VIRTUAL_LONG_CODE: useCases and, for SMS + Voice, voice).
                 *     Owner, quantity, capabilities and the provider order are fixed.
                 * @param array $options Additional request options
                 * @return array The response from the server
                 */
                public function correctRegistration(string $registrationId, string $reason, array $data, array $options = []): array
                {
                    $this->client->require(['registrationId' => $registrationId, 'reason' => $reason]);
                    $safeRegistrationId = rawurlencode($registrationId);
                    return $this->client->request("/registration/{$safeRegistrationId}/corrections", array_merge([
                        'method' => 'POST',
                        'body' => ['reason' => $reason, 'data' => $data],
                    ], $options));
                }

                /**
                 * Allocate a provider-owned number to a registration through the staff recovery path
                 *
                 * @param string $registrationId The ID of the registration
                 * @param string $phoneNumber The allocated number in E.164 digits (10-15 digits, optional leading +)
                 * @param array $options Additional request options
                 * @return array The response from the server
                 */
                public function addNumber(string $registrationId, string $phoneNumber, array $options = []): array
                {
                    $this->client->require(['registrationId' => $registrationId, 'phoneNumber' => $phoneNumber]);
                    $safeRegistrationId = rawurlencode($registrationId);
                    return $this->client->request("/registration/{$safeRegistrationId}/numbers", array_merge([
                        'method' => 'POST',
                        'body' => ['phoneNumber' => $phoneNumber],
                    ], $options));
                }

                /**
                 * Link a RECONCILIATION_REQUIRED registration to the provider order that was actually placed.
                 * Never place a second order to retry an ambiguous submission.
                 *
                 * @param string $registrationId The ID of the registration to reconcile
                 * @param string $externalId The provider's identifier for the existing order
                 * @param array $options Additional request options
                 * @return array The response from the server
                 */
                public function reconcile(string $registrationId, string $externalId, array $options = []): array
                {
                    $this->client->require(['registrationId' => $registrationId, 'externalId' => $externalId]);
                    $safeRegistrationId = rawurlencode($registrationId);
                    return $this->client->request("/registration/{$safeRegistrationId}/reconcile", array_merge([
                        'method' => 'POST',
                        'body' => ['externalId' => $externalId],
                    ], $options));
                }

                /**
                 * Get the completed carrier application as ['filename', 'contentType', 'base64']; decode base64 to save the file
                 *
                 * @param string $registrationId The ID of the registration whose application to download
                 * @param array $options Additional request options
                 * @return array The response from the server
                 */
                public function getApplication(string $registrationId, array $options = []): array
                {
                    $this->client->require(['registrationId' => $registrationId]);
                    $safeRegistrationId = rawurlencode($registrationId);
                    return $this->client->request("/registration/{$safeRegistrationId}/application?format=json", array_merge([
                        'method' => 'GET',
                    ], $options));
                }
            };
        }
    }

    /**
     * Get the registration types a jurisdiction offers and whether each can be acquired right now
     *
     * @param string $region The jurisdiction (US, GB, CA, AU; any case of "UK"/"GB"/"GBR"/"United Kingdom" is accepted for GB)
     * @param array $options Additional request options
     * @return array The response whose data is region, jurisdiction, sendingRequiresRegistration, registeredThrough, provisioning and
     *     types: [type, kind, channel, bundles, quantity (min, max), requiresParent, available]
     */
    public function getCatalog(string $region, array $options = []): array
    {
        $this->client->require(['region' => $region]);
        $queryString = $this->client->getQueryString(['region' => $region]);
        return $this->client->request("/registration/catalog{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get setup and monthly prices, in integer microdollars, for each capability bundle of a type
     *
     * @param array $params Query parameters: region (required), type (required), quantity (1-100, server default 1)
     *     and groupId (staff only — quote on behalf of another group). type is e.g. VIRTUAL_LONG_CODE, or
     *     ALPHANUMERIC_SENDER_ID for GB (one ['SMS'] bundle, 6000000 setup and 6000000 monthly by default)
     * @param array $options Additional request options
     * @return array The response whose data is one quote per capability bundle: capabilities, quantity, currency,
     *     setupAmount, monthlyAmount, totalSetupAmount, totalMonthlyAmount, etaDays. setupAmount/monthlyAmount are
     *     per sender; the totals cover the quantity.
     */
    public function getQuotes(array $params = [], array $options = []): array
    {
        $this->client->require(['region' => $params['region'] ?? null, 'type' => $params['type'] ?? null]);
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/registration/quotes{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a list of registrations with optional filters and pagination
     *
     * @param array $params Filter parameters (groupId, subgroupId, region, type, kind, status, parentRegistrationId,
     *     phoneNumber, sortBy, sortOrder, page, limit). phoneNumber filters to registrations holding this phone number.
     *     sortBy is createdAt (default), subgroupId, registrationId, region or status; sortOrder is asc or desc
     *     (default). kind is identity, program or sender. region filters by one jurisdiction or a list (array,
     *     sent as repeated keys). status filters by one registration status or a list (array, sent as repeated
     *     keys) from DRAFT, SIGNAL_HOUSE_REVIEW,
     *     SIGNAL_HOUSE_APPROVED, SIGNAL_HOUSE_REJECTED, SUBMITTING, PENDING_PROVIDER, NEEDS_INFORMATION,
     *     PAYMENT_REQUIRED, APPROVED, REJECTED, CANCELLED, PENDING_DELETE, DELETED, EXPIRED or
     *     RECONCILIATION_REQUIRED. limit is at most 100.
     * @param array $options Additional request options
     * @return array The response with data, totalCount, page, and limit
     */
    public function getRegistrations(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/registration{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a single registration with its type data, review outcome and status history
     *
     * @param string $registrationId The ID of the registration to retrieve
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getRegistration(string $registrationId, array $options = []): array
    {
        $this->client->require(['registrationId' => $registrationId]);
        $safeRegistrationId = rawurlencode($registrationId);
        return $this->client->request("/registration/{$safeRegistrationId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Cancel a registration. Allowed from SIGNAL_HOUSE_REVIEW, SIGNAL_HOUSE_APPROVED, PENDING_PROVIDER,
     * NEEDS_INFORMATION, PAYMENT_REQUIRED and APPROVED (409 otherwise). Numbers already allocated stay on the
     * account and stop sending.
     *
     * @param string $registrationId The ID of the registration to cancel
     * @param array $options Additional request options
     * @return array The response containing the cancelled registration
     */
    public function cancelRegistration(string $registrationId, array $options = []): array
    {
        $this->client->require(['registrationId' => $registrationId]);
        $safeRegistrationId = rawurlencode($registrationId);
        return $this->client->request("/registration/{$safeRegistrationId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Submit a registration for Signal House review
     *
     * @param array $registrationData ['region', 'type', 'subgroupId', 'capabilities' (one of
     *     the type's bundles), 'data']. type selects the data shape; for VIRTUAL_LONG_CODE it is ['quantity' (1-100),
     *     'useCases' (1-10), 'voice'?] with voice required exactly when capabilities include VOICE. For GB
     *     ALPHANUMERIC_SENDER_ID (capabilities ['SMS']) it is ['requestedSenderId' (3-11 letters/digits/space/dot/dash,
     *     at least one letter), 'trafficOrigin' (LOCAL | INTERNATIONAL), 'trafficType' (TRANSACTIONAL | PROMOTIONAL),
     *     'companyName', 'companyCountry', 'companyWebsite', 'industry', 'messageExample', 'senderRelationship'?]
     *     with senderRelationship required when requestedSenderId differs from companyName; quantity is always 1 and
     *     data.phoneNumbers carries the granted sender (e.g. ['ACME']) once APPROVED. Pass
     *     $options['idempotencyKey'] to make a retry safe.
     * @param array $options Additional request options
     * @return array The response (HTTP 201) containing the new registration
     */
    public function createRegistration(array $registrationData, array $options = []): array
    {
        return $this->client->request('/registration', array_merge([
            'method' => 'POST',
            'body' => $registrationData,
        ], $options));
    }
}
