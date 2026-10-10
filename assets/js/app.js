/**
 * CMU Computer Science Study Planner
 * Frontend Application Logic (Supports both Authenticated DB mode & Guest in-browser mode)
 */

const App = {
    planId: 1,
    isLoggedIn: false,
    currentPlanData: null,
    targetYear: 1,
    targetSemester: 1,
    availableCourses: [],
    currentCategoryFilter: 'all',
    searchDebounceTimer: null,

    // ค่าคงที่คะแนนเกรด
    gradePoints: {
        'A': 4.0,
        'B+': 3.5,
        'B': 3.0,
        'C+': 2.5,
        'C': 2.0,
        'D+': 1.5,
        'D': 1.0,
        'F': 0.0
    },

    // แผนการเรียนและเกณฑ์หน่วยกิตตามหลักสูตร (TQF)
    currentPlanType: 'coop',
    curriculumRequirements: {
        coop: {
            total: 123,
            name: 'แผนสหกิจศึกษา (123 นก.)',
            categories: {
                ge: { key: 'หมวดวิชาศึกษาทั่วไป', name: 'วิชาศึกษาทั่วไป', target: 24, idPrefix: 'Ge' },
                core: { key: 'หมวดวิชาแกน', name: 'วิชาแกน', target: 25, idPrefix: 'Core' },
                majorReq: { key: 'หมวดวิชาเอกบังคับ', name: 'วิชาเอกบังคับ', target: 35, idPrefix: 'MajorReq' },
                majorElec: { key: 'หมวดวิชาเอกเลือก', name: 'วิชาเอกเลือก', target: 33, idPrefix: 'MajorElec' },
                freeElec: { key: 'หมวดวิชาเลือกเสรี', name: 'วิชาเลือกเสรี', target: 6, idPrefix: 'FreeElec' }
            }
        },
        project: {
            total: 126,
            name: 'แผนมุ่งเน้นโครงงาน (126 นก.)',
            categories: {
                ge: { key: 'หมวดวิชาศึกษาทั่วไป', name: 'วิชาศึกษาทั่วไป', target: 24, idPrefix: 'Ge' },
                core: { key: 'หมวดวิชาแกน', name: 'วิชาแกน', target: 25, idPrefix: 'Core' },
                majorReq: { key: 'หมวดวิชาเอกบังคับ', name: 'วิชาเอกบังคับ', target: 35, idPrefix: 'MajorReq' },
                majorElec: { key: 'หมวดวิชาเอกเลือก', name: 'วิชาเอกเลือก', target: 36, idPrefix: 'MajorElec' },
                freeElec: { key: 'หมวดวิชาเลือกเสรี', name: 'วิชาเลือกเสรี', target: 6, idPrefix: 'FreeElec' }
            }
        }
    },

    init() {
        this.isLoggedIn = Boolean(window.isUserLoggedIn);
        this.currentPlanType = localStorage.getItem('cmu_cs_plan_type') || 'coop';
        const planRadio = document.querySelector(`input[name="planTypeRadio"][value="${this.currentPlanType}"]`);
        if (planRadio) planRadio.checked = true;

        this.bindEvents();
        this.fetchPlan();

        // หากมีพารามิเตอร์ ?search= ส่งมาจาก create-course.php หรือลิงก์อื่น ให้เปิด Modal ค้นหาทันที
        const urlParams = new URLSearchParams(window.location.search);
        const searchCode = urlParams.get('search');
        if (searchCode) {
            setTimeout(() => {
                this.openAddCourseModal(1, 1);
                const searchInput = document.getElementById('courseSearchInput');
                if (searchInput) {
                    searchInput.value = searchCode;
                    this.searchCourses(searchCode);
                }
            }, 500);
        }
    },

    bindEvents() {
        // Modal Search Input with Debounce
        const searchInput = document.getElementById('courseSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(this.searchDebounceTimer);
                this.searchDebounceTimer = setTimeout(() => {
                    this.searchCourses(e.target.value.trim());
                }, 250);
            });
        }

        // Category Filter Pills
        document.querySelectorAll('.filter-pill').forEach(pill => {
            pill.addEventListener('click', (e) => {
                document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
                e.target.classList.add('active');
                this.currentCategoryFilter = e.target.dataset.category || 'all';
                this.searchCourses(document.getElementById('courseSearchInput')?.value.trim() || '');
            });
        });

        // Curriculum Plan Type Switcher (สหกิจศึกษา 123 นก. / มุ่งเน้นโครงงาน 126 นก.)
        document.querySelectorAll('input[name="planTypeRadio"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.currentPlanType = e.target.value;
                localStorage.setItem('cmu_cs_plan_type', this.currentPlanType);
                this.refreshCurriculumProgress();
            });
        });

        // Expand / Collapse All
        document.getElementById('btnExpandAll')?.addEventListener('click', () => this.toggleAllAccordions(true));
        document.getElementById('btnCollapseAll')?.addEventListener('click', () => this.toggleAllAccordions(false));

        // Reset / Load Sample Plan Buttons
        document.getElementById('btnLoadSample')?.addEventListener('click', () => this.confirmLoadSample());
        document.getElementById('btnResetPlan')?.addEventListener('click', () => this.confirmResetPlan());
    },

    // -------------------------------------------------------------
    // ดึงข้อมูลแผนการเรียน (DB หากล็อกอิน, LocalStorage หากเป็น Guest)
    // -------------------------------------------------------------
    async fetchPlan() {
        if (!this.isLoggedIn) {
            this.loadGuestPlan();
            return;
        }

        try {
            this.setLoading(true);
            const response = await fetch('api/planned-courses.php');

            if (response.status === 401) {
                // หาก Session หมดอายุ สลับไปใช้โหมด Guest
                this.isLoggedIn = false;
                this.loadGuestPlan();
                return;
            }

            const data = await response.json();

            if (!data.success) {
                this.showToast(data.message || 'เกิดข้อผิดพลาดในการโหลดแผนการเรียน', 'danger');
                return;
            }

            this.currentPlanData = data;
            this.planId = data.plan_id;
            this.renderKPIs(data.summary);
            this.renderSemesters(data.semesters);
            this.renderCurriculumProgress(this.calculateCurriculumProgress(this.getAllPlannedCourses()));
        } catch (error) {
            console.error('Fetch Plan Error:', error);
            this.showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กำลังโหลดโหมดสำรอง', 'warning');
            this.loadGuestPlan();
        } finally {
            this.setLoading(false);
        }
    },

    // -------------------------------------------------------------
    // จัดการแผนการเรียนในโหมดผู้เยี่ยมชม (Guest Mode)
    // -------------------------------------------------------------
    loadGuestPlan() {
        this.setLoading(true);
        let guestCourses = [];
        const saved = localStorage.getItem('cmu_cs_guest_plan');

        if (saved) {
            try {
                guestCourses = JSON.parse(saved);
                guestCourses = this.normalizeGuestCourseData(guestCourses);
                this.saveGuestPlan(guestCourses);
            } catch (e) {
                guestCourses = this.getDefaultGuestCourses();
            }
        } else {
            guestCourses = this.getDefaultGuestCourses();
            this.saveGuestPlan(guestCourses);
        }

        this.calculateAndRenderGuestData(guestCourses);
        this.setLoading(false);
    },

    saveGuestPlan(courses) {
        localStorage.setItem('cmu_cs_guest_plan', JSON.stringify(courses));
    },

    normalizeGuestCourseData(courses) {
        const knownFixes = {
            '204111': { category: 'หมวดวิชาแกน', credits: 3, name: 'การเขียนโปรแกรมเบื้องต้น' },
            '204115': { category: 'หมวดวิชาแกน', credits: 3, name: 'หลักการเขียนโปรแกรมเชิงฟังก์ชัน' },
            '204255': { category: 'หมวดวิชาแกน', credits: 4, name: 'การเขียนโปรแกรมเชิงวัตถุและโครงสร้างข้อมูล' },
            '140104': { category: 'หมวดวิชาศึกษาทั่วไป', credits: 3, name: 'การเป็นพลเมือง' },
            '204232': { category: 'หมวดวิชาเอกบังคับ', credits: 3, name: 'เครือข่ายคอมพิวเตอร์และเกณฑ์วิธี' },
            '204306': { credits: 1 },
            '204497': { credits: 1 },
            '204496': { credits: 6 }
        };
        return (courses || []).map(c => {
            const fix = knownFixes[c.course_code];
            if (fix) {
                if (fix.category) c.category = fix.category;
                if (fix.credits) c.credits = fix.credits;
                if (fix.name) c.course_name = fix.name;
            }
            return c;
        });
    },

    getDefaultGuestCourses() {
        return [
            { id: 101, course_id: 1, course_code: '001101', course_name: 'ภาษาอังกฤษพื้นฐาน 1', credits: 3, section: '', class_time: '', instructor: '', category: 'หมวดวิชาศึกษาทั่วไป', year: 1, semester: 1, expected_grade: 'A' },
            { id: 102, course_id: 8, course_code: '206111', course_name: 'แคลคูลัส 1', credits: 3, section: '', class_time: '', instructor: '', category: 'หมวดวิชาแกน', year: 1, semester: 1, expected_grade: 'B+' },
            { id: 103, course_id: 3, course_code: '204111', course_name: 'การเขียนโปรแกรมเบื้องต้น', credits: 3, section: '', class_time: '', instructor: '', category: 'หมวดวิชาแกน', year: 1, semester: 1, expected_grade: 'A' },
            { id: 104, course_id: 2, course_code: '204100', course_name: 'เรื่องน่ารู้ทางปัญญาประดิษฐ์และดิจิทัล', credits: 3, section: '', class_time: '', instructor: '', category: 'หมวดวิชาศึกษาทั่วไป', year: 1, semester: 1, expected_grade: 'A' },
            { id: 105, course_id: 11, course_code: '140104', course_name: 'การเป็นพลเมือง', credits: 3, section: '', class_time: '', instructor: '', category: 'หมวดวิชาศึกษาทั่วไป', year: 1, semester: 1, expected_grade: 'B' },
            { id: 106, course_id: 12, course_code: '001102', course_name: 'ภาษาอังกฤษพื้นฐาน 2', credits: 3, section: '', class_time: '', instructor: '', category: 'หมวดวิชาศึกษาทั่วไป', year: 1, semester: 2, expected_grade: 'B+' },
            { id: 107, course_id: 13, course_code: '206183', course_name: 'โครงสร้างวิยุต', credits: 3, section: '', class_time: '', instructor: '', category: 'หมวดวิชาแกน', year: 1, semester: 2, expected_grade: 'B' },
            { id: 108, course_id: 4, course_code: '204115', course_name: 'หลักการเขียนโปรแกรมเชิงฟังก์ชัน', credits: 3, section: '', class_time: '', instructor: '', category: 'หมวดวิชาแกน', year: 1, semester: 2, expected_grade: 'A' },
            { id: 109, course_id: 7, course_code: '204255', course_name: 'การเขียนโปรแกรมเชิงวัตถุและโครงสร้างข้อมูล', credits: 4, section: '', class_time: '', instructor: '', category: 'หมวดวิชาแกน', year: 1, semester: 2, expected_grade: 'A' },
            { id: 110, course_id: 5, course_code: '204212', course_name: 'การพัฒนาเว็บแอปพลิเคชันแบบฟูลสแต็ก', credits: 3, section: '', class_time: '', instructor: '', category: 'หมวดวิชาเอกบังคับ', year: 2, semester: 1, expected_grade: 'A' },
            { id: 111, course_id: 6, course_code: '204231', course_name: 'การจัดระบบและสถาปัตยกรรมคอมพิวเตอร์', credits: 3, section: '', class_time: '', instructor: '', category: 'หมวดวิชาเอกบังคับ', year: 2, semester: 1, expected_grade: 'B+' },
            { id: 112, course_id: 9, course_code: '204232', course_name: 'เครือข่ายคอมพิวเตอร์และเกณฑ์วิธี', credits: 3, section: '', class_time: '', instructor: '', category: 'หมวดวิชาเอกบังคับ', year: 2, semester: 1, expected_grade: '' }
        ];
    },

    calculateAndRenderGuestData(coursesList) {
        const semestersData = {};
        for (let y = 1; y <= 4; y++) {
            for (let s = 1; s <= 2; s++) {
                semestersData[`${y}_${s}`] = {
                    year: y,
                    semester: s,
                    courses: [],
                    total_credits: 0,
                    graded_credits: 0,
                    total_points: 0.0,
                    gpa: null,
                    gpa_text: 'ยังไม่มีข้อมูลเกรด'
                };
            }
        }

        const yearStats = {};
        for (let y = 1; y <= 4; y++) {
            yearStats[y] = {
                total_credits: 0,
                graded_credits: 0,
                total_points: 0.0,
                gpa: null,
                gpa_text: 'ยังไม่มีข้อมูลเกรด',
                courses_count: 0
            };
        }

        let overallTotalCredits = 0;
        let overallGradedCredits = 0;
        let overallTotalPoints = 0.0;

        coursesList.forEach(c => {
            const y = Number(c.year);
            const s = Number(c.semester);
            const key = `${y}_${s}`;
            const credits = Number(c.credits) || 3;
            const grade = c.expected_grade ? c.expected_grade.trim() : '';
            const point = this.gradePoints[grade];

            if (semestersData[key]) {
                semestersData[key].courses.push(c);
                semestersData[key].total_credits += credits;

                yearStats[y].total_credits += credits;
                yearStats[y].courses_count += 1;
                overallTotalCredits += credits;

                if (point !== undefined) {
                    semestersData[key].graded_credits += credits;
                    semestersData[key].total_points += (credits * point);

                    yearStats[y].graded_credits += credits;
                    yearStats[y].total_points += (credits * point);

                    overallGradedCredits += credits;
                    overallTotalPoints += (credits * point);
                }
            }
        });

        // คำนวณ GPA ภาคการศึกษา
        Object.values(semestersData).forEach(sem => {
            if (sem.graded_credits > 0) {
                sem.gpa = Math.round((sem.total_points / sem.graded_credits) * 100) / 100;
                sem.gpa_text = sem.gpa.toFixed(2);
            }
        });

        // คำนวณ GPA รายปี
        Object.values(yearStats).forEach(yr => {
            if (yr.graded_credits > 0) {
                yr.gpa = Math.round((yr.total_points / yr.graded_credits) * 100) / 100;
                yr.gpa_text = yr.gpa.toFixed(2);
            }
        });

        // คำนวณ GPAX สะสม
        let gpax = null;
        let gpaxText = 'ยังไม่มีข้อมูลเกรด';
        if (overallGradedCredits > 0) {
            gpax = Math.round((overallTotalPoints / overallGradedCredits) * 100) / 100;
            gpaxText = gpax.toFixed(2);
        }

        const summary = {
            total_courses: coursesList.length,
            total_credits: overallTotalCredits,
            graded_credits: overallGradedCredits,
            gpax: gpax,
            gpax_text: gpaxText,
            year_stats: yearStats
        };

        this.currentPlanData = {
            success: true,
            is_guest: true,
            summary: summary,
            semesters: semestersData
        };

        this.renderKPIs(summary);
        this.renderSemesters(semestersData);
        this.renderCurriculumProgress(this.calculateCurriculumProgress(coursesList));
    },

    // -------------------------------------------------------------
    // แปลงชื่อหมวดหมู่และจัดการข้อมูลหลักสูตร (TQF Curriculum Tracker)
    // -------------------------------------------------------------
    normalizeCategory(cat) {
        if (!cat) return 'freeElec';
        const c = String(cat).trim();
        if (c.includes('ศึกษาทั่วไป')) return 'ge';
        if (c.includes('แกน')) return 'core';
        if (c.includes('เอกบังคับ')) return 'majorReq';
        if (c.includes('เอกเลือก')) return 'majorElec';
        return 'freeElec';
    },

    getAllPlannedCourses() {
        if (!this.currentPlanData) return [];
        if (this.currentPlanData.is_guest) {
            const saved = localStorage.getItem('cmu_cs_guest_plan');
            try { return JSON.parse(saved) || []; } catch (e) { return []; }
        }
        if (this.currentPlanData.semesters) {
            const list = [];
            Object.values(this.currentPlanData.semesters).forEach(sem => {
                if (sem.courses && Array.isArray(sem.courses)) {
                    list.push(...sem.courses);
                }
            });
            return list;
        }
        return [];
    },

    calculateCurriculumProgress(coursesList) {
        const planType = this.currentPlanType || 'coop';
        const req = this.curriculumRequirements[planType] || this.curriculumRequirements.coop;

        const stats = {
            ge: { enrolled: 0, count: 0, target: req.categories.ge.target },
            core: { enrolled: 0, count: 0, target: req.categories.core.target },
            majorReq: { enrolled: 0, count: 0, target: req.categories.majorReq.target },
            majorElec: { enrolled: 0, count: 0, target: req.categories.majorElec.target },
            freeElec: { enrolled: 0, count: 0, target: req.categories.freeElec.target }
        };

        let totalEnrolled = 0;
        let totalCount = 0;

        (coursesList || []).forEach(course => {
            const catGroup = this.normalizeCategory(course.category);
            const credits = Number(course.credits) || 0;
            if (stats[catGroup]) {
                stats[catGroup].enrolled += credits;
                stats[catGroup].count += 1;
            }
            totalEnrolled += credits;
            totalCount += 1;
        });

        return {
            planType: planType,
            totalRequired: req.total,
            totalEnrolled: totalEnrolled,
            totalRemaining: Math.max(0, req.total - totalEnrolled),
            totalCount: totalCount,
            stats: stats
        };
    },

    renderCurriculumProgress(progressData) {
        if (!progressData) return;

        // 1. สรุปภาพรวมตลอดหลักสูตร
        const totalEnrolledEl = document.getElementById('curTotalEnrolled');
        const totalRequiredEl = document.getElementById('curTotalRequired');
        const totalCountEl = document.getElementById('curTotalCountText');
        const totalPercentEl = document.getElementById('curTotalPercent');
        const totalProgressBar = document.getElementById('curTotalProgressBar');
        const totalBadge = document.getElementById('curTotalBadge');

        const overallPercent = Math.min(100, Math.round((progressData.totalEnrolled / progressData.totalRequired) * 100));

        if (totalEnrolledEl) totalEnrolledEl.textContent = progressData.totalEnrolled;
        if (totalRequiredEl) totalRequiredEl.textContent = progressData.totalRequired;
        if (totalCountEl) totalCountEl.textContent = `(${progressData.totalCount} วิชา)`;
        if (totalPercentEl) totalPercentEl.textContent = `${overallPercent}%`;
        if (totalProgressBar) {
            totalProgressBar.style.width = `${overallPercent}%`;
            totalProgressBar.setAttribute('aria-valuenow', overallPercent);
        }

        if (totalBadge) {
            if (progressData.totalRemaining > 0) {
                totalBadge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1 rounded-pill fw-medium';
                totalBadge.innerHTML = `<i class="bi bi-hourglass-split me-1"></i> ขาดอีก ${progressData.totalRemaining} หน่วยกิต`;
            } else {
                totalBadge.className = 'badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill fw-medium';
                const extra = progressData.totalEnrolled > progressData.totalRequired ? ` (+${progressData.totalEnrolled - progressData.totalRequired} นก.)` : '';
                totalBadge.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ครบตามเกณฑ์หลักสูตรแล้ว 🎓${extra}`;
            }
        }

        // 2. แสดงผลแยกตาม 5 หมวดหมู่วิชา
        const categoriesMap = [
            { key: 'ge', prefix: 'Ge' },
            { key: 'core', prefix: 'Core' },
            { key: 'majorReq', prefix: 'MajorReq' },
            { key: 'majorElec', prefix: 'MajorElec' },
            { key: 'freeElec', prefix: 'FreeElec' },
        ];

        categoriesMap.forEach(({ key, prefix }) => {
            const s = progressData.stats[key];
            if (!s) return;

            const enrolled = s.enrolled;
            const target = s.target;
            const remaining = Math.max(0, target - enrolled);
            const percent = Math.min(100, Math.round((enrolled / target) * 100));

            const enrolledEl = document.getElementById(`cur${prefix}Enrolled`);
            const countEl = document.getElementById(`cur${prefix}CountText`);
            const percentEl = document.getElementById(`cur${prefix}Percent`);
            const progressBar = document.getElementById(`cur${prefix}ProgressBar`);
            const badgeEl = document.getElementById(`cur${prefix}Badge`);

            if (enrolledEl) enrolledEl.textContent = enrolled;
            if (countEl) countEl.textContent = `(${s.count} วิชา)`;
            if (percentEl) percentEl.textContent = `${percent}%`;
            if (progressBar) {
                progressBar.style.width = `${percent}%`;
                progressBar.setAttribute('aria-valuenow', percent);
            }

            // พิเศษสำหรับวิชาเอกเลือก อัปเดตตัวเลขเป้าหมายตามแผน (33 หรือ 36)
            if (key === 'majorElec') {
                const badgeTarget = document.getElementById('curMajorElecTargetBadge');
                const textTarget = document.getElementById('curMajorElecTargetText');
                if (badgeTarget) badgeTarget.textContent = target;
                if (textTarget) textTarget.textContent = target;
            }

            if (badgeEl) {
                if (remaining > 0) {
                    badgeEl.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle w-100 py-1 text-truncate';
                    badgeEl.innerHTML = `<i class="bi bi-hourglass-split me-1"></i> ขาดอีก ${remaining} หน่วยกิต`;
                } else {
                    badgeEl.className = 'badge bg-success-subtle text-success border border-success-subtle w-100 py-1 text-truncate';
                    const extra = enrolled > target ? ` (+${enrolled - target})` : '';
                    badgeEl.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ครบตามเกณฑ์แล้ว ✅${extra}`;
                }
            }
        });
    },

    refreshCurriculumProgress() {
        const courses = this.getAllPlannedCourses();
        const progress = this.calculateCurriculumProgress(courses);
        this.renderCurriculumProgress(progress);

        if (this.currentPlanData && this.currentPlanData.summary) {
            this.renderKPIs(this.currentPlanData.summary);
        }
    },

    // -------------------------------------------------------------
    // แสดงผลสรุปภาพรวม (KPI Dashboard)
    // -------------------------------------------------------------
    renderKPIs(summary) {
        if (!summary) return;

        const gpaxEl = document.getElementById('kpiGpax');
        const gpaxTextEl = document.getElementById('kpiGpaxText');
        const gpaxBadgeEl = document.getElementById('kpiGpaxBadge');

        if (summary.gpax !== null) {
            const gpaxVal = parseFloat(summary.gpax);
            gpaxEl.textContent = gpaxVal.toFixed(2);
            gpaxEl.className = 'gpax-pill fw-bold';
            
            if (gpaxVal >= 3.50) {
                gpaxEl.classList.add('gpa-high');
                gpaxBadgeEl.className = 'badge bg-success-subtle text-success border border-success-subtle';
                gpaxBadgeEl.textContent = 'เกียรตินิยม / ยอดเยี่ยม';
            } else if (gpaxVal >= 3.00) {
                gpaxEl.classList.add('gpa-med');
                gpaxBadgeEl.className = 'badge bg-primary-subtle text-primary border border-primary-subtle';
                gpaxBadgeEl.textContent = 'ดีมาก';
            } else if (gpaxVal >= 2.00) {
                gpaxEl.classList.add('gpa-low');
                gpaxBadgeEl.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                gpaxBadgeEl.textContent = 'ผ่านเกณฑ์ปกติ';
            } else {
                gpaxEl.classList.add('text-danger');
                gpaxBadgeEl.className = 'badge bg-danger-subtle text-danger border border-danger-subtle';
                gpaxBadgeEl.textContent = 'วิกฤต / ต้องปรับปรุง';
            }
            gpaxTextEl.textContent = `คำนวณจาก ${summary.graded_credits} หน่วยกิตที่ประเมินแล้ว`;
        } else {
            gpaxEl.textContent = 'ยังไม่มีข้อมูลเกรด';
            gpaxEl.className = 'gpax-pill gpa-none';
            gpaxBadgeEl.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle';
            gpaxBadgeEl.textContent = 'ยังไม่ประเมิน';
            gpaxTextEl.textContent = 'เลือกเกรดที่คาดว่าจะได้รับเพื่อดูเกรดเฉลี่ย';
        }

        const totalCreditsEl = document.getElementById('kpiTotalCredits');
        const creditsProgressEl = document.getElementById('kpiCreditsProgress');
        const req = this.curriculumRequirements[this.currentPlanType || 'coop'] || this.curriculumRequirements.coop;
        const targetCredits = req.total;
        const currentCredits = summary.total_credits || 0;
        const percent = Math.min(Math.round((currentCredits / targetCredits) * 100), 100);

        if (totalCreditsEl) totalCreditsEl.textContent = currentCredits;
        const targetEl = document.getElementById('kpiTargetCredits');
        if (targetEl) targetEl.textContent = targetCredits;
        const targetTextEl = document.getElementById('kpiTargetCreditsText');
        if (targetTextEl) targetTextEl.textContent = targetCredits;

        if (creditsProgressEl) {
            creditsProgressEl.style.width = `${percent}%`;
            creditsProgressEl.setAttribute('aria-valuenow', percent);
            const progressText = document.getElementById('kpiCreditsProgressText');
            if (progressText) progressText.textContent = `${percent}% ของหลักสูตร (เป้าหมาย ${targetCredits} นก.)`;
        }

        const totalCoursesEl = document.getElementById('kpiTotalCourses');
        if (totalCoursesEl) totalCoursesEl.textContent = summary.total_courses || 0;

        for (let y = 1; y <= 4; y++) {
            const yr = summary.year_stats[y] || { total_credits: 0, gpa_text: 'ยังไม่มีข้อมูลเกรด' };
            const chipCredits = document.getElementById(`year${y}Credits`);
            const chipGpa = document.getElementById(`year${y}Gpa`);
            if (chipCredits) chipCredits.textContent = `${yr.total_credits} นก.`;
            if (chipGpa) {
                chipGpa.textContent = yr.gpa !== null ? `GPA ${yr.gpa_text}` : 'ยังไม่มีเกรด';
                chipGpa.className = yr.gpa !== null ? 'fw-bold text-primary' : 'text-muted';
            }
        }
    },

    // -------------------------------------------------------------
    // แสดงผลรายวิชาในแต่ละภาคการศึกษาทั้ง 8 ภาคเรียน
    // -------------------------------------------------------------
    renderSemesters(semesters) {
        if (!semesters) return;

        for (let y = 1; y <= 4; y++) {
            for (let s = 1; s <= 2; s++) {
                const key = `${y}_${s}`;
                const semData = semesters[key] || {
                    courses: [],
                    total_credits: 0,
                    gpa: null,
                    gpa_text: 'ยังไม่มีข้อมูลเกรด'
                };

                const creditsBadge = document.getElementById(`semCredits_${y}_${s}`);
                const gpaBadge = document.getElementById(`semGpa_${y}_${s}`);
                const countBadge = document.getElementById(`semCount_${y}_${s}`);

                if (creditsBadge) creditsBadge.textContent = `${semData.total_credits} หน่วยกิต`;
                if (countBadge) countBadge.textContent = `${semData.courses.length} วิชา`;
                if (gpaBadge) {
                    if (semData.gpa !== null) {
                        gpaBadge.textContent = `GPA: ${semData.gpa_text}`;
                        gpaBadge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1';
                    } else {
                        gpaBadge.textContent = 'GPA: ยังไม่มีข้อมูลเกรด';
                        gpaBadge.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1';
                    }
                }

                const container = document.getElementById(`semContent_${y}_${s}`);
                if (!container) continue;

                if (semData.courses.length === 0) {
                    container.innerHTML = `
                        <div class="semester-empty py-4">
                            <i class="bi bi-journal-plus display-6 d-block mb-2 text-muted opacity-50"></i>
                            <div class="fw-medium text-secondary mb-1">ยังไม่มีรายวิชาในภาคการศึกษานี้</div>
                            <small class="text-muted d-block mb-3">กดปุ่ม "เพิ่มรายวิชา" เพื่อเลือกวิชาจากหลักสูตร</small>
                            <button class="btn btn-sm btn-cmu-outline rounded-pill px-3" onclick="App.openAddCourseModal(${y}, ${s})">
                                <i class="bi bi-plus-circle me-1"></i> เพิ่มรายวิชา
                            </button>
                        </div>
                    `;
                } else {
                    let rowsHtml = '';
                    semData.courses.forEach(c => {
                        const gradeVal = c.expected_grade || '';
                        const secText = c.section ? c.section : '-';
                        const timeText = c.class_time ? c.class_time : '';
                        const instructorText = c.instructor ? c.instructor : '';

                        rowsHtml += `
                            <tr id="row_planned_${c.id}">
                                <td style="width: 105px;">
                                    <span class="course-code-badge">${this.escapeHtml(c.course_code)}</span>
                                </td>
                                <td>
                                    <div class="course-name-text">${this.escapeHtml(c.course_name)}</div>
                                    <div class="course-meta-text d-flex align-items-center gap-2 mt-1 flex-wrap">
                                        <span class="badge bg-light text-secondary border">${this.escapeHtml(c.category || 'วิชาเฉพาะ')}</span>
                                        ${instructorText ? `<span><i class="bi bi-person me-1"></i>${this.escapeHtml(instructorText)}</span>` : ''}
                                        ${timeText ? `<span><i class="bi bi-clock me-1"></i>${this.escapeHtml(timeText)}</span>` : ''}
                                    </div>
                                </td>
                                <td class="text-center fw-semibold text-secondary hide-on-mobile" style="width: 60px;">
                                    ${this.escapeHtml(secText)}
                                </td>
                                <td class="text-center fw-semibold" style="width: 80px;">
                                    <span class="badge bg-light text-primary border">${c.credits} นก.</span>
                                </td>
                                <td style="width: 120px;" class="text-center">
                                    <select class="form-select form-select-sm grade-select" 
                                            data-grade="${gradeVal}"
                                            onchange="App.onGradeChange(${c.id}, this)">
                                        <option value="" ${gradeVal === '' ? 'selected' : ''}>- ยังไม่ประเมิน -</option>
                                        <option value="A" ${gradeVal === 'A' ? 'selected' : ''}>A (4.00)</option>
                                        <option value="B+" ${gradeVal === 'B+' ? 'selected' : ''}>B+ (3.50)</option>
                                        <option value="B" ${gradeVal === 'B' ? 'selected' : ''}>B (3.00)</option>
                                        <option value="C+" ${gradeVal === 'C+' ? 'selected' : ''}>C+ (2.50)</option>
                                        <option value="C" ${gradeVal === 'C' ? 'selected' : ''}>C (2.00)</option>
                                        <option value="D+" ${gradeVal === 'D+' ? 'selected' : ''}>D+ (1.50)</option>
                                        <option value="D" ${gradeVal === 'D' ? 'selected' : ''}>D (1.00)</option>
                                        <option value="F" ${gradeVal === 'F' ? 'selected' : ''}>F (0.00)</option>
                                    </select>
                                </td>
                                <td class="text-end" style="width: 50px;">
                                    <button class="btn btn-icon-danger btn-sm" 
                                            title="ลบวิชานี้ออกจากแผน" 
                                            onclick="App.deletePlannedCourse(${c.id}, '${this.escapeHtml(c.course_code)}')">
                                        <i class="bi bi-trash3-fill"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    container.innerHTML = `
                        <div class="table-responsive">
                            <table class="table table-hover course-table align-middle">
                                <thead>
                                    <tr>
                                        <th>รหัสวิชา</th>
                                        <th>ชื่อกระบวนวิชา (ภาษาไทย)</th>
                                        <th class="text-center hide-on-mobile">Sec</th>
                                        <th class="text-center">หน่วยกิต</th>
                                        <th class="text-center">เกรดที่คาดหมาย</th>
                                        <th class="text-end">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${rowsHtml}
                                </tbody>
                            </table>
                        </div>
                    `;
                }
            }
        }
    },

    // -------------------------------------------------------------
    // ระบบ Modal เพิ่มรายวิชา
    // -------------------------------------------------------------
    openAddCourseModal(year, semester) {
        this.targetYear = year;
        this.targetSemester = semester;

        const titleEl = document.getElementById('addCourseModalLabel');
        if (titleEl) {
            titleEl.innerHTML = `<i class="bi bi-plus-circle me-2"></i>เพิ่มรายวิชา - ชั้นปีที่ ${year} ภาคการศึกษาที่ ${semester}`;
        }

        const searchInput = document.getElementById('courseSearchInput');
        if (searchInput) searchInput.value = '';

        this.currentCategoryFilter = 'all';
        document.querySelectorAll('.filter-pill').forEach(p => {
            if (p.dataset.category === 'all') p.classList.add('active');
            else p.classList.remove('active');
        });

        this.searchCourses('');

        const modalEl = document.getElementById('addCourseModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    },

    async searchCourses(query = '') {
        const listContainer = document.getElementById('courseModalResults');
        if (!listContainer) return;

        listContainer.innerHTML = `
            <div class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                กำลังค้นหารายวิชาในหลักสูตร TQF...
            </div>
        `;

        try {
            const params = new URLSearchParams({
                q: query,
                category: this.currentCategoryFilter
            });
            const response = await fetch(`api/courses.php?${params.toString()}`);
            const data = await response.json();

            if (!data.success) {
                listContainer.innerHTML = `<div class="p-4 text-center text-danger">${data.message || 'ไม่สามารถโหลดวิชาได้'}</div>`;
                return;
            }

            this.availableCourses = data.data || [];
            this.renderCourseSearchResults();
        } catch (error) {
            console.error('Search Courses Error:', error);
            listContainer.innerHTML = `<div class="p-4 text-center text-danger">เกิดข้อผิดพลาดในการเชื่อมต่อ</div>`;
        }
    },

    renderCourseSearchResults() {
        const listContainer = document.getElementById('courseModalResults');
        if (!listContainer) return;

        if (this.availableCourses.length === 0) {
            listContainer.innerHTML = `
                <div class="text-center py-5 px-3">
                    <i class="bi bi-journal-x display-6 d-block mb-3 text-secondary opacity-50"></i>
                    <div class="fw-bold text-dark fs-6 mb-1">ไม่พบรายวิชาที่ตรงกับเงื่อนไขการค้นหา</div>
                    <p class="text-muted small mb-3">วิชานี้อาจยังไม่มีในระบบ หรือเป็นรายวิชาใหม่ / วิชาเลือกเสรีต่างคณะ</p>
                    <a href="create-course.php" class="btn btn-sm btn-primary rounded-pill px-4 py-2 fw-medium shadow-sm" style="background: linear-gradient(135deg, #682c91, #6366f1); border:none;">
                        <i class="bi bi-plus-circle me-1"></i> เพิ่มวิชานี้เข้าสู่ระบบเพื่อแบ่งปันเพื่อนๆ
                    </a>
                </div>
            `;
            return;
        }

        const currentSemKey = `${this.targetYear}_${this.targetSemester}`;
        const enrolledInSem = new Set(
            (this.currentPlanData?.semesters?.[currentSemKey]?.courses || []).map(c => Number(c.course_id || c.id))
        );

        let html = '';
        this.availableCourses.forEach(c => {
            const isAlreadyInSem = enrolledInSem.has(Number(c.id));

            html += `
                <div class="course-search-item d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <span class="course-code-badge">${this.escapeHtml(c.course_code)}</span>
                            <span class="badge bg-light text-secondary border">${this.escapeHtml(c.category)}</span>
                            <span class="badge bg-light text-primary border">${c.credits} หน่วยกิต</span>
                            ${c.section ? `<span class="text-muted small"><i class="bi bi-bookmark me-1"></i>Sec: ${this.escapeHtml(c.section)}</span>` : ''}
                        </div>
                        <div class="fw-semibold text-dark">${this.escapeHtml(c.course_name)}</div>
                        ${(c.instructor || c.class_time) ? `
                            <div class="small text-muted d-flex align-items-center gap-3 mt-1 flex-wrap">
                                ${c.class_time ? `<span><i class="bi bi-clock me-1"></i>${this.escapeHtml(c.class_time)}</span>` : ''}
                                ${c.instructor ? `<span><i class="bi bi-person me-1"></i>${this.escapeHtml(c.instructor)}</span>` : ''}
                            </div>
                        ` : ''}
                    </div>
                    <div class="mt-2 mt-md-0 text-md-end">
                        ${isAlreadyInSem ? `
                            <button class="btn btn-sm btn-secondary disabled rounded-pill px-3" disabled>
                                <i class="bi bi-check2 me-1"></i> อยู่ในเทอมนี้แล้ว
                            </button>
                        ` : `
                            <button class="btn btn-sm btn-cmu rounded-pill px-3 shadow-sm" onclick="App.addCourseToPlan(${c.id})">
                                <i class="bi bi-plus-lg me-1"></i> เลือกวิชานี้
                            </button>
                        `}
                    </div>
                </div>
            `;
        });

        listContainer.innerHTML = html;
    },

    // -------------------------------------------------------------
    // เพิ่มวิชาลงในแผนการเรียน
    // -------------------------------------------------------------
    async addCourseToPlan(courseId) {
        const selectedCourse = this.availableCourses.find(c => Number(c.id) === Number(courseId));
        if (!selectedCourse) return;

        // โหมดผู้เยี่ยมชม (Guest Mode)
        if (!this.isLoggedIn) {
            const guestCourses = JSON.parse(localStorage.getItem('cmu_cs_guest_plan') || '[]');
            
            // ตรวจสอบซ้ำในเทอมเดียวกัน
            const exists = guestCourses.some(c => 
                Number(c.course_id || c.id) === Number(courseId) && 
                Number(c.year) === Number(this.targetYear) && 
                Number(c.semester) === Number(this.targetSemester)
            );

            if (exists) {
                this.showToast(`วิชา ${selectedCourse.course_code} มีอยู่ในเทอมนี้แล้ว`, 'warning');
                return;
            }

            const newEntry = {
                id: Date.now(),
                course_id: selectedCourse.id,
                course_code: selectedCourse.course_code,
                course_name: selectedCourse.course_name,
                credits: selectedCourse.credits,
                section: selectedCourse.section || '',
                class_time: selectedCourse.class_time || '',
                instructor: selectedCourse.instructor || '',
                category: selectedCourse.category,
                year: this.targetYear,
                semester: this.targetSemester,
                expected_grade: ''
            };

            guestCourses.push(newEntry);
            this.saveGuestPlan(guestCourses);
            this.calculateAndRenderGuestData(guestCourses);
            this.renderCourseSearchResults();
            this.showToast(`เพิ่มวิชา ${selectedCourse.course_code} ในแผนทดลองแล้ว (ยังไม่ได้บันทึกลงฐานข้อมูล)`, 'info');
            return;
        }

        // โหมดเข้าสู่ระบบแล้ว (Database Mode)
        try {
            const payload = {
                action: 'add',
                course_id: courseId,
                year: this.targetYear,
                semester: this.targetSemester,
                expected_grade: ''
            };

            const response = await fetch('api/planned-courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (!data.success) {
                this.showToast(data.message || 'ไม่สามารถเพิ่มรายวิชาได้', 'warning');
                return;
            }

            this.showToast(data.message || 'บันทึกวิชาลงฐานข้อมูลสำเร็จ', 'success');
            await this.fetchPlan();
            this.renderCourseSearchResults();

        } catch (error) {
            console.error('Add Course Error:', error);
            this.showToast('เกิดข้อผิดพลาดในการเพิ่มรายวิชา', 'danger');
        }
    },

    // -------------------------------------------------------------
    // อัปเดตเกรดที่คาดการณ์
    // -------------------------------------------------------------
    async onGradeChange(plannedId, selectElement) {
        const newGrade = selectElement.value;
        selectElement.setAttribute('data-grade', newGrade);

        // โหมดผู้เยี่ยมชม (Guest Mode)
        if (!this.isLoggedIn) {
            const guestCourses = JSON.parse(localStorage.getItem('cmu_cs_guest_plan') || '[]');
            const item = guestCourses.find(c => Number(c.id) === Number(plannedId));
            if (item) {
                item.expected_grade = newGrade;
                this.saveGuestPlan(guestCourses);
                this.calculateAndRenderGuestData(guestCourses);
                this.showToast('คำนวณเกรดใหม่แล้ว (โหมดทดลอง - ยังไม่บันทึกลงฐานข้อมูล)', 'info');
            }
            return;
        }

        // โหมดเข้าสู่ระบบ (Database Mode)
        try {
            const response = await fetch('api/update-grade.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id: plannedId,
                    expected_grade: newGrade
                })
            });

            const data = await response.json();

            if (!data.success) {
                this.showToast(data.message || 'ไม่สามารถอัปเดตเกรดได้', 'danger');
                return;
            }

            this.showToast(data.message || 'บันทึกเกรดลงฐานข้อมูลสำเร็จ', 'success');
            await this.fetchPlan();

        } catch (error) {
            console.error('Update Grade Error:', error);
            this.showToast('เกิดข้อผิดพลาดในการอัปเดตเกรด', 'danger');
        }
    },

    // -------------------------------------------------------------
    // ลบวิชาออกจากแผนการเรียน
    // -------------------------------------------------------------
    async deletePlannedCourse(plannedId, courseCode) {
        if (!confirm(`ยืนยันการลบวิชา ${courseCode} ออกจากภาคการศึกษานี้ใช่หรือไม่?`)) {
            return;
        }

        // โหมดผู้เยี่ยมชม (Guest Mode)
        if (!this.isLoggedIn) {
            let guestCourses = JSON.parse(localStorage.getItem('cmu_cs_guest_plan') || '[]');
            guestCourses = guestCourses.filter(c => Number(c.id) !== Number(plannedId));
            this.saveGuestPlan(guestCourses);
            this.calculateAndRenderGuestData(guestCourses);
            this.showToast(`ลบวิชา ${courseCode} ออกจากแผนทดลองแล้ว`, 'info');
            return;
        }

        // โหมดเข้าสู่ระบบ (Database Mode)
        try {
            const response = await fetch('api/planned-courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    id: plannedId
                })
            });

            const data = await response.json();

            if (!data.success) {
                this.showToast(data.message || 'ไม่สามารถลบรายวิชาได้', 'danger');
                return;
            }

            this.showToast(data.message || 'ลบรายวิชาออกจากฐานข้อมูลสำเร็จ', 'success');
            await this.fetchPlan();

        } catch (error) {
            console.error('Delete Course Error:', error);
            this.showToast('เกิดข้อผิดพลาดในการลบรายวิชา', 'danger');
        }
    },

    // -------------------------------------------------------------
    // โหลดแผนการเรียนตัวอย่าง
    // -------------------------------------------------------------
    async confirmLoadSample() {
        if (!confirm('ต้องการโหลดแผนการเรียนตัวอย่างตามหลักสูตรวิทยาการคอมพิวเตอร์หรือไม่? (ข้อมูลเดิมจะถูกแทนที่)')) {
            return;
        }

        // โหมดผู้เยี่ยมชม (Guest Mode)
        if (!this.isLoggedIn) {
            const defaultCourses = this.getDefaultGuestCourses();
            this.saveGuestPlan(defaultCourses);
            this.calculateAndRenderGuestData(defaultCourses);
            this.showToast('โหลดแผนการเรียนตัวอย่างเรียบร้อยแล้ว (โหมดทดลอง)', 'info');
            return;
        }

        // โหมดเข้าสู่ระบบ (Database Mode)
        try {
            const response = await fetch('api/planned-courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'load_sample' })
            });

            const data = await response.json();
            this.showToast(data.message || 'โหลดตัวอย่างสำเร็จและบันทึกลงฐานข้อมูลแล้ว', 'success');
            await this.fetchPlan();
        } catch (error) {
            console.error('Load Sample Error:', error);
            this.showToast('เกิดข้อผิดพลาดในการโหลดตัวอย่าง', 'danger');
        }
    },

    // -------------------------------------------------------------
    // รีเซ็ตแผนการเรียนทั้งหมด
    // -------------------------------------------------------------
    async confirmResetPlan() {
        if (!confirm('คำเตือน: คุณต้องการล้างแผนการเรียนทั้งหมดใช่หรือไม่?')) {
            return;
        }

        // โหมดผู้เยี่ยมชม (Guest Mode)
        if (!this.isLoggedIn) {
            this.saveGuestPlan([]);
            this.calculateAndRenderGuestData([]);
            this.showToast('ล้างแผนการเรียนทดลองเรียบร้อยแล้ว', 'warning');
            return;
        }

        // โหมดเข้าสู่ระบบ (Database Mode)
        try {
            const response = await fetch('api/planned-courses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset' })
            });

            const data = await response.json();
            this.showToast(data.message || 'ล้างแผนการเรียนในฐานข้อมูลเรียบร้อย', 'warning');
            await this.fetchPlan();
        } catch (error) {
            console.error('Reset Plan Error:', error);
            this.showToast('เกิดข้อผิดพลาดในการรีเซ็ตแผน', 'danger');
        }
    },

    // -------------------------------------------------------------
    // ขยาย / ย่อ Accordion ทุกเทอม
    // -------------------------------------------------------------
    toggleAllAccordions(expand) {
        for (let y = 1; y <= 4; y++) {
            const collapseEl = document.getElementById(`collapseYear_${y}`);
            if (collapseEl) {
                const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });
                if (expand) bsCollapse.show();
                else bsCollapse.hide();
            }
        }
    },

    // -------------------------------------------------------------
    // Toast Notification Manager
    // -------------------------------------------------------------
    showToast(message, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const bgClass = {
            'success': 'bg-success text-white',
            'danger': 'bg-danger text-white',
            'warning': 'bg-warning text-dark',
            'info': 'bg-primary text-white',
        }[type] || 'bg-dark text-white';

        const toastId = 'toast_' + Date.now();
        const toastHtml = `
            <div id="${toastId}" class="toast align-items-center ${bgClass} border-0 shadow-lg rounded-3 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center gap-2">
                        <i class="bi ${type === 'danger' ? 'bi-exclamation-triangle-fill' : (type === 'warning' ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill')} fs-5"></i>
                        <span>${this.escapeHtml(message)}</span>
                    </div>
                    <button type="button" class="btn-close ${type !== 'warning' ? 'btn-close-white' : ''} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

        container.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = document.getElementById(toastId);
        const bsToast = new bootstrap.Toast(toastElement, { delay: 3500 });
        bsToast.show();

        toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
    },

    setLoading(loading) {
        const loader = document.getElementById('globalLoading');
        if (loader) {
            loader.style.display = loading ? 'inline-block' : 'none';
        }
    },

    escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});
