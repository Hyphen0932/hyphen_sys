<?php
include_once '../../../build/config.php';
include_once '../../../build/authorization.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_set_charset($conn, 'utf8mb4');

hyphen_boot_session();

if (!hyphen_is_authenticated()) {
	json_response(false, 'Your session has expired. Please sign in again.', [], 401);
}

hyphen_refresh_session_authorization($conn, (string) ($_SESSION['staff_id'] ?? ''));

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	json_response(false, 'Invalid request method.', [], 405);
}

$action = trim((string) ($_POST['action'] ?? ''));

switch ($action) {
	case 'add_menu':
		hyphen_require_ability('add', 'sys_admin/system_menu', null, true);
		add_menu($conn);
		break;

	case 'update_menu':
		hyphen_require_ability('edit', 'sys_admin/system_menu', null, true);
		update_menu($conn);
		break;

	case 'delete_menu':
		hyphen_require_ability('delete', 'sys_admin/system_menu', null, true);
		delete_menu($conn);
		break;

	case 'add_page':
		hyphen_require_ability('add', 'sys_admin/system_menu', null, true);
		add_page($conn);
		break;

	case 'update_page':
		hyphen_require_ability('edit', 'sys_admin/system_menu', null, true);
		update_page($conn);
		break;

	case 'delete_page':
		hyphen_require_ability('delete', 'sys_admin/system_menu', null, true);
		delete_page($conn);
		break;

	default:
		json_response(false, 'Unsupported action.', [], 400);
}

function posted_value(string $key): string
{
	return trim((string) ($_POST[$key] ?? ''));
}

function posted_checkbox_value(string $key, int $default = 0): int
{
	if (!isset($_POST[$key])) {
		return $default;
	}

	return $_POST[$key] === '1' ? 1 : 0;
}

function normalize_path(string $value): string
{
	$value = str_replace('\\', '/', trim($value));
	$value = ltrim($value, '/');

	if (strpos($value, 'pages/') === 0) {
		$value = substr($value, 6);
	}

	if (substr($value, -4) === '.php') {
		$value = substr($value, 0, -4);
	}

	return $value;
}

function pages_root_path(): string
{
	return dirname(__DIR__, 2);
}

function empty_page_template_path(): string
{
	return pages_root_path() . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR . 'empty_page.php';
}

function page_artifact_debug_info(string $pageUrl): array
{
	$pageUrl = normalize_path($pageUrl);
	$templatePath = empty_page_template_path();
	$targetFilePath = pages_root_path() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pageUrl) . '.php';
	$targetDirectory = dirname($targetFilePath);

	return [
		'normalized_page_url' => $pageUrl,
		'template_path' => $templatePath,
		'template_exists' => is_file($templatePath),
		'target_file_path' => $targetFilePath,
		'target_file_exists' => is_file($targetFilePath),
		'target_directory' => $targetDirectory,
		'target_directory_exists' => is_dir($targetDirectory),
		'target_directory_writable' => is_dir($targetDirectory) ? is_writable($targetDirectory) : false,
		'pages_root' => pages_root_path(),
		'pages_root_writable' => is_writable(pages_root_path()),
	];
}

function validate_relative_page_path(string $relativePath): void
{
	if ($relativePath === '') {
		return;
	}

	$segments = explode('/', $relativePath);
	foreach ($segments as $segment) {
		if ($segment === '' || $segment === '.' || $segment === '..') {
			json_response(false, 'Invalid page path.', [], 422);
		}
	}
}

function ensure_directory_exists(string $directoryPath): void
{
	if (is_dir($directoryPath)) {
		return;
	}

	if (!mkdir($directoryPath, 0777, true) && !is_dir($directoryPath)) {
		throw new RuntimeException('Unable to create directory: ' . $directoryPath);
	}
}

function ensure_menu_directory(string $link): void
{
	$link = normalize_path($link);
	validate_relative_page_path($link);

	if ($link === '') {
		return;
	}

	$directoryPath = pages_root_path() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $link);
	ensure_directory_exists($directoryPath);
}

