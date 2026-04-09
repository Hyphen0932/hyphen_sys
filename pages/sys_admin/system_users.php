<?php
include_once '../../build/config.php';
include_once '../../build/session.php';

$pageAuth = hyphen_bind_page_auth('sys_admin/system_users');

$canCreateUsers = hyphen_page_auth('sys_admin/system_users_new')['add'];
$canEditUsers = hyphen_page_auth('sys_admin/system_users_edit')['edit'];
$canDeleteUsers = $canDelete;

$users = [];
$dbError = null;

$userResult = mysqli_query($conn, 'SELECT id, username, staff_id, email, phone, designation, role, status, image_url, created_at FROM hy_users ORDER BY id DESC');
if ($userResult === false) {
    $dbError = 'Unable to load user records from hy_users.';
} else {
    while ($row = mysqli_fetch_assoc($userResult)) {
        $users[] = $row;
    }
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

function system_user_status_badge(string $status): array
{
    $normalized = strtolower(trim($status));
    $isActive = $normalized === 'active';

    return [
        'class' => $isActive ? 'bg-success-transparent text-success' : 'bg-danger-transparent text-danger',
        'label' => $status !== '' ? ucfirst($status) : 'Unknown',
    ];
}

include_once '../../include/h_main.php';
include_once '../../include/h_cstable.php';
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
<div class="row">
    <div class="col-xl-9">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">
                    User List
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="SysUsers" class="table table-bordered text-nowrap w-100">
                        <thead>
                            <tr>
                                <th style="width:5%;">S/N</th>
                                <th style="width:5%;">User Image</th>
                                <th>Email</th>
                                <th>User ID</th>
                                <th>User Name</th>
                                <th>User Role</th>
                                <th>Status</th>
                                <th style="width:5%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)): ?>
                                <?php foreach ($users as $index => $user): ?>
                                    <?php $statusBadge = system_user_status_badge((string) ($user['status'] ?? '')); ?>
                                    <?php $isCurrentUser = (string) ($_SESSION['staff_id'] ?? '') === (string) ($user['staff_id'] ?? ''); ?>
                                    <?php $isUserActive = strtolower(trim((string) ($user['status'] ?? ''))) === 'active'; ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td class="text-center align-middle">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <span class="avatar avatar-lg me-2 online avatar-rounded">
                                                    <img src="<?php echo htmlspecialchars(system_user_image_path($user['image_url'] ?? '')); ?>" alt="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
                                                </span>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars((string) ($user['email'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($user['staff_id'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($user['username'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($user['role'] ?? '')); ?></td>
                                        <td>
                                            <span class="badge user-status-badge <?php echo htmlspecialchars($statusBadge['class']); ?>">
                                                <?php echo htmlspecialchars($statusBadge['label']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
                                            <ul class="dropdown-menu">
                                                <?php if ($canEditUsers): ?>
                                                    <li><a class="dropdown-item" href="system_users_edit?id=<?php echo (int) ($user['id'] ?? 0); ?>">Edit</a></li>
                                                <?php else: ?>
                                                    <li><span class="dropdown-item disabled">Edit</span></li>
                                                <?php endif; ?>
                                                <?php if ($canEditUsers): ?>
                                                    <li>
                                                        <button
                                                            type="button"
                                                            class="dropdown-item send-user-email-notification"
                                                            data-user-id="<?php echo (int) ($user['id'] ?? 0); ?>"
                                                            data-staff-id="<?php echo htmlspecialchars((string) ($user['staff_id'] ?? '')); ?>"
                                                            data-email="<?php echo htmlspecialchars((string) ($user['email'] ?? '')); ?>"
                                                            <?php echo trim((string) ($user['email'] ?? '')) === '' ? 'disabled' : ''; ?>>
                                                            Email Notification
                                                        </button>
                                                    </li>
                                                <?php else: ?>
                                                    <li><span class="dropdown-item disabled">Email Notification</span></li>
                                                <?php endif; ?>
                                                <?php if ($canEditUsers): ?>
                                                    <li>
                                                        <button
                                                            type="button"
                                                            class="dropdown-item toggle-user-status"
                                                            data-user-id="<?php echo (int) ($user['id'] ?? 0); ?>"
                                                            data-staff-id="<?php echo htmlspecialchars((string) ($user['staff_id'] ?? '')); ?>"
                                                            data-current-status="<?php echo htmlspecialchars(strtolower(trim((string) ($user['status'] ?? 'inactive')))); ?>"
                                                            <?php echo $isCurrentUser && $isUserActive ? 'disabled' : ''; ?>>
                                                            <?php echo $isUserActive ? 'Deactivate' : 'Activate'; ?>
                                                        </button>
                                                    </li>
                                                <?php else: ?>
                                                    <li><span class="dropdown-item disabled">Change Status</span></li>
                                                <?php endif; ?>
                                                <li><span class="dropdown-item disabled"><?php echo $canDeleteUsers ? 'Delete (Pending)' : 'Delete'; ?></span></li>
                                            </ul>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No user records found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card text-center custom-card">
            <!-- <div class="card-header">
                <ul class="nav nav-pills card-header-pills ms-1">
                    <li class="nav-item">
                        <?php if ($canCreateUsers): ?>
                            <a class="nav-link active" href="system_users_new">Add New User</a>
                        <?php else: ?>
                            <span class="nav-link disabled">Add New User</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div> -->
            <div class="card-body">
                <h6 class="card-title fw-semibold">Create New User</h6>
                <p class="card-text">Use the existing user form to create a new account and assign page permissions.</p>
                <?php if ($canCreateUsers): ?>
                    <a href="system_users_new" class="btn btn-primary">Add New User</a>
                <?php else: ?>
                    <button type="button" class="btn btn-primary" disabled>Add New User</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!-- End::content -->
<?php
include_once '../../include/h_footer.php';
include_once '../../include/h_jstable.php';
?>
<script>
    $(document).ready(function() {
        const userEmailNotificationUrl = 'action/sys_users_email_noti.php';
        const userCrudUrl = 'action/sys_users_crud.php';

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

        function setEmailNotificationButtonLoading(button, loading) {
            if (!button) {
                return;
            }

            if (!button.dataset.defaultHtml) {
                button.dataset.defaultHtml = button.innerHTML;
            }

            if (loading) {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm align-middle me-2" role="status" aria-hidden="true"></span><span>Sending...</span>';
                return;
            }

            button.disabled = button.dataset.hasEmail !== 'true';
            button.innerHTML = button.dataset.defaultHtml;
        }

        function statusBadgeMarkup(status) {
            const normalized = String(status || '').trim().toLowerCase();
            const isActive = normalized === 'active';
            const label = normalized !== '' ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : 'Unknown';
            const className = isActive ? 'bg-success-transparent text-success' : 'bg-danger-transparent text-danger';
            return '<span class="badge user-status-badge ' + className + '">' + label + '</span>';
        }

        function setStatusButtonLoading(button, loading) {
            if (!button) {
                return;
            }

            if (!button.dataset.defaultHtml) {
                button.dataset.defaultHtml = button.innerHTML;
            }

            if (loading) {
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm align-middle me-2" role="status" aria-hidden="true"></span><span>Updating...</span>';
                return;
            }

            button.disabled = button.dataset.selfProtected === 'true' && button.dataset.currentStatus === 'active';
            button.innerHTML = button.dataset.defaultHtml;
        }

        // basic datatable
        $('#SysUsers').DataTable({
            language: {
                searchPlaceholder: 'Search...',
                sSearch: '',
            },
            "pageLength": 10,
            scrollX: true,
            columnDefs: [{
                targets: [1],
                className: 'text-center align-middle',
            }],
        });

        $('#SysUsers tbody').on('click', '.send-user-email-notification', async function() {
            const button = this;
            const staffId = button.dataset.staffId || '';
            const email = button.dataset.email || '';
            const userId = button.dataset.userId || '';
            const confirmed = await showConfirm('Email to ' + staffId + ' <' + email + '> using template NF-00001?');

            if (!confirmed) {
                return;
            }

            setEmailNotificationButtonLoading(button, true);

            try {
                const response = await fetch(userEmailNotificationUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    credentials: 'same-origin',
                    body: new URLSearchParams({
                        action: 'send_new_user_login_email',
                        user_id: userId
                    }).toString()
                });

                const result = await response.json();
                if (!result.success) {
                    await showError(result.message || 'Unable to send login email.');
                    return;
                }

                await showSuccess(result.message || 'Login email sent successfully.');
            } catch (error) {
                await showError('Unable to send login email.');
            } finally {
                setEmailNotificationButtonLoading(button, false);
            }
        });

        $('.send-user-email-notification').each(function() {
            this.dataset.hasEmail = ((this.dataset.email || '').trim() !== '').toString();
        });

        $('.toggle-user-status').each(function() {
            const row = this.closest('tr');
            this.dataset.selfProtected = (this.disabled).toString();
            this.dataset.rowIndex = row ? row.rowIndex : '';
        });

        $('#SysUsers tbody').on('click', '.toggle-user-status', async function() {
            const button = this;
            const currentStatus = (button.dataset.currentStatus || '').trim().toLowerCase();
            const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const staffId = button.dataset.staffId || '';
            const userId = button.dataset.userId || '';
            const confirmed = await showConfirm('Change user ' + staffId + ' status to ' + nextStatus + '?');

            if (!confirmed) {
                return;
            }

            setStatusButtonLoading(button, true);

            try {
                const response = await fetch(userCrudUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    credentials: 'same-origin',
                    body: new URLSearchParams({
                        action: 'toggle_user_status',
                        user_id: userId
                    }).toString()
                });

                const result = await response.json();
                if (!result.success) {
                    await showError(result.message || 'Unable to update user status.');
                    return;
                }

                const newStatus = (((result.data || {}).status) || nextStatus).toLowerCase();
                button.dataset.currentStatus = newStatus;
                button.dataset.defaultHtml = newStatus === 'active' ? 'Deactivate' : 'Activate';
                const row = button.closest('tr');
                const badge = row ? row.querySelector('.user-status-badge') : null;
                if (badge) {
                    badge.outerHTML = statusBadgeMarkup(newStatus);
                }

                await showSuccess(result.message || 'User status updated successfully.');
            } catch (error) {
                await showError('Unable to update user status.');
            } finally {
                setStatusButtonLoading(button, false);
            }
        });
    });
</script>