<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Subscriptions
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
                 * Get subscription templates
                 */
                public function getTemplates(?string $templateId = null, array $options = []): array
                {
                    $queryString = $this->client->getQueryString(['templateId' => $templateId]);
                    return $this->client->request("/subscription/template{$queryString}", array_merge([
                        'method' => 'GET',
                    ], $options));
                }

                /**
                 * Create a new subscription template
                 */
                public function createTemplate(array $templateData, array $options = []): array
                {
                    $this->client->require(['templateData' => $templateData]);
                    return $this->client->request('/subscription/template', array_merge([
                        'method' => 'POST',
                        'body' => $templateData,
                    ], $options));
                }

                /**
                 * Update a subscription template
                 */
                public function updateTemplate(string $templateId, array $templateData, array $options = []): array
                {
                    $this->client->require(['templateId' => $templateId, 'templateData' => $templateData]);
                    $safeTemplateId = rawurlencode($templateId);
                    return $this->client->request("/subscription/template/{$safeTemplateId}", array_merge([
                        'method' => 'PUT',
                        'body' => $templateData,
                    ], $options));
                }

                /**
                 * Delete a subscription template
                 */
                public function deleteTemplate(string $templateId, array $options = []): array
                {
                    $this->client->require(['templateId' => $templateId]);
                    $safeTemplateId = rawurlencode($templateId);
                    return $this->client->request("/subscription/template/{$safeTemplateId}", array_merge([
                        'method' => 'DELETE',
                    ], $options));
                }

                /**
                 * Create a custom subscription for a group
                 */
                public function createCustomSubscription(string $groupId, array $subscriptionData, array $options = []): array
                {
                    $this->client->require(['groupId' => $groupId, 'subscriptionData' => $subscriptionData]);
                    $safeGroupId = rawurlencode($groupId);
                    return $this->client->request("/subscription/user/custom/{$safeGroupId}", array_merge([
                        'method' => 'POST',
                        'body' => $subscriptionData,
                    ], $options));
                }

                /**
                 * Update volume tiers, ramp, and monthly minimum on an active custom subscription
                 */
                public function updateCustomSubscription(string $groupId, array $updateData, array $options = []): array
                {
                    $this->client->require(['groupId' => $groupId, 'updateData' => $updateData]);
                    $safeGroupId = rawurlencode($groupId);
                    return $this->client->request("/subscription/user/custom/{$safeGroupId}", array_merge([
                        'method' => 'PUT',
                        'body' => $updateData,
                    ], $options));
                }
            };
        }
    }

    /**
     * Get subscription history for a group
     *
     * @param string $groupId The group ID
     * @param bool|null $onlyActive Whether to only include active subscriptions
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getSubscriptions(string $groupId, ?bool $onlyActive = null, array $options = []): array
    {
        $queryString = $this->client->getQueryString(['groupId' => $groupId, 'onlyActive' => $onlyActive]);
        return $this->client->request("/subscription/user{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Subscribe a group to a template
     *
     * @param string $groupId The group ID
     * @param string $templateId The template ID
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function subscribe(string $groupId, string $templateId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId, 'templateId' => $templateId]);
        $safeGroupId = rawurlencode($groupId);
        $safeTemplateId = rawurlencode($templateId);
        return $this->client->request("/subscription/user/subscribe/{$safeGroupId}/{$safeTemplateId}", array_merge([
            'method' => 'POST',
            'body' => (object) [],
        ], $options));
    }

    /**
     * Unsubscribe a group
     *
     * @param string $groupId The group ID
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function unsubscribe(string $groupId, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $safeGroupId = rawurlencode($groupId);
        return $this->client->request("/subscription/user/cancel/{$safeGroupId}", array_merge([
            'method' => 'POST',
        ], $options));
    }
}
