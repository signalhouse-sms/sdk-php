<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * Programmable Voice Profiles.
 *
 * A Programmable Voice Profile groups a set of the account's numbers (across one
 * or more subgroups) under a single inbound call-handling decision routed through
 * Signal House's own voice servers — the same idea as a SIP trunk, but without a
 * customer SBC. A number can belong to at most one profile and is mutually
 * exclusive with SIP trunks.
 *
 * Create/update fields:
 *  - name               (string, required for create) Profile name.
 *  - subgroupIds        (string[], required for create) Subgroups this profile spans.
 *  - region             (string, optional) Region/POP label.
 *  - routeAction        (string, optional) FORWARD|WEBRTC|SIP_TRUNK|SIP_PROFILE|CALL_CONTROL (default FORWARD).
 *                       Deliver the call to a PSTN number (FORWARD), ring the profile's subgroup
 *                       registered softphones (WEBRTC), send to a SIP trunk (SIP_TRUNK), ring a
 *                       registered SIP endpoint (SIP_PROFILE), or hand the call to your programmable
 *                       call-control webhook that returns SHML (CALL_CONTROL).
 *  - forwardToE164      (string, optional) Destination number, required when routeAction is FORWARD.
 *  - forwardAfterSeconds(int, optional) Ring seconds before the action fires.
 *  - routeSipTrunkId    (string, optional) SIP trunk id, required when routeAction is SIP_TRUNK.
 *  - routeSipProfileId  (string, optional) SIP endpoint id, required when routeAction is SIP_PROFILE.
 *  - webhookUrl         (string, optional) Inbound voice webhook / SHML program URL, required when
 *                       routeAction is CALL_CONTROL.
 *  - recordingEnabled   (bool, optional) Record calls on this profile.
 *  - enabled            (bool, optional) Active state (default true).
 *
 * Routes are mounted under /voice/api/v1/programmable-voice-profiles on the
 * Voice service.
 */
class ProgrammableVoiceProfiles
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * List all Programmable Voice Profiles for the current account.
     * GET /voice/api/v1/programmable-voice-profiles
     */
    public function list(array $options = []): array
    {
        return $this->client->request('/voice/api/v1/programmable-voice-profiles', array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a single Programmable Voice Profile by ID.
     * GET /voice/api/v1/programmable-voice-profiles/:id
     */
    public function get(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/programmable-voice-profiles/{$safeId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create a Programmable Voice Profile.
     *
     * Required: name, subgroupIds. Optional: region, routeAction
     * (FORWARD|WEBRTC|SIP_TRUNK|SIP_PROFILE|CALL_CONTROL), forwardToE164, forwardAfterSeconds,
     * routeSipTrunkId (required when routeAction is SIP_TRUNK), routeSipProfileId
     * (required when routeAction is SIP_PROFILE), webhookUrl (required when routeAction
     * is CALL_CONTROL), recordingEnabled, enabled.
     *
     * @param array $profileData Profile fields (see class docblock for the full list).
     * POST /voice/api/v1/programmable-voice-profiles
     */
    public function create(array $profileData, array $options = []): array
    {
        $this->client->require(['profileData' => $profileData]);
        return $this->client->request('/voice/api/v1/programmable-voice-profiles', array_merge([
            'method' => 'POST',
            'body' => $profileData,
        ], $options));
    }

    /**
     * Update an existing Programmable Voice Profile (partial). Any create field may
     * be supplied, including routeAction (FORWARD|WEBRTC|SIP_TRUNK|SIP_PROFILE|CALL_CONTROL)
     * and its dependents routeSipTrunkId (SIP_TRUNK) / routeSipProfileId (SIP_PROFILE) /
     * webhookUrl (CALL_CONTROL).
     *
     * @param array $updateData Fields to update (see class docblock for the full list).
     * PATCH /voice/api/v1/programmable-voice-profiles/:id
     */
    public function update(string $id, array $updateData, array $options = []): array
    {
        $this->client->require(['id' => $id, 'updateData' => $updateData]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/programmable-voice-profiles/{$safeId}", array_merge([
            'method' => 'PATCH',
            'body' => $updateData,
        ], $options));
    }

    /**
     * Toggle a profile active/inactive.
     * POST /voice/api/v1/programmable-voice-profiles/:id/toggle-active
     */
    public function toggleActive(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/programmable-voice-profiles/{$safeId}/toggle-active", array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Delete a Programmable Voice Profile. Its number assignments cascade away,
     * so those numbers fall back to subgroup/global routing.
     * DELETE /voice/api/v1/programmable-voice-profiles/:id
     */
    public function delete(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/programmable-voice-profiles/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Assign a phone number (by E.164) to this profile. Rejected if the number is
     * already configured on a SIP trunk/endpoint or another profile — it must be
     * de-configured there first (mutual exclusivity).
     * POST /voice/api/v1/programmable-voice-profiles/:id/assign-number
     */
    public function assignNumber(string $id, string $e164, array $options = []): array
    {
        $this->client->require(['id' => $id, 'e164' => $e164]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/programmable-voice-profiles/{$safeId}/assign-number", array_merge([
            'method' => 'POST',
            'body' => ['e164' => $e164],
        ], $options));
    }

    /**
     * Unassign a phone number (by E.164) from this profile.
     * POST /voice/api/v1/programmable-voice-profiles/:id/unassign-number
     */
    public function unassignNumber(string $id, string $e164, array $options = []): array
    {
        $this->client->require(['id' => $id, 'e164' => $e164]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/programmable-voice-profiles/{$safeId}/unassign-number", array_merge([
            'method' => 'POST',
            'body' => ['e164' => $e164],
        ], $options));
    }
}
