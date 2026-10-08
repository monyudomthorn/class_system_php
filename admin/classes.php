<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$user_id = $_SESSION['id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit;
}

// -------------------------------------------------------------
// Handle Delete Class
// -------------------------------------------------------------
if (isset($_GET['delete_class_id'])) {
    $del_class_id = filter_input(INPUT_GET, 'delete_class_id', FILTER_VALIDATE_INT);
    if ($del_class_id) {
        try {
            // Find all students enrolled in this class
            $stu_ids = [];
            $s_res = mysqli_query($con, "SELECT student_id FROM tb_students WHERE student_class = $del_class_id");
            if ($s_res) {
                while ($sr = mysqli_fetch_assoc($s_res)) {
                    $stu_ids[] = (int)$sr['student_id'];
                }
            }
            if (!empty($stu_ids)) {
                $id_list = implode(',', $stu_ids);
                mysqli_query($con, "DELETE FROM tb_attendance WHERE student_id IN ($id_list)");
                mysqli_query($con, "DELETE FROM tb_students WHERE student_id IN ($id_list)");
            }
            // Delete class record
            $c_del = $con->prepare("DELETE FROM tb_class WHERE class_id = ?");
            if ($c_del) {
                $c_del->bind_param("i", $del_class_id);
                $c_del->execute();
            }
            header("Location: classes.php?deleted=1");
            exit;
        } catch (Exception $e) {
            error_log("Delete class failed: " . $e->getMessage());
        }
    }
}

// -------------------------------------------------------------
// Handle Add New Class
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['add_class'])) {
    $course    = trim($_POST['course'] ?? '');
    $status    = trim($_POST['status'] ?? 'Active');
    $building  = trim($_POST['building'] ?? '');
    $floor     = trim($_POST['floor'] ?? '');
    $room      = trim($_POST['room'] ?? '');
    $term      = trim($_POST['term'] ?? '');
    $time      = trim($_POST['time'] ?? '');

    if ($course && $room) {
        $ins_stmt = $con->prepare("INSERT INTO `tb_class`(`class_course`, `class_status`, `class_building`, `class_floor`, `class_room`, `class_term`, `class_time`, `class_create`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($ins_stmt) {
            $ins_stmt->bind_param("sssssssi", $course, $status, $building, $floor, $room, $term, $time, $user_id);
            if ($ins_stmt->execute()) {
                header("Location: classes.php?added=1");
                exit;
            }
        }
    }
}

