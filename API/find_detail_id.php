<?php
// find_detail_id.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

require_once 'conn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

// อ่านข้อมูลจาก request
$input = file_get_contents('php://input');
$json_data = json_decode($input, true);

$item_id = (int) ($json_data['item_id'] ?? 0);
$area_id = (int) ($json_data['area_id'] ?? 0);
$expire_date = $json_data['expire_date'] ?? '';

if ($item_id <= 0 || $area_id <= 0 || empty($expire_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Debug: แสดงข้อมูลที่ได้รับ
    error_log("find_detail_id.php - Searching for: item_id=$item_id, area_id=$area_id, expire_date=$expire_date");
    
    // ค้นหา detail_id จากข้อมูลที่มี
    $sql = "
        SELECT detail_id, status, quantity
        FROM item_details 
        WHERE item_id = :item_id 
        AND area_id = :area_id 
        AND DATE(expire_date) = DATE(:expire_date)
        AND status IN ('active', 'used', 'expired')
        ORDER BY detail_id ASC
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
    $stmt->bindParam(':area_id', $area_id, PDO::PARAM_INT);
    $stmt->bindParam(':expire_date', $expire_date);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug: แสดงผลการค้นหา
    error_log("find_detail_id.php - Query result: " . json_encode($result));
    
    if ($result) {
        echo json_encode([
            'status' => 'success', 
            'detail_id' => (int)$result['detail_id'],
            'current_status' => $result['status'],
            'quantity' => (int)$result['quantity']
        ]);
    } else {
        // ลองค้นหาอีกครั้งโดยไม่เช็ค status
        $sql2 = "
            SELECT detail_id, status, quantity
            FROM item_details 
            WHERE item_id = :item_id 
            AND area_id = :area_id 
            AND DATE(expire_date) = DATE(:expire_date)
            ORDER BY detail_id ASC
            LIMIT 1
        ";
        
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bindParam(':item_id', $item_id, PDO::PARAM_INT);
        $stmt2->bindParam(':area_id', $area_id, PDO::PARAM_INT);
        $stmt2->bindParam(':expire_date', $expire_date);
        $stmt2->execute();
        
        $result2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        error_log("find_detail_id.php - Second query result: " . json_encode($result2));
        
        if ($result2) {
            echo json_encode([
                'status' => 'success', 
                'detail_id' => (int)$result2['detail_id'],
                'current_status' => $result2['status'],
                'quantity' => (int)$result2['quantity'],
                'note' => 'Found with any status'
            ]);
        } else {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Detail not found for item_id=' . $item_id . ', area_id=' . $area_id . ', expire_date=' . $expire_date
            ]);
        }
    }
    
} catch (PDOException $e) {
    error_log("find_detail_id.php - Database error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>