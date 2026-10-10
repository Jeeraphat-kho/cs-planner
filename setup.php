<?php
/**
 * CMU Computer Science Study Planner
 * Database Setup & Initialization Script
 * -------------------------------------------------------------
 * สามารถรันผ่านเบราว์เซอร์ http://localhost/cs-planer/setup.php
 * หรือผ่าน Command Line: php setup.php
 */

$isCli = (php_sapi_name() === 'cli');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'cmu_cs_planner';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root';

$pdo = null;
$errorMsg = null;

$combinations = [
    ['user' => $user, 'pass' => $pass],
    ['user' => 'root', 'pass' => ''],
    ['user' => 'root', 'pass' => 'root']
];

foreach ($combinations as $combo) {
    try {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $combo['user'], $combo['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $user = $combo['user'];
        $pass = $combo['pass'];
        break;
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
    }
}

if (!$pdo) {
    $msg = "ไม่สามารถเชื่อมต่อ MySQL ได้: " . $errorMsg;
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    } else {
        die("<div style='font-family:sans-serif;color:red;padding:20px;'>{$msg}</div>");
    }
}

$logs = [];

try {
    // 1. สร้างฐานข้อมูล
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbname}`");
    $logs[] = "สร้างและเลือกฐานข้อมูล '{$dbname}' สำเร็จ";

    // 2. โหลด schema.sql
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (file_exists($schemaFile)) {
        $schemaSql = file_get_contents($schemaFile);
        $pdo->exec($schemaSql);
        $logs[] = "สร้างโครงสร้างตาราง (users, courses, study_plans, planned_courses) สำเร็จ";
    }

    // 3. โหลด sample_data.sql
    $sampleFile = __DIR__ . '/database/sample_data.sql';
    if (file_exists($sampleFile)) {
        $sampleSql = file_get_contents($sampleFile);
        $pdo->exec($sampleSql);
        $logs[] = "นำเข้าข้อมูลรายวิชาจากหลักสูตร TQF มช. (85 วิชา) และผู้ใช้ตัวอย่างสำเร็จ";
    }

    // นับจำนวนข้อมูล
    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM `courses`");
    $courseCount = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM `users`");
    $userCount = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) AS total FROM `planned_courses`");
    $plannedCount = $stmt->fetch()['total'];

    $logs[] = "พบรายวิชาหลักสูตร TQF ทั้งหมด: {$courseCount} วิชา";
    $logs[] = "พบผู้ใช้งานในระบบ: {$userCount} บัญชี";
    $logs[] = "พบรายวิชาในแผนเริ่มต้น: {$plannedCount} วิชา";

    $success = true;
} catch (Exception $e) {
    $success = false;
    $logs[] = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

if ($isCli) {
    echo "=== CMU CS Planner Setup ===" . PHP_EOL;
    foreach ($logs as $log) {
        echo "- " . $log . PHP_EOL;
    }
    echo ($success ? "Setup Completed Successfully!" : "Setup Failed!") . PHP_EOL;
    exit($success ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - CMU CS Study Planner</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="CSCMU-LOGO.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
        .hero-banner { background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white; border-radius: 1rem; }
    </style>
</head>
<body class="py-5">
    <div class="container" style="max-width: 680px;">
        <div class="hero-banner p-4 p-md-5 mb-4 shadow">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white rounded-3 p-1.5 shadow-sm" style="width: 58px; height: 58px; display: inline-flex; align-items: center; justify-content: center;">
                    <img src="CSCMU-LOGO.png" alt="CS CMU Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                </div>
                <div>
                    <h2 class="fw-bold mb-1">CMU CS Study Planner</h2>
                    <p class="mb-0 opacity-75">การติดตั้งและเตรียมฐานข้อมูล (Database Setup)</p>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
                    <?php if ($success): ?>
                        <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        <span>การติดตั้งเสร็จสมบูรณ์เรียบร้อยแล้ว</span>
                    <?php else: ?>
                        <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                        <span>เกิดข้อผิดพลาดระหว่างการติดตั้ง</span>
                    <?php endif; ?>
                </h5>

                <div class="list-group mb-4">
                    <?php foreach ($logs as $log): ?>
                        <div class="list-group-item d-flex align-items-center gap-2 py-2 border-0 bg-light rounded mb-1">
                            <i class="bi bi-arrow-right-circle text-primary"></i>
                            <span class="small"><?= htmlspecialchars($log) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-info py-2 px-3 small rounded-3 mb-3">
                        <i class="bi bi-person-badge me-1"></i> บัญชีทดสอบเริ่มต้น: <strong>660510001</strong> / รหัสผ่าน: <strong>123456</strong>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="login.php" class="btn btn-primary btn-lg rounded-pill fw-semibold shadow-sm" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border:none;">
                            <i class="bi bi-box-arrow-in-right me-1"></i> ไปที่หน้าเข้าสู่ระบบ (Login)
                        </a>
                    </div>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <a href="setup.php" class="btn btn-warning rounded-pill">
                            <i class="bi bi-arrow-clockwise me-1"></i> ลองใหม่อีกครั้ง
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-center text-muted small mt-4">Computer Science, Chiang Mai University</p>
    </div>
</body>
</html>