// -------------------------------------------------------------
// Handle Update Existing Class
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_class'])) {
    $c_id      = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $course    = trim($_POST['course'] ?? '');
    $status    = trim($_POST['status'] ?? 'Active');
    $building  = trim($_POST['building'] ?? '');
    $floor     = trim($_POST['floor'] ?? '');
    $room      = trim($_POST['room'] ?? '');
    $term      = trim($_POST['term'] ?? '');
    $time      = trim($_POST['time'] ?? '');

    if ($c_id && $course && $room) {
        $up_stmt = $con->prepare("UPDATE `tb_class` 
            SET `class_course`=?, `class_status`=?, `class_building`=?, `class_floor`=?, `class_room`=?, `class_term`=?, `class_time`=? 
            WHERE `class_id`=?");
        if ($up_stmt) {
            $up_stmt->bind_param("sssssssi", $course, $status, $building, $floor, $room, $term, $time, $c_id);
            if ($up_stmt->execute()) {
                header("Location: classes.php?updated=1");
                exit;
            }
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
            <h4 class="fw-bold mb-1 text-dark">Class & Batch Management</h4>
            <p class="text-muted small mb-0">Manage course schedules, room assignments, and class cards.</p>
        </div>

        <div class="d-flex align-items-center gap-3">
            <!-- Search bar -->
            <div class="search-input-group d-none d-sm-block">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" id="searchClassInput" placeholder="Search course, room, building..." onkeyup="filterClassCards()">
            </div>

            <!-- Add New Class Button (Primary CTA) -->
            <button style="background-color: #0D1C42;" class="btn btn-primary-custom d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="bi bi-plus-lg"></i>
                <span>Add Class</span>
            </button>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <?php 
        $sql = "SELECT COUNT(*) AS total FROM `tb_class` WHERE `class_create` = $user_id";
        $res = $con->query($sql);
        $total_row = $res ? mysqli_fetch_assoc($res) : null;
        $total_classes = (int)($total_row['total'] ?? 0);
        if ($total_classes === 0) {
            $all_c = mysqli_query($con, "SELECT COUNT(*) AS total FROM tb_class");
            $total_classes = (int)(mysqli_fetch_assoc($all_c)['total'] ?? 0);
            $class_filter = "";
        } else {
            $class_filter = "WHERE `class_create` = $user_id";
        }

        $sql_act = "SELECT COUNT(*) AS total_active FROM `tb_class` WHERE `class_status` = 'Active'";
        $res_act = $con->query($sql_act);
        $active_row = $res_act ? mysqli_fetch_assoc($res_act) : null;

        $sql_room = "SELECT COUNT(DISTINCT class_room) AS total_room FROM `tb_class` $class_filter";
        $res_room = $con->query($sql_room);
        $room_row = $res_room ? mysqli_fetch_assoc($res_room) : null;

        $sql_upc = "SELECT COUNT(*) AS total_upcoming FROM `tb_class` WHERE `class_status` = 'Upcoming'";
        $res_upc = $con->query($sql_upc);
        $upc_row = $res_upc ? mysqli_fetch_assoc($res_upc) : null;
    ?>
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Total Classes</span>
                    <div class="stat-icon primary">
                        <i class="bi bi-door-open-fill"></i>
                    </div>
                </div>
                <div class="stat-number mb-2" id="totalClassesCount"><?= $total_classes ?></div>
                <div class="stat-trend up">
                    <i class="bi bi-arrow-up-short"></i>
                    <span>Card view directory</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Active Sessions</span>
                    <div class="stat-icon success">
                        <i class="bi bi-broadcast"></i>
                    </div>
                </div>
                <div class="stat-number mb-2"><?= (int)($active_row['total_active'] ?? 0) ?></div>
                <div class="stat-trend up">
                    <span>In session now</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Allocated Rooms</span>
                    <div class="stat-icon info">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
                <div class="stat-number mb-2"><?= (int)($room_row['total_room'] ?? 0) ?></div>
                <div class="text-muted small">
                    <span>Across campus halls</span>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Upcoming Batches</span>
                    <div class="stat-icon warning">
                        <i class="bi bi-calendar-event-fill"></i>
                    </div>
                </div>
                <div class="stat-number mb-2"><?= (int)($upc_row['total_upcoming'] ?? 0) ?></div>
                <div class="text-muted small">
                    <span>Next term enrolments</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar Filters -->
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button class="btn btn-sm btn-outline-secondary active px-3" onclick="filterByStatus('all', this)">All Classes</button>
            <button class="btn btn-sm btn-outline-secondary px-3" onclick="filterByStatus('active', this)">Active</button>
            <button class="btn btn-sm btn-outline-secondary px-3" onclick="filterByStatus('upcoming', this)">Upcoming</button>
            <button class="btn btn-sm btn-outline-secondary px-3" onclick="filterByStatus('completed', this)">Completed</button>
        </div>
        <div class="text-muted small">
            Showing class details in <strong>Card View</strong>
        </div>
    </div>

    <!-- CLASS DETAIL CARDS GRID -->
    <div class="row g-4" id="classCardsContainer">
        <?php 
            $sql = "SELECT `class_id`, `class_course`, `class_status`, `class_building`, `class_floor`, `class_room`, `class_term`, `class_time`, `class_create` 
                    FROM `tb_class` $class_filter ORDER BY `class_id` ASC";
            $res = $con->query($sql);
            $has_classes = false;

            if ($res && $res->num_rows > 0) {
                while($row = mysqli_fetch_assoc($res)) {
                    $has_classes = true;
                    $cid = (int)$row['class_id'];
                    $c_course = htmlspecialchars($row['class_course'] ?? '');
                    $c_status = trim($row['class_status'] ?? 'Active');
                    $status_lower = strtolower($c_status);
                    $badge_class = ($status_lower === 'active') ? 'active' : (($status_lower === 'upcoming') ? 'pending' : 'inactive');

                    // Course Icon
                    $course_lower = strtolower($c_course);
                    $icon_class = 'bi-code-slash';
                    $course_badge_class = 'web';
                    if (strpos($course_lower, 'mobile') !== false) {
                        $icon_class = 'bi-phone';
                        $course_badge_class = 'mobile';
                    } elseif (strpos($course_lower, 'data') !== false || strpos($course_lower, 'ai') !== false) {
                        $icon_class = 'bi-cpu';
                        $course_badge_class = 'ai';
                    } elseif (strpos($course_lower, 'database') !== false) {
                        $icon_class = 'bi-database';
                        $course_badge_class = 'database';
                    } elseif (strpos($course_lower, 'security') !== false) {
                        $icon_class = 'bi-shield-check';
                        $course_badge_class = 'security';
                    }

                    $search_blob = strtolower($c_course . ' ' . ($row['class_room'] ?? '') . ' ' . ($row['class_building'] ?? '') . ' ' . ($row['class_term'] ?? '') . ' ' . ($row['class_floor'] ?? ''));
        ?>
        <div class="col-12 col-md-6 col-xl-4 class-card-wrapper" data-status="<?= htmlspecialchars($status_lower) ?>" data-search="<?= htmlspecialchars($search_blob) ?>">
            <div class="class-detail-card">
                <div>
                    <!-- Header -->
                    <div class="class-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="class-course-badge <?= $course_badge_class ?>">
                                <i class="bi <?= $icon_class ?>"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?= $c_course ?></h6>
                            </div>
                        </div>
                        <span class="badge-status <?= $badge_class ?>"><?= htmlspecialchars($c_status) ?></span>
                    </div>

                    <!-- Room & Building Details -->
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <div class="class-detail-item">
                                <i class="bi bi-building text-primary"></i>
                                <div>
                                    <span class="text-muted small">Building:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($row['class_building'] ?? '-') ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="class-detail-item">
                                <i class="bi bi-layers"></i>
                                <div>
                                    <span class="text-muted small">Floor:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($row['class_floor'] ?? '-') ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="class-detail-item">
                                <i class="bi bi-door-closed"></i>
                                <div>
                                    <span class="text-muted small">Room:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($row['class_room'] ?? '-') ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Term & Time Schedule -->
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <div class="class-detail-item">
                                <i class="bi bi-calendar-range text-success"></i>
                                <div>
                                    <span class="text-muted small">Term:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($row['class_term'] ?? '-') ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="class-detail-item">
                                <i class="bi bi-clock text-warning"></i>
                                <div>
                                    <span class="text-muted small">Time:</span>
                                    <strong class="text-dark"><?= htmlspecialchars($row['class_time'] ?? '-') ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enrolled Students & Attendance Metrics -->
                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border mb-2">
                        <?php 
                            $sqlCount = "SELECT COUNT(*) AS total_students FROM tb_students WHERE student_class = $cid";
                            $resCount = $con->query($sqlCount);
                            $rowCount = $resCount ? mysqli_fetch_assoc($resCount) : null;

                            // Calculate real attendance rate from tb_attendance
                            $sqlAtt = "SELECT COUNT(*) AS total_logs, 
                                              SUM(CASE WHEN a.attendance_status_id = 1 THEN 1 ELSE 0 END) AS present_logs 
                                       FROM tb_attendance a 
                                       JOIN tb_students s ON a.student_id = s.student_id 
                                       WHERE s.student_class = $cid";
                            $resAtt = $con->query($sqlAtt);
                            $rowAtt = $resAtt ? mysqli_fetch_assoc($resAtt) : null;
                            $attLogs = (int)($rowAtt['total_logs'] ?? 0);
                            $attPresent = (int)($rowAtt['present_logs'] ?? 0);
                            $rateText = ($attLogs > 0) ? round(($attPresent / $attLogs) * 100) . '% Att.' : 'No data';
                        ?>
                        <span class="class-meta-pill students">
                            <i class="bi bi-people-fill"></i>
                            <span id="enrolled-<?= $cid ?>"><?= (int)($rowCount['total_students'] ?? 0) ?> Students</span>
                        </span>
                        <span class="class-meta-pill attendance">
                            <i class="bi bi-pie-chart-fill"></i>
                            <span id="rate-<?= $cid ?>"><?= $rateText ?></span>
                        </span>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="pt-3 border-top mt-2">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-light text-muted border">ID: CLS-00<?= $cid ?></span>
                        <div class="d-flex gap-2">
                            <!-- Edit Class Button -->
                            <button type="button" class="action-btn" title="Edit Class" onclick='openEditClassModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <!-- Delete Class Button -->
                            <button type="button" class="action-btn text-danger" title="Delete Class" onclick="confirmDeleteClass(<?= $cid ?>, '<?= htmlspecialchars(addslashes($c_course . ' (' . ($row['class_room'] ?? '') . ')')) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="./student.php?class_id=<?= $cid ?>" class="btn-card-action btn-outline-custom w-50 justify-content-center text-decoration-none">
                            <i class="bi bi-person-plus-fill"></i> Add Student
                        </a>
                        <a href="attendance.php?class_id=<?= $cid ?>" class="btn-card-action btn-primary-tint w-50 justify-content-center text-decoration-none">
                            <i class="bi bi-calendar-check-fill"></i> Attendance
                        </a>
                        <a href="./viewstudent.php?class_id=<?= $cid ?>" class="btn-card-action btn-primary-tint w-50 justify-content-center text-decoration-none">
                            <i class="bi bi-eye"></i> View Students
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php 
                }
            }
            if (!$has_classes):
        ?>
        <div class="col-12 text-center py-5">
            <div class="p-5 bg-light rounded-4 border">
                <i class="bi bi-door-open fs-1 text-muted d-block mb-2"></i>
                <h5 class="fw-bold text-dark">No classes available</h5>
                <p class="text-muted small mb-3">Get started by creating your first class batch.</p>
                <button class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addClassModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Class
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ADD NEW CLASS MODAL -->
    <div class="modal fade class-modal" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center justify-content-between">
                    <h5 class="modal-title mb-0" id="addClassModalLabel">Add New Class</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addClassForm" action="" method="POST">
                    <div class="modal-body p-4">
                        <!-- Row 1: Course, Status -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label form-label-custom">Course</label>
                                <select class="form-select form-select-custom" id="modalCourse" name="course" required>
                                    <option value="" selected disabled>Select Course</option>
                                    <option value="Web Development">Web Development</option>
                                    <option value="Mobile App Development">Mobile App Development</option>
                                    <option value="Data Science & AI">Data Science & AI</option>
                                    <option value="Database Administration">Database Administration</option>
                                    <option value="Network Security">Network Security</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label form-label-custom">Status</label>
                                <select class="form-select form-select-custom" id="modalStatus" name="status" required>
                                    <option value="Active" selected>Active</option>
                                    <option value="Upcoming">Upcoming</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 2: Building, Floor, Room -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label form-label-custom">Building</label>
                                <select class="form-select form-select-custom" id="modalBuilding" name="building" required>
                                    <option value="" selected disabled>Select Building</option>
                                    <option value="Building A (Engineering)">Building A (Engineering)</option>
                                    <option value="Building B (IT Center)">Building B (IT Center)</option>
                                    <option value="Building C (Main Hall)">Building C (Main Hall)</option>
                                    <option value="Building D (Science Wing)">Building D (Science Wing)</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label form-label-custom">Floor</label>
                                <select class="form-select form-select-custom" id="modalFloor" name="floor" required>
                                    <option value="" selected disabled>Select Floor</option>
                                    <option value="Ground Floor">Ground Floor</option>
                                    <option value="1st Floor">1st Floor</option>
                                    <option value="2nd Floor">2nd Floor</option>
                                    <option value="3rd Floor">3rd Floor</option>
                                    <option value="4th Floor">4th Floor</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label form-label-custom">Room</label>
                                <select class="form-select form-select-custom" id="modalRoom" name="room" required>
                                    <option value="" selected disabled>Select Room</option>
                                    <option value="Room 101">Room 101</option>
                                    <option value="Room 102">Room 102</option>
                                    <option value="Room 204 (Lab)">Room 204 (Lab)</option>
                                    <option value="Room 305 (Studio)">Room 305 (Studio)</option>
                                    <option value="Room 402 (Hall)">Room 402 (Hall)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 3: Term, Time -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label form-label-custom">Term</label>
                                <select class="form-select form-select-custom" id="modalTerm" name="term" required>
                                    <option value="" selected disabled>Select Term</option>
                                    <option value="Fall Term 2026">Fall Term 2026</option>
                                    <option value="Spring Term 2026">Spring Term 2026</option>
                                    <option value="Summer Term 2026">Summer Term 2026</option>
                                    <option value="Short Intensive Batch">Short Intensive Batch</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label form-label-custom">Time</label>
                                <select class="form-select form-select-custom" id="modalTime" name="time" required>
                                    <option value="" selected disabled>Select Time</option>
                                    <option value="08:00 AM - 09:30 AM">08:00 AM - 09:30 AM</option>
                                    <option value="10:00 AM - 11:30 AM">10:00 AM - 11:30 AM</option>
                                    <option value="01:00 PM - 02:30 PM">01:00 PM - 02:30 PM</option>
                                    <option value="03:00 PM - 04:30 PM">03:00 PM - 04:30 PM</option>
                                    <option value="05:00 PM - 06:30 PM">05:00 PM - 06:30 PM</option>
                                </select>
                            </div>
                        </div>

                        <p class="text-muted small mb-0 mt-3">Please fill all fields before submitting</p>
                    </div>

                    <div class="modal-footer border-top py-3 px-4 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="add_class" style="background-color: #0D1C42; border-color: #0D1C42;">Add Class</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT CLASS MODAL -->
    <div class="modal fade class-modal" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header d-flex align-items-center justify-content-between">
                    <h5 class="modal-title mb-0" id="editClassModalLabel">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Edit Class Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editClassForm" action="" method="POST">
                    <input type="hidden" name="class_id" id="editClassId" value="">
                    <div class="modal-body p-4">
                        <!-- Row 1: Course, Status -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label form-label-custom">Course</label>
                                <select class="form-select form-select-custom" id="editCourse" name="course" required>
                                    <option value="" disabled>Select Course</option>
                                    <option value="Web Development">Web Development</option>
                                    <option value="Mobile App Development">Mobile App Development</option>
                                    <option value="Data Science & AI">Data Science & AI</option>
                                    <option value="Database Administration">Database Administration</option>
                                    <option value="Network Security">Network Security</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label form-label-custom">Status</label>
                                <select class="form-select form-select-custom" id="editStatus" name="status" required>
                                    <option value="Active">Active</option>
                                    <option value="Upcoming">Upcoming</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 2: Building, Floor, Room -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label form-label-custom">Building</label>
                                <select class="form-select form-select-custom" id="editBuilding" name="building" required>
                                    <option value="" disabled>Select Building</option>
                                    <option value="Building A (Engineering)">Building A (Engineering)</option>
                                    <option value="Building B (IT Center)">Building B (IT Center)</option>
                                    <option value="Building C (Main Hall)">Building C (Main Hall)</option>
                                    <option value="Building D (Science Wing)">Building D (Science Wing)</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label form-label-custom">Floor</label>
                                <select class="form-select form-select-custom" id="editFloor" name="floor" required>
                                    <option value="" disabled>Select Floor</option>
                                    <option value="Ground Floor">Ground Floor</option>
                                    <option value="1st Floor">1st Floor</option>
                                    <option value="2nd Floor">2nd Floor</option>
                                    <option value="3rd Floor">3rd Floor</option>
                                    <option value="4th Floor">4th Floor</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label form-label-custom">Room</label>
                                <select class="form-select form-select-custom" id="editRoom" name="room" required>
                                    <option value="" disabled>Select Room</option>
                                    <option value="Room 101">Room 101</option>
                                    <option value="Room 102">Room 102</option>
                                    <option value="Room 204 (Lab)">Room 204 (Lab)</option>
                                    <option value="Room 305 (Studio)">Room 305 (Studio)</option>
                                    <option value="Room 402 (Hall)">Room 402 (Hall)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 3: Term, Time -->
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label form-label-custom">Term</label>
                                <select class="form-select form-select-custom" id="editTerm" name="term" required>
                                    <option value="" disabled>Select Term</option>
                                    <option value="Fall Term 2026">Fall Term 2026</option>
                                    <option value="Spring Term 2026">Spring Term 2026</option>
                                    <option value="Summer Term 2026">Summer Term 2026</option>
                                    <option value="Short Intensive Batch">Short Intensive Batch</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label form-label-custom">Time</label>
                                <select class="form-select form-select-custom" id="editTime" name="time" required>
                                    <option value="" disabled>Select Time</option>
                                    <option value="08:00 AM - 09:30 AM">08:00 AM - 09:30 AM</option>
                                    <option value="10:00 AM - 11:30 AM">10:00 AM - 11:30 AM</option>
                                    <option value="01:00 PM - 02:30 PM">01:00 PM - 02:30 PM</option>
                                    <option value="03:00 PM - 04:30 PM">03:00 PM - 04:30 PM</option>
                                    <option value="05:00 PM - 06:30 PM">05:00 PM - 06:30 PM</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-top py-3 px-4 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_class" style="background-color: #0D1C42; border-color: #0D1C42;">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
        </div>
    </main>

    <!-- Bootstrap JS Bundle -->
    <script src="assets/js/bootstrap.js"></script>

    <!-- SweetAlert Feedback Handler -->
    <?php if (isset($_GET['added'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                Swal.fire({
                    title: "Class Added!",
                    text: "New class has been added successfully.",
                    icon: "success",
                    confirmButtonColor: "#0D1C42"
                });
            });
        </script>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                Swal.fire({
                    title: "Class Updated!",
                    text: "Class details have been updated successfully.",
                    icon: "success",
                    confirmButtonColor: "#0D1C42"
                });
            });
        </script>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                Swal.fire({
                    title: "Class Deleted!",
                    text: "The class and all related records have been deleted.",
                    icon: "success",
                    confirmButtonColor: "#0D1C42"
                });
            });
        </script>
    <?php endif; ?>

    <!-- Interactive Class Actions Logic -->
    <script>
        function openEditClassModal(data) {
            if (!data) return;
            document.getElementById('editClassId').value = data.class_id || '';
            document.getElementById('editCourse').value = data.class_course || '';
            document.getElementById('editStatus').value = data.class_status || 'Active';
            document.getElementById('editBuilding').value = data.class_building || '';
            document.getElementById('editFloor').value = data.class_floor || '';
            document.getElementById('editRoom').value = data.class_room || '';
            document.getElementById('editTerm').value = data.class_term || '';
            document.getElementById('editTime').value = data.class_time || '';

            const editModal = new bootstrap.Modal(document.getElementById('editClassModal'));
            editModal.show();
        }

        function confirmDeleteClass(classId, className) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Delete Class?',
                    text: `Are you sure you want to delete ${className}? This will remove the class along with any enrolled students and attendance records.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, delete class',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `classes.php?delete_class_id=${classId}`;
                    }
                });
            } else {
                if (confirm(`Are you sure you want to delete ${className}? This will remove the class along with enrolled students and attendance records.`)) {
                    window.location.href = `classes.php?delete_class_id=${classId}`;
                }
            }
        }

        function filterClassCards() {
            const query = (document.getElementById('searchClassInput').value || '').toLowerCase().trim();
            const cards = document.querySelectorAll('.class-card-wrapper');
            cards.forEach(card => {
                const searchData = (card.getAttribute('data-search') || '').toLowerCase();
                card.style.display = (!query || searchData.includes(query)) ? '' : 'none';
            });
        }

        function filterByStatus(status, btnElement) {
            document.querySelectorAll('.btn-outline-secondary').forEach(btn => btn.classList.remove('active'));
            if (btnElement) btnElement.classList.add('active');

            const cards = document.querySelectorAll('.class-card-wrapper');
            cards.forEach(card => {
                const cardStatus = (card.getAttribute('data-status') || '').toLowerCase();
                card.style.display = (status === 'all' || cardStatus === status) ? '' : 'none';
            });
        }
    </script>
</body>
</html>
