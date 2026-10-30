<?php

namespace SignalHouse\SDK;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SignalHouse\SDK\Exceptions\ValidationException;

class HttpClient
{
    private Client $client;
    private string $baseUrl;
    private string $apiKey;
    private bool $isMultipart;

    public function __construct(string $baseUrl, string $apiKey, bool $isMultipart = false)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->isMultipart = $isMultipart;

        $headers = ['Authorization' => "Bearer {$apiKey}"];
        if (!$isMultipart) {
            $headers['Content-Type'] = 'application/json';
        }

        $this->client = new Client([
            'base_uri' => $baseUrl,
            'headers' => $headers,
            'http_errors' => false,
        ]);
    }

    /**
     * Get the API base URL this client was configured with, without a trailing slash
     *
     * @return string The base URL
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Make an API request
     *
     * @param string $url The endpoint URL
     * @param array $options Request options (method, body, token, headers, multipart, idempotencyKey)
     * @return array Standardized response array
     */
    public function request(string $url, array $options = []): array
    {
        $method = $options['method'] ?? 'GET';
        $requestOptions = [];

        // Override token if provided
        if (isset($options['token'])) {
            $requestOptions['headers']['Authorization'] = "Bearer {$options['token']}";
        }

        // Additional headers
        if (isset($options['headers'])) {
            $requestOptions['headers'] = array_merge($requestOptions['headers'] ?? [], $options['headers']);
        }

        // Idempotency key: a retry with the same key and request replays the first response
        if (isset($options['idempotencyKey'])) {
            $requestOptions['headers']['Idempotency-Key'] = $options['idempotencyKey'];
        }

        // Request body. Multipart callers pass `multipart` without a `body`, so it must be
        // checked on its own; nesting it under `body` sent every multipart call empty.
        if (isset($options['multipart'])) {
            $requestOptions['multipart'] = $options['multipart'];
        } elseif (isset($options['body'])) {
            $requestOptions['json'] = $options['body'];
        }

        try {
            $response = $this->client->request($method, $url, $requestOptions);
            $statusCode = $response->getStatusCode();
            $body = json_decode($response->getBody()->getContents(), true);

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'success' => true,
                    'data' => $body,
                    'status' => $statusCode,
                ];
            }

            return [
                'success' => false,
                'error' => $body['message'] ?? $body ?? 'Unknown error',
                'status' => $statusCode,
            ];
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => null,
            ];
        }
    }

    /**
     * Convert an associative array to a query string, excluding null/undefined values and 'options' key
     *
     * @param array $params The parameters to convert
     * @return string The query string (including leading '?' if non-empty)
     */
    public function getQueryString(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null || $key === 'options') {
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = urlencode($key) . '=' . urlencode((string) $v);
                }
            } elseif (is_bool($value)) {
                $parts[] = urlencode($key) . '=' . ($value ? 'true' : 'false');
            } else {
                $parts[] = urlencode($key) . '=' . urlencode((string) $value);
            }
        }

        if (empty($parts)) {
            return '';
        }

        return '?' . implode('&', $parts);
    }

    /**
     * Validate that required fields are present and non-empty
     *
     * @param array $fields Associative array of field names => values
     * @throws ValidationException If any required field is missing
     */
    public function require(array $fields): void
    {
        foreach ($fields as $name => $value) {
            if ($value === null || $value === '') {
                throw new ValidationException($name);
            }
        }
    }
}
