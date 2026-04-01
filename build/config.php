<?php
// error_reporting(0);
date_default_timezone_set('Asia/Singapore');
if(!defined("HOST")) define("HOST", "localhost");
if(!defined("USER")) define("USER", "Seikowall");
if(!defined("PASSWORD")) define("PASSWORD", "IDDQR");
if(!defined("DATABASE")) define("DATABASE", "hyphen_sys");

$conn = mysqli_connect(HOST, USER, PASSWORD, DATABASE);
if (!$conn) {
    header('location: ../404.html?lmsg=true');
    exit();
}