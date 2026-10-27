<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\HttpClient;

class Landings
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
     * Get details of a landing page by its ID
     *
     * @param string $landingId The ID of the landing page
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getLandings(string $landingId, array $options = []): array
    {
        $this->client->require(['landingId' => $landingId]);
        $safeLandingId = rawurlencode($landingId);
        return $this->client->request("/landing/{$safeLandingId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a public (published) landing page by its ID. This endpoint is public and does not require authentication.
     *
     * @param string $landingId The ID of the public landing page
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getPublicLanding(string $landingId, array $options = []): array
    {
        $this->client->require(['landingId' => $landingId]);
        $safeLandingId = rawurlencode($landingId);
        return $this->client->request("/landing/public/{$safeLandingId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a landing page by its associated brand ID
     *
     * @param string $brandId The brand ID
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getLandingByBrandId(string $brandId, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/landing/brand/{$safeBrandId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a brand's Toll-Free landing page template
     *
     * The template is the reusable look every page the brand generates snapshots. Returns an empty
     * array when the brand has no template.
     *
     * @param string $brandId The brand ID to look up the template for
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function getLandingTemplate(string $brandId, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $safeBrandId = rawurlencode($brandId);
        return $this->client->request("/landing/template/{$safeBrandId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Create or replace a brand's Toll-Free landing page template
     *
     * Editing a template never alters a page that already exists, so a URL already given to a
     * carrier keeps rendering what was declared.
     *
     * @param string $brandId The brand the template belongs to
     * @param array $templateData userDescription (the customer's own sentence, not the finished
     *   About paragraph -- the services clause is added per campaign), the four colors, webhookUrl
     * @param string|resource|null $file Logo file path or resource. Required the first time only;
     *   omit it on a later save to keep the stored logo
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function upsertLandingTemplate(string $brandId, array $templateData, mixed $file = null, array $options = []): array
    {
        $this->client->require(['brandId' => $brandId]);
        $safeBrandId = rawurlencode($brandId);

        $multipart = [];

        if ($file !== null) {
            $multipart[] = [
                'name' => 'file',
                'contents' => is_string($file) ? fopen($file, 'r') : $file,
            ];
        }

        foreach ($templateData as $key => $value) {
            if ($value !== null) {
                $multipart[] = [
                    'name' => $key,
                    'contents' => is_array($value) ? json_encode($value) : (string) $value,
                ];
            }
        }

        return $this->multipartClient->request("/landing/template/{$safeBrandId}", array_merge([
            'method' => 'PUT',
            'multipart' => $multipart,
        ], $options));
    }

    /**
     * Create a new landing page with data and optional logo file
     *
     * @param array $landingData The landing page data (brandId, description, colors, etc.).
     *   Optionally set registrationType to "TOLL_FREE" and supply tollFree
     *   {useCases, channels, consentDisclosure, consentBoxes[{useCase, channel, text}]} to build a
     *   Toll-Free page, which renders one consent checkbox per use case per channel. Set
     *   useBrandTemplate to true (Toll-Free only) to build the page from the brand's landing page
     *   template, taking its colors, logo and webhook URL from there; no logo file is then required.
     * @param string|resource|null $file Optional logo file path or resource
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function createLanding(array $landingData, mixed $file = null, array $options = []): array
    {
        $multipart = [];

        if ($file !== null) {
            $multipart[] = [
                'name' => 'file',
                'contents' => is_string($file) ? fopen($file, 'r') : $file,
            ];
        }

        foreach ($landingData as $key => $value) {
            if ($value !== null) {
                $multipart[] = [
                    'name' => $key,
                    'contents' => is_array($value) ? json_encode($value) : (string) $value,
                ];
            }
        }

        return $this->multipartClient->request('/landing', array_merge([
            'method' => 'POST',
            'multipart' => $multipart,
        ], $options));
    }

    /**
     * Update an existing landing page
     *
     * @param string $landingId The ID of the landing page
     * @param array $landingData The data to update. Set useBrandTemplate to true (Toll-Free only)
     *   to re-copy the brand's landing page template into this page rather than creating a second one.
     * @param string|resource|null $file Optional new logo file
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function updateLanding(string $landingId, array $landingData, mixed $file = null, array $options = []): array
    {
        $this->client->require(['landingId' => $landingId]);
        $safeLandingId = rawurlencode($landingId);

        $multipart = [];

        if ($file !== null) {
            $multipart[] = [
                'name' => 'file',
                'contents' => is_string($file) ? fopen($file, 'r') : $file,
            ];
        }

        foreach ($landingData as $key => $value) {
            if ($value !== null) {
                $multipart[] = [
                    'name' => $key,
                    'contents' => is_array($value) ? json_encode($value) : (string) $value,
                ];
            }
        }

        return $this->multipartClient->request("/landing/{$safeLandingId}", array_merge([
            'method' => 'PUT',
            'multipart' => $multipart,
        ], $options));
    }

    /**
     * Delete a landing page by its ID
     *
     * @param string $landingId The ID of the landing page
     * @param array $options Additional request options
     * @return array The response from the server
     */
    public function deleteLanding(string $landingId, array $options = []): array
    {
        $this->client->require(['landingId' => $landingId]);
        $safeLandingId = rawurlencode($landingId);
        return $this->client->request("/landing/{$safeLandingId}", array_merge([
            'method' => 'DELETE',
        ], $options));
    }
}
