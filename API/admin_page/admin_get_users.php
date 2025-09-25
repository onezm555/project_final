<?php
// ตั้งค่า CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// จัดการ preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// เช็ค method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Include connection
require_once 'conn.php';

try {
    // ดึงข้อมูลผู้ใช้ทั้งหมด ยกเว้น admin (id = 0) และไม่ดึง password
    // เปลี่ยนจาก 'code_sent_at' เป็น 'registered_at'
    $stmt = $conn->prepare("
        SELECT 
            id,
            name, 
            email, 
            phone, 
            is_verified,
            user_img,
            DATE_FORMAT(code_sent_at, '%d/%m/%Y %H:%i') as registered_at,
            DATE_FORMAT(code_sent_at, '%Y-%m') as registered_month
        FROM users 
        WHERE id != 0 
        ORDER BY id DESC
    ");
    $stmt->execute();
    $users = $stmt->fetchAll();

    // จัดรูปแบบข้อมูลให้สวยงาม รองรับสถานะถูกบล๊อค (is_verified = 0 และ user_img = 'blocked')
    $formattedUsers = [];
    foreach ($users as $user) {
        // is_verified: 0 = รอยืนยัน, 1 = ยืนยันแล้ว, 2 = ถูกบล๊อค
        if ($user['is_verified'] == 2) {
            $statusText = 'ถูกบล๊อค';
            $isVerified = false;
        } else if ($user['is_verified'] == 1) {
            $statusText = 'ยืนยันแล้ว';
            $isVerified = true;
        } else {
            $statusText = 'รอยืนยัน';
            $isVerified = false;
        }
        $formattedUsers[] = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'is_verified' => $isVerified,
            'user_img' => $user['user_img'],
            'registered_at' => $user['registered_at'],
            'registered_month' => $user['registered_month'],
            'status' => $statusText,
            'profile_url' => get_user_profile_url($user['user_img'])
        ];
    }
    
    // ส่งข้อมูลกลับ
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'ดึงข้อมูลผู้ใช้สำเร็จ',
        'data' => $formattedUsers,
        'total' => count($formattedUsers)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage()
    ]);
}
?>