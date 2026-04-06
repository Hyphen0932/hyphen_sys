<?php
include_once '../../../build/config.php';
include_once '../../../build/authorization.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_set_charset($conn, 'utf8mb4');

const DEFAULT_USER_IMAGE = '00000.jpg';

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
	case 'create_user':
		hyphen_require_ability('add', 'sys_admin/system_users_new', null, true);
		create_user($conn);
		break;

	case 'update_user':
		hyphen_require_ability('edit', 'sys_admin/system_users_edit', null, true);
		update_user($conn);
		break;

	default:
		json_response(false, 'Unsupported action.', [], 400);
}

function posted_value(string $key): string
{
	return trim((string) ($_POST[$key] ?? ''));
}

function ensure_upload_directory(string $directory): void
{
	if (is_dir($directory)) {
		return;
	}

	if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
		throw new RuntimeException('Unable to create upload directory.');
	}
}

function random_image_token(): string
{
	return strtolower(bin2hex(random_bytes(3)));
}

function build_image_filename(string $staffId, string $extension): string
{
	$staffId = preg_replace('/[^A-Za-z0-9_-]/', '', $staffId);
	return $staffId . '_' . random_image_token() . '.' . $extension;
}

function remove_previous_image_if_needed(?string $fileName, ?string $replacementFileName = null): void
{
	$fileName = trim((string) $fileName);
	$replacementFileName = trim((string) $replacementFileName);

	if ($fileName === '' || $fileName === DEFAULT_USER_IMAGE || $fileName === $replacementFileName) {
		return;
	}

	$absolutePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'user_image' . DIRECTORY_SEPARATOR . basename($fileName);
	if (is_file($absolutePath)) {
		@unlink($absolutePath);
	}
}

function handle_profile_image(string $staffId, ?string $existingImage = null): string
{
	if (!isset($_FILES['image_url']) || !is_array($_FILES['image_url'])) {
		return $existingImage !== null && trim($existingImage) !== '' ? trim($existingImage) : DEFAULT_USER_IMAGE;
	}

	$file = $_FILES['image_url'];
	$errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
	if ($errorCode === UPLOAD_ERR_NO_FILE) {
		return $existingImage !== null && trim($existingImage) !== '' ? trim($existingImage) : DEFAULT_USER_IMAGE;
	}

	if ($errorCode !== UPLOAD_ERR_OK) {
		json_response(false, 'Image upload failed.', [], 422);
	}

	$extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
	$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	if (!in_array($extension, $allowedExtensions, true)) {
		json_response(false, 'Only JPG, PNG, GIF or WEBP images are allowed.', [], 422);
	}

	if (((int) ($file['size'] ?? 0)) > 2 * 1024 * 1024) {
		json_response(false, 'Image size must not exceed 2MB.', [], 422);
	}

	$uploadDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'user_image';
	ensure_upload_directory($uploadDirectory);

	$targetFileName = build_image_filename($staffId, $extension);
	$targetPath = $uploadDirectory . DIRECTORY_SEPARATOR . $targetFileName;

	if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
		json_response(false, 'Unable to save uploaded image.', [], 500);
	}

	remove_previous_image_if_needed($existingImage, $targetFileName);

	return $targetFileName;
}

function validate_user_payload(string $username, string $staffId, string $email, string $phone, string $designation, string $role): void
{
	if ($username === '' || $staffId === '' || $email === '' || $phone === '' || $designation === '' || $role === '') {
		json_response(false, 'Staff ID, user name, email, phone, designation and role are required.', [], 422);
	}

	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		json_response(false, 'Please enter a valid email address.', [], 422);
	}

	if (strlen($phone) > 10) {
		json_response(false, 'Phone number must not exceed 10 digits.', [], 422);
	}

	if (strlen($designation) > 20) {
		json_response(false, 'Designation must not exceed 20 characters.', [], 422);
	}
}

function load_pages(mysqli $conn): array
{
	$pageResult = mysqli_query($conn, 'SELECT id, menu_id FROM hy_user_pages ORDER BY page_order ASC, id ASC');
	if ($pageResult === false) {
		json_response(false, 'Unable to load page permission definitions.', [], 500);
	}

	$pages = [];
	while ($row = mysqli_fetch_assoc($pageResult)) {
		$pages[] = $row;
	}

	return $pages;
}

function load_valid_menu_ids(mysqli $conn): array
{
	$validMenuIds = [];
	$menuResult = mysqli_query($conn, 'SELECT menu_id FROM hy_user_menu');
	if ($menuResult !== false) {
		while ($row = mysqli_fetch_assoc($menuResult)) {
			$validMenuIds[] = (string) ($row['menu_id'] ?? '');
		}
	}

	return $validMenuIds;
}

