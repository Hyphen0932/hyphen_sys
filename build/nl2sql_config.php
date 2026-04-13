<?php

if (!function_exists('hyphen_nl2sql_table_exists')) {
	function hyphen_nl2sql_table_exists(mysqli $conn, string $tableName): bool
	{
		$escapedTable = mysqli_real_escape_string($conn, $tableName);
		$result = mysqli_query($conn, "SHOW TABLES LIKE '{$escapedTable}'");
		$exists = $result !== false && mysqli_num_rows($result) > 0;
		if ($result !== false) {
			mysqli_free_result($result);
		}

		return $exists;
	}
}

if (!function_exists('hyphen_nl2sql_env_allowed_tables')) {
	function hyphen_nl2sql_env_allowed_tables(): array
	{
		$raw = (string) (getenv('AI_ALLOWED_TABLES') ?: 'hy_users,hy_user_menu,hy_user_pages,hy_user_permissions');
		$tables = array_map('trim', explode(',', $raw));
		$tables = array_values(array_filter($tables, static function ($item): bool {
			return $item !== '';
		}));

		return array_values(array_unique($tables));
	}
}

if (!function_exists('hyphen_nl2sql_available_tables')) {
	function hyphen_nl2sql_available_tables(mysqli $conn): array
	{
		$tables = [];
		$result = mysqli_query($conn, 'SHOW TABLES');
		if ($result === false) {
			return hyphen_nl2sql_env_allowed_tables();
		}

		while ($row = mysqli_fetch_row($result)) {
			$tableName = trim((string) ($row[0] ?? ''));
			if ($tableName !== '') {
				$tables[] = $tableName;
			}
		}

		mysqli_free_result($result);
		sort($tables);
		return $tables;
	}
}

if (!function_exists('hyphen_nl2sql_default_config')) {
	function hyphen_nl2sql_default_config(): array
	{
		return [
			'id' => 0,
			'provider' => 'ollama',
			'model_name' => (string) (getenv('OLLAMA_MODEL') ?: 'qwen2.5-coder:7b'),
			'allowed_tables' => hyphen_nl2sql_env_allowed_tables(),
			'result_row_limit' => (int) (getenv('AI_RESULT_ROW_LIMIT') ?: 50),
			'prompt_notes' => '',
			'is_enabled' => 1,
			'is_active' => 1,
			'source' => 'env-default',
			'created_by' => null,
			'updated_by' => null,
			'created_at' => null,
			'updated_at' => null,
		];
	}
}

if (!function_exists('hyphen_nl2sql_decode_tables')) {
	function hyphen_nl2sql_decode_tables($value): array
	{
		if (is_string($value) && trim($value) !== '') {
			$decoded = json_decode($value, true);
			if (is_array($decoded)) {
				$value = $decoded;
			} else {
				$value = array_map('trim', explode(',', $value));
			}
		}

		if (!is_array($value)) {
			return [];
		}

		$tables = [];
		foreach ($value as $tableName) {
			$tableName = trim((string) $tableName);
			if ($tableName !== '') {
				$tables[] = $tableName;
			}
		}

		return array_values(array_unique($tables));
	}
}

if (!function_exists('hyphen_nl2sql_normalize_tables')) {
	function hyphen_nl2sql_normalize_tables(array $requestedTables, array $availableTables): array
	{
		$availableMap = array_fill_keys($availableTables, true);
		$normalized = [];
		foreach ($requestedTables as $tableName) {
			if (isset($availableMap[$tableName])) {
				$normalized[] = $tableName;
			}
		}

		return array_values(array_unique($normalized));
	}
}

if (!function_exists('hyphen_nl2sql_db_config')) {
	function hyphen_nl2sql_db_config(mysqli $conn): ?array
	{
		if (!hyphen_nl2sql_table_exists($conn, 'hy_nl2sql_configurations')) {
			return null;
		}

		$result = mysqli_query(
			$conn,
			'SELECT id, provider, model_name, allowed_tables_json, result_row_limit, prompt_notes, is_enabled, is_active, created_by, updated_by, created_at, updated_at
			 FROM hy_nl2sql_configurations
			 WHERE is_active = 1
			 ORDER BY updated_at DESC, id DESC
			 LIMIT 1'
		);

		if ($result === false) {
			return null;
		}

		$row = mysqli_fetch_assoc($result) ?: null;
		mysqli_free_result($result);
		if (!is_array($row)) {
			return null;
		}

		$row['allowed_tables'] = hyphen_nl2sql_decode_tables($row['allowed_tables_json'] ?? []);
		$row['result_row_limit'] = max(1, (int) ($row['result_row_limit'] ?? 50));
		$row['is_enabled'] = (int) ($row['is_enabled'] ?? 0);
		$row['is_active'] = (int) ($row['is_active'] ?? 0);
		$row['source'] = 'database';

		return $row;
	}
}

