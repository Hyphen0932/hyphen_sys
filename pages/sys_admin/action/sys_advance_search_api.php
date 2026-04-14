<?php
include_once __DIR__ . '/../../../build/api_bootstrap.php';
include_once __DIR__ . '/../../../build/nl2sql_config.php';
include_once __DIR__ . '/../../../build/ai_jobs.php';

hyphen_api_bootstrap([
	'allowed_methods' => ['GET', 'POST'],
	'audit' => [
		'enabled' => false,
		'page_key' => 'sys_admin/system_advance_search',
	],
]);

hyphen_require_ability('view', 'sys_admin/system_advance_search', null, true);

$payload = api_request_payload();
$requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'POST'));
$action = trim((string) ($payload['action'] ?? ($requestMethod === 'GET' ? 'get_job' : 'create_job')));

switch ($action) {
	case 'create_job':
		create_job($conn, $payload);
		break;

	case 'get_job':
		get_job($conn, $payload);
		break;

	case 'query_sync':
		query_sync($conn, $payload);
		break;

	default:
		json_response(false, 'Unsupported action.', [], 400);
}

function create_job(mysqli $conn, array $payload): void
{
	if (!hyphen_ai_job_tables_ready($conn)) {
		json_response(false, 'AI job tables are not available yet. Run database migrations first.', [], 500);
	}

	$question = trim((string) ($payload['question'] ?? ''));
	$conversationId = trim((string) ($payload['conversation_id'] ?? ''));
	$includeRows = hyphen_ai_bool($payload['include_rows'] ?? false);
	$staffId = trim((string) ($_SESSION['staff_id'] ?? ''));
	$runtime = hyphen_nl2sql_effective_runtime($conn, $staffId);

	ensure_runtime_is_available($runtime);
	validate_question($question);

	if ($conversationId === '') {
		$conversationId = 'sys-adv-search-' . substr(session_id(), 0, 12);
	}

	$jobId = hyphen_ai_generate_job_id();
	$queueName = hyphen_ai_default_queue_name();
	$priority = 100;
	$requestPayload = [
		'question' => $question,
		'conversation_id' => $conversationId,
		'include_rows' => $includeRows && !empty($runtime['can_include_rows']),
		'model' => $runtime['model_name'],
		'allowed_tables' => $runtime['allowed_tables'],
		'row_limit' => (int) ($runtime['row_limit'] ?? 50),
		'prompt_notes' => (string) ($runtime['prompt_notes'] ?? ''),
	];
	$requestPayloadJson = json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	if (!is_string($requestPayloadJson) || $requestPayloadJson === '') {
		json_response(false, 'Failed to prepare AI job payload.', [], 500);
	}

	$jobType = 'nl2sql';
	$modeKey = 'nl2sql';
	$status = 'queued';
	$modelKey = (string) ($runtime['model_name'] ?? '');
	$rowCount = 0;
	$includeRowsValue = $requestPayload['include_rows'] ? 1 : 0;

	$statement = mysqli_prepare(
		$conn,
		'INSERT INTO hy_ai_jobs (job_id, staff_id, conversation_id, job_type, mode_key, model_key, question_text, request_payload_json, status, priority, include_rows, row_count, queue_name)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
	);
	if (!$statement) {
		json_response(false, 'Failed to prepare AI job insert statement.', [], 500);
	}

	mysqli_stmt_bind_param(
		$statement,
		'sssssssssiiis',
		$jobId,
		$staffId,
		$conversationId,
		$jobType,
		$modeKey,
		$modelKey,
		$question,
		$requestPayloadJson,
		$status,
		$priority,
		$includeRowsValue,
		$rowCount,
		$queueName
	);

	if (!mysqli_stmt_execute($statement)) {
		$error = mysqli_stmt_error($statement);
		mysqli_stmt_close($statement);
		json_response(false, 'Unable to queue AI job: ' . $error, [], 500);
	}
	mysqli_stmt_close($statement);

	hyphen_ai_insert_job_event($conn, $jobId, 'queued', 'Job queued by user request.', [
		'queue_name' => $queueName,
		'staff_id' => $staffId,
	]);

	$enqueueResult = hyphen_ai_redis_enqueue($queueName, [
		'job_id' => $jobId,
		'queue_name' => $queueName,
	]);

	if (!$enqueueResult['success']) {
		$errorMessage = (string) ($enqueueResult['error'] ?? 'Unable to enqueue AI job.');
		$updateStatement = mysqli_prepare($conn, 'UPDATE hy_ai_jobs SET status = ?, error_message = ?, finished_at = CURRENT_TIMESTAMP WHERE job_id = ?');
		if ($updateStatement) {
			$failedStatus = 'failed';
			mysqli_stmt_bind_param($updateStatement, 'sss', $failedStatus, $errorMessage, $jobId);
			mysqli_stmt_execute($updateStatement);
			mysqli_stmt_close($updateStatement);
		}
		hyphen_ai_insert_job_event($conn, $jobId, 'failed', $errorMessage);
		json_response(false, $errorMessage, [], 502);
	}

	json_response(true, 'AI job accepted successfully.', [
		'job_id' => $jobId,
		'status' => 'queued',
		'conversation_id' => $conversationId,
		'poll_interval_ms' => 2000,
	]);
}

