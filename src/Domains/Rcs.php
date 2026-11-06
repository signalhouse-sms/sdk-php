<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;
use SignalHouse\SDK\Domains\Rcs\Agents;
use SignalHouse\SDK\Domains\Rcs\Messages as RcsMessages;

/**
 * RCS (Google RBM) resources. Templates for RCS live under $sdk->templates.
 *
 * Sub-resources:
 *  - $sdk->rcs->agents — RCS agents (/rcs/agent): drafts linked to a brand and campaign, validate, submit,
 *    archive, logo and hero upload
 *  - $sdk->rcs->messages — RCS messages: send, validate, estimate, capabilities, get, list
 */
class Rcs
{
    public Agents $agents;
    public RcsMessages $messages;

    public function __construct(HttpClient $client, HttpClient $multipartClient)
    {
        $this->agents = new Agents($client, $multipartClient);
        $this->messages = new RcsMessages($client);
    }
}
