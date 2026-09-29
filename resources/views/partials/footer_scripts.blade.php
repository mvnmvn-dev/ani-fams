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
        searchResults.addEventListener('click', function (e) {
            var link = e.target.closest('a[href="#sessions"]');
            if (!link) return;
            e.preventDefault();
            searchResults.hidden = true;
            if (typeof openModal === 'function') openModal('sessionsModal');
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
        // Nested action modals (party, cost, etc.) must sit above the open record detail.
        if (id !== 'recordModal' && id !== 'confirmModal') {
            var record = document.getElementById('recordModal');
            if (record && record.classList.contains('is-open')) {
                modal.style.zIndex = '1030';
            }
        }
        document.body.classList.add('modal-open');
        requestAnimationFrame(function () {
            modal.querySelectorAll('canvas[data-signature-pad]').forEach(function (canvas) {
                prepareSignatureCanvas(canvas);
            });
        });
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
            if (form.hasAttribute('data-ajax-action')) {
                window.submitAjaxForm(form);
                return;
            }
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
    var nestedRecordModalIds = ['accessModal', 'versionModal', 'amendModal', 'partyModal', 'documentModal', 'milestoneModal', 'costModal', 'linkVendorModal'];

    function cleanupNestedRecordModals() {
        nestedRecordModalIds.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.remove();
        });
        document.querySelectorAll('[id^="signModal_"]').forEach(function (el) {
            el.remove();
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

    window.openRecordModal = function (url, options) {
        options = options || {};
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
            if (options.esign) {
                var signModal = document.querySelector('[id^="signModal_"][data-esign-modal]');
                if (signModal) {
                    setTimeout(function () {
                        openModal(signModal.id);
                    }, 50);
                }
            }
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
            openRecordModal(boot.getAttribute('data-url'), {
                esign: boot.getAttribute('data-esign') === '1'
            });
            try {
                var clean = new URL(window.location.href);
                clean.searchParams.delete('open');
                clean.searchParams.delete('esign');
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
        if (link.hasAttribute('data-export-link')) return;

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        if (link.target === '_blank' || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        var url;
        try { url = new URL(href, window.location.origin); } catch (err) { return; }
        if (url.origin !== window.location.origin) return;
        // Never intercept file downloads (CSV/PDF export, attachments).
        if (url.pathname.indexOf('/export') !== -1) return;
        if (/\.(pdf|csv|xlsx|ics)(\?|$)/i.test(url.pathname + url.search)) return;

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

    /* ---------- Module list filters + 10-per-page pagination ---------- */
    var LIST_PAGE_SIZE = 10;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function formatVisitStamp(iso) {
        if (!iso) return '';
        var d = new Date(String(iso).replace(' ', 'T'));
        if (isNaN(d.getTime())) return String(iso);
        return d.toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
    }

    /** Ensure list rows are marked for filter/pagination (covers plain tables). */
    function ensureFilterItems(scope) {
        if (!scope || !scope.querySelectorAll) return;
        scope.querySelectorAll('.module-table-wrap tbody > tr, .directory-grid > .directory-card, [data-paginate-shell] > *').forEach(function (el) {
            if (el.classList.contains('dash-filter-empty')) return;
            if (el.matches('tr') && el.querySelector('td[colspan]') && (el.textContent || '').toLowerCase().indexOf('no ') !== -1) return;
            if (!el.hasAttribute('data-filter-item')) {
                el.setAttribute('data-filter-item', '');
            }
        });
    }

    function refreshFilterRoots(scope) {
        var roots = [];
        if (scope && scope.closest) {
            var root = scope.closest('[data-filter-root]');
            if (root) roots = [root];
        }
        if (!roots.length) {
            roots = Array.prototype.slice.call(document.querySelectorAll('[data-filter-root]'));
        }
        roots.forEach(function (root) {
            if (typeof root._moduleFilterApply === 'function') root._moduleFilterApply();
        });
    }

    function applyAjaxDomUpdate(form, data) {
        var action = (data && data.action) || form.getAttribute('data-ajax-action') || '';
        var row = form.closest('[data-filter-item], tr, .directory-card, .visitor-onsite-card');

        if (action === 'reservation.decide' && row) {
            row.remove();
            refreshFilterRoots(document);
            return;
        }

        if (action === 'reservation.checkin') {
            var codeInput = form.querySelector('input[name="code"]');
            if (codeInput) codeInput.value = '';
            return;
        }

        if ((action === 'visitor.checkin' || action === 'visitor.checkout') && row) {
            var status = data.status || (action === 'visitor.checkin' ? 'checked_in' : 'checked_out');
            var visitId = data.id || row.getAttribute('data-visit-id');
            var visitorsBase = <?= json_encode(url('/visitors')) ?>;
            var token = csrfToken();

            function updateVisitRow(target) {
                if (!target) return;
                target.setAttribute('data-filter-status', status);

                var badge = target.querySelector('[data-status-badge]');
                if (badge) {
                    badge.textContent = status.replace(/_/g, ' ');
                    badge.className = 'inline-block px-2.5 py-1 text-[11px] border rounded-md capitalize ' + (
                        status === 'checked_in' ? 'bg-brand-50 text-brand-700 border-brand-200'
                        : status === 'checked_out' ? 'bg-gray-100 text-gray-700 border-gray-200'
                        : 'bg-gray-50 border-gray-200'
                    );
                }

                var timeCell = target.querySelector('[data-visit-times]');
                if (timeCell) {
                    function stampParts(iso) {
                        var d = new Date(String(iso).replace(' ', 'T'));
                        if (isNaN(d.getTime())) return { day: String(iso), time: '' };
                        return {
                            day: d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }),
                            time: d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
                        };
                    }
                    var html = timeCell.innerHTML;
                    if (status === 'checked_in' && data.check_in_time) {
                        var inn = stampParts(data.check_in_time);
                        html = '<div class="text-brand-700 font-medium">In: ' + escapeHtml(inn.day) + '</div>' +
                            '<div class="text-brand-700/80 text-[10px]">' + escapeHtml(inn.time) + '</div>';
                    }
                    if (status === 'checked_out' && data.check_out_time) {
                        var out = stampParts(data.check_out_time);
                        if (html.indexOf('In:') === -1) html = '<div class="text-ink/30">—</div>';
                        html += '<div class="text-ink/50 mt-1">Out: ' + escapeHtml(out.day) + '</div>' +
                            '<div class="text-ink/40 text-[10px]">' + escapeHtml(out.time) + '</div>';
                    }
                    timeCell.innerHTML = html;
                }

                var actions = target.querySelector('[data-row-actions]');
                if (actions) {
                    var watchForm = actions.querySelector('[data-ajax-action="visitor.watchlist"]');
                    var watchHtml = watchForm ? watchForm.outerHTML : '';
                    if (status === 'checked_in' && visitId) {
                        actions.innerHTML =
                            '<form action="' + visitorsBase + '/' + visitId + '/check-out" method="POST" class="inline" data-ajax-action="visitor.checkout">' +
                            '<input type="hidden" name="_token" value="' + escapeHtml(token) + '">' +
                            '<button type="submit" class="text-xs font-bold text-ink hover:text-ink/80 mr-2">Check Out</button></form> ' +
                            watchHtml;
                    } else if (status === 'checked_out') {
                        actions.innerHTML = watchHtml || '<span class="text-ink/30">—</span>';
                    }
                }
            }

            var tableRow = visitId
                ? document.querySelector('tr[data-visit-id="' + visitId + '"]')
                : (row.tagName === 'TR' ? row : null);
            updateVisitRow(tableRow || (row.tagName === 'TR' ? row : null));

            if (status === 'checked_out' && visitId) {
                var onsite = document.querySelector('.visitor-onsite-card[data-visit-id="' + visitId + '"]');
                if (onsite) onsite.remove();
            }
            refreshFilterRoots(tableRow || row);
            return;
        }

        if (action === 'visitor.watchlist') {
            var visitorId = data.visitor_id;
            if (!visitorId) {
                var host = form.closest('[data-visitor-id]');
                if (host) visitorId = host.getAttribute('data-visitor-id');
            }
            var watched = !!data.is_watchlisted;
            document.querySelectorAll('[data-visitor-id="' + visitorId + '"]').forEach(function (el) {
                el.setAttribute('data-filter-watch', watched ? 'yes' : 'no');
                el.classList.toggle('is-watch', watched);
                var watchBadge = el.querySelector('[data-watch-badge]');
                if (watchBadge) watchBadge.hidden = !watched;
                var watchForm = el.querySelector('[data-ajax-action="visitor.watchlist"]');
                if (watchForm) {
                    var input = watchForm.querySelector('input[name="watchlist"]');
                    if (input) input.value = watched ? '0' : '1';
                    var btn = watchForm.querySelector('button');
                    if (btn) btn.textContent = watched ? 'Unwatch' : 'Watch';
                }
            });
            refreshFilterRoots(form);
            return;
        }

        if (action === 'retention.dispose' && row) {
            var st = data.status || 'disposed';
            row.setAttribute('data-filter-status', st);
            var sBadge = row.querySelector('[data-status-badge]');
            if (sBadge) {
                sBadge.textContent = st.replace(/_/g, ' ');
            }
            var rActions = row.querySelector('[data-row-actions]');
            if (rActions) rActions.innerHTML = '<span class="text-ink/30">—</span>';
            refreshFilterRoots(row);
            return;
        }

        if (action === 'retention.release' && row) {
            row.setAttribute('data-filter-status', 'released');
            var holdBadge = row.querySelector('[data-status-badge]');
            if (holdBadge) holdBadge.textContent = 'released';
            var holdActions = row.querySelector('[data-row-actions]');
            if (holdActions) holdActions.innerHTML = '<span class="text-xs text-ink/40">Released</span>';
            refreshFilterRoots(row);
            return;
        }

        if (action === 'user.toggle') {
            var userId = data.id;
            var card = document.querySelector('.directory-card[data-user-id="' + userId + '"]');
            if (card && data.status) {
                card.setAttribute('data-filter-status', data.status);
                card.classList.toggle('is-inactive', data.status !== 'active');
                var statusEl = card.querySelector('[data-status-badge]');
                if (statusEl) {
                    statusEl.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                    statusEl.className = 'px-2.5 py-1 rounded-md text-xs border font-semibold ' + (
                        data.status === 'active'
                            ? 'bg-brand-50 text-brand-700 border-brand-300'
                            : 'bg-ink/5 text-ink/50 border-ink/10'
                    );
                }
                var toggleBtn = card.querySelector('[data-user-toggle]');
                if (toggleBtn) {
                    var next = data.status === 'active' ? 'Suspend' : 'Reactivate';
                    var nameEl = card.querySelector('h3');
                    var nameText = nameEl ? nameEl.childNodes[0].textContent.trim() : 'User';
                    toggleBtn.textContent = next;
                    toggleBtn.className = 'text-xs font-bold px-3 py-2 rounded-lg ' + (
                        data.status === 'active' ? 'text-accent-700 hover:bg-accent-50' : 'text-brand-700 hover:bg-brand-50'
                    ) + ' transition';
                    toggleBtn.setAttribute('onclick',
                        'openStatusModal(' + userId + ', ' + JSON.stringify(nameText) + ', ' + JSON.stringify(data.status) + ')'
                    );
                }
            }
            closeModal('statusUserModal');
            refreshFilterRoots(card || form);
            return;
        }

        if (action === 'contract.approve') {
            if (data.id && data.status) {
                var crow = document.querySelector('[data-contract-id="' + data.id + '"]');
                if (crow) {
                    crow.setAttribute('data-filter-status', data.status);
                    var cBadge = crow.querySelector('[data-status-badge]');
                    if (cBadge) cBadge.textContent = String(data.status).replace(/_/g, ' ');
                }
            }
            var reloadUrl = form.getAttribute('data-ajax-reload-modal');
            if (reloadUrl && typeof window.openRecordModal === 'function') {
                window.openRecordModal(reloadUrl);
            }
            refreshFilterRoots(document);
            return;
        }

        if (action === 'legal.milestone.toggle') {
            var mReload = form.getAttribute('data-ajax-reload-modal');
            if (mReload && typeof window.openRecordModal === 'function') {
                window.openRecordModal(mReload);
            }
            return;
        }
    }

    window.submitAjaxForm = function (form, submitter) {
        if (!form || form.getAttribute('data-ajax-busy') === '1') return Promise.resolve();
        form.setAttribute('data-ajax-busy', '1');

        var buttons = form.querySelectorAll('button, input[type="submit"]');
        buttons.forEach(function (btn) { btn.disabled = true; });

        var fd = new FormData(form);
        if (submitter && submitter.name) {
            fd.set(submitter.name, submitter.value);
        }

        var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timedOut = false;
        var timer = window.setTimeout(function () {
            timedOut = true;
            if (controller) controller.abort();
        }, 20000);

        function unlock() {
            window.clearTimeout(timer);
            form.removeAttribute('data-ajax-busy');
            buttons.forEach(function (btn) { btn.disabled = false; });
        }

        return fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(),
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
                'Accept': 'application/json'
            },
            body: fd,
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined
        }).then(function (res) {
            return res.text().then(function (text) {
                var data = {};
                try { data = text ? JSON.parse(text) : {}; } catch (err) { data = {}; }
                return { ok: res.ok, status: res.status, data: data, raw: text };
            });
        }).then(function (result) {
            unlock();
            var d = result.data || {};
            var succeeded = result.ok && d.success === true;
            if (succeeded) {
                if (d.message) showToast('success', d.message);
                applyAjaxDomUpdate(form, d);
            } else {
                showToast('error', d.error || d.message || (timedOut ? 'Request timed out. Please try again.' : 'Action failed.'));
            }
        }).catch(function (err) {
            unlock();
            showToast('error', timedOut || (err && err.name === 'AbortError')
                ? 'Request timed out. Please try again.'
                : 'Network error. Please try again.');
        });
    };

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form && form.hasAttribute('data-ajax-action')) {
            e.preventDefault();
            window.submitAjaxForm(form, e.submitter);
        }
    });

    // Clear stuck leave/busy states after soft navigation or bfcache restore.
    function resetInteractiveUi() {
        root.classList.remove('is-leaving', 'sidebar-booting');
        document.body.classList.remove('is-leaving');
        document.querySelectorAll('[data-ajax-busy]').forEach(function (el) {
            el.removeAttribute('data-ajax-busy');
            el.querySelectorAll('button[disabled], input[type="submit"][disabled]').forEach(function (btn) {
                btn.disabled = false;
            });
        });
        document.querySelectorAll('.ui-reveal').forEach(function (el) {
            el.classList.add('is-visible');
        });
    }
    window.addEventListener('pageshow', resetInteractiveUi);
    window.addEventListener('load', resetInteractiveUi);
    resetInteractiveUi();
    /* ---------- Global Signature Pad Handler ---------- */
    var activeSignature = null;

    function prepareSignatureCanvas(canvas) {
        if (!canvas) return;
        var parent = canvas.parentElement;
        var cssW = Math.max(parent ? parent.clientWidth : 0, 280);
        var cssH = 180;
        var ratio = window.devicePixelRatio || 1;
        canvas.width = Math.floor(cssW * ratio);
        canvas.height = Math.floor(cssH * ratio);
        canvas.style.width = cssW + 'px';
        canvas.style.height = cssH + 'px';
        var ctx = canvas.getContext('2d');
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2.25;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#0f172a';
        ctx.clearRect(0, 0, cssW, cssH);
        canvas._initialized = true;
        canvas._hasDrawn = false;
        canvas._isDrawing = false;
    }

    function getCanvasCoordinates(canvas, e) {
        var rect = canvas.getBoundingClientRect();
        var scaleX = (canvas.width / (window.devicePixelRatio || 1)) / rect.width;
        var scaleY = (canvas.height / (window.devicePixelRatio || 1)) / rect.height;
        var clientX = e.clientX;
        var clientY = e.clientY;
        if (e.touches && e.touches.length > 0) {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        } else if (e.changedTouches && e.changedTouches.length > 0) {
            clientX = e.changedTouches[0].clientX;
            clientY = e.changedTouches[0].clientY;
        }
        return [
            (clientX - rect.left) * scaleX,
            (clientY - rect.top) * scaleY
        ];
    }

    function initCanvasCtx(canvas) {
        if (!canvas._initialized) {
            prepareSignatureCanvas(canvas);
        }
        return canvas.getContext('2d');
    }

    document.addEventListener('mousedown', function(e) {
        var canvas = e.target.closest('canvas[data-signature-pad]');
        if (!canvas) return;
        activeSignature = canvas;
        var ctx = initCanvasCtx(canvas);
        canvas._isDrawing = true;
        canvas._hasDrawn = true;
        var coords = getCanvasCoordinates(canvas, e);
        canvas._lastX = coords[0];
        canvas._lastY = coords[1];
        ctx.beginPath();
        ctx.arc(coords[0], coords[1], 0.6, 0, Math.PI * 2);
        ctx.fillStyle = '#0f172a';
        ctx.fill();
    });

    document.addEventListener('mousemove', function(e) {
        if (!activeSignature || !activeSignature._isDrawing) return;
        var canvas = activeSignature;
        var ctx = canvas.getContext('2d');
        var coords = getCanvasCoordinates(canvas, e);
        ctx.beginPath();
        ctx.moveTo(canvas._lastX, canvas._lastY);
        ctx.lineTo(coords[0], coords[1]);
        ctx.stroke();
        canvas._lastX = coords[0];
        canvas._lastY = coords[1];
    });

    document.addEventListener('mouseup', function(e) {
        if (activeSignature) activeSignature._isDrawing = false;
        activeSignature = null;
    });

    document.addEventListener('touchstart', function(e) {
        var canvas = e.target.closest('canvas[data-signature-pad]');
        if (!canvas) return;
        e.preventDefault();
        activeSignature = canvas;
        var ctx = initCanvasCtx(canvas);
        canvas._isDrawing = true;
        canvas._hasDrawn = true;
        var coords = getCanvasCoordinates(canvas, e);
        canvas._lastX = coords[0];
        canvas._lastY = coords[1];
        ctx.beginPath();
        ctx.arc(coords[0], coords[1], 0.6, 0, Math.PI * 2);
        ctx.fillStyle = '#0f172a';
        ctx.fill();
    }, { passive: false });

    document.addEventListener('touchmove', function(e) {
        if (!activeSignature || !activeSignature._isDrawing) return;
        e.preventDefault();
        var canvas = activeSignature;
        var ctx = canvas.getContext('2d');
        var coords = getCanvasCoordinates(canvas, e);
        ctx.beginPath();
        ctx.moveTo(canvas._lastX, canvas._lastY);
        ctx.lineTo(coords[0], coords[1]);
        ctx.stroke();
        canvas._lastX = coords[0];
        canvas._lastY = coords[1];
    }, { passive: false });

    document.addEventListener('touchend', function(e) {
        if (activeSignature) activeSignature._isDrawing = false;
        activeSignature = null;
    });

    window.clearSignaturePad = function(id) {
        var canvas = document.getElementById(id);
        if (!canvas) return;
        prepareSignatureCanvas(canvas);
    };

    window.submitSignaturePad = function(form, canvasId, inputId) {
        var canvas = document.getElementById(canvasId);
        var input = document.getElementById(inputId);
        if (!canvas || !canvas._hasDrawn) {
            alert('Please draw your signature before submitting.');
            return false;
        }
        if (input) input.value = canvas.toDataURL('image/png');
        return true;
    };

    function initModuleFilters(root) {
        if (!root) return;
        ensureFilterItems(root);
        var controls = root.querySelectorAll('[data-module-filter]');
        var emptyEl = root.querySelector('[data-filter-empty]');
        var countEl = root.querySelector('[data-filter-count]');
        var clearBtn = root.querySelector('[data-filter-clear]');
        var pageState = { page: 1 };

        function paginateShells() {
            return Array.prototype.filter.call(
                root.querySelectorAll('.module-table-wrap, .directory-grid, [data-paginate-shell]'),
                function (shell) {
                    return !shell.closest('#jsCalendar, .cal-shell');
                }
            );
        }

        function ensurePager(shell) {
            var pager = shell.parentElement && shell.parentElement.querySelector(':scope > .module-pagination');
            if (pager && pager._forShell === shell) return pager;
            // Prefer pager immediately after shell
            var next = shell.nextElementSibling;
            if (next && next.classList.contains('module-pagination')) {
                next._forShell = shell;
                return next;
            }
            pager = document.createElement('div');
            pager.className = 'module-pagination';
            pager._forShell = shell;
            pager.hidden = true;
            shell.insertAdjacentElement('afterend', pager);
            return pager;
        }

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

        function renderPager(pager, page, totalPages, onChange) {
            if (totalPages <= 1) {
                pager.hidden = true;
                pager.innerHTML = '';
                return;
            }
            pager.hidden = false;
            var html = '';
            html += '<button type="button" class="module-pagination-btn" data-page="prev"' + (page <= 1 ? ' disabled' : '') + '>Prev</button>';
            html += '<span class="module-pagination-meta">Page ' + page + ' of ' + totalPages + '</span>';
            html += '<button type="button" class="module-pagination-btn" data-page="next"' + (page >= totalPages ? ' disabled' : '') + '>Next</button>';
            pager.innerHTML = html;
            pager.querySelectorAll('button[data-page]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (btn.disabled) return;
                    var dir = btn.getAttribute('data-page');
                    onChange(dir === 'next' ? page + 1 : page - 1);
                });
            });
        }

        function apply() {
            ensureFilterItems(root);
            var filters = activeFilters();
            var items = root.querySelectorAll('[data-filter-item]');
            var sections = root.querySelectorAll('[data-filter-section]');
            var matched = [];

            items.forEach(function (item) {
                var ok = itemMatches(item, filters);
                item.setAttribute('data-filter-matched', ok ? '1' : '0');
                if (ok) matched.push(item);
                // Hide non-matches immediately; pagination will reveal a page of matches
                item.hidden = !ok;
            });

            var shells = paginateShells();
            var visible = 0;

            if (shells.length) {
                shells.forEach(function (shell) {
                    var shellItems = Array.prototype.filter.call(
                        shell.querySelectorAll('[data-filter-item]'),
                        function (item) { return item.getAttribute('data-filter-matched') === '1'; }
                    );
                    var key = shell.getAttribute('data-page-key') || (shell.className + ':' + Array.prototype.indexOf.call(shells, shell));
                    if (!pageState[key]) pageState[key] = 1;
                    var totalPages = Math.max(1, Math.ceil(shellItems.length / LIST_PAGE_SIZE));
                    if (pageState[key] > totalPages) pageState[key] = totalPages;
                    var page = pageState[key];
                    var start = (page - 1) * LIST_PAGE_SIZE;
                    var end = start + LIST_PAGE_SIZE;

                    shellItems.forEach(function (item, idx) {
                        var onPage = idx >= start && idx < end;
                        item.hidden = !onPage;
                        if (onPage) visible += 1;
                    });

                    // Non-matched already hidden
                    shell.hidden = shellItems.length === 0;
                    var pager = ensurePager(shell);
                    renderPager(pager, page, totalPages, function (nextPage) {
                        pageState[key] = nextPage;
                        apply();
                    });
                });

                // Items outside shells (e.g. calendar events): keep filter-only behavior
                items.forEach(function (item) {
                    if (item.closest('.module-table-wrap, .directory-grid, [data-paginate-shell]')) return;
                    if (item.getAttribute('data-filter-matched') === '1') {
                        item.hidden = false;
                        visible += 1;
                    }
                });
            } else {
                // No dedicated shell — still paginate flat item lists in groups of 10
                var totalPages = Math.max(1, Math.ceil(matched.length / LIST_PAGE_SIZE));
                if (pageState.page > totalPages) pageState.page = totalPages;
                var page = pageState.page || 1;
                var start = (page - 1) * LIST_PAGE_SIZE;
                var end = start + LIST_PAGE_SIZE;
                matched.forEach(function (item, idx) {
                    var onPage = idx >= start && idx < end;
                    item.hidden = !onPage;
                    if (onPage) visible += 1;
                });
                var flatPagerHost = root.querySelector('[data-filter-empty]') || root.lastElementChild || root;
                var pager = ensurePager(flatPagerHost);
                renderPager(pager, page, totalPages, function (nextPage) {
                    pageState.page = nextPage;
                    apply();
                });
            }

            sections.forEach(function (section) {
                var sectionItems = section.querySelectorAll('[data-filter-item]');
                var any = false;
                sectionItems.forEach(function (item) {
                    if (item.getAttribute('data-filter-matched') === '1') any = true;
                });
                section.hidden = sectionItems.length > 0 && !any;
            });

            var hasCalendar = !!root.querySelector('#jsCalendar, .cal-shell');
            if (emptyEl) emptyEl.hidden = hasCalendar || matched.length !== 0;
            if (countEl) {
                var showing = Math.min(LIST_PAGE_SIZE, matched.length - ((pageState.page || 1) - 1) * LIST_PAGE_SIZE);
                if (shells.length) showing = visible;
                countEl.textContent = matched.length + ' match' + (matched.length === 1 ? '' : 'es') + ' · ' + Math.max(0, showing) + ' on this page';
            }
            if (clearBtn) clearBtn.hidden = Object.keys(filters).length === 0;

            if (typeof window.famsRestagger === 'function') {
                root.querySelectorAll('.module-table-wrap, .directory-grid, .module-insight-panel, [data-paginate-shell]').forEach(function (shell) {
                    if (!shell.hidden) window.famsRestagger(shell);
                });
            }
        }

        root._moduleFilterApply = apply;

        // Reset to page 1 when filters change
        controls.forEach(function (el) {
            el.addEventListener('input', function () {
                Object.keys(pageState).forEach(function (k) { if (k !== 'page') pageState[k] = 1; });
                apply();
            });
            el.addEventListener('change', function () {
                Object.keys(pageState).forEach(function (k) { if (k !== 'page') pageState[k] = 1; });
                apply();
            });
        });
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                controls.forEach(function (el) { el.value = ''; });
                Object.keys(pageState).forEach(function (k) { if (k !== 'page') pageState[k] = 1; });
                apply();
            });
        }
        apply();
    }

    document.querySelectorAll('[data-filter-root]').forEach(initModuleFilters);

    // Auto-wrap orphan module tables (no data-filter-root yet) so they still paginate at 10/page.
    document.querySelectorAll('.module-table-wrap, .directory-grid, [data-paginate-shell]').forEach(function (shell) {
        if (shell.closest('[data-filter-root]')) return;
        if (shell.closest('#jsCalendar, .cal-shell, .dash-panel')) return;
        var wrap = document.createElement('div');
        wrap.setAttribute('data-filter-root', '');
        shell.parentNode.insertBefore(wrap, shell);
        wrap.appendChild(shell);
        initModuleFilters(wrap);
    });

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

        // Interactive tables must be usable immediately (Approve / Check In / Reject).
        blocks.forEach(function (el) {
            if (el.classList.contains('module-table-wrap') || el.querySelector('[data-ajax-action], [data-row-actions]')) {
                el.classList.add('is-visible');
            }
        });

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
        }, { threshold: 0.05, rootMargin: '40px 0px 40px 0px' });

        blocks.forEach(function (el, i) {
            if (el.classList.contains('is-visible')) return;
            el.style.animationDelay = ((i % 3) * 0.06) + 's';
            io.observe(el);
        });

        // Failsafe: never leave actionable UI stuck invisible after soft navigation.
        window.setTimeout(function () {
            blocks.forEach(function (el) { el.classList.add('is-visible'); });
            try { io.disconnect(); } catch (err) {}
        }, 500);
    })();

    /* ---------- Real-time notifications polling ---------- */
    function pollNotifications() {
        fetch('<?= url("/notifications/poll") ?>', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var badgeBtn = document.getElementById('alerts-btn');
            if (badgeBtn) {
                var badge = badgeBtn.querySelector('.app-topbar-badge');
                if (data.total > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'app-topbar-badge';
                        badgeBtn.appendChild(badge);
                    }
                    badge.textContent = data.total > 9 ? '9+' : data.total;
                } else if (badge) {
                    badge.remove();
                }
            }
            var subtitle = document.querySelector('#alerts-menu .app-notif-subtitle');
            if (subtitle) {
                subtitle.textContent = data.total > 0 
                    ? (data.total === 1 ? '1 unread message' : data.total + ' unread messages')
                    : 'No new messages';
            }
            var list = document.querySelector('#alerts-menu .app-notif-list');
            if (list) {
                if (data.alerts.length === 0) {
                    list.innerHTML = '<div class="app-notif-empty"><p class="app-notif-empty-title">You\'re all caught up</p><p class="app-notif-empty-text">New system messages will appear here.</p></div>';
                } else {
                    var html = '';
                    var notifIcons = {
                        'amber': 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                        'sky': 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-4.13a4 4 0 11-8 0 4 4 0 018 0zm6 4a4 4 0 10-8 0',
                        'purple': 'M9 17v-2a4 4 0 014-4h4m0 0l-3-3m3 3l-3 3M7 7h.01M7 3h5a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z'
                    };
                    data.alerts.forEach(function(note) {
                        var tone = note.module === 'reservations' ? 'sky' : (note.module === 'contracts' ? 'amber' : 'purple');
                        var iconPath = notifIcons['amber'];
                        html += '<a href="' + escapeHtml(note.link) + '" class="app-notif-item ' + (!note.is_read ? 'is-unread' : '') + '" data-nav-link>';
                        html += '<span class="app-notif-icon tone-' + tone + '"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' + iconPath + '"/></svg></span>';
                        html += '<span class="app-notif-body"><span class="app-notif-item-top"><span class="app-notif-item-title">' + escapeHtml(note.title) + '</span>';
                        if (!note.is_read) {
                            html += '<span class="app-notif-dot" aria-hidden="true"></span>';
                        }
                        html += '</span><span class="app-notif-message">' + escapeHtml(note.body) + '</span>';
                        html += '<span class="app-notif-meta"><span>' + escapeHtml(note.module.charAt(0).toUpperCase() + note.module.slice(1)) + '</span><span aria-hidden="true">·</span><span>' + escapeHtml(note.time) + '</span></span></span></a>';
                    });
                    list.innerHTML = html;
                }
            }
        })
        .catch(function(err) { /* ignore */ });
    }
    if (document.getElementById('alerts-btn')) {
        setInterval(pollNotifications, 15000);
    }
})();
</script>
</body>
</html>
