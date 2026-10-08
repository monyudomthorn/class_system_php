<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Ensure user is authenticated
if (!isset($_SESSION["id"]) || empty($_SESSION["id"])) {
    header("Location: login.php");
    exit;
}
$user_id = (int)$_SESSION['id'];

date_default_timezone_set('Asia/Phnom_Penh');
$now = new DateTime();

// Selected attendance date (defaults to today)
$attendance_date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_SPECIAL_CHARS);
if (!$attendance_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $attendance_date)) {
    $attendance_date = $now->format('Y-m-d');
}
$date_obj = DateTime::createFromFormat('Y-m-d', $attendance_date) ?: $now;

// Fetch all classes for the selector dropdown
$all_classes = [];
if (isset($con) && $con) {
    $c_query = $con->query("SELECT `class_id`, `class_course`, `class_room`, `class_building` FROM `tb_class` ORDER BY `class_id` ASC");
    if ($c_query) {
        while ($c = $c_query->fetch_assoc()) {
            $all_classes[] = $c;
        }
    }
}

// Class ID selection
$class_id = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
if (!$class_id && !empty($all_classes)) {
    $class_id = (int)$all_classes[0]['class_id'];
}

// Status mapping
$status_map = [
    'present' => 1,
    'late'    => 2,
    'absent'  => 3,
    'excused' => 4,
    1         => 1,
    2         => 2,
    3         => 3,
    4         => 4
];
$status_code_map = [
    1 => 'present',
    2 => 'late',
    3 => 'absent',
    4 => 'excused'
];

// -------------------------------------------------------------
// Handle CSV Export
// -------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_class_id = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT) ?: $class_id;
    $export_date = $_GET['date'] ?? $attendance_date;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $export_date)) {
        $export_date = $now->format('Y-m-d');
    }

    $c_course = "Class";
    $c_room = "";
    if ($export_class_id && isset($con) && $con) {
        $c_meta = $con->query("SELECT `class_course`, `class_room` FROM `tb_class` WHERE `class_id` = $export_class_id");
        if ($c_meta && $crow = $c_meta->fetch_assoc()) {
            $c_course = $crow['class_course'];
            $c_room = $crow['class_room'];
        }
    }

    $filename = "attendance_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $c_course) . "_" . $export_date . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for proper Excel display
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['Student ID', 'Roll Number', 'Full Name', 'Gender', 'Email', 'Class', 'Room', 'Date', 'Status', 'Remarks', 'Recorded By']);

    if ($export_class_id && isset($con) && $con) {
        $exp_sql = "SELECT s.student_id, s.student_first_name, s.student_last_name, s.student_gender, s.student_email,
                           COALESCE(st.name, 'Unrecorded') AS status_name,
                           a.attendance_remarks,
                           COALESCE(u.users_fullname, 'System') AS recorder_name
                    FROM tb_students s
                    LEFT JOIN tb_attendance a ON s.student_id = a.student_id AND a.attendance_date = '$export_date'
                    LEFT JOIN attendance_statuses st ON a.attendance_status_id = st.id
                    LEFT JOIN tb_users u ON a.attendance_record = u.users_id
                    WHERE s.student_class = $export_class_id
                    ORDER BY s.student_id ASC";
        $exp_res = $con->query($exp_sql);
        if ($exp_res) {
            while ($erow = $exp_res->fetch_assoc()) {
                fputcsv($output, [
                    $erow['student_id'],
                    'B-' . $erow['student_id'],
                    trim(($erow['student_last_name'] ?? '') . ' ' . ($erow['student_first_name'] ?? '')),
                    ucfirst($erow['student_gender'] ?? ''),
                    $erow['student_email'] ?? '',
                    $c_course,
                    $c_room,
                    $export_date,
                    $erow['status_name'],
                    $erow['attendance_remarks'] ?? '',
                    $erow['recorder_name']
                ]);
            }
        }
    }
    fclose($output);
    exit;
}