function assert_page_name_matches_url(string $pageUrl, string $pageName): void
{
	if ($pageUrl === '' || $pageName === '') {
		return;
	}

	$pageUrlBaseName = basename($pageUrl);
	$pageName = preg_replace('/\.php$/i', '', $pageName);

	if ($pageUrlBaseName !== $pageName) {
		json_response(false, 'File name must match the last segment of Page URL.', [], 422);
	}
}

function ensure_page_artifact(string $pageUrl): void
{
	$pageUrl = normalize_path($pageUrl);
	validate_relative_page_path($pageUrl);

	if ($pageUrl === '') {
		return;
	}

	$templatePath = empty_page_template_path();
	if (!is_file($templatePath)) {
		throw new RuntimeException('Template file not found: ' . $templatePath);
	}

	$targetFilePath = pages_root_path() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pageUrl) . '.php';
	$targetDirectory = dirname($targetFilePath);
	ensure_directory_exists($targetDirectory);

	if (is_file($targetFilePath)) {
		return;
	}

	if (!copy($templatePath, $targetFilePath)) {
		$copyError = error_get_last();
		$copyMessage = is_array($copyError) && !empty($copyError['message']) ? ' Copy error: ' . $copyError['message'] : '';
		throw new RuntimeException('Unable to create page file: ' . $targetFilePath . $copyMessage);
	}
}

function require_menu_exists(mysqli $conn, string $menuId): void
{
	$statement = mysqli_prepare($conn, 'SELECT id FROM hy_user_menu WHERE menu_id = ? LIMIT 1');
	if (!$statement) {
		json_response(false, 'Failed to prepare menu validation query.', [], 500);
	}

	mysqli_stmt_bind_param($statement, 's', $menuId);
	mysqli_stmt_execute($statement);
	mysqli_stmt_store_result($statement);

	if (mysqli_stmt_num_rows($statement) === 0) {
		mysqli_stmt_close($statement);
		json_response(false, 'Selected main directory does not exist.', [], 404);
	}

	mysqli_stmt_close($statement);
}

function validate_required_ability(string $ability): string
{
	$ability = strtolower(trim($ability));
	if (!in_array($ability, ['view', 'add', 'edit', 'delete'], true)) {
		return 'view';
	}

	return $ability;
}

function validate_permission_target_page_id(mysqli $conn, string $pageId): ?int
{
	$pageId = trim($pageId);
	if ($pageId === '') {
		return null;
	}

	$targetPageId = (int) $pageId;
	if ($targetPageId <= 0) {
		json_response(false, 'Invalid permission target page.', [], 422);
	}

	$statement = mysqli_prepare($conn, 'SELECT id FROM hy_user_pages WHERE id = ? LIMIT 1');
	if (!$statement) {
		json_response(false, 'Failed to validate permission target page.', [], 500);
	}

	mysqli_stmt_bind_param($statement, 'i', $targetPageId);
	mysqli_stmt_execute($statement);
	mysqli_stmt_store_result($statement);

	if (mysqli_stmt_num_rows($statement) === 0) {
		mysqli_stmt_close($statement);
		json_response(false, 'Selected permission target page does not exist.', [], 404);
	}

	mysqli_stmt_close($statement);
	return $targetPageId;
}

