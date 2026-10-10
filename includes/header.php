<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth.php';
$loggedIn = isLoggedIn();
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="เว็บไซต์วางแผนการเรียน 4 ปี สาขาวิทยาการคอมพิวเตอร์ มหาวิทยาลัยเชียงใหม่ (CMU Computer Science Study Planner)">
    <title>CMU Computer Science Study Planner | วางแผนการเรียน วศ.คอมฯ มช.</title>

    <!-- Google Font: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="CSCMU-LOGO.png">

    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark planner-navbar py-3">
        <div class="container-xl">
            <a class="navbar-brand d-flex align-items-center gap-3" href="index.php">
                <div class="brand-icon-wrapper shadow-sm bg-white p-1">
                    <img src="CSCMU-LOGO.png" alt="CS CMU Logo">
                </div>
                <div>
                    <div class="fw-bold fs-5 tracking-tight lh-sm">CMU CS Study Planner</div>
                    <small class="opacity-75 d-block" style="font-size: 0.78rem;">
                        สาขาวิทยาการคอมพิวเตอร์ มหาวิทยาลัยเชียงใหม่ (TQF)
                    </small>
                </div>
            </a>

            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <div class="ms-auto d-flex align-items-center gap-2 flex-wrap mt-3 mt-lg-0">
                    <!-- Nav Link: แผนการเรียน -->
                    <a href="index.php" class="btn btn-sm <?= basename($_SERVER['SCRIPT_NAME']) === 'index.php' ? 'btn-light text-dark fw-bold' : 'btn-light bg-white bg-opacity-25 text-white border-white border-opacity-25' ?> rounded-pill px-3">
                        <i class="bi bi-calendar3 me-1"></i> แผนการเรียน
                    </a>

                    <!-- Nav Link: เพิ่มรายวิชาใหม่เพื่อแบ่งปัน -->
                    <a href="create-course.php" class="btn btn-sm <?= basename($_SERVER['SCRIPT_NAME']) === 'create-course.php' ? 'btn-light text-dark fw-bold' : 'btn-light bg-white bg-opacity-25 text-white border-white border-opacity-25' ?> rounded-pill px-3" title="เพิ่มรายวิชาใหม่เข้าสู่ฐานข้อมูลส่วนกลางเพื่อแบ่งปันเพื่อนๆ">
                        <i class="bi bi-plus-circle me-1"></i> เพิ่มรายวิชาใหม่
                    </a>

                    <!-- Nav Link: ค้นหาวิชาจาก REG -->
                    <a href="reg-search.php" class="btn btn-sm <?= basename($_SERVER['SCRIPT_NAME']) === 'reg-search.php' ? 'btn-light text-dark fw-bold' : 'btn-light bg-white bg-opacity-25 text-white border-white border-opacity-25' ?> rounded-pill px-3" title="ค้นหากระบวนวิชาที่เปิดสอนจากระบบสำนักทะเบียน มช. (REG CMU)">
                        <i class="bi bi-search me-1"></i> ค้นหาวิชาจาก REG
                    </a>

                    <?php if (isAdmin()): ?>
                        <!-- Nav Link: แผงควบคุมผู้ดูแลระบบ (เฉพาะรหัส 690510692) -->
                        <a href="admin.php" class="btn btn-sm <?= basename($_SERVER['SCRIPT_NAME']) === 'admin.php' ? 'btn-danger text-white fw-bold shadow' : 'btn-warning text-dark fw-bold shadow-sm' ?> rounded-pill px-3" title="แผงควบคุมผู้ดูแลระบบ (เฉพาะ 690510692)">
                            <i class="bi bi-shield-lock-fill me-1"></i> เมนูแอดมิน
                        </a>
                    <?php endif; ?>

                    <?php if (basename($_SERVER['SCRIPT_NAME']) === 'index.php'): ?>
                        <?php if ($loggedIn): ?>
                            <!-- Quick Tool Buttons on Dashboard -->
                            <button class="btn btn-sm btn-light bg-white bg-opacity-25 text-white border-white border-opacity-25 rounded-pill px-3" id="btnExpandAll" title="ขยายทุกภาคการศึกษา">
                                <i class="bi bi-arrows-expand me-1"></i> ขยายทั้งหมด
                            </button>
                            <button class="btn btn-sm btn-light bg-white bg-opacity-25 text-white border-white border-opacity-25 rounded-pill px-3" id="btnCollapseAll" title="ย่อทุกภาคการศึกษา">
                                <i class="bi bi-arrows-collapse me-1"></i> ย่อทั้งหมด
                            </button>
                            <button class="btn btn-sm btn-light bg-white bg-opacity-25 text-white border-white border-opacity-25 rounded-pill px-3" id="btnLoadSample" title="โหลดแผนการเรียนตัวอย่าง">
                                <i class="bi bi-magic me-1"></i> โหลดตัวอย่าง
                            </button>
                            <button class="btn btn-sm btn-light bg-white bg-opacity-25 text-white border-white border-opacity-25 rounded-pill px-3" id="btnResetPlan" title="ล้างแผนการเรียนทั้งหมด">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> รีเซ็ตแผน
                            </button>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-light bg-white bg-opacity-25 text-white border-white border-opacity-25 rounded-pill px-3" onclick="window.print()" title="พิมพ์หรือบันทึกเป็น PDF">
                            <i class="bi bi-printer me-1"></i> พิมพ์ / PDF
                        </button>
                    <?php endif; ?>

                    <?php if ($loggedIn): ?>
                        <!-- User Profile Dropdown & Logout -->
                        <div class="dropdown ms-lg-2">
                            <button class="btn btn-sm btn-warning bg-opacity-75 text-dark fw-semibold dropdown-toggle rounded-pill px-3 shadow-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle fs-6"></i>
                                <span><?= htmlspecialchars($user['full_name']) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 mt-2">
                                <li class="px-3 py-2 border-bottom">
                                    <div class="fw-bold small text-dark"><?= htmlspecialchars($user['full_name']) ?></div>
                                    <div class="text-muted small">รหัส นศ: <?= htmlspecialchars($user['student_id']) ?></div>
                                <?php if (isAdmin()): ?>
                                    <li>
                                        <a class="dropdown-item py-2 text-primary fw-semibold small d-flex align-items-center gap-2" href="admin.php">
                                            <i class="bi bi-shield-lock"></i> แผงควบคุมระบบ (Admin)
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                <?php endif; ?>
                                <li>
                                    <a class="dropdown-item py-2 text-danger small d-flex align-items-center gap-2" href="logout.php">
                                        <i class="bi bi-box-arrow-right"></i> ออกจากระบบ (Logout)
                                    </a>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-sm btn-light rounded-pill px-3">
                            <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
                        </a>
                        <a href="register.php" class="btn btn-sm btn-warning rounded-pill px-3 fw-semibold">
                            <i class="bi bi-person-plus me-1"></i> สมัครสมาชิก
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Toast Notification Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>
