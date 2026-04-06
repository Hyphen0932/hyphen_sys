<?php
include_once '../../build/config.php';
include_once '../../build/session.php';

$defaultProfileImage = '../../assets/user_image/00000.jpg';

$menus = [];
$pagesByMenu = [];
$dbError = null;

$menuResult = mysqli_query($conn, 'SELECT id, menu_id, menu_name, menu_icon FROM hy_user_menu ORDER BY menu_id ASC');
if ($menuResult === false) {
    $dbError = 'Unable to load menu records from hy_user_menu.';
} else {
    while ($row = mysqli_fetch_assoc($menuResult)) {
        $menus[] = $row;
    }
}

if ($dbError === null) {
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

include_once '../../include/h_main.php';
?>
<!-- Start::content -->
<?php if ($dbError !== null): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Database Error:</strong> <?php echo htmlspecialchars($dbError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
</div>
<?php endif; ?>
<form id="createUserForm" enctype="multipart/form-data">
    <div class="row">
        <div class="col-xl-4 col-lg-5">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="ps-0">
                        <div class="main-profile-overview">
                            <span class="avatar avatar-xxl avatar-rounded main-img-user profile-user user-profile">
                                <img src="<?php echo htmlspecialchars($defaultProfileImage); ?>" alt="" class="profile-img" id="profilePreview">
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
                                        <option value="" selected disabled>Select System Role</option>
                                        <option value="System Admin">System Admin</option>
                                        <option value="Manager">Manager</option>
                                        <option value="Employee">Employee</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="deptSelect">Department</label>
                                </div>
                                <div class="col-md-9">
                                    <select class="form-control" data-trigger id="deptSelect">
                                        <option value="" selected disabled>Select Department</option>
                                        <option>Human Resources</option>
                                        <option>Finance</option>
                                        <option>IT</option>
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
                                    <input type="text" class="form-control" id="staffIdInput" name="staff_id" placeholder="Staff ID" required>
                                    <div class="form-text">Default password will be the same as Staff ID.</div>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="usernameInput">User Name</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="usernameInput" name="username" placeholder="User Name" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="designationInput">Designation</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="designationInput" name="designation" placeholder="Designation" maxlength="20" required>
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
                                    <input type="email" class="form-control" id="emailInput" name="email" placeholder="Email" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label" for="phoneInput">Phone</label>
                                </div>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="phoneInput" name="phone" placeholder="phone number" maxlength="10" required>
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
                            <div class="text-muted">Menu access is saved to hy_users.menu_rights. Page CRUD is saved to hy_user_permissions.</div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <div id="pageAccessList" class="list-group">
                                    <?php if (!empty($menus)): ?>
                                        <?php foreach ($menus as $index => $menu): ?>
                                            <?php
                                            $menuId = (string) ($menu['menu_id'] ?? '');
                                            $menuPages = $pagesByMenu[$menuId] ?? [];
                                            $collapseId = collapse_dom_id($menuId === '' ? ('menu-' . $index) : $menuId);
                                            $inputId = 'menu-right-' . $index;
                                            $isExpanded = $index === 0;
                                            ?>
                                            <div class="list-group-item active py-2 <?php echo $index > 0 ? 'mt-3 rounded-top' : ''; ?>">
                                                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                                                    <button class="btn btn-link p-0 text-decoration-none text-white text-start d-flex align-items-center gap-2 flex-grow-1 <?php echo $isExpanded ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($collapseId); ?>" aria-expanded="<?php echo $isExpanded ? 'true' : 'false'; ?>" aria-controls="<?php echo htmlspecialchars($collapseId); ?>">
                                                        <i class="<?php echo htmlspecialchars((string) (($menu['menu_icon'] ?? '') !== '' ? $menu['menu_icon'] : 'bi bi-folder')); ?>"></i>
                                                        <span class="fs-15 fw-semibold"><?php echo htmlspecialchars((string) ($menu['menu_name'] ?? 'Menu')); ?></span>
                                                        <span class="badge bg-light text-dark"><?php echo count($menuPages); ?> Pages</span>
                                                    </button>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <input type="checkbox" class="d-none menu-toggle-input" id="<?php echo htmlspecialchars($inputId); ?>" name="menu_rights[]" value="<?php echo htmlspecialchars($menuId); ?>" checked>
                                                        <div class="toggle toggle-success on mb-0 menu-toggle" data-input-id="<?php echo htmlspecialchars($inputId); ?>">
                                                            <span></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="collapse <?php echo $isExpanded ? 'show' : ''; ?>" id="<?php echo htmlspecialchars($collapseId); ?>">
                                                <?php if (!empty($menuPages)): ?>
                                                    <?php foreach ($menuPages as $page): ?>
                                                        <?php $pageId = (int) ($page['id'] ?? 0); ?>
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
                                                                                <input class="form-check-input form-checked-info me-2 permission-input" type="checkbox" name="permissions[<?php echo $pageId; ?>][view]" id="page-<?php echo $pageId; ?>-view" checked>
                                                                                <label class="form-check-label" for="page-<?php echo $pageId; ?>-view">View</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col">
                                                                            <div class="form-check form-check-lg h-100 d-flex align-items-center">
                                                                                <input class="form-check-input form-checked-info me-2 permission-input" type="checkbox" name="permissions[<?php echo $pageId; ?>][add]" id="page-<?php echo $pageId; ?>-add">
                                                                                <label class="form-check-label" for="page-<?php echo $pageId; ?>-add">Add</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col">
                                                                            <div class="form-check form-check-lg h-100 d-flex align-items-center">
                                                                                <input class="form-check-input form-checked-info me-2 permission-input" type="checkbox" name="permissions[<?php echo $pageId; ?>][edit]" id="page-<?php echo $pageId; ?>-edit">
                                                                                <label class="form-check-label" for="page-<?php echo $pageId; ?>-edit">Edit</label>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col">
                                                                            <div class="form-check form-check-lg h-100 d-flex align-items-center">
                                                                                <input class="form-check-input form-checked-danger me-2 permission-input" type="checkbox" name="permissions[<?php echo $pageId; ?>][delete]" id="page-<?php echo $pageId; ?>-delete">
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
                                    <?php else: ?>
                                        <div class="list-group-item py-3 text-muted">No menu records found. Create menus first before assigning page access.</div>
                                    <?php endif; ?>
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
                <button type="submit" class="btn btn-primary" id="saveUserButton">Save User</button>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('createUserForm');
    const imageInput = document.getElementById('userImageInput');
    const previewImage = document.getElementById('profilePreview');
    const resetButton = document.getElementById('resetUserForm');
    const crudUrl = './action/sys_users_crud.php';

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
                previewImage.src = '<?php echo htmlspecialchars($defaultProfileImage, ENT_QUOTES); ?>';
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
                    toggle.classList.add('on');
                    const input = getToggleInput(toggle);
                    if (input) {
                        input.checked = true;
                    }
                    syncPermissionState(toggle);
                });

                if (previewImage) {
                    previewImage.src = '<?php echo htmlspecialchars($defaultProfileImage, ENT_QUOTES); ?>';
                }
            }, 0);
        });
    }

    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(form);
            formData.append('action', 'create_user');

            postRequest(formData)
                .then(function(payload) {
                    showSuccess(payload.message || 'User created successfully.').then(function() {
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

<!-- End::content -->
<?php
include_once '../../include/h_footer.php';
?>