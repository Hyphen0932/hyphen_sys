<?php

include_once __DIR__ . '/audit_rules.php';

if (!function_exists('hyphen_audit_connection')) {
    function hyphen_audit_connection(?mysqli $conn = null): ?mysqli
    {
        if ($conn instanceof mysqli) {
            return $conn;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            return $GLOBALS['conn'];
        }

        return null;
    }
}

if (!function_exists('hyphen_audit_table_exists')) {
    function hyphen_audit_table_exists(?mysqli $conn = null): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        $conn = hyphen_audit_connection($conn);
        if (!$conn instanceof mysqli) {
            $exists = false;
            return false;
        }

        $result = mysqli_query(
            $conn,
            "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'hy_audit_logs' LIMIT 1"
        );

        $exists = $result !== false && mysqli_num_rows($result) > 0;
        if ($result !== false) {
            mysqli_free_result($result);
        }

        return $exists;
    }
}

if (!function_exists('hyphen_audit_request_page_url')) {
    function hyphen_audit_request_page_url(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

        if (function_exists('hyphen_normalize_page_key')) {
            return hyphen_normalize_page_key($scriptName);
        }

        $pagesPosition = strpos($scriptName, '/pages/');
        if ($pagesPosition !== false) {
            $scriptName = substr($scriptName, $pagesPosition + 7);
        }

        $scriptName = ltrim($scriptName, '/');
        $scriptName = preg_replace('/\.php$/i', '', $scriptName);

        return trim((string) $scriptName, '/');
    }
}

if (!function_exists('hyphen_audit_actor_staff_id')) {
    function hyphen_audit_actor_staff_id(): ?string
    {
        $staffId = trim((string) ($_SESSION['staff_id'] ?? ''));
        return $staffId !== '' ? $staffId : null;
    }
}

if (!function_exists('hyphen_audit_actor_name')) {
    function hyphen_audit_actor_name(): ?string
    {
        $username = trim((string) ($_SESSION['username'] ?? ''));
        return $username !== '' ? $username : null;
    }
}

