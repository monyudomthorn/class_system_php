<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduPulse - Student Management System</title>
    <!-- Bootstrap 5 CSS (Local) -->
    <link rel="stylesheet" href="assets/css/bootstrap.css">
    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Style -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Sweet Alert -->
    <script src="sweetalert2.min.js"></script>
    <link rel="stylesheet" href="sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <main class="container-fluid p-0 min-vh-100">
        <div class="row g-0 min-vh-100">
            <!-- Sidebar Navigation Column (col-2 on large screens, col-md-3 on medium) -->
            <div class="col-12 col-md-3 col-lg-2 sidebar">
                <!-- Brand Header -->
                <a href="index.php" class="sidebar-brand">
                    <div class="brand-icon-box">
                        <i class="bi bi-mortarboard-fill"></i>
                    </div>
                    <span>Bay Ben</span>
                </a>

                <!-- User Quick Info -->
                <div class="sidebar-user">
                    <div class="student-avatar bg-primary text-white" style="width: 38px; height: 38px; border-radius: 50%;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="text-white fw-bold small text-truncate"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?></div>
                        <div class="d-flex align-items-center gap-1" style="font-size: 0.72rem; color: #10b981;">
                            <span class="d-inline-block rounded-circle bg-success" style="width: 6px; height: 6px;"></span>
                            <span>Super Admin</span>
                        </div>
                    </div>
                </div>

                <!-- Nav Menu -->
                <div class="sidebar-nav-category">Main Menu</div>
                <nav class="nav flex-column mb-3">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </nav>

                <div class="sidebar-nav-category">Academic</div>
                <nav class="nav flex-column mb-3">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'classes.php') ? 'active' : ''; ?>" href="classes.php">
                        <i class="bi bi-door-open-fill"></i>
                        <span>Classes & Batches</span>
                    </a>
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'attendance.php') ? 'active' : ''; ?>" href="attendance.php">
                        <i class="bi bi-calendar-check-fill"></i>
                        <span>Attendance</span>
                    </a>
                    <!-- <a class="nav-link" href="#exams">
                        <i class="bi bi-file-earmark-bar-graph-fill"></i>
                        <span>Grades & Exams</span>
                    </a> -->
                </nav>

                <!-- <div class="sidebar-nav-category">Management</div>
                <nav class="nav flex-column mb-4">
                    <a class="nav-link" href="#fees">
                        <i class="bi bi-credit-card-2-front-fill"></i>
                        <span>Fees & Invoices</span>
                    </a>
                    <a class="nav-link" href="#reports">
                        <i class="bi bi-pie-chart-fill"></i>
                        <span>Reports</span>
                    </a>
                    <a class="nav-link" href="#settings">
                        <i class="bi bi-gear-fill"></i>
                        <span>Settings</span>
                    </a>
                </nav> -->

                <!-- Sidebar Footer / Logout -->
                <div class="sidebar-footer">
                    <a href="logout.php" class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-2 py-2" style="border-color: #334155; color: #f87171;">
                        <i class="bi bi-box-arrow-left"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
