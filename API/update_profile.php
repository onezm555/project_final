<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Handle OPTIONS request for CORS preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once 'conn.php'; // เรียกใช้ไฟล์ conn.php เพื่อให้ได้ $conn object (PDO)

// $conn พร้อมใช้งานจาก conn.php แล้ว


// รองรับทั้ง JSON (raw body) และ form-data (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
    } else {
        $data = $_POST;
    }
} else {
    $data = [];
}

$email = $data['email'] ?? '';
$user_id = $data['user_id'] ?? '';
$name = $data['name'] ?? $data['user_name'] ?? '';
$currentPassword = $data['current_password'] ?? null;
$newPassword = $data['new_password'] ?? null;

// ต้องมี email หรือ user_id อย่างใดอย่างหนึ่ง
if (empty($email) && empty($user_id)) {
    echo json_encode(['success' => false, 'message' => 'Email or user_id is required.']);
    exit();
}

try {
    // เริ่มต้น SQL query สำหรับอัปเดตชื่อ
    if (!empty($email)) {
        $sql = "UPDATE users SET name = :name WHERE email = :email";
        $params = [':name' => $name, ':email' => $email];
    } else {
        $sql = "UPDATE users SET name = :name WHERE id = :user_id";
        $params = [':name' => $name, ':user_id' => $user_id];
    }

    // ตรวจสอบว่ามีการเปลี่ยนรหัสผ่านหรือไม่
    if ($currentPassword !== null && $newPassword !== null) {
        // 1. ตรวจสอบรหัสผ่านเดิมก่อน
        if (!empty($email)) {
            $stmt_check_password = $conn->prepare("SELECT password FROM users WHERE email = :email");
            $stmt_check_password->bindParam(':email', $email);
        } else {
            $stmt_check_password = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
            $stmt_check_password->bindParam(':user_id', $user_id);
        }
        $stmt_check_password->execute();
        $user = $stmt_check_password->fetch();

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit();
        }
        $hashedPassword = $user['password'];

        // ตรวจสอบรหัสผ่านเดิม
        if (!password_verify($currentPassword, $hashedPassword)) {
            echo json_encode(['success' => false, 'message' => 'รหัสผ่านเดิมไม่ถูกต้อง']);
            exit();
        }

        // 2. แฮชรหัสผ่านใหม่
        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // 3. อัปเดตทั้งชื่อและรหัสผ่าน
        if (!empty($email)) {
            $sql = "UPDATE users SET name = :name, password = :password WHERE email = :email";
            $params[':password'] = $hashedNewPassword;
        } else {
            $sql = "UPDATE users SET name = :name, password = :password WHERE id = :user_id";
            $params[':password'] = $hashedNewPassword;
        }
    }

    // เตรียมและรัน Statement สำหรับอัปเดตข้อมูล
    $stmt_update_profile = $conn->prepare($sql);
    $stmt_update_profile->execute($params);

    if ($stmt_update_profile->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'ข้อมูลโปรไฟล์อัปเดตสำเร็จ']);
    } else {
        // ไม่มีแถวไหนถูกอัปเดต อาจจะเพราะข้อมูลเหมือนเดิม หรือ email ไม่พบ
        echo json_encode(['success' => false, 'message' => 'ไม่มีการเปลี่ยนแปลงข้อมูล หรือไม่พบผู้ใช้นี้']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $e->getMessage()]);
}

// ไม่จำเป็นต้อง $conn->close() สำหรับ PDO เพราะ PHP จัดการเองเมื่อ script จบ
?>