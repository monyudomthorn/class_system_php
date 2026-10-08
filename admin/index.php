<?php 
session_start();
require_once __DIR__ . '/db.php';
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
            <p class="text-muted small mb-0">Welcome back,<strong><?= $_SESSION['fullname'] ?></strong>.</p>
        </div>

        <div class="d-flex align-items-center gap-3">
            <!-- Search bar -->
            <div class="search-input-group d-none d-sm-block">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" placeholder="Search student name, ID, class...">
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
                <div class="stat-number mb-2">1,428</div>
                <div class="stat-trend up">
                    <i class="bi bi-arrow-up-short"></i>
                    <span>+8.4% from last semester</span>
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
                    <span class="text-muted small fw-semibold">Active Classes</span>
                    <div class="stat-icon warning">
                        <i class="bi bi-door-open-fill"></i>
                    </div>
                </div>
                <div class="stat-number mb-2">42</div>
                <div class="text-muted small">
                    <span>16 Sections in session</span>
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
                <div class="stat-number mb-2">96.2%</div>
                <div class="stat-trend up">
                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                    <span>1,374 Present today</span>
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
                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3">Filter</button>
                        <button class="btn btn-sm btn-light border rounded-pill px-3">Export CSV</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table custom-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Roll No</th>
                                <th>Class</th>
                                <th>Gender</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="student-avatar">JD</div>
                                        <div>
                                            <div class="fw-semibold text-dark">Jessica Davis</div>
                                            <div class="text-muted small" style="font-size: 0.78rem;">jessica.d@school.edu</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border">STD-2024-041</span></td>
                                <td>Grade 10 - Section A</td>
                                <td>Female</td>
                                <td><span class="badge-status active">Active</span></td>
                                <td class="text-end">
                                    <a href="#" class="action-btn" title="View Details"><i class="bi bi-eye"></i></a>
                                    <a href="#" class="action-btn" title="Edit Student"><i class="bi bi-pencil"></i></a>
                                    <a href="#" class="action-btn text-danger" title="Delete"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="student-avatar" style="color: #0ea5e9; background-color: #e0f2fe;">EM</div>
                                        <div>
                                            <div class="fw-semibold text-dark">Ethan Miller</div>
                                            <div class="text-muted small" style="font-size: 0.78rem;">ethan.m@school.edu</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border">STD-2024-042</span></td>
                                <td>Grade 11 - Section B</td>
                                <td>Male</td>
                                <td><span class="badge-status active">Active</span></td>
                                <td class="text-end">
                                    <a href="#" class="action-btn" title="View Details"><i class="bi bi-eye"></i></a>
                                    <a href="#" class="action-btn" title="Edit Student"><i class="bi bi-pencil"></i></a>
                                    <a href="#" class="action-btn text-danger" title="Delete"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="student-avatar" style="color: #10b981; background-color: #d1fae5;">SW</div>
                                        <div>
                                            <div class="fw-semibold text-dark">Sophia Williams</div>
                                            <div class="text-muted small" style="font-size: 0.78rem;">sophia.w@school.edu</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border">STD-2024-043</span></td>
                                <td>Grade 9 - Section C</td>
                                <td>Female</td>
                                <td><span class="badge-status pending">Pending Docs</span></td>
                                <td class="text-end">
                                    <a href="#" class="action-btn" title="View Details"><i class="bi bi-eye"></i></a>
                                    <a href="#" class="action-btn" title="Edit Student"><i class="bi bi-pencil"></i></a>
                                    <a href="#" class="action-btn text-danger" title="Delete"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="student-avatar" style="color: #f59e0b; background-color: #fef3c7;">LB</div>
                                        <div>
                                            <div class="fw-semibold text-dark">Lucas Brown</div>
                                            <div class="text-muted small" style="font-size: 0.78rem;">lucas.b@school.edu</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border">STD-2024-044</span></td>
                                <td>Grade 12 - Section A</td>
                                <td>Male</td>
                                <td><span class="badge-status active">Active</span></td>
                                <td class="text-end">
                                    <a href="#" class="action-btn" title="View Details"><i class="bi bi-eye"></i></a>
                                    <a href="#" class="action-btn" title="Edit Student"><i class="bi bi-pencil"></i></a>
                                    <a href="#" class="action-btn text-danger" title="Delete"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="student-avatar" style="color: #8b5cf6; background-color: #ede9fe;">AT</div>
                                        <div>
                                            <div class="fw-semibold text-dark">Ava Taylor</div>
                                            <div class="text-muted small" style="font-size: 0.78rem;">ava.t@school.edu</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border">STD-2024-045</span></td>
                                <td>Grade 10 - Section B</td>
                                <td>Female</td>
                                <td><span class="badge-status active">Active</span></td>
                                <td class="text-end">
                                    <a href="#" class="action-btn" title="View Details"><i class="bi bi-eye"></i></a>
                                    <a href="#" class="action-btn" title="Edit Student"><i class="bi bi-pencil"></i></a>
                                    <a href="#" class="action-btn text-danger" title="Delete"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer -->
                <div class="d-flex align-items-center justify-content-between pt-3 mt-2 border-top">
                    <span class="text-muted small">Showing 1 to 5 of 1,428 students</span>
                    <nav aria-label="Table navigation">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                            <li class="page-item active"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">Next</a></li>
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

    <!-- Add Student Modal (UI Demonstration) -->
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
                    <form onsubmit="event.preventDefault(); alert('Student registered successfully in demo mode!'); bootstrap.Modal.getInstance(document.getElementById('addStudentModal')).hide();">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">First Name</label>
                                <input type="text" class="form-control" placeholder="e.g. Liam" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Last Name</label>
                                <input type="text" class="form-control" placeholder="e.g. Johnson" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Email Address</label>
                                <input type="email" class="form-control" placeholder="student@school.edu" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Roll Number</label>
                                <input type="text" class="form-control" placeholder="STD-2024-046" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Class / Grade</label>
                                <select class="form-select" required>
                                    <option value="">Select Class</option>
                                    <option>Grade 9</option>
                                    <option>Grade 10</option>
                                    <option>Grade 11</option>
                                    <option>Grade 12</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Section</label>
                                <select class="form-select" required>
                                    <option value="">Select Section</option>
                                    <option>Section A</option>
                                    <option>Section B</option>
                                    <option>Section C</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Gender</label>
                                <select class="form-select" required>
                                    <option>Male</option>
                                    <option>Female</option>
                                    <option>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Date of Birth</label>
                                <input type="date" class="form-control" required>
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
    </div>
</main>

    <!-- Bootstrap JS Bundle -->
    <script src="assets/js/bootstrap.js"></script>
</body>
</html>
