<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$current_user_id = (int) $_SESSION['user_id'];
$is_admin = strtolower($_SESSION['role'] ?? '') === 'admin';

// Get current user's section
$me_q = $conn->prepare("
        SELECT u.id, u.first_name, u.mid_name, u.last_name, 
           CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name,
           u.email, u.student_id, u.role, u.section_id, u.created_at,
           s.program, s.year, s.section, s.name AS section_name
        FROM users u
        LEFT JOIN sections s ON u.section_id = s.id
        WHERE u.id = ?");
$me_q->bind_param("i", $current_user_id);
$me_q->execute();
$me = $me_q->get_result()->fetch_assoc();
$me_q->close();

$my_section_id = $me['section_id'] ?? null;
$my_section_name = $me['section_name'] ?? ''; // New var for display

// --- For admins: fetch all sections for the "add member" feature ---
$all_sections = [];
$all_unassigned = [];
if ($is_admin) {
  // Use 'program' not 'course'
  $sec_q = $conn->query("SELECT id, name, program FROM sections ORDER BY name");
  $all_sections = $sec_q ? $sec_q->fetch_all(MYSQLI_ASSOC) : [];

  // Users without a section
  $unassigned_q = $conn->query("SELECT u.id, u.first_name, u.mid_name, u.last_name, 
           CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name,
           u.email, u.student_id, u.role, u.section_id, u.created_at 
           FROM users u WHERE u.section_id IS NULL ORDER BY full_name");
  $all_unassigned = $unassigned_q ? $unassigned_q->fetch_all(MYSQLI_ASSOC) : [];
}

// Build members list per section
if ($is_admin) {
  $members_q = $conn->query("
        SELECT u.id, u.first_name, u.mid_name, u.last_name,
               CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name,
               u.student_id, u.role, u.section_id, u.created_at,
               s.id AS section_db_id, s.name AS section_name, s.program,
               CASE WHEN s.manager_id = u.id THEN 1 ELSE 0 END AS is_manager
        FROM users u
        JOIN sections s ON s.id = u.section_id
        ORDER BY s.name, u.first_name
    ");
} else {
  $members_q = $conn->prepare("
        SELECT u.id, u.first_name, u.mid_name, u.last_name,
               CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS full_name,
               u.student_id, u.role, u.section_id, u.created_at,
               s.id AS section_db_id, s.name AS section_name, s.program,
               CASE WHEN s.manager_id = u.id THEN 1 ELSE 0 END AS is_manager
        FROM users u
        JOIN sections s ON s.id = u.section_id
        WHERE u.section_id = ?
        ORDER BY u.first_name
    ");
  $members_q->bind_param("i", $my_section_id);
  $members_q->execute();
  $members_q = $members_q->get_result();
}

$members_raw = $members_q ? $members_q->fetch_all(MYSQLI_ASSOC) : [];

// Group by section
$sections_map = [];
foreach ($members_raw as $m) {
  // Use section_name instead of section
  $key = $m['section_name'] ?: 'Unknown';
  $sections_map[$key][] = $m;
}

function avatarColor(string $name): string
{
  $colors = ['#33b77a', '#4a90d9', '#d23c3c', '#f5a623', '#9b59b6', '#1abc9c', '#e67e22', '#2980b9'];
  return $colors[abs(crc32($name)) % count($colors)];
}
function initials(string $name): string
{
  $parts = explode(' ', trim($name));
  if (empty($parts) || $parts[0] === '')
    return '?';
  $i = strtoupper(substr($parts[0], 0, 1));
  if (count($parts) > 1 && !empty(end($parts)))
    $i .= strtoupper(substr(end($parts), 0, 1));
  return $i;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Member Dictionary – Iskonek</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/members.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
</head>

<body>
  <div class="app-wrapper">
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
      <?php include 'includes/navbar.php'; ?>

      <div class="container-fluid px-4 py-4" style="max-width:900px;">

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
          <div>
            <h4 class="mb-0 fw-semibold" style="font-family:var(--font-heading);">Member Directory</h4>
            <p class="text-muted mb-0" style="font-size:13px;">
              <?php if ($is_admin): ?>
                All sections and their members
              <?php elseif ($my_section_id): ?>
                Your classmates in <strong><?= htmlspecialchars($my_section_name) ?></strong>
              <?php else: ?>
                You haven't been assigned to a section yet.
              <?php endif; ?>
            </p>
          </div>
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <input type="text" id="memberSearch" class="search-bar" placeholder="Search members…">
            <?php if ($is_admin): ?>
              <button class="btn-add-member" onclick="openAddMemberModal()">
                <i class="bi bi-person-plus-fill"></i> Add Member
              </button>
            <?php endif; ?>
          </div>
        </div>

        <div id="membersList">
          <?php if (empty($sections_map)): ?>
            <div class="members-panel">
              <div class="empty-state">
                <i class="bi bi-people" style="font-size:32px;opacity:.3;"></i>
                <p class="mt-2 mb-0" style="font-size:14px;">
                  <?= $is_admin ? 'No members have been assigned to sections yet.' : 'No classmates found in your section.' ?>
                </p>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($sections_map as $section_key => $members): ?>
              <?php $course_label = $members[0]['program'] ?? ''; ?>
              <div class="members-panel member-section-panel mb-4">
                <div class="members-panel-header">
                  <div class="d-flex align-items-center gap-2">
                    <p class="section-title mb-0"><?= htmlspecialchars($section_key) ?></p>
                    <?php if ($course_label): ?>
                      <span class="section-badge"><?= htmlspecialchars($course_label) ?></span>
                    <?php endif; ?>
                  </div>
                  <span class="text-muted" style="font-size:13px;"><?= count($members) ?>
                    member<?= count($members) !== 1 ? 's' : '' ?></span>
                </div>

                <?php foreach ($members as $member): ?>
                  <?php $m_name = !empty(trim($member['full_name'])) ? $member['full_name'] : 'Student User'; ?>
                  <div class="member-row" data-name="<?= strtolower(htmlspecialchars($m_name)) ?>">
                    <div class="member-avatar" style="background:<?= avatarColor($m_name) ?>">
                      <?= initials($m_name) ?>
                    </div>
                    <div class="flex-grow-1">
                      <div class="member-name">
                        <?= htmlspecialchars($m_name) ?>
                        <?php if ((int) $member['id'] === $current_user_id): ?>
                          <span class="you-badge">You</span>
                        <?php endif; ?>
                        <?php if ($member['is_manager']): ?>
                          <span class="manager-badge"><i class="bi bi-star-fill" style="font-size:9px;"></i> Manager</span>
                        <?php endif; ?>
                      </div>
                      <div class="member-meta"><?= htmlspecialchars($member['student_id'] ?? '—') ?> ·
                        <?= htmlspecialchars($member['role'] ?? 'Student') ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>

  <?php if ($is_admin): ?>
    <div class="modal fade" id="addMemberModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title fw-medium" style="font-family:var(--font-heading);">Add Member to Section</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body pt-3">
            <div id="addMemberError" class="alert-error mb-3" style="display:none;"></div>
            <div class="mb-3">
              <label class="form-label small fw-medium">Select Section</label>
              <select id="am_section" class="form-select" style="border-radius:10px;border-color:var(--border-color);">
                <option value="">— Choose section —</option>
                <?php foreach ($all_sections as $sec): ?>
                  <option value="<?= htmlspecialchars($sec['name']) ?>"><?= htmlspecialchars($sec['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-medium">Select User (no section yet)</label>
              <select id="am_user" class="form-select" style="border-radius:10px;border-color:var(--border-color);">
                <option value="">— Choose user —</option>
                <?php foreach ($all_unassigned as $u): ?>
                  <?php $u_name = !empty(trim($u['full_name'])) ? $u['full_name'] : 'Unassigned User'; ?>
                  <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u_name) ?>
                    (<?= htmlspecialchars($u['student_id'] ?? 'No ID') ?>)</option>
                <?php endforeach; ?>
              </select>
              <div class="form-text" style="font-size:11px;">Only users without a section are shown. To move an existing
                member, edit them in Manage Users.</div>
            </div>
            <button class="btn-login w-100" onclick="submitAddMember()">Add to Section</button>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    let addMemberModalObj = null;

    document.addEventListener('DOMContentLoaded', function () {
      const toggleBtn = document.getElementById('sidebarToggle');
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebarOverlay');
      if (toggleBtn) toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); overlay?.classList.toggle('show'); });
      if (overlay) overlay.addEventListener('click', () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); });

      <?php if ($is_admin): ?>
        addMemberModalObj = new bootstrap.Modal(document.getElementById('addMemberModal'));
      <?php endif; ?>
    });

    // Live search
    document.getElementById('memberSearch').addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      document.querySelectorAll('.member-row').forEach(row => {
        row.style.setProperty('display', row.dataset.name.includes(q) ? '' : 'none', 'important');
      });
    });

    <?php if ($is_admin): ?>
      function openAddMemberModal() {
        document.getElementById('addMemberError').style.display = 'none';
        document.getElementById('am_section').value = '';
        document.getElementById('am_user').value = '';
        addMemberModalObj.show();
      }

      function submitAddMember() {
        const section_name = document.getElementById('am_section').value;
        const user_id = document.getElementById('am_user').value;
        const errEl = document.getElementById('addMemberError');
        errEl.style.display = 'none';

        if (!section_name || !user_id) {
          errEl.textContent = 'Please select both a section and a user.';
          errEl.style.display = 'block';
          return;
        }

        const fd = new FormData();
        fd.append('action', 'add_member');
        fd.append('section_name', section_name);
        fd.append('user_id', user_id);

        fetch('actions/section_actions.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(data => {
            if (data.success) {
              addMemberModalObj.hide();
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
      }
    <?php endif; ?>
  </script>
</body>

</html>