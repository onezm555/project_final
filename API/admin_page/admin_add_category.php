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

// Only allow POST method for adding
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาใช้ HTTP POST method สำหรับการเพิ่มข้อมูล'
    ]);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['type_name']) || empty(trim($input['type_name']))) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณาระบุชื่อหมวดหมู่'
        ]);
        exit();
    }
    
    $type_name = trim($input['type_name']);
    $default_image = isset($input['default_image']) ? trim($input['default_image']) : 'default.png';
    
    // Check if category name already exists
    $duplicateStmt = $conn->prepare("SELECT type_id FROM types WHERE type_name = ?");
    $duplicateStmt->execute([$type_name]);
    
    if ($duplicateStmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'ชื่อหมวดหมู่นี้มีอยู่ในระบบแล้ว'
        ]);
        exit();
    }
    
    // Insert new category
    $insertStmt = $conn->prepare("INSERT INTO types (type_name, default_image) VALUES (?, ?)");
    $insertStmt->execute([$type_name, $default_image]);
    
    // Get the new category ID
    $newCategoryId = $conn->lastInsertId();
    
    // Get the newly created category data
    $getStmt = $conn->prepare("SELECT * FROM types WHERE type_id = ?");
    $getStmt->execute([$newCategoryId]);
    $newCategory = $getStmt->fetch(PDO::FETCH_ASSOC);
    
    // Add full image URL
    if ($newCategory) {
        $image_filename = $newCategory['default_image'];
        $newCategory['default_image_url'] = get_full_image_url($image_filename) ?? $base_image_url . 'default.png';
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'เพิ่มหมวดหมู่สำเร็จ',
        'category' => $newCategory
    ]);
    
} catch (PDOException $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการเพิ่มหมวดหมู่: ' . $e->getMessage()
    ]);
}
?>