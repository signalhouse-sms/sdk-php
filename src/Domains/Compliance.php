<?php

namespace SignalHouse\SDK\Domains;

use SignalHouse\SDK\Exceptions\ValidationException;
use SignalHouse\SDK\HttpClient;

/**
 * Compliance cases under /compliance. Signal House opens a case when a carrier suspends one of your objects, a carrier
 * escalates a message, or Signal House needs something from you. You read your cases, answer their requirements and
 * upload files; only Signal House opens, works and closes them.
 */
class Compliance
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
     * List the compliance cases you can see, newest first. counts.waitingOnYou is the open cases with requirements
     * still to answer. Roles: api, admin, developer, billing, user.
     *
     * @param string|null $groupId A group you can view; null uses your active group
     * @param string|null $status OPEN, LIFT_REQUESTED or CLOSED
     * @param string|null $stage new, customer, ready, lift_requested or closed
     * @param string|null $objectType CAMPAIGN, BRAND, NUMBER, RCS_AGENT or SUBGROUP
     * @param string|null $objectId The object's ID
     * @param string|null $subgroupId Only cases in this subgroup
     * @param bool|null $suspended true for cases with an open suspension or hold, false for the rest
     * @param string|null $from Opened on or after this ISO 8601 time
     * @param string|null $to Opened on or before this ISO 8601 time
     * @param string|null $sort createdAt (the API default), fixBy or severity
     * @param int|null $page Page number, from 1
     * @param int|null $limit Cases per page, 1-200
     * @param array $options Additional request options
     * @return array The response; data is {data, total, page, limit, totalPages, counts: {open, waitingOnYou}}
     */
    public function getCases(
        ?string $groupId = null,
        ?string $status = null,
        ?string $stage = null,
        ?string $objectType = null,
        ?string $objectId = null,
        ?string $subgroupId = null,
        ?bool $suspended = null,
        ?string $from = null,
        ?string $to = null,
        ?string $sort = null,
        ?int $page = null,
        ?int $limit = null,
        array $options = []
    ): array {
        $queryString = $this->client->getQueryString([
            'groupId' => $groupId,
            'status' => $status,
            'stage' => $stage,
            'objectType' => $objectType,
            'objectId' => $objectId,
            'subgroupId' => $subgroupId,
            'suspended' => $suspended,
            'from' => $from,
            'to' => $to,
            'sort' => $sort,
            'page' => $page,
            'limit' => $limit,
        ]);
        return $this->client->request("/compliance/cases{$queryString}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Get one compliance case: its stage, suspensions, tickets and requirements. Roles: api, admin, developer,
     * billing, user.
     *
     * @param string $caseId The case ID (e.g. "CASE-…")
     * @param array $options Additional request options
     * @return array The response; data is the case
     * @throws ValidationException If caseId is missing
     */
    public function getCase(string $caseId, array $options = []): array
    {
        $this->client->require(['caseId' => $caseId]);
        $safeCaseId = rawurlencode($caseId);
        return $this->client->request("/compliance/cases/{$safeCaseId}", array_merge([
            'method' => 'GET',
        ], $options));
    }

    /**
     * Answer one requirement with a note, files, or both. A RECORDS requirement needs a file or a note.
     * Roles: api, admin, developer, user.
     *
     * @param string $caseId The case ID (e.g. "CASE-…")
     * @param string $requirementId The requirement ID (e.g. "CASE-…-1")
     * @param string|null $answer What you did, up to 2,000 characters
     * @param array|null $fileIds Up to 10 fileIds from uploadFile(), on this case
     * @param array $options Additional request options
     * @return array The response; data is the case
     * @throws ValidationException If caseId or requirementId is missing
     */
    public function answerRequirement(string $caseId, string $requirementId, ?string $answer = null, ?array $fileIds = null, array $options = []): array
    {
        $this->client->require(['caseId' => $caseId, 'requirementId' => $requirementId]);
        $body = array_filter(
            ['answer' => $answer, 'fileIds' => $fileIds],
            fn ($value) => $value !== null
        );
        $safeCaseId = rawurlencode($caseId);
        $safeRequirementId = rawurlencode($requirementId);
        return $this->client->request("/compliance/cases/{$safeCaseId}/requirements/{$safeRequirementId}", array_merge([
            'method' => 'PUT',
            'body' => $body ?: (object) [],
        ], $options));
    }

    /**
     * Upload a file to a compliance case (CSV, XLSX, PDF, PNG, JPG or TXT, up to 10 MB). Pass the returned fileId in
     * fileIds when you answer the requirement. Roles: api, admin, developer, user.
     *
     * @param string $caseId The case the file answers (e.g. "CASE-…")
     * @param string|resource $file The file path or file resource
     * @param string|null $requirementId The requirement the file answers (e.g. "CASE-…-1")
     * @param string|null $fileName The part's filename. The API recognizes a CSV or TXT by its filename, so pass one for a stream that has no path
     * @param array $options Additional request options
     * @return array The response; data is {fileId, fileName, mimeType, size} (201)
     * @throws ValidationException If caseId or file is missing
     */
    public function uploadFile(string $caseId, mixed $file, ?string $requirementId = null, ?string $fileName = null, array $options = []): array
    {
        $this->client->require(['caseId' => $caseId, 'file' => $file]);

        $multipart = [['name' => 'caseId', 'contents' => $caseId]];
        if ($requirementId !== null && $requirementId !== '') {
            $multipart[] = ['name' => 'requirementId', 'contents' => $requirementId];
        }
        $filePart = ['name' => 'file', 'contents' => is_string($file) ? fopen($file, 'r') : $file];
        if ($fileName !== null && $fileName !== '') {
            $filePart['filename'] = $fileName;
        }
        $multipart[] = $filePart;

        return $this->multipartClient->request('/compliance/files', array_merge([
            'method' => 'POST',
            'multipart' => $multipart,
        ], $options));
    }

    /**
     * Download a compliance file. Roles: api, admin, developer, billing, user.
     *
     * @param string $fileId The fileId returned by uploadFile()
     * @param array $options Additional request options
     * @return array The response from the server containing the raw file bytes
     * @throws ValidationException If fileId is missing
     */
    public function getFile(string $fileId, array $options = []): array
    {
        $this->client->require(['fileId' => $fileId]);
        $safeFileId = rawurlencode($fileId);
        return $this->client->request("/compliance/files/{$safeFileId}", array_merge([
            'method' => 'GET',
        ], $options));
    }
}
