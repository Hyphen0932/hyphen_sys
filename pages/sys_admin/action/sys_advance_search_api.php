<?php
include_once __DIR__ . '/../../../build/api_bootstrap.php';
include_once __DIR__ . '/../../../build/nl2sql_config.php';

hyphen_api_bootstrap([
	'allowed_methods' => ['POST'],
	'audit' => [
		'enabled' => false,
		'page_key' => 'sys_admin/system_advance_search',
	],
]);

hyphen_require_ability('view', 'sys_admin/system_advance_search', null, true);

$payload = api_request_payload();
$question = trim((string) ($payload['question'] ?? ''));
$conversationId = trim((string) ($payload['conversation_id'] ?? ''));
$includeRows = hyphen_ai_bool($payload['include_rows'] ?? false);
$runtime = hyphen_nl2sql_effective_runtime($conn, (string) ($_SESSION['staff_id'] ?? ''));

if (!$runtime['enabled']) {
	json_response(false, 'NL2SQL access is disabled for your account.', [], 403);
}

if (($runtime['allowed_tables'] ?? []) === []) {
	json_response(false, 'No allowed tables are configured for your account.', [], 403);
}

if ($question === '') {
	json_response(false, 'Question is required.', [], 422);
}

if (mb_strlen($question) > 2000) {
	json_response(false, 'Question is too long.', [], 422);
}

if ($conversationId === '') {
	$conversationId = 'sys-adv-search-' . substr(session_id(), 0, 12);
}

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

json_response(true, 'AI query completed successfully.', [
	'question' => (string) ($decoded['question'] ?? $question),
	'conversation_id' => (string) ($decoded['conversation_id'] ?? $conversationId),
	'sql' => !empty($runtime['can_view_sql']) ? (string) ($decoded['sql'] ?? '') : '',
	'answer' => (string) ($decoded['answer'] ?? ''),
	'row_count' => (int) ($decoded['row_count'] ?? 0),
	'rows' => !empty($runtime['can_include_rows']) && is_array($decoded['rows'] ?? null) ? $decoded['rows'] : [],
]);

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