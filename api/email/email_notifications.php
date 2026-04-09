<?php

include_once __DIR__ . '/../../build/config.php';
include_once __DIR__ . '/../../build/authorization.php';
include_once __DIR__ . '/../../build/email_notifications.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_set_charset($conn, 'utf8mb4');

if (!function_exists('json_response')) {
    function json_response(bool $success, string $message, array $data = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ]);
        exit;
    }
}

hyphen_boot_session();

if (!hyphen_is_authenticated()) {
    json_response(false, 'Your session has expired. Please sign in again.', [], 401);
}

hyphen_refresh_session_authorization($conn, (string) ($_SESSION['staff_id'] ?? ''));

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    json_response(false, 'Invalid request method.', [], 405);
}

$payload = api_request_payload();
$action = trim((string) ($payload['action'] ?? ''));

switch ($action) {
    case 'get_mail_configuration':
        hyphen_require_ability('view', 'sys_admin/system_email_notification', null, true);
        get_mail_configuration($conn);
        break;

    case 'update_mail_configuration':
        hyphen_require_ability('edit', 'sys_admin/system_email_notification', null, true);
        update_mail_configuration($conn, $payload);
        break;

    case 'list_templates':
        hyphen_require_ability('view', 'sys_admin/system_email_notification', null, true);
        list_templates($conn);
        break;

    case 'list_logs':
        hyphen_require_ability('view', 'sys_admin/system_email_notification', null, true);
        list_logs($conn, $payload);
        break;

    case 'get_template':
        hyphen_require_ability('view', 'sys_admin/system_email_notification', null, true);
        get_template($conn, $payload);
        break;

    case 'create_template':
        hyphen_require_ability('add', 'sys_admin/system_email_notification', null, true);
        create_template($conn, $payload);
        break;

    case 'update_template':
        hyphen_require_ability('edit', 'sys_admin/system_email_notification', null, true);
        update_template($conn, $payload);
        break;

    case 'delete_template':
        hyphen_require_ability('delete', 'sys_admin/system_email_notification', null, true);
        delete_template($conn, $payload);
        break;

    case 'send_test_email':
        hyphen_require_ability('edit', 'sys_admin/system_email_notification', null, true);
        send_test_email($conn, $payload);
        break;

    case 'send_notification':
        hyphen_require_ability('edit', 'sys_admin/system_email_notification', null, true);
        send_notification($conn, $payload);
        break;

    case 'mail_status':
        hyphen_require_ability('view', 'sys_admin/system_email_notification', null, true);
        mail_status();
        break;

    default:
        json_response(false, 'Unsupported action.', [], 400);
}

function api_request_payload(): array
{
    $payload = $_REQUEST;
    $input = file_get_contents('php://input');
    if (is_string($input) && trim($input) !== '') {
        $decoded = json_decode($input, true);
        if (is_array($decoded)) {
            $payload = array_merge($payload, $decoded);
        }
    }

    return is_array($payload) ? $payload : [];
}

function payload_value(array $payload, string $key): string
{
    return trim((string) ($payload[$key] ?? ''));
}

function payload_boolean(array $payload, string $key, bool $default = true): int
{
    if (!array_key_exists($key, $payload)) {
        return $default ? 1 : 0;
    }

    $value = $payload[$key];
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

function payload_json_array(array $payload, string $key): array
{
    $value = $payload[$key] ?? [];
    if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        }
    }

    return is_array($value) ? $value : [];
}

function normalize_notification_code(string $code): string
{
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9_.-]/', '_', $code);
    return trim((string) $code, '_');
}

