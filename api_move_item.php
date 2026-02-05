<?php
include 'db_connect.php';

// รับค่า action ถ้าไม่มีให้ถือว่าเป็น 'move' (เบิกทันทีแบบเดิม)
$action = isset($_POST['action']) ? $_POST['action'] : 'move';

// --------------------------------------------------------
// โหมดที่ 1: เช็คสถานะสินค้าเฉยๆ (สำหรับตอนยิงลงตะกร้า)
// --------------------------------------------------------
if ($action == 'check_status') {
    $sn = $_POST['sn'];

    // ค้นหาและจอยตารางสินค้าเพื่อเอาชื่อมาโชว์
    $sql = "SELECT s.*, p.name as product_name 
            FROM product_serials s 
            JOIN products p ON s.product_barcode = p.barcode 
            WHERE s.serial_number = '$sn'";
    
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['status'] == 'available') {
            echo json_encode(['status' => 'available', 'product_name' => $row['product_name']]);
        } else {
            echo json_encode(['status' => 'unavailable', 'msg' => "ไม่ว่าง ({$row['status']})"]);
        }
    } else {
        echo json_encode(['status' => 'not_found', 'msg' => 'ไม่พบ S/N นี้']);
    }
    exit; // จบการทำงานตรงนี้สำหรับโหมดเช็ค
}

// --------------------------------------------------------
// โหมดที่ 2: เบิกสินค้า (Move) - โค้ดเดิม
// --------------------------------------------------------
$sn = $_POST['sn'];
$pid = $_POST['project_id'];
$operator = isset($_POST['operator']) ? $_POST['operator'] : ''; 

$check_item = $conn->query("SELECT status FROM product_serials WHERE serial_number = '$sn'");
if ($check_item->num_rows == 0) { echo json_encode(['status'=>'error', 'msg'=>'ไม่พบ S/N']); exit; }
$item = $check_item->fetch_assoc();

if ($item['status'] != 'available') { echo json_encode(['status'=>'error', 'msg'=>'สินค้านี้ไม่ว่าง (อาจถูกเบิกไปแล้ว)']); exit; }

$sql = "UPDATE product_serials SET project_id = $pid, status = 'sold', date_added = NOW() WHERE serial_number = '$sn'";

if($conn->query($sql)) {
    $get_bc = $conn->query("SELECT product_barcode FROM product_serials WHERE serial_number = '$sn'")->fetch_assoc();
    $barcode = $get_bc['product_barcode'];
    $conn->query("UPDATE products SET quantity = quantity - 1 WHERE barcode = '$barcode'");

    $stmt = $conn->prepare("INSERT INTO product_history (serial_number, project_id, action_type, operator) VALUES (?, ?, 'export', ?)");
    $stmt->bind_param("sis", $sn, $pid, $operator);
    $stmt->execute();

    echo json_encode(['status'=>'success']);
} else {
    echo json_encode(['status'=>'error', 'msg'=>$conn->error]);
}
?>