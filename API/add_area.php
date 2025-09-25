<?php
// add_area.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header('Content-Type: application/json');

// --- START CORS PREFLIGHT HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Respond to preflight request with 200 OK
    http_response_code(200);
    exit(); // Exit here, no further PHP processing for OPTIONS
}
// --- END CORS PREFLIGHT HANDLING ---

require_once 'conn.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'วิธีการร้องขอไม่ถูกต้อง (ต้องใช้ POST เท่านั้น)'], JSON_UNESCAPED_UNICODE);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

// รับค่า area_name และ user_id จาก request body
$area_name = $data['area_name'] ?? '';
$user_id = $data['user_id'] ?? null;

if (empty($area_name)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุชื่อพื้นที่จัดเก็บ'], JSON_UNESCAPED_UNICODE);
    exit();
}

if ($user_id === null || !is_numeric($user_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบข้อมูลผู้ใช้งาน กรุณาลองเข้าสู่ระบบใหม่'], JSON_UNESCAPED_UNICODE);
    exit();
}
$user_id = (int) $user_id;

try {
    // ตรวจสอบว่ามี area_name นี้อยู่แล้วหรือไม่
    // (สำหรับผู้ใช้แต่ละคน ไม่ใช่ทั่วทั้งระบบ)
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM areas WHERE area_name = :area_name AND user_id = :user_id");
    $stmt_check->bindParam(':area_name', $area_name);
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->execute();

    if ($stmt_check->fetchColumn() > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['status' => 'error', 'message' => 'มีพื้นที่จัดเก็บชื่อ "' . $area_name . '" อยู่แล้ว กรุณาใช้ชื่ออื่น'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // เพิ่มพื้นที่จัดเก็บใหม่
    $stmt_insert = $conn->prepare("INSERT INTO areas (area_name, user_id) VALUES (:area_name, :user_id)");
    $stmt_insert->bindParam(':area_name', $area_name);
    $stmt_insert->bindParam(':user_id', $user_id);

    if ($stmt_insert->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'เพิ่มพื้นที่จัดเก็บสำเร็จแล้ว!', 'area_id' => $conn->lastInsertId()], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเพิ่มพื้นที่จัดเก็บได้ กรุณาลองใหม่อีกครั้ง'], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล กรุณาลองใหม่อีกครั้ง'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง'], JSON_UNESCAPED_UNICODE);
}
?>