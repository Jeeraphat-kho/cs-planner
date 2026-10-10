<?php
/**
 * CMU Computer Science Study Planner
 * Authentication Helper & Session Manager
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'is_guest' => true,
                'message' => 'กรุณาเข้าสู่ระบบหากต้องการบันทึกข้อมูลลงฐานข้อมูล'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: login.php');
        exit;
    }
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => (int)$_SESSION['user_id'],
        'student_id' => $_SESSION['student_id'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'plan_id' => (int)($_SESSION['plan_id'] ?? 1)
    ];
}

function getUserPlanId(PDO $pdo, int $userId, string $studentId, string $fullName): int {
    $stmt = $pdo->prepare("SELECT id FROM study_plans WHERE user_id = :user_id LIMIT 1");
    $stmt->execute([':user_id' => $userId]);
    $plan = $stmt->fetch();

    if ($plan) {
        return (int)$plan['id'];
    }

    $stmtCreate = $pdo->prepare("
        INSERT INTO study_plans (user_id, student_id, student_name, title) 
        VALUES (:user_id, :student_id, :student_name, 'แผนการเรียน 4 ปี (วิทยาการคอมพิวเตอร์)')
    ");
    $stmtCreate->execute([
        ':user_id' => $userId,
        ':student_id' => $studentId,
        ':student_name' => $fullName
    ]);

    return (int)$pdo->lastInsertId();
}

/**
 * ตรวจสอบว่าเป็นผู้ดูแลระบบ (รหัส 690510692 หรือ admin_cs)
 */
function isAdmin(): bool {
    $user = getCurrentUser();
    return $user !== null && in_array($user['student_id'], ['690510692', 'admin_cs'], true);
}

/**
 * บังคับสิทธิ์ Admin เท่านั้น หากไม่ใช่จะส่ง 403 หรือ Redirect ออก
 */
function requireAdmin(): void {
    requireAuth();
    if (!isAdmin()) {
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false) {
            header('Content-Type: application/json; charset=UTF-8');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (เฉพาะผู้ดูแลระบบรหัส 690510692 เท่านั้น)'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: index.php?error=forbidden');
        exit;
    }
}
