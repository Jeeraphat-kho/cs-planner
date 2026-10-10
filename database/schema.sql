-- ==============================================================
-- CMU Computer Science Study Planner
-- Database Schema with Authentication & TQF Courses
-- Charset: utf8mb4, Engine: InnoDB
-- ==============================================================

CREATE DATABASE IF NOT EXISTS `cmu_cs_planner` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cmu_cs_planner`;

-- 1. ตารางผู้ใช้งาน / นักศึกษา (users)
DROP TABLE IF EXISTS `planned_courses`;
DROP TABLE IF EXISTS `study_plans`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `courses`;

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(20) NOT NULL UNIQUE COMMENT 'รหัสประจำตัวนักศึกษา (ใช้เข้าสู่ระบบ)',
    `full_name` VARCHAR(100) NOT NULL COMMENT 'ชื่อ - นามสกุลจริง',
    `password` VARCHAR(255) NOT NULL COMMENT 'รหัสผ่านแฮช (Password Hash)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ตารางรายวิชาทั้งหมดในหลักสูตร (courses)
CREATE TABLE `courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(20) NOT NULL COMMENT 'รหัสกระบวนวิชา เช่น 204111',
    `course_name` VARCHAR(255) NOT NULL COMMENT 'ชื่อกระบวนวิชา',
    `credits` INT NOT NULL DEFAULT 3 COMMENT 'จำนวนหน่วยกิต',
    `section` VARCHAR(20) DEFAULT '' COMMENT 'ตอนเรียน (Section)',
    `class_time` VARCHAR(100) DEFAULT '' COMMENT 'วันเวลาเรียน',
    `instructor` VARCHAR(255) DEFAULT '' COMMENT 'อาจารย์ผู้สอน',
    `category` VARCHAR(100) DEFAULT 'หมวดวิชาเอกบังคับ' COMMENT 'หมวดหมู่วิชา',
    `description` TEXT DEFAULT NULL COMMENT 'คำอธิบายรายวิชา',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_course_code` (`course_code`),
    INDEX `idx_course_name` (`course_name`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ตารางแผนการเรียนของนักศึกษา (study_plans)
CREATE TABLE `study_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL COMMENT 'รหัสผู้ใช้งานที่ครอบครองแผนนี้',
    `student_id` VARCHAR(20) NOT NULL COMMENT 'รหัสนักศึกษา',
    `student_name` VARCHAR(100) NOT NULL COMMENT 'ชื่อนักศึกษา',
    `title` VARCHAR(255) DEFAULT 'แผนการเรียน 4 ปี (วิทยาการคอมพิวเตอร์)' COMMENT 'ชื่อแผนการเรียน',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ตารางรายวิชาที่เลือกในแผนการเรียน (planned_courses)
CREATE TABLE `planned_courses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plan_id` INT NOT NULL COMMENT 'รหัสแผนการเรียน',
    `course_id` INT NOT NULL COMMENT 'รหัสรายวิชาในตาราง courses',
    `year` TINYINT NOT NULL COMMENT 'ชั้นปี (1-4)',
    `semester` TINYINT NOT NULL COMMENT 'ภาคการศึกษา (1-2)',
    `expected_grade` VARCHAR(5) DEFAULT NULL COMMENT 'เกรดที่คาดว่าจะได้รับ',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`plan_id`) REFERENCES `study_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
    -- ป้องกันเพิ่มวิชาซ้ำในเทอมเดียวกัน
    UNIQUE KEY `uk_plan_course_semester` (`plan_id`, `course_id`, `year`, `semester`),
    INDEX `idx_plan_year_sem` (`plan_id`, `year`, `semester`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ตารางแคชผลการค้นหากระบวนวิชาจากสำนักทะเบียน มช. (reg_search_cache)
CREATE TABLE IF NOT EXISTS `reg_search_cache` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cache_key` VARCHAR(64) NOT NULL UNIQUE COMMENT 'MD5(term:type:query)',
    `term` VARCHAR(20) NOT NULL COMMENT 'ภาคการศึกษา เช่น 2/2569, 2/2567H',
    `search_type` VARCHAR(20) NOT NULL COMMENT 'code, group, title',
    `query_str` VARCHAR(100) NOT NULL COMMENT 'คำค้นหา',
    `response_data` MEDIUMTEXT NOT NULL COMMENT 'ผลลัพธ์ JSON ของกระบวนวิชาและ Section',
    `courses_count` INT NOT NULL DEFAULT 0 COMMENT 'จำนวนวิชาที่ค้นพบ',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL COMMENT 'วันเวลาหมดอายุของแคช',
    INDEX `idx_cache_key` (`cache_key`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

