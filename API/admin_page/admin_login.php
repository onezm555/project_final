<?php
// ตั้งค่า CORS headers ก่อนอื่น
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// จัดการ preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// เช็ค method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Include connection
require_once 'conn.php';

// รับข้อมูลจาก request
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน'
    ]);
    exit();
}

$username = trim($input['username']);
$password = trim($input['password']);

try {
    // ค้นหาผู้ใช้ที่มี id = 0 เท่านั้น
    $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE id = 0 AND (name = :username OR email = :email)");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':email', $username);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'ไม่พบผู้ใช้หรือไม่มีสิทธิ์เข้าสู่ระบบ'
        ]);
        exit();
    }
    
    // เช็ครหัสผ่าน
    $password_valid = false;
    
    if ($user['password'] === 'admin' && $password === 'admin') {
        // รหัสผ่าน plain text สำหรับ admin
        $password_valid = true;
    } else if (password_verify($password, $user['password'])) {
        // รหัสผ่านที่ถูก hash
        $password_valid = true;
    }
    
    if (!$password_valid) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'รหัสผ่านไม่ถูกต้อง'
        ]);
        exit();
    }
    
    // สร้าง token
    $token = bin2hex(random_bytes(32));
    
    // ส่งข้อมูลกลับ
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'เข้าสู่ระบบสำเร็จ',
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage()
    ]);
}
?>