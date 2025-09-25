<?php
// my_items_simple.php - เวอร์ชันง่าย ๆ เพื่อทดสอบ
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include 'conn.php';

$current_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if ($current_user_id === null) {
    echo json_encode([
        "success" => false,
        "message" => "User ID is required."
    ]);
    exit();
}

try {
    // SQL แบบง่าย ๆ ก่อน
    $sql = "
        SELECT
            i.item_id,
            i.user_id,
            i.item_name,
            i.item_number,
            i.item_img,
            i.item_date,
            i.item_notification,
            i.item_barcode,
            i.item_status,
            i.date_type,
            t.type_name,
            a.area_name
        FROM
            items i
        LEFT JOIN types t ON i.type_id = t.type_id
        LEFT JOIN areas a ON i.area_id = a.area_id
        WHERE i.user_id = :user_id AND i.item_status = 'active'
        ORDER BY i.item_date ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':user_id', $current_user_id);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formatted_items = [];
    foreach ($items as $item) {
        $item_img_full_url = '';
        if (!empty($item['item_img'])) {
            $item_img_full_url = 'http://localhost/project/uploads/' . $item['item_img'];
        } else {
            $item_img_full_url = 'assets/images/default.png';
        }

        $formatted_items[] = [
            'item_id' => $item['item_id'],
            'user_id' => $item['user_id'],
            'item_name' => $item['item_name'],
            'item_number' => $item['item_number'],
            'item_img_full_url' => $item_img_full_url,
            'item_date' => $item['item_date'],
            'item_notification' => $item['item_notification'],
            'item_barcode' => $item['item_barcode'],
            'item_status' => $item['item_status'],
            'date_type' => $item['date_type'],
            'category' => $item['type_name'],
            'storage_location' => $item['area_name'],
            'storage_locations' => [
                [
                    'area_name' => $item['area_name'],
                    'quantity' => (int)$item['item_number'],
                    'is_main' => true
                ]
            ]
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $formatted_items
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'query' => $sql ?? 'Unknown'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage()
    ]);
}
?>
