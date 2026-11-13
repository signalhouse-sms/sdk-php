<?php

namespace SignalHouse\SDK\Domains\Rcs;

use SignalHouse\SDK\Exceptions\ValidationException;
use SignalHouse\SDK\HttpClient;

/**
 * RCS agents under /rcs/agent. An agent links one brand and one campaign, of any registration type: the campaign's
 * numbers are the SMS fallback, and the brand and campaign supply the company identity and consent text for the carrier
 * launch. One brand or campaign can serve many agents. An agent is a registration (type RCS_AGENT, id RCSA...), so the
 * registrations methods read and edit the same record. 404 while the environment's RCS switch is off; 400 while the
 * group's rcsEnabled flag is off.
 *
 * Body fields: agent (the profile: name (max 40), description (max 100), color (hex, default #6868ab), logoUrl,
 * heroUrl, phone (E.164), website, email, phoneLabel, websiteLabel, emailLabel (captions, max 40; submit defaults
 * them to Call, Website and Email), privacyPolicyUrl, termsUrl), consent (optInDetails, optInUrl, optInResponse,
 * optOutDetails, optOutResponse, helpResponse, campaignDescription, messageExamples (1-6), estimatedMonthlyVolume
 * (UP_TO_10K|10K_100K|100K_500K|500K_1M|1M_PLUS), launchVideoUrl; every field is required before the launch request),
 * contact (firstName, lastName, role, email, mobile: the individual at the brand the carrier partner's reviewers may
 * write to; required in full before the launch request), useCase
 * (OTP|TRANSACTIONAL|PROMOTIONAL|MULTI_USE), billingCategory (NON_CONVERSATIONAL (default)|CONVERSATIONAL),
 * defaultFallback (['mode' => auto|rcs_only|sms_only|none, 'channel' => auto|sms|mms, 'from' => a number on the
 * linked campaign]), excludedCarriers ([] or ['T_MOBILE'], the carrier partner's carrier name), testers (up to
 * 200 numbers) and autofill (where each prefilled field came from, by path such as 'consent.optInDetails':
 * ['source' => brand|campaign|landing, 'sourceId', 'at', 'edited']; the API adds its own for the fields it fills).
 */
class Agents
{
    /** The body keys an agent create or update may carry. */
    private const BODY_KEYS = [
        'brandId', 'campaignId', 'agent', 'consent', 'contact', 'useCase', 'billingCategory', 'defaultFallback', 'excludedCarriers', 'testers', 'autofill',
    ];

    /** Body keys whose value is a JSON object, so an empty PHP array must be sent as {} rather than []. */
    private const OBJECT_KEYS = ['agent', 'consent', 'contact', 'defaultFallback', 'autofill'];

    private HttpClient $client;
    private HttpClient $multipartClient;

    public function __construct(HttpClient $client, HttpClient $multipartClient)
    {
        $this->client = $client;
        $this->multipartClient = $multipartClient;
    }

