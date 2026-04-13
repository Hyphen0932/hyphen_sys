<?php
include_once '../../build/config.php';
include_once '../../build/session.php';

$pageAuth = hyphen_bind_page_auth('sys_admin/system_advance_search');
$aiApiUrl = './action/sys_advance_search_api.php';
$defaultConversationId = 'sys-adv-search-' . substr(session_id(), 0, 12);

include_once '../../include/h_main.php';
?>
<!-- Start::content -->
<div class="row">
	<div class="col-12">
		<div class="card custom-card overflow-hidden">
			<div class="card-body p-4 p-lg-5">
				<div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
					<div>
						<span class="badge bg-primary-transparent text-primary mb-2">AI Query Console</span>
						<h3 class="mb-2">Natural Language Database Search</h3>
						<p class="text-muted mb-0">Use plain language to query the approved Hyphen System tables. The service generates read-only SQL, validates it, and returns a concise answer.</p>
					</div>
					<div class="d-flex flex-wrap gap-2">
						<span class="badge bg-light text-dark border">hy_users</span>
						<span class="badge bg-light text-dark border">hy_user_menu</span>
						<span class="badge bg-light text-dark border">hy_user_pages</span>
						<span class="badge bg-light text-dark border">hy_user_permissions</span>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-xl-8">
		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">Ask the Database</div>
				<div class="text-muted small" id="aiStatusText">Ready</div>
			</div>
			<div class="card-body">
				<div id="aiAlertHost"></div>
				<form id="aiQueryForm" class="row g-3">
					<div class="col-12">
						<label for="aiQuestion" class="form-label">Question</label>
						<textarea class="form-control" id="aiQuestion" name="question" rows="5" placeholder="Example: show active users with their staff_id and email" required></textarea>
					</div>
					<div class="col-md-6">
						<label for="aiConversationId" class="form-label">Conversation ID</label>
						<input type="text" class="form-control" id="aiConversationId" name="conversation_id" value="<?php echo htmlspecialchars($defaultConversationId, ENT_QUOTES, 'UTF-8'); ?>">
					</div>
					<div class="col-md-6 d-flex align-items-end">
						<div class="form-check form-switch mb-1">
							<input class="form-check-input" type="checkbox" role="switch" id="aiIncludeRows" checked>
							<label class="form-check-label" for="aiIncludeRows">Include raw result rows</label>
						</div>
					</div>
					<div class="col-12 d-flex flex-wrap gap-2">
						<button type="submit" class="btn btn-primary" id="aiSubmitBtn">Ask AI</button>
						<button type="button" class="btn btn-light" id="aiResetBtn">Reset</button>
						<button type="button" class="btn btn-outline-secondary" id="aiClearBtn">Clear Result</button>
						<div class="spinner-border spinner-border-sm text-primary d-none" role="status" id="aiLoadingSpinner">
							<span class="visually-hidden">Loading...</span>
						</div>
					</div>
				</form>
			</div>
		</div>

		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">Answer</div>
				<div class="text-muted small" id="aiMetaText">No query yet</div>
			</div>
			<div class="card-body">
				<div class="border rounded p-3 bg-light-subtle" id="aiAnswerBox" style="min-height: 120px; white-space: pre-wrap;">Ask a question to see the answer.</div>
			</div>
		</div>

		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">Generated SQL</div>
			</div>
			<div class="card-body">
				<pre class="mb-0 bg-dark text-light rounded p-3 small" id="aiSqlBox" style="min-height: 120px; white-space: pre-wrap;">-- SQL will appear here</pre>
			</div>
		</div>
	</div>

	<div class="col-xl-4">
		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">Quick Prompts</div>
			</div>
			<div class="card-body d-grid gap-2">
				<button type="button" class="btn btn-outline-primary text-start ai-prompt-btn" data-prompt="How many active users are there?">How many active users are there?</button>
				<button type="button" class="btn btn-outline-primary text-start ai-prompt-btn" data-prompt="List the staff_id and email for System Admin users">List the staff_id and email for System Admin users</button>
				<button type="button" class="btn btn-outline-primary text-start ai-prompt-btn" data-prompt="Which pages belong to menu_id 99?">Which pages belong to menu_id 99?</button>
				<button type="button" class="btn btn-outline-primary text-start ai-prompt-btn" data-prompt="Show the user records that have can_edit permission">Show the user records that have can_edit permission</button>
			</div>
		</div>

		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">Execution Summary</div>
			</div>
			<div class="card-body">
				<div class="row g-3">
					<div class="col-6">
						<div class="border rounded p-3 h-100">
							<div class="text-muted small mb-1">Rows</div>
							<div class="fs-4 fw-semibold" id="aiRowCount">0</div>
						</div>
					</div>
					<div class="col-6">
						<div class="border rounded p-3 h-100">
							<div class="text-muted small mb-1">Conversation</div>
							<div class="fw-semibold small text-break" id="aiConversationLabel"><?php echo htmlspecialchars($defaultConversationId, ENT_QUOTES, 'UTF-8'); ?></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-12">
		<div class="card custom-card">
			<div class="card-header justify-content-between">
				<div class="card-title">Result Rows</div>
				<div class="text-muted small" id="aiTableHint">No rows loaded.</div>
			</div>
			<div class="card-body">
				<div class="table-responsive" id="aiTableWrapper">
					<div class="text-muted">Run a query with raw rows enabled to render the result table.</div>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- End::content -->
