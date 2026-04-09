# Email Notification Module Deployment Guide

## Purpose

This guide defines a repeatable pattern for sending module-specific emails by reusing the central email notification template engine.

The first implementation in this project is the System Users module:

- list page: `pages/sys_admin/system_users.php`
- action endpoint: `pages/sys_admin/action/sys_users_email_noti.php`
- template code: `NF-00001`

Use the same pattern when another module needs its own email action later.

## Architecture

The project already has a shared email engine:

- template management API: `api/email/email_notifications.php`
- shared mail sender: `build/email_notifications.php`
- admin UI for templates and SMTP config: `pages/sys_admin/system_email_notification.php`

Do not duplicate SMTP or PHPMailer logic inside business modules.

The module action file should only do 4 things:

1. authorize the request
2. load the target business record
3. map business fields into email template variables
4. call `hyphen_email_send_template()`

## System Users Implementation

### What it sends

`pages/sys_admin/action/sys_users_email_noti.php` sends login onboarding details to a user using template `NF-00001`.

Current variables mapped by the action file:

- `username`
- `user_name`
- `user_id`
- `login_user_id`
- `staff_id`
- `default_password`
- `password`
- `email`
- `role`
- `status`
- `login_url`

### Important password rule

The system stores password hashes only.

Because of that, the module can safely send only the known default-password rule used during user creation:

- default password = `staff_id`

It cannot recover a user's later changed password.

If you need to email a fresh credential later, implement a reset flow that writes a new temporary password first, then emails that temporary password immediately.

## Required Template Setup

In `System Email Notification`, create and activate template `NF-00001`.

Suggested subject:

```text
Welcome to Hyphen System - Your Login Information
```

Suggested HTML body:

```html
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f6f8; padding: 24px; color: #1f2937; }
    .card { max-width: 680px; margin: 0 auto; background: #ffffff; border: 1px solid #dbe2ea; border-radius: 8px; overflow: hidden; }
    .header { background: #1d4ed8; color: #ffffff; padding: 20px 24px; }
    .body { padding: 24px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th { background: #0f172a; color: #ffffff; padding: 10px; border: 1px solid #cbd5e1; text-align: left; }
    td { padding: 10px; border: 1px solid #cbd5e1; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h2 style="margin: 0;">Welcome to Hyphen System</h2>
    </div>
    <div class="body">
      <p>Hello {{username}},</p>
      <p>Your account has been created. Please use the login details below:</p>
      <table>
        <tr>
          <th>Login User ID</th>
          <td>{{login_user_id}}</td>
        </tr>
        <tr>
          <th>Default Password</th>
          <td>{{default_password}}</td>
        </tr>
        <tr>
          <th>Login URL</th>
          <td><a href="{{login_url}}">{{login_url}}</a></td>
        </tr>
      </table>
      <p style="margin-top: 16px;">Please sign in and change your password as soon as possible.</p>
    </div>
  </div>
</body>
</html>
```

Suggested plain text body:

```text
Hello {{username}},

Your account has been created.
Login User ID: {{login_user_id}}
Default Password: {{default_password}}
Login URL: {{login_url}}

Please sign in and change your password as soon as possible.
```

## Front-End Pattern For New Modules

On the list page, add an action button that posts to a dedicated action file instead of mixing the logic into the CRUD endpoint.

Recommended pattern:

- keep CRUD in `*_crud.php`
- keep email logic in `*_email_noti.php`

Benefits:

- clearer responsibility split
- easier permission review
- easier future expansion for other templates
- lower risk of breaking create or update behavior

## Backend Pattern For New Modules

Recommended skeleton:

```php
<?php
include_once '../../../build/config.php';
include_once '../../../build/authorization.php';
include_once '../../../build/email_notifications.php';

hyphen_boot_session();

if (!hyphen_is_authenticated()) {
    json_response(false, 'Your session has expired. Please sign in again.', [], 401);
}

hyphen_refresh_session_authorization($conn, (string) ($_SESSION['staff_id'] ?? ''));

hyphen_require_ability('edit', 'module/page_key', null, true);

$template = hyphen_email_fetch_template_by_code($conn, 'YOUR-TEMPLATE-CODE');
$result = hyphen_email_send_template($conn, $template, [$recipientEmail], $variables, [
    'created_by' => trim((string) ($_SESSION['staff_id'] ?? '')),
]);
```

## Deployment Checklist

1. Configure SMTP in `System Email Notification`.
2. Create and activate the required notification template.
3. Add the module action button in the list page.
4. Add a dedicated `*_email_noti.php` backend file.
5. Protect the backend action with `hyphen_require_ability()`.
6. Verify the recipient email field exists and is valid.
7. Send a test email from the target module.
8. Confirm delivery results in `Email Notification Templates` logs.

## Reuse Notes

When another module needs email notification later, copy the same structure and only replace:

- target SQL lookup
- template code
- variable mapping
- success message

Leave SMTP transport and template rendering in the shared email engine.