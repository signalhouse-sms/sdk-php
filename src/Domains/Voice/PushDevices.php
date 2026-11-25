<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * Mobile Push Devices — your mobile app registers each device so incoming calls
 * wake it. Calls the Voice service mounted under /voice. Accessed via
 * $sdk->voice->pushDevices.
 *
 * These methods are called by the app with the user's voice token, not the API
 * key or a dashboard token: pass the token from $sdk->voice->tokens->create()
 * (minted with a subgroupId) as $options['token'], which the SDK sends as the
 * bearer token. The device is tied to that token's identity.
 *
 *   $sdk->voice->pushDevices->register(
 *       ['provider' => 'fcm', 'deviceToken' => $fcmToken],
 *       ['token' => $voiceToken]
 *   );
 *
 * deviceData keys: provider ('apns' for iOS with a PushKit VoIP token, 'fcm' for
 * Android with a Firebase registration token), deviceToken (the device's push
 * token), environment (apns only: 'production' (default) or 'sandbox' for
 * development builds), appId (the bundle ID or Firebase project ID; only needed
 * when the account has several apps for the provider).
 *
 * Routes are mounted under /voice/api/v1/push-devices on the Voice service.
 */
class PushDevices
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Register this device for push. Call it after each token mint and whenever
     * the OS gives the app a new push token. A push token already registered to
     * another user moves to this one.
     *
     * Returns data: id, identity, provider, environment, appId, registeredAt (201).
     *
     * @param array $deviceData provider, deviceToken, environment?, appId? (see class docblock).
     * @param array $options Must carry the voice token as 'token'.
     * POST /voice/api/v1/push-devices
     */
    public function register(array $deviceData, array $options = []): array
    {
        $this->client->require(['deviceData' => $deviceData]);
        return $this->client->request('/voice/api/v1/push-devices', array_merge([
            'method' => 'POST',
            'body' => $deviceData,
        ], $options));
    }

    /**
     * Unregister this device, e.g. when the user signs out. Returns data: removed.
     *
     * @param string $deviceToken The device's push token. URL-encoded here, since
     *                            FCM tokens contain ':'.
     * @param array $options Must carry the voice token as 'token'.
     * DELETE /voice/api/v1/push-devices/:deviceToken
     */
    public function unregister(string $deviceToken, array $options = []): array
    {
        $this->client->require(['deviceToken' => $deviceToken]);
        $safeToken = rawurlencode($deviceToken);
        return $this->client->request("/voice/api/v1/push-devices/{$safeToken}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * The pushes recently sent to the voice token's user, newest first (kept 7
     * days), for debugging. Each has id, callUuid, provider, kind, status, error,
     * createdAt.
     *
     * @param int|null $limit How many, 1 to 100 (the service defaults to 20).
     * @param array $options Must carry the voice token as 'token'.
     * GET /voice/api/v1/push-devices/deliveries
     */
    public function listDeliveries(?int $limit = null, array $options = []): array
    {
        $queryString = $this->client->getQueryString(['limit' => $limit]);
        return $this->client->request("/voice/api/v1/push-devices/deliveries{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }
}
