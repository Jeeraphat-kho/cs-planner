<?php
/**
 * CMU Computer Science Study Planner
 * API: admin.php
 * จัดการผู้ใช้งาน, รายวิชา และแคชระบบ (เฉพาะผู้ดูแลระบบรหัส 690510692)
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// ป้องกันความปลอดภัย: เฉพาะรหัส 690510692 เท่านั้น
requireAdmin();

$validCategories = [
    'หมวดวิชาศึกษาทั่วไป',
    'หมวดวิชาแกน',
    'หมวดวิชาเอกบังคับ',
    'หมวดวิชาเอกเลือก',
    'หมวดวิชาเลือกเสรี'
];

try {
    $pdo = Database::getConnection();
    $method = $_SERVER['REQUEST_METHOD'];
    $action = trim($_GET['action'] ?? $_POST['action'] ?? '');

    // อ่านข้อมูล Request Body ถ้าส่งเป็น JSON
    $rawInput = file_get_contents('php://input');
    $inputData = json_decode($rawInput, true);
    if (!is_array($inputData)) {
        $inputData = $_POST;
    }

    if (empty($action) && isset($inputData['action'])) {
        $action = trim($inputData['action']);
    }

    // =========================================================================
    // 1. สถิติภาพรวมระบบ (Overview Stats)
    // =========================================================================
    if ($action === 'get_stats') {
        $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();
        $totalPlans = (int)$pdo->query("SELECT COUNT(*) FROM study_plans")->fetchColumn();
        $totalPlannedCourses = (int)$pdo->query("SELECT COUNT(*) FROM planned_courses")->fetchColumn();
        $totalCache = (int)$pdo->query("SELECT COUNT(*) FROM reg_search_cache")->fetchColumn();
        $activeCache = (int)$pdo->query("SELECT COUNT(*) FROM reg_search_cache WHERE expires_at > NOW()")->fetchColumn();

        // หมวดหมู่ที่มีวิชามากที่สุด
        $catStats = $pdo->query("SELECT category, COUNT(*) as count FROM courses GROUP BY category ORDER BY count DESC")->fetchAll();

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_users' => $totalUsers,
                'total_courses' => $totalCourses,
                'total_plans' => $totalPlans,
                'total_planned_courses' => $totalPlannedCourses,
                'total_cache' => $totalCache,
                'active_cache' => $activeCache,
                'category_stats' => $catStats
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // 2. จัดการผู้ใช้งาน (User Management)
    // =========================================================================
    if ($action === 'get_users') {
        $search = trim($_GET['search'] ?? '');
        $sql = "
            SELECT 
                u.id, 
                u.student_id, 
                u.full_name, 
                u.created_at, 
                u.updated_at,
                COUNT(DISTINCT sp.id) AS plans_count,
                COUNT(DISTINCT pc.id) AS planned_courses_count
            FROM users u
            LEFT JOIN study_plans sp ON sp.user_id = u.id
            LEFT JOIN planned_courses pc ON pc.plan_id = sp.id
        ";

        $params = [];
        if (!empty($search)) {
            $sql .= " WHERE u.student_id LIKE :s OR u.full_name LIKE :s2 ";
            $params[':s'] = "%{$search}%";
            $params[':s2'] = "%{$search}%";
        }

        $sql .= " GROUP BY u.id ORDER BY u.id DESC ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'users' => $users
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'create_user') {
        $studentId = trim($inputData['student_id'] ?? '');
        $fullName = trim($inputData['full_name'] ?? '');
        $password = trim($inputData['password'] ?? '');

        if (empty($studentId) || empty($fullName) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'กรุณากรอกรหัสนักศึกษา, ชื่อ-นามสกุล และรหัสผ่านให้ครบถ้วน'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ตรวจสอบรหัสซ้ำ
        $chk = $pdo->prepare("SELECT id FROM users WHERE student_id = :sid LIMIT 1");
        $chk->execute([':sid' => $studentId]);
        if ($chk->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "รหัสนักศึกษา {$studentId} มีอยู่ในระบบแล้ว"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $ins = $pdo->prepare("INSERT INTO users (student_id, full_name, password) VALUES (:sid, :name, :pass)");
        $ins->execute([
            ':sid' => $studentId,
            ':name' => $fullName,
            ':pass' => $hash
        ]);
        $newUserId = (int)$pdo->lastInsertId();

        // สร้างแผนการเรียนเริ่มต้นให้ด้วย
        getUserPlanId($pdo, $newUserId, $studentId, $fullName);

        echo json_encode([
            'success' => true,
            'message' => "เพิ่มผู้ใช้ {$fullName} ({$studentId}) เรียบร้อยแล้ว",
            'user_id' => $newUserId
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'update_user') {
        $userId = (int)($inputData['user_id'] ?? 0);
        $fullName = trim($inputData['full_name'] ?? '');
        $newPassword = trim($inputData['new_password'] ?? '');

        if ($userId <= 0 || empty($fullName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ข้อมูลผู้ใช้ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!empty($newPassword)) {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET full_name = :name, password = :pass WHERE id = :id");
            $stmt->execute([':name' => $fullName, ':pass' => $hash, ':id' => $userId]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = :name WHERE id = :id");
            $stmt->execute([':name' => $fullName, ':id' => $userId]);
        }

        // อัปเดตชื่อในตาราง study_plans ด้วย
        $stmtPlan = $pdo->prepare("UPDATE study_plans SET student_name = :name WHERE user_id = :uid");
        $stmtPlan->execute([':name' => $fullName, ':uid' => $userId]);

        echo json_encode([
            'success' => true,
            'message' => 'อัปเดตข้อมูลผู้ใช้งานเรียบร้อยแล้ว'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'reset_password') {
        $userId = (int)($inputData['user_id'] ?? 0);
        $newPassword = trim($inputData['new_password'] ?? '');

        if ($userId <= 0 || strlen($newPassword) < 4) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 4 ตัวอักษร'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password = :pass WHERE id = :id");
        $stmt->execute([':pass' => $hash, ':id' => $userId]);

        echo json_encode([
            'success' => true,
            'message' => 'รีเซ็ตรหัสผ่านเรียบร้อยแล้ว'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'delete_user') {
        $userId = (int)($inputData['user_id'] ?? 0);

        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสผู้ใช้ที่ต้องการลบ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ป้องกันไม่ให้ลบตัวเอง (690510692)
        $chk = $pdo->prepare("SELECT student_id, full_name FROM users WHERE id = :id LIMIT 1");
        $chk->execute([':id' => $userId]);
        $targetUser = $chk->fetch();

        if (!$targetUser) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้นี้ในระบบ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (in_array($targetUser['student_id'], ['690510692', 'admin_cs'], true)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'ไม่อนุญาตให้ลบบัญชีผู้ดูแลระบบหลัก (690510692 / admin_cs)'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $del = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $del->execute([':id' => $userId]);

        echo json_encode([
            'success' => true,
            'message' => "ลบผู้ใช้ {$targetUser['full_name']} ({$targetUser['student_id']}) เรียบร้อยแล้ว"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // 3. จัดการกระบวนวิชา (Course Management)
    // =========================================================================
    if ($action === 'get_courses') {
        $search = trim($_GET['search'] ?? '');
        $category = trim($_GET['category'] ?? '');

        $sql = "
            SELECT 
                c.id, 
                c.course_code, 
                c.course_name, 
                c.credits, 
                c.section, 
                c.class_time, 
                c.instructor, 
                c.category, 
                c.description, 
                c.created_at,
                COUNT(pc.id) AS planned_count
            FROM courses c
            LEFT JOIN planned_courses pc ON pc.course_id = c.id
            WHERE 1=1
        ";

        $params = [];
        if (!empty($search)) {
            $sql .= " AND (c.course_code LIKE :s OR c.course_name LIKE :s2 OR c.instructor LIKE :s3) ";
            $params[':s'] = "%{$search}%";
            $params[':s2'] = "%{$search}%";
            $params[':s3'] = "%{$search}%";
        }

        if (!empty($category)) {
            $sql .= " AND c.category = :cat ";
            $params[':cat'] = $category;
        }

        $sql .= " GROUP BY c.id ORDER BY c.course_code ASC ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $courses = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'count' => count($courses),
            'courses' => $courses
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'get_course') {
        $courseId = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $courseId]);
        $course = $stmt->fetch();

        if (!$course) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบกระบวนวิชานี้'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode([
            'success' => true,
            'course' => $course
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'create_course') {
        $courseCode = strtoupper(trim($inputData['course_code'] ?? ''));
        $courseName = trim($inputData['course_name'] ?? '');
        $credits = isset($inputData['credits']) ? (int)$inputData['credits'] : 3;
        $category = trim($inputData['category'] ?? 'หมวดวิชาเอกเลือก');
        $section = trim($inputData['section'] ?? '');
        $classTime = trim($inputData['class_time'] ?? '');
        $instructor = trim($inputData['instructor'] ?? '');
        $description = trim($inputData['description'] ?? '');

        if (empty($courseCode) || empty($courseName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'กรุณากรอกรหัสวิชาและชื่อกระบวนวิชา'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($credits < 1 || $credits > 12) {
            $credits = 3;
        }

        if (!in_array($category, $validCategories, true)) {
            $category = 'หมวดวิชาเอกเลือก';
        }

        // ตรวจสอบรหัสวิชาซ้ำ
        $chk = $pdo->prepare("SELECT id FROM courses WHERE course_code = :code LIMIT 1");
        $chk->execute([':code' => $courseCode]);
        if ($chk->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "รหัสกระบวนวิชา {$courseCode} มีอยู่ในระบบแล้ว"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO courses (course_code, course_name, credits, section, class_time, instructor, category, description)
            VALUES (:code, :name, :credits, :sec, :ctime, :inst, :cat, :desc)
        ");
        $stmt->execute([
            ':code' => $courseCode,
            ':name' => $courseName,
            ':credits' => $credits,
            ':sec' => $section,
            ':ctime' => $classTime,
            ':inst' => $instructor,
            ':cat' => $category,
            ':desc' => $description
        ]);

        echo json_encode([
            'success' => true,
            'message' => "เพิ่มกระบวนวิชา {$courseCode} ({$courseName}) สำเร็จแล้ว",
            'course_id' => (int)$pdo->lastInsertId()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'update_course') {
        $courseId = (int)($inputData['course_id'] ?? 0);
        $courseCode = strtoupper(trim($inputData['course_code'] ?? ''));
        $courseName = trim($inputData['course_name'] ?? '');
        $credits = isset($inputData['credits']) ? (int)$inputData['credits'] : 3;
        $category = trim($inputData['category'] ?? 'หมวดวิชาเอกเลือก');
        $section = trim($inputData['section'] ?? '');
        $classTime = trim($inputData['class_time'] ?? '');
        $instructor = trim($inputData['instructor'] ?? '');
        $description = trim($inputData['description'] ?? '');

        if ($courseId <= 0 || empty($courseCode) || empty($courseName)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ข้อมูลกระบวนวิชาไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ตรวจสอบรหัสวิชาซ้ำกับวิชาอื่น
        $chk = $pdo->prepare("SELECT id FROM courses WHERE course_code = :code AND id != :id LIMIT 1");
        $chk->execute([':code' => $courseCode, ':id' => $courseId]);
        if ($chk->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => "รหัสวิชา {$courseCode} ซ้ำกับวิชาอื่นในระบบ"], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE courses 
            SET course_code = :code, course_name = :name, credits = :credits, 
                section = :sec, class_time = :ctime, instructor = :inst, 
                category = :cat, description = :desc 
            WHERE id = :id
        ");
        $stmt->execute([
            ':code' => $courseCode,
            ':name' => $courseName,
            ':credits' => $credits,
            ':sec' => $section,
            ':ctime' => $classTime,
            ':inst' => $instructor,
            ':cat' => $category,
            ':desc' => $description,
            ':id' => $courseId
        ]);

        echo json_encode([
            'success' => true,
            'message' => "แก้ไขกระบวนวิชา {$courseCode} เรียบร้อยแล้ว"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'delete_course') {
        $courseId = (int)($inputData['course_id'] ?? 0);

        if ($courseId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสวิชาที่ต้องการลบ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtChk = $pdo->prepare("SELECT course_code, course_name FROM courses WHERE id = :id LIMIT 1");
        $stmtChk->execute([':id' => $courseId]);
        $target = $stmtChk->fetch();

        if (!$target) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบกระบวนวิชานี้ในระบบ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $del = $pdo->prepare("DELETE FROM courses WHERE id = :id");
        $del->execute([':id' => $courseId]);

        echo json_encode([
            'success' => true,
            'message' => "ลบกระบวนวิชา {$target['course_code']} ({$target['course_name']}) ออกจากระบบเรียบร้อยแล้ว"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // 4. จัดการแคชสำนักทะเบียน (REG Cache Management)
    // =========================================================================
    if ($action === 'get_caches') {
        $stmt = $pdo->query("
            SELECT id, term, search_type, query_str, courses_count, created_at, expires_at,
                   IF(expires_at > NOW(), 1, 0) AS is_active
            FROM reg_search_cache 
            ORDER BY id DESC 
            LIMIT 100
        ");
        $caches = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'caches' => $caches
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'clear_cache') {
        $mode = trim($inputData['mode'] ?? 'all');
        if ($mode === 'expired') {
            $stmt = $pdo->query("DELETE FROM reg_search_cache WHERE expires_at <= NOW()");
            $deleted = $stmt->rowCount();
            $msg = "ล้างแคชที่หมดอายุแล้วจำนวน {$deleted} รายการ เรียบร้อย";
        } else {
            $stmt = $pdo->query("TRUNCATE TABLE reg_search_cache");
            $msg = "ล้างแคชสำนักทะเบียนทั้งหมดเรียบร้อยแล้ว";
        }

        echo json_encode([
            'success' => true,
            'message' => $msg
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Default Action Not Found
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => "ไม่พบคำสั่ง action: '{$action}'"
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการประมวลผล: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
