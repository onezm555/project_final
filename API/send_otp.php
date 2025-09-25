<?php

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

// เรียกใช้ไฟล์ conn.php เพื่อเชื่อมต่อฐานข้อมูล
require_once 'conn.php';

// Handle OPTIONS requests (CORS preflight)
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data['email'] ?? '';

    if (empty($email)) {
        http_response_code(400); // Bad Request
        echo json_encode([
            'status' => 'error',
            'message' => 'กรุณาระบุอีเมล'
        ]);
        exit;
    }

    try {
        // ตรวจสอบว่าอีเมลมีอยู่ในระบบหรือไม่
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404); // Not Found
            echo json_encode([
                'status' => 'error',
                'message' => 'ไม่พบอีเมลนี้ในระบบ'
            ]);
            exit;
        }

        // สร้างรหัสยืนยัน 6 หลัก
        $verificationCode = sprintf("%06d", mt_rand(100000, 999999));
        $code_sent_at = date('Y-m-d H:i:s');

        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';

        try {
            // ตั้งค่า SMTP
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
            $mail->Subject = mb_encode_mimeheader('รหัสยืนยันใหม่สำหรับการเข้าสู่ระบบ', 'UTF-8');
            $mail->Body = "
                <div style='font-family: Tahoma, sans-serif; font-size: 14px; line-height: 1.8; color: #333;'>
                    <p>เรียน " . htmlspecialchars($user['name']) . "</p>
                    <p>คุณได้ขอรหัสยืนยันใหม่สำหรับการเข้าสู่ระบบจัดการของอุปโภค-บริโภคภายในบ้าน</p>
                    <p>กรุณาใช้รหัสยืนยัน 6 หลักด้านล่างนี้เพื่อยืนยันตัวตนของคุณ:</p>
                    <h2 style='color: #2c3e50;'>รหัสยืนยันของคุณคือ: <b>$verificationCode</b></h2>
                    <p>รหัสนี้จะมีอายุการใช้งาน 15 นาที</p>
                    <p>หากคุณไม่ได้ทำรายการนี้ โปรดละเว้นอีเมลฉบับนี้ได้ทันที</p>
                    <p>ขอแสดงความนับถือ<br>ทีมงานระบบจัดการของอุปโภค-บริโภคภายในบ้าน</p>
                    <hr>
                    <small style='color: #999;'>อีเมลฉบับนี้จัดส่งโดยระบบอัตโนมัติ กรุณาอย่าตอบกลับ</small>
                </div>
            ";

            $mail->send(); // ส่งอีเมล

            // อัปเดตรหัสยืนยันในฐานข้อมูล หลังจากส่งอีเมลสำเร็จ
            $stmt_update = $conn->prepare("UPDATE users SET verification_code = ?, code_sent_at = ? WHERE email = ?");
            $stmt_update->execute([$verificationCode, $code_sent_at, $email]);

            // บันทึก log สำหรับการดีบั๊ก
            $logMessage = "OTP sent to $email: $verificationCode at $code_sent_at\n";
            file_put_contents(__DIR__ . '/otp_log.txt', $logMessage, FILE_APPEND);

            http_response_code(200); // OK
            echo json_encode([
                'status' => 'success',
                'message' => 'รหัสยืนยันถูกส่งไปยังอีเมลของคุณแล้ว',
                'debug_code' => $verificationCode // ลบออกในการใช้งานจริง
            ]);

        } catch (Exception $e) {
            // ถ้าส่งอีเมลไม่สำเร็จ
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
    // กรณีที่ไม่ใช่ POST method
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'status' => 'error',
        'message' => 'Method Not Allowed'
    ]);
}
?>