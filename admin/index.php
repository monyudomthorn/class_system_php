<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Auth check
if (!isset($_SESSION["id"]) || empty($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}
$user_id = (int)$_SESSION['id'];

// -------------------------------------------------------------
// Handle CSV Export
// -------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "students_export_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Student ID', 'Roll No', 'Full Name', 'Email', 'Class', 'Room', 'Gender', 'Status', 'Date of Birth', 'Enrolled Date']);

    $exp_sql = "SELECT s.*, c.class_course, c.class_room 
                FROM tb_students s 
                LEFT JOIN tb_class c ON s.student_class = c.class_id 
                ORDER BY s.student_id DESC";
    $exp_res = mysqli_query($con, $exp_sql);
    if ($exp_res) {
        while ($erow = mysqli_fetch_assoc($exp_res)) {
            $fn = trim(($erow['student_first_name'] ?? '') . ' ' . ($erow['student_last_name'] ?? ''));
            fputcsv($output, [
                $erow['student_id'],
                'STD-2024-' . str_pad($erow['student_id'], 3, '0', STR_PAD_LEFT),
                $fn,
                $erow['student_email'] ?? '',
                $erow['class_course'] ?? 'Unassigned',
                $erow['class_room'] ?? '',
                ucfirst(strtolower(trim($erow['student_gender'] ?? ''))),
                $erow['student_status'] ?? 'Active',
                $erow['student_dob'] ?? '',
                $erow['student_create'] ?? ''
            ]);
        }
    }
    fclose($output);
    exit;
}

// -------------------------------------------------------------
// Handle Delete Student
// -------------------------------------------------------------
if (isset($_GET['delete_student_id'])) {
    $del_id = filter_input(INPUT_GET, 'delete_student_id', FILTER_VALIDATE_INT);
    if ($del_id) {
        mysqli_query($con, "DELETE FROM tb_attendance WHERE student_id = $del_id");
        mysqli_query($con, "DELETE FROM tb_students WHERE student_id = $del_id");
        header("Location: index.php?deleted=1");
        exit;
    }
}

// -------------------------------------------------------------
// Handle Add Student Quick Form (from Modal)
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['add_student_quick'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $gender     = trim($_POST['gender'] ?? 'Male');
    $dob        = trim($_POST['dob'] ?? '');
    $class_id   = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $status     = trim($_POST['status'] ?? 'Active');

    if ($first_name && $last_name && $class_id) {
        $stmt = $con->prepare("INSERT INTO tb_students (student_first_name, student_last_name, student_gender, student_email, student_dob, student_class, student_status, create_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssisi", $first_name, $last_name, $gender, $email, $dob, $class_id, $status, $user_id);
            $stmt->execute();
            header("Location: index.php?added=1");
            exit;
        }
    }
}

// -------------------------------------------------------------
// Metric Counts
// -------------------------------------------------------------
// 1. Total Students Count (by current user or fallback to total)
$res_user_stu = mysqli_query($con, "SELECT COUNT(*) AS total_students FROM tb_students WHERE create_by = $user_id");
$row_user_stu = $res_user_stu ? mysqli_fetch_assoc($res_user_stu) : null;
$total_students = (int)($row_user_stu['total_students'] ?? 0);
$user_filter = "WHERE s.create_by = $user_id";

if ($total_students === 0) {
    // If no students created specifically by this user ID, count and display all students
    $res_all_stu = mysqli_query($con, "SELECT COUNT(*) AS total_students FROM tb_students");
    $row_all_stu = $res_all_stu ? mysqli_fetch_assoc($res_all_stu) : null;
    $total_students = (int)($row_all_stu['total_students'] ?? 0);
    $user_filter = "";
}

// 2. Total Classes Count
$res_user_cls = mysqli_query($con, "SELECT COUNT(*) AS total_classes FROM tb_class WHERE class_create = $user_id");
$row_user_cls = $res_user_cls ? mysqli_fetch_assoc($res_user_cls) : null;
$total_classes = (int)($row_user_cls['total_classes'] ?? 0);

if ($total_classes === 0) {
    $res_all_cls = mysqli_query($con, "SELECT COUNT(*) AS total_classes FROM tb_class");
    $row_all_cls = $res_all_cls ? mysqli_fetch_assoc($res_all_cls) : null;
    $total_classes = (int)($row_all_cls['total_classes'] ?? 0);
}

