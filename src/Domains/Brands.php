<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

/**
 * 10DLC Brand registration operations.
 *
 * Brand lookup id: carrier Brand ID (B… or TFNB…), record _id, or internal reference UUID.
 * Read/create responses return brandId: null for pending brands — poll GET /brand?id=<record _id>.
 */
class Brands
{
    private HttpClient $client;
    private HttpClient $multipartClient;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, HttpClient $multipartClient, bool $enableAdmin)
    {
        $this->client = $client;
        $this->multipartClient = $multipartClient;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Get a list of brands with optional filters
     *
     * A toll-free brand's tollFree holds reviewsBusinessOnSubmit: whether the brand is reviewed when it's
     * submitted and keeps its business details from then on. A brand reviewed on submit also holds
     * businessStructure, industry, contactEmail, contactJobTitle, contactJobLevel and rejectionReason (why the
     * brand was rejected, in plain English, or null) in its tollFree.
     *
     * @param array $params Filter parameters (id, subgroupId, groupId, page, limit, status, registrationType, currentTollFreeCarrier).
    *                      id is a brand lookup id. registrationType filters by "TEN_DLC", "TOLL_FREE", or "SHORT_CODE".
     *                      currentTollFreeCarrier (bool), with groupId or subgroupId: when true, return only the
     *                      toll-free brands a new toll-free campaign in that group can use.
     *                      Responses may include brandId: null for pending brands.
     *                      Each brand carries `carrierSuspensions` (carrier brand suspension rules; active while liftedAt is null).
     *                      Each brand also carries `cnamName` (its caller name for numbers, or null; see getCallerName).
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getBrands(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/brand{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get external vetting information for a brand
     *
     * @param string $brandId Brand lookup id (carrier Brand ID, record _id, or internal reference)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getExternalVetting(string $brandId, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/brand/externalvetting/{$safeBrandId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create a new brand
     *
     * @param array $brandData The brand data (see JS SDK CreateBrandData for fields)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createBrand(array $brandData, array $options = []): array
    {
        $this->client->require(['brandData' => $brandData]);
        return $this->client->request('/brand', array_merge([
            'method' => 'POST',
            'body' => $brandData,
        ], $options));
    }

    /**
     * Create a new Toll-Free (TFN) brand
     *
     * registrationType is forced to TOLL_FREE server-side; TFN-specific fields live under
     * $brandData['tollFree'] (businessRegistrationType, legalEntityType, taxId, countryCode,
     * supportPhone, and optional taxIdIssuingCountry / businessDBA / businessWebsite).
     * supportPhone must be E.164 with an optional leading '+' and no spaces, parentheses or
     * hyphens (e.g. '17025550100').
     *
     * A group whose toll-free brands are reviewed on submit (see getTollFreeRequirements) must also send five
     * business details under $brandData['tollFree']: businessStructure (CO_OPERATIVE, CORPORATION,
     * LIMITED_LIABILITY_CORPORATION, NON_PROFIT_CORPORATION or PARTNERSHIP), industry (AGRICULTURE,
     * AUTOMOTIVE, BANKING, CONSTRUCTION, CONSUMER, EDUCATION, ELECTRONICS, ENGINEERING, ENERGY,
     * FAST_MOVING_CONSUMER_GOODS, FINANCIAL, FINTECH, FOOD_AND_BEVERAGE, GOVERNMENT, HEALTHCARE, HOSPITALITY,
     * INSURANCE, JEWELRY, LEGAL, MANUFACTURING, MEDIA, NOT_FOR_PROFIT, OIL_AND_GAS, ONLINE,
     * PROFESSIONAL_SERVICES, RAW_MATERIALS, REAL_ESTATE, RELIGION, RETAIL, TECHNOLOGY, TELECOMMUNICATIONS,
     * TRANSPORTATION or TRAVEL), contactEmail (a valid email, at most 100 characters), contactJobTitle (at
     * most 100 characters) and contactJobLevel (DIRECTOR, GM, VP, CEO, CFO, GENERAL_COUNSEL or OTHER). Its
     * tollFree.countryCode must then be 'US' or 'CA' and its state a 2-letter state or province code. Any
     * other group gets 400 if it sends one of the five.
     *
     * Such a brand stays DCA_PENDING until it is reviewed, usually 1–2 business days, then becomes DCA_ACTIVE,
     * or DCA_REJECTED with tollFree.rejectionReason saying why; a rejected brand is fixed by creating it again
     * with the corrections. Its business details (companyName, street, city, state, postalCode, country,
     * firstName, lastName, tollFree's businessRegistrationType, taxId, countryCode and taxIdIssuingCountry,
     * and the five above) can't be changed after it is submitted.
     *
     * @param array $brandData The toll-free brand data (see JS SDK CreateTollFreeBrandData for fields)
     * @param array $options Additional request options
     * @return array The response from the server. For Toll-Free, brandId is a TFNB-prefixed id when
     *               assigned; otherwise null — poll by _id.
     */
    public function createTollFreeBrand(array $brandData, array $options = []): array
    {
        $this->client->require(['brandData' => $brandData]);
        return $this->client->request('/brand/toll-free', array_merge([
            'method' => 'POST',
            'body' => $brandData,
        ], $options));
    }

    /**
     * Get the toll-free brand requirements for a specific group
     *
     * The response data holds groupId and reviewsBusinessOnSubmit. When reviewsBusinessOnSubmit is true, the
     * group's new toll-free brands are reviewed when they're submitted: createTollFreeBrand needs the five
     * business details under tollFree (businessStructure, industry, contactEmail, contactJobTitle and
     * contactJobLevel), a 'US' or 'CA' tollFree.countryCode and a 2-letter state or province code; the brand
     * stays DCA_PENDING until it is reviewed; and its business details can't be changed after it is
     * submitted. The caller must be able to view the group (403 otherwise).
     *
     * @param string $groupId The ID of the group
     * @param array $options Additional request options
     * @return array The response from the server
     * @throws \SignalHouse\SDK\Exceptions\ValidationException If groupId is missing
     */
    public function getTollFreeRequirements(string $groupId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $safeGroupId = rawurlencode($groupId);
        return $this->client->request("/brand/toll-free-requirements/{$safeGroupId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create a local Short Code brand for manual Signal House review.
     * The route forces SHORT_CODE and returns a synchronous 201 PENDING_APPROVAL record with an
     * SCB-prefixed brandId.
     *
     * @param array $brandData Short Code payload with a shortCode sub-array.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createShortCodeBrand(array $brandData, array $options = []): array
    {
        $this->client->require(['brandData' => $brandData]);
        return $this->client->request('/brand/short-code', array_merge([
            'method' => 'POST',
            'body' => $brandData,
        ], $options));
    }

    /** Approve a pending Short Code brand. signalhouse_admin only. */
    public function approveShortCodeBrand(string $brandId, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/brand/approve/{$safeBrandId}", array_merge(['method' => 'POST'], $options));
    }

    /** Reject a pending Short Code brand with a required customer-visible reason. signalhouse_admin only. */
    public function rejectShortCodeBrand(string $brandId, string $internalRejectionReason, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId, 'internalRejectionReason' => $internalRejectionReason]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/brand/reject/{$safeBrandId}", array_merge([
            'method' => 'POST',
            'body' => ['internalRejectionReason' => $internalRejectionReason],
        ], $options));
    }

    /**
     * Transfer one or more brands to a different subgroup
     *
     * @param string $subgroupId The target subgroup ID
     * @param array $brandIds Brand lookup ids to transfer
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function transferBrand(string $subgroupId, array $brandIds, array $options = []): array
    {
        $this->client->require(['subgroupId' => $subgroupId, 'brandIds' => $brandIds]);
        $safeSubgroupId = rawurlencode($subgroupId);
        return $this->client->request("/brand/transfer/{$safeSubgroupId}", array_merge([
            'method' => 'POST',
            'body' => ['brandIds' => $brandIds],
        ], $options));
    }

    /**
     * Create external vetting for a brand
     *
     * @param string $brandId Brand lookup id (carrier Brand ID, record _id, or internal reference)
     * @param string $vettingProviderId The external vetting provider (AEGIS or WMC)
     * @param string $vettingClass The vetting class: STANDARD, ENHANCED, AUTHPLUS, or RCS (AEGIS accepts all four; WMC accepts STANDARD only)
     * @param array|null $additionalData Provider-specific additional data forwarded to TCR
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createExternalVetting(string $brandId, string $vettingProviderId, string $vettingClass, ?array $additionalData = null, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId, 'vettingProviderId' => $vettingProviderId, 'vettingClass' => $vettingClass]);
        $safeBrandId = rawurlencode($brandId);
        $body = ['vettingProviderId' => $vettingProviderId, 'vettingClass' => $vettingClass];
        if ($additionalData !== null) {
            $body['additionalData'] = $additionalData;
        }
        return $this->client->request("/brand/externalvetting/{$safeBrandId}", array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Import an existing external vetting record for a brand
     *
     * Unlike createExternalVetting (which orders a new, billable vetting), this attaches a vetting
     * the brand already completed directly with the provider, using the provider-issued vettingId
     * and vettingToken. It is synchronous and not billable.
     *
     * @param string $brandId Brand lookup id (carrier Brand ID, record _id, or internal reference)
     * @param string $vettingProviderId The external vetting provider (AEGIS, WMC, CV)
     * @param string $vettingId The provider-issued vetting / transaction ID to import
     * @param string|null $vettingToken The provider-issued vetting token (required by some providers, e.g. AEGIS)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function importExternalVetting(string $brandId, string $vettingProviderId, string $vettingId, ?string $vettingToken = null, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId, 'vettingProviderId' => $vettingProviderId, 'vettingId' => $vettingId]);
        $safeBrandId = rawurlencode($brandId);
        $body = ['vettingProviderId' => $vettingProviderId, 'vettingId' => $vettingId];
        if ($vettingToken !== null) {
            $body['vettingToken'] = $vettingToken;
        }
        return $this->client->request("/brand/externalvetting/import/{$safeBrandId}", array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Update a brand's information
     *
     * @param string $brandId Brand lookup id (carrier Brand ID, record _id, or internal reference)
     * @param array $brandData The data to update. For a Toll-Free brand, pass the editable Toll-Free
     *                         fields under a 'tollFree' sub-array (legalEntityType,
     *                         businessRegistrationType, taxId, countryCode, supportPhone,
     *                         taxIdIssuingCountry, businessDBA, businessWebsite); supportPhone must
     *                         be E.164 with an optional leading '+' and no spaces, parentheses or
     *                         hyphens (e.g. '17025550100'); subgroupId is immutable.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateBrand(string $brandId, array $brandData, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/brand/{$safeBrandId}", array_merge([
            'method' => 'PUT',
            'body' => $brandData,
        ], $options));
    }

    /**
     * Revet a brand that is in UNVERIFIED status
     *
     * @param string $brandId Brand lookup id (carrier Brand ID, record _id, or internal reference)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function revetBrand(string $brandId, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/brand/revet/{$safeBrandId}", array_merge([
            'method' => 'PUT',
        ], $options));
    }

    /**
     * Delete a brand (mark as DELETED)
     *
     * @param string $brandId Brand lookup id (carrier Brand ID, record _id, or internal reference)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteBrand(string $brandId, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/brand/{$safeBrandId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Get appeal history for a brand
     *
     * @param string $brandId Brand lookup id (carrier Brand ID, record _id, or internal reference)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAppealHistory(string $brandId, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/brand/appeal/{$safeBrandId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Submit an appeal for a brand
     *
     * @param string $brandId Brand lookup id (carrier Brand ID, record _id, or internal reference)
     * @param array $appealCategories Array of appeal category strings
     * @param string $explanation The appeal explanation
     * @param string|resource $file The appeal file path or file resource
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function submitAppeal(
        string $brandId,
        array $appealCategories,
        string $explanation,
        mixed $file,
        array $options = []
    ): array {
        $this->client->require([
            'brandId' => $brandId,
            'appealCategories' => $appealCategories,
            'explanation' => $explanation,
            'file' => $file,
        ]);
        $safeBrandId = rawurlencode($brandId);

        $multipart = [];

        $multipart[] = [
            'name' => 'file',
            'contents' => is_string($file) ? fopen($file, 'r') : $file,
        ];

        $multipart[] = ['name' => 'appealCategories', 'contents' => json_encode($appealCategories)];
        $multipart[] = ['name' => 'explanation', 'contents' => $explanation];

        return $this->multipartClient->request("/brand/appeal/{$safeBrandId}", array_merge([
            'method' => 'POST',
            'multipart' => $multipart,
        ], $options));
    }

    /**
     * Get a brand's caller name (CNAM) and the numbers it applies to: every number whose caller name comes
     * from this brand (its campaign numbers, numbers it was picked on, and campaign-less numbers of
     * subgroups that use it as their default caller-ID brand)
     *
     * Roles: api, admin, developer, billing, user
     *
     * @param string $brandId Brand lookup id (carrier Brand ID, record _id, or internal reference)
     * @param array $options Additional request options
     * @return array Response resolving to { brandId, companyName, cnamName, eligible, numbers: { total, items } }; items lists the first 100
     */
    public function getCallerName(string $brandId, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/brand/cnam/{$safeBrandId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Set a brand's caller name (CNAM). It replaces the caller name on every number whose caller name comes
     * from this brand, names set on a number included, and numbers that later come under the brand get it
     * too. The name may only remove characters from the brand's registered name and must be 1-15 letters,
     * digits, spaces, - or _; the brand must be verified. Numbers are updated at the carrier in the background.
     *
     * Roles: api, admin, developer
     *
     * @param string $brandId Brand lookup id (carrier Brand ID, record _id, or internal reference)
     * @param string $name The caller name to show
     * @param array $options Additional request options
     * @return array Response resolving to { brandId, companyName, cnamName, eligible, numbers: { total, items } }
     */
    public function setCallerName(string $brandId, string $name, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId, 'name' => $name]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/brand/cnam/{$safeBrandId}", array_merge([
            'method' => 'PUT',
            'body' => ['name' => $name],
        ], $options));
    }

    /**
     * Remove a brand's caller name (CNAM). Numbers that had it go back to the automatic name if their
     * subgroup has caller name auto-apply on, otherwise to no caller name. Names set on a number are kept.
     *
     * Roles: api, admin, developer
     *
     * @param string $brandId Brand lookup id (carrier Brand ID, record _id, or internal reference)
     * @param array $options Additional request options
     * @return array Response resolving to { brandId, companyName, cnamName, eligible, numbers: { total, items } }
     */
    public function deleteCallerName(string $brandId, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/brand/cnam/{$safeBrandId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }
}
