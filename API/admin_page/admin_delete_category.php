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

// Only allow DELETE method
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาใช้ HTTP DELETE method สำหรับการลบข้อมูล'
    ]);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['type_id']) || empty($input['type_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณาระบุ ID หมวดหมู่ที่ต้องการลบ'
        ]);
        exit();
    }
    
    $type_id = intval($input['type_id']);
    
    // Check which image column exists in the table
    $imageColumn = null;
    $possibleImageColumns = ['default_image', 'type_image', 'image', 'img', 'picture', 'pic', 'photo'];
    $debugInfo = [];
    
    try {
        $allColumnsStmt = $conn->prepare("SHOW COLUMNS FROM types");
        $allColumnsStmt->execute();
        $columns = $allColumnsStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $debugInfo['available_columns'] = $columns;
        
        // Find the image column
        foreach ($possibleImageColumns as $possibleColumn) {
            if (in_array($possibleColumn, $columns)) {
                $imageColumn = $possibleColumn;
                $debugInfo['found_image_column'] = $imageColumn;
                break;
            }
        }
        
        if (!$imageColumn) {
            $debugInfo['image_column_status'] = 'No image column found in: ' . implode(', ', $possibleImageColumns);
        }
        
    } catch (PDOException $e) {
        $debugInfo['column_check_error'] = $e->getMessage();
    }
    
    // Check if category exists and get image info (if image column exists)
    if ($imageColumn) {
        $checkStmt = $conn->prepare("SELECT type_id, type_name, {$imageColumn} as image_filename FROM types WHERE type_id = ?");
    } else {
        $checkStmt = $conn->prepare("SELECT type_id, type_name FROM types WHERE type_id = ?");
    }
    $checkStmt->execute([$type_id]);
    $category = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบหมวดหมู่ที่ต้องการลบ'
        ]);
        exit();
    }
    
    // Check if category is being used in items table
    try {
        $usageCheckStmt = $conn->prepare("SELECT COUNT(*) as usage_count FROM items WHERE type_id = ?");
        $usageCheckStmt->execute([$type_id]);
        $usageResult = $usageCheckStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usageResult['usage_count'] > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ไม่สามารถลบได้เนื่องจากผู้ใช้มีสินค้า ' . $usageResult['usage_count'] . ' รายการในหมวดหมู่นี้',
                'usage_count' => $usageResult['usage_count']
            ]);
            exit();
        }
    } catch (PDOException $e) {
        // หากตาราง items ไม่มี ให้ลองตรวจสอบตารางอื่น หรือข้ามการตรวจสอบ
        // สำหรับตอนนี้ให้ข้ามการตรวจสอบการใช้งาน
        error_log("Warning: Could not check item usage - " . $e->getMessage());
    }
    
    // Function to delete image file
    function deleteImageFile($imageFilename, &$debugInfo) {
        $debugInfo['delete_attempt'] = $imageFilename;
        
        if (!empty($imageFilename)) {
            // Remove any URL prefix and get just the filename
            $filename = basename($imageFilename);
            
            // Remove any URL parameters or fragments
            $filename = explode('?', $filename)[0];
            $filename = explode('#', $filename)[0];
            
            $debugInfo['cleaned_filename'] = $filename;
            
            // Try multiple possible paths
            $possiblePaths = [
                'C:\\xampp\\htdocs\\project\\img\\' . $filename,
                'C:/xampp/htdocs/project/img/' . $filename,
                __DIR__ . '/../img/' . $filename,
                __DIR__ . '/img/' . $filename
            ];
            
            $debugInfo['checked_paths'] = [];
            
            foreach ($possiblePaths as $fullPath) {
                $pathInfo = [
                    'path' => $fullPath,
                    'exists' => file_exists($fullPath)
                ];
                
                if (file_exists($fullPath)) {
                    $pathInfo['delete_attempt'] = true;
                    if (unlink($fullPath)) {
                        $pathInfo['deleted'] = true;
                        $debugInfo['checked_paths'][] = $pathInfo;
                        $debugInfo['result'] = 'success';
                        return true;
                    } else {
                        $pathInfo['deleted'] = false;
                        $pathInfo['error'] = 'unlink failed';
                        $debugInfo['checked_paths'][] = $pathInfo;
                        $debugInfo['result'] = 'unlink_failed';
                        return false;
                    }
                }
                
                $debugInfo['checked_paths'][] = $pathInfo;
            }
            
            $debugInfo['result'] = 'file_not_found';
            return false;
        }
        
        $debugInfo['result'] = 'no_filename';
        return true; // No image to delete
    }
    
    // Delete the category
    $deleteStmt = $conn->prepare("DELETE FROM types WHERE type_id = ?");
    $deleteStmt->execute([$type_id]);
    
    // Check if deletion was successful
    if ($deleteStmt->rowCount() > 0) {
        $debugInfo['category_data'] = $category;
        $debugInfo['image_column'] = $imageColumn;
        
        // Try to delete the associated image file (only if image column exists)
        $imageDeleted = true;
        if ($imageColumn && !empty($category['image_filename'])) {
            $debugInfo['attempting_image_delete'] = true;
            $imageDeleted = deleteImageFile($category['image_filename'], $debugInfo);
        } else {
            $debugInfo['image_delete_skipped'] = [
                'has_image_column' => $imageColumn ? true : false,
                'has_image_filename' => isset($category['image_filename']) && !empty($category['image_filename'])
            ];
        }
        
        $response = [
            'success' => true,
            'message' => 'ลบหมวดหมู่ "' . $category['type_name'] . '" สำเร็จ',
            'deleted_category' => $category,
            'debug_info' => $debugInfo
        ];
        
        // Add image deletion status to response (only if image column exists)
        if ($imageColumn && !empty($category['image_filename'])) {
            if ($imageDeleted) {
                $response['message'] .= ' และลบรูปภาพเรียบร้อย';
                $response['image_deleted'] = true;
            } else {
                $response['message'] .= ' แต่ไม่สามารถลบรูปภาพได้';
                $response['image_deleted'] = false;
            }
        }
        
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในการลบหมวดหมู่'
        ]);
    }
    
} catch (PDOException $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการลบหมวดหมู่: ' . $e->getMessage()
    ]);
}
?>