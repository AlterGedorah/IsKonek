<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
  header('Location: dashboard.php');
  exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name   = trim($_POST['fname'] ?? '');
  $mid_name     = trim($_POST['mname'] ?? '');
  $last_name    = trim($_POST['lname'] ?? '');
  $email        = trim($_POST['email'] ?? '');
  $student_id   = trim($_POST['student_id'] ?? '');
  $course       = trim($_POST['course'] ?? '');
  $year         = trim($_POST['year'] ?? '');
  $section_num  = trim($_POST['section'] ?? ''); 
  $password     = $_POST['password'] ?? '';
  $confirm_pass = $_POST['confirm_password'] ?? '';

  if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($student_id)) {
    $error = 'Please fill in all required fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } elseif (strlen($password) < 8) {
    $error = 'Password must be at least 8 characters.';
  } elseif ($password !== $confirm_pass) {
    $error = 'Passwords do not match.';
  } else {
    // Check if the email is already in use
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
      $error = 'An account with this email already exists.';
      $check->close();
    } else {
      $check->close();
      
      // NEW: Automatically generate the standard section text representation string (e.g., "BSIT 2-1")
      $computed_section = '';
      if (!empty($course) && !empty($year) && !empty($section_num)) {
         $computed_section = $course . ' ' . $year . '-' . $section_num;
      }

      $role_str = 'Student';
      $hashed = password_hash($password, PASSWORD_BCRYPT);

      // FIXED: Aligned names and variable arguments accurately to match your query structural requirements
      $stmt = $conn->prepare("INSERT INTO users (first_name, mid_name, last_name, email, student_id, year, program, section, role, password, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
      $stmt->bind_param("sssssissss", $first_name, $mid_name, $last_name, $email, $student_id, $year, $course, $computed_section, $role_str, $hashed);

      if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;
        $stmt->close();

        // NEW: Handles dynamic enrollment by matching your standard computed section identifier back to the sections table
        if (!empty($computed_section)) {
          $sec_lookup = $conn->prepare("SELECT id FROM sections WHERE name = ?");
          $sec_lookup->bind_param("s", $computed_section);
          $sec_lookup->execute();
          $sec_row = $sec_lookup->get_result()->fetch_assoc();
          $sec_lookup->close();

          if ($sec_row) {
            $section_db_id = (int)$sec_row['id'];
            
            $subj_q = $conn->prepare("SELECT subject_id FROM section_subjects WHERE section_id = ?");
            $subj_q->bind_param("i", $section_db_id);
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
        }

        $success = 'Registration successful! You can now sign in.';
      } else {
        $error = 'Registration failed. Please try again.';
        $stmt->close();
      }
    }
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
</head>

<body>
  <div class="login-wrapper py-5">
    <div class="login-card" style="max-width: 850px; width: 100%;">
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
        <div class="row g-4 mb-3">

          <div class="col-md-6 d-flex flex-column justify-content-between gap-3">
            <div>
              <label class="form-label small fw-medium">Name Fields <span class="text-danger">*</span></label>
              <div class="row g-2">
                <div class="col-4">
                  <input type="text" name="fname" class="form-control" placeholder="First Name"
                    value="<?= htmlspecialchars($_POST['fname'] ?? '') ?>" required>
                </div>
                <div class="col-4">
                  <input type="text" name="mname" class="form-control" placeholder="Middle Init."
                    value="<?= htmlspecialchars($_POST['mname'] ?? '') ?>">
                </div>
                <div class="col-4">
                  <input type="text" name="lname" class="form-control" placeholder="Last Name"
                    value="<?= htmlspecialchars($_POST['lname'] ?? '') ?>" required>
                </div>
              </div>
            </div>

            <div>
              <label class="form-label small fw-medium">Email Address <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" placeholder="student@school.edu.ph"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="row g-2">
              <div class="col-6">
                <label class="form-label small fw-medium">Password <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" placeholder="Min 8 characters" required>
              </div>
              <div class="col-6">
                <label class="form-label small fw-medium">Confirm Password <span class="text-danger">*</span></label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
              </div>
            </div>
          </div>

          <div class="col-md-6 d-flex flex-column justify-content-between gap-3">
            <div>
              <label class="form-label small fw-medium">Student ID <span class="text-danger">*</span></label>
              <input type="text" name="student_id" class="form-control" placeholder="2024-XXXXX"
                value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
            </div>

            <div>
              <label class="form-label small fw-medium">Course / Program</label>
              <select name="course" class="form-select">
                <option value="BSIT" <?= ($_POST['course'] ?? '') === 'BSIT' ? 'selected' : '' ?>>BSIT</option>
                <option value="BSCS" <?= ($_POST['course'] ?? '') === 'BSCS' ? 'selected' : '' ?>>BSCS</option>
                <option value="BSIS" <?= ($_POST['course'] ?? '') === 'BSIS' ? 'selected' : '' ?>>BSIS</option>
                <option value="ACT" <?= ($_POST['course'] ?? '') === 'ACT' ? 'selected' : '' ?>>ACT</option>
              </select>
            </div>

            <div class="row g-2">
              <div class="col-6">
                <label class="form-label small fw-medium">Year Level</label>
                <input type="number" name="year" class="form-control" min="1" max="5" placeholder="e.g. 2"
                  value="<?= htmlspecialchars($_POST['year'] ?? '') ?>">
              </div>
              <div class="col-6">
                <label class="form-label small fw-medium">Section</label>
                <input type="number" name="section" class="form-control" placeholder="e.g. 1"
                  value="<?= htmlspecialchars($_POST['section'] ?? '') ?>">
              </div>
            </div>
          </div>

        </div>

        <div class="row">
          <div class="col-12 text-center mt-3">
            <button type="submit" class="btn-login w-100 py-2">Create Account</button>
          </div>
        </div>
      </form>

      <p class="text-center text-muted mt-4 mb-0" style="font-size:13px;">
        Already have an account? <a href="index.php" class="text-decoration-none fw-medium" style="color:var(--accent-green);">Sign in</a>
      </p>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>