function add_menu(mysqli $conn): void
{
	$category = posted_value('category');
	$link = posted_value('link');
	$menuId = posted_value('menu_id');
	$menuName = posted_value('menu_name');
	$menuIcon = posted_value('menu_icon');

	if ($category === '' || $link === '' || $menuId === '' || $menuName === '') {
		json_response(false, 'Category, system link, menu ID and menu name are required.', [], 422);
	}

	$duplicateStatement = mysqli_prepare($conn, 'SELECT id FROM hy_user_menu WHERE menu_id = ? LIMIT 1');
	if (!$duplicateStatement) {
		json_response(false, 'Failed to prepare duplicate check.', [], 500);
	}

	mysqli_stmt_bind_param($duplicateStatement, 's', $menuId);
	mysqli_stmt_execute($duplicateStatement);
	mysqli_stmt_store_result($duplicateStatement);

	if (mysqli_stmt_num_rows($duplicateStatement) > 0) {
		mysqli_stmt_close($duplicateStatement);
		json_response(false, 'Menu ID already exists.', [], 409);
	}

	mysqli_stmt_close($duplicateStatement);

	validate_relative_page_path(normalize_path($link));
	mysqli_begin_transaction($conn);

	$statement = mysqli_prepare($conn, 'INSERT INTO hy_user_menu (category, link, menu_id, menu_name, menu_icon) VALUES (?, ?, ?, ?, ?)');
	if (!$statement) {
		mysqli_rollback($conn);
		json_response(false, 'Failed to prepare menu insert.', [], 500);
	}

	mysqli_stmt_bind_param($statement, 'sssss', $category, $link, $menuId, $menuName, $menuIcon);
	if (!mysqli_stmt_execute($statement)) {
		$error = mysqli_stmt_error($statement);
		mysqli_stmt_close($statement);
		mysqli_rollback($conn);
		json_response(false, 'Failed to create menu: ' . $error, [], 500);
	}

	mysqli_stmt_close($statement);

	try {
		ensure_menu_directory($link);
		mysqli_commit($conn);
	} catch (Throwable $exception) {
		mysqli_rollback($conn);
		json_response(false, 'Failed to create menu directory: ' . $exception->getMessage(), [], 500);
	}

	json_response(true, 'Menu created successfully.');
}

function update_menu(mysqli $conn): void
{
	$id = (int) posted_value('id');
	$category = posted_value('category');
	$link = posted_value('link');
	$menuId = posted_value('menu_id');
	$menuName = posted_value('menu_name');
	$menuIcon = posted_value('menu_icon');

	if ($id <= 0 || $category === '' || $link === '' || $menuId === '' || $menuName === '') {
		json_response(false, 'Invalid menu payload.', [], 422);
	}

	$duplicateStatement = mysqli_prepare($conn, 'SELECT id FROM hy_user_menu WHERE menu_id = ? AND id <> ? LIMIT 1');
	if (!$duplicateStatement) {
		json_response(false, 'Failed to prepare duplicate check.', [], 500);
	}

	mysqli_stmt_bind_param($duplicateStatement, 'si', $menuId, $id);
	mysqli_stmt_execute($duplicateStatement);
	mysqli_stmt_store_result($duplicateStatement);

	if (mysqli_stmt_num_rows($duplicateStatement) > 0) {
		mysqli_stmt_close($duplicateStatement);
		json_response(false, 'Menu ID already exists.', [], 409);
	}

	mysqli_stmt_close($duplicateStatement);

	$statement = mysqli_prepare($conn, 'UPDATE hy_user_menu SET category = ?, link = ?, menu_id = ?, menu_name = ?, menu_icon = ? WHERE id = ?');
	if (!$statement) {
		json_response(false, 'Failed to prepare menu update.', [], 500);
	}

	mysqli_stmt_bind_param($statement, 'sssssi', $category, $link, $menuId, $menuName, $menuIcon, $id);
	if (!mysqli_stmt_execute($statement)) {
		$error = mysqli_stmt_error($statement);
		mysqli_stmt_close($statement);
		json_response(false, 'Failed to update menu: ' . $error, [], 500);
	}

	mysqli_stmt_close($statement);
	json_response(true, 'Menu updated successfully.');
}

