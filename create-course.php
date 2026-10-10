<?php
/**
 * CMU Computer Science Study Planner
 * หน้าเพิ่มรายวิชาเข้าสู่ฐานข้อมูลส่วนกลาง (Crowdsourced Course Library)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$loggedIn = isLoggedIn();
$currentUser = getCurrentUser();

$validCategories = [
    'หมวดวิชาศึกษาทั่วไป' => [
        'name' => 'หมวดวิชาศึกษาทั่วไป (General Education)',
        'badge' => 'bg-info-subtle text-info-emphasis border border-info-subtle',
        'desc' => 'วิชาศึกษาทั่วไป มช. เช่น ภาษาอังกฤษ (001xxx), การเป็นพลเมือง, วิทยาศาสตร์ทั่วไป (เป้าหมาย 24 นก.)'
    ],
    'หมวดวิชาแกน' => [
        'name' => 'หมวดวิชาแกน (Core Courses)',
        'badge' => 'bg-primary-subtle text-primary border border-primary-subtle',
        'desc' => 'วิชาพื้นฐานทางวิทยาศาสตร์และคณิตศาสตร์ เช่น แคลคูลัส (206xxx), ฟิสิกส์ (207xxx), สถิติ (208xxx) (เป้าหมาย 25 นก.)'
    ],
    'หมวดวิชาเอกบังคับ' => [
        'name' => 'หมวดวิชาเอกบังคับ (Major Compulsory)',
        'badge' => 'bg-purple-subtle text-primary border border-purple-subtle',
        'desc' => 'วิชาบังคับของภาคคอมพิวเตอร์ เช่น 204111, 204212, 204255, 204321, 204361 เป็นต้น (เป้าหมาย 35 นก.)'
    ],
    'หมวดวิชาเอกเลือก' => [
        'name' => 'หมวดวิชาเอกเลือก (Major Elective)',
        'badge' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        'desc' => 'วิชาเลือกเฉพาะกลุ่มของภาคคอมพิวเตอร์ (รหัส 204xxx ระดับสูง) เช่น AI, Web, Cloud, Game, Data (เป้าหมาย 33 หรือ 36 นก.)'
    ],
    'หมวดวิชาเลือกเสรี' => [
        'name' => 'หมวดวิชาเลือกเสรี (Free Elective)',
        'badge' => 'bg-success-subtle text-success border border-success-subtle',
        'desc' => 'วิชาใดๆ ที่เปิดสอนในมหาวิทยาลัยเชียงใหม่ หรือวิชานอกคณะที่สนใจ (เป้าหมาย 6 นก.)'
    ]
];

$successMsg = null;
$errorMsg = null;
$warningMsg = null;
$createdCourse = null;

// รองรับการบันทึกผ่านฟอร์มแบบ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));
    $courseName = trim($_POST['course_name'] ?? '');
    $credits = isset($_POST['credits']) ? (int)$_POST['credits'] : 3;
    $category = trim($_POST['category'] ?? 'หมวดวิชาเอกเลือก');
    $section = trim($_POST['section'] ?? '');
    $classTime = trim($_POST['class_time'] ?? '');
    $instructor = trim($_POST['instructor'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $forceCreate = !empty($_POST['force_create']);

    if (empty($courseCode)) {
        $errorMsg = 'กรุณากรอกรหัสกระบวนวิชา (เช่น 204499, 050100)';
    } elseif (empty($courseName)) {
        $errorMsg = 'กรุณากรอกชื่อกระบวนวิชา (ภาษาไทยหรืออังกฤษ)';
    } elseif ($credits < 1 || $credits > 12) {
        $errorMsg = 'จำนวนหน่วยกิตต้องอยู่ระหว่าง 1 ถึง 12 หน่วยกิต';
    } elseif (!array_key_exists($category, $validCategories)) {
        $errorMsg = 'กรุณาเลือกหมวดหมู่วิชาที่ถูกต้อง';
    } else {
        try {
            $pdo = Database::getConnection();

            // ตรวจสอบวิชาซ้ำ
            $checkStmt = $pdo->prepare("SELECT * FROM courses WHERE course_code = :code LIMIT 1");
            $checkStmt->execute([':code' => $courseCode]);
            $existing = $checkStmt->fetch();

            if ($existing && !$forceCreate) {
                $warningMsg = [
                    'code' => $courseCode,
                    'name' => $existing['course_name'],
                    'category' => $existing['category'],
                    'credits' => $existing['credits'],
                    'id' => $existing['id']
                ];
            } else {
                // บันทึกรายวิชาใหม่
                $stmt = $pdo->prepare("
                    INSERT INTO courses (course_code, course_name, credits, section, class_time, instructor, category, description)
                    VALUES (:code, :name, :credits, :section, :class_time, :instructor, :category, :description)
                ");

                $note = $description;
                if ($loggedIn && !empty($currentUser['full_name'])) {
                    $note = ($note !== '' ? $note . " | " : "") . "เพิ่มโดย " . $currentUser['full_name'];
                }

                $stmt->execute([
                    ':code' => $courseCode,
                    ':name' => $courseName,
                    ':credits' => $credits,
                    ':section' => $section,
                    ':class_time' => $classTime,
                    ':instructor' => $instructor,
                    ':category' => $category,
                    ':description' => $note !== '' ? $note : 'รายวิชาที่ช่วยกันเพิ่มโดยชุมชนนักศึกษา (Crowdsourced)'
                ]);

                $newId = (int)$pdo->lastInsertId();
                $createdCourse = [
                    'id' => $newId,
                    'code' => $courseCode,
                    'name' => $courseName,
                    'credits' => $credits,
                    'category' => $category
                ];

                $successMsg = "เพิ่มกระบวนวิชา <strong>{$courseCode} {$courseName}</strong> ({$category}) เข้าสู่ฐานข้อมูลส่วนกลางสำเร็จแล้ว! เพื่อนๆ ทุกคนสามารถค้นหาและนำไปวางแผนการเรียนได้ทันที";
            }
        } catch (Exception $e) {
            $errorMsg = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
}

// ดึงสถิติและรายวิชาล่าสุดเพื่อแสดงในหน้า
$totalCoursesCount = 0;
$recentCourses = [];
$categoryCounts = [];

try {
    $pdo = Database::getConnection();
    
    // นับจำนวนรวม
    $totalCoursesCount = (int)$pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

    // นับแยกตามหมวด
    $catStmt = $pdo->query("SELECT category, COUNT(*) as count FROM courses GROUP BY category");
    while ($row = $catStmt->fetch()) {
        $categoryCounts[$row['category']] = (int)$row['count'];
    }

    // ดึงวิชาล่าสุด 50 รายการ
    $recentStmt = $pdo->query("
        SELECT id, course_code, course_name, credits, section, class_time, instructor, category, description, created_at 
        FROM courses 
        ORDER BY id DESC 
        LIMIT 50
    ");
    $recentCourses = $recentStmt->fetchAll();
} catch (Exception $e) {
    // กรณีมีข้อผิดพลาดให้ดำเนินการต่อได้
}

require_once __DIR__ . '/includes/header.php';
?>

<main class="container-xl py-4 flex-grow-1">

    <!-- Breadcrumb & Page Header -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none text-muted"><i class="bi bi-house-door me-1"></i>หน้าหลัก (แผนการเรียน)</a></li>
            <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">เพิ่มรายวิชาเข้าสู่ฐานข้อมูลส่วนกลาง</li>
        </ol>
    </nav>

    <!-- Header Banner -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" style="background: linear-gradient(135deg, #4c1d95 0%, #682c91 50%, #4338ca 100%);">
        <div class="card-body p-4 p-md-5 text-white position-relative">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <span class="badge bg-white bg-opacity-20 text-dark border border-white border-opacity-25 px-3 py-1.5 rounded-pill mb-3">
                        <i class="bi bi-people-fill me-1"></i> CMU Crowdsourced Course Library
                    </span>
                    <h1 class="h2 fw-bold text-white mb-2">
                        เพิ่มรายวิชาใหม่เพื่อแบ่งปันเพื่อนๆ
                    </h1>
                    <p class="text-white text-opacity-90 mb-3 fs-6" style="max-width: 650px;">
                        บางวิชาในหลักสูตรอาจมีการเปิดใหม่ ไม่อัปเดต หรือเป็นวิชาเลือกเสรีต่างคณะ คุณสามารถช่วยเพื่อนๆ โดยการบันทึกรายวิชาเข้าสู่ฐานข้อมูลส่วนกลาง ทุกคนที่ใช้งานระบบจะสามารถค้นหาและนำไปวางแผนการเรียนได้ทันที
                    </p>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2 bg-black bg-opacity-25 px-3 py-1.5 rounded-pill text-white small">
                            <i class="bi bi-collection-fill text-warning"></i>
                            <span>วิชาในระบบทั้งหมด: <strong><?= number_format($totalCoursesCount) ?></strong> วิชา</span>
                        </div>
                        <a href="index.php" class="btn btn-sm btn-light rounded-pill px-3 fw-semibold shadow-sm text-dark">
                            <i class="bi bi-arrow-left me-1"></i> กลับหน้าแผนการเรียน
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 d-none d-lg-flex justify-content-end">
                    <div class="text-center bg-white bg-opacity-10 p-4 rounded-4 border border-white border-opacity-20 backdrop-blur" style="max-width: 260px;">
                        <i class="bi bi-share-fill display-4 text-warning mb-2 d-block"></i>
                        <div class="fw-bold fs-6">ร่วมด้วยช่วยกัน</div>
                        <small class="opacity-75">เพิ่มเพียง 1 วิชา ก็เป็นประโยชน์กับเพื่อน นศ. มช. ทุกชั้นปี</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($successMsg): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 p-4 mb-4 alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-start gap-3">
                <div class="fs-2 text-success"><i class="bi bi-check-circle-fill"></i></div>
                <div class="flex-grow-1">
                    <h5 class="fw-bold mb-1">บันทึกรายวิชาสำเร็จ!</h5>
                    <div class="text-secondary mb-3"><?= $successMsg ?></div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="index.php" class="btn btn-success btn-sm rounded-pill px-3 fw-semibold">
                            <i class="bi bi-calendar-check me-1"></i> ไปที่แผนการเรียน เพื่อเลือกวิชานี้
                        </a>
                        <button type="button" class="btn btn-outline-success btn-sm rounded-pill px-3" onclick="document.getElementById('courseForm').reset(); window.scrollTo({top: 300, behavior: 'smooth'});">
                            <i class="bi bi-plus-lg me-1"></i> เพิ่มวิชาอื่นต่อ
                        </button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($warningMsg): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4" role="alert">
            <div class="d-flex align-items-start gap-3">
                <div class="fs-2 text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="flex-grow-1">
                    <h5 class="fw-bold mb-1">พบรหัสกระบวนวิชานี้ในระบบแล้ว!</h5>
                    <p class="text-secondary mb-3">
                        รหัสวิชา <strong><?= htmlspecialchars($warningMsg['code']) ?></strong> มีอยู่ในฐานข้อมูลแล้ว โดยใช้ชื่อว่า:
                        <br>
                        <strong><?= htmlspecialchars($warningMsg['name']) ?></strong> (<?= htmlspecialchars($warningMsg['credits']) ?> หน่วยกิต - <?= htmlspecialchars($warningMsg['category']) ?>)
                    </p>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="index.php" class="btn btn-primary btn-sm rounded-pill px-3 fw-semibold">
                            <i class="bi bi-search me-1"></i> ไปเลือกวิชานี้ในแผนการเรียน
                        </a>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="course_code" value="<?= htmlspecialchars($_POST['course_code'] ?? '') ?>">
                            <input type="hidden" name="course_name" value="<?= htmlspecialchars($_POST['course_name'] ?? '') ?>">
                            <input type="hidden" name="credits" value="<?= htmlspecialchars($_POST['credits'] ?? '3') ?>">
                            <input type="hidden" name="category" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">
                            <input type="hidden" name="section" value="<?= htmlspecialchars($_POST['section'] ?? '') ?>">
                            <input type="hidden" name="class_time" value="<?= htmlspecialchars($_POST['class_time'] ?? '') ?>">
                            <input type="hidden" name="instructor" value="<?= htmlspecialchars($_POST['instructor'] ?? '') ?>">
                            <input type="hidden" name="description" value="<?= htmlspecialchars($_POST['description'] ?? '') ?>">
                            <input type="hidden" name="force_create" value="1">
                            <button type="submit" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                                <i class="bi bi-plus-circle me-1"></i> ยืนยันต้องการเพิ่มซ้ำอีกตอนเรียน/Section
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 p-3 mb-4 alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-x-circle-fill fs-5 text-danger"></i>
                <div class="fw-medium"><?= htmlspecialchars($errorMsg) ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Col 1: Form เพิ่มรายวิชา -->
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="kpi-icon purple shadow-sm" style="width: 38px; height: 38px; font-size: 1.1rem;">
                            <i class="bi bi-plus-lg"></i>
                        </div>
                        <div>
                            <h2 class="h5 fw-bold text-dark mb-0">แบบฟอร์มเพิ่มรายวิชา</h2>
                            <small class="text-muted">กรอกข้อมูลกระบวนวิชาเพื่อบันทึกเข้าสู่ระบบ</small>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form method="POST" id="courseForm" novalidate>
                        <!-- รหัสวิชา & จำนวนหน่วยกิต -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label for="course_code" class="form-label fw-semibold text-dark small">
                                    รหัสกระบวนวิชา (Course Code) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-tag-fill"></i></span>
                                    <input type="text" class="form-control border-start-0" id="course_code" name="course_code" 
                                           placeholder="เช่น 204499 หรือ 050100" 
                                           value="<?= htmlspecialchars($_POST['course_code'] ?? '') ?>" 
                                           maxlength="20" required>
                                </div>
                                <div class="form-text small text-muted">รหัสกระบวนวิชา 6 หลักของ มช. หรือรหัสวิชาของหลักสูตร</div>
                            </div>
                            <div class="col-md-5">
                                <label for="credits" class="form-label fw-semibold text-dark small">
                                    จำนวนหน่วยกิต <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-calculator"></i></span>
                                    <select class="form-select border-start-0" id="credits" name="credits" required>
                                        <option value="1" <?= (($_POST['credits'] ?? '3') == '1') ? 'selected' : '' ?>>1 หน่วยกิต</option>
                                        <option value="2" <?= (($_POST['credits'] ?? '3') == '2') ? 'selected' : '' ?>>2 หน่วยกิต</option>
                                        <option value="3" <?= (($_POST['credits'] ?? '3') == '3') ? 'selected' : '' ?>>3 หน่วยกิต (ปกติ)</option>
                                        <option value="4" <?= (($_POST['credits'] ?? '3') == '4') ? 'selected' : '' ?>>4 หน่วยกิต</option>
                                        <option value="6" <?= (($_POST['credits'] ?? '3') == '6') ? 'selected' : '' ?>>6 หน่วยกิต (สหกิจฯ/โครงงาน)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- ชื่อวิชา -->
                        <div class="mb-3">
                            <label for="course_name" class="form-label fw-semibold text-dark small">
                                ชื่อกระบวนวิชา (Course Name) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-book-half"></i></span>
                                <input type="text" class="form-control border-start-0" id="course_name" name="course_name" 
                                       placeholder="เช่น สถาปัตยกรรมคลาวด์คอมพิวติ้ง หรือ การเงินเบื้องต้น" 
                                       value="<?= htmlspecialchars($_POST['course_name'] ?? '') ?>" required>
                            </div>
                            <div class="form-text small text-muted">แนะนำให้ใส่ชื่อภาษาไทย หรือชื่อวิชาที่ปรากฏในระบบ CMU SIS / TQF</div>
                        </div>

                        <!-- หมวดหมู่วิชา (สำคัญมากสำหรับ Progress Tracker) -->
                        <div class="mb-3">
                            <label for="category" class="form-label fw-semibold text-dark small">
                                หมวดหมู่วิชาตามหลักสูตร <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-diagram-3-fill"></i></span>
                                <select class="form-select border-start-0" id="category" name="category" required onchange="updateCategoryHint()">
                                    <?php foreach ($validCategories as $key => $info): ?>
                                        <option value="<?= htmlspecialchars($key) ?>" <?= (($_POST['category'] ?? 'หมวดวิชาเอกเลือก') == $key) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($info['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="alert alert-light border mt-2 p-2 rounded-3 small" id="categoryHintBox">
                                <i class="bi bi-info-circle-fill text-primary me-1"></i>
                                <span id="categoryHintText">เลือกหมวดหมู่วิชาให้ตรงกับโครงสร้างหลักสูตรเพื่อการคำนวณหน่วยกิตที่ถูกต้อง</span>
                            </div>
                        </div>

                        <!-- ข้อมูลเพิ่มเติม: Section & วันเวลา & ผู้สอน -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="section" class="form-label fw-semibold text-dark small">
                                    Section (ตอนเรียน) <small class="text-muted fw-normal">(ถ้ามี)</small>
                                </label>
                                <input type="text" class="form-control" id="section" name="section" 
                                       placeholder="เช่น 001, 701" 
                                       value="<?= htmlspecialchars($_POST['section'] ?? '') ?>">
                            </div>
                            <div class="col-md-8">
                                <label for="class_time" class="form-label fw-semibold text-dark small">
                                    วันและเวลาเรียน <small class="text-muted fw-normal">(ถ้ามี)</small>
                                </label>
                                <input type="text" class="form-control" id="class_time" name="class_time" 
                                       placeholder="เช่น จ. พฤ. 09.30 - 11.00 น." 
                                       value="<?= htmlspecialchars($_POST['class_time'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- อาจารย์ผู้สอน -->
                        <div class="mb-3">
                            <label for="instructor" class="form-label fw-semibold text-dark small">
                                อาจารย์ผู้สอน <small class="text-muted fw-normal">(ถ้ามี)</small>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person-badge"></i></span>
                                <input type="text" class="form-control border-start-0" id="instructor" name="instructor" 
                                       placeholder="เช่น ผศ.ดร. นามสมมุติ หรือ คณาจารย์ภาคคอมฯ" 
                                       value="<?= htmlspecialchars($_POST['instructor'] ?? '') ?>">
                            </div>
                        </div>

                        <!-- คำอธิบาย / โน้ตย่อ -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold text-dark small">
                                คำอธิบายเพิ่มเติม / เงื่อนไขก่อนหน้า (Prerequisite) <small class="text-muted fw-normal">(ถ้ามี)</small>
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="2" 
                                      placeholder="เช่น ต้องผ่าน 204211 ก่อน, วิชาเลือกแนะนำสำหรับสาย Data / DevOps, รีวิววิชาสั้นๆ"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary py-2.5 rounded-pill fw-semibold shadow-sm" style="background: linear-gradient(135deg, #682c91 0%, #6366f1 100%); border: none;">
                                <i class="bi bi-cloud-arrow-up-fill me-1"></i> บันทึกรายวิชาเข้าสู่ฐานข้อมูลส่วนกลาง
                            </button>
                            <a href="index.php" class="btn btn-light py-2 rounded-pill text-secondary">
                                ยกเลิก / กลับไปที่แผนการเรียน
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Col 2: รายการวิชาในระบบ และคำแนะนำ -->
        <div class="col-12 col-lg-6">
            <!-- Card สถิติแยกตามหมวด -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                    <h2 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-pie-chart-fill text-primary"></i>
                        <span>สัดส่วนวิชาในฐานข้อมูลส่วนกลาง (<?= number_format($totalCoursesCount) ?> วิชา)</span>
                    </h2>
                </div>
                <div class="card-body px-4 pb-4 pt-2">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($validCategories as $catKey => $catMeta): ?>
                            <?php $cnt = $categoryCounts[$catKey] ?? 0; ?>
                            <div class="p-2 px-3 rounded-3 border bg-light d-flex align-items-center gap-2 flex-grow-1" style="min-width: 140px;">
                                <div class="badge rounded-circle p-2 <?= str_replace('border', '', $catMeta['badge']) ?>" style="width: 12px; height: 12px;"></div>
                                <div>
                                    <div class="small fw-semibold text-dark text-truncate" style="max-width: 150px;"><?= $catKey ?></div>
                                    <div class="text-muted small"><strong><?= $cnt ?></strong> วิชา</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Card ค้นหาและดูรายวิชาที่เพิ่งเพิ่ม -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-2">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h2 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-clock-history text-secondary"></i>
                            <span>รายวิชาที่อยู่ในระบบ (ค้นหาด่วน)</span>
                        </h2>
                        <span class="badge bg-secondary-subtle text-secondary border">50 รายการล่าสุด</span>
                    </div>

                    <!-- Filter & Search Input in Table -->
                    <div class="mt-3">
                        <div class="input-group input-group-sm rounded-pill overflow-hidden border">
                            <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="filterInput" class="form-control border-0 py-2 ps-2" placeholder="พิมพ์ค้นหารหัสวิชา หรือชื่อวิชา..." onkeyup="filterCourseList()">
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0" id="coursesTable">
                            <thead class="table-light sticky-top small text-muted">
                                <tr>
                                    <th class="ps-4">รหัส / หมวด</th>
                                    <th>ชื่อกระบวนวิชา</th>
                                    <th class="text-center">นก.</th>
                                    <th class="pe-4 text-end">ดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentCourses)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">ยังไม่มีรายวิชาในระบบ</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentCourses as $c): ?>
                                        <?php 
                                            $catInfo = $validCategories[$c['category']] ?? [
                                                'badge' => 'bg-secondary-subtle text-secondary'
                                            ];
                                        ?>
                                        <tr class="course-row">
                                            <td class="ps-4" style="min-width: 140px;">
                                                <div class="fw-bold font-monospace text-primary"><?= htmlspecialchars($c['course_code']) ?></div>
                                                <span class="badge <?= $catInfo['badge'] ?> small" style="font-size: 0.7rem;">
                                                    <?= htmlspecialchars($c['category']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark course-title"><?= htmlspecialchars($c['course_name']) ?></div>
                                                <?php if (!empty($c['instructor']) || !empty($c['class_time'])): ?>
                                                    <div class="text-muted small" style="font-size: 0.75rem;">
                                                        <?= !empty($c['instructor']) ? '<i class="bi bi-person me-1"></i>' . htmlspecialchars($c['instructor']) : '' ?>
                                                        <?= (!empty($c['instructor']) && !empty($c['class_time'])) ? ' | ' : '' ?>
                                                        <?= !empty($c['class_time']) ? '<i class="bi bi-clock me-1"></i>' . htmlspecialchars($c['class_time']) : '' ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-light text-dark border"><?= (int)$c['credits'] ?></span>
                                            </td>
                                            <td class="pe-4 text-end">
                                                <a href="index.php?search=<?= urlencode($c['course_code']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-1" style="font-size: 0.75rem;" title="ไปเพิ่มลงในแผนการเรียน">
                                                    <i class="bi bi-calendar-plus me-1"></i>เลือกวิชานี้
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-0 py-2.5 px-4 text-muted small d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-info-circle me-1"></i>วิชาที่บันทึกแล้วจะพร้อมให้ทุกคนเลือกใช้งานทันที</span>
                    <a href="index.php" class="text-primary text-decoration-none fw-medium">กลับหน้าแผนการเรียน &rarr;</a>
                </div>
            </div>
        </div>
    </div>

</main>

<script>
const categoryDescriptions = {
    'หมวดวิชาศึกษาทั่วไป': 'วิชาศึกษาทั่วไป มช. เช่น ภาษาอังกฤษ (001xxx), การเป็นพลเมือง, วิทยาศาสตร์ทั่วไป (เป้าหมาย 24 นก.)',
    'หมวดวิชาแกน': 'วิชาพื้นฐานทางวิทยาศาสตร์และคณิตศาสตร์ เช่น แคลคูลัส (206xxx), ฟิสิกส์ (207xxx), สถิติ (208xxx) (เป้าหมาย 25 นก.)',
    'หมวดวิชาเอกบังคับ': 'วิชาบังคับของภาคคอมพิวเตอร์ เช่น 204111, 204212, 204255, 204321, 204361 เป็นต้น (เป้าหมาย 35 นก.)',
    'หมวดวิชาเอกเลือก': 'วิชาเลือกเฉพาะกลุ่มของภาคคอมพิวเตอร์ (รหัส 204xxx ระดับสูง) เช่น AI, Web, Cloud, Game, Data (เป้าหมาย 33 หรือ 36 นก.)',
    'หมวดวิชาเลือกเสรี': 'วิชาใดๆ ที่เปิดสอนในมหาวิทยาลัยเชียงใหม่ หรือวิชานอกคณะที่สนใจ (เป้าหมาย 6 นก.)'
};

function updateCategoryHint() {
    const sel = document.getElementById('category');
    const hintText = document.getElementById('categoryHintText');
    if (sel && hintText) {
        hintText.textContent = categoryDescriptions[sel.value] || 'เลือกหมวดหมู่วิชาให้ตรงกับโครงสร้างหลักสูตร';
    }
}

function filterCourseList() {
    const input = document.getElementById('filterInput');
    const filter = (input ? input.value : '').toLowerCase().trim();
    const rows = document.querySelectorAll('#coursesTable tbody tr.course-row');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    updateCategoryHint();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
