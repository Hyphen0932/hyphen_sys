<?php

include_once __DIR__ . '/../../../build/api_bootstrap.php';
include_once __DIR__ . '/../../../build/nl2sql_config.php';

hyphen_api_bootstrap([
	'allowed_methods' => ['GET', 'POST'],
	'audit' => [
		'page_key' => 'sys_admin/nl2sql_config',
	],
]);

$payload = api_request_payload();
$action = trim((string) ($payload['action'] ?? 'get_configuration'));

switch ($action) {
	case 'get_configuration':
		hyphen_require_ability('view', 'sys_admin/nl2sql_config', null, true);
		get_configuration($conn);
		break;

	case 'list_user_policies':
		hyphen_require_ability('view', 'sys_admin/nl2sql_config', null, true);
		list_user_policies($conn);
		break;

	case 'get_user_policy':
		hyphen_require_ability('view', 'sys_admin/nl2sql_config', null, true);
		get_user_policy($conn, $payload);
		break;

	case 'update_configuration':
		hyphen_require_ability('edit', 'sys_admin/nl2sql_config', null, true);
		update_configuration($conn, $payload);
		break;

	case 'save_user_policy':
		hyphen_require_ability('edit', 'sys_admin/nl2sql_config', null, true);
		save_user_policy($conn, $payload);
		break;

	default:
		json_response(false, 'Unsupported action.', [], 400);
}

function nl2sql_payload_value(array $payload, string $key): string
{
	return trim((string) ($payload[$key] ?? ''));
}

function nl2sql_payload_bool(array $payload, string $key, bool $default = false): int
{
	if (!array_key_exists($key, $payload)) {
		return $default ? 1 : 0;
	}

	$value = $payload[$key];
	if (is_bool($value)) {
		return $value ? 1 : 0;
	}

	return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
}

function nl2sql_payload_tables(mysqli $conn, array $payload, string $key): array
{
	$value = $payload[$key] ?? [];
	$tables = hyphen_nl2sql_decode_tables($value);
	return hyphen_nl2sql_normalize_tables($tables, hyphen_nl2sql_available_tables($conn));
}

function validate_global_configuration(mysqli $conn, array $payload): array
{
	$provider = strtolower(nl2sql_payload_value($payload, 'provider')) ?: 'ollama';
	$modelName = nl2sql_payload_value($payload, 'model_name');
	$resultRowLimit = (int) ($payload['result_row_limit'] ?? 50);
	$promptNotes = trim((string) ($payload['prompt_notes'] ?? ''));
	$isEnabled = nl2sql_payload_bool($payload, 'is_enabled', true);
	$allowedTables = nl2sql_payload_tables($conn, $payload, 'allowed_tables');

	if ($modelName === '') {
		json_response(false, 'Model name is required.', [], 422);
	}

	if (!in_array($provider, ['ollama'], true)) {
		json_response(false, 'Only ollama is supported in the current implementation.', [], 422);
	}

	if ($resultRowLimit < 1 || $resultRowLimit > 500) {
		json_response(false, 'Result row limit must be between 1 and 500.', [], 422);
	}

	if ($allowedTables === []) {
		json_response(false, 'Select at least one allowed table.', [], 422);
	}

	return [
		'provider' => $provider,
		'model_name' => $modelName,
		'allowed_tables' => $allowedTables,
		'result_row_limit' => $resultRowLimit,
		'prompt_notes' => $promptNotes !== '' ? $promptNotes : null,
		'is_enabled' => $isEnabled,
	];
}

