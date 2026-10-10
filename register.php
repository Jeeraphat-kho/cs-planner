<?php
/**
 * CMU Computer Science Study Planner
 * Registration Page (สมัครสมาชิก)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// หากล็อกอินอยู่แล้ว ให้ไปหน้า Dashboard ทันที
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$errorMsg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = trim($_POST['student_id'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    // ตรวจสอบความถูกต้องของข้อมูล
    if (empty($studentId) || empty($fullName) || empty($password)) {
        $errorMsg = 'กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง';
    } elseif (mb_strlen($password) < 4) {
        $errorMsg = 'รหัสผ่านต้องมีความยาวอย่างน้อย 4 ตัวอักษร';
    } elseif ($password !== $confirmPassword) {
        $errorMsg = 'รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        try {
            $pdo = Database::getConnection();

            // ตรวจสอบว่ารหัสนักศึกษานี้มีในระบบแล้วหรือไม่
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE student_id = :student_id LIMIT 1");
            $stmtCheck->execute([':student_id' => $studentId]);
            if ($stmtCheck->fetch()) {
                $errorMsg = 'รหัสนักศึกษา ' . htmlspecialchars($studentId) . ' ได้ลงทะเบียนในระบบแล้ว กรุณาเข้าสู่ระบบ';
            } else {
                // แฮชรหัสผ่านเพื่อความปลอดภัยสูงสุด
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // บันทึกผู้ใช้ใหม่
                $stmtInsert = $pdo->prepare("
                    INSERT INTO users (student_id, full_name, password) 
                    VALUES (:student_id, :full_name, :password)
                ");
                $stmtInsert->execute([
                    ':student_id' => $studentId,
                    ':full_name' => $fullName,
                    ':password' => $passwordHash
                ]);

                $newUserId = (int)$pdo->lastInsertId();

                // สร้างแผนการเรียนเริ่มต้นให้ผู้ใช้ใหม่ทันที
                $planId = getUserPlanId($pdo, $newUserId, $studentId, $fullName);

                // เข้าสู่ระบบให้อัตโนมัติทันทีหลังสมัคร
                $_SESSION['user_id'] = $newUserId;
                $_SESSION['student_id'] = $studentId;
                $_SESSION['full_name'] = $fullName;
                $_SESSION['plan_id'] = $planId;

                header('Location: index.php?welcome=1');
                exit;
            }
        } catch (Exception $e) {
            $errorMsg = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก | CMU CS Study Planner</title>

    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="CSCMU-LOGO.png">
    <link href="assets/css/style.css" rel="stylesheet">

    <style>
        body {
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Prompt', sans-serif;
            padding: 1.5rem;
        }
        .auth-card {
            background: #ffffff;
            border-radius: 1.5rem;
            box-shadow: 0 20px 40px -15px rgba(104, 44, 145, 0.15);
            border: 1px solid #e9d5ff;
            overflow: hidden;
            max-width: 480px;
            width: 100%;
        }
        .auth-header {
            background: linear-gradient(135deg, #4c1d6b 0%, #682c91 50%, #4f46e5 100%);
            color: white;
            padding: 2.25rem 2rem 2rem;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="auth-card shadow-lg">
        <!-- Header -->
        <div class="auth-header">
            <div class="brand-icon-wrapper mx-auto mb-3 bg-white p-1 shadow-sm" style="width: 72px; height: 72px; border-radius: 20px;">
                <img src="CSCMU-LOGO.png" alt="CS CMU Logo">
            </div>
            <h4 class="fw-bold mb-1 tracking-tight">สมัครสมาชิกใหม่</h4>
            <p class="mb-0 opacity-75 small">ระบบวางแผนการเรียน วศ.คอมฯ มหาวิทยาลัยเชียงใหม่</p>
        </div>

        <!-- Body -->
        <div class="p-4 p-md-5">
            <?php if ($errorMsg): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 rounded-3 small mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                    <div><?= htmlspecialchars($errorMsg) ?></div>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" autocomplete="off">
                <!-- 1. เลข นศ. -->
                <div class="mb-3">
                    <label for="student_id" class="form-label small fw-semibold text-secondary">
                        <i class="bi bi-card-heading me-1 text-primary"></i> เลขประจำตัวนักศึกษา (Student ID) *
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="bi bi-123"></i>
                        </span>
                        <input type="text" 
                               class="form-control bg-light border-start-0 py-2" 
                               id="student_id" 
                               name="student_id" 
                               placeholder="เช่น 690510999" 
                               value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>" 
                               required 
                               autofocus>
                    </div>
                </div>

                <!-- 2. ชื่อจริง -->
                <div class="mb-3">
                    <label for="full_name" class="form-label small fw-semibold text-secondary">
                        <i class="bi bi-person-fill me-1 text-primary"></i> ชื่อ - นามสกุลจริง (Full Name) *
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="bi bi-person"></i>
                        </span>
                        <input type="text" 
                               class="form-control bg-light border-start-0 py-2" 
                               id="full_name" 
                               name="full_name" 
                               placeholder="เช่น กานต์ธีรา พัฒนกิจ" 
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" 
                               required>
                    </div>
                </div>

                <!-- 3. รหัสเข้าสู่ระบบ -->
                <div class="mb-3">
                    <label for="password" class="form-label small fw-semibold text-secondary">
                        <i class="bi bi-key me-1 text-primary"></i> รหัสเข้าสู่ระบบ (Password) *
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control bg-light border-start-0 py-2" 
                               id="password" 
                               name="password" 
                               placeholder="ตั้งรหัสผ่านของคุณ (อย่างน้อย 4 ตัวอักษร)" 
                               required>
                    </div>
                </div>

                <!-- 4. ยืนยันรหัสผ่าน -->
                <div class="mb-4">
                    <label for="confirm_password" class="form-label small fw-semibold text-secondary">
                        <i class="bi bi-shield-check me-1 text-primary"></i> ยืนยันรหัสเข้าสู่ระบบ *
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="bi bi-lock-fill"></i>
                        </span>
                        <input type="password" 
                               class="form-control bg-light border-start-0 py-2" 
                               id="confirm_password" 
                               name="confirm_password" 
                               placeholder="กรอกรหัสผ่านอีกครั้ง" 
                               required>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-semibold shadow-sm" style="background: linear-gradient(135deg, #682c91, #6366f1); border:none;">
                        <i class="bi bi-check-circle me-1"></i> สมัครสมาชิกและเริ่มวางแผน
                    </button>
                </div>
            </form>

            <!-- Login Link -->
            <div class="text-center">
                <span class="small text-muted">มีบัญชีผู้ใช้แล้ว? </span>
                <a href="login.php" class="small fw-semibold text-primary text-decoration-none">
                    เข้าสู่ระบบที่นี่
                </a>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="bg-light py-2 text-center border-top">
            <small class="text-muted" style="font-size: 0.75rem;">
                ภาควิชาวิทยาการคอมพิวเตอร์ คณะวิทยาศาสตร์ มหาวิทยาลัยเชียงใหม่
            </small>
        </div>
    </div>

</body>
</html>
