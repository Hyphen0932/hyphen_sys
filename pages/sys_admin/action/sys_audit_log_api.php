<?php
include_once '../../../build/api_bootstrap.php';

hyphen_api_bootstrap([
	'allowed_methods' => ['GET'],
	'audit' => [
		'enabled' => false,
		'page_key' => 'sys_admin/system_audit_log',
	],
]);

hyphen_require_ability('view', 'sys_admin/system_audit_log', null, true);

$payload = api_request_payload();
$action = trim((string) ($payload['action'] ?? 'list_logs'));

switch ($action) {
	case 'get_summary':
		get_summary($conn, $payload);
		break;

	case 'list_logs':
		list_logs($conn, $payload);
		break;

	default:
		json_response(false, 'Unsupported action.', [], 400);
}

function hyphen_audit_table_exists(mysqli $conn): bool
{
	$result = mysqli_query($conn, "SHOW TABLES LIKE 'hy_audit_logs'");
	$exists = $result !== false && mysqli_num_rows($result) > 0;
	if ($result !== false) {
		mysqli_free_result($result);
	}

	return $exists;
}

function hyphen_audit_table_columns(mysqli $conn): array
{
	static $columns = null;
	if (is_array($columns)) {
		return $columns;
	}

	$columns = [];
	$result = mysqli_query($conn, 'SHOW COLUMNS FROM hy_audit_logs');
	if ($result === false) {
		return $columns;
	}

	while ($row = mysqli_fetch_assoc($result)) {
		$columnName = trim((string) ($row['Field'] ?? ''));
		if ($columnName !== '') {
			$columns[] = $columnName;
		}
	}

	mysqli_free_result($result);
	return $columns;
}

function hyphen_audit_filters(array $payload): array
{
	$dateFrom = trim((string) ($payload['date_from'] ?? ''));
	$dateTo = trim((string) ($payload['date_to'] ?? ''));
	$staffId = trim((string) ($payload['staff_id'] ?? ''));
	$status = strtolower(trim((string) ($payload['status'] ?? '')));
	$action = trim((string) ($payload['audit_action'] ?? ''));
	$endpoint = trim((string) ($payload['endpoint'] ?? ''));
	$limit = (int) ($payload['limit'] ?? 500);

	if ($dateFrom === '') {
		$dateFrom = date('Y-m-d', strtotime('-7 days'));
	}

	if ($dateTo === '') {
		$dateTo = date('Y-m-d');
	}

	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
		json_response(false, 'Date filters must use YYYY-MM-DD format.', [], 422);
	}

	if (!in_array($status, ['', 'success', 'failure'], true)) {
		json_response(false, 'Status filter is invalid.', [], 422);
	}

	if ($limit <= 0) {
		$limit = 100;
	}

	if ($limit > 1000) {
		$limit = 1000;
	}

	return [
		'date_from' => $dateFrom,
		'date_to' => $dateTo,
		'staff_id' => $staffId,
		'status' => $status,
		'audit_action' => $action,
		'endpoint' => $endpoint,
		'limit' => $limit,
	];
}

function hyphen_audit_where_clause(array $filters, array $columns, array &$params, string &$types): string
{
	$where = [];

	if (in_array('created_at', $columns, true)) {
		$where[] = 'created_at >= ?';
		$where[] = 'created_at < DATE_ADD(?, INTERVAL 1 DAY)';
		$params[] = $filters['date_from'];
		$params[] = $filters['date_to'];
		$types .= 'ss';
	}

	if ($filters['staff_id'] !== '' && in_array('staff_id', $columns, true)) {
		$where[] = 'staff_id = ?';
		$params[] = $filters['staff_id'];
		$types .= 's';
	}

	if ($filters['status'] !== '' && in_array('status', $columns, true)) {
		$where[] = 'status = ?';
		$params[] = $filters['status'];
		$types .= 's';
	}

	if ($filters['audit_action'] !== '' && in_array('action', $columns, true)) {
		$where[] = 'action LIKE ?';
		$params[] = '%' . $filters['audit_action'] . '%';
		$types .= 's';
	}

	if ($filters['endpoint'] !== '' && in_array('api_endpoint', $columns, true)) {
		$where[] = 'api_endpoint LIKE ?';
		$params[] = '%' . $filters['endpoint'] . '%';
		$types .= 's';
	}

	if ($where === []) {
		return '';
	}

	return ' WHERE ' . implode(' AND ', $where);
}

function hyphen_audit_select_expression(array $columns, string $column, string $fallback): string
{
	return in_array($column, $columns, true) ? $column : $fallback . ' AS ' . $column;
}

function hyphen_bind_dynamic_params(mysqli_stmt $statement, string $types, array $params): void
{
	if ($types === '') {
		return;
	}

	$bindValues = [$types];
	foreach ($params as $index => $param) {
		$bindValues[] = &$params[$index];
	}

	call_user_func_array([$statement, 'bind_param'], $bindValues);
}

