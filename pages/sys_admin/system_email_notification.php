<?php
include_once '../../build/config.php';
include_once '../../build/session.php';
include_once '../../build/email_notifications.php';

$pageAuth = hyphen_bind_page_auth('sys_admin/system_email_notification');
$canCreateEmail = hyphen_page_auth('sys_admin/system_email_notification')['add'];
$canEditEmail = hyphen_page_auth('sys_admin/system_email_notification')['edit'];
$canDeleteEmail = hyphen_page_auth('sys_admin/system_email_notification')['delete'];
$dbError = null;

$mailConfig = hyphen_mail_config();
$mailConfigured = hyphen_mail_is_configured($mailConfig);
$mailMissingConfig = hyphen_mail_missing_config_keys($mailConfig);
$emailApiUrl = '../../api/email_notifications.php';

include_once '../../include/h_main.php';
include_once '../../include/h_cstable.php';
?>
<style>
    #exampleModalScrollable .modal-dialog {
        max-width: 1200px;
    }

    #exampleModalScrollable .modal-content {
        max-height: calc(100vh - 2rem);
    }

    #exampleModalScrollable .modal-body {
        max-height: calc(100vh - 180px);
        overflow-y: auto;
        overflow-x: hidden;
    }

    #exampleModalScrollable .modal-footer {
        position: sticky;
        bottom: 0;
        z-index: 2;
        background: #fff;
        border-top: 1px solid #e9ecef;
    }

    #templateTextPreview {
        min-height: 120px;
        max-height: 200px;
        overflow: auto;
    }

    #templateHtmlPreview {
        min-height: 220px;
    }
