<?php
// กำหนด Content-Type เป็น JSON และอนุญาต CORS
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS, GET'); // เพิ่ม GET method
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// เรียกใช้ไฟล์ conn.php เพื่อเชื่อมต่อฐานข้อมูลและใช้ฟังก์ชัน get_full_image_url
require_once 'conn.php';

// รับค่าจาก CORS preflight (OPTIONS request)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['status' => 'error', 'message' => 'Invalid request method'];

// ตรวจสอบว่าเป็น POST หรือ GET method (ตามที่คุณจะส่ง user_id มา)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // สำหรับ POST request, รับ user_id จาก body (เช่น form-data หรือ JSON)
    $userId = $_POST['user_id'] ?? '';

    // หากรับเป็น JSON (กรณี body เป็น json)
    if (empty($userId)) {
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $data['user_id'] ?? '';
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // สำหรับ GET request, รับ user_id จาก query parameter
    $userId = $_GET['user_id'] ?? '';
}

if (empty($userId)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'User ID is required.';
} else {
    try {
        // เตรียมคำสั่ง SQL เพื่อดึงข้อมูลผู้ใช้
        // ดึง user_img ด้วย เพื่อนำไปสร้าง full URL
        $stmt = $conn->prepare("SELECT id, name, email, user_img FROM users WHERE id = :user_id LIMIT 1");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Debug: ดูข้อมูล user_img ที่ได้จากฐานข้อมูล
            error_log("get_user_data.php - user_img from DB: " . var_export($user['user_img'], true));
            
            // สร้าง Full Image URL โดยใช้ฟังก์ชันจาก conn.php
            $imgUrl = get_full_image_url($user['user_img']);
            
            // Debug: ดู URL ที่สร้างได้
            error_log("get_user_data.php - generated imgUrl: " . var_export($imgUrl, true));
            
            // เพิ่ม query string timestamp เพื่อป้องกัน cache
            $user['user_img_full_url'] = $imgUrl ? $imgUrl . '?t=' . time() : '';
            
            // Debug: ดู final URL
            error_log("get_user_data.php - final user_img_full_url: " . var_export($user['user_img_full_url'], true));
            
            unset($user['user_img']); // ไม่ต้องส่ง user_img ดิบๆ กลับไป

            $response = [
                'status' => 'success',
                'message' => 'User data fetched successfully.',
                'user' => $user // ส่งข้อมูลผู้ใช้กลับไป
            ];
            http_response_code(200); // OK
        } else {
            http_response_code(404); // Not Found
            $response['message'] = 'User not found.';
        }
    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>