function validate_mail_configuration_payload(array $payload, ?array $existingConfig = null): array
{
    $provider = strtolower(payload_value($payload, 'provider')) ?: 'gmail';
    $host = payload_value($payload, 'host');
    $port = (int) ($payload['port'] ?? 587);
    $encryption = strtolower(payload_value($payload, 'encryption')) ?: 'tls';
    $username = payload_value($payload, 'username');
    $password = trim((string) ($payload['password'] ?? ''));
    $fromAddress = payload_value($payload, 'from_address');
    $fromName = payload_value($payload, 'from_name');
    $replyTo = payload_value($payload, 'reply_to');

    if ($host === '' || $username === '' || $fromAddress === '' || $fromName === '') {
        json_response(false, 'Host, username, from address and from name are required.', [], 422);
    }

    if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        json_response(false, 'Please enter a valid from address.', [], 422);
    }

    if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        json_response(false, 'Please enter a valid reply-to address.', [], 422);
    }

    if (!in_array($encryption, ['tls', 'ssl'], true)) {
        json_response(false, 'Encryption must be tls or ssl.', [], 422);
    }

    if ($port <= 0 || $port > 65535) {
        json_response(false, 'Please enter a valid SMTP port.', [], 422);
    }

    if ($password === '' && is_array($existingConfig) && !empty($existingConfig['password'])) {
        $password = (string) $existingConfig['password'];
    }

    if ($password === '') {
        json_response(false, 'Password is required.', [], 422);
    }

    return [
        'provider' => $provider,
        'host' => $host,
        'port' => $port,
        'encryption' => $encryption,
        'username' => $username,
        'password' => $password,
        'from_address' => $fromAddress,
        'from_name' => $fromName,
        'reply_to' => $replyTo !== '' ? $replyTo : null,
    ];
}

function get_mail_configuration(mysqli $conn): void
{
    $config = hyphen_mail_config($conn);
    $configured = hyphen_mail_is_configured($config);
    $missingConfig = hyphen_mail_missing_config_keys($config);
    $config['password'] = '';

    json_response(true, 'Mail configuration loaded successfully.', [
        'configuration' => $config,
        'configured' => $configured,
        'missing_config' => $missingConfig,
    ]);
}

function update_mail_configuration(mysqli $conn, array $payload): void
{
    if (!hyphen_mail_table_exists($conn, 'hy_email_configurations')) {
        json_response(false, 'Mail configuration table is not available yet. Run database migrations first.', [], 500);
    }

    $existingConfig = hyphen_mail_db_config($conn) ?? hyphen_mail_default_config();
    $config = validate_mail_configuration_payload($payload, $existingConfig);
    $updatedBy = trim((string) ($_SESSION['staff_id'] ?? ''));
    $existingId = isset($existingConfig['id']) ? (int) $existingConfig['id'] : 0;

    if ($existingId > 0 && (($existingConfig['source'] ?? '') === 'database')) {
        $statement = mysqli_prepare(
            $conn,
            'UPDATE hy_email_configurations
             SET provider = ?, host = ?, port = ?, encryption = ?, username = ?, password = ?, from_address = ?, from_name = ?, reply_to = ?, is_active = 1, updated_by = ?
             WHERE id = ?'
        );

        if (!$statement) {
            json_response(false, 'Failed to prepare mail configuration update.', [], 500);
        }

        mysqli_stmt_bind_param(
            $statement,
            'ssisssssssi',
            $config['provider'],
            $config['host'],
            $config['port'],
            $config['encryption'],
            $config['username'],
            $config['password'],
            $config['from_address'],
            $config['from_name'],
            $config['reply_to'],
            $updatedBy,
            $existingId
        );
    } else {
        mysqli_query($conn, 'UPDATE hy_email_configurations SET is_active = 0');

        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO hy_email_configurations (provider, host, port, encryption, username, password, from_address, from_name, reply_to, is_active, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)' 
        );

        if (!$statement) {
            json_response(false, 'Failed to prepare mail configuration insert.', [], 500);
        }

        mysqli_stmt_bind_param(
            $statement,
            'ssissssssss',
            $config['provider'],
            $config['host'],
            $config['port'],
            $config['encryption'],
            $config['username'],
            $config['password'],
            $config['from_address'],
            $config['from_name'],
            $config['reply_to'],
            $updatedBy,
            $updatedBy
        );
    }

    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        json_response(false, 'Unable to save mail configuration: ' . $error, [], 500);
    }

    mysqli_stmt_close($statement);
    $savedConfig = hyphen_mail_config($conn);
    $configured = hyphen_mail_is_configured($savedConfig);
    $missingConfig = hyphen_mail_missing_config_keys($savedConfig);
    $savedConfig['password'] = '';

    json_response(true, 'Mail configuration saved successfully.', [
        'configuration' => $savedConfig,
        'configured' => $configured,
        'missing_config' => $missingConfig,
    ]);
}

