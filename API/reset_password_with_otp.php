<?php
// ปิด error output และล้าง buffer เพื่อให้แน่ใจว่า output เป็น JSON เท่านั้น
ob_clean();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
header('Access-Control-Allow-Origin: *'); // อนุญาตให้ทุกโดเมนเข้าถึงได้ (สำหรับพัฒนา)
header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); // อนุญาต methods ที่ใช้
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // อนุญาต headers ที่ใช้
header('Content-Type: application/json');
require 'conn.php'; // Your database connection file

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? '';
$otp = $data['otp'] ?? '';
$new_password = $data['new_password'] ?? '';

if (empty($email) || empty($otp) || empty($new_password)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

// 1. Fetch user by email and check OTP
$stmt = $conn->prepare("SELECT id, verification_code, code_sent_at FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบอีเมลนี้']);
    exit();
}

// 2. Verify OTP
if ($user['verification_code'] !== $otp) {
    echo json_encode(['status' => 'error', 'message' => 'รหัสยืนยันไม่ถูกต้อง']);
    exit();
}

// 3. Check OTP expiry (e.g., 5 minutes)
$code_sent_time = strtotime($user['code_sent_at']);
$current_time = time();
$expiry_duration = 5 * 60; // 5 minutes in seconds

if (($current_time - $code_sent_time) > $expiry_duration) {
    echo json_encode(['status' => 'error', 'message' => 'รหัสยืนยันหมดอายุแล้ว']);
    exit();
}

// 4. Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// 5. Update password and clear verification code/timestamp
$stmt = $conn->prepare("UPDATE users SET password = ?, verification_code = NULL, code_sent_at = NULL, is_verified = 1 WHERE id = ?");
$stmt->execute([$hashed_password, $user['id']]);

echo json_encode(['status' => 'success', 'message' => 'รีเซ็ตรหัสผ่านสำเร็จ']);

?>