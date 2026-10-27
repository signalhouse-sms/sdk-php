<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * Global Voice Settings — account-wide voice defaults (accepted regions, max
 * spend per minute, E911). SIP trunks and Programmable Voice Profiles override
 * these. Calls the Voice service mounted under /voice. Accessed via
 * $sdk->voice->globalVoiceSettings.
 *
 * settingsData keys (camelCase): acceptedRegions (string[]), maxSpendPerMinute
 * (float|null), e911Enabled (bool).
 *
 * Routes are mounted under /voice/api/v1/global-voice-settings on the
 * Voice service.
 */
class GlobalVoiceSettings
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Get the current account's global voice settings. Returns data: null if the
     * account has never configured them (defaults apply).
     * GET /voice/api/v1/global-voice-settings
     */
    public function get(array $options = []): array
    {
        return $this->client->request("/voice/api/v1/global-voice-settings", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Upsert the account's global voice settings. Partial — only the fields you
     * provide are written; omitted fields are preserved.
     * PUT /voice/api/v1/global-voice-settings
     */
    public function update(array $settingsData, array $options = []): array
    {
        $this->client->require(['settingsData' => $settingsData]);
        return $this->client->request("/voice/api/v1/global-voice-settings", array_merge([
            'method' => 'PUT',
            'body' => $settingsData,
        ], $options));
    }
}
