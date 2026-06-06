<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$action  = $_POST['action'] ?? '';

if ($action === 'update_profile') {
    $full_name  = trim($_POST['full_name']  ?? '');
    $email      = trim($_POST['email']      ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $section    = trim($_POST['section']    ?? '');
    $avatar_data = $_POST['avatar_data']   ?? '';

    if (!$full_name || !$email) {
        header('Location: ../settings.php?success=&error=profile&tab=profile');
        exit();
    }

    $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $check->bind_param("si", $email, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        header('Location: ../settings.php?error=profile&tab=profile');
        exit();
    }
    $check->close();

    // Handle avatar upload (base64 → file)
    $avatar_path = null;
    if (!empty($avatar_data) && strpos($avatar_data, 'data:image') === 0) {
        $upload_dir = '../assets/avatars/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext  = 'jpg';
        $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
        $data = preg_replace('/^data:image\/\w+;base64,/', '', $avatar_data);
        file_put_contents($upload_dir . $filename, base64_decode($data));
        $avatar_path = 'assets/avatars/' . $filename;
    }

    if ($avatar_path) {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, student_id=?, section=?, avatar=? WHERE id=?");
        $stmt->bind_param("sssssi", $full_name, $email, $student_id, $section, $avatar_path, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, student_id=?, section=? WHERE id=?");
        $stmt->bind_param("ssssi", $full_name, $email, $student_id, $section, $user_id);
    }

    if ($stmt->execute()) {
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email']     = $email;
        $_SESSION['section']   = $section;
        header('Location: ../settings.php?success=profile&tab=profile');
    } else {
        header('Location: ../settings.php?error=profile&tab=profile');
    }
    $stmt->close();
    exit();
}

if ($action === 'change_password') {
    $current  = $_POST['current_password']  ?? '';
    $new_pass = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    if (strlen($new_pass) < 8 || $new_pass !== $confirm) {
        header('Location: ../settings.php?error=password&tab=password');
        exit();
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!password_verify($current, $row['password'])) {
        header('Location: ../settings.php?error=password&tab=password');
        exit();
    }

    $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
    $upd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
    $upd->bind_param("si", $hashed, $user_id);

    if ($upd->execute()) {
        header('Location: ../settings.php?success=password&tab=password');
    } else {
        header('Location: ../settings.php?error=password&tab=password');
    }
    $upd->close();
    exit();
}

header('Location: ../settings.php');
exit();
