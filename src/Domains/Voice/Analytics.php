<?php

namespace SignalHouse\SDK\Domains\Voice;

use SignalHouse\SDK\HttpClient;

/**
 * Voice Analytics — aggregated call metrics for the portal Voice Analytics view
 * (Voice service /voice/stats/voice-analytics). Returns per-direction summary
 * tiles (both directions) plus byDate / byNumber / byCarrier / byChannel status breakdowns
 * for charts. Distinct from voice.callLogs, which is the raw call history.
 *
 * Filters (camelCase): dateFrom, dateTo, groupBy (day|week|month),
 * direction (INBOUND|OUTBOUND), callSource (comma list), subgroupId, number,
 * carrier. Summary tiles honor every filter except direction; breakdowns honor
 * all filters.
 *
 * Routes are mounted under /voice/stats on the Voice service.
 */
class Analytics
{
    private HttpClient $client;
    private bool $enableAdmin;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->client = $client;
        $this->enableAdmin = $enableAdmin;
    }

    /**
     * Get aggregated voice analytics for the current account.
     *
     * Optional filters: dateFrom, dateTo, groupBy, direction, callSource,
     * subgroupId, number, carrier.
     *
     * GET /voice/stats/voice-analytics
     */
    public function get(array $filters = [], array $options = []): array
    {
        $queryString = $this->client->getQueryString($filters);
        return $this->client->request("/voice/stats/voice-analytics{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }
}
