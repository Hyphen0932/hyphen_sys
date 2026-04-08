<?php
// error_reporting(0);
date_default_timezone_set('Asia/Singapore');
if (!function_exists('hyphen_env')) {
    function hyphen_env(string $key, string $default): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return $value;
    }
}

if(!defined("HOST")) define("HOST", hyphen_env('DB_HOST', 'localhost'));
if(!defined("USER")) define("USER", hyphen_env('DB_USER', 'Seikowall'));
if(!defined("PASSWORD")) define("PASSWORD", hyphen_env('DB_PASSWORD', 'IDDQR'));
if(!defined("DATABASE")) define("DATABASE", hyphen_env('DB_NAME', 'hyphen_sys'));
if(!defined("DB_PORT")) define("DB_PORT", (int) hyphen_env('DB_PORT', '3306'));
if(!defined("APP_ENV")) define("APP_ENV", hyphen_env('APP_ENV', 'production'));

$conn = mysqli_connect(HOST, USER, PASSWORD, DATABASE, DB_PORT);
if (!$conn) {
    header('location: ../404.html?lmsg=true');
    exit();
}