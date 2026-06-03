<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$post_id) {
    header('Location: forums.php');
    exit();
}

// Increment view count
$conn->query("UPDATE forum_posts SET views = views + 1 WHERE id = $post_id");

// Get post + author info
$post_query = $conn->prepare("
    SELECT fp.id, fp.title, fp.body, fp.views, fp.created_at,
           fp.user_id AS author_id,
           u.full_name, u.role,
           s.id AS subject_id, s.code AS subject_code, s.name AS subject_name, s.color AS subject_color
    FROM forum_posts fp
    JOIN users u ON u.id = fp.user_id
    JOIN subjects s ON s.id = fp.subject_id
    WHERE fp.id = ?
");
$post_query->bind_param("i", $post_id);
$post_query->execute();
$post = $post_query->get_result()->fetch_assoc();

if (!$post) {
    header('Location: forums.php');
    exit();
}

// Check user is enrolled in the subject
$enroll_check = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND subject_id = ?");
$enroll_check->bind_param("ii", $user_id, $post['subject_id']);
$enroll_check->execute();
if ($enroll_check->get_result()->num_rows === 0) {
    header('Location: forums.php');
    exit();
}

// Get replies
$replies_query = $conn->prepare("
    SELECT fr.id, fr.body, fr.created_at,
           u.id AS user_id, u.full_name, u.role
    FROM forum_replies fr
    JOIN users u ON u.id = fr.user_id
    WHERE fr.post_id = ?
    ORDER BY fr.created_at ASC
");
$replies_query->bind_param("i", $post_id);
$replies_query->execute();
$replies = $replies_query->get_result()->fetch_all(MYSQLI_ASSOC);

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

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($post['title']) ?> – Iskonek Forums</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <link rel="icon" href="assets/images/Icon.png">
  <style>
    .post-container {
      max-width: 820px;
    }

    .back-link {
      font-size: 13px;
      color: var(--text-muted);
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      margin-bottom: 20px;
      transition: color 0.15s;
    }
    .back-link:hover { color: var(--text-primary); }

    /* Post card */
    .post-card {
      background: white;
      border: 1px solid var(--border-color);
      border-radius: 20px;
      padding: 28px 32px;
      margin-bottom: 16px;
    }

    .post-subject-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: 600;
      color: white;
      padding: 3px 10px;
      border-radius: 20px;
      margin-bottom: 14px;
    }

    .post-title {
      font-family: var(--font-heading);
      font-size: 22px;
      font-weight: 600;
      color: var(--text-primary);
      line-height: 1.3;
      margin-bottom: 20px;
    }

    .post-author-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--border-color);
    }

    .post-avatar {
      width: 42px;
      height: 42px;
      min-width: 42px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: 600;
      color: white;
    }

    .post-body {
      font-size: 15px;
      line-height: 1.75;
      color: var(--text-primary);
      white-space: pre-wrap;
    }

    .post-stats {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-top: 20px;
      padding-top: 16px;
      border-top: 1px solid var(--border-color);
      font-size: 13px;
      color: var(--text-muted);
    }

    /* Replies */
    .replies-header {
      font-family: var(--font-heading);
      font-size: 14px;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 12px;
    }

    .reply-card {
      background: white;
      border: 1px solid var(--border-color);
      border-radius: 16px;
      padding: 20px 24px;
      margin-bottom: 12px;
      transition: box-shadow 0.15s;
    }

    .reply-card:hover {
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .reply-avatar {
      width: 36px;
      height: 36px;
      min-width: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
      color: white;
    }

    .reply-body {
      font-size: 14px;
      line-height: 1.65;
      color: var(--text-primary);
      margin-top: 10px;
      white-space: pre-wrap;
    }

    .reply-delete {
      font-size: 12px;
      color: var(--text-muted);
      border: none;
      background: none;
      padding: 0;
      cursor: pointer;
      transition: color 0.15s;
    }
    .reply-delete:hover { color: var(--accent-red); }

    /* Reply composer */
    .reply-composer {
      background: white;
      border: 1px solid var(--border-color);
      border-radius: 16px;
      padding: 20px 24px;
      margin-top: 8px;
    }

    .reply-composer textarea {
      border-radius: 10px;
      border-color: var(--border-color);
      font-size: 14px;
      resize: vertical;
      min-height: 90px;
    }

    .reply-composer textarea:focus {
      border-color: var(--accent-green);
      box-shadow: 0 0 0 3px rgba(51,183,122,0.15);
    }

    @media (max-width: 767px) {
      .post-card { padding: 20px; }
      .post-title { font-size: 18px; }
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
      <div class="post-container mx-auto">

        <!-- Back link -->
        <a href="forums.php?subject=<?= $post['subject_id'] ?>" class="back-link">
          <i class="bi bi-arrow-left"></i> Back to Forums
        </a>

        <!-- Post card -->
        <div class="post-card">
          <span class="post-subject-badge" style="background:<?= htmlspecialchars($post['subject_color']) ?>;">
            <?= htmlspecialchars($post['subject_code']) ?>
          </span>

          <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>

          <div class="post-author-row">
            <div class="post-avatar" style="background:<?= avatarColor($post['full_name']) ?>;">
              <?= initials($post['full_name']) ?>
            </div>
            <div>
              <p class="mb-0 fw-medium" style="font-size:14px;"><?= htmlspecialchars($post['full_name']) ?></p>
              <p class="mb-0 text-muted" style="font-size:12px;"><?= htmlspecialchars($post['role']) ?> &middot; <?= timeAgo($post['created_at']) ?></p>
            </div>
            <?php if ((int)$post['author_id'] === $user_id): ?>
            <button class="ms-auto reply-delete" id="deletePostBtn" data-id="<?= $post_id ?>">
              <i class="bi bi-trash me-1"></i>Delete post
            </button>
            <?php endif; ?>
          </div>

          <?php if (!empty(trim($post['body'] ?? ''))): ?>
          <div class="post-body"><?= htmlspecialchars($post['body']) ?></div>
          <?php else: ?>
          <p class="text-muted fst-italic" style="font-size:14px;">No description provided.</p>
          <?php endif; ?>

          <div class="post-stats">
            <span><i class="bi bi-chat me-1"></i><?= count($replies) ?> <?= count($replies) === 1 ? 'reply' : 'replies' ?></span>
            <span><i class="bi bi-eye me-1"></i><?= (int)$post['views'] ?> views</span>
          </div>
        </div>

        <!-- Replies -->
        <?php if (!empty($replies)): ?>
        <p class="replies-header"><?= count($replies) ?> <?= count($replies) === 1 ? 'Reply' : 'Replies' ?></p>
        <div id="repliesContainer">
          <?php foreach ($replies as $reply): ?>
          <div class="reply-card" id="reply-<?= $reply['id'] ?>">
            <div class="d-flex align-items-center gap-2">
              <div class="reply-avatar" style="background:<?= avatarColor($reply['full_name']) ?>;">
                <?= initials($reply['full_name']) ?>
              </div>
              <div class="flex-grow-1">
                <p class="mb-0 fw-medium" style="font-size:13px;"><?= htmlspecialchars($reply['full_name']) ?></p>
                <p class="mb-0 text-muted" style="font-size:11px;"><?= htmlspecialchars($reply['role']) ?> &middot; <?= timeAgo($reply['created_at']) ?></p>
              </div>
              <?php if ((int)$reply['user_id'] === $user_id): ?>
              <button class="reply-delete delete-reply-btn" data-id="<?= $reply['id'] ?>">
                <i class="bi bi-trash"></i>
              </button>
              <?php endif; ?>
            </div>
            <div class="reply-body"><?= htmlspecialchars($reply['body']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div id="repliesContainer"></div>
        <?php endif; ?>

        <!-- Reply composer -->
        <div class="reply-composer">
          <p class="mb-2 fw-medium" style="font-size:13px;">Add a Reply</p>
          <div id="replyError" class="alert-error mb-2" style="display:none;"></div>
          <textarea class="form-control mb-2" id="replyBody" placeholder="Write your reply here..." rows="3"></textarea>
          <div class="d-flex justify-content-end">
            <button class="btn btn-sm" id="submitReply"
                    style="background:var(--accent-green);color:white;border-radius:10px;padding:7px 20px;font-size:13px;font-weight:500;">
              Post Reply
            </button>
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

  const POST_ID = <?= $post_id ?>;

  // Submit reply
  document.getElementById('submitReply').addEventListener('click', function () {
    const body = document.getElementById('replyBody').value.trim();
    if (!body) return;

    const fd = new FormData();
    fd.append('action', 'create_reply');
    fd.append('post_id', POST_ID);
    fd.append('body', body);

    fetch('actions/forum_actions.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          document.getElementById('replyBody').value = '';
          appendReply(data.reply);
        } else {
          const err = document.getElementById('replyError');
          err.textContent = data.message || 'Failed to post reply.';
          err.style.display = 'block';
        }
      });
  });

  // Append reply to DOM
  function appendReply(reply) {
    const container = document.getElementById('repliesContainer');
    const div = document.createElement('div');
    div.className = 'reply-card';
    div.id = 'reply-' + reply.id;
    div.innerHTML = `
      <div class="d-flex align-items-center gap-2">
        <div class="reply-avatar" style="background:${reply.avatar_color};">${reply.initials}</div>
        <div class="flex-grow-1">
          <p class="mb-0 fw-medium" style="font-size:13px;">${escHtml(reply.full_name)}</p>
          <p class="mb-0 text-muted" style="font-size:11px;">${escHtml(reply.role)} · just now</p>
        </div>
        <button class="reply-delete delete-reply-btn" data-id="${reply.id}">
          <i class="bi bi-trash"></i>
        </button>
      </div>
      <div class="reply-body">${escHtml(reply.body)}</div>`;
    container.appendChild(div);
    div.querySelector('.delete-reply-btn').addEventListener('click', deleteReplyHandler);
    div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  // Delete reply
  function deleteReplyHandler() {
    const id = this.dataset.id;
    if (!confirm('Delete this reply?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_reply');
    fd.append('reply_id', id);
    fetch('actions/forum_actions.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) document.getElementById('reply-' + id)?.remove();
      });
  }

  document.querySelectorAll('.delete-reply-btn').forEach(btn => {
    btn.addEventListener('click', deleteReplyHandler);
  });

  // Delete post
  const deletePostBtn = document.getElementById('deletePostBtn');
  if (deletePostBtn) {
    deletePostBtn.addEventListener('click', function () {
      if (!confirm('Delete this topic? All replies will be removed.')) return;
      const fd = new FormData();
      fd.append('action', 'delete_post');
      fd.append('post_id', POST_ID);
      fetch('actions/forum_actions.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          if (data.success) window.location.href = 'forums.php';
        });
    });
  }

  function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
});
</script>
</body>
</html>