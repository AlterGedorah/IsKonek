<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

function isAdmin(): bool {
    return strtolower($_SESSION["role"] ?? "") === "admin";
}

function json_success(array $data = []): void {
    echo json_encode(array_merge(['success' => true], $data));
    exit();
}

function json_error(string $message): void {
    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

function notifyEnrolledUsers(mysqli $conn, int $subject_id, int $announcement_id, string $title): void {
    $subj = $conn->prepare("SELECT code FROM subjects WHERE id = ?");
    $subj->bind_param("i", $subject_id);
    $subj->execute();
    $subj_row = $subj->get_result()->fetch_assoc();
    $subj->close();

    $code = $subj_row['code'] ?? 'Subject';
    $message = "New announcement in {$code}: {$title}";

    $enrolled = $conn->prepare("SELECT user_id FROM enrollments WHERE subject_id = ?");
    $enrolled->bind_param("i", $subject_id);
    $enrolled->execute();
    $users = $enrolled->get_result()->fetch_all(MYSQLI_ASSOC);
    $enrolled->close();

    $ins = $conn->prepare("INSERT INTO notifications (user_id, type, reference_id, message) VALUES (?,?,?,?)");
    foreach ($users as $u) {
        $uid  = (int)$u['user_id'];
        $type = 'announcement';
        $ins->bind_param("iiss", $uid, $announcement_id, $type, $message);
        $ins->execute();
    }
    $ins->close();
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

    notifyEnrolledUsers($conn, $subject_id, $new_id, $title);

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