<?php
// test_individual_api.php - ทดสอบ API สำหรับอัปเดตรายการแต่ละชิ้น
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");

echo "<h3>Test Individual Item Update API</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<p>Received POST data:</p>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";
    
    // เรียก API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/project/update_individual_item_status.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<h4>API Response (HTTP $http_code):</h4>";
    echo "<pre>$response</pre>";
    exit;
}

// แสดงฟอร์มทดสอบ
?>
<form method="POST">
    <h4>Test Delete Individual Item Detail:</h4>
    <input type="hidden" name="item_id" value="10092">
    <input type="hidden" name="user_id" value="2">
    <input type="hidden" name="detail_id" value="6">
    <input type="hidden" name="action" value="used">
    
    <p>Test: Mark detail_id 6 of item 10092 as used</p>
    <button type="submit">Test Delete Detail</button>
</form>

<form method="POST">
    <h4>Test by Area and Date:</h4>
    <input type="hidden" name="item_id" value="10092">
    <input type="hidden" name="user_id" value="2">
    <input type="hidden" name="area_id" value="2">
    <input type="hidden" name="expire_date" value="2025-08-09">
    <input type="hidden" name="action" value="expired">
    
    <p>Test: Mark item in area 2, expire 2025-08-09 as expired</p>
    <button type="submit">Test by Area/Date</button>
</form>
