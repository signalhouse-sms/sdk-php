<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Auth
{
    private HttpClient $client;
    private bool $enableAdmin;
    public ?object $admin = null;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;

        if ($enableAdmin) {
            $this->admin = new class($client) {
                private HttpClient $client;

                public function __construct(HttpClient $client)
                {
                    $this->client = $client;
                }

                /**
                 * Turn off two-factor authentication for a user who has lost every factor (lockout
                 * recovery). Clears their methods, backup codes and trusted devices. Audited.
                 *
                 * @param string $userId The id of the user to reset
                 * @param array $options Additional request options
                 * @return array The response from the server (['reset' => true])
                 */
                public function resetUserTwoFactor(string $userId, array $options = []): array
                {
                    $this->client->require(['userId' => $userId]);
                    return $this->client->request('/auth/2fa/users/' . rawurlencode($userId), array_merge([
                        'method' => 'DELETE',
                    ], $options));
                }
            };
        }
    }

    /**
     * Login with email and password
     *
     * @param string $email The user's email address
     * @param string $password The user's password
     * @param array $options Additional request options
     * @param string|null $deviceToken A trusted-device token from an earlier verifyTwoFactorChallenge; skips 2FA while valid
     * @return array The response from the server: ['token' => ...], or ['mfaRequired' => true, 'challengeId' => ...] when 2FA is on,
     *   or ['token' => ..., 'twoFactorSetupRequired' => true] (a setup-only session) when the user's group requires 2FA and they haven't turned it on
     */
    public function login(string $email, string $password, array $options = [], ?string $deviceToken = null): array
    {
        $body = ['email' => $email, 'password' => $password];
        if ($deviceToken !== null) {
            $body['deviceToken'] = $deviceToken;
        }
        return $this->client->request('/auth', array_merge([
            'method' => 'POST',
            'body' => $body,
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

    /**
     * List the OAuth sign-in providers enabled on this API (only providers with credentials configured)
     *
     * @param array $options Additional request options
     * @return array The response from the server ([['id' => ..., 'label' => ...]])
     */
    public function getOAuthProviders(array $options = []): array
    {
        return $this->client->request('/auth/oauth/providers', array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Build the URL that starts an OAuth sign-in. Navigate a browser to it (it is a redirect, not
     * an API call); the API returns the user to the portal's /auth/callback with a single-use ticket.
     * With a $linkTicket (from createOAuthLink) it links the provider to the signed-in account instead,
     * and the API returns the user to /Settings/Security with `pendingLink` (pass it to
     * confirmOAuthLink to finish linking) or `linkError`. With a $reauthTicket (from createOAuthReauth)
     * the signed-in user signs in again with a linked provider to confirm it's them, and the API returns
     * them to /Settings/Security with a single-use `pendingReauth` (the session that started it redeems it with confirmOAuthReauth) or `reauthError`.
     *
     * @param string $provider The provider id ("google", "microsoft", "apple" or "sso" for Enterprise SSO)
     * @param string|null $next A same-site path (e.g. "/oauth/consent?request=abc") to return the user to
     *   after sign-in. The API hands it back on the /auth/callback redirect as `next`; anything that
     *   isn't a rooted path on the portal is dropped
     * @param string|null $email Required for "sso" sign-in: the user's work email, whose domain picks the
     *   organization's identity provider. Not needed with a $reauthTicket
     * @param string|null $linkTicket Link the provider to the signed-in account that requested this ticket
     * @param string|null $reauthTicket Sign in again with a linked provider to confirm it's the account that
     *   requested this ticket
     * @return string The absolute authorization URL
     */
    public function getOAuthAuthorizationUrl(string $provider, ?string $next = null, ?string $email = null, ?string $linkTicket = null, ?string $reauthTicket = null): string
    {
        $this->client->require(['provider' => $provider]);
        $url = $this->client->getBaseUrl() . '/auth/oauth/' . rawurlencode($provider);
        $query = [];
        if ($email !== null && $email !== '') {
            $query[] = 'email=' . rawurlencode($email);
        }
        if ($linkTicket !== null && $linkTicket !== '') {
            $query[] = 'linkTicket=' . rawurlencode($linkTicket);
        }
        if ($reauthTicket !== null && $reauthTicket !== '') {
            $query[] = 'reauthTicket=' . rawurlencode($reauthTicket);
        }
        if ($next !== null && $next !== '') {
            $query[] = 'next=' . rawurlencode($next);
        }
        if (!empty($query)) {
            $url .= '?' . implode('&', $query);
        }
        return $url;
    }

    /**
     * List the caller's sign-in methods: whether they chose their own password ("passwordKnown" is
     * false for an account created through a provider or Enterprise SSO), and every provider linked
     * to the account
     *
     * @param array $options Additional request options
     * @return array The response from the server (['passwordKnown' => ..., 'links' => [['provider' => ..., 'email' => ..., 'linkedAt' => ..., 'lastLoginAt' => ...]]])
     */
    public function getOAuthLinks(array $options = []): array
    {
        return $this->client->request('/auth/oauth/links', array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Start linking Google, Microsoft or Apple to the caller's account. Returns a single-use link
     * ticket (valid 2 minutes): navigate the browser to
     * getOAuthAuthorizationUrl($provider, null, null, $linkTicket). The API returns the browser to
     * /Settings/Security with a `pendingLink`, which must be passed to confirmOAuthLink.
     * Requires a fresh confirmTwoFactorIdentity from the same session, which this spends.
     *
     * @param string $provider "google", "microsoft" or "apple"
     * @param array $options Additional request options
     * @return array The response from the server (['linkTicket' => ..., 'expiresInSeconds' => ...])
     */
    public function createOAuthLink(string $provider, array $options = []): array
    {
        $this->client->require(['provider' => $provider]);
        $safeProvider = rawurlencode($provider);
        return $this->client->request("/auth/oauth/{$safeProvider}/link", array_merge(['method' => 'POST'], $options));
    }

    /**
     * Start signing in again with a provider already linked to the caller's account, to confirm it's
     * them. Spends nothing. Returns a single-use ticket (valid 2 minutes): navigate the browser to
     * getOAuthAuthorizationUrl($provider, null, null, null, $reauthTicket). The browser comes back to
     * /Settings/Security with a single-use `pendingReauth` (the session that started it redeems it with confirmOAuthReauth) or `reauthError` (reauth_wrong_account,
     * reauth_unsupported, reauth_stale, invalid_state, sso_not_configured, access_denied or provider_error).
     *
     * @param string $provider "google", "microsoft", "apple" or "sso"; must be linked to the account
     * @param array $options Additional request options
     * @return array The response from the server (['reauthTicket' => ..., 'reauthSecret' => ..., 'expiresInSeconds' => ...]).
     *   Keep reauthSecret in the client that started the flow: it is returned only here, and confirmOAuthReauth needs it
     */
    public function createOAuthReauth(string $provider, array $options = []): array
    {
        $this->client->require(['provider' => $provider]);
        $safeProvider = rawurlencode($provider);
        return $this->client->request("/auth/oauth/{$safeProvider}/reauth", array_merge(['method' => 'POST'], $options));
    }

    /**
     * Finish signing in again with a linked provider. The round trip started with createOAuthReauth
     * returns the browser to /Settings/Security with a single-use `pendingReauth` (valid 5 minutes); this
     * redeems it, but only for the same account and the same session that called createOAuthReauth, so a
     * re-sign-in completed in someone else's browser confirms nothing. Fails with 400 when it expired or
     * was used, and 403 for another account or session.
     *
     * @param string $pendingReauth The `pendingReauth` query parameter from the redirect
     * @param string $reauthSecret The reauthSecret createOAuthReauth returned; a wrong or missing secret is
     *   refused (403) and uses up the pending re-sign-in
     * @param array $options Additional request options
     * @return array The response from the server (['identityConfirmed' => ...]; true when 2FA is off, so it counts as a confirmed identity)
     */
    public function confirmOAuthReauth(string $pendingReauth, string $reauthSecret, array $options = []): array
    {
        $this->client->require(['pendingReauth' => $pendingReauth, 'reauthSecret' => $reauthSecret]);
        return $this->client->request('/auth/oauth/reauth/confirm', array_merge([
            'method' => 'POST',
            'body' => ['pendingReauth' => $pendingReauth, 'reauthSecret' => $reauthSecret],
        ], $options));
    }

    /**
     * Finish linking a provider. The provider round trip started with createOAuthLink returns the
     * browser to /Settings/Security with a single-use `pendingLink`; this links it, but only for the
     * account the link was started for, so a link URL opened in someone else's browser can't attach
     * their provider to your account.
     *
     * @param string $pendingLink The `pendingLink` query parameter from the redirect
     * @param array $options Additional request options
     * @return array The response from the server: the caller's (['passwordKnown' => ..., 'links' => [['provider' => ..., 'email' => ..., 'linkedAt' => ..., 'lastLoginAt' => ...]]])
     */
    public function confirmOAuthLink(string $pendingLink, array $options = []): array
    {
        $this->client->require(['pendingLink' => $pendingLink]);
        return $this->client->request('/auth/oauth/links/confirm', array_merge([
            'method' => 'POST',
            'body' => ['pendingLink' => $pendingLink],
        ], $options));
    }

    /**
     * Unlink a provider from the caller's account. Refused (400) when it is the account's only way
     * to sign in. Requires a fresh confirmTwoFactorIdentity from the same session.
     *
     * @param string $provider "google", "microsoft", "apple" or "sso"
     * @param array $options Additional request options
     * @return array The response from the server: the remaining (['passwordKnown' => ..., 'links' => [...]])
     */
    public function deleteOAuthLink(string $provider, array $options = []): array
    {
        $this->client->require(['provider' => $provider]);
        $safeProvider = rawurlencode($provider);
        return $this->client->request("/auth/oauth/{$safeProvider}/link", array_merge(['method' => 'DELETE'], $options));
    }

    /**
     * Read what a single-use OAuth ticket resolves to, without consuming it: a "session" (ready to
     * exchange), a "select" (pick one of several accounts) or a "signup" (finish registration)
     *
     * @param string $ticket The ticket from the OAuth callback redirect
     * @param array $options Additional request options
     * @return array The response from the server (['kind' => ..., 'provider' => ..., 'profile'?, 'accounts'?])
     */
    public function readOAuthTicket(string $ticket, array $options = []): array
    {
        $this->client->require(['ticket' => $ticket]);
        return $this->client->request('/auth/oauth/ticket', array_merge([
            'method' => 'POST',
            'body' => ['ticket' => $ticket],
        ], $options));
    }

    /**
     * Exchange a single-use "session" or "select" OAuth ticket for a JWT. Consumes the ticket.
     *
     * @param string $ticket The ticket from the OAuth callback redirect
     * @param string|null $userId Required for a "select" ticket: the chosen account from "accounts"
     * @param array $options Additional request options
     * @param string|null $deviceToken A trusted-device token from an earlier verifyTwoFactorChallenge; skips 2FA while valid
     * @return array The response from the server (['token' => ...]), or ['mfaRequired' => true, ...] when 2FA is on
     */
    public function exchangeOAuthTicket(string $ticket, ?string $userId = null, array $options = [], ?string $deviceToken = null): array
    {
        $this->client->require(['ticket' => $ticket]);
        $body = ['ticket' => $ticket];
        if ($userId !== null) {
            $body['userId'] = $userId;
        }
        if ($deviceToken !== null) {
            $body['deviceToken'] = $deviceToken;
        }
        return $this->client->request('/auth/oauth/exchange', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Get the caller's two-factor settings: methods (targets masked), default method, backup-code
     * counts and trusted devices. Never returns secrets or codes.
     *
     * @param array $options Additional request options
     * @return array The response from the server (the settings object)
     */
    public function getTwoFactorSettings(array $options = []): array
    {
        return $this->client->request('/auth/2fa', array_merge(['method' => 'GET'], $options));
    }

    /**
     * Confirm it's really the account owner before a sensitive change: startTwoFactorEnrollment,
     * removeTwoFactorMethod, regenerateBackupCodes or disableTwoFactor; linking or unlinking a
     * sign-in provider (createOAuthLink, deleteOAuthLink); and saving or deleting a group's SSO
     * connection (groups->saveSsoConnection, groups->deleteSsoConnection).
     * With 2FA on, pass a current $code (or a backup code); with 2FA off, pass the account $password.
     * An account without its own password ("passwordKnown" false in getOAuthLinks, e.g. created
     * through a provider or Enterprise SSO) confirms instead with the code from
     * sendTwoFactorIdentityCode("email"), passing $method "email".
     * A confirmation lasts 5 minutes, belongs to the session that made it, and unlocks exactly one change.
     *
     * @param string|null $password The account password. Required while 2FA is off
     * @param string|null $code A code from an enrolled method, or a backup code. Required while 2FA is on
     * @param string|null $method The method the code came from; defaults to the primary method
     * @param bool|null $usingBackupCode True when $code is a backup code
     * @param array $options Additional request options
     * @return array The response from the server (['confirmed' => true, 'expiresInSeconds' => ...])
     */
    public function confirmTwoFactorIdentity(?string $password = null, ?string $code = null, ?string $method = null, ?bool $usingBackupCode = null, array $options = []): array
    {
        $body = array_filter(
            ['password' => $password, 'code' => $code, 'method' => $method, 'usingBackupCode' => $usingBackupCode],
            fn ($value) => $value !== null
        );
        return $this->client->request('/auth/2fa/confirm-identity', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Send a code by text or email to use with confirmTwoFactorIdentity (not needed for "app").
     * An account without its own password ("passwordKnown" false in getOAuthLinks) sends "email"
     * here with 2FA off, then confirms with that code and $method "email".
     *
     * @param string $method An enrolled "sms" or "email" method, or "email" for an account without its own password
     * @param array $options Additional request options
     * @return array The response from the server (['sent' => ..., 'target' => ..., 'expiresInSeconds'?])
     */
    public function sendTwoFactorIdentityCode(string $method, array $options = []): array
    {
        $this->client->require(['method' => $method]);
        return $this->client->request('/auth/2fa/confirm-identity/send', array_merge([
            'method' => 'POST',
            'body' => ['method' => $method],
        ], $options));
    }

    /**
     * Start enrolling a two-factor method. For "app" the response carries the setup secret and
     * otpauth URI; for "sms" and "email" a code is sent.
     * Requires a fresh confirmTwoFactorIdentity from the same session (403 otherwise); each
     * confirmation unlocks one change.
     *
     * @param string $method "app", "sms" or "email"
     * @param string|null $phone Required for "sms"
     * @param string|null $address Required for "email"
     * @param string|null $deviceName Optional label for an authenticator app
     * @param array $options Additional request options
     * @return array The response from the server (['enrollmentId' => ..., 'secret'?, 'otpauthUri'?, 'sentTo'?])
     */
    public function startTwoFactorEnrollment(string $method, ?string $phone = null, ?string $address = null, ?string $deviceName = null, array $options = []): array
    {
        $this->client->require(['method' => $method]);
        $body = array_filter(
            ['method' => $method, 'phone' => $phone, 'address' => $address, 'deviceName' => $deviceName],
            fn ($value) => $value !== null
        );
        return $this->client->request('/auth/2fa/enroll', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Confirm a pending enrollment with its code. The first method turns 2FA on and returns backup
     * codes, shown only this once.
     *
     * @param string $enrollmentId From startTwoFactorEnrollment
     * @param string $code The 6-digit code
     * @param array $options Additional request options
     * @return array The response from the server (['settings' => ..., 'backupCodes' => [...] | null])
     */
    public function confirmTwoFactorEnrollment(string $enrollmentId, string $code, array $options = []): array
    {
        $this->client->require(['enrollmentId' => $enrollmentId, 'code' => $code]);
        return $this->client->request('/auth/2fa/enroll/' . rawurlencode($enrollmentId) . '/verify', array_merge([
            'method' => 'POST',
            'body' => ['code' => $code],
        ], $options));
    }

    /**
     * Choose which enrolled method is offered first at sign-in
     *
     * @param string $method "app", "sms" or "email"
     * @param array $options Additional request options
     * @return array The response from the server (the settings object)
     */
    public function setTwoFactorPrimaryMethod(string $method, array $options = []): array
    {
        $this->client->require(['method' => $method]);
        return $this->client->request('/auth/2fa/primary', array_merge([
            'method' => 'PUT',
            'body' => ['method' => $method],
        ], $options));
    }

    /**
     * Remove an enrolled two-factor method. Removing the last one turns 2FA off.
     * Requires a fresh confirmTwoFactorIdentity from the same session (403 otherwise); each
     * confirmation unlocks one change.
     *
     * @param string $method "app", "sms" or "email"
     * @param array $options Additional request options
     * @return array The response from the server (the settings object)
     */
    public function removeTwoFactorMethod(string $method, array $options = []): array
    {
        $this->client->require(['method' => $method]);
        return $this->client->request('/auth/2fa/methods/' . rawurlencode($method), array_merge(['method' => 'DELETE'], $options));
    }

    /**
     * Replace all backup codes with a new set, shown only once. The previous codes stop working.
     * Requires a fresh confirmTwoFactorIdentity from the same session (403 otherwise); each
     * confirmation unlocks one change.
     *
     * @param array $options Additional request options
     * @return array The response from the server (['codes' => [...], 'settings' => ...])
     */
    public function regenerateBackupCodes(array $options = []): array
    {
        return $this->client->request('/auth/2fa/backup-codes', array_merge(['method' => 'POST'], $options));
    }

    /**
     * Stop trusting a device, so its next sign-in asks for the second factor again
     *
     * @param string $deviceId The trusted device's id from getTwoFactorSettings
     * @param array $options Additional request options
     * @return array The response from the server (the settings object)
     */
    public function revokeTrustedDevice(string $deviceId, array $options = []): array
    {
        $this->client->require(['deviceId' => $deviceId]);
        return $this->client->request('/auth/2fa/trusted-devices/' . rawurlencode($deviceId), array_merge(['method' => 'DELETE'], $options));
    }

    /**
     * Turn two-factor authentication off: removes every method, backup code and trusted device.
     * Requires a fresh confirmTwoFactorIdentity from the same session (403 otherwise); each
     * confirmation unlocks one change.
     *
     * @param array $options Additional request options
     * @return array The response from the server (the settings object)
     */
    public function disableTwoFactor(array $options = []): array
    {
        return $this->client->request('/auth/2fa', array_merge(['method' => 'DELETE'], $options));
    }

    /**
     * Whether the "secure your account" enrollment prompt is due for the caller
     *
     * @param array $options Additional request options
     * @return array The response from the server (['due' => ...])
     */
    public function getTwoFactorPrompt(array $options = []): array
    {
        return $this->client->request('/auth/2fa/prompt', array_merge(['method' => 'GET'], $options));
    }

    /**
     * Dismiss the enrollment prompt until the next sign-in. It stops for good after three dismissals.
     *
     * @param array $options Additional request options
     * @return array The response from the server (['due' => false])
     */
    public function dismissTwoFactorPrompt(array $options = []): array
    {
        return $this->client->request('/auth/2fa/prompt/dismiss', array_merge(['method' => 'POST'], $options));
    }

    /**
     * Send a sign-in code by text or email for a pending two-factor challenge (not needed for "app")
     *
     * @param string $challengeId From the mfaRequired login or exchange response
     * @param string $method "sms" or "email"
     * @param array $options Additional request options
     * @return array The response from the server (['sent' => ..., 'target' => ..., 'expiresInSeconds' => ...])
     */
    public function sendTwoFactorChallengeCode(string $challengeId, string $method, array $options = []): array
    {
        $this->client->require(['challengeId' => $challengeId, 'method' => $method]);
        return $this->client->request('/auth/2fa/challenge/' . rawurlencode($challengeId) . '/send', array_merge([
            'method' => 'POST',
            'body' => ['method' => $method],
        ], $options));
    }

    /**
     * Verify a sign-in code (or backup code) for a pending challenge and receive the session JWT
     *
     * @param string $challengeId From the mfaRequired login or exchange response
     * @param string $code The 6-digit code, or a backup code (xxxx-xxxx)
     * @param string|null $method The method the code came from; defaults to the primary method
     * @param bool|null $usingBackupCode True when $code is a backup code
     * @param bool|null $rememberDevice Trust this device for 30 days (response then carries a deviceToken)
     * @param string|null $deviceName Label for the trusted device
     * @param array $options Additional request options
     * @return array The response from the server (['token' => ..., 'deviceToken'?])
     */
    public function verifyTwoFactorChallenge(string $challengeId, string $code, ?string $method = null, ?bool $usingBackupCode = null, ?bool $rememberDevice = null, ?string $deviceName = null, array $options = []): array
    {
        $this->client->require(['challengeId' => $challengeId, 'code' => $code]);
        $body = array_filter(
            ['code' => $code, 'method' => $method, 'usingBackupCode' => $usingBackupCode, 'rememberDevice' => $rememberDevice, 'deviceName' => $deviceName],
            fn ($value) => $value !== null
        );
        return $this->client->request('/auth/2fa/challenge/' . rawurlencode($challengeId) . '/verify', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Cancel a pending two-factor challenge
     *
     * @param string $challengeId From the mfaRequired login or exchange response
     * @param array $options Additional request options
     * @return array The response from the server (['cancelled' => true])
     */
    public function cancelTwoFactorChallenge(string $challengeId, array $options = []): array
    {
        $this->client->require(['challengeId' => $challengeId]);
        return $this->client->request('/auth/2fa/challenge/' . rawurlencode($challengeId), array_merge(['method' => 'DELETE'], $options));
    }
}
