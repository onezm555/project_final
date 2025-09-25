<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// เรียกใช้ไฟล์ conn.php เพื่อเชื่อมต่อฐานข้อมูล
require_once 'conn.php'; // ตรวจสอบให้แน่ใจว่า path นี้ถูกต้อง

// Handle OPTIONS requests (CORS preflight)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data['email'] ?? '';
    $enteredCode = $data['code'] ?? ''; // ใช้ 'code' ตามที่ Flutter ส่งมา
    $action = $data['action'] ?? ''; // เพิ่ม action parameter

    // ตรวจสอบว่าเป็นการขอ bypass verification หรือไม่
    if ($action === 'bypass_verification') {
        if (empty($email)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'กรุณาระบุอีเมล'
            ]);
            exit;
        }

        try {
            // อัปเดต is_verified เป็น 1 โดยตรง
            $stmt_update = $conn->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
            $stmt_update->execute([$email]);

            if ($stmt_update->rowCount() > 0) {
                http_response_code(200);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'ยืนยันอีเมลสำเร็จ! บัญชีของคุณพร้อมใช้งานแล้ว'
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'ไม่พบอีเมลนี้ในระบบ'
                ]);
            }
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'เกิดข้อผิดพลาดในการทำงานกับฐานข้อมูล: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    // กรณีปกติ - ตรวจสอบรหัส verification
    if (empty($email) || empty($enteredCode)) {
        http_response_code(400); // Bad Request
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณาระบุอีเมลและรหัสยืนยัน'
        ]);
        exit;
    }

    try {
        // ดึงข้อมูลผู้ใช้จากฐานข้อมูล
        $stmt = $conn->prepare("SELECT verification_code, code_sent_at, is_verified FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            $storedCode = $userData['verification_code'];
            $codeSentAt = strtotime($userData['code_sent_at']);
            $isVerified = $userData['is_verified'];
            $currentTime = time();
            $expirationTime = 300; // รหัสหมดอายุใน 5 นาที (300 วินาที)

            // ตรวจสอบว่าบัญชีได้รับการยืนยันแล้ว
            if ($isVerified == 1) {
                http_response_code(400); // Bad Request
                echo json_encode([
                    'status' => 'error',
                    'message' => 'บัญชีนี้ได้รับการยืนยันแล้ว'
                ]);
                exit;
            }

            // **ส่วนที่แก้ไข: แยกเงื่อนไขการตรวจสอบรหัส**
            if ($enteredCode == $storedCode) {
                // รหัสถูกต้อง
                if (($currentTime - $codeSentAt) <= $expirationTime) {
                    // รหัสยังไม่หมดอายุ
                    $stmt_update = $conn->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
                    $stmt_update->execute([$email]);

                    http_response_code(200); // OK
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'ยืนยันอีเมลสำเร็จ! บัญชีของคุณพร้อมใช้งานแล้ว'
                    ]);
                } else {
                    // รหัสหมดอายุแล้ว
                    http_response_code(400); // Bad Request
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'รหัสยืนยันหมดอายุแล้ว กรุณาลองส่งรหัสใหม่อีกครั้ง'
                    ]);
                }
            } else {
                // รหัสไม่ถูกต้อง
                http_response_code(400); // Bad Request
                echo json_encode([
                    'status' => 'error',
                    'message' => 'รหัสยืนยันโค้ดไม่ถูกต้องโปรดตรวจสอบใหม่'
                ]);
            }
        } else {
            // ไม่พบอีเมลในระบบ
            http_response_code(404); // Not Found
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่พบอีเมลนี้ในระบบ กรุณาสมัครสมาชิกใหม่'
            ]);
        }

    } catch (PDOException $e) {
        // ข้อผิดพลาดที่เกี่ยวกับฐานข้อมูล
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการทำงานกับฐานข้อมูล: ' . $e->getMessage()
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