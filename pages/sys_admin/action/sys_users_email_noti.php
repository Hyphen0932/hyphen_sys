<?php
include_once '../../../build/config.php';
include_once '../../../build/authorization.php';
include_once '../../../build/audit.php';
include_once '../../../build/email_notifications.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_set_charset($conn, 'utf8mb4');

const NEW_USER_LOGIN_TEMPLATE_CODE = 'NF-00001';

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
	case 'send_new_user_login_email':
		hyphen_require_ability('edit', 'sys_admin/system_users_edit', null, true);
		send_new_user_login_email($conn);
		break;

	default:
		json_response(false, 'Unsupported action.', [], 400);
}

function posted_value(string $key): string
{
	return trim((string) ($_POST[$key] ?? ''));
}

function fetch_user_notification_target(mysqli $conn, int $userId): ?array
{
	$statement = mysqli_prepare($conn, 'SELECT id, username, staff_id, email, role, status FROM hy_users WHERE id = ? LIMIT 1');
	if (!$statement) {
		json_response(false, 'Failed to prepare user lookup.', [], 500);
	}

	mysqli_stmt_bind_param($statement, 'i', $userId);
	mysqli_stmt_execute($statement);
	$result = mysqli_stmt_get_result($statement);
	$user = $result ? mysqli_fetch_assoc($result) : null;
	mysqli_stmt_close($statement);

	return $user ?: null;
}

function hyphen_absolute_login_url(): string
{
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
	$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
	$pagesPosition = strpos($scriptName, '/pages/');
	$rootPrefix = $pagesPosition !== false ? rtrim(substr($scriptName, 0, $pagesPosition), '/') : '';
	$loginPath = ($rootPrefix !== '' ? $rootPrefix : '') . '/index.html';

	if ($host === '') {
		return $loginPath;
	}

	return $scheme . '://' . $host . $loginPath;
}

function send_new_user_login_email(mysqli $conn): void
{
	$userId = (int) ($_POST['user_id'] ?? 0);
	if ($userId <= 0) {
		hyphen_audit_action($conn, 'sys_users', 'send_login_email', [
			'status' => 'failed',
			'entity_type' => 'user',
			'metadata' => ['reason' => 'missing_user_id'],
		]);
		json_response(false, 'User id is required.', [], 422);
	}

	$user = fetch_user_notification_target($conn, $userId);
	if ($user === null) {
		hyphen_audit_action($conn, 'sys_users', 'send_login_email', [
			'status' => 'failed',
			'entity_type' => 'user',
			'entity_id' => (string) $userId,
			'metadata' => ['reason' => 'user_not_found'],
		]);
		json_response(false, 'User record not found.', [], 404);
	}

	$email = trim((string) ($user['email'] ?? ''));
	$staffId = trim((string) ($user['staff_id'] ?? ''));
	$username = trim((string) ($user['username'] ?? ''));
	$role = trim((string) ($user['role'] ?? ''));
	$status = trim((string) ($user['status'] ?? ''));
	$auditSummary = [
		'email' => $email,
		'role' => $role,
		'status' => $status,
		'notification_code' => NEW_USER_LOGIN_TEMPLATE_CODE,
	];

	if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		hyphen_audit_action($conn, 'sys_users', 'send_login_email', [
			'status' => 'failed',
			'entity_type' => 'user',
			'entity_id' => (string) $userId,
			'target_label' => $staffId,
			'metadata' => ['reason' => 'invalid_email', 'summary' => $auditSummary],
		]);
		json_response(false, 'This user does not have a valid email address.', [], 422);
	}

	if ($staffId === '') {
		hyphen_audit_action($conn, 'sys_users', 'send_login_email', [
			'status' => 'failed',
			'entity_type' => 'user',
			'entity_id' => (string) $userId,
			'metadata' => ['reason' => 'missing_staff_id', 'summary' => $auditSummary],
		]);
		json_response(false, 'This user does not have a valid login user ID.', [], 422);
	}

	$template = hyphen_email_fetch_template_by_code($conn, NEW_USER_LOGIN_TEMPLATE_CODE);
	if ($template === null) {
		hyphen_audit_action($conn, 'sys_users', 'send_login_email', [
			'status' => 'failed',
			'entity_type' => 'user',
			'entity_id' => (string) $userId,
			'target_label' => $staffId,
			'metadata' => ['reason' => 'template_not_found', 'summary' => $auditSummary],
		]);
		json_response(false, 'Email template NF-00001 was not found or is inactive.', [], 404);
	}

	$defaultPassword = $staffId;
	$result = hyphen_email_send_template($conn, $template, [$email], [
		'username' => $username,
		'user_name' => $username,
		'user_id' => $staffId,
		'login_user_id' => $staffId,
		'staff_id' => $staffId,
		'default_password' => $defaultPassword,
		'password' => $defaultPassword,
		'email' => $email,
		'role' => $role,
		'status' => $status,
		'login_url' => hyphen_absolute_login_url(),
	], [
		'created_by' => trim((string) ($_SESSION['staff_id'] ?? '')),
	]);

	if (!$result['success']) {
		hyphen_audit_action($conn, 'sys_users', 'send_login_email', [
			'status' => 'failed',
			'entity_type' => 'user',
			'entity_id' => (string) $userId,
			'target_label' => $staffId,
			'metadata' => [
				'summary' => $auditSummary,
				'mail_result' => $result,
			],
		]);
		json_response(false, $result['message'], $result, 422);
	}

	hyphen_audit_action($conn, 'sys_users', 'send_login_email', [
		'entity_type' => 'user',
		'entity_id' => (string) $userId,
		'target_label' => $staffId,
		'metadata' => [
			'summary' => $auditSummary,
			'login_url' => hyphen_absolute_login_url(),
		],
	]);

	json_response(true, 'Login email sent successfully to ' . $email . '.', [
		'recipient_email' => $email,
		'notification_code' => NEW_USER_LOGIN_TEMPLATE_CODE,
		'login_user_id' => $staffId,
		'default_password_rule' => 'Default password equals Staff ID.',
		'login_url' => hyphen_absolute_login_url(),
		'mail_result' => $result,
	]);
}