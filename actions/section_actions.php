<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$is_admin = strtolower($_SESSION['role'] ?? '') === 'admin';
if (!$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit();
}

$action = $_POST['action'] ?? '';

function json_success(): void {
    echo json_encode(['success' => true]);
    exit();
}

function json_error(string $msg): void {
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

function syncSubjects(mysqli $conn, int $section_id, array $subject_ids): void {
    $conn->prepare("DELETE FROM section_subjects WHERE section_id = ?")->execute() || true;
    $del = $conn->prepare("DELETE FROM section_subjects WHERE section_id = ?");
    $del->bind_param("i", $section_id);
    $del->execute();
    $del->close();

    if (!empty($subject_ids)) {
        $ins = $conn->prepare("INSERT IGNORE INTO section_subjects (section_id, subject_id) VALUES (?,?)");
        foreach ($subject_ids as $sid) {
            $sid = (int)$sid;
            $ins->bind_param("ii", $section_id, $sid);
            $ins->execute();
        }
        $ins->close();
    }
}

if ($action === 'add_section') {
    $name    = trim($_POST['name']   ?? '');
    $course  = trim($_POST['course'] ?? '');
    $subjects = $_POST['subjects']   ?? [];

    if (!$name || !$course) json_error('Section name and course are required.');

    $check = $conn->prepare("SELECT id FROM sections WHERE name = ?");
    $check->bind_param("s", $name);
    $check->execute();
    if ($check->get_result()->num_rows > 0) json_error('Section already exists.');
    $check->close();

    $stmt = $conn->prepare("INSERT INTO sections (name, course) VALUES (?,?)");
    $stmt->bind_param("ss", $name, $course);
    if (!$stmt->execute()) json_error('Failed to add section.');
    $section_id = $conn->insert_id;
    $stmt->close();

    syncSubjects($conn, $section_id, $subjects);
    json_success();
}

if ($action === 'edit_section') {
    $id      = (int)($_POST['section_id'] ?? 0);
    $name    = trim($_POST['name']        ?? '');
    $course  = trim($_POST['course']      ?? '');
    $subjects = $_POST['subjects']        ?? [];

    if (!$id || !$name || !$course) json_error('Section name and course are required.');

    $check = $conn->prepare("SELECT id FROM sections WHERE name = ? AND id != ?");
    $check->bind_param("si", $name, $id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) json_error('Section name already used.');
    $check->close();

    $stmt = $conn->prepare("UPDATE sections SET name=?, course=? WHERE id=?");
    $stmt->bind_param("ssi", $name, $course, $id);
    if (!$stmt->execute()) json_error('Failed to update section.');
    $stmt->close();

    syncSubjects($conn, $id, $subjects);
    json_success();
}

if ($action === 'delete_section') {
    $id = (int)($_POST['section_id'] ?? 0);
    if (!$id) json_error('Invalid section.');

    $stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
    $stmt->bind_param("i", $id);
    if (!$stmt->execute() || $stmt->affected_rows === 0) json_error('Failed to delete section.');
    $stmt->close();

    json_success();
}

json_error('Unknown action.');
