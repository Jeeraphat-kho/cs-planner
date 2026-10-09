<?php
/**
 * CMU Computer Science Study Planner
 * Main Dashboard (index.php)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$loggedIn = isLoggedIn();
$currentUser = getCurrentUser();

require_once __DIR__ . '/includes/header.php';
?>

<main class="container-xl py-4 flex-grow-1">

    <?php if (!$loggedIn): ?>
        <!-- Guest Mode Alert Banner -->
        <div class="alert alert-warning border-0 rounded-4 shadow-sm p-3 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon amber shadow-sm flex-shrink-0" style="width: 44px; height: 44px; font-size: 1.25rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <div>
                    <div class="fw-bold text-dark">คุณกำลังใช้งานในโหมดผู้เยี่ยมชม (Guest Mode)</div>
                    <small class="text-secondary d-block">
                        คุณสามารถทดลองเลือกวิชาและประเมินเกรดได้เต็มรูปแบบ <strong>แต่คะแนนและแผนการเรียนจะไม่ถูกบันทึกลงฐานข้อมูล</strong> หากต้องการบันทึกถาวร กรุณาเข้าสู่ระบบ
                    </small>
                </div>
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <a href="login.php" class="btn btn-sm btn-outline-dark rounded-pill px-3 fw-medium">
                    <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
                </a>
                <a href="register.php" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-sm" style="background: linear-gradient(135deg, #682c91, #6366f1); border:none;">
                    <i class="bi bi-person-plus me-1"></i> สมัครสมาชิก
                </a>
            </div>
        </div>
    <?php elseif (isset($_GET['welcome'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 mb-4" role="alert">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-patch-check-fill fs-4 text-success"></i>
                <div>
                    <strong>ยินดีต้อนรับสู่ระบบ!</strong> บัญชีของคุณ (<?= htmlspecialchars($currentUser['student_id']) ?> - <?= htmlspecialchars($currentUser['full_name']) ?>) เข้าสู่ระบบเรียบร้อยแล้ว แผนการเรียนของคุณจะถูกบันทึกลงฐานข้อมูลโดยอัตโนมัติ
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Hero / Welcome Banner -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <span>แผนการเรียน 4 ปี (8 ภาคการศึกษา)</span>
                <span class="spinner-border spinner-border-sm text-primary" id="globalLoading" style="display:none;" role="status"></span>
            </h1>
            <p class="text-secondary mb-0 small">
                วางแผนรายวิชา ประเมินเกรดเฉลี่ย (GPA/GPAX) และติดตามหน่วยกิตสะสมตลอดหลักสูตร วท.บ. วิทยาการคอมพิวเตอร์ มหาวิทยาลัยเชียงใหม่
            </p>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <?php if ($loggedIn): ?>
                <span class="badge bg-purple-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill d-flex align-items-center gap-2">
                    <i class="bi bi-person-check-fill"></i> <?= htmlspecialchars($currentUser['full_name']) ?> (<?= htmlspecialchars($currentUser['student_id']) ?>)
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                    <i class="bi bi-cloud-check-fill me-1"></i> บันทึกลงฐานข้อมูล
                </span>
            <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill d-flex align-items-center gap-2">
                    <i class="bi bi-person-circle"></i> โหมดผู้เยี่ยมชม (Guest Mode)
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top KPI Summary Dashboard -->
    <div class="row g-3 mb-4">
        <!-- 1. GPAX สะสม -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card p-3 h-100 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div>
                        <div class="text-muted small fw-medium">เกรดเฉลี่ยสะสมโดยประมาณ (GPAX)</div>
                        <div id="kpiGpax" class="gpax-pill gpa-none my-1">กำลังคำนวณ...</div>
                    </div>
                    <div class="kpi-icon purple shadow-sm">
                        <i class="bi bi-award-fill"></i>
                    </div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                    <span id="kpiGpaxBadge" class="badge bg-secondary-subtle text-secondary border">ยังไม่ประเมิน</span>
                    <small id="kpiGpaxText" class="text-muted text-truncate" style="max-width: 150px;">-</small>
                </div>
            </div>
        </div>

        <!-- 2. หน่วยกิตรวม -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card p-3 h-100 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div>
                        <div class="text-muted small fw-medium">หน่วยกิตรวมที่วางแผนไว้</div>
                        <div class="h2 fw-bold text-dark my-1"><span id="kpiTotalCredits">0</span> <small class="fs-6 fw-normal text-muted">/ <span id="kpiTargetCredits">123</span> นก.</small></div>
                    </div>
                    <div class="kpi-icon emerald shadow-sm">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
                <div>
                    <div class="progress" style="height: 6px;">
                        <div id="kpiCreditsProgress" class="progress-bar bg-success" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small id="kpiCreditsProgressText" class="text-muted small">0% ของหลักสูตร</small>
                        <small class="text-muted small">เป้าหมาย <span id="kpiTargetCreditsText">123</span> นก.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. จำนวนวิชาทั้งหมด -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card p-3 h-100 d-flex flex-column justify-content-between">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div>
                        <div class="text-muted small fw-medium">จำนวนรายวิชาในแผน</div>
                        <div class="h2 fw-bold text-dark my-1"><span id="kpiTotalCourses">0</span> <small class="fs-6 fw-normal text-muted">วิชา</small></div>
                    </div>
                    <div class="kpi-icon indigo shadow-sm">
                        <i class="bi bi-book-half"></i>
                    </div>
                </div>
                <div class="pt-2 border-top d-flex justify-content-between align-items-center">
                    <span class="badge bg-light text-secondary border">ครอบคลุม 8 เทอม</span>
                    <small class="text-muted">ปี 1 - ปี 4</small>
                </div>
            </div>
        </div>

        <!-- 4. สรุปรายปี -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="kpi-card p-3 h-100 d-flex flex-column justify-content-between">
                <div class="text-muted small fw-medium mb-2 d-flex justify-content-between align-items-center">
                    <span>สรุปเกรดและหน่วยกิตรายปี</span>
                    <i class="bi bi-graph-up text-primary"></i>
                </div>
                <div class="d-grid gap-1">
                    <div class="d-flex justify-content-between align-items-center small py-1 border-bottom">
                        <span class="text-secondary">ปี 1: <strong id="year1Credits">0 นก.</strong></span>
                        <span id="year1Gpa" class="text-muted">ยังไม่มีเกรด</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center small py-1 border-bottom">
                        <span class="text-secondary">ปี 2: <strong id="year2Credits">0 นก.</strong></span>
                        <span id="year2Gpa" class="text-muted">ยังไม่มีเกรด</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center small py-1 border-bottom">
                        <span class="text-secondary">ปี 3: <strong id="year3Credits">0 นก.</strong></span>
                        <span id="year3Gpa" class="text-muted">ยังไม่มีเกรด</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center small py-1">
                        <span class="text-secondary">ปี 4: <strong id="year4Credits">0 นก.</strong></span>
                        <span id="year4Gpa" class="text-muted">ยังไม่มีเกรด</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Curriculum Structure & Credit Progress Tracker (สถานะหน่วยกิตตามโครงสร้างหลักสูตร) -->
    <div class="card border-0 rounded-4 shadow-sm mb-4 overflow-hidden curriculum-card">
        <!-- Header with Plan Switcher -->
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon purple shadow-sm flex-shrink-0" style="width: 42px; height: 42px; font-size: 1.2rem;">
                    <i class="bi bi-pie-chart-fill"></i>
                </div>
                <div>
                    <h2 class="h5 fw-bold text-dark mb-0">สถานะหน่วยกิตตามโครงสร้างหลักสูตร</h2>
                    <small class="text-secondary">วท.บ. วิทยาการคอมพิวเตอร์ มหาวิทยาลัยเชียงใหม่ (เกณฑ์ขั้นต่ำตามหลักสูตร มช.)</small>
                </div>
            </div>

            <!-- Plan Selector (สหกิจศึกษา 123 นก. / มุ่งเน้นโครงงาน 126 นก.) -->
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted fw-semibold d-none d-sm-inline">แผนการศึกษา:</span>
                <div class="btn-group btn-group-sm rounded-pill p-1 bg-light border shadow-sm" role="group" id="curriculumPlanSelector">
                    <input type="radio" class="btn-check" name="planTypeRadio" id="planCoopRadio" value="coop" checked autocomplete="off">
                    <label class="btn btn-sm rounded-pill px-3 fw-semibold" for="planCoopRadio">
                        <i class="bi bi-briefcase-fill me-1"></i> แผนสหกิจศึกษา (123 นก.)
                    </label>

                    <input type="radio" class="btn-check" name="planTypeRadio" id="planProjectRadio" value="project" autocomplete="off">
                    <label class="btn btn-sm rounded-pill px-3 fw-semibold" for="planProjectRadio">
                        <i class="bi bi-journal-code me-1"></i> แผนมุ่งเน้นโครงงาน (126 นก.)
                    </label>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <!-- Overall Curriculum Progress -->
            <div class="row g-3 align-items-center mb-4 pb-4 border-bottom">
                <div class="col-12 col-md-4">
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="display-6 fw-bold text-dark" id="curTotalEnrolled">0</span>
                        <span class="text-secondary fs-5 fw-normal">/ <span id="curTotalRequired">123</span> หน่วยกิต</span>
                    </div>
                    <div class="mt-2 d-flex align-items-center gap-2">
                        <span id="curTotalBadge" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1 rounded-pill fw-medium">
                            <i class="bi bi-hourglass-split me-1"></i> ขาดอีก 123 หน่วยกิต
                        </span>
                        <small class="text-muted" id="curTotalCountText">(0 วิชา)</small>
                    </div>
                </div>
                <div class="col-12 col-md-8">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold text-dark small">
                            <i class="bi bi-mortarboard-fill text-primary me-1"></i> ความคืบหน้าการสำเร็จการศึกษาตลอดหลักสูตร
                        </span>
                        <span class="fw-bold text-primary small" id="curTotalPercent">0%</span>
                    </div>
                    <div class="progress rounded-pill shadow-sm" style="height: 14px;">
                        <div id="curTotalProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-gradient-purple" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>

            <!-- 5 Category Progress Cards Grid -->
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-5 g-3">
                <!-- 1. หมวดวิชาศึกษาทั่วไป (24 หน่วยกิต) -->
                <div class="col">
                    <div class="curriculum-cat-box border-top border-4 border-info">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="cur-cat-icon bg-info-subtle text-info">
                                        <i class="bi bi-book-half"></i>
                                    </div>
                                    <span class="fw-bold text-dark small">วิชาศึกษาทั่วไป</span>
                                </div>
                                <span class="badge bg-light text-secondary border small">24 นก.</span>
                            </div>

                            <div class="my-2">
                                <div class="d-flex align-items-baseline gap-1">
                                    <span class="h4 fw-bold text-dark mb-0" id="curGeEnrolled">0</span>
                                    <span class="text-muted small">/ 24 นก.</span>
                                </div>
                                <small class="text-muted" id="curGeCountText">(0 วิชา)</small>
                            </div>

                            <div class="progress rounded-pill mt-2 mb-1" style="height: 8px;">
                                <div id="curGeProgressBar" class="progress-bar bg-info" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted fw-semibold" id="curGePercent">0%</small>
                            </div>
                        </div>

                        <div class="pt-2 border-top mt-2">
                            <span id="curGeBadge" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle w-100 py-1 text-truncate">
                                ขาดอีก 24 หน่วยกิต
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 2. หมวดวิชาแกน (25 หน่วยกิต) -->
                <div class="col">
                    <div class="curriculum-cat-box border-top border-4 border-warning">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="cur-cat-icon bg-warning-subtle text-warning">
                                        <i class="bi bi-cpu-fill"></i>
                                    </div>
                                    <span class="fw-bold text-dark small">วิชาแกน</span>
                                </div>
                                <span class="badge bg-light text-secondary border small">25 นก.</span>
                            </div>

                            <div class="my-2">
                                <div class="d-flex align-items-baseline gap-1">
                                    <span class="h4 fw-bold text-dark mb-0" id="curCoreEnrolled">0</span>
                                    <span class="text-muted small">/ 25 นก.</span>
                                </div>
                                <small class="text-muted" id="curCoreCountText">(0 วิชา)</small>
                            </div>

                            <div class="progress rounded-pill mt-2 mb-1" style="height: 8px;">
                                <div id="curCoreProgressBar" class="progress-bar bg-warning" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted fw-semibold" id="curCorePercent">0%</small>
                            </div>
                        </div>

                        <div class="pt-2 border-top mt-2">
                            <span id="curCoreBadge" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle w-100 py-1 text-truncate">
                                ขาดอีก 25 หน่วยกิต
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 3. หมวดวิชาเอกบังคับ (35 หน่วยกิต) -->
                <div class="col">
                    <div class="curriculum-cat-box border-top border-4 border-danger">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="cur-cat-icon bg-danger-subtle text-danger">
                                        <i class="bi bi-shield-check"></i>
                                    </div>
                                    <span class="fw-bold text-dark small">วิชาเอกบังคับ</span>
                                </div>
                                <span class="badge bg-light text-secondary border small">35 นก.</span>
                            </div>

                            <div class="my-2">
                                <div class="d-flex align-items-baseline gap-1">
                                    <span class="h4 fw-bold text-dark mb-0" id="curMajorReqEnrolled">0</span>
                                    <span class="text-muted small">/ 35 นก.</span>
                                </div>
                                <small class="text-muted" id="curMajorReqCountText">(0 วิชา)</small>
                            </div>

                            <div class="progress rounded-pill mt-2 mb-1" style="height: 8px;">
                                <div id="curMajorReqProgressBar" class="progress-bar bg-danger" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted fw-semibold" id="curMajorReqPercent">0%</small>
                            </div>
                        </div>

                        <div class="pt-2 border-top mt-2">
                            <span id="curMajorReqBadge" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle w-100 py-1 text-truncate">
                                ขาดอีก 35 หน่วยกิต
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 4. หมวดวิชาเอกเลือก (33 หรือ 36 หน่วยกิต) -->
                <div class="col">
                    <div class="curriculum-cat-box border-top border-4 border-success">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="cur-cat-icon bg-success-subtle text-success">
                                        <i class="bi bi-diagram-3-fill"></i>
                                    </div>
                                    <span class="fw-bold text-dark small">วิชาเอกเลือก</span>
                                </div>
                                <span class="badge bg-light text-secondary border small"><span id="curMajorElecTargetBadge">33</span> นก.</span>
                            </div>

                            <div class="my-2">
                                <div class="d-flex align-items-baseline gap-1">
                                    <span class="h4 fw-bold text-dark mb-0" id="curMajorElecEnrolled">0</span>
                                    <span class="text-muted small">/ <span id="curMajorElecTargetText">33</span> นก.</span>
                                </div>
                                <small class="text-muted" id="curMajorElecCountText">(0 วิชา)</small>
                            </div>

                            <div class="progress rounded-pill mt-2 mb-1" style="height: 8px;">
                                <div id="curMajorElecProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted fw-semibold" id="curMajorElecPercent">0%</small>
                            </div>
                        </div>

                        <div class="pt-2 border-top mt-2">
                            <span id="curMajorElecBadge" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle w-100 py-1 text-truncate">
                                ขาดอีก 33 หน่วยกิต
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 5. หมวดวิชาเลือกเสรี (6 หน่วยกิต) -->
                <div class="col">
                    <div class="curriculum-cat-box border-top border-4 border-purple">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="cur-cat-icon bg-purple-subtle text-purple">
                                        <i class="bi bi-stars"></i>
                                    </div>
                                    <span class="fw-bold text-dark small">วิชาเลือกเสรี</span>
                                </div>
                                <span class="badge bg-light text-secondary border small">6 นก.</span>
                            </div>

                            <div class="my-2">
                                <div class="d-flex align-items-baseline gap-1">
                                    <span class="h4 fw-bold text-dark mb-0" id="curFreeElecEnrolled">0</span>
                                    <span class="text-muted small">/ 6 นก.</span>
                                </div>
                                <small class="text-muted" id="curFreeElecCountText">(0 วิชา)</small>
                            </div>

                            <div class="progress rounded-pill mt-2 mb-1" style="height: 8px;">
                                <div id="curFreeElecProgressBar" class="progress-bar bg-purple" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="text-end">
                                <small class="text-muted fw-semibold" id="curFreeElecPercent">0%</small>
                            </div>
                        </div>

                        <div class="pt-2 border-top mt-2">
                            <span id="curFreeElecBadge" class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle w-100 py-1 text-truncate">
                                ขาดอีก 6 หน่วยกิต
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 8 Semesters by Year (Year 1 to Year 4) -->
    <?php
    $yearsInfo = [
        1 => ['title' => 'ชั้นปีที่ 1 (Freshman Year)', 'sub' => 'พื้นฐานวิทยาการคอมพิวเตอร์และคณิตศาสตร์', 'badge' => 'ปี 1'],
        2 => ['title' => 'ชั้นปีที่ 2 (Sophomore Year)', 'sub' => 'โครงสร้างข้อมูล สถาปัตยกรรมคอมพิวเตอร์ และเว็บแอปพลิเคชัน', 'badge' => 'ปี 2'],
        3 => ['title' => 'ชั้นปีที่ 3 (Junior Year)', 'sub' => 'ขั้นตอนวิธี ระบบปฏิบัติการ เครือข่าย และโครงงานเตรียมการ', 'badge' => 'ปี 3'],
        4 => ['title' => 'ชั้นปีที่ 4 (Senior Year)', 'sub' => 'วิชาเลือกเฉพาะทาง โครงงานวิจัย และสหกิจศึกษา/การฝึกงาน', 'badge' => 'ปี 4'],
    ];

    foreach ($yearsInfo as $year => $info):
    ?>
    <section class="year-section" id="yearSection_<?= $year ?>">
        <!-- Year Header -->
        <div class="year-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-primary px-3 py-2 rounded-pill fs-6"><?= $info['badge'] ?></span>
                <div>
                    <h2 class="h5 fw-bold mb-0 text-dark"><?= $info['title'] ?></h2>
                    <small class="text-muted"><?= $info['sub'] ?></small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapseYear_<?= $year ?>" aria-expanded="true" aria-controls="collapseYear_<?= $year ?>">
                    <i class="bi bi-chevron-up collapse-icon me-1"></i> ย่อ/ขยายปี <?= $year ?>
                </button>
            </div>
        </div>

        <!-- Year Content (Two Semesters) -->
        <div class="collapse show" id="collapseYear_<?= $year ?>">
            <div class="row g-3">
                <?php for ($sem = 1; $sem <= 2; $sem++): ?>
                <div class="col-12 col-lg-6">
                    <div class="semester-card">
                        <!-- Semester Header -->
                        <div class="semester-card-header d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-calendar3 text-primary"></i>
                                <span class="fw-bold text-dark">ภาคการศึกษาที่ <?= $sem ?></span>
                                <span class="badge bg-light text-dark border ms-1" id="semCount_<?= $year ?>_<?= $sem ?>">0 วิชา</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-light text-primary border" id="semCredits_<?= $year ?>_<?= $sem ?>">0 หน่วยกิต</span>
                                <span class="badge bg-secondary-subtle text-secondary border" id="semGpa_<?= $year ?>_<?= $sem ?>">GPA: ยังไม่มีข้อมูลเกรด</span>
                            </div>
                        </div>

                        <!-- Semester Body (Course List) -->
                        <div class="semester-card-body p-0" id="semContent_<?= $year ?>_<?= $sem ?>">
                            <div class="semester-empty py-4 text-center">
                                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                                <div class="small text-muted">กำลังโหลดข้อมูล...</div>
                            </div>
                        </div>

                        <!-- Semester Footer -->
                        <div class="semester-card-footer d-flex justify-content-between align-items-center">
                            <small class="text-muted">ปีการศึกษาที่ <?= $year ?> / เทอม <?= $sem ?></small>
                            <button type="button" class="btn btn-sm btn-cmu rounded-pill px-3 shadow-sm" onclick="App.openAddCourseModal(<?= $year ?>, <?= $sem ?>)">
                                <i class="bi bi-plus-lg me-1"></i> เพิ่มรายวิชา
                            </button>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
    <?php endforeach; ?>

</main>

<!-- ==============================================================
     Modal: ค้นหาและเลือกรายวิชา (Add Course Modal)
     ============================================================== -->
<div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <!-- Modal Header -->
            <div class="modal-header modal-header-cmu py-3 px-4">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="addCourseModalLabel">
                    <i class="bi bi-plus-circle me-1"></i> เพิ่มรายวิชาในแผนการเรียน
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-4">
                <!-- Search Input -->
                <div class="mb-3">
                    <div class="input-group shadow-sm rounded-pill overflow-hidden border">
                        <span class="input-group-text bg-white border-0 ps-3">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" id="courseSearchInput" class="form-control border-0 py-2 ps-2" placeholder="ค้นหาด้วยรหัสวิชา (เช่น 204111) หรือชื่อวิชา..." autocomplete="off">
                    </div>
                </div>

                <!-- Category Filters -->
                <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                    <span class="small text-muted fw-semibold me-1">หมวดหมู่วิชา:</span>
                    <button type="button" class="filter-pill active" data-category="all">ทั้งหมด</button>
                    <button type="button" class="filter-pill" data-category="หมวดวิชาศึกษาทั่วไป">ศึกษาทั่วไป</button>
                    <button type="button" class="filter-pill" data-category="หมวดวิชาแกน">วิชาแกน</button>
                    <button type="button" class="filter-pill" data-category="หมวดวิชาเอกบังคับ">วิชาเอกบังคับ</button>
                    <button type="button" class="filter-pill" data-category="หมวดวิชาเอกเลือก">วิชาเอกเลือก</button>
                    <button type="button" class="filter-pill" data-category="หมวดวิชาเลือกเสรี">เลือกเสรี</button>
                </div>

                <!-- Search Results List -->
                <div class="border rounded-3 course-modal-list" id="courseModalResults">
                    <div class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        กำลังโหลดข้อมูลรายวิชา...
                    </div>
                </div>

                <!-- Crowdsource helper prompt -->
                <div class="mt-3 p-3 bg-light rounded-3 border d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                    <div class="small text-muted">
                        <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                        <span>ไม่พบวิชาที่ต้องการ หรือรายวิชาไม่อัปเดต? ร่วมสร้างวิชาใหม่ลงฐานข้อมูล</span>
                    </div>
                    <a href="create-course.php" class="btn btn-sm btn-outline-primary rounded-pill px-3 text-nowrap flex-shrink-0">
                        <i class="bi bi-plus-circle me-1"></i> เพิ่มรายวิชาใหม่
                    </a>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer bg-light py-2 px-4 justify-content-between">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i> สามารถเพิ่มวิชาเดียวกันในต่างภาคเรียนได้ แต่ไม่สามารถเพิ่มซ้ำในเทอมเดียวกัน
                </small>
                <button type="button" class="btn btn-sm btn-secondary rounded-pill px-4" data-bs-dismiss="modal">
                    ปิด
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // ส่งสถานะการล็อกอินให้ JavaScript ทราบ
    window.isUserLoggedIn = <?= $loggedIn ? 'true' : 'false' ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
