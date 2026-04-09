# Audit Logging Module Deployment Guide

## Purpose

This guide defines the project standard for adding audit logging to future modules without rewriting large amounts of audit code in every action file.

The goal is:

- keep audit logging consistent
- default to minimal records for most actions
- keep detailed diff only for a small number of important updates
- centralize rules and helper behavior so module files stay light

## Current Core Files

- audit core: `build/audit.php`
- audit rule config: `build/audit_rules.php`
- audit table migration: `db/migrations/20260409_180000_create_audit_logs_table.sql`
- audit UI page: `pages/sys_admin/system_audit_log.php`

## Recommended Design

Use the audit system in 3 layers:

1. `hyphen_audit_action()`
2. `hyphen_audit_context()`
3. `hyphen_audit_log_success()` / `hyphen_audit_log_failure()`

In normal business code, use only `hyphen_audit_action()`.

The lower helpers remain in place for compatibility and for rare edge cases, but they should not be the default choice in new modules.

## Standard Rule

For most actions, write only a minimal record:

- module
- action name
- entity type
- entity id
- target label
- short metadata summary

Only use old/new values for a small number of important actions such as:

- record updates with important field changes
- status toggles
- configuration updates
- template/content edits

Detailed diff rules must be declared centrally in `build/audit_rules.php`.

Do not spread diff field decisions across many module files.

## How Rules Work

`build/audit_rules.php` stores the audit detail rules by key:

```php
'module_name.action_name' => [
    'mode' => 'diff',
    'fields' => ['field_a', 'field_b']
]
```

If no rule exists, the action automatically falls back to `minimal` mode.

That means new modules do not need any rule unless they truly need detailed diff.

## Standard Coding Pattern

### Minimal audit example

```php
hyphen_audit_action($conn, 'inventory', 'create_item', [
    'status' => 'failed',
    'entity_type' => 'item',
    'entity_id' => $itemCode,
    'target_label' => $itemName,
    'metadata' => ['reason' => 'duplicate_code'],
]);
```

```php
hyphen_audit_action($conn, 'inventory', 'create_item', [
    'entity_type' => 'item',
    'entity_id' => (string) $newItemId,
    'target_label' => $itemName,
    'metadata' => ['summary' => ['category' => $category]],
]);
```

### Diff audit example

```php
$oldSnapshot = [
    'name' => (string) ($existing['name'] ?? ''),
    'status' => (string) ($existing['status'] ?? ''),
];

$newSnapshot = [
    'name' => $name,
    'status' => $status,
];

hyphen_audit_action($conn, 'inventory', 'update_item', [
    'entity_type' => 'item',
    'entity_id' => (string) $itemId,
    'target_label' => $name,
    'old_values' => $oldSnapshot,
    'new_values' => $newSnapshot,
]);
```

If `inventory.update_item` is configured as `diff` in `build/audit_rules.php`, the system will automatically store only changed fields.

## Snapshot Rule

For modules that need diff, create one small snapshot helper in the action file.

Examples already in the project:

- `audit_user_snapshot()`
- `audit_menu_snapshot()`
- `audit_page_snapshot()`
- `audit_template_snapshot()`

The snapshot helper should:

- normalize field names
- remove irrelevant data
- avoid raw DB rows when possible
- keep only business fields that matter

Do not pass full unfiltered database rows unless there is no better option.

## Sensitive Data Rule

The audit core already redacts sensitive keys such as:

- password
- token
- secret
- authorization
- smtp
- app_password

Still, module code should avoid placing unnecessary secrets into metadata.

Best practice:

- store only summary or result code
- do not place raw credentials in metadata
- do not audit entire request payloads blindly

## How To Add Audit To A New Module

1. Include `build/audit.php` in the action endpoint.
2. Add one small snapshot helper only if the module has update diff needs.
3. Add `hyphen_audit_action()` in the main success and failure branches.
4. Keep most actions in minimal mode.
5. If one action needs detailed diff, add a rule in `build/audit_rules.php`.
6. Verify the new logs appear in `System Audit Log`.

## Copy-Paste Module Template

Use the following snippets as the default starting point when a new module needs audit logging.

### Template A: Minimal action endpoint

Use this when the module only needs minimal audit records for create, delete, send, approve, reject, or other straightforward actions.

```php
<?php
include_once '../../../build/config.php';
include_once '../../../build/authorization.php';
include_once '../../../build/audit.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_set_charset($conn, 'utf8mb4');

if (!function_exists('json_response')) {
    function json_response(bool $success, string $message, array $data = [], int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ]);
        exit;
    }
}

hyphen_boot_session();

if (!hyphen_is_authenticated()) {
    json_response(false, 'Your session has expired. Please sign in again.', [], 401);
}

hyphen_refresh_session_authorization($conn, (string) ($_SESSION['staff_id'] ?? ''));
hyphen_require_ability('add', 'module/page_key', null, true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$recordCode = trim((string) ($_POST['record_code'] ?? ''));
$recordName = trim((string) ($_POST['record_name'] ?? ''));

if ($recordCode === '' || $recordName === '') {
    hyphen_audit_action($conn, 'module_name', 'create_record', [
        'status' => 'failed',
        'entity_type' => 'record',
        'entity_id' => $recordCode,
        'target_label' => $recordName,
        'metadata' => ['reason' => 'missing_required_fields'],
    ]);
    json_response(false, 'Required fields are missing.', [], 422);
}

$statement = mysqli_prepare($conn, 'INSERT INTO some_table (record_code, record_name) VALUES (?, ?)');
if (!$statement) {
    json_response(false, 'Failed to prepare insert.', [], 500);
}

mysqli_stmt_bind_param($statement, 'ss', $recordCode, $recordName);
if (!mysqli_stmt_execute($statement)) {
    $error = mysqli_stmt_error($statement);
    mysqli_stmt_close($statement);

    hyphen_audit_action($conn, 'module_name', 'create_record', [
        'status' => 'failed',
        'entity_type' => 'record',
        'entity_id' => $recordCode,
        'target_label' => $recordName,
        'metadata' => ['error' => $error],
    ]);

    json_response(false, 'Unable to create record: ' . $error, [], 500);
}

$newId = mysqli_insert_id($conn);
mysqli_stmt_close($statement);

hyphen_audit_action($conn, 'module_name', 'create_record', [
    'entity_type' => 'record',
    'entity_id' => (string) $newId,
    'target_label' => $recordName,
    'metadata' => ['summary' => ['record_code' => $recordCode]],
]);

json_response(true, 'Record created successfully.', ['id' => $newId]);
```

