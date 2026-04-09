<?php
include_once '../../build/config.php';
include_once '../../build/session.php';
include_once '../../build/audit.php';

$pageAuth = hyphen_bind_page_auth('sys_admin/system_audit_log');
$auditLogs = [];
$dbError = null;
$tableReady = hyphen_audit_table_exists($conn);

if ($tableReady) {
	$result = mysqli_query(
		$conn,
		'SELECT id, staff_id, actor_name, module, action_name, entity_type, entity_id, target_label, page_url, request_method, status, old_values_json, new_values_json, metadata_json, ip_address, user_agent, created_at
		 FROM hy_audit_logs
		 ORDER BY id DESC
		 LIMIT 500'
	);

	if ($result === false) {
		$dbError = 'Unable to load audit logs.';
	} else {
		while ($row = mysqli_fetch_assoc($result)) {
			$row['old_values'] = json_decode((string) ($row['old_values_json'] ?? ''), true);
			$row['new_values'] = json_decode((string) ($row['new_values_json'] ?? ''), true);
			$row['metadata'] = json_decode((string) ($row['metadata_json'] ?? ''), true);
			$auditLogs[] = $row;
		}
	}
}

function audit_status_badge(string $status): array
{
	$status = strtolower(trim($status));

	switch ($status) {
		case 'failed':
			return ['class' => 'bg-danger-transparent text-danger', 'label' => 'Failed'];
		case 'denied':
			return ['class' => 'bg-warning-transparent text-warning', 'label' => 'Denied'];
		default:
			return ['class' => 'bg-success-transparent text-success', 'label' => ucfirst($status !== '' ? $status : 'success')];
	}
}

function audit_summary_label(array $log): string
{
	$parts = [];

	if (!empty($log['target_label'])) {
		$parts[] = (string) $log['target_label'];
	}

	if (!empty($log['entity_type'])) {
		$parts[] = (string) $log['entity_type'];
	}

	if (!empty($log['entity_id'])) {
		$parts[] = '#' . (string) $log['entity_id'];
	}

	return $parts !== [] ? implode(' ', $parts) : '-';
}

include_once '../../include/h_main.php';
include_once '../../include/h_cstable.php';
?>
<!-- Start::content -->
<?php if (!$tableReady): ?>
<div class="row">
	<div class="col-12">
		<div class="alert alert-warning">
			Audit log table is not available yet. Run database migrations first.
		</div>
	</div>
</div>
<?php elseif ($dbError !== null): ?>
<div class="row">
	<div class="col-12">
		<div class="alert alert-danger">
			<?php echo htmlspecialchars($dbError); ?>
		</div>
	</div>