// -------------------------------------------------------------
// Handle Attendance Save (POST)
// -------------------------------------------------------------
$save_feedback = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    $post_class_id = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT) ?: $class_id;
    $post_date = $_POST['attendance_date'] ?? $attendance_date;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $post_date)) {
        $post_date = $now->format('Y-m-d');
    }
    $attendance_entries = $_POST['attendance'] ?? [];
    $remarks_entries = $_POST['remarks'] ?? [];
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || isset($_POST['ajax']);

    if ($post_class_id && !empty($attendance_entries) && isset($con) && $con) {
        $check_stmt = $con->prepare("SELECT `attendance_id` FROM `tb_attendance` WHERE `student_id` = ? AND `attendance_date` = ?");
        $update_stmt = $con->prepare("UPDATE `tb_attendance` SET `attendance_status_id` = ?, `attendance_remarks` = ?, `attendance_record` = ? WHERE `student_id` = ? AND `attendance_date` = ?");
        $insert_stmt = $con->prepare("INSERT INTO `tb_attendance` (`student_id`, `attendance_date`, `attendance_remarks`, `attendance_record`, `attendance_status_id`) VALUES (?, ?, ?, ?, ?)");

        $saved_count = 0;
        foreach ($attendance_entries as $s_id => $raw_status) {
            $s_id = (int)$s_id;
            $st_id = $status_map[$raw_status] ?? 1;
            $remark = isset($remarks_entries[$s_id]) ? trim(substr($remarks_entries[$s_id], 0, 100)) : null;
            if ($remark === '') $remark = null;

            $check_stmt->bind_param("is", $s_id, $post_date);
            $check_stmt->execute();
            $existing = $check_stmt->get_result()->fetch_assoc();

            if ($existing) {
                $update_stmt->bind_param("isiss", $st_id, $remark, $user_id, $s_id, $post_date);
                $update_stmt->execute();
            } else {
                $insert_stmt->bind_param("issii", $s_id, $post_date, $remark, $user_id, $st_id);
                $insert_stmt->execute();
            }
            $saved_count++;
        }

        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => "Attendance records for {$saved_count} student(s) saved successfully!",
                'count' => $saved_count,
                'date' => $post_date
            ]);
            exit;
        }

        $save_feedback = [
            'type' => 'success',
            'message' => "Attendance records for {$saved_count} student(s) saved successfully!"
        ];
        $attendance_date = $post_date;
        $date_obj = DateTime::createFromFormat('Y-m-d', $attendance_date) ?: $now;
    } else {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No attendance data found to save.'
            ]);
            exit;
        }
        $save_feedback = [
            'type' => 'danger',
            'message' => 'No attendance data found to save.'
        ];
    }
}

// -------------------------------------------------------------
// Fetch Enrolled Students & Saved Attendance for Selected Class
// -------------------------------------------------------------
$current_class = null;
foreach ($all_classes as $cls) {
    if ((int)$cls['class_id'] === (int)$class_id) {
        $current_class = $cls;
        break;
    }
}

$students = [];
if ($class_id && isset($con) && $con) {
    $stmt = $con->prepare("SELECT `student_id`, `student_first_name`, `student_last_name`, `student_gender`, `student_email`, `student_dob`, `student_create` 
                           FROM `tb_students` WHERE `student_class` = ? ORDER BY `student_id` ASC");
    if ($stmt) {
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $students[] = $r;
        }
    }
}
$student_count = count($students);

