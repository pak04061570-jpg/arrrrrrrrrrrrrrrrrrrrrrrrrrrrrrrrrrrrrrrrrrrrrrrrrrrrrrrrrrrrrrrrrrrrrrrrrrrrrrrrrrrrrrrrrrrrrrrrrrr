<?php 
include 'db_connect.php'; 

$pid = isset($_GET['id']) ? $_GET['id'] : 0;
$selected_batch = isset($_GET['batch']) ? $_GET['batch'] : '';
$export_mode = isset($_GET['export']) ? $_GET['export'] : ''; 
$view_mode = isset($_GET['mode']) ? $_GET['mode'] : 'summary';
$doc_type = isset($_GET['type']) ? $_GET['type'] : 'withdraw'; // withdraw = เบิก, return = คืน

$proj = $conn->query("SELECT * FROM projects WHERE id = $pid")->fetch_assoc();
if(!$proj) die("ไม่พบข้อมูลโปรเจกต์");

// ==========================================
// 1. กำหนดค่าตามประเภทเอกสาร (Logic แยกเบิก/คืน)
// ==========================================
$batch_map = [];
$doc_prefix = ($doc_type == 'return') ? "RTN" : "JOB"; // RTN = Return, JOB = Withdrawal
$default_title = ($doc_type == 'return') ? "ใบคืนสินค้า / Return Note" : "ใบรายการเบิกสินค้า / Job Sheet";

// SQL สำหรับดึงงวด (Batches)
if ($doc_type == 'return') {
    // [แก้ไข] เปลี่ยน date_added เป็น action_date สำหรับตาราง History
    $sql_run = "SELECT DATE_FORMAT(action_date, '%Y-%m-%d %H:%i') as batch_time 
                FROM product_history 
                WHERE project_id = $pid AND action_type = 'return'
                GROUP BY batch_time ORDER BY batch_time ASC";
} else {
    // โหมดเบิก: ดึงจาก Serials ในโปรเจกต์ (ตารางนี้ใช้ date_added ถูกแล้ว)
    $sql_run = "SELECT DATE_FORMAT(date_added, '%Y-%m-%d %H:%i') as batch_time 
                FROM product_serials 
                WHERE project_id = $pid 
                GROUP BY batch_time ORDER BY batch_time ASC";
}

// สร้าง Map เลขงวด
$run_res = $conn->query($sql_run);
$run_counter = 1;
if($run_res) {
    while($row = $run_res->fetch_assoc()){ $batch_map[$row['batch_time']] = $run_counter++; }
}

// ดึงข้อมูล Dropdown
$batches = $conn->query($sql_run); // ใช้ Query เดียวกัน

// ==========================================
// 2. ดึงรายการสินค้า (Items)
// ==========================================
$doc_number = $doc_prefix . "-" . str_pad($proj['id'], 4, '0', STR_PAD_LEFT);
$print_date = date('d/m/Y'); 

if($selected_batch) {
    if(isset($batch_map[$selected_batch])) {
        $doc_number .= "/" . str_pad($batch_map[$selected_batch], 2, '0', STR_PAD_LEFT);
    }
    $print_date = date('d/m/Y', strtotime($selected_batch)); 
}

if ($doc_type == 'return') {
    // --- QUERY รายการคืน (จาก History JOIN Serials JOIN Products) ---
    // [แก้ไข] เปลี่ยน date_added เป็น action_date
    $batch_filter = $selected_batch ? "AND DATE_FORMAT(h.action_date, '%Y-%m-%d %H:%i') = '$selected_batch'" : "";
    
    $sql = "SELECT h.serial_number, h.action_date, 
                   p.name, p.unit, p.barcode 
            FROM product_history h
            LEFT JOIN product_serials s ON h.serial_number = s.serial_number
            LEFT JOIN products p ON s.product_barcode = p.barcode
            WHERE h.project_id = $pid 
              AND h.action_type = 'return' 
              $batch_filter
            ORDER BY p.name ASC, h.serial_number ASC";

} else {
    // --- QUERY รายการเบิก (จาก Serials ในโปรเจกต์) ---
    $batch_filter = $selected_batch ? "AND DATE_FORMAT(s.date_added, '%Y-%m-%d %H:%i') = '$selected_batch'" : "";
    
    $sql = "SELECT s.*, p.name, p.unit, p.barcode 
            FROM product_serials s 
            JOIN products p ON s.product_barcode = p.barcode 
            WHERE s.project_id = $pid 
              $batch_filter
            ORDER BY p.name ASC, s.serial_number ASC";
}

