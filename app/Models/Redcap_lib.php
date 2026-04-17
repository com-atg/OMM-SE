<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;

/**
 * Redcap_lib — Static wrapper for the REDCap REST API.
 *
 * Credentials default to env('REDCAP_URL') / env('REDCAP_TOKEN').
 * Every method accepts optional tail parameters to override them per-call:
 *   ?string $url   = null   — override REDCAP_URL for this call
 *   ?string $token = null   — override REDCAP_TOKEN for this call
 *
 * Export methods also accept:
 *   string $returnAs = 'raw'   — pass 'array' to json_decode the response
 */
class Redcap_lib
{
    // ─── Private Helpers ────────────────────────────────────────────────────

    private static function resolveUrl(?string $url): string
    {
        return $url ?? env('REDCAP_URL', '');
    }

    private static function resolveToken(?string $token): string
    {
        return $token ?? env('REDCAP_TOKEN', '');
    }

    /**
     * Drop null/empty-string values; convert booleans to '1'/'0'.
     */
    private static function normaliseParams(array $params): array
    {
        $out = [];
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $out[$k] = is_bool($v) ? ($v ? '1' : '0') : $v;
        }

        return $out;
    }

    /**
     * Convert a comma-separated param into REDCap's array-style format:
     *   "a,b,c"  →  key[0]=a & key[1]=b & key[2]=c
     */
    private static function expandArrayParam(array &$params, string $key): void
    {
        if (! empty($params[$key])) {
            foreach (explode(',', $params[$key]) as $i => $v) {
                $params["{$key}[{$i}]"] = trim($v);
            }
            unset($params[$key]);
        }
    }

    private static function initCurl(string $url): \CurlHandle
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 120,
        ]);

        return $ch;
    }

    private static function executeCurl(\CurlHandle $ch): string
    {
        $body = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('cURL error: '.$error);
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status >= 400) {
            throw new \RuntimeException("REDCap API HTTP {$status}: {$body}");
        }

        return (string) $body;
    }

    private static function post(array $params, ?string $url, ?string $token): string
    {
        $params = self::normaliseParams(
            array_merge(['token' => self::resolveToken($token)], $params)
        );
        $ch = self::initCurl(self::resolveUrl($url));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        return self::executeCurl($ch);
    }

    private static function postWithFile(
        array $params,
        string $filePath,
        string $fileName,
        string $mimeType,
        ?string $url,
        ?string $token,
    ): string {
        $params = self::normaliseParams(
            array_merge(['token' => self::resolveToken($token)], $params)
        );
        $params['file'] = new \CURLFile($filePath, $mimeType, $fileName);
        $ch = self::initCurl(self::resolveUrl($url));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

        return self::executeCurl($ch);
    }

    /**
     * Decode the response based on $returnAs and $format.
     * 'array' + format 'json'  → json_decode to PHP array
     * anything else            → raw string
     */
    private static function decode(string $response, string $format, string $returnAs): string|array
    {
        if ($returnAs === 'array' && $format === 'json') {
            return json_decode($response, true) ?? [];
        }

        return $response;
    }

    // ─── Records ────────────────────────────────────────────────────────────

    /**
     * Export records from the project.
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $type  flat|eav
     * @param  string  $records  Comma-separated record IDs to export (empty = all)
     * @param  string  $fields  Comma-separated field names to export (empty = all)
     * @param  string  $forms  Comma-separated instrument names (empty = all)
     * @param  string  $events  Comma-separated unique event names (empty = all)
     * @param  string  $rawOrLabel  raw|label
     * @param  string  $rawOrLabelHeaders  raw|label
     * @param  string  $filterLogic  REDCap logic string, e.g. [age] > 18
     * @param  string  $dateRangeBegin  YYYY-MM-DD HH:MM:SS
     * @param  string  $dateRangeEnd  YYYY-MM-DD HH:MM:SS
     * @param  string  $csvDelimiter  comma|tab|semi|pipe|caret
     * @param  string  $decimalCharacter  dot|comma
     * @param  string  $returnAs  raw|array
     * @param  string|null  $url  Override REDCAP_URL
     * @param  string|null  $token  Override REDCAP_TOKEN
     */
    public static function exportRecords(
        string $format = 'json',
        string $type = 'flat',
        string $records = '',
        string $fields = '',
        string $forms = '',
        string $events = '',
        string $rawOrLabel = 'raw',
        string $rawOrLabelHeaders = 'raw',
        bool $exportCheckboxLabel = false,
        bool $exportSurveyFields = false,
        bool $exportDataAccessGroups = false,
        string $filterLogic = '',
        string $dateRangeBegin = '',
        string $dateRangeEnd = '',
        string $csvDelimiter = '',
        string $decimalCharacter = '',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            $params = [
                'content' => 'record',
                'action' => 'export',
                'format' => $format,
                'type' => $type,
                'rawOrLabel' => $rawOrLabel,
                'rawOrLabelHeaders' => $rawOrLabelHeaders,
                'exportCheckboxLabel' => $exportCheckboxLabel,
                'exportSurveyFields' => $exportSurveyFields,
                'exportDataAccessGroups' => $exportDataAccessGroups,
                'filterLogic' => $filterLogic,
                'dateRangeBegin' => $dateRangeBegin,
                'dateRangeEnd' => $dateRangeEnd,
                'csvDelimiter' => $csvDelimiter,
                'decimalCharacter' => $decimalCharacter,
                'records' => $records,
                'fields' => $fields,
                'forms' => $forms,
                'events' => $events,
            ];
            foreach (['records', 'fields', 'forms', 'events'] as $key) {
                self::expandArrayParam($params, $key);
            }

            return self::decode(self::post($params, $url, $token), $format, $returnAs);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportRecords failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportRecords error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import records into the project.
     *
     * @param  string  $data  JSON/CSV/XML record data
     * @param  string  $format  json|csv|xml
     * @param  string  $type  flat|eav
     * @param  string  $overwriteBehavior  normal|overwrite
     * @param  bool  $backgroundProcess  Run as background job
     * @param  string  $returnContent  count|ids|auto_ids
     * @param  string  $dateFormat  YMD|MDY|DMY
     * @param  string  $csvDelimiter  comma|tab|semi|pipe|caret
     * @param  string  $returnAs  raw|array
     */
    public static function importRecords(
        string $data,
        string $format = 'json',
        string $type = 'flat',
        string $overwriteBehavior = 'normal',
        bool $forceAutoNumbering = false,
        bool $backgroundProcess = false,
        string $returnContent = 'count',
        string $dateFormat = 'YMD',
        string $csvDelimiter = '',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            $params = [
                'content' => 'record',
                'action' => 'import',
                'format' => $format,
                'type' => $type,
                'overwriteBehavior' => $overwriteBehavior,
                'forceAutoNumbering' => $forceAutoNumbering,
                'backgroundProcess' => $backgroundProcess,
                'returnContent' => $returnContent,
                'dateFormat' => $dateFormat,
                'csvDelimiter' => $csvDelimiter,
                'data' => $data,
            ];

            return self::decode(self::post($params, $url, $token), $format, $returnAs);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importRecords failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importRecords error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete records from the project.
     *
     * @param  string  $records  Comma-separated record IDs
     * @param  string  $arm  Arm number (longitudinal projects)
     * @param  string  $instrument  Instrument name (delete data for one form only)
     * @param  string  $event  Unique event name
     * @param  string  $repeatInstance  Repeat instance number
     * @param  bool  $deleteLogging  Log the deletion
     */
    public static function deleteRecords(
        string $records,
        string $arm = '',
        string $instrument = '',
        string $event = '',
        string $repeatInstance = '',
        bool $deleteLogging = false,
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            $params = [
                'content' => 'record',
                'action' => 'delete',
                'arm' => $arm,
                'instrument' => $instrument,
                'event' => $event,
                'repeat_instance' => $repeatInstance,
                'deleteLogging' => $deleteLogging,
                'records' => $records,
            ];
            self::expandArrayParam($params, 'records');

            return self::post($params, $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::deleteRecords failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap deleteRecords error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate the next auto-number record name.
     */
    public static function generateNextRecordName(?string $url = null, ?string $token = null): string
    {
        try {
            return self::post(['content' => 'generateNextRecordName'], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::generateNextRecordName failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap generateNextRecordName error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── Metadata ───────────────────────────────────────────────────────────

    /**
     * Export the project data dictionary (metadata).
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $fields  Comma-separated field names (empty = all)
     * @param  string  $forms  Comma-separated instrument names (empty = all)
     * @param  string  $returnAs  raw|array
     */
    public static function exportMetadata(
        string $format = 'json',
        string $fields = '',
        string $forms = '',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            $params = [
                'content' => 'metadata',
                'format' => $format,
                'fields' => $fields,
                'forms' => $forms,
            ];
            foreach (['fields', 'forms'] as $key) {
                self::expandArrayParam($params, $key);
            }

            return self::decode(self::post($params, $url, $token), $format, $returnAs);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportMetadata failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportMetadata error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import metadata (data dictionary) into the project.
     *
     * @param  string  $data  JSON/CSV/XML metadata
     * @param  string  $format  json|csv|xml
     */
    public static function importMetadata(
        string $data,
        string $format = 'json',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'metadata',
                'format' => $format,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importMetadata failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importMetadata error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── Project ────────────────────────────────────────────────────────────

    /**
     * Export project information (title, purpose, IRB number, etc.).
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $returnAs  raw|array
     */
    public static function exportProjectInfo(
        string $format = 'json',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(
                self::post(['content' => 'project', 'format' => $format], $url, $token),
                $format,
                $returnAs,
            );
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportProjectInfo failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportProjectInfo error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Update basic project settings (title, purpose, etc.).
     *
     * @param  string  $data  JSON/CSV/XML project settings
     * @param  string  $format  json|csv|xml
     */
    public static function importProjectSettings(
        string $data,
        string $format = 'json',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'project_settings',
                'format' => $format,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importProjectSettings failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importProjectSettings error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Export the REDCap version number.
     */
    public static function exportVersion(?string $url = null, ?string $token = null): string
    {
        try {
            return self::post(['content' => 'version'], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportVersion failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportVersion error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a new REDCap project. Requires a Super API Token.
     *
     * @param  string  $data  JSON/CSV/XML — must include project_title and purpose
     * @param  string  $format  json|csv|xml
     * @param  string  $odm  Optional CDISC ODM XML string
     * @param  string|null  $token  Super API Token
     */
    public static function createProject(
        string $data,
        string $format = 'json',
        string $odm = '',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'project',
                'action' => 'create',
                'format' => $format,
                'odm' => $odm,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::createProject failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap createProject error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Export the entire project as a CDISC ODM XML file.
     *
     * @param  bool  $returnMetadataOnly  Only include metadata, no record data
     * @param  string  $records  Comma-separated record IDs
     * @param  string  $events  Comma-separated unique event names
     * @param  string  $instruments  Comma-separated instrument names
     */
    public static function exportProjectXml(
        bool $returnMetadataOnly = false,
        string $records = '',
        string $events = '',
        string $instruments = '',
        bool $exportSurveyFields = false,
        bool $exportDataAccessGroups = false,
        string $filterLogic = '',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            $params = [
                'content' => 'project_xml',
                'returnMetadataOnly' => $returnMetadataOnly,
                'exportSurveyFields' => $exportSurveyFields,
                'exportDataAccessGroups' => $exportDataAccessGroups,
                'filterLogic' => $filterLogic,
                'records' => $records,
                'events' => $events,
                'instruments' => $instruments,
            ];
            foreach (['records', 'events', 'instruments'] as $key) {
                self::expandArrayParam($params, $key);
            }

            return self::post($params, $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportProjectXml failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportProjectXml error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── Instruments ────────────────────────────────────────────────────────

    /**
     * Export the list of instruments (data collection forms).
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $returnAs  raw|array
     */
    public static function exportInstruments(
        string $format = 'json',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(
                self::post(['content' => 'instrument', 'format' => $format], $url, $token),
                $format,
                $returnAs,
            );
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportInstruments failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportInstruments error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Export a list of the original + export field names for all fields.
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $field  Specific field name (empty = all fields)
     * @param  string  $returnAs  raw|array
     */
    public static function exportFieldNames(
        string $format = 'json',
        string $field = '',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(
                self::post([
                    'content' => 'exportFieldNames',
                    'format' => $format,
                    'field' => $field,
                ], $url, $token),
                $format,
                $returnAs,
            );
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportFieldNames failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportFieldNames error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Export a PDF of the data collection instruments.
     * Returns raw PDF binary.
     *
     * @param  string  $record  Record ID (empty = blank form)
     * @param  string  $event  Unique event name
     * @param  string  $instrument  Instrument name (empty = all)
     * @param  bool  $allRecords  Export all records
     * @param  bool  $compactDisplay  Compact PDF layout
     */
    public static function exportPdf(
        string $record = '',
        string $event = '',
        string $instrument = '',
        bool $allRecords = false,
        bool $compactDisplay = false,
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'pdf',
                'record' => $record,
                'event' => $event,
                'instrument' => $instrument,
                'allRecords' => $allRecords,
                'compactDisplay' => $compactDisplay,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportPdf failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportPdf error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── Arms ───────────────────────────────────────────────────────────────

    /**
     * Export arms for a longitudinal project.
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $arms  Comma-separated arm numbers (empty = all)
     * @param  string  $returnAs  raw|array
     */
    public static function exportArms(
        string $format = 'json',
        string $arms = '',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            $params = ['content' => 'arm', 'format' => $format, 'arms' => $arms];
            self::expandArrayParam($params, 'arms');

            return self::decode(self::post($params, $url, $token), $format, $returnAs);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportArms failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportArms error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import arms into a longitudinal project.
     *
     * @param  string  $data  JSON/CSV/XML — fields: arm_num, name
     * @param  string  $format  json|csv|xml
     * @param  string  $action  import|replace
     * @param  bool  $override  Replace all existing arms when action=import
     */
    public static function importArms(
        string $data,
        string $format = 'json',
        string $action = 'import',
        bool $override = false,
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'arm',
                'action' => $action,
                'format' => $format,
                'override' => $override,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importArms failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importArms error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete arms from a longitudinal project.
     * WARNING: also deletes all events associated with those arms.
     *
     * @param  string  $arms  Comma-separated arm numbers
     */
    public static function deleteArms(string $arms, ?string $url = null, ?string $token = null): string
    {
        try {
            $params = ['content' => 'arm', 'action' => 'delete', 'arms' => $arms];
            self::expandArrayParam($params, 'arms');

            return self::post($params, $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::deleteArms failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap deleteArms error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── Events ─────────────────────────────────────────────────────────────

    /**
     * Export events for a longitudinal project.
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $arms  Comma-separated arm numbers (empty = all)
     * @param  string  $returnAs  raw|array
     */
    public static function exportEvents(
        string $format = 'json',
        string $arms = '',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            $params = ['content' => 'event', 'format' => $format, 'arms' => $arms];
            self::expandArrayParam($params, 'arms');

            return self::decode(self::post($params, $url, $token), $format, $returnAs);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportEvents failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportEvents error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import events into a longitudinal project.
     *
     * @param  string  $data  JSON/CSV/XML — fields: event_name, arm_num, day_offset, offset_min, offset_max, unique_event_name
     * @param  string  $format  json|csv|xml
     * @param  bool  $override  Override existing events
     */
    public static function importEvents(
        string $data,
        string $format = 'json',
        bool $override = false,
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'event',
                'action' => 'import',
                'format' => $format,
                'override' => $override,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importEvents failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importEvents error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete events from a longitudinal project.
     * WARNING: also deletes all record data associated with those events.
     *
     * @param  string  $events  Comma-separated unique event names
     */
    public static function deleteEvents(string $events, ?string $url = null, ?string $token = null): string
    {
        try {
            $params = ['content' => 'event', 'action' => 'delete', 'events' => $events];
            self::expandArrayParam($params, 'events');

            return self::post($params, $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::deleteEvents failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap deleteEvents error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── Instrument-Event Mappings ───────────────────────────────────────────

    /**
     * Export instrument-event mappings for a longitudinal project.
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $arms  Comma-separated arm numbers (empty = all)
     * @param  string  $returnAs  raw|array
     */
    public static function exportInstrumentEventMappings(
        string $format = 'json',
        string $arms = '',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            $params = ['content' => 'formEventMapping', 'format' => $format, 'arms' => $arms];
            self::expandArrayParam($params, 'arms');

            return self::decode(self::post($params, $url, $token), $format, $returnAs);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportInstrumentEventMappings failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportInstrumentEventMappings error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import instrument-event mappings for a longitudinal project.
     *
     * @param  string  $data  JSON/CSV/XML — fields: arm_num, unique_event_name, form
     * @param  string  $format  json|csv|xml
     */
    public static function importInstrumentEventMappings(
        string $data,
        string $format = 'json',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'formEventMapping',
                'action' => 'import',
                'format' => $format,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importInstrumentEventMappings failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importInstrumentEventMappings error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── Repeating Instruments & Events ─────────────────────────────────────

    /**
     * Export repeating instruments and events setup.
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $returnAs  raw|array
     */
    public static function exportRepeatingInstrumentsAndEvents(
        string $format = 'json',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(
                self::post(['content' => 'repeatingFormsEvents', 'format' => $format], $url, $token),
                $format,
                $returnAs,
            );
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportRepeatingInstrumentsAndEvents failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportRepeatingInstrumentsAndEvents error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import repeating instruments and events setup.
     *
     * @param  string  $data  JSON/CSV/XML
     * @param  string  $format  json|csv|xml
     */
    public static function importRepeatingInstrumentsAndEvents(
        string $data,
        string $format = 'json',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'repeatingFormsEvents',
                'action' => 'import',
                'format' => $format,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importRepeatingInstrumentsAndEvents failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importRepeatingInstrumentsAndEvents error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── Users ───────────────────────────────────────────────────────────────

    /**
     * Export the list of users with their privileges.
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $returnAs  raw|array
     */
    public static function exportUsers(
        string $format = 'json',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(
                self::post(['content' => 'user', 'format' => $format], $url, $token),
                $format,
                $returnAs,
            );
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportUsers failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportUsers error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import/update users and their privileges.
     *
     * @param  string  $data  JSON/CSV/XML — must include username + at least one privilege flag
     * @param  string  $format  json|csv|xml
     */
    public static function importUsers(
        string $data,
        string $format = 'json',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'user',
                'action' => 'import',
                'format' => $format,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importUsers failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importUsers error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete users from the project.
     *
     * @param  string  $users  Comma-separated usernames
     */
    public static function deleteUsers(string $users, ?string $url = null, ?string $token = null): string
    {
        try {
            $params = ['content' => 'user', 'action' => 'delete', 'users' => $users];
            self::expandArrayParam($params, 'users');

            return self::post($params, $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::deleteUsers failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap deleteUsers error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── User Roles ──────────────────────────────────────────────────────────

    /**
     * Export user roles defined in the project.
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $returnAs  raw|array
     */
    public static function exportUserRoles(
        string $format = 'json',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(
                self::post(['content' => 'userRole', 'format' => $format], $url, $token),
                $format,
                $returnAs,
            );
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportUserRoles failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportUserRoles error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import user roles into the project.
     *
     * @param  string  $data  JSON/CSV/XML
     * @param  string  $format  json|csv|xml
     */
    public static function importUserRoles(
        string $data,
        string $format = 'json',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'userRole',
                'action' => 'import',
                'format' => $format,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importUserRoles failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importUserRoles error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete user roles from the project.
     *
     * @param  string  $roles  Comma-separated unique role names
     */
    public static function deleteUserRoles(string $roles, ?string $url = null, ?string $token = null): string
    {
        try {
            $params = ['content' => 'userRole', 'action' => 'delete', 'roles' => $roles];
            self::expandArrayParam($params, 'roles');

            return self::post($params, $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::deleteUserRoles failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap deleteUserRoles error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── User Role Assignments ───────────────────────────────────────────────

    /**
     * Export user-to-role assignments.
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $returnAs  raw|array
     */
    public static function exportUserRoleAssignments(
        string $format = 'json',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(
                self::post(['content' => 'userRoleMapping', 'format' => $format], $url, $token),
                $format,
                $returnAs,
            );
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportUserRoleAssignments failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportUserRoleAssignments error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import user-to-role assignments.
     *
     * @param  string  $data  JSON/CSV/XML — fields: username, unique_role_name
     * @param  string  $format  json|csv|xml
     */
    public static function importUserRoleAssignments(
        string $data,
        string $format = 'json',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'userRoleMapping',
                'action' => 'import',
                'format' => $format,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importUserRoleAssignments failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importUserRoleAssignments error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── DAGs ────────────────────────────────────────────────────────────────

    /**
     * Export Data Access Groups (DAGs).
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $returnAs  raw|array
     */
    public static function exportDags(
        string $format = 'json',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(
                self::post(['content' => 'dag', 'action' => 'export', 'format' => $format], $url, $token),
                $format,
                $returnAs,
            );
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportDags failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportDags error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import Data Access Groups.
     *
     * @param  string  $data  JSON/CSV/XML — fields: data_access_group_name, unique_group_name
     * @param  string  $format  json|csv|xml
     */
    public static function importDags(
        string $data,
        string $format = 'json',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'dag',
                'action' => 'import',
                'format' => $format,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importDags failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importDags error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete Data Access Groups.
     * WARNING: unassigns all users and records from the DAG before deleting.
     *
     * @param  string  $dags  Comma-separated unique_group_names
     */
    public static function deleteDags(string $dags, ?string $url = null, ?string $token = null): string
    {
        try {
            $params = ['content' => 'dag', 'action' => 'delete', 'dags' => $dags];
            self::expandArrayParam($params, 'dags');

            return self::post($params, $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::deleteDags failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap deleteDags error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── DAG Assignments ─────────────────────────────────────────────────────

    /**
     * Export user-to-DAG assignments.
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $returnAs  raw|array
     */
    public static function exportDagAssignments(
        string $format = 'json',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(
                self::post(['content' => 'userDagMapping', 'action' => 'export', 'format' => $format], $url, $token),
                $format,
                $returnAs,
            );
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportDagAssignments failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportDagAssignments error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Import user-to-DAG assignments.
     *
     * @param  string  $data  JSON/CSV/XML — fields: username, redcap_data_access_group
     * @param  string  $format  json|csv|xml
     */
    public static function importDagAssignments(
        string $data,
        string $format = 'json',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'userDagMapping',
                'action' => 'import',
                'format' => $format,
                'data' => $data,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importDagAssignments failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importDagAssignments error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Switch the active DAG context for the current API token.
     *
     * @param  string  $dag  Unique group name (empty string to remove context)
     */
    public static function switchDag(string $dag = '', ?string $url = null, ?string $token = null): string
    {
        try {
            return self::post(['content' => 'dag', 'action' => 'switch', 'dag' => $dag], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::switchDag failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap switchDag error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── Files ───────────────────────────────────────────────────────────────

    /**
     * Export a file uploaded to a file-upload field.
     * Returns the file content as a base64-encoded string.
     *
     * @param  string  $record  Record ID
     * @param  string  $field  File-upload field name
     * @param  string  $event  Unique event name (longitudinal projects)
     * @param  string  $repeatInstance  Repeat instance number
     */
    public static function exportFile(
        string $record,
        string $field,
        string $event = '',
        string $repeatInstance = '',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            $response = self::post([
                'content' => 'file',
                'action' => 'export',
                'record' => $record,
                'field' => $field,
                'event' => $event,
                'repeat_instance' => $repeatInstance,
            ], $url, $token);

            return base64_encode($response);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportFile failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportFile error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Upload a file to a file-upload field.
     *
     * @param  string  $record  Record ID
     * @param  string  $field  File-upload field name
     * @param  string  $fileContent  Base64-encoded file content
     * @param  string  $fileName  Original filename
     * @param  string  $mimeType  MIME type (default: application/octet-stream)
     * @param  string  $event  Unique event name (longitudinal projects)
     * @param  string  $repeatInstance  Repeat instance number
     */
    public static function importFile(
        string $record,
        string $field,
        string $fileContent,
        string $fileName,
        string $mimeType = 'application/octet-stream',
        string $event = '',
        string $repeatInstance = '',
        ?string $url = null,
        ?string $token = null,
    ): string {
        $tmpPath = null;
        try {
            $tmpPath = tempnam(sys_get_temp_dir(), 'redcap_');
            file_put_contents($tmpPath, base64_decode($fileContent));

            return self::postWithFile([
                'content' => 'file',
                'action' => 'import',
                'record' => $record,
                'field' => $field,
                'event' => $event,
                'repeat_instance' => $repeatInstance,
            ], $tmpPath, $fileName, $mimeType, $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::importFile failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap importFile error: '.$e->getMessage(), 0, $e);
        } finally {
            if ($tmpPath && file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    /**
     * Delete a file from a file-upload field.
     *
     * @param  string  $record  Record ID
     * @param  string  $field  File-upload field name
     * @param  string  $event  Unique event name (longitudinal projects)
     * @param  string  $repeatInstance  Repeat instance number
     */
    public static function deleteFile(
        string $record,
        string $field,
        string $event = '',
        string $repeatInstance = '',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'file',
                'action' => 'delete',
                'record' => $record,
                'field' => $field,
                'event' => $event,
                'repeat_instance' => $repeatInstance,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::deleteFile failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap deleteFile error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── Surveys ─────────────────────────────────────────────────────────────

    /**
     * Export the survey participant list for an instrument.
     *
     * @param  string  $instrument  Instrument name (required)
     * @param  string  $format  json|csv|xml
     * @param  string  $event  Unique event name (longitudinal projects)
     * @param  string  $returnAs  raw|array
     */
    public static function exportSurveyParticipantList(
        string $instrument,
        string $format = 'json',
        string $event = '',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(self::post([
                'content' => 'participantList',
                'instrument' => $instrument,
                'format' => $format,
                'event' => $event,
            ], $url, $token), $format, $returnAs);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportSurveyParticipantList failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportSurveyParticipantList error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Export a unique survey link for a participant.
     *
     * @param  string  $record  Record ID (required)
     * @param  string  $instrument  Instrument name (required)
     * @param  string  $event  Unique event name (longitudinal projects)
     * @param  string  $repeatInstance  Repeat instance number
     */
    public static function exportSurveyLink(
        string $record,
        string $instrument,
        string $event = '',
        string $repeatInstance = '',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'surveyLink',
                'record' => $record,
                'instrument' => $instrument,
                'event' => $event,
                'repeat_instance' => $repeatInstance,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportSurveyLink failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportSurveyLink error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Export the survey queue link for a participant.
     * Requires the Survey Queue feature to be enabled.
     *
     * @param  string  $record  Record ID (required)
     */
    public static function exportSurveyQueueLink(
        string $record,
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post(['content' => 'surveyQueueLink', 'record' => $record], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportSurveyQueueLink failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportSurveyQueueLink error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Export the survey return (completion confirmation) code for a participant.
     *
     * @param  string  $record  Record ID (required)
     * @param  string  $instrument  Instrument name (required)
     * @param  string  $event  Unique event name (longitudinal projects)
     * @param  string  $repeatInstance  Repeat instance number
     */
    public static function exportSurveyReturnCode(
        string $record,
        string $instrument,
        string $event = '',
        string $repeatInstance = '',
        ?string $url = null,
        ?string $token = null,
    ): string {
        try {
            return self::post([
                'content' => 'surveyReturnCode',
                'record' => $record,
                'instrument' => $instrument,
                'event' => $event,
                'repeat_instance' => $repeatInstance,
            ], $url, $token);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportSurveyReturnCode failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportSurveyReturnCode error: '.$e->getMessage(), 0, $e);
        }
    }

    // ─── Reports & Logging ───────────────────────────────────────────────────

    /**
     * Export data from a saved report.
     *
     * @param  string  $reportId  Report ID (required)
     * @param  string  $format  json|csv|xml
     * @param  string  $csvDelimiter  comma|tab|semi|pipe|caret
     * @param  string  $rawOrLabel  raw|label
     * @param  string  $rawOrLabelHeaders  raw|label
     * @param  string  $filterLogic  REDCap logic string
     * @param  string  $decimalCharacter  dot|comma
     * @param  string  $returnAs  raw|array
     */
    public static function exportReports(
        string $reportId,
        string $format = 'json',
        string $csvDelimiter = '',
        string $rawOrLabel = 'raw',
        string $rawOrLabelHeaders = 'raw',
        bool $exportCheckboxLabel = false,
        string $filterLogic = '',
        string $decimalCharacter = '',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(self::post([
                'content' => 'report',
                'report_id' => $reportId,
                'format' => $format,
                'csvDelimiter' => $csvDelimiter,
                'rawOrLabel' => $rawOrLabel,
                'rawOrLabelHeaders' => $rawOrLabelHeaders,
                'exportCheckboxLabel' => $exportCheckboxLabel,
                'filterLogic' => $filterLogic,
                'decimalCharacter' => $decimalCharacter,
            ], $url, $token), $format, $returnAs);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportReports failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportReports error: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Export the project audit log.
     *
     * @param  string  $format  json|csv|xml
     * @param  string  $logtype  export|manage|user|record|record_add|record_edit|record_delete|lock_record|page_view (empty = all)
     * @param  string  $user  Filter by username
     * @param  string  $record  Filter by record ID
     * @param  string  $dag  Filter by DAG name
     * @param  string  $beginTime  YYYY-MM-DD HH:MM:SS
     * @param  string  $endTime  YYYY-MM-DD HH:MM:SS
     * @param  string  $returnAs  raw|array
     */
    public static function exportLogging(
        string $format = 'json',
        string $logtype = '',
        string $user = '',
        string $record = '',
        string $dag = '',
        string $beginTime = '',
        string $endTime = '',
        string $returnAs = 'raw',
        ?string $url = null,
        ?string $token = null,
    ): string|array {
        try {
            return self::decode(self::post([
                'content' => 'log',
                'format' => $format,
                'logtype' => $logtype,
                'user' => $user,
                'record' => $record,
                'dag' => $dag,
                'beginTime' => $beginTime,
                'endTime' => $endTime,
            ], $url, $token), $format, $returnAs);
        } catch (\Throwable $e) {
            Log::error('Redcap_lib::exportLogging failed', [
                'url' => self::resolveUrl($url),
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('REDCap exportLogging error: '.$e->getMessage(), 0, $e);
        }
    }
}