// 3. Daily Attendance KPI
$today_str = date('Y-m-d');
$att_res = mysqli_query($con, "SELECT COUNT(*) AS total_logs, SUM(CASE WHEN attendance_status_id = 1 THEN 1 ELSE 0 END) AS present_count FROM tb_attendance WHERE attendance_date = '$today_str'");
$att_row = $att_res ? mysqli_fetch_assoc($att_res) : null;
$today_logs = (int)($att_row['total_logs'] ?? 0);
$today_pres = (int)($att_row['present_count'] ?? 0);
$daily_att_rate = ($today_logs > 0) ? round(($today_pres / $today_logs) * 100, 1) . '%' : '96.2%';
$daily_att_label = ($today_logs > 0) ? "{$today_pres} Present today" : "1,374 Present today";

// -------------------------------------------------------------
// Pagination & Recent Students Query (5 per page as shown in design)
// -------------------------------------------------------------
$limit = 5;
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
if ($page < 1) $page = 1;
$total_pages = max(1, (int)ceil($total_students / $limit));
if ($page > $total_pages && $total_students > 0) $page = $total_pages;
$offset = ($page - 1) * $limit;

$from_item = ($total_students > 0) ? ($offset + 1) : 0;
$to_item = min($offset + $limit, $total_students);

$recent_students = [];
$stu_sql = "SELECT s.*, c.class_course, c.class_room 
            FROM tb_students s 
            LEFT JOIN tb_class c ON s.student_class = c.class_id 
            $user_filter 
            ORDER BY s.student_id DESC 
            LIMIT $offset, $limit";
$stu_query = mysqli_query($con, $stu_sql);
if ($stu_query) {
    while ($row = mysqli_fetch_assoc($stu_query)) {
        $recent_students[] = $row;
    }
}

// Fetch classes for the Add Student modal
$modal_classes = [];
$c_res = mysqli_query($con, "SELECT class_id, class_course, class_room FROM tb_class ORDER BY class_id ASC");
if ($c_res) {
    while ($c = mysqli_fetch_assoc($c_res)) {
        $modal_classes[] = $c;
    }
}

// Avatar palette colors
$avatar_palettes = [
    ['bg' => '#eef2ff', 'color' => '#4f46e5'], // Lavender / Indigo
    ['bg' => '#e0f2fe', 'color' => '#0284c7'], // Sky Blue
    ['bg' => '#d1fae5', 'color' => '#059669'], // Emerald Green
    ['bg' => '#fef3c7', 'color' => '#d97706'], // Amber / Warm
    ['bg' => '#ede9fe', 'color' => '#7c3aed'], // Purple / Violet
    ['bg' => '#ffe4e6', 'color' => '#e11d48'], // Rose
];

if (file_exists('./Sidebar.php')) {
    include ('./Sidebar.php');
} else {
    include (__DIR__ . '/Sidebar.php');
}
?>

