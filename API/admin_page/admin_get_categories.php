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

try {
    // Query to get all categories
    $stmt = $conn->query("SELECT * FROM types ORDER BY type_name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process each category to get the full image URL
    $processedCategories = array_map(function($category) use ($base_image_url) {
        $image_filename = $category['default_image'];
        // Use the get_full_image_url function from conn.php
        $category['default_image_url'] = get_full_image_url($image_filename) ?? $base_image_url . 'default.png';
        return $category;
    }, $categories);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'ดึงข้อมูลหมวดหมู่สำเร็จ',
        'categories' => $processedCategories,
        'total' => count($processedCategories)
    ]);
    
} catch (PDOException $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลหมวดหมู่: ' . $e->getMessage()
    ]);
}
?>