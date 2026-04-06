# Sys Admin CRUD Page Deployment Guide

## Current Audit Result

Audit scope on 2026-04-06:

- `pages/sys_admin/system_menu.php`
- `pages/sys_admin/system_users.php`
- `pages/sys_admin/system_users_new.php`
- `pages/sys_admin/system_users_edit.php`
- `pages/sys_admin/system_dashboard.php`
- `pages/sys_admin/action/sys_menu_crud.php`
- `pages/sys_admin/action/sys_users_crud.php`

Result:

- `system_menu.php`: now protected on both page render and backend CRUD actions.
- `system_users.php`: page auth is bound and button visibility follows permissions.
- `system_users_new.php`: page access is enforced by `build/session.php`; backend create action requires `add`.
- `system_users_edit.php`: page access is enforced by `build/session.php`; backend update action requires `edit`.
- `system_dashboard.php`: was missing session/auth bootstrap because the file was empty; fixed on 2026-04-06.
- `table_sample.php`: intentionally excluded from business-page governance.

Known functional gap that still remains by design:

- `system_users.php` shows no real delete action yet because there is no delete backend implemented for users.

## Rule Of Thumb

Reusable template files have been added here:

- `pages/template/sys_admin/sample_module.php`
- `pages/template/sys_admin/sample_module_new.php`
- `pages/template/sys_admin/sample_module_edit.php`
- `pages/template/sys_admin/action/sample_module_crud.php`

Recommended usage:

1. Copy these files into `pages/sys_admin` and `pages/sys_admin/action`.
2. Rename `sample_module` to your real module key.
3. Replace placeholder field names and SQL.
4. Register the copied pages in `System Menu`.

In this project, page authorization has 3 layers and all 3 must be aligned:

1. `hy_user_pages` controls which page exists, what ability is required to enter it, and which page's permission record it maps to.
2. The page PHP file must include `build/session.php` so direct access is checked automatically.
3. Every write endpoint in `pages/.../action/*.php` must call `hyphen_require_ability(...)` before changing data.

If any one of these 3 layers is missing, users can end up seeing or doing things they should not.

## When You Add A New Business Page

### 1. Create the page file

Example skeleton:

```php
<?php
include_once '../../build/config.php';
include_once '../../build/session.php';

$pageAuth = hyphen_bind_page_auth('sys_admin/example_list');

$canCreate = hyphen_page_auth('sys_admin/example_new')['add'];
$canEdit = hyphen_page_auth('sys_admin/example_edit')['edit'];
$canDelete = hyphen_page_auth('sys_admin/example_delete')['delete'];

include_once '../../include/h_main.php';
?>

<!-- page content -->

<?php
include_once '../../include/h_footer.php';
?>
```

Notes:

- Keep `page_url` and the bound page key in the same format, for example `sys_admin/example_list`.
- Because your platform hides `.php`, all front-end links should use extensionless routes such as `example_list`, not `example_list.php`.
- `hyphen_bind_page_auth()` is most useful on list/index pages where buttons must be shown or hidden.

### 2. Register the page in `System Menu`

Use the `System Menu` page to add a row into `hy_user_pages`.

For a normal list page:

- `Display Name`: human-readable page name
- `Page URL`: `sys_admin/example_list`
- `File Name`: `example_list`
- `Required Ability`: `view`
- `Permission Target Page`: `Self`
- `Show in Sidebar`: `1`
- `Show in Breadcrumb`: `1`

For a create page:

- `Page URL`: `sys_admin/example_new`
- `File Name`: `example_new`
- `Required Ability`: `add`
- `Permission Target Page`: point to the main list page record
- `Show in Sidebar`: `0`
- `Show in Breadcrumb`: usually `1`

For an edit page:

- `Page URL`: `sys_admin/example_edit`
- `File Name`: `example_edit`
- `Required Ability`: `edit`
- `Permission Target Page`: point to the main list page record
- `Show in Sidebar`: `0`
- `Show in Breadcrumb`: `0` or `1` based on your UX choice

For a delete-only endpoint page, you usually do not need a visible page. Most projects keep delete control only in the action endpoint and use the list page as the permission target.

## How CRUD Permission Mapping Works

Recommended pattern:

- Main list page owns the permission row.
- Create and edit pages map back to the main list page through `permission_target_page_id`.
- `required_ability` decides which permission must be true to enter the page.

Example:

- `sys_admin/system_users` => required `view`, target `self`
- `sys_admin/system_users_new` => required `add`, target `system_users`
- `sys_admin/system_users_edit` => required `edit`, target `system_users`

This means one permission record in `hy_user_permissions` can control the whole CRUD family.

## Backend Action File Checklist

Every action file must do all of this:

```php
<?php
include_once '../../../build/config.php';
include_once '../../../build/authorization.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_set_charset($conn, 'utf8mb4');

hyphen_boot_session();

if (!hyphen_is_authenticated()) {
    json_response(false, 'Your session has expired. Please sign in again.', [], 401);
}

hyphen_refresh_session_authorization($conn, (string) ($_SESSION['staff_id'] ?? ''));
```

Then protect each action explicitly:

```php
switch ($action) {
    case 'create_item':
        hyphen_require_ability('add', 'sys_admin/example_new', null, true);
        create_item($conn);
        break;

    case 'update_item':
        hyphen_require_ability('edit', 'sys_admin/example_edit', null, true);
        update_item($conn);
        break;

    case 'delete_item':
        hyphen_require_ability('delete', 'sys_admin/example_list', null, true);
        delete_item($conn);
        break;
}
```

Do not rely on front-end hidden buttons alone. The backend check is mandatory.

## Front-End Visibility Checklist

On list pages, hide or disable buttons using permission helpers:

```php
$pageAuth = hyphen_bind_page_auth('sys_admin/example_list');
$canCreate = hyphen_page_auth('sys_admin/example_new')['add'];
$canEdit = hyphen_page_auth('sys_admin/example_edit')['edit'];
$canDelete = hyphen_page_auth('sys_admin/example_list')['delete'];
```

Then wire the UI:

- show `Add` only when `$canCreate` is true
- show `Edit` only when `$canEdit` is true
- show `Delete` only when `$canDelete` is true

If the page itself is a pure create page or pure edit page, `build/session.php` already blocks direct entry based on `required_ability` as long as the page includes `build/session.php`.

## Minimal Deployment Checklist For Every New CRUD Module

- Create the page file under `pages/...`
- Include `build/config.php`
- Include `build/session.php`
- Bind page auth on the list page
- Register rows in `System Menu` / `hy_user_pages`
- Set `required_ability` correctly
- Set `permission_target_page_id` correctly
- Hide non-list pages from sidebar unless they should be navigable
- Protect every backend write action with `hyphen_require_ability()`
- Use extensionless links in the front end if `.php` is hidden by `.htaccess`

## Common Mistakes To Avoid

- Creating a page file but forgetting to register it in `hy_user_pages`
- Registering the page but forgetting to include `build/session.php`
- Showing Add/Edit/Delete buttons without checking `hyphen_page_auth()`
- Protecting the UI but forgetting to protect the backend action file
- Saving `.php` in `page_url` even though this project stores extensionless paths
- Setting `permission_target_page_id` to self for `_new` or `_edit` pages when they should inherit from the main list page

## Recommended Quick Test After Deployment

Test with a non-admin user:

1. Give only `view` on the module list page and confirm the user cannot open create/edit routes.
2. Give `add` and confirm the user can open create but still cannot edit existing records.
3. Give `edit` and confirm the user can open edit but not create if `add` is still off.
4. Try calling the backend action URL directly and confirm it still rejects unauthorized requests.