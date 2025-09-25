<?php
// edit_item.php - Updated for better item_details structure and edit mode handling
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Only POST requests are accepted.']);
    exit();
}

// รับข้อมูลจาก $_POST (ไม่รับ quantity เพราะไม่ให้แก้ไข)
$item_id = (int) ($_POST['item_id'] ?? 0);
$item_name = $_POST['name'] ?? '';
$category_name = $_POST['category'] ?? '';
$storage_name = $_POST['storage_location'] ?? '';
$area_id = (int) ($_POST['storage_id'] ?? 0);
$item_date = $_POST['selected_date'] ?? '';
$item_notification = (int) ($_POST['notification_days'] ?? 0);
$item_barcode = $_POST['barcode'] ?? '';
$user_id = (int) ($_POST['user_id'] ?? 0);
$date_type_raw = $_POST['date_type'] ?? null;

// ตรวจสอบว่ามีการส่ง quantity มาหรือไม่ (ซึ่งไม่ควรมีในโหมดแก้ไข)
if (isset($_POST['quantity'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Quantity modification is not allowed in edit mode.']);
    exit();
}

// แปลงค่า date_type
if ($date_type_raw === 'วันหมดอายุ(EXP)') {
    $date_type = 'EXP';
} elseif ($date_type_raw === 'ควรบริโภคก่อน(BBF)') {
    $date_type = 'BBF';
} else {
    $date_type = null;
}

$item_img_filename_in_db = null;
$upload_dir = 'img/';

// --- ส่วนการจัดการอัปโหลดรูปภาพ (ถ้ามี) ---
if (isset($_FILES['item_img']) && $_FILES['item_img']['error'] === UPLOAD_ERR_OK) {
    $file_tmp_path = $_FILES['item_img']['tmp_name'];
    $file_name = basename($_FILES['item_img']['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $new_file_name = uniqid('item_') . '.' . $file_ext;
    $dest_path = $upload_dir . $new_file_name;

    if (move_uploaded_file($file_tmp_path, $dest_path)) {
        $item_img_filename_in_db = $new_file_name;
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file.']);
        exit();
    }
} elseif (isset($_POST['default_image']) && !empty($_POST['default_image'])) {
    $item_img_filename_in_db = $_POST['default_image'];
}
// หากมี keep_existing_image = true จะไม่เปลี่ยนรูปภาพ (ไม่เซ็ต $item_img_filename_in_db)
// --- สิ้นสุดส่วนการจัดการอัปโหลดรูปภาพ ---

try {
    // เริ่ม transaction
    $conn->beginTransaction();
    
    // ตรวจสอบว่า item_id มีอยู่จริงและเป็นของ user นี้
    $stmt_check = $conn->prepare("SELECT item_number FROM items WHERE item_id = :item_id AND user_id = :user_id");
    $stmt_check->bindParam(':item_id', $item_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $item_info = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$item_info) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Item not found or not authorized.']);
        exit();
    }
    
    // เก็บจำนวนเดิมไว้ (ไม่ให้แก้ไข)
    $item_number = $item_info['item_number'];
    
    // ตรวจสอบจำนวน item_details ที่มีอยู่ (เพื่อป้องกันการเพิ่ม/ลดจำนวน)
    $stmt_count = $conn->prepare("SELECT COUNT(*) as detail_count FROM item_details WHERE item_id = :item_id AND status = 'active'");
    $stmt_count->bindParam(':item_id', $item_id, PDO::PARAM_INT);
    $stmt_count->execute();
    $existing_detail_count = $stmt_count->fetchColumn();
    
    // ถ้ามีการส่ง item_expire_details มา ให้ตรวจสอบว่าจำนวนไม่เปลี่ยน
    if (isset($_POST['item_expire_details']) && !empty($_POST['item_expire_details'])) {
        $item_expire_details = json_decode($_POST['item_expire_details'], true);
        if ($item_expire_details && count($item_expire_details) != $existing_detail_count) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode([
                'status' => 'error', 
                'message' => 'Cannot modify item quantity in edit mode. Expected: ' . $existing_detail_count . ', Got: ' . count($item_expire_details)
            ]);
            exit();
        }
    }

    // ดึง type_id จาก category_name
    $stmt_type = $conn->prepare("SELECT type_id FROM types WHERE type_name = :type_name");
    $stmt_type->bindParam(':type_name', $category_name);
    $stmt_type->execute();
    $type_id = $stmt_type->fetchColumn();

    if (!$type_id) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Category not found.']);
        exit();
    }

    // อัปเดตข้อมูลหลักในตาราง items (ไม่รวม item_number)
    $sql = "UPDATE items SET
                item_name = :item_name,
                item_date = :item_date,
                date_type = :date_type,
                item_notification = :item_notification,
                item_barcode = :item_barcode,
                type_id = :type_id";

    // ถ้ามีรูปใหม่ ให้เพิ่ม field และไม่อัปเดต area_id ในตาราง items
    if ($item_img_filename_in_db !== null) {
        $sql .= ", item_img = :item_img";
    }

    $sql .= " WHERE item_id = :item_id AND user_id = :user_id";

    $stmt_update = $conn->prepare($sql);

    $stmt_update->bindParam(':item_name', $item_name);
    $stmt_update->bindParam(':item_date', $item_date);
    $stmt_update->bindParam(':date_type', $date_type);
    $stmt_update->bindParam(':item_notification', $item_notification);
    $stmt_update->bindParam(':item_barcode', $item_barcode);
    $stmt_update->bindParam(':type_id', $type_id, PDO::PARAM_INT);
    $stmt_update->bindParam(':item_id', $item_id, PDO::PARAM_INT);
    $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);

    if ($item_img_filename_in_db !== null) {
        $stmt_update->bindParam(':item_img', $item_img_filename_in_db);
    }

    $stmt_update->execute();

    // อัปเดต item_details แต่ละรายการแยกกัน (ตรวจสอบว่ามีการส่งข้อมูล item_expire_details มา)
    if (isset($_POST['item_expire_details']) && !empty($_POST['item_expire_details'])) {
        $item_expire_details = json_decode($_POST['item_expire_details'], true);
        
        if (!$item_expire_details) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid item_expire_details JSON format.']);
            exit();
        }
        
        // เตรียม statement สำหรับอัปเดต item_details แต่ละรายการ
        $sql_update_detail = "UPDATE item_details SET
                area_id = :area_id,
                expire_date = :expire_date,
                barcode = :barcode,
                notification_days = :notification_days";
        
        if ($item_img_filename_in_db !== null) {
            $sql_update_detail .= ", item_img = :item_img";
        }
        
        $sql_update_detail .= " WHERE detail_id = :detail_id AND item_id = :item_id AND status = 'active'";
        
        $stmt_update_detail = $conn->prepare($sql_update_detail);
        
        foreach ($item_expire_details as $detail) {
            if (isset($detail['detail_id']) && isset($detail['area_id']) && isset($detail['expire_date'])) {
                $stmt_update_detail->bindParam(':area_id', $detail['area_id'], PDO::PARAM_INT);
                $stmt_update_detail->bindParam(':expire_date', $detail['expire_date']);
                $stmt_update_detail->bindParam(':barcode', $item_barcode);
                $stmt_update_detail->bindParam(':notification_days', $item_notification, PDO::PARAM_INT);
                $stmt_update_detail->bindParam(':detail_id', $detail['detail_id'], PDO::PARAM_INT);
                $stmt_update_detail->bindParam(':item_id', $item_id, PDO::PARAM_INT);
                
                if ($item_img_filename_in_db !== null) {
                    $stmt_update_detail->bindParam(':item_img', $item_img_filename_in_db);
                }
                
                $result = $stmt_update_detail->execute();
                if (!$result) {
                    error_log("Failed to update detail_id: " . $detail['detail_id']);
                }
            }
        }
        
        // Log การอัปเดต
        error_log("Updated " . count($item_expire_details) . " item details for item_id: $item_id");
        
    } else {
        // ถ้าไม่มีข้อมูล item_expire_details ให้อัปเดตแบบเดิม (backward compatibility)
        // ใช้ area_id จากการเลือกพื้นที่หลัก
        if ($area_id > 0) {
            $stmt_update_details = $conn->prepare("
                UPDATE item_details SET
                    area_id = :area_id,
                    expire_date = :expire_date,
                    barcode = :barcode,
                    notification_days = :notification_days" . 
                    ($item_img_filename_in_db !== null ? ", item_img = :item_img" : "") . "
                WHERE item_id = :item_id AND status = 'active'
            ");
            
            $stmt_update_details->bindParam(':area_id', $area_id, PDO::PARAM_INT);
            $stmt_update_details->bindParam(':expire_date', $item_date);
            $stmt_update_details->bindParam(':barcode', $item_barcode);
            $stmt_update_details->bindParam(':notification_days', $item_notification, PDO::PARAM_INT);
            $stmt_update_details->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            
            if ($item_img_filename_in_db !== null) {
                $stmt_update_details->bindParam(':item_img', $item_img_filename_in_db);
            }
            
            $stmt_update_details->execute();
        }
    }

    // commit transaction
    $conn->commit();

    echo json_encode([
        'status' => 'success', 
        'message' => 'Item updated successfully!',
        'item_id' => $item_id,
        'updated_details' => isset($item_expire_details) ? count($item_expire_details) : 'legacy_mode'
    ]);

} catch (PDOException $e) {
    // rollback transaction
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("Edit item database error: " . $e->getMessage());
} catch (Exception $e) {
    // rollback transaction
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'General error: ' . $e->getMessage()
    ]);
    error_log("Edit item general error: " . $e->getMessage());
}
?>
