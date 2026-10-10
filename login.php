<?php
/**
 * CMU Computer Science Study Planner
 * Login Page (เข้าสู่ระบบ)
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
$successMsg = null;

if (isset($_GET['registered'])) {
    $successMsg = 'สมัครสมาชิกสำเร็จเรียบร้อยแล้ว กรุณาเข้าสู่ระบบด้วยรหัสผ่านที่คุณตั้งไว้';
} elseif (isset($_GET['logout'])) {
    $successMsg = 'ออกจากระบบเรียบร้อยแล้ว';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $studentId = trim($_POST['student_id'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($studentId) || empty($password)) {
        $errorMsg = 'กรุณากรอกรหัสนักศึกษาและรหัสผ่านให้ครบถ้วน';
    } else {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT id, student_id, full_name, password FROM users WHERE student_id = :student_id LIMIT 1");
            $stmt->execute([':student_id' => $studentId]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // ล็อกอินสำเร็จ: ตั้งค่า Session
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['full_name'] = $user['full_name'];

                // ดึงหรือสร้างแผนการเรียนของผู้ใช้นี้
                $_SESSION['plan_id'] = getUserPlanId($pdo, (int)$user['id'], $user['student_id'], $user['full_name']);

                // หากเป็น Admin ให้ไปที่หน้า admin.php โดยตรง
                if (in_array($user['student_id'], ['690510692', 'admin_cs'], true)) {
                    header('Location: admin.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                $errorMsg = 'รหัสนักศึกษาหรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
            }
        } catch (Exception $e) {
            $errorMsg = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | CMU CS Study Planner</title>

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
            max-width: 460px;
            width: 100%;
        }
        .auth-header {
            background: linear-gradient(135deg, #4c1d6b 0%, #682c91 50%, #4f46e5 100%);
            color: white;
            padding: 2.25rem 2rem 2rem;
            text-align: center;
            position: relative;
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
            <h4 class="fw-bold mb-1 tracking-tight">CMU CS Study Planner</h4>
            <p class="mb-0 opacity-75 small">เข้าสู่ระบบเพื่อวางแผนการเรียน 4 ปีของคุณ</p>
        </div>

        <!-- Body -->
        <div class="p-4 p-md-5">
            <?php if ($errorMsg): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 rounded-3 small mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0"></i>
                    <div><?= htmlspecialchars($errorMsg) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($successMsg): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 rounded-3 small mb-4" role="alert">
                    <i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>
                    <div><?= htmlspecialchars($successMsg) ?></div>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="off">
                <!-- รหัสนักศึกษา -->
                <div class="mb-3">
                    <label for="student_id" class="form-label small fw-semibold text-secondary">
                        <i class="bi bi-person-badge me-1 text-primary"></i> รหัสนักศึกษา (Student ID)
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="bi bi-card-heading"></i>
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

                <!-- รหัสผ่าน -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="password" class="form-label small fw-semibold text-secondary">
                            <i class="bi bi-key me-1 text-primary"></i> รหัสผ่าน (Password)
                        </label>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input type="password" 
                               class="form-control bg-light border-start-0 py-2" 
                               id="password" 
                               name="password" 
                               placeholder="กรอกรหัสผ่านของคุณ" 
                               required>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-lg rounded-pill fw-semibold shadow-sm" style="background: linear-gradient(135deg, #682c91, #6366f1); border:none;">
                        <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
                    </button>
                </div>
            </form>

            <!-- Quick Demo Login Helper -->
            <div class="p-3 bg-light rounded-3 text-center border mb-3">
                <small class="text-muted d-block mb-2">💡 ทดลองเข้าสู่ระบบด้วยบัญชีตัวอย่าง:</small>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="fillDemoAccount()">
                    <i class="bi bi-person-check me-1"></i> กรอกบัญชีทดสอบ (660510001 / 123456)
                </button>
            </div>

            <!-- Register Link -->
            <div class="text-center">
                <span class="small text-muted">ยังไม่มีบัญชีผู้ใช้? </span>
                <a href="register.php" class="small fw-semibold text-primary text-decoration-none">
                    สมัครสมาชิกใหม่ที่นี่
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

    <script>
        function fillDemoAccount() {
            document.getElementById('student_id').value = '660510001';
            document.getElementById('password').value = '123456';
        }
    </script>
</body>
</html>
