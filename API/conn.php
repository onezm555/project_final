<?php
$host = 'localhost';
$db   = 'project_data';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conn = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // ไม่ echo หรือ exit ที่นี่ ให้ไฟล์ที่เรียกใช้จัดการเอง
    $conn = null;
    // เก็บข้อผิดพลาดไว้ในตัวแปร global
    $GLOBALS['db_connection_error'] = 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage();
}

$api_base_url = 'http://10.10.33.57/project/';
$image_folder = 'img/';

$base_image_url = $api_base_url . $image_folder;
$default_image_url = $base_image_url . 'default.png';

function get_full_image_url($image_filename_from_db = null) {
    global $base_image_url, $default_image_url, $image_folder;

    if (empty($image_filename_from_db)) {
        return $default_image_url;
    }

    $image_filename_from_db = trim($image_filename_from_db);

  
    $image_filename_from_db = preg_replace('/^(https?:\/\/)+/i', 'http://', $image_filename_from_db);

    // ถ้าเป็น URL เต็ม (http/https) ให้ return เลย
    if (preg_match('/^https?:\/\//i', $image_filename_from_db)) {
        return $image_filename_from_db;
    }

    // ตัด prefix ออกถ้ามี
    if (str_starts_with($image_filename_from_db, $base_image_url)) {
        $image_filename_from_db = substr($image_filename_from_db, strlen($base_image_url));
    } else if (str_starts_with($image_filename_from_db, $image_folder)) {
        $image_filename_from_db = substr($image_filename_from_db, strlen($image_folder));
    }

    return $base_image_url . ltrim($image_filename_from_db, '/');
}