<nav class="top-navbar d-flex align-items-center justify-content-between px-4">
    <!-- Mobile toggle -->
    <button class="btn btn-link d-lg-none p-0 me-3 text-dark" id="sidebarToggle">
        <i class="bi bi-list fs-4"></i>
    </button>

    <!-- Spacer -->
    <div class="flex-grow-1"></div>

    <!-- Search -->
    <div class="search-wrapper me-3">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0">
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" class="form-control border-start-0 ps-0" placeholder="Search..." id="globalSearch">
        </div>
    </div>

    <!-- Notifications -->
<div class="d-flex align-items-center gap-3">
    <div class="dropdown position-relative">
        <button class="btn btn-link text-dark p-0" id="notifBtn"
                data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
            <i class="bi bi-bell fs-5"></i>
        </button>
        <span id="notifBadge"
              class="position-absolute badge rounded-pill bg-danger"
              style="font-size:9px;top:0;right:0;transform:translate(40%,-30%);display:none;min-width:16px;padding:2px 4px;pointer-events:none;">
            0
        </span>
        <div class="dropdown-menu dropdown-menu-end shadow p-0" id="notifDropdown"
             style="width:340px;border-radius:16px;border:1px solid var(--border-color);overflow:hidden;">
            <div class="d-flex align-items-center justify-content-between px-3 py-2"
                 style="border-bottom:1px solid var(--border-color);">
                <span class="fw-medium" style="font-family:var(--font-heading);font-size:14px;">Notifications</span>
                <button class="btn btn-link p-0 text-muted" id="markAllReadBtn" style="font-size:12px;text-decoration:none;">
                    Mark all as read
                </button>
            </div>
            <div id="notifList" style="max-height:320px;overflow-y:auto;">
                <div class="text-center text-muted py-4" style="font-size:13px;" id="notifEmpty">
                    <i class="bi bi-bell-slash d-block mb-2 fs-4 opacity-50"></i>
                    No notifications
                </div>
            </div>
            <div style="border-top:1px solid var(--border-color);">
                <a href="announcements.php" class="d-block text-center py-2 small text-decoration-none"
                   style="color:var(--accent-green);font-size:13px;">View announcements</a>
            </div>
        </div>
    </div>

        <!-- Account dropdown -->
        <div class="dropdown">
            <button class="btn btn-link p-0 text-dark" data-bs-toggle="dropdown">
                <div class="avatar-circle-sm d-flex align-items-center justify-content-center">
                    <i class="bi bi-person-fill"></i>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><h6 class="dropdown-header">
                    <?= isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'User' ?>
                </h6></li>
                <li><a class="dropdown-item small" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item small text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
    <script>
(function () {
    const badge    = document.getElementById('notifBadge');
    const list     = document.getElementById('notifList');
    const emptyEl  = document.getElementById('notifEmpty');
    const markAll  = document.getElementById('markAllReadBtn');
    const notifBtn = document.getElementById('notifBtn');

    const typeIcon = {
        announcement: { icon: 'bi-megaphone-fill',     color: '#33b77a' },
        reply:        { icon: 'bi-chat-dots-fill',      color: '#4a90d9' },
        deadline:     { icon: 'bi-calendar-event-fill', color: '#d23c3c' }
    };

    function loadNotifications() {
        fetch('actions/notification_actions.php?action=get_notifications')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                renderNotifications(data.notifications, data.unread);
            })
            .catch(() => {});
    }

    function renderNotifications(notifications, unread) {
        badge.textContent   = unread > 99 ? '99+' : unread;
        badge.style.display = unread > 0 ? '' : 'none';
        list.innerHTML = '';

        if (!notifications || notifications.length === 0) {
            list.appendChild(emptyEl);
            return;
        }

        notifications.forEach(n => {
            const meta     = typeIcon[n.type] || { icon: 'bi-bell-fill', color: '#888' };
            const isUnread = n.is_read == 0;
            const item     = document.createElement('div');
            item.style.cssText = `border-bottom:1px solid var(--border-color);background:${isUnread ? '#f9fffe' : '#fff'};cursor:pointer;transition:background 0.15s;`;
            item.className     = 'd-flex align-items-start gap-2 px-3 py-2';
            item.dataset.id    = n.id;
            item.dataset.link  = n.link || '';
            item.dataset.read  = n.is_read;
            item.innerHTML = `
                <div class="mt-1 flex-shrink-0 d-flex align-items-center justify-content-center"
                     style="width:32px;height:32px;border-radius:50%;background:${meta.color}15;">
                    <i class="bi ${meta.icon}" style="color:${meta.color};font-size:14px;"></i>
                </div>
                <div class="flex-grow-1" style="min-width:0;">
                    <p class="mb-0" style="font-size:13px;line-height:1.4;${isUnread ? 'font-weight:500;' : 'color:#555;'}">${n.message.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p>
                    <p class="mb-0 text-muted" style="font-size:11px;margin-top:2px;">${n.time}</p>
                </div>
                ${isUnread ? `<div class="flex-shrink-0 mt-2" style="width:7px;height:7px;border-radius:50%;background:var(--accent-green);"></div>` : ''}
            `;
            item.addEventListener('click', function () {
                if (this.dataset.read == 0) {
                    const fd = new FormData();
                    fd.append('action', 'mark_read');
                    fd.append('notification_id', this.dataset.id);
                    fetch('actions/notification_actions.php', { method: 'POST', body: fd })
                        .then(() => loadNotifications()).catch(() => {});
                }
                if (this.dataset.link) window.location.href = this.dataset.link;
            });
            list.appendChild(item);
        });
    }

    markAll.addEventListener('click', function (e) {
        e.stopPropagation();
        const fd = new FormData();
        fd.append('action', 'mark_all_read');
        fetch('actions/notification_actions.php', { method: 'POST', body: fd })
            .then(() => loadNotifications()).catch(() => {});
    });

    notifBtn.addEventListener('click', loadNotifications);
    loadNotifications();
})();
</script>
</nav>
