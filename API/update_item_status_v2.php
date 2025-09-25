<?php
// update_item_status_v2.php - API ใหม่ที่รองรับโครงสร้างฐานข้อมูลจริง
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    include_once __DIR__ . '/conn.php';
    
    if (!isset($conn) || $conn === null) {
        throw new Exception('Database connection failed');
    }

    // รับข้อมูลจาก request
    $input = file_get_contents('php://input');
    $json_data = json_decode($input, true);

    // รองรับทั้ง JSON และ form data
    if (!empty($json_data)) {
        $item_id = (int) ($json_data['item_id'] ?? 0);
        $user_id = (int) ($json_data['user_id'] ?? 0);
        $quantity_type = $json_data['quantity_type'] ?? '';
        $quantity = (int) ($json_data['quantity'] ?? 0);
        $area_id = (int) ($json_data['area_id'] ?? 0); // optional
        $new_status = $json_data['new_status'] ?? '';
    } else {
        $item_id = (int) ($_POST['item_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $quantity_type = $_POST['quantity_type'] ?? '';
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $area_id = (int) ($_POST['area_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
    }

    // Validate input
    if ($item_id <= 0 || $user_id <= 0) {
        throw new Exception('Invalid item_id or user_id');
    }

    // ตรวจสอบว่า item มีอยู่จริง
    $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ? AND user_id = ?");
    $stmt->execute([$item_id, $user_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        throw new Exception('Item not found or access denied');
    }

    $conn->beginTransaction();

    if (!empty($quantity_type) && $quantity > 0) {
        // === อัปเดตจำนวน (used/expired) ===
        
        if (!in_array($quantity_type, ['used', 'expired'])) {
            throw new Exception('Invalid quantity_type. Must be "used" or "expired"');
        }

        if ($area_id > 0) {
            // อัปเดตในพื้นที่เฉพาะ (item_locations)
            $stmt = $conn->prepare("
                SELECT * FROM item_locations 
                WHERE item_id = ? AND area_id = ?
            ");
            $stmt->execute([$item_id, $area_id]);
            $location = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$location) {
                throw new Exception('Item not found in specified area');
            }

            // ตรวจสอบจำนวนที่เหลือในพื้นที่
            $remaining = $location['quantity_in_area'] - $location['expired_quantity'] - $location['used_quantity'];
            if ($quantity > $remaining) {
                throw new Exception("Insufficient quantity in area. Available: $remaining");
            }

            // อัปเดต item_locations
            $field = $quantity_type . '_quantity';
            $new_quantity = $location[$field] + $quantity;
            
            $stmt = $conn->prepare("
                UPDATE item_locations 
                SET {$field} = ? 
                WHERE item_id = ? AND area_id = ?
            ");
            $stmt->execute([$new_quantity, $item_id, $area_id]);

        } else {
            // อัปเดตในตาราง items (รวมทั้งหมด)
            $remaining = $item['item_number'] - $item['expired_quantity'] - $item['used_quantity'];
            if ($quantity > $remaining) {
                throw new Exception("Insufficient quantity. Available: $remaining");
            }

            $field = $quantity_type . '_quantity';
            $new_quantity = $item[$field] + $quantity;
            
            $stmt = $conn->prepare("
                UPDATE items 
                SET {$field} = ? 
                WHERE item_id = ?
            ");
            $stmt->execute([$new_quantity, $item_id]);
        }

        // ตรวจสอบและอัปเดตสถานะอัตโนมัติ
        $stmt = $conn->prepare("SELECT used_quantity, expired_quantity, remaining_quantity FROM items WHERE item_id = ?");
        $stmt->execute([$item_id]);
        $updated_item = $stmt->fetch();
        
        $auto_status = null;
        if ($updated_item['remaining_quantity'] <= 0) {
            // เลือกสถานะตามสิ่งที่เกิดขึ้นมากกว่า
            if ($updated_item['used_quantity'] > $updated_item['expired_quantity']) {
                $auto_status = 'disposed'; // ใช้หมดมากกว่า
            } else {
                $auto_status = 'expired';  // ทิ้ง/หมดอายุมากกว่า
            }
            
            if ($auto_status) {
                $stmt = $conn->prepare("UPDATE items SET item_status = ? WHERE item_id = ?");
                $stmt->execute([$auto_status, $item_id]);
            }
        }

    } elseif (!empty($new_status)) {
        // === เปลี่ยนสถานะ ===
        
        if (!in_array($new_status, ['active', 'expired', 'disposed'])) {
            throw new Exception('Invalid status. Must be "active", "expired", or "disposed"');
        }

        $stmt = $conn->prepare("UPDATE items SET item_status = ? WHERE item_id = ?");
        $stmt->execute([$new_status, $item_id]);

    } else {
        throw new Exception('No valid operation specified');
    }

    $conn->commit();

    // ดึงข้อมูลล่าสุด
    $stmt = $conn->prepare("
        SELECT i.*, 
               COALESCE(SUM(il.quantity_in_area), 0) as total_distributed,
               COALESCE(SUM(il.remaining_quantity), 0) as total_location_remaining
        FROM items i 
        LEFT JOIN item_locations il ON i.item_id = il.item_id 
        WHERE i.item_id = ? 
        GROUP BY i.item_id
    ");
    $stmt->execute([$item_id]);
    $final_item = $stmt->fetch(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'Item updated successfully',
        'data' => [
            'item_id' => $final_item['item_id'],
            'remaining_quantity' => $final_item['remaining_quantity'],
            'new_status' => $final_item['item_status'],
            'total_distributed' => $final_item['total_distributed'],
            'expired_quantity' => $final_item['expired_quantity'],
            'used_quantity' => $final_item['used_quantity']
        ]
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollBack();
    }
    ob_end_clean();
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}
?>
