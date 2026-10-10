<?php
/**
 * CMU Computer Science Study Planner
 * API: reg-courses.php
 * ค้นหากระบวนวิชาจากสำนักทะเบียน มช. (REG CMU) พร้อมระบบความปลอดภัยและแคช
 */

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// กำหนดหัวข้อความปลอดภัย
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// -------------------------------------------------------------
// 1. ระบบจำกัดความถี่การค้นหา (Rate Limiting)
// -------------------------------------------------------------
$maxRequestsPerMinute = 15;
$currentTime = time();
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

if (!isset($_SESSION['reg_rate_limit'])) {
    $_SESSION['reg_rate_limit'] = [];
}

// ล้าง timestamp ที่เกิน 60 วินาที
$_SESSION['reg_rate_limit'] = array_filter($_SESSION['reg_rate_limit'], function ($timestamp) use ($currentTime) {
    return ($currentTime - $timestamp) < 60;
});

// ตรวจสอบจำนวนครั้ง
if (count($_SESSION['reg_rate_limit']) >= $maxRequestsPerMinute) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'คุณค้นหาถี่เกินไป กรุณารอ 1 นาทีก่อนลองใหม่อีกครั้ง เพื่อไม่ให้กระทบต่อระบบของมหาวิทยาลัย (Rate Limited)'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------------------------------------------------
// 2. จัดการ Action: IMPORT คอร์สจาก REG เข้าสู่ระบบ / แผนการเรียน
// -------------------------------------------------------------
$action = trim($_GET['action'] ?? $_POST['action'] ?? 'search');

