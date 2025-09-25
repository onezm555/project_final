<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'ไม่อนุญาตให้ใช้วิธีการนี้ อนุญาตเฉพาะคำขอ POST สำหรับการลบเท่านั้น']);
    exit();
}

// Get item_id and user_id from POST data
$item_id = $_POST['item_id'] ?? null;
$user_id = $_POST['user_id'] ?? null;

// Validate inputs
if ($item_id === null || !is_numeric($item_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'รหัสสินค้าหายไปหรือไม่ถูกต้อง']);
    exit();
}

if ($user_id === null || !is_numeric($user_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'รหัสผู้ใช้งานหายไปหรือไม่ถูกต้อง']);
    exit();
}

$item_id = (int) $item_id;
$user_id = (int) $user_id;

try {
    // เริ่ม transaction
    $conn->beginTransaction();
    
    // Before deleting, get the image filename to delete the file from the server
    $stmt_get_img = $conn->prepare("SELECT item_img FROM items WHERE item_id = :item_id AND user_id = :user_id");
    $stmt_get_img->bindParam(':item_id', $item_id);
    $stmt_get_img->bindParam(':user_id', $user_id);
    $stmt_get_img->execute();
    $image_row = $stmt_get_img->fetch(PDO::FETCH_ASSOC);

    $item_img_filename = null;
    if ($image_row && !empty($image_row['item_img'])) {
        $item_img_filename = $image_row['item_img'];
    }

    // ตรวจสอบว่า item นี้เป็นของ user นี้หรือไม่ก่อน
    $stmt_check = $conn->prepare("SELECT item_id FROM items WHERE item_id = :item_id AND user_id = :user_id");
    $stmt_check->bindParam(':item_id', $item_id);
    $stmt_check->bindParam(':user_id', $user_id);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() === 0) {
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบสินค้าหรือไม่ได้รับอนุญาตให้ลบสินค้า']);
        exit();
    }

    // ลบข้อมูลใน item_details ก่อน (เนื่องจากมี foreign key constraint)
    $stmt_delete_details = $conn->prepare("DELETE FROM item_details WHERE item_id = :item_id");
    $stmt_delete_details->bindParam(':item_id', $item_id);
    $stmt_delete_details->execute();

    // ลบข้อมูลใน items สุดท้าย
    $sql = "DELETE FROM items WHERE item_id = :item_id AND user_id = :user_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':item_id', $item_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // commit transaction
        $conn->commit();
        
        // ตรวจสอบและลบไฟล์รูปภาพ (แต่ไม่ลบรูป default)
        if ($item_img_filename && 
            $item_img_filename !== 'default.png' && 
            !str_starts_with($item_img_filename, 'default_') &&
            !str_contains($item_img_filename, '_default')) {
            
            $upload_dir = 'img/';
            $file_to_delete = $upload_dir . $item_img_filename;
            if (file_exists($file_to_delete) && is_file($file_to_delete)) {
                unlink($file_to_delete);
                error_log("Deleted image file: " . $file_to_delete);
            }
        } else {
            error_log("Skipped deleting default image: " . $item_img_filename);
        }
        echo json_encode(['status' => 'success', 'message' => 'ลบสิ่งของสำเร็จ!']);
    } else {
        // rollback transaction
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบสินค้าหรือไม่ได้รับอนุญาตให้ลบสินค้า']);
    }

} catch (\PDOException $e) {
    // rollback transaction
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'ข้อผิดพลาดของฐานข้อมูล: ' . $e->getMessage()]);
} catch (\Exception $e) {
    // rollback transaction
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'ข้อผิดพลาดทั่วไป: ' . $e->getMessage()]);
}
?>