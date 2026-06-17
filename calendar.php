<?php
// ── Session & auth ─────────────────────────────────────────────────────────────
session_start();

// ── Database connection ────────────────────────────────────────────────────────
$host = 'localhost';
$dbName = 'iskonek_db';
$dbUser = 'root';
$dbPass = '';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // throw exceptions on errors

function getDb()
{
    global $host, $dbName, $dbUser, $dbPass;
    static $db;
    if (!$db) {
        $db = new mysqli($host, $dbUser, $dbPass, $dbName);
        $db->set_charset('utf8mb4');
    }
    return $db;
}

// Helper: run a prepared statement and return all rows as assoc array
function dbFetchAll(string $sql, string $types = '', array $params = []): array
{
    $db = getDb();
    $stmt = $db->prepare($sql);
    if ($types && $params)
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Helper: run a prepared statement and return the first row (or null)
function dbFetchOne(string $sql, string $types = '', array $params = []): ?array
{
    $rows = dbFetchAll($sql, $types, $params);
    return $rows[0] ?? null;
}

// Helper: run an INSERT/UPDATE/DELETE prepared statement, return affected rows
function dbExec(string $sql, string $types = '', array $params = []): int
{
    $db = getDb();
    $stmt = $db->prepare($sql);
    if ($types && $params)
        $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->affected_rows;
}

// ── Simulate logged-in user (replace with real session logic) ──────────────────
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // default: Ryan (Student)
}

// ── AJAX handlers ─────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';

