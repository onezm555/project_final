<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conn.php';

try {
    // ปรับ Query ให้ใช้ item_details แทน items
    $sql = "
        SELECT
            DATE_FORMAT(id.expire_date, '%Y-%m') as month_year,
            YEAR(id.expire_date) as year,
            MONTH(id.expire_date) as month,
            MONTHNAME(id.expire_date) as month_name,
            COALESCE(t.type_name, 'ไม่ระบุประเภท') as type_name,
            CASE
                WHEN id.expire_date IS NULL THEN 'ไม่มีวันหมดอายุ'
                WHEN id.expire_date < CURDATE() THEN 'หมดอายุแล้ว'
                WHEN id.expire_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'ใกล้หมดอายุ'
                ELSE 'ปกติ'
            END as status_category,
            id.status as item_status,
            COUNT(*) as item_count
        FROM item_details id
        LEFT JOIN items i ON id.item_id = i.item_id
        LEFT JOIN types t ON i.type_id = t.type_id
        WHERE i.user_id != 0  -- ไม่รวม admin
        GROUP BY
            DATE_FORMAT(id.expire_date, '%Y-%m'),
            t.type_name,
            status_category,
            id.status
        ORDER BY
            year DESC,
            month DESC,
            type_name,
            status_category
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ปรับ Query สถิติรวม - ใช้ item_details
    $totalSql = "
        SELECT
            COUNT(*) as total_items,
            COUNT(DISTINCT i.type_id) as total_types,
            SUM(CASE WHEN id.expire_date IS NULL THEN 1 ELSE 0 END) as no_expiry_count,
            SUM(CASE WHEN id.expire_date < CURDATE() THEN 1 ELSE 0 END) as expired_count,
            SUM(CASE WHEN id.expire_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND id.expire_date >= CURDATE() THEN 1 ELSE 0 END) as near_expiry_count,
            SUM(CASE WHEN id.expire_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as normal_count,
            SUM(CASE WHEN id.status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN id.status = 'expired' THEN 1 ELSE 0 END) as expired_status_count,
            SUM(CASE WHEN id.status = 'disposed' THEN 1 ELSE 0 END) as disposed_count
        FROM item_details id
        LEFT JOIN items i ON id.item_id = i.item_id
        WHERE i.user_id != 0
    ";

    $totalStmt = $conn->prepare($totalSql);
    $totalStmt->execute();
    $totalStats = $totalStmt->fetch(PDO::FETCH_ASSOC);

    // ปรับ Query สถิติตามประเภท - ใช้ item_details
    $typeSql = "
        SELECT
            t.type_name,
            COUNT(id.detail_id) as item_count,
            SUM(CASE WHEN id.expire_date IS NULL THEN 1 ELSE 0 END) as no_expiry_count,
            SUM(CASE WHEN id.expire_date < CURDATE() THEN 1 ELSE 0 END) as expired_count,
            SUM(CASE WHEN id.expire_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND id.expire_date >= CURDATE() THEN 1 ELSE 0 END) as near_expiry_count,
            SUM(CASE WHEN id.expire_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as normal_count,
            SUM(CASE WHEN id.status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN id.status = 'expired' THEN 1 ELSE 0 END) as expired_status_count,
            SUM(CASE WHEN id.status = 'disposed' THEN 1 ELSE 0 END) as disposed_count
        FROM types t
        LEFT JOIN items i ON t.type_id = i.type_id AND i.user_id != 0
        LEFT JOIN item_details id ON i.item_id = id.item_id
        GROUP BY t.type_id, t.type_name
        ORDER BY item_count DESC
    ";

    $typeStmt = $conn->prepare($typeSql);
    $typeStmt->execute();
    $typeStats = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

    // ปรับ Query เปรียบเทียบรายเดือน - ใช้ item_details
    $comparisonSql = "
        SELECT
            DATE_FORMAT(id.expire_date, '%Y-%m') as month_year,
            YEAR(id.expire_date) as year,
            MONTH(id.expire_date) as month,
            MONTHNAME(id.expire_date) as month_name,
            COUNT(*) as total_items
        FROM item_details id
        LEFT JOIN items i ON id.item_id = i.item_id
        WHERE i.user_id != 0
        GROUP BY YEAR(id.expire_date), MONTH(id.expire_date)
        ORDER BY year DESC, month DESC
        LIMIT 12
    ";

    $comparisonStmt = $conn->prepare($comparisonSql);
    $comparisonStmt->execute();
    $comparisonStats = $comparisonStmt->fetchAll(PDO::FETCH_ASSOC);

    // คำนวณเปอร์เซ็นต์การเปลี่ยนแปลง
    for ($i = 0; $i < count($comparisonStats); $i++) {
        if (isset($comparisonStats[$i + 1])) {
            $current = $comparisonStats[$i];
            $previous = $comparisonStats[$i + 1];

            if ($previous['total_items'] > 0) {
                $comparisonStats[$i]['items_change_percent'] = round((($current['total_items'] - $previous['total_items']) / $previous['total_items']) * 100, 2);
            } else {
                $comparisonStats[$i]['items_change_percent'] = 0;
            }
            $comparisonStats[$i]['users_change_percent'] = 0;
        } else {
            $comparisonStats[$i]['items_change_percent'] = 0;
            $comparisonStats[$i]['users_change_percent'] = 0;
        }
    }

    // จัดกลุ่มข้อมูลตามเดือน
    $groupedData = [];
    foreach ($monthlyStats as $row) {
        $key = $row['month_year'];
        if (!isset($groupedData[$key])) {
            $groupedData[$key] = [
                'month_year' => $row['month_year'],
                'year' => $row['year'],
                'month' => $row['month'],
                'month_name' => $row['month_name'],
                'total_items' => 0,
                'by_type' => [],
                'by_status' => [],
                'by_item_status' => [
                    'active' => 0,
                    'expired' => 0,
                    'disposed' => 0,
                ],
            ];
        }

        $groupedData[$key]['total_items'] += $row['item_count'];

        // จัดกลุ่มตามประเภท
        if (!isset($groupedData[$key]['by_type'][$row['type_name']])) {
            $groupedData[$key]['by_type'][$row['type_name']] = 0;
        }
        $groupedData[$key]['by_type'][$row['type_name']] += $row['item_count'];

        // จัดกลุ่มตามสถานะวันหมดอายุ
        if (!isset($groupedData[$key]['by_status'][$row['status_category']])) {
            $groupedData[$key]['by_status'][$row['status_category']] = 0;
        }
        $groupedData[$key]['by_status'][$row['status_category']] += $row['item_count'];

        // จัดกลุ่มตาม item_status
        if (isset($groupedData[$key]['by_item_status'][$row['item_status']])) {
            $groupedData[$key]['by_item_status'][$row['item_status']] += $row['item_count'];
        }
    }

    $monthlyData = array_values($groupedData);

    // Query สำหรับดึงหมวดหมู่สินค้า
    $categoriesSql = "
        SELECT type_id, type_name
        FROM types
        ORDER BY type_name ASC
    ";
    $categoriesStmt = $conn->prepare($categoriesSql);
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'status' => 'success',
        'data' => [
            'total_stats' => $totalStats,
            'monthly_data' => $monthlyData,
            'type_stats' => $typeStats,
            'comparison_stats' => $comparisonStats,
            'raw_monthly_stats' => $monthlyStats,
            'categories' => $categories
        ],
        'debug_info' => [
            'date_column_used' => 'item_details.expire_date',
            'note' => 'ใช้ item_details.expire_date เป็นวันหมดอายุ และ item_details.status เป็นสถานะสินค้า'
        ],
        'message' => 'ดึงสถิติ items สำเร็จ (ใช้ item_details)'
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาดในการดึงข้อมูลจากฐานข้อมูล: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>