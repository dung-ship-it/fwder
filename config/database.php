<?php
// Cấu hình kết nối database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'forwarder_db');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Kết nối thất bại: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

session_start();

// Load permissions helpers
require_once __DIR__ . '/permissions.php';

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /forwarder/login.php");
        exit();
    }
}

function checkAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        die("Bạn không có quyền truy cập!");
    }
}

function generateJobNo($conn) {
    $year  = date('Y');
    $month = date('m');

    $stmt = $conn->prepare("SELECT counter FROM job_no_counter WHERE year = ? AND month = ?");
    $stmt->bind_param("ii", $year, $month);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row     = $result->fetch_assoc();
        $counter = $row['counter'] + 1;
        $stmt_update = $conn->prepare("UPDATE job_no_counter SET counter = ? WHERE year = ? AND month = ?");
        $stmt_update->bind_param("iii", $counter, $year, $month);
        $stmt_update->execute();
        $stmt_update->close();
    } else {
        $counter = 1;
        $stmt_insert = $conn->prepare("INSERT INTO job_no_counter (year, month, counter) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("iii", $year, $month, $counter);
        $stmt_insert->execute();
        $stmt_insert->close();
    }
    $stmt->close();

    return sprintf("JOB-%s%s-%04d", $year, $month, $counter);
}