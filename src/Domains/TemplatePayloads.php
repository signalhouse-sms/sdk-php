<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\Exceptions\SignalHouseException;
use SignalHouse\SDK\Exceptions\ValidationException;

/**
 * Validation, request bodies and query strings for Templates. The using class provides $this->client.
 */
trait TemplatePayloads
{
    /**
     * Validate and build a create body, omitting unset optionals
     *
     * @param array $params groupId, name, body, tag?
     * @return array The JSON request body
     * @throws ValidationException If groupId, name or body is missing
     */
    protected function createBody(array $params): array
    {
        $this->client->require([
            'groupId' => $params['groupId'] ?? null,
            'name' => $params['name'] ?? null,
            'body' => $params['body'] ?? null,
        ]);
        $body = ['groupId' => $params['groupId'], 'name' => $params['name'], 'body' => $params['body']];
        if (isset($params['tag'])) {
            $body['tag'] = $params['tag'];
        }
        return $body;
    }

    /**
     * Validate and build a list query string
     *
     * @param array $params groupId, state?, channel?, tag?, search?, sortBy?, sortOrder?, page?, limit?
     * @return string The query string
     * @throws ValidationException If groupId is missing
     */
    protected function listQuery(array $params): string
    {
        $this->client->require(['groupId' => $params['groupId'] ?? null]);
        return $this->client->getQueryString([
            'groupId' => $params['groupId'],
            'state' => $params['state'] ?? null,
            'channel' => $params['channel'] ?? null,
            'tag' => $params['tag'] ?? null,
            'search' => $params['search'] ?? null,
            'sortBy' => $params['sortBy'] ?? null,
            'sortOrder' => $params['sortOrder'] ?? null,
            'page' => $params['page'] ?? null,
            'limit' => $params['limit'] ?? null,
        ]);
    }

    /**
     * Validate a per-template read and build its query string from groupId plus the given optional keys
     *
     * @param string $templateId The template ID
     * @param array $params groupId and the optional keys
     * @param array $keys The optional query keys to forward, e.g. ['version']
     * @return string The query string
     * @throws ValidationException If templateId or groupId is missing
     */
    protected function templateQuery(string $templateId, array $params, array $keys): string
    {
        $this->client->require(['templateId' => $templateId, 'groupId' => $params['groupId'] ?? null]);
        $query = ['groupId' => $params['groupId']];
        foreach ($keys as $key) {
            $query[$key] = $params[$key] ?? null;
        }
        return $this->client->getQueryString($query);
    }

    /**
     * Validate and build an update body; a null tag clears the tag while an absent key keeps it
     *
     * @param string $templateId The template ID
     * @param array $params groupId, name?, tag?, body?
     * @return array The JSON request body
     * @throws ValidationException If templateId or groupId is missing
     */
    protected function updateBody(string $templateId, array $params): array
    {
        $this->client->require(['templateId' => $templateId, 'groupId' => $params['groupId'] ?? null]);
        $body = ['groupId' => $params['groupId']];
        if (isset($params['name'])) {
            $body['name'] = $params['name'];
        }
        if (array_key_exists('tag', $params)) {
            $body['tag'] = $params['tag'];
        }
        if (isset($params['body'])) {
            $body['body'] = $params['body'];
        }
        return $body;
    }

    /**
     * Validate and build a publish body
     *
     * @param string $templateId The template ID
     * @param array $params groupId
     * @return array The JSON request body
     * @throws ValidationException If templateId or groupId is missing
     */
    protected function publishBody(string $templateId, array $params): array
    {
        $this->client->require(['templateId' => $templateId, 'groupId' => $params['groupId'] ?? null]);
        return ['groupId' => $params['groupId']];
    }

    /**
     * Validate and build an archive body
     *
     * @param string $templateId The template ID
     * @param array $params groupId
     * @return array The JSON request body
     * @throws ValidationException If templateId or groupId is missing
     */
    protected function archiveBody(string $templateId, array $params): array
    {
        $this->client->require(['templateId' => $templateId, 'groupId' => $params['groupId'] ?? null]);
        return ['groupId' => $params['groupId']];
    }

    /**
     * Validate and build a validate body from exactly one of body or version
     *
     * @param string $templateId The template ID
     * @param array $params groupId and exactly one of body or version
     * @return array The JSON request body
     * @throws ValidationException If templateId or groupId is missing
     * @throws SignalHouseException If not exactly one of body or version is given
     */
    protected function validateBody(string $templateId, array $params): array
    {
        $this->client->require(['templateId' => $templateId, 'groupId' => $params['groupId'] ?? null]);
        $hasBody = isset($params['body']);
        if ($hasBody === isset($params['version'])) {
            throw new SignalHouseException('Pass exactly one of body or version', 400);
        }
        return $hasBody
            ? ['groupId' => $params['groupId'], 'body' => $params['body']]
            : ['groupId' => $params['groupId'], 'version' => $params['version']];
    }

    /**
     * Validate and build a capabilities query string
     *
     * @param array $params groupId, channel?, provider?
     * @return string The query string
     * @throws ValidationException If groupId is missing
     */
    protected function capabilitiesQuery(array $params): string
    {
        $this->client->require(['groupId' => $params['groupId'] ?? null]);
        return $this->client->getQueryString([
            'groupId' => $params['groupId'],
            'channel' => $params['channel'] ?? null,
            'provider' => $params['provider'] ?? null,
        ]);
    }

    /**
     * Validate and build the multipart parts: file (named fileName, else "upload", so a stream is still sent as a file) and groupId
     *
     * @param array $params groupId, file (a file path or an open resource/stream), fileName?
     * @return array The Guzzle multipart parts
     * @throws ValidationException If groupId or file is missing
     */
    protected function mediaParts(array $params): array
    {
        $this->client->require(['groupId' => $params['groupId'] ?? null, 'file' => $params['file'] ?? null]);
        $file = $params['file'];
        $filePart = ['name' => 'file', 'contents' => is_string($file) ? fopen($file, 'r') : $file, 'filename' => $params['fileName'] ?? 'upload'];
        return [$filePart, ['name' => 'groupId', 'contents' => (string) $params['groupId']]];
    }
}
