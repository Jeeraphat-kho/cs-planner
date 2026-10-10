<?php
/**
 * API: planned-courses.php
 * จัดการรายวิชาในแผนการเรียน (เชื่อมต่อกับระบบล็อกอิน)
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// แปลงตัวอักษรเกรดเป็นแต้มคะแนน
function getGradePoint(?string $grade): ?float {
    if ($grade === null || trim($grade) === '') {
        return null;
    }
    $grade = strtoupper(trim($grade));
    return match ($grade) {
        'A' => 4.0,
        'B+' => 3.5,
        'B' => 3.0,
        'C+' => 2.5,
        'C' => 2.0,
        'D+' => 1.5,
        'D' => 1.0,
        'F' => 0.0,
        default => null,
    };
}

try {
    $pdo = Database::getConnection();

    // ตรวจสอบการเข้าสู่ระบบ
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อนดำเนินการ'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $currentUser = getCurrentUser();
    $userId = $currentUser['id'];

    // อ่านข้อมูล JSON body ถ้ามี
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true) ?? [];
    $input = array_merge($_GET, $_POST, $jsonInput);

    // ดึง plan_id ของผู้ใช้ปัจจุบัน
    $planId = getUserPlanId($pdo, $userId, $currentUser['student_id'], $currentUser['full_name']);

    $method = $_SERVER['REQUEST_METHOD'];

    // -------------------------------------------------------------
    // GET: ดึงรายการวิชาในแผนทั้งหมดและคำนวณสถิติ GPA
    // -------------------------------------------------------------
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT 
                pc.id,
                pc.plan_id,
                pc.course_id,
                pc.year,
                pc.semester,
                pc.expected_grade,
                c.course_code,
                c.course_name,
                c.credits,
                c.section,
                c.class_time,
                c.instructor,
                c.category,
                c.description
            FROM planned_courses pc
            JOIN courses c ON pc.course_id = c.id
            WHERE pc.plan_id = :plan_id
            ORDER BY pc.year ASC, pc.semester ASC, c.course_code ASC
        ");
        $stmt->execute([':plan_id' => $planId]);
        $rows = $stmt->fetchAll();

        // โครงสร้างสำหรับเก็บผลการจัดกลุ่มและคำนวณ
        $semestersData = [];
        for ($y = 1; $y <= 4; $y++) {
            for ($s = 1; $s <= 2; $s++) {
                $key = "{$y}_{$s}";
                $semestersData[$key] = [
                    'year' => $y,
                    'semester' => $s,
                    'courses' => [],
                    'total_credits' => 0,
                    'graded_credits' => 0,
                    'total_points' => 0.0,
                    'gpa' => null,
                    'gpa_text' => 'ยังไม่มีข้อมูลเกรด',
                ];
            }
        }

        $overallTotalCredits = 0;
        $overallGradedCredits = 0;
        $overallTotalPoints = 0.0;
        $totalCoursesCount = count($rows);

        $yearStats = [];
        for ($y = 1; $y <= 4; $y++) {
            $yearStats[$y] = [
                'total_credits' => 0,
                'graded_credits' => 0,
                'total_points' => 0.0,
                'gpa' => null,
                'gpa_text' => 'ยังไม่มีข้อมูลเกรด',
                'courses_count' => 0,
            ];
        }

        foreach ($rows as $row) {
            $y = (int)$row['year'];
            $s = (int)$row['semester'];
            $key = "{$y}_{$s}";
            $credits = (int)$row['credits'];
            $grade = $row['expected_grade'] !== null ? trim($row['expected_grade']) : '';
            $point = getGradePoint($grade);

            $semestersData[$key]['courses'][] = $row;
            $semestersData[$key]['total_credits'] += $credits;

            $yearStats[$y]['total_credits'] += $credits;
            $yearStats[$y]['courses_count'] += 1;

            $overallTotalCredits += $credits;

            if ($point !== null) {
                $semestersData[$key]['graded_credits'] += $credits;
                $semestersData[$key]['total_points'] += ($credits * $point);

                $yearStats[$y]['graded_credits'] += $credits;
                $yearStats[$y]['total_points'] += ($credits * $point);

                $overallGradedCredits += $credits;
                $overallTotalPoints += ($credits * $point);
            }
        }

        // คำนวณ GPA แต่ละภาคการศึกษา
        foreach ($semestersData as &$sem) {
            if ($sem['graded_credits'] > 0) {
                $sem['gpa'] = round($sem['total_points'] / $sem['graded_credits'], 2);
                $sem['gpa_text'] = number_format($sem['gpa'], 2);
            }
        }
        unset($sem);

        // คำนวณ GPA แต่ละชั้นปี
        foreach ($yearStats as &$yr) {
            if ($yr['graded_credits'] > 0) {
                $yr['gpa'] = round($yr['total_points'] / $yr['graded_credits'], 2);
                $yr['gpa_text'] = number_format($yr['gpa'], 2);
            }
        }
        unset($yr);

        // คำนวณ GPAX สะสมตลอด 4 ปี
        $gpax = null;
        $gpaxText = 'ยังไม่มีข้อมูลเกรด';
        if ($overallGradedCredits > 0) {
            $gpax = round($overallTotalPoints / $overallGradedCredits, 2);
            $gpaxText = number_format($gpax, 2);
        }

        // คำนวณหน่วยกิตและจำนวนวิชาตามหมวดหมู่โครงสร้างหลักสูตร (TQF)
        $categoryStats = [
            'หมวดวิชาศึกษาทั่วไป' => ['enrolled' => 0, 'count' => 0],
            'หมวดวิชาแกน' => ['enrolled' => 0, 'count' => 0],
            'หมวดวิชาเอกบังคับ' => ['enrolled' => 0, 'count' => 0],
            'หมวดวิชาเอกเลือก' => ['enrolled' => 0, 'count' => 0],
            'หมวดวิชาเลือกเสรี' => ['enrolled' => 0, 'count' => 0],
        ];

        foreach ($rows as $row) {
            $cat = trim($row['category'] ?? '');
            $cr = (int)$row['credits'];
            if (str_contains($cat, 'ศึกษาทั่วไป')) {
                $categoryStats['หมวดวิชาศึกษาทั่วไป']['enrolled'] += $cr;
                $categoryStats['หมวดวิชาศึกษาทั่วไป']['count']++;
            } elseif (str_contains($cat, 'แกน')) {
                $categoryStats['หมวดวิชาแกน']['enrolled'] += $cr;
                $categoryStats['หมวดวิชาแกน']['count']++;
            } elseif (str_contains($cat, 'เอกบังคับ')) {
                $categoryStats['หมวดวิชาเอกบังคับ']['enrolled'] += $cr;
                $categoryStats['หมวดวิชาเอกบังคับ']['count']++;
            } elseif (str_contains($cat, 'เอกเลือก')) {
                $categoryStats['หมวดวิชาเอกเลือก']['enrolled'] += $cr;
                $categoryStats['หมวดวิชาเอกเลือก']['count']++;
            } else {
                $categoryStats['หมวดวิชาเลือกเสรี']['enrolled'] += $cr;
                $categoryStats['หมวดวิชาเลือกเสรี']['count']++;
            }
        }

        echo json_encode([
            'success' => true,
            'plan_id' => $planId,
            'user' => [
                'student_id' => $currentUser['student_id'],
                'full_name' => $currentUser['full_name'],
            ],
            'summary' => [
                'total_courses' => $totalCoursesCount,
                'total_credits' => $overallTotalCredits,
                'graded_credits' => $overallGradedCredits,
                'gpax' => $gpax,
                'gpax_text' => $gpaxText,
                'year_stats' => $yearStats,
                'category_stats' => $categoryStats,
            ],
            'semesters' => $semestersData,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -------------------------------------------------------------
    // POST / DELETE Actions
    // -------------------------------------------------------------
    $action = $input['action'] ?? ($method === 'DELETE' ? 'delete' : '');

    // 1. เพิ่มวิชาลงในแผนการเรียน (Action: add)
    if ($action === 'add') {
        $courseId = (int)($input['course_id'] ?? 0);
        $year = (int)($input['year'] ?? 0);
        $semester = (int)($input['semester'] ?? 0);
        $expectedGrade = isset($input['expected_grade']) ? trim($input['expected_grade']) : '';

        if ($courseId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'กรุณาระบุรายวิชาที่ต้องการเพิ่ม'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($year < 1 || $year > 4 || $semester < 1 || $semester > 2) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ชั้นปีหรือภาคการศึกษาไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtCourse = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE id = :id");
        $stmtCourse->execute([':id' => $courseId]);
        $course = $stmtCourse->fetch();
        if (!$course) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรายวิชานี้ในระบบ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ตรวจสอบว่ามีการเพิ่มวิชานี้ในภาคการศึกษาเดียวกันแล้วหรือไม่
        $stmtDup = $pdo->prepare("
            SELECT id FROM planned_courses 
            WHERE plan_id = :plan_id AND course_id = :course_id AND year = :year AND semester = :semester
        ");
        $stmtDup->execute([
            ':plan_id' => $planId,
            ':course_id' => $courseId,
            ':year' => $year,
            ':semester' => $semester
        ]);
        if ($stmtDup->fetch()) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => "วิชา {$course['course_code']} ({$course['course_name']}) มีอยู่ใน ปี {$year} เทอม {$semester} แล้ว ไม่สามารถเพิ่มซ้ำในเทอมเดียวกันได้"
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtInsert = $pdo->prepare("
            INSERT INTO planned_courses (plan_id, course_id, year, semester, expected_grade) 
            VALUES (:plan_id, :course_id, :year, :semester, :expected_grade)
        ");
        $stmtInsert->execute([
            ':plan_id' => $planId,
            ':course_id' => $courseId,
            ':year' => $year,
            ':semester' => $semester,
            ':expected_grade' => $expectedGrade !== '' ? $expectedGrade : null
        ]);

        $newId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => "เพิ่มวิชา {$course['course_code']} ในแผนการเรียนเรียบร้อยแล้ว",
            'id' => $newId
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. ลบวิชาออกจากแผน (Action: delete หรือ remove)
    if ($action === 'delete' || $action === 'remove' || $method === 'DELETE') {
        $plannedId = (int)($input['id'] ?? 0);
        if ($plannedId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'กรุณาระบุรายการที่ต้องการลบ'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // ตรวจสอบสิทธิ์ว่าวิชานี้อยู่ในแผนของผู้ใช้จริง
        $stmtGet = $pdo->prepare("
            SELECT c.course_code, c.course_name 
            FROM planned_courses pc 
            JOIN courses c ON pc.course_id = c.id 
            WHERE pc.id = :id AND pc.plan_id = :plan_id
        ");
        $stmtGet->execute([':id' => $plannedId, ':plan_id' => $planId]);
        $item = $stmtGet->fetch();

        if (!$item) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'ไม่พบรายการที่ต้องการลบ หรือคุณไม่มีสิทธิ์เข้าถึง'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmtDelete = $pdo->prepare("DELETE FROM planned_courses WHERE id = :id AND plan_id = :plan_id");
        $stmtDelete->execute([':id' => $plannedId, ':plan_id' => $planId]);

        echo json_encode([
            'success' => true,
            'message' => "ลบรายวิชา {$item['course_code']} ออกจากแผนเรียบร้อยแล้ว"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 3. รีเซ็ตหรือโหลดตัวอย่างใหม่ (Action: load_sample / reset)
    if ($action === 'load_sample' || $action === 'reset') {
        $pdo->prepare("DELETE FROM planned_courses WHERE plan_id = :plan_id")->execute([':plan_id' => $planId]);

        if ($action === 'load_sample') {
            // โหลดรายวิชาตามหลักสูตร CS CMU
            $recommendedCourses = [
                ['001101', 1, 1, 'A'],
                ['206111', 1, 1, 'B+'],
                ['204111', 1, 1, 'A'],
                ['204100', 1, 1, 'A'],
                ['140104', 1, 1, 'B'],
                ['001102', 1, 2, 'B+'],
                ['206183', 1, 2, 'B'],
                ['204115', 1, 2, 'A'],
                ['204255', 1, 2, 'A'],
                ['204212', 2, 1, 'A'],
                ['204231', 2, 1, 'B+'],
                ['204232', 2, 1, '']
            ];

            $stmtFindCourse = $pdo->prepare("SELECT id FROM courses WHERE course_code = :code LIMIT 1");
            $stmtInsertSample = $pdo->prepare("
                INSERT INTO planned_courses (plan_id, course_id, year, semester, expected_grade) 
                VALUES (:plan_id, :course_id, :year, :semester, :expected_grade)
            ");

            foreach ($recommendedCourses as $rc) {
                $stmtFindCourse->execute([':code' => $rc[0]]);
                $cRow = $stmtFindCourse->fetch();
                if ($cRow) {
                    $stmtInsertSample->execute([
                        ':plan_id' => $planId,
                        ':course_id' => $cRow['id'],
                        ':year' => $rc[1],
                        ':semester' => $rc[2],
                        ':expected_grade' => $rc[3] !== '' ? $rc[3] : null
                    ]);
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => ($action === 'load_sample') ? 'โหลดแผนการเรียนตัวอย่างเรียบร้อยแล้ว' : 'ล้างแผนการเรียนทั้งหมดเรียบร้อยแล้ว'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action ไม่ถูกต้อง'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
