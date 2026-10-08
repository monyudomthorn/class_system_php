<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';
$user_id = $_SESSION['id'] ?? null;
$class_id = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);

if (!isset($_SESSION["id"]) || $_SESSION["id"] == '') {
    header("Location: login.php");
    exit;
}

// Handle delete action
if (isset($_GET['delete_id']) && $class_id) {
    $del_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($del_id) {
        try {
            // Delete related attendance logs first to prevent foreign key constraint failure
            $att_stmt = $con->prepare("DELETE FROM `tb_attendance` WHERE `student_id` = ?");
            if ($att_stmt) {
                $att_stmt->bind_param("i", $del_id);
                $att_stmt->execute();
            }

            // Delete the student record
            $del_stmt = $con->prepare("DELETE FROM `tb_students` WHERE `student_id` = ?");
            if ($del_stmt) {
                $del_stmt->bind_param("i", $del_id);
                $del_stmt->execute();
            }
        } catch (mysqli_sql_exception $e) {
            error_log("Failed to delete student: " . $e->getMessage());
        }
        header("Location: viewstudent.php?class_id=" . $class_id);
        exit;
    }
}

// Fetch class info
$course_name = "Class #" . ($class_id ?: 'Unknown');
if ($class_id) {
    $c_stmt = $con->prepare("SELECT `class_course`, `class_room`, `class_building` FROM `tb_class` WHERE `class_id` = ?");
    if ($c_stmt) {
        $c_stmt->bind_param("i", $class_id);
        $c_stmt->execute();
        $c_res = $c_stmt->get_result()->fetch_assoc();
        if ($c_res && !empty($c_res['class_course'])) {
            $course_name = $c_res['class_course'];
        }
    }
}

// Fetch enrolled students
$students = [];
if ($class_id) {
    $sql = "SELECT `student_id`, `student_first_name`, `student_last_name`, `student_gender`, `student_email`, `student_dob`, `student_create` 
            FROM `tb_students` WHERE `student_class` = $class_id ORDER BY `student_id` ASC";
    $res = $con->query($sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $students[] = $r;
        }
    }
}
$student_count = count($students);

if (file_exists('./Sidebar.php')) {
    include ('./Sidebar.php');
} else {
    include (__DIR__ . '/Sidebar.php');
}
?>

<!-- Custom Style -->
<link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">

