<?php
// add_item.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

require_once 'conn.php';

// เพิ่ม debug logging
error_log("=== ADD ITEM DEBUG START ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Only POST requests are accepted.']);
    exit();
}

// รับข้อมูลจาก $_POST สำหรับ field ข้อความ

$item_name = $_POST['name'] ?? '';
$item_number = (int) ($_POST['quantity'] ?? 0);
$category_name = $_POST['category'] ?? '';
$storage_name = $_POST['storage_location'] ?? '';
$area_id = (int) ($_POST['storage_id'] ?? 0);
$item_date = $_POST['selected_date'] ?? '';
$item_notification = (int) ($_POST['notification_days'] ?? 0);
$item_barcode = $_POST['barcode'] ?? '';
$user_id = (int) ($_POST['user_id'] ?? 0);
$date_type_raw = $_POST['date_type'] ?? null;
$use_multiple_locations = isset($_POST['use_multiple_locations']) && $_POST['use_multiple_locations'] === 'true';
$use_storage_groups = isset($_POST['use_storage_groups']) && $_POST['use_storage_groups'] === 'true';
$item_locations_json = $_POST['item_locations'] ?? null;
$storage_groups_json = $_POST['storage_groups'] ?? null;

// แปลงค่าจากภาษาไทยเป็นรหัสฐานข้อมูล
if ($date_type_raw === 'วันหมดอายุ(EXP)') {
    $date_type = 'EXP';
} elseif ($date_type_raw === 'ควรบริโภคก่อน(BBF)') {
    $date_type = 'BBF';
} else {
    $date_type = null;
}

// เพิ่ม debug เพื่อดูข้อมูลที่ได้รับ
error_log("Parsed data:");
error_log("item_name: " . $item_name);
error_log("item_number: " . $item_number);
error_log("category_name: " . $category_name);
error_log("storage_name: " . $storage_name);
error_log("area_id: " . $area_id);
error_log("item_date: " . $item_date);
error_log("item_notification: " . $item_notification);
error_log("item_barcode: " . $item_barcode);
error_log("user_id: " . $user_id);
error_log("date_type_raw: " . $date_type_raw);
error_log("date_type: " . $date_type);
error_log("use_storage_groups: " . ($use_storage_groups ? 'true' : 'false'));
error_log("storage_groups_json: " . ($storage_groups_json ?? 'NULL'));

$item_img_filename_in_db = null;
$upload_dir = 'img/';

