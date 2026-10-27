<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Shortlinks
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Get the redirect URL for a shortlink by its ID
     *
     * @param string $shortlinkId The shortlink ID
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getShortlinkRedirect(string $shortlinkId, array $options = []): array
    {
        $this->client->require(['shortlinkId' => $shortlinkId]);
        $safeShortlinkId = rawurlencode($shortlinkId);
        return $this->client->request("/shortlink/redirect/{$safeShortlinkId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get the recipient opt-out details behind an alphanumeric-sender opt-out link (sihou.io/o/{code})
     *
     * @param string $code The opt-out code from the link
     * @param array $options Additional request options
     * @return array The response from the server; data is {code, senderName, maskedRecipient, optedOut}
     */
    public function getOptOut(string $code, array $options = []): array
    {
        $this->client->require(['code' => $code]);
        $safeCode = rawurlencode($code);
        return $this->client->request("/shortlink/optout/{$safeCode}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Confirm the recipient opt-out behind an alphanumeric-sender opt-out link; idempotent
     *
     * @param string $code The opt-out code from the link
     * @param array $options Additional request options
     * @return array The response from the server; data is {code, senderName, maskedRecipient, optedOut: true}
     */
    public function confirmOptOut(string $code, array $options = []): array
    {
        $this->client->require(['code' => $code]);
        $safeCode = rawurlencode($code);
        return $this->client->request("/shortlink/optout/{$safeCode}", array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Get shortlink details with optional filters
     *
     * @param array $params Filter parameters (shortlinkId, messageId, phoneNumber, campaignId, brandId, subgroupId, groupId, page, limit)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getShortlink(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/shortlink{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }
}
