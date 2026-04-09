<?php
date_default_timezone_set('Asia/Singapore');
include_once __DIR__ . '/authorization.php';

hyphen_boot_session();

define('HYPHEN_SESSION_TIMEOUT', 7200);

if (!function_exists('hyphen_session_redirect_path')) {
	function hyphen_session_redirect_path(): string
	{
		$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
		$pagesPosition = strpos($scriptName, '/pages/');

		if ($pagesPosition !== false) {
			return rtrim(substr($scriptName, 0, $pagesPosition), '/') . '/index.html';
		}

		return '/index.html';
	}
}

if (!function_exists('hyphen_session_root_prefix')) {
	function hyphen_session_root_prefix(): string
	{
		$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
		$pagesPosition = strpos($scriptName, '/pages/');

		if ($pagesPosition !== false) {
			return rtrim(substr($scriptName, 0, $pagesPosition), '/');
		}

		return '';
	}
}

if (!function_exists('hyphen_build_page_location')) {
	function hyphen_build_page_location(string $pageUrl): string
	{
		$pageUrl = hyphen_normalize_page_key($pageUrl);
		if ($pageUrl === '') {
			return hyphen_session_root_prefix() . '/pages/template/empty_page';
		}

		return hyphen_session_root_prefix() . '/pages/' . $pageUrl;
	}
}

if (!function_exists('hyphen_session_redirect_to_allowed_page')) {
	function hyphen_session_redirect_to_allowed_page(mysqli $conn, string $staffId, array $menuRights): void
	{
		$menuRights = hyphen_normalize_menu_rights($menuRights);

		$statement = mysqli_prepare(
			$conn,
			'SELECT p.menu_id, p.page_url, up.can_view
			 FROM hy_user_pages p
			 LEFT JOIN hy_user_permissions up ON up.page_id = p.id AND up.staff_id = ?
			 ORDER BY p.page_order ASC, p.id ASC'
		);

		if ($statement) {
			mysqli_stmt_bind_param($statement, 's', $staffId);
			mysqli_stmt_execute($statement);
			$result = mysqli_stmt_get_result($statement);

			while ($result && ($row = mysqli_fetch_assoc($result))) {
				$menuId = trim((string) ($row['menu_id'] ?? ''));
				$pageUrl = trim((string) ($row['page_url'] ?? ''));
				$canView = (int) ($row['can_view'] ?? 0) === 1;

				if ($pageUrl === '' || !$canView || !in_array($menuId, $menuRights, true)) {
					continue;
				}

				mysqli_stmt_close($statement);
				header('Location: ' . hyphen_build_page_location($pageUrl));
				exit;
			}

			mysqli_stmt_close($statement);
		}

		header('Location: ' . hyphen_session_root_prefix() . '/pages/template/empty_page');
		exit;
	}
}

if (!function_exists('hyphen_session_enforce_page_access')) {
	function hyphen_session_enforce_page_access(mysqli $conn): void
	{
		$staffId = trim((string) ($_SESSION['staff_id'] ?? ''));
		if ($staffId === '') {
			hyphen_session_destroy_and_redirect();
		}

		$currentPage = hyphen_normalize_page_key((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
		if ($currentPage === '') {
			return;
		}

		$matchedRecord = hyphen_find_permission_record($currentPage);
		if ($matchedRecord === null) {
			return;
		}

		$menuRights = hyphen_normalize_menu_rights(is_array($_SESSION['menu_rights'] ?? null) ? $_SESSION['menu_rights'] : []);
		$requiredAbility = hyphen_page_required_ability($currentPage);
		if (hyphen_can($requiredAbility, $currentPage)) {
			return;
		}

		hyphen_session_redirect_to_allowed_page($conn, $staffId, $menuRights);
	}
}

if (!function_exists('hyphen_session_destroy_and_redirect')) {
	function hyphen_session_destroy_and_redirect(): void
	{
		$_SESSION = [];

		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
		}

		session_destroy();
		header('Location: ' . hyphen_session_redirect_path());
		exit;
	}
}

if (!hyphen_is_authenticated()) {
	hyphen_session_destroy_and_redirect();
}

$sessionTimeout = (int) ($_SESSION['session_timeout'] ?? HYPHEN_SESSION_TIMEOUT);
if ($sessionTimeout <= 0) {
	$sessionTimeout = HYPHEN_SESSION_TIMEOUT;
}

$lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
if ($lastActivity > 0 && (time() - $lastActivity) > $sessionTimeout) {
	hyphen_session_destroy_and_redirect();
}

$_SESSION['last_activity'] = time();

include_once __DIR__ . '/config.php';
hyphen_refresh_session_authorization($conn, (string) ($_SESSION['staff_id'] ?? ''));
hyphen_session_enforce_page_access($conn);