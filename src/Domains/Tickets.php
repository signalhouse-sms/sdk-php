<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Tickets
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
                 * Load Jira form metadata for the admin ticket form. Staff-only.
                 */
                public function getJiraMetadata(array $options = []): array
                {
                    return $this->client->request('/admin/ticket/jira/metadata', array_merge([
                        'method' => 'GET',
                    ], $options));
                }

                /**
                 * Search open epics. Staff-only.
                 */
                public function searchJiraEpics(?string $query = null, array $options = []): array
                {
                    $queryString = $this->client->getQueryString(['query' => $query]);
                    return $this->client->request("/admin/ticket/jira/epics{$queryString}", array_merge([
                        'method' => 'GET',
                    ], $options));
                }

                /**
                 * Search open non-Epic, non-Subtask issues for the Subtask parent picker. Staff-only.
                 */
                public function searchJiraParentIssues(?string $query = null, array $options = []): array
                {
                    $queryString = $this->client->getQueryString(['query' => $query]);
                    return $this->client->request("/admin/ticket/jira/parent-issues{$queryString}", array_merge([
                        'method' => 'GET',
                    ], $options));
                }

                /**
                 * Search assignable Jira users. Staff-only.
                 */
                public function searchJiraAssignees(?string $query = null, array $options = []): array
                {
                    $queryString = $this->client->getQueryString(['query' => $query]);
                    return $this->client->request("/admin/ticket/jira/assignees{$queryString}", array_merge([
                        'method' => 'GET',
                    ], $options));
                }

                /**
                 * Suggest Jira labels. Staff-only.
                 */
                public function searchJiraLabels(?string $query = null, array $options = []): array
                {
                    $queryString = $this->client->getQueryString(['query' => $query]);
                    return $this->client->request("/admin/ticket/jira/labels{$queryString}", array_merge([
                        'method' => 'GET',
                    ], $options));
                }

                /**
                 * Create a Jira ticket from the admin customer ticket form. Staff-only.
                 */
                public function createJiraTicket(array $data, array $options = []): array
                {
                    $this->client->require(['data' => $data]);
                    return $this->client->request('/admin/ticket/jira', array_merge([
                        'method' => 'POST',
                        'body' => $data,
                    ], $options));
                }
            };
        }
    }
}