$result = $conn->query($sql);
$detailed_items = [];
$summary_items = [];

if($result) {
    while($row = $result->fetch_assoc()) {
        $detailed_items[] = $row;
        $key = $row['barcode']; 
        if(!isset($summary_items[$key])) {
            $summary_items[$key] = ['name' => $row['name'], 'unit' => $row['unit'], 'qty' => 0];
        }
        $summary_items[$key]['qty']++;
    }
}

// เตรียมข้อความรายละเอียด
$default_detail_text = "";
if($selected_batch) {
    $default_detail_text = "งวดที่ " . ($batch_map[$selected_batch] ?? '-') . " (เวลา: " . date('H:i น.', strtotime($selected_batch)) . ")";
} else {
    $default_detail_text = "รวมรายการทั้งหมด";
}

// ==========================================
// [✨] Export Excel
// ==========================================
if ($export_mode == 'excel') {
    $filename = $doc_prefix . "_" . $doc_number . "_" . date('Ymd') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
    
    echo "<table border='0'>";
    echo "<tr><td colspan='5' style='font-size:20px; font-weight:bold; text-align:center;'>$default_title</td></tr>";
    echo "<tr><td colspan='5' style='text-align:center;'>บริษัท ซี.เอ็ม.เอส. คอนโทรล ซิสเต็ม จำกัด</td></tr>";
    echo "<tr><td colspan='5'>&nbsp;</td></tr>";
    echo "<tr><td colspan='3'><strong>ชื่อโครงการ:</strong> " . $proj['project_name'] . "</td><td colspan='2'><strong>เลขที่เอกสาร:</strong> $doc_number</td></tr>";
    echo "<tr><td colspan='3'><strong>รายละเอียด:</strong> $default_detail_text</td><td colspan='2'><strong>วันที่:</strong> $print_date</td></tr>";
    echo "</table><br>";

    echo "<table border='1' cellspacing='0' cellpadding='5'>";
    if ($view_mode == 'detail') {
        echo "<tr style='background-color:#eee;'><th>ลำดับ</th><th>รายการสินค้า</th><th>Serial Number</th><th>หน่วย</th><th>หมายเหตุ</th></tr>";
        $i = 0;
        foreach($detailed_items as $row) {
            $i++;
            echo "<tr><td align='center'>$i</td><td>{$row['name']}</td><td align='center' style='mso-number-format:\"\@\"'>{$row['serial_number']}</td><td align='center'>{$row['unit']}</td><td></td></tr>";
        }
    } else {
        echo "<tr style='background-color:#eee;'><th>ลำดับ</th><th>รายการสินค้า</th><th>จำนวน</th><th>หน่วย</th><th>หมายเหตุ</th></tr>";
        $j = 0; $total = 0;
        foreach($summary_items as $row) {
            $j++; $total += $row['qty'];
            echo "<tr><td align='center'>$j</td><td>{$row['name']}</td><td align='center'>".number_format($row['qty'])."</td><td align='center'>{$row['unit']}</td><td></td></tr>";
        }
        echo "<tr><td colspan='2' align='right'><strong>รวมทั้งหมด</strong></td><td align='center'><strong>".number_format($total)."</strong></td><td align='center'>รายการ</td><td></td></tr>";
    }
    echo "</table>";
    echo "</body></html>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title><?php echo $doc_number; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        body { font-family: 'Sarabun', sans-serif; padding: 40px; color: #333; background: #fff; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; }
        .info-box { border: 1px solid #000; padding: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 8px 12px; text-align: left; }
        th { background-color: #f0f0f0; text-align: center; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .footer { margin-top: 50px; display: flex; justify-content: space-between; text-align: center; }
        .sign-line { border-bottom: 1px solid #000; width: 200px; display: inline-block; margin-bottom: 5px; }
        
        /* Table Visibility */
        .table-container { display: none; width: 100%; } 
        body.print-mode-summary .table-summary { display: table !important; }
        body.print-mode-detail .table-detail { display: table !important; }

        /* Control Panel */
        .no-print { 
            background: #f8f9fa; padding: 15px; border-bottom: 4px solid #3b82f6; 
            margin: -40px -40px 30px -40px; 
            display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .control-group { display: flex; flex-direction: column; gap: 5px; position: relative; }
        .control-group label { font-size: 13px; font-weight: bold; color: #475569; }
        
        /* Dropdown Style */
        .custom-select-wrapper { position: relative; user-select: none; width: 220px; font-family: 'Sarabun'; }
        .custom-select-trigger {
            position: relative; display: flex; align-items: center; justify-content: space-between;
            padding: 8px 12px; font-size: 14px; color: #333;
            background: #fff; border: 1px solid #cbd5e1; border-radius: 6px;
            cursor: pointer; height: 38px; box-sizing: border-box; transition: all 0.2s;
        }
        .custom-select-trigger:hover { border-color: #3b82f6; }
        .custom-options {
            position: absolute; display: none; top: 100%; left: 0; right: 0;
            background: #fff; border: 1px solid #cbd5e1; border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); z-index: 100; margin-top: 8px; overflow: hidden;
        }
        .custom-select-wrapper.open .custom-options { display: block; animation: fadeIn 0.15s ease-out; }
        .custom-options-list { max-height: 250px; overflow-y: auto; }
        .custom-option { padding: 10px 12px; font-size: 14px; color: #333; cursor: pointer; transition: 0.1s; border-bottom: 1px solid #f8fafc; }
        .custom-option:hover { background: #f1f5f9; }
        .custom-option.selected { background: #e0f2fe; color: #0284c7; font-weight: bold; }
        .custom-controls { display: flex; gap: 8px; padding: 10px; background: #f8fafc; border-top: 1px solid #e2e8f0; position: sticky; bottom: 0; }
        .control-btn { flex: 1; padding: 8px 0; border: none; border-radius: 6px; font-size: 12px; cursor: pointer; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 6px; }
        .control-btn.add { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .control-btn.del { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .form-control { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: 'Sarabun'; height: 38px; box-sizing: border-box; width: 100%; min-width: 150px; }
        
        .btn-group { display: flex; gap: 5px; }
        .btn { border: none; padding: 0 20px; height: 38px; font-weight: bold; cursor: pointer; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 5px; color: white; transition: 0.2s; text-decoration: none; font-size: 14px; font-family: 'Sarabun'; }
        .btn-print { background: #ef4444; }
        .btn-print:hover { background: #dc2626; }
        .btn-excel { background: #10b981; } 
        .btn-excel:hover { background: #059669; }

        .detail-textarea {
            flex: 1; width: 100%; min-height: 60px; padding: 5px;
            font-family: 'Sarabun'; font-size: 16px; color: #333;
            border: 1px dashed #ccc; border-radius: 4px; background: transparent;
            resize: vertical; line-height: 1.5; overflow: hidden;
        }
        .detail-textarea:focus { outline: none; border: 1px solid #3b82f6; background: #f0f9ff; }

        /* สไตล์ปุ่มเลือกประเภทเอกสาร */
        .doc-type-switch {
            display: flex; background: #e2e8f0; border-radius: 8px; padding: 4px; gap: 5px;
        }
        .doc-type-btn {
            flex: 1; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; color: #64748b; background: transparent; transition: 0.2s;
        }
        .doc-type-btn.active { background: #fff; color: #3b82f6; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .doc-type-btn:hover:not(.active) { color: #334155; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0; background: #fff; }
            @page { margin: 1cm; }
            .detail-textarea { border: none; resize: none; padding: 0; }
        }
    </style>
</head>
<body class="print-mode-summary">

    <div class="no-print">
        <form method="GET" id="mainForm" style="display:flex; gap:15px; align-items:flex-end;">
            <input type="hidden" name="id" value="<?php echo $pid; ?>">
            <input type="hidden" name="type" id="docTypeInput" value="<?php echo $doc_type; ?>">
            
            <div class="control-group">
                <label>📂 ประเภทเอกสาร:</label>
                <div class="doc-type-switch">
                    <button type="button" class="doc-type-btn <?php echo ($doc_type == 'withdraw') ? 'active' : ''; ?>" onclick="changeDocType('withdraw')">ใบเบิก</button>
                    <button type="button" class="doc-type-btn <?php echo ($doc_type == 'return') ? 'active' : ''; ?>" onclick="changeDocType('return')">ใบคืน</button>
                </div>
            </div>

            <div class="control-group">
                <label>📌 เลือกงวด:</label>
                <select name="batch" id="batchSelect" class="form-control" onchange="this.form.submit()">
                    <option value="">-- รวมทั้งหมด (All) --</option>
                    <?php if($batches) while($b = $batches->fetch_assoc()): 
                        $this_run_no = isset($batch_map[$b['batch_time']]) ? $batch_map[$b['batch_time']] : '?';
                    ?>
                        <option value="<?php echo $b['batch_time']; ?>" <?php echo ($selected_batch == $b['batch_time']) ? 'selected' : ''; ?>>
                            งวดที่ <?php echo $this_run_no; ?> (<?php echo date('d/m/Y H:i', strtotime($b['batch_time'])); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </form>

        <div style="border-left: 1px solid #cbd5e1; height: 40px; margin: 0 5px;"></div>

        <div class="control-group">
            <label>📝 หัวข้อเอกสาร:</label>
            <div class="custom-select-wrapper" id="titleWrapper" style="width:200px;">
                <div class="custom-select-trigger">
                    <span id="titleDisplay"><?php echo $default_title; ?></span>
                    <i class="fas fa-chevron-down text-muted" style="font-size:12px;"></i>
                </div>
                <div class="custom-options">
                    <div id="titleList" class="custom-options-list"></div>
                    <div class="custom-controls">
                        <button class="control-btn add" onclick="addNewItem('custom_titles', 'title', 'docTitle')"><i class="fas fa-plus"></i></button>
                        <button class="control-btn del" onclick="deleteCurrentItem('custom_titles', 'title', 'docTitle')"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="control-group">
            <label>1. ซ้าย:</label>
            <div class="custom-select-wrapper" id="approverWrapper" style="width:160px;">
                <div class="custom-select-trigger">
                    <span id="approverDisplay">( ... )</span>
                    <i class="fas fa-chevron-down text-muted" style="font-size:12px;"></i>
                </div>
                <div class="custom-options">
                    <div id="approverList" class="custom-options-list"></div>
                    <div class="custom-controls">
                        <button class="control-btn add" onclick="addNewItem('custom_approvers', 'approver', 'footerApprover')"><i class="fas fa-plus"></i></button>
                        <button class="control-btn del" onclick="deleteCurrentItem('custom_approvers', 'approver', 'footerApprover')"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="control-group">
            <label>2. กลาง:</label>
            <div class="custom-select-wrapper" id="requesterWrapper" style="width:160px;">
                <div class="custom-select-trigger">
                    <span id="requesterDisplay">( ... )</span>
                    <i class="fas fa-chevron-down text-muted" style="font-size:12px;"></i>
                </div>
                <div class="custom-options">
                    <div id="requesterList" class="custom-options-list"></div>
                    <div class="custom-controls">
                        <button class="control-btn add" onclick="addNewItem('custom_requesters', 'requester', 'footerRequester')"><i class="fas fa-plus"></i></button>
                        <button class="control-btn del" onclick="deleteCurrentItem('custom_requesters', 'requester', 'footerRequester')"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="control-group">
            <label>3. ขวา:</label>
            <div class="custom-select-wrapper" id="payerWrapper" style="width:160px;">
                <div class="custom-select-trigger">
                    <span id="payerDisplay">( ... )</span>
                    <i class="fas fa-chevron-down text-muted" style="font-size:12px;"></i>
                </div>
                <div class="custom-options">
                    <div id="payerList" class="custom-options-list"></div>
                    <div class="custom-controls">
                        <button class="control-btn add" onclick="addNewItem('custom_payers', 'payer', 'footerPayer')"><i class="fas fa-plus"></i></button>
                        <button class="control-btn del" onclick="deleteCurrentItem('custom_payers', 'payer', 'footerPayer')"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <div style="border-left: 1px solid #cbd5e1; height: 40px; margin: 0 5px;"></div>

        <div class="control-group">
            <label>🖨️ รูปแบบ:</label>
            <select id="modeSelect" onchange="changePrintMode(this.value)" class="form-control" style="width: 140px;">
                <option value="summary">แบบสรุปยอด</option>
                <option value="detail">แบบละเอียด</option>
            </select>
        </div>

        <div class="control-group">
            <label>💾 ส่งออก:</label>
            <div class="btn-group">
                <button class="btn btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i>
                </button>
                <a href="#" id="btnExcel" class="btn btn-excel" target="_blank">
                    <i class="fas fa-file-excel"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="header">
        <h1 id="docTitle"><?php echo $default_title; ?></h1>
        <p>บริษัท ซี.เอ็ม.เอส. คอนโทรล ซิสเต็ม จำกัด</p>
    </div>

    <div class="info-box">
        <div>
            <strong>ชื่อโครงการ:</strong> <?php echo $proj['project_name']; ?><br>
            <strong>รหัสโครงการ:</strong> <?php echo $proj['project_code']; ?>
        </div>
        <div>
            <strong>เลขที่เอกสาร:</strong> <?php echo $doc_number; ?><br>
            <strong>วันที่:</strong> <span id="docDate"><?php echo $print_date; ?></span><br>
        </div>
    </div>

    <div class="detail-box-container">
        <span class="label-fixed">รายละเอียด:</span> 
        <textarea class="detail-textarea" oninput="autoResize(this)"><?php echo $default_detail_text . ""; ?></textarea>
    </div>

    <table class="table-container table-detail">
        <thead>
            <tr style="background: #e0f2fe;">
                <th width="50" class="text-center">ลำดับ</th>
                <th>รายการสินค้า</th>
                <th width="180" class="text-center">Serial Number</th>
                <th width="80" class="text-center">หน่วย</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $i = 0;
            if(count($detailed_items) > 0):
                foreach($detailed_items as $row): $i++;
            ?>
            <tr>
                <td class="text-center"><?php echo $i; ?></td>
                <td><?php echo $row['name']; ?></td>
                <td class="text-center" style="color:#0369a1; font-family:monospace;"><?php echo $row['serial_number']; ?></td>
                <td class="text-center"><?php echo $row['unit']; ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" class="text-center">-- ไม่พบข้อมูล --</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <table class="table-container table-summary">
        <thead>
            <tr>
                <th width="50" class="text-center">ลำดับ</th>
                <th>รายการสินค้า</th>
                <th width="100" class="text-center">จำนวน</th>
                <th width="80" class="text-center">หน่วย</th>
                <th width="150" class="text-center">หมายเหตุ</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $j = 0; $total_qty = 0;
            if(count($summary_items) > 0):
                foreach($summary_items as $row): 
                    $j++; $total_qty += $row['qty'];
            ?>
            <tr>
                <td class="text-center"><?php echo $j; ?></td>
                <td><?php echo $row['name']; ?></td>
                <td class="text-center" style="font-weight:bold; font-size:1.1em;"><?php echo number_format($row['qty']); ?></td>
                <td class="text-center"><?php echo $row['unit']; ?></td>
                <td></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background-color: #fafafa; font-weight: bold;">
                <td colspan="2" class="text-right">รวมจำนวนทั้งสิ้น</td>
                <td class="text-center"><?php echo number_format($total_qty); ?></td>
                <td class="text-center">รายการ</td>
                <td></td>
            </tr>
            <?php else: ?>
            <tr><td colspan="5" class="text-center">-- ไม่พบข้อมูล --</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <div>
            <br><span class="sign-line"></span><br>
            <span id="footerApprover">( ... )</span><br>
            วันที่ ____/____/____
        </div>
        <div>
            <br><span class="sign-line"></span><br>
            <span id="footerRequester">( ... )</span><br>
            วันที่ ____/____/____
        </div>
        <div>
            <br><span class="sign-line"></span><br>
            <span id="footerPayer">( ... )</span><br>
            วันที่ ____/____/____
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // กำหนดค่าเริ่มต้นสำหรับแต่ละโหมด
        const config = {
            'withdraw': {
                'title': "ใบรายการเบิกสินค้า / Job Sheet",
                'approver': "( ผู้อนุมัติ )",
                'requester': "( ผู้เบิกสินค้า )",
                'payer': "( ผู้จ่ายสินค้า )"
            },
            'return': {
                'title': "ใบคืนสินค้า / Return Note",
                'approver': "( ผู้รับคืนสินค้า )",
                'requester': "( ผู้คืนสินค้า )",
                'payer': "( ผู้อนุมัติ )"
            }
        };

        const currentType = "<?php echo $doc_type; ?>";

        // รายการตัวเลือกสำหรับ Dropdown
        const defaults = {
            'custom_titles': ["ใบรายการเบิกสินค้า / Job Sheet", "ใบคืนสินค้า / Return Note", "ใบส่งของ / Delivery Note", "ใบยืมสินค้า / Borrowing Form", "ใบเสนอราคา / Quotation", "ใบแจ้งหนี้ / Invoice"],
            'custom_approvers': ["( ผู้อนุมัติ )", "( ผู้จัดการโครงการ )", "( ผู้รับคืนสินค้า )"],
            'custom_requesters': ["( ผู้เบิกสินค้า )", "( ช่างหน้างาน )", "( ผู้คืนสินค้า )"],
            'custom_payers': ["( ผู้จ่ายสินค้า )", "( เจ้าหน้าที่คลัง )", "( Admin )"]
        };

        let currentSelection = { 'title': '', 'approver': '', 'requester': '', 'payer': '' };

        window.onload = function() {
            // ตั้งค่าเริ่มต้นตามโหมด
            setDefaultSignatures();

            initDropdown('titleWrapper', 'titleList', 'custom_titles', 'titleDisplay', 'docTitle', 'title');
            initDropdown('approverWrapper', 'approverList', 'custom_approvers', 'approverDisplay', 'footerApprover', 'approver');
            initDropdown('requesterWrapper', 'requesterList', 'custom_requesters', 'requesterDisplay', 'footerRequester', 'requester');
            initDropdown('payerWrapper', 'payerList', 'custom_payers', 'payerDisplay', 'footerPayer', 'payer');

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.custom-select-wrapper')) {
                    document.querySelectorAll('.custom-select-wrapper').forEach(el => el.classList.remove('open'));
                }
            });
            const textarea = document.querySelector('.detail-textarea');
            if(textarea) autoResize(textarea);
            updateExcelLink();
        };

        function changeDocType(type) {
            document.getElementById('docTypeInput').value = type;
            document.getElementById('mainForm').submit();
        }

        function setDefaultSignatures() {
            if(!currentSelection['title']) selectItem(config[currentType].title, 'titleDisplay', 'docTitle', 'title', 'custom_titles');
            if(!currentSelection['approver']) selectItem(config[currentType].approver, 'approverDisplay', 'footerApprover', 'approver', 'custom_approvers');
            if(!currentSelection['requester']) selectItem(config[currentType].requester, 'requesterDisplay', 'footerRequester', 'requester', 'custom_requesters');
            if(!currentSelection['payer']) selectItem(config[currentType].payer, 'payerDisplay', 'footerPayer', 'payer', 'custom_payers');
        }

        function autoResize(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }

        function updateExcelLink() {
            const batch = document.querySelector('select[name="batch"]').value;
            const mode = document.getElementById('modeSelect').value;
            const type = document.getElementById('docTypeInput').value;
            const pid = "<?php echo $pid; ?>";
            const btn = document.getElementById('btnExcel');
            btn.href = `print_job.php?id=${pid}&batch=${batch}&type=${type}&export=excel&mode=${mode}`;
        }

        function changePrintMode(mode) {
            document.body.className = 'print-mode-' + mode;
            updateExcelLink();
        }

        function initDropdown(wrapperId, listId, storageKey, displayId, targetTextId, typeKey) {
            const wrapper = document.getElementById(wrapperId);
            const trigger = wrapper.querySelector('.custom-select-trigger');
            trigger.addEventListener('click', () => {
                document.querySelectorAll('.custom-select-wrapper').forEach(el => { if(el !== wrapper) el.classList.remove('open'); });
                wrapper.classList.toggle('open');
            });
            renderList(listId, storageKey, displayId, targetTextId, typeKey);
        }

        function renderList(listId, storageKey, displayId, targetTextId, typeKey) {
            let stored = localStorage.getItem(storageKey);
            let items = stored ? JSON.parse(stored) : defaults[storageKey];
            const listEl = document.getElementById(listId);
            listEl.innerHTML = '';
            
            items.forEach(item => {
                let div = document.createElement('div');
                div.className = 'custom-option ' + (item === currentSelection[typeKey] ? 'selected' : '');
                div.innerText = item;
                div.onclick = function() {
                    selectItem(item, displayId, targetTextId, typeKey, storageKey);
                    this.closest('.custom-select-wrapper').classList.remove('open');
                };
                listEl.appendChild(div);
            });
        }

        function selectItem(val, displayId, targetTextId, typeKey, storageKey) {
            currentSelection[typeKey] = val;
            let displayEl = document.getElementById(displayId);
            let targetEl = document.getElementById(targetTextId);
            
            if(displayEl) displayEl.innerText = val;
            if(targetEl) targetEl.innerText = val;
            
            if(displayEl) {
                let wrapper = displayEl.closest('.custom-select-wrapper');
                if(wrapper) {
                    wrapper.querySelectorAll('.custom-option').forEach(opt => {
                        if(opt.innerText === val) opt.classList.add('selected'); else opt.classList.remove('selected');
                    });
                }
            }
        }

        async function addNewItem(storageKey, typeKey, targetTextId) {
            const { value: newVal } = await Swal.fire({
                title: 'เพิ่มรายการใหม่', input: 'text', inputPlaceholder: 'กรอกข้อความ...', showCancelButton: true, confirmButtonText: 'บันทึก', cancelButtonText: 'ยกเลิก', confirmButtonColor: '#10b981',
                inputValidator: (value) => { if (!value) return 'กรุณากรอกข้อความ!'; }
            });
            if(newVal) {
                let val = newVal;
                if(storageKey !== 'custom_titles' && !val.startsWith('(')) val = '( ' + val + ' )';
                let stored = localStorage.getItem(storageKey);
                let items = stored ? JSON.parse(stored) : defaults[storageKey];
                if(!items.includes(val)) {
                    items.push(val); localStorage.setItem(storageKey, JSON.stringify(items));
                    let listId = typeKey + 'List';
                    let displayId = typeKey + 'Display';
                    renderList(listId, storageKey, displayId, targetTextId, typeKey);
                    selectItem(val, displayId, targetTextId, typeKey, storageKey);
                    Swal.fire({ icon: 'success', title: 'เพิ่มสำเร็จ', timer: 1000, showConfirmButton: false });
                }
            }
        }

        function deleteCurrentItem(storageKey, typeKey, targetTextId) {
            let val = currentSelection[typeKey];
            if(!val) return;
            Swal.fire({
                title: 'ยืนยันการลบ?', text: `ต้องการลบ "${val}" หรือไม่?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#6b7280', confirmButtonText: 'ลบเลย'
            }).then((result) => {
                if (result.isConfirmed) {
                    let items = JSON.parse(localStorage.getItem(storageKey)) || defaults[storageKey];
                    items = items.filter(item => item !== val);
                    localStorage.setItem(storageKey, JSON.stringify(items));
                    currentSelection[typeKey] = '';
                    let listId = typeKey + 'List';
                    let displayId = typeKey + 'Display';
                    renderList(listId, storageKey, displayId, targetTextId, typeKey);
                    Swal.fire({ icon: 'success', title: 'ลบเรียบร้อย', timer: 1000, showConfirmButton: false });
                }
            })
        }
    </script>
</body>
</html>