function get_summary(mysqli $conn, array $payload): void
{
	if (!hyphen_audit_table_exists($conn)) {
		json_response(false, 'Audit log table is not available yet. Run database migrations first.', [], 500);
	}

	$filters = hyphen_audit_filters($payload);
	$params = [];
	$types = '';
	$columns = hyphen_audit_table_columns($conn);
	$whereClause = hyphen_audit_where_clause($filters, $columns, $params, $types);
	$statusExpression = in_array('status', $columns, true) ? 'status' : '""';
	$staffIdExpression = in_array('staff_id', $columns, true) ? 'staff_id' : '""';
	$executionTimeExpression = in_array('execution_time_ms', $columns, true)
		? 'AVG(execution_time_ms)'
		: '0';

	$sql = 'SELECT COUNT(*) AS total_logs,
		SUM(CASE WHEN ' . $statusExpression . ' = "success" THEN 1 ELSE 0 END) AS success_logs,
		SUM(CASE WHEN ' . $statusExpression . ' = "failure" THEN 1 ELSE 0 END) AS failure_logs,
		COUNT(DISTINCT CASE WHEN ' . $staffIdExpression . ' <> "" THEN ' . $staffIdExpression . ' END) AS unique_staff,
		' . $executionTimeExpression . ' AS average_execution_ms
		FROM hy_audit_logs' . $whereClause;

	$statement = mysqli_prepare($conn, $sql);
	if (!$statement) {
		json_response(false, 'Failed to prepare audit summary query.', [], 500);
	}

	hyphen_bind_dynamic_params($statement, $types, $params);
	mysqli_stmt_execute($statement);
	$result = mysqli_stmt_get_result($statement);
	$row = $result ? mysqli_fetch_assoc($result) : null;
	mysqli_stmt_close($statement);

	json_response(true, 'Audit summary loaded successfully.', [
		'summary' => [
			'total_logs' => (int) ($row['total_logs'] ?? 0),
			'success_logs' => (int) ($row['success_logs'] ?? 0),
			'failure_logs' => (int) ($row['failure_logs'] ?? 0),
			'unique_staff' => (int) ($row['unique_staff'] ?? 0),
			'average_execution_ms' => (float) ($row['average_execution_ms'] ?? 0),
		],
		'filters' => $filters,
	]);
}

function list_logs(mysqli $conn, array $payload): void
{
	if (!hyphen_audit_table_exists($conn)) {
		json_response(false, 'Audit log table is not available yet. Run database migrations first.', [], 500);
	}

	$filters = hyphen_audit_filters($payload);
	$params = [];
	$types = '';
	$columns = hyphen_audit_table_columns($conn);
	$whereClause = hyphen_audit_where_clause($filters, $columns, $params, $types);
	$idSelect = hyphen_audit_select_expression($columns, 'id', '0');
	$staffIdSelect = hyphen_audit_select_expression($columns, 'staff_id', '""');
	$pageKeySelect = hyphen_audit_select_expression($columns, 'page_key', '""');
	$endpointSelect = hyphen_audit_select_expression($columns, 'api_endpoint', '""');
	$actionSelect = hyphen_audit_select_expression($columns, 'action', '""');
	$methodSelect = hyphen_audit_select_expression($columns, 'method', '""');
	$requestDataSelect = hyphen_audit_select_expression($columns, 'request_data', '""');
	$responseDataSelect = hyphen_audit_select_expression($columns, 'response_data', '""');
	$statusSelect = hyphen_audit_select_expression($columns, 'status', '""');
	$errorMessageSelect = hyphen_audit_select_expression($columns, 'error_message', '""');
	$ipAddressSelect = hyphen_audit_select_expression($columns, 'ip_address', '""');
	$userAgentSelect = hyphen_audit_select_expression($columns, 'user_agent', '""');
	$responseCodeSelect = hyphen_audit_select_expression($columns, 'response_code', '0');
	$executionTimeSelect = hyphen_audit_select_expression($columns, 'execution_time_ms', '0');
	$createdAtSelect = hyphen_audit_select_expression($columns, 'created_at', '""');
	$orderBy = in_array('id', $columns, true) ? 'id DESC' : (in_array('created_at', $columns, true) ? 'created_at DESC' : '1 DESC');
	$sql = 'SELECT ' . $idSelect . ', ' . $staffIdSelect . ', ' . $pageKeySelect . ', ' . $endpointSelect . ', ' . $actionSelect . ', ' . $methodSelect . ', ' . $requestDataSelect . ', ' . $responseDataSelect . ', ' . $statusSelect . ', ' . $errorMessageSelect . ', ' . $ipAddressSelect . ', ' . $userAgentSelect . ', ' . $responseCodeSelect . ', ' . $executionTimeSelect . ', ' . $createdAtSelect . '
		FROM hy_audit_logs' . $whereClause . ' ORDER BY ' . $orderBy . ' LIMIT ?';

	$statement = mysqli_prepare($conn, $sql);
	if (!$statement) {
		json_response(false, 'Failed to prepare audit log query.', [], 500);
	}

	$params[] = $filters['limit'];
	$types .= 'i';
	hyphen_bind_dynamic_params($statement, $types, $params);
	mysqli_stmt_execute($statement);
	$result = mysqli_stmt_get_result($statement);

	$logs = [];
	while ($result && ($row = mysqli_fetch_assoc($result))) {
		$logs[] = [
			'id' => (int) ($row['id'] ?? 0),
			'staff_id' => (string) ($row['staff_id'] ?? ''),
			'page_key' => (string) ($row['page_key'] ?? ''),
			'api_endpoint' => (string) ($row['api_endpoint'] ?? ''),
			'action' => (string) ($row['action'] ?? ''),
			'method' => (string) ($row['method'] ?? ''),
			'request_data' => (string) ($row['request_data'] ?? ''),
			'response_data' => (string) ($row['response_data'] ?? ''),
			'status' => (string) ($row['status'] ?? ''),
			'error_message' => (string) ($row['error_message'] ?? ''),
			'ip_address' => (string) ($row['ip_address'] ?? ''),
			'user_agent' => (string) ($row['user_agent'] ?? ''),
			'response_code' => (int) ($row['response_code'] ?? 0),
			'execution_time_ms' => (int) ($row['execution_time_ms'] ?? 0),
			'created_at' => (string) ($row['created_at'] ?? ''),
		];
	}

	mysqli_stmt_close($statement);

	json_response(true, 'Audit logs loaded successfully.', [
		'logs' => $logs,
		'filters' => $filters,
	]);
}