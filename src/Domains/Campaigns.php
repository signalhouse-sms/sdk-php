<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Campaigns
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
                 * Approve a campaign that is pending approval
                 */
                public function approveCampaign(string $campaignId, array $options = []): array
                {
                    $this->client->require(['campaignId' => $campaignId]);
                    $safeCampaignId = rawurlencode($campaignId);
                    return $this->client->request("/campaign/approve/{$safeCampaignId}", array_merge([
                        'method' => 'POST',
                    ], $options));
                }

                /**
                 * Reject a campaign that is pending approval
                 *
                 * @param string $campaignId The ID of the campaign to reject
                 * @param array $options Additional request options (body should include ['rejectionReason' => '...'])
                 * @return array The response from the server
                 */
                public function rejectCampaign(string $campaignId, array $options = []): array
                {
                    $this->client->require(['campaignId' => $campaignId]);
                    $safeCampaignId = rawurlencode($campaignId);
                    return $this->client->request("/campaign/reject/{$safeCampaignId}", array_merge([
                        'method' => 'POST',
                    ], $options));
                }

                /**
                 * Transition a Short Code campaign's review status. A customer-visible
                 * reason is required for both rejection states ("REJECTED" = Signal House Rejected,
                 * "DCA_REJECTED" = Rejected). Selecting "PENDING_DCA_APPROVAL" runs the guarded
                 * carrier-review transition; fulfillment remains a separate staff operation.
                 *
                 * @param string $campaignId The ID of the campaign to transition
                 * @param string $status The target status: "PENDING_REVIEW", "REJECTED",
                 *                      "PENDING_DCA_APPROVAL", "DCA_REJECTED", or "ACTIVE".
                 *                      PENDING_DCA_APPROVAL requires a Signal House-approved brand, issued code,
                 *                      and captured opt-in proof.
                 * @param string|null $rejectionReason Required and nonblank for "REJECTED"/"DCA_REJECTED" (max 1024 characters)
                 * @param array $options Additional request options
                 * @return array The updated campaign
                 */
                public function updateShortCodeCampaignStatus(string $campaignId, string $status, ?string $rejectionReason = null, array $options = []): array
                {
                    $this->client->require(['campaignId' => $campaignId, 'status' => $status]);
                    $safeCampaignId = rawurlencode($campaignId);
                    return $this->client->request("/campaign/short-code/{$safeCampaignId}/status", array_merge([
                        'method' => 'PUT',
                        'body' => ['status' => $status, 'rejectionReason' => $rejectionReason],
                    ], $options));
                }

                /** Fulfill a campaign-bound Signal House Short Code request. A YYYY-MM-DD lease end date expires at 23:59:59 UTC. */
                public function fulfillShortCodeCampaign(string $campaignId, string $actualCode, string $leaseEndDate, ?string $internalNotes = null, array $options = []): array
                {
                    $this->client->require(['campaignId' => $campaignId, 'actualCode' => $actualCode, 'leaseEndDate' => $leaseEndDate]);
                    $safeCampaignId = rawurlencode($campaignId);
                    return $this->client->request("/campaign/short-code/{$safeCampaignId}/fulfill", array_merge([
                        'method' => 'POST', 'body' => ['actualCode' => $actualCode, 'leaseEndDate' => $leaseEndDate, 'internalNotes' => $internalNotes],
                    ], $options));
                }

                /** Submit an internally approved Short Code campaign to carrier review. */
                public function submitShortCodeCampaignToCarrier(string $campaignId, array $options = []): array
                {
                    $this->client->require(['campaignId' => $campaignId]);
                    $safeCampaignId = rawurlencode($campaignId);
                    return $this->client->request("/campaign/short-code/{$safeCampaignId}/submit-to-carrier", array_merge(['method' => 'POST'], $options));
                }

                /** Confirm Registry offboarding and complete a pending Short Code cancellation. */
                public function completeShortCodeCancellation(string $campaignId, string $requestId, ?string $note = null, array $options = []): array
                {
                    $this->client->require(['campaignId' => $campaignId, 'requestId' => $requestId]);
                    $safeCampaignId = rawurlencode($campaignId);
                    return $this->client->request("/campaign/short-code/{$safeCampaignId}/cancellation/complete", array_merge([
                        'method' => 'POST', 'body' => ['requestId' => $requestId, 'note' => $note],
                    ], $options));
                }

                /** Dismiss a pending Short Code cancellation and restore its prior lifecycle state. */
                public function dismissShortCodeCancellation(string $campaignId, string $requestId, ?string $note = null, array $options = []): array
                {
                    $this->client->require(['campaignId' => $campaignId, 'requestId' => $requestId]);
                    $safeCampaignId = rawurlencode($campaignId);
                    return $this->client->request("/campaign/short-code/{$safeCampaignId}/cancellation/dismiss", array_merge([
                        'method' => 'POST', 'body' => ['requestId' => $requestId, 'note' => $note],
                    ], $options));
                }

                /** Read expired Short Code leases awaiting manual Registry offboarding. */
                public function getExpiredShortCodeLeases(?int $page = null, ?int $limit = null, array $options = []): array
                {
                    $queryString = $this->client->getQueryString(['page' => $page, 'limit' => $limit]);
                    return $this->client->request("/campaign/short-code/expired-leases{$queryString}", array_merge(['method' => 'GET'], $options));
                }

                /** Confirm Registry offboarding and release an expired Short Code. */
                public function releaseExpiredShortCodeLease(string $campaignId, ?string $note = null, array $options = []): array
                {
                    $this->client->require(['campaignId' => $campaignId]);
                    $safeCampaignId = rawurlencode($campaignId);
                    return $this->client->request("/campaign/short-code/{$safeCampaignId}/expired-lease/release", array_merge(['method' => 'POST', 'body' => ['note' => $note]], $options));
                }
            };
        }
    }

    /**
     * Get a list of campaigns with optional filters
     *
     * @param array $params Filter parameters (id, brandId, subgroupId, groupId, page, limit, status,
    *                       registrationType). registrationType filters by "TEN_DLC", "TOLL_FREE", or "SHORT_CODE".
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
    public function getCampaigns(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/campaign{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get aggregated campaign health (7-day and 30-day windows) for a campaign.
     *
     * @param string $campaignId The campaign identifier
     * @param bool|null $includeNumbers When true, include per-number health entries (default: omitted)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getCampaignHealth(string $campaignId, ?bool $includeNumbers = null, array $options = []): array
    {
        $queryString = $this->client->getQueryString([
            'campaignId' => $campaignId,
            'includeNumbers' => $includeNumbers,
        ]);

        return $this->client->request("/campaign/health{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get the carrier throughput ceilings a campaign is currently sending under: the AT&T
     * per-minute limit (per campaign, held 10% under the published ceiling) and the T-Mobile daily
     * cap (per brand), each with its current tier and the next rung on the ladder, plus today's
     * T-Mobile usage. Resolved from the same brand status / brand score rules the send path
     * enforces with.
     *
     * @param string $campaignId The campaign identifier
     * @param array $options Additional request options
     * @return array The response from the server: campaignId, brandId, usecase, brand {status,
     *     brandScore, entityType}, att {tier, scope, unit, sms {limit}, mms {limit}, marginPercent,
     *     nextTier}, tmobile {tier, scope, unit, dailyLimit, thresholds, nextTier, usedToday {date,
     *     segments, resetsAt} | null}. A null tier/limit means the carrier ladder does not apply
     *     (unverified brand, GOVERNMENT entity, PUBLIC_SAFETY_RESTRICTED usecase); a null nextTier
     *     means the top rung or a usecase-capped (LOW_VOLUME) campaign.
     */
    public function getCampaignThroughputLimits(string $campaignId, array $options = []): array
    {
        $this->client->require(['campaignId' => $campaignId]);
        $queryString = $this->client->getQueryString(['campaignId' => $campaignId]);

        return $this->client->request("/campaign/throughput-limits{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create a new campaign
     *
     * @param array $campaignData The campaign data (see JS SDK CreateCampaignData for fields). Optional
     *                            'sampleMediaIds' (10DLC only): up to 5 mediaId strings from
     *                            uploadSampleMedia(), attached to the TCR campaign as sample MMS media
     *                            for carrier review.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createCampaign(array $campaignData, array $options = []): array
    {
        $this->client->require(['campaignData' => $campaignData]);
        return $this->client->request('/campaign', array_merge([
            'method' => 'POST',
            'body' => $campaignData,
        ], $options));
    }

    /**
     * Create a new Toll-Free (TFN) campaign and submit it for Signal House review
     *
     * registrationType is forced to TOLL_FREE server-side; TFN-specific fields live under
     * $campaignData['tollFree'] (useCase, messageVolume, programSummary, exampleMessage,
     * customerCareEmail, optInImageURLs, optional optIns / multiNumberReason / useCases / channels / landingId). phoneNumbers must
     * list 1 up to the brand's tollFree.maxNumbersPerCampaign Toll-Free numbers (at most 5), on the brand's toll-free
     * carrier, or the campaign is refused. They are locked to the campaign once assigned.
     *
     * @param array $campaignData The toll-free campaign data (see JS SDK CreateTollFreeCampaignData for fields)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createTollFreeCampaign(array $campaignData, array $options = []): array
    {
        $this->client->require(['campaignData' => $campaignData]);
        return $this->client->request('/campaign/toll-free', array_merge([
            'method' => 'POST',
            'body' => $campaignData,
        ], $options));
    }

    /**
     * Create a new Short Code campaign and submit it for Signal House review.
     *
     * Requires an approved (VERIFIED) Short Code brand. Upload the opt-in proof screenshot first with
     * `uploadOptInImage()` and pass the returned URL as `shortCode.screenshotUrl` (required; external URLs
     * are rejected). `shortCode.optInUrl` (required) is the public HTTPS link to the live opt-in experience. Request or
     * register the campaign's Short Code separately through `numbers->requestShortCodeAcquisition()` after creation.
     *
     * @param array $campaignData The Short Code campaign data: brandId, privacyPolicyLink,
     *                            termsAndConditionsLink, optinMessage, optoutMessage, helpMessage,
    *                            sample1-3, tag, and a 'shortCode' sub-array
     *                            (useCases, optInMethods, optInMethodDescriptions,
     *                            messageFrequency, pricingTier, adultContent, doubleOptInMessage,
     *                            programSummary, optInConfirmationMessage, screenshotUrl, optInUrl).
     * @param array $options Additional request options
     * @return array The response from the server containing the created campaign
     */
    public function createShortCodeCampaign(array $campaignData, array $options = []): array
    {
        $this->client->require(['campaignData' => $campaignData]);

        $multipart = [
            [
                'name' => 'campaignData',
                'contents' => json_encode($campaignData),
            ],
        ];
        return $this->multipartClient->request('/campaign/short-code', array_merge([
            'method' => 'POST',
            'multipart' => $multipart,
        ], $options));
    }

    /**
     * Update a Short Code campaign's editable fields. Only permitted while the
     * campaign is in Signal House Review or Signal House Rejected status; the number source
     * cannot be changed once submitted.
     *
     * @param string $campaignId The ID of the campaign to update
     * @param array $updateData The fields to update (top-level campaign fields plus an optional
     *                          'shortCode' array of editable Short Code fields)
     * @param array $options Additional request options
     * @return array The response from the server containing the updated campaign
     */
    public function updateShortCodeCampaign(string $campaignId, array $updateData, array $options = []): array
    {
        $this->client->require(['campaignId' => $campaignId]);
        $safeCampaignId = rawurlencode($campaignId);
        return $this->client->request("/campaign/short-code/{$safeCampaignId}", array_merge([
            'method' => 'PUT',
            'body' => $updateData,
        ], $options));
    }

    /**
    * Cancel a Short Code campaign. Issued customer leases return PENDING_DELETE until Signal House
    * confirms Registry offboarding; safe-to-dispose and staff cancellations return EXPIRED immediately.
     *
     * @param string $campaignId The ID of the campaign to cancel
     * @param array $options Additional request options
     * @return array The response from the server containing the cancelled campaign
     */
    public function cancelShortCodeCampaign(string $campaignId, array $options = []): array
    {
        $this->client->require(['campaignId' => $campaignId]);
        $safeCampaignId = rawurlencode($campaignId);
        return $this->client->request("/campaign/short-code/{$safeCampaignId}/cancel", array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
    * Download a private Short Code external lease receipt. Opt-in screenshots are public at the
    * shortCode.screenshotUrl returned on the campaign.
     *
    * @param string $artifactId The receipt identifier from numberSource.externalLease.receiptArtifactId
     * @param array $options Additional request options
     * @return array The response from the server containing the raw file bytes
     */
    public function readCampaignArtifact(string $artifactId, array $options = []): array
    {
        $this->client->require(['artifactId' => $artifactId]);
        $safeArtifactId = rawurlencode($artifactId);
        return $this->client->request("/campaign/short-code/artifact/{$safeArtifactId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Upload an opt-in proof image (multipart/form-data), returning the hosted image's id and URL
     * suitable for a Toll-Free campaign's optInImageURLs.
     *
     * @param string|resource $file The image file path or file resource
     * @param array $options Additional request options
     * @return array The response from the server containing the hosted image's id and url
     */
    public function uploadOptInImage(mixed $file, array $options = []): array
    {
        $this->client->require(['file' => $file]);

        $multipart = [
            [
                'name' => 'file',
                'contents' => is_string($file) ? fopen($file, 'r') : $file,
            ],
        ];

        return $this->multipartClient->request('/campaign/opt-in-image', array_merge([
            'method' => 'POST',
            'multipart' => $multipart,
        ], $options));
    }

    /**
     * Upload a sample media file (JPEG, PNG, GIF, MP4 or MP3, up to 5MB) for a 10DLC campaign.
     * Reference the returned mediaId in sampleMediaIds on campaign create or update; it is
     * attached to the TCR campaign for carrier review.
     *
     * @param string|resource $file The media file path or file resource
     * @param array $options Additional request options
     * @param string|null $groupId The group the file belongs to. Required with a staff (signalhouse_*)
     *     key, which has no active group; otherwise optional and must be your own group
     * @return array The response from the server containing the uploaded file's mediaId, fileName,
     *     mimeType and size
     */
    public function uploadSampleMedia(mixed $file, array $options = [], ?string $groupId = null): array
    {
        $this->client->require(['file' => $file]);

        $multipart = [];
        if ($groupId !== null && $groupId !== '') {
            $multipart[] = ['name' => 'groupId', 'contents' => $groupId];
        }
        $multipart[] = [
            'name' => 'file',
            'contents' => is_string($file) ? fopen($file, 'r') : $file,
        ];

        return $this->multipartClient->request('/campaign/sample-media', array_merge([
            'method' => 'POST',
            'multipart' => $multipart,
        ], $options));
    }

    /**
     * Download a campaign sample media file your group owns.
     *
     * @param string $mediaId The mediaId returned by uploadSampleMedia()
     * @param array $options Additional request options
     * @return array The response from the server containing the raw file bytes
     */
    public function readSampleMedia(string $mediaId, array $options = []): array
    {
        $this->client->require(['mediaId' => $mediaId]);
        $safeMediaId = rawurlencode($mediaId);
        return $this->client->request("/campaign/sample-media/{$safeMediaId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Auto-capture an opt-in proof image from a brand's generated landing page and host it
     * on-platform, returning the hosted image's id and URL suitable for a Toll-Free campaign's
     * optInImageURLs.
     *
     * @param string $brandId The ID of the brand whose generated landing page should be captured
     * @param string|null $landingId The specific page to capture. Required for Toll-Free brands, which have one page per campaign — omitted, the request is refused rather than guessing. A 10DLC brand has a single page, so it may be omitted there.
     * @param array $options Additional request options
     * @return array The response from the server containing the hosted image's id and url
     */
    public function captureOptInImageFromLanding(string $brandId, ?string $landingId = null, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $body = ['brandId' => $brandId];
        if ($landingId !== null) {
            $body['landingId'] = $landingId;
        }
        return $this->client->request('/campaign/opt-in-image/from-landing', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Update an existing campaign
     *
     * @param string $campaignId The ID of the campaign
     * @param array $campaignData The data to update. For a Toll-Free campaign, pass the editable
     *                            Toll-Free fields under a 'tollFree' sub-array (useCase,
     *                            messageVolume, programSummary, exampleMessage, customerCareEmail,
     *                            optInImageURLs, optIns, multiNumberReason, useCases, channels, landingId); phoneNumbers cannot be
     *                            changed — Toll-Free numbers are locked to their campaign. For a
     *                            10DLC campaign, an optional 'sampleMediaIds' list (up to 5 mediaId
     *                            strings from uploadSampleMedia()) sets the sample MMS media for
     *                            carrier review.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateCampaign(string $campaignId, array $campaignData, array $options = []): array
    {
        $this->client->require(['campaignId' => $campaignId]);
        $safeCampaignId = rawurlencode($campaignId);
        return $this->client->request("/campaign/{$safeCampaignId}", array_merge([
            'method' => 'PUT',
            'body' => $campaignData,
        ], $options));
    }

    /**
     * Delete a campaign (mark as EXPIRED)
     *
     * @param string $campaignId The ID of the campaign
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteCampaign(string $campaignId, array $options = []): array
    {
        $this->client->require(['campaignId' => $campaignId]);
        $safeCampaignId = rawurlencode($campaignId);
        return $this->client->request("/campaign/{$safeCampaignId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Appeal a DCA rejection for a campaign.
     *
     * @param string $campaignId The ID of the campaign
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function appealDcaRejection(string $campaignId, array $options = []): array
    {
        $this->client->require(['campaignId' => $campaignId]);
        $safeCampaignId = rawurlencode($campaignId);
        return $this->client->request("/campaign/appealDcaRejection/{$safeCampaignId}", array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Nudge a connectivity partner to prioritize review of a campaign.
     *
     * @param string $campaignId The ID of the campaign
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function nudgeDcaForCampaign(string $campaignId, array $options = []): array
    {
        $this->client->require(['campaignId' => $campaignId]);
        $safeCampaignId = rawurlencode($campaignId);
        return $this->client->request("/campaign/nudge/{$safeCampaignId}", array_merge([
            'method' => 'POST',
        ], $options));
    }
}
