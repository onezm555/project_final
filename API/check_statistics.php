<?php
// check_statistics.php - API สำหรับดูสถิติการใช้และการทิ้ง
error_reporting(0);
ini_set('display_errors', 0);

ob_start();

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_end_clean();
    http_response_code(200);
    exit();
}

try {
    include_once __DIR__ . '/conn.php';
    
    if (!isset($conn) || $conn === null) {
        throw new Exception('Database connection failed');
    }

    $user_id = $_GET['user_id'] ?? 1; // default user_id = 1

    // ดึงข้อมูลรายละเอียดทุก item
    $stmt = $conn->prepare("
        SELECT 
            i.item_id,
            i.item_name,
            i.item_number as total_quantity,
            i.used_quantity,
            i.expired_quantity,
            i.remaining_quantity,
            i.item_status,
            COUNT(id.detail_id) as remaining_details
        FROM items i 
        LEFT JOIN item_details id ON i.item_id = id.item_id 
        WHERE i.user_id = ? 
        GROUP BY i.item_id
        ORDER BY i.item_id DESC
    ");
    $stmt->execute([$user_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // คำนวณสถิติรวม
    $total_items = count($items);
    $total_used = array_sum(array_column($items, 'used_quantity'));
    $total_expired = array_sum(array_column($items, 'expired_quantity'));
    $total_remaining = array_sum(array_column($items, 'remaining_quantity'));
    $total_original = array_sum(array_column($items, 'total_quantity'));

    // แยกตามสถานะ
    $status_summary = [
        'active' => 0,
        'expired' => 0,
        'disposed' => 0
    ];
    
    foreach ($items as $item) {
        if (isset($status_summary[$item['item_status']])) {
            $status_summary[$item['item_status']]++;
        }
    }

    ob_end_clean();
    echo json_encode([
        'status' => 'success',
        'message' => 'Statistics retrieved successfully',
        'server_time' => date('Y-m-d H:i:s'),
        'user_id' => $user_id,
        'summary' => [
            'total_items' => $total_items,
            'total_original_quantity' => $total_original,
            'total_used' => $total_used,
            'total_expired' => $total_expired,
            'total_remaining' => $total_remaining,
            'usage_percentage' => $total_original > 0 ? round(($total_used / $total_original) * 100, 2) : 0,
            'expired_percentage' => $total_original > 0 ? round(($total_expired / $total_original) * 100, 2) : 0,
            'remaining_percentage' => $total_original > 0 ? round(($total_remaining / $total_original) * 100, 2) : 0
        ],
        'status_count' => $status_summary,
        'items' => $items
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage(),
        'file' => __FILE__,
        'line' => __LINE__
    ]);
}
?>
