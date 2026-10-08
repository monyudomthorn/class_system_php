<?php 
session_start();
require_once __DIR__ . '/db.php';
$user_id = $_SESSION['id'] ?? null;
// $class_id =  $_GET['class_id'];
$class_id =  filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);

if(!isset($_SESSION["id"]) && $_SESSION["id"] == '') {
    echo "No session ... ";
}

if (file_exists('./Sidebar.php')) {
    include ('./Sidebar.php');
} else {
    include (__DIR__ . '/Sidebar.php');
}
?>

<!-- Add Student -->
<?php 
    if($_SERVER['REQUEST_METHOD'] === "POST"){
        if (!$class_id) {
            die("Missing class_id in URL");
        }
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $gender     = trim($_POST['gender'] ?? '');
        $dob     = trim($_POST['dob'] ?? '');
        $status     = trim($_POST['status'] ?? '');
        
        // echo $first_name, $last_name, $email, $gender, $dob, $status, $class_id;
        
        $sql= "INSERT INTO `tb_students`(`student_first_name`, `student_last_name`, `student_gender`, `student_email`, `student_dob`, `student_class`, `student_status`, `student_create`)
                 VALUES ('$first_name','$last_name','$gender','$email','$dob','$class_id','$status','$user_id')";
        $res = $con->query($sql);

        if($res === false){
            die("Insert failed: " . $con->error); 
        }
        // header("Location: ./classes.php");
        // exit();
    }
?>
<link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
<div class="col-12 col-md-9 col-lg-10 main-content">
    <!-- Top Bar Navigation -->
    <div class="top-navbar d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="./classes.php" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 rounded-pill px-3 py-1 text-decoration-none shadow-sm">
                <i class="bi bi-arrow-left"></i> Classes
            </a>
            <div>
                <h4 class="fw-bold mb-0 text-dark">
                    Student & Batch Management (User ID: <?= htmlspecialchars($_SESSION['id'] ?? 'Guest') ?>)
                </h4>
                <p class="text-muted small mb-0">Enroll a new student into the class roster</p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="cool-badge-count">
                <i class="bi bi-person-plus-fill me-1"></i> New Student Entry
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
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">Student Information</h5>
                        <span class="text-muted small">Fill in the student details to create enrollment</span>
                    </div>
                </div>
                <div>
                    <span class="student-id-pill">
                        <span class="student-id-dot"></span>
                        Registration Form
                    </span>
                </div>
            </div>

            <!-- Form Body -->
            <div class="student-form-body p-4">
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
                            <select class="form-select form-select-custom"
                                    name="gender"
                                    required>
                                <option value="" selected disabled>
                                    Select Gender
                                </option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
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
                                   required>
                        </div>
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label form-label-custom">
                            <i class="bi bi-check2-circle"></i> Status <span class="required-star">*</span>
                        </label>
                        <div class="form-input-wrap">
                            <i class="bi bi-check2-circle input-icon"></i>
                            <select class="form-select form-select-custom"
                                    name="status"
                                    required>
                                <option value="" selected disabled>
                                    Select Status
                                </option>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
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
                    <a href="./classes.php" class="btn btn-cancel-modal">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-add-class">
                        <i class="bi bi-person-plus-fill"></i> Add Student
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
