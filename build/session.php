<?php
date_default_timezone_set('Asia/Singapore');

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_name('hyphen_sys');
	session_set_cookie_params([
		'httponly' => true,
		'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
		'samesite' => 'Lax',
	]);
	session_start();
}

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

if (!function_exists('hyphen_normalize_page_key')) {
	function hyphen_normalize_page_key(string $value): string
	{
		$value = str_replace('\\', '/', trim($value));
		$value = preg_replace('#^https?://[^/]+#i', '', $value);
		$pagesPosition = strpos($value, '/pages/');
		if ($pagesPosition !== false) {
			$value = substr($value, $pagesPosition + 7);
		}

		$value = ltrim($value, '/');
		$value = preg_replace('/\.php$/i', '', $value);

		return trim((string) $value, '/');
	}
}

if (!function_exists('hyphen_page_permission_aliases')) {
	function hyphen_page_permission_aliases(string $pageKey): array
	{
		$aliases = [];
		$pageKey = hyphen_normalize_page_key($pageKey);
		if ($pageKey !== '') {
			$aliases[] = $pageKey;
		}

		if ($pageKey !== '' && preg_match('/_edit$/', $pageKey) === 1) {
			$aliases[] = preg_replace('/_edit$/', '', $pageKey);
		}

		return array_values(array_unique(array_filter($aliases)));
	}
}

if (!function_exists('hyphen_build_page_location')) {
	function hyphen_build_page_location(string $pageUrl): string
	{
		$pageUrl = hyphen_normalize_page_key($pageUrl);
		if ($pageUrl === '') {
			return hyphen_session_root_prefix() . '/pages/template/empty_page.php';
		}

		return hyphen_session_root_prefix() . '/pages/' . $pageUrl . '.php';
	}
}

if (!function_exists('hyphen_session_redirect_to_allowed_page')) {
	function hyphen_session_redirect_to_allowed_page(mysqli $conn, string $staffId, array $menuRights): void
	{
		$menuRights = array_values(array_unique(array_filter(array_map(static function ($value) {
			return trim((string) $value);
		}, $menuRights))));

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

		header('Location: ' . hyphen_session_root_prefix() . '/pages/template/empty_page.php');
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

		$aliases = hyphen_page_permission_aliases($currentPage);
		if (empty($aliases)) {
			return;
		}

		$menuRights = is_array($_SESSION['menu_rights'] ?? null) ? $_SESSION['menu_rights'] : [];
		$menuRights = array_values(array_unique(array_filter(array_map(static function ($value) {
			return trim((string) $value);
		}, $menuRights))));

		$statement = mysqli_prepare(
			$conn,
			'SELECT p.menu_id, p.page_url, COALESCE(up.can_view, 0) AS can_view
			 FROM hy_user_pages p
			 LEFT JOIN hy_user_permissions up ON up.page_id = p.id AND up.staff_id = ?'
		);

		if (!$statement) {
			return;
		}

		mysqli_stmt_bind_param($statement, 's', $staffId);
		mysqli_stmt_execute($statement);
		$result = mysqli_stmt_get_result($statement);
		$matchedRecord = null;

		while ($result && ($row = mysqli_fetch_assoc($result))) {
			$pageKey = hyphen_normalize_page_key((string) ($row['page_url'] ?? ''));
			if ($pageKey === '' || !in_array($pageKey, $aliases, true)) {
				continue;
			}

			$matchedRecord = $row;
			break;
		}

		mysqli_stmt_close($statement);

		if ($matchedRecord === null) {
			return;
		}

		$menuId = trim((string) ($matchedRecord['menu_id'] ?? ''));
		$canView = (int) ($matchedRecord['can_view'] ?? 0) === 1;
		if ($canView && in_array($menuId, $menuRights, true)) {
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

if (!isset($_SESSION['username'], $_SESSION['role'], $_SESSION['menu_rights'], $_SESSION['staff_id'])) {
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
hyphen_session_enforce_page_access($conn);