<?php

// **สำคัญ: เปิดการแสดงผลข้อผิดพลาดเพื่อดีบั๊ก (ลบออกเมื่อขึ้น Production)**
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// เรียกใช้ไฟล์ conn.php เพื่อเชื่อมต่อฐานข้อมูล
require_once 'conn.php'; // ตรวจสอบให้แน่ใจว่า path นี้ถูกต้อง

// รับค่าจาก CORS preflight (OPTIONS request)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    // ตรวจสอบว่ามีการส่งอีเมลและรหัสผ่านมาหรือไม่
    if (empty($email) || empty($password)) {
        http_response_code(400); // Bad Request
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณากรอกอีเมลและรหัสผ่าน'
        ]);
        exit;
    }

    try {
        // ดึงข้อมูลผู้ใช้จากฐานข้อมูลด้วยอีเมล
        // *** เพิ่ม user_img เข้ามาใน SELECT query ***
        $stmt = $conn->prepare("SELECT id, name, email, password, is_verified, user_img FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // ตรวจสอบว่าไม่พบผู้ใช้
        if (!$user) {
            http_response_code(401); // Unauthorized
            echo json_encode([
                'status' => 'error',
                'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'
            ]);
            exit;
        }

        // ตรวจสอบรหัสผ่านที่ป้อนเข้ามากับรหัสผ่านที่แฮชในฐานข้อมูล
        if (!password_verify($password, $user['password'])) {
            http_response_code(401); // Unauthorized
            echo json_encode([
                'status' => 'error',
                'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'
            ]);
            exit;
        }

        // ตรวจสอบสถานะการยืนยันบัญชี
        if ($user['is_verified'] == 0) {
            http_response_code(403); // Forbidden
            echo json_encode([
                'status' => 'unverified',
                'message' => 'บัญชีของคุณยังไม่ได้รับการยืนยัน',
                'email' => $user['email'] // ส่งอีเมลไปด้วยเพื่อใช้ในการส่ง OTP ใหม่
            ]);
            exit;
        }

        // ถ้าทุกอย่างถูกต้อง
        http_response_code(200); // OK
        echo json_encode([
            'status' => 'success',
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'user_id' => $user['id'],
            'name' => $user['name'],
            'user_img' => $user['user_img'] ?? null // *** เพิ่ม user_img เข้าไปใน Response ***
        ]);

    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อหรือทำงานกับฐานข้อมูล: ' . $e->getMessage()
        ]);
    }

} else {
    // กรณีที่ไม่ใช่ POST method
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'status' => 'error',
        'message' => 'Method Not Allowed'
    ]);
}
?>