<?php
// get_types.php
header("Access-Control-Allow-Origin: *"); // *** เพิ่มบรรทัดนี้ ***
header('Content-Type: application/json');

require_once 'conn.php';

$types = [];

try {
    $stmt = $conn->query('SELECT type_id, type_name FROM types'); 
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($types);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลประเภท: ' . $e->getMessage()
    ]);
    exit();
}
?>