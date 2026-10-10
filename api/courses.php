<?php
/**
 * API: courses.php
 * ค้นหาและดึงข้อมูลรายวิชา รวมถึงสร้างรายวิชาใหม่เข้าสู่ฐานข้อมูลส่วนกลาง
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';

$validCategories = [
    'หมวดวิชาศึกษาทั่วไป',
    'หมวดวิชาแกน',
    'หมวดวิชาเอกบังคับ',
    'หมวดวิชาเอกเลือก',
    'หมวดวิชาเลือกเสรี'
];

try {
    $pdo = Database::getConnection();

    // -----------------------------------------------------------------
    // 1. POST: สร้างรายวิชาใหม่เข้าสู่ฐานข้อมูลส่วนกลาง (Crowdsourced)
    // -----------------------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!$data || !is_array($data)) {
            $data = $_POST;
        }

        $courseCode = strtoupper(trim($data['course_code'] ?? ''));
        $courseName = trim($data['course_name'] ?? '');
        $credits = isset($data['credits']) ? (int)$data['credits'] : 3;
        $category = trim($data['category'] ?? 'หมวดวิชาเอกเลือก');
        $section = trim($data['section'] ?? '');
        $classTime = trim($data['class_time'] ?? '');
        $instructor = trim($data['instructor'] ?? '');
        $description = trim($data['description'] ?? '');

        // ตรวจสอบความถูกต้องของข้อมูล
        if ($courseCode === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'กรุณาระบุรหัสกระบวนวิชา (เช่น 204499 หรือ 001103)'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($courseName === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'กรุณาระบุชื่อกระบวนวิชา'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($credits < 1 || $credits > 12) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'จำนวนหน่วยกิตต้องอยู่ระหว่าง 1 ถึง 12 หน่วยกิต'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!in_array($category, $validCategories, true)) {
            $category = 'หมวดวิชาเอกเลือก';
        }

        // ตรวจสอบว่ามีรหัสวิชานี้ในฐานข้อมูลแล้วหรือไม่
        $checkStmt = $pdo->prepare("SELECT id, course_code, course_name, credits, category FROM courses WHERE course_code = :code LIMIT 1");
        $checkStmt->execute([':code' => $courseCode]);
        $existing = $checkStmt->fetch();

        $allowDuplicate = !empty($data['force_create']);

        if ($existing && !$allowDuplicate) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'duplicate' => true,
                'message' => "รหัสวิชา {$courseCode} มีอยู่ในระบบแล้ว ({$existing['course_name']} - {$existing['category']})",
                'existing_course' => $existing
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // บันทึกรายวิชาใหม่ลงตาราง courses
        $insertSql = "INSERT INTO courses (course_code, course_name, credits, section, class_time, instructor, category, description) 
                      VALUES (:code, :name, :credits, :section, :class_time, :instructor, :category, :description)";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([
            ':code' => $courseCode,
            ':name' => $courseName,
            ':credits' => $credits,
            ':section' => $section,
            ':class_time' => $classTime,
            ':instructor' => $instructor,
            ':category' => $category,
            ':description' => $description !== '' ? $description : 'รายวิชาที่ร่วมสร้างโดยชุมชนนักศึกษา (Crowdsourced)'
        ]);

        $newId = (int)$pdo->lastInsertId();

        $getStmt = $pdo->prepare("SELECT * FROM courses WHERE id = :id");
        $getStmt->execute([':id' => $newId]);
        $newCourse = $getStmt->fetch();

        echo json_encode([
            'success' => true,
            'message' => "เพิ่มกระบวนวิชา {$courseCode} {$courseName} เข้าสู่ฐานข้อมูลส่วนกลางสำเร็จเรียบร้อยแล้ว!",
            'data' => $newCourse
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -----------------------------------------------------------------
    // 2. GET: ค้นหาและดึงข้อมูลรายวิชา
    // -----------------------------------------------------------------
    $q = trim($_GET['q'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $recentOnly = !empty($_GET['recent']);
    $limit = isset($_GET['limit']) ? min(max((int)$_GET['limit'], 1), 200) : 100;

    $sql = "SELECT id, course_code, course_name, credits, section, class_time, instructor, category, description, created_at 
            FROM courses WHERE 1=1";
    $params = [];

    if ($q !== '') {
        $sql .= " AND (course_code LIKE :q1 OR course_name LIKE :q2 OR instructor LIKE :q3)";
        $searchTerm = "%{$q}%";
        $params[':q1'] = $searchTerm;
        $params[':q2'] = $searchTerm;
        $params[':q3'] = $searchTerm;
    }

    if ($category !== '' && $category !== 'all') {
        $sql .= " AND category = :category";
        $params[':category'] = $category;
    }

    if ($recentOnly) {
        $sql .= " ORDER BY id DESC LIMIT :limit";
    } else {
        $sql .= " ORDER BY course_code ASC LIMIT :limit";
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $courses = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count' => count($courses),
        'data' => $courses
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการประมวลผลรายวิชา: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

