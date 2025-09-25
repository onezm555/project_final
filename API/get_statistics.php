<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

include 'conn.php';

$current_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$filter_month = isset($_GET['month']) ? trim($_GET['month']) : null; // ถ้าไม่มี month parameter จะเป็น null (สถิติทั้งหมด)

if ($current_user_id === null || $current_user_id === '') {
    echo json_encode([
        "success" => false,
        "message" => "User ID is required."
    ]);
    exit();
}

// แปลง user_id เป็น integer
$current_user_id = (int)$current_user_id;

// Debug log
file_put_contents(__DIR__ . '/debug_statistics.log',
    "==================== DEBUG INPUT ====================\n" .
    "User ID: " . $current_user_id . "\n" .
    "Filter Month: " . $filter_month . "\n" .
    "=======================================================\n",
    FILE_APPEND
);

try {
    // ตรวจสอบว่าเป็นสถิติทั้งหมด (ไม่มี month parameter) หรือสถิติรายเดือน
    $is_all_stats = ($filter_month === null || $filter_month === '');
    
    if (!$is_all_stats) {
        // ตรวจสอบรูปแบบ month (YYYY-MM) เฉพาะเมื่อมี filter_month
        if (!preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
            echo json_encode([
                "success" => false,
                "message" => "Invalid month format. Use YYYY-MM format."
            ]);
            exit();
        }
        
        // แยกปีและเดือน
        list($year, $month) = explode('-', $filter_month);
        
        // สร้างวันที่เริ่มต้นและสิ้นสุดของเดือน
        $start_date = $year . '-' . $month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date)); // วันสุดท้ายของเดือน
    } else {
        // สถิติทั้งหมด - ไม่กรองตามวันที่
        $start_date = null;
        $end_date = null;
        $filter_month = 'all'; // กำหนดเป็น 'all' สำหรับ debug
    }

    // Debug log สำหรับ parameters
    file_put_contents(__DIR__ . '/debug_statistics.log',
        "Parameters - User ID: " . $current_user_id . ", Filter Month: " . $filter_month . "\n",
        FILE_APPEND
    );

    // ตรวจสอบข้อมูลในตารางก่อน
    if ($is_all_stats) {
        // สถิติทั้งหมด - ไม่กรองตามวันที่
        $check_sql = "SELECT COUNT(*) as total_items, 
                             COUNT(CASE WHEN id.status = 'expired' THEN 1 END) as expired_items,
                             COUNT(CASE WHEN id.status = 'disposed' THEN 1 END) as disposed_items,
                             MIN(id.used_date) as min_date,
                             MAX(id.used_date) as max_date
                      FROM item_details id
                      INNER JOIN items i ON id.item_id = i.item_id
                      WHERE i.user_id = :user_id AND (id.status = 'expired' OR id.status = 'disposed')";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
    } else {
        // สถิติรายเดือน - กรองตามวันที่
        $check_sql = "SELECT COUNT(*) as total_items, 
                             COUNT(CASE WHEN id.status = 'expired' THEN 1 END) as expired_items,
                             COUNT(CASE WHEN id.status = 'disposed' THEN 1 END) as disposed_items,
                             MIN(id.used_date) as min_date,
                             MAX(id.used_date) as max_date
                      FROM item_details id
                      INNER JOIN items i ON id.item_id = i.item_id
                      WHERE i.user_id = :user_id AND (id.status = 'expired' OR id.status = 'disposed')
                      AND DATE(id.used_date) >= :start_date AND DATE(id.used_date) <= :end_date";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
        $check_stmt->bindValue(':start_date', $start_date);
        $check_stmt->bindValue(':end_date', $end_date);
    }
    $check_stmt->execute();
    $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    file_put_contents(__DIR__ . '/debug_statistics.log',
        "=== DATABASE CHECK ===\n" .
        "Total items for user $current_user_id: " . ($check_result['total_items'] ?? 0) . "\n" .
        "Expired items: " . ($check_result['expired_items'] ?? 0) . "\n" .
        "Disposed items: " . ($check_result['disposed_items'] ?? 0) . "\n" .
        "Date range: " . ($check_result['min_date'] ?? 'NULL') . " to " . ($check_result['max_date'] ?? 'NULL') . "\n" .
        "======================\n",
        FILE_APPEND
    );

    // Query แบบใหม่ - ดึงข้อมูลจาก item_details แทน items
    if ($is_all_stats) {
        // สถิติทั้งหมด - ไม่กรองตามวันที่
        $expired_sql = "
            SELECT COUNT(*) as count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            WHERE i.user_id = :user_id 
            AND id.status = 'expired'
        ";

        $disposed_sql = "
            SELECT COUNT(*) as count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            WHERE i.user_id = :user_id 
            AND id.status = 'disposed'
        ";

        $total_sql = "
            SELECT COUNT(*) as count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            WHERE i.user_id = :user_id
            AND (id.status = 'expired' OR id.status = 'disposed')
        ";

        $active_sql = "
            SELECT COUNT(*) as count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            WHERE i.user_id = :user_id 
            AND id.status = 'active'
        ";
    } else {
        // สถิติรายเดือน - กรองตามวันที่ used_date สำหรับ expired/disposed
        $expired_sql = "
            SELECT COUNT(*) as count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            WHERE i.user_id = :user_id 
            AND id.status = 'expired'
            AND DATE(id.used_date) >= :start_date AND DATE(id.used_date) <= :end_date
        ";

        $disposed_sql = "
            SELECT COUNT(*) as count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            WHERE i.user_id = :user_id 
            AND id.status = 'disposed'
            AND DATE(id.used_date) >= :start_date AND DATE(id.used_date) <= :end_date
        ";

        $total_sql = "
            SELECT COUNT(*) as count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            WHERE i.user_id = :user_id
            AND (id.status = 'expired' OR id.status = 'disposed')
            AND DATE(id.used_date) >= :start_date AND DATE(id.used_date) <= :end_date
        ";

        $active_sql = "
            SELECT COUNT(*) as count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            WHERE i.user_id = :user_id 
            AND id.status = 'active'
            AND DATE(i.item_date) >= :start_date AND DATE(i.item_date) <= :end_date
        ";
    }

    // Execute queries with proper error handling
    try {
        // Query 1: Expired items
        file_put_contents(__DIR__ . '/debug_statistics.log', "Executing expired query...\n", FILE_APPEND);
        $expired_stmt = $conn->prepare($expired_sql);
        $expired_stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
        if (!$is_all_stats) {
            $expired_stmt->bindValue(':start_date', $start_date);
            $expired_stmt->bindValue(':end_date', $end_date);
        }
        $expired_stmt->execute();
        $expired_result = $expired_stmt->fetch(PDO::FETCH_ASSOC);
        $expired_count = $expired_result ? $expired_result['count'] : 0;
        file_put_contents(__DIR__ . '/debug_statistics.log', "Expired count: " . $expired_count . "\n", FILE_APPEND);

        // Query 2: Disposed items
        file_put_contents(__DIR__ . '/debug_statistics.log', "Executing disposed query...\n", FILE_APPEND);
        $disposed_stmt = $conn->prepare($disposed_sql);
        $disposed_stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
        if (!$is_all_stats) {
            $disposed_stmt->bindValue(':start_date', $start_date);
            $disposed_stmt->bindValue(':end_date', $end_date);
        }
        $disposed_stmt->execute();
        $disposed_result = $disposed_stmt->fetch(PDO::FETCH_ASSOC);
        $disposed_count = $disposed_result ? $disposed_result['count'] : 0;
        file_put_contents(__DIR__ . '/debug_statistics.log', "Disposed count: " . $disposed_count . "\n", FILE_APPEND);

        // Query 3: Total items (เฉพาะ expired/disposed)
        file_put_contents(__DIR__ . '/debug_statistics.log', "Executing total query...\n", FILE_APPEND);
        $total_stmt = $conn->prepare($total_sql);
        $total_stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
        if (!$is_all_stats) {
            $total_stmt->bindValue(':start_date', $start_date);
            $total_stmt->bindValue(':end_date', $end_date);
        }
        $total_stmt->execute();
        $total_result = $total_stmt->fetch(PDO::FETCH_ASSOC);
        $total_count = $total_result ? $total_result['count'] : 0;
        file_put_contents(__DIR__ . '/debug_statistics.log', "Total count: " . $total_count . "\n", FILE_APPEND);

        // Query 4: Active items
        file_put_contents(__DIR__ . '/debug_statistics.log', "Executing active query...\n", FILE_APPEND);
        $active_stmt = $conn->prepare($active_sql);
        $active_stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
        if (!$is_all_stats) {
            $active_stmt->bindValue(':start_date', $start_date);
            $active_stmt->bindValue(':end_date', $end_date);
        }
        $active_stmt->execute();
        $active_result = $active_stmt->fetch(PDO::FETCH_ASSOC);
        $active_count = $active_result ? $active_result['count'] : 0;
        file_put_contents(__DIR__ . '/debug_statistics.log', "Active count: " . $active_count . "\n", FILE_APPEND);

    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/debug_statistics.log', 
            "Query execution error: " . $e->getMessage() . "\n" .
            "Error code: " . $e->getCode() . "\n", 
            FILE_APPEND
        );
        throw $e;
    }

    // ถ้าต้องการข้อมูลรายละเอียดเพิ่มเติมตามหมวดหมู่
    if ($is_all_stats) {
        // สถิติทั้งหมด - ไม่กรองตามวันที่
        $category_stats_sql = "
            SELECT 
                COALESCE(t.type_name, 'ไม่ระบุหมวดหมู่') as category,
                COUNT(CASE WHEN id.status = 'expired' THEN 1 END) as expired_count,
                COUNT(CASE WHEN id.status = 'disposed' THEN 1 END) as disposed_count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            LEFT JOIN types t ON i.type_id = t.type_id
            WHERE i.user_id = :user_id
            AND (id.status = 'expired' OR id.status = 'disposed')
            GROUP BY t.type_name
            HAVING (expired_count > 0 OR disposed_count > 0)
            ORDER BY t.type_name
        ";
    } else {
        // สถิติรายเดือน - กรองตามวันที่
        $category_stats_sql = "
            SELECT 
                COALESCE(t.type_name, 'ไม่ระบุหมวดหมู่') as category,
                COUNT(CASE WHEN id.status = 'expired' THEN 1 END) as expired_count,
                COUNT(CASE WHEN id.status = 'disposed' THEN 1 END) as disposed_count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            LEFT JOIN types t ON i.type_id = t.type_id
            WHERE i.user_id = :user_id
            AND (id.status = 'expired' OR id.status = 'disposed')
            AND DATE(id.used_date) >= :start_date AND DATE(id.used_date) <= :end_date
            GROUP BY t.type_name
            HAVING (expired_count > 0 OR disposed_count > 0)
            ORDER BY t.type_name
        ";
    }

    // Category statistics with error handling
    try {
        file_put_contents(__DIR__ . '/debug_statistics.log', "Executing category stats query...\n", FILE_APPEND);
        $category_stmt = $conn->prepare($category_stats_sql);
        $category_stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
        if (!$is_all_stats) {
            $category_stmt->bindValue(':start_date', $start_date);
            $category_stmt->bindValue(':end_date', $end_date);
        }
        $category_stmt->execute();
        $category_stats = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
        file_put_contents(__DIR__ . '/debug_statistics.log', "Category stats count: " . count($category_stats) . "\n", FILE_APPEND);
    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/debug_statistics.log', 
            "Category query error: " . $e->getMessage() . "\n", 
            FILE_APPEND
        );
        $category_stats = [];
    }

    // สถิติตามตำแหน่งเก็บ
    if ($is_all_stats) {
        // สถิติทั้งหมด - ไม่กรองตามวันที่
        $location_stats_sql = "
            SELECT 
                COALESCE(a.area_name, 'ไม่ระบุตำแหน่ง') as location,
                COUNT(CASE WHEN id.status = 'expired' THEN 1 END) as expired_count,
                COUNT(CASE WHEN id.status = 'disposed' THEN 1 END) as disposed_count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            LEFT JOIN areas a ON id.area_id = a.area_id
            WHERE i.user_id = :user_id
            AND (id.status = 'expired' OR id.status = 'disposed')
            GROUP BY a.area_name
            HAVING (expired_count > 0 OR disposed_count > 0)
            ORDER BY a.area_name
        ";
    } else {
        // สถิติรายเดือน - กรองตามวันที่
        $location_stats_sql = "
            SELECT 
                COALESCE(a.area_name, 'ไม่ระบุตำแหน่ง') as location,
                COUNT(CASE WHEN id.status = 'expired' THEN 1 END) as expired_count,
                COUNT(CASE WHEN id.status = 'disposed' THEN 1 END) as disposed_count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            LEFT JOIN areas a ON id.area_id = a.area_id
            WHERE i.user_id = :user_id
            AND (id.status = 'expired' OR id.status = 'disposed')
            AND DATE(id.used_date) >= :start_date AND DATE(id.used_date) <= :end_date
            GROUP BY a.area_name
            HAVING (expired_count > 0 OR disposed_count > 0)
            ORDER BY a.area_name
        ";
    }

    // Location statistics with error handling
    try {
        file_put_contents(__DIR__ . '/debug_statistics.log', "Executing location stats query...\n", FILE_APPEND);
        $location_stmt = $conn->prepare($location_stats_sql);
        $location_stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
        if (!$is_all_stats) {
            $location_stmt->bindValue(':start_date', $start_date);
            $location_stmt->bindValue(':end_date', $end_date);
        }
        $location_stmt->execute();
        $location_stats = $location_stmt->fetchAll(PDO::FETCH_ASSOC);
        file_put_contents(__DIR__ . '/debug_statistics.log', "Location stats count: " . count($location_stats) . "\n", FILE_APPEND);
    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/debug_statistics.log', 
            "Location query error: " . $e->getMessage() . "\n", 
            FILE_APPEND
        );
        $location_stats = [];
    }

    // สถิติตามประเภทสิ่งของเฉพาะ (product breakdown) - จัดกลุ่มตามคำหลัก
    if ($is_all_stats) {
        // สถิติทั้งหมด - ไม่กรองตามวันที่
        $product_stats_sql = "
            SELECT 
                CASE 
                    WHEN LOWER(i.item_name) LIKE '%มาม่า%' OR LOWER(i.item_name) LIKE '%บะหมี่%' OR LOWER(i.item_name) LIKE '%เส้น%' THEN 'มาม่า/บะหมี่'
                    WHEN LOWER(i.item_name) LIKE '%ข้าว%' THEN 'ข้าว'
                    WHEN LOWER(i.item_name) LIKE '%น้ำ%' AND LOWER(i.item_name) NOT LIKE '%น้ำตาล%' AND LOWER(i.item_name) NOT LIKE '%น้ำปลา%' THEN 'น้ำ'
                    WHEN LOWER(i.item_name) LIKE '%น้ำปลา%' OR LOWER(i.item_name) LIKE '%ปลา%' THEN 'ปลา/น้ำปลา'
                    WHEN LOWER(i.item_name) LIKE '%น้ำตาล%' OR LOWER(i.item_name) LIKE '%ตาล%' THEN 'น้ำตาล'
                    WHEN LOWER(i.item_name) LIKE '%ปากกา%' OR LOWER(i.item_name) LIKE '%ดินสอ%' OR LOWER(i.item_name) LIKE '%ปากกาลูกลื่น%' THEN 'เครื่องเขียน'
                    WHEN LOWER(i.item_name) LIKE '%ชา%' OR LOWER(i.item_name) LIKE '%กาแฟ%' THEN 'เครื่องดื่มร้อน'
                    WHEN LOWER(i.item_name) LIKE '%โค้ก%' OR LOWER(i.item_name) LIKE '%โซดา%' OR LOWER(i.item_name) LIKE '%น้ำอัดลม%' THEN 'น้ำอัดลม'
                    WHEN LOWER(i.item_name) LIKE '%นม%' THEN 'นม'
                    WHEN LOWER(i.item_name) LIKE '%ขนม%' OR LOWER(i.item_name) LIKE '%บิสกิต%' THEN 'ขนมขบเคี้ยว'
                    WHEN LOWER(i.item_name) LIKE '%ผัก%' OR LOWER(i.item_name) LIKE '%ใบไผ่%' THEN 'ผักใบ'
                    WHEN LOWER(i.item_name) LIKE '%เนื้อ%' OR LOWER(i.item_name) LIKE '%หมู%' OR LOWER(i.item_name) LIKE '%ไก่%' THEN 'เนื้อสัตว์'
                    WHEN LOWER(i.item_name) LIKE '%ผลไม้%' OR LOWER(i.item_name) LIKE '%แอปเปิ้ล%' OR LOWER(i.item_name) LIKE '%กล้วย%' OR LOWER(i.item_name) LIKE '%ส้ม%' THEN 'ผลไม้'
                    ELSE 'อื่นๆ'
                END as item_category,
                COUNT(CASE WHEN id.status = 'expired' THEN 1 END) as expired_count,
                COUNT(CASE WHEN id.status = 'disposed' THEN 1 END) as disposed_count,
                COUNT(*) as total_count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            WHERE i.user_id = :user_id
            AND (id.status = 'expired' OR id.status = 'disposed')
            GROUP BY item_category
            HAVING (expired_count > 0 OR disposed_count > 0)
            ORDER BY total_count DESC, item_category
        ";
    } else {
        // สถิติรายเดือน - กรองตามวันที่
        $product_stats_sql = "
            SELECT 
                CASE 
                    WHEN LOWER(i.item_name) LIKE '%มาม่า%' OR LOWER(i.item_name) LIKE '%บะหมี่%' OR LOWER(i.item_name) LIKE '%เส้น%' THEN 'มาม่า/บะหมี่'
                    WHEN LOWER(i.item_name) LIKE '%ข้าว%' THEN 'ข้าว'
                    WHEN LOWER(i.item_name) LIKE '%น้ำ%' AND LOWER(i.item_name) NOT LIKE '%น้ำตาล%' AND LOWER(i.item_name) NOT LIKE '%น้ำปลา%' THEN 'น้ำ'
                    WHEN LOWER(i.item_name) LIKE '%น้ำปลา%' OR LOWER(i.item_name) LIKE '%ปลา%' THEN 'ปลา/น้ำปลา'
                    WHEN LOWER(i.item_name) LIKE '%น้ำตาล%' OR LOWER(i.item_name) LIKE '%ตาล%' THEN 'น้ำตาล'
                    WHEN LOWER(i.item_name) LIKE '%ปากกา%' OR LOWER(i.item_name) LIKE '%ดินสอ%' OR LOWER(i.item_name) LIKE '%ปากกาลูกลื่น%' THEN 'เครื่องเขียน'
                    WHEN LOWER(i.item_name) LIKE '%ชา%' OR LOWER(i.item_name) LIKE '%กาแฟ%' THEN 'เครื่องดื่มร้อน'
                    WHEN LOWER(i.item_name) LIKE '%โค้ก%' OR LOWER(i.item_name) LIKE '%โซดา%' OR LOWER(i.item_name) LIKE '%น้ำอัดลม%' THEN 'น้ำอัดลม'
                    WHEN LOWER(i.item_name) LIKE '%นม%' THEN 'นม'
                    WHEN LOWER(i.item_name) LIKE '%ขนม%' OR LOWER(i.item_name) LIKE '%บิสกิต%' THEN 'ขนมขบเคี้ยว'
                    WHEN LOWER(i.item_name) LIKE '%ผัก%' OR LOWER(i.item_name) LIKE '%ใบไผ่%' THEN 'ผักใบ'
                    WHEN LOWER(i.item_name) LIKE '%เนื้อ%' OR LOWER(i.item_name) LIKE '%หมู%' OR LOWER(i.item_name) LIKE '%ไก่%' THEN 'เนื้อสัตว์'
                    WHEN LOWER(i.item_name) LIKE '%ผลไม้%' OR LOWER(i.item_name) LIKE '%แอปเปิ้ล%' OR LOWER(i.item_name) LIKE '%กล้วย%' OR LOWER(i.item_name) LIKE '%ส้ม%' THEN 'ผลไม้'
                    ELSE 'อื่นๆ'
                END as item_category,
                COUNT(CASE WHEN id.status = 'expired' THEN 1 END) as expired_count,
                COUNT(CASE WHEN id.status = 'disposed' THEN 1 END) as disposed_count,
                COUNT(*) as total_count
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            WHERE i.user_id = :user_id
            AND (id.status = 'expired' OR id.status = 'disposed')
            AND DATE(id.used_date) >= :start_date AND DATE(id.used_date) <= :end_date
            GROUP BY item_category
            HAVING (expired_count > 0 OR disposed_count > 0)
            ORDER BY total_count DESC, item_category
        ";
    }

    // Product statistics with error handling
    try {
        file_put_contents(__DIR__ . '/debug_statistics.log', "Executing product stats query...\n", FILE_APPEND);
        $product_stmt = $conn->prepare($product_stats_sql);
        $product_stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
        if (!$is_all_stats) {
            $product_stmt->bindValue(':start_date', $start_date);
            $product_stmt->bindValue(':end_date', $end_date);
        }
        $product_stmt->execute();
        $product_stats = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
        file_put_contents(__DIR__ . '/debug_statistics.log', "Product stats count: " . count($product_stats) . "\n", FILE_APPEND);
    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/debug_statistics.log', 
            "Product query error: " . $e->getMessage() . "\n", 
            FILE_APPEND
        );
        $product_stats = [];
    }

    // คำนวณเปรียบเทียบกับเดือนก่อน (สำหรับ expired items เท่านั้น)
    $expired_change_percent = 0;
    if (!$is_all_stats) {
        // เดือนก่อน
        $prev_month_date = date('Y-m-d', strtotime("$start_date -1 month"));
        $prev_start_date = date('Y-m-01', strtotime($prev_month_date));
        $prev_end_date = date('Y-m-t', strtotime($prev_month_date));
        
        try {
            $prev_expired_sql = "
                SELECT COUNT(*) as count
                FROM item_details id
                INNER JOIN items i ON id.item_id = i.item_id
                WHERE i.user_id = :user_id 
                AND id.status = 'expired'
                AND DATE(id.used_date) >= :prev_start_date AND DATE(id.used_date) <= :prev_end_date
            ";
            
            $prev_stmt = $conn->prepare($prev_expired_sql);
            $prev_stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
            $prev_stmt->bindValue(':prev_start_date', $prev_start_date);
            $prev_stmt->bindValue(':prev_end_date', $prev_end_date);
            $prev_stmt->execute();
            $prev_result = $prev_stmt->fetch(PDO::FETCH_ASSOC);
            $prev_expired_count = $prev_result ? $prev_result['count'] : 0;
            
            // คำนวณเปอร์เซ็นต์การเปลี่ยนแปลง
            if ($prev_expired_count > 0) {
                $expired_change_percent = (($expired_count - $prev_expired_count) / $prev_expired_count) * 100;
            } else if ($expired_count > 0) {
                $expired_change_percent = 100; // เพิ่มขึ้น 100% หากเดือนก่อนไม่มีข้อมูล
            }
            
        } catch (PDOException $e) {
            file_put_contents(__DIR__ . '/debug_statistics.log', 
                "Previous month comparison error: " . $e->getMessage() . "\n", 
                FILE_APPEND
            );
            $expired_change_percent = 0;
        }
    }

    // ดึงรายการเดือนที่มีข้อมูล (สำหรับการเลื่อนดูเดือน)
    $available_months = [];
    try {
        $months_sql = "
            SELECT DISTINCT DATE_FORMAT(id.used_date, '%Y-%m') as month_key
            FROM item_details id
            INNER JOIN items i ON id.item_id = i.item_id
            WHERE i.user_id = :user_id 
            AND (id.status = 'expired' OR id.status = 'disposed')
            AND id.used_date IS NOT NULL
            ORDER BY month_key DESC
        ";
        
        $months_stmt = $conn->prepare($months_sql);
        $months_stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
        $months_stmt->execute();
        $months_result = $months_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($months_result as $row) {
            if (!empty($row['month_key'])) {
                $available_months[] = $row['month_key'];
            }
        }
        
        file_put_contents(__DIR__ . '/debug_statistics.log', 
            "Available months: " . json_encode($available_months) . "\n", 
            FILE_APPEND
        );
        
    } catch (PDOException $e) {
        file_put_contents(__DIR__ . '/debug_statistics.log', 
            "Available months query error: " . $e->getMessage() . "\n", 
            FILE_APPEND
        );
        $available_months = [];
    }

    $debug_data = [
        "total_items" => intval($total_count),
        "expired_items" => intval($expired_count),
        "disposed_items" => intval($disposed_count),
        "active_items" => intval($active_count),
        "month" => strval($filter_month),
        "expired_change_percent" => floatval(round($expired_change_percent, 1)),
        "available_months" => $available_months, // เพิ่มรายการเดือนที่มีข้อมูล
        "category_breakdown" => $category_stats,
        "location_breakdown" => $location_stats,
        "product_breakdown" => $product_stats,
        "debug_info" => [
            "user_id" => $current_user_id,
            "filter_month" => $filter_month,
            "is_all_stats" => $is_all_stats,
            "start_date" => $start_date,
            "end_date" => $end_date,
            "timestamp" => date('Y-m-d H:i:s'),
            "database_check" => $check_result,
            "available_months_count" => count($available_months)
        ]
    ];
    
    file_put_contents(__DIR__ . '/debug_statistics.log',
        "==================== DEBUG API OUTPUT ====================\n" .
        "Success: true\n" .
        "Response data: " . json_encode($debug_data, JSON_PRETTY_PRINT) .
        "\n===========================================================\n",
        FILE_APPEND
    );
    
    echo json_encode([
        "success" => true,
        "data" => $debug_data
    ]);

} catch (PDOException $e) {
    file_put_contents(__DIR__ . '/debug_statistics.log',
        "==================== DATABASE ERROR ====================\n" .
        "Error Message: " . $e->getMessage() . "\n" .
        "Error Code: " . $e->getCode() . "\n" .
        "File: " . $e->getFile() . "\n" .
        "Line: " . $e->getLine() . "\n" .
        "Trace: " . $e->getTraceAsString() . "\n" .
        "=========================================================\n",
        FILE_APPEND
    );
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => $e->getCode(),
        'debug_info' => [
            'user_id' => $current_user_id ?? 'unknown',
            'filter_month' => $filter_month ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/debug_statistics.log',
        "==================== GENERAL ERROR ====================\n" .
        "Error Message: " . $e->getMessage() . "\n" .
        "Error Code: " . $e->getCode() . "\n" .
        "File: " . $e->getFile() . "\n" .
        "Line: " . $e->getLine() . "\n" .
        "=========================================================\n",
        FILE_APPEND
    );
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unexpected error: ' . $e->getMessage(),
        'debug_info' => [
            'user_id' => $current_user_id ?? 'unknown',
            'filter_month' => $filter_month ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
?>