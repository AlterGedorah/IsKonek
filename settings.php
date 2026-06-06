<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];

$user_query = $conn->prepare("SELECT id, full_name, email, student_id, role, section, avatar FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user = $user_query->get_result()->fetch_assoc();

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

function avatarColor(string $name): string {
    $colors = ['#33b77a','#4a90d9','#d23c3c','#f5a623','#9b59b6','#1abc9c','#e67e22','#2980b9'];
    return $colors[abs(crc32($name)) % count($colors)];
}

function initials(string $name): string {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) $i .= strtoupper(substr(end($parts), 0, 1));
    return $i;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
  <style>
    .settings-layout {
      display: grid;
      grid-template-columns: 220px 1fr;
      gap: 24px;
      align-items: start;
    }

    .settings-nav {
      position: sticky;
      top: calc(var(--navbar-height) + 24px);
      background: white;
      border: 1px solid var(--border-color);
      border-radius: 20px;
      overflow: hidden;
      padding: 8px;
    }

    .settings-nav-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 14px;
      border-radius: 12px;
      font-size: 14px;
      color: var(--text-secondary);
      cursor: pointer;
      transition: background 0.15s;
      border: none;
      background: none;
      width: 100%;
      text-align: left;
    }

    .settings-nav-item:hover { background: var(--hover-bg); color: var(--text-primary); }
    .settings-nav-item.active { background: var(--hover-bg); color: var(--text-primary); font-weight: 500; }

    .settings-panel {
      background: white;
      border: 1px solid var(--border-color);
      border-radius: 20px;
      overflow: hidden;
    }

    .settings-section {
      display: none;
      padding: 28px;
    }

    .settings-section.active { display: block; }

    .settings-section-title {
      font-family: var(--font-heading);
      font-weight: 600;
      font-size: 16px;
      color: var(--text-primary);
      margin-bottom: 4px;
    }

    .settings-section-desc {
      font-size: 13px;
      color: var(--text-muted);
      margin-bottom: 24px;
    }

    .settings-divider {
      border: none;
      border-top: 1px solid var(--border-color);
      margin: 24px 0;
    }

    .avatar-preview {
      width: 72px; height: 72px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      color: white;
      font-weight: 700; font-size: 24px;
      font-family: var(--font-heading);
      flex-shrink: 0;
    }

    .avatar-preview img {
      width: 72px; height: 72px;
      border-radius: 50%;
      object-fit: cover;
    }

    .btn-upload-avatar {
      background: none;
      border: 1px solid var(--border-color);
      border-radius: 10px;
      padding: 7px 16px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: background 0.15s;
      color: var(--text-primary);
    }

    .btn-upload-avatar:hover { background: var(--hover-bg); }

    .form-control:focus, .form-select:focus {
      border-color: var(--accent-green);
      box-shadow: 0 0 0 3px rgba(51,183,122,0.15);
    }

    .btn-save {
      background-color: var(--accent-green);
      color: white;
      border: none;
      border-radius: 10px;
      padding: 10px 28px;
      font-size: 14px;
      font-weight: 500;
      font-family: var(--font-heading);
      transition: background-color 0.15s;
      cursor: pointer;
    }

    .btn-save:hover { background-color: #28a06a; }

    .alert-success-inline {
      background: #f0faf5;
      border: 1px solid #b3e6cc;
      color: #1a7a4c;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 13px;
      margin-bottom: 16px;
    }

    @media (max-width: 768px) {
      .settings-layout { grid-template-columns: 1fr; }
      .settings-nav { position: static; display: flex; gap: 4px; padding: 6px; }
      .settings-nav-item { padding: 8px 12px; }
    }
  </style>
</head>
<body>
<div class="app-wrapper">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <?php include 'includes/navbar.php'; ?>

    <div class="page-body">

      <div class="mb-4">
        <h2 class="hero-greeting mb-1">Settings ⚙️</h2>
        <p class="hero-sub text-muted">Manage your account and preferences.</p>
      </div>

      <div class="settings-layout">

        <!-- Settings Nav -->
        <nav class="settings-nav">
          <button class="settings-nav-item active" onclick="switchTab('profile', this)">
            <i class="bi bi-person"></i> Profile
          </button>
          <button class="settings-nav-item" onclick="switchTab('password', this)">
            <i class="bi bi-lock"></i> Password
          </button>
        </nav>

        <!-- Settings Panel -->
        <div class="settings-panel">

          <!-- Profile Section -->
          <div class="settings-section active" id="tab-profile">
            <p class="settings-section-title">Profile Information</p>
            <p class="settings-section-desc">Update your personal details.</p>

            <?php if ($success === 'profile'): ?>
              <div class="alert-success-inline"><i class="bi bi-check-circle me-1"></i>Profile updated successfully.</div>
            <?php endif; ?>
            <?php if ($error === 'profile'): ?>
              <div class="alert-error mb-3">Failed to update profile. Please try again.</div>
            <?php endif; ?>

            <!-- Avatar -->
            <div class="d-flex align-items-center gap-16 mb-4" style="gap:16px;">
              <div class="avatar-preview" id="avatarPreview"
                   style="background:<?= avatarColor($user['full_name']) ?>;">
                <?php if (!empty($user['avatar'])): ?>
                  <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar">
                <?php else: ?>
                  <?= initials($user['full_name']) ?>
                <?php endif; ?>
              </div>
              <div>
                <label class="btn-upload-avatar" for="avatarInput">
                  <i class="bi bi-upload me-1"></i> Upload Photo
                </label>
                <input type="file" id="avatarInput" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
                <p class="text-muted mt-1 mb-0" style="font-size:12px;">JPG, PNG up to 2MB</p>
              </div>
            </div>

            <form method="POST" action="actions/settings_actions.php" enctype="multipart/form-data">
              <input type="hidden" name="action" value="update_profile">
              <input type="hidden" name="avatar_data" id="avatarData">

              <div class="row g-3 mb-3">
                <div class="col-12">
                  <label class="form-label small fw-medium">Full Name</label>
                  <input type="text" name="full_name" class="form-control" required maxlength="120"
                         value="<?= htmlspecialchars($user['full_name']) ?>"
                         style="border-radius:10px;border-color:var(--border-color);">
                </div>
                <div class="col-12">
                  <label class="form-label small fw-medium">Email Address</label>
                  <input type="email" name="email" class="form-control" required maxlength="160"
                         value="<?= htmlspecialchars($user['email']) ?>"
                         style="border-radius:10px;border-color:var(--border-color);">
                </div>
                <div class="col-sm-6">
                  <label class="form-label small fw-medium">Student ID</label>
                  <input type="text" name="student_id" class="form-control" maxlength="30"
                         value="<?= htmlspecialchars($user['student_id']) ?>"
                         style="border-radius:10px;border-color:var(--border-color);">
                </div>
                <div class="col-sm-6">
                  <label class="form-label small fw-medium">Section</label>
                  <input type="text" name="section" class="form-control" maxlength="40"
                         value="<?= htmlspecialchars($user['section'] ?? '') ?>"
                         placeholder="e.g. BSIT 2-1"
                         style="border-radius:10px;border-color:var(--border-color);">
                </div>
              </div>

              <button type="submit" class="btn-save">Save Changes</button>
            </form>
          </div>

          <!-- Password Section -->
          <div class="settings-section" id="tab-password">
            <p class="settings-section-title">Change Password</p>
            <p class="settings-section-desc">Make sure your new password is at least 8 characters.</p>

            <?php if ($success === 'password'): ?>
              <div class="alert-success-inline"><i class="bi bi-check-circle me-1"></i>Password changed successfully.</div>
            <?php endif; ?>
            <?php if ($error === 'password'): ?>
              <div class="alert-error mb-3">Failed to change password. Please check your current password.</div>
            <?php endif; ?>

            <form method="POST" action="actions/settings_actions.php">
              <input type="hidden" name="action" value="change_password">

              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label small fw-medium">Current Password</label>
                  <input type="password" name="current_password" class="form-control" required
                         placeholder="Enter current password"
                         style="border-radius:10px;border-color:var(--border-color);">
                </div>
                <div class="col-sm-6">
                  <label class="form-label small fw-medium">New Password</label>
                  <input type="password" name="new_password" class="form-control" required minlength="8"
                         placeholder="Min 8 characters"
                         style="border-radius:10px;border-color:var(--border-color);">
                </div>
                <div class="col-sm-6">
                  <label class="form-label small fw-medium">Confirm New Password</label>
                  <input type="password" name="confirm_password" class="form-control" required
                         placeholder="Repeat new password"
                         style="border-radius:10px;border-color:var(--border-color);">
                </div>
                <div class="col-12 mt-1">
                  <button type="submit" class="btn-save">Change Password</button>
                </div>
              </div>
            </form>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar   = document.getElementById('sidebar');
  const overlay   = document.getElementById('sidebarOverlay');
  if (toggleBtn) toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); overlay?.classList.toggle('show'); });
  if (overlay)   overlay.addEventListener('click', () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); });

  // Auto-show correct tab if redirected with success/error
  const params = new URLSearchParams(window.location.search);
  const tab = params.get('tab');
  if (tab) {
    const btn = document.querySelector(`.settings-nav-item[onclick*="${tab}"]`);
    if (btn) switchTab(tab, btn);
  }
});

function switchTab(tab, el) {
  document.querySelectorAll('.settings-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.settings-nav-item').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  el.classList.add('active');
}

function previewAvatar(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = function (e) {
    const preview = document.getElementById('avatarPreview');
    preview.innerHTML = `<img src="${e.target.result}" alt="Avatar">`;
    document.getElementById('avatarData').value = e.target.result;
  };
  reader.readAsDataURL(file);
}
</script>
</body>
</html>
