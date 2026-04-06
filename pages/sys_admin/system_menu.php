<?php
include_once '../../build/config.php';
include_once '../../build/session.php';

$pageAuth = hyphen_bind_page_auth('sys_admin/system_menu');

$menus = [];
$pagesByMenu = [];
$pageOptions = [];
$dbError = null;
$pageSchemaFeatures = hyphen_page_schema_features($conn);

$menuResult = mysqli_query($conn, "SELECT id, category, link, menu_id, menu_name, menu_icon, created_at FROM hy_user_menu");
if ($menuResult === false) {
    $dbError = 'Unable to load menu records from hy_user_menu.';
} else {
    while ($row = mysqli_fetch_assoc($menuResult)) {
        $menus[] = $row;
    }

    usort($menus, static function ($left, $right) {
        return strnatcmp((string) $left['menu_id'], (string) $right['menu_id']);
    });
}

if ($dbError === null) {
    $pageSelect = 'SELECT id, menu_id, display_name, page_name, page_url, page_order, created_at';
    if ($pageSchemaFeatures['permission_target_page_id']) {
        $pageSelect .= ', permission_target_page_id';
    } else {
        $pageSelect .= ', NULL AS permission_target_page_id';
    }
    if ($pageSchemaFeatures['required_ability']) {
        $pageSelect .= ', required_ability';
    } else {
        $pageSelect .= ", 'view' AS required_ability";
    }
    if ($pageSchemaFeatures['show_in_sidebar']) {
        $pageSelect .= ', show_in_sidebar';
    } else {
        $pageSelect .= ', 1 AS show_in_sidebar';
    }
    if ($pageSchemaFeatures['show_in_breadcrumb']) {
        $pageSelect .= ', show_in_breadcrumb';
    } else {
        $pageSelect .= ', 1 AS show_in_breadcrumb';
    }

    $pageResult = mysqli_query($conn, $pageSelect . ' FROM hy_user_pages');
    if ($pageResult === false) {
        $dbError = 'Unable to load submenu records from hy_user_pages.';
    } else {
        while ($row = mysqli_fetch_assoc($pageResult)) {
            $menuId = (string) $row['menu_id'];
            if (!empty($row['page_url'])) {
                $pageOptions[(int) ($row['id'] ?? 0)] = trim((string) ($row['display_name'] ?? '')) . ' [' . format_page_path((string) ($row['page_url'] ?? '')) . ']';
            }
            if (!isset($pagesByMenu[$menuId])) {
                $pagesByMenu[$menuId] = [];
            }

            $pagesByMenu[$menuId][] = $row;
        }

        foreach ($pagesByMenu as &$menuPages) {
            usort($menuPages, static function ($left, $right) {
                return version_compare((string) ($left['page_order'] ?? '0'), (string) ($right['page_order'] ?? '0'));
            });
        }
        unset($menuPages);
    }
}

function format_page_path(?string $pageUrl): string
{
    $pageUrl = trim((string) $pageUrl);
    if ($pageUrl === '') {
        return '';
    }

    return ltrim(str_replace('\\', '/', $pageUrl), '/');
}

function page_ability_label(?string $ability): string
{
    $ability = strtolower(trim((string) $ability));
    if (!in_array($ability, ['view', 'add', 'edit', 'delete'], true)) {
        $ability = 'view';
    }

    return ucfirst($ability);
}