<?php
include_once '../../include/h_footer.php';
?>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		const apiUrl = <?php echo json_encode($aiApiUrl); ?>;
		const form = document.getElementById('aiQueryForm');
		const questionInput = document.getElementById('aiQuestion');
		const conversationInput = document.getElementById('aiConversationId');
		const includeRowsInput = document.getElementById('aiIncludeRows');
		const submitBtn = document.getElementById('aiSubmitBtn');
		const resetBtn = document.getElementById('aiResetBtn');
		const clearBtn = document.getElementById('aiClearBtn');
		const spinner = document.getElementById('aiLoadingSpinner');
		const statusText = document.getElementById('aiStatusText');
		const alertHost = document.getElementById('aiAlertHost');
		const answerBox = document.getElementById('aiAnswerBox');
		const sqlBox = document.getElementById('aiSqlBox');
		const rowCountNode = document.getElementById('aiRowCount');
		const conversationLabel = document.getElementById('aiConversationLabel');
		const metaText = document.getElementById('aiMetaText');
		const tableWrapper = document.getElementById('aiTableWrapper');
		const tableHint = document.getElementById('aiTableHint');

		if (!form) {
			return;
		}

		document.querySelectorAll('.ai-prompt-btn').forEach(function(button) {
			button.addEventListener('click', function() {
				questionInput.value = button.getAttribute('data-prompt') || '';
				questionInput.focus();
			});
		});

		form.addEventListener('submit', function(event) {
			event.preventDefault();
			submitQuery();
		});

		resetBtn.addEventListener('click', function() {
			form.reset();
			conversationInput.value = <?php echo json_encode($defaultConversationId); ?>;
			conversationLabel.textContent = conversationInput.value;
			setAlert('');
		});

		clearBtn.addEventListener('click', function() {
			clearResults();
			setAlert('');
		});

		function submitQuery() {
			const question = questionInput.value.trim();
			const conversationId = conversationInput.value.trim() || <?php echo json_encode($defaultConversationId); ?>;

			if (!question) {
				setAlert('Please enter a question before submitting.', 'warning');
				questionInput.focus();
				return;
			}

			setBusy(true);
			setAlert('');
			conversationLabel.textContent = conversationId;

			fetch(apiUrl, {
				method: 'POST',
				credentials: 'same-origin',
				cache: 'no-store',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: JSON.stringify({
					question: question,
					conversation_id: conversationId,
					include_rows: includeRowsInput.checked
				})
			})
			.then(function(response) {
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

					return payload.data || {};
				});
			})
			.then(function(data) {
				renderResponse(data);
			})
			.catch(function(error) {
				setAlert(error.message || 'Failed to query AI service.');
				statusText.textContent = 'Failed';
			})
			.finally(function() {
				setBusy(false);
			});
		}

		function renderResponse(data) {
			answerBox.textContent = data.answer || 'No answer returned.';
			sqlBox.textContent = data.sql || '-- No SQL returned';
			rowCountNode.textContent = String(data.row_count || 0);
			metaText.textContent = 'Question processed successfully';
			statusText.textContent = 'Completed';
			renderRows(Array.isArray(data.rows) ? data.rows : []);
		}

		function renderRows(rows) {
			if (!rows.length) {
				tableWrapper.innerHTML = '<div class="text-muted">No raw rows returned for this query.</div>';
				tableHint.textContent = '0 rows';
				return;
			}

			const columns = Object.keys(rows[0]);
			const thead = '<thead><tr>' + columns.map(function(column) {
				return '<th>' + escapeHtml(column) + '</th>';
			}).join('') + '</tr></thead>';

			const tbody = '<tbody>' + rows.map(function(row) {
				return '<tr>' + columns.map(function(column) {
					return '<td class="text-break">' + escapeHtml(formatCell(row[column])) + '</td>';
				}).join('') + '</tr>';
			}).join('') + '</tbody>';

			tableWrapper.innerHTML = '<table class="table table-bordered table-striped align-middle mb-0">' + thead + tbody + '</table>';
			tableHint.textContent = rows.length + ' row(s) returned';
		}

		function clearResults() {
			answerBox.textContent = 'Ask a question to see the answer.';
			sqlBox.textContent = '-- SQL will appear here';
			rowCountNode.textContent = '0';
			metaText.textContent = 'No query yet';
			statusText.textContent = 'Ready';
			tableWrapper.innerHTML = '<div class="text-muted">Run a query with raw rows enabled to render the result table.</div>';
			tableHint.textContent = 'No rows loaded.';
		}

		function setBusy(isBusy) {
			submitBtn.disabled = isBusy;
			spinner.classList.toggle('d-none', !isBusy);
			statusText.textContent = isBusy ? 'Running...' : statusText.textContent;
		}

		function setAlert(message, type) {
			if (!message) {
				alertHost.innerHTML = '';
				return;
			}

			const resolvedType = type || 'danger';
			alertHost.innerHTML = '<div class="alert alert-' + resolvedType + '" role="alert">' + escapeHtml(message) + '</div>';
		}

		function escapeHtml(value) {
			return String(value == null ? '' : value)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;')
				.replace(/'/g, '&#039;');
		}

		function formatCell(value) {
			if (value == null) {
				return '';
			}

			if (typeof value === 'object') {
				return JSON.stringify(value);
			}

			return String(value);
		}
	});
</script>