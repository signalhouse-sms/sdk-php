<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Agents
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * List the voices an agent can be configured to speak with.
     *
     * The catalog is platform-wide, not per-account, so this takes no scope. 'voiceId' is
     * the value to set on a spoken channel's setting.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAgentVoices(array $options = []): array
    {
        return $this->client->request('/agent/voices', array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * List the agent profiles under a group.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param array $params Filter parameters. Requires 'groupId'; also accepts 'subgroupId'
     *                       (Narrow to the agents this subgroup can use — its own, plus the group-level agents shared with every subgroup.) and 'page'/'limit' for pagination.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAgentProfiles(array $params = [], array $options = []): array
    {
        $this->client->require(['groupId' => $params['groupId'] ?? null]);
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/agent/profiles{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a single agent profile by id.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $agentProfileId The id of the agent profile to fetch
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAgentProfile(string $agentProfileId, array $options = []): array
    {
        $this->client->require(['agentProfileId' => $agentProfileId]);
        $safeId = rawurlencode($agentProfileId);
        return $this->client->request("/agent/profiles/{$safeId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create a new agent profile.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param array $profileData The agent profile data. Required: groupId (string starting with 'G') and name (string).
     *                           Optional: subgroupId (string starting with 'S', or null for a group-level agent),
     *                           status ("active"|"inactive"), systemPrompt, greeting, guardrails,
     *                           sendAuthority ("review"|"autopilot", defaults to "review"; deployments
     *                           may override it per subgroup). publishStatus and publishedAt are
     *                           read-only and change only through publishAgentProfile and
     *                           unpublishAgentProfile. Model, voice and sampling settings are
     *                           per-channel and live on the channel setting, not here.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createAgentProfile(array $profileData, array $options = []): array
    {
        $this->client->require(['profileData' => $profileData]);
        return $this->client->request('/agent/profiles', array_merge([
            'method' => 'POST',
            'body' => $profileData,
        ], $options));
    }

    /**
     * Update an existing agent profile.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $id The id of the agent profile to update
     * @param array $updateData The fields to update. All optional: name, status ("active"|"inactive"),
     *                          systemPrompt, greeting, guardrails, sendAuthority ("review"|"autopilot").
     *                          publishStatus and publishedAt are read-only and change only through
     *                          publishAgentProfile and unpublishAgentProfile. Model, voice and sampling
     *                          settings are per-channel and live on the channel setting, not here.
     *                          The scope fields groupId and subgroupId are immutable.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateAgentProfile(string $id, array $updateData, array $options = []): array
    {
        $this->client->require(['id' => $id, 'updateData' => $updateData]);
        $safeId = rawurlencode($id);
        return $this->client->request("/agent/profiles/{$safeId}", array_merge([
            'method' => 'PUT',
            'body' => $updateData,
        ], $options));
    }

    /**
     * Permanently delete an agent profile by its id.
     *
     * Permanently deletes the agent, its channel settings, and any tools and knowledge scoped to it, plus their provider-side counterparts. Tools and knowledge shared across the account are untouched. Conversation records survive — a transcript outlives the agent that produced it.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $id The id of the agent profile to delete
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteAgentProfile(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/agent/profiles/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * Send a message to an agent and get its reply — the app-native conversation
     * endpoint (webchat / SMS). Omit 'conversationId' to start a new conversation, or
     * pass one to continue an existing one. The agent's LLM tool loop runs server-side.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param array $params The message parameters. Required: 'agentProfileId', 'channel'
     *                       ("webchat"|"sms"|"voice"), and 'message'. Optional: 'conversationId'
     *                       (continue an existing conversation) and 'contactIdentifier'
     *                       (webchat visitor id or sender number).
     * @param array $options Additional request options
     * @return array The response from the server: conversationId, conversationSessionId,
     *               reply, toolInvocations, and ok.
     */
    public function sendAgentMessage(array $params, array $options = []): array
    {
        $this->client->require([
            'agentProfileId' => $params['agentProfileId'] ?? null,
            'channel' => $params['channel'] ?? null,
            'message' => $params['message'] ?? null,
        ]);
        $body = [
            'agentProfileId' => $params['agentProfileId'],
            'channel' => $params['channel'],
            'message' => $params['message'],
        ];
        if (isset($params['conversationId'])) {
            $body['conversationId'] = $params['conversationId'];
        }
        if (isset($params['contactIdentifier'])) {
            $body['contactIdentifier'] = $params['contactIdentifier'];
        }
        return $this->client->request('/agent/messages', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Start a conversation without sending a message.
     *
     * The counterpart to sendAgentMessage for callers that run the model themselves — a
     * voice runtime, or your own LLM. Those record what was said; sendAgentMessage decides
     * it. Both write the same conversation records.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param array $params agentProfileId (required), channel (required), contactIdentifier,
     *                      callId, metadata
     * @param array $options Additional request options
     * @return array The created conversation, including conversationId and conversationSessionId
     */
    public function startConversation(array $params, array $options = []): array
    {
        $this->client->require([
            'agentProfileId' => $params['agentProfileId'] ?? null,
            'channel' => $params['channel'] ?? null,
        ]);
        $body = [
            'agentProfileId' => $params['agentProfileId'],
            'channel' => $params['channel'],
        ];
        if (isset($params['contactIdentifier'])) {
            $body['contactIdentifier'] = $params['contactIdentifier'];
        }
        if (isset($params['callId'])) {
            $body['callId'] = $params['callId'];
        }
        if (isset($params['metadata'])) {
            $body['metadata'] = $params['metadata'];
        }
        return $this->client->request('/agent/conversations', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Read one conversation with a page of its messages, in order. Paged rather than whole:
     * a transcript has no natural ceiling. Omitting limit gives 100 messages, not all of them.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $conversationId The conversation to read
     * @param array $params page (defaults to 1), limit (defaults to 100, capped at 500)
     * @param array $options Additional request options
     * @return array A one-element array with the conversation and its messages
     */
    public function getConversation(string $conversationId, array $params = [], array $options = []): array
    {
        $this->client->require(['conversationId' => $conversationId]);
        $safeId = rawurlencode($conversationId);
        $queryString = $this->client->getQueryString([
            'page' => $params['page'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
        return $this->client->request("/agent/conversations/{$safeId}{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Append one message to a conversation.
     *
     * Records a turn rather than generating one, so role is explicit — an assistant turn
     * your own runtime produced is the normal case here. content is optional so a tool-only
     * turn needs no prose.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $conversationId The conversation to append to
     * @param array $params role (required), content, toolCalls, toolResults, ttfbMs,
     *                      latencyMs, bargeInOccurred
     * @param array $options Additional request options
     * @return array The persisted message
     */
    public function appendConversationMessage(string $conversationId, array $params, array $options = []): array
    {
        $this->client->require([
            'conversationId' => $conversationId,
            'role' => $params['role'] ?? null,
        ]);
        $safeId = rawurlencode($conversationId);
        $body = ['role' => $params['role']];
        foreach (['content', 'toolCalls', 'toolResults', 'ttfbMs', 'latencyMs', 'bargeInOccurred'] as $key) {
            if (isset($params[$key])) {
                $body[$key] = $params[$key];
            }
        }
        return $this->client->request("/agent/conversations/{$safeId}/messages", array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * End a conversation, closing its open session.
     *
     * status records HOW it ended and cannot be recovered afterwards, so pass the one that
     * actually happened. Ending an already-ended conversation is a no-op, so a retry after a
     * dropped connection cannot overwrite it.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $conversationId The conversation to end
     * @param array $params status ("completed"|"escalated"|"abandoned"), metadata
     * @param array $options Additional request options
     * @return array The ended conversation
     */
    public function endConversation(string $conversationId, array $params = [], array $options = []): array
    {
        $this->client->require(['conversationId' => $conversationId]);
        $safeId = rawurlencode($conversationId);
        $body = [];
        if (isset($params['status'])) {
            $body['status'] = $params['status'];
        }
        if (isset($params['metadata'])) {
            $body['metadata'] = $params['metadata'];
        }
        return $this->client->request("/agent/conversations/{$safeId}", array_merge([
            'method' => 'PUT',
            'body' => $body,
        ], $options));
    }

    /**
     * List an agent's per-channel settings.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $agentProfileId The agent whose channel settings to list
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAgentChannelSettings(string $agentProfileId, array $options = []): array
    {
        $this->client->require(['agentProfileId' => $agentProfileId]);
        $safeId = rawurlencode($agentProfileId);
        return $this->client->request("/agent/profiles/{$safeId}/channels", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a single per-channel setting for an agent.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $agentProfileId The agent the setting belongs to
     * @param string $channel The channel ("webchat"|"sms"|"voice")
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAgentChannelSetting(string $agentProfileId, string $channel, array $options = []): array
    {
        $this->client->require(['agentProfileId' => $agentProfileId, 'channel' => $channel]);
        $safeId = rawurlencode($agentProfileId);
        $safeChannel = rawurlencode($channel);
        return $this->client->request("/agent/profiles/{$safeId}/channels/{$safeChannel}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create or update (upsert) an agent's per-channel setting. One row per (agent, channel);
     * the channel is taken from the path.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $agentProfileId The agent the setting belongs to
     * @param string $channel The channel ("webchat"|"sms"|"voice")
     * @param array $settingData Fields to set: allowedTools (string[]), enabled (bool),
     *                           channelPrompt (string), greeting (string|null),
     *                           llmProvider ("bedrock"|"openai"|"anthropic"|"groq"),
     *                           llmModel (string|null), temperature (number 0-2),
     *                           voiceId (string|null). Model settings are per-channel
     *                           because each channel is served by a different runtime.
     *                           Spoken channels also accept: speed (0.7-1.2), stability
     *                           (0-1), similarityBoost (0-1), speechModel (string — how
     *                           the agent is voiced, chosen independently of llmModel),
     *                           turnEagerness ("patient"|"normal"|"eager"),
     *                           turnTimeoutSeconds and initialWaitSeconds (1-300, or -1
     *                           for no timeout), silenceEndCallSeconds (10-7200),
     *                           maxCallDurationSeconds (60-7200),
     *                           allowGreetingInterruption (bool), keyterms (string[] the
     *                           transcriber is biased toward), backgroundSound
     *                           (['preset' => string, 'volume' => 0.01-1]|null), workflow (the
     *                           conversation workflow graph: 'nodes' and 'edges' keyed arrays, plus
     *                           optional 'subgraphs' and 'prevent_subagent_loops'; node bodies are
     *                           defined by the voice runtime and passed through as-is). workflow is
     *                           accepted on the voice channel only — sending one on webchat or sms
     *                           is a 400; null clears it.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function upsertAgentChannelSetting(string $agentProfileId, string $channel, array $settingData = [], array $options = []): array
    {
        $this->client->require(['agentProfileId' => $agentProfileId, 'channel' => $channel]);
        $safeId = rawurlencode($agentProfileId);
        $safeChannel = rawurlencode($channel);
        return $this->client->request("/agent/profiles/{$safeId}/channels/{$safeChannel}", array_merge([
            'method' => 'PUT',
            'body' => $settingData,
        ], $options));
    }

    /**
     * Delete an agent's per-channel setting.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $agentProfileId The agent the setting belongs to
     * @param string $channel The channel ("webchat"|"sms"|"voice")
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteAgentChannelSetting(string $agentProfileId, string $channel, array $options = []): array
    {
        $this->client->require(['agentProfileId' => $agentProfileId, 'channel' => $channel]);
        $safeId = rawurlencode($agentProfileId);
        $safeChannel = rawurlencode($channel);
        return $this->client->request("/agent/profiles/{$safeId}/channels/{$safeChannel}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * List a group-level agent's deployments to subgroups. Each deployment carries
     * agentDeploymentId, groupId, subgroupId, agentProfileId, sendAuthorityOverride,
     * sendAuthorityChangedBy, sendAuthorityChangedAt, enabled, createdAt, and updatedAt.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $agentProfileId The agent whose deployments to list
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAgentDeployments(string $agentProfileId, array $options = []): array
    {
        $this->client->require(['agentProfileId' => $agentProfileId]);
        $safeId = rawurlencode($agentProfileId);
        return $this->client->request("/agent/profiles/{$safeId}/deployments", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Deploy a group-level agent to a subgroup, or change that deployment (upsert). One row per
     * (agent, subgroup); the subgroup is taken from the path.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $agentProfileId The agent to deploy
     * @param string $subgroupId The subgroup to deploy to (starts with 'S')
     * @param array $deploymentData Fields to set: sendAuthorityOverride ("review"|"autopilot"|null;
     *                              null clears the override so the deployment inherits the profile
     *                              default), enabled (bool, defaults to true).
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function upsertAgentDeployment(string $agentProfileId, string $subgroupId, array $deploymentData = [], array $options = []): array
    {
        $this->client->require(['agentProfileId' => $agentProfileId, 'subgroupId' => $subgroupId]);
        $safeId = rawurlencode($agentProfileId);
        $safeSubgroupId = rawurlencode($subgroupId);
        return $this->client->request("/agent/profiles/{$safeId}/deployments/{$safeSubgroupId}", array_merge([
            'method' => 'PUT',
            'body' => $deploymentData,
        ], $options));
    }

    /**
     * Remove an agent's deployment from a subgroup.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $agentProfileId The deployed agent
     * @param string $subgroupId The subgroup to remove the deployment from
     * @param array $options Additional request options
     * @return array The response from the server (the removed deployment)
     */
    public function deleteAgentDeployment(string $agentProfileId, string $subgroupId, array $options = []): array
    {
        $this->client->require(['agentProfileId' => $agentProfileId, 'subgroupId' => $subgroupId]);
        $safeId = rawurlencode($agentProfileId);
        $safeSubgroupId = rawurlencode($subgroupId);
        return $this->client->request("/agent/profiles/{$safeId}/deployments/{$safeSubgroupId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * List the endpoint bindings (which agent answers which number) in a group, ordered by
     * number. Each binding carries agentEndpointId, groupId, subgroupId, channel, endpointType,
     * endpointValue, agentProfileId, answerMode, overrides, enabled, createdAt, and updatedAt.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param array $params Filter parameters. Requires 'groupId'; also accepts 'subgroupId',
     *                       'agentProfileId' and 'channel' ("sms") to narrow the list.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAgentEndpoints(array $params = [], array $options = []): array
    {
        $this->client->require(['groupId' => $params['groupId'] ?? null]);
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/agent/endpoints{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Assign an agent to answer a number (upsert keyed by channel + number). Assigning again
     * replaces the previous agent, so a number has one agent per channel. Only SMS numbers can be bound.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $channel The channel to bind; must be "sms"
     * @param string $endpointValue The phone number: 10 to 15 digits, country code included, no "+"
     * @param array $endpointData agentProfileId (required; the agent must be active, in the number's
     *                            group, and either belong to the number's subgroup or be a group-level
     *                            agent with an enabled deployment there), answerMode ("all_inbound",
     *                            the default).
     * @param array $options Additional request options
     * @return array The response from the server (the created or updated binding)
     */
    public function upsertAgentEndpoint(string $channel, string $endpointValue, array $endpointData, array $options = []): array
    {
        $this->client->require([
            'channel' => $channel,
            'endpointValue' => $endpointValue,
            'agentProfileId' => $endpointData['agentProfileId'] ?? null,
        ]);
        $safeChannel = rawurlencode($channel);
        $safeEndpointValue = rawurlencode($endpointValue);
        return $this->client->request("/agent/endpoints/{$safeChannel}/{$safeEndpointValue}", array_merge([
            'method' => 'PUT',
            'body' => $endpointData,
        ], $options));
    }

    /**
     * Remove the agent assigned to a number.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $channel The bound channel; must be "sms"
     * @param string $endpointValue The phone number: digits only, country code included, no "+"
     * @param array $options Additional request options
     * @return array The response from the server (the removed binding)
     */
    public function deleteAgentEndpoint(string $channel, string $endpointValue, array $options = []): array
    {
        $this->client->require(['channel' => $channel, 'endpointValue' => $endpointValue]);
        $safeChannel = rawurlencode($channel);
        $safeEndpointValue = rawurlencode($endpointValue);
        return $this->client->request("/agent/endpoints/{$safeChannel}/{$safeEndpointValue}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * List paused agent conversations in a group, newest first. A paused conversation is one a
     * person took over; the agent stays out of it until it is resumed. Each pause carries groupId,
     * subgroupId, channel, endpointValue, contactIdentifier, reason ("human_reply", "escalation" or "manual"),
     * pausedBy, createdAt, and updatedAt.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param array $params Filter parameters. Requires 'groupId'; also accepts 'subgroupId',
     *                       'channel' ("sms"), 'endpointValue' (the agent's number) and
     *                       'contactIdentifier' (the contact's number), digits only, no "+".
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAgentContactPauses(array $params = [], array $options = []): array
    {
        $this->client->require(['groupId' => $params['groupId'] ?? null]);
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/agent/pauses{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Pause an agent on one conversation (its number + one contact). The agent stops answering that
     * contact on that number until the conversation is resumed; there is no timer. The conversation's
     * pending replies are rejected. Pausing a paused conversation keeps the original pause.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $channel The channel; must be "sms"
     * @param string $endpointValue The agent's number: 10 to 15 digits, country code included, no "+"
     * @param string $contactIdentifier The contact's number: 10 to 15 digits, country code included, no "+"
     * @param array $pauseData Optional 'reason': "human_reply" or "manual" (the default). Sent as an
     *                         empty JSON object when empty.
     * @param array $options Additional request options
     * @return array The response from the server (the pause)
     */
    public function pauseAgentConversation(
        string $channel,
        string $endpointValue,
        string $contactIdentifier,
        array $pauseData = [],
        array $options = []
    ): array {
        $this->client->require([
            'channel' => $channel,
            'endpointValue' => $endpointValue,
            'contactIdentifier' => $contactIdentifier,
        ]);
        $safeChannel = rawurlencode($channel);
        $safeEndpointValue = rawurlencode($endpointValue);
        $safeContactIdentifier = rawurlencode($contactIdentifier);
        return $this->client->request("/agent/pauses/{$safeChannel}/{$safeEndpointValue}/{$safeContactIdentifier}", array_merge([
            'method' => 'PUT',
            'body' => $pauseData === [] ? new \stdClass() : $pauseData,
        ], $options));
    }

    /**
     * Resume an agent on a paused conversation, so it answers the contact's next text again.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $channel The channel; must be "sms"
     * @param string $endpointValue The agent's number: 10 to 15 digits, country code included, no "+"
     * @param string $contactIdentifier The contact's number: 10 to 15 digits, country code included, no "+"
     * @param array $options Additional request options
     * @return array The response from the server (the removed pause)
     */
    public function resumeAgentConversation(
        string $channel,
        string $endpointValue,
        string $contactIdentifier,
        array $options = []
    ): array {
        $this->client->require([
            'channel' => $channel,
            'endpointValue' => $endpointValue,
            'contactIdentifier' => $contactIdentifier,
        ]);
        $safeChannel = rawurlencode($channel);
        $safeEndpointValue = rawurlencode($endpointValue);
        $safeContactIdentifier = rawurlencode($contactIdentifier);
        return $this->client->request("/agent/pauses/{$safeChannel}/{$safeEndpointValue}/{$safeContactIdentifier}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * List held agent replies (the Pending Replies queue) in a group, newest first. Each draft
     * carries agentReplyDraftId, groupId, subgroupId, agentProfileId, conversationId, channel,
     * endpointValue, contactPhoneNumber, inboundText, draftText, sentText, reason, status,
     * decidedBy, decidedAt, sentMessageId, deliveredBy ("signalhouse" or "external"; null until
     * decided), failureReason ("delivery_unconfirmed" or null), claimId (the current claim's id;
     * null until claimed), createdAt, and updatedAt.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param array $params Filter parameters. Requires 'groupId'; also accepts 'subgroupId',
     *                       'agentProfileId', 'status' ("pending", "sending", "sent", "rejected"
     *                       or "failed"), 'page' and 'limit' (default 25, max 500).
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAgentReplyDrafts(array $params = [], array $options = []): array
    {
        $this->client->require(['groupId' => $params['groupId'] ?? null]);
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/agent/drafts{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Approve a held agent reply and send it from the agent's number, optionally with edited
     * text. It goes through the normal SMS send path, so opt-out and billing apply; the draft
     * comes back "failed" when the send path accepted nothing. A draft that is no longer
     * pending returns 409.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $agentReplyDraftId The draft to approve
     * @param string|null $text Replacement text when a person edited the reply (1 to 10000
     *                          characters); null sends the agent's draft as written
     * @param array $options Additional request options
     * @return array The response from the server (the draft, now sent or failed)
     */
    public function approveAgentReplyDraft(string $agentReplyDraftId, ?string $text = null, array $options = []): array
    {
        $this->client->require(['agentReplyDraftId' => $agentReplyDraftId]);
        $safeId = rawurlencode($agentReplyDraftId);
        $request = ['method' => 'POST'];
        // An empty PHP array JSON-encodes as [], which the API rejects; send no body instead.
        if ($text !== null) {
            $request['body'] = ['text' => $text];
        }
        return $this->client->request("/agent/drafts/{$safeId}/approve", array_merge($request, $options));
    }

    /**
     * Reject a held agent reply so it is never sent. A draft that is no longer pending
     * returns 409.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $agentReplyDraftId The draft to reject
     * @param array $options Additional request options
     * @return array The response from the server (the rejected draft)
     */
    public function rejectAgentReplyDraft(string $agentReplyDraftId, array $options = []): array
    {
        $this->client->require(['agentReplyDraftId' => $agentReplyDraftId]);
        $safeId = rawurlencode($agentReplyDraftId);
        return $this->client->request("/agent/drafts/{$safeId}/reject", array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Claim a held agent reply for delivery through your own channel instead of Signal House's
     * send path. Nothing is sent: the draft becomes "sending" with deliveredBy "external",
     * sentText and a new claimId set, and you then report the outcome with
     * completeAgentReplyDraft or releaseAgentReplyDraft, passing that claimId back. The claim means two approvals can never both deliver. A draft that
     * is no longer pending returns 409; one whose number has left its subgroup returns 400 and is
     * closed as failed.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $agentReplyDraftId The draft to claim
     * @param string|null $text Replacement text when a person edited the reply (1 to 10000
     *                          characters); null claims the agent's draft as written
     * @param array $options Additional request options
     * @return array The response from the server (the claimed draft, including its claimId)
     */
    public function claimAgentReplyDraft(string $agentReplyDraftId, ?string $text = null, array $options = []): array
    {
        $this->client->require(['agentReplyDraftId' => $agentReplyDraftId]);
        $safeId = rawurlencode($agentReplyDraftId);
        $request = ['method' => 'POST'];
        // An empty PHP array JSON-encodes as [], which the API rejects; send no body instead.
        if ($text !== null) {
            $request['body'] = ['text' => $text];
        }
        return $this->client->request("/agent/drafts/{$safeId}/claim", array_merge($request, $options));
    }

    /**
     * Mark a claimed agent reply sent once your channel accepted it. Only the claim named by
     * $claimId can be completed; anything else returns 409. It is also accepted after an
     * unfinished claim was closed as "failed" with failureReason "delivery_unconfirmed", which it
     * turns into "sent".
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $agentReplyDraftId The claimed draft
     * @param string $claimId The claim being completed, as returned by claimAgentReplyDraft
     * @param string|null $externalMessageId Your channel's id for the sent message (up to 256
     *                                       characters), stored as sentMessageId; null omits it
     * @param array $options Additional request options
     * @return array The response from the server (the draft, now sent)
     */
    public function completeAgentReplyDraft(string $agentReplyDraftId, string $claimId, ?string $externalMessageId = null, array $options = []): array
    {
        $this->client->require(['agentReplyDraftId' => $agentReplyDraftId, 'claimId' => $claimId]);
        $safeId = rawurlencode($agentReplyDraftId);
        $body = ['claimId' => $claimId];
        if ($externalMessageId !== null) {
            $body['externalMessageId'] = $externalMessageId;
        }
        $request = ['method' => 'POST', 'body' => $body];
        return $this->client->request("/agent/drafts/{$safeId}/complete", array_merge($request, $options));
    }

    /**
     * Give back a claim whose reply certainly did not go out: the draft returns to "pending", or
     * with $permanent is closed as "failed". If you cannot tell whether it went out, do not
     * release it; a claim left unfinished for 30 minutes is closed as failed with failureReason
     * "delivery_unconfirmed". Only the claim named by $claimId can be released; anything else
     * returns 409.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $agentReplyDraftId The claimed draft
     * @param string $claimId The claim being released, as returned by claimAgentReplyDraft
     * @param bool|null $permanent True closes the draft as failed instead of returning it to
     *                             pending; null omits it
     * @param array $options Additional request options
     * @return array The response from the server (the draft, pending again or failed)
     */
    public function releaseAgentReplyDraft(string $agentReplyDraftId, string $claimId, ?bool $permanent = null, array $options = []): array
    {
        $this->client->require(['agentReplyDraftId' => $agentReplyDraftId, 'claimId' => $claimId]);
        $safeId = rawurlencode($agentReplyDraftId);
        $body = ['claimId' => $claimId];
        if ($permanent !== null) {
            $body['permanent'] = $permanent;
        }
        $request = ['method' => 'POST', 'body' => $body];
        return $this->client->request("/agent/drafts/{$safeId}/release", array_merge($request, $options));
    }

    /**
     * Publish an agent profile, setting publishStatus to "published" and stamping publishedAt.
     * The server rejects the call with 400 when the agent has no enabled deployment, and with
     * 409 when the agent is inactive.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $agentProfileId The agent profile to publish
     * @param array $options Additional request options
     * @return array The response from the server (the published profile)
     */
    public function publishAgentProfile(string $agentProfileId, array $options = []): array
    {
        $this->client->require(['agentProfileId' => $agentProfileId]);
        $safeId = rawurlencode($agentProfileId);
        return $this->client->request("/agent/profiles/{$safeId}/publish", array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Return an agent profile to draft (publishStatus "draft").
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $agentProfileId The agent profile to unpublish
     * @param array $options Additional request options
     * @return array The response from the server (the unpublished profile)
     */
    public function unpublishAgentProfile(string $agentProfileId, array $options = []): array
    {
        $this->client->require(['agentProfileId' => $agentProfileId]);
        $safeId = rawurlencode($agentProfileId);
        return $this->client->request("/agent/profiles/{$safeId}/unpublish", array_merge([
            'method' => 'POST',
        ], $options));
    }

    /**
     * Read the tenant AI settings for a scope (group, or a subgroup within it) — brand, tone,
     * timezone, business hours, and after-hours message. Returns the scope's own row only.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $groupId The group (required)
     * @param string|null $subgroupId The subgroup; null for the group-level settings
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getTenantAiSettings(string $groupId, ?string $subgroupId = null, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $queryString = $this->client->getQueryString(['groupId' => $groupId, 'subgroupId' => $subgroupId]);
        return $this->client->request("/agent/tenant-settings{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create or update (upsert) the tenant AI settings for a scope. The scope is identified by
     * the query params; the body carries the settings fields.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $groupId The group (required)
     * @param string|null $subgroupId The subgroup; null for the group-level settings
     * @param array $settingsData Fields to set: brandName (string), tone (string), timezone (string),
     *                            businessHours (array of {day, closed, open, close}), afterHoursMessage (string)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function upsertTenantAiSettings(string $groupId, ?string $subgroupId = null, array $settingsData = [], array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $queryString = $this->client->getQueryString(['groupId' => $groupId, 'subgroupId' => $subgroupId]);
        return $this->client->request("/agent/tenant-settings{$queryString}", array_merge([
            'method' => 'PUT',
            'body' => $settingsData,
        ], $options));
    }

    /**
     * Delete the tenant AI settings for a scope.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $groupId The group (required)
     * @param string|null $subgroupId The subgroup; null for the group-level settings
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteTenantAiSettings(string $groupId, ?string $subgroupId = null, array $options = []): array
    {
        $this->client->require(['groupId' => $groupId]);
        $queryString = $this->client->getQueryString(['groupId' => $groupId, 'subgroupId' => $subgroupId]);
        return $this->client->request("/agent/tenant-settings{$queryString}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * List the knowledge-base items an agent can answer from (RAG), scoped to a group and
     * optionally narrowed to a subgroup and/or a single agent.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param array $params Filter parameters. Requires 'groupId'; also accepts 'subgroupId',
     *                      'agentProfileId', and pagination 'page'/'limit' (max 100; server default 25).
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getKnowledgeItems(array $params = [], array $options = []): array
    {
        $this->client->require(['groupId' => $params['groupId'] ?? null]);
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/agent/knowledge{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a single knowledge item by id.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $knowledgeItemId The id of the knowledge item to fetch
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getKnowledgeItem(string $knowledgeItemId, array $options = []): array
    {
        $this->client->require(['knowledgeItemId' => $knowledgeItemId]);
        $safeId = rawurlencode($knowledgeItemId);
        return $this->client->request("/agent/knowledge/{$safeId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create a knowledge item. The raw text is stored; Atlas Vector Search owns the embedding.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param array $itemData The knowledge fields. Required: groupId (starts with 'G'), text.
     *                        Optional: subgroupId (starts with 'S', nullable), agentProfileId (nullable),
     *                        title, source ("manual"|"url"|"document"|"import"), enabled (bool), metadata (array|null).
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createKnowledgeItem(array $itemData, array $options = []): array
    {
        $this->client->require(['itemData' => $itemData]);
        return $this->client->request('/agent/knowledge', array_merge([
            'method' => 'POST',
            'body' => $itemData,
        ], $options));
    }

    /**
     * Update a knowledge item. The item's scope (group/subgroup/agent) is immutable.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $id The id of the knowledge item to update
     * @param array $updateData The fields to update: title, text, source, enabled, metadata
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateKnowledgeItem(string $id, array $updateData, array $options = []): array
    {
        $this->client->require(['id' => $id, 'updateData' => $updateData]);
        $safeId = rawurlencode($id);
        return $this->client->request("/agent/knowledge/{$safeId}", array_merge([
            'method' => 'PUT',
            'body' => $updateData,
        ], $options));
    }

    /**
     * Delete a knowledge item by its id.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $id The id of the knowledge item to delete
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteKnowledgeItem(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/agent/knowledge/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }

    /**
     * List the tools defined under a group (webhook, configured builtin, mcp). webhookAuth
     * secrets are redacted in the response.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param array $params Filter parameters. Requires 'groupId'; also accepts 'subgroupId',
     *                      'agentProfileId', 'type' ("builtin"|"webhook"|"mcp"), and pagination
     *                      'page'/'limit' (max 100; server default 25).
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAgentTools(array $params = [], array $options = []): array
    {
        $this->client->require(['groupId' => $params['groupId'] ?? null]);
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/agent/tools{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a single agent tool by id. webhookAuth secrets are redacted.
     *
     * Allowed roles: api, admin, developer, billing, user.
     *
     * @param string $agentToolId The id of the tool to fetch
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getAgentTool(string $agentToolId, array $options = []): array
    {
        $this->client->require(['agentToolId' => $agentToolId]);
        $safeId = rawurlencode($agentToolId);
        return $this->client->request("/agent/tools/{$safeId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create an agent tool. A webhook tool's webhookUrl must be an https URL that does not
     * target a private/internal address. webhookAuth is stored and never echoed back.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param array $toolData The tool fields. Required: groupId (starts with 'G'), name, description.
     *                        Type-specific: type ("builtin"|"webhook"|"mcp"); webhook => webhookUrl,
     *                        builtin => builtinKey, mcp => mcpServerUrl. Optional: subgroupId, agentProfileId,
     *                        parametersSchema, config, channels, enabled, and webhookAuth — which MUST be
     *                        ['headers' => [name => value], 'signingSecret' => string] (any other shape,
     *                        e.g. ['header','value'], is rejected with a 400). headers are sent verbatim on
     *                        every call (reserved/framing and x-signalhouse-* names rejected); signingSecret
     *                        makes us send X-SignalHouse-Signature: sha256=HMAC-SHA256("{X-SignalHouse-Timestamp}.{rawBody}")
     *                        so you can verify the call and reject replays. Stored, never returned.
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createAgentTool(array $toolData, array $options = []): array
    {
        $this->client->require(['toolData' => $toolData]);
        return $this->client->request('/agent/tools', array_merge([
            'method' => 'POST',
            'body' => $toolData,
        ], $options));
    }

    /**
     * Update an agent tool. The scope is immutable; a supplied webhookUrl is SSRF-checked.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $id The id of the tool to update
     * @param array $updateData The fields to update: name, description, type, builtinKey,
     *                          parametersSchema, config, webhookUrl, webhookAuth, mcpServerUrl, channels, enabled
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateAgentTool(string $id, array $updateData, array $options = []): array
    {
        $this->client->require(['id' => $id, 'updateData' => $updateData]);
        $safeId = rawurlencode($id);
        return $this->client->request("/agent/tools/{$safeId}", array_merge([
            'method' => 'PUT',
            'body' => $updateData,
        ], $options));
    }

    /**
     * Delete an agent tool by its id.
     *
     * Allowed roles: api, admin, developer.
     *
     * @param string $id The id of the tool to delete
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteAgentTool(string $id, array $options = []): array
    {
        $this->client->require(['id' => $id]);
        $safeId = rawurlencode($id);
        return $this->client->request("/agent/tools/{$safeId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }
}
