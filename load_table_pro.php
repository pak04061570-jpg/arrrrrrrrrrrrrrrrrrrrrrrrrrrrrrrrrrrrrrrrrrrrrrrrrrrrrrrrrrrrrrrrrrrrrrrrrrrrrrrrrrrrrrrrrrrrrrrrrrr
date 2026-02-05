<?php
include 'db_connect.php';

// SQL ใหม่: เพิ่ม LEFT JOIN units
$sql = "SELECT p.*, 
               t.name AS type_name, 
               s.name AS supplier_name,
               u.name AS unit_name     /* เพิ่ม */
        FROM products p 
        LEFT JOIN product_types t ON p.type_id = t.id 
        LEFT JOIN suppliers s ON p.supplier_id = s.id 
        LEFT JOIN units u ON p.unit_id = u.id /* JOIN ตารางหน่วย */
        ORDER BY p.last_updated DESC";

$result = $conn->query($sql);
?>
<table id="proTable" class="table table-hover align-middle w-100">
    <thead class="table-light">
        <tr>
            <th width="15%">SKU / Code</th>
            <th width="30%">สินค้า</th>
            <th width="15%">ประเภท</th>
            <th width="15%">Supplier</th>
            
            <th class="text-end">ราคา</th>
            <th class="text-center">สถานะ</th>
            <th class="text-center">จัดการ</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = $result->fetch_assoc()): 
            if($row['quantity'] == 0) $bg = 'bg-danger';
            elseif($row['quantity'] < 5) $bg = 'bg-warning text-dark';
            else $bg = 'bg-success';
        ?>
        <tr>
            <td><span class="badge bg-light text-dark border"><?php echo $row['barcode']; ?></span></td>
            <td>
                <div class="fw-bold"><?php echo $row['name']; ?></div>
                <small class="text-muted">หน่วย: <?php echo $row['unit_name']; ?></small>
            </td>
            
            <td>
                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                    <?php echo $row['type_name']; ?>
                </span>
            </td>

            <td>
                <small class="text-secondary">
                    <i class="fas fa-store me-1"></i><?php echo $row['supplier_name']; ?>
                </small>
            </td>

            <td class="text-end text-primary fw-bold"><?php echo number_format($row['price_sell'], 2); ?></td>
            <td class="text-center"><span class="badge <?php echo $bg; ?> rounded-pill"><?php echo $row['quantity']; ?></span></td>
            <td class="text-center">
                <a href="product_details.php?barcode=<?php echo $row['barcode']; ?>" 
                   class="btn btn-sm btn-outline-primary rounded-pill me-1" 
                   title="ดู Serial Number">
                    <i class="fas fa-barcode"></i> S/N
                </a>
                
                <button class="btn btn-sm btn-outline-warning rounded-pill px-3" 
                    onclick="openEditModal(
                        <?php echo $row['id']; ?>, 
                        '<?php echo $row['name']; ?>', 
                        '<?php echo $row['type_name']; ?>',     /* แก้เป็น type_name */
                        '<?php echo $row['supplier_name']; ?>', /* แก้เป็น supplier_name */
                        <?php echo $row['price_sell']; ?>, 
                        <?php echo $row['quantity']; ?>, 
                        '<?php echo $row['unit_name']; ?>'
                    )">
                    <i class="fas fa-pen me-1"></i> แก้ไข
                </button>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>