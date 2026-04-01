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
                    Menu List
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="datatable-basic" class="table table-bordered text-nowrap w-100">
                        <thead>
                            <tr>
                                <th style="width:5%;">S/N</th>
                                <th style="width:5%;">Menu Icon</th>
                                <th>Menu Name</th>
                                <th>Menu Order</th>
                                <th style="width:5%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 0 24 24" width="32" height="32">
                                        <path d="M0 0h24v24H0V0z" fill="none" />
                                        <path d="M5 5h4v6H5zm10 8h4v6h-4zM5 17h4v2H5zM15 5h4v2h-4z" opacity=".3" />
                                        <path d="M3 13h8V3H3v10zm2-8h4v6H5V5zm8 16h8V11h-8v10zm2-8h4v6h-4v-6zM13 3v6h8V3h-8zm6 4h-4V5h4v2zM3 21h8v-6H3v6zm2-4h4v2H5v-2z" />
                                    </svg>
                                </td>
                                <td>Index</td>
                                <td>61</td>
                                <td>
                                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="javascript:void(0);">Edit</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);">Delete</a></li>
                                    </ul>
                                </td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="side-menu__icon" viewBox="0 -960 960 960" width="32" height="32">
                                        <path d="M722.5-297.5Q740-315 740-340t-17.5-42.5Q705-400 680-400t-42.5 17.5Q620-365 620-340t17.5 42.5Q655-280 680-280t42.5-17.5ZM680-160q31.38 0 57.19-14.31t42.19-38.31Q757-226 732-233q-25-7-52-7t-52 7q-25 7-47.38 20.38 16.38 24 42.19 38.31Q648.62-160 680-160Zm-200 59.23q-129.77-35.39-214.88-152.77Q180-370.92 180-516v-230.15l300-112.31 300 112.31v226.61q-14-5.69-29.39-10.27-15.38-4.57-30.61-7.19v-167.38L480-794l-240 89.62V-516q0 53.15 15 103.81 15 50.65 41.35 94.69 26.34 44.04 62.96 79.08 36.61 35.04 79.46 55.96l1.16-.39q7.92 22 20.53 42.16 12.62 20.15 28.69 36.61-2.53.77-4.57 1.66-2.04.88-4.58 1.65Zm200 .77q-74.92 0-127.46-52.54Q500-205.08 500-280q0-74.92 52.54-127.46Q605.08-460 680-460q74.92 0 127.46 52.54Q860-354.92 860-280q0 74.92-52.54 127.46Q754.92-100 680-100ZM480-488.23Z" />
                                    </svg>
                                </td>
                                <td>System Admin</td>
                                <td>63</td>
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
                        <a class="nav-link active" href="javascript:void(0);">Add New Page</a>
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
        $('#datatable-basic').DataTable({
            language: {
                searchPlaceholder: 'Search...',
                sSearch: '',
            },
            "pageLength": 10,
            // scrollX: true
        });
        // basic datatable



    });
</script>