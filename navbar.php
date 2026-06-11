<?php
$notif_count = 0;
$notifications = [];

if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];

    $nq = $conn->prepare("
        SELECT id, type, reference_id, message, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $nq->bind_param("i", $uid);
    $nq->execute();
    $notifications = $nq->get_result()->fetch_all(MYSQLI_ASSOC);
    $nq->close();

    $notif_count = array_reduce($notifications, fn($c, $n) => $c + ($n['is_read'] ? 0 : 1), 0);
}

function notifTimeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    return date('M j', strtotime($datetime));
}

function notifLink(string $type, int $ref_id): string {
    if ($type === 'announcement') return 'announcements.php';
    if ($type === 'event')        return 'calendar.php';
    return '#';
}

function notifIcon(string $type): string {
    if ($type === 'announcement') return 'bi-megaphone-fill text-success';
    if ($type === 'event')        return 'bi-calendar-event-fill text-primary';
    return 'bi-bell-fill';
}
?>
<nav class="top-navbar d-flex align-items-center justify-content-between px-4">
    <button class="btn btn-link d-lg-none p-0 me-3 text-dark" id="sidebarToggle">
        <i class="bi bi-list fs-4"></i>
    </button>

    <div class="flex-grow-1"></div>

    <div class="search-wrapper me-3">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0">
                <i class="bi bi-search text-muted"></i>
            </span>
            <input type="text" class="form-control border-start-0 ps-0" placeholder="Search..." id="globalSearch">
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- Notifications -->
        <div class="position-relative" id="notifWrapper">
            <button class="btn btn-link text-dark p-0" id="notifBtn" data-bs-toggle="dropdown" aria-expanded="false"
                    onclick="markAllRead()">
                <i class="bi bi-bell fs-5"></i>
                <?php if ($notif_count > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                          id="notifBadge" style="font-size:9px;"><?= $notif_count ?></span>
                <?php else: ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                          id="notifBadge" style="font-size:9px;display:none;">0</span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow p-0" style="width:320px;border-radius:16px;overflow:hidden;">
                <li>
                    <h6 class="dropdown-header px-4 py-3 mb-0"
                        style="font-family:var(--font-heading);font-size:14px;font-weight:600;color:var(--text-primary);background:#fafafa;border-bottom:1px solid var(--border-color);">
                        Notifications
                    </h6>
                </li>
                <?php if (empty($notifications)): ?>
                    <li>
                        <div class="px-4 py-5 text-center text-muted" style="font-size:13px;">
                            <i class="bi bi-bell-slash d-block mb-2" style="font-size:24px;opacity:0.4;"></i>
                            No notifications yet
                        </div>
                    </li>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <li>
                            <a class="dropdown-item px-4 py-3 d-flex align-items-start gap-3 notif-item
                                      <?= !$notif['is_read'] ? 'notif-unread' : '' ?>"
                               href="<?= notifLink($notif['type'], (int)$notif['reference_id']) ?>"
                               data-id="<?= $notif['id'] ?>"
                               style="border-bottom:1px solid var(--border-color);white-space:normal;">
                                <i class="bi <?= notifIcon($notif['type']) ?> mt-1 flex-shrink-0" style="font-size:15px;"></i>
                                <div style="flex:1;min-width:0;">
                                    <p class="mb-0" style="font-size:13px;color:var(--text-primary);line-height:1.4;">
                                        <?= htmlspecialchars($notif['message']) ?>
                                    </p>
                                    <p class="mb-0 mt-1" style="font-size:11px;color:var(--text-muted);">
                                        <?= notifTimeAgo($notif['created_at']) ?>
                                    </p>
                                </div>
                                <?php if (!$notif['is_read']): ?>
                                    <span class="flex-shrink-0 mt-1" style="width:8px;height:8px;border-radius:50%;background:var(--accent-green);display:inline-block;"></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <li>
                    <a class="dropdown-item text-center py-3" href="#"
                       style="font-size:13px;color:var(--accent-green);font-weight:500;border-top:1px solid var(--border-color);">
                        View all notifications
                    </a>
                </li>
            </ul>
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
</nav>

<style>
.notif-unread { background: #f9fffe; }
.notif-item:hover { background: #f0faf5 !important; }
</style>

<script>
function markAllRead() {
    fetch('actions/notification_actions.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'mark_all_read' })
    }).then(() => {
        document.getElementById('notifBadge').style.display = 'none';
        document.querySelectorAll('.notif-unread').forEach(el => {
            el.classList.remove('notif-unread');
            const dot = el.querySelector('span[style*="border-radius:50%"]');
            if (dot) dot.remove();
        });
    });
}
</script>
