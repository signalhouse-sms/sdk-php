<?php

namespace SignalHouse\SDK;

use SignalHouse\SDK\Domains\Agents;
use SignalHouse\SDK\Domains\Auth;
use SignalHouse\SDK\Domains\Billing;
use SignalHouse\SDK\Domains\Brands;
use SignalHouse\SDK\Domains\Campaigns;
use SignalHouse\SDK\Domains\Groups;
use SignalHouse\SDK\Domains\Landings;
use SignalHouse\SDK\Domains\Templates;
use SignalHouse\SDK\Domains\Messages;
use SignalHouse\SDK\Domains\Notifications;
use SignalHouse\SDK\Domains\Numbers;
use SignalHouse\SDK\Domains\Registrations;
use SignalHouse\SDK\Domains\Onboarding;
use SignalHouse\SDK\Domains\Shortlinks;
use SignalHouse\SDK\Domains\Subgroups;
use SignalHouse\SDK\Domains\Subscriptions;
use SignalHouse\SDK\Domains\Tickets;
use SignalHouse\SDK\Domains\Users;
use SignalHouse\SDK\Domains\Voice;
use SignalHouse\SDK\Domains\Webhooks;

class SignalHouseSDK
{
    public Agents $agents;
    public Auth $auth;
    public Billing $billing;
    public Brands $brands;
    public Campaigns $campaigns;
    public Groups $groups;
    public Landings $landings;
    public Templates $templates;
    public Messages $messages;
    public Notifications $notifications;
    public Numbers $numbers;
    public Registrations $registrations;
    public Onboarding $onboarding;
    public Shortlinks $shortlinks;
    public Subgroups $subgroups;
    public Subscriptions $subscriptions;
    public Tickets $tickets;
    public Users $users;
    public Voice $voice;
    public Webhooks $webhooks;

    /**
     * Initialize the SignalHouseSDK with the required configuration
     *
     * @param string $apiKey The API key for authenticating requests
     * @param string $baseUrl The base URL for the SignalHouse API
     * @param bool $enableAdmin Whether to enable admin endpoints
     * @throws \InvalidArgumentException If apiKey or baseUrl is missing
     */
    public function __construct(string $apiKey, string $baseUrl, bool $enableAdmin = false)
    {
        if (empty($apiKey)) {
            throw new \InvalidArgumentException('API key is required to initialize SignalHouseSDK');
        }
        if (empty($baseUrl)) {
            throw new \InvalidArgumentException('Base URL is required to initialize SignalHouseSDK');
        }

        $client = new HttpClient($baseUrl, $apiKey, false);
        $multipartClient = new HttpClient($baseUrl, $apiKey, true);

        $this->agents = new Agents($client, $enableAdmin);
        $this->auth = new Auth($client, $enableAdmin);
        $this->billing = new Billing($client, $enableAdmin);
        $this->brands = new Brands($client, $multipartClient, $enableAdmin);
        $this->campaigns = new Campaigns($client, $multipartClient, $enableAdmin);
        $this->groups = new Groups($client, $enableAdmin);
        $this->landings = new Landings($client, $multipartClient, $enableAdmin);
        $this->templates = new Templates($client, $multipartClient, $enableAdmin);
        $this->messages = new Messages($client, $multipartClient, $enableAdmin);
        $this->notifications = new Notifications($client, $enableAdmin);
        $this->numbers = new Numbers($client, $multipartClient, $enableAdmin);
        $this->registrations = new Registrations($client, $enableAdmin);
        $this->onboarding = new Onboarding($client, $enableAdmin);
        $this->shortlinks = new Shortlinks($client, $enableAdmin);
        $this->subgroups = new Subgroups($client, $enableAdmin);
        $this->subscriptions = new Subscriptions($client, $enableAdmin);
        $this->tickets = new Tickets($client, $enableAdmin);
        $this->users = new Users($client, $enableAdmin);
        $this->voice = new Voice($client, $enableAdmin);
        $this->webhooks = new Webhooks($client, $enableAdmin);
    }
}
