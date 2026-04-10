<?php
include_once '../../build/config.php';
include_once '../../build/session.php';

$pageAuth = hyphen_bind_page_auth('sys_admin/system_audit_log');
$auditApiUrl = 'action/sys_audit_log_api.php';

include_once '../../include/h_main.php';
include_once '../../include/h_cstable.php';
?>
<!-- Start::content -->
<div class="row">
	<div class="col-xl-3 col-md-6">
		<div class="card custom-card">
			<div class="card-body">
				<div class="text-muted mb-1">Total Logs</div>
				<div class="fs-3 fw-semibold" id="auditTotalLogs">0</div>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-md-6">
		<div class="card custom-card">
			<div class="card-body">
				<div class="text-muted mb-1">Success</div>
				<div class="fs-3 fw-semibold text-success" id="auditSuccessLogs">0</div>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-md-6">
		<div class="card custom-card">
			<div class="card-body">
				<div class="text-muted mb-1">Failure</div>
				<div class="fs-3 fw-semibold text-danger" id="auditFailureLogs">0</div>
			</div>
		</div>
	</div>
	<div class="col-xl-3 col-md-6">
		<div class="card custom-card">
			<div class="card-body">
				<div class="text-muted mb-1">Avg. Execution</div>
				<div class="fs-3 fw-semibold" id="auditAverageExecution">0 ms</div>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-12">
		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">Audit Filters</div>
			</div>
			<div class="card-body">
				<form id="auditFilterForm" class="row g-3">
					<div class="col-xl-2 col-md-6">
						<label for="auditDateFrom" class="form-label">Date From</label>
						<input type="date" class="form-control" id="auditDateFrom" name="date_from">
					</div>
					<div class="col-xl-2 col-md-6">
						<label for="auditDateTo" class="form-label">Date To</label>
						<input type="date" class="form-control" id="auditDateTo" name="date_to">
					</div>
					<div class="col-xl-2 col-md-6">
						<label for="auditStaffId" class="form-label">Staff ID</label>
						<input type="text" class="form-control" id="auditStaffId" name="staff_id" placeholder="e.g. A12345">
					</div>
					<div class="col-xl-2 col-md-6">
						<label for="auditStatus" class="form-label">Status</label>
						<select class="form-select" id="auditStatus" name="status">
							<option value="">All</option>
							<option value="success">Success</option>
							<option value="failure">Failure</option>
						</select>
					</div>
					<div class="col-xl-2 col-md-6">
						<label for="auditAction" class="form-label">Action</label>
						<input type="text" class="form-control" id="auditAction" name="audit_action" placeholder="create_user">
					</div>
					<div class="col-xl-2 col-md-6">
						<label for="auditLimit" class="form-label">Rows</label>
						<select class="form-select" id="auditLimit" name="limit">
							<option value="100">100</option>
							<option value="250">250</option>
							<option value="500" selected>500</option>
							<option value="1000">1000</option>
						</select>
					</div>
					<div class="col-12">
						<label for="auditEndpoint" class="form-label">Endpoint</label>
						<input type="text" class="form-control" id="auditEndpoint" name="endpoint" placeholder="pages/sys_admin/action/sys_users_crud.php">
					</div>
					<div class="col-12 d-flex gap-2 flex-wrap">
						<button type="submit" class="btn btn-primary">Apply Filters</button>
						<button type="button" class="btn btn-light" id="auditResetBtn">Reset</button>
						<button type="button" class="btn btn-info" id="auditRefreshBtn">Refresh</button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-12">
		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">Audit Log Stream</div>
				<div class="text-muted small" id="auditTableHint">Showing the most recent matching entries.</div>
			</div>
			<div class="card-body">
				<div id="auditAlertHost"></div>
				<div class="table-responsive">
					<table id="auditLogTable" class="table table-bordered text-nowrap w-100">
						<thead>
							<tr>
								<th>ID</th>
								<th>Timestamp</th>
								<th>Staff ID</th>
								<th>Module</th>
								<th>Action</th>
								<th>Method</th>
								<th>Status</th>
								<th>Code</th>
								<th>Exec. ms</th>
								<th>Endpoint</th>
								<th>Details</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="auditDetailModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h6 class="modal-title">Audit Entry Details</h6>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="row g-3 mb-3">
					<div class="col-md-4">
						<div class="border rounded p-3 h-100">
							<div class="text-muted small mb-1">Staff ID</div>
							<div class="fw-semibold" id="auditDetailStaffId">-</div>
						</div>
					</div>
					<div class="col-md-4">
						<div class="border rounded p-3 h-100">
							<div class="text-muted small mb-1">Endpoint</div>
							<div class="fw-semibold text-break" id="auditDetailEndpoint">-</div>
						</div>
					</div>
					<div class="col-md-4">
						<div class="border rounded p-3 h-100">
							<div class="text-muted small mb-1">Error</div>
							<div class="fw-semibold text-break" id="auditDetailError">-</div>
						</div>
					</div>
				</div>
				<div class="row g-3">
					<div class="col-lg-6">
						<label class="form-label">Request Payload</label>
						<pre class="bg-light border rounded p-3 small mb-0" id="auditDetailRequest" style="min-height:260px;"></pre>
					</div>
					<div class="col-lg-6">
						<label class="form-label">Response Payload</label>
						<pre class="bg-light border rounded p-3 small mb-0" id="auditDetailResponse" style="min-height:260px;"></pre>
					</div>
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
		const auditApiUrl = <?php echo json_encode($auditApiUrl); ?>;
		const alertHost = document.getElementById('auditAlertHost');
		const detailModalElement = document.getElementById('auditDetailModal');
		const detailModal = detailModalElement ? bootstrap.Modal.getOrCreateInstance(detailModalElement) : null;

		const filterForm = document.getElementById('auditFilterForm');
		const dateFromInput = document.getElementById('auditDateFrom');
		const dateToInput = document.getElementById('auditDateTo');
		const staffIdInput = document.getElementById('auditStaffId');
		const statusInput = document.getElementById('auditStatus');
		const actionInput = document.getElementById('auditAction');
		const endpointInput = document.getElementById('auditEndpoint');
		const limitInput = document.getElementById('auditLimit');
		const resetBtn = document.getElementById('auditResetBtn');
		const refreshBtn = document.getElementById('auditRefreshBtn');

		const totalLogsNode = document.getElementById('auditTotalLogs');
		const successLogsNode = document.getElementById('auditSuccessLogs');
		const failureLogsNode = document.getElementById('auditFailureLogs');
		const averageExecutionNode = document.getElementById('auditAverageExecution');
		const tableHintNode = document.getElementById('auditTableHint');

		const detailStaffIdNode = document.getElementById('auditDetailStaffId');
		const detailEndpointNode = document.getElementById('auditDetailEndpoint');
		const detailErrorNode = document.getElementById('auditDetailError');
		const detailRequestNode = document.getElementById('auditDetailRequest');
		const detailResponseNode = document.getElementById('auditDetailResponse');

		let latestLogs = [];

		const auditTable = $('#auditLogTable').DataTable({
			language: {
				searchPlaceholder: 'Search...',
				sSearch: '',
			},
			pageLength: 25,
			scrollX: true,
			order: [[0, 'desc']],
			columnDefs: [{
				targets: [10],
				orderable: false,
				searchable: false,
				className: 'text-center align-middle',
			}],
		});

		function formatDate(date) {
			return date.toISOString().slice(0, 10);
		}

		function defaultFilters() {
			const today = new Date();
			const sevenDaysAgo = new Date(today);
			sevenDaysAgo.setDate(today.getDate() - 7);

			return {
				date_from: formatDate(sevenDaysAgo),
				date_to: formatDate(today),
				staff_id: '',
				status: '',
				audit_action: '',
				endpoint: '',
				limit: '500'
			};
		}

		function applyFiltersToForm(filters) {
			dateFromInput.value = filters.date_from || '';
			dateToInput.value = filters.date_to || '';
			staffIdInput.value = filters.staff_id || '';
			statusInput.value = filters.status || '';
			actionInput.value = filters.audit_action || '';
			endpointInput.value = filters.endpoint || '';
			limitInput.value = String(filters.limit || '500');
		}

		function currentFilters() {
			return {
				date_from: dateFromInput.value,
				date_to: dateToInput.value,
				staff_id: staffIdInput.value.trim(),
				status: statusInput.value,
				audit_action: actionInput.value.trim(),
				endpoint: endpointInput.value.trim(),
				limit: limitInput.value
			};
		}

		function setAlert(message, type = 'danger') {
			if (!alertHost) {
				return;
			}

			alertHost.innerHTML = message ? '<div class="alert alert-' + type + '" role="alert">' + $('<div>').text(message).html() + '</div>' : '';
		}

		function statusBadge(status) {
			const normalized = String(status || '').toLowerCase();
			if (normalized === 'success') {
				return '<span class="badge bg-success-transparent text-success">Success</span>';
			}

			return '<span class="badge bg-danger-transparent text-danger">Failure</span>';
		}

		function prettyJson(value) {
			if (!value) {
				return '{}';
			}

			try {
				return JSON.stringify(JSON.parse(value), null, 2);
			} catch (error) {
				return String(value);
			}
		}

		async function callApi(action, filters) {
			const params = new URLSearchParams({
				action,
				...filters,
				_ts: Date.now()
			});
			const response = await fetch(auditApiUrl + '?' + params.toString(), {
				method: 'GET',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				}
			});

			const responseText = await response.text();

			try {
				return JSON.parse(responseText);
			} catch (error) {
				const compactText = String(responseText || '').replace(/\s+/g, ' ').trim();
				const snippet = compactText.slice(0, 220) || 'Empty response body.';
				throw new Error('Server returned a non-JSON response: ' + snippet);
			}
		}

		async function loadSummary(filters) {
			const response = await callApi('get_summary', filters);
			if (!response.success) {
				throw new Error(response.message || 'Unable to load audit summary.');
			}

			const summary = response.data.summary || {};
			totalLogsNode.textContent = summary.total_logs || 0;
			successLogsNode.textContent = summary.success_logs || 0;
			failureLogsNode.textContent = summary.failure_logs || 0;
			averageExecutionNode.textContent = Math.round(summary.average_execution_ms || 0) + ' ms';
		}

		async function loadLogs(filters) {
			const response = await callApi('list_logs', filters);
			if (!response.success) {
				throw new Error(response.message || 'Unable to load audit logs.');
			}

			latestLogs = response.data.logs || [];
			auditTable.clear();

			latestLogs.forEach(function(log) {
				auditTable.row.add([
					log.id,
					$('<div>').text(log.created_at || '').html(),
					$('<div>').text(log.staff_id || '-').html(),
					$('<div>').text(log.page_key || '-').html(),
					$('<div>').text(log.action || '').html(),
					$('<div>').text(log.method || '').html(),
					statusBadge(log.status),
					log.response_code || 0,
					log.execution_time_ms || 0,
					$('<div>').text(log.api_endpoint || '').html(),
					'<button type="button" class="btn btn-sm btn-primary audit-detail-btn" data-id="' + log.id + '">View</button>'
				]);
			});

			auditTable.draw();
			tableHintNode.textContent = 'Showing ' + latestLogs.length + ' matching entries.';
		}

		async function refreshAuditView() {
			const filters = currentFilters();
			setAlert('');

			try {
				await Promise.all([
					loadSummary(filters),
					loadLogs(filters)
				]);
			} catch (error) {
				setAlert(error.message || 'Failed to load audit data.');
				auditTable.clear().draw();
			}
		}

		function openDetail(logId) {
			const selectedLog = latestLogs.find(function(log) {
				return Number(log.id) === Number(logId);
			});

			if (!selectedLog || !detailModal) {
				return;
			}

			detailStaffIdNode.textContent = selectedLog.staff_id || '-';
			detailEndpointNode.textContent = selectedLog.api_endpoint || '-';
			detailErrorNode.textContent = selectedLog.error_message || '-';
			detailRequestNode.textContent = prettyJson(selectedLog.request_data);
			detailResponseNode.textContent = prettyJson(selectedLog.response_data);
			detailModal.show();
		}

		filterForm.addEventListener('submit', function(event) {
			event.preventDefault();
			refreshAuditView();
		});

		resetBtn.addEventListener('click', function() {
			applyFiltersToForm(defaultFilters());
			refreshAuditView();
		});

		refreshBtn.addEventListener('click', function() {
			refreshAuditView();
		});

		$('#auditLogTable').on('click', '.audit-detail-btn', function() {
			openDetail(this.getAttribute('data-id'));
		});

		applyFiltersToForm(defaultFilters());
		refreshAuditView();
	});
</script>