<div class="col-12 col-md-9 col-lg-10 main-content">
    <!-- Top Bar Navigation -->
    <div class="top-navbar d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="classes.php" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 rounded-pill px-3 py-1 text-decoration-none shadow-sm">
                <i class="bi bi-arrow-left"></i> Classes
            </a>
            <div>
                <h4 class="fw-bold mb-0 text-dark">
                    <?= htmlspecialchars($course_name) ?>
                </h4>
                <p class="text-muted small mb-0">Enrolled student directory and records</p>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="student.php?class_id=<?= $class_id ?>" class="cool-btn-add">
                <i class="bi bi-person-plus-fill"></i> Add Student
            </a>
        </div>
    </div>

    <!-- Cool Table Card Container -->
    <div class="cool-table-card">
        <!-- Card Header Toolbar -->
        <div class="cool-table-header">
            <div class="cool-table-title">
                <div class="cool-title-icon">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark">Student List</h5>
                    <span class="cool-badge-count" id="countBadge"><?= $student_count ?> Enrolled</span>
                </div>
            </div>

            <!-- Search box -->
            <div class="cool-search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="studentSearchInput" class="cool-search-input" placeholder="Search by name, ID, email...">
            </div>
        </div>

        <!-- Table View -->
        <div class="table-responsive">
            <table class="cool-table" id="studentsTable">
                <thead>
                    <tr>
                        <th style="width: 120px;"><i class="bi bi-hash"></i> ID</th>
                        <th><i class="bi bi-person-fill"></i> Student</th>
                        <th style="width: 140px;"><i class="bi bi-gender-ambiguous"></i> Gender</th>
                        <th><i class="bi bi-envelope-fill"></i> Email</th>
                        <th style="width: 170px;"><i class="bi bi-calendar3"></i> Date of Birth</th>
                        <th style="width: 120px;" class="text-end pe-4"><i class="bi bi-gear-fill"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($student_count > 0): ?>
                        <?php foreach ($students as $row): 
                            $first = trim($row['student_first_name'] ?? '');
                            $last = trim($row['student_last_name'] ?? '');
                            $fullName = htmlspecialchars($last . ' ' . $first);
                            $initials = strtoupper(substr($last ?: ($first ?: 'S'), 0, 1) . substr($first ?: '', 0, 1));
                            $gender = strtolower(trim($row['student_gender'] ?? ''));
                            $genderClass = ($gender === 'female') ? 'female' : (($gender === 'male') ? 'male' : 'other');
                            $genderIcon = ($gender === 'female') ? 'bi-gender-female' : (($gender === 'male') ? 'bi-gender-male' : 'bi-person');
                            $genderLabel = ucfirst($gender) ?: 'Unknown';
                        ?>
                        <tr>
                            <td>
                                <span class="student-id-pill">
                                    <span class="student-id-dot"></span>
                                    B-<?= htmlspecialchars($row['student_id']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="cool-avatar"><?= $initials ?></div>
                                    <div>
                                        <div class="student-full-name"><?= $fullName ?></div>
                                        <div class="student-sub-badge">Enrolled Student</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="gender-pill <?= $genderClass ?>">
                                    <i class="bi <?= $genderIcon ?>"></i> <?= htmlspecialchars($genderLabel) ?>
                                </span>
                            </td>
                            <td>
                                <div class="cool-email">
                                    <i class="bi bi-envelope-at"></i>
                                    <span><?= htmlspecialchars($row['student_email']) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="cool-dob">
                                    <i class="bi bi-calendar-event"></i>
                                    <span><?= !empty($row['student_dob']) ? date('d-M-Y', strtotime($row['student_dob'])) : '-' ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="cool-actions pe-3">
                                    <a href="editstudent.php?id=<?= $row['student_id'] ?>" class="cool-action-btn edit" title="Edit Student">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <button type="button" class="cool-action-btn delete" onclick="confirmDelete(<?= $row['student_id'] ?>, '<?= addslashes($fullName) ?>')" title="Delete Student">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="cool-empty-state">
                                    <div class="cool-empty-icon">
                                        <i class="bi bi-person-x"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">No Students Found</h6>
                                    <p class="text-muted small mb-3">There are no students enrolled in this class yet.</p>
                                    <a href="student.php?class_id=<?= $class_id ?>" class="cool-btn-add">
                                        <i class="bi bi-person-plus-fill"></i> Add First Student
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    </div>
</main>

    <!-- Bootstrap JS Bundle -->
    <script src="assets/js/bootstrap.js"></script>

    <script>
    // Live client-side instant search
    document.getElementById('studentSearchInput')?.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#studentsTable tbody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            if (row.querySelector('.cool-empty-state')) return;
            const text = row.textContent.toLowerCase();
            if (text.includes(filter)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        const countBadge = document.getElementById('countBadge');
        if (countBadge) {
            countBadge.textContent = filter ? `${visibleCount} Found` : `<?= $student_count ?> Enrolled`;
        }
    });

    // Delete confirmation with SweetAlert
    function confirmDelete(id, name) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Delete Student?',
                text: `Are you sure you want to remove ${name}? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `viewstudent.php?class_id=<?= $class_id ?>&delete_id=${id}`;
                }
            });
        } else {
            if (confirm(`Are you sure you want to delete ${name}?`)) {
                window.location.href = `viewstudent.php?class_id=<?= $class_id ?>&delete_id=${id}`;
            }
        }
    }
    </script>
</body>
</html>

