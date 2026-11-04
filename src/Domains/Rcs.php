<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;
use SignalHouse\SDK\Domains\Rcs\Agents;

/**
 * RCS (Google RBM) resources. Templates for RCS live under $sdk->templates.
 *
 * Sub-resources:
 *  - $sdk->rcs->agents — RCS agents (/rcs/agent): drafts linked to a 10DLC brand and campaign, validate, submit,
 *    archive, logo and hero upload
 */
class Rcs
{
    public Agents $agents;

    public function __construct(HttpClient $client, HttpClient $multipartClient)
    {
        $this->agents = new Agents($client, $multipartClient);
    }
}
