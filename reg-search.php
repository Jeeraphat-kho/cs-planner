<?php
/**
 * CMU Computer Science Study Planner
 * หน้าค้นหากระบวนวิชาที่เปิดสอนจากสำนักทะเบียน มช. (REG CMU Live Search)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$loggedIn = isLoggedIn();
$currentUser = getCurrentUser();

// ดึงสถิติจำนวนแคชที่บันทึกไว้ในระบบ
$cachedCount = 0;
try {
    $pdo = Database::getConnection();
    $cachedCount = (int)$pdo->query("SELECT COUNT(*) FROM reg_search_cache WHERE expires_at > NOW()")->fetchColumn();
} catch (Exception $e) {
    $cachedCount = 0;
}

require_once __DIR__ . '/includes/header.php';
?>

<main class="container-xl py-4 flex-grow-1">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item">
                <a href="index.php" class="text-decoration-none text-muted">
                    <i class="bi bi-house-door me-1"></i>หน้าหลัก (แผนการเรียน)
                </a>
            </li>
            <li class="breadcrumb-item active text-primary fw-medium" aria-current="page">
                ค้นหากระบวนวิชาที่เปิดสอนจาก REG CMU
            </li>
        </ol>
    </nav>

    <!-- Header Banner -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" style="background: linear-gradient(135deg, #3b0764 0%, #682c91 50%, #1e1b4b 100%);">
        <div class="card-body p-4 p-md-5 text-white position-relative">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                        <span class="badge bg-white bg-opacity-20 text-dark border border-white border-opacity-25 px-3 py-1.5 rounded-pill">
                            <i class="bi bi-broadcast me-1"></i> REG CMU Live Course Search
                        </span>
                        <span class="badge bg-success bg-opacity-25 text-white border border-success border-opacity-25 px-3 py-1.5 rounded-pill">
                            <i class="bi bi-shield-check me-1"></i> ปลอดภัย & แคชอัจฉริยะ (Smart Cache)
                        </span>
                    </div>

                    <h1 class="h2 fw-bold text-white mb-2">
                        ค้นหากระบวนวิชาที่เปิดสอนจากสำนักทะเบียน
                    </h1>
                    <p class="text-white text-opacity-90 mb-3 fs-6" style="max-width: 680px;">
                        ค้นหาข้อมูลกระบวนวิชา ตารางเรียน ตารางสอบ ห้องเรียน และอาจารย์ผู้สอนแบบสดๆ ส่งตรงจากสำนักทะเบียนและประมวลผล มหาวิทยาลัยเชียงใหม่ พร้อมนำเข้าสู่แผนการเรียนของคุณได้ในคลิกเดียว
                    </p>

                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2 bg-black bg-opacity-25 px-3 py-1.5 rounded-pill text-white small">
                            <i class="bi bi-lightning-charge-fill text-warning"></i>
                            <span>แคชในระบบ: <strong><?= number_format($cachedCount) ?></strong> ผลการค้นหา</span>
                        </div>
                        <a href="https://www1.reg.cmu.ac.th/registrationoffice/searchcourse.php" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-light rounded-pill px-3">
                            <i class="bi bi-box-arrow-up-right me-1"></i> เว็บไซต์สำนักทะเบียน มช.
                        </a>
                    </div>
                </div>

                <div class="col-lg-4 d-none d-lg-flex justify-content-end">
                    <div class="text-center bg-white bg-opacity-10 p-4 rounded-4 border border-white border-opacity-20 backdrop-blur" style="max-width: 280px;">
                        <i class="bi bi-database-check display-4 text-warning mb-2 d-block"></i>
                        <div class="fw-bold fs-6">ปลอดภัย & รวดเร็ว</div>
                        <small class="opacity-75 d-block mt-1">มี Rate Limiter ป้องกันเซิร์ฟเวอร์ มช. ล่ม พร้อมระบบแคชข้อมูล 24 ชม.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Security & Best Practice Notice Banner -->
    <div class="alert alert-light border border-primary-subtle shadow-sm rounded-4 p-3 mb-4">
        <div class="d-flex align-items-start gap-3">
            <div class="fs-4 text-primary mt-0.5"><i class="bi bi-info-circle-fill"></i></div>
            <div class="small text-secondary flex-grow-1">
                <strong class="text-dark">ข้อควรรู้และนโยบายความปลอดภัย:</strong>
                ระบบมีการป้องกัน <strong>SSRF</strong>, ตรวจสอบความถูกต้องของ Input, จำกัดความถี่การค้นหา (Rate Limiting) และแคชข้อมูลผลลัพธ์ไว้ 24 ชั่วโมง เพื่อความรวดเร็วและไม่สร้างภาระให้แก่เซิร์ฟเวอร์สำนักทะเบียนฯ ข้อมูลที่ค้นพบสามารถกด <em>"นำเข้าสู่ระบบ / เพิ่มเข้าแผนการเรียน"</em> ได้ทันที
            </div>
        </div>
    </div>

    <!-- Search Form Card -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <form id="regSearchForm" onsubmit="event.preventDefault(); performRegSearch();">
            <div class="row g-3">
                <!-- 1. ภาคการศึกษา (Term) -->
                <div class="col-md-3">
                    <label for="selectTerm" class="form-label fw-semibold small text-dark">
                        <i class="bi bi-calendar-event text-primary me-1"></i> ภาคการศึกษา (Semester)
                    </label>
                    <select class="form-select rounded-3 py-2" id="selectTerm">
                        <option value="2/2569" selected>2/2569 (ภาคการศึกษาที่ 2/2569)</option>
                        <option value="1/2569">1/2569 (ภาคการศึกษาที่ 1/2569)</option>
                        <option value="3/2568">3/2568 (ภาคฤดูร้อน/2568)</option>
                        <option value="2/2568H">2/2568 (ภาคการศึกษาที่ 2/2568)</option>
                        <option value="1/2568H">1/2568 (ภาคการศึกษาที่ 1/2568)</option>
                        <option value="2/2567H">2/2567 (ภาคการศึกษาที่ 2/2567 - มีข้อมูลครบ)</option>
                        <option value="1/2567H">1/2567 (ภาคการศึกษาที่ 1/2567 - มีข้อมูลครบ)</option>
                        <option value="3/2567H">3/2567 (ภาคฤดูร้อน/2567)</option>
                        <option value="2/2566H">2/2566 (ภาคการศึกษาที่ 2/2566)</option>
                        <option value="1/2566H">1/2566 (ภาคการศึกษาที่ 1/2566)</option>
                    </select>
                </div>

                <!-- 2. ประเภทการค้นหา (Search Type) -->
                <div class="col-md-3">
                    <label for="selectType" class="form-label fw-semibold small text-dark">
                        <i class="bi bi-funnel text-primary me-1"></i> รูปแบบการค้นหา
                    </label>
                    <select class="form-select rounded-3 py-2" id="selectType" onchange="updatePlaceholder()">
                        <option value="code" selected>รหัสกระบวนวิชา (6 หลัก)</option>
                        <option value="group">รหัสกลุ่มวิชา (3-4 ตัวหน้า)</option>
                        <option value="title">ชื่อกระบวนวิชา (Course Title)</option>
                    </select>
                </div>

                <!-- 3. คำค้นหา (Query Input) -->
                <div class="col-md-4">
                    <label for="inputQuery" class="form-label fw-semibold small text-dark">
                        <i class="bi bi-search text-primary me-1"></i> คำค้นหา (Search Query)
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control rounded-start-3 py-2" id="inputQuery" placeholder="เช่น 204100 หรือ 204111" required autocomplete="off">
                        <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()" title="ล้างคำค้นหา">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <!-- 4. ปุ่มค้นหา -->
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" id="btnSubmitSearch" class="btn btn-primary w-100 rounded-3 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                        <i class="bi bi-search"></i>
                        <span>ค้นหาวิชา</span>
                    </button>
                </div>
            </div>

            <!-- Quick Search Tags -->
            <div class="mt-3 pt-3 border-top d-flex align-items-center gap-2 flex-wrap">
                <span class="small text-muted fw-medium"><i class="bi bi-tag-fill me-1"></i>ค้นหาด่วน:</span>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-0.5" onclick="quickSearch('group', '204')">
                    204 (วิทยาการคอมฯ)
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-0.5" onclick="quickSearch('group', '206')">
                    206 (คณิตศาสตร์)
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-0.5" onclick="quickSearch('group', '208')">
                    208 (สถิติ)
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-2.5 py-0.5" onclick="quickSearch('group', '001')">
                    001 (ภาษาอังกฤษ)
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-0.5" onclick="quickSearch('code', '204100')">
                    204100 (AI Essentials)
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-0.5" onclick="quickSearch('code', '204111')">
                    204111 (CS I)
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2.5 py-0.5" onclick="quickSearch('title', 'PROGRAMMING')">
                    PROGRAMMING
                </button>
            </div>
        </form>
    </div>

    <!-- Status & Results Container -->
    <div id="resultsWrapper">
        <!-- Initial Empty State -->
        <div id="initialState" class="card border-0 shadow-sm rounded-4 p-5 text-center my-4">
            <div class="my-3">
                <div class="brand-icon-wrapper mx-auto mb-3 bg-white p-2 shadow-sm" style="width: 76px; height: 76px; border-radius: 20px;">
                    <img src="CSCMU-LOGO.png" alt="CS CMU Logo" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <h4 class="fw-bold text-dark">พร้อมค้นหากระบวนวิชาจากสำนักทะเบียน มช.</h4>
                <p class="text-muted mx-auto" style="max-width: 550px;">
                    เลือกภาคการศึกษาและป้อนรหัสวิชา (เช่น <code>204100</code>) หรือคลิกแท็กค้นหาด่วนด้านบนเพื่อดูรายวิชาและเวลาเรียนที่เปิดสอน
                </p>
                <!-- <div class="d-inline-flex gap-2 p-2 px-3 bg-light rounded-pill border small text-secondary">
                    <i class="bi bi-lightbulb text-warning"></i>
                    <span>แนะนำ: เลือกภาคการศึกษา <strong>2/2567</strong> เพื่อทดสอบผลลัพธ์ที่มีตารางเรียนและ Section จริงครบถ้วน</span>
                </div> -->
            </div>
        </div>

        <!-- Loading State -->
        <div id="loadingState" class="card border-0 shadow-sm rounded-4 p-5 text-center my-4 d-none">
            <div class="my-4">
                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="fw-bold text-dark mb-1">กำลังติดต่อระบบสำนักทะเบียน มช...</h5>
                <p class="text-muted small mb-0">ระบบกำลังส่งคำขอและแยกวิเคราะห์ข้อมูลตารางเรียน กรุณารอสักครู่</p>
            </div>
        </div>

        <!-- Error State -->
        <div id="errorState" class="alert alert-danger border-0 shadow-sm rounded-4 p-4 my-4 d-none">
            <div class="d-flex align-items-start gap-3">
                <div class="fs-2 text-danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div>
                    <h5 class="fw-bold mb-1">ไม่สามารถค้นหาข้อมูลได้</h5>
                    <div id="errorMessage" class="text-secondary mb-3">เกิดข้อผิดพลาดในการเชื่อมต่อ</div>
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="performRegSearch()">
                        <i class="bi bi-arrow-clockwise me-1"></i> ลองใหม่อีกครั้ง
                    </button>
                </div>
            </div>
        </div>

        <!-- Result Content -->
        <div id="resultsContent" class="d-none">
            <!-- Results Header Bar -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3 px-1">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-check2-circle text-success me-1"></i> ผลการค้นหา
                    </h5>
                    <span class="badge bg-primary rounded-pill px-2.5 py-1" id="badgeResultCount">0 วิชา</span>
                    <span id="badgeCacheStatus" class="badge bg-secondary rounded-pill px-2.5 py-1"></span>
                </div>
                <div class="text-muted small" id="searchMetaInfo"></div>
            </div>

            <!-- List of Courses -->
            <div id="coursesList" class="d-flex flex-column gap-4"></div>
        </div>
    </div>

</main>

<!-- Modal นำเข้ากระบวนวิชาสู่แผนการเรียน -->
<div class="modal fade" id="importCourseModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header border-bottom py-3" style="background: linear-gradient(135deg, #682c91 0%, #4c1d95 100%);">
                <h5 class="modal-title text-white fw-bold d-flex align-items-center gap-2" id="importModalLabel">
                    <i class="bi bi-cloud-arrow-down-fill text-warning"></i> นำเข้ารายวิชาสู่ระบบ
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="importCourseForm">
                    <input type="hidden" id="importCourseCode">
                    <input type="hidden" id="importCourseName">
                    <input type="hidden" id="importCredits">
                    <input type="hidden" id="importSection">
                    <input type="hidden" id="importClassTime">
                    <input type="hidden" id="importInstructor">

                    <!-- ข้อมูลวิชาสรุป -->
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-primary rounded-pill mb-1" id="previewCourseCode"></span>
                                <h6 class="fw-bold text-dark mb-1" id="previewCourseName"></h6>
                            </div>
                            <span class="badge bg-secondary-subtle text-dark border" id="previewCredits"></span>
                        </div>
                        <div class="small text-muted mt-2 pt-2 border-top">
                            <div><i class="bi bi-clock me-1"></i>ตอนเรียน/เวลา: <strong class="text-dark" id="previewSchedule"></strong></div>
                            <div><i class="bi bi-person me-1"></i>อาจารย์: <strong class="text-dark" id="previewInstructor"></strong></div>
                        </div>
                    </div>

                    <!-- หมวดหมู่วิชา -->
                    <div class="mb-3">
                        <label for="importCategory" class="form-label fw-semibold small text-dark">
                            หมวดหมู่วิชาในหลักสูตร วท.บ. คอมพิวเตอร์
                        </label>
                        <select class="form-select rounded-3" id="importCategory" required>
                            <option value="หมวดวิชาเอกบังคับ">หมวดวิชาเอกบังคับ (Major Compulsory)</option>
                            <option value="หมวดวิชาเอกเลือก">หมวดวิชาเอกเลือก (Major Elective)</option>
                            <option value="หมวดวิชาแกน">หมวดวิชาแกน (Core Courses)</option>
                            <option value="หมวดวิชาศึกษาทั่วไป">หมวดวิชาศึกษาทั่วไป (General Education)</option>
                            <option value="หมวดวิชาเลือกเสรี">หมวดวิชาเลือกเสรี (Free Elective)</option>
                        </select>
                    </div>

                    <!-- เลือกว่าจะเพิ่มเข้าแผนการเรียนด้วยหรือไม่ (ถ้าล็อกอิน) -->
                    <?php if ($loggedIn): ?>
                        <div class="card border border-primary-subtle bg-primary bg-opacity-10 rounded-3 p-3 mb-3">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="switchAddToPlan" checked onchange="togglePlanSelectors()">
                                <label class="form-check-label fw-bold text-primary small" for="switchAddToPlan">
                                    <i class="bi bi-calendar-plus me-1"></i> เพิ่มวิชานี้เข้าแผนการเรียนของคุณทันที
                                </label>
                            </div>
                            <div id="planSelectorsContainer" class="row g-2 mt-1">
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1">ชั้นปีที่เรียน</label>
                                    <select class="form-select form-select-sm rounded-3" id="importPlanYear">
                                        <option value="1">ปี 1 (Freshman)</option>
                                        <option value="2">ปี 2 (Sophomore)</option>
                                        <option value="3">ปี 3 (Junior)</option>
                                        <option value="4">ปี 4 (Senior)</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted mb-1">ภาคการศึกษา</label>
                                    <select class="form-select form-select-sm rounded-3" id="importPlanSemester">
                                        <option value="1">เทอม 1 (ภาคเรียนที่ 1)</option>
                                        <option value="2">เทอม 2 (ภาคเรียนที่ 2)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 px-3 small rounded-3 mb-3">
                            <i class="bi bi-info-circle me-1"></i> คุณยังไม่ได้เข้าสู่ระบบ รายวิชานี้จะถูกบันทึกเข้าสู่ <strong>คลังวิชาส่วนกลาง</strong> เพื่อให้ทุกคนค้นหาได้ (หากต้องการเพิ่มเข้าแผนการเรียนส่วนบุคคล กรุณา <a href="login.php" class="alert-link">เข้าสู่ระบบ</a> ก่อน)
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="button" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnConfirmImport" onclick="submitImportCourse()">
                            <i class="bi bi-check-lg me-1"></i> บันทึกเข้าระบบ
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="appToast" class="toast align-items-center text-white bg-dark border-0 rounded-4 shadow" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2 py-3" id="toastMessage">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <span>ข้อความ</span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
// จัดการ Placeholder อัตโนมัติตามประเภทการค้นหา
function updatePlaceholder() {
    const type = document.getElementById('selectType').value;
    const input = document.getElementById('inputQuery');
    if (type === 'code') {
        input.placeholder = 'ระบุรหัสวิชา 6 หลัก เช่น 204100 หรือ 204111';
        input.maxLength = 6;
    } else if (type === 'group') {
        input.placeholder = 'ระบุรหัส 3-4 ตัวแรก เช่น 204 หรือ 206';
        input.maxLength = 4;
    } else {
        input.placeholder = 'ระบุชื่อวิชา เช่น INTRO หรือ PROGRAMMING';
        input.maxLength = 40;
    }
}

function clearSearch() {
    document.getElementById('inputQuery').value = '';
    document.getElementById('inputQuery').focus();
}

function quickSearch(type, query) {
    document.getElementById('selectType').value = type;
    updatePlaceholder();
    document.getElementById('inputQuery').value = query;
    performRegSearch();
}

function togglePlanSelectors() {
    const sw = document.getElementById('switchAddToPlan');
    const container = document.getElementById('planSelectorsContainer');
    if (container) {
        container.style.display = sw && sw.checked ? 'flex' : 'none';
    }
}

function showToast(msg, isSuccess = true) {
    const toastEl = document.getElementById('appToast');
    const toastMsg = document.getElementById('toastMessage');
    toastEl.className = `toast align-items-center text-white ${isSuccess ? 'bg-success' : 'bg-danger'} border-0 rounded-4 shadow`;
    toastMsg.innerHTML = `<i class="bi ${isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} fs-5"></i> <span>${msg}</span>`;
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
}

// ฟังก์ชันค้นหากระบวนวิชาจาก REG API
async function performRegSearch() {
    const term = document.getElementById('selectTerm').value;
    const type = document.getElementById('selectType').value;
    const q = document.getElementById('inputQuery').value.trim();

    if (!q) {
        showToast('กรุณาระบุคำค้นหากระบวนวิชา', false);
        document.getElementById('inputQuery').focus();
        return;
    }

    // จัดการ UI States
    document.getElementById('initialState').classList.add('d-none');
    document.getElementById('errorState').classList.add('d-none');
    document.getElementById('resultsContent').classList.add('d-none');
    document.getElementById('loadingState').classList.remove('d-none');

    const btn = document.getElementById('btnSubmitSearch');
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> กำลังค้นหา...`;

    try {
        const url = `api/reg-courses.php?term=${encodeURIComponent(term)}&type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`;
        const res = await fetch(url);
        const data = await res.json();

        document.getElementById('loadingState').classList.add('d-none');
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-search"></i> <span>ค้นหาวิชา</span>`;

        if (!data.success) {
            document.getElementById('errorState').classList.remove('d-none');
            document.getElementById('errorMessage').textContent = data.message || 'ไม่สามารถดึงข้อมูลได้';
            return;
        }

        renderResults(data);
    } catch (err) {
        document.getElementById('loadingState').classList.add('d-none');
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-search"></i> <span>ค้นหาวิชา</span>`;

        document.getElementById('errorState').classList.remove('d-none');
        document.getElementById('errorMessage').textContent = 'เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย หรือเซิร์ฟเวอร์สำนักทะเบียนฯ ไม่ตอบสนอง';
    }
}

// เรนเดอร์การ์ดผลลัพธ์
function renderResults(data) {
    const listContainer = document.getElementById('coursesList');
    listContainer.innerHTML = '';

    const count = data.courses_count || 0;
    document.getElementById('badgeResultCount').textContent = `${count} รายวิชา`;

    // แคชสเตตัส
    const cacheBadge = document.getElementById('badgeCacheStatus');
    if (data.from_cache) {
        cacheBadge.className = 'badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1';
        cacheBadge.innerHTML = `<i class="bi bi-lightning-charge-fill me-1"></i> โหลดจาก Local Cache (${data.cached_at || 'บันทึกแล้ว'})`;
    } else {
        cacheBadge.className = 'badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-2.5 py-1';
        cacheBadge.innerHTML = `<i class="bi bi-globe me-1"></i> ดึงสดจากสำนักทะเบียน มช. (บันทึกแคชแล้ว)`;
    }

    document.getElementById('searchMetaInfo').innerHTML = `ภาคการศึกษา: <strong class="text-dark">${escapeHtml(data.term)}</strong> | คำค้นหา: <strong class="text-dark">${escapeHtml(data.query)}</strong>`;

    if (count === 0) {
        listContainer.innerHTML = `
            <div class="card border-0 shadow-sm rounded-4 p-5 text-center">
                <div class="my-3 text-muted">
                    <i class="bi bi-search display-5 text-secondary d-block mb-3"></i>
                    <h5 class="fw-bold text-dark">ไม่พบกระบวนวิชาที่เปิดสอนตามเงื่อนไข</h5>
                    <p class="small mb-0">ในภาคการศึกษา ${escapeHtml(data.term)} อาจยังไม่มีการเปิดสอนวิชานี้ หรือลองเปลี่ยนคำค้นหาเป็นรหัสอื่น</p>
                </div>
            </div>
        `;
        document.getElementById('resultsContent').classList.remove('d-none');
        return;
    }

    data.data.forEach((course) => {
        const card = createCourseCard(course);
        listContainer.appendChild(card);
    });

    document.getElementById('resultsContent').classList.remove('d-none');
}

// สร้างการ์ดแสดงรายวิชาและตาราง Section
function createCourseCard(c) {
    const div = document.createElement('div');
    div.className = 'card border-0 shadow-sm rounded-4 overflow-hidden';

    // คำนวณหน่วยกิตรวม
    let totalCredits = 3;
    if (c.sections && c.sections.length > 0) {
        const s0 = c.sections[0];
        const lCredits = parseInt(s0.credit_lec, 10) || 0;
        const bCredits = parseInt(s0.credit_lab, 10) || 0;
        totalCredits = (lCredits + bCredits) > 0 ? (lCredits + bCredits) : 3;
    }

    let examBadge = '';
    if (c.exam_info) {
        examBadge = `<div class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill py-1 px-2.5 small"><i class="bi bi-calendar-check me-1"></i>${escapeHtml(c.exam_info)}</div>`;
    }

    let tqfBtn = '';
    if (c.tqf_link) {
        tqfBtn = `<a href="${escapeHtml(c.tqf_link)}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1"><i class="bi bi-book me-1"></i>รายละเอียด TQF (MIS)</a>`;
    }

    let rowsHtml = '';
    if (c.sections && c.sections.length > 0) {
        c.sections.forEach(sec => {
            const statusBadge = sec.status === 'O' 
                ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">เปิดรับ (Open)</span>'
                : `<span class="badge bg-secondary-subtle text-secondary border px-2 py-1">${escapeHtml(sec.status || 'ปิด')}</span>`;

            const secDisplay = `LEC: <strong>${escapeHtml(sec.sec_lec || '-')}</strong> / LAB: <strong>${escapeHtml(sec.sec_lab || '-')}</strong>`;
            const scheduleDisplay = `${escapeHtml(sec.day || '-')} ${escapeHtml(sec.time || '')}`.trim();
            const seatsDisplay = sec.total_seats ? `${escapeHtml(sec.enrolled || '0')} / ${escapeHtml(sec.total_seats)}` : '-';

            // เตรียมข้อมูลสำหรับ Import Modal
            const courseDataStr = JSON.stringify({
                code: c.course_code,
                name: c.course_name,
                credits: totalCredits,
                section: `LEC ${sec.sec_lec} / LAB ${sec.sec_lab}`,
                time: scheduleDisplay,
                instructor: sec.instructor || ''
            }).replace(/"/g, '&quot;');

            rowsHtml += `
                <tr>
                    <td class="text-center align-middle">${statusBadge}</td>
                    <td class="align-middle">${secDisplay}</td>
                    <td class="align-middle fw-medium">${escapeHtml(scheduleDisplay || 'TBA')}</td>
                    <td class="align-middle"><i class="bi bi-geo-alt text-danger me-1"></i>${escapeHtml(sec.room || 'TBA')}</td>
                    <td class="align-middle"><i class="bi bi-person-badge text-primary me-1"></i>${escapeHtml(sec.instructor || 'ยังไม่ระบุ')}</td>
                    <td class="align-middle text-center small text-muted">${seatsDisplay}</td>
                    <td class="align-middle text-end">
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1 shadow-sm" onclick="openImportModal(${courseDataStr})">
                            <i class="bi bi-plus-circle"></i> นำเข้าระบบ
                        </button>
                    </td>
                </tr>
            `;
        });
    } else {
        rowsHtml = `<tr><td colspan="7" class="text-center text-muted py-3">ไม่พบข้อมูล Section สำหรับวิชานี้</td></tr>`;
    }

    div.innerHTML = `
        <div class="card-header bg-white border-bottom p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span class="badge bg-primary fs-6 px-3 py-1 rounded-pill fw-bold">${escapeHtml(c.course_code)}</span>
                        <span class="badge bg-light text-dark border rounded-pill">${totalCredits} หน่วยกิต</span>
                        <span class="badge bg-light text-dark border rounded-pill">${c.sections.length} Sections</span>
                        ${examBadge}
                    </div>
                    <h5 class="fw-bold text-dark mb-0 mt-2">${escapeHtml(c.course_name || 'ไม่ระบุชื่อวิชา')}</h5>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    ${tqfBtn}
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small text-secondary">
                    <tr>
                        <th class="text-center" style="width: 110px;">สถานะ</th>
                        <th>Section</th>
                        <th>วันและเวลาเรียน</th>
                        <th>ห้องเรียน</th>
                        <th>อาจารย์ผู้สอน</th>
                        <th class="text-center">ลงแล้ว / รับ</th>
                        <th class="text-end" style="width: 140px;">การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="small">
                    ${rowsHtml}
                </tbody>
            </table>
        </div>
    `;

    return div;
}

// เปิด Modal นำเข้ารายวิชา
function openImportModal(c) {
    document.getElementById('importCourseCode').value = c.code;
    document.getElementById('importCourseName').value = c.name;
    document.getElementById('importCredits').value = c.credits;
    document.getElementById('importSection').value = c.section;
    document.getElementById('importClassTime').value = c.time;
    document.getElementById('importInstructor').value = c.instructor;

    document.getElementById('previewCourseCode').textContent = c.code;
    document.getElementById('previewCourseName').textContent = c.name;
    document.getElementById('previewCredits').textContent = `${c.credits} หน่วยกิต`;
    document.getElementById('previewSchedule').textContent = `${c.section} (${c.time})`;
    document.getElementById('previewInstructor').textContent = c.instructor || 'ไม่ระบุ';

    // หมวดหมู่เริ่มต้นตามรหัส
    const code = c.code || '';
    const sel = document.getElementById('importCategory');
    if (code.startsWith('204')) {
        const num = parseInt(code.substring(3), 10);
        if (num < 200 || ['204111', '204212', '204255', '204321', '204361'].includes(code)) {
            sel.value = 'หมวดวิชาเอกบังคับ';
        } else {
            sel.value = 'หมวดวิชาเอกเลือก';
        }
    } else if (['206', '207', '208'].some(p => code.startsWith(p))) {
        sel.value = 'หมวดวิชาแกน';
    } else if (code.startsWith('001')) {
        sel.value = 'หมวดวิชาศึกษาทั่วไป';
    } else {
        sel.value = 'หมวดวิชาเลือกเสรี';
    }

    const modal = new bootstrap.Modal(document.getElementById('importCourseModal'));
    modal.show();
}

// ยืนยันนำเข้าข้อมูล
async function submitImportCourse() {
    const btn = document.getElementById('btnConfirmImport');
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span> กำลังบันทึก...`;

    const code = document.getElementById('importCourseCode').value;
    const name = document.getElementById('importCourseName').value;
    const credits = document.getElementById('importCredits').value;
    const section = document.getElementById('importSection').value;
    const classTime = document.getElementById('importClassTime').value;
    const instructor = document.getElementById('importInstructor').value;
    const category = document.getElementById('importCategory').value;

    const switchAdd = document.getElementById('switchAddToPlan');
    const addToPlan = switchAdd ? switchAdd.checked : false;
    const planYear = addToPlan ? document.getElementById('importPlanYear').value : null;
    const planSemester = addToPlan ? document.getElementById('importPlanSemester').value : null;

    try {
        const res = await fetch('api/reg-courses.php?action=import', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                course_code: code,
                course_name: name,
                credits: parseInt(credits, 10) || 3,
                section: section,
                class_time: classTime,
                instructor: instructor,
                category: category,
                plan_year: planYear,
                plan_semester: planSemester
            })
        });

        const data = await res.json();
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-check-lg me-1"></i> บันทึกเข้าระบบ`;

        if (data.success) {
            const modalEl = document.getElementById('importCourseModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            showToast(data.message, true);
        } else {
            showToast(data.message || 'ไม่สามารถนำเข้าข้อมูลได้', false);
        }
    } catch (err) {
        btn.disabled = false;
        btn.innerHTML = `<i class="bi bi-check-lg me-1"></i> บันทึกเข้าระบบ`;
        showToast('เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', false);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
