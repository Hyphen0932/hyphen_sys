<?php

include_once __DIR__ . '/config.php';
include_once __DIR__ . '/authorization.php';
include_once __DIR__ . '/audit_middleware.php';

if (!function_exists('hyphen_api_raw_input')) {
    function hyphen_api_raw_input(): string
    {
        static $rawInput = null;
        if ($rawInput !== null) {
            return $rawInput;
        }

        $rawInput = file_get_contents('php://input');
        return is_string($rawInput) ? $rawInput : '';
    }
}

if (!function_exists('hyphen_api_request_payload')) {
    function hyphen_api_request_payload(): array
    {
        static $payload = null;
        if (is_array($payload)) {
            return $payload;
        }

        $payload = $_REQUEST;
        $input = hyphen_api_raw_input();
        if (is_string($input) && trim($input) !== '') {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                $payload = array_merge($payload, $decoded);
            }
        }

        return is_array($payload) ? $payload : [];
    }
}

if (!function_exists('api_request_payload')) {
    function api_request_payload(): array
    {
        return hyphen_api_request_payload();
    }
}

if (!function_exists('json_response')) {
    function json_response(bool $success, string $message, array $data = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ]);
        exit;
    }
}

if (!function_exists('hyphen_api_bootstrap')) {
    function hyphen_api_bootstrap(array $options = []): ?AuditMiddleware
    {
        global $conn;

        if (!($conn instanceof mysqli)) {
            return null;
        }

        header('Content-Type: application/json; charset=utf-8');
        mysqli_set_charset($conn, 'utf8mb4');

        if (($options['boot_session'] ?? true) === true) {
            hyphen_boot_session();
        }

        static $auditInstances = [];
        $auditKey = md5((string) ($_SERVER['SCRIPT_NAME'] ?? '') . '|' . serialize($options['audit'] ?? []));
        if (!isset($auditInstances[$auditKey])) {
            $auditInstances[$auditKey] = new AuditMiddleware($conn, is_array($options['audit'] ?? null) ? $options['audit'] : []);
            $auditInstances[$auditKey]->start();
        }

        $requiresAuth = $options['require_auth'] ?? true;
        if ($requiresAuth && !hyphen_is_authenticated()) {
            json_response(false, 'Your session has expired. Please sign in again.', [], 401);
        }

        if ($requiresAuth && ($options['refresh_authorization'] ?? true) === true) {
            hyphen_refresh_session_authorization($conn, (string) ($_SESSION['staff_id'] ?? ''));
        }

        $allowedMethods = $options['allowed_methods'] ?? [];
        if (is_array($allowedMethods) && $allowedMethods !== []) {
            $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $normalizedAllowedMethods = array_map(
                static function ($method): string {
                    return strtoupper(trim((string) $method));
                },
                $allowedMethods
            );

            if (!in_array($requestMethod, $normalizedAllowedMethods, true)) {
                json_response(false, 'Invalid request method.', [], 405);
            }
        }

        return $auditInstances[$auditKey];
    }
}