<?php
// delete_area.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS"); // Ensure OPTIONS is allowed
header("Access-Control-Allow-Headers: Content-Type"); // Allow Content-Type header
header('Content-Type: application/json');

// --- IMPORTANT: Handle preflight OPTIONS request first ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Respond with 200 OK for OPTIONS
    exit(); // Terminate script after sending headers for preflight
}
// --- End of OPTIONS handling ---


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'วิธีการเรียกใช้ไม่ถูกต้อง กรุณาใช้ POST method เท่านั้น']);
    exit();
}

require_once 'conn.php'; // Include your database connection file

// Get raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$area_name = $data['area_name'] ?? '';
$user_id = (int) ($data['user_id'] ?? 0);

if (empty($area_name)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุชื่อพื้นที่จัดเก็บที่ต้องการลบ']);
    exit();
}

if ($user_id === 0) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'ไม่มีสิทธิ์ลบพื้นที่จัดเก็บ กรุณาเข้าสู่ระบบใหม่']);
    exit();
}

try {
    // Before deleting the area, you might want to handle items associated with this area.
    // For example, set their area_id to NULL, or to a default area, or delete them.
    // This example will just delete the area. In a real app, you should consider foreign key constraints.

    // ก่อนลบพื้นที่ ตรวจสอบว่ามี item_details ที่ยังใช้งานอยู่หรือไม่
    $check_area_id_sql = "SELECT area_id FROM areas WHERE area_name = :area_name AND user_id = :user_id";
    $stmt_check_area = $conn->prepare($check_area_id_sql);
    $stmt_check_area->bindParam(':area_name', $area_name);
    $stmt_check_area->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_check_area->execute();
    $area_result = $stmt_check_area->fetch(PDO::FETCH_ASSOC);
    
    if (!$area_result) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบพื้นที่จัดเก็บที่ระบุ']);
        exit();
    }
    
    $area_id = $area_result['area_id'];
    
    // ตรวจสอบ item_details ที่ยังใช้งานอยู่ในพื้นที่นี้
    $stmt_check_items = $conn->prepare("SELECT COUNT(*) FROM item_details WHERE area_id = :area_id AND status = 'active'");
    $stmt_check_items->bindParam(':area_id', $area_id, PDO::PARAM_INT);
    $stmt_check_items->execute();
    $active_items_count = $stmt_check_items->fetchColumn();

    if ($active_items_count > 0) {
        http_response_code(409); // Conflict
        echo json_encode([
            'status' => 'error', 
            'message' => "ไม่สามารถลบพื้นที่ \"$area_name\" ได้\n\nเนื่องจากยังมีสิ่งของที่กำลังใช้งานอยู่ $active_items_count รายการ\n\nกรุณาใช้หมด หรือย้ายสิ่งของเหล่านี้ไปพื้นที่อื่นก่อน จึงจะสามารถลบพื้นที่นี้ได้"
        ]);
        exit();
    }


    // Delete the area
    $stmt_delete = $conn->prepare("DELETE FROM areas WHERE area_name = :area_name AND user_id = :user_id");
    $stmt_delete->bindParam(':area_name', $area_name);
    $stmt_delete->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_delete->execute();

    if ($stmt_delete->rowCount() > 0) {
        echo json_encode(['status' => 'success', 'message' => "ลบพื้นที่ \"$area_name\" เรียบร้อยแล้ว"]);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบพื้นที่ที่ต้องการลบ หรืออาจถูกลบไปแล้ว']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดกับฐานข้อมูล: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage()
    ]);
}
?>