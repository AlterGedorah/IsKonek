<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT id, first_name,mid_name,last_name, email, password, role,year, section,program FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['fname'] = $user['first_name'];
                $_SESSION['mname'] = $user['mid_name'];
                $_SESSION['lname'] = $$user['last_name'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['program']   = $user['program'];
                $_SESSION['year']   = $user['year'];
                $_SESSION['section']   = $user['section'];
                $_SESSION['full_name'] = $full_name = $user['first_name'].' '.$user['mid_name'].' '.$user['last_name'];;
                // Auto-enroll in section subjects
                if (!empty($user['section'])) {
                    $sec = $conn->prepare("SELECT id,course,year,section FROM sections WHERE name = ?");
                    $sec->bind_param("s", $user['section']);
                    $sec->execute();
                    $sec_row = $sec->get_result()->fetch_assoc();
                    $sec->close();

                    if ($sec_row) {
                        $subs = $conn->prepare("SELECT subject_id FROM section_subjects WHERE section_id = ?");
                        $subs->bind_param("i", $sec_row['id']);
                        $subs->execute();
                        $sub_rows = $subs->get_result()->fetch_all(MYSQLI_ASSOC);
                        $subs->close();

                        $enroll = $conn->prepare("INSERT IGNORE INTO enrollments (user_id, subject_id) VALUES (?,?)");
                        foreach ($sub_rows as $sub) {
                            $enroll->bind_param("ii", $user['id'], $sub['subject_id']);
                            $enroll->execute();
                        }
                        $enroll->close();
                    }
                }

                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iskonek – Sign In</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
</head>
<body>
<div class="login-wrapper">
  <div class="login-card">
    <div class="d-flex align-items-center gap-3 mb-4">
      <div class="login-logo d-flex align-items-center justify-content-center">
        <span class="fw-bold fs-4" style="font-family:'Poppins',sans-serif;">IK</span>
      </div>
      <div>
        <h5 class="mb-0 fw-medium" style="font-family:'Poppins',sans-serif;">Iskonek</h5>
        <p class="mb-0 text-muted" style="font-size:13px;">Student Portal</p>
      </div>
    </div>

    <h4 class="fw-medium mb-1" style="font-family:'Poppins',sans-serif;">Welcome back!</h4>
    <p class="text-muted mb-4" style="font-size:14px;">Sign in to your account to continue.</p>

    <?php if ($error): ?>
      <div class="alert-error mb-3"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="mb-3">
        <label class="form-label small fw-medium">Email address</label>
        <input type="email" name="email" class="form-control"
               placeholder="student@school.edu.ph"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label class="form-label small fw-medium mb-0">Password</label>
          <a href="forgot_password.php" class="small text-decoration-none" style="color:var(--accent-green);">Forgot password?</a>
        </div>
        <div class="input-group">
          <input type="password" name="password" class="form-control" id="passwordInput" placeholder="••••••••" required>
          <button class="btn btn-outline-secondary" type="button" id="togglePassword"
                  style="border-color:var(--border-color);border-radius:0 10px 10px 0;">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn-login">Sign In</button>
    </form>

    <p class="text-center text-muted mt-4 mb-0" style="font-size:13px;">
      Don't have an account? <a href="register.php" class="text-decoration-none fw-medium" style="color:var(--accent-green);">Register</a>
    </p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('togglePassword').addEventListener('click', function () {
    const inp  = document.getElementById('passwordInput');
    const icon = document.getElementById('eyeIcon');
    if (inp.type === 'password') {
      inp.type = 'text';
      icon.className = 'bi bi-eye-slash';
    } else {
      inp.type = 'password';
      icon.className = 'bi bi-eye';
    }
  });
</script>
</body>
</html>