</style>
<!-- Start::content -->
<?php if ($dbError !== null): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Database Error:</strong> <?php echo htmlspecialchars($dbError); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    </div>
<?php endif; ?>
<div class="row">
    <div class="col-xl-4">
        <div class="card custom-card overflow-hidden">
            <div class="card-header justify-content-between">
                <div class="card-title">
                    Mail Transport Status
                </div>
                <span class="badge <?php echo $mailConfigured ? 'bg-success-transparent text-success' : 'bg-warning-transparent text-warning'; ?>">
                    <?php echo $mailConfigured ? 'Configured' : 'Action Required'; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="mb-3 d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="fw-semibold mb-1">Config Source</div>
                        <div class="text-muted text-capitalize" id="mailConfigSourceLabel"><?php echo htmlspecialchars((string) ($mailConfig['source'] ?? 'env')); ?></div>
                    </div>
                    <div>
                        <?php if ($canEditEmail): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="toggleMailConfigBtn">Edit Settings</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" disabled>Edit Settings</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-1">SMTP Host</div>
                    <div class="text-muted" id="mailHostSummary"><?php echo htmlspecialchars((string) ($mailConfig['host'] ?? '')); ?> : <?php echo (int) ($mailConfig['port'] ?? 0); ?></div>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-1">From</div>
                    <div class="text-muted" id="mailFromSummary"><?php echo htmlspecialchars((string) ($mailConfig['from_name'] ?? '')); ?> &lt;<?php echo htmlspecialchars((string) ($mailConfig['from_address'] ?? '')); ?>&gt;</div>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-1">Encryption</div>
                    <div class="text-muted text-uppercase" id="mailEncryptionSummary"><?php echo htmlspecialchars((string) ($mailConfig['encryption'] ?? '')); ?></div>
                </div>
                <div class="mb-3">
                    <div class="fw-semibold mb-1">Password Status</div>
                    <div class="text-muted" id="mailPasswordSummary"><?php echo !empty($mailConfig['has_password']) ? 'Saved in current source' : 'Not saved'; ?></div>
                </div>
                <div class="alert <?php echo !$mailConfigured ? 'alert-warning' : 'alert-info'; ?> mb-3" role="alert" id="mailStatusMessage">
                    <?php if (!$mailConfigured): ?>
                        Missing mail config: <?php echo htmlspecialchars(implode(', ', $mailMissingConfig)); ?>
                    <?php else: ?>
                        Gmail SMTP is configured. 
                    <?php endif; ?>
                </div>

                <form id="mailConfigForm" class="border rounded p-3 bg-light d-none" novalidate>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="mailProvider" class="form-label">Provider</label>
                            <select id="mailProvider" name="provider" class="form-select">
                                <option value="gmail">Gmail SMTP</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="mailEncryption" class="form-label">Encryption</label>
                            <select id="mailEncryption" name="encryption" class="form-select">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="mailHost" class="form-label">SMTP Host</label>
                            <input type="text" id="mailHost" name="host" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label for="mailPort" class="form-label">SMTP Port</label>
                            <input type="number" id="mailPort" name="port" class="form-control" min="1" max="65535" required>
                        </div>
                        <div class="col-md-6">
                            <label for="mailUsername" class="form-label">Username</label>
                            <input type="text" id="mailUsername" name="username" class="form-control" placeholder="your_gmail_address@gmail.com" required>
                        </div>
                        <div class="col-md-6">
                            <label for="mailPassword" class="form-label">Password / App Password</label>
                            <input type="password" id="mailPassword" name="password" class="form-control" placeholder="Leave blank to keep current password">
                            <div class="form-text">For Gmail, use an App Password.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="mailFromAddress" class="form-label">From Address</label>
                            <input type="email" id="mailFromAddress" name="from_address" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label for="mailFromName" class="form-label">From Name</label>
                            <input type="text" id="mailFromName" name="from_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label for="mailReplyTo" class="form-label">Reply-To</label>
                            <input type="email" id="mailReplyTo" name="reply_to" class="form-control">
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-primary" <?php echo $canEditEmail ? '' : 'disabled'; ?>>Save Mail Settings</button>
                        <button type="button" class="btn btn-light" id="cancelMailConfigBtn">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">
                    Send Test Email
                </div>
            </div>
            <div class="card-body">
                <form id="emailTestForm" novalidate>
                    <div class="mb-3">
                        <label for="testTemplateId" class="form-label">Template</label>
                        <select id="testTemplateId" name="id" class="form-select" required>
                            <option value="">Select a template</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="testRecipient" class="form-label">Recipient Email</label>
                        <input type="email" id="testRecipient" name="to_email" class="form-control" placeholder="recipient@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label for="testVariables" class="form-label">Variables JSON</label>
                        <textarea id="testVariables" name="variables" class="form-control" rows="6" placeholder='{"username":"Steve","request_no":"REQ-001"}'></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" <?php echo $canEditEmail ? '' : 'disabled'; ?>>Send Test Email</button>
                        <button type="button" class="btn btn-outline-secondary" id="refreshLogsBtn">Refresh Logs</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card custom-card">
            <div class="card-header justify-content-between">
                <div class="card-title">
                    Email Notification Templates
                </div>
                <div>
                    <?php if ($canCreateEmail): ?>
                        <button type="button" class="btn btn-primary btn-wave" id="openTemplateModalBtn">Add New Template</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary btn-wave" disabled>Add New Template</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="SysNoti" class="table table-bordered text-nowrap w-100 align-middle">
                        <thead>
                            <tr>
                                <th style="width:5%;">S/N</th>
                                <th>Category</th>
                                <th>Notification Code</th>
                                <th>Template Name</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th style="width:10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">
                    Delivery Logs
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="EmailLogTable" class="table table-bordered text-nowrap w-100 align-middle">
                        <thead>
                            <tr>
                                <th style="width:5%;">S/N</th>
                                <th>Notification Code</th>
                                <th>Recipient</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Sent At</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="exampleModalScrollable" tabindex="-1" aria-labelledby="exampleModalScrollableLabel" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="exampleModalScrollableLabel">Create Email Template</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="templateForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="templateId" name="id">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="templateCategory" class="form-label">Category</label>
                            <input type="text" id="templateCategory" name="category" class="form-control" maxlength="100" required>
                        </div>
                        <div class="col-md-4">
                            <label for="templateCode" class="form-label">Notification Code</label>
                            <input type="text" id="templateCode" name="notification_code" class="form-control" maxlength="100" placeholder="HR.NEW_JOINER" required>
                        </div>
                        <div class="col-md-4">
                            <label for="templateName" class="form-label">Template Name</label>
                            <input type="text" id="templateName" name="template_name" class="form-control" maxlength="150" required>
                        </div>
                        <div class="col-12">
                            <label for="templateSubject" class="form-label">Email Subject</label>
                            <input type="text" id="templateSubject" name="email_subject" class="form-control" maxlength="255" placeholder="Welcome {{username}}" required>
                        </div>
                        <div class="col-lg-8">
                            <label for="templateHtml" class="form-label">HTML Body</label>
                            <textarea id="templateHtml" name="body_html" class="form-control" rows="12" placeholder="<p>Hello {{username}}</p>" required></textarea>
                        </div>
                        <div class="col-lg-4">
                            <label for="templateText" class="form-label">Plain Text Body</label>
                            <textarea id="templateText" name="body_text" class="form-control" rows="5" placeholder="Hello {{username}}"></textarea>
                            <label for="templateVariables" class="form-label mt-3">Variables JSON</label>
                            <textarea id="templateVariables" name="variables" class="form-control" rows="6" placeholder='{"username":"User Name","request_no":"REQ-001"}'></textarea>
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="templateActive" name="is_active" checked>
                                <label class="form-check-label" for="templateActive">Template Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="row g-3 mt-1">
                                <div class="col-lg-4">
                                    <div class="card border shadow-none h-100">
                                        <div class="card-header">
                                            <div class="card-title mb-0">Variable Preview</div>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-2">Detected Placeholders</div>
                                                <div id="templateDetectedVariables" class="d-flex flex-wrap gap-2"></div>
                                            </div>
                                            <div>
                                                <div class="fw-semibold mb-2">Preview Values</div>
                                                <div id="templateVariablePreview" class="small text-muted"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="card border shadow-none h-100">
                                        <div class="card-header">
                                            <div class="card-title mb-0">Rendered Preview</div>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1">Subject</div>
                                                <div id="templateSubjectPreview" class="text-muted small"></div>
                                            </div>
                                            <div>
                                                <div class="fw-semibold mb-1">Plain Text</div>
                                                <pre id="templateTextPreview" class="small bg-light border rounded p-2 mb-0" style="white-space: pre-wrap; min-height: 180px;"></pre>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="card border shadow-none h-100">
                                        <div class="card-header">
                                            <div class="card-title mb-0">HTML Preview</div>
                                        </div>
                                        <div class="card-body">
                                            <div id="templatePreviewStatus" class="small text-muted mb-2">Preview updates automatically as you type.</div>
                                            <iframe id="templateHtmlPreview" title="Template HTML Preview" class="w-100 border rounded bg-white" style="min-height: 320px;" sandbox=""></iframe>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" <?php echo ($canCreateEmail || $canEditEmail) ? '' : 'disabled'; ?>>Save Template</button>
                </div>
            </form>
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
        const emailApiUrl = <?php echo json_encode($emailApiUrl); ?>;
        const canCreateEmail = <?php echo $canCreateEmail ? 'true' : 'false'; ?>;
        const canEditEmail = <?php echo $canEditEmail ? 'true' : 'false'; ?>;
        const canDeleteEmail = <?php echo $canDeleteEmail ? 'true' : 'false'; ?>;
        const templateModalElement = document.getElementById('exampleModalScrollable');
        const templateModal = templateModalElement ? bootstrap.Modal.getOrCreateInstance(templateModalElement) : null;
        const mailConfigForm = document.getElementById('mailConfigForm');
        const toggleMailConfigBtn = document.getElementById('toggleMailConfigBtn');
        const cancelMailConfigBtn = document.getElementById('cancelMailConfigBtn');
        const templateForm = document.getElementById('templateForm');
        const emailTestForm = document.getElementById('emailTestForm');
        const openTemplateModalBtn = document.getElementById('openTemplateModalBtn');
        const refreshLogsBtn = document.getElementById('refreshLogsBtn');
        const templateIdInput = document.getElementById('templateId');
        const templateCategoryInput = document.getElementById('templateCategory');
        const templateCodeInput = document.getElementById('templateCode');
        const templateNameInput = document.getElementById('templateName');
        const templateSubjectInput = document.getElementById('templateSubject');
        const templateHtmlInput = document.getElementById('templateHtml');
        const templateTextInput = document.getElementById('templateText');
        const templateVariablesInput = document.getElementById('templateVariables');
        const templateActiveInput = document.getElementById('templateActive');
        const testTemplateId = document.getElementById('testTemplateId');
        const mailProviderInput = document.getElementById('mailProvider');
        const mailHostInput = document.getElementById('mailHost');
        const mailPortInput = document.getElementById('mailPort');
        const mailEncryptionInput = document.getElementById('mailEncryption');
        const mailUsernameInput = document.getElementById('mailUsername');
        const mailPasswordInput = document.getElementById('mailPassword');
        const mailFromAddressInput = document.getElementById('mailFromAddress');
        const mailFromNameInput = document.getElementById('mailFromName');
        const mailReplyToInput = document.getElementById('mailReplyTo');
        const mailConfigSourceLabel = document.getElementById('mailConfigSourceLabel');
        const mailHostSummary = document.getElementById('mailHostSummary');
        const mailFromSummary = document.getElementById('mailFromSummary');
        const mailEncryptionSummary = document.getElementById('mailEncryptionSummary');
        const mailPasswordSummary = document.getElementById('mailPasswordSummary');
        const mailStatusMessage = document.getElementById('mailStatusMessage');
        const templateDetectedVariables = document.getElementById('templateDetectedVariables');
        const templateVariablePreview = document.getElementById('templateVariablePreview');
        const templateSubjectPreview = document.getElementById('templateSubjectPreview');
        const templateTextPreview = document.getElementById('templateTextPreview');
        const templatePreviewStatus = document.getElementById('templatePreviewStatus');
        const templateHtmlPreview = document.getElementById('templateHtmlPreview');

        const templateTable = $('#SysNoti').DataTable({
            language: {
                searchPlaceholder: 'Search...',
                sSearch: '',
            },
            pageLength: 10,
            scrollX: true,
            columnDefs: [{
                targets: [5],
                className: 'text-center align-middle',
            }],
        });

        const logTable = $('#EmailLogTable').DataTable({
            language: {
                searchPlaceholder: 'Search...',
                sSearch: '',
            },
            pageLength: 10,
            scrollX: true,
        });

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

        function showConfirm(message) {
            if (window.Swal && typeof window.Swal.fire === 'function') {
                return window.Swal.fire({
                    title: 'Confirm',
                    text: message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes',
                    cancelButtonText: 'Cancel'
                }).then(function(result) {
                    return result.isConfirmed;
                });
            }

            return Promise.resolve(window.confirm(message));
        }

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        }

        function prettyPreviewValue(value) {
            if (value === null || value === undefined || value === '') {
                return '<em class="text-muted">Empty</em>';
            }

            if (typeof value === 'object') {
                return '<code>' + escapeHtml(JSON.stringify(value)) + '</code>';
            }

            return '<code>' + escapeHtml(String(value)) + '</code>';
        }

        function extractTemplateVariables() {
            const combined = [templateSubjectInput.value, templateHtmlInput.value, templateTextInput.value].join('\n');
            const matches = combined.match(/{{\s*[a-zA-Z0-9_.-]+\s*}}/g) || [];
            const keys = matches.map(function(match) {
                return match.replace(/[{}\s]/g, '');
            });

            return Array.from(new Set(keys));
        }

        function getTemplatePreviewVariables() {
            const raw = templateVariablesInput.value.trim();
            if (raw === '') {
                return { values: {}, error: '' };
            }

            try {
                const parsed = JSON.parse(raw);
                if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') {
                    return { values: {}, error: 'Variables JSON must be an object.' };
                }

                return { values: parsed, error: '' };
            } catch (error) {
                return { values: {}, error: 'Variables JSON is invalid.' };
            }
        }

        function resolveTemplateValue(values, key) {
            return key.split('.').reduce(function(current, part) {
                if (current && Object.prototype.hasOwnProperty.call(current, part)) {
                    return current[part];
                }

                return undefined;
            }, values);
        }

        function renderTemplateString(template, values) {
            return String(template || '').replace(/{{\s*([a-zA-Z0-9_.-]+)\s*}}/g, function(match, key) {
                const resolved = resolveTemplateValue(values, key);
                if (resolved === undefined || resolved === null) {
                    return match;
                }

                return typeof resolved === 'object' ? JSON.stringify(resolved) : String(resolved);
            });
        }

        function stripHtml(value) {
            return $('<div>').html(value || '').text();
        }

        function buildPreviewDocument(html) {
            return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{font-family:Segoe UI,Tahoma,sans-serif;padding:16px;color:#1f2937;line-height:1.5;}img{max-width:100%;height:auto;}table{max-width:100%;}a{color:#0d6efd;}</style></head><body>' + html + '</body></html>';
        }

        function updateTemplatePreview() {
            const previewData = getTemplatePreviewVariables();
            const variables = previewData.values;
            const detectedVariables = extractTemplateVariables();

            if (templateDetectedVariables) {
                templateDetectedVariables.innerHTML = detectedVariables.length
                    ? detectedVariables.map(function(key) {
                        return '<span class="badge bg-primary-transparent text-primary">{{' + escapeHtml(key) + '}}</span>';
                    }).join('')
                    : '<span class="text-muted small">No placeholders detected.</span>';
            }

            if (templateVariablePreview) {
                if (previewData.error) {
                    templateVariablePreview.innerHTML = '<div class="text-danger">' + escapeHtml(previewData.error) + '</div>';
                } else if (detectedVariables.length === 0) {
                    templateVariablePreview.innerHTML = '<span class="text-muted">Add placeholders like {{username}} to see preview values.</span>';
                } else {
                    templateVariablePreview.innerHTML = detectedVariables.map(function(key) {
                        return '<div class="mb-2"><span class="fw-semibold">' + escapeHtml(key) + '</span><div>' + prettyPreviewValue(resolveTemplateValue(variables, key)) + '</div></div>';
                    }).join('');
                }
            }

            const renderedSubject = renderTemplateString(templateSubjectInput.value, variables);
            const renderedHtml = renderTemplateString(templateHtmlInput.value, variables);
            const renderedText = templateTextInput.value.trim() !== ''
                ? renderTemplateString(templateTextInput.value, variables)
                : stripHtml(renderedHtml);

            if (templateSubjectPreview) {
                templateSubjectPreview.textContent = renderedSubject || 'No subject yet.';
            }

            if (templateTextPreview) {
                templateTextPreview.textContent = renderedText || 'No plain text preview yet.';
            }

            if (templatePreviewStatus) {
                templatePreviewStatus.className = 'small mb-2 ' + (previewData.error ? 'text-danger' : 'text-muted');
                templatePreviewStatus.textContent = previewData.error || 'Preview updates automatically as you type.';
            }

            if (templateHtmlPreview) {
                templateHtmlPreview.srcdoc = buildPreviewDocument(renderedHtml || '<p style="color:#6c757d;">No HTML content yet.</p>');
            }
        }

        async function apiRequest(action, payload = {}, method = 'POST') {
            const options = {
                method,
                headers: {},
                credentials: 'same-origin'
            };

            if (method === 'GET') {
                const params = new URLSearchParams({ action, ...payload });
                return fetch(emailApiUrl + '?' + params.toString(), options).then((response) => response.json());
            }

            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify({ action, ...payload });
            return fetch(emailApiUrl, options).then((response) => response.json());
        }

        function setMailFormVisible(visible) {
            if (!mailConfigForm) {
                return;
            }

            mailConfigForm.classList.toggle('d-none', !visible);
        }

        function renderMailStatus(configuration, configured, missingConfig) {
            const source = configuration.source || 'env';
            mailConfigSourceLabel.textContent = source;
            mailHostSummary.textContent = (configuration.host || '') + ' : ' + (configuration.port || '');
            mailFromSummary.textContent = (configuration.from_name || '') + ' <' + (configuration.from_address || '') + '>';
            mailEncryptionSummary.textContent = (configuration.encryption || '').toUpperCase();
            mailPasswordSummary.textContent = configuration.has_password ? 'Saved in ' + source : 'Not saved';
            mailStatusMessage.className = 'alert mb-3 ' + (configured ? 'alert-info' : 'alert-warning');
            mailStatusMessage.textContent = configured
                ? ('Mail transport is configured from ' + source + '. For Gmail, use an App Password.')
                : ('Missing mail config: ' + (missingConfig || []).join(', '));

            mailProviderInput.value = configuration.provider || 'gmail';
            mailHostInput.value = configuration.host || 'smtp.gmail.com';
            mailPortInput.value = configuration.port || 587;
            mailEncryptionInput.value = configuration.encryption || 'tls';
            mailUsernameInput.value = configuration.username || '';
            mailPasswordInput.value = '';
            mailFromAddressInput.value = configuration.from_address || '';
            mailFromNameInput.value = configuration.from_name || '';
            mailReplyToInput.value = configuration.reply_to || '';
        }

        async function loadMailConfiguration() {
            const response = await apiRequest('get_mail_configuration', {}, 'GET');
            if (!response.success) {
                showError(response.message || 'Unable to load mail configuration.');
                return;
            }

            renderMailStatus(response.data.configuration || {}, response.data.configured, response.data.missing_config);
        }

        function resetTemplateForm() {
            templateForm.reset();
            templateIdInput.value = '';
            templateActiveInput.checked = true;
            document.getElementById('exampleModalScrollableLabel').textContent = 'Create Email Template';
            updateTemplatePreview();
        }

        function populateTemplateForm(template) {
            templateIdInput.value = template.id || '';
            templateCategoryInput.value = template.category || '';
            templateCodeInput.value = template.notification_code || '';
            templateNameInput.value = template.template_name || '';
            templateSubjectInput.value = template.email_subject || '';
            templateHtmlInput.value = template.body_html || '';
            templateTextInput.value = template.body_text || '';
            templateVariablesInput.value = template.variables && Object.keys(template.variables).length ? JSON.stringify(template.variables, null, 2) : '';
            templateActiveInput.checked = !!template.is_active;
            document.getElementById('exampleModalScrollableLabel').textContent = 'Edit Email Template';
            updateTemplatePreview();
        }

        async function loadTemplates() {
            const response = await apiRequest('list_templates', {}, 'GET');
            if (!response.success) {
                showError(response.message || 'Unable to load templates.');
                return;
            }

            const templates = response.data.templates || [];
            templateTable.clear();

            const selectOptions = ['<option value="">Select a template</option>'];

            templates.forEach(function(template, index) {
                const statusBadge = template.is_active
                    ? '<span class="badge bg-success-transparent text-success">Active</span>'
                    : '<span class="badge bg-danger-transparent text-danger">Inactive</span>';

                const actionButtons = [
                    canEditEmail ? '<button type="button" class="btn btn-sm btn-primary edit-template" data-id="' + template.id + '">Edit</button>' : '<button type="button" class="btn btn-sm btn-primary" disabled>Edit</button>',
                    canDeleteEmail ? '<button type="button" class="btn btn-sm btn-danger delete-template" data-id="' + template.id + '">Delete</button>' : '<button type="button" class="btn btn-sm btn-danger" disabled>Delete</button>'
                ].join(' ');

                templateTable.row.add([
                    index + 1,
                    $('<div>').text(template.category || '').html(),
                    $('<div>').text(template.notification_code || '').html(),
                    $('<div>').text(template.template_name || '').html(),
                    $('<div>').text(template.email_subject || '').html(),
                    statusBadge,
                    actionButtons
                ]);

                selectOptions.push('<option value="' + template.id + '">' + $('<div>').text(template.notification_code + ' - ' + template.template_name).html() + '</option>');
            });

            templateTable.draw(false);
            testTemplateId.innerHTML = selectOptions.join('');
        }

        async function loadLogs() {
            const response = await apiRequest('list_logs', { limit: 100 }, 'GET');
            if (!response.success) {
                showError(response.message || 'Unable to load logs.');
                return;
            }

            const logs = response.data.logs || [];
            logTable.clear();
            logs.forEach(function(log, index) {
                const statusBadge = log.status === 'sent'
                    ? '<span class="badge bg-success-transparent text-success">Sent</span>'
                    : '<span class="badge bg-danger-transparent text-danger">' + $('<div>').text(log.status || 'failed').html() + '</span>';

                logTable.row.add([
                    index + 1,
                    $('<div>').text(log.notification_code || '').html(),
                    $('<div>').text(log.recipient_email || '').html(),
                    $('<div>').text(log.email_subject || '').html(),
                    statusBadge,
                    $('<div>').text(log.sent_at || log.created_at || '').html()
                ]);
            });
            logTable.draw(false);
        }

        async function loadTemplate(templateId) {
            const response = await apiRequest('get_template', { id: templateId }, 'GET');
            if (!response.success) {
                showError(response.message || 'Unable to load template.');
                return;
            }

            populateTemplateForm(response.data.template || {});
            templateModal.show();
        }

        if (openTemplateModalBtn) {
            openTemplateModalBtn.addEventListener('click', function() {
                resetTemplateForm();
                templateModal.show();
            });
        }

        if (toggleMailConfigBtn) {
            toggleMailConfigBtn.addEventListener('click', function() {
                setMailFormVisible(true);
            });
        }

        if (cancelMailConfigBtn) {
            cancelMailConfigBtn.addEventListener('click', function() {
                setMailFormVisible(false);
                loadMailConfiguration();
            });
        }

        if (refreshLogsBtn) {
            refreshLogsBtn.addEventListener('click', function() {
                loadLogs();
            });
        }

        templateForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const variablesRaw = templateVariablesInput.value.trim();
            let variables = {};
            if (variablesRaw !== '') {
                try {
                    variables = JSON.parse(variablesRaw);
                } catch (error) {
                    showError('Variables JSON is invalid.');
                    return;
                }
            }

            const payload = {
                id: templateIdInput.value,
                category: templateCategoryInput.value,
                notification_code: templateCodeInput.value,
                template_name: templateNameInput.value,
                email_subject: templateSubjectInput.value,
                body_html: templateHtmlInput.value,
                body_text: templateTextInput.value,
                variables,
                is_active: templateActiveInput.checked
            };

            const action = templateIdInput.value ? 'update_template' : 'create_template';
            const response = await apiRequest(action, payload);
            if (!response.success) {
                showError(response.message || 'Unable to save template.');
                return;
            }

            templateModal.hide();
            resetTemplateForm();
            await loadTemplates();
            await showSuccess(response.message || 'Template saved successfully.');
        });

        mailConfigForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            const payload = {
                provider: mailProviderInput.value,
                host: mailHostInput.value,
                port: mailPortInput.value,
                encryption: mailEncryptionInput.value,
                username: mailUsernameInput.value,
                password: mailPasswordInput.value,
                from_address: mailFromAddressInput.value,
                from_name: mailFromNameInput.value,
                reply_to: mailReplyToInput.value
            };

            const response = await apiRequest('update_mail_configuration', payload);
            if (!response.success) {
                showError(response.message || 'Unable to save mail settings.');
                return;
            }

            renderMailStatus(response.data.configuration || {}, response.data.configured, response.data.missing_config);
            setMailFormVisible(false);
            await showSuccess(response.message || 'Mail settings saved successfully.');
        });

        emailTestForm.addEventListener('submit', async function(event) {
            event.preventDefault();

            let variables = {};
            const variablesRaw = document.getElementById('testVariables').value.trim();
            if (variablesRaw !== '') {
                try {
                    variables = JSON.parse(variablesRaw);
                } catch (error) {
                    showError('Test variables JSON is invalid.');
                    return;
                }
            }

            const response = await apiRequest('send_test_email', {
                id: document.getElementById('testTemplateId').value,
                to_email: document.getElementById('testRecipient').value,
                variables
            });

            if (!response.success) {
                showError(response.message || 'Unable to send test email.');
                return;
            }

            await loadLogs();
            await showSuccess(response.message || 'Test email sent successfully.');
        });

        [templateSubjectInput, templateHtmlInput, templateTextInput, templateVariablesInput].forEach(function(element) {
            if (!element) {
                return;
            }

            element.addEventListener('input', updateTemplatePreview);
        });

        $('#SysNoti tbody').on('click', '.edit-template', function() {
            const templateId = $(this).data('id');
            loadTemplate(templateId);
        });

        $('#SysNoti tbody').on('click', '.delete-template', async function() {
            const templateId = $(this).data('id');
            const confirmed = await showConfirm('Delete this email template?');
            if (!confirmed) {
                return;
            }

            const response = await apiRequest('delete_template', { id: templateId });
            if (!response.success) {
                showError(response.message || 'Unable to delete template.');
                return;
            }

            await loadTemplates();
            await showSuccess(response.message || 'Template deleted successfully.');
        });

        updateTemplatePreview();
        loadMailConfiguration();
        loadTemplates();
        loadLogs();
    });
</script>