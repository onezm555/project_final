<?php
// update_item_status.php
// ปิด error reporting และ output buffering
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 0);

// เริ่ม output buffering เพื่อป้องกัน output ที่ไม่ต้องการ
ob_start();

// ตั้งค่า headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

// ตรวจสอบ method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// ลองเชื่อมต่อฐานข้อมูล
$conn = null;
try {
    // ตรวจสอบว่าไฟล์ conn.php มีอยู่หรือไม่
    if (!file_exists(__DIR__ . '/conn.php')) {
        throw new Exception('conn.php file not found in directory: ' . __DIR__);
    }
    
    // Clear any previous output
    ob_clean();
    
    // Include database connection with absolute path
    include_once __DIR__ . '/conn.php';
    
    // ตรวจสอบว่าตัวแปร $conn ถูกสร้างขึ้นหรือไม่
    if (!isset($conn) || $conn === null) {
        $error_message = 'Database connection variable not set';
        if (isset($GLOBALS['db_connection_error'])) {
            $error_message .= ': ' . $GLOBALS['db_connection_error'];
        }
        throw new Exception($error_message);
    }
    
    // ทดสอบการเชื่อมต่อ
    $testQuery = $conn->query("SELECT 1 as test");
    if (!$testQuery) {
        throw new Exception('Database connection test failed');
    }
    
} catch (PDOException $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database PDO error: ' . $e->getMessage()]);
    exit();
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit();
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Fatal database error: ' . $e->getMessage()]);
    exit();
}

// อ่านข้อมูลจาก request
$input = file_get_contents('php://input');
$json_data = json_decode($input, true);

// รองรับทั้ง JSON และ form data
if (!empty($json_data)) {
    $item_id = (int) ($json_data['item_id'] ?? 0);
    $user_id = (int) ($json_data['user_id'] ?? 0);
    $new_status = $json_data['new_status'] ?? '';
    $quantity_type = $json_data['quantity_type'] ?? '';
    $quantity = (int) ($json_data['quantity'] ?? 0);
    $area_id = (int) ($json_data['area_id'] ?? 0);
    $detail_id = (int) ($json_data['detail_id'] ?? 0); // เพิ่มรองรับ detail_id สำหรับอัปเดตชิ้นเฉพาะ
} else {
    $item_id = (int) ($_POST['item_id'] ?? 0);
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';
    $quantity_type = $_POST['quantity_type'] ?? '';
    $quantity = (int) ($_POST['quantity'] ?? 0);
    $area_id = (int) ($_POST['area_id'] ?? 0);
    $detail_id = (int) ($_POST['detail_id'] ?? 0); // เพิ่มรองรับ detail_id สำหรับอัปเดตชิ้นเฉพาะ
}

// ตรวจสอบประเภทการอัปเดต
$is_quantity_update = !empty($quantity_type) && $quantity > 0;
$is_detail_status_update = $detail_id > 0 && !empty($new_status); // เพิ่มการตรวจสอบอัปเดตสถานะชิ้นเฉพาะ

// Debug logging
error_log("update_item_status.php DEBUG:");
error_log("item_id: " . $item_id);
error_log("user_id: " . $user_id);
error_log("detail_id: " . $detail_id);
error_log("new_status: " . $new_status);
error_log("is_detail_status_update: " . ($is_detail_status_update ? 'true' : 'false'));
error_log("JSON input: " . $input);

// Validate input
if ($item_id <= 0 || $user_id <= 0) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid item_id or user_id']);
    exit();
}

if ($is_quantity_update) {
    if (!in_array($quantity_type, ['used', 'expired'])) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid quantity_type']);
        exit();
    }
} elseif ($is_detail_status_update) {
    // ตรวจสอบสถานะสำหรับการอัปเดตชิ้นเฉพาะ
    if (!in_array($new_status, ['disposed', 'expired'])) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid new_status for detail update']);
        exit();
    }
} else {
    if (!in_array($new_status, ['disposed', 'expired'])) {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Invalid new_status']);
        exit();
    }
}

// ตรวจสอบการเชื่อมต่อฐานข้อมูลอีกครั้ง
if (!isset($conn) || $conn === null) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database connection not available']);
    exit();
}

