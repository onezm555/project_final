<?php
// calendar_items.php - API สำหรับดึงข้อมูลสิ่งของสำหรับ Calendar
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include 'conn.php';

$current_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if ($current_user_id === null) {
    echo json_encode([
        "success" => false,
        "message" => "User ID is required."
    ]);
    exit();
}

try {
    // Query สำหรับดึงข้อมูลจาก item_details ที่ active
    $sql = "
        SELECT DISTINCT
            i.item_id,
            i.user_id,
            i.item_name,
            i.item_number,
            i.item_img,
            i.item_barcode,
            i.item_status,
            i.date_type,
            t.type_name as category,
            id.expire_date as item_date,
            id.notification_days,
            a.area_name as storage_location,
            DATEDIFF(id.expire_date, CURDATE()) as days_left,
            -- คำนวณจำนวนต่างๆ จาก item_details
            COALESCE(SUM(CASE WHEN id.status = 'active' THEN id.quantity ELSE 0 END), 0) as remaining_quantity,
            COALESCE(SUM(CASE WHEN id.status = 'disposed' THEN id.quantity ELSE 0 END), 0) as used_quantity,
            COALESCE(SUM(CASE WHEN id.status = 'expired' THEN id.quantity ELSE 0 END), 0) as expired_quantity
        FROM
            items i
        INNER JOIN item_details id ON i.item_id = id.item_id
        LEFT JOIN types t ON i.type_id = t.type_id
        LEFT JOIN areas a ON id.area_id = a.area_id
        WHERE 
            i.user_id = :user_id 
            AND i.item_status = 'active'
            AND id.status = 'active'
        GROUP BY i.item_id, i.user_id, i.item_name, i.item_number, i.item_img, i.item_barcode, i.item_status, i.date_type, t.type_name, id.expire_date, id.notification_days, a.area_name
        HAVING remaining_quantity > 0
        ORDER BY id.expire_date ASC
    ";

    error_log("Calendar API - SQL Query: " . $sql);

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':user_id', $current_user_id);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Calendar API - Found " . count($items) . " items");

    $calendar_items = [];

    foreach ($items as $item) {
        error_log("Calendar API - Processing item ID: " . $item['item_id']);
        
        // สร้าง URL รูปภาพแบบเต็ม
        $item_img_full_url = null;
        if (!empty($item['item_img'])) {
            // ถ้าเป็น URL เต็มแล้ว
            if (strpos($item['item_img'], 'http') === 0) {
                $item_img_full_url = $item['item_img'];
            } else {
                // เติม base URL
                $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
                $script_dir = dirname($_SERVER['SCRIPT_NAME']);
                $item_img_full_url = $base_url . $script_dir . '/img/' . $item['item_img'];
            }
        }

        // ดึงข้อมูล item_details ทั้งหมดของ item นี้
        $item_expire_details = [];
        $details_sql = "
            SELECT 
                id.detail_id as id,
                id.item_id,
                id.area_id,
                id.expire_date,
                id.quantity,
                id.barcode,
                id.item_img,
                id.notification_days,
                id.status,
                a.area_name
            FROM item_details id
            LEFT JOIN areas a ON id.area_id = a.area_id
            WHERE id.item_id = :item_id AND id.status = 'active'
            ORDER BY id.expire_date ASC
        ";
        $details_stmt = $conn->prepare($details_sql);
        $details_stmt->bindValue(':item_id', $item['item_id']);
        $details_stmt->execute();
        $item_expire_details_raw = $details_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ประมวลผล item_expire_details เพื่อเพิ่ม notification_days หากเป็น NULL
        foreach ($item_expire_details_raw as $detail) {
            // หาก notification_days เป็น NULL ให้ใช้ค่าเริ่มต้น 3 วัน
            if ($detail['notification_days'] === null) {
                $detail['notification_days'] = 3;
            }
            $item_expire_details[] = $detail;
        }

        // ดึงข้อมูลพื้นที่การเก็บจาก item_details
        $storage_locations = [];
        $locations_sql = "
            SELECT 
                id.area_id,
                SUM(id.quantity) as quantity_in_area,
                0 as is_main,
                a.area_name
            FROM item_details id
            LEFT JOIN areas a ON id.area_id = a.area_id
            WHERE id.item_id = :item_id AND id.status = 'active'
            GROUP BY id.area_id, a.area_name
            ORDER BY a.area_name ASC
        ";
        $locations_stmt = $conn->prepare($locations_sql);
        $locations_stmt->bindValue(':item_id', $item['item_id']);
        $locations_stmt->execute();
        $storage_locations = $locations_stmt->fetchAll(PDO::FETCH_ASSOC);

        $calendar_item = [
            'item_id' => $item['item_id'],
            'user_id' => $item['user_id'],
            'item_name' => $item['item_name'],
            'name' => $item['item_name'], // alias
            'item_number' => $item['item_number'],
            'quantity' => $item['remaining_quantity'],
            'remaining_quantity' => $item['remaining_quantity'],
            'used_quantity' => $item['used_quantity'],
            'expired_quantity' => $item['expired_quantity'],
            'item_img' => $item['item_img'],
            'item_img_full_url' => $item_img_full_url,
            'item_date' => $item['item_date'],
            'item_notification' => $item['notification_days'] ?? 3,
            'notification_days' => $item['notification_days'] ?? 3,
            'item_barcode' => $item['item_barcode'],
            'barcode' => $item['item_barcode'],
            'item_status' => $item['item_status'],
            'date_type' => $item['date_type'] ?? 'EXP',
            'unit' => $item['date_type'] ?? 'EXP',
            'category' => $item['category'],
            'type_name' => $item['category'],
            'storage_location' => $item['storage_location'],
            'area_name' => $item['storage_location'],
            'days_left' => $item['days_left'],
            'storage_locations' => $storage_locations,
            'item_expire_details' => $item_expire_details
        ];

        $calendar_items[] = $calendar_item;
    }

    error_log("Calendar API - Successfully processed " . count($calendar_items) . " calendar items");

    echo json_encode([
        "success" => true,
        "data" => $calendar_items,
        "total_items" => count($calendar_items),
        "fetch_time" => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage()
    ]);
}
?>
