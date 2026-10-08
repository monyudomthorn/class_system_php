<?php 
session_start();
require_once __DIR__ . '/db.php';
$class_id =  filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
if (file_exists('./Sidebar.php')) {
    include ('./Sidebar.php');
} else {
    include (__DIR__ . '/Sidebar.php');
}
$user_id = $_SESSION['id'] ?? null;

if (!$user_id) {
    die('User is not logged in.');
}
?>
<?php 
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])){
        $course    = $_POST['course'];
        $status    = $_POST['status'];
        $building  = $_POST['building'];
        $floor     = $_POST['floor'];
        $room      = $_POST['room'];
        $term      = $_POST['term'];
        $time      = $_POST['time'];
        $user_id   = $_SESSION['id'];

        $sql = "INSERT INTO `tb_class`(`class_course`, `class_status`, `class_building`, `class_floor`, `class_room`, `class_term`, `class_time`, `class_create`) 
                VALUES ('$course','$status','$building','$floor','$room','$term','$time','$user_id')";
        if($con->query($sql)){
            echo 
            '<script> 
                Swal.fire({
                    title: "Add Class Success",
                    text: "Click OK to continue!!!",
                    icon: "success"
                });
            </script>';
        }   
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
<!-- Data Analyst -->
<?php 
    $sql = "SELECT COUNT(*) AS total FROM `tb_class` WHERE `class_create`= $user_id";
    $res = $con-> query($sql);
    $row = mysqli_fetch_assoc($res)

?>
    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Total Classes</span>
                    <div class="stat-icon primary">
                        <i class="bi bi-door-open-fill"></i>
                    </div>
                </div>
                <div class="stat-number mb-2" id="totalClassesCount"><?= $row['total'] ?></div>
                <div class="stat-trend up">
                    <i class="bi bi-arrow-up-short"></i>
                    <span>Card view directory</span>
                </div>
            </div>
        </div>
<?php 
    $sql = "SELECT COUNT(*) AS total_active FROM `tb_class` WHERE `class_status`= 'Active'";
    $res = $con-> query($sql);
    $row = mysqli_fetch_assoc($res)

?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Active Sessions</span>
                    <div class="stat-icon success">
                        <i class="bi bi-broadcast"></i>
                    </div>
                </div>
                <div class="stat-number mb-2"><?= $row['total_active'] ?></div>
                <div class="stat-trend up">
                    <span>In session now</span>
                </div>
            </div>
        </div>
<?php 
    $sql = "SELECT COUNT(class_room) AS total_room FROM `tb_class` WHERE `class_create`= $user_id";
    $res = $con-> query($sql);
    $row = mysqli_fetch_assoc($res)

?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Allocated Rooms</span>
                    <div class="stat-icon info">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
                <div class="stat-number mb-2"><?= $row['total_room'] ?></div>
                <div class="text-muted small">
                    <span>Across 4 campus buildings</span>
                </div>
            </div>
        </div>
<?php 
    $sql = "SELECT COUNT(*) AS total_upcoming FROM `tb_class` WHERE `class_status`= 'Upcoming' and `class_create` = $user_id";
    $res = $con-> query($sql);
    $row = mysqli_fetch_assoc($res)

?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-semibold">Upcoming Batches</span>
                    <div class="stat-icon warning">
                        <i class="bi bi-calendar-event-fill"></i>
                    </div>
                </div>
                <div class="stat-number mb-2"><?= $row['total_upcoming'] ?></div>
                <div class="text-muted small">
                    <span>Starting next month</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Alerts -->
    <div id="classSuccessAlert" class="alert alert-success alert-dismissible fade d-none mb-4" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <span id="alertMessage">Operation completed successfully!</span>
        </div>
        <button type="button" class="btn-close" onclick="document.getElementById('classSuccessAlert').classList.add('d-none');"></button>
    </div>

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div class="text-muted small">
            Showing class details in <strong>Card View</strong> (No Table)
        </div>
    </div>


    <!-- CLASS DETAIL CARDS GRID (NO TABLE) -->
    <div class="row g-4" id="classCardsContainer">
        <!-- Class Card 1: Web Development -->

        <?php 
            $sql = "SELECT `class_id`, `class_course`, `class_status`, `class_building`, `class_floor`, `class_room`, `class_term`, `class_time`, `class_create` 
            FROM `tb_class` WHERE `class_create` = $user_id";
            $res = $con->query($sql);
            while($row = mysqli_fetch_assoc($res)){
  
        ?>
         
        <div class="col-12 col-md-6 col-xl-4 class-card-wrapper" data-status="active" data-search="web development lesson 2 building b it center room 204 lab fall term">
            <div class="class-detail-card">
                <div>
                    <!-- Header -->
                    <div class="class-card-header">
                        <div class="d-flex align-items-center gap-3">
                            <div class="class-course-badge web">
                                <i class="bi bi-code-slash"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-0"><?= $row['class_course'] ?></h6>
                            </div>
                        </div>
                        <span class="badge-status active"><?= $row['class_status'] ?></span>
                    </div>
                    <!-- Room & Building Details -->
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <div class="class-detail-item">
                                <i class="bi bi-building text-primary"></i>
                                <div>
                                    <span class="text-muted small">Building:</span>
                                    <strong class="text-dark"><?= $row['class_building'] ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="class-detail-item">
                                <i class="bi bi-layers"></i>
                                <div>
                                    <span class="text-muted small">Floor:</span>
                                    <strong class="text-dark"><?= $row['class_floor'] ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="class-detail-item">
                                <i class="bi bi-door-closed"></i>
                                <div>
                                    <span class="text-muted small">Room:</span>
                                    <strong class="text-dark"><?= $row['class_room'] ?></strong>
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
                                    <strong class="text-dark"><?= $row['class_term'] ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="class-detail-item">
                                <i class="bi bi-clock text-warning"></i>
                                <div>
                                    <span class="text-muted small">Time:</span>
                                    <strong class="text-dark"><?= $row['class_time'] ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enrolled Students & Attendance Metrics -->
                    <div class="d-flex align-items-center justify-content-between p-2 rounded bg-light border mb-2">
                    <?php 
                            $cid = (int)$row['class_id'];
                            $sqlCount = "SELECT COUNT(*) AS total_students FROM tb_students WHERE student_class = $cid";
                            $resCount = $con->query($sqlCount);
                            $rowCount = mysqli_fetch_assoc($resCount);

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
                            <span id="enrolled-<?= $row['class_id'] ?>"><?= $rowCount['total_students'] ?? 0 ?> Students</span>
                        </span>
                        <span class="class-meta-pill attendance">
                            <i class="bi bi-pie-chart-fill"></i>
                            <span id="rate-<?= $row['class_id'] ?>"><?= $rateText ?></span>
                        </span>
                    </div>
                </div>
                <!-- Footer Actions -->
                <div class="pt-3 border-top mt-2">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-light text-muted border">ID: CLS-00<?= $row['class_id'] ?></span>
                        <div class="d-flex gap-2">
                            <button class="action-btn" title="Edit Class"><i class="bi bi-pencil"></i></button>
                            <button class="action-btn text-danger" title="Delete Class" onclick="this.closest('.class-card-wrapper').remove();"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a  href="./student.php?class_id=<?= $row['class_id']?>" class="btn-card-action btn-outline-custom w-50 justify-content-center text-decoration-none" onclick="openAddStudentModal('CLS-001', 'Web Development')">
                            <i class="bi bi-person-plus-fill"></i> Add Student
                        </a>
                        <a href="attendance.php?class_id=<?= $row['class_id']?>" class="btn-card-action btn-primary-tint w-50 justify-content-center text-decoration-none">
                            <i class="bi bi-calendar-check-fill"></i> Attendance
                        </a>
                        <a href="./viewstudent.php?class_id=<?= $row['class_id']?>" class="btn-card-action btn-primary-tint w-50 justify-content-center text-decoration-none">
                            <i class="bi bi-eye "></i> View Students
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php 
            }
        ?>
    </div>

            <!-- ADD NEW CLASS MODAL (MATCHING SCREENSHOT) -->
            <div class="modal fade class-modal" id="addClassModal" tabindex="-1" aria-labelledby="addClassModalLabel" aria-hidden="true" >
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header d-flex align-items-center justify-content-between">
                            <h5 class="modal-title mb-0" id="addClassModalLabel">Add New Class</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="addClassForm" action="" method="POST" >
                            <div class="modal-body p-4">
                                <!-- Row 1: Course, Lessons, Status -->
                                <div class="row g-3 mb-3">
                                    <div class="col-12 col-md-4">
                                        <label class="form-label form-label-custom">Course</label>
                                        <select class="form-select form-select-custom" id="modalCourse" name="course">
                                            <option value="" selected disabled>Select Course</option>
                                            <option value="Web Development">Web Development</option>
                                            <option value="Mobile App Development">Mobile App Development</option>
                                            <option value="Data Science & AI">Data Science & AI</option>
                                            <option value="Database Administration">Database Administration</option>
                                            <option value="Network Security">Network Security</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label form-label-custom">Status</label>
                                        <select class="form-select form-select-custom" id="modalStatus" name="status">
                                            <option value="" selected disabled>Select Status</option>
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
                                        <select class="form-select form-select-custom" id="modalBuilding" name="building">
                                            <option value="" selected disabled>Select Building</option>
                                            <option value="Building A (Engineering)">Building A (Engineering)</option>
                                            <option value="Building B (IT Center)">Building B (IT Center)</option>
                                            <option value="Building C (Main Hall)">Building C (Main Hall)</option>
                                            <option value="Building D (Science Wing)">Building D (Science Wing)</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4">
                                        <label class="form-label form-label-custom">Floor</label>
                                        <select class="form-select form-select-custom" id="modalFloor" name="floor">
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
                                        <select class="form-select form-select-custom" id="modalRoom" name="room">
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
                                        <select class="form-select form-select-custom" id="modalTerm" name="term">
                                            <option value="" selected disabled>Select Term</option>
                                            <option value="Fall Term 2026">Fall Term 2026</option>
                                            <option value="Spring Term 2026">Spring Term 2026</option>
                                            <option value="Summer Term 2026">Summer Term 2026</option>
                                            <option value="Short Intensive Batch">Short Intensive Batch</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label form-label-custom">Time</label>
                                        <select class="form-select form-select-custom" id="modalTime" name="time">
                                            <option value="" selected disabled>Select Time</option>
                                            <option value="08:00 AM - 09:30 AM">08:00 AM - 09:30 AM</option>
                                            <option value="10:00 AM - 11:30 AM">10:00 AM - 11:30 AM</option>
                                            <option value="01:00 PM - 02:30 PM">01:00 PM - 02:30 PM</option>
                                            <option value="03:00 PM - 04:30 PM">03:00 PM - 04:30 PM</option>
                                            <option value="05:00 PM - 06:30 PM">05:00 PM - 06:30 PM</option>
                                        </select>
                                    </div>
                                </div>

                                <p class="text-muted small mb-0 mt-3">Please fill all the form before submit</p>
                            </div>

                            <div class="modal-footer border-top py-3 px-4 d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-cancel-modal" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-add-class btn-primary" name="add_class">Add Class</button>
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
</body>
</html>

