<?php
// check_area_status.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// --- IMPORTANT: Handle preflight OPTIONS request first ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Respond with 200 OK for OPTIONS
    exit(); 
}


require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// อ่านข้อมูลจาก request
$input = file_get_contents('php://input');
$json_data = json_decode($input, true);

$area_name = $json_data['area_name'] ?? '';
$user_id = (int) ($json_data['user_id'] ?? 0);

// Debug log
error_log("check_area_status.php - Received: area_name='$area_name', user_id=$user_id");

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($area_name) || $user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters (area_name or user_id)']);
    exit();
}

try {
    // ตรวจสอบโครงสร้างตาราง areas ก่อน
    $describe_sql = "DESCRIBE areas";
    $describe_stmt = $conn->prepare($describe_sql);
    $describe_stmt->execute();
    $columns = $describe_stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("check_area_status.php - Areas table structure: " . json_encode($columns));
    
    // ค้นหา area_id จาก area_name
    $find_area_sql = "
        SELECT area_id 
        FROM areas 
        WHERE area_name = :area_name AND user_id = :user_id
        LIMIT 1
    ";
    
    error_log("check_area_status.php - Query: $find_area_sql with area_name='$area_name', user_id=$user_id");
    
    $find_area_stmt = $conn->prepare($find_area_sql);
    $find_area_stmt->bindParam(':area_name', $area_name);
    $find_area_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $find_area_stmt->execute();
    
    $area = $find_area_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$area) {
        echo json_encode(['status' => 'error', 'message' => 'Area not found']);
        exit();
    }
    
    $area_id = $area['area_id'];
    error_log("check_area_status.php - Found area_id: $area_id");
    
    // ตรวจสอบสถานะของ items ในพื้นที่นี้
    $check_sql = "
        SELECT 
            COUNT(*) as total_items,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_items,
            SUM(CASE WHEN status IN ('disposed', 'expired') THEN 1 ELSE 0 END) as disposed_expired_items,
            SUM(CASE WHEN status = 'active' THEN quantity ELSE 0 END) as active_quantity,
            SUM(CASE WHEN status IN ('disposed', 'expired') THEN quantity ELSE 0 END) as disposed_expired_quantity
        FROM item_details 
        WHERE area_id = :area_id
    ";
    
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':area_id', $area_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    $status_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_items = (int) $status_result['total_items'];
    $active_items = (int) $status_result['active_items'];
    $disposed_expired_items = (int) $status_result['disposed_expired_items'];
    $active_quantity = (int) $status_result['active_quantity'];
    $disposed_expired_quantity = (int) $status_result['disposed_expired_quantity'];
    
    error_log("check_area_status.php - Status: total=$total_items, active=$active_items, disposed/expired=$disposed_expired_items, active_qty=$active_quantity");
    
    // ส่งผลลัพธ์กลับ
    echo json_encode([
        'status' => 'success',
        'area_id' => $area_id,
        'total_items' => $total_items,
        'active_items' => $active_items,
        'disposed_expired_items' => $disposed_expired_items,
        'active_quantity' => $active_quantity,
        'disposed_expired_quantity' => $disposed_expired_quantity,
        'can_delete' => $active_items == 0, // สามารถลบได้เมื่อไม่มี active items
        'message' => $active_items > 0 
            ? "พื้นที่นี้มีสิ่งของที่ยังใช้งานอยู่ $active_items รายการ ($active_quantity ชิ้น)"
            : ($disposed_expired_items > 0 
                ? "พื้นที่นี้มีเฉพาะสิ่งของที่ใช้แล้ว/หมดอายุ $disposed_expired_items รายการ ($disposed_expired_quantity ชิ้น)"
                : "พื้นที่นี้ว่างเปล่า สามารถลบได้")
    ]);
    
} catch (PDOException $e) {
    error_log("check_area_status.php - Database error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 