function normalize_enabled_menus(array $menuRights, array $validMenuIds): array
{
	$enabledMenus = [];
	foreach ($menuRights as $menuId) {
		$menuId = trim((string) $menuId);
		if ($menuId !== '' && in_array($menuId, $validMenuIds, true)) {
			$enabledMenus[] = $menuId;
		}
	}

	return array_values(array_unique($enabledMenus));
}

function sync_user_permissions(mysqli $conn, string $staffId, array $pages, array $enabledMenus, array $permissions): void
{
	$deleteStatement = mysqli_prepare($conn, 'DELETE FROM hy_user_permissions WHERE staff_id = ?');
	if (!$deleteStatement) {
		json_response(false, 'Failed to prepare permission cleanup.', [], 500);
	}

	mysqli_stmt_bind_param($deleteStatement, 's', $staffId);
	if (!mysqli_stmt_execute($deleteStatement)) {
		$error = mysqli_stmt_error($deleteStatement);
		mysqli_stmt_close($deleteStatement);
		json_response(false, 'Failed to clear page permissions: ' . $error, [], 500);
	}
	mysqli_stmt_close($deleteStatement);

	$permissionStatement = mysqli_prepare($conn, 'INSERT INTO hy_user_permissions (page_id, staff_id, can_view, can_add, can_edit, can_delete, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
	if (!$permissionStatement) {
		json_response(false, 'Failed to prepare permission insert.', [], 500);
	}

	$status = 'active';
	foreach ($pages as $page) {
		$pageId = (int) ($page['id'] ?? 0);
		$pageMenuId = (string) ($page['menu_id'] ?? '');
		$pagePermissions = is_array($permissions[$pageId] ?? null) ? $permissions[$pageId] : [];

		$menuEnabled = in_array($pageMenuId, $enabledMenus, true);
		$canView = $menuEnabled && isset($pagePermissions['view']) ? 1 : 0;
		$canAdd = $menuEnabled && isset($pagePermissions['add']) ? 1 : 0;
		$canEdit = $menuEnabled && isset($pagePermissions['edit']) ? 1 : 0;
		$canDelete = $menuEnabled && isset($pagePermissions['delete']) ? 1 : 0;

		mysqli_stmt_bind_param($permissionStatement, 'isiiiis', $pageId, $staffId, $canView, $canAdd, $canEdit, $canDelete, $status);
		if (!mysqli_stmt_execute($permissionStatement)) {
			$error = mysqli_stmt_error($permissionStatement);
			mysqli_stmt_close($permissionStatement);
			json_response(false, 'Failed to save page permissions: ' . $error, [], 500);
		}
	}

	mysqli_stmt_close($permissionStatement);
}

function fetch_user_by_id(mysqli $conn, int $userId): ?array
{
	$statement = mysqli_prepare($conn, 'SELECT id, username, staff_id, password, email, phone, designation, role, status, menu_rights, image_url FROM hy_users WHERE id = ? LIMIT 1');
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

function create_user(mysqli $conn): void
{
	$username = posted_value('username');
	$staffId = posted_value('staff_id');
	$email = posted_value('email');
	$phone = posted_value('phone');
	$designation = posted_value('designation');
	$role = posted_value('role');
	$menuRights = $_POST['menu_rights'] ?? [];
	$permissions = $_POST['permissions'] ?? [];

	validate_user_payload($username, $staffId, $email, $phone, $designation, $role);

	$duplicateStatement = mysqli_prepare($conn, 'SELECT id FROM hy_users WHERE username = ? OR staff_id = ? OR email = ? LIMIT 1');
	if (!$duplicateStatement) {
		json_response(false, 'Failed to prepare duplicate check.', [], 500);
	}

	mysqli_stmt_bind_param($duplicateStatement, 'sss', $username, $staffId, $email);
	mysqli_stmt_execute($duplicateStatement);
	mysqli_stmt_store_result($duplicateStatement);

	if (mysqli_stmt_num_rows($duplicateStatement) > 0) {
		mysqli_stmt_close($duplicateStatement);
		json_response(false, 'Username, staff ID or email already exists.', [], 409);
	}

	mysqli_stmt_close($duplicateStatement);

	$pages = load_pages($conn);
	$validMenuIds = load_valid_menu_ids($conn);
	$enabledMenus = normalize_enabled_menus((array) $menuRights, $validMenuIds);

	$imageUrl = handle_profile_image($staffId);
	$passwordHash = password_hash($staffId, PASSWORD_DEFAULT);
	$menuRightsJson = json_encode($enabledMenus, JSON_UNESCAPED_UNICODE);

	mysqli_begin_transaction($conn);

	$userStatement = mysqli_prepare($conn, 'INSERT INTO hy_users (username, staff_id, password, email, phone, designation, role, status, menu_rights, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
	if (!$userStatement) {
		mysqli_rollback($conn);
		json_response(false, 'Failed to prepare user insert.', [], 500);
	}

	$status = 'active';
	mysqli_stmt_bind_param($userStatement, 'ssssssssss', $username, $staffId, $passwordHash, $email, $phone, $designation, $role, $status, $menuRightsJson, $imageUrl);

	if (!mysqli_stmt_execute($userStatement)) {
		$error = mysqli_stmt_error($userStatement);
		mysqli_stmt_close($userStatement);
		mysqli_rollback($conn);
		json_response(false, 'Failed to create user: ' . $error, [], 500);
	}

	mysqli_stmt_close($userStatement);

	try {
		sync_user_permissions($conn, $staffId, $pages, $enabledMenus, (array) $permissions);
	} catch (Throwable $exception) {
		mysqli_rollback($conn);
		json_response(false, $exception->getMessage(), [], 500);
	}

	mysqli_commit($conn);

	json_response(true, 'User created successfully. Default password is the Staff ID.', [
		'staff_id' => $staffId,
	]);
}

function update_user(mysqli $conn): void
{
	$userId = (int) ($_POST['user_id'] ?? 0);
	$currentStaffId = posted_value('current_staff_id');
	$username = posted_value('username');
	$email = posted_value('email');
	$phone = posted_value('phone');
	$designation = posted_value('designation');
	$role = posted_value('role');
	$status = posted_value('status');
	$newPassword = (string) ($_POST['password'] ?? '');
	$menuRights = $_POST['menu_rights'] ?? [];
	$permissions = $_POST['permissions'] ?? [];

	if ($userId <= 0 || $currentStaffId === '') {
		json_response(false, 'Invalid user reference.', [], 422);
	}

	$existingUser = fetch_user_by_id($conn, $userId);
	if (!$existingUser) {
		json_response(false, 'User record not found.', [], 404);
	}

	if ((string) ($existingUser['staff_id'] ?? '') !== $currentStaffId) {
		json_response(false, 'User reference mismatch.', [], 409);
	}

	validate_user_payload($username, $currentStaffId, $email, $phone, $designation, $role);

	if ($status === '') {
		$status = (string) ($existingUser['status'] ?? 'active');
	}

	$duplicateStatement = mysqli_prepare($conn, 'SELECT id FROM hy_users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
	if (!$duplicateStatement) {
		json_response(false, 'Failed to prepare duplicate check.', [], 500);
	}

	mysqli_stmt_bind_param($duplicateStatement, 'ssi', $username, $email, $userId);
	mysqli_stmt_execute($duplicateStatement);
	mysqli_stmt_store_result($duplicateStatement);
	if (mysqli_stmt_num_rows($duplicateStatement) > 0) {
		mysqli_stmt_close($duplicateStatement);
		json_response(false, 'Username or email already exists.', [], 409);
	}
	mysqli_stmt_close($duplicateStatement);

	$pages = load_pages($conn);
	$validMenuIds = load_valid_menu_ids($conn);
	$enabledMenus = normalize_enabled_menus((array) $menuRights, $validMenuIds);
	$imageUrl = handle_profile_image($currentStaffId, (string) ($existingUser['image_url'] ?? ''));
	$menuRightsJson = json_encode($enabledMenus, JSON_UNESCAPED_UNICODE);
	$passwordToStore = trim($newPassword) !== '' ? password_hash($newPassword, PASSWORD_DEFAULT) : (string) ($existingUser['password'] ?? '');

	mysqli_begin_transaction($conn);

	$userStatement = mysqli_prepare($conn, 'UPDATE hy_users SET username = ?, password = ?, email = ?, phone = ?, designation = ?, role = ?, status = ?, menu_rights = ?, image_url = ? WHERE id = ?');
	if (!$userStatement) {
		mysqli_rollback($conn);
		json_response(false, 'Failed to prepare user update.', [], 500);
	}

	mysqli_stmt_bind_param($userStatement, 'sssssssssi', $username, $passwordToStore, $email, $phone, $designation, $role, $status, $menuRightsJson, $imageUrl, $userId);
	if (!mysqli_stmt_execute($userStatement)) {
		$error = mysqli_stmt_error($userStatement);
		mysqli_stmt_close($userStatement);
		mysqli_rollback($conn);
		json_response(false, 'Failed to update user: ' . $error, [], 500);
	}
	mysqli_stmt_close($userStatement);

	try {
		sync_user_permissions($conn, $currentStaffId, $pages, $enabledMenus, (array) $permissions);
	} catch (Throwable $exception) {
		mysqli_rollback($conn);
		json_response(false, $exception->getMessage(), [], 500);
	}

	mysqli_commit($conn);

	if ((string) ($_SESSION['staff_id'] ?? '') === $currentStaffId) {
		$_SESSION['username'] = $username;
		$_SESSION['role'] = $role;
		$_SESSION['image_url'] = $imageUrl;
		hyphen_refresh_session_authorization($conn, $currentStaffId);
	}

	json_response(true, 'User updated successfully.');
}