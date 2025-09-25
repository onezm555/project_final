<?php
// notification_check.php - API สำหรับตรวจสอบและส่งการแจ้งเตือนสินค้าใกล้หมดอายุ
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include 'conn.php';

// ฟังก์ชันสำหรับสร้าง URL รูปภาพแบบเต็ม
function get_full_image_url($image_path) {
    if (empty($image_path)) {
        return null;
    }
    
    // ถ้ามี http:// หรือ https:// แล้ว ให้ return ตรงๆ
    if (strpos($image_path, 'http://') === 0 || strpos($image_path, 'https://') === 0) {
        return $image_path;
    }
    
    // สร้าง base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_url = $protocol . '://' . $host . '/project/';
    
    // ลบ slash ที่ซ้ำ
    $image_path = ltrim($image_path, '/');
    
    return $base_url . $image_path;
}

$current_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$check_only = isset($_GET['check_only']) ? filter_var($_GET['check_only'], FILTER_VALIDATE_BOOLEAN) : false;

if ($current_user_id === null) {
    echo json_encode([
        "success" => false,
        "message" => "User ID is required."
    ]);
    exit();
}

try {
    // Query สำหรับดึงสินค้าจากตาราง item_details ที่ยังใช้งานได้ (active)
    $sql = "
        SELECT
            i.item_id,
            i.user_id,
            i.item_name,
            i.item_img,
            i.date_type,
            i.item_status,
            t.type_name,
            a.area_name,
            d.detail_id,
            d.expire_date,
            d.quantity,
            d.notification_days,
            d.status as detail_status,
            DATEDIFF(d.expire_date, CURDATE()) as days_left,
            DATE_SUB(d.expire_date, INTERVAL COALESCE(d.notification_days, i.item_notification, 7) DAY) as notify_date
        FROM
            item_details d
        INNER JOIN items i ON d.item_id = i.item_id
        LEFT JOIN types t ON i.type_id = t.type_id
        LEFT JOIN areas a ON d.area_id = a.area_id
        WHERE 
            i.user_id = :user_id 
            AND i.item_status = 'active'
            AND d.status = 'active'
            AND COALESCE(d.notification_days, i.item_notification, 0) > 0
        ORDER BY d.expire_date ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':user_id', $current_user_id);
    $stmt->execute();
    $item_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notifications = [];
    $current_date = new DateTime();

    foreach ($item_details as $detail) {
        $expire_date = new DateTime($detail['expire_date']);
        $notification_days = (int)$detail['notification_days'];
        $notify_date = new DateTime($detail['notify_date']);
        $days_left = (int)$detail['days_left'];
        
        // กำหนดวันที่ปัจจุบันในเขตเวลาท้องถิ่น
        $current_date_only = new DateTime($current_date->format('Y-m-d'));
        $notify_date_only = new DateTime($notify_date->format('Y-m-d'));
        $expire_date_only = new DateTime($expire_date->format('Y-m-d'));
        
        // ตรวจสอบว่าถึงเวลาแจ้งเตือนแล้วหรือยัง
        // แจ้งเตือนตั้งแต่วันที่กำหนดจนถึงวันหมดอายุ
        $should_notify = false;
        
        if ($days_left < 0) {
            // สินค้าหมดอายุแล้ว - แจ้งเตือนต่อไปอีก 3 วัน
            $days_expired = abs($days_left);
            if ($days_expired <= 3) {
                $should_notify = true;
            }
        } else if ($current_date_only >= $notify_date_only && $current_date_only <= $expire_date_only) {
            // สินค้ายังไม่หมดอายุ แต่ถึงเวลาแจ้งเตือนแล้ว
            $should_notify = true;
        }
        
        // ตรวจสอบเพิ่มเติม: ถ้าเป็นวันหมดอายุพอดี ให้แจ้งเตือนแน่นอน
        if ($days_left == 0) {
            $should_notify = true;
        }
        
        if ($should_notify) {
            $item_img_full_url = get_full_image_url($detail['item_img']);
            if (empty($item_img_full_url)) {
                $item_img_full_url = 'assets/images/default.png';
            }

            $notification_data = [
                'item_id' => $detail['item_id'],
                'detail_id' => $detail['detail_id'],
                'user_id' => $detail['user_id'],
                'item_name' => $detail['item_name'],
                'quantity' => $detail['quantity'],
                'item_img_full_url' => $item_img_full_url,
                'expire_date' => $detail['expire_date'],
                'notification_days' => $notification_days,
                'date_type' => $detail['date_type'],
                'category' => $detail['type_name'],
                'storage_location' => $detail['area_name'],
                'days_left' => $days_left,
                'notify_date' => $detail['notify_date'],
                'notification_type' => $days_left <= 0 ? 'expired' : 'expiring',
                'notification_title' => $days_left <= 0 ? 'สินค้าหมดอายุแล้ว' : 'สินค้าใกล้หมดอายุ',
                'notification_message' => $days_left < 0 
                    ? $detail['item_name'] . ' (' . $detail['quantity'] . ' ชิ้น) หมดอายุแล้ว ' . abs($days_left) . ' วัน ที่จัดเก็บ: ' . $detail['area_name']
                    : ($days_left == 0 
                        ? $detail['item_name'] . ' (' . $detail['quantity'] . ' ชิ้น) หมดอายุวันนี้! ที่จัดเก็บ: ' . $detail['area_name']
                        : $detail['item_name'] . ' (' . $detail['quantity'] . ' ชิ้น) จะหมดอายุในอีก ' . $days_left . ' วัน ที่จัดเก็บ: ' . $detail['area_name']),
                'urgency_level' => $days_left <= 0 ? 'high' : ($days_left <= 3 ? 'medium' : 'low'),
                'created_at' => $current_date->format('Y-m-d H:i:s')
            ];

            $notifications[] = $notification_data;

            // ถ้าไม่ใช่แค่ check_only ให้ส่งการแจ้งเตือนจริง
            if (!$check_only) {
                // TODO: เพิ่ม FCM notification ตรงนี้
                // sendFCMNotification($user_fcm_token, $notification_data);
            }
        }
    }

    // สถิติการแจ้งเตือน
    $expired_count = count(array_filter($notifications, function($n) {
        return $n['notification_type'] === 'expired';
    }));
    
    $expiring_count = count(array_filter($notifications, function($n) {
        return $n['notification_type'] === 'expiring';
    }));

    echo json_encode([
        "success" => true,
        "data" => $notifications,
        "summary" => [
            "total_notifications" => count($notifications),
            "expired_items" => $expired_count,
            "expiring_items" => $expiring_count,
            "total_details_checked" => count($item_details)
        ],
        "check_time" => $current_date->format('Y-m-d H:i:s'),
        "current_date" => $current_date->format('Y-m-d'),
        "debug_info" => [
            "details_found" => count($item_details),
            "notifications_generated" => count($notifications)
        ]
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

// ฟังก์ชันส่ง FCM Notification (สำหรับอนาคต)
function sendFCMNotification($fcm_token, $notification_data) {
    // TODO: เพิ่ม Firebase Cloud Messaging ตรงนี้
    // $serverKey = 'YOUR_FIREBASE_SERVER_KEY';
    // 
    // $url = 'https://fcm.googleapis.com/fcm/send';
    // 
    // $notification = [
    //     'title' => $notification_data['notification_title'],
    //     'body' => $notification_data['notification_message'],
    //     'sound' => 'default'
    // ];
    // 
    // $fields = [
    //     'to' => $fcm_token,
    //     'notification' => $notification,
    //     'data' => [
    //         'item_id' => $notification_data['item_id'],
    //         'type' => $notification_data['notification_type']
    //     ]
    // ];
    // 
    // $headers = [
    //     'Authorization: key=' . $serverKey,
    //     'Content-Type: application/json'
    // ];
    // 
    // $ch = curl_init();
    // curl_setopt($ch, CURLOPT_URL, $url);
    // curl_setopt($ch, CURLOPT_POST, true);
    // curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    // 
    // $result = curl_exec($ch);
    // curl_close($ch);
    // 
    // return json_decode($result, true);
    
    return ['success' => true, 'message' => 'FCM not implemented yet'];
}
?>
