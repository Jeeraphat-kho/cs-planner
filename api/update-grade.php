<?php
/**
 * API: update-grade.php
 * ปรับปรุงเกรดที่คาดว่าจะได้รับของรายวิชาในแผน
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

try {
    $pdo = Database::getConnection();

    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อนดำเนินการ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $currentUser = getCurrentUser();
    $planId = getUserPlanId($pdo, $currentUser['id'], $currentUser['student_id'], $currentUser['full_name']);

    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true) ?? [];
    $input = array_merge($_POST, $jsonInput);

    $id = (int)($input['id'] ?? 0);
    $grade = isset($input['expected_grade']) ? strtoupper(trim($input['expected_grade'])) : null;

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ระบุ id รายการไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $validGrades = ['A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'F', ''];
    if ($grade !== null && !in_array($grade, $validGrades, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'เกรดที่ระบุไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ตรวจสอบว่ารายการนี้เป็นของผู้ใช้ปัจจุบัน
    $stmtCheck = $pdo->prepare("
        SELECT pc.id, c.course_code 
        FROM planned_courses pc 
        JOIN courses c ON pc.course_id = c.id 
        WHERE pc.id = :id AND pc.plan_id = :plan_id
    ");
    $stmtCheck->execute([':id' => $id, ':plan_id' => $planId]);
    $item = $stmtCheck->fetch();

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'ไม่พบรายการรายวิชานี้ในแผนของคุณ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $newGradeVal = ($grade === '' || $grade === null) ? null : $grade;

    $stmt = $pdo->prepare("UPDATE planned_courses SET expected_grade = :grade, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND plan_id = :plan_id");
    $stmt->execute([
        ':grade' => $newGradeVal,
        ':id' => $id,
        ':plan_id' => $planId
    ]);

    $gradeLabel = $newGradeVal ? "เกรด {$newGradeVal}" : 'ยังไม่ประเมินเกรด';
    echo json_encode([
        'success' => true,
        'message' => "อัปเดตวิชา {$item['course_code']} เป็น{$gradeLabel} เรียบร้อยแล้ว",
        'id' => $id,
        'expected_grade' => $newGradeVal
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการอัปเดตเกรด: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