function get_job(mysqli $conn, array $payload): void
{
	if (!hyphen_ai_job_tables_ready($conn)) {
		json_response(false, 'AI job tables are not available yet. Run database migrations first.', [], 500);
	}

	$jobId = trim((string) ($payload['job_id'] ?? ''));
	$staffId = trim((string) ($_SESSION['staff_id'] ?? ''));
	if ($jobId === '') {
		json_response(false, 'Job ID is required.', [], 422);
	}

	$job = hyphen_ai_fetch_job($conn, $jobId, $staffId);
	if (!is_array($job)) {
		json_response(false, 'AI job was not found.', [], 404);
	}

	$resultPayload = is_array($job['result_payload'] ?? null) ? $job['result_payload'] : null;
	$data = [
		'job_id' => $job['job_id'],
		'status' => (string) ($job['status'] ?? 'queued'),
		'conversation_id' => (string) ($job['conversation_id'] ?? ''),
		'row_count' => (int) ($job['row_count'] ?? 0),
		'attempt_count' => (int) ($job['attempt_count'] ?? 0),
		'error_message' => (string) ($job['error_message'] ?? ''),
		'queued_at' => $job['queued_at'] ?? null,
		'started_at' => $job['started_at'] ?? null,
		'finished_at' => $job['finished_at'] ?? null,
		'updated_at' => $job['updated_at'] ?? null,
	];

	if ($resultPayload !== null) {
		$data['result'] = $resultPayload;
	}

	json_response(true, 'AI job status loaded successfully.', $data);
}

function query_sync(mysqli $conn, array $payload): void
{
	$question = trim((string) ($payload['question'] ?? ''));
	$conversationId = trim((string) ($payload['conversation_id'] ?? ''));
	$includeRows = hyphen_ai_bool($payload['include_rows'] ?? false);
	$runtime = hyphen_nl2sql_effective_runtime($conn, (string) ($_SESSION['staff_id'] ?? ''));

	ensure_runtime_is_available($runtime);
	validate_question($question);

	if ($conversationId === '') {
		$conversationId = 'sys-adv-search-' . substr(session_id(), 0, 12);
	}

	$decoded = execute_sync_query($runtime, $question, $conversationId, $includeRows);

	json_response(true, 'AI query completed successfully.', [
		'question' => (string) ($decoded['question'] ?? $question),
		'conversation_id' => (string) ($decoded['conversation_id'] ?? $conversationId),
		'sql' => !empty($runtime['can_view_sql']) ? (string) ($decoded['sql'] ?? '') : '',
		'answer' => (string) ($decoded['answer'] ?? ''),
		'row_count' => (int) ($decoded['row_count'] ?? 0),
		'rows' => !empty($runtime['can_include_rows']) && is_array($decoded['rows'] ?? null) ? $decoded['rows'] : [],
	]);
}

