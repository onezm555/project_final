<?php
// get_user_areas_types.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once 'conn.php';

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required.']);
    exit();
}

try {
    // พื้นที่จัดเก็บที่ user มีสินค้า active อยู่ (ดึงจาก item_details)
    $stmt1 = $conn->prepare('
        SELECT DISTINCT a.area_id, a.area_name 
        FROM items i 
        INNER JOIN item_details id ON i.item_id = id.item_id 
        LEFT JOIN areas a ON id.area_id = a.area_id 
        WHERE i.user_id = :user_id 
        AND i.item_status = "active" 
        AND id.status = "active"
        AND a.area_id IS NOT NULL 
        ORDER BY a.area_name
    ');
    $stmt1->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt1->execute();
    $areas = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // หมวดหมู่ที่ user มีสินค้า active อยู่
    $stmt2 = $conn->prepare('
        SELECT DISTINCT t.type_id, t.type_name 
        FROM items i 
        LEFT JOIN types t ON i.type_id = t.type_id 
        WHERE i.user_id = :user_id 
        AND i.item_status = "active" 
        AND t.type_id IS NOT NULL 
        ORDER BY t.type_name
    ');
    $stmt2->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt2->execute();
    $types = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'areas' => $areas, 'types' => $types]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
