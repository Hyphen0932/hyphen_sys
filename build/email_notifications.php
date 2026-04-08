<?php

$hyphenMailerAutoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($hyphenMailerAutoload)) {
    require_once $hyphenMailerAutoload;
}

if (!function_exists('hyphen_mail_default_config')) {
    function hyphen_mail_default_config(): array
    {
        $password = trim(hyphen_env('MAIL_PASSWORD', ''));

        return [
            'id' => 0,
            'provider' => 'gmail',
            'host' => hyphen_env('MAIL_HOST', 'smtp.gmail.com'),
            'port' => (int) hyphen_env('MAIL_PORT', '587'),
            'encryption' => strtolower(trim(hyphen_env('MAIL_ENCRYPTION', 'tls'))),
            'username' => trim(hyphen_env('MAIL_USERNAME', '')),
            'password' => $password,
            'from_address' => trim(hyphen_env('MAIL_FROM_ADDRESS', '')),
            'from_name' => trim(hyphen_env('MAIL_FROM_NAME', 'Hyphen System')),
            'reply_to' => trim(hyphen_env('MAIL_REPLY_TO', '')),
            'source' => 'env',
            'has_password' => $password !== '',
            'is_active' => true,
            'created_by' => '',
            'updated_by' => '',
            'created_at' => '',
            'updated_at' => '',
        ];
    }
}

if (!function_exists('hyphen_mail_connection')) {
    function hyphen_mail_connection(?mysqli $conn = null): ?mysqli
    {
        if ($conn instanceof mysqli) {
            return $conn;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            return $GLOBALS['conn'];
        }

        return null;
    }
}

if (!function_exists('hyphen_mail_table_exists')) {
    function hyphen_mail_table_exists(?mysqli $conn, string $tableName): bool
    {
        static $cache = [];

        $conn = hyphen_mail_connection($conn);
        if (!$conn instanceof mysqli) {
            return false;
        }

        if (array_key_exists($tableName, $cache)) {
            return $cache[$tableName];
        }

        $escapedTableName = mysqli_real_escape_string($conn, $tableName);
        $query = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '{$escapedTableName}' LIMIT 1";
        $result = mysqli_query($conn, $query);

        $cache[$tableName] = $result !== false && mysqli_num_rows($result) > 0;
        if ($result !== false) {
            mysqli_free_result($result);
        }

        return $cache[$tableName];
    }
}