function validate_user_policy(mysqli $conn, array $payload): array
{
	$staffId = nl2sql_payload_value($payload, 'staff_id');
	$isEnabled = nl2sql_payload_bool($payload, 'is_enabled', true);
	$allowedTables = nl2sql_payload_tables($conn, $payload, 'allowed_tables');
	$maxRowLimitRaw = trim((string) ($payload['max_row_limit'] ?? ''));
	$canViewSql = nl2sql_payload_bool($payload, 'can_view_sql', true);
	$canIncludeRows = nl2sql_payload_bool($payload, 'can_include_rows', true);
	$notes = trim((string) ($payload['notes'] ?? ''));

	if ($staffId === '') {
		json_response(false, 'Staff ID is required.', [], 422);
	}

	$maxRowLimit = null;
	if ($maxRowLimitRaw !== '') {
		$maxRowLimit = (int) $maxRowLimitRaw;
		if ($maxRowLimit < 1 || $maxRowLimit > 500) {
			json_response(false, 'Max row limit must be between 1 and 500.', [], 422);
		}
	}

	$staffStatement = mysqli_prepare($conn, 'SELECT staff_id FROM hy_users WHERE staff_id = ? LIMIT 1');
	if (!$staffStatement) {
		json_response(false, 'Failed to validate selected user.', [], 500);
	}

	mysqli_stmt_bind_param($staffStatement, 's', $staffId);
	mysqli_stmt_execute($staffStatement);
	$result = mysqli_stmt_get_result($staffStatement);
	$userExists = $result && mysqli_fetch_assoc($result);
	mysqli_stmt_close($staffStatement);

	if (!$userExists) {
		json_response(false, 'Selected user was not found.', [], 422);
	}

	return [
		'staff_id' => $staffId,
		'is_enabled' => $isEnabled,
		'allowed_tables' => $allowedTables,
		'max_row_limit' => $maxRowLimit,
		'can_view_sql' => $canViewSql,
		'can_include_rows' => $canIncludeRows,
		'notes' => $notes !== '' ? $notes : null,
	];
}

function get_configuration(mysqli $conn): void
{
	$config = hyphen_nl2sql_config($conn);
	json_response(true, 'NL2SQL configuration loaded successfully.', [
		'configuration' => $config,
		'available_tables' => hyphen_nl2sql_available_tables($conn),
	]);
}

function list_user_policies(mysqli $conn): void
{
	$result = mysqli_query(
		$conn,
		'SELECT u.id, u.username, u.staff_id, u.email, u.role, u.status,
			p.is_enabled, p.allowed_tables_json, p.max_row_limit, p.can_view_sql, p.can_include_rows, p.notes, p.updated_at
		 FROM hy_users u
		 LEFT JOIN hy_nl2sql_user_policies p ON p.staff_id = u.staff_id
		 ORDER BY u.username ASC, u.id ASC'
	);
	if ($result === false) {
		json_response(false, 'Unable to load user policies.', [], 500);
	}

	$users = [];
	while ($row = mysqli_fetch_assoc($result)) {
		$row['allowed_tables'] = hyphen_nl2sql_decode_tables($row['allowed_tables_json'] ?? []);
		$row['has_policy'] = array_key_exists('is_enabled', $row) && $row['is_enabled'] !== null;
		$row['is_enabled'] = (int) ($row['is_enabled'] ?? 1);
		$row['can_view_sql'] = (int) ($row['can_view_sql'] ?? 1);
		$row['can_include_rows'] = (int) ($row['can_include_rows'] ?? 1);
		$row['max_row_limit'] = isset($row['max_row_limit']) ? (int) $row['max_row_limit'] : null;
		$users[] = $row;
	}
	mysqli_free_result($result);

	json_response(true, 'User policies loaded successfully.', [
		'users' => $users,
	]);
}

function get_user_policy(mysqli $conn, array $payload): void
{
	$staffId = nl2sql_payload_value($payload, 'staff_id');
	if ($staffId === '') {
		json_response(false, 'Staff ID is required.', [], 422);
	}

	$statement = mysqli_prepare(
		$conn,
		'SELECT u.id, u.username, u.staff_id, u.email, u.role, u.status,
			p.is_enabled, p.allowed_tables_json, p.max_row_limit, p.can_view_sql, p.can_include_rows, p.notes, p.updated_at
		 FROM hy_users u
		 LEFT JOIN hy_nl2sql_user_policies p ON p.staff_id = u.staff_id
		 WHERE u.staff_id = ?
		 LIMIT 1'
	);
	if (!$statement) {
		json_response(false, 'Unable to load user policy.', [], 500);
	}

	mysqli_stmt_bind_param($statement, 's', $staffId);
	mysqli_stmt_execute($statement);
	$result = mysqli_stmt_get_result($statement);
	$row = $result ? mysqli_fetch_assoc($result) : null;
	mysqli_stmt_close($statement);

	if (!is_array($row)) {
		json_response(false, 'User was not found.', [], 404);
	}

	$row['allowed_tables'] = hyphen_nl2sql_decode_tables($row['allowed_tables_json'] ?? []);
	$row['has_policy'] = array_key_exists('is_enabled', $row) && $row['is_enabled'] !== null;
	$row['is_enabled'] = (int) ($row['is_enabled'] ?? 1);
	$row['can_view_sql'] = (int) ($row['can_view_sql'] ?? 1);
	$row['can_include_rows'] = (int) ($row['can_include_rows'] ?? 1);
	$row['max_row_limit'] = isset($row['max_row_limit']) ? (int) $row['max_row_limit'] : null;

	json_response(true, 'User policy loaded successfully.', [
		'user' => $row,
		'available_tables' => hyphen_nl2sql_available_tables($conn),
	]);
}

