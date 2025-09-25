<?php
// search_item_by_barcode.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include 'conn.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : null;

if ($user_id === null) {
    echo json_encode([
        "success" => false,
        "message" => "User ID is required."
    ]);
    exit();
}

if (empty($barcode)) {
    echo json_encode([
        "success" => false,
        "message" => "Barcode is required."
    ]);
    exit();
}

try {
    // เพิ่ม debug เพื่อตรวจสอบ parameters
    error_log("Search barcode API called with user_id: $user_id, barcode: $barcode");
    
    // ตรวจสอบการเชื่อมต่อฐานข้อมูล
    if ($conn === null) {
        throw new Exception("Database connection failed");
    }
    
    // ค้นหาสินค้าด้วยรหัสบาร์โค้ดจากตาราง item_details ที่มีโครงสร้างใหม่
    $sql = "
        SELECT DISTINCT
            i.item_name,
            i.date_type,
            t.type_name as category,
            a.area_name as storage_location,
            id.barcode,
            id.item_img
        FROM items i
        INNER JOIN item_details id ON i.item_id = id.item_id
        LEFT JOIN types t ON i.type_id = t.type_id
        LEFT JOIN areas a ON id.area_id = a.area_id
        WHERE i.user_id = :user_id 
        AND (id.barcode = :barcode1 OR i.item_barcode = :barcode2)
        AND id.status = 'active'
        ORDER BY i.item_id DESC
        LIMIT 1
    ";

    error_log("Executing SQL: $sql");
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':barcode1', $barcode, PDO::PARAM_STR);
    $stmt->bindParam(':barcode2', $barcode, PDO::PARAM_STR);
    $stmt->execute();
    
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("Query result: " . print_r($item, true));

    if ($item) {
        // สร้าง URL รูปภาพ
        if (!empty($item['item_img'])) {
            $item_img_full_url = get_full_image_url($item['item_img']);
        } else {
            $item_img_full_url = get_full_image_url();
        }

        $response_data = [
            'item_name' => $item['item_name'] ?? '',
            'item_barcode' => $item['barcode'] ?? '', // ใช้ barcode จาก item_details
            'barcode' => $item['barcode'] ?? '', // เพิ่ม field สำรอง
            'item_img_full_url' => $item_img_full_url,
            'date_type' => $item['date_type'] ?? 'EXP',
            'category' => $item['category'] ?? '',
            'type_name' => $item['category'] ?? '', // เพิ่ม field สำรอง
            'storage_location' => $item['storage_location'] ?? '',
            'area_name' => $item['storage_location'] ?? '', // เพิ่ม field สำรอง
        ];

        error_log("Response data: " . print_r($response_data, true));

        echo json_encode([
            "success" => true,
            "data" => $response_data,
            "message" => "Item found successfully."
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "data" => null,
            "message" => "No item found with this barcode."
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'file' => 'search_item_by_barcode.php',
        'line' => $e->getLine()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage(),
        'file' => 'search_item_by_barcode.php'
    ]);
}
?>
