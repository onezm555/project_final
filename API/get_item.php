 <?php
// get_item.php - API สำหรับดึงข้อมูลของ item เดียว
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include 'conn.php';

// รับค่า item_id และ user_id
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if ($item_id === null || $user_id === null) {
    echo json_encode([
        "success" => false,
        "message" => "Item ID and User ID are required."
    ]);
    exit();
}

try {
    // Query สำหรับดึงข้อมูล item หลัก รวมกับข้อมูลจาก item_details
    $sql = "
        SELECT
            i.item_id,
            i.user_id,
            i.item_name,
            i.item_number,
            i.item_img,
            i.item_date,
            i.item_notification,
            i.item_barcode,
            i.item_status,
            i.date_type,
            i.type_id,
            t.type_name as category,
            -- ใช้ข้อมูลพื้นที่แรกจาก item_details แทน
            (SELECT a2.area_name FROM item_details id2 
             LEFT JOIN areas a2 ON id2.area_id = a2.area_id 
             WHERE id2.item_id = i.item_id AND id2.status = 'active'
             ORDER BY id2.created_at ASC LIMIT 1) as storage_location,
            -- คำนวณข้อมูลจาก item_details เป็นหลัก
            COALESCE(SUM(CASE WHEN id.status = 'active' THEN id.quantity ELSE 0 END), 0) as active_quantity,
            COALESCE(SUM(CASE WHEN id.status = 'disposed' THEN id.quantity ELSE 0 END), 0) as used_quantity,
            COALESCE(SUM(CASE WHEN id.status = 'expired' THEN id.quantity ELSE 0 END), 0) as expired_quantity,
            COUNT(DISTINCT CASE WHEN id.status = 'active' THEN id.area_id END) as location_count,
            MIN(CASE WHEN id.status = 'active' THEN id.expire_date END) as earliest_expire_date
        FROM
            items i
        LEFT JOIN types t ON i.type_id = t.type_id
        LEFT JOIN item_details id ON i.item_id = id.item_id
        WHERE 
            i.item_id = :item_id
            AND i.user_id = :user_id
        GROUP BY i.item_id
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':item_id', $item_id);
    $stmt->bindValue(':user_id', $user_id);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        echo json_encode([
            "success" => false,
            "message" => "Item not found or not accessible."
        ]);
        exit();
    }

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

    // ดึงข้อมูล item_details พร้อมข้อมูลการแจ้งเตือน (ทุกสถานะ)
    $details_sql = "
        SELECT 
            id.detail_id as id,
            id.detail_id as item_detail_id, -- เพิ่ม alias นี้สำหรับ Dart code
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
        WHERE id.item_id = :item_id
        ORDER BY id.status ASC, id.expire_date ASC
    ";
    $details_stmt = $conn->prepare($details_sql);
    $details_stmt->bindValue(':item_id', $item_id);
    $details_stmt->execute();
    $item_expire_details_raw = $details_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ประมวลผล item_expire_details เพื่อเพิ่ม notification_days หากเป็น NULL
    $item_expire_details = [];
    foreach ($item_expire_details_raw as $detail) {
        // หาก notification_days เป็น NULL ให้ใช้ค่าจาก items.item_notification
        if ($detail['notification_days'] === null) {
            $detail['notification_days'] = $item['item_notification'];
        }
        
        $item_expire_details[] = $detail;
    }

    // ดึงข้อมูลพื้นที่เก็บจาก item_details แทน item_locations
    $locations_sql = "
        SELECT 
            id.area_id,
            SUM(CASE WHEN id.status = 'active' THEN id.quantity ELSE 0 END) as quantity_in_area,
            0 as is_main,
            a.area_name
        FROM item_details id
        LEFT JOIN areas a ON id.area_id = a.area_id
        WHERE id.item_id = :item_id
        GROUP BY id.area_id, a.area_name
        HAVING quantity_in_area > 0
        ORDER BY a.area_name ASC
    ";
    $locations_stmt = $conn->prepare($locations_sql);
    $locations_stmt->bindValue(':item_id', $item_id);
    $locations_stmt->execute();
    $storage_locations = $locations_stmt->fetchAll(PDO::FETCH_ASSOC);

    // สร้าง storage_info string
    $storage_info_parts = [];
    foreach ($storage_locations as $location) {
        if ($location['area_name'] && $location['quantity_in_area'] > 0) {
            $storage_info_parts[] = $location['area_name'] . ' (' . $location['quantity_in_area'] . ' ชิ้น)';
        }
    }
    $storage_info = implode(', ', $storage_info_parts);

    // จัดรูปแบบข้อมูลส่งกลับ
    $item_data = [
        'item_id' => $item['item_id'],
        'user_id' => $item['user_id'],
        'item_name' => $item['item_name'],
        'name' => $item['item_name'], // alias
        'item_number' => $item['item_number'],
        'quantity' => $item['active_quantity'], // ใช้จำนวนจริงจาก item_details
        'remaining_quantity' => $item['active_quantity'],
        'used_quantity' => $item['used_quantity'],
        'expired_quantity' => $item['expired_quantity'],
        'item_img' => $item['item_img'],
        'item_img_full_url' => $item_img_full_url,
        'item_date' => $item['earliest_expire_date'] ?? $item['item_date'], // ใช้วันหมดอายุที่เร็วที่สุด
        'item_notification' => $item['item_notification'],
        'notification_days' => $item['item_notification'],
        'item_barcode' => $item['item_barcode'],
        'barcode' => $item['item_barcode'],
        'item_status' => $item['item_status'],
        'date_type' => $item['date_type'],
        'unit' => $item['date_type'],
        'type_id' => $item['type_id'],
        // ไม่ส่ง area_id จากตาราง items เพราะไม่ได้ใช้แล้ว
        'category' => $item['category'],
        'type_name' => $item['category'],
        'storage_location' => $item['storage_location'],
        'area_name' => $item['storage_location'],
        'location_count' => $item['location_count'],
        'storage_info' => $storage_info, // ใช้ storage_info ที่สร้างขึ้น
        'storage_locations' => $storage_locations,
        'item_expire_details' => $item_expire_details
    ];

    // Debug: เพิ่ม logging
    error_log("DEBUG get_item.php: item_id=$item_id, user_id=$user_id");
    error_log("DEBUG get_item.php: item_expire_details count=" . count($item_expire_details));
    error_log("DEBUG get_item.php: item_expire_details=" . json_encode($item_expire_details));
    
    echo json_encode([
        "success" => true,
        "data" => $item_data,
        "active_details_count" => count($item_expire_details),
        "total_details_found" => count($item_expire_details_raw),
        "fetch_time" => date('Y-m-d H:i:s'),
        "debug_sql_query" => "SELECT * FROM item_details WHERE item_id = $item_id ORDER BY status ASC, expire_date ASC"
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
