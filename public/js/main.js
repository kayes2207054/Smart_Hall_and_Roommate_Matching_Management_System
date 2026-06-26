/**
 * NestSync – Main JavaScript
 * Features: Sidebar toggle, Toast notifications, Confirm delete,
 *           Tooltip init, Alert auto-dismiss, Live table search
 */

'use strict';

/* ================================================================
   SIDEBAR TOGGLE (Desktop collapse + Mobile drawer)
   ================================================================ */
(function initSidebar() {
    const sidebar        = document.getElementById('sidebar');
    const toggleBtn      = document.getElementById('sidebarToggle');
    const closeBtn       = document.getElementById('sidebarClose');
    const overlay        = document.getElementById('sidebarOverlay');
    const mainContent    = document.querySelector('.main-content');

    if (!sidebar) return;

    function openSidebar() {
        sidebar.classList.add('open');
        overlay?.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay?.classList.remove('active');
        document.body.style.overflow = '';
    }

    toggleBtn?.addEventListener('click', () => {
        if (window.innerWidth >= 993) {
            // Desktop: collapse sidebar (shift main-content)
            sidebar.classList.toggle('collapsed');
            sidebar.style.transform = sidebar.classList.contains('collapsed')
                ? 'translateX(-100%)' : '';
            if (mainContent) {
                mainContent.style.marginLeft = sidebar.classList.contains('collapsed')
                    ? '0' : '';
            }
        } else {
            // Mobile: show drawer
            sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
        }
    });

    closeBtn?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);

    // Close on resize to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 993) closeSidebar();
    });
})();

/* ================================================================
   BOOTSTRAP INIT
   ================================================================ */
document.addEventListener('DOMContentLoaded', function () {
    // Tooltips
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el, { trigger: 'hover' });
    });

    // Popovers
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
        new bootstrap.Popover(el);
    });

    // Auto-dismiss Bootstrap alerts after 6 s
    document.querySelectorAll('.alert.alert-dismissible').forEach(function (el) {
        setTimeout(function () {
            try { bootstrap.Alert.getOrCreateInstance(el).close(); } catch(e) {}
        }, 6000);
    });
});

/* ================================================================
   TOAST NOTIFICATIONS
   ================================================================ */

// Icon map for toast types
const _toastIcons = {
    success: 'fas fa-check-circle',
    danger:  'fas fa-times-circle',
    warning: 'fas fa-exclamation-triangle',
    info:    'fas fa-info-circle',
};

/**
 * showToast(message, type)
 * type: 'success' | 'danger' | 'warning' | 'info'
 */
function showToast(message, type) {
    type = type || 'info';

    // Create container if missing
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container-custom';
        document.body.appendChild(container);
    }

    const iconClass = _toastIcons[type] || _toastIcons.info;
    const toast = document.createElement('div');
    toast.className = 'toast-custom ' + type;
    toast.innerHTML =
        '<i class="toast-icon ' + iconClass + '"></i>' +
        '<span class="toast-message">' + _escapeHtml(message) + '</span>' +
        '<button class="toast-close" onclick="this.parentElement.remove()" aria-label="Close">×</button>';

    container.appendChild(toast);

    // Auto-remove after 5 s
    setTimeout(function () {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(30px)';
        toast.style.transition = 'all 0.35s ease';
        setTimeout(function () { toast.remove(); }, 360);
    }, 5000);
}

/* ================================================================
   CONFIRM DELETE / ACTION DIALOGS
   ================================================================ */

/**
 * confirmAction(message, formIdOrCallback)
 * Displays a confirm dialog; if accepted, submits the form or calls callback.
 */
function confirmAction(message, formIdOrCallback) {
    if (!confirm(message || 'Are you sure? This action cannot be undone.')) return false;
    if (typeof formIdOrCallback === 'string') {
        const form = document.getElementById(formIdOrCallback);
        if (form) form.submit();
    } else if (typeof formIdOrCallback === 'function') {
        formIdOrCallback();
    }
    return true;
}

/**
 * confirmDelete(formId, itemName)
 * Convenience wrapper for delete confirmation.
 */
function confirmDelete(formId, itemName) {
    const name = itemName ? ' "' + itemName + '"' : '';
    return confirmAction('Delete' + name + '? This cannot be undone.', formId);
}

/* ================================================================
   LIVE TABLE SEARCH (client-side, no page reload)
   ================================================================ */

/**
 * initTableSearch(inputId, tableId)
 * Filters table rows as user types in the search input.
 */
function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        const rows  = table.querySelectorAll('tbody tr');
        let visible = 0;

        rows.forEach(function (row) {
            const text = row.textContent.toLowerCase();
            const show = !query || text.includes(query);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        // Show/hide empty-state row if present
        const emptyRow = table.querySelector('tbody .empty-search-row');
        if (emptyRow) emptyRow.style.display = visible === 0 ? '' : 'none';
    });
}

/* ================================================================
   PASSWORD TOGGLE (show / hide)
   ================================================================ */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.password-toggle-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const wrapper = this.closest('.password-toggle');
            const input   = wrapper?.querySelector('input');
            if (!input) return;
            const isText  = input.type === 'text';
            input.type    = isText ? 'password' : 'text';
            this.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    });
});

/* ================================================================
   FORM VALIDATION HELPERS
   ================================================================ */

/**
 * Adds real-time validation feedback to a form.
 * Marks Bootstrap 'was-validated' on submit.
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});

/* ================================================================
   SCORE BAR ANIMATION (Roommate matching)
   ================================================================ */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.score-bar-fill').forEach(function (bar) {
        const target = bar.getAttribute('data-score') || '0';
        bar.style.width = '0%';
        // small delay for animation to be visible
        setTimeout(function () { bar.style.width = target + '%'; }, 200);
    });
});

/* ================================================================
   COUNTER ANIMATION (Dashboard stats)
   ================================================================ */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.stat-number[data-count]').forEach(function (el) {
        const target   = parseInt(el.getAttribute('data-count'), 10);
        const duration = 1200;
        const step     = Math.ceil(target / (duration / 16));
        let current    = 0;

        const timer = setInterval(function () {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = current.toLocaleString();
        }, 16);
    });
});

/* ================================================================
   UTILITY: HTML Escape
   ================================================================ */
function _escapeHtml(str) {
    const map = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;' };
    return String(str).replace(/[&<>"']/g, function (c) { return map[c]; });
}

/* ================================================================
   MODAL AUTO-POPULATE (Edit forms)
   Reads data-* attributes from trigger buttons and populates forms.
   ================================================================ */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="modal"][data-form-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const formId = this.getAttribute('data-form-target');
            const form   = document.getElementById(formId);
            if (!form) return;

            // Copy all data-field-* attributes into matching form fields
            Object.keys(this.dataset).forEach(function (key) {
                if (!key.startsWith('field')) return;
                const fieldName = key.replace('field', '').toLowerCase();
                const field     = form.querySelector('[name="' + fieldName + '"]');
                if (field) field.value = btn.dataset[key];
            });
        });
    });
});
