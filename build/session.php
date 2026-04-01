<?php
date_default_timezone_set('Asia/Singapore');
session_name("hyphen_sys");
session_start();

if(!isset($_SESSION['username'], $_SESSION['role'], $_SESSION['menu_rights'])){
    
}