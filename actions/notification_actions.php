<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$user_id = (int) $_SESSION['user_id'];
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

function json_success(array $data = []): void {
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

function json_error(string $message): void {
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

// ── Check & insert deadline notifications ─────────────────────
// Called on every page load via get_notifications.
// Inserts a notification if a kanban task is due within 3 days
// and one hasn't already been created for it.
function checkDeadlines(mysqli $conn, int $user_id): void {
    $stmt = $conn->prepare("
        SELECT id, title, due_date
        FROM kanban_tasks
        WHERE user_id = ?
          AND status != 'done'
          AND due_date IS NOT NULL
          AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
          AND id NOT IN (
              SELECT CAST(link AS UNSIGNED)
              FROM notifications
              WHERE user_id = ? AND type = 'deadline'
                AND link REGEXP '^[0-9]+$'
          )
    ");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($tasks)) return;

    $ins = $conn->prepare("
        INSERT INTO notifications (user_id, type, message, link)
        VALUES (?, 'deadline', ?, ?)
    ");
    foreach ($tasks as $task) {
        $days_left = (int) ceil((strtotime($task['due_date']) - strtotime('today')) / 86400);
        $label     = $days_left === 0 ? 'due today' : "due in {$days_left} day" . ($days_left > 1 ? 's' : '');
        $message   = "Task \"{$task['title']}\" is {$label}.";
        $link      = (string) $task['id']; // store task id as link reference
        $ins->bind_param("iss", $user_id, $message, $link);
        $ins->execute();
    }
    $ins->close();
}

// ── get_notifications ─────────────────────────────────────────
if ($action === 'get_notifications') {
    // First check for any upcoming deadlines
    checkDeadlines($conn, $user_id);

    $stmt = $conn->prepare("
        SELECT id, type, message, link, is_read, created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 30
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Unread count
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) AS cnt FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $count_stmt->bind_param("i", $user_id);
    $count_stmt->execute();
    $unread = (int) $count_stmt->get_result()->fetch_assoc()['cnt'];
    $count_stmt->close();

    // Format timestamps
    foreach ($notifications as &$n) {
        $diff = time() - strtotime($n['created_at']);
        if ($diff < 60)        $n['time'] = 'just now';
        elseif ($diff < 3600)  $n['time'] = floor($diff / 60) . 'm ago';
        elseif ($diff < 86400) $n['time'] = floor($diff / 3600) . 'h ago';
        else                   $n['time'] = date('M j', strtotime($n['created_at']));

        // Build proper href for deadline type
        if ($n['type'] === 'deadline') {
            $n['link'] = 'pages/kanban.php';
        }
    }
    unset($n);

    json_success(['notifications' => $notifications, 'unread' => $unread]);
}

// ── mark_read ─────────────────────────────────────────────────
if ($action === 'mark_read') {
    $notif_id = (int) ($_POST['notification_id'] ?? 0);
    if (!$notif_id) json_error('Invalid notification.');

    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    $stmt->execute();
    $stmt->close();

    json_success();
}

// ── mark_all_read ─────────────────────────────────────────────
if ($action === 'mark_all_read') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    json_success();
}

json_error('Unknown action.');