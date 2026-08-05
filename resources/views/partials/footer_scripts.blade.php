<script>
(function () {
    var STORAGE_KEY = 'fams_sidebar_mode';
    var root = document.documentElement;

    /* ---------- Digital clock (12-hour) ---------- */
    function pad(n) { return String(n).padStart(2, '0'); }
    function tickClock() {
        var timeEl = document.getElementById('app-clock-time');
        var dateEl = document.getElementById('app-clock-date');
        if (!timeEl || !dateEl) return;
        var now = new Date();
        var hours24 = now.getHours();
        var hours12 = hours24 % 12;
        if (hours12 === 0) hours12 = 12;
        var ampm = hours24 >= 12 ? 'PM' : 'AM';
        timeEl.textContent = hours12 + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()) + ' ' + ampm;
        dateEl.textContent = now.toLocaleDateString(undefined, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    }
    tickClock();
    setInterval(tickClock, 1000);

    /* ---------- Topbar dropdown menus ---------- */
    function closeAllTopbarMenus(exceptId) {
        [
            ['profile-menu-btn', 'profile-menu'],
            ['alerts-btn', 'alerts-menu']
        ].forEach(function (pair) {
            var btn = document.getElementById(pair[0]);
            var menu = document.getElementById(pair[1]);
            if (!menu || menu.id === exceptId) return;
            menu.hidden = true;
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    }

    window.closeProfileMenu = function () {
        closeAllTopbarMenus(null);
    };

    function bindTopbarMenu(btnId, menuId) {
        var btn = document.getElementById(btnId);
        var menu = document.getElementById(menuId);
        if (!btn || !menu) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var willOpen = menu.hidden;
            closeAllTopbarMenus(willOpen ? menuId : null);
            menu.hidden = !willOpen;
            btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
        menu.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    }

    bindTopbarMenu('profile-menu-btn', 'profile-menu');
    bindTopbarMenu('alerts-btn', 'alerts-menu');

    document.addEventListener('click', function () {
        closeAllTopbarMenus(null);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAllTopbarMenus(null);
    });

    /* ---------- Global search ---------- */
    var searchInput = document.getElementById('global-search');
    var searchResults = document.getElementById('global-search-results');
    var searchDataEl = document.getElementById('global-search-data');
    var searchItems = [];
    try {
        searchItems = searchDataEl ? JSON.parse(searchDataEl.textContent || '[]') : [];
    } catch (err) {
        searchItems = [];
    }

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function renderSearch(query) {
        if (!searchResults) return;
        var q = (query || '').trim().toLowerCase();
        if (!q) {
            searchResults.hidden = true;
            searchResults.innerHTML = '';
            return;
        }
        var matches = searchItems.filter(function (item) {
            return (item.label + ' ' + (item.keywords || '')).toLowerCase().indexOf(q) !== -1;
        }).slice(0, 8);

        if (!matches.length) {
            searchResults.innerHTML = '<div class="app-search-empty">No modules match “' + escapeHtml(query) + '”.</div>';
            searchResults.hidden = false;
            return;
        }

        searchResults.innerHTML = matches.map(function (item) {
            return '<a class="app-search-result" href="' + item.path + '" data-nav-link>' + escapeHtml(item.label) + '</a>';
        }).join('');
        searchResults.hidden = false;
    }

    if (searchInput && searchResults) {
        searchInput.addEventListener('input', function () {
            renderSearch(searchInput.value);
        });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                searchInput.value = '';
                renderSearch('');
                searchInput.blur();
            }
            if (e.key === 'Enter') {
                var first = searchResults.querySelector('.app-search-result');
                if (first) {
                    e.preventDefault();
                    first.click();
                }
            }
        });
        searchInput.addEventListener('focus', function () {
            closeAllTopbarMenus(null);
            if (searchInput.value.trim()) renderSearch(searchInput.value);
        });
        document.addEventListener('click', function (e) {
            if (!searchResults.hidden && !searchResults.contains(e.target) && e.target !== searchInput && !searchInput.contains(e.target)) {
                searchResults.hidden = true;
            }
        });
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 'k') {
                e.preventDefault();
                closeAllTopbarMenus(null);
                searchInput.focus();
                searchInput.select();
            }
        });
    }

    /* ---------- Modals ---------- */
    function portalModals() {
        document.querySelectorAll('.modal-overlay').forEach(function (modal) {
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    }

    function anyModalOpen() {
        return Array.prototype.some.call(
            document.querySelectorAll('.modal-overlay'),
            function (m) { return m.classList.contains('is-open'); }
        );
    }

    function topOpenModal() {
        var open = Array.prototype.filter.call(
            document.querySelectorAll('.modal-overlay.is-open'),
            function () { return true; }
        );
        if (!open.length) return null;
        open.sort(function (a, b) {
            var za = parseInt(window.getComputedStyle(a).zIndex, 10) || 0;
            var zb = parseInt(window.getComputedStyle(b).zIndex, 10) || 0;
            return zb - za;
        });
        return open[0];
    }

    window.openModal = function (id) {
        portalModals();
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.removeAttribute('hidden');
        modal.classList.remove('hidden');
        modal.classList.add('is-open', 'flex');
        if (id !== 'recordModal' && id !== 'confirmModal') {
            var record = document.getElementById('recordModal');
            if (record && record.classList.contains('is-open')) {
                modal.style.zIndex = '130';
            }
        }
        document.body.classList.add('modal-open');
    };

    window.closeModal = function (id) {
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.setAttribute('hidden', 'hidden');
        modal.classList.add('hidden');
        modal.classList.remove('is-open', 'flex');
        modal.style.zIndex = '';
        if (!anyModalOpen()) {
            document.body.classList.remove('modal-open');
        }
    };

    /* ---------- Confirm modal ---------- */
    var confirmResolver = null;

    function styleConfirmModal(options) {
        options = options || {};
        var titleEl = document.getElementById('confirmModalTitle');
        var msgEl = document.getElementById('confirmModalMessage');
        var okBtn = document.getElementById('confirmModalOk');
        var icon = document.querySelector('#confirmModal .confirm-modal-icon');
        if (titleEl) titleEl.textContent = options.title || 'Please confirm';
        if (msgEl) msgEl.textContent = options.message || '';
        if (okBtn) {
            okBtn.textContent = options.confirmLabel || 'Confirm';
            okBtn.className = options.danger
                ? 'px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-semibold text-sm transition shadow-sm'
                : 'px-5 py-2.5 rounded-xl bg-ink hover:bg-ink/90 text-sand font-semibold text-sm transition shadow-sm';
        }
        if (icon) {
            icon.className = options.danger
                ? 'confirm-modal-icon flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-accent-50 text-accent-700 border border-accent-200'
                : 'confirm-modal-icon flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 border border-brand-100';
        }
    }

    function settleConfirm(result) {
        closeModal('confirmModal');
        var resolve = confirmResolver;
        confirmResolver = null;
        if (resolve) resolve(!!result);
    }

    window.confirmAction = function (message, options) {
        options = options || {};
        options.message = message;
        styleConfirmModal(options);
        return new Promise(function (resolve) {
            confirmResolver = resolve;
            openModal('confirmModal');
            var okBtn = document.getElementById('confirmModalOk');
            if (okBtn) okBtn.focus();
        });
    };

    window.confirmForm = function (event, message, options) {
        event.preventDefault();
        var form = event.target.closest('form');
        if (!form) return false;
        confirmAction(message, options || {}).then(function (ok) {
            if (!ok) return;
            // Native submit() skips the button onclick, so confirm does not loop.
            HTMLFormElement.prototype.submit.call(form);
        });
        return false;
    };

    var confirmCancel = document.getElementById('confirmModalCancel');
    var confirmOk = document.getElementById('confirmModalOk');
    if (confirmCancel) confirmCancel.addEventListener('click', function () { settleConfirm(false); });
    if (confirmOk) confirmOk.addEventListener('click', function () { settleConfirm(true); });

    /* ---------- Record detail modal (contracts / documents) ---------- */
    var nestedRecordModalIds = ['accessModal', 'versionModal', 'amendModal', 'partyModal', 'documentModal', 'milestoneModal', 'costModal'];

    function cleanupNestedRecordModals() {
        nestedRecordModalIds.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.remove();
        });
    }

    window.closeRecordModal = function () {
        cleanupNestedRecordModals();
        var body = document.getElementById('recordModalBody');
        if (body) body.innerHTML = '<div class="p-10 text-center text-ink/40 text-sm">Loading…</div>';
        var title = document.getElementById('recordModalTitle');
        if (title) title.textContent = 'Details';
        closeModal('recordModal');
    };

    window.openRecordModal = function (url) {
        var body = document.getElementById('recordModalBody');
        var title = document.getElementById('recordModalTitle');
        if (!body) return;

        cleanupNestedRecordModals();
        body.innerHTML = '<div class="p-10 text-center text-ink/40 text-sm">Loading…</div>';
        if (title) title.textContent = 'Loading…';
        openModal('recordModal');

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok) throw new Error('Failed to load record');
            return res.text();
        }).then(function (html) {
            body.innerHTML = html;
            var named = body.querySelector('[data-record-title]');
            if (title && named) title.textContent = named.getAttribute('data-record-title') || 'Details';
            portalModals();
        }).catch(function () {
            body.innerHTML = '<div class="p-10 text-center text-accent-700 text-sm">Could not load this record. Please try again.</div>';
            if (title) title.textContent = 'Unavailable';
        });
    };

    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[data-record-modal]');
        if (!link) return;
        e.preventDefault();
        e.stopPropagation();
        var href = link.getAttribute('href');
        if (!href) return;
        var url;
        try { url = new URL(href, window.location.origin); } catch (err) { return; }
        url.searchParams.set('modal', '1');
        openRecordModal(url.href);
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        portalModals();
        var boot = document.getElementById('open-record-bootstrap');
        if (boot && boot.getAttribute('data-url')) {
            openRecordModal(boot.getAttribute('data-url'));
            try {
                var clean = new URL(window.location.href);
                clean.searchParams.delete('open');
                window.history.replaceState({}, '', clean.pathname + (clean.searchParams.toString() ? '?' + clean.searchParams.toString() : '') + clean.hash);
            } catch (err) {}
        }
    });

    /* Outside-click does not close modals — use X / Cancel / Confirm controls only. */

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var top = topOpenModal();
        if (!top) return;
        if (top.id === 'confirmModal') {
            settleConfirm(false);
            return;
        }
        if (top.id === 'recordModal') {
            closeRecordModal();
            return;
        }
        closeModal(top.id);
    });

    /* ---------- Sidebar collapse / hide ---------- */
    var toggleBtn = document.getElementById('sidebar-toggle-btn');

    function syncSidebarToggle(mode) {
        if (!toggleBtn) return;
        var isHidden = mode === 'hidden';
        toggleBtn.title = isHidden ? 'Show sidebar' : 'Hide sidebar';
        toggleBtn.setAttribute('aria-label', toggleBtn.title);
        toggleBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        var path = toggleBtn.querySelector('svg path');
        if (path) {
            path.setAttribute(
                'd',
                isHidden
                    ? 'M4 6h16M4 12h10M4 18h16'
                    : 'M4 6h16M4 12h16M4 18h16'
            );
        }
    }

    function applySidebarMode(mode, animate) {
        if (!animate) root.classList.add('sidebar-booting');

        root.classList.remove('sidebar-collapsed', 'sidebar-hidden');
        document.body.classList.remove('sidebar-collapsed', 'sidebar-hidden');

        if (mode === 'collapsed') {
            root.classList.add('sidebar-collapsed');
        } else if (mode === 'hidden') {
            root.classList.add('sidebar-hidden');
        }

        syncSidebarToggle(mode);

        try { localStorage.setItem(STORAGE_KEY, mode); } catch (err) {}

        if (!animate) {
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    root.classList.remove('sidebar-booting');
                });
            });
        }
    }

    function currentMode() {
        if (root.classList.contains('sidebar-hidden')) return 'hidden';
        if (root.classList.contains('sidebar-collapsed')) return 'collapsed';
        return 'expanded';
    }

    try {
        var saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'collapsed' || saved === 'hidden' || saved === 'expanded') {
            applySidebarMode(saved, false);
        } else {
            root.classList.remove('sidebar-booting');
            syncSidebarToggle('expanded');
        }
    } catch (err) {
        root.classList.remove('sidebar-booting');
        syncSidebarToggle('expanded');
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            applySidebarMode(currentMode() === 'hidden' ? 'expanded' : 'hidden', true);
        });
    }

    var brandMark = document.querySelector('.sidebar-mark');
    if (brandMark) {
        brandMark.addEventListener('click', function () {
            if (currentMode() === 'collapsed') applySidebarMode('expanded', true);
        });
        brandMark.style.cursor = 'pointer';
    }

    /* ---------- Page transfer: fade content only, keep sidebar frozen ---------- */
    document.addEventListener('click', function (e) {
        var link = e.target.closest('a[href]');
        if (!link || link.hasAttribute('download')) return;
        if (link.hasAttribute('data-record-modal')) return;

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        if (link.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        var url;
        try { url = new URL(href, window.location.origin); } catch (err) { return; }
        if (url.origin !== window.location.origin) return;

        var base = <?= json_encode(rtrim((string) (parse_url(config('app.url'), PHP_URL_PATH) ?: ''), '/')) ?>;
        if (base && url.pathname.indexOf(base) !== 0) return;

        var current = window.location.pathname.replace(/\/$/, '') + window.location.search;
        var next = url.pathname.replace(/\/$/, '') + url.search;
        if (next === current) return;

        e.preventDefault();
        root.classList.add('is-leaving', 'sidebar-booting');
        document.body.classList.add('is-leaving');
        window.setTimeout(function () {
            window.location.href = url.href;
        }, 100);
    });

    /* ---------- Toast notifications (flash after CRUD) ---------- */
    var toastHost = document.getElementById('toast-host');
    if (!toastHost) {
        toastHost = document.createElement('div');
        toastHost.id = 'toast-host';
        toastHost.className = 'toast-host';
        toastHost.setAttribute('aria-live', 'polite');
        toastHost.setAttribute('aria-atomic', 'false');
        document.body.appendChild(toastHost);
    }

    var toastIcons = {
        success: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
        error: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>'
    };

    function dismissToast(el) {
        if (!el || el.classList.contains('is-leaving')) return;
        el.classList.remove('is-visible');
        el.classList.add('is-leaving');
        window.setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 280);
    }

    window.showToast = function (type, message, options) {
        options = options || {};
        var kind = type === 'error' ? 'error' : 'success';
        var title = options.title || (kind === 'success' ? 'Success' : 'Something went wrong');
        var duration = typeof options.duration === 'number' ? options.duration : (kind === 'error' ? 5500 : 4200);

        var toast = document.createElement('div');
        toast.className = 'toast toast-' + kind;
        toast.setAttribute('role', kind === 'error' ? 'alert' : 'status');
        toast.innerHTML =
            '<span class="toast-icon" aria-hidden="true">' +
                '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' + toastIcons[kind] + '</svg>' +
            '</span>' +
            '<div class="toast-body">' +
                '<p class="toast-title">' + title + '</p>' +
                '<p class="toast-message"></p>' +
            '</div>' +
            '<button type="button" class="toast-close" aria-label="Dismiss">' +
                '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>' +
                '</svg>' +
            '</button>';

        toast.querySelector('.toast-message').textContent = String(message || '');
        toast.querySelector('.toast-close').addEventListener('click', function () {
            dismissToast(toast);
        });

        toastHost.appendChild(toast);
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                toast.classList.add('is-visible');
            });
        });

        if (duration > 0) {
            window.setTimeout(function () {
                dismissToast(toast);
            }, duration);
        }
    };

    var flashMessages = <?= json_encode(($pendingFlashes ?? []), JSON_UNESCAPED_UNICODE) ?>;
    if (Array.isArray(flashMessages)) {
        flashMessages.forEach(function (flash, index) {
            window.setTimeout(function () {
                showToast(flash.type, flash.message);
            }, index * 120);
        });
    }

    /* ---------- Module list filters ---------- */
    function initModuleFilters(root) {
        if (!root) return;
        var controls = root.querySelectorAll('[data-module-filter]');
        var emptyEl = root.querySelector('[data-filter-empty]');
        var countEl = root.querySelector('[data-filter-count]');
        var clearBtn = root.querySelector('[data-filter-clear]');
        if (!controls.length) return;

        function activeFilters() {
            var filters = {};
            controls.forEach(function (el) {
                var key = el.getAttribute('data-module-filter');
                var value = (el.value || '').trim().toLowerCase();
                if (value !== '') filters[key] = value;
            });
            return filters;
        }

        function itemMatches(item, filters) {
            for (var key in filters) {
                if (!Object.prototype.hasOwnProperty.call(filters, key)) continue;
                var wanted = filters[key];
                if (key === 'q') {
                    var hay = (item.getAttribute('data-filter-search') || item.textContent || '').toLowerCase();
                    if (hay.indexOf(wanted) === -1) return false;
                    continue;
                }
                var attr = (item.getAttribute('data-filter-' + key) || '').toLowerCase();
                var tokens = attr.split(/\s+/).filter(Boolean);
                if (tokens.indexOf(wanted) === -1) return false;
            }
            return true;
        }

        function anyVisible(nodes) {
            var found = false;
            nodes.forEach(function (item) {
                if (!item.hidden) found = true;
            });
            return found;
        }

        function apply() {
            var filters = activeFilters();
            var items = root.querySelectorAll('[data-filter-item]');
            var sections = root.querySelectorAll('[data-filter-section]');
            var visible = 0;

            items.forEach(function (item) {
                var ok = itemMatches(item, filters);
                item.hidden = !ok;
                if (ok) visible += 1;
            });

            sections.forEach(function (section) {
                var sectionItems = section.querySelectorAll('[data-filter-item]');
                section.hidden = sectionItems.length > 0 && !anyVisible(sectionItems);
            });

            /* Hide hollow table / grid shells so empty state is only the message */
            root.querySelectorAll('.module-table-wrap, .directory-grid').forEach(function (shell) {
                var shellItems = shell.querySelectorAll('[data-filter-item]');
                if (!shellItems.length) return;
                shell.hidden = !anyVisible(shellItems);
            });

            /* Facilities keeps the calendar as the empty state — never swap it for a message box */
            var hasCalendar = !!root.querySelector('#jsCalendar, .cal-shell');
            if (emptyEl) emptyEl.hidden = hasCalendar || visible !== 0;
            if (countEl) countEl.textContent = visible + ' of ' + items.length + ' shown';
            if (clearBtn) clearBtn.hidden = Object.keys(filters).length === 0;

            if (typeof window.famsRestagger === 'function') {
                root.querySelectorAll('.module-table-wrap, .directory-grid, .module-insight-panel').forEach(function (shell) {
                    if (!shell.hidden) window.famsRestagger(shell);
                });
            }
        }

        root._moduleFilterApply = apply;

        controls.forEach(function (el) {
            el.addEventListener('input', apply);
            el.addEventListener('change', apply);
        });
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                controls.forEach(function (el) { el.value = ''; });
                apply();
            });
        }
        apply();
    }

    document.querySelectorAll('[data-filter-root]').forEach(initModuleFilters);

    /* ---------- Module / dashboard UI reveals ---------- */
    (function initUiReveals() {
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function staggerRows(root, selector, kpi) {
            if (!root) return;
            var idx = 0;
            root.querySelectorAll(selector).forEach(function (el) {
                if (el.classList.contains('dash-filter-empty')) return;
                el.classList.add('ui-row');
                if (kpi) el.classList.add('ui-row--kpi');
                el.style.animationDelay = Math.min(idx * 0.045, 0.4) + 's';
                idx++;
            });
        }

        window.famsRestagger = function (root) {
            if (reduceMotion || !root) return;
            var idx = 0;
            root.querySelectorAll('.ui-row, .dash-row, .dash-animate-row, .dash-attn-item').forEach(function (el) {
                if (el.hidden || el.style.display === 'none' || el.classList.contains('hidden')) return;
                el.style.animation = 'none';
                void el.offsetWidth;
                el.style.animation = '';
                el.style.animationDelay = (idx * 0.04) + 's';
                idx++;
            });
        };

        var blocks = [];
        document.querySelectorAll(
            '.module-table-wrap, .module-insight-panel, .stat-kpi-grid, .directory-grid, .audit-timeline, .chart-card, .dash-panel'
        ).forEach(function (el) {
            el.classList.add('ui-reveal');
            if (el.classList.contains('chart-card') || el.classList.contains('dash-panel')) {
                el.classList.add('is-reveal');
            }
            staggerRows(el, '.module-table tbody tr');
            staggerRows(el, '.module-feed-item');
            staggerRows(el, '.module-insight-list > li');
            staggerRows(el, '.stat-kpi-card, .module-kpi-card', true);
            if (el.classList.contains('directory-grid')) {
                staggerRows(el, '[data-filter-item]', true);
            }
            if (el.classList.contains('audit-timeline')) {
                staggerRows(el, '.audit-event');
            }
            staggerRows(el, '.dash-row, .dash-animate-row, .dash-attn-item');
            blocks.push(el);
        });

        if (!blocks.length) return;

        if (reduceMotion || !('IntersectionObserver' in window)) {
            blocks.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                io.unobserve(entry.target);
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -20px 0px' });

        blocks.forEach(function (el, i) {
            el.style.animationDelay = ((i % 3) * 0.06) + 's';
            io.observe(el);
        });
    })();
})();
</script>
</body>
</html>
