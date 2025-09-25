<?php
// update_item_detail_by_criteria.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// อ่านข้อมูลจาก request
$input = file_get_contents('php://input');
$json_data = json_decode($input, true);

$item_id = (int) ($json_data['item_id'] ?? 0);
$user_id = (int) ($json_data['user_id'] ?? 0);
$area_id = (int) ($json_data['area_id'] ?? 0);
$expire_date = $json_data['expire_date'] ?? '';
$new_status = $json_data['new_status'] ?? '';
$quantity_to_update = (int) ($json_data['quantity_to_update'] ?? 1);

// ตรวจสอบข้อมูลที่จำเป็น
if ($item_id <= 0 || $user_id <= 0 || $area_id <= 0 || empty($expire_date) || empty($new_status)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit();
}

// ตรวจสอบ new_status ที่ยอมรับได้
if (!in_array($new_status, ['disposed', 'expired'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid status']);
    exit();
}

try {
    $conn->beginTransaction();
    
    // Debug log
    error_log("update_item_detail_by_criteria.php - Parameters: item_id=$item_id, user_id=$user_id, area_id=$area_id, expire_date=$expire_date, new_status=$new_status, quantity_to_update=$quantity_to_update");
    
    // ค้นหา detail_id ที่ตรงกับเงื่อนไข
    $find_sql = "
        SELECT detail_id, quantity, status
        FROM item_details 
        WHERE item_id = :item_id 
        AND area_id = :area_id 
        AND DATE(expire_date) = DATE(:expire_date)
        AND status = 'active'
        ORDER BY detail_id ASC
        LIMIT 1
    ";
    
    $find_stmt = $conn->prepare($find_sql);
    $find_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
    $find_stmt->bindParam(':area_id', $area_id, PDO::PARAM_INT);
    $find_stmt->bindParam(':expire_date', $expire_date);
    $find_stmt->execute();
    
    $detail = $find_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$detail) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Detail not found or already processed']);
        exit();
    }
    
    $detail_id = $detail['detail_id'];
    $current_quantity = (int) $detail['quantity'];
    
    error_log("update_item_detail_by_criteria.php - Found detail_id: $detail_id, current_quantity: $current_quantity");
    
    // ตรวจสอบว่าจำนวนที่จะอัปเดตไม่เกินที่มีอยู่
    if ($quantity_to_update > $current_quantity) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Quantity to update exceeds available quantity']);
        exit();
    }
    
    if ($quantity_to_update == $current_quantity) {
        // หากจำนวนเท่ากัน ให้เปลี่ยนสถานะ detail นั้น
        $update_sql = "
            UPDATE item_details 
            SET status = :new_status, 
                used_date = NOW()
            WHERE detail_id = :detail_id
        ";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bindParam(':new_status', $new_status);
        $update_stmt->bindParam(':detail_id', $detail_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        error_log("update_item_detail_by_criteria.php - Updated detail_id $detail_id to status $new_status");
        
    } else {
        // หากจำนวนน้อยกว่า ให้ลดจำนวนในรายการเดิม และสร้างรายการใหม่สำหรับส่วนที่ใช้/ทิ้ง
        $remaining_quantity = $current_quantity - $quantity_to_update;
        
        // ลดจำนวนในรายการเดิม
        $update_sql = "
            UPDATE item_details 
            SET quantity = :remaining_quantity
            WHERE detail_id = :detail_id
        ";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bindParam(':remaining_quantity', $remaining_quantity, PDO::PARAM_INT);
        $update_stmt->bindParam(':detail_id', $detail_id, PDO::PARAM_INT);
        $update_stmt->execute();
        
        // สร้างรายการใหม่สำหรับส่วนที่ใช้/ทิ้ง
        $insert_sql = "
            INSERT INTO item_details (item_id, area_id, expire_date, quantity, status, barcode, used_date)
            SELECT item_id, area_id, expire_date, :used_quantity, :new_status, barcode, NOW()
            FROM item_details 
            WHERE detail_id = :detail_id
        ";
        
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bindParam(':used_quantity', $quantity_to_update, PDO::PARAM_INT);
        $insert_stmt->bindParam(':new_status', $new_status);
        $insert_stmt->bindParam(':detail_id', $detail_id, PDO::PARAM_INT);
        $insert_stmt->execute();
        
        error_log("update_item_detail_by_criteria.php - Split detail: remaining=$remaining_quantity, used/expired=$quantity_to_update");
    }
    
    // อัปเดต item หลักถ้าจำเป็น (ไม่ต้องอัปเดต updated_at เพราะอาจไม่มีคอลัมน์นี้)
    // ปล่อยให้ updated_at อัปเดตอัตโนมัติ
    /*
    $update_main_sql = "
        UPDATE items 
        SET updated_at = CURRENT_TIMESTAMP
        WHERE item_id = :item_id AND user_id = :user_id
    ";
    
    $update_main_stmt = $conn->prepare($update_main_sql);
    $update_main_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
    $update_main_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $update_main_stmt->execute();
    */
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success', 
        'message' => 'Item detail updated successfully',
        'detail_id' => $detail_id,
        'quantity_updated' => $quantity_to_update,
        'new_status' => $new_status
    ]);
    
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("update_item_detail_by_criteria.php - Database error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 
