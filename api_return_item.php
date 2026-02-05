<?php
include 'db_connect.php';

$sn = $_POST['sn'];
$note = isset($_POST['note']) ? $_POST['note'] : '';
$operator = isset($_POST['operator']) ? $_POST['operator'] : ''; // <--- 1. รับค่าชื่อผู้ทำรายการ

// เช็คข้อมูลสินค้า
$check = $conn->query("SELECT * FROM product_serials WHERE serial_number = '$sn'");
if($check->num_rows == 0) {
    echo json_encode(['status'=>'error', 'msg'=>'ไม่พบ S/N นี้ในระบบ']);
    exit;
}
$item = $check->fetch_assoc();

// เช็คสถานะ (ถ้า available แปลว่าคืนไปแล้ว)
if($item['status'] == 'available') {
    echo json_encode(['status'=>'error', 'msg'=>'สินค้านี้สถานะว่างอยู่แล้ว (อาจถูกคืนไปแล้ว)']);
    exit;
}

$project_id = $item['project_id']; // เก็บ project_id เดิมไว้ก่อนลบ

// เริ่มกระบวนการคืน
// 1. อัปเดตตาราง Serial (ล้าง Project ID, ปรับสถานะ available)
$sql = "UPDATE product_serials SET project_id = NULL, status = 'available' WHERE serial_number = '$sn'";

if($conn->query($sql)) {
    // 2. คืนยอดสต็อก (+1)
    $barcode = $item['product_barcode'];
    $conn->query("UPDATE products SET quantity = quantity + 1 WHERE barcode = '$barcode'");
    
    // 3. บันทึกประวัติ (History) พร้อมชื่อผู้ทำรายการ
    // (ต้องแน่ใจว่าใน Database มีคอลัมน์ operator แล้ว)
    $stmt = $conn->prepare("INSERT INTO product_history (serial_number, project_id, action_type, note, operator) VALUES (?, ?, 'return', ?, ?)");
    $stmt->bind_param("siss", $sn, $project_id, $note, $operator);
    $stmt->execute();
    
    echo json_encode(['status'=>'success']);
} else {
    echo json_encode(['status'=>'error', 'msg'=>$conn->error]);
}
?>