if ($action === 'import') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?: $_POST;

    $courseCode = strtoupper(trim($data['course_code'] ?? ''));
    $courseName = trim($data['course_name'] ?? '');
    $credits = isset($data['credits']) ? (int)$data['credits'] : 3;
    $section = trim($data['section'] ?? '');
    $classTime = trim($data['class_time'] ?? '');
    $instructor = trim($data['instructor'] ?? '');
    $category = trim($data['category'] ?? '');
    $planYear = isset($data['plan_year']) ? (int)$data['plan_year'] : null;
    $planSemester = isset($data['plan_semester']) ? (int)$data['plan_semester'] : null;

    if (!preg_match('/^[0-9A-Z]{6}$/', $courseCode)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'รหัสกระบวนวิชาไม่ถูกต้อง (ต้องเป็น 6 หลัก)'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (empty($courseName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'กรุณาระบุชื่อกระบวนวิชา'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // กำหนดหมวดหมู่อัตโนมัติถ้าไม่ได้เลือก
    if (empty($category)) {
        $prefix = substr($courseCode, 0, 3);
        if ($prefix === '204') {
            $num = (int)substr($courseCode, 3, 3);
            if ($num < 200 || in_array($courseCode, ['204111', '204212', '204255', '204321', '204361'])) {
                $category = 'หมวดวิชาเอกบังคับ';
            } else {
                $category = 'หมวดวิชาเอกเลือก';
            }
        } elseif (in_array($prefix, ['206', '207', '208'])) {
            $category = 'หมวดวิชาแกน';
        } elseif ($prefix === '001') {
            $category = 'หมวดวิชาศึกษาทั่วไป';
        } else {
            $category = 'หมวดวิชาเลือกเสรี';
        }
    }

    try {
        $pdo = Database::getConnection();

        // ตรวจสอบว่ามีวิชานี้ในฐานข้อมูลแล้วหรือไม่
        $stmt = $pdo->prepare("SELECT id, course_code, course_name FROM courses WHERE course_code = :code LIMIT 1");
        $stmt->execute([':code' => $courseCode]);
        $existing = $stmt->fetch();

        $courseId = null;
        if ($existing) {
            $courseId = (int)$existing['id'];
            // อัปเดตข้อมูลเวลาเรียนและอาจารย์ถ้าเดิมยังไม่มี
            $updateStmt = $pdo->prepare("UPDATE courses SET section = IF(section = '', :sec, section), class_time = IF(class_time = '', :ctime, class_time), instructor = IF(instructor = '', :inst, instructor) WHERE id = :id");
            $updateStmt->execute([
                ':sec' => $section,
                ':ctime' => $classTime,
                ':inst' => $instructor,
                ':id' => $courseId
            ]);
        } else {
            // เพิ่มวิชาใหม่
            $insStmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, credits, section, class_time, instructor, category) VALUES (:code, :name, :credits, :sec, :ctime, :inst, :cat)");
            $insStmt->execute([
                ':code' => $courseCode,
                ':name' => $courseName,
                ':credits' => $credits > 0 ? $credits : 3,
                ':sec' => $section,
                ':ctime' => $classTime,
                ':inst' => $instructor,
                ':cat' => $category
            ]);
            $courseId = (int)$pdo->lastInsertId();
        }

        $addedToPlan = false;
        $planMessage = '';

        // ถ้าผู้ใช้ล็อกอินอยู่ และส่ง year + semester มา ให้เพิ่มเข้าแผนการเรียนทันที
        if (isLoggedIn() && $planYear >= 1 && $planYear <= 4 && ($planSemester === 1 || $planSemester === 2)) {
            $user = getCurrentUser();
            // ค้นหา study plan ของ user
            $planStmt = $pdo->prepare("SELECT id FROM study_plans WHERE user_id = :uid LIMIT 1");
            $planStmt->execute([':uid' => $user['id']]);
            $planId = $planStmt->fetchColumn();

            if (!$planId) {
                // สร้าง study plan ใหม่ให้อัตโนมัติ
                $createPlanStmt = $pdo->prepare("INSERT INTO study_plans (user_id, student_id, student_name, title) VALUES (:uid, :sid, :sname, 'แผนการเรียน 4 ปี (วิทยาการคอมพิวเตอร์)')");
                $createPlanStmt->execute([
                    ':uid' => $user['id'],
                    ':sid' => $user['student_id'],
                    ':sname' => $user['full_name']
                ]);
                $planId = (int)$pdo->lastInsertId();
            }

            // ตรวจสอบว่าลงวิชานี้ในเทอมนี้แล้วหรือไม่
            $chkPlanStmt = $pdo->prepare("SELECT id FROM planned_courses WHERE plan_id = :pid AND course_id = :cid AND year = :y AND semester = :s LIMIT 1");
            $chkPlanStmt->execute([
                ':pid' => $planId,
                ':cid' => $courseId,
                ':y' => $planYear,
                ':s' => $planSemester
            ]);

            if ($chkPlanStmt->fetch()) {
                $planMessage = " (วิชานี้มีอยู่ในแผนปี {$planYear} เทอม {$planSemester} อยู่แล้ว)";
            } else {
                $addPlanStmt = $pdo->prepare("INSERT INTO planned_courses (plan_id, course_id, year, semester) VALUES (:pid, :cid, :y, :s)");
                $addPlanStmt->execute([
                    ':pid' => $planId,
                    ':cid' => $courseId,
                    ':y' => $planYear,
                    ':s' => $planSemester
                ]);
                $addedToPlan = true;
                $planMessage = " พร้อมเพิ่มเข้าแผนการเรียนปี {$planYear} เทอม {$planSemester} เรียบร้อยแล้ว";
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "นำเข้ากระบวนวิชา {$courseCode} เข้าสู่ระบบสำเร็จ{$planMessage}",
            'course_id' => $courseId,
            'added_to_plan' => $addedToPlan
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// -------------------------------------------------------------
// 3. จัดการ Action: SEARCH ค้นหากระบวนวิชาจาก REG
// -------------------------------------------------------------
$_SESSION['reg_rate_limit'][] = $currentTime;

// รับและตรวจสอบพารามิเตอร์ Input Validation & Sanitization
$term = trim($_GET['term'] ?? '2/2569');
if (!preg_match('/^[1-3]\/25[5-7][0-9]H?$/', $term)) {
    $term = '2/2569';
}

$searchType = trim($_GET['type'] ?? 'code');
if (!in_array($searchType, ['code', 'group', 'title'], true)) {
    $searchType = 'code';
}

$query = trim($_GET['q'] ?? '');

// Sanitization ตามประเภทการค้นหา
if ($searchType === 'code') {
    $query = preg_replace('/[^0-9]/', '', $query);
    if (strlen($query) > 6) {
        $query = substr($query, 0, 6);
    }
    if (strlen($query) < 6) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณาระบุรหัสกระบวนวิชาให้ครบ 6 หลัก เช่น 204100 หรือ 204111'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} elseif ($searchType === 'group') {
    $query = preg_replace('/[^0-9]/', '', $query);
    if (strlen($query) > 4) {
        $query = substr($query, 0, 4);
    }
    if (strlen($query) < 3) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณาระบุรหัสกลุ่มวิชา 3 หรือ 4 หลัก เช่น 204 (คอมพิวเตอร์) หรือ 206 (คณิตศาสตร์)'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} else { // title
    $query = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $query);
    if (mb_strlen($query, 'UTF-8') < 2) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณาระบุชื่อกระบวนวิชาอย่างน้อย 2 ตัวอักษร เช่น INTRO หรือ PROGRAMMING'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (mb_strlen($query, 'UTF-8') > 40) {
        $query = mb_substr($query, 0, 40, 'UTF-8');
    }
}

// -------------------------------------------------------------
// 4. ตรวจสอบ Smart Cache Layer (Database Cache)
// -------------------------------------------------------------
$cacheKey = md5("{$term}:{$searchType}:{$query}");
$pdo = null;

try {
    $pdo = Database::getConnection();
    $cacheStmt = $pdo->prepare("SELECT response_data, courses_count, created_at FROM reg_search_cache WHERE cache_key = :key AND expires_at > NOW() LIMIT 1");
    $cacheStmt->execute([':key' => $cacheKey]);
    $cached = $cacheStmt->fetch();

    if ($cached) {
        $cachedData = json_decode($cached['response_data'], true);
        if ($cachedData !== null) {
            echo json_encode([
                'success' => true,
                'term' => $term,
                'search_type' => $searchType,
                'query' => $query,
                'from_cache' => true,
                'cached_at' => $cached['created_at'],
                'courses_count' => (int)$cached['courses_count'],
                'data' => $cachedData
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
} catch (Exception $e) {
    // หากมีปัญหากับแคช ให้ทำงานต่อไปโดยข้ามแคช
}

// -------------------------------------------------------------
// 5. ส่ง Request ไปยัง REG CMU (SSRF Protection & Timeout)
// -------------------------------------------------------------
$targetUrl = 'https://www1.reg.cmu.ac.th/registrationoffice/searchcourse.php?tterm=' . urlencode($term);

$postFields = [];
if ($searchType === 'code') {
    $postFields = [
        'fcourse' => $query,
        'button2' => 'Search'
    ];
} elseif ($searchType === 'group') {
    $postFields = [
        'fgroup' => $query,
        'button' => 'Search'
    ];
} else {
    $postFields = [
        'ftitle' => $query,
        'button2' => 'Search'
    ];
}

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postFields),
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_TIMEOUT => 8,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => false // ป้องกัน SSRF Redirect
]);

$responseHtml = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($responseHtml === false || $httpCode !== 200) {
    echo json_encode([
        'success' => false,
        'message' => 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์สำนักทะเบียน มช. ได้ในขณะนี้ (' . ($curlError ?: "HTTP {$httpCode}") . ') กรุณาลองใหม่อีกครั้ง'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// -------------------------------------------------------------
// 6. DOM Parser แปลง HTML Table เป็น Structured JSON
// -------------------------------------------------------------
libxml_use_internal_errors(true);
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="utf-8" ?>' . $responseHtml);
libxml_clear_errors();

$xpath = new DOMXPath($dom);
$trs = $xpath->query('//table[contains(@class, "tblCourse")]//tr');

$courses = [];
$currentCode = null;

foreach ($trs as $tr) {
    // ตรวจสอบหัวตารางรายวิชา
    $th = $xpath->query('./th', $tr);
    if ($th->length > 0) {
        $thText = trim(preg_replace('/\s+/', ' ', $th->item(0)->textContent));
        if (preg_match('/(\d{6})/', $thText, $m)) {
            $currentCode = $m[1];

            // ข้อมูลการสอบ
            $examInfo = '';
            if (preg_match('/(MIDTERM|FINAL)\s*Exam\s*=>\s*([^<]+)/i', $thText, $em)) {
                $examInfo = trim($em[0]);
            }

            // ลิงก์รายละเอียดกระบวนวิชา TQF จาก MIS
            $tqfLink = '';
            $aLinks = $xpath->query('.//a[contains(@href, "mis.cmu.ac.th/tqf")]', $th->item(0));
            if ($aLinks->length > 0) {
                $tqfLink = $aLinks->item(0)->getAttribute('href');
            }

            $courses[$currentCode] = [
                'course_code' => $currentCode,
                'course_name' => '',
                'exam_info' => $examInfo,
                'tqf_link' => $tqfLink,
                'sections' => []
            ];
        }
        continue;
    }

    // ตรวจสอบแถว Section
    $tds = $xpath->query('./td', $tr);
    if ($tds->length >= 10 && $currentCode && isset($courses[$currentCode])) {
        $secLec = trim($tds->item(3)->textContent);
        $secLab = trim($tds->item(4)->textContent);

        if (is_numeric($secLec) || is_numeric($secLab)) {
            $title = trim(preg_replace('/\s+/', ' ', $tds->item(2)->textContent));
            if (empty($courses[$currentCode]['course_name']) && !empty($title)) {
                $courses[$currentCode]['course_name'] = $title;
            }

            $creditLec = trim($tds->item(5)->textContent);
            $creditLab = trim($tds->item(6)->textContent);
            $day = trim($tds->item(7)->textContent);
            $time = trim($tds->item(8)->textContent);
            $room = trim($tds->item(9)->textContent);
            $instructor = $tds->length > 10 ? trim(preg_replace('/\s+/', ' ', $tds->item(10)->textContent)) : '';
            $status = trim($tds->item(0)->textContent);
            $cond = trim($tds->item(1)->textContent);
            $totalSeats = $tds->length > 11 ? trim($tds->item(11)->textContent) : '';
            $enrolled = $tds->length > 12 ? trim($tds->item(12)->textContent) : '';

            $courses[$currentCode]['sections'][] = [
                'sec_lec' => $secLec,
                'sec_lab' => $secLab,
                'credit_lec' => $creditLec,
                'credit_lab' => $creditLab,
                'day' => $day,
                'time' => $time,
                'room' => $room,
                'instructor' => $instructor,
                'status' => $status,
                'condition' => $cond,
                'total_seats' => $totalSeats,
                'enrolled' => $enrolled
            ];
        }
    }
}

// แปลง Array จาก associative เป็น indexed list
$resultList = array_values($courses);
$coursesCount = count($resultList);

// -------------------------------------------------------------
// 7. บันทึกผลลัพธ์ลง Cache (TTL 24 ชั่วโมง)
// -------------------------------------------------------------
if ($pdo !== null && $coursesCount > 0) {
    try {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $insCacheStmt = $pdo->prepare("REPLACE INTO reg_search_cache (cache_key, term, search_type, query_str, response_data, courses_count, expires_at) VALUES (:key, :term, :stype, :q, :data, :count, :exp)");
        $insCacheStmt->execute([
            ':key' => $cacheKey,
            ':term' => $term,
            ':stype' => $searchType,
            ':q' => $query,
            ':data' => json_encode($resultList, JSON_UNESCAPED_UNICODE),
            ':count' => $coursesCount,
            ':exp' => $expiresAt
        ]);
    } catch (Exception $e) {
        // ละเว้นหากบันทึกแคชไม่สำเร็จ
    }
}

echo json_encode([
    'success' => true,
    'term' => $term,
    'search_type' => $searchType,
    'query' => $query,
    'from_cache' => false,
    'courses_count' => $coursesCount,
    'data' => $resultList
], JSON_UNESCAPED_UNICODE);
