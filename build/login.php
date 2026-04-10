<?php
include_once __DIR__ . '/api_bootstrap.php';

hyphen_api_bootstrap([
	'boot_session' => false,
	'require_auth' => false,
	'refresh_authorization' => false,
	'allowed_methods' => ['POST'],
	'audit' => [
		'page_key' => 'auth/login',
		'fixed_action' => 'login',
	],
]);

$staffId = trim((string) ($_POST['staff_id'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$remember = isset($_POST['remember']) && $_POST['remember'] === '1';

if ($staffId === '' || $password === '') {
	json_response(false, 'Staff ID and password are required.', [], 422);
}

session_name('hyphen_sys');
session_set_cookie_params([
	'lifetime' => $remember ? 86400 * 30 : 0,
	'httponly' => true,
	'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
	'samesite' => 'Lax',
]);

hyphen_boot_session();

$statement = mysqli_prepare($conn, 'SELECT username, staff_id, password, role, status, menu_rights, image_url FROM hy_users WHERE staff_id = ? LIMIT 1');
if (!$statement) {
	json_response(false, 'Failed to prepare login query.', [], 500);
}

mysqli_stmt_bind_param($statement, 's', $staffId);
mysqli_stmt_execute($statement);
$result = mysqli_stmt_get_result($statement);
$user = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($statement);

if (!$user) {
	json_response(false, 'Invalid Staff ID or password.', [], 401);
}


$status = strtolower(trim((string) ($user['status'] ?? '')));
if (!login_status_is_active($status)) {
	json_response(false, 'This account is inactive.', [], 403);
}

$storedPassword = (string) ($user['password'] ?? '');
$passwordMatches = password_verify($password, $storedPassword);

if (!$passwordMatches) {
	$passwordMatches = hash_equals($storedPassword, $password);
	if ($passwordMatches) {
		$rehash = password_hash($password, PASSWORD_DEFAULT);
		$updateStatement = mysqli_prepare($conn, 'UPDATE hy_users SET password = ? WHERE staff_id = ?');
		if ($updateStatement) {
			mysqli_stmt_bind_param($updateStatement, 'ss', $rehash, $staffId);
			mysqli_stmt_execute($updateStatement);
			mysqli_stmt_close($updateStatement);
		}
		$storedPassword = $rehash;
	}
	}

if (!$passwordMatches) {
	json_response(false, 'Invalid Staff ID or password.', [], 401);
}

if (password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
	$rehash = password_hash($password, PASSWORD_DEFAULT);
	$updateStatement = mysqli_prepare($conn, 'UPDATE hy_users SET password = ? WHERE staff_id = ?');
	if ($updateStatement) {
		mysqli_stmt_bind_param($updateStatement, 'ss', $rehash, $staffId);
		mysqli_stmt_execute($updateStatement);
		mysqli_stmt_close($updateStatement);
	}
}

session_regenerate_id(true);

$_SESSION['username'] = (string) ($user['username'] ?? '');
$_SESSION['staff_id'] = (string) ($user['staff_id'] ?? '');
$_SESSION['role'] = (string) ($user['role'] ?? '');
$_SESSION['image_url'] = (string) ($user['image_url'] ?? '');
$_SESSION['last_activity'] = time();
$_SESSION['session_timeout'] = $remember ? 86400 * 30 : 7200;

$authorization = hyphen_refresh_session_authorization($conn, (string) ($user['staff_id'] ?? ''));
$menuRights = $authorization['menu_rights'] ?? [];

$redirectUrl = determine_redirect_url($conn, (string) ($user['staff_id'] ?? ''), $menuRights);

json_response(true, 'Login successful.', [
	'redirect_url' => $redirectUrl,
]);

function determine_redirect_url(mysqli $conn, string $staffId, array $menuRights): string
{
	$dashboardPath = 'pages/home/user_dashboard';
	$dashboardFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'home' . DIRECTORY_SEPARATOR . 'user_dashboard.php';
	if (is_file($dashboardFile)) {
		return $dashboardPath;
	}

	if ($staffId !== '') {
		$permissionStatement = mysqli_prepare(
			$conn,
			'SELECT p.page_url
			 FROM hy_user_permissions up
			 INNER JOIN hy_user_pages p ON p.id = up.page_id
			 WHERE up.staff_id = ? AND up.can_view = 1
			 ORDER BY p.page_order ASC, p.id ASC
			 LIMIT 1'
		);

		if ($permissionStatement) {
			mysqli_stmt_bind_param($permissionStatement, 's', $staffId);
			mysqli_stmt_execute($permissionStatement);
			$result = mysqli_stmt_get_result($permissionStatement);
			$row = $result ? mysqli_fetch_assoc($result) : null;
			mysqli_stmt_close($permissionStatement);

			if ($row && !empty($row['page_url'])) {
				return build_page_target((string) $row['page_url']);
			}
		}
	}

	if (!empty($menuRights)) {
		$pageStatement = mysqli_prepare($conn, 'SELECT page_url FROM hy_user_pages WHERE menu_id = ? ORDER BY page_order ASC, id ASC LIMIT 1');
		if ($pageStatement) {
			$menuId = (string) reset($menuRights);
			mysqli_stmt_bind_param($pageStatement, 's', $menuId);
			mysqli_stmt_execute($pageStatement);
			$result = mysqli_stmt_get_result($pageStatement);
			$row = $result ? mysqli_fetch_assoc($result) : null;
			mysqli_stmt_close($pageStatement);

			if ($row && !empty($row['page_url'])) {
				return build_page_target((string) $row['page_url']);
			}
		}
	}

	return 'pages/template/empty_page';
}

function login_status_is_active(string $status): bool
{
	if ($status === '') {
		return true;
	}

	return $status === 'active';
}

function build_page_target(string $pageUrl): string
{
	$pageUrl = trim(str_replace('\\', '/', trim($pageUrl)), '/');
	if ($pageUrl === '') {
		return 'pages/template/empty_page';
	}

	$pageUrl = preg_replace('/\.php$/i', '', $pageUrl);

	if (strpos($pageUrl, 'pages/') === 0) {
		return $pageUrl;
	}

	return 'pages/' . $pageUrl;
}