function delete_menu(mysqli $conn): void
{
	$id = (int) posted_value('id');
	if ($id <= 0) {
		json_response(false, 'Invalid menu ID.', [], 422);
	}

	$menuLookup = mysqli_prepare($conn, 'SELECT menu_id FROM hy_user_menu WHERE id = ? LIMIT 1');
	if (!$menuLookup) {
		json_response(false, 'Failed to prepare menu lookup.', [], 500);
	}

	mysqli_stmt_bind_param($menuLookup, 'i', $id);
	mysqli_stmt_execute($menuLookup);
	mysqli_stmt_bind_result($menuLookup, $menuId);

	if (!mysqli_stmt_fetch($menuLookup)) {
		mysqli_stmt_close($menuLookup);
		json_response(false, 'Menu record not found.', [], 404);
	}

	mysqli_stmt_close($menuLookup);

	mysqli_begin_transaction($conn);

	try {
		$deletePages = mysqli_prepare($conn, 'DELETE FROM hy_user_pages WHERE menu_id = ?');
		if (!$deletePages) {
			throw new RuntimeException('Failed to prepare submenu delete.');
		}

		mysqli_stmt_bind_param($deletePages, 's', $menuId);
		if (!mysqli_stmt_execute($deletePages)) {
			$error = mysqli_stmt_error($deletePages);
			mysqli_stmt_close($deletePages);
			throw new RuntimeException($error);
		}
		mysqli_stmt_close($deletePages);

		$deleteMenu = mysqli_prepare($conn, 'DELETE FROM hy_user_menu WHERE id = ?');
		if (!$deleteMenu) {
			throw new RuntimeException('Failed to prepare menu delete.');
		}

		mysqli_stmt_bind_param($deleteMenu, 'i', $id);
		if (!mysqli_stmt_execute($deleteMenu)) {
			$error = mysqli_stmt_error($deleteMenu);
			mysqli_stmt_close($deleteMenu);
			throw new RuntimeException($error);
		}
		mysqli_stmt_close($deleteMenu);

		mysqli_commit($conn);
	} catch (Throwable $exception) {
		mysqli_rollback($conn);
		json_response(false, 'Failed to delete menu: ' . $exception->getMessage(), [], 500);
	}

	json_response(true, 'Menu deleted successfully.');
}

