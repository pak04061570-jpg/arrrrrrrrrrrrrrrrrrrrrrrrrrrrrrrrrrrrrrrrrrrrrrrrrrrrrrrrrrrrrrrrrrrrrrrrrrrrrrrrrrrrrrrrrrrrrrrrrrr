<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติสินค้า</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <style> body{ font-family: 'Sarabun', sans-serif; background: #f8f9fa; padding: 30px; } </style>
</head>
<body>

<div class="container bg-white p-4 rounded shadow-sm">
    <h4 class="mb-4"><i class="fas fa-history text-primary"></i> Timeline ประวัติสินค้า</h4>
    
    <form method="get" class="mb-4 d-flex gap-2">
        <input type="text" name="sn" class="form-control" placeholder="ระบุ Serial Number..." value="<?php echo $_GET['sn'] ?? ''; ?>" required>
        <button type="submit" class="btn btn-primary">ค้นหา</button>
    </form>

    <?php if(isset($_GET['sn'])): 
        $sn = $_GET['sn'];
        // [✨] Query: ดึงชื่อจากประวัติ (snapshot_name) และจากตารางปัจจุบัน (current_name)
        $sql = "SELECT h.*, 
                       h.project_name AS snapshot_name, 
                       p.project_name AS current_name 
                FROM product_history h 
                LEFT JOIN projects p ON h.project_id = p.id 
                WHERE h.serial_number = '$sn' 
                ORDER BY h.id DESC";
        $result = $conn->query($sql);
    ?>
    
    <h5 class="text-secondary">S/N: <strong><?php echo $sn; ?></strong></h5>
    <table class="table table-bordered mt-3 align-middle">
        <thead class="table-light">
            <tr>
                <th>วัน/เวลา</th>
                <th class="text-center">เหตุการณ์</th>
                <th>รายละเอียด / โครงการ</th>
                <th>หมายเหตุ (Note)</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): 
                $badge = '';
                if($row['action_type'] == 'import') $badge = '<span class="badge bg-success">รับเข้า</span>';
                if($row['action_type'] == 'export') $badge = '<span class="badge bg-danger">เบิกออก</span>';
                if($row['action_type'] == 'return') $badge = '<span class="badge bg-warning text-dark">รับคืน</span>';

                // [✨] Logic: เลือกชื่อที่ถูกต้อง
                $show_name = !empty($row['snapshot_name']) ? $row['snapshot_name'] : $row['current_name'];
                
                if(empty($show_name) && $row['project_id'] > 0) {
                    $show_name = '<span class="text-muted fst-italic">(โครงการถูกลบ)</span>';
                } elseif(empty($show_name)) {
                    $show_name = "-";
                }
            ?>
            <tr>
                <td><?php echo $row['action_date']; ?></td>
                <td class="text-center"><?php echo $badge; ?></td>
                <td><?php echo "โครงการ: " . $show_name; ?></td>
                <td>
                    <span id="note_text_<?php echo $row['id']; ?>"><?php echo $row['note']; ?></span>
                    <button class="btn btn-sm text-secondary ms-2" onclick="editNote(<?php echo $row['id']; ?>, '<?php echo $row['note']; ?>')">
                        <i class="fas fa-pen"></i>
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function editNote(id, oldNote) {
        Swal.fire({
            title: 'แก้ไขหมายเหตุ',
            input: 'text',
            inputValue: oldNote,
            showCancelButton: true,
            confirmButtonText: 'บันทึก'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post("api_update_history.php", { id: id, note: result.value }, function(res) {
                    if(res.trim() == 'success') {
                        document.getElementById('note_text_' + id).innerText = result.value;
                        Swal.fire({ icon: 'success', title: 'แก้ไขแล้ว', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
                    } else {
                        Swal.fire('Error', res, 'error');
                    }
                });
            }
        })
    }
</script>

</body>
</html>