<?php
include_once('../../build/config.php');

// Create SQL table if it doesn't exist for users
$users = "CREATE TABLE IF NOT EXISTS hy_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    staff_id VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    menu_rights JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB";

// Create SQL table for user menu
$user_menu = "CREATE TABLE IF NOT EXISTS hy_user_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_id VARCHAR(50) NOT NULL,
    menu_name VARCHAR(255) NOT NULL,
    menu_url VARCHAR(255),
    menu_icon VARCHAR(100),
    menu_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB";


// Create SQL table for user pages
$user_pages = "CREATE TABLE IF NOT EXISTS hy_user_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_code VARCHAR(50) NOT NULL,
    group_name VARCHAR(255) NOT NULL,
    display_name VARCHAR(255),
    page_name VARCHAR(100),
    page_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB";

// Create SQL table for user permissions
$user_permissions = "CREATE TABLE IF NOT EXISTS hy_user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    staff_id VARCHAR(50) NOT NULL,
    can_view BOOLEAN DEFAULT FALSE,
    can_add BOOLEAN DEFAULT FALSE,
    can_edit BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES hy_user_pages(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES hy_users(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB";

$tables = [
    'hy_users' => $users,
    'hy_user_menu' => $user_menu,
    'hy_user_pages' => $user_pages,
    'hy_user_permissions' => $user_permissions,
];

foreach ($tables as $name => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table $name created successfully<br>";
    } else {
        echo "Error creating $name: " . $conn->error . "<br>";
    }
}
