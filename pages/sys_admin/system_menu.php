<?php
include_once '../../include/h_main.php';
?>
<div class="row">
    <div class="col-xxl-9">
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">
                    Vertical alignment with links
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-xl-3">
                        <nav class="nav nav-tabs flex-column nav-style-5" role="tablist">

                            <a class="nav-link active" data-bs-toggle="tab" role="tab"
                                aria-current="page" href="#home-vertical-link" aria-selected="false">
                                <i class="ri-home-smile-line me-2 align-middle d-inline-block"></i>Home
                            </a>
                            <a class="nav-link" data-bs-toggle="tab" role="tab"
                                aria-current="page" href="#about-vertical-link" aria-selected="false">
                                <i class="ri-archive-drawer-line me-2 align-middle d-inline-block"></i>About
                            </a>
                            <a class="nav-link" data-bs-toggle="tab" role="tab"
                                aria-current="page" href="#services-vertical-link" aria-selected="false">
                                <i class="ri-bank-line me-2 align-middle d-inline-block"></i>Services</a>
                            <a class="nav-link" data-bs-toggle="tab" role="tab"
                                aria-current="page" href="#contacts-vertical-link" aria-selected="false">
                                <i class="ri-contacts-book-2-line me-2 align-middle d-inline-block"></i>Contacts
                            </a>
                            <!-- Create new menu item here -->
                            <a class="nav-link" href="#">
                                <i class="ri-checkbox-multiple-line me-2 align-middle d-inline-block"></i>Add New Menu
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
                                                <th>Group Name</th>
                                                <th>Display Name</th>
                                                <th>URL Location</th>
                                                <th>Page Order</th>
                                                <th style="text-align: center;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>1</td>
                                                <td>Home</td>
                                                <td>Home Page</td>
                                                <td>/pages/home/</td>
                                                <td>1</td>
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
                                            <td colspan="5" style="text-align: center;">
                                                <a href="javascript:void(0);"><i class="ri-checkbox-multiple-line me-2 align-middle d-inline-block"></i>Add New</a>
                                            </td>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane text-muted" id="about-vertical-link"
                                role="tabpanel">
                                How travel coupons make you a better lover. Why cultural solutions
                                are the new black. Why mom was right about travel insurances. How
                                family trip ideas can help you predict the future. <b>How carnival
                                    cruises make you a better lover</b>. Why you'll never succeed at
                                daily deals. 11 ways cheapest flights can find you the love of your
                                life. The complete beginner's guide to mission trips.
                            </div>
                            <div class="tab-pane text-muted" id="services-vertical-link"
                                role="tabpanel">
                                Unbelievable healthy snack success stories. 12 facts about safe food
                                handling tips that will impress your friends. Restaurant weeks by
                                the numbers. <b><i>Will mexican food ever rule the world? The 10 best thai
                                        restaurant youtube videos</i></b>. How restaurant weeks can make you sick.
                                The complete beginner's guide to cooking healthy food. Unbelievable
                                food stamp success stories.
                            </div>
                            <div class="tab-pane text-muted" id="contacts-vertical-link"
                                role="tabpanel">
                                Why delicious magazines are killing you. Why our world would end if
                                restaurants disappeared. Why restaurants are on crack about
                                restaurants. How restaurants are making the world a better place. 8
                                great articles about minute meals. Why our world would end if
                                healthy snacks disappeared. Why the world would end without mexican
                                food. The evolution of chef uniforms.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include_once '../../include/h_footer.php';
?>