if ($action === 'get_deadlines') {
    header('Content-Type: application/json');
    try {
        $userId = (int) $_SESSION['user_id'];

        // FIXED: Query section_id, not section
        $me = dbFetchOne("SELECT role, section_id FROM users WHERE id = ?", 'i', [$userId]);

        if ($me['role'] === 'Admin') {
            // Admins see all section-wide deadlines + their own personal ones
            $rows = dbFetchAll("
                SELECT d.*, CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS creator_name,
                       s.code AS subject_code, s.color AS subject_color
                FROM deadlines d
                LEFT JOIN users    u ON u.id = d.created_by
                LEFT JOIN subjects s ON s.id = d.subject_id
                WHERE d.visibility = 'section' OR d.created_by = ?
                ORDER BY d.due_date, d.due_time
            ", 'i', [$userId]);
        } else {
            // Students see section-wide deadlines for their section + their own personal ones
            // FIXED: use section_id directly
            $section_id = $me['section_id'] ?? 0;
            $rows = dbFetchAll("
                SELECT d.*, CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS creator_name,
                       s.code AS subject_code, s.color AS subject_color
                FROM deadlines d
                LEFT JOIN users    u ON u.id = d.created_by
                LEFT JOIN subjects s ON s.id = d.subject_id
                WHERE (d.visibility = 'section' AND d.section_id = ?)
                   OR (d.visibility = 'personal' AND d.created_by = ?)
                ORDER BY d.due_date, d.due_time
            ", 'ii', [$section_id, $userId]);
        }

        echo json_encode($rows);
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'add_deadline' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $userId = (int) $_SESSION['user_id'];
        $data = json_decode(file_get_contents('php://input'), true);

        // FIXED: Query section_id
        $me = dbFetchOne("SELECT role, section_id FROM users WHERE id = ?", 'i', [$userId]);

        $title = trim($data['title'] ?? '');
        $desc = trim($data['description'] ?? '') ?: null;
        $dueDate = $data['due_date'] ?? '';
        $dueTime = $data['due_time'] ?: null;
        $type = in_array($data['type'] ?? '', ['deadline', 'exam', 'activity', 'event', 'holiday'])
            ? $data['type'] : 'deadline';
        $subjectId = !empty($data['subject_id']) ? (int) $data['subject_id'] : null;
        $visibility = ($me['role'] === 'Admin' && ($data['visibility'] ?? '') === 'section')
            ? 'section' : 'personal';

        if (!$title || !$dueDate) {
            http_response_code(400);
            echo json_encode(['error' => 'Title and due date are required.']);
            exit;
        }

        // FIXED: Resolve section_id directly from the admin's profile or dropdown if needed
        $sectionId = null;
        if ($visibility === 'section') {
            $sectionId = $me['section_id'] ? (int) $me['section_id'] : null;
        }

        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO deadlines
                (title, description, due_date, due_time, type, subject_id, visibility, section_id, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'sssssisis',
            $title,
            $desc,
            $dueDate,
            $dueTime,
            $type,
            $subjectId,
            $visibility,
            $sectionId,
            $userId
        );
        $stmt->execute();

        echo json_encode(['success' => true, 'id' => (int) $db->insert_id]);
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_deadline' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $userId = (int) $_SESSION['user_id'];
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int) ($data['id'] ?? 0);

        $me = dbFetchOne("SELECT role FROM users WHERE id = ?", 'i', [$userId]);

        if ($me['role'] === 'Admin') {
            $affected = dbExec("DELETE FROM deadlines WHERE id = ?", 'i', [$id]);
        } else {
            $affected = dbExec("DELETE FROM deadlines WHERE id = ? AND created_by = ?", 'ii', [$id, $userId]);
        }

        echo json_encode(['success' => true, 'affected' => $affected]);
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Page load: fetch subjects, events, current user ────────────────────────────
$subjects = [];
$events = [];
$currentUser = null;
$dbError = null;

try {
    $db = getDb();

    // Ensure deadlines table exists
    $db->query("
        CREATE TABLE IF NOT EXISTS `deadlines` (
            `id`          int(11)      NOT NULL AUTO_INCREMENT,
            `title`       varchar(255) NOT NULL,
            `description` text         DEFAULT NULL,
            `due_date`    date         NOT NULL,
            `due_time`    time         DEFAULT NULL,
            `type`        enum('deadline','exam','activity','event','holiday') NOT NULL DEFAULT 'deadline',
            `subject_id`  int(11)      DEFAULT NULL,
            `visibility`  enum('section','personal') NOT NULL DEFAULT 'personal',
            `section_id`  int(11)      DEFAULT NULL,
            `created_by`  int(11)      NOT NULL,
            `created_at`  timestamp    NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `created_by` (`created_by`),
            KEY `section_id` (`section_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // FIXED: Use CONCAT_WS instead of 'full_name' for users table
    $currentUser = dbFetchOne("
        SELECT id, CONCAT_WS(' ', first_name, mid_name, last_name) AS full_name, role, section_id 
        FROM users WHERE id = ?",
        'i',
        [(int) $_SESSION['user_id']]
    );

    $subjects = dbFetchAll("SELECT id, code, name, instructor, color FROM subjects ORDER BY code");

    // FIXED: Use CONCAT_WS instead of 'full_name' for users table in JOIN
    $events = dbFetchAll("
        SELECT e.id, e.title, e.description, e.event_date, e.event_time,
               CONCAT_WS(' ', u.first_name, u.mid_name, u.last_name) AS created_by_name
        FROM events e
        LEFT JOIN users u ON u.id = e.created_by
        ORDER BY e.event_date
    ");

} catch (mysqli_sql_exception $e) {
    $dbError = $e->getMessage();
}

$subjectsJson = json_encode($subjects, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$eventsJson = json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$currentUserJson = json_encode($currentUser, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

$initials = $currentUser
    ? implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', $currentUser['full_name'])))
    : '?';
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Iskonek – Campus Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="assets/css/calendar.css">
</head>

<body>

    <div class="app-wrapper">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/navbar.php'; ?>

            <div class="page-body"></div>

            <?php if ($dbError): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 mb-3" style="font-size:.83rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Database error:</strong> <?= htmlspecialchars($dbError) ?> — Check your credentials at the top
                    of this file.
                </div>
            <?php endif; ?>

            <div class="d-flex align-items-start justify-content-between mb-1 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold mb-0" style="font-size:1.5rem;">Campus Calendar</h4>
                    <p class="text-muted mb-0" style="font-size:.82rem;">Track deadlines, exams, and events across all
                        your subjects.</p>
                </div>
                <button class="btn-add-deadline" id="openAddDeadlineBtn">
                    <i class="bi bi-plus-lg"></i> Add Deadline
                </button>
            </div>

            <div class="row g-4 mt-0">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                        <div class="cal-nav">
                            <button class="nav-btn" id="prevBtn"><i class="bi bi-chevron-left"
                                    style="font-size:.75rem;"></i></button>
                            <span class="month-label" id="monthLabel"></span>
                            <button class="nav-btn" id="nextBtn"><i class="bi bi-chevron-right"
                                    style="font-size:.75rem;"></i></button>
                            <button class="today-btn" id="todayBtn">Today</button>
                        </div>
                        <div class="filter-tabs d-flex align-items-center gap-2" id="filterTabs">
                            <button class="filter-tab active" data-filter="all">All</button>
                            <button class="filter-tab" data-filter="deadline">Deadlines</button>
                            <button class="filter-tab" data-filter="exam">Exams</button>
                            <button class="filter-tab" data-filter="event">Events</button>
                            <button class="filter-tab" data-filter="activity">Activities</button>
                            <input type="text" id="searchInput" class="form-control form-control-sm ms-2" placeholder="Search..." style="width:150px;border-radius:10px;">
                        </div>
                    </div>

                    <div class="cal-grid mb-3">
                        <div class="cal-day-headers">
                            <div class="cal-day-header">Sun</div>
                            <div class="cal-day-header">Mon</div>
                            <div class="cal-day-header">Tue</div>
                            <div class="cal-day-header">Wed</div>
                            <div class="cal-day-header">Thu</div>
                            <div class="cal-day-header">Fri</div>
                            <div class="cal-day-header">Sat</div>
                        </div>
                        <div class="cal-days" id="calDays"></div>
                    </div>

                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-dot" style="background:var(--red);"></div> Deadline
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background:var(--blue);"></div> Event
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background:var(--purple);"></div> Exam
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background:var(--green);"></div> Activity
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background:var(--yellow);"></div> Holiday
                        </div>
                        <div class="legend-item">
                            <div
                                style="width:12px;height:8px;border-radius:2px;border-left:3px dashed var(--gray-400);">
                            </div> Personal
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="upcoming-card">
                        <div class="upcoming-title"><i class="bi bi-pin-angle-fill text-danger"></i> Upcoming Events
                        </div>
                        <div id="upcomingList"></div>
                    </div>
                    <div class="upcoming-card mt-3">
                        <div class="upcoming-title"><i class="bi bi-book text-primary"></i> Subjects</div>
                        <div id="subjectList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="eventModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:12px;overflow:hidden;">
                <div class="modal-header event-modal-header border-0 pb-2">
                    <div>
                        <div class="modal-tag" id="modalTag"></div>
                        <h5 class="modal-title fw-bold mb-0" id="modalTitle"></h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-calendar3 text-muted" style="font-size:.85rem;"></i>
                        <span style="font-size:.85rem;" id="modalDate"></span>
                    </div>
                    <div class="mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-book text-muted" style="font-size:.85rem;"></i>
                        <span style="font-size:.85rem;" id="modalSubject"></span>
                    </div>
                    <div class="mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-person text-muted" style="font-size:.85rem;"></i>
                        <span style="font-size:.85rem;" id="modalProf"></span>
                    </div>
                    <div class="mb-2 d-flex align-items-center gap-2" id="modalVisRow" style="display:none!important;">
                        <i class="bi bi-eye text-muted" style="font-size:.85rem;"></i>
                        <span style="font-size:.85rem;" id="modalVis"></span>
                    </div>
                    <hr class="my-2" />
                    <p style="font-size:.83rem;" id="modalDesc"></p>
                </div>
                <div class="modal-footer border-0 pt-0 gap-2">
                    <button class="btn btn-sm btn-danger" id="modalDeleteBtn">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addDeadlineModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0" style="border-radius:12px;overflow:hidden;">
                <div class="modal-header border-0 pb-1"
                    style="background:var(--blue);color:#fff;border-radius:10px 10px 0 0;">
                    <h5 class="modal-title fw-bold mb-0" style="font-size:1rem;">
                        <i class="bi bi-calendar-plus me-1"></i> Add Deadline
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="addFormError" class="alert alert-danger py-2 px-3 mb-3"
                        style="font-size:.8rem;display:none;"></div>

                    <div class="mb-3">
                        <label class="form-label" style="font-size:.8rem;font-weight:600;">Title <span
                                class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="dlTitle"
                            placeholder="e.g. Lab Exercise 3 Submission" />
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label" style="font-size:.8rem;font-weight:600;">Due Date <span
                                    class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="dlDate" />
                        </div>
                        <div class="col-5">
                            <label class="form-label" style="font-size:.8rem;font-weight:600;">Time <span
                                    class="text-muted fw-normal">(optional)</span></label>
                            <input type="time" class="form-control form-control-sm" id="dlTime" />
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label" style="font-size:.8rem;font-weight:600;">Type</label>
                            <select class="form-select form-select-sm" id="dlType">
                                <option value="deadline">Deadline</option>
                                <option value="exam">Exam</option>
                                <option value="activity">Activity</option>
                                <option value="event">Event</option>
                                <option value="holiday">Holiday</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="font-size:.8rem;font-weight:600;">Subject <span
                                    class="text-muted fw-normal">(optional)</span></label>
                            <select class="form-select form-select-sm" id="dlSubject">
                                <option value="">— None —</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.8rem;font-weight:600;">Description <span
                                class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control form-control-sm" id="dlDesc" rows="2"
                            placeholder="Additional details..."></textarea>
                    </div>

                    <div id="visibilityRow" style="display:none;">
                        <hr class="my-2" />
                        <div class="d-flex align-items-center gap-3">
                            <span style="font-size:.82rem;font-weight:600;">Visible to</span>
                            <div class="d-flex gap-2">
                                <label class="d-flex align-items-center gap-1" style="font-size:.8rem;cursor:pointer;">
                                    <input type="radio" name="dlVisibility" value="personal" id="visPersonal" checked />
                                    <span>Only me</span>
                                </label>
                                <label class="d-flex align-items-center gap-1" style="font-size:.8rem;cursor:pointer;">
                                    <input type="radio" name="dlVisibility" value="section" id="visSection" />
                                    <span>Whole section</span>
                                </label>
                            </div>
                        </div>
                        <p class="text-muted mt-1 mb-0" style="font-size:.72rem;" id="visHint">
                            Section-wide deadlines are visible to all students in your section.
                        </p>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-primary" id="saveDeadlineBtn">
                        <i class="bi bi-check-lg"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // FIXED: Added missing Sidebar toggle JS logic to prevent console errors
        const toggleBtn = document.getElementById('sidebarToggle');
        const sidebar   = document.getElementById('sidebar');
        const overlay   = document.getElementById('sidebarOverlay');
        if (toggleBtn) toggleBtn.addEventListener('click', () => { sidebar.classList.toggle('show'); overlay?.classList.toggle('show'); });
        if (overlay)   overlay.addEventListener('click', () => { sidebar.classList.remove('show'); overlay.classList.remove('show'); });

        // ── Injected PHP data ──────────────────────────────────────────────────────────
        const CURRENT_USER = <?= $currentUserJson ?>;
        const SUBJECTS_RAW = <?= $subjectsJson ?>;
        const EVENTS_RAW = <?= $eventsJson ?>;
        const IS_ADMIN = CURRENT_USER && CURRENT_USER.role === 'Admin';

        // Normalise subjects
        const SUBJECTS = SUBJECTS_RAW.map(s => ({
            id: s.id, code: s.code, name: s.name,
            prof: s.instructor ?? '—', color: s.color ?? '#2563eb',
        }));

        // Normalise campus-wide events (from `events` table)
        let EVENTS = EVENTS_RAW.map(e => ({
            id: e.id, date: e.event_date, type: 'event',
            title: e.title, subject: '—',
            desc: e.description ?? '', prof: e.created_by_name ?? '—',
            time: e.event_time ?? null, source: 'event',
            canDelete: false,
        }));

        // Deadlines fetched from DB (populated via loadDeadlines)
        let DEADLINES = [];

        // Combined live list
        let ALL_EVENTS = [];

        <?php if ($dbError): ?>
            console.warn('Database error: <?= addslashes($dbError) ?>');
        <?php endif; ?>

        let currentDate = new Date();
        let activeFilter = 'all';
        let searchQuery = '';

        const typeMap = {
            deadline: { cls: 'chip-deadline', label: 'Deadline', dot: '#ef4444', tagBg: '#fee2e2', tagColor: '#b91c1c' },
            exam: { cls: 'chip-exam', label: 'Exam', dot: '#7c3aed', tagBg: '#ede9fe', tagColor: '#5b21b6' },
            event: { cls: 'chip-event', label: 'Event', dot: '#2563eb', tagBg: '#dbeafe', tagColor: '#1d4ed8' },
            activity: { cls: 'chip-activity', label: 'Activity', dot: '#16a34a', tagBg: '#dcfce7', tagColor: '#15803d' },
            holiday: { cls: 'chip-holiday', label: 'Holiday', dot: '#d97706', tagBg: '#fef3c7', tagColor: '#b45309' },
        };
        const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        // ── Load deadlines from server ─────────────────────────────────────────────────
        async function loadDeadlines() {
            try {
                const res = await fetch('?action=get_deadlines');
                const data = await res.json();
                if (!Array.isArray(data)) throw new Error(data.error ?? 'Unknown error');

                DEADLINES = data.map(d => ({
                    id: d.id,
                    date: d.due_date,
                    type: d.type,
                    title: d.title,
                    subject: d.subject_code ?? '—',
                    desc: d.description ?? '',
                    prof: d.creator_name ?? '—',
                    time: d.due_time ?? null,
                    visibility: d.visibility,
                    source: 'deadline',
                    canDelete: IS_ADMIN || String(d.created_by) === String(CURRENT_USER?.id),
                }));

                rebuildAndRender();
            } catch (err) {
                console.error('Failed to load deadlines:', err);
            }
        }

        function rebuildAndRender() {
            ALL_EVENTS = [...EVENTS, ...DEADLINES];
            renderCalendar();
        }

        // ── Calendar rendering ─────────────────────────────────────────────────────────
        function renderCalendar() {
            const y = currentDate.getFullYear();
            const m = currentDate.getMonth();
            document.getElementById('monthLabel').textContent = `${MONTH_NAMES[m]} ${y}`;

            const firstDay = new Date(y, m, 1).getDay();
            const daysInMonth = new Date(y, m + 1, 0).getDate();
            const daysInPrev = new Date(y, m, 0).getDate();
            const today = new Date();
            const todayStr = fmtDate(today);
            const container = document.getElementById('calDays');
            container.innerHTML = '';

            const totalCells = Math.ceil((firstDay + daysInMonth) / 7) * 7;

            for (let i = 0; i < totalCells; i++) {
                let day, monthOffset = 0;
                if (i < firstDay) { day = daysInPrev - firstDay + i + 1; monthOffset = -1; }
                else if (i >= firstDay + daysInMonth) { day = i - firstDay - daysInMonth + 1; monthOffset = 1; }
                else { day = i - firstDay + 1; }

                const cellMonth = m + monthOffset;
                const cellYear = cellMonth < 0 ? y - 1 : cellMonth > 11 ? y + 1 : y;
                const cellMonthNorm = ((cellMonth % 12) + 12) % 12;
                const dateStr = `${cellYear}-${pad(cellMonthNorm + 1)}-${pad(day)}`;

                const isOther = monthOffset !== 0;
                const isToday = dateStr === todayStr;

                const cell = document.createElement('div');
                cell.className = `cal-cell${isOther ? ' other-month' : ''}${isToday ? ' today' : ''}`;

                const numEl = document.createElement('div');
                numEl.className = 'day-num';
                numEl.textContent = day;
                cell.appendChild(numEl);

                visibleEvents(dateStr).forEach(ev => {
                    const chip = document.createElement('div');
                    const isPersonal = ev.source === 'deadline' && ev.visibility === 'personal';
                    chip.className = `event-chip ${typeMap[ev.type]?.cls ?? 'chip-event'}${isPersonal ? ' chip-personal' : ''}`;
                    const dot = typeMap[ev.type]?.dot ?? '#2563eb';
                    chip.innerHTML = `<span style="width:5px;height:5px;border-radius:50%;background:${dot};flex-shrink:0;display:inline-block;"></span>${isPersonal ? '<span class="vis-badge">me</span> ' : ' '}${escHtml(ev.title)}`;
                    chip.addEventListener('click', () => openModal(ev));
                    cell.appendChild(chip);
                });

                container.appendChild(cell);
            }

            renderUpcoming();
        }

        function visibleEvents(dateStr) {
            return ALL_EVENTS.filter(e =>
                e.date === dateStr &&
                (activeFilter === 'all' || e.type === activeFilter) &&
                (!searchQuery || e.title.toLowerCase().includes(searchQuery))
            );
        }

        function renderUpcoming() {
            const y = currentDate.getFullYear();
            const m = currentDate.getMonth();
            const monthStr = `${y}-${pad(m + 1)}`;

            const upcoming = ALL_EVENTS
                .filter(e =>
                    e.date.startsWith(monthStr) &&
                    (activeFilter === 'all' || e.type === activeFilter) &&
                    (!searchQuery || e.title.toLowerCase().includes(searchQuery))
                )
                .sort((a, b) => a.date.localeCompare(b.date));

            const list = document.getElementById('upcomingList');
            if (!upcoming.length) {
                list.innerHTML = `<p class="text-muted" style="font-size:.8rem;">No events this month.</p>`;
                return;
            }
            list.innerHTML = upcoming.map(ev => {
                const d = new Date(ev.date + 'T00:00:00');
                const label = d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
                const isPersonal = ev.source === 'deadline' && ev.visibility === 'personal';
                const dot = typeMap[ev.type]?.dot ?? '#2563eb';
                return `
        <div class="upcoming-item" style="cursor:pointer;" onclick='openModal(${JSON.stringify(ev).replace(/'/g, "\\'")})'> 
            <div class="upcoming-dot" style="background:${dot};${isPersonal ? 'border:1.5px dashed ' + dot + ';background:transparent;' : ''}"></div>
            <div>
                <div class="upcoming-label">${escHtml(ev.title)}${isPersonal ? ' <span style="font-size:.65rem;color:var(--gray-400);">(personal)</span>' : ''}</div>
                <div class="upcoming-sub">${escHtml(ev.subject)}</div>
            </div>
            <div class="upcoming-date">${label}</div>
        </div>`;
            }).join('');
        }

        function renderSubjects() {
            const list = document.getElementById('subjectList');
            list.innerHTML = SUBJECTS.map(s => `
    <div class="d-flex align-items-start gap-2 py-2" style="border-bottom:1px solid var(--gray-100);">
        <div style="width:8px;height:8px;border-radius:50%;background:${s.color};margin-top:5px;flex-shrink:0;"></div>
        <div>
            <div style="font-size:.78rem;font-weight:600;">${escHtml(s.code)} – ${escHtml(s.name)}</div>
            <div style="font-size:.7rem;color:var(--gray-400);">${escHtml(s.prof)}</div>
        </div>
    </div>`).join('');
        }

        // ── View modal ─────────────────────────────────────────────────────────────────
        let modalCurrentEv = null;

        function openModal(ev) {
            modalCurrentEv = ev;
            const t = typeMap[ev.type] ?? typeMap.event;
            document.getElementById('modalTitle').textContent = ev.title;
            const tag = document.getElementById('modalTag');
            tag.textContent = t.label;
            tag.style.background = t.tagBg;
            tag.style.color = t.tagColor;

            const d = new Date(ev.date + 'T00:00:00');
            let dateLabel = d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            if (ev.time) {
                const [h, min] = ev.time.split(':');
                const hNum = parseInt(h, 10);
                dateLabel += ` · ${hNum % 12 || 12}:${min} ${hNum >= 12 ? 'PM' : 'AM'}`;
            }
            document.getElementById('modalDate').textContent = dateLabel;
            document.getElementById('modalSubject').textContent = ev.subject;
            document.getElementById('modalProf').textContent = ev.prof;
            document.getElementById('modalDesc').textContent = ev.desc;

            // Visibility row (only for deadlines)
            const visRow = document.getElementById('modalVisRow');
            const visEl = document.getElementById('modalVis');
            if (ev.source === 'deadline') {
                visRow.style.display = '';
                visEl.textContent = ev.visibility === 'section'
                    ? '📢 Visible to whole section'
                    : '🔒 Only visible to you';
            } else {
                visRow.style.display = 'none';
            }

            // Delete button — show if user can delete this item
            const delBtn = document.getElementById('modalDeleteBtn');
            delBtn.style.display = ev.canDelete ? '' : 'none';

            new bootstrap.Modal(document.getElementById('eventModal')).show();
        }

        document.getElementById('modalDeleteBtn').addEventListener('click', async () => {
            if (!modalCurrentEv || modalCurrentEv.source !== 'deadline') return;
            if (!confirm(`Delete "${modalCurrentEv.title}"?`)) return;

            try {
                const res = await fetch('?action=delete_deadline', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: modalCurrentEv.id }),
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error ?? 'Delete failed');

                bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
                await loadDeadlines();
            } catch (err) {
                alert('Error deleting: ' + err.message);
            }
        });

        // ── Add deadline modal ─────────────────────────────────────────────────────────
        // Show visibility toggle only for admins
        if (IS_ADMIN) {
            document.getElementById('visibilityRow').style.display = '';
        }

        document.getElementById('openAddDeadlineBtn').addEventListener('click', () => {
            document.getElementById('dlTitle').value = '';
            document.getElementById('dlDate').value = '';
            document.getElementById('dlTime').value = '';
            document.getElementById('dlType').value = 'deadline';
            document.getElementById('dlSubject').value = '';
            document.getElementById('dlDesc').value = '';
            document.getElementById('visPersonal').checked = true;
            document.getElementById('addFormError').style.display = 'none';
            new bootstrap.Modal(document.getElementById('addDeadlineModal')).show();
        });

        document.getElementById('saveDeadlineBtn').addEventListener('click', async () => {
            const title = document.getElementById('dlTitle').value.trim();
            const dueDate = document.getElementById('dlDate').value;
            const errEl = document.getElementById('addFormError');
            errEl.style.display = 'none';

            if (!title || !dueDate) {
                errEl.textContent = 'Title and due date are required.';
                errEl.style.display = '';
                return;
            }

            const visibility = IS_ADMIN
                ? document.querySelector('input[name="dlVisibility"]:checked').value
                : 'personal';

            const payload = {
                title,
                due_date: dueDate,
                due_time: document.getElementById('dlTime').value || null,
                type: document.getElementById('dlType').value,
                subject_id: document.getElementById('dlSubject').value || null,
                description: document.getElementById('dlDesc').value.trim() || null,
                visibility,
            };

            try {
                const saveBtn = document.getElementById('saveDeadlineBtn');
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving…';

                const res = await fetch('?action=add_deadline', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error ?? 'Save failed');

                bootstrap.Modal.getInstance(document.getElementById('addDeadlineModal')).hide();

                // Jump to the month of the new deadline
                const [y, mo] = dueDate.split('-').map(Number);
                currentDate = new Date(y, mo - 1, 1);

                await loadDeadlines();
            } catch (err) {
                document.getElementById('addFormError').textContent = err.message;
                document.getElementById('addFormError').style.display = '';
            } finally {
                const saveBtn = document.getElementById('saveDeadlineBtn');
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-check-lg"></i> Save';
            }
        });

        // ── Nav & filter listeners ─────────────────────────────────────────────────────
        document.getElementById('prevBtn').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); });
        document.getElementById('nextBtn').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); });
        document.getElementById('todayBtn').addEventListener('click', () => { currentDate = new Date(); renderCalendar(); });
        document.getElementById('filterTabs').addEventListener('click', e => {
            if (!e.target.dataset.filter) return;
            activeFilter = e.target.dataset.filter;
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            e.target.classList.add('active');
            renderCalendar();
        });
        
        // FIXED: Safe check for searchInput
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', e => {
                searchQuery = e.target.value.trim().toLowerCase();
                renderCalendar();
            });
        }

        // ── Helpers ────────────────────────────────────────────────────────────────────
        function pad(n) { return String(n).padStart(2, '0'); }
        function fmtDate(d) { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; }
        function escHtml(s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // ── Init ───────────────────────────────────────────────────────────────────────
        renderSubjects();
        loadDeadlines(); // also calls rebuildAndRender → renderCalendar
    </script>
</body>

</html>