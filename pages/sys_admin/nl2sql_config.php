<?php
include_once '../../build/config.php';
include_once '../../build/session.php';
include_once '../../build/nl2sql_config.php';

$pageAuth = hyphen_bind_page_auth('sys_admin/nl2sql_config');
$canEditNl2Sql = hyphen_page_auth('sys_admin/nl2sql_config')['edit'];
$nl2sqlApiUrl = './action/nl2sql_config_api.php';

include_once '../../include/h_main.php';
?>
<!-- Start::content -->
<div class="row">
	<div class="col-xl-4">
		<div class="card custom-card overflow-hidden">
			<div class="card-header justify-content-between">
				<div class="card-title">NL2SQL Runtime Status</div>
				<span class="badge bg-info-transparent text-info" id="nl2sqlConfigSource">Loading</span>
			</div>
			<div class="card-body">
				<div class="mb-3">
					<div class="text-muted small mb-1">Current Model</div>
					<div class="fw-semibold" id="nl2sqlModelSummary">-</div>
				</div>
				<div class="mb-3">
					<div class="text-muted small mb-1">Row Limit</div>
					<div class="fw-semibold" id="nl2sqlRowLimitSummary">-</div>
				</div>
				<div class="mb-3">
					<div class="text-muted small mb-1">Allowed Tables</div>
					<div class="fw-semibold" id="nl2sqlAllowedTableSummary">-</div>
				</div>
				<div class="alert alert-info mb-0" id="nl2sqlStatusMessage">Loading current configuration...</div>
			</div>
		</div>

		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">How It Works</div>
			</div>
			<div class="card-body">
				<p class="mb-2">This page controls the NL2SQL runtime used by the Advance Search screen.</p>
				<ul class="mb-0 ps-3">
					<li>Global settings define the active model and the default allowed tables.</li>
					<li>User policies can restrict or disable access per staff account.</li>
					<li>The runtime proxy enforces these settings before each AI query.</li>
				</ul>
			</div>
		</div>
	</div>

	<div class="col-xl-8">
		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">Global NL2SQL Configuration</div>
			</div>
			<div class="card-body">
				<form id="nl2sqlConfigForm" class="row g-3" novalidate>
					<div class="col-md-4">
						<label for="nl2sqlProvider" class="form-label">Provider</label>
						<input type="text" id="nl2sqlProvider" class="form-control" value="ollama" disabled>
					</div>
					<div class="col-md-8">
						<label for="nl2sqlModelName" class="form-label">Model Name</label>
						<input type="text" id="nl2sqlModelName" name="model_name" class="form-control" placeholder="qwen2.5-coder:7b" <?php echo $canEditNl2Sql ? '' : 'disabled'; ?> required>
					</div>
					<div class="col-md-4">
						<label for="nl2sqlRowLimit" class="form-label">Default Row Limit</label>
						<input type="number" id="nl2sqlRowLimit" name="result_row_limit" class="form-control" min="1" max="500" <?php echo $canEditNl2Sql ? '' : 'disabled'; ?> required>
					</div>
					<div class="col-md-4 d-flex align-items-end">
						<div class="form-check form-switch mb-2">
							<input class="form-check-input" type="checkbox" role="switch" id="nl2sqlEnabled" <?php echo $canEditNl2Sql ? '' : 'disabled'; ?>>
							<label class="form-check-label" for="nl2sqlEnabled">Enable NL2SQL globally</label>
						</div>
					</div>
					<div class="col-md-4 d-flex align-items-end justify-content-md-end">
						<button type="submit" class="btn btn-primary" id="saveNl2sqlConfigBtn" <?php echo $canEditNl2Sql ? '' : 'disabled'; ?>>Save Configuration</button>
					</div>
					<div class="col-12">
						<label class="form-label">Allowed Tables</label>
						<div class="row g-2" id="nl2sqlAllowedTablesHost"></div>
						<div class="form-text">Only the selected tables can be queried by default. User policies can further restrict this list.</div>
					</div>
					<div class="col-12">
						<label for="nl2sqlPromptNotes" class="form-label">Prompt Notes</label>
						<textarea id="nl2sqlPromptNotes" name="prompt_notes" class="form-control" rows="4" placeholder="Optional extra guidance for SQL generation and answer tone." <?php echo $canEditNl2Sql ? '' : 'disabled'; ?>></textarea>
					</div>
				</form>
			</div>
		</div>

		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">User Access Policies</div>
				<button type="button" class="btn btn-outline-primary btn-sm" id="refreshNl2sqlUsersBtn">Refresh</button>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-bordered align-middle mb-0">
						<thead>
							<tr>
								<th>User</th>
								<th>Staff ID</th>
								<th>Role</th>
								<th>Access</th>
								<th>Allowed Tables</th>
								<th>Row Limit</th>
								<th>SQL</th>
								<th>Rows</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody id="nl2sqlUserPolicyBody">
							<tr><td colspan="9" class="text-center text-muted">Loading user policies...</td></tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="nl2sqlUserPolicyModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h6 class="modal-title">Edit User NL2SQL Policy</h6>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<form id="nl2sqlUserPolicyForm" novalidate>
				<div class="modal-body">
					<input type="hidden" id="policyStaffId" name="staff_id">
					<div class="row g-3">
						<div class="col-md-6">
							<label class="form-label">User</label>
							<input type="text" id="policyUserLabel" class="form-control" disabled>
						</div>
						<div class="col-md-6 d-flex align-items-end">
							<div class="form-check form-switch mb-2">
								<input class="form-check-input" type="checkbox" role="switch" id="policyEnabled" <?php echo $canEditNl2Sql ? '' : 'disabled'; ?>>
								<label class="form-check-label" for="policyEnabled">Enable NL2SQL for this user</label>
							</div>
						</div>
						<div class="col-md-4">
							<label for="policyMaxRowLimit" class="form-label">Max Row Limit</label>
							<input type="number" id="policyMaxRowLimit" class="form-control" min="1" max="500" placeholder="Use global default" <?php echo $canEditNl2Sql ? '' : 'disabled'; ?>>
						</div>
						<div class="col-md-4 d-flex align-items-end">
							<div class="form-check form-switch mb-2">
								<input class="form-check-input" type="checkbox" role="switch" id="policyCanViewSql" <?php echo $canEditNl2Sql ? '' : 'disabled'; ?>>
								<label class="form-check-label" for="policyCanViewSql">Allow generated SQL view</label>
							</div>
						</div>
						<div class="col-md-4 d-flex align-items-end">
							<div class="form-check form-switch mb-2">
								<input class="form-check-input" type="checkbox" role="switch" id="policyCanIncludeRows" <?php echo $canEditNl2Sql ? '' : 'disabled'; ?>>
								<label class="form-check-label" for="policyCanIncludeRows">Allow raw rows</label>
							</div>
						</div>
						<div class="col-12">
							<label class="form-label">Allowed Tables Override</label>
							<div class="row g-2" id="policyAllowedTablesHost"></div>
							<div class="form-text">Leave all tables unchecked to inherit the full global list.</div>
						</div>
						<div class="col-12">
							<label for="policyNotes" class="form-label">Notes</label>
							<textarea id="policyNotes" class="form-control" rows="3" <?php echo $canEditNl2Sql ? '' : 'disabled'; ?>></textarea>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" <?php echo $canEditNl2Sql ? '' : 'disabled'; ?>>Save Policy</button>
				</div>
			</form>
		</div>
	</div>