// --- ส่วนการจัดการอัปโหลดรูปภาพ (แก้ไขให้รับ key 'item_img') ---
if (isset($_FILES['item_img']) && $_FILES['item_img']['error'] === UPLOAD_ERR_OK) {
    $file_tmp_path = $_FILES['item_img']['tmp_name'];
    $file_name = basename($_FILES['item_img']['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $new_file_name = uniqid('item_') . '.' . $file_ext;
    $dest_path = $upload_dir . $new_file_name;

    if (move_uploaded_file($file_tmp_path, $dest_path)) {
        $item_img_filename_in_db = $new_file_name;
        error_log("Image uploaded successfully: " . $item_img_filename_in_db);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file.']);
        exit();
    }
} else {
    // ใช้ default image สำหรับประเภทที่เลือก
    $default_image = $_POST['default_image'] ?? 'default.png';
    $item_img_filename_in_db = $default_image;
    error_log("Using default image: " . $item_img_filename_in_db);
}
// --- สิ้นสุดส่วนการจัดการอัปโหลดรูปภาพ ---

try {
    // ดึง type_id จาก category_name
    error_log("Looking for category: " . $category_name);
    $stmt_type = $conn->prepare("SELECT type_id FROM types WHERE type_name = :type_name");
    $stmt_type->bindParam(':type_name', $category_name);
    $stmt_type->execute();
    $type_id = $stmt_type->fetchColumn();
    error_log("Found type_id: " . ($type_id ? $type_id : 'NULL'));

    if (!$type_id) {
        error_log("Category not found: " . $category_name);
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Category not found: ' . $category_name]);
        exit();
    }

    // ตรวจสอบว่า area_id มีอยู่จริงในตาราง areas
    error_log("Checking area_id: " . $area_id);
    $stmt_area_check = $conn->prepare("SELECT COUNT(*) FROM areas WHERE area_id = :area_id");
    $stmt_area_check->bindParam(':area_id', $area_id, PDO::PARAM_INT);
    $stmt_area_check->execute();
    $area_count = $stmt_area_check->fetchColumn();
    error_log("Area count: " . $area_count);
    
    if ($area_count == 0) {
        error_log("Storage location ID not found: " . $area_id);
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Storage location ID not found: ' . $area_id]);
        exit();
    }

    // ตรวจสอบบาร์โค้ดซ้ำภายใน user_id เดียวกัน (ถ้ามีบาร์โค้ด)
    // สำหรับการสแกนสิ่งของที่มีอยู่ ให้ใส่เลขลำดับต่อท้ายบาร์โค้ดเพื่อทำให้ไม่ซ้ำ
    if (!empty($item_barcode)) {
        error_log("Checking barcode duplicate: " . $item_barcode . " for user: " . $user_id);
        $stmt_barcode_check = $conn->prepare("SELECT COUNT(*) FROM items WHERE item_barcode = :item_barcode AND user_id = :user_id");
        $stmt_barcode_check->bindParam(':item_barcode', $item_barcode);
        $stmt_barcode_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_barcode_check->execute();
        $barcode_count = $stmt_barcode_check->fetchColumn();
        error_log("Barcode count: " . $barcode_count);
        
        if ($barcode_count > 0) {
            // สำหรับสิ่งของที่มีบาร์โค้ดซ้ำ ให้เพิ่มลำดับต่อท้าย
            $original_barcode = $item_barcode;
            $counter = 1;
            do {
                $item_barcode = $original_barcode . "-" . $counter;
                error_log("Trying new barcode: " . $item_barcode);
                
                $stmt_barcode_check = $conn->prepare("SELECT COUNT(*) FROM items WHERE item_barcode = :item_barcode AND user_id = :user_id");
                $stmt_barcode_check->bindParam(':item_barcode', $item_barcode);
                $stmt_barcode_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_barcode_check->execute();
                $barcode_count = $stmt_barcode_check->fetchColumn();
                $counter++;
            } while ($barcode_count > 0 && $counter <= 100); // ป้องกัน infinite loop
            
            if ($barcode_count > 0) {
                error_log("Could not generate unique barcode after 100 attempts");
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถสร้างบาร์โค้ดที่ไม่ซ้ำได้ กรุณาลองใหม่อีกครั้ง']);
                exit();
            }
            
            error_log("Generated unique barcode: " . $item_barcode);
        }
    }

    // Start transaction
    $conn->beginTransaction();
    error_log("Transaction started");

    // Insert main item
    error_log("Preparing to insert item with data:");
    error_log("type_id: " . $type_id . ", area_id: " . $area_id);
    
    $sql = "INSERT INTO items (
                item_name,
                item_number,
                item_img,
                item_date,
                date_type,
                item_notification,
                item_barcode,
                user_id,
                type_id
            ) VALUES (
                :item_name,
                :item_number,
                :item_img,
                :item_date,
                :date_type,
                :item_notification,
                :item_barcode,
                :user_id,
                :type_id
            )";

    $stmt_insert = $conn->prepare($sql);
    $stmt_insert->bindParam(':item_name', $item_name);
    $stmt_insert->bindParam(':item_number', $item_number);
    $stmt_insert->bindParam(':item_img', $item_img_filename_in_db);
    $stmt_insert->bindParam(':item_date', $item_date);
    $stmt_insert->bindParam(':date_type', $date_type);
    $stmt_insert->bindParam(':item_notification', $item_notification);
    $stmt_insert->bindParam(':item_barcode', $item_barcode);
    $stmt_insert->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_insert->bindParam(':type_id', $type_id, PDO::PARAM_INT);

    $stmt_insert->execute();
    $item_id = $conn->lastInsertId();
    error_log("Item inserted successfully with ID: " . $item_id);

    // เพิ่มข้อมูลไปใน item_details เสมอ (แม้แค่ 1 ชิ้น)
    // ใช้ item_details เป็น single source of truth แทน item_locations
    // เพราะ item_details มี area_id อยู่แล้ว ไม่จำเป็นต้องแยกตาราง
    
    if ($use_storage_groups && $storage_groups_json) {
        // กรณีใช้ระบบ Storage Groups ใหม่
        $storage_groups = json_decode($storage_groups_json, true);
        if ($storage_groups && is_array($storage_groups)) {
            error_log("Processing storage groups. Total groups: " . count($storage_groups));
            error_log("storage_groups data: " . json_encode($storage_groups));
            
            foreach ($storage_groups as $group) {
                $group_area_id = $group['area_id'] ?? null;
                $group_quantity = $group['quantity'] ?? 0;
                $details = $group['details'] ?? [];
                
                if ($group_area_id && $group_quantity > 0) {
                    // Verify the area exists
                    $stmt_check_area = $conn->prepare("SELECT COUNT(*) FROM areas WHERE area_id = :area_id");
                    $stmt_check_area->bindParam(':area_id', $group_area_id, PDO::PARAM_INT);
                    $stmt_check_area->execute();
                    
                    if ($stmt_check_area->fetchColumn() > 0) {
                        if (is_array($details) && count($details) > 0) {
                            // เพิ่มรายละเอียดแต่ละชิ้นตามที่ส่งมาจากแอป
                            foreach ($details as $detail) {
                                $expire_date = $detail['expire_date'] ?? $item_date;
                                $barcode = $detail['barcode'] ?? $item_barcode;
                                $item_img = $detail['item_img'] ?? $item_img_filename_in_db;
                                $quantity = $detail['quantity'] ?? 1;
                                $notification_days = $detail['notification_days'] ?? $item_notification;
                                $status = $detail['status'] ?? 'active';
                                
                                $stmt_detail = $conn->prepare("
                                    INSERT INTO item_details (item_id, area_id, expire_date, barcode, item_img, quantity, notification_days, status)
                                    VALUES (:item_id, :area_id, :expire_date, :barcode, :item_img, :quantity, :notification_days, :status)
                                ");
                                $stmt_detail->bindParam(':item_id', $item_id, PDO::PARAM_INT);
                                $stmt_detail->bindParam(':area_id', $group_area_id, PDO::PARAM_INT);
                                $stmt_detail->bindParam(':expire_date', $expire_date);
                                $stmt_detail->bindParam(':barcode', $barcode);
                                $stmt_detail->bindParam(':item_img', $item_img);
                                $stmt_detail->bindParam(':quantity', $quantity, PDO::PARAM_INT);
                                $stmt_detail->bindParam(':notification_days', $notification_days, PDO::PARAM_INT);
                                $stmt_detail->bindParam(':status', $status);
                                $stmt_detail->execute();
                                error_log("Storage group detail inserted: area_id=$group_area_id, expire_date=$expire_date, quantity=$quantity");
                            }
                        } else {
                            // ถ้าไม่มี details แบบละเอียด ให้เพิ่มแบบปกติ
                            $stmt_detail = $conn->prepare("
                                INSERT INTO item_details (item_id, area_id, expire_date, barcode, item_img, quantity, notification_days, status)
                                VALUES (:item_id, :area_id, :expire_date, :barcode, :item_img, :quantity, :notification_days, :status)
                            ");
                            $stmt_detail->bindParam(':item_id', $item_id, PDO::PARAM_INT);
                            $stmt_detail->bindParam(':area_id', $group_area_id, PDO::PARAM_INT);
                            $stmt_detail->bindParam(':expire_date', $item_date);
                            $stmt_detail->bindParam(':barcode', $item_barcode);
                            $stmt_detail->bindParam(':item_img', $item_img_filename_in_db);
                            $stmt_detail->bindParam(':quantity', $group_quantity, PDO::PARAM_INT);
                            $stmt_detail->bindParam(':notification_days', $item_notification, PDO::PARAM_INT);
                            $status_active = 'active';
                            $stmt_detail->bindParam(':status', $status_active);
                            $stmt_detail->execute();
                            error_log("Simple storage group detail inserted: area_id=$group_area_id, quantity=$group_quantity");
                        }
                    } else {
                        error_log("Warning: area_id $group_area_id not found in areas table");
                    }
                }
            }
            
            // สรุปผลการเพิ่มข้อมูล Storage Groups
            $total_inserted_quantity = 0;
            $stmt_summary = $conn->prepare("SELECT SUM(quantity) FROM item_details WHERE item_id = :item_id");
            $stmt_summary->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            $stmt_summary->execute();
            $total_inserted_quantity = $stmt_summary->fetchColumn() ?? 0;
            
            error_log("STORAGE GROUPS SUMMARY: Expected total quantity: $item_number, Actually inserted: $total_inserted_quantity");
        }
    } elseif ($use_multiple_locations && $item_locations_json) {
        // กรณีมีหลายพื้นที่ - ให้เพิ่ม item_details สำหรับแต่ละพื้นที่
        $item_locations = json_decode($item_locations_json, true);
        if ($item_locations && is_array($item_locations)) {
            error_log("Processing multiple locations. Total item_number: " . $item_number);
            error_log("Main area_id: " . $area_id);
            error_log("item_locations data: " . json_encode($item_locations));
            
            // เพิ่ม item_details สำหรับแต่ละพื้นที่โดยตรง (ไม่แยกพื้นที่หลักออกมา)
            foreach ($item_locations as $location) {
                $location_area_id = $location['area_id'] ?? null;
                $location_quantity = $location['quantity'] ?? 0;
                $details = $location['details'] ?? [];
                
                if ($location_area_id && $location_quantity > 0) {
                    // Verify the area exists
                    $stmt_check_area = $conn->prepare("SELECT COUNT(*) FROM areas WHERE area_id = :area_id");
                    $stmt_check_area->bindParam(':area_id', $location_area_id, PDO::PARAM_INT);
                    $stmt_check_area->execute();
                    
                    if ($stmt_check_area->fetchColumn() > 0) {
                        // Insert เฉพาะ item_details (ไม่ใช้ item_locations)
                        if (is_array($details) && count($details) > 0) {
                            foreach ($details as $detail) {
                                $expire_date = $detail['expire_date'] ?? $item_date;
                                $barcode = $detail['barcode'] ?? $item_barcode;
                                $item_img = $detail['item_img'] ?? $item_img_filename_in_db;
                                $quantity = $detail['quantity'] ?? 1;
                                $notification_days = $detail['notification_days'] ?? $item_notification;
                                $status = $detail['status'] ?? 'active';
                                
                                $stmt_detail = $conn->prepare("
                                    INSERT INTO item_details (item_id, area_id, expire_date, barcode, item_img, quantity, notification_days, status)
                                    VALUES (:item_id, :area_id, :expire_date, :barcode, :item_img, :quantity, :notification_days, :status)
                                ");
                                $stmt_detail->bindParam(':item_id', $item_id, PDO::PARAM_INT);
                                $stmt_detail->bindParam(':area_id', $location_area_id, PDO::PARAM_INT);
                                $stmt_detail->bindParam(':expire_date', $expire_date);
                                $stmt_detail->bindParam(':barcode', $barcode);
                                $stmt_detail->bindParam(':item_img', $item_img);
                                $stmt_detail->bindParam(':quantity', $quantity, PDO::PARAM_INT);
                                $stmt_detail->bindParam(':notification_days', $notification_days, PDO::PARAM_INT);
                                $stmt_detail->bindParam(':status', $status);
                                $stmt_detail->execute();
                                error_log("Detail inserted for area_id: $location_area_id, quantity: $quantity");
                            }
                        } else {
                            // ถ้าไม่มี details แบบละเอียด ให้เพิ่มแบบปกติ
                            $stmt_detail = $conn->prepare("
                                INSERT INTO item_details (item_id, area_id, expire_date, barcode, item_img, quantity, notification_days, status)
                                VALUES (:item_id, :area_id, :expire_date, :barcode, :item_img, :quantity, :notification_days, :status)
                            ");
                            $stmt_detail->bindParam(':item_id', $item_id, PDO::PARAM_INT);
                            $stmt_detail->bindParam(':area_id', $location_area_id, PDO::PARAM_INT);
                            $stmt_detail->bindParam(':expire_date', $item_date);
                            $stmt_detail->bindParam(':barcode', $item_barcode);
                            $stmt_detail->bindParam(':item_img', $item_img_filename_in_db);
                            $stmt_detail->bindParam(':quantity', $location_quantity, PDO::PARAM_INT);
                            $stmt_detail->bindParam(':notification_days', $item_notification, PDO::PARAM_INT);
                            $status_active = 'active';
                            $stmt_detail->bindParam(':status', $status_active);
                            $stmt_detail->execute();
                            error_log("Simple detail inserted for area_id: $location_area_id, quantity: $location_quantity");
                        }
                    }
                }
            }
            
            // สรุปผลการเพิ่มข้อมูล
            $total_inserted_quantity = 0;
            $stmt_summary = $conn->prepare("SELECT SUM(quantity) FROM item_details WHERE item_id = :item_id");
            $stmt_summary->bindParam(':item_id', $item_id, PDO::PARAM_INT);
            $stmt_summary->execute();
            $total_inserted_quantity = $stmt_summary->fetchColumn() ?? 0;
            
            error_log("SUMMARY: Expected total quantity: $item_number, Actually inserted: $total_inserted_quantity");
        }
    } else {
        // กรณีพื้นที่เดียว - เพิ่ม item_details ปกติ
        $stmt_detail = $conn->prepare("
            INSERT INTO item_details (item_id, area_id, expire_date, barcode, item_img, quantity, notification_days, status)
            VALUES (:item_id, :area_id, :expire_date, :barcode, :item_img, :quantity, :notification_days, :status)
        ");
        $stmt_detail->bindParam(':item_id', $item_id, PDO::PARAM_INT);
        $stmt_detail->bindParam(':area_id', $area_id, PDO::PARAM_INT);
        $stmt_detail->bindParam(':expire_date', $item_date);
        $stmt_detail->bindParam(':barcode', $item_barcode);
        $stmt_detail->bindParam(':item_img', $item_img_filename_in_db);
        $stmt_detail->bindParam(':quantity', $item_number, PDO::PARAM_INT);
        $stmt_detail->bindParam(':notification_days', $item_notification, PDO::PARAM_INT);
        $status_active = 'active';
        $stmt_detail->bindParam(':status', $status_active);
        $stmt_detail->execute();
        error_log("Single location item_details inserted successfully for item_id: " . $item_id);
    }

    // Commit transaction
    $conn->commit();
    error_log("Transaction committed successfully");
    echo json_encode(['status' => 'success', 'message' => 'Item added successfully!', 'item_id' => $item_id]);

} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollBack();
    error_log("General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'General error: ' . $e->getMessage()
    ]);
}

error_log("=== ADD ITEM DEBUG END ===");
?>