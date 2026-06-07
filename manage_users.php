<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$is_admin = strtolower($_SESSION['role'] ?? '') === 'admin';
if (!$is_admin) {
    header('Location: dashboard.php');
    exit();
}

$users_query = $conn->query("SELECT id, full_name, email, student_id, role, created_at FROM users ORDER BY created_at DESC");
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
  <link href="assets/css/style.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
  <style>
    .users-panel {
      background: white;
      border: 1px solid var(--border-color);
      border-radius: 20px;
      overflow: hidden;
    }

    .users-panel-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 18px 24px;
      border-bottom: 1px solid var(--border-color);
    }

    .users-panel-title {
      font-family: var(--font-heading);
      font-size: 15px;
      font-weight: 600;
      color: var(--text-primary);
      margin: 0;
    }

    .btn-new-user {
      background-color: var(--accent-green);
      color: white;
      border: none;
      border-radius: 10px;
      padding: 8px 18px;
      font-size: 14px;
      font-weight: 500;
      font-family: var(--font-heading);
      transition: background-color 0.15s;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .btn-new-user:hover { background-color: #28a06a; color: white; }

    .user-table-header {
      display: grid;
      grid-template-columns: 2fr 1.5fr 1fr 1fr 100px;
      padding: 8px 24px;
      font-size: 12px;
      font-weight: 500;
      color: var(--text-muted);
      letter-spacing: 0.3px;
      border-bottom: 1px solid var(--border-color);
      background: #fafafa;
    }

    .user-row {
      display: grid;
      grid-template-columns: 2fr 1.5fr 1fr 1fr 100px;
      align-items: center;
      padding: 12px 24px;
      border-bottom: 1px solid var(--border-color);
      transition: background-color 0.15s ease;
    }

    .user-row:last-child { border-bottom: none; }
    .user-row:hover { background-color: #f9fffe; }

    .user-info {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .user-avatar {
      width: 34px; height: 34px; min-width: 34px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      color: white;
      font-weight: 600; font-size: 12px;
      font-family: var(--font-heading);
    }

    .user-name { font-size: 14px; font-weight: 500; color: var(--text-primary); }
    .user-email { font-size: 12px; color: var(--text-muted); }
    .user-student-id { font-size: 13px; color: var(--text-secondary); }

    .user-role-badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
    }

    .role-admin { background: #fff3cd; color: #856404; }
    .role-student { background: #e8f5f0; color: #1a7a52; }

    .user-actions {
      display: flex;
      gap: 6px;
      justify-content: flex-end;
    }

    .btn-edit-user, .btn-delete-user {
      background: none;
      border: none;
      padding: 5px 8px;
      border-radius: 8px;
      font-size: 13px;
      cursor: pointer;
      transition: background 0.15s, color 0.15s;
    }

    .btn-edit-user { color: var(--text-muted); }
    .btn-edit-user:hover { background: #eaf6f0; color: var(--accent-green); }
    .btn-delete-user { color: var(--text-muted); }
    .btn-delete-user:hover { background: #fff0f0; color: var(--accent-red); }

    .users-empty {
      padding: 60px 24px;
      text-align: center;
      color: var(--text-muted);
    }

    .users-empty i { font-size: 40px; margin-bottom: 12px; display: block; opacity: 0.4; }

    .modal-content {
      border-radius: 20px !important;
      border: 1px solid var(--border-color) !important;
    }

    .form-control:focus, .form-select:focus {
      border-color: var(--accent-green);
      box-shadow: 0 0 0 3px rgba(51,183,122,0.15);
    }

    @media (max-width: 768px) {
      .user-table-header,
      .user-row { grid-template-columns: 1fr 1fr 80px; }
      .user-table-header span:nth-child(2),
      .user-table-header span:nth-child(3),
      .user-row > *:nth-child(2),
      .user-row > *:nth-child(3) { display: none; }
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
        <h2 class="hero-greeting mb-1">Manage Users 👥</h2>
        <p class="hero-sub text-muted">View, add, edit, or remove users from the system.</p>
      </div>

      <div class="users-panel">
        <div class="users-panel-header">
          <p class="users-panel-title">All Users (<?= count($users) ?>)</p>
          <button class="btn-new-user" data-bs-toggle="modal" data-bs-target="#userModal"
                  onclick="openAddModal()">
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
            <span></span>
          </div>

          <?php foreach ($users as $u): ?>
            <div class="user-row">
              <div class="user-info">
                <div class="user-avatar" style="background:<?= avatarColor($u['full_name']) ?>;">
                  <?= initials($u['full_name']) ?>
                </div>
                <div>
                  <div class="user-name"><?= htmlspecialchars($u['full_name']) ?></div>
                </div>
              </div>
              <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
              <div class="user-student-id"><?= htmlspecialchars($u['student_id']) ?></div>
              <div>
                <?php $role_lower = strtolower($u['role']); ?>
                <span class="user-role-badge <?= $role_lower === 'admin' ? 'role-admin' : 'role-student' ?>">
                  <?= htmlspecialchars($u['role']) ?>
                </span>
              </div>
              <div class="user-actions">
                <button class="btn-edit-user" title="Edit"
                        onclick='openEditModal(<?= json_encode($u) ?>)'>
                  <i class="bi bi-pencil"></i>
                </button>
                <button class="btn-delete-user" title="Delete"
                        onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>')">
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

<!-- Add / Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-medium" id="userModalTitle" style="font-family:var(--font-heading);">Add User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body pt-3">
        <div id="userError" class="alert-error mb-3" style="display:none;"></div>
        <form id="userForm">
          <input type="hidden" name="user_id" id="field_user_id">

          <div class="mb-3">
            <label class="form-label small fw-medium">Full Name</label>
            <input type="text" name="full_name" id="field_full_name" class="form-control" required maxlength="120"
                   placeholder="e.g. Juan Dela Cruz"
                   style="border-radius:10px;border-color:var(--border-color);">
          </div>

          <div class="mb-3">
            <label class="form-label small fw-medium">Email</label>
            <input type="email" name="email" id="field_email" class="form-control" required maxlength="160"
                   placeholder="e.g. juan@iskolarngbayan.pup.edu.ph"
                   style="border-radius:10px;border-color:var(--border-color);">
          </div>

          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-medium">Student ID</label>
              <input type="text" name="student_id" id="field_student_id" class="form-control" required maxlength="30"
                     placeholder="e.g. 2024-00502-ST-0"
                     style="border-radius:10px;border-color:var(--border-color);">
            </div>
            <div class="col-6">
              <label class="form-label small fw-medium">Role</label>
              <input type="text" name="role" id="field_role" class="form-control" maxlength="80"
                     placeholder="e.g. Student - BSIT 2-1"
                     style="border-radius:10px;border-color:var(--border-color);">
            </div>
          </div>

          <div class="mb-4" id="passwordField">
            <label class="form-label small fw-medium">Password <span class="text-muted fw-normal" id="passwordHint">(required)</span></label>
            <input type="password" name="password" id="field_password" class="form-control" maxlength="255"
                   placeholder="Enter password"
                   style="border-radius:10px;border-color:var(--border-color);">
          </div>

          <button type="submit" class="btn-login" id="userSubmitBtn">Add User</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Delete Confirm Modal -->
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
document.addEventListener('DOMContentLoaded', function () {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar   = document.getElementById('sidebar');
  const overlay   = document.getElementById('sidebarOverlay');
  if (toggleBtn) toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); overlay?.classList.toggle('show'); });
  if (overlay)   overlay.addEventListener('click', () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); });
});

function openAddModal() {
  document.getElementById('userModalTitle').textContent = 'Add User';
  document.getElementById('userSubmitBtn').textContent  = 'Add User';
  document.getElementById('userForm').reset();
  document.getElementById('field_user_id').value = '';
  document.getElementById('field_password').required = true;
  document.getElementById('passwordHint').textContent = '(required)';
  document.getElementById('userError').style.display = 'none';
}

function openEditModal(u) {
  document.getElementById('userModalTitle').textContent = 'Edit User';
  document.getElementById('userSubmitBtn').textContent  = 'Save Changes';
  document.getElementById('field_user_id').value    = u.id;
  document.getElementById('field_full_name').value  = u.full_name;
  document.getElementById('field_email').value      = u.email;
  document.getElementById('field_student_id').value = u.student_id;
  document.getElementById('field_role').value       = u.role ?? '';
  document.getElementById('field_password').value   = '';
  document.getElementById('field_password').required = false;
  document.getElementById('passwordHint').textContent = '(leave blank to keep current)';
  document.getElementById('userError').style.display = 'none';
  new bootstrap.Modal(document.getElementById('userModal')).show();
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
const deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));

function confirmDelete(id, name) {
  pendingDeleteId = id;
  document.getElementById('deleteUserName').textContent = name;
  deleteModal.show();
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
  if (!pendingDeleteId) return;
  const fd = new FormData();
  fd.append('action', 'delete_user');
  fd.append('user_id', pendingDeleteId);

  fetch('actions/user_actions.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      deleteModal.hide();
      if (data.success) window.location.reload();
      else alert(data.message || 'Failed to delete.');
      pendingDeleteId = null;
    })
    .catch(() => {
      deleteModal.hide();
      alert('Network error.');
      pendingDeleteId = null;
    });
});
</script>
</body>
</html>
