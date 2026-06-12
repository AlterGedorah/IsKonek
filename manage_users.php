<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$is_admin = strtolower($_SESSION['role'] ?? '') === 'admin';
if (!$is_admin) {
    header('Location: dashboard.php');
    exit();
}

// UPDATED: Added course/program, year, and section to the selection fields
$users_query = $conn->query("
    SELECT id, 
           first_name, 
           mid_name, 
           last_name, 
           CONCAT_WS(' ', first_name, mid_name, last_name) AS full_name,
           email, 
           student_id, 
           role, 
           program,
           year,
           section,
           created_at 
    FROM users 
    ORDER BY created_at DESC
");
$users = $users_query->fetch_all(MYSQLI_ASSOC);

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
  <title>Manage Users – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/manage_users.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
</head>
<body>
<div class="app-wrapper">
  <div class="sidebar-overlay" id="sidebarOverlay"></div>
  <?php include 'includes/sidebar.php'; ?>

  <div class="main-content">
    <?php include 'includes/navbar.php'; ?>

    <div class="page-body">

      <div class="mb-4">
        <h2 class="hero-greeting mb-1">Manage Users 👥</h2>
        <p class="hero-sub text-muted">View, add, edit, or remove users from the system.</p>
      </div>

      <div class="users-panel">
        <div class="users-panel-header">
          <p class="users-panel-title">All Users (<?= count($users) ?>)</p>
          <button class="btn-new-user" onclick="openAddModal()">
            <i class="bi bi-plus-lg"></i> Add User
          </button>
        </div>

        <?php if (empty($users)): ?>
          <div class="users-empty">
            <i class="bi bi-people"></i>
            <p class="mb-1 fw-medium" style="font-size:15px;">No users yet</p>
          </div>
        <?php else: ?>
          <div class="user-table-header">
            <span>Name</span>
            <span>Email</span>
            <span>Student ID</span>
            <span>Role</span>
            <span>Class / Section</span> <span>Actions</span>
          </div>

          <?php foreach ($users as $u): ?>
            <?php $display_name = !empty(trim($u['full_name'])) ? $u['full_name'] : 'System User'; ?>
            <div class="user-row">
              <div class="user-info">
                <div class="user-avatar" style="background:<?= avatarColor($display_name) ?>;">
                  <?= initials($display_name) ?>
                </div>
                <div>
                  <div class="user-name"><?= htmlspecialchars($display_name) ?></div>
                </div>
              </div>
              <div class="user-email"><?= htmlspecialchars($u['email'] ?? '') ?></div>
              <div class="user-student-id"><?= htmlspecialchars($u['student_id'] ?? '') ?></div>
              <div>
                <?php $role_lower = strtolower($u['role'] ?? 'student'); ?>
                <span class="user-role-badge <?= $role_lower === 'admin' ? 'role-admin' : 'role-student' ?>">
                  <?= htmlspecialchars($u['role'] ?? 'Student') ?>
                </span>
              </div>
              <div class="user-section text-secondary fw-medium">
                <?php 
                  if (!empty($u['program'])) {
                      echo htmlspecialchars($u['program']) . ' ' . htmlspecialchars($u['year'] ?? '') . '-' . htmlspecialchars($u['section'] ?? '');
                  } else {
                      echo '<span class="text-muted small">—</span>';
                  }
                ?>
              </div>
              <div class="user-actions">
                <button class="btn-edit-user" title="Edit"
                        onclick='openEditModal(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn-delete-user" title="Delete"
                        onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($display_name)) ?>')">
                  <i class="bi bi-trash3"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg"> <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-medium" id="userModalTitle" style="font-family:var(--font-heading);">Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3">
        <div id="userError" class="alert-error mb-3" style="display:none;"></div>
        <form id="userForm">
          <input type="hidden" name="user_id" id="field_user_id">

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-medium">First Name</label>
              <input type="text" name="first_name" id="field_first_name" class="form-control" required maxlength="120"
                     placeholder="e.g. Juan" style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-medium">Email</label>
              <input type="email" name="email" id="field_email" class="form-control" required maxlength="160"
                     placeholder="e.g. juan@iskolarngbayan.pup.edu.ph" style="border-radius:10px;border-color:var(--border-color);">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-medium">Student ID</label>
              <input type="text" name="student_id" id="field_student_id" class="form-control" required maxlength="30"
                     placeholder="e.g. 2024-00502-ST-0" style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-medium">System Role</label>
              <select name="role" id="field_role" class="form-select" style="border-radius:10px;border-color:var(--border-color);">
                <option value="Student">Student</option>
                <option value="Admin">Admin</option>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label small fw-medium">Program / Course</label>
              <select name="course" id="field_course" class="form-select" style="border-radius:10px;border-color:var(--border-color);">
                <option value="">— Select Program —</option>
                <option value="BSIT">BSIT</option>
                <option value="BSCS">BSCS</option>
                <option value="BSIS">BSIS</option>
                <option value="ACT">ACT</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-medium">Year Level</label>
              <input type="number" name="year" id="field_year" class="form-control" min="1" max="5" placeholder="e.g. 2"
                     style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-medium">Section</label>
              <input type="text" name="section" id="field_section" class="form-control" placeholder="e.g. 1"
                     style="border-radius:10px;border-color:var(--border-color);">
            </div>
          </div>

          <div class="mb-4" id="passwordField">
            <label class="form-label small fw-medium">Password <span class="text-muted fw-normal" id="passwordHint">(required)</span></label>
            <input type="password" name="password" id="field_password" class="form-control" maxlength="255"
                   placeholder="Enter password" style="border-radius:10px;border-color:var(--border-color);">
          </div>

          <button type="submit" class="btn-login w-100" id="userSubmitBtn">Add User</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="deleteUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h6 class="modal-title fw-medium" style="font-family:var(--font-heading);">Delete User?</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-2 pb-1">
        <p class="text-muted small mb-0">This will permanently delete <strong id="deleteUserName"></strong> and all their data.</p>
      </div>
      <div class="modal-footer border-0 pt-2">
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-danger" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let userModalObj = null;
let deleteModalObj = null;