if (!function_exists('hyphen_mail_db_config')) {
    function hyphen_mail_db_config(?mysqli $conn = null): ?array
    {
        $conn = hyphen_mail_connection($conn);
        if (!$conn instanceof mysqli || !hyphen_mail_table_exists($conn, 'hy_email_configurations')) {
            return null;
        }

        $result = mysqli_query(
            $conn,
            'SELECT id, provider, host, port, encryption, username, password, from_address, from_name, reply_to, is_active, created_by, updated_by, created_at, updated_at
             FROM hy_email_configurations
             WHERE is_active = 1
             ORDER BY id DESC
             LIMIT 1'
        );

        if ($result === false) {
            return null;
        }

        $row = mysqli_fetch_assoc($result) ?: null;
        mysqli_free_result($result);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'provider' => trim((string) ($row['provider'] ?? 'gmail')),
            'host' => trim((string) ($row['host'] ?? '')),
            'port' => (int) ($row['port'] ?? 0),
            'encryption' => strtolower(trim((string) ($row['encryption'] ?? 'tls'))),
            'username' => trim((string) ($row['username'] ?? '')),
            'password' => trim((string) ($row['password'] ?? '')),
            'from_address' => trim((string) ($row['from_address'] ?? '')),
            'from_name' => trim((string) ($row['from_name'] ?? '')),
            'reply_to' => trim((string) ($row['reply_to'] ?? '')),
            'source' => 'database',
            'has_password' => trim((string) ($row['password'] ?? '')) !== '',
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
            'created_by' => (string) ($row['created_by'] ?? ''),
            'updated_by' => (string) ($row['updated_by'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('hyphen_mail_config')) {
    function hyphen_mail_config(?mysqli $conn = null): array
    {
        $config = hyphen_mail_default_config();
        $dbConfig = hyphen_mail_db_config($conn);

        if ($dbConfig !== null) {
            $config = array_merge($config, $dbConfig);
        }

        return $config;
    }
}

if (!function_exists('hyphen_mail_missing_config_keys')) {
    function hyphen_mail_missing_config_keys(?array $config = null): array
    {
        $config = $config ?? hyphen_mail_config();
        $missing = [];

        foreach (['host', 'port', 'username', 'password', 'from_address'] as $key) {
            $value = $config[$key] ?? null;
            if ($value === null || $value === '' || $value === 0) {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}

if (!function_exists('hyphen_mail_is_configured')) {
    function hyphen_mail_is_configured(?array $config = null): bool
    {
        return hyphen_mail_missing_config_keys($config) === [];
    }
}

if (!function_exists('hyphen_mailer_instance')) {
    function hyphen_mailer_instance()
    {
        $mailerClass = 'PHPMailer\\PHPMailer\\PHPMailer';
        if (!class_exists($mailerClass)) {
            throw new RuntimeException('PHPMailer dependency is not installed. Rebuild the Docker app image to install vendor packages.');
        }

        $config = hyphen_mail_config(hyphen_mail_connection());
        $mailer = new $mailerClass(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->isSMTP();
        $mailer->Host = $config['host'];
        $mailer->Port = (int) $config['port'];
        $mailer->SMTPAuth = true;
        $mailer->Username = $config['username'];
        $mailer->Password = $config['password'];

        if ($config['encryption'] === 'ssl') {
            $mailer->SMTPSecure = 'ssl';
        } else {
            $mailer->SMTPSecure = 'tls';
        }

        $mailer->setFrom($config['from_address'], $config['from_name'] !== '' ? $config['from_name'] : 'Hyphen System');
        if ($config['reply_to'] !== '') {
            $mailer->addReplyTo($config['reply_to']);
        }

        return $mailer;
    }
}

if (!function_exists('hyphen_email_normalize_list')) {
    function hyphen_email_normalize_list($value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
        }

        if (!is_array($value)) {
            return [];
        }

        $emails = [];
        foreach ($value as $item) {
            $email = trim((string) $item);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($email);
            }
        }

        return array_values(array_unique($emails));
    }
}

if (!function_exists('hyphen_email_template_variables')) {
    function hyphen_email_template_variables($value): array
    {
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $variables = [];
        foreach ($value as $key => $item) {
            $normalizedKey = trim((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            if (is_scalar($item) || $item === null) {
                $variables[$normalizedKey] = (string) $item;
            }
        }

        return $variables;
    }
}

if (!function_exists('hyphen_email_render_content')) {
    function hyphen_email_render_content(string $content, array $variables): string
    {
        if ($content === '' || $variables === []) {
            return $content;
        }

        $search = [];
        $replace = [];
        foreach ($variables as $key => $value) {
            $search[] = '{{' . $key . '}}';
            $replace[] = (string) $value;
        }

        return str_replace($search, $replace, $content);
    }
}

if (!function_exists('hyphen_email_fetch_template_by_id')) {
    function hyphen_email_fetch_template_by_id(mysqli $conn, int $templateId): ?array
    {
        $statement = mysqli_prepare($conn, 'SELECT * FROM hy_email_notification_templates WHERE id = ? LIMIT 1');
        if (!$statement) {
            throw new RuntimeException('Failed to prepare template lookup by id.');
        }

        mysqli_stmt_bind_param($statement, 'i', $templateId);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $template = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($statement);

        return $template ?: null;
    }
}

if (!function_exists('hyphen_email_fetch_template_by_code')) {
    function hyphen_email_fetch_template_by_code(mysqli $conn, string $notificationCode): ?array
    {
        $statement = mysqli_prepare($conn, 'SELECT * FROM hy_email_notification_templates WHERE notification_code = ? AND is_active = 1 LIMIT 1');
        if (!$statement) {
            throw new RuntimeException('Failed to prepare template lookup by code.');
        }

        mysqli_stmt_bind_param($statement, 's', $notificationCode);
        mysqli_stmt_execute($statement);
        $result = mysqli_stmt_get_result($statement);
        $template = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($statement);

        return $template ?: null;
    }
}

if (!function_exists('hyphen_email_log_delivery')) {
    function hyphen_email_log_delivery(mysqli $conn, array $log): void
    {
        $statement = mysqli_prepare(
            $conn,
            'INSERT INTO hy_email_notification_logs (template_id, notification_code, recipient_email, cc_json, bcc_json, email_subject, body_html, body_text, payload_json, status, error_message, created_by, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' 
        );

        if (!$statement) {
            throw new RuntimeException('Failed to prepare email log insert.');
        }

        $templateId = isset($log['template_id']) ? (int) $log['template_id'] : null;
        $notificationCode = (string) ($log['notification_code'] ?? '');
        $recipientEmail = (string) ($log['recipient_email'] ?? '');
        $ccJson = $log['cc_json'] ?? null;
        $bccJson = $log['bcc_json'] ?? null;
        $emailSubject = (string) ($log['email_subject'] ?? '');
        $bodyHtml = (string) ($log['body_html'] ?? '');
        $bodyText = $log['body_text'] ?? null;
        $payloadJson = $log['payload_json'] ?? null;
        $status = (string) ($log['status'] ?? 'queued');
        $errorMessage = $log['error_message'] ?? null;
        $createdBy = $log['created_by'] ?? null;
        $sentAt = $log['sent_at'] ?? null;

        mysqli_stmt_bind_param(
            $statement,
            'issssssssssss',
            $templateId,
            $notificationCode,
            $recipientEmail,
            $ccJson,
            $bccJson,
            $emailSubject,
            $bodyHtml,
            $bodyText,
            $payloadJson,
            $status,
            $errorMessage,
            $createdBy,
            $sentAt
        );

        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
    }
}

if (!function_exists('hyphen_email_send_template')) {
    function hyphen_email_send_template(mysqli $conn, array $template, array $toEmails, array $variables = [], array $options = []): array
    {
        $config = hyphen_mail_config($conn);
        $missingConfigKeys = hyphen_mail_missing_config_keys($config);
        if ($missingConfigKeys !== []) {
            return [
                'success' => false,
                'message' => 'Mail transport is not configured.',
                'missing_config' => $missingConfigKeys,
            ];
        }

        $toEmails = hyphen_email_normalize_list($toEmails);
        if ($toEmails === []) {
            return [
                'success' => false,
                'message' => 'At least one valid recipient email is required.',
            ];
        }

        $variables = hyphen_email_template_variables($variables);
        $subject = hyphen_email_render_content((string) ($template['email_subject'] ?? ''), $variables);
        $bodyHtml = hyphen_email_render_content((string) ($template['body_html'] ?? ''), $variables);
        $bodyTextSource = (string) ($template['body_text'] ?? '');
        $bodyText = trim($bodyTextSource) !== ''
            ? hyphen_email_render_content($bodyTextSource, $variables)
            : trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $ccEmails = hyphen_email_normalize_list($options['cc'] ?? []);
        $bccEmails = hyphen_email_normalize_list($options['bcc'] ?? []);
        $createdBy = trim((string) ($options['created_by'] ?? ''));
        $payloadJson = json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $mailer = hyphen_mailer_instance();
            foreach ($toEmails as $email) {
                $mailer->addAddress($email);
            }
            foreach ($ccEmails as $email) {
                $mailer->addCC($email);
            }
            foreach ($bccEmails as $email) {
                $mailer->addBCC($email);
            }

            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $bodyHtml;
            $mailer->AltBody = $bodyText;
            $mailer->send();

            foreach ($toEmails as $email) {
                hyphen_email_log_delivery($conn, [
                    'template_id' => (int) ($template['id'] ?? 0),
                    'notification_code' => (string) ($template['notification_code'] ?? ''),
                    'recipient_email' => $email,
                    'cc_json' => $ccEmails !== [] ? json_encode($ccEmails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'bcc_json' => $bccEmails !== [] ? json_encode($bccEmails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'email_subject' => $subject,
                    'body_html' => $bodyHtml,
                    'body_text' => $bodyText,
                    'payload_json' => $payloadJson,
                    'status' => 'sent',
                    'error_message' => null,
                    'created_by' => $createdBy !== '' ? $createdBy : null,
                    'sent_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return [
                'success' => true,
                'message' => 'Email sent successfully.',
                'subject' => $subject,
                'recipient_count' => count($toEmails),
            ];
        } catch (Throwable $exception) {
            foreach ($toEmails as $email) {
                hyphen_email_log_delivery($conn, [
                    'template_id' => (int) ($template['id'] ?? 0),
                    'notification_code' => (string) ($template['notification_code'] ?? ''),
                    'recipient_email' => $email,
                    'cc_json' => $ccEmails !== [] ? json_encode($ccEmails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'bcc_json' => $bccEmails !== [] ? json_encode($bccEmails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'email_subject' => $subject,
                    'body_html' => $bodyHtml,
                    'body_text' => $bodyText,
                    'payload_json' => $payloadJson,
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                    'created_by' => $createdBy !== '' ? $createdBy : null,
                    'sent_at' => null,
                ]);
            }

            return [
                'success' => false,
                'message' => 'Email sending failed: ' . $exception->getMessage(),
            ];
        }
    }
}