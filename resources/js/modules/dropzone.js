/**
 * Drag & Drop Module + Live Dry-Run XML/JSON Validation + XHR Progress Bar
 */
export function initDropzone() {
    const dropzone = document.querySelector('[data-dropzone]');
    if (!dropzone) return;

    const fileInput = dropzone.querySelector('input[type="file"]');
    const fileLabel = dropzone.querySelector('[data-dropzone-filename]');
    const form = dropzone.closest('form');
    const terminalBody = document.querySelector('[data-terminal-output]');
    const terminalStatus = document.querySelector('[data-terminal-status]');
    const progressBox = document.querySelector('[data-upload-progress]');
    const progressBar = document.querySelector('[data-upload-progress-bar]');
    const progressLabel = document.querySelector('[data-upload-progress-label]');
    const submitBtn = form?.querySelector('[data-upload-submit]');
    const validateUrl = dropzone.getAttribute('data-validate-url');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    let dragDepth = 0;

    const setDropzoneState = (state) => {
        dropzone.classList.remove('is-dragover', 'is-valid', 'is-warning', 'is-invalid');
        if (state) dropzone.classList.add(state);
    };

    const escapeHtml = (str) =>
        String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

    const renderTerminalLines = (htmlLines, statusText = 'READY') => {
        if (terminalStatus) terminalStatus.textContent = statusText;
        if (terminalBody) {
            terminalBody.innerHTML = htmlLines.join('');
        }
    };

    const runDryRunValidation = async (file) => {
        const allowed = ['zip', 'jar', 'xml', 'json', 'info'];
        const ext = (file.name.split('.').pop() || '').toLowerCase();

        if (!allowed.includes(ext)) {
            setDropzoneState('is-invalid');
            renderTerminalLines([
                `<div class="terminal__line terminal__line--error">[FATAL ERROR] EXTENSION ".${escapeHtml(ext.toUpperCase())}" BLOCKED BY VALIDATOR.</div>`,
                `<div class="terminal__line">ACCEPTED FORMATS: .ZIP, .JAR, .XML (modDesc.xml), .JSON (fabric.mod.json) AND .INFO (mcmod.info).</div>`,
            ], 'REJECTED');
            return;
        }

        if (file.size > 50 * 1024 * 1024) {
            setDropzoneState('is-invalid');
            renderTerminalLines([
                `<div class="terminal__line terminal__line--error">[FATAL ERROR] FILE EXCEEDS THE 50 MB MAXIMUM LIMIT.</div>`,
            ], 'REJECTED');
            return;
        }

        if (!validateUrl) return;

        renderTerminalLines([
            `<div class="terminal__line">&gt; INSPECTING PACKAGE "${escapeHtml(file.name)}" (${(file.size / 1024).toFixed(1)} KB)...</div>`,
            `<div class="terminal__line">&gt; RUNNING LIBXML / JSON SCHEMA PARSER IN BACKEND...</div>`,
        ], 'ANALYZING...');

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(validateUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const payload = await response.json();

            if (!response.ok) {
                setDropzoneState('is-invalid');
                const firstErr = payload?.errors?.file?.[0] || payload?.message || 'Package validation error.';
                renderTerminalLines([
                    `<div class="terminal__line terminal__line--error">[422 REJECTED BY FORMREQUEST] ${escapeHtml(firstErr)}</div>`,
                ], 'BLOCKED (422)');
                return;
            }

            const report = payload.report;
            const lines = [];

            lines.push(
                `<div class="terminal__line">&gt; FILE: <strong>${escapeHtml(payload.filename)}</strong> | FORMAT: <strong>${escapeHtml(report.manifest_label || 'N/A')}</strong></div>`
            );

            if (report.status === 'validated') {
                setDropzoneState('is-valid');
                lines.push(
                    `<div class="terminal__line terminal__line--ok">[STATUS: VALIDATED] 100% STRUCTURAL INTEGRITY CONFIRMED.</div>`
                );
            } else if (report.status === 'warning') {
                setDropzoneState('is-warning');
                lines.push(
                    `<div class="terminal__line terminal__line--warning">[STATUS: VALIDATED W/ WARNINGS] ${report.counts.warnings} STRUCTURAL WARNING(S) DETECTED.</div>`
                );
            } else {
                setDropzoneState('is-invalid');
                lines.push(
                    `<div class="terminal__line terminal__line--error">[STATUS: XML/JSON SYNTAX ERROR] ${report.counts.errors} CRITICAL ERROR(S). IF SUBMITTED, THIS FILE WILL BE QUARANTINED WITH A RED ERROR BADGE.</div>`
                );
            }

            if (report.manifest) {
                const m = report.manifest;
                const compat = (m.compatible_game_versions || []).join(', ') || 'N/A';
                lines.push(
                    `<div class="terminal__line">&gt; EXTRACTED METADATA: ID="<strong>${escapeHtml(m.identifier)}</strong>" | TITLE="<strong>${escapeHtml(m.name)}</strong>" | VERSION="<strong>${escapeHtml(m.version)}</strong>" | AUTHOR="<strong>${escapeHtml(m.author)}</strong>" | COMPATIBILITY=[<strong>${escapeHtml(compat)}</strong>]</div>`
                );
            }

            for (const issue of report.issues || []) {
                const cls = issue.severity === 'error' ? 'terminal__line--error' : 'terminal__line--warning';
                const loc = issue.line ? ` (LINE ${issue.line}${issue.column ? `:${issue.column}` : ''})` : '';
                lines.push(
                    `<div class="terminal__line ${cls}">[${escapeHtml(issue.code)}]${escapeHtml(loc)} ${escapeHtml(issue.message)}</div>`
                );
                if (issue.context) {
                    lines.push(`<pre class="terminal__snippet">${escapeHtml(issue.context)}</pre>`);
                }
            }

            renderTerminalLines(lines, report.status_label || 'COMPLETED');
        } catch {
            setDropzoneState('is-invalid');
            renderTerminalLines([
                `<div class="terminal__line terminal__line--error">[NETWORK ERROR] UNABLE TO REACH BACKEND VALIDATION ENGINE.</div>`,
            ], 'ERROR');
        }
    };

    const handleFileSelected = (file) => {
        if (!file) return;
        if (fileLabel) {
            fileLabel.hidden = false;
            fileLabel.textContent = `SELECTED FILE: ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
        }
        runDryRunValidation(file);
    };

    dropzone.addEventListener('click', (e) => {
        if (e.target !== fileInput) {
            fileInput?.click();
        }
    });

    dropzone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            fileInput?.click();
        }
    });

    dropzone.addEventListener('dragenter', (e) => {
        e.preventDefault();
        dragDepth++;
        dropzone.classList.add('is-dragover');
    });

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
    });

    dropzone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        dragDepth = Math.max(0, dragDepth - 1);
        if (dragDepth === 0) {
            dropzone.classList.remove('is-dragover');
        }
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dragDepth = 0;
        dropzone.classList.remove('is-dragover');

        const files = e.dataTransfer?.files;
        if (files && files.length > 0 && fileInput) {
            const dt = new DataTransfer();
            dt.items.add(files[0]);
            fileInput.files = dt.files;
            handleFileSelected(files[0]);
        }
    });

    fileInput?.addEventListener('change', () => {
        if (fileInput.files && fileInput.files.length > 0) {
            handleFileSelected(fileInput.files[0]);
        }
    });

    form?.addEventListener('submit', (e) => {
        if (!window.XMLHttpRequest || !fileInput?.files?.length) {
            return;
        }

        e.preventDefault();
        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();

        if (submitBtn) submitBtn.disabled = true;
        if (progressBox) progressBox.classList.add('is-active');

        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable) return;
            const percent = Math.round((event.loaded / event.total) * 100);
            if (progressBar) progressBar.style.width = `${percent}%`;
            if (progressLabel) progressLabel.textContent = `UPLOADING & VALIDATING: ${percent}%`;
        });

        xhr.addEventListener('load', () => {
            try {
                const res = JSON.parse(xhr.responseText);
                if (xhr.status >= 200 && xhr.status < 300 && res.redirect) {
                    window.location.href = res.redirect;
                    return;
                }
                const firstErr =
                    res?.errors?.file?.[0] ||
                    res?.message ||
                    'Failed to process mod submission.';
                renderTerminalLines([
                    `<div class="terminal__line terminal__line--error">[SUBMISSION ERROR] ${escapeHtml(firstErr)}</div>`,
                ], 'ERROR');
            } catch {
                form.submit();
            } finally {
                if (submitBtn) submitBtn.disabled = false;
            }
        });

        xhr.addEventListener('error', () => {
            if (submitBtn) submitBtn.disabled = false;
            form.submit();
        });

        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.send(formData);
    });
}