function validate_template_payload(array $payload): array
{
    $category = payload_value($payload, 'category');
    $notificationCode = normalize_notification_code(payload_value($payload, 'notification_code'));
    $templateName = payload_value($payload, 'template_name');
    $subject = payload_value($payload, 'email_subject');
    $bodyHtml = trim((string) ($payload['body_html'] ?? ''));
    $bodyText = trim((string) ($payload['body_text'] ?? ''));
    $variables = hyphen_email_template_variables($payload['variables'] ?? ($payload['variables_json'] ?? []));
    $isActive = payload_boolean($payload, 'is_active', true);

    if ($category === '' || $notificationCode === '' || $templateName === '' || $subject === '' || $bodyHtml === '') {
        json_response(false, 'Category, notification code, template name, subject and HTML body are required.', [], 422);
    }

    return [
        'category' => $category,
        'notification_code' => $notificationCode,
        'template_name' => $templateName,
        'email_subject' => $subject,
        'body_html' => $bodyHtml,
        'body_text' => $bodyText !== '' ? $bodyText : null,
        'variables_json' => $variables !== [] ? json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        'is_active' => $isActive,
    ];
}

function list_templates(mysqli $conn): void
{
    $result = mysqli_query($conn, 'SELECT id, category, notification_code, template_name, email_subject, body_html, body_text, variables_json, is_active, created_by, updated_by, created_at, updated_at FROM hy_email_notification_templates ORDER BY created_at DESC, id DESC');
    if ($result === false) {
        json_response(false, 'Unable to load email notification templates.', [], 500);
    }

    $templates = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['variables'] = hyphen_email_template_variables($row['variables_json'] ?? []);
        $row['is_active'] = (int) ($row['is_active'] ?? 0) === 1;
        $templates[] = $row;
    }

    json_response(true, 'Templates loaded successfully.', ['templates' => $templates]);
}

function list_logs(mysqli $conn, array $payload): void
{
    $limit = (int) ($payload['limit'] ?? 50);
    if ($limit < 1 || $limit > 200) {
        $limit = 50;
    }

    $result = mysqli_query($conn, 'SELECT id, template_id, notification_code, recipient_email, email_subject, status, error_message, created_by, sent_at, created_at FROM hy_email_notification_logs ORDER BY id DESC LIMIT ' . $limit);
    if ($result === false) {
        json_response(false, 'Unable to load email notification logs.', [], 500);
    }

    $logs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }

    json_response(true, 'Logs loaded successfully.', ['logs' => $logs]);
}

function get_template(mysqli $conn, array $payload): void
{
    $templateId = (int) ($payload['id'] ?? 0);
    if ($templateId <= 0) {
        json_response(false, 'Template id is required.', [], 422);
    }

    $template = hyphen_email_fetch_template_by_id($conn, $templateId);
    if ($template === null) {
        json_response(false, 'Template not found.', [], 404);
    }

    $template['variables'] = hyphen_email_template_variables($template['variables_json'] ?? []);
    $template['is_active'] = (int) ($template['is_active'] ?? 0) === 1;
    json_response(true, 'Template loaded successfully.', ['template' => $template]);
}

function create_template(mysqli $conn, array $payload): void
{
    $template = validate_template_payload($payload);
    $createdBy = trim((string) ($_SESSION['staff_id'] ?? ''));

    $statement = mysqli_prepare(
        $conn,
        'INSERT INTO hy_email_notification_templates (category, notification_code, template_name, email_subject, body_html, body_text, variables_json, is_active, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' 
    );

    if (!$statement) {
        json_response(false, 'Failed to prepare template creation.', [], 500);
    }

    mysqli_stmt_bind_param(
        $statement,
        'sssssssiss',
        $template['category'],
        $template['notification_code'],
        $template['template_name'],
        $template['email_subject'],
        $template['body_html'],
        $template['body_text'],
        $template['variables_json'],
        $template['is_active'],
        $createdBy,
        $createdBy
    );

    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        json_response(false, 'Unable to create template: ' . $error, [], 500);
    }

    $templateId = mysqli_insert_id($conn);
    mysqli_stmt_close($statement);
    json_response(true, 'Template created successfully.', ['id' => $templateId]);
}

