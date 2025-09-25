<?php
// my_items.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include 'conn.php'; //

$current_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$filter_storage_location = isset($_GET['storage_location']) ? trim($_GET['storage_location']) : null;
$filter_category = isset($_GET['category']) ? trim($_GET['category']) : null;
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : null;
$filter_sort_order = isset($_GET['sort_order']) ? trim($_GET['sort_order']) : null;
$filter_search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : null;

// Debug log เพื่อดูค่าที่ได้รับ
error_log("sort_order received: " . ($filter_sort_order ?? 'NULL'));

if ($current_user_id === null) {
    echo json_encode([
        "success" => false,
        "message" => "User ID is required."
    ]);
    exit();
}

try {
    $where_parts = ["i.user_id = :user_id"];
    $params = [':user_id' => $current_user_id];

    // ไม่ต้องใส่ filter status ใน WHERE แล้ว เพราะจะใช้ HAVING
    // Default filter จะถูกจัดการใน HAVING clause

    if ($filter_storage_location) {
        $where_parts[] = "areas.area_name = :storage_location";
        $params[':storage_location'] = $filter_storage_location;
    }
    if ($filter_category) {
        $where_parts[] = "t.type_name = :category";
        $params[':category'] = $filter_category;
    }

    if ($filter_search_query) {
        $where_parts[] = "(i.item_name LIKE :search_query OR i.item_barcode LIKE :search_query_barcode)";
        $params[':search_query'] = '%' . $filter_search_query . '%';
        $params[':search_query_barcode'] = '%' . $filter_search_query . '%';
    }

    $where = implode(" AND ", $where_parts);

    $order_by = "MIN(CASE WHEN id.status = 'active' THEN id.expire_date END) ASC"; 
    if ($filter_sort_order === 'ชื่อ (ก-ฮ)') {
        $order_by = "i.item_name ASC";
    } elseif ($filter_sort_order === 'ชื่อ (ฮ-ก)') {
        $order_by = "i.item_name DESC";
    } elseif ($filter_sort_order === 'วันหมดอายุ (เร็วที่สุด)') {
        $order_by = "MIN(CASE WHEN id.status = 'active' THEN id.expire_date END) ASC";
    } elseif ($filter_sort_order === 'วันหมดอายุ (ช้าที่สุด)') {
        $order_by = "MIN(CASE WHEN id.status = 'active' THEN id.expire_date END) DESC";
    }

    // Debug log เพื่อดู ORDER BY ที่ใช้
    error_log("ORDER BY clause: " . $order_by);

    $sql = "
        SELECT
            i.item_id,
            i.user_id,
            i.item_name,
            i.item_number,
            SUM(CASE WHEN id.status = 'expired' THEN id.quantity ELSE 0 END) as expired_quantity,
            SUM(CASE WHEN id.status = 'disposed' THEN id.quantity ELSE 0 END) as used_quantity,
            SUM(CASE WHEN id.status = 'active' THEN id.quantity ELSE 0 END) as actual_quantity,
            i.item_img,
            COALESCE(MIN(CASE WHEN id.status = 'active' THEN id.expire_date END), i.item_date) as item_date,
            i.item_notification,
            i.item_barcode,
            CASE 
                WHEN COUNT(CASE WHEN id.status = 'active' THEN 1 END) > 0 THEN 'active'
                WHEN COUNT(CASE WHEN id.status = 'expired' THEN 1 END) > 0 THEN 'expired'
                ELSE 'disposed'
            END as item_status,
            i.date_type,
            t.type_name,
            GROUP_CONCAT(DISTINCT areas.area_name ORDER BY areas.area_name SEPARATOR ', ') as storage_locations,
            MIN(CASE WHEN id.status = 'active' THEN id.expire_date END) as nearest_expire_date,
            MAX(id.used_date) as latest_used_date,
            COUNT(DISTINCT CASE WHEN id.status = 'active' THEN id.area_id END) as location_count
        FROM
            items i
        LEFT JOIN types t ON i.type_id = t.type_id
        LEFT JOIN item_details id ON i.item_id = id.item_id
        LEFT JOIN areas ON id.area_id = areas.area_id
        WHERE $where
        GROUP BY i.item_id
        HAVING 
            CASE 
                WHEN '$filter_status' = 'active' THEN COUNT(CASE WHEN id.status = 'active' THEN 1 END) > 0
                WHEN '$filter_status' = 'expired' THEN (
                    COUNT(CASE WHEN id.status = 'active' AND id.expire_date < CURDATE() THEN 1 END) > 0 OR
                    COUNT(CASE WHEN id.status = 'expired' THEN 1 END) > 0
                )
                WHEN '$filter_status' = 'disposed' THEN COUNT(CASE WHEN id.status = 'disposed' THEN 1 END) > 0
                WHEN '$filter_status' = 'all_expired' THEN (COUNT(CASE WHEN id.status = 'expired' THEN 1 END) > 0 OR COUNT(CASE WHEN id.status = 'disposed' THEN 1 END) > 0)
                WHEN '$filter_status' = 'expiring_7_days' THEN COUNT(CASE WHEN id.status = 'active' AND id.expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 END) > 0
                WHEN '$filter_status' = 'expiring_30_days' THEN COUNT(CASE WHEN id.status = 'active' AND id.expire_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) > 0
                ELSE COUNT(CASE WHEN id.status = 'active' THEN 1 END) > 0
            END
        ORDER BY $order_by
    ";

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูล item details แยกต่างหาก
    $item_details = [];
    if (!empty($items)) {
        $item_ids = array_column($items, 'item_id');
        $placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
        
        // ดึงข้อมูล item details (วันหมดอายุแต่ละชิ้น) - ทุกสถานะ
        $details_sql = "
            SELECT 
                id.item_id,
                id.area_id,
                id.expire_date,
                id.barcode,
                id.item_img,
                id.quantity,
                id.status,
                a.area_name
            FROM item_details id
            LEFT JOIN areas a ON id.area_id = a.area_id
            WHERE id.item_id IN ($placeholders)
            ORDER BY id.item_id, id.expire_date
        ";
        $details_stmt = $conn->prepare($details_sql);
        $details_stmt->execute($item_ids);
        $details_data = $details_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // จัดกลุ่มตาม item_id
        foreach ($details_data as $detail) {
            $item_details[$detail['item_id']][] = $detail;
        }
    }

    $formatted_items = [];
    foreach ($items as $item) {
        $item_id = $item['item_id'];
        
        // ตรวจสอบสถานะ - สำหรับ expired/disposed ไม่ต้องข้าม
        // เพราะเราต้องการแสดงรายการเหล่านี้ในหน้า expired items
        
        // สร้าง URL รูปภาพ
        $base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $base_url .= '://' . $_SERVER['HTTP_HOST'];
        $project_path = '/project'; // ปรับตามโครงสร้างโปรเจค
        
        if (!empty($item['item_img'])) {
            // ตรวจสอบว่ารูปภาพเป็น URL เต็มแล้วหรือไม่
            if (strpos($item['item_img'], 'http') === 0) {
                $item_img_full_url_for_flutter = $item['item_img'];
            } else {
                $item_img_full_url_for_flutter = $base_url . $project_path . '/img/' . $item['item_img'];
            }
        } else {
            $item_img_full_url_for_flutter = $base_url . $project_path . '/img/default.png';
        }

        // Process storage locations - ใช้ข้อมูลจาก item_details
        $storage_display = $item['storage_locations'] ?? '';
        $storage_info = [];
        
        // สร้างข้อมูล storage_info จาก item_details
        if (isset($item_details[$item_id]) && !empty($item_details[$item_id])) {
            $area_summary = [];
            foreach ($item_details[$item_id] as $detail) {
                $area_name = $detail['area_name'] ?? 'ไม่ระบุ';
                if (!isset($area_summary[$area_name])) {
                    $area_summary[$area_name] = [
                        'area_name' => $area_name,
                        'area_id' => $detail['area_id'],
                        'total_quantity' => 0,
                        'active_quantity' => 0,
                        'is_main' => false // จะได้จาก is_main_location ในอนาคต
                    ];
                }
                $area_summary[$area_name]['total_quantity'] += $detail['quantity'];
                if ($detail['status'] === 'active') {
                    $area_summary[$area_name]['active_quantity'] += $detail['quantity'];
                }
            }
            $storage_info = array_values($area_summary);
        }
        
        // สร้างข้อความแสดงพื้นที่จัดเก็บ
        if (empty($storage_display)) {
            $storage_display = !empty($storage_info) 
                ? implode(', ', array_column($storage_info, 'area_name'))
                : 'ไม่ระบุ';
        }

        // เพิ่มข้อมูล item details (วันหมดอายุแต่ละชิ้น) ถ้ามี  
        $item_expire_details = [];
        if (isset($item_details[$item_id]) && !empty($item_details[$item_id])) {
            $item_expire_details = $item_details[$item_id];
        }
        
        // ใช้จำนวนจริงจาก SQL query
        $actual_quantity = (int)$item['actual_quantity'];

        $formatted_items[] = [
            'item_id' => $item['item_id'],
            'user_id' => $item['user_id'],
            'item_name' => $item['item_name'],
            'item_number' => $actual_quantity, // ใช้จำนวนจริงจาก active item_details
            'quantity' => $actual_quantity, // เพิ่ม field quantity สำหรับความชัดเจน
            'item_img_full_url' => $item_img_full_url_for_flutter, // เปลี่ยน key ให้ตรงกับ Flutter
            'item_date' => $item['item_date'],
            'item_notification' => $item['item_notification'],
            'item_barcode' => $item['item_barcode'],
            'item_status' => $item['item_status'],
            'date_type' => $item['date_type'],
            'category' => $item['type_name'], // เปลี่ยน key ให้ตรงกับ Flutter
            'storage_location' => $storage_display, // ข้อความรวมสำหรับแสดงผล
            'storage_locations' => $storage_info, // ข้อมูลละเอียดสำหรับ ItemDetailPage
            'item_expire_details' => $item_expire_details, // วันหมดอายุแต่ละชิ้น
            'type_name' => $item['type_name'], // เพิ่มเพื่อ backward compatibility
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $formatted_items
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'file' => 'my_items.php',
        'line' => $e->getLine()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage(),
        'file' => 'my_items.php'
    ]);
}
?>