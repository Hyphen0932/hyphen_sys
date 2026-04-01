<?php
include_once '../../include/h_main.php';
?>
<div class="row">
    <div class="col-xxl-9">
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

                            <a class="nav-link active" data-bs-toggle="tab" role="tab"
                                aria-current="page" href="#home-vertical-link" aria-selected="false">
                                <i class="ri-home-office-line side-menu__icon me-2 align-middle d-inline-block"></i>
                                Home
                            </a>
                            <a class="nav-link" data-bs-toggle="tab" role="tab"
                                aria-current="page" href="#setting-vertical-link" aria-selected="false">
                                <i class="ri-user-settings-fill side-menu__icon me-2 align-middle d-inline-block"></i>
                                System Settings</a>

                            <!-- Create new menu item here -->
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#exampleModalScrollable3">
                                <i class="ri-checkbox-multiple-line me-2 align-middle d-inline-block"></i>
                                Add New Menu
                            </a>
                        </nav>
                    </div>
                    <div class="col-xl-9">
                        <div class="tab-content">
                            <div class="tab-pane show active text-muted" id="home-vertical-link"
                                role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table text-nowrap table-sm">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Display Name</th>
                                                <th>Page URL</th>
                                                <th>File Name</th>
                                                <th>Page Order</th>
                                                <th style="text-align: center;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>1</td>
                                                <td>User Dashboard</td>
                                                <td>/pages/home/user_dashboard</td>
                                                <td>user_dashboard</td>
                                                <td>1.1.0</td>
                                                <td style="text-align: center;">
                                                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="javascript:void(0);">Edit</a></li>
                                                        <li><a class="dropdown-item" href="javascript:void(0);">Delete</a></li>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <!-- Add more menu items here -->
                                        </tbody>
                                        <tfoot>
                                            <td colspan="7" style="text-align: center;">
                                                <a href="javascript:void(0);"><i class="ri-checkbox-multiple-line me-2 align-middle d-inline-block"></i>Add New</a>
                                            </td>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane text-muted" id="setting-vertical-link"
                                role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table text-nowrap table-sm">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Display Name</th>
                                                <th>Page URL</th>
                                                <th>File Name</th>
                                                <th>Page Order</th>
                                                <th style="text-align: center;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>1</td>
                                                <td>System Settings</td>
                                                <td></td>
                                                <td></td>
                                                <td>99.2.0</td>
                                                <td style="text-align: center;">
                                                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="javascript:void(0);">Edit</a></li>
                                                        <li><a class="dropdown-item" href="javascript:void(0);">Delete</a></li>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td>System Menu</td>
                                                <td>/pages/sys_admin/system_menu</td>
                                                <td>system_menu</td>
                                                <td>99.2.1</td>
                                                <td style="text-align: center;">
                                                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="javascript:void(0);">Edit</a></li>
                                                        <li><a class="dropdown-item" href="javascript:void(0);">Delete</a></li>
                                                    </ul>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>3</td>
                                                <td>System Users</td>
                                                <td>/pages/sys_admin/system_users</td>
                                                <td>system_users</td>
                                                <td>99.2.2</td>
                                                <td style="text-align: center;">
                                                    <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="javascript:void(0);">Edit</a></li>
                                                        <li><a class="dropdown-item" href="javascript:void(0);">Delete</a></li>
                                                    </ul>
                                                </td>
                                            </tr>

                                        </tbody>
                                        <tfoot>
                                            <td colspan="7" style="text-align: center;">
                                                <a href="javascript:void(0);"><i class="ri-checkbox-multiple-line me-2 align-middle d-inline-block"></i>Add New</a>
                                            </td>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Modal Form for add new sidebar -->
<div class="modal fade" id="exampleModalScrollable3" tabindex="-1" aria-labelledby="exampleModalScrollable3" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalScrollableTitle3">Add New Menu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <input type="text" class="form-control" id="category" name="category" placeholder="Select Category">
                    </div>
                    <div class="mb-3">
                        <label for="link" class="form-label">System Link</label>
                        <input type="text" class="form-control" id="link" name="link" placeholder="Enter system link">
                    </div>
                    <div class="mb-3">
                        <label for="menu_id" class="form-label">Menu ID</label>
                        <input type="text" class="form-control" id="menu_id" name="menu_id" placeholder="Enter menu ID">
                    </div>
                    <div class="mb-3">
                        <label for="menu_name" class="form-label">Menu Name</label>
                        <input type="text" class="form-control" id="menu_name" name="menu_name" placeholder="Enter menu name">
                    </div>
                    <div class="mb-3">
                        <label for="menu_icon" class="form-label">Menu Icon</label>
                        <input type="text" class="form-control" id="menu_icon" name="menu_icon" placeholder="Enter menu icon">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Menu</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include_once '../../include/h_footer.php';
?>