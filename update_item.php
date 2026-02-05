<?php
include 'db_connect.php';

if(isset($_POST['id'])){
    $id = $_POST['id'];
    $name = $conn->real_escape_string($_POST['name']);
    $price = $_POST['price'];
    $unit = $conn->real_escape_string($_POST['unit']);
    
    // รับค่าที่เป็นข้อความจากหน้าเว็บ (Type & Supplier)
    $type_input = trim($_POST['type']);
    $supplier_input = trim($_POST['supplier']);

    // --- ฟังก์ชันหา ID หรือสร้างใหม่ (Logic เดียวกับ api_add_product) ---
    function getOrCreateID($conn, $table, $col, $val) {
        if(empty($val)) return "NULL";
        
        $safe_val = $conn->real_escape_string($val);
        
        // 1. ลองหา ID เดิม
        $sql_check = "SELECT id FROM $table WHERE $col = '$safe_val'";
        $result = $conn->query($sql_check);
        if ($result->num_rows > 0) {
            return $result->fetch_assoc()['id'];
        } else {
            // 2. ถ้าไม่เจอ ให้สร้างใหม่
            $sql_insert = "INSERT INTO $table ($col) VALUES ('$safe_val')";
            if($conn->query($sql_insert)) return $conn->insert_id;
            else return "NULL";
        }
    }
    // ----------------------------------------------------------------

    // แปลงข้อความให้เป็น ID
    $type_id = getOrCreateID($conn, 'product_types', 'name', $type_input);
    $supplier_id = getOrCreateID($conn, 'suppliers', 'name', $supplier_input);

    // อัปเดตข้อมูลลงตาราง products (ใช้ type_id และ supplier_id)
    $sql = "UPDATE products SET 
            name='$name', 
            type_id=$type_id, 
            supplier_id=$supplier_id, 
            price_sell='$price', 
            unit='$unit' 
            WHERE id=$id";

    if($conn->query($sql) === TRUE) {
        echo "success";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>