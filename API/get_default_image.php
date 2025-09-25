<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

include 'conn.php';

$category = $_GET['category'] ?? '';

if (empty($category)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Category is required'
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT default_image FROM types WHERE type_name = ?");
    $stmt->execute([$category]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'status' => 'success',
            'default_image' => $result['default_image']
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'default_image' => 'default.png'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>