<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * Mobile Push Credentials — the Apple APNs auth key and Firebase service account
 * the Voice service uses to wake your mobile app for incoming calls. One
 * credential per app; secrets are encrypted and never returned (responses show a
 * fingerprint instead). Calls the Voice service mounted under /voice. Accessed
 * via $sdk->voice->pushCredentials.
 *
 * credentialData is one of:
 *  - Apple (iOS): ['provider' => 'apns', 'name' => ?string, 'keyId' => string,
 *    'teamId' => string, 'bundleId' => string, 'privateKey' => string]
 *    keyId is the 10-character key ID of the APNs auth key, teamId your
 *    10-character Apple developer team ID, privateKey the contents of the .p8
 *    file. VoIP pushes go to the bundle ID's .voip topic.
 *  - Firebase (Android): ['provider' => 'fcm', 'name' => ?string,
 *    'serviceAccount' => string|array] — the service account key file, as its
 *    JSON text or the decoded array.
 * name is an optional label, up to 100 characters.
 *
 * Each credential is returned as metadata: id, provider, name, appId, keyId,
 * teamId, bundleId, projectId, clientEmail, fingerprint, createdAt, updatedAt.
 *
 * Routes are mounted under /voice/api/v1/push-credentials on the Voice service.
 */
class PushCredentials
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * List the account's push credentials (metadata only).
     * GET /voice/api/v1/push-credentials
     */
    public function list(array $options = []): array
    {
        return $this->client->request('/voice/api/v1/push-credentials', array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get one push credential (metadata only).
     * GET /voice/api/v1/push-credentials/:id
     */
    public function get(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/push-credentials/{$safeId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Upload push credentials for one app: an Apple APNs auth key, or a Firebase
     * service account. The key is checked on upload.
     *
     * @param array $credentialData An apns or fcm credential (see class docblock).
     * POST /voice/api/v1/push-credentials
     */
    public function create(array $credentialData, array $options = []): array
    {
        $this->client->require(['credentialData' => $credentialData]);
        return $this->client->request('/voice/api/v1/push-credentials', array_merge([
            'method' => 'POST',
            'body' => $credentialData,
        ], $options));
    }

    /**
     * Replace a credential's key or service account (and name). Same body as
     * create(); the provider cannot change. Registered devices keep working with
     * the new key.
     *
     * @param array $credentialData The replacement (see class docblock).
     * PUT /voice/api/v1/push-credentials/:id
     */
    public function update(string $id, array $credentialData, array $options = []): array
    {
        $this->client->require(['id' => $id, 'credentialData' => $credentialData]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/push-credentials/{$safeId}", array_merge([
            'method' => 'PUT',
            'body' => $credentialData,
        ], $options));
    }

    /**
     * Delete a credential and every device registered for that app; their calls
     * stop being pushed.
     * DELETE /voice/api/v1/push-credentials/:id
     */
    public function delete(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/voice/api/v1/push-credentials/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }
}
