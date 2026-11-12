<?php

namespace SignalHouse\SDK\Domains\Rcs;

use SignalHouse\SDK\Exceptions\ValidationException;
use SignalHouse\SDK\HttpClient;

/**
 * RCS messages. Sends go through an agent to POST /message/rcs. A message is a Message record (messageType RCS), so the
 * messages methods read the same record. 404 while the environment's RCS switch is off; 403 while the group's rcsEnabled
 * flag is off; 409 while the agent cannot send yet.
 *
 * Body fields: agentId, recipients (up to 100 of ['phoneNumber', 'variables'?, 'clientMessageId'?, 'metadata'?]), exactly
 * one of templateId (with templateVersion?) or content, contentFormat (content.v1 (default)|google.rcs), trafficType
 * (AUTHENTICATION|TRANSACTION|SERVICEREQUEST|ACKNOWLEDGEMENT|PROMOTION; required on a MULTI_USE agent when the template
 * sets none), at most one of ttlSeconds or expireTime (ISO-8601), fallback (['mode' => auto|rcs_only|sms_only, 'channel'
 * => auto|sms|mms, 'from' => a Ready number on the agent's linked Active campaign, 'content' => ['text', 'mediaUrls'?],
 * 'validitySeconds']; if content is omitted the template's fallback, else a generated one, is used), statusCallbackUrl,
 * enableShortlink, isTest and simulate (not_capable|ttl_expired|delivered|read; approved testers only).
 */
class Messages
{
    /** The body keys of a send or a validate. */
    private const SEND_KEYS = [
        'agentId', 'recipients', 'templateId', 'templateVersion', 'content', 'contentFormat', 'trafficType', 'ttlSeconds',
        'expireTime', 'fallback', 'statusCallbackUrl', 'enableShortlink', 'isTest', 'simulate',
    ];

    /** The body keys of an estimate. */
    private const ESTIMATE_KEYS = [
        'agentId', 'recipients', 'recipientCount', 'templateId', 'templateVersion', 'content', 'contentFormat', 'trafficType',
        'ttlSeconds', 'expireTime', 'fallback',
    ];

    /** Body keys whose value is a JSON object, so an empty PHP array must be sent as {} rather than []. */
    private const OBJECT_KEYS = ['content', 'fallback'];

    /** Recipient keys whose value is a JSON object, for the same reason. */
    private const RECIPIENT_OBJECT_KEYS = ['variables', 'metadata'];

    private HttpClient $client;

    public function __construct(HttpClient $client)
    {
        $this->client = $client;
    }

    /**
     * Send an RCS message to up to 100 recipients. A check that blocks one recipient writes a BLOCKED message for it and
     * the rest still send. Pass $options['idempotencyKey'] to make a retry safe.
     *
     * @param array $params agentId, recipients, templateId?, templateVersion?, content?, contentFormat?, trafficType?,
     *     ttlSeconds?, expireTime?, fallback?, statusCallbackUrl?, enableShortlink?, isTest?, simulate?. Unset or null keys
     *     are omitted.
     * @param array $options Additional request options
     * @return array The response whose data is ['requested', 'enqueued', 'failed', 'bulkId', 'messages'] (201).
     *     A blocked row carries blockReason, reason and issues; an archived template answers 409 TEMPLATE_ARCHIVED. A recipient
     *     from 19999990000 to 19999990359 makes the whole request a simulation, as on SMS: data is ['simulated' => true,
     *     'webhooks'] and nothing is stored, sent or charged
     * @throws ValidationException If agentId or recipients is missing
     */
    public function send(array $params, array $options = []): array
    {
        $this->client->require(['agentId' => $params['agentId'] ?? null, 'recipients' => $params['recipients'] ?? null]);
        return $this->client->request('/message/rcs', array_merge([
            'method' => 'POST',
            'body' => self::body(self::SEND_KEYS, $params),
        ], $options));
    }

