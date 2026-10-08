<?php
require_once __DIR__ . '/db.php';
$base_path = file_exists('assets/css/bootstrap.css') ? 'assets/' : (file_exists('admin/assets/css/bootstrap.css') ? 'admin/assets/' : './assets/');
$admin_path = file_exists('login.php') ? './' : 'admin/';

    if($_SERVER['REQUEST_METHOD'] === "POST"){
        $full_name = trim($_POST['full_name'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        

        if (empty($full_name) || empty($username) || empty($email) || empty($password)){
            echo  "All fields are required.";
            exit();
        }elseif(strlen($password) < 8){
            echo  "Password must be at least 8 characters.";
            exit();
        }else{
            $con->query("INSERT INTO `tb_users` (`users_name`, `users_email`, `users_password`, `users_fullname`) VALUES ('$username', '$email', '$password', '$full_name')");

            header("Location: ./login.php");
            exit();
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Account - Student Management System</title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/bootstrap.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Modern Styling -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>css/style.css">
</head>
<body class="login-body py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-11 col-xl-10">
                <div class="login-card row g-0">
                    <!-- Left Banner / Branding -->
                    <div class="col-lg-5 login-brand-banner d-none d-lg-flex flex-column justify-content-between p-5">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-4">
                                <div class="brand-icon-box">
                                    <i class="bi bi-mortarboard-fill"></i>
                                </div>
                                <span class="fs-4 fw-bold text-white tracking-wide">EduPulse</span>
                            </div>
                            <h2 class="fw-bold mb-3 text-white">Create Your Account</h2>
                            <p class="text-white-50 small mb-4">
                                Join the centralized institution portal to manage student enrollments, academic courses, classroom allocations, and daily attendance.
                            </p>
                        </div>
                        
                        <div class="pt-4 border-top border-white-50 mt-4">
                            <span class="badge rounded-pill bg-white text-dark py-2 px-3 fw-semibold">
                                <i class="bi bi-shield-lock-fill text-primary me-1"></i> Data Protection & Privacy Compliant
                            </span>
                        </div>
                    </div>

                    <!-- Right Form Container -->
                    <div class="col-12 col-lg-7 p-4 p-md-5">
                        <div class="d-flex align-items-center gap-2 mb-3 d-lg-none">
                            <div class="brand-icon-box">
                                <i class="bi bi-mortarboard-fill"></i>
                            </div>
                            <span class="fs-4 fw-bold text-dark">EduPulse</span>
                        </div>

                        <div class="mb-4">
                            <h3 class="fw-bold text-dark mb-1">Get Started Free </h3>
                            <p class="text-muted small">Register your user profile to access the management portal.</p>
                        </div>

                        <!-- Success Alert Placeholder -->
                        <div id="registerSuccessAlert" class="alert alert-success alert-dismissible fade d-none mb-3" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <span>Account created successfully! Redirecting to login...</span>
                        </div>

                        <!-- Registration Form (Maps directly to tbl_user) -->
                        <form id="registerForm" onsubmit="handleRegister(event)" action="" method="POST">
                            <!-- Full Name (tbl_user.full_name) -->
                            <div class="mb-3">
                                <label for="fullName" class="form-label fw-semibold text-dark small">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted">
                                        <i class="bi bi-person"></i>
                                    </span>
                                    <input type="text" class="form-control bg-light border-start-0 ps-0" id="fullName" name="full_name" placeholder="e.g. Alex Morgan" >
                                </div>
                            </div>

                            <!-- Username (tbl_user.username) -->
                            <div class="mb-3">
                                <label for="username" class="form-label fw-semibold text-dark small">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted">
                                        <i class="bi bi-at"></i>
                                    </span>
                                    <input type="text" class="form-control bg-light border-start-0 ps-0" id="username" name="username" placeholder="e.g. alex_morgan" >
                                </div>
                            </div>

                            <!-- Email Address (tbl_user.email) -->
                            <div class="mb-3">
                                <label for="email" class="form-label fw-semibold text-dark small">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted">
                                        <i class="bi bi-envelope"></i>
                                    </span>
                                    <input type="email" class="form-control bg-light border-start-0 ps-0" id="email" name="email" placeholder="e.g. alex@edupulse.edu" >
                                </div>
                            </div>

                            <!-- Password & Confirm Password Row (tbl_user.password) -->
                            <div class=" mb-3">
                               
                                    <label for="password" class="form-label fw-semibold text-dark small">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted">
                                            <i class="bi bi-lock"></i>
                                        </span>
                                        <input type="password" class="form-control bg-light border-start-0 border-end-0 ps-0" id="password" name="password" placeholder="••••••••"onkeyup="checkPasswordMatch()">
                                        <button class="btn btn-light border border-start-0 text-muted" type="button" onclick="togglePasswordVisibility('password', 'toggleIcon1')">
                                            <i class="bi bi-eye" id="toggleIcon1"></i>
                                        </button>
                                    </div>
                            </div>

                            <!-- Password Match Indicator Note -->
                            <div id="passwordMismatchNote" class="small text-danger d-none mb-3">
                                <i class="bi bi-exclamation-circle me-1"></i> Passwords do not match.
                            </div>

                            <!-- Terms & Conditions Agreement -->
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="termsCheck" >
                                <label class="form-check-label text-muted small" for="termsCheck">
                                    I agree to the <a href="#" class="text-primary text-decoration-none">Terms of Service</a> & <a href="#" class="text-primary text-decoration-none">Privacy Policy</a>
                                </label>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" id="btnSubmit" class="btn btn-primary-custom w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                                <span>Create Account</span>
                                <i class="bi bi-arrow-right"></i>
                            </button>
                        </form>

                        <!-- Back to Login Link -->
                        <div class="mt-4 pt-3 text-center border-top">
                            <p class="text-muted small mb-0">
                                Already have an account? <a href="<?php echo $admin_path; ?>login.php" class="text-primary fw-semibold text-decoration-none">Sign In</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


</body>
</html>
