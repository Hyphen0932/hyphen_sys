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
                                            <span class="badge <?php echo htmlspecialchars($statusBadge['class']); ?>">
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
            <div class="card-header">
                <ul class="nav nav-pills card-header-pills ms-1">
                    <li class="nav-item">
                        <?php if ($canCreateUsers): ?>
                            <a class="nav-link active" href="system_users_new">Add New User</a>
                        <?php else: ?>
                            <span class="nav-link disabled">Add New User</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
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
        // basic datatable
    });
</script>