    /**
     * Run a send's checks without writing anything or holding funds
     *
     * @param array $params The send parameters
     * @param array $options Additional request options
     * @return array The response whose data is ['valid', 'errors', 'warnings', 'billingClass', 'segments', 'estimatedCostMicros',
     *     'unsupportedFiles', 'recipients']. valid is true only with no errors; each blocked recipient adds an error and carries
     *     blockReason, reason and issues
     * @throws ValidationException If agentId or recipients is missing
     */
    public function validate(array $params, array $options = []): array
    {
        $this->client->require(['agentId' => $params['agentId'] ?? null, 'recipients' => $params['recipients'] ?? null]);
        return $this->client->request('/message/rcs/validate', array_merge([
            'method' => 'POST',
            'body' => self::body(self::SEND_KEYS, $params),
        ], $options));
    }

    /**
     * Price an RCS draft in microdollars; no charge or send. Sends POST /message/estimate with channel "rcs".
     *
     * @param array $params agentId, recipients? or recipientCount? (exactly one; with a count, carriers are unknown and
     *     the default fees apply), templateId?, templateVersion?, content?, contentFormat?, trafficType?, ttlSeconds?,
     *     expireTime?, fallback? (its from is optional here; with none, the leg is priced on the channel of the agent's
     *     linked campaign). Unset or null keys are omitted.
     * @param array $options Additional request options
     * @return array The response whose data is ['billingClass', 'segments', 'recipientCount', 'rcsLeg', 'fallbackLeg',
     *     'totals', 'balance', 'issues', 'warnings'], issues listing what would block the send and warnings what an iPhone draws differently. rcsLeg.mediaSurcharge is the carrier's surcharge on media over 5 MB for one recipient, and rcsLeg.cost already holds it for every recipient; rcsLeg.cost is null with the fallback mode sms_only; a message over 100 MB adds a MEDIA_TOO_LARGE issue.
     * @throws ValidationException If agentId is missing
     */
    public function estimate(array $params, array $options = []): array
    {
        $this->client->require(['agentId' => $params['agentId'] ?? null]);
        return $this->client->request('/message/estimate', array_merge([
            'method' => 'POST',
            'body' => ['channel' => 'rcs'] + self::body(self::ESTIMATE_KEYS, $params),
        ], $options));
    }

    /**
     * Report which numbers can receive RCS from an agent
     *
     * @param string $agentId The agent ID
     * @param array $phoneNumbers Up to 500 numbers, 10-15 digits, with or without the +
     * @param array $options Additional request options
     * @return array The response whose data is { results: [{ phoneNumber, capability, rcsEnabled, reason, features, carrier, checkedAt, cached }] },
     *     capability being ENABLED, UNREACHABLE or UNKNOWN, and reason CAPABLE, NOT_CAPABLE, NOT_LAUNCHED_ON_CARRIER or UNKNOWN; rcsEnabled is true only for CAPABLE, carrier and checkedAt may be null, and features is empty today
     * @throws ValidationException If agentId or phoneNumbers is missing
     */
    public function capabilities(string $agentId, array $phoneNumbers, array $options = []): array
    {
        $this->client->require(['agentId' => $agentId]);
        if ($phoneNumbers === []) {
            throw new ValidationException('phoneNumbers');
        }
        return $this->client->request('/rcs/capabilities', array_merge([
            'method' => 'POST',
            'body' => ['agentId' => $agentId, 'phoneNumbers' => $phoneNumbers],
        ], $options));
    }

    /**
     * Fake an inbound event from an approved tester of the agent, with no carrier involved: it runs the real inbound handlers and webhooks, as free test traffic. A simulated STOP really opts the tester out; a simulated START clears it. Anyone else gets a 403 with code NOT_TESTER. Returns 202 with { status, messageId }.
     *
     * @param string $agentId The agent ID
     * @param string $from The approved tester's number
     * @param string $type TEXT, SUGGESTION, LOCATION, FILE or UNSUBSCRIBE
     * @param array $fields text (required for TEXT), postbackData (SUGGESTION needs this or buttonId), messageId, buttonId,
     *     latitude and longitude (required for LOCATION), fileName
     * @param array $options Additional request options
     * @return array The response whose data is ['status' => 'QUEUED', 'messageId']
     * @throws ValidationException If agentId, from or type is missing
     */
    public function simulateInbound(string $agentId, string $from, string $type, array $fields = [], array $options = []): array
    {
        $this->client->require(['agentId' => $agentId, 'from' => $from, 'type' => $type]);
        return $this->client->request('/message/rcs/simulate-inbound', array_merge([
            'method' => 'POST',
            'body' => array_merge(['agentId' => $agentId, 'from' => $from, 'type' => $type], self::body(['text', 'postbackData', 'messageId', 'buttonId', 'latitude', 'longitude', 'fileName'], $fields)),
        ], $options));
    }

