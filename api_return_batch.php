<?php
include 'db_connect.php';

$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (empty($data['items'])) {
    echo json_encode(['status' => 'error', 'msg' => 'ไม่พบรายการที่เลือก']);
    exit;
}

$operator = $data['operator'];
$note = $data['note'];
$items = $data['items'];

$success_count = 0;
$errors = [];

foreach ($items as $sn) {
    $check = $conn->query("SELECT * FROM product_serials WHERE serial_number = '$sn'");
    if($check->num_rows == 0) {
        $errors[] = "$sn: ไม่พบ S/N";
        continue;
    }
    $item = $check->fetch_assoc();

    if($item['status'] == 'available') {
        $errors[] = "$sn: สถานะว่างอยู่แล้ว";
        continue;
    }
    
    $project_id = $item['project_id'];
    $barcode = $item['product_barcode'];

    // [✨] ดึงชื่อโปรเจกต์ไว้ก่อน (Snapshot Name)
    $proj_q = $conn->query("SELECT project_name FROM projects WHERE id = '$project_id'");
    $project_name = ($proj_q && $proj_q->num_rows > 0) ? $proj_q->fetch_assoc()['project_name'] : '';

    $sql = "UPDATE product_serials SET project_id = NULL, status = 'available' WHERE serial_number = '$sn'";
    
    if($conn->query($sql)) {
        $conn->query("UPDATE products SET quantity = quantity + 1 WHERE barcode = '$barcode'");
        
        // [✨] บันทึกประวัติ พร้อมชื่อโปรเจกต์
        $stmt = $conn->prepare("INSERT INTO product_history (serial_number, project_id, project_name, action_type, note, operator) VALUES (?, ?, ?, 'return', ?, ?)");
        $stmt->bind_param("sisss", $sn, $project_id, $project_name, $note, $operator);
        
        if(!$stmt->execute()) {
             $stmt_old = $conn->prepare("INSERT INTO product_history (serial_number, project_id, action_type, note, operator) VALUES (?, ?, 'return', ?, ?)");
             $stmt_old->bind_param("siss", $sn, $project_id, $note, $operator);
             $stmt_old->execute();
        }
        
        $success_count++;
    } else {
        $errors[] = "$sn: เกิดข้อผิดพลาด";
    }
}

if (count($errors) == 0) {
    echo json_encode(['status' => 'success', 'msg' => "คืนสำเร็จครบ $success_count รายการ"]);
} else {
    echo json_encode(['status' => 'partial_error', 'msg' => "สำเร็จ $success_count รายการ, มีปัญหา " . count($errors), 'errors' => $errors]);
}
?>