<?php
include_once '../../build/config.php';
include_once '../../build/session.php';

$defaultProfileImage = '../../assets/user_image/00000.jpg';

$menus = [];
$pagesByMenu = [];
$permissionMap = [];
$dbError = null;
$userError = null;
$userId = (int) ($_GET['id'] ?? 0);
$user = null;
$selectedMenus = [];

if ($userId <= 0) {
    $userError = 'Invalid user ID.';
} else {
    $userStatement = mysqli_prepare($conn, 'SELECT id, username, staff_id, email, phone, designation, role, status, menu_rights, image_url FROM hy_users WHERE id = ? LIMIT 1');
    if (!$userStatement) {
        $dbError = 'Unable to prepare user lookup.';
    } else {
        mysqli_stmt_bind_param($userStatement, 'i', $userId);
        mysqli_stmt_execute($userStatement);
        $userResult = mysqli_stmt_get_result($userStatement);
        $user = $userResult ? mysqli_fetch_assoc($userResult) : null;
        mysqli_stmt_close($userStatement);

        if (!$user) {
            $userError = 'User record not found.';
        }
    }
}

if ($dbError === null && $userError === null) {
    $menuResult = mysqli_query($conn, 'SELECT id, menu_id, menu_name, menu_icon FROM hy_user_menu ORDER BY menu_id ASC');
    if ($menuResult === false) {
        $dbError = 'Unable to load menu records from hy_user_menu.';
    } else {
        while ($row = mysqli_fetch_assoc($menuResult)) {
            $menus[] = $row;
        }
    }
}

if ($dbError === null && $userError === null) {
    $pageResult = mysqli_query($conn, 'SELECT id, menu_id, display_name, page_name, page_url, page_order FROM hy_user_pages ORDER BY page_order ASC, id ASC');
    if ($pageResult === false) {
        $dbError = 'Unable to load submenu records from hy_user_pages.';
    } else {
        while ($row = mysqli_fetch_assoc($pageResult)) {
            $menuId = (string) ($row['menu_id'] ?? '');
            if (!isset($pagesByMenu[$menuId])) {
                $pagesByMenu[$menuId] = [];
            }

            $pagesByMenu[$menuId][] = $row;
        }
    }
}

if ($dbError === null && $userError === null && $user !== null) {
    $decodedMenus = json_decode((string) ($user['menu_rights'] ?? '[]'), true);
    if (is_array($decodedMenus)) {
        foreach ($decodedMenus as $menuId) {
            $menuId = trim((string) $menuId);
            if ($menuId !== '') {
                $selectedMenus[] = $menuId;
            }
        }
    }

    $permissionStatement = mysqli_prepare($conn, 'SELECT page_id, can_view, can_add, can_edit, can_delete FROM hy_user_permissions WHERE staff_id = ?');
    if (!$permissionStatement) {
        $dbError = 'Unable to prepare permission lookup.';
    } else {
        $staffId = (string) ($user['staff_id'] ?? '');
        mysqli_stmt_bind_param($permissionStatement, 's', $staffId);
        mysqli_stmt_execute($permissionStatement);
        $permissionResult = mysqli_stmt_get_result($permissionStatement);

        while ($permissionResult && ($row = mysqli_fetch_assoc($permissionResult))) {
            $pageId = (int) ($row['page_id'] ?? 0);
            $permissionMap[$pageId] = [
                'view' => (int) ($row['can_view'] ?? 0) === 1,
                'add' => (int) ($row['can_add'] ?? 0) === 1,
                'edit' => (int) ($row['can_edit'] ?? 0) === 1,
                'delete' => (int) ($row['can_delete'] ?? 0) === 1,
            ];
        }

        mysqli_stmt_close($permissionStatement);
    }
}

function collapse_dom_id(string $value): string
{
    $normalized = preg_replace('/[^A-Za-z0-9_-]/', '-', $value);
    return 'access-' . trim((string) $normalized, '-');
}

function page_access_description(array $page): string
{
    $pageUrl = trim((string) ($page['page_url'] ?? ''));
    $pageName = trim((string) ($page['page_name'] ?? ''));

    if ($pageUrl !== '') {
        return $pageUrl;
    }

    if ($pageName !== '') {
        return $pageName;
    }

    return 'Page permission configuration';
}

