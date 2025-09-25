<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Only allow DELETE method
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Include database connection
require_once 'conn.php';

// Get area_id from URL parameter or JSON input
$area_id = null;

// Try to get from URL parameter first
if (isset($_GET['area_id'])) {
    $area_id = (int)$_GET['area_id'];
} else {
    // Try to get from JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['area_id'])) {
        $area_id = (int)$input['area_id'];
    }
}

// Validate input
if (!$area_id || $area_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ไม่พบ ID ของพื้นที่ที่ต้องการลบ']);
    exit;
}

// Check if area exists
try {
    $checkStmt = $conn->prepare("SELECT area_name FROM areas WHERE area_id = ?");
    $checkStmt->execute([$area_id]);
    $area = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$area) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบพื้นที่ที่ต้องการลบ']);
        exit;
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error checking area: ' . $e->getMessage()]);
    exit;
}

// เช็คว่ามีสิ่งของในพื้นที่นี้หรือไม่ (จากตาราง item_details)
try {
    // ตรวจสอบว่ามีสิ่งของที่ยังคงอยู่ในพื้นที่นี้หรือไม่ (status = 'active')
    $checkItemDetailsStmt = $conn->prepare("SELECT COUNT(*) FROM item_details WHERE area_id = ? AND status = 'active'");
    $checkItemDetailsStmt->execute([$area_id]);
    
    $activeItemsCount = $checkItemDetailsStmt->fetchColumn();
    
    if ($activeItemsCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "ไม่สามารถลบพื้นที่ได้ เนื่องจากมีสิ่งของที่ยังคงใช้งานอยู่ในพื้นที่นี้ จำนวน {$activeItemsCount} รายการ"
        ]);
        exit;
    }
    
    // เช็คสิ่งของทั้งหมดในพื้นที่ (รวมทุก status)
    $checkAllItemsStmt = $conn->prepare("SELECT COUNT(*) FROM item_details WHERE area_id = ?");
    $checkAllItemsStmt->execute([$area_id]);
    
    $totalItemsCount = $checkAllItemsStmt->fetchColumn();
    
    if ($totalItemsCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "ไม่สามารถลบพื้นที่ได้ เนื่องจากมีข้อมูลสิ่งของในพื้นที่นี้ จำนวน {$totalItemsCount} รายการ (รวมที่ใช้หมดแล้วและหมดอายุ)"
        ]);
        exit;
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error checking items in area: ' . $e->getMessage()]);
    exit;
}

// เช็คว่ามีผู้ใช้ที่เชื่อมโยงกับพื้นที่นี้หรือไม่
try {
    // สมมติว่ามีตาราง users ที่มี area_id เป็น foreign key
    $checkUsersStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE area_id = ?");
    $checkUsersStmt->execute([$area_id]);
    
    $usersCount = $checkUsersStmt->fetchColumn();
    
    if ($usersCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "ไม่สามารถลบพื้นที่ได้ เนื่องจากมีผู้ใช้ที่เชื่อมโยงกับพื้นที่นี้ จำนวน {$usersCount} คน"
        ]);
        exit;
    }
    
} catch(PDOException $e) {
    // หากไม่มี area_id ใน users table หรือไม่มีตาราง users ให้ข้ามการเช็คนี้
    // echo json_encode(['success' => false, 'message' => 'Error checking users in area: ' . $e->getMessage()]);
}

try {
    $stmt = $conn->prepare("DELETE FROM areas WHERE area_id = ?");
    $stmt->execute([$area_id]);
    
    // Check if any row was actually deleted
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'ลบพื้นที่สำเร็จ',
            'deleted_area' => [
                'area_id' => $area_id,
                'area_name' => $area['area_name']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถลบพื้นที่ได้']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error deleting area: ' . $e->getMessage()]);
}
?>