include_once '../../include/h_main.php';
?>
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
<div class="row">
    <div class="col-xxl-12">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">
                    Menu Management
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-3">
                        <nav class="nav nav-tabs flex-column nav-style-5" role="tablist">
                            <?php if (!empty($menus)): ?>
                                <?php foreach ($menus as $index => $menu): ?>
                                    <a class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" data-bs-toggle="tab" role="tab"
                                        aria-current="page" href="#menu-<?php echo (int) $menu['id']; ?>" aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                        <i class="<?php echo htmlspecialchars($menu['menu_icon'] ?: 'ri-folder-line'); ?> side-menu__icon me-2 align-middle d-inline-block"></i>
                                        <?php echo htmlspecialchars($menu['menu_name']); ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="nav-link disabled">No menu records found</span>
                            <?php endif; ?>

                            <?php if ($canAdd): ?>
                                <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#exampleModalScrollable3">
                                    <i class="ri-checkbox-multiple-line me-2 align-middle d-inline-block"></i>
                                    Add New Menu
                                </a>
                            <?php else: ?>
                                <span class="nav-link disabled">
                                    <i class="ri-checkbox-multiple-line me-2 align-middle d-inline-block"></i>
                                    Add New Menu
                                </span>
                            <?php endif; ?>
                        </nav>
                    </div>
                    <div class="col-xl-9">
                        <div class="tab-content">
                            <?php if (!empty($menus)): ?>
                                <?php foreach ($menus as $index => $menu): ?>
                                    <?php $menuPages = $pagesByMenu[(string) $menu['menu_id']] ?? []; ?>
                                    <div class="tab-pane <?php echo $index === 0 ? 'show active' : ''; ?> text-muted" id="menu-<?php echo (int) $menu['id']; ?>"
                                        role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table text-nowrap table-sm menu-table" data-menu-id="<?php echo htmlspecialchars($menu['menu_id']); ?>">
                                                <thead>
                                                    <tr>
                                                        <th>S/N</th>
                                                        <th>Display Name</th>
                                                        <th>Page URL</th>
                                                        <th>File Name</th>
                                                        <th>Page Order</th>
                                                        <th>Ability</th>
                                                        <th>Target</th>
                                                        <th>Sidebar</th>
                                                        <th>Breadcrumb</th>
                                                        <th style="text-align: center;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($menuPages)): ?>
                                                        <?php foreach ($menuPages as $rowIndex => $page): ?>
                                                            <tr
                                                                data-page-id="<?php echo (int) $page['id']; ?>"
                                                                data-menu-id="<?php echo htmlspecialchars($page['menu_id']); ?>"
                                                                data-display-name="<?php echo htmlspecialchars($page['display_name'] ?? '', ENT_QUOTES); ?>"
                                                                data-page-url="<?php echo htmlspecialchars($page['page_url'] ?? '', ENT_QUOTES); ?>"
                                                                data-page-name="<?php echo htmlspecialchars($page['page_name'] ?? '', ENT_QUOTES); ?>"
                                                                data-page-order="<?php echo htmlspecialchars($page['page_order'] ?? '', ENT_QUOTES); ?>"
                                                                data-required-ability="<?php echo htmlspecialchars((string) ($page['required_ability'] ?? 'view'), ENT_QUOTES); ?>"
                                                                data-permission-target-page-id="<?php echo (int) ($page['permission_target_page_id'] ?? 0); ?>"
                                                                data-show-in-sidebar="<?php echo ((int) ($page['show_in_sidebar'] ?? 1) === 1) ? '1' : '0'; ?>"
                                                                data-show-in-breadcrumb="<?php echo ((int) ($page['show_in_breadcrumb'] ?? 1) === 1) ? '1' : '0'; ?>">
                                                                <td><?php echo $rowIndex + 1; ?></td>
                                                                <td><?php echo htmlspecialchars($page['display_name'] ?? ''); ?></td>
                                                                <td><?php echo htmlspecialchars(format_page_path($page['page_url'] ?? '')); ?></td>
                                                                <td><?php echo htmlspecialchars($page['page_name'] ?? ''); ?></td>
                                                                <td><?php echo htmlspecialchars($page['page_order'] ?? ''); ?></td>
                                                                <td><?php echo htmlspecialchars(page_ability_label($page['required_ability'] ?? 'view')); ?></td>
                                                                <td><?php echo htmlspecialchars($pageOptions[(int) ($page['permission_target_page_id'] ?? 0)] ?? 'Self'); ?></td>
                                                                <td><span class="badge <?php echo ((int) ($page['show_in_sidebar'] ?? 1) === 1) ? 'bg-success-transparent text-success' : 'bg-light text-dark'; ?>"><?php echo ((int) ($page['show_in_sidebar'] ?? 1) === 1) ? 'Show' : 'Hide'; ?></span></td>
                                                                <td><span class="badge <?php echo ((int) ($page['show_in_breadcrumb'] ?? 1) === 1) ? 'bg-success-transparent text-success' : 'bg-light text-dark'; ?>"><?php echo ((int) ($page['show_in_breadcrumb'] ?? 1) === 1) ? 'Show' : 'Hide'; ?></span></td>
                                                                <td style="text-align: center;">
                                                                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" <?php echo (!$canEdit && !$canDelete) ? 'disabled' : ''; ?>>Action</button>
                                                                    <ul class="dropdown-menu">
                                                                        <?php if ($canEdit): ?>
                                                                            <li><a class="dropdown-item edit-menu-row" href="javascript:void(0);">Edit</a></li>
                                                                        <?php else: ?>
                                                                            <li><span class="dropdown-item disabled">Edit</span></li>
                                                                        <?php endif; ?>
                                                                        <?php if ($canDelete): ?>
                                                                            <li><a class="dropdown-item delete-menu-row" href="javascript:void(0);">Delete</a></li>
                                                                        <?php else: ?>
                                                                            <li><span class="dropdown-item disabled">Delete</span></li>
                                                                        <?php endif; ?>
                                                                    </ul>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="10" style="text-align: center;">No submenu records found</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                                <tfoot>
                                                    <tr>
                                                        <td colspan="10" style="text-align: center;">
                                                            <?php if ($canAdd): ?>
                                                                <a href="javascript:void(0);" class="add-menu-row" data-menu-id="<?php echo htmlspecialchars($menu['menu_id']); ?>" data-menu-name="<?php echo htmlspecialchars($menu['menu_name'], ENT_QUOTES); ?>"><i class="ri-checkbox-multiple-line me-2 align-middle d-inline-block"></i>Add New</a>
                                                            <?php else: ?>
                                                                <span class="text-muted"><i class="ri-checkbox-multiple-line me-2 align-middle d-inline-block"></i>Add New</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">No menu records found. Use Add New Menu to create the first main directory.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="exampleModalScrollable3" tabindex="-1" aria-labelledby="exampleModalScrollable3" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalScrollableTitle3">Add New Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addMenuForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <input type="text" class="form-control" id="category" name="category" placeholder="Select Category" required>
                    </div>
                    <div class="mb-3">
                        <label for="link" class="form-label">System Link</label>
                        <input type="text" class="form-control" id="link" name="link" placeholder="Enter system link" required>
                    </div>
                    <div class="mb-3">
                        <label for="menu_id" class="form-label">Menu ID</label>
                        <input type="text" class="form-control" id="menu_id" name="menu_id" placeholder="Enter menu ID" required>
                    </div>
                    <div class="mb-3">
                        <label for="menu_name" class="form-label">Menu Name</label>
                        <input type="text" class="form-control" id="menu_name" name="menu_name" placeholder="Enter menu name" required>
                    </div>
                    <div class="mb-3">
                        <label for="menu_icon" class="form-label">Menu Icon</label>
                        <input type="text" class="form-control" id="menu_icon" name="menu_icon" placeholder="Enter menu icon">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" <?php echo !$canAdd ? 'disabled' : ''; ?>>Add Menu</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="addPageModal" tabindex="-1" aria-labelledby="addPageModalLabel" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addPageModalLabel">Add New Sub Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addPageForm">
                <input type="hidden" id="add_page_menu_id" name="menu_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="page_display_name" class="form-label">Display Name</label>
                        <input type="text" class="form-control" id="page_display_name" name="display_name" placeholder="Enter display name" required>
                    </div>
                    <div class="mb-3">
                        <label for="page_url" class="form-label">Page URL</label>
                        <input type="text" class="form-control" id="page_url" name="page_url" placeholder="e.g. sys_admin/system_menu">
                    </div>
                    <div class="mb-3">
                        <label for="page_name" class="form-label">File Name</label>
                        <input type="text" class="form-control" id="page_name" name="page_name" placeholder="e.g. system_menu">
                    </div>
                    <div class="mb-3">
                        <label for="page_order" class="form-label">Page Order</label>
                        <input type="text" class="form-control" id="page_order" name="page_order" placeholder="e.g. 99.2.1" required>
                    </div>
                    <div class="mb-3">
                        <label for="required_ability" class="form-label">Required Ability</label>
                        <select class="form-control" id="required_ability" name="required_ability">
                            <option value="view">View</option>
                            <option value="add">Add</option>
                            <option value="edit">Edit</option>
                            <option value="delete">Delete</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="permission_target_page_id" class="form-label">Permission Target Page</label>
                        <select class="form-control" id="permission_target_page_id" name="permission_target_page_id">
                            <option value="">Self</option>
                            <?php foreach ($pageOptions as $pageId => $pageLabel): ?>
                                <option value="<?php echo (int) $pageId; ?>"><?php echo htmlspecialchars($pageLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check form-check-inline mb-3">
                        <input class="form-check-input" type="checkbox" id="add_show_in_sidebar" name="show_in_sidebar" value="1" checked>
                        <label class="form-check-label" for="add_show_in_sidebar">Show in Sidebar</label>
                    </div>
                    <div class="form-check form-check-inline mb-3">
                        <input class="form-check-input" type="checkbox" id="add_show_in_breadcrumb" name="show_in_breadcrumb" value="1" checked>
                        <label class="form-check-label" for="add_show_in_breadcrumb">Show in Breadcrumb</label>
                    </div>
                    <div class="form-text">Leave Page URL and File Name empty to create a child group header in the sidebar.</div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" <?php echo !$canAdd ? 'disabled' : ''; ?>>Add Page</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="editPageModal" tabindex="-1" aria-labelledby="editPageModalLabel" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPageModalLabel">Edit Sub Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPageForm">
                <input type="hidden" id="edit_page_id" name="id">
                <input type="hidden" id="edit_page_menu_id" name="menu_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_display_name" class="form-label">Display Name</label>
                        <input type="text" class="form-control" id="edit_display_name" name="display_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_page_url" class="form-label">Page URL</label>
                        <input type="text" class="form-control" id="edit_page_url" name="page_url">
                    </div>
                    <div class="mb-3">
                        <label for="edit_page_name" class="form-label">File Name</label>
                        <input type="text" class="form-control" id="edit_page_name" name="page_name">
                    </div>
                    <div class="mb-3">
                        <label for="edit_page_order" class="form-label">Page Order</label>
                        <input type="text" class="form-control" id="edit_page_order" name="page_order" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_required_ability" class="form-label">Required Ability</label>
                        <select class="form-control" id="edit_required_ability" name="required_ability">
                            <option value="view">View</option>
                            <option value="add">Add</option>
                            <option value="edit">Edit</option>
                            <option value="delete">Delete</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_permission_target_page_id" class="form-label">Permission Target Page</label>
                        <select class="form-control" id="edit_permission_target_page_id" name="permission_target_page_id">
                            <option value="">Self</option>
                            <?php foreach ($pageOptions as $pageId => $pageLabel): ?>
                                <option value="<?php echo (int) $pageId; ?>"><?php echo htmlspecialchars($pageLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check form-check-inline mb-3">
                        <input class="form-check-input" type="checkbox" id="edit_show_in_sidebar" name="show_in_sidebar" value="1">
                        <label class="form-check-label" for="edit_show_in_sidebar">Show in Sidebar</label>
                    </div>
                    <div class="form-check form-check-inline mb-3">
                        <input class="form-check-input" type="checkbox" id="edit_show_in_breadcrumb" name="show_in_breadcrumb" value="1">
                        <label class="form-check-label" for="edit_show_in_breadcrumb">Show in Breadcrumb</label>
                    </div>
                    <div class="form-text">Leave Page URL and File Name empty to keep this item as a child group header.</div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" <?php echo !$canEdit ? 'disabled' : ''; ?>>Save Changes</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const crudUrl = './action/sys_menu_crud.php';
    const activeTabStorageKey = 'system_menu_active_tab';
    const addMenuForm = document.getElementById('addMenuForm');
    const addPageForm = document.getElementById('addPageForm');
    const editPageForm = document.getElementById('editPageForm');
    const addMenuModalElement = document.getElementById('exampleModalScrollable3');
    const addPageModalElement = document.getElementById('addPageModal');
    const editPageModalElement = document.getElementById('editPageModal');
    const addMenuModal = addMenuModalElement ? bootstrap.Modal.getOrCreateInstance(addMenuModalElement) : null;
    const addPageModal = addPageModalElement ? bootstrap.Modal.getOrCreateInstance(addPageModalElement) : null;
    const editPageModal = editPageModalElement ? bootstrap.Modal.getOrCreateInstance(editPageModalElement) : null;
    const tabLinks = document.querySelectorAll('.nav-tabs[role="tablist"] .nav-link[data-bs-toggle="tab"]');

    restoreActiveTab();

    tabLinks.forEach(function(tabLink) {
        tabLink.addEventListener('shown.bs.tab', function(event) {
            const targetSelector = event.target.getAttribute('href') || '';
            if (targetSelector) {
                window.sessionStorage.setItem(activeTabStorageKey, targetSelector);
            }
        });
    });

    document.querySelectorAll('.add-menu-row').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            addPageForm.reset();
            document.getElementById('add_page_menu_id').value = this.getAttribute('data-menu-id') || '';
            addPageModal.show();
        });
    });

    if (addMenuForm) {
        addMenuForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'add_menu', function() {
                addMenuModal.hide();
            });
        });
    }

    if (addPageForm) {
        addPageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'add_page', function() {
                addPageModal.hide();
            });
        });
    }

    if (editPageForm) {
        editPageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'update_page', function() {
                editPageModal.hide();
            });
        });
    }

    document.addEventListener('click', function(e) {
        const editButton = e.target.closest('.edit-menu-row');
        if (editButton) {
            e.preventDefault();
            const row = editButton.closest('tr');
            if (!row || !row.dataset.pageId) {
                return;
            }

            document.getElementById('edit_page_id').value = row.dataset.pageId || '';
            document.getElementById('edit_page_menu_id').value = row.dataset.menuId || '';
            document.getElementById('edit_display_name').value = row.dataset.displayName || '';
            document.getElementById('edit_page_url').value = row.dataset.pageUrl || '';
            document.getElementById('edit_page_name').value = row.dataset.pageName || '';
            document.getElementById('edit_page_order').value = row.dataset.pageOrder || '';
            document.getElementById('edit_required_ability').value = row.dataset.requiredAbility || 'view';
            document.getElementById('edit_permission_target_page_id').value = row.dataset.permissionTargetPageId && row.dataset.permissionTargetPageId !== '0' ? row.dataset.permissionTargetPageId : '';
            document.getElementById('edit_show_in_sidebar').checked = (row.dataset.showInSidebar || '1') === '1';
            document.getElementById('edit_show_in_breadcrumb').checked = (row.dataset.showInBreadcrumb || '1') === '1';
            editPageModal.show();
            return;
        }

        const deleteButton = e.target.closest('.delete-menu-row');
        if (deleteButton) {
            e.preventDefault();
            const row = deleteButton.closest('tr');
            if (!row || !row.dataset.pageId) {
                return;
            }

            showConfirm('Are you sure you want to delete this submenu?')
                .then(function(confirmed) {
                    if (!confirmed) {
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'delete_page');
                    formData.append('id', row.dataset.pageId || '');

                    postRequest(formData)
                        .then(function(payload) {
                            showSuccess(payload.message || 'Sub menu deleted successfully.').then(function() {
                                window.location.reload();
                            });
                        })
                        .catch(function(error) {
                            showError(error.message);
                        });
                });
        }
    });

    function submitForm(form, action, afterSuccess) {
        const formData = new FormData(form);
        formData.append('action', action);

        postRequest(formData)
            .then(function(payload) {
                if (typeof afterSuccess === 'function') {
                    afterSuccess();
                }

                persistActiveTab();
                showSuccess(payload.message || 'Request completed successfully.').then(function() {
                    window.location.reload();
                });
            })
            .catch(function(error) {
                showError(error.message);
            });
    }

    function showConfirm(message) {
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire({
                title: 'Confirm',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes',
                cancelButtonText: 'Cancel'
            }).then(function(result) {
                return result.isConfirmed;
            });
        }

        return Promise.resolve(window.confirm(message));
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

    function persistActiveTab() {
        const activeTab = document.querySelector('.nav-tabs[role="tablist"] .nav-link.active[data-bs-toggle="tab"]');
        if (!activeTab) {
            return;
        }

        const targetSelector = activeTab.getAttribute('href') || '';
        if (targetSelector) {
            window.sessionStorage.setItem(activeTabStorageKey, targetSelector);
        }
    }

    function restoreActiveTab() {
        const savedTarget = window.sessionStorage.getItem(activeTabStorageKey);
        if (!savedTarget) {
            return;
        }

        const savedTab = document.querySelector('.nav-tabs[role="tablist"] .nav-link[data-bs-toggle="tab"][href="' + savedTarget + '"]');
        if (!savedTab) {
            window.sessionStorage.removeItem(activeTabStorageKey);
            return;
        }

        bootstrap.Tab.getOrCreateInstance(savedTab).show();
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
});
</script>

<?php
include_once '../../include/h_footer.php';
?>