<?php
include_once '../../include/h_main.php';
include_once '../../include/h_cstable.php';
?>
<!-- Start::content -->
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
                                <th>User ID</th>
                                <th>User Name</th>
                                <th>User Role</th>
                                <th style="width:5%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td class="text-center align-middle">
                                    <div class="d-flex align-items-center justify-content-center">
                                        <span class="avatar avatar-lg me-2 online avatar-rounded">
                                            <img src="../../assets/user_image/00001.jpg" alt="">
                                        </span>
                                    </div>
                                </td>
                                <td>00001</td>
                                <td>Steve Ding</td>
                                <td>System Admin</td>
                                <td>
                                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="javascript:void(0);">Edit</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">Delete</a></li>
                                    </ul>
                                </td>
                            </tr>
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
                        <a class="nav-link active" href="javascript:void(0);">Add New User</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <h6 class="card-title fw-semibold">Special title treatment</h6>
                <p class="card-text">With supporting text below as a natural lead-in to
                    additional content.</p>
                <a href="javascript:void(0);" class="btn btn-primary">Save</a>
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
            // scrollX: true
            columnDefs: [{
                targets: [1],
                className: 'text-center align-middle',
            }],
        });
        // basic datatable
    });
</script>