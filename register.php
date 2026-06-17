<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
  header('Location: dashboard.php');
  exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name = trim($_POST['fname'] ?? '');
  $mid_name = trim($_POST['mname'] ?? '');
  $last_name = trim($_POST['lname'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $student_id = trim($_POST['student_id'] ?? '');
  $course = trim($_POST['course'] ?? '');
  $year_level = trim($_POST['year_level'] ?? '');
  $section_num = trim($_POST['section_num'] ?? '');
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';
  $school_year = trim($_POST['school_year'] ?? '');

  $valid_courses = ['BSIT', 'BSCS', 'BSIS'];

  // Resolve section_id by matching course (program) + year + section number
  $section_id = 0;
  $has_section_input = ($course !== '' || $year_level !== '' || $section_num !== '' || $school_year !== '');
  if ($course !== '' && $year_level !== '' && $section_num !== '' && $school_year !== '' && ctype_digit($year_level) && ctype_digit($section_num)) {
        $sec_lookup = $conn->prepare("SELECT id FROM sections WHERE program = ? AND year = ? AND section = ? AND school_year = ? LIMIT 1");
        $sec_lookup->bind_param("siis", $course, $year_level, $section_num, $school_year);
        $sec_lookup->execute();
        $sec_lookup_row = $sec_lookup->get_result()->fetch_assoc();
        $sec_lookup->close();
        
        if ($sec_lookup_row) {
            $section_id = (int)$sec_lookup_row['id'];
        } else {
            // Auto-create new section
            $sec_name = "$course $year_level-$section_num ($school_year)";
            $ins_sec = $conn->prepare("INSERT INTO sections (name, program, year, section, school_year) VALUES (?, ?, ?, ?, ?)");
            $ins_sec->bind_param("ssiis", $sec_name, $course, $year_level, $section_num, $school_year);
            if ($ins_sec->execute()) {
                $section_id = $conn->insert_id;
            }
            $ins_sec->close();
        }
    }

  if (empty($first_name) || empty($last_name) || empty($email) || empty($student_id) || empty($password)) {
    $error = 'Please fill in all required fields.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
  } elseif (strlen($password) < 8) {
    $error = 'Password must be at least 8 characters.';
  } elseif ($password !== $confirm) {
    $error = 'Passwords do not match.';
  } elseif ($course !== '' && !in_array($course, $valid_courses, true)) {
    $error = 'Please select a valid course.';
  } elseif (($year_level !== '' && !ctype_digit($year_level)) || ($section_num !== '' && !ctype_digit($section_num))) {
    $error = 'Year and Section must be numbers.';
  } else {
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
      $error = 'An account with this email already exists.';
    } else {
      $hashed = password_hash($password, PASSWORD_BCRYPT);
      $role_str = 'Student';
      $section_id_param = $section_id > 0 ? $section_id : null;

      $stmt = $conn->prepare("INSERT INTO users (first_name, mid_name, last_name, email, student_id, section_id, role, password, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())");
      $stmt->bind_param("sssssiss", $first_name, $mid_name, $last_name, $email, $student_id, $section_id_param, $role_str, $hashed);

      if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;
        $stmt->close();

        // Auto-enroll in section subjects if section selected
        if ($section_id > 0) {
          $subs = $conn->prepare("SELECT subject_id FROM section_subjects WHERE section_id = ?");
          $subs->bind_param("i", $section_id);
          $subs->execute();
          $sub_rows = $subs->get_result()->fetch_all(MYSQLI_ASSOC);
          $subs->close();

          if (!empty($sub_rows)) {
            $enroll = $conn->prepare("INSERT IGNORE INTO enrollments (user_id, subject_id) VALUES (?,?)");
            foreach ($sub_rows as $sub) {
              $enroll->bind_param("ii", $new_user_id, $sub['subject_id']);
              $enroll->execute();
            }
            $enroll->close();
          }

          // Check if section has a manager — if not, first enrollee becomes manager
          $sec_check = $conn->prepare("SELECT manager_id FROM sections WHERE id = ?");
          $sec_check->bind_param("i", $section_id);
          $sec_check->execute();
          $sec_row = $sec_check->get_result()->fetch_assoc();
          $sec_check->close();

          if (empty($sec_row['manager_id'])) {
            $set_mgr = $conn->prepare("UPDATE sections SET manager_id = ? WHERE id = ?");
            $set_mgr->bind_param("ii", $new_user_id, $section_id);
            $set_mgr->execute();
            $set_mgr->close();

            $ins_mgr = $conn->prepare("INSERT IGNORE INTO managers (section_id, user_id) VALUES (?,?)");
            $ins_mgr->bind_param("ii", $section_id, $new_user_id);
            $ins_mgr->execute();
            $ins_mgr->close();
          }
        }

        header('Location: index.php?registered=1');
        exit();
      } else {
        $error = 'Registration failed. Please try again.';
        $stmt->close();
      }
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
  <link rel="icon" href="assets/images/Icon.png">
</head>

<body>
  <div class="login-wrapper">
    <div class="login-card" style="max-width:90vw;">
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

      <form method="POST" action="">
        <div class="row g-4 mb-3">

          <div class="col-md-6 d-flex flex-column gap-3">
            <div class="d-flex flex-row gap-1">
              <div class="col-4">
                <label class="form-label small fw-medium">First Name <span class="text-danger">*</span></label>
                <input type="text" name="fname" class="form-control" placeholder="Juan"
                  value="<?= htmlspecialchars($_POST['fname'] ?? '') ?>" required>
              </div>
              <div class="col-4 px-1">
                <label class="form-label small fw-medium">Middle Name</label>
                <input type="text" name="mname" class="form-control" placeholder="Dela"
                  value="<?= htmlspecialchars($_POST['mname'] ?? '') ?>">
              </div>
              <div class="col-4">
                <label class="form-label small fw-medium">Last Name <span class="text-danger">*</span></label>
                <input type="text" name="lname" class="form-control" placeholder="Cruz"
                  value="<?= htmlspecialchars($_POST['lname'] ?? '') ?>" required>
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
                <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password"
                  required>
              </div>
            </div>
          </div>

          <div class="col-md-6 d-flex flex-column gap-3">
            <div>
              <label class="form-label small fw-medium">Student ID <span class="text-danger">*</span></label>
              <input type="text" name="student_id" class="form-control" placeholder="2024-XXXXX"
                value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" required>
            </div>

            <div>
              <label class="form-label small fw-medium">Course</label>
              <select name="course" class="form-select">
                <option value="">— Select course —</option>
                <option value="BSIT" <?= (($_POST['course'] ?? '') === 'BSIT') ? 'selected' : '' ?>>BSIT</option>
                <option value="BSCS" <?= (($_POST['course'] ?? '') === 'BSCS') ? 'selected' : '' ?>>BSCS</option>
                <option value="BSIS" <?= (($_POST['course'] ?? '') === 'BSIS') ? 'selected' : '' ?>>BSIS</option>
              </select>
            </div>

            <div class="row g-2">
            <div class="col-4">
              <label class="form-label small fw-medium">Year</label>
              <input type="number" name="year_level" class="form-control" placeholder="e.g. 2" min="1" max="6"
                     value="<?= htmlspecialchars($_POST['year_level'] ?? '') ?>">
            </div>
            <div class="col-4">
              <label class="form-label small fw-medium">Section</label>
              <input type="number" name="section_num" class="form-control" placeholder="e.g. 1" min="1"
                     value="<?= htmlspecialchars($_POST['section_num'] ?? '') ?>">
            </div>
            <div class="col-4">
              <label class="form-label small fw-medium">School Year</label>
              <input type="text" name="school_year" class="form-control" placeholder="e.g. 2024-2025"
                     value="<?= htmlspecialchars($_POST['school_year'] ?? '') ?>">
            </div>
          </div>
          </div>

        </div>

        <div class="col-12 mt-2">
          <button type="submit" class="btn-login w-100">Create Account</button>
        </div>
      </form>

      <p class="text-center text-muted mt-4 mb-0" style="font-size:13px;">
        Already have an account? <a href="index.php" class="text-decoration-none fw-medium"
          style="color:var(--accent-green);">Sign in</a>
      </p>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>