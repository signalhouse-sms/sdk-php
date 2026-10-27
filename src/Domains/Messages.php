<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

/**
 * Messages domain.
 *
 * Date-range parameters (startDate, endDate) on list and analytics methods
 * accept ISO-8601 date or timestamp strings, bound per dateBounds. A bare date is
 * always that whole UTC day. A timestamp is widened to its whole UTC day in "day"
 * mode and bound as the instant it names, in whatever
 * offset it was written ("2026-09-14T00:00:00-04:00" is 04:00Z), in "exact" mode,
 * where day buckets also align to the offset endDate carries. Omitted, dateBounds is "day".
 * Business-number filters and SMS/MMS senders accept digits-only 5-6 digit
 * Short Codes or 10+ digit long numbers. Recipient numbers remain 10+ digits.
 */
class Messages
{
    private HttpClient $client;
    private HttpClient $multipartClient;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, HttpClient $multipartClient, bool $enableAdmin)
    {
        $this->client = $client;
        $this->multipartClient = $multipartClient;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Build the query string for a regional read, JSON-encoding an array regionScopes
     *
     * @param array $params Filter parameters, possibly holding a regionScopes array
     * @return string The query string
     */
    private function reportingQueryString(array $params): string
    {
        if (isset($params['regionScopes']) && is_array($params['regionScopes'])) {
            $params['regionScopes'] = json_encode($params['regionScopes'], JSON_THROW_ON_ERROR);
        }
        return $this->client->getQueryString($params);
    }

    /**
     * Estimate Canadian or UK retail cost in microdollars; no charge or send. A Ready Canadian sender takes SMS or
     * MMS to Canadian or US recipients; a UK (+44) virtual long code takes SMS only to GB mobile recipients (10-15
     * digits). The response region is "CA" or "GB". Unavailable unless that region is enabled for the environment.
     * The sender may also be a READY alphanumeric sender ID (e.g. "ACME"; UK recipients, SMS only); its estimate
     * counts the "\nOpt out: sihou.io/o/{code}" footer the server appends to every such message.
     */
    public function estimateMessage(string $senderPhoneNumber, array $recipientPhoneNumbers, string $messageBody, array $options = [], string $messageType = 'SMS'): array
    {
        return $this->client->request('/message/estimate', array_merge(['method' => 'POST', 'body' => compact('senderPhoneNumber', 'recipientPhoneNumbers', 'messageBody', 'messageType')], $options));
    }

    /**
     * Get a list of messages with optional filters and pagination
     *
     * @param array $params Filter parameters. messageType accepts a single value or array; allowed
     *     values are SMS, MMS, RCS, WHATSAPP, VIBER, and P2P. channel accepts "tenDLC", "virtualLongCode",
     *     "tollFree", "shortCode", or "p2p" as a single value or array. Older messages without
     *     a stored channel count as tenDLC. sentimentLabel filters by sentiment bucket and accepts
     *     "positive", "neutral", or "negative" as a single value or array; it is applied as the
     *     sentimentScore range the label threshold produces, so unscored messages match no bucket and
     *     outbound messages are excluded. errorCode filters FAILED messages by Signal House error code
     *     (single value or array); "OUT" is the campaign opt-out, displayed as RECIPIENT_OPTED_OUT.
     *     senderPhoneNumber/recipientPhoneNumber accept a plain digits-only exact value, or a value
     *     containing % as a wildcard for a partial match (e.g. "%1234" matches numbers ending in
     *     1234); 3-15 digits are required alongside any %.
     *     sortField accepts "createdAt", "segmentCount",
     *     "status", or "sentimentScore".
     *     registrationId filters to messages attributed to these registrations; single value or array.
     *     It cannot be combined with campaignId, and each id must belong to the group.
     *     dateBounds is which contract binds startDate/endDate. "day": each bound is widened to its
     *     whole UTC day. "exact": a timestamp is bound as the instant it
     *     names and a bare date is its whole UTC day. Omitted: "day".
     *     region filters by "US", "GB", or "CA" (any case of "UK"/"GB"/"GBR"/"United Kingdom" is accepted for GB) as a single value or array.
     *     regionScopes is an array of OR-ed branches ['region', 'channels', 'brandId'?, 'campaignId'?,
     *     'phoneNumber'?] (channels: tenDLC, virtualLongCode, tollFree, shortCode, p2p), JSON-encoded for
     *     the query; a string is sent as-is and [] matches nothing.
     * @param array $options Additional request options
     * @return array The response from the server. Inbound messages also carry sentimentScore
     *     (-100..100, null when unscored), sentimentLabel ("positive", "neutral" or "negative",
     *     null when sentimentScore is null) and sentimentScoredAt. Outbound messages are never scored.
     */
    public function getMessages(array $params = [], array $options = []): array
    {
        $queryString = $this->reportingQueryString($params);
        return $this->client->request("/message{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get aggregated analytics for messages with optional filters
     *
     * @param array $params Filter parameters. channel accepts "tenDLC", "virtualLongCode", "tollFree", "shortCode",
     *     or "p2p" as a single value or array. A tenDLC selection includes older messages with no
     *     channel; p2p is matched by carrier. subgroupId, brandId, campaignId, phoneNumber and
     *     carrier each accept a single value, an array, or a comma-separated string; groupId is
     *     single-valued.
     *     registrationId filters to traffic attributed to these registrations (hourly analytics only;
     *     a 400 while hourly reads are off); single value or array. A phoneNumber filter takes precedence.
     *     messageType scopes the SENTIMENT figures to SMS, MMS or P2P (single value, array, or
     *     comma-separated string); omit for all scored types. It does NOT narrow the message counts,
     *     which are always returned split per type.
     *     carrierFamily filters by the recipient's resolved carrier family (Default, ATT, TMobile, Verizon, USCellular, GoogleVoice, ClearSky, Interop, RogueMobile, P2P, Standard, IceWireless);
     *     single value, array, or comma-separated string. Only accepted where the environment serves hourly analytics; elsewhere any value is rejected with a 400 -- omit the parameter unless it is wanted.
     *     dateBounds is which contract binds startDate/endDate. "day": each bound is widened to its
     *     whole UTC day. "exact": a timestamp is bound as the instant it
     *     names and a bare date is its whole UTC day. Omitted: "day".
     *     region filters by "US", "GB", or "CA" (any case of "UK"/"GB"/"GBR"/"United Kingdom" is accepted for GB) as a single value or array.
     *     regionScopes is an array of OR-ed branches ['region', 'channels', 'brandId'?, 'campaignId'?,
     *     'phoneNumber'?] (channels: tenDLC, virtualLongCode, tollFree, shortCode, p2p), JSON-encoded for
     *     the query; a string is sent as-is and [] matches nothing. Both are accepted only where the
     *     environment serves hourly analytics; elsewhere any value is rejected with a 400.
     * @param array $options Additional request options
     * @return array The response from the server. Also includes inbound-message sentiment for the
     *     requested scope: sentimentScore (volume-weighted average of every scored inbound message
     *     in range, -100..100, null when nothing was scored), sentimentLabel, sentimentScoredCount,
     *     sentimentPositive, sentimentNeutral and sentimentNegative. sentimentScore is an average,
     *     so it must never be summed across responses.
     */
    public function getAnalytics(array $params = [], array $options = []): array
    {
        $queryString = $this->reportingQueryString($params);
        return $this->client->request("/message/analytics{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get detailed analytics snapshot records for charting and aggregation
     *
     * @param array $params Filter parameters. channel accepts "tenDLC", "virtualLongCode", "tollFree", "shortCode",
     *     or "p2p" as a single value or array. A tenDLC selection includes older messages with no
     *     channel; p2p is a first-class channel. subgroupId, brandId, campaignId, phoneNumber and
     *     carrier each accept a single value, an array, or a comma-separated string; groupId is
     *     single-valued.
     *     registrationId filters to traffic attributed to these registrations (hourly analytics only;
     *     a 400 while hourly reads are off); single value or array. A phoneNumber filter takes precedence.
     *     messageType scopes the SENTIMENT figures to SMS, MMS or P2P (single value, array, or
     *     comma-separated string); omit for all scored types. It does NOT narrow the message counts,
     *     which are always returned split per type.
     *     carrierFamily filters by the recipient's resolved carrier family (Default, ATT, TMobile, Verizon, USCellular, GoogleVoice, ClearSky, Interop, RogueMobile, P2P, Standard, IceWireless);
     *     single value, array, or comma-separated string. Only accepted where the environment serves hourly analytics; elsewhere any value is rejected with a 400 -- omit the parameter unless it is wanted.
     *     granularity ("day" | "hour") is the time-bucket grain of the byDate rows. Omit for day (each
     *     row's _id is a "YYYY-MM-DD" date). "hour" buckets by hour (each row's _id is a
     *     "YYYY-MM-DD HH:MM:SS" UTC hour; startDate is floored to its hour and endDate honoured as
     *     given) and is capped to a 7-day span. Only accepted where the environment serves hourly
     *     analytics; elsewhere any value, including "day", is rejected with a 400 -- omit it unless
     *     hour grain is wanted.
     *     breakdown ("carrier" | "carrierFamily") adds a byBreakdown time × dimension series to the
     *     response ({dimension, rows}: each row is one byDate bucket (_id) for one carrier or carrier
     *     family (key), top 50 keys by volume, excluding P2P) and caps the span at 31 days. Only accepted where the environment serves hourly analytics; elsewhere any value is rejected with a 400 -- omit the parameter unless it is wanted.
     *     dateBounds is which contract binds startDate/endDate. "day": each bound is widened to its
     *     whole UTC day. "exact": a timestamp is bound as the instant it
     *     names and a bare date is its whole UTC day. Omitted: "day".
     *     region filters by "US", "GB", or "CA" (any case of "UK"/"GB"/"GBR"/"United Kingdom" is accepted for GB) as a single value or array.
     *     regionScopes is an array of OR-ed branches ['region', 'channels', 'brandId'?, 'campaignId'?,
     *     'phoneNumber'?] (channels: tenDLC, virtualLongCode, tollFree, shortCode, p2p), JSON-encoded for
     *     the query; a string is sent as-is and [] matches nothing. Both are accepted only where the
     *     environment serves hourly analytics; elsewhere any value is rejected with a 400.
     * @param array $options Additional request options
     * @return array The response from the server with an array of analytics snapshot records.
     *     cardTotals and every byDate/byPhoneNumber/byCarrier row also carry that row's own
     *     inbound-message sentiment: sentimentScore (volume-weighted average, -100..100, null when
     *     nothing in that row's scope was scored), sentimentLabel, sentimentScoredCount,
     *     sentimentPositive, sentimentNeutral and sentimentNegative. The fields are always present;
     *     sentimentScore is an average and must never be summed across rows or responses.
     */
    public function getAnalyticsDetail(array $params = [], array $options = []): array
    {
        $queryString = $this->reportingQueryString($params);
        return $this->client->request("/message/analytics/detail{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get per-minute send throughput (peak segments per minute, sending minutes, minutes at a
     * ceiling) per time bucket and per carrier family. Measured from SENT transitions at
     * campaign x carrier-family grain -- the grain carrier ceilings are enforced at -- so a peak
     * here is the figure a ceiling is compared against. Only accepted where the environment serves
     * hourly analytics; elsewhere the request is rejected with a 400.
     *
     * @param array $params Query parameters: groupId (required), brandId, campaignId (wins over
     *     brandId; single value, array, or comma-separated string), registrationId (filter to traffic
     *     attributed to these registrations (hourly analytics only; a 400 while hourly reads are off);
     *     single value or array), carrierFamily (ATT, TMobile,
     *     Verizon, USCellular, ...; single value, array, or comma-separated string), startDate and
     *     endDate (required, ISO-8601; both are bound per dateBounds, a bare date being its whole UTC day
     *     either way; startDate is then floored to its hour at hour granularity and to its minute at day
     *     granularity, and at day granularity in "exact" mode the buckets align to the UTC offset endDate carries; span capped at
     *     35 days), granularity ("hour" default, span capped at 7 days | "day"), ceiling (positive
     *     integer segments per minute; when given every row also reports minutesAtCeiling, otherwise
     *     minutesAtCeiling is null).
     *     dateBounds is which contract binds startDate/endDate. "day": each bound is widened to its
     *     whole UTC day. "exact": a timestamp is bound as the instant it
     *     names and a bare date is its whole UTC day. Omitted: "day".
     * @param array $options Additional request options
     * @return array The response from the server: totals, byDate, byCarrierFamily and byBreakdown
     *     ({dimension: "carrierFamily", rows}). Every row carries segments, messages,
     *     peakSegmentsPerMinute, peakMessagesPerMinute, peakMinute (UTC "YYYY-MM-DD HH:MM:SS", null
     *     when no traffic), sendingMinutes, averageSegmentsPerSendingMinute and minutesAtCeiling.
     */
    public function getAnalyticsThroughput(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/message/analytics/throughput{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get filter dropdown options (subgroups, brands, campaigns, phone numbers) for the
     * Analytics page, scoped to a single group. Only items with
     * at least one message are returned.
     *
     * @param array $params Filter parameters (groupId required). region restricts the options to "US", "GB",
     *     or "CA" (any case of "UK"/"GB"/"GBR"/"United Kingdom" is accepted for GB) as a single value or array; only accepted where the environment
     *     serves hourly analytics, elsewhere any value is rejected with a 400.
     * @param array $options Additional request options
     * @return array The response with subgroups, brands, campaigns, phoneNumbers, and carriers arrays. Each phone
     *     number carries type (TOLL_FREE, SHORT_CODE, ALPHANUMERIC, VIRTUAL_LONG_NUMBER, ...) and country
     *     (ISO-2), null when unknown: TOLL_FREE, SHORT_CODE and ALPHANUMERIC are channels by type; any other
     *     US number is 10DLC and a non-US one is a Virtual Long Code. carriers is a plain array of carrier
     *     name strings.
     */
    public function getAnalyticsFilterOptions(array $params = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($params);
        return $this->client->request("/message/analytics/filter-options{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a paginated breakdown of message metrics grouped by subgroup. Each row carries the
     * full set of sms/mms/p2p metric columns plus smsOptOuts and mmsOptOuts (sourced from
     * the DNC analytics MV — P2P has no opt-outs) so callers can apply channel toggles client-side.
     *
     * @param array $params Filter parameters. limit is capped at 50. channel accepts "both" (all
     *     activity), "tenDLC", "virtualLongCode", "tollFree", "shortCode", or "p2p" as a single value or array and
     *     scopes row inclusion and ranking by the stored channel. messageType scopes ONLY the
     *     sentiment figures (SMS, MMS or P2P, single value or array); omit for all scored types. It
     *     does not narrow the message counts, which are always returned split per type.
     *     carrierFamily filters by the recipient's resolved carrier family (Default, ATT, TMobile, Verizon, USCellular, GoogleVoice, ClearSky, Interop, RogueMobile, P2P, Standard, IceWireless);
     *     single value, array, or comma-separated string. Only accepted where the environment serves hourly analytics; elsewhere any value is rejected with a 400 -- omit the parameter unless it is wanted.
     *     registrationId filters to traffic attributed to these registrations (hourly analytics only;
     *     a 400 while hourly reads are off); single value or array. A phoneNumber filter takes precedence.
     *     dateBounds is which contract binds startDate/endDate. "day": each bound is widened to its
     *     whole UTC day. "exact": a timestamp is bound as the instant it
     *     names and a bare date is its whole UTC day. Omitted: "day".
     *     region filters by "US", "GB", or "CA" (any case of "UK"/"GB"/"GBR"/"United Kingdom" is accepted for GB) as a single value or array.
     *     regionScopes is an array of OR-ed branches ['region', 'channels', 'brandId'?, 'campaignId'?,
     *     'phoneNumber'?] (channels: tenDLC, virtualLongCode, tollFree, shortCode, p2p), JSON-encoded for
     *     the query; a string is sent as-is and [] matches nothing. Both are accepted only where the
     *     environment serves hourly analytics; elsewhere any value is rejected with a 400.
     * @param array $options Additional request options
     * @return array { rows, totalCount, page, limit }. Each row carries that subgroup's
     *     inbound-message sentiment alongside its message counters: sentimentScore
     *     (volume-weighted, -100..100, null when nothing was scored), sentimentLabel,
     *     sentimentScoredCount, sentimentPositive, sentimentNeutral, sentimentNegative, plus the
     *     same six per message type under smsSentiment*, mmsSentiment* and p2pSentiment*. The
     *     score is derived server-side; combine counts across rows, never the scores.
     */
    public function getAnalyticsBySubgroup(array $params = [], array $options = []): array
    {
        $queryString = $this->reportingQueryString($params);
        return $this->client->request("/message/analytics/by-subgroup{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a paginated breakdown of failed messages grouped by error code. Each row contains
     * per-channel (sms/mms/p2p) error counts plus an enriched description.
     *
     * @param array $params Filter parameters. limit is capped at 50. channel accepts "both" (every
     *     code), "tenDLC", "virtualLongCode", "tollFree", "shortCode", or "p2p" as a single value or array. When one
     *     channel is selected, totalErrors reflects only that channel.
     *     carrierFamily filters by the recipient's resolved carrier family (Default, ATT, TMobile, Verizon, USCellular, GoogleVoice, ClearSky, Interop, RogueMobile, P2P, Standard, IceWireless);
     *     single value, array, or comma-separated string. Only accepted where the environment serves hourly analytics; elsewhere any value is rejected with a 400 -- omit the parameter unless it is wanted.
     *     registrationId filters to traffic attributed to these registrations (hourly analytics only;
     *     a 400 while hourly reads are off); single value or array. A phoneNumber filter takes precedence.
     *     dateBounds is which contract binds startDate/endDate. "day": each bound is widened to its
     *     whole UTC day. "exact": a timestamp is bound as the instant it
     *     names and a bare date is its whole UTC day. Omitted: "day".
     *     region filters by "US", "GB", or "CA" (any case of "UK"/"GB"/"GBR"/"United Kingdom" is accepted for GB) as a single value or array.
     *     regionScopes is an array of OR-ed branches ['region', 'channels', 'brandId'?, 'campaignId'?,
     *     'phoneNumber'?] (channels: tenDLC, virtualLongCode, tollFree, shortCode, p2p), JSON-encoded for
     *     the query; a string is sent as-is and [] matches nothing. Both are accepted only where the
     *     environment serves hourly analytics; elsewhere any value is rejected with a 400.
     *     errorCode narrows the breakdown to a single failure reason: a numeric Infobip carrier code
     *     (as a string, e.g. "607"), the Signal House campaign-opt-out code "OUT", or a no-code failure
     *     class (other, moderation, att_limit, p2p_stop, campaign_opt_out, dnc_blocked, bind_hold_expired).
     *     Omitted: every reason.
     * @param array $options Additional request options
     * @return array { rows, totalCount, totalErrors: { sms, mms, p2p, all }, page, limit }
     */
    public function getAnalyticsByErrorCode(array $params = [], array $options = []): array
    {
        $queryString = $this->reportingQueryString($params);
        return $this->client->request("/message/analytics/by-error-code{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get aggregated DNC (Do Not Contact) opt-out analytics with optional filters
     *
     * @param array $params Filter parameters. channel accepts "tenDLC", "virtualLongCode", "tollFree", or "shortCode"
     *     as a single value or array. Opt-outs are A2P-only, so "p2p" applies no scope; tenDLC also
     *     includes older opt-outs with no channel.
     *     registrationId filters to traffic attributed to these registrations (hourly analytics only;
     *     a 400 while hourly reads are off); single value or array. A phoneNumber filter takes precedence.
     *     dateBounds is which contract binds startDate/endDate. "day": each bound is widened to its
     *     whole UTC day. "exact": a timestamp is bound as the instant it
     *     names and a bare date is its whole UTC day. Omitted: "day".
     *     region filters by "US", "GB", or "CA" (any case of "UK"/"GB"/"GBR"/"United Kingdom" is accepted for GB) as a single value or array.
     *     regionScopes is an array of OR-ed branches ['region', 'channels', 'brandId'?, 'campaignId'?,
     *     'phoneNumber'?] (channels: tenDLC, virtualLongCode, tollFree, shortCode, p2p), JSON-encoded for
     *     the query; a string is sent as-is and [] matches nothing. Both are accepted only where the
     *     environment serves hourly analytics; elsewhere any value is rejected with a 400.
     * @param array $options Additional request options
     * @return array The response from the server with totals, byDate, byPhoneNumber, bySenderNumber,
     *     byCampaign, bySubgroup, byType, byCarrier, byKeyword. bySenderNumber ({senderPhoneNumber,
     *     total, sms, mms}) attributes each opt-out to the first business number that received it, so
     *     it is unchanged by later campaign moves and START replies. byCampaign counts campaign-scoped
     *     opt-outs only, by the campaign at the time of the STOP; subgroup-scoped opt-outs (non-US
     *     recipients) are never attributed to a campaign and appear under bySubgroup instead. byType
     *     splits SMS/MMS. byKeyword breaks opt-outs down by the normalized keyword that revoked consent
     *     ({optOutKeyword, total, sms, mms}); opt-outs recorded before keyword capture shipped
     *     appear as a single optOutKeyword: null bucket ("not recorded"). The distinct keyword set
     *     is open-ended (campaigns register their own keywords beyond the mandatory floor) — render
     *     top-N plus Other, never a fixed list.
     */
    public function getDncAnalytics(array $params = [], array $options = []): array
    {
        $queryString = $this->reportingQueryString($params);
        return $this->client->request("/message/dnc/analytics{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get paginated Do Not Call records with optional filters
     *
     * @param array $params Filter parameters. channel accepts "tenDLC", "virtualLongCode", "tollFree", or "shortCode"
     *     as a single value or array. Opt-outs are A2P-only, so "p2p" applies no scope; tenDLC also
     *     includes older opt-outs with no channel.
     *     registrationId filters to traffic attributed to these registrations (hourly analytics only;
     *     a 400 while hourly reads are off); single value or array. A phoneNumber filter takes precedence.
     *     dateBounds is which contract binds startDate/endDate. "day": each bound is widened to its
     *     whole UTC day. "exact": a timestamp is bound as the instant it
     *     names and a bare date is its whole UTC day. Omitted: "day".
     *     region filters by "US", "GB", or "CA" (any case of "UK"/"GB"/"GBR"/"United Kingdom" is accepted for GB) as a single value or array.
     *     regionScopes is an array of OR-ed branches ['region', 'channels', 'brandId'?, 'campaignId'?,
     *     'phoneNumber'?] (channels: tenDLC, virtualLongCode, tollFree, shortCode, p2p), JSON-encoded for
     *     the query; a string is sent as-is and [] matches nothing. Both are accepted only where the
     *     environment serves hourly analytics; elsewhere any value is rejected with a 400.
     * @param array $options Additional request options
     * @return array The response from the server with paginated DNC records. Each record carries
     *     senderPhoneNumber — the first business number that received the opt-out (set once, never
     *     changed by a later STOP or a campaign move; the same value as phoneNumber), campaignId (null
     *     on subgroup-scoped records for non-US recipients), optOutKeyword — the normalized keyword that revoked consent (e.g. "stop", "opt out"; a
     *     repeat opt-out records the most recent word), null for opt-outs recorded before keyword
     *     capture shipped — and carrierFamily, the resolved carrier family of the recipient number
     *     (Default, ATT, TMobile, Verizon, USCellular, GoogleVoice, ClearSky, Interop, RogueMobile, Standard, IceWireless),
     *     copied from the inbound message that triggered the opt-out.
     */
    public function getDncRecords(array $params = [], array $options = []): array
    {
        $queryString = $this->reportingQueryString($params);
        return $this->client->request("/message/dnc{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Send an SMS message
     *
     * The response reports the send outcome via requestedRecipientCount, enqueuedCount, and
     * failedCount. enqueuedCount is the number of recipients accepted for delivery and is 0 when the
     * send was fully blocked (by the moderation filter or a campaign opt-out); the request still
     * succeeds in that case, so treat enqueuedCount, not the HTTP status, as the signal that a message
     * was accepted. Blocked recipients are returned in insertedMessages with status "FAILED";
     * recipients on the global Do Not Contact list are reported in dncBlockedNumbers with no message
     * record. Each FAILED message carries errorCode, a stable machine-readable cause (campaign opt-out
     * is "OUT"; null on success or on failures with no assigned code) that is also included on the
     * FAILED status-callback payload.
     *
     * @param string $senderPhoneNumber Digits-only 5-6 digit Short Code or 10+ digit long number, or a READY alphanumeric sender ID (e.g. "ACME"; UK recipients and SMS only). Every message from an alphanumeric sender gets "\nOpt out: sihou.io/o/{code}" appended by the server, counted in segments.
     * @param string|array $recipientPhoneNumbers 10+ digit recipients; Short Codes permit exactly one, and it must be a +1 (US/Canada) number (a bare 10-digit number is treated as +1)
     * @param string $messageBody The message body
     * @param string|null $statusCallbackUrl Optional callback URL for status updates
     * @param bool $enableShortlink Whether to enable shortlinks
     * @param array $options Additional request options
     * @param bool $filterLandlinesAndInactiveNumbers Whether to filter out landline and inactive numbers before sending
     * @return array The response from the server
     */
    public function sendSMS(
        string $senderPhoneNumber,
        string|array $recipientPhoneNumbers,
        string $messageBody,
        ?string $statusCallbackUrl = null,
        bool $enableShortlink = false,
        array $options = [],
        bool $filterLandlinesAndInactiveNumbers = false
    ): array {
        $this->client->require([
            'senderPhoneNumber' => $senderPhoneNumber,
            'recipientPhoneNumbers' => $recipientPhoneNumbers,
            'messageBody' => $messageBody,
        ]);

        $body = [
            'senderPhoneNumber' => $senderPhoneNumber,
            'recipientPhoneNumber' => $recipientPhoneNumbers,
            'messageBody' => $messageBody,
            'enableShortlink' => $enableShortlink,
        ];

        if ($filterLandlinesAndInactiveNumbers) {
            $body['filterLandlinesAndInactiveNumbers'] = true;
        }

        if ($statusCallbackUrl !== null) {
            $body['statusCallbackUrl'] = $statusCallbackUrl;
        }

        return $this->client->request('/message/sms', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Send a P2P message via Rogue Mobile SMPP
     *
     * @param string|array $recipientPhoneNumbers The phone number(s) to send to
     * @param string $messageBody The body of the P2P message
     * @param string|null $statusCallbackUrl Optional URL to receive status callbacks
     * @param bool|null $useSignalHouseShortlinks When false, SignalHouse applies no link-shortening or text-spin and your pre-spun links/content are sent verbatim (bring-your-own spinner). Defaults to true.
     * @param string|null $groupId Explicit group to send from. Defaults to the group in your auth context.
     * @param string|null $subgroupId Explicit subgroup to attribute the send (and its inbound reply) to. P2P has no dedicated sender number, so pass this to record the send against a specific subgroup; it also resolves the group. Defaults to the group's oldest subgroup.
     * @param array $options Additional request options
     * @return array Standardized response
     */
    public function sendP2P(
        string|array $recipientPhoneNumbers,
        string $messageBody,
        ?string $statusCallbackUrl = null,
        ?bool $useSignalHouseShortlinks = null,
        ?string $groupId = null,
        ?string $subgroupId = null,
        array $options = []
    ): array {
        $this->client->require([
            'recipientPhoneNumbers' => $recipientPhoneNumbers,
            'messageBody' => $messageBody,
        ]);

        $body = [
            'recipientPhoneNumber' => $recipientPhoneNumbers,
            'messageBody' => $messageBody,
        ];

        if ($statusCallbackUrl !== null) {
            $body['statusCallbackUrl'] = $statusCallbackUrl;
        }

        if ($groupId !== null) {
            $body['groupId'] = $groupId;
        }

        if ($subgroupId !== null) {
            $body['subgroupId'] = $subgroupId;
        }

        if ($useSignalHouseShortlinks !== null) {
            $body['useSignalHouseShortlinks'] = $useSignalHouseShortlinks;
        }

        return $this->client->request('/message/p2p', array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Get the details of a P2P batch by its ID
     *
     * @param string $batchId The ID of the P2P batch to retrieve
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getP2PBatch(string $batchId, array $options = []): array
    {
        $this->client->require(['batchId' => $batchId]);
        $safeBatchId = rawurlencode($batchId);
        return $this->client->request("/message/p2p/batches/{$safeBatchId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Fetch the latest upstream carrier delivery report for a set of messages by ID
     *
     * Useful for reconciling messages stuck in a non-terminal state. Outbound SMS and MMS messages sent
     * from numbers on supported carriers are looked up upstream (reports are retained about
     * 48 hours); every other message, including P2P messages and messages sent from numbers on other
     * carriers, is returned with its stored status and upstreamReportAvailable false. The request fails
     * with a 500 when the sending numbers can't be read.
     *
     * @param string[] $messageIds The message IDs to fetch carrier delivery reports for (1-100)
     * @param array $options Additional request options
     * @return array Standardized response containing an array of carrier delivery reports
     */
    public function getDeliveryReports(array $messageIds, array $options = []): array
    {
        $this->client->require([
            'messageIds' => $messageIds,
        ]);

        return $this->client->request('/message/delivery-report', array_merge([
            'method' => 'POST',
            'body' => ['messageIds' => $messageIds],
        ], $options));
    }

    /**
     * Send an MMS message with optional media attachments
     *
     * The response reports the send outcome via requestedRecipientCount, enqueuedCount, and
     * failedCount. enqueuedCount is the number of recipients accepted for delivery and is 0 when the
     * send was fully blocked (by the moderation filter or a campaign opt-out); the request still
     * succeeds in that case, so treat enqueuedCount, not the HTTP status, as the signal that a message
     * was accepted. Blocked recipients are returned in insertedMessages with status "FAILED";
     * recipients on the global Do Not Contact list are reported in dncBlockedNumbers with no message
     * record. Each FAILED message carries errorCode, a stable machine-readable cause (campaign opt-out
     * is "OUT"; null on success or on failures with no assigned code) that is also included on the
     * FAILED status-callback payload.
     *
     * @param string $senderPhoneNumber Digits-only 5-6 digit Short Code or 10+ digit long number
     * @param array $recipientPhoneNumbers 10+ digit recipients; Short Codes permit exactly one, and it must be a +1 (US/Canada) number (a bare 10-digit number is treated as +1)
     * @param string $messageBody The message body
     * @param array|null $mediaUrls Optional media URLs
     * @param string|null $statusCallbackUrl Optional callback URL
     * @param bool $enableShortlink Whether to enable shortlinks
     * @param bool $enableCompression Whether to enable image compression
     * @param array|null $images Optional image file paths
     * @param array $options Additional request options
     * @param bool $filterLandlinesAndInactiveNumbers Whether to filter out landline and inactive numbers before sending
     * @return array The response from the server
     */
    public function sendMMS(
        string $senderPhoneNumber,
        array $recipientPhoneNumbers,
        string $messageBody,
        ?array $mediaUrls = null,
        ?string $statusCallbackUrl = null,
        bool $enableShortlink = false,
        bool $enableCompression = true,
        ?array $images = null,
        array $options = [],
        bool $filterLandlinesAndInactiveNumbers = false
    ): array {
        $this->client->require([
            'senderPhoneNumber' => $senderPhoneNumber,
            'recipientPhoneNumbers' => $recipientPhoneNumbers,
        ]);

        $multipart = [];

        if ($images !== null) {
            foreach ($images as $image) {
                $multipart[] = [
                    'name' => 'images',
                    'contents' => is_string($image) ? fopen($image, 'r') : $image,
                ];
            }
        }

        $multipart[] = ['name' => 'senderPhoneNumber', 'contents' => $senderPhoneNumber];
        $multipart[] = ['name' => 'recipientPhoneNumber', 'contents' => json_encode($recipientPhoneNumbers)];
        $multipart[] = ['name' => 'messageBody', 'contents' => $messageBody];

        if ($mediaUrls !== null && count($mediaUrls) > 0) {
            $multipart[] = ['name' => 'mediaUrls', 'contents' => json_encode($mediaUrls)];
        }

        if ($statusCallbackUrl !== null) {
            $multipart[] = ['name' => 'statusCallbackUrl', 'contents' => $statusCallbackUrl];
        }

        $multipart[] = ['name' => 'enableShortlink', 'contents' => $enableShortlink ? 'true' : 'false'];
        $multipart[] = ['name' => 'enableCompression', 'contents' => $enableCompression ? 'true' : 'false'];
        if ($filterLandlinesAndInactiveNumbers) {
            $multipart[] = ['name' => 'filterLandlinesAndInactiveNumbers', 'contents' => 'true'];
        }

        return $this->multipartClient->request('/message/mms', array_merge([
            'method' => 'POST',
            'multipart' => $multipart,
        ], $options));
    }

    /**
     * Send a group MMS message
     *
     * The response reports the send outcome via requestedRecipientCount, enqueuedCount, and
     * failedCount. enqueuedCount is the number of recipients accepted for delivery and is 0 when the
     * send was fully blocked (by the moderation filter or a campaign opt-out); the request still
     * succeeds in that case, so treat enqueuedCount, not the HTTP status, as the signal that a message
     * was accepted. Blocked recipients are returned in insertedMessages with status "FAILED";
     * recipients on the global Do Not Contact list are reported in dncBlockedNumbers with no message
     * record. Each FAILED message carries errorCode, a stable machine-readable cause (campaign opt-out
     * is "OUT"; null on success or on failures with no assigned code) that is also included on the
     * FAILED status-callback payload.
     *
     * @param string $senderPhoneNumber 10+ digit long number; Short Codes do not support group messaging
     * @param array $recipientPhoneNumbers The recipient phone numbers
     * @param string $messageBody The message body
     * @param array|null $mediaUrls Optional media URLs
     * @param string|null $statusCallbackUrl Optional callback URL
     * @param bool $enableShortlink Whether to enable shortlinks
     * @param bool $enableCompression Whether to enable image compression
     * @param array|null $images Optional image file paths
     * @param array $options Additional request options
     * @param bool $filterLandlinesAndInactiveNumbers Whether to filter out landline and inactive numbers before sending
     * @return array The response from the server
     */
    public function sendGroupMessage(
        string $senderPhoneNumber,
        array $recipientPhoneNumbers,
        string $messageBody,
        ?array $mediaUrls = null,
        ?string $statusCallbackUrl = null,
        bool $enableShortlink = false,
        bool $enableCompression = true,
        ?array $images = null,
        array $options = [],
        bool $filterLandlinesAndInactiveNumbers = false
    ): array {
        $this->client->require([
            'senderPhoneNumber' => $senderPhoneNumber,
            'recipientPhoneNumbers' => $recipientPhoneNumbers,
        ]);

        $multipart = [];

        if ($images !== null) {
            foreach ($images as $image) {
                $multipart[] = [
                    'name' => 'images',
                    'contents' => is_string($image) ? fopen($image, 'r') : $image,
                ];
            }
        }

        $multipart[] = ['name' => 'senderPhoneNumber', 'contents' => $senderPhoneNumber];
        $multipart[] = ['name' => 'recipientPhoneNumber', 'contents' => json_encode($recipientPhoneNumbers)];
        $multipart[] = ['name' => 'messageBody', 'contents' => $messageBody];

        if ($mediaUrls !== null && count($mediaUrls) > 0) {
            $multipart[] = ['name' => 'mediaUrls', 'contents' => json_encode($mediaUrls)];
        }

        if ($statusCallbackUrl !== null) {
            $multipart[] = ['name' => 'statusCallbackUrl', 'contents' => $statusCallbackUrl];
        }

        $multipart[] = ['name' => 'enableShortlink', 'contents' => $enableShortlink ? 'true' : 'false'];
        $multipart[] = ['name' => 'enableCompression', 'contents' => $enableCompression ? 'true' : 'false'];
        if ($filterLandlinesAndInactiveNumbers) {
            $multipart[] = ['name' => 'filterLandlinesAndInactiveNumbers', 'contents' => 'true'];
        }

        return $this->multipartClient->request('/message/groupMessage', array_merge([
            'method' => 'POST',
            'multipart' => $multipart,
        ], $options));
    }

    /**
     * Look up the carrier for a phone number. Always fresh (not cached). Billed per request.
     *
     * @param string $phoneNumber The phone number to look up (10+ digits, no + prefix)
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function carrierIdLookup(string $phoneNumber, array $options = []): array
    {
        $this->client->require(['phoneNumber' => $phoneNumber]);

        return $this->client->request('/message/carrier-id', array_merge([
            'method' => 'POST',
            'body' => ['phoneNumber' => $phoneNumber],
        ], $options));
    }
}
