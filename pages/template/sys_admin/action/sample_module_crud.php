<?php
include_once '../../../../build/config.php';
include_once '../../../../build/authorization.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_set_charset($conn, 'utf8mb4');

const SAMPLE_MODULE_TABLE = 'your_table_name';

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$action = trim((string) ($_POST['action'] ?? ''));

switch ($action) {
    case 'create_record':
        hyphen_require_ability('add', 'sys_admin/sample_module_new', null, true);
        create_record($conn);
        break;

    case 'update_record':
        hyphen_require_ability('edit', 'sys_admin/sample_module_edit', null, true);
        update_record($conn);
        break;

    default:
        json_response(false, 'Unsupported action.', [], 400);
}

function posted_value(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function validate_payload(string $recordCode, string $recordName, string $status): void
{
    if ($recordCode === '' || $recordName === '' || $status === '') {
        json_response(false, 'Code, name and status are required.', [], 422);
    }
}

function create_record(mysqli $conn): void
{
    $recordCode = posted_value('record_code');
    $recordName = posted_value('record_name');
    $status = posted_value('status');

    validate_payload($recordCode, $recordName, $status);

    // Replace this SQL with the real insert for your module.
    $sql = 'INSERT INTO ' . SAMPLE_MODULE_TABLE . ' (record_code, record_name, status) VALUES (?, ?, ?)';
    $statement = mysqli_prepare($conn, $sql);
    if (!$statement) {
        json_response(false, 'Replace SAMPLE_MODULE_TABLE and insert SQL before using this action file.', [], 500);
    }

    mysqli_stmt_bind_param($statement, 'sss', $recordCode, $recordName, $status);
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        json_response(false, 'Failed to create record: ' . $error, [], 500);
    }

    mysqli_stmt_close($statement);
    json_response(true, 'Record created successfully.');
}

function update_record(mysqli $conn): void
{
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $recordCode = posted_value('record_code');
    $recordName = posted_value('record_name');
    $status = posted_value('status');

    if ($recordId <= 0) {
        json_response(false, 'Invalid record ID.', [], 422);
    }

    validate_payload($recordCode, $recordName, $status);

    // Replace this SQL with the real update for your module.
    $sql = 'UPDATE ' . SAMPLE_MODULE_TABLE . ' SET record_code = ?, record_name = ?, status = ? WHERE id = ?';
    $statement = mysqli_prepare($conn, $sql);
    if (!$statement) {
        json_response(false, 'Replace SAMPLE_MODULE_TABLE and update SQL before using this action file.', [], 500);
    }

    mysqli_stmt_bind_param($statement, 'sssi', $recordCode, $recordName, $status, $recordId);
    if (!mysqli_stmt_execute($statement)) {
        $error = mysqli_stmt_error($statement);
        mysqli_stmt_close($statement);
        json_response(false, 'Failed to update record: ' . $error, [], 500);
    }

    mysqli_stmt_close($statement);
    json_response(true, 'Record updated successfully.');
}