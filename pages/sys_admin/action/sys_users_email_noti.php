<?php
include_once '../../../build/api_bootstrap.php';
include_once '../../../build/email_notifications.php';

const NEW_USER_LOGIN_TEMPLATE_CODE = 'NF-00001';

hyphen_api_bootstrap([
	'allowed_methods' => ['POST'],
	'audit' => [
		'page_key' => 'sys_admin/system_users',
	],
]);

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
		json_response(false, 'User id is required.', [], 422);
	}

	$user = fetch_user_notification_target($conn, $userId);
	if ($user === null) {
		json_response(false, 'User record not found.', [], 404);
	}

	$email = trim((string) ($user['email'] ?? ''));
	$staffId = trim((string) ($user['staff_id'] ?? ''));
	$username = trim((string) ($user['username'] ?? ''));
	$role = trim((string) ($user['role'] ?? ''));
	$status = trim((string) ($user['status'] ?? ''));

	if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
		json_response(false, 'This user does not have a valid email address.', [], 422);
	}

	if ($staffId === '') {
		json_response(false, 'This user does not have a valid login user ID.', [], 422);
	}

	$template = hyphen_email_fetch_template_by_code($conn, NEW_USER_LOGIN_TEMPLATE_CODE);
	if ($template === null) {
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
		json_response(false, $result['message'], $result, 422);
	}

	json_response(true, 'Login email sent successfully to ' . $email . '.', [
		'recipient_email' => $email,
		'notification_code' => NEW_USER_LOGIN_TEMPLATE_CODE,
		'login_user_id' => $staffId,
		'default_password_rule' => 'Default password equals Staff ID.',
		'login_url' => hyphen_absolute_login_url(),
		'mail_result' => $result,
	]);
}