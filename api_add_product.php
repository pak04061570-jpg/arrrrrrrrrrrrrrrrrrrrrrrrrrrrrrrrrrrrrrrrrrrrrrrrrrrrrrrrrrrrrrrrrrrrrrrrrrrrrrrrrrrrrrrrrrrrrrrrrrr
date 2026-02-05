<?php
include 'db_connect.php';

$barcode  = $conn->real_escape_string($_POST['barcode']);
$name     = $conn->real_escape_string($_POST['name']);
$price    = $conn->real_escape_string($_POST['price']);

// รับค่า (ตัดช่องว่าง)
$type_input     = trim($_POST['type']);
$supplier_input = trim($_POST['supplier']);
$unit_input     = trim($_POST['unit']); // รับค่าหน่วยสินค้า

// --------------------------------------------------------------------------
// ฟังก์ชัน: หา ID จากชื่อ (ถ้าไม่มีให้สร้างใหม่)
// --------------------------------------------------------------------------
function getOrCreateID($conn, $table, $col, $val) {
    if($val == "") return "NULL";
    $safe_val = $conn->real_escape_string($val);

    // 1. ค้นหา
    $check = $conn->query("SELECT id FROM $table WHERE $col = '$safe_val'");
    if($check->num_rows > 0) {
        return $check->fetch_assoc()['id'];
    } else {
        // 2. สร้างใหม่
        $insert = $conn->query("INSERT INTO $table ($col) VALUES ('$safe_val')");
        return ($insert) ? $conn->insert_id : "NULL";
    }
}

// หา/สร้าง ID ของ 3 ตารางหลัก
$type_id     = getOrCreateID($conn, 'product_types', 'name', $type_input);
$supplier_id = getOrCreateID($conn, 'suppliers', 'name', $supplier_input);
$unit_id     = getOrCreateID($conn, 'units', 'name', $unit_input); // <-- เพิ่มหน่วยนับ

// เช็ค Barcode ซ้ำ
$chk_dup = $conn->query("SELECT id FROM products WHERE barcode = '$barcode'");
if($chk_dup->num_rows > 0) {
    echo json_encode(['status'=>'error', 'msg'=>'รหัสสินค้านี้มีอยู่แล้ว']);
    exit;
}

// บันทึก (เก็บ unit_id แทน unit เดิม)
$sql = "INSERT INTO products (barcode, name, type_id, supplier_id, unit_id, price_sell, quantity) 
        VALUES ('$barcode', '$name', $type_id, $supplier_id, $unit_id, '$price', 0)";

if($conn->query($sql)) {
    echo json_encode(['status'=>'success']);
} else {
    echo json_encode(['status'=>'error', 'msg'=>$conn->error]);
}
?>