function ensure_runtime_is_available(array $runtime): void
{
	if (!$runtime['enabled']) {
		json_response(false, 'NL2SQL access is disabled for your account.', [], 403);
	}

	if (($runtime['allowed_tables'] ?? []) === []) {
		json_response(false, 'No allowed tables are configured for your account.', [], 403);
	}
}

function validate_question(string $question): void
{
	if ($question === '') {
		json_response(false, 'Question is required.', [], 422);
	}

	if (mb_strlen($question) > 2000) {
		json_response(false, 'Question is too long.', [], 422);
	}
}

function execute_sync_query(array $runtime, string $question, string $conversationId, bool $includeRows): array
{
	$serviceBaseUrl = rtrim((string) (getenv('AI_SERVICE_BASE_URL') ?: 'http://ai-service:8000'), '/');
	$serviceUrl = $serviceBaseUrl . '/query';

	$requestBody = json_encode([
		'question' => $question,
		'conversation_id' => $conversationId,
		'include_rows' => $includeRows && !empty($runtime['can_include_rows']),
		'model' => $runtime['model_name'],
		'allowed_tables' => $runtime['allowed_tables'],
		'row_limit' => (int) ($runtime['row_limit'] ?? 50),
		'prompt_notes' => (string) ($runtime['prompt_notes'] ?? ''),
	], JSON_UNESCAPED_UNICODE);

	if (!is_string($requestBody) || $requestBody === '') {
		json_response(false, 'Failed to prepare AI request payload.', [], 500);
	}

	$serviceResponse = hyphen_ai_post_json($serviceUrl, $requestBody, [
		'Content-Type: application/json',
		'Accept: application/json',
	]);

	if ($serviceResponse['error'] !== null) {
		json_response(false, $serviceResponse['error'], [], 502);
	}

	$decoded = json_decode($serviceResponse['body'], true);
	if (!is_array($decoded)) {
		json_response(false, 'AI service returned a non-JSON response.', [], 502);
	}

	if (($serviceResponse['status_code'] ?? 500) >= 400) {
		$detail = trim((string) ($decoded['detail'] ?? 'AI service request failed.'));
		json_response(false, $detail !== '' ? $detail : 'AI service request failed.', [], (int) $serviceResponse['status_code']);
	}

	return $decoded;
}

function hyphen_ai_bool(mixed $value): bool
{
	if (is_bool($value)) {
		return $value;
	}

	$value = strtolower(trim((string) $value));
	return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function hyphen_ai_post_json(string $url, string $jsonBody, array $headers = []): array
{
	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		if ($ch === false) {
			return ['status_code' => 0, 'body' => '', 'error' => 'Unable to initialize cURL.'];
		}

		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POSTFIELDS => $jsonBody,
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => 60,
		]);

		$body = curl_exec($ch);
		$error = curl_error($ch);
		$statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);

		return [
			'status_code' => $statusCode,
			'body' => is_string($body) ? $body : '',
			'error' => $error !== '' ? 'AI service connection failed: ' . $error : null,
		];
	}

	$context = stream_context_create([
		'http' => [
			'method' => 'POST',
			'header' => implode("\r\n", $headers),
			'content' => $jsonBody,
			'timeout' => 60,
			'ignore_errors' => true,
		],
	]);

	$body = @file_get_contents($url, false, $context);
	$statusCode = hyphen_ai_status_code($http_response_header ?? []);

	if ($body === false) {
		return [
			'status_code' => $statusCode,
			'body' => '',
			'error' => 'AI service connection failed.',
		];
	}

	return [
		'status_code' => $statusCode,
		'body' => $body,
		'error' => null,
	];
}

function hyphen_ai_status_code(array $headers): int
{
	foreach ($headers as $header) {
		if (preg_match('/HTTP\/\d+\.\d+\s+(\d{3})/', (string) $header, $matches)) {
			return (int) $matches[1];
		}
	}

	return 0;
}