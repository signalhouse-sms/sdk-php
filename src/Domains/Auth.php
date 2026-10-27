<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Auth
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Login with email and password
     *
     * @param string $email The user's email address
     * @param string $password The user's password
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function login(string $email, string $password, array $options = []): array
    {
        return $this->client->request('/auth', array_merge([
            'method' => 'POST',
            'body' => ['email' => $email, 'password' => $password],
        ], $options));
    }

    /**
     * Reset a user's password
     *
     * @param string $userId The ID of the user
     * @param string $newPassword The new password to set
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function resetPassword(string $userId, string $newPassword, array $options = []): array
    {
        $this->client->require(['userId' => $userId, 'newPassword' => $newPassword]);
        $safeUserId = rawurlencode($userId);
        return $this->client->request("/auth/resetpassword/{$safeUserId}", array_merge([
            'method' => 'PUT',
            'body' => ['newPassword' => $newPassword],
        ], $options));
    }

    /**
     * Request a password-reset link be emailed to the given address (public).
     * Always succeeds regardless of whether an account exists for the email, so it
     * cannot be used to enumerate registered emails.
     *
     * @param string $email The email address to send a reset link to
     * @param array $options Additional request options
     * @return array The response from the server (['success' => true])
     */
    public function forgotPassword(string $email, array $options = []): array
    {
        $this->client->require(['email' => $email]);
        return $this->client->request('/auth/forgot-password', array_merge([
            'method' => 'POST',
            'body' => ['email' => $email],
        ], $options));
    }

    /**
     * Reset a password using the single-use token from a reset email (public)
     *
     * @param string $token The single-use reset token from the email link
     * @param string $password The new password to set (min 8 characters)
     * @param array $options Additional request options
     * @return array The response from the server (['success' => true])
     */
    public function resetPasswordWithToken(string $token, string $password, array $options = []): array
    {
        $this->client->require(['token' => $token, 'password' => $password]);
        return $this->client->request('/auth/reset-password', array_merge([
            'method' => 'POST',
            'body' => ['token' => $token, 'password' => $password],
        ], $options));
    }

    /**
     * Get token login history for a group or user
     *
     * @param array $params Filter parameters (groupId, userId, page, limit)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAuthHistory(array $params = [], array $options = []): array
    {
        $this->client->require(['groupId or userId' => $params['groupId'] ?? $params['userId'] ?? null]);
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/auth/history{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Log out all other users in the caller's active group
     *
     * @param array $options Additional request options
     * @return array The response containing loggedOutCount
     */
    public function logoutAll(array $options = []): array
    {
        return $this->client->request('/auth/logout-all', array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Get the Group ID associated with the caller's JWT (their active group)
     *
     * @param array $options Additional request options
     * @return array The response containing ['groupId' => ...]
     */
    public function getGroupId(array $options = []): array
    {
        return $this->client->request('/auth/group-id', array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Mint a single-use, short-lived external-link token for the authenticated caller.
     * The token is handed to the GHL/Shopify backend so it can link the caller's existing
     * V2 group to its tenant.
     *
     * @param string $product The external system to link to ("ghl" or "shopify")
     * @param array $options Additional request options
     * @return array The response from the server (['token' => ...])
     */
    public function requestExternalLinkToken(string $product, array $options = []): array
    {
        $this->client->require(['product' => $product]);
        return $this->client->request('/auth/external-link-token', array_merge([
            'method' => 'POST',
            'body' => ['product' => $product],
        ], $options));
    }
}
