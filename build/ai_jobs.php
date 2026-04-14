<?php

if (!function_exists('hyphen_ai_job_tables_ready')) {
	function hyphen_ai_job_tables_ready(mysqli $conn): bool
	{
		return hyphen_nl2sql_table_exists($conn, 'hy_ai_jobs') && hyphen_nl2sql_table_exists($conn, 'hy_ai_job_events');
	}
}

if (!function_exists('hyphen_ai_generate_job_id')) {
	function hyphen_ai_generate_job_id(): string
	{
		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

		$hex = bin2hex($bytes);
		return sprintf(
			'%s-%s-%s-%s-%s',
			substr($hex, 0, 8),
			substr($hex, 8, 4),
			substr($hex, 12, 4),
			substr($hex, 16, 4),
			substr($hex, 20, 12)
		);
	}
}

if (!function_exists('hyphen_ai_default_queue_name')) {
	function hyphen_ai_default_queue_name(): string
	{
		return trim((string) (getenv('AI_JOB_QUEUE_NAME') ?: 'queue:ai:nl2sql'));
	}
}

if (!function_exists('hyphen_ai_insert_job_event')) {
	function hyphen_ai_insert_job_event(mysqli $conn, string $jobId, string $eventType, ?string $messageText = null, ?array $payload = null): void
	{
		$payloadJson = $payload !== null
			? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
			: null;

		$statement = mysqli_prepare(
			$conn,
			'INSERT INTO hy_ai_job_events (job_id, event_type, message_text, payload_json) VALUES (?, ?, ?, ?)'
		);
		if (!$statement) {
			return;
		}

		mysqli_stmt_bind_param($statement, 'ssss', $jobId, $eventType, $messageText, $payloadJson);
		mysqli_stmt_execute($statement);
		mysqli_stmt_close($statement);
	}
}

if (!function_exists('hyphen_ai_fetch_job')) {
	function hyphen_ai_fetch_job(mysqli $conn, string $jobId, string $staffId): ?array
	{
		$statement = mysqli_prepare(
			$conn,
			'SELECT id, job_id, staff_id, conversation_id, job_type, mode_key, model_key, status, priority, include_rows, row_count, attempt_count, queue_name, worker_id, error_message, request_payload_json, result_payload_json, queued_at, started_at, finished_at, updated_at
			 FROM hy_ai_jobs
			 WHERE job_id = ? AND staff_id = ?
			 LIMIT 1'
		);
		if (!$statement) {
			return null;
		}

		mysqli_stmt_bind_param($statement, 'ss', $jobId, $staffId);
		mysqli_stmt_execute($statement);
		$result = mysqli_stmt_get_result($statement);
		$row = $result ? mysqli_fetch_assoc($result) : null;
		mysqli_stmt_close($statement);

		if (!is_array($row)) {
			return null;
		}

		$row['include_rows'] = (int) ($row['include_rows'] ?? 0) === 1;
		$row['row_count'] = (int) ($row['row_count'] ?? 0);
		$row['attempt_count'] = (int) ($row['attempt_count'] ?? 0);
		$row['request_payload'] = hyphen_ai_decode_json($row['request_payload_json'] ?? null);
		$row['result_payload'] = hyphen_ai_decode_json($row['result_payload_json'] ?? null);

		return $row;
	}
}

if (!function_exists('hyphen_ai_decode_json')) {
	function hyphen_ai_decode_json($value)
	{
		if (!is_string($value) || trim($value) === '') {
			return null;
		}

		$decoded = json_decode($value, true);
		return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
	}
}

if (!function_exists('hyphen_ai_redis_enqueue')) {
	function hyphen_ai_redis_enqueue(string $queueName, array $message): array
	{
		$host = trim((string) (getenv('AI_REDIS_HOST') ?: 'redis'));
		$port = (int) (getenv('AI_REDIS_PORT') ?: 6379);
		$timeout = (float) (getenv('AI_REDIS_TIMEOUT') ?: 2.0);
		$socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $error, $timeout);

		if (!is_resource($socket)) {
			return [
				'success' => false,
				'error' => 'Unable to connect to Redis queue: ' . ($error ?: 'unknown error'),
			];
		}

		stream_set_timeout($socket, (int) ceil($timeout));
		$payload = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$command = hyphen_ai_redis_build_command(['RPUSH', $queueName, (string) $payload]);
		fwrite($socket, $command);
		$response = fgets($socket);
		fclose($socket);

		if (!is_string($response) || $response === '') {
			return [
				'success' => false,
				'error' => 'Redis queue did not return a response.',
			];
		}

		if ($response[0] === '-') {
			return [
				'success' => false,
				'error' => 'Redis queue rejected the request: ' . trim(substr($response, 1)),
			];
		}

		return [
			'success' => true,
			'error' => null,
		];
	}
}

if (!function_exists('hyphen_ai_redis_build_command')) {
	function hyphen_ai_redis_build_command(array $parts): string
	{
		$command = '*' . count($parts) . "\r\n";
		foreach ($parts as $part) {
			$part = (string) $part;
			$command .= '$' . strlen($part) . "\r\n{$part}\r\n";
		}

		return $command;
	}
}