<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * Number Routes.
 *
 * One inbound route set directly on a single phone number. A number's own route
 * takes priority over its Programmable Voice Profile and subgroup voice settings.
 *
 * Route fields (camelCase, sent as given):
 *  - routeAction         (string, required) FORWARD|WEBRTC|SIP_TRUNK|SIP_PROFILE|CALL_CONTROL|AI|VOICEMAIL.
 *  - forwardToE164       (string, optional) FORWARD destination (required for FORWARD), or the WEBRTC
 *                        no-answer destination when noAnswerAction is FORWARD.
 *  - noAnswerAction      (string, optional) WEBRTC only: VOICEMAIL|FORWARD|KEEP_RINGING (default VOICEMAIL).
 *  - noAnswerSeconds     (int, optional) WEBRTC only: seconds to ring before the no-answer action, 5-120 (default 20).
 *  - routeSipTrunkId     (string, optional) SIP trunk id, required for SIP_TRUNK.
 *  - routeSipProfileId   (string, optional) SIP endpoint id, required for SIP_PROFILE.
 *  - webhookUrl          (string, optional) SHML program URL, required for CALL_CONTROL.
 *  - routeAgentProfileId (string, optional) AI agent profile id (AGP...), required for AI.
 *  - recordingEnabled    (bool, optional) Record calls handled by this route.
 *
 * Routes are mounted under /voice/api/v1/numbers/:e164/route on the
 * Voice service. The e164 leading + is optional.
 */
class NumberRoutes
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Get a number's own route and the routing currently in effect for it.
     * Roles: api, admin, developer, billing, user.
     * Returns { e164, route: {...}|null, effective: {...} }.
     * GET /voice/api/v1/numbers/:e164/route
     */
    public function get(string $e164, array $options = []): array
    {
        $this->client->require(['e164' => $e164]);
        $safeE164 = rawurlencode($e164);
        return $this->client->request("/voice/api/v1/numbers/{$safeE164}/route", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Set (create or replace) a number's route. The whole route is replaced:
     * fields the new routeAction doesn't use are cleared.
     * Roles: api, admin, developer.
     * Returns { e164, route: {...}, effective: {...} }.
     *
     * @param array $routeData The route to set (see class docblock for the full list).
     * PUT /voice/api/v1/numbers/:e164/route
     */
    public function set(string $e164, array $routeData, array $options = []): array
    {
        $this->client->require(['e164' => $e164, 'routeData' => $routeData]);
        $safeE164 = rawurlencode($e164);
        return $this->client->request("/voice/api/v1/numbers/{$safeE164}/route", array_merge([
            'method' => 'PUT',
            'body' => $routeData,
        ], $options));
    }

    /**
     * Remove a number's own route. The number falls back to its Programmable
     * Voice Profile, subgroup voice settings, or the default.
     * Roles: api, admin, developer.
     * Returns { e164, route: null, effective: {...} }.
     * DELETE /voice/api/v1/numbers/:e164/route
     */
    public function delete(string $e164, array $options = []): array
    {
        $this->client->require(['e164' => $e164]);
        $safeE164 = rawurlencode($e164);
        return $this->client->request("/voice/api/v1/numbers/{$safeE164}/route", array_merge([
            'method' => 'DELETE',
        ], $options));
    }
}
