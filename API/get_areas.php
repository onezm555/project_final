<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conn.php';

$areas = [];

try {
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

    if ($user_id === null) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'User ID is missing or invalid.'
        ]);
        exit();
    } else {
        // ดึง area_id, area_name และ user_id กลับมาด้วย
        // และจัดเรียงโดยให้ user_id = 0 มาก่อน จากนั้นเรียงตามชื่อพื้นที่
        $sql = 'SELECT area_id, area_name, user_id FROM areas 
                WHERE user_id = :user_id OR user_id = 0 
                ORDER BY CASE WHEN user_id = 0 THEN 0 ELSE 1 END, area_name ASC';
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($areas);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลพื้นที่จัดเก็บ: ' . $e->getMessage()
    ]);
    exit();
}
?>