if (!function_exists('hyphen_audit_ip_address')) {
    function hyphen_audit_ip_address(): ?string
    {
        $candidates = [
            (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim(explode(',', $candidate)[0] ?? '');
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('hyphen_audit_user_agent')) {
    function hyphen_audit_user_agent(): ?string
    {
        $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($userAgent === '') {
            return null;
        }

        return substr($userAgent, 0, 255);
    }
}

if (!function_exists('hyphen_audit_sensitive_key')) {
    function hyphen_audit_sensitive_key(string $key): bool
    {
        return preg_match('/password|passwd|token|secret|authorization|cookie|smtp|app_password/i', $key) === 1;
    }
}

if (!function_exists('hyphen_audit_sanitize_value')) {
    function hyphen_audit_sanitize_value($value, ?string $key = null)
    {
        if ($key !== null && hyphen_audit_sensitive_key($key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = hyphen_audit_sanitize_value($childValue, is_string($childKey) ? $childKey : null);
            }

            return $sanitized;
        }

        if (is_object($value)) {
            return hyphen_audit_sanitize_value((array) $value, $key);
        }

        return $value;
    }
}

if (!function_exists('hyphen_audit_json')) {
    function hyphen_audit_json($value): ?string
    {
        if ($value === null || $value === [] || $value === '') {
            return null;
        }

        $sanitized = hyphen_audit_sanitize_value($value);
        $encoded = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : null;
    }
}

if (!function_exists('hyphen_audit_rules')) {
    function hyphen_audit_rules(): array
    {
        static $rules = null;

        if ($rules !== null) {
            return $rules;
        }

        $rules = function_exists('hyphen_audit_rule_definitions')
            ? hyphen_audit_rule_definitions()
            : [];

        return $rules;
    }
}

if (!function_exists('hyphen_audit_rule')) {
    function hyphen_audit_rule(string $module, string $actionName): array
    {
        $rules = hyphen_audit_rules();
        $key = trim($module) . '.' . trim($actionName);

        return $rules[$key] ?? ['mode' => 'minimal', 'fields' => null];
    }
}

if (!function_exists('hyphen_audit_pick_fields')) {
    function hyphen_audit_pick_fields(?array $values, ?array $fields = null): array
    {
        if (!is_array($values) || $values === []) {
            return [];
        }

        if ($fields === null || $fields === []) {
            return $values;
        }

        $picked = [];
        foreach ($fields as $field) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            if (array_key_exists($field, $values)) {
                $picked[$field] = $values[$field];
            }
        }

        return $picked;
    }
}

if (!function_exists('hyphen_audit_values_equal')) {
    function hyphen_audit_values_equal($left, $right): bool
    {
        return hyphen_audit_json($left) === hyphen_audit_json($right);
    }
}

if (!function_exists('hyphen_audit_diff')) {
    function hyphen_audit_diff(?array $oldValues, ?array $newValues, ?array $fields = null): array
    {
        $oldValues = hyphen_audit_pick_fields($oldValues, $fields);
        $newValues = hyphen_audit_pick_fields($newValues, $fields);

        if ($oldValues === [] && $newValues === []) {
            return [];
        }

        $keys = array_values(array_unique(array_merge(array_keys($oldValues), array_keys($newValues))));
        $oldDiff = [];
        $newDiff = [];
        $changedFields = [];

        foreach ($keys as $key) {
            $hasOld = array_key_exists($key, $oldValues);
            $hasNew = array_key_exists($key, $newValues);
            $oldValue = $hasOld ? $oldValues[$key] : null;
            $newValue = $hasNew ? $newValues[$key] : null;

            if ($hasOld && $hasNew && hyphen_audit_values_equal($oldValue, $newValue)) {
                continue;
            }

            if ($hasOld) {
                $oldDiff[$key] = $oldValue;
            }

            if ($hasNew) {
                $newDiff[$key] = $newValue;
            }

            $changedFields[] = (string) $key;
        }

        $diff = [];
        if ($oldDiff !== []) {
            $diff['old_values'] = $oldDiff;
        }
        if ($newDiff !== []) {
            $diff['new_values'] = $newDiff;
        }
        if ($changedFields !== []) {
            $diff['changed_fields'] = $changedFields;
        }

        return $diff;
    }
}

if (!function_exists('hyphen_audit_context')) {
    function hyphen_audit_context(
        string $module,
        string $actionName,
        string $entityType,
        $entityId = null,
        ?string $targetLabel = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = [],
        array $overrides = []
    ): array {
        $entityIdText = $entityId === null ? '' : trim((string) $entityId);
        $targetLabelText = trim((string) ($targetLabel ?? ''));
        $context = [
            'entity_type' => trim($entityType),
            'entity_id' => $entityIdText,
            'target_label' => $targetLabelText !== '' ? $targetLabelText : $entityIdText,
        ];

        $rule = hyphen_audit_rule($module, $actionName);
        $detailMode = (string) ($overrides['detail_mode'] ?? $rule['mode'] ?? 'minimal');
        $fields = $overrides['fields'] ?? ($rule['fields'] ?? null);

        if ($detailMode === 'diff') {
            $diff = hyphen_audit_diff($oldValues, $newValues, is_array($fields) ? $fields : null);

            if (isset($diff['old_values'])) {
                $context['old_values'] = $diff['old_values'];
            }

            if (isset($diff['new_values'])) {
                $context['new_values'] = $diff['new_values'];
            }

            if (!empty($diff['changed_fields'])) {
                $metadata['changed_fields'] = $diff['changed_fields'];
            }
        }

        if ($metadata !== []) {
            $context['metadata'] = $metadata;
        }

        unset($overrides['detail_mode'], $overrides['fields']);

        foreach ($overrides as $key => $value) {
            if ($key === 'metadata' && is_array($value)) {
                $context['metadata'] = array_merge($context['metadata'] ?? [], $value);
                continue;
            }

            $context[$key] = $value;
        }

        return $context;
    }
}

if (!function_exists('hyphen_audit_action')) {
    function hyphen_audit_action(?mysqli $conn, string $module, string $actionName, array $options = []): bool
    {
        $metadata = is_array($options['metadata'] ?? null) ? $options['metadata'] : [];
        $overrides = [];

        foreach (['detail_mode', 'fields', 'page_url', 'request_method', 'staff_id', 'actor_name', 'ip_address', 'user_agent', 'status'] as $key) {
            if (array_key_exists($key, $options)) {
                $overrides[$key] = $options[$key];
            }
        }

        $context = hyphen_audit_context(
            $module,
            $actionName,
            trim((string) ($options['entity_type'] ?? 'system')),
            $options['entity_id'] ?? null,
            isset($options['target_label']) ? (string) $options['target_label'] : null,
            is_array($options['old_values'] ?? null) ? $options['old_values'] : null,
            is_array($options['new_values'] ?? null) ? $options['new_values'] : null,
            $metadata,
            $overrides
        );

        $status = strtolower(trim((string) ($context['status'] ?? $options['status'] ?? 'success')));

        if ($status === '' || $status === 'success') {
            return hyphen_audit_log_success($conn, $module, $actionName, $context);
        }

        return hyphen_audit_log_failure($conn, $module, $actionName, $context);
    }
}

if (!function_exists('hyphen_audit_log')) {
    function hyphen_audit_log(?mysqli $conn, array $entry): bool
    {
        $conn = hyphen_audit_connection($conn);
        if (!$conn instanceof mysqli || !hyphen_audit_table_exists($conn)) {
            return false;
        }

        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO hy_audit_logs (staff_id, actor_name, module, action_name, entity_type, entity_id, target_label, page_url, request_method, status, old_values_json, new_values_json, metadata_json, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$statement) {
            return false;
        }

        $staffId = isset($entry['staff_id']) ? trim((string) $entry['staff_id']) : (hyphen_audit_actor_staff_id() ?? '');
        $actorName = isset($entry['actor_name']) ? trim((string) $entry['actor_name']) : (hyphen_audit_actor_name() ?? '');
        $module = trim((string) ($entry['module'] ?? 'system'));
        $actionName = trim((string) ($entry['action_name'] ?? 'unknown'));
        $entityType = trim((string) ($entry['entity_type'] ?? ''));
        $entityId = isset($entry['entity_id']) ? trim((string) $entry['entity_id']) : '';
        $targetLabel = trim((string) ($entry['target_label'] ?? ''));
        $pageUrl = trim((string) ($entry['page_url'] ?? hyphen_audit_request_page_url()));
        $requestMethod = strtoupper(trim((string) ($entry['request_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? ''))));
        $status = strtolower(trim((string) ($entry['status'] ?? 'success')));
        $oldValuesJson = hyphen_audit_json($entry['old_values'] ?? null);
        $newValuesJson = hyphen_audit_json($entry['new_values'] ?? null);
        $metadataJson = hyphen_audit_json($entry['metadata'] ?? null);
        $ipAddress = trim((string) ($entry['ip_address'] ?? (hyphen_audit_ip_address() ?? '')));
        $userAgent = trim((string) ($entry['user_agent'] ?? (hyphen_audit_user_agent() ?? '')));

        mysqli_stmt_bind_param(
            $statement,
            'sssssssssssssss',
            $staffId,
            $actorName,
            $module,
            $actionName,
            $entityType,
            $entityId,
            $targetLabel,
            $pageUrl,
            $requestMethod,
            $status,
            $oldValuesJson,
            $newValuesJson,
            $metadataJson,
            $ipAddress,
            $userAgent
        );

        $success = mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        return $success;
    }
}

if (!function_exists('hyphen_audit_log_success')) {
    function hyphen_audit_log_success(?mysqli $conn, string $module, string $actionName, array $context = []): bool
    {
        $context['module'] = $module;
        $context['action_name'] = $actionName;
        $context['status'] = 'success';

        return hyphen_audit_log($conn, $context);
    }
}

if (!function_exists('hyphen_audit_log_failure')) {
    function hyphen_audit_log_failure(?mysqli $conn, string $module, string $actionName, array $context = []): bool
    {
        $context['module'] = $module;
        $context['action_name'] = $actionName;
        $context['status'] = $context['status'] ?? 'failed';

        return hyphen_audit_log($conn, $context);
    }
};

?>