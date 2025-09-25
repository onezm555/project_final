<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Only allow PUT method
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Include database connection
require_once 'conn.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!$input || !isset($input['area_id']) || !isset($input['area_name']) || empty(trim($input['area_name']))) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$area_id = (int)$input['area_id'];
$area_name = trim($input['area_name']);

// Check if area exists
try {
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM areas WHERE area_id = ?");
    $checkStmt->execute([$area_id]);
    
    if ($checkStmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบพื้นที่ที่ต้องการแก้ไข']);
        exit;
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error checking area: ' . $e->getMessage()]);
    exit;
}

// Check if new area name already exists (excluding current area)
try {
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM areas WHERE area_name = ? AND area_id != ?");
    $checkStmt->execute([$area_name, $area_id]);
    
    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'ชื่อพื้นที่นี้มีอยู่ในระบบแล้ว']);
        exit;
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error checking existing area: ' . $e->getMessage()]);
    exit;
}

// Update area
try {
    $stmt = $conn->prepare("UPDATE areas SET area_name = ? WHERE area_id = ?");
    $stmt->execute([$area_name, $area_id]);
    
    // Return success response with updated area data
    echo json_encode([
        'success' => true,
        'message' => 'แก้ไขพื้นที่สำเร็จ',
        'area' => [
            'area_id' => $area_id,
            'area_name' => $area_name,
            'user_id' => 0
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error updating area: ' . $e->getMessage()]);
}
?>
