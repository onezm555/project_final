<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Include database connection
require_once 'conn.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input || !isset($input['area_name']) || empty(trim($input['area_name']))) {
    echo json_encode(['success' => false, 'message' => 'ชื่อพื้นที่เป็นข้อมูลที่จำเป็น']);
    exit;
}

$area_name = trim($input['area_name']);

// Check if area name already exists
try {
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM areas WHERE area_name = ?");
    $checkStmt->execute([$area_name]);
    
    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'ชื่อพื้นที่นี้มีอยู่ในระบบแล้ว']);
        exit;
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error checking existing area: ' . $e->getMessage()]);
    exit;
}

// Insert new area with user_id = 0
try {
    $stmt = $conn->prepare("INSERT INTO areas (area_name, user_id) VALUES (?, 0)");
    $stmt->execute([$area_name]);
    
    $area_id = $conn->lastInsertId();
    
    // Return success response with the new area data
    echo json_encode([
        'success' => true,
        'message' => 'เพิ่มพื้นที่สำเร็จ',
        'area' => [
            'area_id' => (int)$area_id,
            'area_name' => $area_name,
            'user_id' => 0
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error adding area: ' . $e->getMessage()]);
}
?>
