<?php
// item_statistics.php - ดูสถิติการใช้งานสินค้า
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

try {
    include_once __DIR__ . '/conn.php';
    
    if (!isset($conn) || $conn === null) {
        throw new Exception('Database connection failed');
    }

    $user_id = $_GET['user_id'] ?? 2;

    // สถิติรวมของ user
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_items,
            SUM(item_number) as total_original_quantity,
            SUM(used_quantity) as total_used,
            SUM(expired_quantity) as total_expired,
            SUM(remaining_quantity) as total_remaining,
            COUNT(CASE WHEN item_status = 'active' THEN 1 END) as active_items,
            COUNT(CASE WHEN item_status = 'disposed' THEN 1 END) as disposed_items,
            COUNT(CASE WHEN item_status = 'expired' THEN 1 END) as expired_items
        FROM items 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // รายละเอียดแต่ละรายการ
    $stmt = $conn->prepare("
        SELECT 
            item_id,
            item_name,
            item_number as original_quantity,
            used_quantity,
            expired_quantity,
            remaining_quantity,
            item_status,
            ROUND((used_quantity / item_number) * 100, 2) as used_percentage,
            ROUND((expired_quantity / item_number) * 100, 2) as expired_percentage
        FROM items 
        WHERE user_id = ?
        ORDER BY item_name
    ");
    $stmt->execute([$user_id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'summary' => $summary,
        'details' => $details
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