try {
    $conn->beginTransaction();

    if ($is_detail_status_update) {
        // อัปเดตสถานะชิ้นเฉพาะใน item_details
        $stmt_check_detail = $conn->prepare("
            SELECT id.detail_id, id.status, id.quantity, i.user_id
            FROM item_details id
            JOIN items i ON id.item_id = i.item_id
            WHERE id.detail_id = ? AND i.item_id = ?
        ");
        $stmt_check_detail->execute([$detail_id, $item_id]);
        $detail = $stmt_check_detail->fetch(PDO::FETCH_ASSOC);
        
        if (!$detail) {
            throw new Exception('Detail not found');
        }
        
        if ($detail['user_id'] != $user_id) {
            throw new Exception('Unauthorized access');
        }
        
        if ($detail['status'] !== 'active') {
            throw new Exception('Detail already processed');
        }
        
        // อัปเดตสถานะและวันที่ใช้
        $stmt_update_detail = $conn->prepare("
            UPDATE item_details 
            SET status = ?, used_date = NOW(), updated_at = NOW()
            WHERE detail_id = ?
        ");
        $stmt_update_detail->execute([$new_status, $detail_id]);
        
        $conn->commit();
        
        ob_end_clean();
        echo json_encode([
            'status' => 'success', 
            'message' => 'Detail status updated successfully',
            'data' => [
                'detail_id' => $detail_id,
                'new_status' => $new_status,
                'item_id' => $item_id,
                'updated_quantity' => $detail['quantity']
            ]
        ]);
        
    } elseif ($is_quantity_update) {
        // อัปเดตจำนวนใน item_details แทน item_locations
        if ($area_id > 0) {
            // อัปเดตใน item_details ตาม area_id
            $stmt_check = $conn->prepare("
                SELECT SUM(CASE WHEN status = 'active' THEN quantity ELSE 0 END) as active_quantity
                FROM item_details 
                WHERE item_id = ? AND area_id = ?
            ");
            $stmt_check->execute([$item_id, $area_id]);
            $location = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$location || $location['active_quantity'] < $quantity) {
                throw new Exception('Insufficient quantity in this area');
            }

            // อัปเดตสถานะของ item_details
            $new_status_for_update = ($quantity_type === 'used') ? 'disposed' : 'expired';
            $stmt_update = $conn->prepare("
                UPDATE item_details 
                SET status = ?, used_date = NOW(), updated_at = NOW()
                WHERE item_id = ? AND area_id = ? AND status = 'active' 
                ORDER BY expire_date ASC 
                LIMIT ?
            ");
            $stmt_update->execute([$new_status_for_update, $item_id, $area_id, $quantity]);
            
            $conn->commit();
            
            ob_end_clean();
            echo json_encode([
                'status' => 'success', 
                'message' => 'Updated successfully',
                'data' => [
                    'updated_quantity' => $quantity,
                    'type' => $quantity_type,
                    'area_id' => $area_id,
                    'new_status' => $new_status_for_update
                ]
            ]);
            
        } else {
            // อัปเดตรวมทั้งหมดใน item_details
            $stmt_check = $conn->prepare("
                SELECT SUM(CASE WHEN status = 'active' THEN quantity ELSE 0 END) as active_quantity
                FROM item_details 
                WHERE item_id = ?
            ");
            $stmt_check->execute([$item_id]);
            $item_details = $stmt_check->fetch(PDO::FETCH_ASSOC);
            
            if (!$item_details || $item_details['active_quantity'] < $quantity) {
                throw new Exception('Insufficient quantity available');
            }

            // อัปเดตสถานะของ item_details
            $new_status_for_update = ($quantity_type === 'used') ? 'disposed' : 'expired';
            $stmt_update = $conn->prepare("
                UPDATE item_details 
                SET status = ?, used_date = NOW(), updated_at = NOW()
                WHERE item_id = ? AND status = 'active' 
                ORDER BY expire_date ASC 
                LIMIT ?
            ");
            $stmt_update->execute([$new_status_for_update, $item_id, $quantity]);
            
            $conn->commit();
            
            ob_end_clean();
            echo json_encode([
                'status' => 'success', 
                'message' => 'Updated successfully',
                'data' => [
                    'updated_quantity' => $quantity,
                    'type' => $quantity_type,
                    'new_status' => $new_status_for_update
                ]
            ]);
        }
    } else {
        // เปลี่ยนสถานะ
        $stmt = $conn->prepare("UPDATE items SET item_status = ? WHERE item_id = ? AND user_id = ?");
        $stmt->execute([$new_status, $item_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $conn->commit();
            
            // Clear buffer and send response
            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Status updated']);
        } else {
            throw new Exception('No changes made');
        }
    }

} catch (PDOException $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    ob_end_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    ob_end_clean();
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollback();
    }
    ob_end_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
}
?>