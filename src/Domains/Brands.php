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
     * @param array $params Filter parameters (id, subgroupId, groupId, page, limit, status, registrationType, currentTollFreeCarrier).
    *                      id is a brand lookup id. registrationType filters by "TEN_DLC", "TOLL_FREE", or "SHORT_CODE".
     *                      currentTollFreeCarrier (bool), with groupId or subgroupId: when true, return only the
     *                      toll-free brands a new toll-free campaign in that group can use.
     *                      Responses may include brandId: null for pending brands.
     *                      Each brand carries `carrierSuspensions` (carrier brand suspension rules; active while liftedAt is null).
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
     * tollFree.businessVerificationId names the business verification the brand uses (starts with BV). A
     * group whose toll-free brands need a business verification must send a VERIFIED one of the brand's group
     * (in a group that verifies each subgroup's business separately, of the brand's subgroup); any other group
     * gets 400 if it sends one. With it, the legal fields (companyName, street, city, state, postalCode,
     * country, firstName, lastName and tollFree's businessRegistrationType, taxId, countryCode and
     * taxIdIssuingCountry) are optional: the business verification's values replace any that are sent. It
     * can't be changed after creation.
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
     * List a group's business verifications, newest first, and whether its toll-free brands need one.
     *
     * The response data holds groupId, required (whether the group's new toll-free brands need a business
     * verification) and businessVerifications, newest first. Each has businessVerificationId, groupId,
     * status (SUBMITTING, IN_REVIEW, VERIFIED or NEEDS_CHANGES), details, submittedAt, reviewedAt and
     * rejectionReason (why it needs changes, or null).
     *
     * Some groups verify each subgroup's business separately: a platform whose subgroups are separate
     * businesses, set up by Signal House. For those groups $subgroupId is required (400 without it), and
     * the response data and each business verification also hold subgroupId, the subgroup whose business
     * it describes. Any other group's business verifications cover all its subgroups, so a $subgroupId is
     * only checked to be in the group. A subgroup outside the group answers 404.
     *
     * @param string $groupId The ID of the group whose business verifications to list
     * @param string|null $subgroupId The subgroup whose business verifications to list; required for a group
     *     that verifies each subgroup's business separately. Sent as a query parameter only when given
     * @param array $options Additional request options
     * @return array The response from the server
     * @throws \SignalHouse\SDK\Exceptions\ValidationException If groupId is missing
     */
    public function getBusinessVerifications(string $groupId, ?string $subgroupId = null, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $safeGroupId = rawurlencode($groupId);
        $queryString = $this->client->getQueryString(['subgroupId' => $subgroupId]);
        return $this->client->request("/brand/business-verification/{$safeGroupId}{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get one of a group's business verifications by its ID.
     *
     * The response data holds the business verification, as getBusinessVerifications lists it, plus
     * required. A business verification that isn't one of the group's answers 404.
     *
     * @param string $groupId The ID of the group the business verification belongs to
     * @param string $businessVerificationId The ID of the business verification to get (starts with BV)
     * @param array $options Additional request options
     * @return array The response from the server
     * @throws \SignalHouse\SDK\Exceptions\ValidationException If groupId or businessVerificationId is missing
     */
    public function getBusinessVerification(string $groupId, string $businessVerificationId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId, 'businessVerificationId' => $businessVerificationId]);
        $safeGroupId = rawurlencode($groupId);
        $safeBusinessVerificationId = rawurlencode($businessVerificationId);
        return $this->client->request("/brand/business-verification/{$safeGroupId}/{$safeBusinessVerificationId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create and submit a new business verification. Review usually takes 1–2 business days; once it is
     * VERIFIED, a new toll-free brand uses it by sending its ID as tollFree.businessVerificationId (see
     * createTollFreeBrand).
     *
     * $details keys: businessName, businessType, industry, registrationType, registrationNumber, website,
     * street, optional street2, city, state (2-letter state or province code), postalCode, country ('US'
     * or 'CA') and representative (firstName, lastName, email, phone, jobTitle, jobLevel).
     * representative.phone must be E.164 with an optional leading '+' and no spaces, parentheses or
     * hyphens (e.g. '+17025550100').
     *
     * A group that verifies each subgroup's business separately (see getBusinessVerifications) must pass
     * $subgroupId (400 without it), and only that subgroup's toll-free brands can use the business
     * verification. A given $subgroupId must be in the group (404 otherwise) and active (400 otherwise). A
     * group whose toll-free brands don't need a business verification answers 400.
     *
     * @param string $groupId The ID of the group the business verification is for (the caller's active group)
     * @param array $details The business verification details (see JS SDK BusinessVerificationDetails for fields)
     * @param string|null $subgroupId The subgroup whose business it verifies; required for a group that
     *     verifies each subgroup's business separately. Sent as a query parameter only when given
     * @param array $options Additional request options
     * @return array The response from the server: a 202 with the new business verification, now SUBMITTING
     * @throws \SignalHouse\SDK\Exceptions\ValidationException If groupId or details is missing
     */
    public function createBusinessVerification(string $groupId, array $details, ?string $subgroupId = null, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId, 'details' => $details]);
        $safeGroupId = rawurlencode($groupId);
        $queryString = $this->client->getQueryString(['subgroupId' => $subgroupId]);
        return $this->client->request("/brand/business-verification/{$safeGroupId}{$queryString}", array_merge([
            'method' => 'POST',
            'body' => $details,
        ], $options));
    }

    /**
     * Correct and resubmit a business verification. Allowed only while its status is NEEDS_CHANGES (400
     * otherwise). Review usually takes 1–2 business days; the toll-free brands that use it stay pending
     * until it is verified. A business verification that isn't one of the group's answers 404.
     *
     * @param string $groupId The ID of the group the business verification belongs to (the caller's active group)
     * @param string $businessVerificationId The ID of the business verification to update (starts with BV)
     * @param array $details The corrected business verification details, with the same keys as createBusinessVerification
     * @param array $options Additional request options
     * @return array The response from the server: a 202 with the business verification, now SUBMITTING
     * @throws \SignalHouse\SDK\Exceptions\ValidationException If groupId, businessVerificationId or details is missing
     */
    public function updateBusinessVerification(string $groupId, string $businessVerificationId, array $details, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId, 'businessVerificationId' => $businessVerificationId, 'details' => $details]);
        $safeGroupId = rawurlencode($groupId);
        $safeBusinessVerificationId = rawurlencode($businessVerificationId);
        return $this->client->request("/brand/business-verification/{$safeGroupId}/{$safeBusinessVerificationId}", array_merge([
            'method' => 'PUT',
            'body' => $details,
        ], $options));
    }

    /**
     * Get a group's toll-free ISV account: its own top-level account at the toll-free provider, which
     * Signal House staff register for a platform whose subgroups are separate businesses.
     * signalhouse_api, signalhouse_admin or signalhouse_user only.
     *
     * The response data holds groupId, accountSid, apiKeySid, primaryProfileSid (the account's approved
     * primary business profile), createdAt and updatedAt, or is null when the group has none. The API key
     * secret is never returned. A group that doesn't exist or isn't active answers 404.
     *
     * @param string $groupId The ID of the group whose ISV account to get
     * @param array $options Additional request options
     * @return array The response from the server
     * @throws \SignalHouse\SDK\Exceptions\ValidationException If groupId is missing
     */
    public function getTollFreeIsvAccount(string $groupId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $safeGroupId = rawurlencode($groupId);
        return $this->client->request("/brand/toll-free-isv-account/{$safeGroupId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Register a group's toll-free ISV account, or replace the API key of the one already registered.
     * signalhouse_admin only.
     *
     * The toll-free provider checks the account and key before they are stored, and finds the account's
     * approved primary business profile itself; a refused account or key answers 400 with the reason.
     * Register it before setting the group's toll-free provider: once the group has records with that
     * provider, only the API key of the registered account can be replaced.
     *
     * @param string $groupId The ID of the group whose ISV account to register
     * @param string $accountSid The ISV's own top-level account SID (AC followed by 32 lowercase hex characters)
     * @param string $apiKeySid A Main API key SID in that account (SK followed by 32 lowercase hex characters)
     * @param string $apiKeySecret The API key's secret (1 to 128 characters); stored encrypted and never returned
     * @param array $options Additional request options
     * @return array The response from the server: the stored ISV account, the same shape as
     *               getTollFreeIsvAccount
     * @throws \SignalHouse\SDK\Exceptions\ValidationException If groupId, accountSid, apiKeySid or apiKeySecret is missing
     */
    public function setTollFreeIsvAccount(
        string $groupId,
        string $accountSid,
        string $apiKeySid,
        string $apiKeySecret,
        array $options = []
    ): array {
        $this->client->require([
            'groupId' => $groupId,
            'accountSid' => $accountSid,
            'apiKeySid' => $apiKeySid,
            'apiKeySecret' => $apiKeySecret,
        ]);
        $safeGroupId = rawurlencode($groupId);
        return $this->client->request("/brand/toll-free-isv-account/{$safeGroupId}", array_merge([
            'method' => 'PUT',
            'body' => ['accountSid' => $accountSid, 'apiKeySid' => $apiKeySid, 'apiKeySecret' => $apiKeySecret],
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
}
