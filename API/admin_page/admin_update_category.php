<?php
// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once 'conn.php';

// Only allow PUT method for updating
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาใช้ HTTP PUT method สำหรับการอัปเดต'
    ]);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['type_id']) || !isset($input['type_name'])) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณาระบุข้อมูลที่จำเป็น (type_id และ type_name)'
        ]);
        exit();
    }
    
    $type_id = intval($input['type_id']);
    $type_name = trim($input['type_name']);
    $default_image = isset($input['default_image']) ? trim($input['default_image']) : null;
    
    // Validate type_name
    if (empty($type_name)) {
        echo json_encode([
            'success' => false,
            'message' => 'ชื่อหมวดหมู่ไม่สามารถเป็นค่าว่างได้'
        ]);
        exit();
    }
    
    // Check if category exists
    $checkStmt = $conn->prepare("SELECT type_id FROM types WHERE type_id = ?");
    $checkStmt->execute([$type_id]);
    
    if (!$checkStmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบหมวดหมู่ที่ต้องการแก้ไข'
        ]);
        exit();
    }
    
    // Check if the new name already exists (excluding current category)
    $duplicateStmt = $conn->prepare("SELECT type_id FROM types WHERE type_name = ? AND type_id != ?");
    $duplicateStmt->execute([$type_name, $type_id]);
    
    if ($duplicateStmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'ชื่อหมวดหมู่นี้มีอยู่ในระบบแล้ว'
        ]);
        exit();
    }
    
    // Prepare update query
    $updateFields = ["type_name = ?"];
    $params = [$type_name];
    
    if ($default_image !== null) {
        $updateFields[] = "default_image = ?";
        $params[] = $default_image;
    }
    
    $params[] = $type_id;
    
    $sql = "UPDATE types SET " . implode(", ", $updateFields) . " WHERE type_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    // Get updated category data
    $getStmt = $conn->prepare("SELECT * FROM types WHERE type_id = ?");
    $getStmt->execute([$type_id]);
    $updatedCategory = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    // Add full image URL
    if ($updatedCategory) {
        $image_filename = $updatedCategory['default_image'];
        $updatedCategory['default_image_url'] = get_full_image_url($image_filename) ?? $base_image_url . 'default.png';
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'แก้ไขหมวดหมู่สำเร็จ',
        'category' => $updatedCategory
    ]);
    
} catch (PDOException $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการแก้ไขหมวดหมู่: ' . $e->getMessage()
    ]);
}
?>