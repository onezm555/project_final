<?php
// ปิด error output และล้าง buffer เพื่อให้แน่ใจว่า output เป็น JSON เท่านั้น
ob_clean();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
// **สำคัญ: เพิ่ม Headers เหล่านี้ที่ด้านบนสุดของไฟล์**
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); // อนุญาตให้ทุกโดเมนเข้าถึงได้ (สำหรับพัฒนา)
header("Access-Control-Allow-Methods: POST, OPTIONS"); // อนุญาตให้ใช้ POST และ OPTIONS
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// รับค่าจาก CORS preflight (OPTIONS request) - สำคัญสำหรับ CORS
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// โค้ดส่วนที่เหลือของ forgot_password.php
require 'conn.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
if (!isset($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';

if (empty($email)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาระบุอีเมล']);
    exit();
}

// ตรวจสอบรูปแบบอีเมล
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'รูปแบบอีเมลไม่ถูกต้อง']);
    exit();
}

// 1. Check if email exists and account is active
$stmt = $conn->prepare("SELECT id, email, is_verified, code_sent_at FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // ส่งข้อความ error ที่ชัดเจนว่าไม่พบอีเมลในระบบ
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบอีเมลนี้ในระบบ กรุณาตรวจสอบอีเมลของคุณหรือสมัครสมาชิกใหม่']);
    exit();
}

// ตรวจสอบว่าบัญชีได้รับการยืนยันแล้วหรือไม่
if ($user['is_verified'] == 0) {
    echo json_encode(['status' => 'error', 'message' => 'บัญชีของคุณยังไม่ได้รับการยืนยัน กรุณายืนยันอีเมลก่อนใช้งาน']);
    exit();
}

// ตรวจสอบว่าเพิ่งส่งรหัสไปหรือไม่ (ป้องกันการส่งซ้ำใน 1 นาที)
if ($user['code_sent_at']) {
    $last_sent = strtotime($user['code_sent_at']);
    $now = time();
    $time_diff = $now - $last_sent;
    
    if ($time_diff < 60) { // ต้องรอ 60 วินาที
        $remaining = 60 - $time_diff;
        echo json_encode(['status' => 'error', 'message' => "กรุณารอ $remaining วินาที ก่อนขอรหัสใหม่"]);
        exit();
    }
}

$user_id = $user['id'];

// 2. Generate a 6-digit OTP (verification_code)
$verification_code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
$code_sent_at = date('Y-m-d H:i:s');

// 3. Store OTP and timestamp in the users table (ไม่เปลี่ยน is_verified)
$stmt = $conn->prepare("UPDATE users SET verification_code = ?, code_sent_at = ? WHERE id = ?");
$stmt->execute([$verification_code, $code_sent_at, $user_id]);

// 4. Send email with the OTP
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
    $mail->Subject = mb_encode_mimeheader('รหัสยืนยันการรีเซ็ตรหัสผ่านของคุณ', 'UTF-8');
    $mail->Body = "
        <div style='font-family: Tahoma, sans-serif; font-size: 14px; line-height: 1.8; color: #333;'>
            <p>เรียนผู้ใช้งาน</p>
            <p>ระบบจัดการของอุปโภค-บริโภคภายในบ้าน</p>
            <p>คุณได้ทำการร้องขอการรีเซ็ตรหัสผ่าน</p>
            <p>รหัสยืนยัน (OTP) สำหรับการรีเซ็ตรหัสผ่านของคุณคือ:</p>
            <h2 style='color: #2c3e50; text-align: center; background: #f8f9fa; padding: 15px; border-radius: 8px;'><b>$verification_code</b></h2>
            <p><strong>หมายเหตุสำคัญ:</strong></p>
            <ul>
                <li>รหัสนี้จะหมดอายุภายใน 10 นาที</li>
                <li>กรุณากรอกรหัสนี้ในแอปของคุณเพื่อดำเนินการรีเซ็ตรหัสผ่าน</li>
                <li>หากคุณไม่ได้ร้องขอการรีเซ็ตรหัสผ่านนี้ โปรดละเว้นอีเมลฉบับนี้</li>
            </ul>
            <p>ขอแสดงความนับถือ<br>ทีมงานระบบจัดการของอุปโภค-บริโภคภายในบ้าน</p>
            <hr>
            <small style='color: #999;'>อีเมลฉบับนี้จัดส่งโดยระบบอัตโนมัติ กรุณาอย่าตอบกลับ</small>
        </div>
    ";
    $mail->AltBody = "สวัสดี,\n\nรหัสยืนยัน (OTP) สำหรับการรีเซ็ตรหัสผ่านของคุณคือ: $verification_code\n\nรหัสนี้จะหมดอายุภายในไม่กี่นาที กรุณากรอกรหัสนี้ในแอปของคุณเพื่อดำเนินการต่อ.\n\nหากคุณไม่ได้ร้องขอการรีเซ็ตรหัสผ่านนี้ โปรดละเว้นอีเมลนี้.\n\nขอบคุณ\nทีมงานระบบจัดการของอุปโภค-บริโภคภายในบ้าน.";

    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'ส่งรหัสยืนยันไปยังอีเมลของคุณแล้ว']);
} catch (Exception $e) {
    error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการส่งอีเมลรหัสยืนยัน: ' . $mail->ErrorInfo]);
}
?>