if (!function_exists('hyphen_nl2sql_config')) {
	function hyphen_nl2sql_config(mysqli $conn): array
	{
		$dbConfig = hyphen_nl2sql_db_config($conn);
		if ($dbConfig === null) {
			return hyphen_nl2sql_default_config();
		}

		$config = array_merge(hyphen_nl2sql_default_config(), $dbConfig);
		$config['allowed_tables'] = $dbConfig['allowed_tables'] !== []
			? $dbConfig['allowed_tables']
			: hyphen_nl2sql_default_config()['allowed_tables'];

		return $config;
	}
}

if (!function_exists('hyphen_nl2sql_user_policy')) {
	function hyphen_nl2sql_user_policy(mysqli $conn, string $staffId): ?array
	{
		$staffId = trim($staffId);
		if ($staffId === '' || !hyphen_nl2sql_table_exists($conn, 'hy_nl2sql_user_policies')) {
			return null;
		}

		$statement = mysqli_prepare(
			$conn,
			'SELECT id, staff_id, is_enabled, allowed_tables_json, max_row_limit, can_view_sql, can_include_rows, notes, created_by, updated_by, created_at, updated_at
			 FROM hy_nl2sql_user_policies
			 WHERE staff_id = ?
			 LIMIT 1'
		);
		if (!$statement) {
			return null;
		}

		mysqli_stmt_bind_param($statement, 's', $staffId);
		mysqli_stmt_execute($statement);
		$result = mysqli_stmt_get_result($statement);
		$row = $result ? mysqli_fetch_assoc($result) : null;
		mysqli_stmt_close($statement);

		if (!is_array($row)) {
			return null;
		}

		$row['allowed_tables'] = hyphen_nl2sql_decode_tables($row['allowed_tables_json'] ?? []);
		$row['is_enabled'] = (int) ($row['is_enabled'] ?? 0);
		$row['max_row_limit'] = isset($row['max_row_limit']) ? (int) $row['max_row_limit'] : null;
		$row['can_view_sql'] = (int) ($row['can_view_sql'] ?? 0);
		$row['can_include_rows'] = (int) ($row['can_include_rows'] ?? 0);

		return $row;
	}
}

if (!function_exists('hyphen_nl2sql_effective_runtime')) {
	function hyphen_nl2sql_effective_runtime(mysqli $conn, string $staffId): array
	{
		$config = hyphen_nl2sql_config($conn);
		$policy = hyphen_nl2sql_user_policy($conn, $staffId);
		$availableTables = hyphen_nl2sql_available_tables($conn);
		$globalTables = hyphen_nl2sql_normalize_tables($config['allowed_tables'] ?? [], $availableTables);
		$enabled = (int) ($config['is_enabled'] ?? 0) === 1;
		$canViewSql = true;
		$canIncludeRows = true;
		$rowLimit = max(1, (int) ($config['result_row_limit'] ?? 50));
		$effectiveTables = $globalTables;

		if (is_array($policy)) {
			$enabled = $enabled && ((int) ($policy['is_enabled'] ?? 0) === 1);
			$canViewSql = (int) ($policy['can_view_sql'] ?? 0) === 1;
			$canIncludeRows = (int) ($policy['can_include_rows'] ?? 0) === 1;
			if (!empty($policy['allowed_tables'])) {
				$userTables = hyphen_nl2sql_normalize_tables($policy['allowed_tables'], $availableTables);
				$effectiveTables = array_values(array_intersect($globalTables, $userTables));
			}
			if (!empty($policy['max_row_limit'])) {
				$rowLimit = min($rowLimit, max(1, (int) $policy['max_row_limit']));
			}
		}

		return [
			'enabled' => $enabled,
			'provider' => (string) ($config['provider'] ?? 'ollama'),
			'model_name' => (string) ($config['model_name'] ?? ''),
			'allowed_tables' => $effectiveTables,
			'row_limit' => $rowLimit,
			'prompt_notes' => trim((string) ($config['prompt_notes'] ?? '')),
			'can_view_sql' => $canViewSql,
			'can_include_rows' => $canIncludeRows,
			'config' => $config,
			'policy' => $policy,
		];
	}
}