<?php
// update_individual_item_status.php - API สำหรับอัปเดตสถานะของชิ้นแต่ละรายการ
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
        $detail_id = (int) ($json_data['detail_id'] ?? 0);
        $area_id = (int) ($json_data['area_id'] ?? 0);
        $expire_date = $json_data['expire_date'] ?? '';
        $action = $json_data['action'] ?? '';
    } else {
        $item_id = (int) ($_POST['item_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $detail_id = (int) ($_POST['detail_id'] ?? 0);
        $area_id = (int) ($_POST['area_id'] ?? 0);
        $expire_date = $_POST['expire_date'] ?? '';
        $action = $_POST['action'] ?? '';
    }

    // Validate input
    if ($item_id <= 0 || $user_id <= 0) {
        throw new Exception('Invalid item_id or user_id');
    }

    if (!in_array($action, ['used', 'expired'])) {
        throw new Exception('Invalid action. Must be "used" or "expired"');
    }

    // ตรวจสอบว่า item มีอยู่จริงและเป็นของ user นี้
    $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ? AND user_id = ?");
    $stmt->execute([$item_id, $user_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        throw new Exception('Item not found or access denied');
    }

    $conn->beginTransaction();

    if ($detail_id > 0) {
        // === กรณีที่ระบุ detail_id (ลบจาก item_details) ===
        
        // ตรวจสอบว่า detail มีอยู่จริง
        $stmt = $conn->prepare("
            SELECT * FROM item_details 
            WHERE detail_id = ? AND item_id = ?
        ");
        $stmt->execute([$detail_id, $item_id]);
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$detail) {
            throw new Exception('Item detail not found');
        }

        // ลบ record จาก item_details
        $stmt = $conn->prepare("DELETE FROM item_details WHERE detail_id = ?");
        $stmt->execute([$detail_id]);

        // อัปเดต quantities ในตาราง items
        $quantity_field = $action === 'used' ? 'used_quantity' : 'expired_quantity';
        $stmt = $conn->prepare("
            UPDATE items 
            SET {$quantity_field} = {$quantity_field} + 1 
            WHERE item_id = ?
        ");
        $stmt->execute([$item_id]);

        // อัปเดต item_locations ถ้ามี
        $stmt = $conn->prepare("
            SELECT * FROM item_locations 
            WHERE item_id = ? AND area_id = ?
        ");
        $stmt->execute([$item_id, $detail['area_id']]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($location) {
            $stmt = $conn->prepare("
                UPDATE item_locations 
                SET {$quantity_field} = {$quantity_field} + 1 
                WHERE item_id = ? AND area_id = ?
            ");
            $stmt->execute([$item_id, $detail['area_id']]);
        }

    } elseif ($area_id > 0 && !empty($expire_date)) {
        // === กรณีที่ระบุ area_id และ expire_date (ลบจาก item_details ที่ตรงเงื่อนไข) ===
        
        // หา detail ที่ตรงเงื่อนไข
        $stmt = $conn->prepare("
            SELECT * FROM item_details 
            WHERE item_id = ? AND area_id = ? AND expire_date = ?
            LIMIT 1
        ");
        $stmt->execute([$item_id, $area_id, $expire_date]);
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$detail) {
            throw new Exception('No matching item detail found');
        }

        // ลบ record จาก item_details
        $stmt = $conn->prepare("DELETE FROM item_details WHERE detail_id = ?");
        $stmt->execute([$detail['detail_id']]);

        // อัปเดต quantities ในตาราง items
        $quantity_field = $action === 'used' ? 'used_quantity' : 'expired_quantity';
        $stmt = $conn->prepare("
            UPDATE items 
            SET {$quantity_field} = {$quantity_field} + 1 
            WHERE item_id = ?
        ");
        $stmt->execute([$item_id]);

        // อัปเดต item_locations ถ้ามี
        $stmt = $conn->prepare("
            SELECT * FROM item_locations 
            WHERE item_id = ? AND area_id = ?
        ");
        $stmt->execute([$item_id, $area_id]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($location) {
            $stmt = $conn->prepare("
                UPDATE item_locations 
                SET {$quantity_field} = {$quantity_field} + 1 
                WHERE item_id = ? AND area_id = ?
            ");
            $stmt->execute([$item_id, $area_id]);
        }

    } else {
        throw new Exception('Either detail_id or both area_id and expire_date must be provided');
    }

    // ตรวจสอบและอัปเดตสถานะอัตโนมัติ
    $stmt = $conn->prepare("SELECT remaining_quantity FROM items WHERE item_id = ?");
    $stmt->execute([$item_id]);
    $updated_item = $stmt->fetch();
    
    if ($updated_item['remaining_quantity'] <= 0) {
        $new_status = $action === 'used' ? 'disposed' : 'expired';
        $stmt = $conn->prepare("UPDATE items SET item_status = ? WHERE item_id = ?");
        $stmt->execute([$new_status, $item_id]);
    }

    $conn->commit();

    // ดึงข้อมูลล่าสุด
    $stmt = $conn->prepare("
        SELECT i.*, 
               COUNT(id.detail_id) as remaining_detail_count
        FROM items i 
        LEFT JOIN item_details id ON i.item_id = id.item_id 
        WHERE i.item_id = ? 
        GROUP BY i.item_id
    ");
    $stmt->execute([$item_id]);
    $final_item = $stmt->fetch(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'Individual item updated successfully',
        'data' => [
            'item_id' => $final_item['item_id'],
            'remaining_quantity' => $final_item['remaining_quantity'],
            'remaining_detail_count' => $final_item['remaining_detail_count'],
            'new_status' => $final_item['item_status'],
            'action' => $action
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
