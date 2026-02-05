<?php
include 'db_connect.php';

// รับข้อมูล JSON ที่ส่งมาเป็น Array List
// รูปแบบ: [{barcode: '...', sn: '...', operator: '...'}, {...}]
$json_data = file_get_contents('php://input');
$items = json_decode($json_data, true);

if (empty($items)) {
    echo json_encode(['status' => 'error', 'msg' => 'ไม่พบรายการที่เลือก']);
    exit;
}

$success_count = 0;
$errors = [];

foreach ($items as $item) {
    $barcode = $conn->real_escape_string($item['barcode']);
    $sn = $conn->real_escape_string($item['sn']);
    $operator = $conn->real_escape_string($item['operator']); // รับชื่อผู้รับของ

    // 1. เช็คว่า S/N ซ้ำไหม
    $check = $conn->query("SELECT id FROM product_serials WHERE serial_number = '$sn'");
    if($check->num_rows > 0) {
        $errors[] = "$sn: มีในระบบแล้ว";
        continue;
    }

    // 2. บันทึกลงตาราง S/N (เพิ่มใหม่)
    $sql = "INSERT INTO product_serials (product_barcode, serial_number, status, date_added) 
            VALUES ('$barcode', '$sn', 'available', NOW())";
    
    if($conn->query($sql)) {
        // 3. เพิ่มจำนวนในตารางสินค้าหลัก (+1)
        $conn->query("UPDATE products SET quantity = quantity + 1 WHERE barcode = '$barcode'");

        // 4. บันทึกประวัติ (History) ว่า "import" พร้อมชื่อผู้รับของ
        $stmt = $conn->prepare("INSERT INTO product_history (serial_number, action_type, operator, note) VALUES (?, 'import', ?, 'รับสินค้าเข้าใหม่')");
        $stmt->bind_param("ss", $sn, $operator);
        $stmt->execute();
        
        $success_count++;
    } else {
        $errors[] = "$sn: บันทึกไม่สำเร็จ (" . $conn->error . ")";
    }
}

// สรุปผลลัพธ์ส่งกลับไปหน้าบ้าน
if (count($errors) == 0) {
    echo json_encode(['status' => 'success', 'msg' => "รับเข้าสำเร็จครบ $success_count รายการ"]);
} else {
    echo json_encode([
        'status' => 'partial_error', 
        'msg' => "สำเร็จ $success_count รายการ, มีปัญหา " . count($errors), 
        'errors' => $errors
    ]);
}
?>