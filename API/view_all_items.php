<?php
// view_all_items.php - API สำหรับดูรายละเอียดสินค้าทั้งหมด
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

try {
    include_once __DIR__ . '/conn.php';
    
    if (!isset($conn) || $conn === null) {
        throw new Exception('Database connection failed');
    }

    $user_id = $_GET['user_id'] ?? 2; // default user_id = 2

    // ดึงข้อมูลสินค้าทั้งหมดพร้อมรายละเอียด
    $stmt = $conn->prepare("
        SELECT 
            i.item_id,
            i.item_name,
            i.item_number as original_quantity,
            i.used_quantity,
            i.expired_quantity,
            i.remaining_quantity,
            i.item_status,
            -- แสดงสถานะการใช้งาน
            CASE 
                WHEN i.used_quantity > 0 AND i.expired_quantity > 0 THEN 'ใช้บางส่วน + ทิ้งบางส่วน'
                WHEN i.used_quantity > 0 AND i.expired_quantity = 0 THEN 'ใช้บางส่วน'
                WHEN i.used_quantity = 0 AND i.expired_quantity > 0 THEN 'ทิ้งบางส่วน'
                WHEN i.remaining_quantity = 0 AND i.used_quantity > 0 THEN 'ใช้หมดแล้ว'
                WHEN i.remaining_quantity = 0 AND i.expired_quantity > 0 THEN 'ทิ้งหมดแล้ว'
                WHEN i.remaining_quantity > 0 THEN 'ยังใช้ได้'
                ELSE 'ไม่ทราบสถานะ'
            END as usage_description,
            -- คำนวณเปอร์เซ็นต์
            ROUND((i.used_quantity / i.item_number) * 100, 2) as used_percentage,
            ROUND((i.expired_quantity / i.item_number) * 100, 2) as expired_percentage,
            ROUND((i.remaining_quantity / i.item_number) * 100, 2) as remaining_percentage
        FROM items i 
        WHERE i.user_id = ? 
        ORDER BY 
            CASE 
                WHEN i.remaining_quantity = 0 THEN 1  -- สินค้าหมดขึ้นก่อน
                ELSE 2 
            END,
            i.item_id DESC
    ");
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูล item_details ที่เหลืออยู่
    $stmt2 = $conn->prepare("
        SELECT 
            id.item_id,
            id.detail_id,
            id.expire_date,
            id.area_id,
            a.area_name as detail_location,
            DATEDIFF(id.expire_date, CURDATE()) as days_to_expire,
            CASE 
                WHEN DATEDIFF(id.expire_date, CURDATE()) < 0 THEN 'หมดอายุแล้ว'
                WHEN DATEDIFF(id.expire_date, CURDATE()) <= 7 THEN 'ใกล้หมดอายุ (≤7 วัน)'
                WHEN DATEDIFF(id.expire_date, CURDATE()) <= 30 THEN 'หมดอายุใน 1 เดือน'
                ELSE 'ยังใช้ได้นาน'
            END as expire_status
        FROM item_details id
        LEFT JOIN areas a ON id.area_id = a.area_id
        WHERE id.item_id IN (SELECT i.item_id FROM items i WHERE i.user_id = ?)
        ORDER BY id.item_id, id.expire_date
    ");
    $stmt2->execute([$user_id]);
    $details = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // จัดกลุ่ม details ตาม item_id
    $grouped_details = [];
    foreach ($details as $detail) {
        $grouped_details[$detail['item_id']][] = $detail;
    }

    // รวมข้อมูล
    foreach ($items as &$item) {
        $item['remaining_details'] = $grouped_details[$item['item_id']] ?? [];
        $item['remaining_details_count'] = count($item['remaining_details']);
    }

    // สรุปสถิติ
    $summary = [
        'total_items' => count($items),
        'items_fully_used' => 0,
        'items_fully_expired' => 0,
        'items_partially_used' => 0,
        'items_active' => 0,
        'total_original' => array_sum(array_column($items, 'original_quantity')),
        'total_used' => array_sum(array_column($items, 'used_quantity')),
        'total_expired' => array_sum(array_column($items, 'expired_quantity')),
        'total_remaining' => array_sum(array_column($items, 'remaining_quantity'))
    ];

    foreach ($items as $item) {
        if ($item['remaining_quantity'] == 0 && $item['used_quantity'] > 0) {
            $summary['items_fully_used']++;
        } elseif ($item['remaining_quantity'] == 0 && $item['expired_quantity'] > 0) {
            $summary['items_fully_expired']++;
        } elseif ($item['used_quantity'] > 0 || $item['expired_quantity'] > 0) {
            $summary['items_partially_used']++;
        } else {
            $summary['items_active']++;
        }
    }

    ob_end_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'All items retrieved successfully',
        'server_time' => date('Y-m-d H:i:s'),
        'user_id' => $user_id,
        'summary' => $summary,
        'items' => $items
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(),
        'file' => __FILE__,
        'line' => __LINE__
    ]);
}
?>
