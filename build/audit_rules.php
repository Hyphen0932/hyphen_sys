<?php

if (!function_exists('hyphen_audit_rule_definitions')) {
    function hyphen_audit_rule_definitions(): array
    {
        return [
            'sys_users.update_user' => [
                'mode' => 'diff',
                'fields' => ['username', 'staff_id', 'email', 'phone', 'designation', 'role', 'status', 'menu_rights', 'image_url'],
            ],
            'sys_users.toggle_user_status' => [
                'mode' => 'diff',
                'fields' => ['status'],
            ],
            'system_menu.update_page' => [
                'mode' => 'diff',
                'fields' => ['menu_id', 'display_name', 'page_name', 'page_url', 'page_order'],
            ],
            'email_notification.update_template' => [
                'mode' => 'diff',
                'fields' => ['category', 'notification_code', 'template_name', 'email_subject', 'body_text', 'variables_json', 'is_active', 'body_html_hash'],
            ],
        ];
    }
}