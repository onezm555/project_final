<?php

// **สำคัญ: เปิดการแสดงผลข้อผิดพลาดเพื่อดีบั๊ก (ลบออกเมื่อขึ้น Production)**
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// **เพิ่มบรรทัดนี้: เรียกใช้ไฟล์ conn.php เพื่อเชื่อมต่อฐานข้อมูล**
require_once 'conn.php'; // ตรวจสอบให้แน่ใจว่า path นี้ถูกต้อง (ควรอยู่ในโฟลเดอร์เดียวกัน)

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';
    $password = $data['password'] ?? '';

    // ตรวจสอบข้อมูลที่จำเป็นทั้งหมด
    if (empty($name) || empty(trim($email)) || empty($phone) || empty($password)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณากรอกข้อมูลให้ครบทุกช่อง (ชื่อ, อีเมล, เบอร์โทร, รหัสผ่าน)'
        ]);
        exit;
    }

    // --- ส่วนสำคัญ: เพิ่มการตรวจสอบโดเมนอีเมลตรงนี้ เพื่อดักจับอีเมลมั่วตั้งแต่แรก ---
    // ตรวจสอบรูปแบบอีเมลเบื้องต้นก่อนแยกโดเมน
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'รูปแบบอีเมลไม่ถูกต้อง'
        ]);
        exit;
    }

$email_domain = substr(strrchr($email, "@"), 1);

// ตรวจสอบว่า DNS ใช้งานได้หรือไม่ (เช่น google.com)
if (!checkdnsrr('google.com', 'MX')) {
    http_response_code(503); // Service Unavailable
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่สามารถตรวจสอบอีเมลได้ในขณะนี้ กรุณาลองใหม่ภายหลัง'
    ]);
    exit;
}

// ตรวจสอบว่าโดเมนอีเมลมี MX record หรือไม่
if (!checkdnsrr($email_domain, 'MX')) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'status' => 'error',
        'message' => 'ไม่พบอีเมล์นี้กรุณาตรวจสอบความถูกต้อง'
    ]);
    exit;
}

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Check if email already exists (ตรวจสอบแยก)
        $stmt_check_email = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt_check_email->execute([$email]);
        if ($stmt_check_email->fetchColumn() > 0) {
            http_response_code(409); // Conflict
            echo json_encode([
                'status' => 'error',
                'message' => 'อีเมลนี้ถูกใช้งานไปแล้ว กรุณากรอกอีเมลอื่น'
            ]);
            exit;
        }

        // Check if phone already exists (ตรวจสอบแยก)
        $stmt_check_phone = $conn->prepare("SELECT COUNT(*) FROM users WHERE phone = ?");
        $stmt_check_phone->execute([$phone]);
        if ($stmt_check_phone->fetchColumn() > 0) {
            http_response_code(409); // Conflict
            echo json_encode([
                'status' => 'error',
                'message' => 'เบอร์โทรศัพท์นี้ถูกใช้งานไปแล้ว กรุณากรอกเบอร์โทรศัพท์อื่น'
            ]);
            exit;
        }

        $code = rand(100000, 999999);
        $code_sent_at = date('Y-m-d H:i:s');

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = '651463014@crru.ac.th';
            $mail->Password   = 'lalu gdca szmp jprz';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('651463014@crru.ac.th', 'ระบบจัดการของอุปโภค-บริโภคภายในบ้าน');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = mb_encode_mimeheader('รหัสยืนยันสำหรับการลงทะเบียน', 'UTF-8');
            $mail->Body = "
                <div style='font-family: Tahoma, sans-serif; font-size: 14px; line-height: 1.8; color: #333;'>
                    <p>เรียนผู้ใช้งาน</p>
                    <p>ระบบจัดการของอุปโภค-บริโภคภายในบ้าน ขอขอบคุณที่คุณให้ความสนใจในการใช้งานระบบของเรา</p>
                    <p>กรุณายืนยันตัวตนของคุณโดยใช้รหัสยืนยัน 6 หลักด้านล่างนี้:</p>
                    <h2 style='color: #2c3e50;'>รหัสยืนยันของคุณคือ: <b>$code</b></h2>
                    <p>หากคุณไม่ได้ทำรายการนี้ โปรดละเว้นอีเมลฉบับนี้ได้ทันที</p>
                    <p>ขอแสดงความนับถือ<br>ทีมงานระบบจัดการของอุปโภค-บริโภคภายในบ้าน</p>
                    <hr>
                    <small style='color: #999;'>อีเมลฉบับนี้จัดส่งโดยระบบอัตโนมัติ กรุณาอย่าตอบกลับ</small>
                </div>
            ";

            $mail->send(); // พยายามส่งอีเมล

            // **สำคัญมาก: ถ้าส่งอีเมลสำเร็จ ค่อยเพิ่มข้อมูลผู้ใช้เข้าฐานข้อมูล**
            $stmt_insert = $conn->prepare(
                "INSERT INTO users (name, email, phone, password, verification_code, is_verified, code_sent_at) VALUES (?, ?, ?, ?, ?, 0, ?)"
            );
            $stmt_insert->execute([$name, $email, $phone, $hashedPassword, $code, $code_sent_at]);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'ส่งอีเมลยืนยันเรียบร้อยแล้ว กรุณาตรวจสอบในอีเมลของคุณ',
                'verification_code_sent' => $code
            ]);
        } catch (Exception $e) {
            // **สำคัญมาก: ถ้าส่งอีเมลไม่สำเร็จ จะไม่เพิ่มข้อมูลผู้ใช้ลงฐานข้อมูล**
            http_response_code(500); // Internal Server Error
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่สามารถส่งอีเมลยืนยันได้: ' . $mail->ErrorInfo,
                'error_details' => $e->getMessage()
            ]);
        }

    } catch (PDOException $e) {
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'status' => 'error',
            'message' => 'เกิดข้อผิดพลาดในการทำงานกับฐานข้อมูล: ' . $e->getMessage()
        ]);
    }

} else {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'status' => 'error',
        'message' => 'Method Not Allowed'
    ]);
}
?>