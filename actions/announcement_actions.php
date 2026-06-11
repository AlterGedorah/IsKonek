<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

function isAdmin(): bool {
    return in_array(strtolower($_SESSION["role"] ?? ""), ["admin", "sup_admin"]);
}

function json_success(array $data = []): void {
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

function json_error(string $message): void {
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($datetime));
}

if ($action === 'create_announcement') {
    if (!isAdmin()) {
        json_error('Only admins can post announcements.');
    }

    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $title      = trim($_POST['title'] ?? '');
    $body       = trim($_POST['body']  ?? '');

    if (!$subject_id || empty($title)) {
        json_error('Subject and title are required.');
    }

    $stmt = $conn->prepare("INSERT INTO announcements (subject_id, title, body, created_by) VALUES (?,?,?,?)");
    $stmt->bind_param("issi", $subject_id, $title, $body, $user_id);
    if (!$stmt->execute()) {
        json_error('Failed to post announcement.');
    }
    $new_id = $conn->insert_id;
    $stmt->close();

    // Notify all enrolled users in this subject
$subj_stmt = $conn->prepare("SELECT code FROM subjects WHERE id = ?");
$subj_stmt->bind_param("i", $subject_id);
$subj_stmt->execute();
$subj_row = $subj_stmt->get_result()->fetch_assoc();
$subj_stmt->close();
$subject_code = $subj_row['code'] ?? 'a subject';

$enrolled = $conn->prepare("SELECT user_id FROM enrollments WHERE subject_id = ? AND user_id != ?");
$enrolled->bind_param("ii", $subject_id, $user_id);
$enrolled->execute();
$enrolled_users = $enrolled->get_result()->fetch_all(MYSQLI_ASSOC);
$enrolled->close();

if (!empty($enrolled_users)) {
    $notif_msg  = "New announcement in {$subject_code}: \"{$title}\"";
    $notif_link = "announcements.php";
    $ins = $conn->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, 'announcement', ?, ?)");
    foreach ($enrolled_users as $eu) {
        $ins->bind_param("iss", $eu['user_id'], $notif_msg, $notif_link);
        $ins->execute();
    }
    $ins->close();
}
    json_success(['announcement_id' => $new_id]);
}

if ($action === 'delete_announcement') {
    if (!isAdmin()) {
        json_error('Only admins can delete announcements.');
    }

    $ann_id = (int)($_POST['announcement_id'] ?? 0);
    if (!$ann_id) json_error('Invalid announcement.');

    $del = $conn->prepare("DELETE FROM announcements WHERE id = ?");
    $del->bind_param("i", $ann_id);
    if (!$del->execute() || $del->affected_rows === 0) {
        json_error('Could not delete announcement.');
    }
    $del->close();

    json_success();
}

json_error('Unknown action.');