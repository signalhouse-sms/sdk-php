<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\Exceptions\SignalHouseException;
use SignalHouse\SDK\Exceptions\ValidationException;
use SignalHouse\SDK\HttpClient;

/**
 * Versioned message templates: drafts, publication, archiving, validation and template media
 */
class Templates
{
    use TemplatePayloads;

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
     * Create a template whose first draft (version 1) holds the given body
     *
     * @param array $params groupId, name (1-120 chars, unique per group and channel), body (the raw template body, e.g. an
     *     RcsTemplateBody), tag? (1-60 chars)
     * @param array $options Additional request options
     * @return array The response whose data is ['head', 'version'] (201)
     * @throws ValidationException If groupId, name or body is missing
     */
    public function create(array $params, array $options = []): array
    {
        $body = $this->createBody($params);
        return $this->client->request("/template", array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * List a group's templates with optional filters and pagination
     *
     * @param array $params groupId, state? (DRAFT|PUBLISHED|ARCHIVED), channel? ("rcs"), tag?, search?,
     *     sortBy? (name|createdAt|updatedAt), sortOrder? (asc|desc), page? (default 1), limit? (default 25, max 100)
     * @param array $options Additional request options
     * @return array The response whose data is ['data' => TemplateSummary[], 'total', 'page', 'limit']
     * @throws ValidationException If groupId is missing
     */
    public function list(array $params, array $options = []): array
    {
        $queryString = $this->listQuery($params);
        return $this->client->request("/template{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get a template's head and one of its versions
     *
     * @param string $templateId The template ID
     * @param array $params groupId, version? (a number, "draft" or "published"; omitted reads the published version, else the draft)
     * @param array $options Additional request options
     * @return array The response whose data is ['head', 'version']
     * @throws ValidationException If templateId or groupId is missing
     */
    public function get(string $templateId, array $params, array $options = []): array
    {
        $queryString = $this->templateQuery($templateId, $params, ['version']);
        $safeTemplateId = rawurlencode($templateId);
        return $this->client->request("/template/{$safeTemplateId}{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Save the template's draft, creating one from the published version when none exists
     *
     * @param string $templateId The template ID
     * @param array $params groupId, name?, tag? (a key set to null clears the tag; an absent key keeps it), body? (full
     *     replacement). Writes apply directly (last write wins); an archived template answers 409 TEMPLATE_ARCHIVED and a
     *     taken name 409 TEMPLATE_NAME_TAKEN
     * @param array $options Additional request options
     * @return array The response whose data is ['head', 'version']
     * @throws ValidationException If templateId or groupId is missing
     */
    public function update(string $templateId, array $params, array $options = []): array
    {
        $body = $this->updateBody($templateId, $params);
        $safeTemplateId = rawurlencode($templateId);
        return $this->client->request("/template/{$safeTemplateId}", array_merge([
            'method' => 'PUT',
            'body' => $body,
        ], $options));
    }

    /**
     * Publish the template's current draft
     *
     * @param string $templateId The template ID
     * @param array $params groupId
     * @param array $options Additional request options
     * @return array The response whose data is ['head', 'version']; an incomplete draft answers 400 TEMPLATE_INVALID,
     *     no draft 409 NO_DRAFT, a draft that changed while being published 409 DRAFT_CHANGED (publish again) and an
     *     archived template 409 TEMPLATE_ARCHIVED
     * @throws ValidationException If templateId or groupId is missing
     */
    public function publish(string $templateId, array $params, array $options = []): array
    {
        $body = $this->publishBody($templateId, $params);
        $safeTemplateId = rawurlencode($templateId);
        return $this->client->request("/template/{$safeTemplateId}/publish", array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Archive a template
     *
     * @param string $templateId The template ID
     * @param array $params groupId
     * @param array $options Additional request options
     * @return array The response whose data is the template head; an already archived template answers 409 TEMPLATE_ARCHIVED
     * @throws ValidationException If templateId or groupId is missing
     */
    public function archive(string $templateId, array $params, array $options = []): array
    {
        $body = $this->archiveBody($templateId, $params);
        $safeTemplateId = rawurlencode($templateId);
        return $this->client->request("/template/{$safeTemplateId}/archive", array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * List a template's versions without their bodies
     *
     * @param string $templateId The template ID
     * @param array $params groupId, page?, limit?
     * @param array $options Additional request options
     * @return array The response whose data is ['data' => VersionSummary[], 'total', 'page', 'limit']
     * @throws ValidationException If templateId or groupId is missing
     */
    public function versions(string $templateId, array $params, array $options = []): array
    {
        $queryString = $this->templateQuery($templateId, $params, ['page', 'limit']);
        $safeTemplateId = rawurlencode($templateId);
        return $this->client->request("/template/{$safeTemplateId}/versions{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Run publication validation against a candidate body or a stored version without saving anything
     *
     * @param string $templateId The template ID
     * @param array $params groupId and exactly one of body (a candidate body) or version (same selector as get)
     * @param array $options Additional request options
     * @return array The response whose data is ['valid', 'contentHash', 'validatorVersion', 'errors', 'warnings']
     * @throws ValidationException If templateId or groupId is missing
     * @throws SignalHouseException If not exactly one of body or version is given
     */
    public function validate(string $templateId, array $params, array $options = []): array
    {
        $body = $this->validateBody($templateId, $params);
        $safeTemplateId = rawurlencode($templateId);
        return $this->client->request("/template/{$safeTemplateId}/validate", array_merge([
            'method' => 'POST',
            'body' => $body,
        ], $options));
    }

    /**
     * Get the template features, limits and media rules the server enforces, optionally for one provider
     *
     * @param array $params groupId, channel? ("rcs", the default), provider? (infobip|sinch; adds that provider's
     *     limits and unsupported features)
     * @param array $options Additional request options
     * @return array The response whose data is the capabilities document
     * @throws ValidationException If groupId is missing
     */
    public function capabilities(array $params, array $options = []): array
    {
        $queryString = $this->capabilitiesQuery($params);
        return $this->client->request("/template/capabilities{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Upload one media file for use in template bodies and get its public URL
     *
     * @param array $params groupId, file (a file path or an open resource/stream), fileName? (the part's filename)
     * @param array $options Additional request options
     * @return array The response whose data is ['mediaId', 'groupId', 'url', 'bytes', 'mimeType', 'kind', 'width',
     *     'height', 'createdAt'] (201)
     * @throws ValidationException If groupId or file is missing
     */
    public function uploadMedia(array $params, array $options = []): array
    {
        $multipart = $this->mediaParts($params);
        return $this->multipartClient->request("/template/media", array_merge([
            'method' => 'POST',
            'multipart' => $multipart,
        ], $options));
    }
}
