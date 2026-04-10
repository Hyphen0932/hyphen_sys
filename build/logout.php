<?php
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/audit_middleware.php';

session_name('hyphen_sys');

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

$auditMiddleware = null;
if (isset($conn) && $conn instanceof mysqli) {
	$auditMiddleware = new AuditMiddleware($conn, [
		'page_key' => 'auth/logout',
		'fixed_action' => 'logout',
	]);
	$auditMiddleware->start();
}

header('Location: ../index.html');

if ($auditMiddleware instanceof AuditMiddleware) {
	$auditMiddleware->flush();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
}

session_destroy();
exit;