</div>
<!-- End::content -->
<?php
include_once '../../include/h_footer.php';
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
	const apiUrl = <?php echo json_encode($nl2sqlApiUrl); ?>;
	const canEdit = <?php echo $canEditNl2Sql ? 'true' : 'false'; ?>;
	const configForm = document.getElementById('nl2sqlConfigForm');
	const providerInput = document.getElementById('nl2sqlProvider');
	const modelInput = document.getElementById('nl2sqlModelName');
	const rowLimitInput = document.getElementById('nl2sqlRowLimit');
	const enabledInput = document.getElementById('nl2sqlEnabled');
	const promptNotesInput = document.getElementById('nl2sqlPromptNotes');
	const allowedTablesHost = document.getElementById('nl2sqlAllowedTablesHost');
	const configSourceNode = document.getElementById('nl2sqlConfigSource');
	const modelSummaryNode = document.getElementById('nl2sqlModelSummary');
	const rowLimitSummaryNode = document.getElementById('nl2sqlRowLimitSummary');
	const allowedTableSummaryNode = document.getElementById('nl2sqlAllowedTableSummary');
	const statusMessageNode = document.getElementById('nl2sqlStatusMessage');
	const userBody = document.getElementById('nl2sqlUserPolicyBody');
	const refreshUsersBtn = document.getElementById('refreshNl2sqlUsersBtn');
	const policyModalElement = document.getElementById('nl2sqlUserPolicyModal');
	const policyModal = policyModalElement ? bootstrap.Modal.getOrCreateInstance(policyModalElement) : null;
	const policyForm = document.getElementById('nl2sqlUserPolicyForm');
	const policyStaffIdInput = document.getElementById('policyStaffId');
	const policyUserLabelInput = document.getElementById('policyUserLabel');
	const policyEnabledInput = document.getElementById('policyEnabled');
	const policyMaxRowLimitInput = document.getElementById('policyMaxRowLimit');
	const policyCanViewSqlInput = document.getElementById('policyCanViewSql');
	const policyCanIncludeRowsInput = document.getElementById('policyCanIncludeRows');
	const policyNotesInput = document.getElementById('policyNotes');
	const policyAllowedTablesHost = document.getElementById('policyAllowedTablesHost');

	let availableTables = [];

	function apiRequest(action, payload, method) {
		const requestMethod = method || 'POST';
		const options = {
			method: requestMethod,
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
				'X-Requested-With': 'XMLHttpRequest'
			}
		};

		if (requestMethod === 'GET') {
			const params = new URLSearchParams(Object.assign({ action: action }, payload || {}));
			return fetch(apiUrl + '?' + params.toString(), options).then(function(response) { return response.json(); });
		}

		options.headers['Content-Type'] = 'application/json';
		options.body = JSON.stringify(Object.assign({ action: action }, payload || {}));
		return fetch(apiUrl, options).then(function(response) { return response.json(); });
	}

	function showSuccess(message) {
		if (window.Swal && typeof window.Swal.fire === 'function') {
			return window.Swal.fire({ title: 'Success', text: message, icon: 'success', confirmButtonText: 'OK' });
		}
		window.alert(message);
		return Promise.resolve();
	}

	function showError(message) {
		if (window.Swal && typeof window.Swal.fire === 'function') {
			return window.Swal.fire({ title: 'Error', text: message, icon: 'error', confirmButtonText: 'OK' });
		}
		window.alert(message);
		return Promise.resolve();
	}

	function badge(enabled, yesLabel, noLabel) {
		return enabled
			? '<span class="badge bg-success-transparent text-success">' + yesLabel + '</span>'
			: '<span class="badge bg-danger-transparent text-danger">' + noLabel + '</span>';
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function renderTableCheckboxes(host, selectedTables, inputName, disabled) {
		host.innerHTML = availableTables.map(function(tableName) {
			const checked = selectedTables.indexOf(tableName) !== -1 ? 'checked' : '';
			const inputId = inputName + '_' + tableName;
			return '<div class="col-md-4 col-sm-6">'
				+ '<div class="form-check border rounded px-3 py-2 h-100">'
				+ '<input class="form-check-input" type="checkbox" id="' + inputId + '" value="' + escapeHtml(tableName) + '" data-table-checkbox="' + inputName + '" ' + checked + ' ' + (disabled ? 'disabled' : '') + '>'
				+ '<label class="form-check-label small text-break" for="' + inputId + '">' + escapeHtml(tableName) + '</label>'
				+ '</div>'
				+ '</div>';
		}).join('');
	}

	function selectedTables(host, inputName) {
		return Array.prototype.slice.call(host.querySelectorAll('[data-table-checkbox="' + inputName + '"]:checked')).map(function(node) {
			return node.value;
		});
	}

	function renderGlobalConfiguration(configuration) {
		providerInput.value = configuration.provider || 'ollama';
		modelInput.value = configuration.model_name || '';
		rowLimitInput.value = configuration.result_row_limit || 50;
		enabledInput.checked = !!Number(configuration.is_enabled || 0);
		promptNotesInput.value = configuration.prompt_notes || '';
		renderTableCheckboxes(allowedTablesHost, configuration.allowed_tables || [], 'global', !canEdit);

		configSourceNode.textContent = configuration.source || 'database';
		modelSummaryNode.textContent = configuration.model_name || '-';
		rowLimitSummaryNode.textContent = String(configuration.result_row_limit || 0);
		allowedTableSummaryNode.textContent = (configuration.allowed_tables || []).length + ' table(s)';
		statusMessageNode.className = 'alert mb-0 ' + (Number(configuration.is_enabled || 0) === 1 ? 'alert-info' : 'alert-warning');
		statusMessageNode.textContent = Number(configuration.is_enabled || 0) === 1
			? 'NL2SQL is enabled and the configuration will be applied on every AI query.'
			: 'NL2SQL is globally disabled. All user queries will be blocked until it is enabled again.';
	}

	function renderUserPolicies(users) {
		if (!users.length) {
			userBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No users found.</td></tr>';
			return;
		}

		userBody.innerHTML = users.map(function(user) {
			const tables = (user.allowed_tables || []).length ? user.allowed_tables.join(', ') : 'Global default';
			return '<tr>'
				+ '<td>' + escapeHtml(user.username || '-') + '<div class="small text-muted">' + escapeHtml(user.email || '') + '</div></td>'
				+ '<td>' + escapeHtml(user.staff_id || '') + '</td>'
				+ '<td>' + escapeHtml(user.role || '') + '</td>'
				+ '<td>' + badge(Number(user.is_enabled || 0) === 1, 'Enabled', 'Disabled') + '</td>'
				+ '<td class="small text-break">' + escapeHtml(tables) + '</td>'
				+ '<td>' + escapeHtml(user.max_row_limit || 'Global') + '</td>'
				+ '<td>' + badge(Number(user.can_view_sql || 0) === 1, 'Visible', 'Hidden') + '</td>'
				+ '<td>' + badge(Number(user.can_include_rows || 0) === 1, 'Allowed', 'Blocked') + '</td>'
				+ '<td><button type="button" class="btn btn-sm btn-primary nl2sql-user-edit" data-staff-id="' + escapeHtml(user.staff_id || '') + '">Edit</button></td>'
				+ '</tr>';
		}).join('');
	}

	async function loadConfiguration() {
		const response = await apiRequest('get_configuration', {}, 'GET');
		if (!response.success) {
			showError(response.message || 'Unable to load NL2SQL configuration.');
			return;
		}

		availableTables = response.data.available_tables || [];
		renderGlobalConfiguration(response.data.configuration || {});
	}

	async function loadUserPolicies() {
		const response = await apiRequest('list_user_policies', {}, 'GET');
		if (!response.success) {
			showError(response.message || 'Unable to load NL2SQL user policies.');
			return;
		}

		renderUserPolicies(response.data.users || []);
	}

	async function openPolicyModal(staffId) {
		const response = await apiRequest('get_user_policy', { staff_id: staffId }, 'GET');
		if (!response.success) {
			showError(response.message || 'Unable to load user policy.');
			return;
		}

		const user = response.data.user || {};
		availableTables = response.data.available_tables || availableTables;
		policyStaffIdInput.value = user.staff_id || '';
		policyUserLabelInput.value = (user.username || '') + ' (' + (user.staff_id || '') + ')';
		policyEnabledInput.checked = !!Number(user.is_enabled || 0);
		policyMaxRowLimitInput.value = user.max_row_limit || '';
		policyCanViewSqlInput.checked = !!Number(user.can_view_sql || 0);
		policyCanIncludeRowsInput.checked = !!Number(user.can_include_rows || 0);
		policyNotesInput.value = user.notes || '';
		renderTableCheckboxes(policyAllowedTablesHost, user.allowed_tables || [], 'policy', !canEdit);
		if (policyModal) {
			policyModal.show();
		}
	}

	configForm.addEventListener('submit', async function(event) {
		event.preventDefault();
		const payload = {
			provider: 'ollama',
			model_name: modelInput.value,
			result_row_limit: rowLimitInput.value,
			is_enabled: enabledInput.checked,
			allowed_tables: selectedTables(allowedTablesHost, 'global'),
			prompt_notes: promptNotesInput.value
		};

		const response = await apiRequest('update_configuration', payload);
		if (!response.success) {
			showError(response.message || 'Unable to save NL2SQL configuration.');
			return;
		}

		availableTables = response.data.available_tables || availableTables;
		renderGlobalConfiguration(response.data.configuration || {});
		await showSuccess(response.message || 'NL2SQL configuration saved successfully.');
	});

	policyForm.addEventListener('submit', async function(event) {
		event.preventDefault();
		const payload = {
			staff_id: policyStaffIdInput.value,
			is_enabled: policyEnabledInput.checked,
			max_row_limit: policyMaxRowLimitInput.value,
			can_view_sql: policyCanViewSqlInput.checked,
			can_include_rows: policyCanIncludeRowsInput.checked,
			allowed_tables: selectedTables(policyAllowedTablesHost, 'policy'),
			notes: policyNotesInput.value
		};

		const response = await apiRequest('save_user_policy', payload);
		if (!response.success) {
			showError(response.message || 'Unable to save user policy.');
			return;
		}

		if (policyModal) {
			policyModal.hide();
		}
		await loadUserPolicies();
		await showSuccess(response.message || 'User policy saved successfully.');
	});

	if (refreshUsersBtn) {
		refreshUsersBtn.addEventListener('click', function() {
			loadUserPolicies();
		});
	}

	userBody.addEventListener('click', function(event) {
		const target = event.target.closest('.nl2sql-user-edit');
		if (!target) {
			return;
		}

		openPolicyModal(target.getAttribute('data-staff-id') || '');
	});

	Promise.all([
		loadConfiguration(),
		loadUserPolicies()
	]);
});
</script>