### Template B: Update action with diff

Use this when the action really needs change tracking.

```php
<?php
include_once '../../../build/config.php';
include_once '../../../build/authorization.php';
include_once '../../../build/audit.php';

function audit_inventory_snapshot(array $row): array
{
    return [
        'item_code' => trim((string) ($row['item_code'] ?? '')),
        'item_name' => trim((string) ($row['item_name'] ?? '')),
        'category' => trim((string) ($row['category'] ?? '')),
        'status' => trim((string) ($row['status'] ?? '')),
    ];
}

$existing = [
    'item_code' => 'ITM-001',
    'item_name' => 'Old Name',
    'category' => 'Raw Material',
    'status' => 'active',
];

$updated = [
    'item_code' => 'ITM-001',
    'item_name' => $itemName,
    'category' => $category,
    'status' => $status,
];

$oldSnapshot = audit_inventory_snapshot($existing);
$newSnapshot = audit_inventory_snapshot($updated);

hyphen_audit_action($conn, 'inventory', 'update_item', [
    'entity_type' => 'item',
    'entity_id' => (string) $itemId,
    'target_label' => $itemName,
    'old_values' => $oldSnapshot,
    'new_values' => $newSnapshot,
]);
```

Then register the diff rule once in `build/audit_rules.php`:

```php
'inventory.update_item' => [
    'mode' => 'diff',
    'fields' => ['item_code', 'item_name', 'category', 'status'],
],
```

### Template C: Status toggle action

Use this when only one or two business fields change and you want a very focused diff.

```php
$oldState = ['status' => $currentStatus];
$newState = ['status' => $nextStatus];

hyphen_audit_action($conn, 'inventory', 'toggle_item_status', [
    'entity_type' => 'item',
    'entity_id' => (string) $itemId,
    'target_label' => $itemCode,
    'old_values' => $oldState,
    'new_values' => $newState,
]);
```

Recommended rule:

```php
'inventory.toggle_item_status' => [
    'mode' => 'diff',
    'fields' => ['status'],
],
```

### Template D: Failure-only external send action

Use this for email, API call, webhook, export, or push notification style actions.

```php
if (!$result['success']) {
    hyphen_audit_action($conn, 'inventory', 'send_stock_alert', [
        'status' => 'failed',
        'entity_type' => 'item',
        'entity_id' => (string) $itemId,
        'target_label' => $itemCode,
        'metadata' => [
            'channel' => 'email',
            'recipient' => $recipientEmail,
            'result' => $result,
        ],
    ]);
}

hyphen_audit_action($conn, 'inventory', 'send_stock_alert', [
    'entity_type' => 'item',
    'entity_id' => (string) $itemId,
    'target_label' => $itemCode,
    'metadata' => [
        'channel' => 'email',
        'recipient' => $recipientEmail,
    ],
]);
```

## Fast Module Checklist

When you need to wire audit quickly, copy this checklist into your working notes:

1. Add `include_once '../../../build/audit.php';`
2. Decide whether the action is minimal or diff.
3. If minimal: add one `hyphen_audit_action()` in failure and one in success.
4. If diff: add a small snapshot helper and pass `old_values` / `new_values`.
5. If diff is important: register the field list in `build/audit_rules.php`.
6. Keep metadata short and business-focused.
7. Do not include secrets or raw credentials.

## Deployment Checklist

1. Run database migrations so `hy_audit_logs` exists.
2. Ensure the module action file includes `build/audit.php`.
3. Add minimal `hyphen_audit_action()` calls for create, update, delete, send, approve, reject, toggle, or login-like actions.
4. Add snapshot helper only when a detailed update diff is needed.
5. Register diff fields in `build/audit_rules.php` only for selected important actions.
6. Test both success and failure flows.
7. Confirm logs render correctly in `pages/sys_admin/system_audit_log.php`.

## Current Recommended Scope

Audit should be added to:

- CRUD endpoints
- status change endpoints
- approval/rejection endpoints
- email or external-send endpoints
- authentication events
- configuration changes

Audit should usually not be added to:

- simple read/list endpoints
- pure modal data loaders
- harmless UI-only helper requests
- high-frequency noise actions without business value

## Maintenance Rule

When adding future modules, follow this decision order:

1. Can this action use minimal audit only?
2. If not, can one small snapshot helper solve it?
3. If diff is needed, can the field list be declared once in `build/audit_rules.php`?

If the answer to step 1 is yes, do not add diff logic.

That is the default project standard.