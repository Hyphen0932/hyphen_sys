<?php
include_once '../../../build/config.php';
include_once '../../../build/session.php';

$pageAuth = hyphen_bind_page_auth('sys_admin/sample_module_edit');

$recordId = (int) ($_GET['id'] ?? 0);
$record = [
    'id' => $recordId,
    'record_code' => '',
    'record_name' => '',
    'status' => 'active',
];
$dbError = null;

if ($recordId <= 0) {
    $dbError = 'Invalid record ID.';
}

// Replace this block with the real lookup query for your module.

include_once '../../../include/h_main.php';
?>
<!-- Start::content -->
<?php if ($dbError !== null): ?>
<div class="row">
    <div class="col-12">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error:</strong> <?php echo htmlspecialchars($dbError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-12">
        <a href="sample_module" class="btn btn-primary">Back to List</a>
    </div>
</div>
<?php else: ?>
<form id="editSampleModuleForm">
    <input type="hidden" name="record_id" value="<?php echo (int) ($record['id'] ?? 0); ?>">
    <div class="row">
        <div class="col-xl-8">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">Edit Sample Record</div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="recordCode" class="form-label">Code</label>
                        <input type="text" class="form-control" id="recordCode" name="record_code" value="<?php echo htmlspecialchars((string) ($record['record_code'] ?? '')); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="recordName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="recordName" name="record_name" value="<?php echo htmlspecialchars((string) ($record['record_name'] ?? '')); ?>" required>
                    </div>
                    <div class="mb-0">
                        <label for="recordStatus" class="form-label">Status</label>
                        <select class="form-control" id="recordStatus" name="status" required>
                            <option value="active" <?php echo (($record['status'] ?? '') === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo (($record['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mb-4">
                <a href="sample_module" class="btn btn-light">Back</a>
                <button type="reset" class="btn btn-outline-secondary">Reset</button>
                <button type="submit" class="btn btn-primary">Update Record</button>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>
<!-- End::content -->
<?php
include_once '../../../include/h_footer.php';
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editSampleModuleForm');
    const crudUrl = './action/sample_module_crud.php';

    if (!form) {
        return;
    }

    form.addEventListener('submit', function(event) {
        event.preventDefault();

        const formData = new FormData(form);
        formData.append('action', 'update_record');

        postRequest(formData)
            .then(function(payload) {
                showSuccess(payload.message || 'Record updated successfully.').then(function() {
                    window.location.href = 'sample_module';
                });
            })
            .catch(function(error) {
                showError(error.message);
            });
    });

    function postRequest(formData) {
        return fetch(crudUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function(response) {
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