    /**
     * Get one message by its ID. The same route as $sdk->messages->getMessage.
     *
     * @param string $messageId The message ID
     * @param array $options Additional request options
     * @return array The response whose data is the message; an unknown ID is a 404
     * @throws ValidationException If messageId is missing
     */
    public function get(string $messageId, array $options = []): array
    {
        $this->client->require(['messageId' => $messageId]);
        $safeMessageId = rawurlencode($messageId);
        return $this->client->request("/message/{$safeMessageId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * List RCS messages. Sends GET /message with messageType RCS. A fallback leg is an SMS or MMS message: read it with
     * $sdk->messages->getMessage and the parent's fallbackMessageId.
     *
     * @param array $params groupId, from (ISO-8601 date or timestamp, sent as startDate), to (sent as endDate), agentId?
     *     (sent as registrationId), status? (ENQUEUED, DEQUEUED, SENT, FAILED, DELIVERED, READ, EXPIRED, FALLBACK or
     *     BLOCKED), page?, limit?, templateId?, templateVersion?, bulkId? (the messages of one send), fallbackReason?
     *     (NOT_CAPABLE, CARRIER_NOT_LAUNCHED, TTL_EXPIRED, RCS_ERROR, PROVIDER_ERROR or SMS_ONLY), billingClass? (RICH or
     *     RICH_MEDIA), deliveredChannel? (rcs, sms or mms), isTest? (true for test messages only, false to leave them
     *     out)
     * @param array $options Additional request options
     * @return array The response from the server
     * @throws ValidationException If groupId, from or to is missing
     */
    public function list(array $params, array $options = []): array
    {
        $this->client->require(['groupId' => $params['groupId'] ?? null, 'from' => $params['from'] ?? null, 'to' => $params['to'] ?? null]);
        $queryString = $this->client->getQueryString([
            'registrationId' => $params['agentId'] ?? null,
            'startDate' => $params['from'] ?? null,
            'endDate' => $params['to'] ?? null,
            'groupId' => $params['groupId'] ?? null,
            'status' => $params['status'] ?? null,
            'page' => $params['page'] ?? null,
            'limit' => $params['limit'] ?? null,
            'templateId' => $params['templateId'] ?? null,
            'templateVersion' => $params['templateVersion'] ?? null,
            'bulkId' => $params['bulkId'] ?? null,
            'fallbackReason' => $params['fallbackReason'] ?? null,
            'billingClass' => $params['billingClass'] ?? null,
            'deliveredChannel' => $params['deliveredChannel'] ?? null,
            'isTest' => $params['isTest'] ?? null,
            'messageType' => 'RCS',
            'dateBounds' => 'exact',
        ]);
        return $this->client->request("/message{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Pick the given keys from the params: unset or null keys are omitted, and an empty array under an object key becomes
     * an empty object.
     *
     * @param array $keys The body keys
     * @param array $params The caller's params
     * @return array The request body
     */
    private static function body(array $keys, array $params): array
    {
        $body = [];
        foreach ($keys as $key) {
            if (isset($params[$key])) {
                $value = $params[$key];
                $body[$key] = $value === [] && in_array($key, self::OBJECT_KEYS, true) ? (object) [] : $value;
                if ($key === 'recipients') {
                    $body[$key] = array_map([self::class, 'recipient'], $value);
                }
            }
        }
        return $body;
    }

    /**
     * Send a recipient's empty maps as {} rather than [].
     *
     * @param array $recipient The recipient
     * @return array The recipient
     */
    private static function recipient(array $recipient): array
    {
        foreach (self::RECIPIENT_OBJECT_KEYS as $key) {
            if (isset($recipient[$key]) && $recipient[$key] === []) {
                $recipient[$key] = (object) [];
            }
        }
        return $recipient;
    }
}