function update_template(mysqli $conn, array $payload): void
{
    $templateId = (int) ($payload['id'] ?? 0);
    if ($templateId <= 0) {
        json_response(false, 'Template id is required.', [], 422);
    }

    if (hyphen_email_fetch_template_by_id($conn, $templateId) === null) {
        json_response(false, 'Template not found.', [], 404);
    }

    $template = validate_template_payload($payload);
    $updatedBy = trim((string) ($_SESSION['staff_id'] ?? ''));

    $statement = mysqli_prepare(
        $conn,
        'UPDATE hy_email_notification_templates SET category = ?, notification_code = ?, template_name = ?, email_subject = ?, body_html = ?, body_text = ?, variables_json = ?, is_active = ?, updated_by = ? WHERE id = ?' 
    );

    if (!$statement) {
        json_response(false, 'Failed to prepare template update.', [], 500);
    }

    mysqli_stmt_bind_param(
        $statement,
        'sssssssisi',
        $template['category'],
        $template['notification_code'],
        $template['template_name'],
        $template['email_subject'],
        $template['body_html'],
        $template['body_text'],
        $template['variables_json'],
        $template['is_active'],
        $updatedBy,
        $templateId
    );

    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        json_response(false, 'Unable to update template: ' . $error, [], 500);
    }

    mysqli_stmt_close($statement);
    json_response(true, 'Template updated successfully.');
}

function delete_template(mysqli $conn, array $payload): void
{
    $templateId = (int) ($payload['id'] ?? 0);
    if ($templateId <= 0) {
        json_response(false, 'Template id is required.', [], 422);
    }

    $statement = mysqli_prepare($conn, 'DELETE FROM hy_email_notification_templates WHERE id = ?');
    if (!$statement) {
        json_response(false, 'Failed to prepare template delete.', [], 500);
    }

    mysqli_stmt_bind_param($statement, 'i', $templateId);
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        json_response(false, 'Unable to delete template: ' . $error, [], 500);
    }

    mysqli_stmt_close($statement);
    json_response(true, 'Template deleted successfully.');
}

function send_test_email(mysqli $conn, array $payload): void
{
    $template = resolve_template($conn, $payload);
    $toEmail = payload_value($payload, 'to_email');
    $variables = payload_json_array($payload, 'variables');

    $result = hyphen_email_send_template($conn, $template, [$toEmail], $variables, [
        'created_by' => trim((string) ($_SESSION['staff_id'] ?? '')),
        'cc' => payload_json_array($payload, 'cc'),
        'bcc' => payload_json_array($payload, 'bcc'),
    ]);

    if (!$result['success']) {
        json_response(false, $result['message'], $result, 422);
    }

    json_response(true, $result['message'], $result);
}

function send_notification(mysqli $conn, array $payload): void
{
    $template = resolve_template($conn, $payload);
    $toEmails = $payload['to_emails'] ?? ($payload['to_email'] ?? []);
    $variables = payload_json_array($payload, 'variables');

    $result = hyphen_email_send_template($conn, $template, $toEmails, $variables, [
        'created_by' => trim((string) ($_SESSION['staff_id'] ?? '')),
        'cc' => payload_json_array($payload, 'cc'),
        'bcc' => payload_json_array($payload, 'bcc'),
    ]);

    if (!$result['success']) {
        json_response(false, $result['message'], $result, 422);
    }

    json_response(true, $result['message'], $result);
}

function resolve_template(mysqli $conn, array $payload): array
{
    $templateId = (int) ($payload['id'] ?? 0);
    $notificationCode = normalize_notification_code(payload_value($payload, 'notification_code'));

    $template = $templateId > 0
        ? hyphen_email_fetch_template_by_id($conn, $templateId)
        : ($notificationCode !== '' ? hyphen_email_fetch_template_by_code($conn, $notificationCode) : null);

    if ($template === null) {
        json_response(false, 'Template not found.', [], 404);
    }

    if ((int) ($template['is_active'] ?? 0) !== 1) {
        json_response(false, 'Template is inactive.', [], 422);
    }

    return $template;
}

function mail_status(): void
{
    $config = hyphen_mail_config(hyphen_mail_connection());
    json_response(true, 'Mail configuration status loaded.', [
        'configured' => hyphen_mail_is_configured($config),
        'missing_config' => hyphen_mail_missing_config_keys($config),
        'source' => $config['source'] ?? 'env',
        'provider' => $config['provider'] ?? 'gmail',
        'host' => $config['host'],
        'port' => $config['port'],
        'encryption' => $config['encryption'],
        'username' => $config['username'],
        'from_name' => $config['from_name'],
        'from_address' => $config['from_address'],
        'reply_to' => $config['reply_to'],
        'has_password' => !empty($config['has_password']),
    ]);
}