function system_user_image_path(?string $imageUrl): string
{
    $imageUrl = trim((string) $imageUrl);
    $defaultPath = '../../assets/user_image/00000.jpg';

    if ($imageUrl === '') {
        return $defaultPath;
    }

    $absolutePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'user_image' . DIRECTORY_SEPARATOR . basename($imageUrl);
    if (!is_file($absolutePath)) {
        return $defaultPath;
    }

    return '../../assets/user_image/' . rawurlencode(basename($imageUrl));
}

function status_option_list(string $currentStatus): array
{
    $options = [
        'active' => 'Active',
        'deactivated' => 'Deactivated',
    ];

    if ($currentStatus !== '' && !array_key_exists($currentStatus, $options)) {
        $options[$currentStatus] = ucfirst($currentStatus);
    }

    return $options;
}

include_once '../../include/h_main.php';
?>
<!-- Start::content -->
<?php if ($dbError !== null || $userError !== null): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error:</strong> <?php echo htmlspecialchars($dbError ?? $userError ?? 'Unable to load user data.'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <a href="system_users" class="btn btn-primary">Back to User List</a>
    </div>
</div>
<?php else: ?>
<form id="editUserForm" enctype="multipart/form-data">
    <input type="hidden" name="user_id" value="<?php echo (int) ($user['id'] ?? 0); ?>">
    <input type="hidden" name="current_staff_id" value="<?php echo htmlspecialchars((string) ($user['staff_id'] ?? '')); ?>">
    <div class="row">
        <div class="col-xl-4 col-lg-5">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="ps-0">
                        <div class="main-profile-overview">
                            <span class="avatar avatar-xxl avatar-rounded main-img-user profile-user user-profile">
                                <img src="<?php echo htmlspecialchars(system_user_image_path($user['image_url'] ?? '')); ?>" alt="" class="profile-img" id="profilePreview">
                                <a href="javascript:void(0);" class="badge rounded-pill bg-primary avatar-badge profile-edit">
                                    <input type="file" name="image_url" class="position-absolute profile-change w-100 h-100 op-0" id="userImageInput" accept=".jpg,.jpeg,.png,.gif,.webp">
                                    <i class="fe fe-camera"></i>
                                </a>
                            </span>
                        </div>
                        <div class="mb-4 main-content-label">Personal Information</div>
                        <div class="form-group mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="roleSelect">System Role</label>
                                </div>
                                <div class="col-md-9">
                                    <select class="form-control" name="role" data-trigger id="roleSelect" required>
                                        <option value="System Admin" <?php echo (($user['role'] ?? '') === 'System Admin') ? 'selected' : ''; ?>>System Admin</option>
                                        <option value="Manager" <?php echo (($user['role'] ?? '') === 'Manager') ? 'selected' : ''; ?>>Manager</option>
                                        <option value="Employee" <?php echo (($user['role'] ?? '') === 'Employee') ? 'selected' : ''; ?>>Employee</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="statusSelect">Status</label>
                                </div>
                                <div class="col-md-9">
                                    <select class="form-control" name="status" id="statusSelect" required>
                                        <?php foreach (status_option_list((string) ($user['status'] ?? '')) as $statusValue => $statusLabel): ?>
                                            <option value="<?php echo htmlspecialchars($statusValue); ?>" <?php echo ((string) ($user['status'] ?? '') === $statusValue) ? 'selected' : ''; ?>><?php echo htmlspecialchars($statusLabel); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4 main-content-label">Name</div>
                        <div class="form-group mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="staffIdInput">Staff ID</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="staffIdInput" name="staff_id" value="<?php echo htmlspecialchars((string) ($user['staff_id'] ?? '')); ?>" readonly>
                                    <div class="form-text">Staff ID is locked because permissions and login are linked to this value.</div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="usernameInput">User Name</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="usernameInput" name="username" value="<?php echo htmlspecialchars((string) ($user['username'] ?? '')); ?>" placeholder="User Name" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="designationInput">Designation</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="designationInput" name="designation" value="<?php echo htmlspecialchars((string) ($user['designation'] ?? '')); ?>" placeholder="Designation" maxlength="20" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4 main-content-label">Contact Info</div>
                        <div class="form-group mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="emailInput">Email<i>*</i></label>
                                </div>
                                <div class="col-md-9">
                                    <input type="email" class="form-control" id="emailInput" name="email" value="<?php echo htmlspecialchars((string) ($user['email'] ?? '')); ?>" placeholder="Email" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="phoneInput">Phone</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="phoneInput" name="phone" value="<?php echo htmlspecialchars((string) ($user['phone'] ?? '')); ?>" placeholder="Phone Number" maxlength="10" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4 main-content-label">Password</div>
                        <div class="form-group mb-0">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="passwordInput">New Password</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="password" class="form-control" id="passwordInput" name="password" placeholder="Leave blank to keep current password">
                                    <div class="form-text">Only fill this field when you want to replace the current password.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-8 col-lg-7">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="ps-0">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                            <div class="main-content-label mb-0">Page Access</div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div id="pageAccessList" class="list-group">
                                    <?php foreach ($menus as $index => $menu): ?>
                                        <?php
                                        $menuId = (string) ($menu['menu_id'] ?? '');
                                        $menuPages = $pagesByMenu[$menuId] ?? [];
                                        $collapseId = collapse_dom_id($menuId === '' ? ('menu-' . $index) : $menuId);
                                        $inputId = 'menu-right-' . $index;
                                        $menuChecked = in_array($menuId, $selectedMenus, true);
                                        $isExpanded = $index === 0 || $menuChecked;
                                        ?>
                                        <div class="list-group-item active py-2 <?php echo $index > 0 ? 'mt-3 rounded-top' : ''; ?>">
                                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                                <button class="btn btn-link p-0 text-decoration-none text-white text-start d-flex align-items-center gap-2 flex-grow-1 <?php echo $isExpanded ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($collapseId); ?>" aria-expanded="<?php echo $isExpanded ? 'true' : 'false'; ?>" aria-controls="<?php echo htmlspecialchars($collapseId); ?>">
                                                    <i class="<?php echo htmlspecialchars((string) (($menu['menu_icon'] ?? '') !== '' ? $menu['menu_icon'] : 'bi bi-folder')); ?>"></i>
                                                    <span class="fs-15 fw-semibold"><?php echo htmlspecialchars((string) ($menu['menu_name'] ?? 'Menu')); ?></span>
                                                    <span class="badge bg-light text-dark"><?php echo count($menuPages); ?> Pages</span>
                                                </button>
                                                <div class="d-flex align-items-center gap-2">
                                                    <input type="checkbox" class="d-none menu-toggle-input" id="<?php echo htmlspecialchars($inputId); ?>" name="menu_rights[]" value="<?php echo htmlspecialchars($menuId); ?>" <?php echo $menuChecked ? 'checked' : ''; ?>>
                                                    <div class="toggle toggle-success mb-0 menu-toggle <?php echo $menuChecked ? 'on' : ''; ?>" data-input-id="<?php echo htmlspecialchars($inputId); ?>">
                                                        <span></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="collapse <?php echo $isExpanded ? 'show' : ''; ?>" id="<?php echo htmlspecialchars($collapseId); ?>">
                                            <?php if (!empty($menuPages)): ?>
                                                <?php foreach ($menuPages as $page): ?>
                                                    <?php
                                                    $pageId = (int) ($page['id'] ?? 0);
                                                    $savedPermission = $permissionMap[$pageId] ?? [];
                                                    ?>
                                                    <div class="list-group-item py-3 permission-group" data-menu-id="<?php echo htmlspecialchars($menuId); ?>">
                                                        <div class="row g-3 align-items-start">
                                                            <div class="col-12 col-xl-4">
                                                                <div class="fw-semibold fs-15"><?php echo htmlspecialchars((string) (($page['display_name'] ?? '') !== '' ? $page['display_name'] : ($page['page_name'] ?? 'Untitled Page'))); ?></div>
                                                                <div class="text-muted"><?php echo htmlspecialchars(page_access_description($page)); ?></div>
                                                            </div>
                                                            <div class="col-12 col-xl-8">
                                                                <div class="row row-cols-2 row-cols-sm-4 g-2">
                                                                    <div class="col">
                                                                        <div class="form-check form-check-lg h-100 d-flex align-items-center">
                                                                            <input class="form-check-input form-checked-info me-2 permission-input" type="checkbox" name="permissions[<?php echo $pageId; ?>][view]" id="page-<?php echo $pageId; ?>-view" <?php echo !empty($savedPermission['view']) ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="page-<?php echo $pageId; ?>-view">View</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col">
                                                                        <div class="form-check form-check-lg h-100 d-flex align-items-center">
                                                                            <input class="form-check-input form-checked-info me-2 permission-input" type="checkbox" name="permissions[<?php echo $pageId; ?>][add]" id="page-<?php echo $pageId; ?>-add" <?php echo !empty($savedPermission['add']) ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="page-<?php echo $pageId; ?>-add">Add</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col">
                                                                        <div class="form-check form-check-lg h-100 d-flex align-items-center">
                                                                            <input class="form-check-input form-checked-info me-2 permission-input" type="checkbox" name="permissions[<?php echo $pageId; ?>][edit]" id="page-<?php echo $pageId; ?>-edit" <?php echo !empty($savedPermission['edit']) ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="page-<?php echo $pageId; ?>-edit">Edit</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col">
                                                                        <div class="form-check form-check-lg h-100 d-flex align-items-center">
                                                                            <input class="form-check-input form-checked-danger me-2 permission-input" type="checkbox" name="permissions[<?php echo $pageId; ?>][delete]" id="page-<?php echo $pageId; ?>-delete" <?php echo !empty($savedPermission['delete']) ? 'checked' : ''; ?>>
                                                                            <label class="form-check-label" for="page-<?php echo $pageId; ?>-delete">Delete</label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="list-group-item py-3 text-muted">No pages found under this menu.</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mb-4">
                <a href="system_users" class="btn btn-light">Back</a>
                <button type="reset" class="btn btn-outline-secondary" id="resetUserForm">Reset</button>
                <button type="submit" class="btn btn-primary" id="saveUserButton">Update User</button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editUserForm');
    const imageInput = document.getElementById('userImageInput');
    const previewImage = document.getElementById('profilePreview');
    const resetButton = document.getElementById('resetUserForm');
    const crudUrl = './action/sys_users_crud';
    const originalPreviewSrc = previewImage ? previewImage.getAttribute('src') : '<?php echo htmlspecialchars($defaultProfileImage, ENT_QUOTES); ?>';

    document.querySelectorAll('.menu-toggle').forEach(function(toggle) {
        syncToggleWithInput(toggle);

        toggle.addEventListener('click', function() {
            const input = getToggleInput(toggle);
            if (!input) {
                return;
            }

            window.setTimeout(function() {
                input.checked = toggle.classList.contains('on');
                syncPermissionState(toggle);
            }, 0);
        });

        syncPermissionState(toggle);
    });

    if (imageInput && previewImage) {
        imageInput.addEventListener('change', function() {
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) {
                previewImage.src = originalPreviewSrc;
                return;
            }

            const reader = new FileReader();
            reader.onload = function(event) {
                previewImage.src = event.target && event.target.result ? event.target.result : previewImage.src;
            };
            reader.readAsDataURL(file);
        });
    }

    if (resetButton) {
        resetButton.addEventListener('click', function() {
            window.setTimeout(function() {
                document.querySelectorAll('.menu-toggle').forEach(function(toggle) {
                    syncToggleWithInput(toggle);
                    syncPermissionState(toggle);
                });

                if (previewImage) {
                    previewImage.src = originalPreviewSrc;
                }
            }, 0);
        });
    }

    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(form);
            formData.append('action', 'update_user');

            postRequest(formData)
                .then(function(payload) {
                    showSuccess(payload.message || 'User updated successfully.').then(function() {
                        window.location.href = 'system_users';
                    });
                })
                .catch(function(error) {
                    showError(error.message);
                });
        });
    }

    function getToggleInput(toggle) {
        const inputId = toggle.getAttribute('data-input-id') || '';
        return inputId ? document.getElementById(inputId) : null;
    }

    function syncToggleWithInput(toggle) {
        const input = getToggleInput(toggle);
        if (!input) {
            return;
        }

        toggle.classList.toggle('on', input.checked);
    }

    function syncPermissionState(toggle) {
        const input = getToggleInput(toggle);
        if (!input) {
            return;
        }

        const collapse = toggle.closest('.list-group-item').nextElementSibling;
        if (!collapse) {
            return;
        }

        collapse.querySelectorAll('.permission-input').forEach(function(permissionInput) {
            permissionInput.disabled = !input.checked;
        });

        collapse.classList.toggle('opacity-50', !input.checked);
    }

    function postRequest(formData) {
        return fetch(crudUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            return response.text().then(function(body) {
                let payload;

                try {
                    payload = JSON.parse(body);
                } catch (error) {
                    throw new Error('Unexpected response from server.');
                }

                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || 'Request failed.');
                }

                return payload;
            });
        });
    }

    function showSuccess(message) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                title: 'Success',
                text: message,
                icon: 'success',
                confirmButtonText: 'OK'
            });
        }

        window.alert(message);
        return Promise.resolve();
    }

    function showError(message) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                title: 'Error',
                text: message,
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }

        window.alert(message);
        return Promise.resolve();
    }
});
</script>
<?php endif; ?>

<!-- End::content -->
<?php
include_once '../../include/h_footer.php';
?>