document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar   = document.getElementById('sidebar');
  const overlay   = document.getElementById('sidebarOverlay');
  if (toggleBtn) toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); overlay?.classList.toggle('show'); });
  if (overlay)   overlay.addEventListener('click', () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); });

  userModalObj = new bootstrap.Modal(document.getElementById('userModal'));
  deleteModalObj = new bootstrap.Modal(document.getElementById('deleteUserModal'));
});

function openAddModal() {
  document.getElementById('userModalTitle').textContent = 'Add User';
  document.getElementById('userSubmitBtn').textContent  = 'Add User';
  document.getElementById('userForm').reset();
  document.getElementById('field_user_id').value = '';
  document.getElementById('field_password').required = true;
  document.getElementById('passwordHint').textContent = '(required)';
  document.getElementById('userError').style.display = 'none';
  userModalObj.show();
}

function openEditModal(u) {
  document.getElementById('userModalTitle').textContent = 'Edit User';
  document.getElementById('userSubmitBtn').textContent  = 'Save Changes';
  document.getElementById('field_user_id').value    = u.id;
  document.getElementById('field_first_name').value  = u.full_name || '';
  document.getElementById('field_email').value      = u.email || '';
  document.getElementById('field_student_id').value = u.student_id || '';
  document.getElementById('field_role').value       = u.role || 'Student';
  
  // NEW: Populate academic values into edit modal view dynamically
  document.getElementById('field_course').value    = u.course || '';
  document.getElementById('field_year').value      = u.year || '';
  document.getElementById('field_section').value   = u.section || '';
  
  document.getElementById('field_password').value   = '';
  document.getElementById('field_password').required = false;
  document.getElementById('passwordHint').textContent = '(leave blank to keep current)';
  document.getElementById('userError').style.display = 'none';
  userModalObj.show();
}

document.getElementById('userForm').addEventListener('submit', function (e) {
  e.preventDefault();
  const fd = new FormData(this);
  const isEdit = !!fd.get('user_id');
  fd.append('action', isEdit ? 'edit_user' : 'add_user');
  const errEl = document.getElementById('userError');
  errEl.style.display = 'none';

  fetch('actions/user_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        userModalObj.hide();
        window.location.reload();
      } else {
        errEl.textContent = data.message || 'Something went wrong.';
        errEl.style.display = 'block';
      }
    })
    .catch(() => {
      errEl.textContent = 'Network error. Please try again.';
      errEl.style.display = 'block';
    });
});

let pendingDeleteId = null;

function confirmDelete(id, name) {
  pendingDeleteId = id;
  document.getElementById('deleteUserName').textContent = name;
  deleteModalObj.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
  if (!pendingDeleteId) return;
  const fd = new FormData();
  fd.append('action', 'delete_user');
  fd.append('user_id', pendingDeleteId);

  fetch('actions/user_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      deleteModalObj.hide();
      if (data.success) {
        window.location.reload();
      } else {
        alert(data.message || 'Failed to delete.');
      }
      pendingDeleteId = null;
    })
    .catch(() => {
      deleteModalObj.hide();
      alert('Network error.');
      pendingDeleteId = null;
    });
});
</script>
</body>
</html>