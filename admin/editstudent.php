<?php 
session_start();
require_once __DIR__ . '/db.php';

// Session check
if (!isset($_SESSION['id']) || $_SESSION['id'] == '') {
    header("Location: login.php"); // change to your login page
    exit;
}

$user_id    = $_SESSION['id'];
$student_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$student_id) {
    die("Invalid student ID");
}

// Update Student (runs only when the form is submitted)
if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $gender     = trim($_POST['gender'] ?? '');
    $dob        = trim($_POST['dob'] ?? '');
    $status     = trim($_POST['status'] ?? '');

    $stmt = $con->prepare("UPDATE `tb_students` 
        SET `student_first_name`=?, `student_last_name`=?, `student_gender`=?, `student_email`=?, `student_dob`=?, `student_status`=? 
        WHERE `student_id`=?");
    $stmt->bind_param("ssssssi", $first_name, $last_name, $gender, $email, $dob, $status, $student_id);
    $stmt->execute();

    // Get the class so we can go back to that class's student list
    $stmt = $con->prepare("SELECT `student_class` FROM `tb_students` WHERE `student_id` = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();

    header("Location: viewstudent.php?class_id=" . $r['student_class']);
    exit;
}

// Load the student to fill the form (runs on every page load)
$stmt = $con->prepare("SELECT `student_id`, `student_first_name`, `student_last_name`, `student_gender`, `student_email`, `student_dob`, `student_class`, `student_status`, `student_create` 
                       FROM `tb_students` WHERE `student_id` = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    die("Student not found");
}

$class_id = $row['student_class'];

if (file_exists('./Sidebar.php')) {
    include ('./Sidebar.php');
} else {
    include (__DIR__ . '/Sidebar.php');
}
?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
<div class="col-12 col-md-9 col-lg-10 main-content">
    <!-- Top Bar Navigation -->
    <div class="top-navbar d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="./viewstudent.php?class_id=<?= $class_id ?>" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 rounded-pill px-3 py-1 text-decoration-none shadow-sm">
                <i class="bi bi-arrow-left"></i> Students
            </a>
            <div>
                <h4 class="fw-bold mb-0 text-dark">
                    Student & Batch Management (User ID: <?= htmlspecialchars($_SESSION['id'] ?? 'Guest') ?>)
                </h4>
                <p class="text-muted small mb-0">Update student enrollment and personal records</p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="cool-badge-count">
                <i class="bi bi-pencil-square me-1"></i> Edit Mode
            </span>
        </div>
    </div>

    <!-- Student Form Card -->
    <form id="addStudentForm" action="" method="POST">
        <div class="student-form-card">
            <!-- Card Header Toolbar -->
            <div class="student-form-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="cool-title-icon">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Edit Student</h5>
                        <span class="text-muted small">Update records for this student</span>
                    </div>
                </div>
                <div>
                    <span class="student-id-pill">
                        <span class="student-id-dot"></span>
                        Student ID: B-<?= htmlspecialchars($row['student_id']) ?>
                    </span>
                </div>
            </div>

            <!-- Form Body -->
            <div class="student-form-body p-4">
                <!-- Student Mini Profile Preview -->
                <div class="student-edit-preview-badge">
                    <div class="d-flex align-items-center gap-3">
                        <div class="student-preview-avatar">
                            <?= htmlspecialchars(strtoupper(substr($row['student_first_name'] ?: 'S', 0, 1) . substr($row['student_last_name'] ?: '', 0, 1))) ?>
                        </div>
                        <div>
                            <div class="student-preview-name">
                                <?= htmlspecialchars($row['student_first_name'] . ' ' . $row['student_last_name']) ?>
                            </div>
                            <div class="student-preview-sub">
                                <span><i class="bi bi-envelope"></i> <?= htmlspecialchars($row['student_email']) ?></span>
                                <span class="text-muted">•</span>
                                <span><i class="bi bi-door-open"></i> Class #<?= htmlspecialchars($class_id) ?></span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <span class="gender-pill <?= strtolower($row['student_gender']) === 'female' ? 'female' : 'male' ?> me-2">
                            <i class="bi <?= strtolower($row['student_gender']) === 'female' ? 'bi-gender-female' : 'bi-gender-male' ?>"></i> <?= htmlspecialchars($row['student_gender']) ?>
                        </span>
                        <span class="badge-status <?= strtolower($row['student_status']) === 'active' ? 'active' : 'inactive' ?>">
                            <?= htmlspecialchars($row['student_status']) ?>
                        </span>
                    </div>
                </div>

                <!-- Section 1: Basic Information -->
                <div class="form-section-title">
                    <i class="bi bi-person-badge"></i>
                    <span>Personal Information</span>
                </div>

                <!-- Row 1: First Name, Last Name, Email -->
                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-custom">
                            <i class="bi bi-person"></i> First Name <span class="required-star">*</span>
                        </label>
                        <div class="form-input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text"
                                   class="form-control form-control-custom"
                                   name="first_name"
                                   placeholder="Enter first name"
                                   value="<?= htmlspecialchars($row['student_first_name']) ?>"
                                   required>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-custom">
                            <i class="bi bi-person"></i> Last Name <span class="required-star">*</span>
                        </label>
                        <div class="form-input-wrap">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text"
                                   class="form-control form-control-custom"
                                   name="last_name"
                                   placeholder="Enter last name"
                                   value="<?= htmlspecialchars($row['student_last_name']) ?>"
                                   required>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-custom">
                            <i class="bi bi-envelope"></i> Email Address <span class="required-star">*</span>
                        </label>
                        <div class="form-input-wrap">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email"
                                   class="form-control form-control-custom"
                                   name="email"
                                   placeholder="Enter email address"
                                   value="<?= htmlspecialchars($row['student_email']) ?>"
                                   required>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Demographic & Enrollment Status -->
                <div class="form-section-title">
                    <i class="bi bi-card-checklist"></i>
                    <span>Demographic & Status</span>
                </div>

                <!-- Row 2: Gender, Date of Birth, Status -->
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-custom">
                            <i class="bi bi-gender-ambiguous"></i> Gender <span class="required-star">*</span>
                        </label>
                        <div class="form-input-wrap">
                            <i class="bi bi-gender-ambiguous input-icon"></i>
                            <select class="form-select form-select-custom" name="gender" required>
                                <option value="" disabled>Select Gender</option>
                                <option value="Male"   <?= $row['student_gender'] == 'Male'   ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $row['student_gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-custom">
                            <i class="bi bi-calendar-event"></i> Date of Birth <span class="required-star">*</span>
                        </label>
                        <div class="form-input-wrap">
                            <i class="bi bi-calendar-event input-icon"></i>
                            <input type="date"
                                   class="form-control form-control-custom"
                                   name="dob"
                                   value="<?= $row['student_dob'] ?>"
                                   required>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-custom">
                            <i class="bi bi-check2-circle"></i> Status <span class="required-star">*</span>
                        </label>
                        <div class="form-input-wrap">
                            <i class="bi bi-check2-circle input-icon"></i>
                            <select class="form-select form-select-custom" name="status" required>
                                <option value="" disabled>Select Status</option>
                                <option value="Active"   <?= $row['student_status'] == 'Active'   ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= $row['student_status'] == 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 text-muted small mt-3">
                    <i class="bi bi-info-circle text-primary"></i>
                    <span>Please fill all the form before submit</span>
                </div>
            </div>

            <!-- Footer -->
            <div class="student-form-footer border-top py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span class="text-muted small">All required fields marked with <span class="required-star">*</span></span>
                <div class="btn-group-actions d-flex gap-2">
                    <a href="./viewstudent.php?class_id=<?= $class_id ?>"
                       class="btn btn-cancel-modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-add-class">
                        <i class="bi bi-check2-circle"></i> Update
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
    </div>
</main>

    <!-- Bootstrap JS Bundle -->
    <script src="assets/js/bootstrap.js"></script>
</body>
</html>