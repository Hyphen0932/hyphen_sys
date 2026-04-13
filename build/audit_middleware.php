<?php

if (!class_exists('AuditMiddleware')) {
    class AuditMiddleware
    {
        private mysqli $conn;
        private float $startTime;
        private string $pageKey = '';
        private string $fixedAction = '';
        private string $actionKey = 'action';
        private string $apiEndpoint = '';
        private string $method = 'GET';
        private array $requestData = [];
        private string $responseBuffer = '';
        private bool $enabled = true;
        private string $mode = 'crud_only';
        private array $allowedActions = [];
        private array $ignoredActions = [];
        private bool $started = false;
        private bool $persisted = false;
        private static ?bool $tableAvailable = null;
        private static ?array $tableColumns = null;

        public function __construct(mysqli $conn, array $options = [])
        {
            $this->conn = $conn;
            $this->startTime = microtime(true);
            $this->pageKey = trim((string) ($options['page_key'] ?? ''));
            $this->fixedAction = trim((string) ($options['fixed_action'] ?? ''));
            $this->actionKey = trim((string) ($options['action_key'] ?? 'action')) ?: 'action';
            $this->enabled = ($options['enabled'] ?? true) === true;
            $this->mode = strtolower(trim((string) ($options['mode'] ?? 'crud_only')));
            $this->allowedActions = $this->normalizeActionList($options['allowed_actions'] ?? []);
            $this->ignoredActions = $this->normalizeActionList($options['ignored_actions'] ?? []);
            $this->apiEndpoint = (string) ($_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
            $this->method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        }

        public function start(): self
        {
            if ($this->started) {
                return $this;
            }

            $this->started = true;
            $this->requestData = $this->collectRequestData();

            ob_start(function (string $buffer): string {
                $this->responseBuffer .= $buffer;
                return $buffer;
            });

            register_shutdown_function([$this, 'flush']);

            return $this;
        }

        public function flush(): void
        {
            if ($this->persisted) {
                return;
            }

            $this->persisted = true;

            if (!$this->enabled || !$this->started || !$this->auditTableExists()) {
                return;
            }

            $action = $this->resolveAction();
            if ($action === '' || !$this->shouldAuditAction($action)) {
                return;
            }

            $responseCode = (int) http_response_code();
            if ($responseCode <= 0) {
                $responseCode = 200;
            }

            $parsedResponse = $this->parseResponsePayload($this->responseBuffer);
            $status = $this->resolveStatus($responseCode, $parsedResponse);
            $errorMessage = $this->resolveErrorMessage($parsedResponse, $status);
            $staffId = $this->resolveStaffId();
            if ($staffId === '') {
                return;
            }

            $executionTime = (int) round((microtime(true) - $this->startTime) * 1000);
            $ipAddress = $this->getClientIp();
            $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000);
            $requestJson = $this->encodeJson($this->requestData);
            $responseJson = $this->encodeJson($parsedResponse);
            $pageKey = $this->pageKey;

            $availableColumns = $this->auditTableColumns();
            $columnMap = [
                'staff_id' => [$staffId, 's'],
                'page_key' => [$pageKey, 's'],
                'api_endpoint' => [$this->apiEndpoint, 's'],
                'action' => [$action, 's'],
                'method' => [$this->method, 's'],
                'request_data' => [$requestJson, 's'],
                'response_data' => [$responseJson, 's'],
                'status' => [$status, 's'],
                'error_message' => [$errorMessage, 's'],
                'ip_address' => [$ipAddress, 's'],
                'user_agent' => [$userAgent, 's'],
                'response_code' => [$responseCode, 'i'],
                'execution_time_ms' => [$executionTime, 'i'],
            ];

            $insertColumns = [];
            $bindTypes = '';
            $bindValues = [];
            foreach ($columnMap as $column => [$value, $type]) {
                if (!in_array($column, $availableColumns, true)) {
                    continue;
                }

                $insertColumns[] = $column;
                $bindTypes .= $type;
                $bindValues[] = $value;
            }

            if ($insertColumns === []) {
                return;
            }

            $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
            try {
                $statement = mysqli_prepare(
                    $this->conn,
                    'INSERT INTO hy_audit_logs (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')'
                );
            } catch (Throwable $exception) {
                return;
            }

            if (!$statement) {
                return;
            }

            $this->bindStatement($statement, $bindTypes, $bindValues);

            try {
                mysqli_stmt_execute($statement);
            } catch (Throwable $exception) {
            }

            mysqli_stmt_close($statement);
        }

        private function auditTableExists(): bool
        {
            if (self::$tableAvailable !== null) {
                return self::$tableAvailable;
            }

            $result = mysqli_query($this->conn, "SHOW TABLES LIKE 'hy_audit_logs'");
            self::$tableAvailable = $result !== false && mysqli_num_rows($result) > 0;

            if ($result !== false) {
                mysqli_free_result($result);
            }

            return self::$tableAvailable;
        }

        private function auditTableColumns(): array
        {
            if (is_array(self::$tableColumns)) {
                return self::$tableColumns;
            }

            self::$tableColumns = [];
            $result = mysqli_query($this->conn, 'SHOW COLUMNS FROM hy_audit_logs');
            if ($result === false) {
                return self::$tableColumns;
            }

            while ($row = mysqli_fetch_assoc($result)) {
                $columnName = trim((string) ($row['Field'] ?? ''));
                if ($columnName !== '') {
                    self::$tableColumns[] = $columnName;
                }
            }

            mysqli_free_result($result);
            return self::$tableColumns;
        }

        private function bindStatement(mysqli_stmt $statement, string $types, array $values): void
        {
            if ($types === '' || $values === []) {
                return;
            }

            $bindValues = [$types];
            foreach ($values as $index => $value) {
                $bindValues[] = &$values[$index];
            }

            call_user_func_array([$statement, 'bind_param'], $bindValues);
        }

        private function collectRequestData(): array
        {
            $payload = [];

            if (!empty($_GET)) {
                $payload = array_merge($payload, $_GET);
            }

            if (!empty($_POST)) {
                $payload = array_merge($payload, $_POST);
            }

            $input = function_exists('hyphen_api_raw_input') ? hyphen_api_raw_input() : file_get_contents('php://input');
            if (is_string($input) && trim($input) !== '') {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    $payload = array_merge($payload, $decoded);
                } else {
                    $payload['_raw_input'] = $this->truncateString($input, 4000);
                }
            }

            if (!empty($_FILES)) {
                $payload['_files'] = $this->normalizeFiles($_FILES);
            }

            return $this->sanitizeValue($payload);
        }

        private function normalizeFiles(array $files): array
        {
            $normalized = [];

            foreach ($files as $field => $fileInfo) {
                if (!is_array($fileInfo)) {
                    continue;
                }

                $normalized[$field] = [
                    'name' => $fileInfo['name'] ?? null,
                    'type' => $fileInfo['type'] ?? null,
                    'size' => $fileInfo['size'] ?? null,
                    'error' => $fileInfo['error'] ?? null,
                ];
            }

            return $normalized;
        }

        private function parseResponsePayload(string $buffer): array
        {
            $buffer = trim($buffer);
            if ($buffer === '') {
                return [];
            }

            $decoded = json_decode($buffer, true);
            if (is_array($decoded)) {
                return $this->sanitizeValue($decoded);
            }

            return ['raw' => $this->truncateString($buffer, 8000)];
        }

        private function resolveStatus(int $responseCode, array $responsePayload): string
        {
            if (isset($responsePayload['success']) && $responsePayload['success'] === false) {
                return 'failure';
            }

            return $responseCode >= 400 ? 'failure' : 'success';
        }

        private function resolveErrorMessage(array $responsePayload, string $status): string
        {
            if ($status !== 'failure') {
                return '';
            }

            $message = trim((string) ($responsePayload['message'] ?? ''));
            if ($message !== '') {
                return $this->truncateString($message, 1000);
            }

            if (isset($responsePayload['raw'])) {
                return $this->truncateString((string) $responsePayload['raw'], 1000);
            }

            return 'Request failed.';
        }

        private function resolveAction(): string
        {
            if ($this->fixedAction !== '') {
                return $this->fixedAction;
            }

            $action = trim((string) ($this->requestData[$this->actionKey] ?? ''));
            if ($action !== '') {
                return $action;
            }

            $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
            $baseName = pathinfo($scriptName, PATHINFO_FILENAME);
            return trim($baseName);
        }

        private function shouldAuditAction(string $action): bool
        {
            $normalizedAction = $this->normalizeActionName($action);
            if ($normalizedAction === '') {
                return false;
            }

            if (in_array($normalizedAction, $this->ignoredActions, true)) {
                return false;
            }

            if ($this->allowedActions !== []) {
                return in_array($normalizedAction, $this->allowedActions, true);
            }

            if ($this->mode === 'all') {
                return true;
            }

            if (!in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                return false;
            }

            foreach ($this->crudActionPrefixes() as $prefix) {
                if (strpos($normalizedAction, $prefix) === 0) {
                    return true;
                }
            }

            return false;
        }

        private function crudActionPrefixes(): array
        {
            return [
                'create',
                'add',
                'update',
                'edit',
                'delete',
                'remove',
                'toggle',
            ];
        }

        private function normalizeActionList($actions): array
        {
            if (!is_array($actions)) {
                return [];
            }

            $normalized = [];
            foreach ($actions as $action) {
                $actionName = $this->normalizeActionName((string) $action);
                if ($actionName !== '') {
                    $normalized[] = $actionName;
                }
            }

            return array_values(array_unique($normalized));
        }

        private function normalizeActionName(string $action): string
        {
            return strtolower(trim($action));
        }

        private function resolveStaffId(): string
        {
            $sessionStaffId = trim((string) ($_SESSION['staff_id'] ?? ''));
            if ($sessionStaffId !== '') {
                return $sessionStaffId;
            }

            foreach (['staff_id', 'login_user_id', 'user_id'] as $key) {
                $value = trim((string) ($this->requestData[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }

            return '';
        }

        private function sanitizeValue($value, ?string $key = null)
        {
            if (is_array($value)) {
                $sanitized = [];
                foreach ($value as $childKey => $childValue) {
                    $sanitized[$childKey] = $this->sanitizeValue($childValue, (string) $childKey);
                }
                return $sanitized;
            }

            if ($key !== null && $this->isSensitiveKey($key)) {
                return '***REDACTED***';
            }

            if (is_string($value)) {
                return $this->truncateString($value, 4000);
            }

            return $value;
        }

        private function isSensitiveKey(string $key): bool
        {
            $key = strtolower(trim($key));
            return in_array($key, [
                'password',
                'new_password',
                'confirm_password',
                'token',
                'secret',
                'api_key',
                'authorization',
                'cookie',
                'set-cookie',
                'password_hash',
            ], true);
        }

        private function truncateString(string $value, int $limit): string
        {
            if (strlen($value) <= $limit) {
                return $value;
            }

            return substr($value, 0, $limit) . '... [truncated]';
        }

        private function encodeJson(array $value): string
        {
            $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $json !== false ? $json : '{}';
        }

        private function getClientIp(): string
        {
            $ip = '';

            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = (string) $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ips = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim((string) ($ips[0] ?? ''));
            } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
                $ip = (string) $_SERVER['REMOTE_ADDR'];
            }

            return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
        }
    }
}
