<?php
// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาใช้ HTTP POST method สำหรับการอัปโหลด'
    ]);
    exit();
}

try {
    // ตรวจสอบว่ามีไฟล์ที่อัปโหลดหรือไม่
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณาเลือกไฟล์รูปภาพ'
        ]);
        exit();
    }

    $uploadedFile = $_FILES['image'];
    $uploadDir = 'C:/xampp/htdocs/project/img/';
    
    // รับชื่อไฟล์ที่ผู้ใช้ต้องการ
    $customFileName = isset($_POST['filename']) ? trim($_POST['filename']) : '';
    
    // สร้างโฟลเดอร์หากยังไม่มี
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // ตรวจสอบประเภทไฟล์
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = $uploadedFile['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode([
            'success' => false,
            'message' => 'รองรับเฉพาะไฟล์ JPG, PNG, GIF และ WebP เท่านั้น'
        ]);
        exit();
    }

    // ตรวจสอบขนาดไฟล์ (ไม่เกิน 5MB)
    if ($uploadedFile['size'] > 5 * 1024 * 1024) {
        echo json_encode([
            'success' => false,
            'message' => 'ขนาดไฟล์ไม่ควรเกิน 5MB'
        ]);
        exit();
    }

    // กำหนดชื่อไฟล์
    $fileExtension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    
    if (!empty($customFileName)) {
        // ใช้ชื่อที่ผู้ใช้กำหนด
        // ลบนามสกุลออกจากชื่อที่ผู้ใช้ใส่มา (ถ้ามี) แล้วใส่นามสกุลจริง
        $customFileName = pathinfo($customFileName, PATHINFO_FILENAME);
        $newFileName = $customFileName . '.' . $fileExtension;
    } else {
        // ใช้ชื่อแบบเดิม
        $newFileName = 'category_' . time() . '_' . uniqid() . '.' . $fileExtension;
    }
    
    $targetPath = $uploadDir . $newFileName;

    // ตรวจสอบว่าไฟล์ซ้ำหรือไม่
    if (file_exists($targetPath)) {
        echo json_encode([
            'success' => false,
            'message' => 'ชื่อไฟล์นี้มีอยู่แล้ว กรุณาเลือกชื่ออื่น'
        ]);
        exit();
    }

    // ย้ายไฟล์ไปยังโฟลเดอร์เป้าหมาย
    if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
        echo json_encode([
            'success' => true,
            'message' => 'อัปโหลดรูปภาพสำเร็จ',
            'filename' => $newFileName,
            'url' => "http://10.10.55.44/project/img/" . $newFileName
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ]);
}
?>