// Query saved attendance for this class and date
$saved_attendance = [];
$has_saved_records = false;
if ($class_id && $student_count > 0 && isset($con) && $con) {
    $att_stmt = $con->prepare("
        SELECT a.attendance_id, a.student_id, a.attendance_status_id, a.attendance_remarks, a.attendance_create_at,
               u.users_fullname
        FROM tb_attendance a
        JOIN tb_students s ON a.student_id = s.student_id
        LEFT JOIN tb_users u ON a.attendance_record = u.users_id
        WHERE s.student_class = ? AND a.attendance_date = ?
    ");
    if ($att_stmt) {
        $att_stmt->bind_param("is", $class_id, $attendance_date);
        $att_stmt->execute();
        $att_res = $att_stmt->get_result();
        while ($arow = $att_res->fetch_assoc()) {
            $saved_attendance[(int)$arow['student_id']] = $arow;
        }
    }
    $has_saved_records = !empty($saved_attendance);
}

// Calculate initial metrics for this session
$init_present = 0;
$init_late = 0;
$init_absent = 0;
$init_excused = 0;

foreach ($students as $stu) {
    $sid = (int)$stu['student_id'];
    if (isset($saved_attendance[$sid])) {
        $st_id = (int)$saved_attendance[$sid]['attendance_status_id'];
    } else {
        $st_id = 1; // Default to present
    }
    if ($st_id === 1) $init_present++;
    elseif ($st_id === 2) $init_late++;
    elseif ($st_id === 3) $init_absent++;
    elseif ($st_id === 4) $init_excused++;
}

$init_rate = ($student_count > 0) ? round((($init_present + ($init_late * 0.5)) / $student_count) * 100) : 0;
$present_pct = ($student_count > 0) ? round(($init_present / $student_count) * 100, 1) : 0;
$absent_pct = ($student_count > 0) ? round(($init_absent / $student_count) * 100, 1) : 0;

// -------------------------------------------------------------
// Calculate Weekly Trend (Monday to Friday of selected date)
// -------------------------------------------------------------
$weekly_trend = [];
if ($class_id && isset($con) && $con) {
    $week_start = clone $date_obj;
    $dayOfWeek = (int)$week_start->format('N');
    $week_start->modify('-' . ($dayOfWeek - 1) . ' days');

    for ($d = 0; $d < 5; $d++) {
        $cur_day = clone $week_start;
        $cur_day->modify("+$d days");
        $d_ymd = $cur_day->format('Y-m-d');
        $d_label = $cur_day->format('l');

        $w_sql = "SELECT COUNT(*) AS total, 
                         SUM(CASE WHEN a.attendance_status_id = 1 THEN 1 ELSE 0 END) AS present_cnt,
                         SUM(CASE WHEN a.attendance_status_id = 2 THEN 1 ELSE 0 END) AS late_cnt
                  FROM tb_attendance a
                  JOIN tb_students s ON a.student_id = s.student_id
                  WHERE s.student_class = $class_id AND a.attendance_date = '$d_ymd'";
        $w_res = $con->query($w_sql);
        $w_row = $w_res ? $w_res->fetch_assoc() : null;
        $w_total = (int)($w_row['total'] ?? 0);
        $w_pres = (int)($w_row['present_cnt'] ?? 0);
        $w_late = (int)($w_row['late_cnt'] ?? 0);

        $w_pct = ($w_total > 0) ? round((($w_pres + ($w_late * 0.5)) / $w_total) * 100) : null;
        $weekly_trend[] = [
            'day' => $d_label,
            'date' => $d_ymd,
            'rate' => $w_pct,
            'is_current' => ($d_ymd === $attendance_date)
        ];
    }
}

// -------------------------------------------------------------
// Calculate Real Attendance Alerts for this class
// -------------------------------------------------------------
$attendance_alerts = [];
if ($class_id && isset($con) && $con) {
    $alert_sql = "SELECT s.student_id, s.student_first_name, s.student_last_name,
                         SUM(CASE WHEN a.attendance_status_id = 3 THEN 1 ELSE 0 END) AS unexcused_absences,
                         SUM(CASE WHEN a.attendance_status_id = 2 THEN 1 ELSE 0 END) AS late_checkins
                  FROM tb_students s
                  JOIN tb_attendance a ON s.student_id = a.student_id
                  WHERE s.student_class = $class_id
                  GROUP BY s.student_id
                  HAVING unexcused_absences >= 2 OR late_checkins >= 3
                  ORDER BY unexcused_absences DESC, late_checkins DESC
                  LIMIT 5";
    $alert_res = $con->query($alert_sql);
    if ($alert_res) {
        while ($alr = $alert_res->fetch_assoc()) {
            $attendance_alerts[] = $alr;
        }
    }
}

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
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="classes.php" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 rounded-pill px-3 py-1 text-decoration-none shadow-sm">
                    <i class="bi bi-arrow-left"></i> Classes
                </a>
                <h4 class="fw-bold mb-0 text-dark">Student Attendance Management</h4>
            </div>
            <p class="text-muted small mb-0">
                Record, monitor, and review daily class attendance for 
                <strong class="text-dark"><?= htmlspecialchars($current_class['class_course'] ?? 'Selected Class') ?></strong> 
                (Room <?= htmlspecialchars($current_class['class_room'] ?? '-') ?>).
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Date Display Badge -->
            <span class="badge bg-light text-dark border px-3 py-2 fw-semibold d-none d-sm-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-calendar-event text-primary"></i>
                <span id="currentDateDisplay"><?= $date_obj->format('F d, Y') ?></span>
            </span>

            <!-- Saved State Indicator Badge -->
            <?php if ($has_saved_records): ?>
                <span id="savedStatusBadge" class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-check-circle-fill"></i> Saved (<?= count($saved_attendance) ?> logged)
                </span>
            <?php else: ?>
                <span id="savedStatusBadge" class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm">
                    <i class="bi bi-exclamation-circle-fill"></i> Not Saved Yet
                </span>
            <?php endif; ?>

            <!-- Export Attendance Report -->
            <a href="attendance.php?export=csv&class_id=<?= $class_id ?>&date=<?= urlencode($attendance_date) ?>" class="btn btn-light border d-flex align-items-center gap-2 shadow-sm text-decoration-none" title="Download attendance log as CSV">
                <i class="bi bi-download"></i>
                <span class="d-none d-md-inline">Export CSV</span>
            </a>

            <!-- Save Attendance CTA -->
            <button type="button" id="saveAttendanceBtn" class="btn btn-primary-custom d-flex align-items-center gap-2 shadow-sm" onclick="saveAttendanceSheet()">
                <i class="bi bi-check2-circle"></i>
                <span>Save Attendance</span>
            </button>
        </div>
    </div>

    <!-- Feedback Alert if POST submit -->
    <?php if ($save_feedback): ?>
        <div class="alert alert-<?= $save_feedback['type'] ?> alert-dismissible fade show mb-4" role="alert">
            <i class="bi <?= $save_feedback['type'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
            <?= htmlspecialchars($save_feedback['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Success Feedback Alert (AJAX) -->
    <div id="attendanceSuccessAlert" class="alert alert-success alert-dismissible fade d-none mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <span id="attendanceAlertMessage">Attendance sheet has been saved successfully!</span>
        <button type="button" class="btn-close" onclick="document.getElementById('attendanceSuccessAlert').classList.add('d-none');"></button>
    </div>

    <!-- Quick Stats Metric Cards -->
    <div class="row g-3 mb-4">
        <!-- Present Today -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Present Today</span>
                    <div class="stat-icon success">
                        <i class="bi bi-person-check-fill"></i>
                    </div>
                </div>
                <div class="stat-number mb-2" id="kpiPresent"><?= $present_pct ?>%</div>
                <div class="stat-trend up">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                    <span id="kpiPresentCount"><?= $init_present ?> / <?= $student_count ?> students present</span>
                </div>
            </div>
        </div>

        <!-- Absent Today -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Absences</span>
                    <div class="stat-icon" style="background-color: #fef2f2; color: #ef4444;">
                        <i class="bi bi-person-x-fill"></i>
                    </div>
                </div>
                <div class="stat-number mb-2" id="kpiAbsent"><?= $init_absent ?></div>
                <div class="stat-trend down">
                    <span id="kpiAbsentCount"><?= $absent_pct ?>% of total enrollment</span>
                </div>
            </div>
        </div>

        <!-- Late Arrivals -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Late Arrivals</span>
                    <div class="stat-icon warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
                <div class="stat-number mb-2" id="kpiLate"><?= $init_late ?></div>
                <div class="text-muted small">
                    <span id="kpiLateDesc">Tardy logged for this session</span>
                </div>
            </div>
        </div>

        <!-- Excused Leaves -->
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Excused Leave</span>
                    <div class="stat-icon info">
                        <i class="bi bi-envelope-paper-fill"></i>
                    </div>
                </div>
                <div class="stat-number mb-2" id="kpiExcused"><?= $init_excused ?></div>
                <div class="text-muted small">
                    <span id="kpiExcusedDesc">Approved leaves / medical notes</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Class Selector & Live Controls Panel -->
    <div class="content-card mb-4">
        <div class="row g-3 align-items-center">
            <!-- Select Class -->
            <div class="col-12 col-md-4">
                <label class="form-label form-label-custom mb-1">Select Class / Batch</label>
                <select class="form-select form-select-custom" id="classSelectDropdown" onchange="changeClassSession(this.value)">
                    <?php if (!empty($all_classes)): ?>
                        <?php foreach ($all_classes as $cls): ?>
                            <option value="<?= $cls['class_id'] ?>" <?= ($class_id == $cls['class_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['class_course']) ?> • Room <?= htmlspecialchars($cls['class_room']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="">No classes available</option>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Attendance Date with Quick Prev / Today / Next Controls -->
            <div class="col-12 col-md-4">
                <label class="form-label form-label-custom mb-1">Attendance Date</label>
                <div class="input-group">
                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="navigateDate(-1)" title="Previous Day">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <input type="date" class="form-control form-select-custom text-center" id="attendanceDateInput" value="<?= htmlspecialchars($attendance_date) ?>" onchange="changeAttendanceDate(this.value)">
                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="navigateDate(1)" title="Next Day">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button class="btn btn-outline-primary btn-sm px-3" type="button" onclick="goToToday()" title="Jump to Today">
                        Today
                    </button>
                </div>
            </div>

            <!-- Quick Action Tools -->
            <div class="col-12 col-md-4 d-flex align-items-end justify-content-md-end gap-2 pt-2 pt-md-0">
                <button type="button" class="btn btn-outline-success btn-sm px-3 d-flex align-items-center gap-1" onclick="markAllPresent()">
                    <i class="bi bi-check-all"></i>
                    <span>Mark All Present</span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm px-3 d-flex align-items-center gap-1" onclick="resetAttendanceSheet()">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Reset</span>
                </button>
            </div>
        </div>

        <!-- Live Session Breakdown Bar -->
        <div class="mt-4 pt-3 border-top">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="small fw-bold text-dark me-2">Session Summary:</span>
                    <span class="badge bg-success-subtle text-success fw-semibold" id="countBadgePresent"><?= $init_present ?> Present</span>
                    <span class="badge bg-warning-subtle text-warning fw-semibold" id="countBadgeLate"><?= $init_late ?> Late</span>
                    <span class="badge bg-danger-subtle text-danger fw-semibold" id="countBadgeAbsent"><?= $init_absent ?> Absent</span>
                    <span class="badge bg-info-subtle text-info fw-semibold" id="countBadgeExcused"><?= $init_excused ?> Excused</span>
                </div>
                <div class="small text-muted" id="sessionRateText">
                    Attendance Rate: <strong><?= $init_rate ?>%</strong>
                </div>
            </div>

            <div class="attendance-rate-bar">
                <div class="bar-segment bg-success" id="barPresent" style="width: <?= ($student_count > 0 ? ($init_present / $student_count) * 100 : 0) ?>%;"></div>
                <div class="bar-segment bg-warning" id="barLate" style="width: <?= ($student_count > 0 ? ($init_late / $student_count) * 100 : 0) ?>%;"></div>
                <div class="bar-segment bg-danger" id="barAbsent" style="width: <?= ($student_count > 0 ? ($init_absent / $student_count) * 100 : 0) ?>%;"></div>
                <div class="bar-segment bg-info" id="barExcused" style="width: <?= ($student_count > 0 ? ($init_excused / $student_count) * 100 : 0) ?>%;"></div>
            </div>
        </div>
    </div>

    <!-- Main Content: Student Attendance Roster List (Cards) -->
    <div class="row g-4">
        <!-- Student Roster Column (8 cols) -->
        <div class="col-12 col-xl-8">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold text-dark mb-0">Student Roster (<span id="totalStudentsCount"><?= $student_count ?></span> enrolled)</h5>
                
                <!-- Search Student in roster -->
                <div class="search-input-group" style="max-width: 260px;">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control form-control-sm" id="searchRosterInput" placeholder="Filter student name, roll..." onkeyup="filterRoster()">
                </div>
            </div>

            <!-- Attendance Form Form Wrapper -->
            <form id="attendanceSheetForm" method="POST" action="attendance.php?class_id=<?= $class_id ?>&date=<?= urlencode($attendance_date) ?>">
                <input type="hidden" name="action" value="save_attendance">
                <input type="hidden" name="class_id" value="<?= $class_id ?>">
                <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($attendance_date) ?>">

                <div id="rosterContainer">
                    <?php if (!empty($student_count) && $student_count > 0): ?>
                        <?php foreach ($students as $row): 
                            $sid = (int)$row['student_id'];
                            $first = trim($row['student_first_name'] ?? '');
                            $last = trim($row['student_last_name'] ?? '');
                            $fullName = htmlspecialchars($last . ' ' . $first);
                            $initials = strtoupper(substr($last ?: ($first ?: 'S'), 0, 1) . substr($first ?: '', 0, 1));
                            $roll = 'B-' . $sid;

                            // Determine saved status & remarks
                            $current_status = 'present';
                            $current_remark = '';
                            if (isset($saved_attendance[$sid])) {
                                $st_num = (int)$saved_attendance[$sid]['attendance_status_id'];
                                $current_status = $status_code_map[$st_num] ?? 'present';
                                $current_remark = $saved_attendance[$sid]['attendance_remarks'] ?? '';
                            }
                        ?>
                        <div class="attendance-card student-row" data-name="<?= htmlspecialchars(strtolower($fullName . ' ' . $roll . ' ' . ($row['student_email'] ?? ''))) ?>" data-student-id="<?= $sid ?>">
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="student-avatar" style="width: 44px; height: 44px; font-size: 1rem; background-color: #eef2ff; color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                                        <?= $initials ?>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0"><?=$fullName?></h6>
                                        <span class="text-muted small">Roll: <strong><?= htmlspecialchars($roll) ?></strong> • <?= htmlspecialchars($row['student_email'] ?? '') ?></span>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <div class="attendance-pill-group">
                                        <label title="Present">
                                            <input type="radio" name="attendance[<?= $sid ?>]" value="present" class="attendance-radio-btn" <?= ($current_status === 'present') ? 'checked' : '' ?> onchange="updateAttendanceMetrics()">
                                            <span class="attendance-btn-label">P</span>
                                        </label>
                                        <label title="Late">
                                            <input type="radio" name="attendance[<?= $sid ?>]" value="late" class="attendance-radio-btn" <?= ($current_status === 'late') ? 'checked' : '' ?> onchange="updateAttendanceMetrics()">
                                            <span class="attendance-btn-label">L</span>
                                        </label>
                                        <label title="Absent">
                                            <input type="radio" name="attendance[<?= $sid ?>]" value="absent" class="attendance-radio-btn" <?= ($current_status === 'absent') ? 'checked' : '' ?> onchange="updateAttendanceMetrics()">
                                            <span class="attendance-btn-label">A</span>
                                        </label>
                                        <label title="Excused">
                                            <input type="radio" name="attendance[<?= $sid ?>]" value="excused" class="attendance-radio-btn" <?= ($current_status === 'excused') ? 'checked' : '' ?> onchange="updateAttendanceMetrics()">
                                            <span class="attendance-btn-label">E</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Optional Remark Note Field -->
                            <div class="mt-2 pt-2 border-top d-flex align-items-center gap-2">
                                <i class="bi bi-chat-left-text text-muted small"></i>
                                <input type="text" name="remarks[<?= $sid ?>]" value="<?= htmlspecialchars($current_remark) ?>" class="form-control form-control-sm border-0 bg-light py-1 px-2 text-secondary" placeholder="Optional remark note (e.g. sick, doctor visit, excused)..." style="font-size: 0.8rem; border-radius: 6px;">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Empty State: No Students in this Class -->
                        <div class="card border-0 bg-light p-5 text-center mb-3 rounded-4">
                            <div class="bg-white d-inline-flex p-3 rounded-circle mb-3 mx-auto shadow-sm" style="border: 2px dashed #cbd5e1; width: 64px; height: 64px; align-items: center; justify-content: center;">
                                <i class="bi bi-person-slash fs-3 text-muted"></i>
                            </div>
                            <h6 class="text-dark fw-bold">No Students Enrolled in this Class</h6>
                            <p class="text-muted small mb-3">Add students to this class in Class Management to begin recording attendance.</p>
                            <div>
                                <a href="student.php?class_id=<?= $class_id ?>" class="btn btn-sm btn-primary-custom px-3">
                                    <i class="bi bi-person-plus-fill me-1"></i> Add Student
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div id="noFilterResults" class="card border-0 bg-light p-4 text-center mb-3 d-none rounded-3">
                        <i class="bi bi-search text-muted fs-4 mb-2"></i>
                        <div class="fw-semibold text-dark small">No matching students found</div>
                        <div class="text-muted small">Try checking student name or roll number.</div>
                    </div>
                </div>

                <!-- Bottom Sticky Save CTA for large rosters -->
                <?php if ($student_count > 0): ?>
                    <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                        <button type="button" class="btn btn-outline-secondary px-3" onclick="resetAttendanceSheet()">
                            Reset Form
                        </button>
                        <button type="button" class="btn btn-primary-custom px-4 d-flex align-items-center gap-2" onclick="saveAttendanceSheet()">
                            <i class="bi bi-check2-circle"></i> Save Attendance
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <!-- Right Side Attendance Widgets (4 cols) -->
        <div class="col-12 col-xl-4">
            <!-- Legend Card -->
            <div class="content-card mb-4">
                <div class="content-card-header mb-3">
                    <h6 class="fw-bold text-dark mb-0">Attendance Status Legend</h6>
                </div>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-success px-2 py-1">P</span>
                            <span class="small fw-semibold text-dark">Present</span>
                        </div>
                        <span class="text-muted small">Attending session</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning text-dark px-2 py-1">L</span>
                            <span class="small fw-semibold text-dark">Late / Tardy</span>
                        </div>
                        <span class="text-muted small">>10 mins delayed</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-danger px-2 py-1">A</span>
                            <span class="small fw-semibold text-dark">Absent</span>
                        </div>
                        <span class="text-muted small">Unexcused absence</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-info text-white px-2 py-1">E</span>
                            <span class="small fw-semibold text-dark">Excused</span>
                        </div>
                        <span class="text-muted small">Leave with notice</span>
                    </div>
                </div>
            </div>

            <!-- Weekly Trend Widget -->
            <div class="content-card mb-4">
                <div class="content-card-header mb-3 d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold text-dark mb-0">Weekly Average Rate</h6>
                    <span class="badge bg-success-subtle text-success">This Week</span>
                </div>

                <div class="d-flex flex-column gap-3">
                    <?php if (!empty($weekly_trend)): ?>
                        <?php foreach ($weekly_trend as $wday): 
                            $wRate = $wday['rate'];
                            $wBarWidth = ($wRate !== null) ? $wRate : 0;
                            $barClass = ($wRate !== null && $wRate >= 90) ? 'bg-success' : (($wRate !== null && $wRate >= 75) ? 'bg-warning' : 'bg-danger');
                        ?>
                        <div class="<?= $wday['is_current'] ? 'p-2 rounded bg-light border' : '' ?>">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-medium text-dark">
                                    <?= htmlspecialchars($wday['day']) ?>
                                    <?php if ($wday['is_current']): ?>
                                        <span class="badge bg-primary-subtle text-primary ms-1" style="font-size: 0.68rem;">Selected</span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($wRate !== null): ?>
                                    <span class="fw-bold text-dark"><?= $wRate ?>%</span>
                                <?php else: ?>
                                    <span class="text-muted small">No session</span>
                                <?php endif; ?>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar <?= $barClass ?>" style="width: <?= $wBarWidth ?>%;"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted small text-center py-2">No weekly data available.</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attendance Advisory Warnings -->
            <div class="content-card">
                <div class="content-card-header mb-3 d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold text-dark mb-0">Attendance Alerts</h6>
                    <span class="badge bg-danger-subtle text-danger">Action Required</span>
                </div>

                <?php if (!empty($attendance_alerts)): ?>
                    <?php foreach ($attendance_alerts as $al): 
                        $al_name = trim($al['student_last_name'] . ' ' . $al['student_first_name']);
                        $unexcused = (int)$al['unexcused_absences'];
                        $late_cnt = (int)$al['late_checkins'];
                    ?>
                        <div class="notice-item mb-2" style="border-left-color: <?= $unexcused >= 3 ? '#ef4444' : '#f59e0b' ?>;">
                            <div class="fw-bold small text-dark"><?= htmlspecialchars($al_name) ?> (B-<?= $al['student_id'] ?>)</div>
                            <div class="text-muted small" style="font-size: 0.78rem;">
                                Logged <?= $unexcused ?> unexcused absence(s) and <?= $late_cnt ?> late arrival(s). Class counselor follow-up suggested.
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-3 text-muted small">
                        <i class="bi bi-shield-check text-success fs-4 d-block mb-1"></i>
                        <span>All students in this class are currently in good attendance standing!</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
        </div>
    </main>

    <!-- Bootstrap JS Bundle -->
    <script src="assets/js/bootstrap.js"></script>

    <!-- Attendance Interactive Logic -->
    <script>
        function updateAttendanceMetrics() {
            const rows = document.querySelectorAll('.student-row');
            let presentCount = 0;
            let lateCount = 0;
            let absentCount = 0;
            let excusedCount = 0;

            rows.forEach(row => {
                const checkedRadio = row.querySelector('.attendance-radio-btn:checked');
                if (checkedRadio) {
                    const val = checkedRadio.value;
                    if (val === 'present' || val === '1') presentCount++;
                    else if (val === 'late' || val === '2') lateCount++;
                    else if (val === 'absent' || val === '3') absentCount++;
                    else if (val === 'excused' || val === '4') excusedCount++;
                }
            });

            const total = rows.length;
            const rate = total > 0 ? Math.round(((presentCount + (lateCount * 0.5)) / total) * 100) : 0;
            const presentPct = total > 0 ? Math.round((presentCount / total) * 100) : 0;
            const absentPct = total > 0 ? Math.round((absentCount / total) * 100) : 0;

            // Update top KPI cards
            const kpiPres = document.getElementById('kpiPresent');
            if (kpiPres) kpiPres.textContent = `${presentPct}%`;
            const kpiPresCnt = document.getElementById('kpiPresentCount');
            if (kpiPresCnt) kpiPresCnt.textContent = `${presentCount} / ${total} students present`;

            const kpiAbs = document.getElementById('kpiAbsent');
            if (kpiAbs) kpiAbs.textContent = absentCount;
            const kpiAbsCnt = document.getElementById('kpiAbsentCount');
            if (kpiAbsCnt) kpiAbsCnt.textContent = `${absentPct}% of total enrollment`;

            const kpiLt = document.getElementById('kpiLate');
            if (kpiLt) kpiLt.textContent = lateCount;

            const kpiExc = document.getElementById('kpiExcused');
            if (kpiExc) kpiExc.textContent = excusedCount;

            // Update session summary badges
            const bPres = document.getElementById('countBadgePresent');
            if (bPres) bPres.textContent = `${presentCount} Present`;
            const bLate = document.getElementById('countBadgeLate');
            if (bLate) bLate.textContent = `${lateCount} Late`;
            const bAbs = document.getElementById('countBadgeAbsent');
            if (bAbs) bAbs.textContent = `${absentCount} Absent`;
            const bExc = document.getElementById('countBadgeExcused');
            if (bExc) bExc.textContent = `${excusedCount} Excused`;

            const sRate = document.getElementById('sessionRateText');
            if (sRate) sRate.innerHTML = `Attendance Rate: <strong>${rate}%</strong>`;

            // Update bar segments
            const barP = document.getElementById('barPresent');
            if (barP) barP.style.width = total > 0 ? `${(presentCount / total) * 100}%` : '0%';
            const barL = document.getElementById('barLate');
            if (barL) barL.style.width = total > 0 ? `${(lateCount / total) * 100}%` : '0%';
            const barA = document.getElementById('barAbsent');
            if (barA) barA.style.width = total > 0 ? `${(absentCount / total) * 100}%` : '0%';
            const barE = document.getElementById('barExcused');
            if (barE) barE.style.width = total > 0 ? `${(excusedCount / total) * 100}%` : '0%';
        }

        function markAllPresent() {
            const rows = document.querySelectorAll('.student-row');
            rows.forEach(row => {
                const presentRadio = row.querySelector('.attendance-radio-btn[value="present"], .attendance-radio-btn[value="1"]');
                if (presentRadio) {
                    presentRadio.checked = true;
                }
            });
            updateAttendanceMetrics();
        }

        function resetAttendanceSheet() {
            const form = document.getElementById('attendanceSheetForm');
            if (form) {
                form.reset();
                updateAttendanceMetrics();
            }
        }

        async function saveAttendanceSheet() {
            const form = document.getElementById('attendanceSheetForm');
            if (!form) return;

            const saveBtn = document.getElementById('saveAttendanceBtn');
            const originalBtnHtml = saveBtn ? saveBtn.innerHTML : '';
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Saving...';
            }

            const formData = new FormData(form);
            formData.append('ajax', '1');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    const statusBadge = document.getElementById('savedStatusBadge');
                    if (statusBadge) {
                        statusBadge.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fw-semibold d-inline-flex align-items-center gap-1 shadow-sm';
                        statusBadge.innerHTML = `<i class="bi bi-check-circle-fill"></i> Saved (${result.count} logged)`;
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Attendance Saved!',
                            text: result.message || 'Attendance saved successfully.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        const alertEl = document.getElementById('attendanceSuccessAlert');
                        const alertMsg = document.getElementById('attendanceAlertMessage');
                        if (alertEl && alertMsg) {
                            alertMsg.textContent = result.message || 'Attendance saved successfully!';
                            alertEl.classList.remove('d-none');
                            alertEl.classList.add('show');
                        }
                    }
                } else {
                    throw new Error(result.message || 'Failed to save attendance');
                }
            } catch (err) {
                console.error('Save attendance error:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Notice',
                        text: 'Could not complete AJAX save: ' + err.message + '. Submitting via standard form...',
                        confirmButtonText: 'Submit'
                    }).then(() => {
                        form.submit();
                    });
                } else {
                    form.submit();
                }
            } finally {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalBtnHtml;
                }
            }
        }

        function filterRoster() {
            const query = (document.getElementById('searchRosterInput').value || '').toLowerCase().trim();
            const rows = document.querySelectorAll('.student-row');
            let visibleCount = 0;
            rows.forEach(row => {
                const searchData = (row.getAttribute('data-name') || '').toLowerCase();
                if (!query || searchData.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const emptyNotice = document.getElementById('noFilterResults');
            if (emptyNotice) {
                emptyNotice.classList.toggle('d-none', visibleCount > 0);
            }
        }

        function changeClassSession(classId) {
            if (classId) {
                const dateVal = document.getElementById('attendanceDateInput').value;
                window.location.href = `attendance.php?class_id=${encodeURIComponent(classId)}&date=${encodeURIComponent(dateVal)}`;
            }
        }

        function changeAttendanceDate(dateVal) {
            if (dateVal) {
                const classSelect = document.getElementById('classSelectDropdown');
                const classId = classSelect ? classSelect.value : '';
                window.location.href = `attendance.php?class_id=${encodeURIComponent(classId)}&date=${encodeURIComponent(dateVal)}`;
            }
        }

        function navigateDate(days) {
            const dateInput = document.getElementById('attendanceDateInput');
            if (!dateInput || !dateInput.value) return;
            const curDate = new Date(dateInput.value + 'T00:00:00');
            curDate.setDate(curDate.getDate() + days);
            const y = curDate.getFullYear();
            const m = String(curDate.getMonth() + 1).padStart(2, '0');
            const d = String(curDate.getDate()).padStart(2, '0');
            changeAttendanceDate(`${y}-${m}-${d}`);
        }

        function goToToday() {
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            changeAttendanceDate(`${y}-${m}-${d}`);
        }

        document.addEventListener('DOMContentLoaded', () => {
            updateAttendanceMetrics();
        });
    </script>
</body>
</html>