</div>
<?php endif; ?>
<div class="row">
	<div class="col-12">
		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">System Audit Log</div>
				<div class="text-muted small">Showing latest 500 records</div>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table id="AuditLogTable" class="table table-bordered text-nowrap w-100 align-middle">
						<thead>
							<tr>
								<th style="width:5%;">S/N</th>
								<th>Time</th>
								<th>Actor</th>
								<th>Module</th>
								<th>Action</th>
								<th>Target</th>
								<th>Status</th>
								<th>Page URL</th>
								<th style="width:10%;">Details</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($auditLogs as $index => $log): ?>
								<?php $statusBadge = audit_status_badge((string) ($log['status'] ?? 'success')); ?>
								<tr>
									<td><?php echo $index + 1; ?></td>
									<td><?php echo htmlspecialchars((string) ($log['created_at'] ?? '')); ?></td>
									<td><?php echo htmlspecialchars(trim((string) (($log['actor_name'] ?? '') !== '' ? $log['actor_name'] : ($log['staff_id'] ?? 'Unknown')))); ?></td>
									<td><?php echo htmlspecialchars((string) ($log['module'] ?? '')); ?></td>
									<td><?php echo htmlspecialchars((string) ($log['action_name'] ?? '')); ?></td>
									<td><?php echo htmlspecialchars(audit_summary_label($log)); ?></td>
									<td><span class="badge <?php echo htmlspecialchars($statusBadge['class']); ?>"><?php echo htmlspecialchars($statusBadge['label']); ?></span></td>
									<td><?php echo htmlspecialchars((string) ($log['page_url'] ?? '')); ?></td>
									<td>
										<button type="button" class="btn btn-sm btn-primary audit-details-btn" data-log-id="<?php echo (int) ($log['id'] ?? 0); ?>">View</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="auditDetailModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title">Audit Log Details</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="row g-3 mb-3">
					<div class="col-md-6"><div class="fw-semibold">Actor</div><div id="auditDetailActor" class="text-muted small"></div></div>
					<div class="col-md-6"><div class="fw-semibold">Time</div><div id="auditDetailTime" class="text-muted small"></div></div>
					<div class="col-md-4"><div class="fw-semibold">Module</div><div id="auditDetailModule" class="text-muted small"></div></div>
					<div class="col-md-4"><div class="fw-semibold">Action</div><div id="auditDetailAction" class="text-muted small"></div></div>
					<div class="col-md-4"><div class="fw-semibold">Status</div><div id="auditDetailStatus" class="text-muted small"></div></div>
					<div class="col-md-6"><div class="fw-semibold">Target</div><div id="auditDetailTarget" class="text-muted small"></div></div>
					<div class="col-md-6"><div class="fw-semibold">Page URL</div><div id="auditDetailPageUrl" class="text-muted small"></div></div>
					<div class="col-md-6"><div class="fw-semibold">IP Address</div><div id="auditDetailIp" class="text-muted small"></div></div>
					<div class="col-md-6"><div class="fw-semibold">Request Method</div><div id="auditDetailMethod" class="text-muted small"></div></div>
				</div>
				<div class="mb-3">
					<div class="fw-semibold mb-2">Old Values</div>
					<pre id="auditDetailOld" class="small bg-light border rounded p-3 mb-0" style="white-space: pre-wrap;"></pre>
				</div>
				<div class="mb-3">
					<div class="fw-semibold mb-2">New Values</div>
					<pre id="auditDetailNew" class="small bg-light border rounded p-3 mb-0" style="white-space: pre-wrap;"></pre>
				</div>
				<div>
					<div class="fw-semibold mb-2">Metadata</div>
					<pre id="auditDetailMeta" class="small bg-light border rounded p-3 mb-0" style="white-space: pre-wrap;"></pre>
				</div>
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
		const auditLogs = <?php echo json_encode($auditLogs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		const detailMap = Object.fromEntries(auditLogs.map(function(log) {
			return [String(log.id), log];
		}));
		const detailModalElement = document.getElementById('auditDetailModal');
		const detailModal = detailModalElement ? bootstrap.Modal.getOrCreateInstance(detailModalElement) : null;

		$('#AuditLogTable').DataTable({
			language: {
				searchPlaceholder: 'Search...',
				sSearch: ''
			},
			pageLength: 25,
			scrollX: true
		});

		function prettyJson(value) {
			if (!value || (typeof value === 'object' && Object.keys(value).length === 0)) {
				return 'None';
			}

			try {
				return JSON.stringify(value, null, 2);
			} catch (error) {
				return String(value);
			}
		}

		document.addEventListener('click', function(event) {
			const button = event.target.closest('.audit-details-btn');
			if (!button) {
				return;
			}

			const log = detailMap[String(button.dataset.logId || '')];
			if (!log || !detailModal) {
				return;
			}

			document.getElementById('auditDetailActor').textContent = log.actor_name || log.staff_id || 'Unknown';
			document.getElementById('auditDetailTime').textContent = log.created_at || '';
			document.getElementById('auditDetailModule').textContent = log.module || '';
			document.getElementById('auditDetailAction').textContent = log.action_name || '';
			document.getElementById('auditDetailStatus').textContent = log.status || '';
			document.getElementById('auditDetailTarget').textContent = (log.target_label || '') + (log.entity_id ? ' #' + log.entity_id : '');
			document.getElementById('auditDetailPageUrl').textContent = log.page_url || '';
			document.getElementById('auditDetailIp').textContent = log.ip_address || '';
			document.getElementById('auditDetailMethod').textContent = log.request_method || '';
			document.getElementById('auditDetailOld').textContent = prettyJson(log.old_values);
			document.getElementById('auditDetailNew').textContent = prettyJson(log.new_values);
			document.getElementById('auditDetailMeta').textContent = prettyJson(log.metadata);
			detailModal.show();
		});
	});
</script>