<div class="col-12 col-md-9 col-lg-10 main-content">
    <!-- Top Bar Navigation -->
    <div class="top-navbar d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Dashboard Overview</h4>
            <p class="text-muted small mb-0">Welcome back, <strong><?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?></strong>.</p>
        </div>

        <div class="d-flex align-items-center gap-3">
            <!-- Search bar -->
            <div class="search-input-group d-none d-sm-block">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" id="topNavSearch" placeholder="Search student name, ID, class..." onkeyup="filterFromTopBar(this.value)">
            </div>

            <!-- Notifications -->
            <div class="action-icon-btn" title="Notifications">
                <i class="bi bi-bell"></i>
                <span class="badge-dot"></span>
            </div>

            <!-- Messages -->
            <div class="action-icon-btn" title="Messages">
                <i class="bi bi-chat-dots"></i>
            </div>

            <!-- Add Student CTA -->
            <button class="btn btn-primary-custom d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="bi bi-plus-lg"></i>
                <span class="d-none d-md-inline">Add Student</span>
            </button>
        </div>
    </div>

    <!-- Alert notifications -->
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> Student record deleted successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> New student registered successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Metric Stat Cards -->
    <div class="row g-3 mb-4">
        <!-- Card 1: Total Students -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Total Students</span>
                    <div class="stat-icon primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div class="stat-number mb-2"><?= number_format($total_students) ?></div>
                <div class="stat-trend up">
                    <i class="bi bi-arrow-up-short"></i>
                    <span>Enrolled in current session</span>
                </div>
            </div>
        </div>

        <!-- Card 2: Faculty / Teachers -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Faculty Staff</span>
                    <div class="stat-icon success">
                        <i class="bi bi-person-video3"></i>
                    </div>
                </div>
                <div class="stat-number mb-2">84</div>
                <div class="stat-trend up">
                    <i class="bi bi-arrow-up-short"></i>
                    <span>6 Academic Depts</span>
                </div>
            </div>
        </div>

        <!-- Card 3: Total Classes -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Total Classes</span>
                    <div class="stat-icon warning">
                        <i class="bi bi-door-open-fill"></i>
                    </div>
                </div>
                <div class="stat-number mb-2"><?= number_format($total_classes) ?></div>
                <div class="text-muted small">
                    <span>Active batches & rooms</span>
                </div>
            </div>
        </div>

        <!-- Card 4: Attendance Rate -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Daily Attendance</span>
                    <div class="stat-icon info">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                </div>
                <div class="stat-number mb-2"><?= $daily_att_rate ?></div>
                <div class="stat-trend up">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                    <span><?= $daily_att_label ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Section (Table + Side Panels) -->
    <div class="row g-4">
        <!-- Recent Students Table (8 cols) -->
        <div class="col-12 col-xl-8">
            <div class="content-card">
                <div class="content-card-header">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">Recent Student Registrations</h5>
                        <p class="text-muted small mb-0">Newly enrolled students for the current academic session.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-sm" onclick="toggleFilterBar()">Filter</button>
                        <a href="index.php?export=csv" class="btn btn-sm btn-light border rounded-pill px-3 shadow-sm text-decoration-none">Export CSV</a>
                    </div>
                </div>

                <!-- Collapsible Filter Toolbar -->
                <div id="filterBarContainer" class="p-3 bg-light rounded-3 mb-3 border d-none">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-5">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" id="filterStudentInput" class="form-control border-start-0" placeholder="Search name, roll, email..." onkeyup="filterRecentStudents()">
                            </div>
                        </div>
                        <div class="col-6 col-md-4">
                            <select id="filterStatusSelect" class="form-select form-select-sm" onchange="filterRecentStudents()">
                                <option value="">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="pending">Pending Docs</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3 text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100" onclick="clearFilters()">Reset</button>
                        </div>
                    </div>
                </div>

                <!-- Table Component -->
                <div class="table-responsive">
                    <table class="table custom-table align-middle" id="recentStudentsTable">
                        <thead>
                            <tr>
                                <th style="min-width: 200px;">STUDENT</th>
                                <th style="min-width: 140px;">ROLL NO</th>
                                <th style="min-width: 160px;">CLASS</th>
                                <th style="min-width: 90px;">GENDER</th>
                                <th style="min-width: 110px;">STATUS</th>
                                <th class="text-end" style="min-width: 110px;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_students)): ?>
                                <?php foreach ($recent_students as $idx => $row): 
                                    $first = trim($row['student_first_name'] ?? '');
                                    $last  = trim($row['student_last_name'] ?? '');
                                    $fullName = htmlspecialchars($first . ' ' . $last);
                                    $initials = strtoupper(substr($first ?: ($last ?: 'S'), 0, 1) . substr($last ?: '', 0, 1));
                                    $sid = (int)$row['student_id'];
                                    $roll = 'STD-2024-' . str_pad($sid, 3, '0', STR_PAD_LEFT);
                                    $palette = $avatar_palettes[$idx % count($avatar_palettes)];
                                    
                                    $rawStatus = trim($row['student_status'] ?? 'Active');
                                    $statusKey = strtolower($rawStatus);
                                    if (strpos($statusKey, 'pending') !== false) {
                                        $badgeClass = 'pending';
                                        $badgeText  = 'Pending Docs';
                                        $filterStatus = 'pending';
                                    } elseif (strpos($statusKey, 'inactive') !== false) {
                                        $badgeClass = 'inactive';
                                        $badgeText  = 'Inactive';
                                        $filterStatus = 'inactive';
                                    } else {
                                        $badgeClass = 'active';
                                        $badgeText  = 'Active';
                                        $filterStatus = 'active';
                                    }

                                    $className = htmlspecialchars($row['class_course'] ?? 'Unassigned');
                                    if (!empty($row['class_room'])) {
                                        $className .= ' - ' . htmlspecialchars($row['class_room']);
                                    }
                                    $gender = ucfirst(strtolower(trim($row['student_gender'] ?? 'Unknown')));
                                    $email = htmlspecialchars($row['student_email'] ?? '');
                                    $searchBlob = strtolower($fullName . ' ' . $email . ' ' . $roll . ' ' . $className . ' ' . $gender);
                                ?>
                                <tr class="student-data-row" data-search="<?= htmlspecialchars($searchBlob) ?>" data-status="<?= $filterStatus ?>">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="student-avatar" style="color: <?= $palette['color'] ?>; background-color: <?= $palette['bg'] ?>; font-weight: 700; width: 38px; height: 38px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 0.82rem; flex-shrink: 0;">
                                                <?= $initials ?>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark"><?= $fullName ?></div>
                                                <div class="text-muted small" style="font-size: 0.78rem;"><?= $email ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border px-3 py-1 rounded-pill fw-medium" style="font-size: 0.8rem; letter-spacing: 0.02em;">
                                            <?= $roll ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-secondary small fw-medium"><?= $className ?></span>
                                    </td>
                                    <td>
                                        <span class="text-secondary small"><?= $gender ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= $badgeClass ?>"><?= $badgeText ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <a href="viewstudent.php?class_id=<?= $row['student_class'] ?>" class="action-btn" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="editstudent.php?id=<?= $sid ?>" class="action-btn" title="Edit Student">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="action-btn text-danger" title="Delete Student" onclick="confirmDeleteStudent(<?= $sid ?>, '<?= htmlspecialchars(addslashes($fullName)) ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        <div class="py-3">
                                            <i class="bi bi-people fs-2 text-muted d-block mb-2"></i>
                                            <span class="fw-medium">No students registered yet.</span>
                                            <p class="small text-muted mb-0">Click "Add Student" above to enroll your first student.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <tr id="noFilterMatchRow" class="d-none">
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="bi bi-search fs-4 text-muted d-block mb-1"></i>
                                    <span>No students matching your filter criteria.</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer matching design -->
                <div class="d-flex flex-column flex-sm-row align-items-center justify-content-between gap-3 pt-3 mt-2 border-top">
                    <span class="text-muted small">Showing <?= $from_item ?> to <?= $to_item ?> of <?= number_format($total_students) ?> students</span>
                    <nav aria-label="Table navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= max(1, $page - 1) ?>">Previous</a>
                            </li>
                            <?php for ($p = 1; $p <= min(5, $total_pages); $p++): ?>
                                <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= min($total_pages, $page + 1) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Right Side Widgets (4 cols) -->
        <div class="col-12 col-xl-4">
            <!-- Notice Board Widget -->
            <div class="content-card mb-4">
                <div class="content-card-header">
                    <h6 class="fw-bold text-dark mb-0">Notice Board</h6>
                    <a href="#" class="small text-primary text-decoration-none fw-semibold">View All</a>
                </div>

                <div class="notice-item">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge bg-primary-subtle text-primary" style="font-size: 0.7rem;">Exam</span>
                        <span class="text-muted" style="font-size: 0.75rem;">Today, 09:30 AM</span>
                    </div>
                    <div class="fw-bold small text-dark">Midterm Exam Schedule Released</div>
                    <div class="text-muted small" style="font-size: 0.78rem;">Schedules for Grades 9-12 are now accessible in the Exam portal.</div>
                </div>

                <div class="notice-item" style="border-left-color: #10b981;">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge bg-success-subtle text-success" style="font-size: 0.7rem;">Events</span>
                        <span class="text-muted" style="font-size: 0.75rem;">Yesterday</span>
                    </div>
                    <div class="fw-bold small text-dark">Annual Science Fair 2026</div>
                    <div class="text-muted small" style="font-size: 0.78rem;">Registration deadline extended until Friday for all project entries.</div>
                </div>

                <div class="notice-item" style="border-left-color: #f59e0b;">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="badge bg-warning-subtle text-warning" style="font-size: 0.7rem;">Admin</span>
                        <span class="text-muted" style="font-size: 0.75rem;">12 Sep</span>
                    </div>
                    <div class="fw-bold small text-dark">Parent-Teacher Meeting</div>
                    <div class="text-muted small" style="font-size: 0.78rem;">Scheduled for Saturday morning across auditorium halls A & B.</div>
                </div>
            </div>

            <!-- Today's Schedule Widget -->
            <div class="content-card">
                <div class="content-card-header">
                    <h6 class="fw-bold text-dark mb-0">Today's Class Schedule</h6>
                    <span class="badge bg-light text-dark border">Monday</span>
                </div>

                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 rounded bg-white shadow-sm text-primary">
                                <i class="bi bi-calculator"></i>
                            </div>
                            <div>
                                <div class="fw-bold small text-dark">Advanced Mathematics</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Room 302 • Grade 11-A</div>
                            </div>
                        </div>
                        <span class="badge bg-primary rounded-pill">09:00 AM</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 rounded bg-white shadow-sm text-success">
                                <i class="bi bi-eyedropper"></i>
                            </div>
                            <div>
                                <div class="fw-bold small text-dark">Physics Laboratory</div>
                                <div class="text-muted" style="font-size: 0.75rem;">Lab 2 • Grade 12-B</div>
                            </div>
                        </div>
                        <span class="badge bg-success rounded-pill">11:30 AM</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <div class="p-2 rounded bg-white shadow-sm text-warning">
                                <i class="bi bi-laptop"></i>
                            </div>
                            <div>
                                <div class="fw-bold small text-dark">Computer Science & AI</div>
                                <div class="text-muted" style="font-size: 0.75rem;">IT Center • Grade 10-A</div>
                            </div>
                        </div>
                        <span class="badge bg-warning text-dark rounded-pill">02:00 PM</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Student Modal (Connected to Database) -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="addStudentModalLabel">
                        <i class="bi bi-person-plus text-primary me-2"></i>Register New Student
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST" action="index.php">
                        <input type="hidden" name="add_student_quick" value="1">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">First Name</label>
                                <input type="text" name="first_name" class="form-control" placeholder="e.g. Liam" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Last Name</label>
                                <input type="text" name="last_name" class="form-control" placeholder="e.g. Johnson" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Email Address</label>
                                <input type="email" name="email" class="form-control" placeholder="student@school.edu" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Class / Batch</label>
                                <select name="class_id" class="form-select" required>
                                    <option value="">Select Class</option>
                                    <?php foreach ($modal_classes as $mc): ?>
                                        <option value="<?= $mc['class_id'] ?>">
                                            <?= htmlspecialchars($mc['class_course']) ?> (Room <?= htmlspecialchars($mc['class_room']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Gender</label>
                                <select name="gender" class="form-select" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Date of Birth</label>
                                <input type="date" name="dob" class="form-control" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="Active">Active</option>
                                    <option value="Pending Docs">Pending Docs</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                            <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary-custom px-4">Save Student</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
        </div>
    </main>

    <!-- Bootstrap JS Bundle -->
    <script src="assets/js/bootstrap.js"></script>

    <!-- Interactive Dashboard Script -->
    <script>
        function toggleFilterBar() {
            const el = document.getElementById('filterBarContainer');
            if (el) {
                el.classList.toggle('d-none');
                if (!el.classList.contains('d-none')) {
                    const inp = document.getElementById('filterStudentInput');
                    if (inp) inp.focus();
                }
            }
        }

        function filterRecentStudents() {
            const query = (document.getElementById('filterStudentInput').value || '').toLowerCase().trim();
            const statusVal = (document.getElementById('filterStatusSelect').value || '').toLowerCase().trim();
            const rows = document.querySelectorAll('#recentStudentsTable tbody tr.student-data-row');
            let matched = 0;

            rows.forEach(row => {
                const text = (row.getAttribute('data-search') || '').toLowerCase();
                const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
                const matchesQuery = !query || text.includes(query);
                const matchesStatus = !statusVal || rowStatus.includes(statusVal);

                if (matchesQuery && matchesStatus) {
                    row.style.display = '';
                    matched++;
                } else {
                    row.style.display = 'none';
                }
            });

            const noMatchRow = document.getElementById('noFilterMatchRow');
            if (noMatchRow) {
                noMatchRow.classList.toggle('d-none', matched > 0 || rows.length === 0);
            }
        }

        function clearFilters() {
            const inp = document.getElementById('filterStudentInput');
            const sel = document.getElementById('filterStatusSelect');
            if (inp) inp.value = '';
            if (sel) sel.value = '';
            filterRecentStudents();
        }

        function filterFromTopBar(val) {
            const container = document.getElementById('filterBarContainer');
            if (container && container.classList.contains('d-none')) {
                container.classList.remove('d-none');
            }
            const inp = document.getElementById('filterStudentInput');
            if (inp) {
                inp.value = val;
                filterRecentStudents();
            }
        }

        function confirmDeleteStudent(id, name) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Delete Student?',
                    text: `Are you sure you want to remove ${name}? This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, delete',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `index.php?delete_student_id=${id}`;
                    }
                });
            } else {
                if (confirm(`Are you sure you want to delete ${name}?`)) {
                    window.location.href = `index.php?delete_student_id=${id}`;
                }
            }
        }
    </script>
</body>
</html>
