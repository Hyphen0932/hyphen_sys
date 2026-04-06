<?php

if (!function_exists('hyphen_boot_session')) {
	function hyphen_boot_session(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			return;
		}

		session_name('hyphen_sys');
		session_set_cookie_params([
			'httponly' => true,
			'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
			'samesite' => 'Lax',
		]);
		session_start();
	}
}

if (!function_exists('hyphen_is_authenticated')) {
	function hyphen_is_authenticated(): bool
	{
		return isset($_SESSION['username'], $_SESSION['role'], $_SESSION['staff_id']);
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

if (!function_exists('hyphen_normalize_menu_rights')) {
	function hyphen_normalize_menu_rights(array $menuRights): array
	{
		$normalized = [];
		foreach ($menuRights as $menuId) {
			$menuId = trim((string) $menuId);
			if ($menuId !== '') {
				$normalized[] = $menuId;
			}
		}

		return array_values(array_unique($normalized));
	}
}

if (!function_exists('hyphen_permission_aliases')) {
	function hyphen_permission_aliases(string $pageKey): array
	{
		$pageKey = hyphen_normalize_page_key($pageKey);
		if ($pageKey === '') {
			return [];
		}

		$aliases = [$pageKey];
		foreach (['_new', '_edit'] as $suffix) {
			if (substr($pageKey, -strlen($suffix)) === $suffix) {
				$aliases[] = substr($pageKey, 0, -strlen($suffix));
			}
		}

		return array_values(array_unique(array_filter($aliases)));
	}
}

if (!function_exists('hyphen_page_schema_features')) {
	function hyphen_page_schema_features(mysqli $conn): array
	{
		static $cache = null;
		if (is_array($cache)) {
			return $cache;
		}

		$cache = [
			'permission_target_page_id' => false,
			'required_ability' => false,
			'show_in_sidebar' => false,
			'show_in_breadcrumb' => false,
		];

		$sql = "SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'hy_user_pages' AND column_name IN ('permission_target_page_id', 'required_ability', 'show_in_sidebar', 'show_in_breadcrumb')";
		$result = mysqli_query($conn, $sql);
		if ($result === false) {
			return $cache;
		}

		while ($row = mysqli_fetch_assoc($result)) {
			$columnName = (string) ($row['column_name'] ?? '');
			if (array_key_exists($columnName, $cache)) {
				$cache[$columnName] = true;
			}
		}

		return $cache;
	}
}

if (!function_exists('hyphen_infer_ability_from_page_key')) {
	function hyphen_infer_ability_from_page_key(string $pageKey): string
	{
		$pageKey = hyphen_normalize_page_key($pageKey);
		if ($pageKey === '') {
			return 'view';
		}

		if (substr($pageKey, -4) === '_new') {
			return 'add';
		}

		if (substr($pageKey, -5) === '_edit') {
			return 'edit';
		}

		return 'view';
	}
}

if (!function_exists('hyphen_fetch_page_registry')) {
	function hyphen_fetch_page_registry(mysqli $conn): array
	{
		$features = hyphen_page_schema_features($conn);
		$select = 'SELECT p.id, p.menu_id, p.display_name, p.page_name, p.page_url, p.page_order';
		$join = '';

		if ($features['permission_target_page_id']) {
			$select .= ', p.permission_target_page_id';
			$join .= ' LEFT JOIN hy_user_pages tp ON tp.id = p.permission_target_page_id';
			$select .= ', tp.page_url AS target_page_url';
		} else {
			$select .= ', NULL AS permission_target_page_id, NULL AS target_page_url';
		}

		if ($features['required_ability']) {
			$select .= ', p.required_ability';
		} else {
			$select .= ", 'view' AS required_ability";
		}

		if ($features['show_in_sidebar']) {
			$select .= ', p.show_in_sidebar';
		} else {
			$select .= ', 1 AS show_in_sidebar';
		}

		if ($features['show_in_breadcrumb']) {
			$select .= ', p.show_in_breadcrumb';
		} else {
			$select .= ', 1 AS show_in_breadcrumb';
		}

		$query = $select . ' FROM hy_user_pages p' . $join . ' ORDER BY p.page_order ASC, p.id ASC';
		$result = mysqli_query($conn, $query);
		if ($result === false) {
			return [];
		}

		$registry = [];
		while ($row = mysqli_fetch_assoc($result)) {
			$pageKey = hyphen_normalize_page_key((string) ($row['page_url'] ?? ''));
			if ($pageKey === '') {
				continue;
			}

			$targetPageKey = hyphen_normalize_page_key((string) ($row['target_page_url'] ?? ''));
			if ($targetPageKey === '') {
				$targetPageKey = $pageKey;
			}

			$requiredAbility = strtolower(trim((string) ($row['required_ability'] ?? '')));
			if (!in_array($requiredAbility, ['view', 'add', 'edit', 'delete'], true)) {
				$requiredAbility = hyphen_infer_ability_from_page_key($pageKey);
			}

			$registry[$pageKey] = [
				'id' => (int) ($row['id'] ?? 0),
				'menu_id' => trim((string) ($row['menu_id'] ?? '')),
				'display_name' => trim((string) ($row['display_name'] ?? '')),
				'page_name' => trim((string) ($row['page_name'] ?? '')),
				'page_key' => $pageKey,
				'page_order' => trim((string) ($row['page_order'] ?? '')),
				'permission_target_page_id' => (int) ($row['permission_target_page_id'] ?? 0),
				'permission_target_key' => $targetPageKey,
				'required_ability' => $requiredAbility,
				'show_in_sidebar' => (int) ($row['show_in_sidebar'] ?? 1) === 1,
				'show_in_breadcrumb' => (int) ($row['show_in_breadcrumb'] ?? 1) === 1,
			];
		}

		return $registry;
	}
}

if (!function_exists('hyphen_fetch_user_menu_rights')) {
	function hyphen_fetch_user_menu_rights(mysqli $conn, string $staffId): array
	{
		$statement = mysqli_prepare($conn, 'SELECT menu_rights FROM hy_users WHERE staff_id = ? LIMIT 1');
		if (!$statement) {
			return [];
		}

		mysqli_stmt_bind_param($statement, 's', $staffId);
		mysqli_stmt_execute($statement);
		$result = mysqli_stmt_get_result($statement);
		$row = $result ? mysqli_fetch_assoc($result) : null;
		mysqli_stmt_close($statement);

		$menuRights = json_decode((string) ($row['menu_rights'] ?? '[]'), true);
		return is_array($menuRights) ? hyphen_normalize_menu_rights($menuRights) : [];
	}
}

if (!function_exists('hyphen_fetch_permission_map')) {
	function hyphen_fetch_permission_map(mysqli $conn, string $staffId): array
	{
		$statement = mysqli_prepare(
			$conn,
			'SELECT p.menu_id, p.page_url, up.can_view, up.can_add, up.can_edit, up.can_delete
			 FROM hy_user_pages p
			 LEFT JOIN hy_user_permissions up ON up.page_id = p.id AND up.staff_id = ?'
		);

		if (!$statement) {
			return [];
		}

		mysqli_stmt_bind_param($statement, 's', $staffId);
		mysqli_stmt_execute($statement);
		$result = mysqli_stmt_get_result($statement);
		$permissionMap = [];

		while ($result && ($row = mysqli_fetch_assoc($result))) {
			$pageKey = hyphen_normalize_page_key((string) ($row['page_url'] ?? ''));
			if ($pageKey === '') {
				continue;
			}

			$permissionMap[$pageKey] = [
				'menu_id' => trim((string) ($row['menu_id'] ?? '')),
				'view' => (int) ($row['can_view'] ?? 0) === 1,
				'add' => (int) ($row['can_add'] ?? 0) === 1,
				'edit' => (int) ($row['can_edit'] ?? 0) === 1,
				'delete' => (int) ($row['can_delete'] ?? 0) === 1,
			];
		}

		mysqli_stmt_close($statement);
		return $permissionMap;
	}
}

if (!function_exists('hyphen_refresh_session_authorization')) {
	function hyphen_refresh_session_authorization(mysqli $conn, ?string $staffId = null): array
	{
		$staffId = trim((string) ($staffId ?? ($_SESSION['staff_id'] ?? '')));
		if ($staffId === '') {
			$_SESSION['menu_rights'] = [];
			$_SESSION['permissions'] = [];
			return [
				'menu_rights' => [],
				'permissions' => [],
			];
		}

		$menuRights = hyphen_fetch_user_menu_rights($conn, $staffId);
		$pageRegistry = hyphen_fetch_page_registry($conn);
		$permissions = hyphen_fetch_permission_map($conn, $staffId);

		$_SESSION['menu_rights'] = $menuRights;
		$_SESSION['page_registry'] = $pageRegistry;
		$_SESSION['permissions'] = $permissions;

		return [
			'menu_rights' => $menuRights,
			'page_registry' => $pageRegistry,
			'permissions' => $permissions,
		];
	}
}

if (!function_exists('hyphen_page_registry')) {
	function hyphen_page_registry(): array
	{
		return is_array($_SESSION['page_registry'] ?? null) ? $_SESSION['page_registry'] : [];
	}
}

if (!function_exists('hyphen_find_page_definition')) {
	function hyphen_find_page_definition(?string $pageKey = null): ?array
	{
		$pageKey = $pageKey === null ? (string) ($_SERVER['SCRIPT_NAME'] ?? '') : $pageKey;
		$pageKey = hyphen_normalize_page_key($pageKey);
		if ($pageKey === '') {
			return null;
		}

		$pageRegistry = hyphen_page_registry();
		if (isset($pageRegistry[$pageKey]) && is_array($pageRegistry[$pageKey])) {
			return $pageRegistry[$pageKey];
		}

		$aliases = hyphen_permission_aliases($pageKey);
		foreach ($aliases as $alias) {
			if (!isset($pageRegistry[$alias]) || !is_array($pageRegistry[$alias])) {
				continue;
			}

			$record = $pageRegistry[$alias];
			$record['page_key'] = $pageKey;
			$record['permission_target_key'] = $record['permission_target_key'] ?: $alias;
			$record['required_ability'] = hyphen_infer_ability_from_page_key($pageKey);
			return $record;
		}

		return null;
	}
}

if (!function_exists('hyphen_session_permission_map')) {
	function hyphen_session_permission_map(): array
	{
		return is_array($_SESSION['permissions'] ?? null) ? $_SESSION['permissions'] : [];
	}
}

if (!function_exists('hyphen_find_permission_record')) {
	function hyphen_find_permission_record(?string $pageKey = null): ?array
	{
		$pageDefinition = hyphen_find_page_definition($pageKey);
		if ($pageDefinition === null) {
			return null;
		}

		$permissionMap = hyphen_session_permission_map();
		$targetKey = trim((string) ($pageDefinition['permission_target_key'] ?? $pageDefinition['page_key'] ?? ''));
		if ($targetKey === '' || !isset($permissionMap[$targetKey]) || !is_array($permissionMap[$targetKey])) {
			return null;
		}

		$record = $permissionMap[$targetKey];
		$record['page_key'] = (string) ($pageDefinition['page_key'] ?? $targetKey);
		$record['permission_target_key'] = $targetKey;
		$record['required_ability'] = (string) ($pageDefinition['required_ability'] ?? 'view');
		$record['show_in_sidebar'] = !empty($pageDefinition['show_in_sidebar']);
		$record['show_in_breadcrumb'] = !empty($pageDefinition['show_in_breadcrumb']);
		$record['display_name'] = (string) ($pageDefinition['display_name'] ?? '');
		if (!isset($record['menu_id'])) {
			$record['menu_id'] = (string) ($pageDefinition['menu_id'] ?? '');
		}

		return $record;
	}
}

if (!function_exists('hyphen_can')) {
	function hyphen_can(string $ability, ?string $pageKey = null): bool
	{
		$ability = strtolower(trim($ability));
		if (!in_array($ability, ['view', 'add', 'edit', 'delete'], true)) {
			return false;
		}

		$record = hyphen_find_permission_record($pageKey);
		if ($record === null) {
			return false;
		}

		$menuRights = hyphen_normalize_menu_rights(is_array($_SESSION['menu_rights'] ?? null) ? $_SESSION['menu_rights'] : []);
		$menuId = trim((string) ($record['menu_id'] ?? ''));
		if ($menuId !== '' && !in_array($menuId, $menuRights, true)) {
			return false;
		}

		if ($ability === 'view') {
			return !empty($record['view']);
		}

		return !empty($record['view']) && !empty($record[$ability]);
	}
}

if (!function_exists('hyphen_page_required_ability')) {
	function hyphen_page_required_ability(?string $pageKey = null): string
	{
		$pageDefinition = hyphen_find_page_definition($pageKey);
		if ($pageDefinition === null) {
			return 'view';
		}

		$requiredAbility = strtolower(trim((string) ($pageDefinition['required_ability'] ?? 'view')));
		return in_array($requiredAbility, ['view', 'add', 'edit', 'delete'], true) ? $requiredAbility : 'view';
	}
}

if (!function_exists('hyphen_page_auth')) {
	function hyphen_page_auth(?string $pageKey = null): array
	{
		$normalizedPageKey = $pageKey === null ? hyphen_normalize_page_key((string) ($_SERVER['SCRIPT_NAME'] ?? '')) : hyphen_normalize_page_key($pageKey);
		$record = hyphen_find_permission_record($normalizedPageKey);

		return [
			'page_key' => $normalizedPageKey,
			'record' => $record,
			'required_ability' => hyphen_page_required_ability($normalizedPageKey),
			'show_in_sidebar' => !empty($record['show_in_sidebar']),
			'show_in_breadcrumb' => !empty($record['show_in_breadcrumb']),
			'view' => hyphen_can('view', $normalizedPageKey),
			'add' => hyphen_can('add', $normalizedPageKey),
			'edit' => hyphen_can('edit', $normalizedPageKey),
			'delete' => hyphen_can('delete', $normalizedPageKey),
		];
	}
}

if (!function_exists('hyphen_bind_page_auth')) {
	function hyphen_bind_page_auth(?string $pageKey = null, string $globalKey = 'pageAuth'): array
	{
		$auth = hyphen_page_auth($pageKey);
		$GLOBALS[$globalKey] = $auth;
		$GLOBALS['canView'] = $auth['view'];
		$GLOBALS['canAdd'] = $auth['add'];
		$GLOBALS['canEdit'] = $auth['edit'];
		$GLOBALS['canDelete'] = $auth['delete'];

		return $auth;
	}
}

if (!function_exists('hyphen_require_ability')) {
	function hyphen_require_ability(string $ability, ?string $pageKey = null, ?mysqli $conn = null, bool $jsonResponse = false): void
	{
		if (hyphen_can($ability, $pageKey)) {
			return;
		}

		$message = 'You do not have permission to perform this action.';
		if ($jsonResponse) {
			http_response_code(403);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode([
				'success' => false,
				'message' => $message,
				'data' => [],
			]);
			exit;
		}

		if ($conn instanceof mysqli && function_exists('hyphen_session_redirect_to_allowed_page')) {
			hyphen_session_redirect_to_allowed_page(
				$conn,
				(string) ($_SESSION['staff_id'] ?? ''),
				hyphen_normalize_menu_rights(is_array($_SESSION['menu_rights'] ?? null) ? $_SESSION['menu_rights'] : [])
			);
		}

		http_response_code(403);
		exit($message);
	}
}