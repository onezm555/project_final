<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

// ต้องมี user_id เสมอ
if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing user_id']);
    exit;
}

$user_id = $_POST['user_id'];

// 1. กรณีเปลี่ยนรหัสผ่าน (ไม่มีไฟล์ profile_image)
if (!isset($_FILES['profile_image']) && isset($_POST['password']) && !empty($_POST['password'])) {
    require_once 'conn.php';
    // ตรวจสอบรหัสผ่านเดิม
    if (!isset($_POST['old_password']) || empty($_POST['old_password'])) {
        echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัสผ่านเดิม']);
        exit;
    }
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
    $stmt->bindValue(":user_id", $user_id);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($_POST['old_password'], $user['password'])) {
        $fields = [];
        $params = [":user_id" => $user_id];
        // ตรวจสอบว่ารหัสผ่านใหม่ไม่ตรงกับรหัสผ่านเดิม
        if (!password_verify($_POST['password'], $user['password'])) {
            $fields[] = "password = :password";
            $params[":password"] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }
        // ถ้ามีการส่ง user_name มาด้วย ให้อัปเดตชื่อ
        if (isset($_POST['user_name']) && !empty($_POST['user_name'])) {
            $fields[] = "name = :user_name";
            $params[":user_name"] = $_POST['user_name'];
        }
        if (count($fields) > 0) {
            $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = :user_id";
            $stmt = $conn->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            echo json_encode(['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่าน/ข้อมูลสำเร็จ']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านใหม่ต้องแตกต่างจากรหัสผ่านเดิม']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านเดิมไม่ถูกต้อง']);
    }
    exit;
}

// 2. กรณีอัปโหลดรูป (และ/หรือเปลี่ยนชื่อ/รหัสผ่าน)
if (isset($_FILES['profile_image'])) {
    $target_dir = "img/user/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    $filename = "user/" . uniqid("profile_") . "_" . basename($_FILES["profile_image"]["name"]);
    $target_file = $target_dir . basename($filename);

    require_once 'conn.php';
    // ตรวจสอบรูปเดิม ถ้าเป็น user/default_profile.png จะไม่ลบหรือทับไฟล์เดิม
    $stmt_check = $conn->prepare("SELECT user_img FROM users WHERE id = :user_id");
    $stmt_check->bindValue(":user_id", $user_id);
    $stmt_check->execute();
    $user = $stmt_check->fetch(PDO::FETCH_ASSOC);
    $old_img = $user ? $user['user_img'] : '';

    // อัปโหลดไฟล์ใหม่
    if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
        $fields = ["user_img = :filename"];
        $params = [":filename" => $filename, ":user_id" => $user_id];

        // ถ้ามีการส่ง user_name มาด้วย ให้อัปเดตชื่อ
        if (isset($_POST['user_name']) && !empty($_POST['user_name'])) {
            $fields[] = "name = :user_name";
            $params[":user_name"] = $_POST['user_name'];
        }

        // ถ้ามีการส่ง password และ old_password มาด้วย ให้ตรวจสอบก่อนอัปเดต
        if (isset($_POST['password']) && !empty($_POST['password'])) {
            if (isset($_POST['old_password']) && !empty($_POST['old_password'])) {
                // ตรวจสอบรหัสผ่านเดิม
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
                $stmt->bindValue(":user_id", $user_id);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user && password_verify($_POST['old_password'], $user['password'])) {
                    $fields[] = "password = :password";
                    $params[":password"] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                } else {
                    // หากรหัสผ่านเดิมไม่ถูกต้อง ให้ลบรูปที่อัปโหลดไปแล้ว
                    unlink($target_file);
                    echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านเดิมไม่ถูกต้อง']);
                    exit;
                }
            } else {
                // หากไม่มีรหัสผ่านเดิม ให้ลบรูปที่อัปโหลดไปแล้ว
                unlink($target_file);
                echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกรหัสผ่านเดิม']);
                exit;
            }
        }

        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = :user_id";
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $full_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/project/img/" . basename($filename);
        echo json_encode([
            'status' => 'success',
            'user_img_full_url' => $full_url,
            'message' => 'อัปโหลดรูปโปรไฟล์/ข้อมูลสำเร็จ'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'อัปโหลดรูปไม่สำเร็จ']);
    }
    exit;
}

// ถ้าไม่เข้าเงื่อนไขใดเลย
echo json_encode(['status' => 'error', 'message' => 'Missing data or invalid request']);
?>