    /**
     * Create an agent draft on a brand, optionally with its campaign. The agent's subgroup is the brand's.
     * Blank consent fields fill from the linked campaign. Pass $options['idempotencyKey'] to make a retry safe.
     *
     * @param array $params brandId (carrier brand ID, provisional UUID or _id), campaignId? (a campaign ID or _id of
     *     that brand; required before the launch request), agent?, consent?, contact?, useCase? (required before
     *     submit), billingCategory?, defaultFallback?, excludedCarriers?, testers?, autofill?. Unset or null keys are omitted.
     * @param array $options Additional request options
     * @return array The response whose data is the agent (status DRAFT, 201)
     * @throws ValidationException If brandId is missing
     */
    public function create(array $params, array $options = []): array
    {
        $this->client->require(['brandId' => $params['brandId'] ?? null]);
        $body = [];
        foreach (self::BODY_KEYS as $key) {
            if (isset($params[$key])) {
                $body[$key] = self::bodyValue($key, $params[$key]);
            }
        }
        return $this->client->request('/rcs/agent', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * List agents, newest first
     *
     * @param array $params groupId?, subgroupId?, status? (DRAFT, PENDING_REVIEW, REJECTED, AGENT_CREATING,
     *     ARCHIVE_PENDING or ARCHIVED; one value or an array, sent as repeated keys), brandId? (agents linked to this brand),
     *     campaignId? (agents linked to this campaign), page? (default 1), limit? (default 25, max 100)
     * @param array $options Additional request options
     * @return array The response whose data is ['data' => agents, 'total', 'page', 'limit']
     */
    public function list(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString([
            'groupId' => $params['groupId'] ?? null,
            'subgroupId' => $params['subgroupId'] ?? null,
            'status' => $params['status'] ?? null,
            'brandId' => $params['brandId'] ?? null,
            'campaignId' => $params['campaignId'] ?? null,
            'page' => $params['page'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
        return $this->client->request("/rcs/agent{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get one agent with the live state of its brand and campaign links
     *
     * @param string $agentId The agent ID (e.g. RCSA7K2M1QZ8RP)
     * @param array $options Additional request options
     * @return array The response whose data is the agent; googleAgentId is Google's id for it, null until Google has
     *     created the agent
     * @throws ValidationException If agentId is missing
     */
    public function get(string $agentId, array $options = []): array
    {
        return $this->client->request($this->agentPath($agentId), array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Edit an agent while it may change: a draft, a Signal House rejection, a request for changes, or an agent in
     * testing (the change is pushed to the carrier partner). agent, consent and contact merge field by field; every
     * other key replaces. A key set to null is sent as null (campaignId => null unlinks the campaign); an absent key
     * keeps its value. 409 in any other status; brandId is locked once the agent exists at the partner and
     * billingCategory once a launch was requested. After a carrier rejected a launch other carriers took
     * (PARTIALLY_LAUNCHED with a REJECTED carrier), only consent and contact may change, and nothing is pushed: send them
     * with requestCarrierUpdate. autofill replaces the prefill markers; absent, the stored ones are kept and a changed
     * field's marker turns edited.
     *
     * @param string $agentId The agent ID
     * @param array $params brandId? (relink to another brand of the same subgroup), campaignId?, agent?,
     *     consent?, contact?, useCase?, billingCategory?, defaultFallback?, excludedCarriers?, testers?, autofill?
     * @param array $options Additional request options
     * @return array The response whose data is the agent
     * @throws ValidationException If agentId is missing
     */
    public function update(string $agentId, array $params, array $options = []): array
    {
        $path = $this->agentPath($agentId);
        $body = [];
        foreach (self::BODY_KEYS as $key) {
            if (array_key_exists($key, $params)) {
                $body[$key] = self::bodyValue($key, $params[$key]);
            }
        }
        return $this->client->request($path, array_merge([
            'method' => 'PUT',
            'body' => $body ?: (object) [],
        ], $options));
    }

    /**
     * Check an agent against every submit requirement without writing
     *
     * @param string $agentId The agent ID
     * @param array $options Additional request options
     * @return array The response whose data is ['valid', 'errors' => [['field', 'reason', 'code'?]], 'warnings' => [['field', 'reason']]];
     *     a copy-rule error at the launch gate carries the carrier partner's key as code (for example OPT_IN_MSG_RATE_DISCL_MISSING)
     * @throws ValidationException If agentId is missing
     */
    public function validate(string $agentId, array $options = []): array
    {
        return $this->client->request($this->agentPath($agentId, '/validate'), array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Submit a draft: create the agent at the carrier partner, with no review first. It needs the full profile, a
     * useCase and a billingCategory; otherwise a 400 whose details carry ['code' => 'REGISTRATION_INCOMPLETE',
     * 'errors', 'warnings']. The agent moves to AGENT_CREATING, then IN_TESTING once the partner reports it ready.
     * A group that has not finished account verification gets a 403 with code GROUP_UNVERIFIED, and a rejected one a
     * 403 with code GROUP_REJECTED; a pending verification passes.
     *
     * @param string $agentId The agent ID
     * @param array $options Additional request options
     * @return array The response whose data is the agent (status AGENT_CREATING)
     * @throws ValidationException If agentId is missing
     */
    public function submit(string $agentId, array $options = []): array
    {
        return $this->client->request($this->agentPath($agentId, '/submit'), array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Request the agent's launch: the launch requirements are checked and the agent goes to Signal House review (status
     * PENDING_REVIEW), whose approval files the launch with the carrier partner. Allowed from IN_TESTING, REJECTED and
     * CARRIER_REJECTED (409 otherwise); a failed requirement is a 400 whose details carry ['code' =>
     * 'REGISTRATION_INCOMPLETE', 'errors', 'warnings'], and a copy-rule error carries the carrier partner's key as its code
     * (for example OPT_IN_MSG_RATE_DISCL_MISSING). A group that has not finished account verification gets a 403 with
     * code GROUP_UNVERIFIED, and a rejected one a 403 with code GROUP_REJECTED. Sends POST /registration/:agentId/launch:
     * the agent is a registration.
     * The request charges the agent's launch fees: a wallet that cannot cover them gets a 402, and the agent stays where it is. When any fee is due the request must set \$acknowledgeFees, or it gets a 400 with code FEE_ACKNOWLEDGEMENT_REQUIRED and nothing is held; a launch with nothing due needs no acknowledgement. The agent's launchFees quotes what the next request charges.
     * When the agent's brand vertical is healthcare or insurance, the launch needs the protected health information (PHI) disclosure acknowledged: set \$acknowledgePhi, or the request gets a 400 REGISTRATION_INCOMPLETE whose errors name consent.phiAcknowledgement. The API records the acknowledgement as consent.phiAcknowledgement ['acknowledgedAt', 'acknowledgedBy'] from the caller; the client sends only the boolean, and an agent update cannot set it. Once recorded, a later launch request needs no new acknowledgement.
     *
     * @param string $agentId The agent ID
     * @param array $options Additional request options
     * @param bool|null $acknowledgeFees Accept the fees due; required when any are
     * @param bool|null $acknowledgePhi Accept the PHI disclosure; required for a healthcare or insurance brand
     * @return array The response whose data is the agent (status PENDING_REVIEW)
     * @throws ValidationException If agentId is missing
     */
    public function launch(string $agentId, array $options = [], ?bool $acknowledgeFees = null, ?bool $acknowledgePhi = null): array
    {
        $this->client->require(['agentId' => $agentId]);
        $safeAgentId = rawurlencode($agentId);
        return $this->client->request("/registration/{$safeAgentId}/launch", array_merge([
            'method' => 'POST',
        ], self::launchBody($acknowledgeFees, $acknowledgePhi), $options));
    }

    /**
     * Send Signal House the corrected launch answers after a carrier rejected a launch other carriers took. The carrier
     * partner takes no change through its API once a carrier launched, so Signal House emails it the update; edit consent
     * and contact first. The agent's carrierUpdate reads REQUESTED for the rejected carriers, then SENT once Signal House
     * sent it. 409 when no carrier is REJECTED after a partial launch, or an update already waits. Sends POST
     * /registration/:agentId/carrier-update: the agent is a registration.
     *
     * @param string $agentId The agent ID
     * @param array $options Additional request options
     * @return array The response whose data is the agent's registration, with data.carrierUpdate
     * @throws ValidationException If agentId is missing
     */
    public function requestCarrierUpdate(string $agentId, array $options = []): array
    {
        $this->client->require(['agentId' => $agentId]);
        $safeAgentId = rawurlencode($agentId);
        return $this->client->request("/registration/{$safeAgentId}/carrier-update", array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Archive an agent. One the carrier partner created stops sending at once and stays ARCHIVE_PENDING until
     * Signal House confirms the partner cancelled it, then ARCHIVED. Archiving cannot be undone; an agent already
     * ARCHIVE_PENDING answers 409.
     *
     * @param string $agentId The agent ID
     * @param array $options Additional request options
     * @return array The response whose data is the agent (status ARCHIVE_PENDING or ARCHIVED)
     * @throws ValidationException If agentId is missing
     */
    public function archive(string $agentId, array $options = []): array
    {
        return $this->client->request($this->agentPath($agentId, '/archive'), array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Make the links that start a chat with the agent: an sms: deep link in Google's format, and a short link that
     * redirects to it, for a QR code. With the agent's defaultFallback.from, the deep link names that number, so a
     * handset that cannot reach the agent texts it. The same request returns the same short link. The links reach
     * approved testers until a carrier launches the agent. Before Google has created the agent the request answers 409
     * with code GOOGLE_AGENT_PENDING.
     *
     * @param string $agentId The agent ID
     * @param string|null $body The message the chat opens with, prefilled on the handset (max 1,000 characters)
     * @param array $options Additional request options
     * @return array The response whose data is ['googleAgentId', 'deepLink', 'shortLink']
     * @throws ValidationException If agentId is missing
     */
    public function createEntryPoints(string $agentId, ?string $body = null, array $options = []): array
    {
        return $this->client->request($this->agentPath($agentId, '/entry-points'), array_merge([
            'method' => 'POST',
        ], $body === null ? [] : ['body' => ['body' => $body]], $options));
    }

    /**
     * Resend a tester's expired invitation, which starts a new 24 hours for the tester to accept it. The number must be
     * a tester on the agent (404) and its invitation must be EXPIRED (409); up to 20 invitations go out a day (429).
     *
     * @param string $agentId The agent ID
     * @param string $phoneNumber The tester's US number, with or without the +
     * @param array $options Additional request options
     * @return array The response whose data is the agent
     * @throws ValidationException If agentId or phoneNumber is missing
     */
    public function resendTesterInvite(string $agentId, string $phoneNumber, array $options = []): array
    {
        $this->client->require(['phoneNumber' => $phoneNumber]);
        return $this->client->request($this->agentPath($agentId, '/testers/' . rawurlencode($phoneNumber) . '/resend'), array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Remove a tester at once, at any point before the agent is archived. The number must be a tester on the agent
     * (404); an archived agent answers 409.
     *
     * @param string $agentId The agent ID
     * @param string $phoneNumber The tester's US number, with or without the +
     * @param array $options Additional request options
     * @return array The response whose data is the agent
     * @throws ValidationException If agentId or phoneNumber is missing
     */
    public function removeTester(string $agentId, string $phoneNumber, array $options = []): array
    {
        $this->client->require(['phoneNumber' => $phoneNumber]);
        return $this->client->request($this->agentPath($agentId, '/testers/' . rawurlencode($phoneNumber)), array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Host the agent's logo (224 x 224 px, 50 KB max) or hero image (1440 x 448 px, 200 KB max), PNG or JPEG, and set
     * it on the draft. Images of any other size are refused.
     *
     * @param string $agentId The agent ID
     * @param array $params kind ("logo" or "hero"), file (a file path or an open resource/stream), fileName? (the
     *     part's filename)
     * @param array $options Additional request options
     * @return array The response whose data is the agent with the new URL
     * @throws ValidationException If agentId, kind or file is missing
     */
    public function uploadAsset(string $agentId, array $params, array $options = []): array
    {
        $path = $this->agentPath($agentId, '/asset');
        return $this->multipartClient->request($path, array_merge([
            'method' => 'POST',
            'multipart' => $this->assetParts($params),
        ], $options));
    }

    /**
     * A launch request's options: the body of the acknowledgements the caller set, or none when there are none.
     *
     * @param bool|null $acknowledgeFees Accept the fees due, or null to leave it out
     * @param bool|null $acknowledgePhi Accept the PHI disclosure, or null to leave it out
     * @return array ['body' => [...]], or [] when both are null
     */
    private static function launchBody(?bool $acknowledgeFees, ?bool $acknowledgePhi): array
    {
        $body = array_filter(['acknowledgeFees' => $acknowledgeFees, 'acknowledgePhi' => $acknowledgePhi], fn ($value) => $value !== null);
        return $body === [] ? [] : ['body' => $body];
    }

    /**
     * A body value as the API expects it: an empty array under an object key becomes an empty object.
     *
     * @param string $key The body key
     * @param mixed $value The caller's value
     * @return mixed The value to serialize
     */
    private static function bodyValue(string $key, $value)
    {
        return $value === [] && in_array($key, self::OBJECT_KEYS, true) ? (object) [] : $value;
    }

    /**
     * Validate an agent ID and build its escaped path
     *
     * @param string $agentId The agent ID
     * @param string $suffix The path after the ID, e.g. '/submit'
     * @return string The request path
     * @throws ValidationException If agentId is missing
     */
    private function agentPath(string $agentId, string $suffix = ''): string
    {
        $this->client->require(['agentId' => $agentId]);
        return '/rcs/agent/' . rawurlencode($agentId) . $suffix;
    }

    /**
     * Validate and build the multipart parts: kind, then file (named fileName, else "upload", so a stream is still
     * sent as a file)
     *
     * @param array $params kind, file (a file path or an open resource/stream), fileName?
     * @return array The Guzzle multipart parts
     * @throws ValidationException If kind or file is missing
     */
    private function assetParts(array $params): array
    {
        $this->client->require(['kind' => $params['kind'] ?? null, 'file' => $params['file'] ?? null]);
        $file = $params['file'];
        return [
            ['name' => 'kind', 'contents' => (string) $params['kind']],
            ['name' => 'file', 'contents' => is_string($file) ? fopen($file, 'r') : $file, 'filename' => $params['fileName'] ?? 'upload'],
        ];
    }
}
