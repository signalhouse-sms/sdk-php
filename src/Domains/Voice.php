<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;
use SignalHouse\SDK\Domains\Voice\SipTrunks;
use SignalHouse\SDK\Domains\Voice\SipProfiles;
use SignalHouse\SDK\Domains\Voice\ProgrammableVoiceProfiles;
use SignalHouse\SDK\Domains\Voice\NumberRoutes;
use SignalHouse\SDK\Domains\Voice\Calls;
use SignalHouse\SDK\Domains\Voice\CallLogs;
use SignalHouse\SDK\Domains\Voice\Analytics;
use SignalHouse\SDK\Domains\Voice\GlobalVoiceSettings;
use SignalHouse\SDK\Domains\Voice\Tokens;
use SignalHouse\SDK\Domains\Voice\CallFlows;
use SignalHouse\SDK\Domains\Voice\PushCredentials;
use SignalHouse\SDK\Domains\Voice\PushDevices;

/**
 * Voice domain — wraps the Voice service (mounted under /voice).
 *
 * Sub-resources:
 *  - $sdk->voice->sipTrunks                 — SIP trunk (peer-to-peer connections to PBX/carrier)
 *  - $sdk->voice->sipProfiles               — SIP profile / endpoint (single registerable UA)
 *  - $sdk->voice->programmableVoiceProfiles — Programmable Voice Profile (route a set of numbers through Signal House)
 *  - $sdk->voice->numberRoutes              — Per-number inbound route (overrides the profile and subgroup settings)
 *  - $sdk->voice->callFlows                 — Call flows: customer-built call trees a number or profile can run
 *  - $sdk->voice->calls                     — Outbound call origination + call log queries
 *  - $sdk->voice->callLogs                  — Account-scoped call history: list/get + presigned recording
 *  - $sdk->voice->analytics                 — Aggregated call metrics (summary tiles + byDate/byNumber/byCarrier breakdowns)
 *  - $sdk->voice->globalVoiceSettings       — Account-wide voice defaults (accepted regions, max spend, E911)
 *  - $sdk->voice->tokens                    — Mint ephemeral SIP credentials for the browser voice SDK
 *  - $sdk->voice->pushCredentials           — Mobile push: the Apple/Firebase credentials that wake your app for calls
 *  - $sdk->voice->pushDevices               — Mobile push: register a device (called by the app with its voice token)
 */
class Voice
{
    public SipTrunks $sipTrunks;
    public SipProfiles $sipProfiles;
    public ProgrammableVoiceProfiles $programmableVoiceProfiles;
    public NumberRoutes $numberRoutes;
    public Calls $calls;
    public CallLogs $callLogs;
    public Analytics $analytics;
    public GlobalVoiceSettings $globalVoiceSettings;
    public Tokens $tokens;
    public CallFlows $callFlows;
    public PushCredentials $pushCredentials;
    public PushDevices $pushDevices;

    public function __construct(HttpClient $client, bool $enableAdmin)
    {
        $this->sipTrunks = new SipTrunks($client, $enableAdmin);
        $this->sipProfiles = new SipProfiles($client, $enableAdmin);
        $this->programmableVoiceProfiles = new ProgrammableVoiceProfiles($client, $enableAdmin);
        $this->numberRoutes = new NumberRoutes($client, $enableAdmin);
        $this->calls = new Calls($client, $enableAdmin);
        $this->callLogs = new CallLogs($client, $enableAdmin);
        $this->analytics = new Analytics($client, $enableAdmin);
        $this->globalVoiceSettings = new GlobalVoiceSettings($client, $enableAdmin);
        $this->tokens = new Tokens($client, $enableAdmin);
        $this->callFlows = new CallFlows($client, $enableAdmin);
        $this->pushCredentials = new PushCredentials($client, $enableAdmin);
        $this->pushDevices = new PushDevices($client, $enableAdmin);
    }
}