function add_page(mysqli $conn): void
{
	$features = hyphen_page_schema_features($conn);
	$menuId = posted_value('menu_id');
	$displayName = posted_value('display_name');
	$pageUrl = normalize_path(posted_value('page_url'));
	$pageName = posted_value('page_name');
	$pageOrder = posted_value('page_order');
	$requiredAbility = validate_required_ability(posted_value('required_ability'));
	$permissionTargetPageId = validate_permission_target_page_id($conn, posted_value('permission_target_page_id'));
	$showInSidebar = posted_checkbox_value('show_in_sidebar', 0);
	$showInBreadcrumb = posted_checkbox_value('show_in_breadcrumb', 0);

	if ($menuId === '' || $displayName === '' || $pageOrder === '') {
		json_response(false, 'Display name and page order are required.', [], 422);
	}

	if (($pageUrl === '' && $pageName !== '') || ($pageUrl !== '' && $pageName === '')) {
		json_response(false, 'Page URL and file name must both be filled or both be empty.', [], 422);
	}

	assert_page_name_matches_url($pageUrl, $pageName);
	validate_relative_page_path($pageUrl);

	require_menu_exists($conn, $menuId);
	mysqli_begin_transaction($conn);

	if ($features['permission_target_page_id'] && $features['required_ability'] && $features['show_in_sidebar'] && $features['show_in_breadcrumb']) {
		$statement = mysqli_prepare($conn, 'INSERT INTO hy_user_pages (menu_id, display_name, page_name, page_url, page_order, permission_target_page_id, required_ability, show_in_sidebar, show_in_breadcrumb) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
	} else {
		$statement = mysqli_prepare($conn, 'INSERT INTO hy_user_pages (menu_id, display_name, page_name, page_url, page_order) VALUES (?, ?, ?, ?, ?)');
	}
	if (!$statement) {
		mysqli_rollback($conn);
		json_response(false, 'Failed to prepare submenu insert.', [], 500);
	}

	if ($features['permission_target_page_id'] && $features['required_ability'] && $features['show_in_sidebar'] && $features['show_in_breadcrumb']) {
		mysqli_stmt_bind_param($statement, 'sssssisii', $menuId, $displayName, $pageName, $pageUrl, $pageOrder, $permissionTargetPageId, $requiredAbility, $showInSidebar, $showInBreadcrumb);
	} else {
		mysqli_stmt_bind_param($statement, 'sssss', $menuId, $displayName, $pageName, $pageUrl, $pageOrder);
	}
	if (!mysqli_stmt_execute($statement)) {
		$error = mysqli_stmt_error($statement);
		mysqli_stmt_close($statement);
		mysqli_rollback($conn);
		json_response(false, 'Failed to create submenu: ' . $error, [], 500);
	}

	mysqli_stmt_close($statement);

	try {
		ensure_page_artifact($pageUrl);
		mysqli_commit($conn);
	} catch (Throwable $exception) {
		mysqli_rollback($conn);
		json_response(false, 'Failed to create page file: ' . $exception->getMessage(), [
			'page_url' => $pageUrl,
			'page_name' => $pageName,
			'artifact_debug' => page_artifact_debug_info($pageUrl),
		], 500);
	}

	json_response(true, 'Sub menu created successfully.');
}

function update_page(mysqli $conn): void
{
	$features = hyphen_page_schema_features($conn);
	$id = (int) posted_value('id');
	$menuId = posted_value('menu_id');
	$displayName = posted_value('display_name');
	$pageUrl = normalize_path(posted_value('page_url'));
	$pageName = posted_value('page_name');
	$pageOrder = posted_value('page_order');
	$requiredAbility = validate_required_ability(posted_value('required_ability'));
	$permissionTargetPageId = validate_permission_target_page_id($conn, posted_value('permission_target_page_id'));
	$showInSidebar = posted_checkbox_value('show_in_sidebar', 0);
	$showInBreadcrumb = posted_checkbox_value('show_in_breadcrumb', 0);

	if ($id <= 0 || $menuId === '' || $displayName === '' || $pageOrder === '') {
		json_response(false, 'Invalid submenu payload.', [], 422);
	}

	if (($pageUrl === '' && $pageName !== '') || ($pageUrl !== '' && $pageName === '')) {
		json_response(false, 'Page URL and file name must both be filled or both be empty.', [], 422);
	}

	assert_page_name_matches_url($pageUrl, $pageName);
	validate_relative_page_path($pageUrl);

	require_menu_exists($conn, $menuId);

	if ($features['permission_target_page_id'] && $features['required_ability'] && $features['show_in_sidebar'] && $features['show_in_breadcrumb']) {
		$statement = mysqli_prepare($conn, 'UPDATE hy_user_pages SET menu_id = ?, display_name = ?, page_name = ?, page_url = ?, page_order = ?, permission_target_page_id = ?, required_ability = ?, show_in_sidebar = ?, show_in_breadcrumb = ? WHERE id = ?');
	} else {
		$statement = mysqli_prepare($conn, 'UPDATE hy_user_pages SET menu_id = ?, display_name = ?, page_name = ?, page_url = ?, page_order = ? WHERE id = ?');
	}
	if (!$statement) {
		json_response(false, 'Failed to prepare submenu update.', [], 500);
	}

	if ($features['permission_target_page_id'] && $features['required_ability'] && $features['show_in_sidebar'] && $features['show_in_breadcrumb']) {
		mysqli_stmt_bind_param($statement, 'sssssisiii', $menuId, $displayName, $pageName, $pageUrl, $pageOrder, $permissionTargetPageId, $requiredAbility, $showInSidebar, $showInBreadcrumb, $id);
	} else {
		mysqli_stmt_bind_param($statement, 'sssssi', $menuId, $displayName, $pageName, $pageUrl, $pageOrder, $id);
	}
	if (!mysqli_stmt_execute($statement)) {
		$error = mysqli_stmt_error($statement);
		mysqli_stmt_close($statement);
		json_response(false, 'Failed to update submenu: ' . $error, [], 500);
	}

	mysqli_stmt_close($statement);
	json_response(true, 'Sub menu updated successfully.');
}

function delete_page(mysqli $conn): void
{
	$id = (int) posted_value('id');
	if ($id <= 0) {
		json_response(false, 'Invalid submenu ID.', [], 422);
	}

	$statement = mysqli_prepare($conn, 'DELETE FROM hy_user_pages WHERE id = ?');
	if (!$statement) {
		json_response(false, 'Failed to prepare submenu delete.', [], 500);
	}

	mysqli_stmt_bind_param($statement, 'i', $id);
	if (!mysqli_stmt_execute($statement)) {
		$error = mysqli_stmt_error($statement);
		mysqli_stmt_close($statement);
		json_response(false, 'Failed to delete submenu: ' . $error, [], 500);
	}

	mysqli_stmt_close($statement);
	json_response(true, 'Sub menu deleted successfully.');
}
