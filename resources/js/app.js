import './bootstrap';

// Progressive UI behavior; authorization and all mutations remain server-side.
const ui = () => {
    const sidebar = document.getElementById('workspace-sidebar');
    const sidebarNavigation = document.querySelector('.sidebar-navigation');
    const navigationUser = sidebarNavigation?.dataset.navUser ?? 'anonymous';
    const collapsedPreferenceKey = `gwo.sidebar.collapsed.${navigationUser}`;
    const groupPreferenceKey = `gwo.sidebar.expanded-group.${navigationUser}`;
    const menuButton = document.querySelector('[data-sidebar-open]');
    const mobile = window.matchMedia('(max-width: 820px)');
    const collapseButton = document.querySelector('[data-sidebar-toggle]');
    const readPreference = (key) => { try { return localStorage.getItem(key); } catch { return null; } };
    const savePreference = (key, value) => { try { localStorage.setItem(key, value); } catch { /* Storage can be disabled. */ } };
    const setCollapsed = (collapsed) => {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        collapseButton?.setAttribute('aria-expanded', String(!collapsed));
        collapseButton?.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    };
    setCollapsed(readPreference(collapsedPreferenceKey) === 'true');
    collapseButton?.addEventListener('click', () => {
        const collapsed = !document.body.classList.contains('sidebar-collapsed');
        setCollapsed(collapsed);
        savePreference(collapsedPreferenceKey, String(collapsed));
    });
    const menuGroups = [...document.querySelectorAll('[data-nav-group]')];
    const activeGroup = menuGroups.find((group) => group.querySelector('[aria-current="page"]'));
    const savedGroup = menuGroups.find((group) => group.dataset.navGroup === readPreference(groupPreferenceKey));
    let expandedGroup = activeGroup ?? savedGroup ?? menuGroups[0] ?? null;
    let syncingMenuGroups = false;
    const syncMenuGroups = () => {
        const compact = !mobile.matches && document.body.classList.contains('sidebar-collapsed');
        syncingMenuGroups = true;
        menuGroups.forEach((group) => { group.open = compact || group === expandedGroup; });
        requestAnimationFrame(() => { syncingMenuGroups = false; });
    };
    menuGroups.forEach((group) => {
        group.addEventListener('toggle', () => {
            if (syncingMenuGroups || (!mobile.matches && document.body.classList.contains('sidebar-collapsed'))) return;
            if (group.open) {
                expandedGroup = group;
                savePreference(groupPreferenceKey, group.dataset.navGroup);
                syncingMenuGroups = true;
                menuGroups.forEach((other) => { if (other !== group) other.open = false; });
                requestAnimationFrame(() => { syncingMenuGroups = false; });
            } else if (expandedGroup === group) {
                expandedGroup = null;
                savePreference(groupPreferenceKey, '');
            }
        });
    });
    collapseButton?.addEventListener('click', syncMenuGroups);
    mobile.addEventListener('change', syncMenuGroups);
    syncMenuGroups();
    const setSidebar = (open) => {
        document.body.classList.toggle('sidebar-open', open);
        menuButton?.setAttribute('aria-expanded', String(open));
        if (sidebar) sidebar.inert = mobile.matches && !open;
        if (open) sidebar?.querySelector('a')?.focus();
    };
    const syncSidebar = () => { if (!mobile.matches) setSidebar(false); else if (sidebar) sidebar.inert = !document.body.classList.contains('sidebar-open'); };
    syncSidebar();
    mobile.addEventListener('change', syncSidebar);
    document.querySelectorAll('[data-sidebar-open]').forEach((button) => button.addEventListener('click', () => setSidebar(true)));
    document.querySelectorAll('[data-sidebar-close]').forEach((button) => button.addEventListener('click', () => { setSidebar(false); menuButton?.focus(); }));
    const dialog = document.getElementById('workspace-command');
    const search = dialog?.querySelector('[data-command-search]');
    const items = [...(dialog?.querySelectorAll('[data-command-item]') ?? [])];
    let commandTrigger;
    const filterCommands = () => {
        const query = search?.value.trim().toLocaleLowerCase() ?? '';
        items.forEach((item) => { item.hidden = !item.textContent.toLocaleLowerCase().includes(query); });
        const empty = dialog?.querySelector('.command-empty');
        if (empty) empty.hidden = items.some((item) => !item.hidden);
    };
    const openCommands = (trigger) => {
        if (!dialog) return;
        commandTrigger = trigger ?? document.activeElement;
        if (search) search.value = '';
        filterCommands();
        dialog.showModal();
        search?.focus();
    };
    document.querySelectorAll('[data-command-open]').forEach((button) => button.addEventListener('click', () => openCommands(button)));
    document.querySelectorAll('[data-command-close]').forEach((button) => button.addEventListener('click', () => dialog?.close()));
    dialog?.addEventListener('close', () => commandTrigger?.focus());
    dialog?.addEventListener('click', (event) => { if (event.target === dialog) { const box = dialog.getBoundingClientRect(); if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close(); } });
    search?.addEventListener('input', filterCommands);
    dialog?.addEventListener('keydown', (event) => {
        const visible = items.filter((item) => !item.hidden);
        if (event.key === 'Enter' && document.activeElement === search && visible[0]) { event.preventDefault(); visible[0].click(); }
        if (['ArrowDown', 'ArrowUp'].includes(event.key) && visible.length) {
            event.preventDefault();
            const current = visible.indexOf(document.activeElement);
            const next = event.key === 'ArrowDown' ? (current + 1) % visible.length : (current - 1 + visible.length) % visible.length;
            visible[next].focus();
        }
    });
    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k' && dialog) { event.preventDefault(); dialog.open ? dialog.close() : openCommands(); }
        if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) { setSidebar(false); menuButton?.focus(); }
        if (event.key === 'Tab' && document.body.classList.contains('sidebar-open') && sidebar) {
            const focusable = [...sidebar.querySelectorAll('a,button,input,select')].filter((element) => element.getClientRects().length && !element.disabled);
            const first = focusable[0], last = focusable.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    const confirmDialog = document.getElementById('workspace-confirm');
    let pendingForm, pendingSubmitter;
    document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
        if (!confirmDialog || form.dataset.confirmed === 'true') return;
        event.preventDefault();
        pendingForm = form; pendingSubmitter = event.submitter;
        confirmDialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
        confirmDialog.showModal();
        confirmDialog.querySelector('[data-confirm-cancel]').focus();
    }));
    confirmDialog?.querySelector('[data-confirm-cancel]').addEventListener('click', () => confirmDialog.close());
    confirmDialog?.querySelector('[data-confirm-accept]').addEventListener('click', () => {
        const form = pendingForm;
        if (!form) return;
        form.dataset.confirmed = 'true';
        confirmDialog.close();
        form.requestSubmit(pendingSubmitter ?? undefined);
        delete form.dataset.confirmed;
    });
    confirmDialog?.addEventListener('close', () => pendingSubmitter?.focus());
    document.querySelectorAll('[data-password-toggle]').forEach((button) => button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(reveal));
    }));
    document.querySelectorAll('[data-dismiss-notice]').forEach((button) => button.addEventListener('click', () => button.closest('.notice')?.remove()));
    const flashMessages = [...document.querySelectorAll('.notice.success, .notice.error, .alert-success, .alert-danger, [role="alert"], [data-auto-dismiss], .bg-green-50, .bg-red-50')];
    if (flashMessages.length) {
        const popup = document.createElement('div');
        popup.className = 'workspace-flash-popup';
        popup.setAttribute('aria-live', 'polite');
        popup.setAttribute('aria-relevant', 'additions');
        document.body.append(popup);
        flashMessages.forEach((notice) => {
            popup.append(notice);
            window.setTimeout(() => {
                notice.remove();
                if (!popup.childElementCount) popup.remove();
            }, 5000);
        });
    }
    const notificationBell = document.querySelector('[data-notification-bell]');
    const notificationBadge = notificationBell?.querySelector('[data-notification-count]');
    const notificationToast = document.querySelector('[data-notification-toast]');
    const notificationToastLink = notificationToast?.querySelector('[data-notification-toast-link]');
    const notificationToastTitle = notificationToast?.querySelector('[data-notification-toast-title]');
    const notificationToastBody = notificationToast?.querySelector('[data-notification-toast-body]');
    let unreadNotifications = Number(notificationBell?.dataset.unread ?? 0);
    let notificationToastTimer;
    document.querySelector('[data-notification-toast-close]')?.addEventListener('click', () => { notificationToast.hidden = true; });
    const refreshNotifications = async () => {
        if (!notificationBell || document.visibilityState === 'hidden') return;
        try {
            const response = await fetch(notificationBell.dataset.feedUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) return;
            const data = await response.json();
            const count = Number(data.unread ?? 0);
            if (notificationBadge) {
                notificationBadge.textContent = count > 99 ? '99+' : String(count);
                notificationBadge.hidden = count === 0;
            }
            if (count > unreadNotifications && notificationToast && Array.isArray(data.items)) {
                const newest = data.items.find((item) => item.unread);
                if (newest) {
                    notificationToastTitle.textContent = newest.title || 'New notification';
                    notificationToastBody.textContent = newest.body || '';
                    try {
                        const target = new URL(newest.url || notificationBell.href, window.location.origin);
                        notificationToastLink.href = target.origin === window.location.origin ? target.href : notificationBell.href;
                    } catch { notificationToastLink.href = notificationBell.href; }
                    notificationToast.hidden = false;
                    clearTimeout(notificationToastTimer);
                    notificationToastTimer = setTimeout(() => { notificationToast.hidden = true; }, 5000);
                }
            }
            unreadNotifications = count;
            notificationBell.dataset.unread = String(count);
        } catch { /* Notification polling is optional; the inbox remains available. */ }
    };
    if (notificationBell) {
        window.setInterval(refreshNotifications, 30000);
        document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') refreshNotifications(); });
    }
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ui); else ui();
