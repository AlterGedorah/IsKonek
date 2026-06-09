<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
  header('Location: dashboard.php');
  exit();
}

$error = '';
$success = '';

// Fetch sections for dropdown
$sections_q = $conn->query("SELECT id, name, course FROM sections ORDER BY name");
$all_sections = $sections_q ? $sections_q->fetch_all(MYSQLI_ASSOC) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = trim($_POST['full_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $student_id = trim($_POST['student_id'] ?? '');
  $course = trim($_POST['course'] ?? '');
  $year = trim($_POST['year'] ?? '');
  $section = trim($_POST['section'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm_pass = $_POST['confirm_password'] ?? '';

  if (empty($full_name) || empty($email) || empty($password) || empty($student_id)) {
    $error = 'Please fill in all required fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } elseif (strlen($password) < 8) {
    $error = 'Password must be at least 8 characters.';
  } elseif ($password !== $confirm_pass) {
    $error = 'Passwords do not match.';
  } else {
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
      $error = 'An account with this email already exists.';
    } else {
        // Get section info
        $section_name = '';
        $section_course = '';
        if ($section_id > 0) {
            $sec_q = $conn->prepare("SELECT name, course FROM sections WHERE id = ?");
            $sec_q->bind_param("i", $section_id);
            $sec_q->execute();
            $sec_row = $sec_q->get_result()->fetch_assoc();
            $sec_q->close();
            if ($sec_row) {
                $section_name   = $sec_row['name'];
                $section_course = $sec_row['course'];
            }
        }

        $role_str = $section_course ? 'Student - ' . $section_course . ' ' . $section_name : 'Student';

        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'An account with this email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, student_id, role, section, password, created_at) VALUES (?,?,?,?,?,?,NOW())");
            $stmt->bind_param("ssssss", $full_name, $email, $student_id, $role_str, $section_name, $hashed);

            if ($stmt->execute()) {
                $new_user_id = $conn->insert_id;
                $stmt->close();

                // Auto-enroll into section subjects if section selected
                if ($section_id > 0) {
                    $subj_q = $conn->prepare("SELECT subject_id FROM section_subjects WHERE section_id = ?");
                    $subj_q->bind_param("i", $section_id);
                    $subj_q->execute();
                    $subj_res = $subj_q->get_result();
                    $enroll = $conn->prepare("INSERT IGNORE INTO enrollments (user_id, subject_id) VALUES (?,?)");
                    while ($row = $subj_res->fetch_assoc()) {
                        $sid = (int)$row['subject_id'];
                        $enroll->bind_param("ii", $new_user_id, $sid);
                        $enroll->execute();
                    }
                    $enroll->close();
                    $subj_q->close();
                }

                $success = 'Registration successful! You can now sign in.';
            } else {
                $error = 'Registration failed. Please try again.';
                $stmt->close();
            }
        }
        $check->close();
    }
    $check->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
  <div class="login-wrapper">
    <div class="login-card" style="max-width:480px;">
      <div class="d-flex align-items-center gap-3 mb-4">
        <div class="login-logo d-flex align-items-center justify-content-center">
          <span class="fw-bold fs-4" style="font-family:'Poppins',sans-serif;">IK</span>
        </div>
        <div>
          <h5 class="mb-0 fw-medium" style="font-family:'Poppins',sans-serif;">Iskonek</h5>
          <p class="mb-0 text-muted" style="font-size:13px;">Create your account</p>
        </div>
      </div>

      <?php if ($error): ?>
        <div class="alert-error mb-3"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="mb-3 p-3 rounded-3" style="background:#f0faf5;border:1px solid #b3e6cc;color:#1a7a4c;font-size:13px;">
          <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
          <a href="index.php" class="fw-medium ms-1 text-decoration-none" style="color:#1a7a4c;">Sign in →</a>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label small fw-medium">Full Name <span class="text-danger">*</span></label>
            <input type="text" name="full_name" class="form-control" placeholder="Juan Dela Cruz"
              value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label small fw-medium">Email Address <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="student@school.edu.ph"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-medium">Student ID <span class="text-danger">*</span></label>
            <input type="text" name="student_id" class="form-control" placeholder="2024-XXXXX"
              value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-medium">Course</label>
            <select name="course" class="form-select">
              <option value="BSIT" <?= ($_POST['course'] ?? '') === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
              <option value="BSCS" <?= ($_POST['course'] ?? '') === 'BSCS' ? 'selected' : '' ?>>BSCS</option>
              <option value="BSIS" <?= ($_POST['course'] ?? '') === 'BSIS' ? 'selected' : '' ?>>BSIS</option>
              <option value="ACT" <?= ($_POST['course'] ?? '') === 'ACT' ? 'selected' : '' ?>>ACT</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small fw-medium">Year</label>
            <input type="number" name="year" class="form-control" placeholder="e.g. 2"
              value="<?= htmlspecialchars($_POST['year'] ?? '') ?>">
            <label class="form-label small fw-medium">Section</label>
            <input type="number" name="section" class="form-control" placeholder="e.g. 1"
              value="<?= htmlspecialchars($_POST['section'] ?? '') ?>">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-medium">Password <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-medium">Confirm Password <span class="text-danger">*</span></label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
          </div>
          <div class="col-12 mt-1">
            <button type="submit" class="btn-login">Create Account</button>
          </div>
        </div>
      </form>

      <p class="text-center text-muted mt-4 mb-0" style="font-size:13px;">
        Already have an account? <a href="index.php" class="text-decoration-none fw-medium"
          style="color:var(--accent-green);">Sign in</a>
      </p>
    </div>

    <?php if ($error): ?>
      <div class="alert-error mb-3"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="mb-3 p-3 rounded-3" style="background:#f0faf5;border:1px solid #b3e6cc;color:#1a7a4c;font-size:13px;">
        <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
        <a href="index.php" class="fw-medium ms-1 text-decoration-none" style="color:#1a7a4c;">Sign in →</a>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label small fw-medium">Full Name <span class="text-danger">*</span></label>
          <input type="text" name="full_name" class="form-control" placeholder="Juan Dela Cruz"
                 value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
        </div>
        <div class="col-12">
          <label class="form-label small fw-medium">Email Address <span class="text-danger">*</span></label>
          <input type="email" name="email" class="form-control" placeholder="student@school.edu.ph"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="col-12">
          <label class="form-label small fw-medium">Student ID <span class="text-danger">*</span></label>
          <input type="text" name="student_id" class="form-control" placeholder="2024-XXXXX"
                 value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
        </div>
        <div class="col-12">
          <label class="form-label small fw-medium">Section</label>
          <select name="section_id" class="form-select">
            <option value="0">— No section yet —</option>
            <?php foreach ($all_sections as $sec): ?>
              <option value="<?= $sec['id'] ?>"
                <?= ((int)($_POST['section_id'] ?? 0) === (int)$sec['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($sec['course'] . ' – ' . $sec['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text" style="font-size:11px;">Selecting a section auto-enrolls you in its subjects.</div>
        </div>
        <div class="col-sm-6">
          <label class="form-label small fw-medium">Password <span class="text-danger">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
        </div>
        <div class="col-sm-6">
          <label class="form-label small fw-medium">Confirm Password <span class="text-danger">*</span></label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
        </div>
        <div class="col-12 mt-1">
          <button type="submit" class="btn-login">Create Account</button>
        </div>
      </div>
    </form>

    <p class="text-center text-muted mt-4 mb-0" style="font-size:13px;">
      Already have an account? <a href="index.php" class="text-decoration-none fw-medium" style="color:var(--accent-green);">Sign in</a>
    </p>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>