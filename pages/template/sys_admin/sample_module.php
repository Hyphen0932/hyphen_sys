<?php
include_once '../../../build/config.php';
include_once '../../../build/session.php';

$pageAuth = hyphen_bind_page_auth('sys_admin/sample_module');

$canCreateRecords = hyphen_page_auth('sys_admin/sample_module_new')['add'];
$canEditRecords = hyphen_page_auth('sys_admin/sample_module_edit')['edit'];

$records = [];
$dbError = null;

// Replace this block with the real query for your module.
// Example:
// $result = mysqli_query($conn, 'SELECT id, code, name, status, created_at FROM your_table_name ORDER BY id DESC');
// if ($result === false) {
//     $dbError = 'Unable to load records.';
// } else {
//     while ($row = mysqli_fetch_assoc($result)) {
//         $records[] = $row;
//     }
// }

include_once '../../../include/h_main.php';
include_once '../../../include/h_cstable.php';
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
                <div class="card-title">Sample Module List</div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="SampleModuleTable" class="table table-bordered text-nowrap w-100">
                        <thead>
                            <tr>
                                <th style="width:5%;">S/N</th>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th style="width:5%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($records)): ?>
                                <?php foreach ($records as $index => $record): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars((string) ($record['code'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($record['name'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($record['status'] ?? '')); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
                                            <ul class="dropdown-menu">
                                                <?php if ($canEditRecords): ?>
                                                    <li><a class="dropdown-item" href="sample_module_edit?id=<?php echo (int) ($record['id'] ?? 0); ?>">Edit</a></li>
                                                <?php else: ?>
                                                    <li><span class="dropdown-item disabled">Edit</span></li>
                                                <?php endif; ?>
                                                <li><span class="dropdown-item disabled">Delete</span></li>
                                            </ul>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">Replace the query block in this template to render real rows.</td>
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
                        <?php if ($canCreateRecords): ?>
                            <a class="nav-link active" href="sample_module_new">Add New Record</a>
                        <?php else: ?>
                            <span class="nav-link disabled">Add New Record</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <h6 class="card-title fw-semibold">Sample CRUD Module</h6>
                <p class="card-text">Copy this file into pages/sys_admin and replace the placeholder query and columns for your real module.</p>
                <?php if ($canCreateRecords): ?>
                    <a href="sample_module_new" class="btn btn-primary">Add New Record</a>
                <?php else: ?>
                    <button type="button" class="btn btn-primary" disabled>Add New Record</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<!-- End::content -->
<?php
include_once '../../../include/h_footer.php';
include_once '../../../include/h_jstable.php';
?>
<script>
$(document).ready(function() {
    $('#SampleModuleTable').DataTable({
        language: {
            searchPlaceholder: 'Search...',
            sSearch: '',
        },
        pageLength: 10,
        scrollX: true,
    });
});
</script>