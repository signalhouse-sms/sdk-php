<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Onboarding
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
                 * Get all onboarding records (one per group). Staff-only.
                 */
                public function getOnboardings(array $options = []): array
                {
                    return $this->client->request('/group/onboarding', array_merge([
                        'method' => 'GET',
                    ], $options));
                }

                /**
                 * Get a single onboarding record by group ID. Staff-only.
                 */
                public function getOnboarding(string $groupId, array $options = []): array
                {
                    $this->client->require(['groupId' => $groupId]);
                    $safeGroupId = rawurlencode($groupId);
                    return $this->client->request("/group/onboarding/{$safeGroupId}", array_merge([
                        'method' => 'GET',
                    ], $options));
                }

                /**
                 * Set where a group's new toll-free brands and numbers go (`infobip` or `twilio`). Staff-only.
                 * Existing brands, numbers and campaigns stay where they are.
                 *
                 * @param string $groupId The group ID whose toll-free provider to set
                 * @param string $tollFreeProvider The toll-free provider: 'infobip' or 'twilio'
                 * @param array $options Additional request options
                 * @return array The response from the server (the updated onboarding record)
                 * @throws \SignalHouse\SDK\Exceptions\ValidationException If groupId or tollFreeProvider is missing
                 */
                public function setTollFreeProvider(string $groupId, string $tollFreeProvider, array $options = []): array
                {
                    $this->client->require(['groupId' => $groupId, 'tollFreeProvider' => $tollFreeProvider]);
                    $safeGroupId = rawurlencode($groupId);
                    return $this->client->request("/group/onboarding/{$safeGroupId}/toll-free-provider", array_merge([
                        'method' => 'PUT',
                        'body' => ['tollFreeProvider' => $tollFreeProvider],
                    ], $options));
                }

                /**
                 * Add an internal comment/note to a group's admin Pricing editor. Staff-only.
                 */
                public function addPricingComment(string $groupId, string $comment, array $options = []): array
                {
                    $this->client->require(['groupId' => $groupId, 'comment' => $comment]);
                    $safeGroupId = rawurlencode($groupId);
                    return $this->client->request("/group/onboarding/{$safeGroupId}/pricing-comments", array_merge([
                        'method' => 'POST',
                        'body' => ['comment' => $comment],
                    ], $options));
                }

                /**
                 * Add an internal comment/note to a group's admin Credits page. Staff-only.
                 */
                public function addCreditComment(string $groupId, string $comment, array $options = []): array
                {
                    $this->client->require(['groupId' => $groupId, 'comment' => $comment]);
                    $safeGroupId = rawurlencode($groupId);
                    return $this->client->request("/group/onboarding/{$safeGroupId}/credit-comments", array_merge([
                        'method' => 'POST',
                        'body' => ['comment' => $comment],
                    ], $options));
                }
            };
        }
    }
}