function update_configuration(mysqli $conn, array $payload): void
{
	if (!hyphen_nl2sql_table_exists($conn, 'hy_nl2sql_configurations')) {
		json_response(false, 'NL2SQL configuration table is not available yet. Run database migrations first.', [], 500);
	}

	$config = validate_global_configuration($conn, $payload);
	$updatedBy = trim((string) ($_SESSION['staff_id'] ?? ''));

	mysqli_begin_transaction($conn);
	try {
		mysqli_query($conn, 'UPDATE hy_nl2sql_configurations SET is_active = 0');

		$statement = mysqli_prepare(
			$conn,
			'INSERT INTO hy_nl2sql_configurations (provider, model_name, allowed_tables_json, result_row_limit, prompt_notes, is_enabled, is_active, created_by, updated_by)
			 VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)'
		);
		if (!$statement) {
			throw new RuntimeException('Failed to prepare configuration save statement.');
		}

		$tablesJson = json_encode($config['allowed_tables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		mysqli_stmt_bind_param(
			$statement,
			'sssisiss',
			$config['provider'],
			$config['model_name'],
			$tablesJson,
			$config['result_row_limit'],
			$config['prompt_notes'],
			$config['is_enabled'],
			$updatedBy,
			$updatedBy
		);

		if (!mysqli_stmt_execute($statement)) {
			$error = mysqli_stmt_error($statement);
			mysqli_stmt_close($statement);
			throw new RuntimeException('Unable to save NL2SQL configuration: ' . $error);
		}
		mysqli_stmt_close($statement);
		mysqli_commit($conn);
	} catch (Throwable $exception) {
		mysqli_rollback($conn);
		json_response(false, $exception->getMessage(), [], 500);
	}

	json_response(true, 'NL2SQL configuration saved successfully.', [
		'configuration' => hyphen_nl2sql_config($conn),
		'available_tables' => hyphen_nl2sql_available_tables($conn),
	]);
}

function save_user_policy(mysqli $conn, array $payload): void
{
	if (!hyphen_nl2sql_table_exists($conn, 'hy_nl2sql_user_policies')) {
		json_response(false, 'NL2SQL user policy table is not available yet. Run database migrations first.', [], 500);
	}

	$policy = validate_user_policy($conn, $payload);
	$updatedBy = trim((string) ($_SESSION['staff_id'] ?? ''));
	$tablesJson = $policy['allowed_tables'] !== []
		? json_encode($policy['allowed_tables'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
		: null;

	$statement = mysqli_prepare(
		$conn,
		'INSERT INTO hy_nl2sql_user_policies (staff_id, is_enabled, allowed_tables_json, max_row_limit, can_view_sql, can_include_rows, notes, created_by, updated_by)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
		 ON DUPLICATE KEY UPDATE
			is_enabled = VALUES(is_enabled),
			allowed_tables_json = VALUES(allowed_tables_json),
			max_row_limit = VALUES(max_row_limit),
			can_view_sql = VALUES(can_view_sql),
			can_include_rows = VALUES(can_include_rows),
			notes = VALUES(notes),
			updated_by = VALUES(updated_by)'
	);
	if (!$statement) {
		json_response(false, 'Failed to prepare user policy save statement.', [], 500);
	}

	mysqli_stmt_bind_param(
		$statement,
		'sisiiisss',
		$policy['staff_id'],
		$policy['is_enabled'],
		$tablesJson,
		$policy['max_row_limit'],
		$policy['can_view_sql'],
		$policy['can_include_rows'],
		$policy['notes'],
		$updatedBy,
		$updatedBy
	);

	if (!mysqli_stmt_execute($statement)) {
		$error = mysqli_stmt_error($statement);
		mysqli_stmt_close($statement);
		json_response(false, 'Unable to save user policy: ' . $error, [], 500);
	}
	mysqli_stmt_close($statement);

	json_response(true, 'User policy saved successfully.', [
		'policy' => hyphen_nl2sql_user_policy($conn, $policy['staff_id']),
	]);
}