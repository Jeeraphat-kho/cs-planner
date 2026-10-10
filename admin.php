<?php
/**
 * CMU Computer Science Study Planner
 * แผงควบคุมผู้ดูแลระบบ (Admin Panel)
 * รองรับทั้งนักศึกษารหัส 690510692 และการเข้าถึงโดยตรงด้วย user : admin_cs pass : ilovecscmu
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$loginError = null;

// 1. รองรับการล็อกอินเข้าสู่ระบบ Admin โดยตรงผ่านหน้า admin.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login_submit'])) {
    $adminUser = trim($_POST['admin_username'] ?? '');
    $adminPass = trim($_POST['admin_password'] ?? '');

    if (empty($adminUser) || empty($adminPass)) {
        $loginError = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านแอดมินให้ครบถ้วน';
    } else {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT id, student_id, full_name, password FROM users WHERE student_id = :sid LIMIT 1");
            $stmt->execute([':sid' => $adminUser]);
            $u = $stmt->fetch();

            $authSuccess = false;
            // ตรวจสอบ user: admin_cs pass: ilovecscmu หรือ hash ในฐานข้อมูล
            if ($adminUser === 'admin_cs' && $adminPass === 'ilovecscmu') {
                $authSuccess = true;
                if (!$u) {
                    $hash = password_hash('ilovecscmu', PASSWORD_BCRYPT);
                    $ins = $pdo->prepare("INSERT INTO users (student_id, full_name, password) VALUES ('admin_cs', 'ผู้ดูแลระบบ (Admin CS)', :pass)");
                    $ins->execute([':pass' => $hash]);
                    $u = [
                        'id' => (int)$pdo->lastInsertId(),
                        'student_id' => 'admin_cs',
                        'full_name' => 'ผู้ดูแลระบบ (Admin CS)'
                    ];
                }
            } elseif ($u && in_array($u['student_id'], ['690510692', 'admin_cs'], true) && password_verify($adminPass, $u['password'])) {
                $authSuccess = true;
            }

            if ($authSuccess && $u) {
                $_SESSION['user_id'] = (int)$u['id'];
                $_SESSION['student_id'] = $u['student_id'];
                $_SESSION['full_name'] = $u['full_name'];
                $_SESSION['plan_id'] = getUserPlanId($pdo, (int)$u['id'], $u['student_id'], $u['full_name']);

                header('Location: admin.php');
                exit;
            } else {
                $loginError = 'ชื่อผู้ใช้หรือรหัสผ่านแอดมินไม่ถูกต้อง (อนุญาตเฉพาะ admin_cs หรือ 690510692)';
            }
        } catch (Exception $e) {
            $loginError = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage();
        }
    }
}

// 2. หากยังไม่ได้เข้าสู่ระบบในฐานะ Admin ให้แสดงหน้า Login เข้า Admin โดยตรง
if (!isAdmin()) {
    require_once __DIR__ . '/includes/header.php';
    ?>
    <main class="container-xl py-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-md-7 col-lg-5">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="p-4 p-md-5 text-center text-white position-relative" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #682c91 100%);">
                        <div class="brand-icon-wrapper mx-auto mb-3 bg-white p-2 shadow-sm" style="width: 76px; height: 76px; border-radius: 22px;">
                            <img src="CSCMU-LOGO.png" alt="CS CMU Logo" style="width: 100%; height: 100%; object-fit: contain;">
                        </div>
                        <h4 class="fw-bold mb-1">เข้าสู่ระบบแอดมิน (Admin Portal)</h4>
                        <p class="mb-0 text-white text-opacity-75 small">ระบบจัดการหลังบ้าน CMU CS Study Planner</p>
                    </div>

                    <div class="card-body p-4 p-md-5 bg-white">
                        <?php if ($loginError): ?>
                            <div class="alert alert-danger d-flex align-items-center gap-2 py-2.5 px-3 rounded-3 small mb-4" role="alert">
                                <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                                <div><?= htmlspecialchars($loginError) ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Credentials Hint -->
                        <div class="alert alert-warning border-warning-subtle py-2.5 px-3 rounded-3 small mb-4">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-key-fill text-warning fs-5 flex-shrink-0 mt-0.5"></i>
                                <div>
                                    <div class="fw-bold text-dark">เข้าถึงหน้าแอดมินโดยตรง:</div>
                                    <div class="text-secondary">User: <code>admin_cs</code> | Pass: <code>ilovecscmu</code></div>
                                    <div class="text-secondary small mt-0.5">หรือเข้าด้วยรหัสนักศึกษา: <code>690510692</code></div>
                                </div>
                            </div>
                        </div>

                        <form method="POST" action="admin.php" autocomplete="off">
                            <input type="hidden" name="admin_login_submit" value="1">
                            <div class="mb-3">
                                <label for="adminUserField" class="form-label small fw-semibold text-dark">
                                    <i class="bi bi-person text-primary me-1"></i> ชื่อผู้ใช้แอดมิน / รหัสนักศึกษา
                                </label>
                                <input type="text" class="form-control rounded-3 py-2.5" id="adminUserField" name="admin_username" value="admin_cs" required autofocus>
                            </div>

                            <div class="mb-4">
                                <label for="adminPassField" class="form-label small fw-semibold text-dark">
                                    <i class="bi bi-lock text-primary me-1"></i> รหัสผ่าน (Password)
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control rounded-start-3 py-2.5" id="adminPassField" name="admin_password" placeholder="••••••••" required>
                                    <button class="btn btn-outline-secondary rounded-end-3" type="button" onclick="togglePassVisibility()">
                                        <i class="bi bi-eye" id="passToggleIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 rounded-pill py-2.5 fw-bold shadow-sm mb-3" style="background: linear-gradient(135deg, #4338ca, #682c91); border:none;">
                                <i class="bi bi-shield-lock-fill me-1"></i> เข้าสู่ระบบแอดมิน
                            </button>

                            <button type="button" class="btn btn-outline-secondary w-100 rounded-pill py-2 small" onclick="fillAdminCredentials()">
                                <i class="bi bi-magic me-1"></i> กรอกรหัสอัตโนมัติ (Autofill admin_cs)
                            </button>
                        </form>

                        <div class="text-center mt-4 pt-3 border-top">
                            <a href="index.php" class="text-decoration-none small text-muted">
                                <i class="bi bi-arrow-left me-1"></i> กลับหน้าหลักแผนการเรียน
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    function togglePassVisibility() {
        const p = document.getElementById('adminPassField');
        const icon = document.getElementById('passToggleIcon');
        if (p.type === 'password') {
            p.type = 'text';
            icon.className = 'bi bi-eye-slash';
        } else {
            p.type = 'password';
            icon.className = 'bi bi-eye';
        }
    }

    function fillAdminCredentials() {
        document.getElementById('adminUserField').value = 'admin_cs';
        document.getElementById('adminPassField').value = 'ilovecscmu';
        document.getElementById('adminPassField').focus();
    }
    </script>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// 3. เมื่อผ่านการตรวจสอบสิทธิ์ Admin เรียบร้อยแล้ว -> แสดงหน้า Dashboard
$user = getCurrentUser();
$validCategories = [
    'หมวดวิชาศึกษาทั่วไป',
    'หมวดวิชาแกน',
    'หมวดวิชาเอกบังคับ',
    'หมวดวิชาเอกเลือก',
    'หมวดวิชาเลือกเสรี'
];

require_once __DIR__ . '/includes/header.php';
?>

<main class="container-xl py-4 flex-grow-1">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-house-door me-1"></i>หน้าหลัก</a></li>
            <li class="breadcrumb-item active text-danger fw-medium" aria-current="page">แผงควบคุมระบบ (Admin Panel)</li>
        </ol>
    </nav>

    <!-- Admin Header Banner -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #682c91 100%);">
        <div class="card-body p-4 p-md-5 text-white position-relative">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                        <span class="badge bg-danger text-white border border-danger-subtle px-3 py-1.5 rounded-pill shadow-sm">
                            <i class="bi bi-shield-lock-fill me-1"></i> ผู้ดูแลระบบสูงสุด (Super Administrator)
                        </span>
                        <span class="badge bg-warning text-dark px-3 py-1.5 rounded-pill shadow-sm">
                            <i class="bi bi-person-badge-fill me-1"></i> รหัสนักศึกษา: <?= htmlspecialchars($user['student_id']) ?>
                        </span>
                    </div>

                    <h1 class="h2 fw-bold text-white mb-2">
                        แผงควบคุมผู้ดูแลระบบ (Admin Dashboard)
                    </h1>
                    <p class="text-white text-opacity-90 mb-3 fs-6" style="max-width: 680px;">
                        ยินดีต้อนรับคุณ <strong><?= htmlspecialchars($user['full_name']) ?></strong> เข้าสู่ระบบจัดการหลังบ้าน คุณสามารถจัดการบัญชีผู้ใช้งาน, เพิ่ม/ลบ/แก้ไขข้อมูลกระบวนวิชาทั้งหมดในหลักสูตร และบริหารจัดการแคชของระบบสำนักทะเบียน มช.
                    </p>

                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <a href="index.php" class="btn btn-sm btn-outline-light rounded-pill px-3">
                            <i class="bi bi-arrow-left me-1"></i> กลับหน้าแผนการเรียน
                        </a>
                        <a href="reg-search.php" class="btn btn-sm btn-light bg-white bg-opacity-20 text-dark rounded-pill px-3 border border-white border-opacity-25">
                            <i class="bi bi-search me-1"></i> ค้นหาวิชาจาก REG
                        </a>
                    </div>
                </div>

                <div class="col-lg-4 d-none d-lg-flex justify-content-end">
                    <div class="text-center bg-white bg-opacity-10 p-4 rounded-4 border border-white border-opacity-20 backdrop-blur" style="max-width: 280px;">
                        <img src="CSCMU-LOGO.png" alt="CS CMU Logo" class="mx-auto mb-2 d-block bg-white rounded-3 p-1 shadow-sm" style="width: 64px; height: 64px; object-fit: contain;">
                        <div class="fw-bold fs-6">CS CMU Admin Center</div>
                        <small class="opacity-75 d-block mt-1">สิทธิ์ดูแลระบบเฉพาะรหัส 690510692</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4" id="statsRow">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="brand-icon-wrapper bg-primary bg-opacity-10 text-primary rounded-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-people-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-medium">ผู้ใช้งานทั้งหมด</div>
                        <h3 class="fw-bold text-dark mb-0" id="statTotalUsers"><span class="spinner-border spinner-border-sm"></span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="brand-icon-wrapper bg-primary bg-opacity-10 text-primary rounded-33" style="width: 48px; height: 48px;">
                        <i class="bi bi-journal-bookmark-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-medium">กระบวนวิชาในระบบ</div>
                        <h3 class="fw-bold text-dark mb-0" id="statTotalCourses"><span class="spinner-border spinner-border-sm"></span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="brand-icon-wrapper bg-success bg-opacity-10 text-success rounded-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-calendar2-check-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-medium">วิชาที่ถูกเลือกในแผน</div>
                        <h3 class="fw-bold text-dark mb-0" id="statTotalPlanned"><span class="spinner-border spinner-border-sm"></span></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
                <div class="d-flex align-items-center gap-3">
                    <div class="brand-icon-wrapper bg-warning bg-opacity-10 text-warning rounded-3" style="width: 48px; height: 48px;">
                        <i class="bi bi-lightning-charge-fill fs-4 text-warning"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-medium">แคชค้นหา REG (Active)</div>
                        <h3 class="fw-bold text-dark mb-0" id="statTotalCache"><span class="spinner-border spinner-border-sm"></span></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Admin Main Tabs Navigation -->
    <div class="card border-0 shadow-sm rounded-4 p-2 mb-4 bg-white">
        <ul class="nav nav-pills nav-fill gap-2" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active rounded-3 py-2.5 fw-semibold d-flex align-items-center justify-content-center gap-2" id="users-tab" data-bs-toggle="pill" data-bs-target="#tab-users" type="button" role="tab" aria-selected="true" onclick="loadUsers()">
                    <i class="bi bi-people"></i> จัดการผู้ใช้งาน (Users)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-3 py-2.5 fw-semibold d-flex align-items-center justify-content-center gap-2" id="courses-tab" data-bs-toggle="pill" data-bs-target="#tab-courses" type="button" role="tab" aria-selected="false" onclick="loadCourses()">
                    <i class="bi bi-book"></i> จัดการกระบวนวิชา (Courses)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link rounded-3 py-2.5 fw-semibold d-flex align-items-center justify-content-center gap-2" id="cache-tab" data-bs-toggle="pill" data-bs-target="#tab-cache" type="button" role="tab" aria-selected="false" onclick="loadCaches()">
                    <i class="bi bi-hdd-network"></i> แคชและระบบ (Cache & System)
                </button>
            </li>
        </ul>
    </div>

    <!-- Tab Contents -->
    <div class="tab-content" id="adminTabContent">

        <!-- =================================================================== -->
        <!-- TAB 1: จัดการผู้ใช้งาน (User Management)                           -->
        <!-- =================================================================== -->
        <div class="tab-pane fade show active" id="tab-users" role="tabpanel" aria-labelledby="users-tab">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill text-primary me-2"></i>รายชื่อนักศึกษาและผู้ใช้งานทั้งหมด</h5>
                        <p class="text-muted small mb-0">ค้นหา, เพิ่มผู้ใช้, แก้ไขชื่อ, รีเซ็ตรหัสผ่าน หรือลบบัญชีผู้ใช้งาน</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="input-group" style="max-width: 280px;">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control bg-light border-start-0" id="userSearchInput" placeholder="ค้นหารหัส นศ. หรือชื่อ..." onkeyup="filterUsers()">
                        </div>
                        <button type="button" class="btn btn-primary rounded-pill px-3 fw-semibold shadow-sm" onclick="openCreateUserModal()">
                            <i class="bi bi-person-plus-fill me-1"></i> เพิ่มผู้ใช้ใหม่
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="usersTable">
                        <thead class="table-light small text-secondary">
                            <tr>
                                <th style="width: 70px;">ID</th>
                                <th>รหัสนักศึกษา</th>
                                <th>ชื่อ - นามสกุล</th>
                                <th class="text-center">จำนวนวิชาในแผน</th>
                                <th>วันที่สมัครสมาชิก</th>
                                <th class="text-end" style="width: 220px;">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <span class="spinner-border spinner-border-sm me-2"></span> กำลังโหลดข้อมูลผู้ใช้...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- TAB 2: จัดการกระบวนวิชา (Course Management)                         -->
        <!-- =================================================================== -->
        <div class="tab-pane fade" id="tab-courses" role="tabpanel" aria-labelledby="courses-tab">
            <div class="card border-0 shadow-sm rounded-4 p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h5 class="fw-bold text-dark mb-1"><i class="bi bi-journal-text text-primary me-2"></i>คลังกระบวนวิชาทั้งหมดในระบบ</h5>
                        <p class="text-muted small mb-0">เพิ่ม, แก้ไขรายละเอียด, เปลี่ยนหมวดหมู่วิชา, Section, เวลาเรียน หรือลบรายวิชา</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <select class="form-select bg-light" id="courseCategoryFilter" style="max-width: 200px;" onchange="loadCourses()">
                            <option value="">ทุกหมวดหมู่วิชา</option>
                            <?php foreach ($validCategories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="input-group" style="max-width: 240px;">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control bg-light border-start-0" id="courseSearchInput" placeholder="รหัส หรือชื่อวิชา..." onkeyup="filterCourses()">
                        </div>
                        <button type="button" class="btn btn-success rounded-pill px-3 fw-semibold shadow-sm" onclick="openCreateCourseModal()">
                            <i class="bi bi-plus-circle me-1"></i> เพิ่มกระบวนวิชา
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="coursesTable">
                        <thead class="table-light small text-secondary">
                            <tr>
                                <th style="width: 110px;">รหัสวิชา</th>
                                <th>ชื่อกระบวนวิชา</th>
                                <th class="text-center" style="width: 80px;">หน่วยกิต</th>
                                <th>หมวดหมู่วิชา</th>
                                <th>Section / เวลาเรียน / อาจารย์</th>
                                <th class="text-center" style="width: 90px;">ถูกเลือกในแผน</th>
                                <th class="text-end" style="width: 150px;">การจัดการ</th>
                            </tr>
                        </thead>
                        <tbody id="coursesTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <span class="spinner-border spinner-border-sm me-2"></span> กำลังโหลดข้อมูลรายวิชา...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- TAB 3: จัดการแคชและระบบ (Cache & System)                           -->
        <!-- =================================================================== -->
        <div class="tab-pane fade" id="tab-cache" role="tabpanel" aria-labelledby="cache-tab">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-4 p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-database-fill text-warning me-2"></i>รายการแคชการค้นหาจากสำนักทะเบียน มช.</h5>
                                <p class="text-muted small mb-0">แคชผลลัพธ์ช่วยลดโหลดเซิร์ฟเวอร์ มช. และตอบสนองรวดเร็วใน 0 ms (TTL 24 ชม.)</p>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="clearCache('expired')">
                                    <i class="bi bi-clock-history me-1"></i> ล้างเฉพาะแคชหมดอายุ
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="clearCache('all')">
                                    <i class="bi bi-trash3 me-1"></i> ล้างแคชทั้งหมด
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle small mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>เทอม</th>
                                        <th>ประเภท</th>
                                        <th>คำค้นหา</th>
                                        <th class="text-center">จำนวนวิชา</th>
                                        <th>วันที่สร้าง</th>
                                        <th>หมดอายุ</th>
                                        <th class="text-center">สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody id="cacheTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">กำลังโหลดแคช...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-hdd-stack text-primary me-2"></i>ข้อมูลระบบเซิร์ฟเวอร์</h6>
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">PHP Version</span>
                                <strong class="text-dark"><?= phpversion() ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Database Server</span>
                                <strong class="text-dark">MySQL (MAMP / InnoDB)</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Charset / Collation</span>
                                <strong class="text-dark">utf8mb4_unicode_ci</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Superadmin ID</span>
                                <strong class="text-danger">690510692</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span class="text-muted">Current Admin</span>
                                <strong class="text-dark"><?= htmlspecialchars($user['full_name']) ?></strong>
                            </li>
                        </ul>
                    </div>

                    <div class="card border-0 shadow-sm rounded-4 p-4 bg-light">
                        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-shield-check text-success me-2"></i>สถานะความปลอดภัย</h6>
                        <p class="text-muted small mb-3">ระบบจำกัดสิทธิ์ Admin ตรวจสอบทั้ง Session ID และ Student ID ฝั่ง Backend ในทุก API Endpoint</p>
                        <div class="d-flex align-items-center gap-2 small text-success fw-medium">
                            <i class="bi bi-check-circle-fill"></i> สิทธิ์รหัส 690510692 ได้รับการยืนยันแล้ว
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</main>

<!-- =================================================================== -->
<!-- MODALS                                                              -->
<!-- =================================================================== -->

<!-- Modal: เพิ่มผู้ใช้ใหม่ -->
<div class="modal fade" id="modalCreateUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-plus text-primary me-2"></i>เพิ่มบัญชีผู้ใช้งานใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formCreateUser" onsubmit="event.preventDefault(); submitCreateUser();">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">รหัสประจำตัวนักศึกษา (Student ID) *</label>
                        <input type="text" class="form-control rounded-3" id="newStudentId" placeholder="เช่น 660510123" required maxlength="15">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ชื่อ - นามสกุลจริง *</label>
                        <input type="text" class="form-control rounded-3" id="newFullName" placeholder="เช่น นายวิทยา คอมพิวเตอร์" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">รหัสผ่านเริ่มต้น *</label>
                        <input type="password" class="form-control rounded-3" id="newPassword" placeholder="อย่างน้อย 4 ตัวอักษร" required minlength="4">
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnSubmitNewUser">บันทึกผู้ใช้</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: แก้ไขผู้ใช้ -->
<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขข้อมูลผู้ใช้งาน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formEditUser" onsubmit="event.preventDefault(); submitEditUser();">
                    <input type="hidden" id="editUserId">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">รหัสนักศึกษา</label>
                        <input type="text" class="form-control rounded-3 bg-light" id="editStudentId" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ชื่อ - นามสกุล *</label>
                        <input type="text" class="form-control rounded-3" id="editFullName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">เปลี่ยนรหัสผ่านใหม่ (เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)</label>
                        <input type="password" class="form-control rounded-3" id="editNewPassword" placeholder="รหัสผ่านใหม่...">
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnSubmitEditUser">บันทึกการแก้ไข</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: รีเซ็ตรหัสผ่าน -->
<div class="modal fade" id="modalResetPassword" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-key-fill text-warning me-2"></i>รีเซ็ตรหัสผ่านนักศึกษา</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formResetPassword" onsubmit="event.preventDefault(); submitResetPassword();">
                    <input type="hidden" id="resetUserId">
                    <p class="small text-muted mb-3">
                        คุณกำลังตั้งรหัสผ่านใหม่ให้กับ: <strong class="text-dark" id="resetUserDisplay"></strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">รหัสผ่านใหม่ *</label>
                        <input type="password" class="form-control rounded-3" id="resetNewPassInput" placeholder="ระบุรหัสผ่านใหม่..." required minlength="4">
                    </div>
                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-warning rounded-pill px-4 fw-semibold" id="btnSubmitResetPass">ยืนยันรีเซ็ตรหัส</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: เพิ่ม / แก้ไขกระบวนวิชา -->
<div class="modal fade" id="modalCourse" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark" id="modalCourseTitle"><i class="bi bi-book text-success me-2"></i>เพิ่มกระบวนวิชาใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formCourse" onsubmit="event.preventDefault(); submitCourseForm();">
                    <input type="hidden" id="courseFormId">

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">รหัสกระบวนวิชา (Course Code) *</label>
                            <input type="text" class="form-control rounded-3" id="courseFormCode" placeholder="เช่น 204111" required maxlength="20">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">ชื่อกระบวนวิชา (Course Name) *</label>
                            <input type="text" class="form-control rounded-3" id="courseFormName" placeholder="เช่น FUNDAMENTAL PROGRAMMING" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-semibold">หน่วยกิต *</label>
                            <input type="number" class="form-control rounded-3" id="courseFormCredits" min="1" max="12" value="3" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">หมวดหมู่วิชา *</label>
                            <select class="form-select rounded-3" id="courseFormCategory" required>
                                <?php foreach ($validCategories as $cat): ?>
                                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Section (ตอนเรียน)</label>
                            <input type="text" class="form-control rounded-3" id="courseFormSection" placeholder="เช่น 001 หรือ LEC 001 / LAB 001">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">วันและเวลาเรียน (Class Time)</label>
                            <input type="text" class="form-control rounded-3" id="courseFormTime" placeholder="เช่น TuF 0930-1100">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">อาจารย์ผู้สอน (Instructor)</label>
                            <input type="text" class="form-control rounded-3" id="courseFormInstructor" placeholder="เช่น ผศ.ดร. ...">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">คำอธิบายรายวิชา (Description)</label>
                        <textarea class="form-control rounded-3" id="courseFormDescription" rows="3" placeholder="รายละเอียดหรือเงื่อนไขของวิชา..."></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnSubmitCourseForm">บันทึกข้อมูลวิชา</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast Feedback -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="adminToast" class="toast align-items-center text-white bg-dark border-0 rounded-4 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2 py-3" id="adminToastMessage">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <span>ข้อความ</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
let usersData = [];
let coursesData = [];

document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadUsers();
});

function showToast(msg, isSuccess = true) {
    const el = document.getElementById('adminToast');
    const msgEl = document.getElementById('adminToastMessage');
    el.className = `toast align-items-center text-white ${isSuccess ? 'bg-success' : 'bg-danger'} border-0 rounded-4 shadow`;
    msgEl.innerHTML = `<i class="bi ${isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} fs-5"></i> <span>${msg}</span>`;
    const toast = new bootstrap.Toast(el, { delay: 4000 });
    toast.show();
}

// =========================================================================
// Stats
// =========================================================================
async function loadStats() {
    try {
        const res = await fetch('api/admin.php?action=get_stats');
        const data = await res.json();
        if (data.success && data.stats) {
            document.getElementById('statTotalUsers').textContent = Number(data.stats.total_users).toLocaleString();
            document.getElementById('statTotalCourses').textContent = Number(data.stats.total_courses).toLocaleString();
            document.getElementById('statTotalPlanned').textContent = Number(data.stats.total_planned_courses).toLocaleString();
            document.getElementById('statTotalCache').textContent = `${Number(data.stats.active_cache).toLocaleString()} / ${Number(data.stats.total_cache).toLocaleString()}`;
        }
    } catch (e) {
        console.error(e);
    }
}

// =========================================================================
// Users Management
// =========================================================================
async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');
    tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> กำลังโหลดข้อมูล...</td></tr>`;

    try {
        const res = await fetch('api/admin.php?action=get_users');
        const data = await res.json();
        if (data.success) {
            usersData = data.users || [];
            renderUsers(usersData);
        } else {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger">${data.message || 'โหลดไม่สำเร็จ'}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger">เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย</td></tr>`;
    }
}

function filterUsers() {
    const q = document.getElementById('userSearchInput').value.trim().toLowerCase();
    if (!q) {
        renderUsers(usersData);
        return;
    }
    const filtered = usersData.filter(u => 
        (u.student_id && u.student_id.toLowerCase().includes(q)) ||
        (u.full_name && u.full_name.toLowerCase().includes(q))
    );
    renderUsers(filtered);
}

function renderUsers(list) {
    const tbody = document.getElementById('usersTableBody');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">ไม่พบข้อมูลผู้ใช้งาน</td></tr>`;
        return;
    }

    tbody.innerHTML = list.map(u => {
        const isAdminUser = (u.student_id === '690510692');
        const badge = isAdminUser 
            ? `<span class="badge bg-danger rounded-pill px-2.5 py-1"><i class="bi bi-shield-fill me-1"></i>ADMIN (690510692)</span>`
            : `<span class="badge bg-light text-dark border rounded-pill px-2.5 py-1">${escapeHtml(u.student_id)}</span>`;

        const deleteBtn = isAdminUser
            ? `<button class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-1" disabled title="ไม่อนุญาตให้ลบ Admin"><i class="bi bi-lock-fill"></i></button>`
            : `<button class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1" onclick="deleteUser(${u.id}, '${escapeHtml(u.full_name)}', '${escapeHtml(u.student_id)}')"><i class="bi bi-trash"></i> ลบ</button>`;

        return `
            <tr>
                <td class="text-muted small">${u.id}</td>
                <td>${badge}</td>
                <td class="fw-semibold text-dark">${escapeHtml(u.full_name)}</td>
                <td class="text-center"><span class="badge bg-primary-subtle text-primary border rounded-pill">${u.planned_courses_count || 0} วิชา</span></td>
                <td class="small text-muted">${escapeHtml(u.created_at || '-')}</td>
                <td class="text-end">
                    <div class="d-inline-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1" onclick="openEditUserModal(${u.id}, '${escapeHtml(u.student_id)}', '${escapeHtml(u.full_name)}')">
                            <i class="bi bi-pencil"></i> แก้ไข
                        </button>
                        <button class="btn btn-sm btn-outline-warning text-dark rounded-pill px-2.5 py-1" onclick="openResetPasswordModal(${u.id}, '${escapeHtml(u.full_name)} (${escapeHtml(u.student_id)}')">
                            <i class="bi bi-key"></i> รีเซ็ตรหัส
                        </button>
                        ${deleteBtn}
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function openCreateUserModal() {
    document.getElementById('formCreateUser').reset();
    new bootstrap.Modal(document.getElementById('modalCreateUser')).show();
}

async function submitCreateUser() {
    const sid = document.getElementById('newStudentId').value.trim();
    const name = document.getElementById('newFullName').value.trim();
    const pass = document.getElementById('newPassword').value;

    const btn = document.getElementById('btnSubmitNewUser');
    btn.disabled = true;

    try {
        const res = await fetch('api/admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'create_user', student_id: sid, full_name: name, password: pass })
        });
        const d = await res.json();
        btn.disabled = false;

        if (d.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalCreateUser')).hide();
            showToast(d.message, true);
            loadUsers();
            loadStats();
        } else {
            showToast(d.message || 'เกิดข้อผิดพลาด', false);
        }
    } catch (e) {
        btn.disabled = false;
        showToast('เชื่อมต่อล้มเหลว', false);
    }
}

function openEditUserModal(id, sid, name) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editStudentId').value = sid;
    document.getElementById('editFullName').value = name;
    document.getElementById('editNewPassword').value = '';
    new bootstrap.Modal(document.getElementById('modalEditUser')).show();
}

async function submitEditUser() {
    const id = document.getElementById('editUserId').value;
    const name = document.getElementById('editFullName').value.trim();
    const pass = document.getElementById('editNewPassword').value;

    const btn = document.getElementById('btnSubmitEditUser');
    btn.disabled = true;

    try {
        const res = await fetch('api/admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'update_user', user_id: id, full_name: name, new_password: pass })
        });
        const d = await res.json();
        btn.disabled = false;

        if (d.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalEditUser')).hide();
            showToast(d.message, true);
            loadUsers();
        } else {
            showToast(d.message || 'เกิดข้อผิดพลาด', false);
        }
    } catch (e) {
        btn.disabled = false;
        showToast('เชื่อมต่อล้มเหลว', false);
    }
}

function openResetPasswordModal(id, display) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetUserDisplay').textContent = display;
    document.getElementById('resetNewPassInput').value = '';
    new bootstrap.Modal(document.getElementById('modalResetPassword')).show();
}

async function submitResetPassword() {
    const id = document.getElementById('resetUserId').value;
    const pass = document.getElementById('resetNewPassInput').value;

    const btn = document.getElementById('btnSubmitResetPass');
    btn.disabled = true;

    try {
        const res = await fetch('api/admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'reset_password', user_id: id, new_password: pass })
        });
        const d = await res.json();
        btn.disabled = false;

        if (d.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalResetPassword')).hide();
            showToast(d.message, true);
        } else {
            showToast(d.message || 'เกิดข้อผิดพลาด', false);
        }
    } catch (e) {
        btn.disabled = false;
        showToast('เชื่อมต่อล้มเหลว', false);
    }
}

async function deleteUser(id, name, sid) {
    if (!confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้ "${name}" (${sid}) ออกจากระบบ? แผนการเรียนทั้งหมดของผู้ใช้นี้จะถูกลบออกด้วย`)) {
        return;
    }

    try {
        const res = await fetch('api/admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete_user', user_id: id })
        });
        const d = await res.json();
        if (d.success) {
            showToast(d.message, true);
            loadUsers();
            loadStats();
        } else {
            showToast(d.message || 'ลบไม่สำเร็จ', false);
        }
    } catch (e) {
        showToast('เชื่อมต่อล้มเหลว', false);
    }
}

// =========================================================================
// Courses Management
// =========================================================================
async function loadCourses() {
    const tbody = document.getElementById('coursesTableBody');
    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> กำลังโหลดรายวิชา...</td></tr>`;

    const cat = document.getElementById('courseCategoryFilter').value;
    const q = document.getElementById('courseSearchInput').value.trim();

    try {
        const url = `api/admin.php?action=get_courses&category=${encodeURIComponent(cat)}&search=${encodeURIComponent(q)}`;
        const res = await fetch(url);
        const data = await res.json();
        if (data.success) {
            coursesData = data.courses || [];
            renderCourses(coursesData);
        } else {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${data.message || 'โหลดไม่สำเร็จ'}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">เกิดข้อผิดพลาดในการเชื่อมต่อ</td></tr>`;
    }
}

function filterCourses() {
    const q = document.getElementById('courseSearchInput').value.trim().toLowerCase();
    if (!q) {
        renderCourses(coursesData);
        return;
    }
    const filtered = coursesData.filter(c => 
        (c.course_code && c.course_code.toLowerCase().includes(q)) ||
        (c.course_name && c.course_name.toLowerCase().includes(q)) ||
        (c.instructor && c.instructor.toLowerCase().includes(q))
    );
    renderCourses(filtered);
}

function renderCourses(list) {
    const tbody = document.getElementById('coursesTableBody');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">ไม่พบกระบวนวิชาตามเงื่อนไข</td></tr>`;
        return;
    }

    const catBadges = {
        'หมวดวิชาศึกษาทั่วไป': 'bg-info-subtle text-info-emphasis border border-info-subtle',
        'หมวดวิชาแกน': 'bg-primary-subtle text-primary border border-primary-subtle',
        'หมวดวิชาเอกบังคับ': 'bg-purple-subtle text-primary border border-purple-subtle',
        'หมวดวิชาเอกเลือก': 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        'หมวดวิชาเลือกเสรี': 'bg-success-subtle text-success border border-success-subtle'
    };

    tbody.innerHTML = list.map(c => {
        const badgeClass = catBadges[c.category] || 'bg-light text-dark border';
        const secDisplay = c.section || '-';
        const timeDisplay = c.class_time ? `<div><i class="bi bi-clock me-1"></i>${escapeHtml(c.class_time)}</div>` : '';
        const instDisplay = c.instructor ? `<div><i class="bi bi-person me-1"></i>${escapeHtml(c.instructor)}</div>` : '';

        return `
            <tr>
                <td><span class="badge bg-primary fs-6 px-2.5 py-1 rounded-pill">${escapeHtml(c.course_code)}</span></td>
                <td>
                    <div class="fw-bold text-dark">${escapeHtml(c.course_name)}</div>
                    ${c.description ? `<small class="text-muted text-truncate d-block" style="max-width: 280px;">${escapeHtml(c.description)}</small>` : ''}
                </td>
                <td class="text-center"><span class="badge bg-light text-dark border rounded-pill">${c.credits} นก.</span></td>
                <td><span class="badge ${badgeClass} rounded-pill px-2.5 py-1 small">${escapeHtml(c.category)}</span></td>
                <td class="small text-muted">
                    <div>Sec: <strong>${escapeHtml(secDisplay)}</strong></div>
                    ${timeDisplay}
                    ${instDisplay}
                </td>
                <td class="text-center"><span class="badge bg-secondary-subtle text-dark border rounded-pill">${c.planned_count || 0} คน</span></td>
                <td class="text-end">
                    <div class="d-inline-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1" onclick="openEditCourseModal(${c.id})">
                            <i class="bi bi-pencil"></i> แก้ไข
                        </button>
                        <button class="btn btn-sm btn-outline-danger rounded-pill px-2.5 py-1" onclick="deleteCourse(${c.id}, '${escapeHtml(c.course_code)}', '${escapeHtml(c.course_name)}')">
                            <i class="bi bi-trash"></i> ลบ
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function openCreateCourseModal() {
    document.getElementById('modalCourseTitle').innerHTML = `<i class="bi bi-plus-circle text-success me-2"></i>เพิ่มกระบวนวิชาใหม่`;
    document.getElementById('courseFormId').value = '';
    document.getElementById('formCourse').reset();
    document.getElementById('courseFormCredits').value = 3;
    document.getElementById('courseFormCategory').value = 'หมวดวิชาเอกเลือก';
    new bootstrap.Modal(document.getElementById('modalCourse')).show();
}

async function openEditCourseModal(id) {
    try {
        const res = await fetch(`api/admin.php?action=get_course&id=${id}`);
        const d = await res.json();
        if (d.success && d.course) {
            const c = d.course;
            document.getElementById('modalCourseTitle').innerHTML = `<i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขกระบวนวิชา ${escapeHtml(c.course_code)}`;
            document.getElementById('courseFormId').value = c.id;
            document.getElementById('courseFormCode').value = c.course_code;
            document.getElementById('courseFormName').value = c.course_name;
            document.getElementById('courseFormCredits').value = c.credits;
            document.getElementById('courseFormCategory').value = c.category;
            document.getElementById('courseFormSection').value = c.section || '';
            document.getElementById('courseFormTime').value = c.class_time || '';
            document.getElementById('courseFormInstructor').value = c.instructor || '';
            document.getElementById('courseFormDescription').value = c.description || '';
            new bootstrap.Modal(document.getElementById('modalCourse')).show();
        } else {
            showToast('ไม่สามารถโหลดข้อมูลวิชานี้ได้', false);
        }
    } catch (e) {
        showToast('เกิดข้อผิดพลาดในการโหลดข้อมูล', false);
    }
}

async function submitCourseForm() {
    const id = document.getElementById('courseFormId').value;
    const isEdit = Boolean(id);

    const payload = {
        action: isEdit ? 'update_course' : 'create_course',
        course_id: id ? parseInt(id, 10) : null,
        course_code: document.getElementById('courseFormCode').value.trim(),
        course_name: document.getElementById('courseFormName').value.trim(),
        credits: parseInt(document.getElementById('courseFormCredits').value, 10) || 3,
        category: document.getElementById('courseFormCategory').value,
        section: document.getElementById('courseFormSection').value.trim(),
        class_time: document.getElementById('courseFormTime').value.trim(),
        instructor: document.getElementById('courseFormInstructor').value.trim(),
        description: document.getElementById('courseFormDescription').value.trim()
    };

    const btn = document.getElementById('btnSubmitCourseForm');
    btn.disabled = true;

    try {
        const res = await fetch('api/admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const d = await res.json();
        btn.disabled = false;

        if (d.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalCourse')).hide();
            showToast(d.message, true);
            loadCourses();
            loadStats();
        } else {
            showToast(d.message || 'บันทึกไม่สำเร็จ', false);
        }
    } catch (e) {
        btn.disabled = false;
        showToast('เชื่อมต่อล้มเหลว', false);
    }
}

async function deleteCourse(id, code, name) {
    if (!confirm(`คุณแน่ใจหรือไม่ว่าต้องการลบกระบวนวิชา "${code} ${name}" ออกจากระบบ? หากมีนักศึกษาเลือกวิชานี้ในแผน ข้อมูลจะถูกลบออกจากแผนด้วย`)) {
        return;
    }

    try {
        const res = await fetch('api/admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete_course', course_id: id })
        });
        const d = await res.json();
        if (d.success) {
            showToast(d.message, true);
            loadCourses();
            loadStats();
        } else {
            showToast(d.message || 'ลบไม่สำเร็จ', false);
        }
    } catch (e) {
        showToast('เชื่อมต่อล้มเหลว', false);
    }
}

// =========================================================================
// Cache Management
// =========================================================================
async function loadCaches() {
    const tbody = document.getElementById('cacheTableBody');
    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span> กำลังโหลดรายการแคช...</td></tr>`;

    try {
        const res = await fetch('api/admin.php?action=get_caches');
        const data = await res.json();
        if (data.success) {
            renderCaches(data.caches || []);
        } else {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${data.message || 'โหลดแคชไม่สำเร็จ'}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">เกิดข้อผิดพลาดในการเชื่อมต่อ</td></tr>`;
    }
}

function renderCaches(list) {
    const tbody = document.getElementById('cacheTableBody');
    if (!list || list.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">ยังไม่มีรายการแคชในระบบ</td></tr>`;
        return;
    }

    tbody.innerHTML = list.map(c => {
        const statusBadge = c.is_active == 1
            ? `<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Active</span>`
            : `<span class="badge bg-secondary-subtle text-secondary border rounded-pill">Expired</span>`;

        return `
            <tr>
                <td><strong>${escapeHtml(c.term)}</strong></td>
                <td><span class="badge bg-light text-dark border rounded-pill">${escapeHtml(c.search_type)}</span></td>
                <td><code>${escapeHtml(c.query_str)}</code></td>
                <td class="text-center">${c.courses_count} วิชา</td>
                <td class="text-muted">${escapeHtml(c.created_at)}</td>
                <td class="text-muted">${escapeHtml(c.expires_at)}</td>
                <td class="text-center">${statusBadge}</td>
            </tr>
        `;
    }).join('');
}

async function clearCache(mode = 'all') {
    const confirmMsg = mode === 'expired' 
        ? 'คุณต้องการล้างเฉพาะแคชที่หมดอายุแล้วใช่หรือไม่?' 
        : 'คุณต้องการล้างแคชสำนักทะเบียนทั้งหมดใช่หรือไม่? (การค้นหาครั้งต่อไปจะส่งไปดึงจากเซิร์ฟเวอร์ มช. ใหม่)';

    if (!confirm(confirmMsg)) return;

    try {
        const res = await fetch('api/admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'clear_cache', mode: mode })
        });
        const d = await res.json();
        if (d.success) {
            showToast(d.message, true);
            loadCaches();
            loadStats();
        } else {
            showToast(d.message || 'ล้างแคชไม่สำเร็จ', false);
        }
    } catch (e) {
        showToast('เกิดข้อผิดพลาดในการเชื่อมต่อ', false);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
