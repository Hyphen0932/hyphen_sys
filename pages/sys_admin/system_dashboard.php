<?php
include_once '../../build/config.php';
include_once '../../build/session.php';

$pageAuth = hyphen_bind_page_auth('sys_admin/system_dashboard');

include_once '../../include/h_main.php';
?>
<!-- Start::content -->
<div class="row">
	<div class="col-12">
		<div class="card custom-card">
			<div class="card-header">
				<div class="card-title">System Dashboard</div>
			</div>
			<div class="card-body">
				<p class="text-muted mb-0">Dashboard content is not implemented yet, but this page now participates in the shared session and authorization flow.</p>
			</div>
		</div>
	</div>
</div>
<!-- End::content -->
<?php
include_once '../../include/h_footer.php';
?>
