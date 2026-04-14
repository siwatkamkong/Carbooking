<?php
require_once __DIR__ . '/pdf-lib/TCPDF-main/tcpdf.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Missing or invalid id');
}
$id = (int) $_GET['id'];

$mysqli = new mysqli('localhost', 'root', 'W@tt7425j4636', 'car_booking');
if ($mysqli->connect_error) {
    die('Connection error: ' . $mysqli->connect_error);
}

$sql = "
    SELECT
        r.mileage_start,
        r.mileage_end,
        r.begin,
        r.end,
        r.time_type,
        r.travelers,
        r.vehicle_id,
        r.detail,
        r.province,
        r.create_date,
        r.number_plate,
        e.cctr,
        e.dept_name,
        e.employee_id,
        e.full_name,
        e.phone,
        e.dept_code,
        v.number        AS vehicle_number,
        v.color         AS vehicle_color,
        v.detail        AS vehicle_detail,
        v.seats         AS vehicle_seats,
        h.dept_name,
        h.head_name,
        h.signature_path
    FROM app_car_reservation AS r
    LEFT JOIN employee_data  AS e ON e.employee_id = r.employee_id
    LEFT JOIN app_vehicles   AS v ON v.id           = r.vehicle_id
    LEFT JOIN head_employee  AS h ON h.dept_name    = e.dept_name
    WHERE r.id = ?
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();
$mysqli->close();

if (!$data) {
    die('Record not found');
}

$MARGIN = 3;    // mm
$BODY_F = 12;   // pt
$HEAD_F = 18;   // pt
$LINE_H = 8.5;  // mm
$BOX    = 5.8;  // mm
$GAP    = 0;    // mm

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins($MARGIN, $MARGIN, $MARGIN);
$pdf->SetAutoPageBreak(true, $MARGIN);

$fontFile = __DIR__ . '/pdf-lib/TCPDF-main/fonts/THSarabunNew.ttf';
$fontFileB = __DIR__ . '/pdf-lib/TCPDF-main/fonts/THSarabunNew-Bold.ttf';
$fontName = TCPDF_FONTS::addTTFfont($fontFile, 'TrueTypeUnicode', '', 32) ?: 'helvetica';
$fontNameBold = TCPDF_FONTS::addTTFfont($fontFileB, 'TrueTypeUnicode', '', 32);

function drawBoxes($pdf,$x,$y,$n,$size,$gap){
    for($i=0;$i<$n;$i++){
        $pdf->Rect($x+$i*($size+$gap),$y,$size,$size);
    }
}
function fillBoxes($pdf,$x,$y,$txt,$size,$gap){
    $c=preg_split('//u',$txt,-1,PREG_SPLIT_NO_EMPTY);
    foreach($c as $i=>$ch){
        $pdf->SetXY($x+$i*($size+$gap),$y+.6);
        $pdf->Cell($size,$size,$ch,0,0,'C');
    }
}
function L($pdf,$x,$y,$w,$h,$t){
    $pdf->SetXY($x,$y);
    $pdf->Cell($w,$h,$t,0,0);
}

/* 
 4) Column sizes 
*/
$totalW = 210 - 2*$MARGIN;
$colW   = ($totalW - 2)/2;
$midX   = $MARGIN + $colW + 2;

/* 
  5) Build page                                                           
*/
$pdf->AddPage();
    $pdf->SetLineWidth(0.8);
    $lines = [
        ['y' => 200, 'x1' => 1,             'x2' => $midX],                              // ครึ่งซ้ายถึงกึ่งกลาง
        ['y' => 125, 'x1' => $midX,         'x2' => $pdf->getPageWidth() - 1],
        ['y' => 200, 'x1' => $midX,         'x2' => $pdf->getPageWidth() - 1],           // ครึ่งขวาจากกึ่งกลางถึงขอบ
    ];
    foreach ($lines as $line) {
        $pdf->Line($line['x1'], $line['y'], $line['x2'], $line['y']);
    }

$pdf->Image(__DIR__ . '/ntlogo.png', $MARGIN+1, $MARGIN+1, 60, '', 'PNG');
$pdf->SetLineWidth(.35);
$pdf->Line($midX, $MARGIN+22, $midX, 297-$MARGIN-10);
$pdf->SetFont($fontName,'B',$HEAD_F);
$pdf->SetXY($MARGIN,$MARGIN+11);
$pdf->Cell($totalW,$LINE_H+3,'แบบขอใช้รถ บริษัท โทรคมนาคมแห่งชาติ จำกัด (มหาชน)',0,0,'C');
$pdf->SetFont($fontName,'',$BODY_F);

$employeeIdStr = str_pad($data['employee_id'],8,' ');
$fullnameStr = str_pad($data['full_name'],50,' ');
$cctrIdStr = str_pad($data['cctr'],5,' ');
$phoneDigits = substr(preg_replace('/\D/','',$data['phone']),-10);
$headnameStr = str_pad($data['head_name'],50,' ');

// ===== แปลงปี begin, end เป็น พ.ศ. 2 หลัก =====
$ts1 = strtotime($data['begin']);
$yearAd1   = date('Y', $ts1);
$yearBe1   = $yearAd1 + 543;
$yearBe1_2 = substr($yearBe1, -2);
$beginStr  = date('d',   $ts1)
           . date('m',   $ts1)
           . $yearBe1_2
           . date('Hi',  $ts1);

$ts2 = strtotime($data['end']);
$yearAd2   = date('Y', $ts2);
$yearBe2   = $yearAd2 + 543;
$yearBe2_2 = substr($yearBe2, -2);
$endStr    = date('d',   $ts2)
           . date('m',   $ts2)
           . $yearBe2_2
           . date('Hi',  $ts2);
// ===== จบการแปลง =====

$mStartStr = str_pad($data['mileage_start'],6,'0',STR_PAD_LEFT);
$mEndStr   = str_pad($data['mileage_end'],6,'0',STR_PAD_LEFT);
$bxFn  = fn($x,$y,$n)=>drawBoxes($GLOBALS['pdf'],$x,$y,$n,$GLOBALS['BOX'],$GLOBALS['GAP']);
$fillFn= fn($x,$y,$v)=>fillBoxes($GLOBALS['pdf'],$x,$y,$v,$GLOBALS['BOX'],$GLOBALS['GAP']);

/* --------------------------------------------------------------------------
 | 5.1 Left column                                                         |
 -------------------------------------------------------------------------- */
 $lx     = $MARGIN;
 $y      = $MARGIN + 22;
 $pdf->SetFont($fontName, 'B', $BODY_F + 5);

 $offsetX = 35;

 // วาดกรอบก่อนเหมือนเดิม
 $pdf->SetLineWidth(0.4);
 $pdf->Rect($lx, $y, $colW, $LINE_H + 0.5);

 L($pdf,
   $lx + $offsetX,   
   $y,
   $colW, 
   $LINE_H,
   'บันทึกการขอใช้รถ'
 );

 $pdf->SetFont($fontName, '', $BODY_F);
 $y += $LINE_H + 3;

 $vertOff = ($LINE_H - $BOX) / 50;

 $rows = [
     ['รหัสส่วนงานผู้ใช้รถ',       5,          $cctrIdStr,             $LINE_H + 3.2],
     ['วันเวลาที่เริ่มใช้รถ',       [2,2,2,4],  null,                   $LINE_H + 3.2],
     ['วันเวลาที่สิ้นสุดการใช้รถ', [2,2,2,4],  null,                   $LINE_H + 2],
     ['ลักษณะงาน',                 str_repeat('_', 34), $data['detail'],  $LINE_H + 1],
 ];

 foreach ($rows as $row) {
     list($label, $cfg, $val, $rowOff) = array_merge($row, [null, null, null, $LINE_H+1]);

     // 1) print row label
     L($pdf, $lx, $y, 35, $LINE_H, $label);

     // 2) grouped boxes?
     if (is_array($cfg)) {
         $groups  = $cfg;
         $bxStart = $lx + 32;
         $bx      = $bxStart;

         // a) small above‐labels for date/time rows
         if (in_array($label, ['วันเวลาที่เริ่มใช้รถ','วันเวลาที่สิ้นสุดการใช้รถ'], true)) {
             $titles = ['วันที่','เดือน','ปี','เวลา'];
             $labelY = $y - ($LINE_H * 0.8);
             foreach ($groups as $i => $n) {
                 $groupW = $n * $BOX + ($n-1)*$GAP;
                 $tw     = $pdf->GetStringWidth($titles[$i]);
                 $pdf->SetXY($bx + ($groupW-$tw)/2, $labelY);
                 $pdf->Cell($tw, $LINE_H, $titles[$i], 0, 0, 'C');
                 $bx += $groupW + 2;
             }
             $bx = $bxStart;
         }

         // b) draw all boxes
         foreach ($groups as $n) {
             $bxFn($bx, $y, $n);
             $bx += $n * ($BOX + $GAP) + 2;
         }

         // c) pick source string
         if ($label === 'วันเวลาที่เริ่มใช้รถ') {
             $source = $beginStr;
         } elseif ($label === 'วันเวลาที่สิ้นสุดการใช้รถ') {
             $source = $endStr;
         } else {
             $source = '';
         }

         // d) fill each sub‐box
         $pos = 0;
         $bx  = $bxStart;
         foreach ($groups as $i => $n) {
             if ($label === 'วันเวลาที่เริ่มใช้รถ' && $i === count($groups)-1) {
                 $seg = '0600';
                 $pos += 4;
             } else {
                 $seg = substr($source, $pos, $n);
                 $pos += $n;
             }
             $fillFn($bx, $y + $vertOff, $seg);
             $bx += $n * ($BOX + $GAP) + 2;
         }

         if (in_array($label, ['วันเวลาที่เริ่มใช้รถ','วันเวลาที่สิ้นสุดการใช้รถ'], true)) {
             $pdf->SetXY($bx, $y + $vertOff);
             $pdf->Cell($pdf->GetStringWidth('น.'), $LINE_H, 'น.', 0, 0, 'L');
         }

     }
     // 3) single code‐box
     elseif ($label === 'รหัสส่วนงานผู้ใช้รถ') {
         $bx = $lx + 32;
         $bxFn($bx, $y, 5);
         $fillFn($bx, $y + $vertOff, $val);
     }
     // 4) detail / underline
     else {
         $pdf->SetXY($lx+37, $y);
         $pdf->Cell(0, $LINE_H, $val, 0, 0, 'L');
         $w     = $pdf->GetStringWidth($val);
         $lineY = $y + $LINE_H - 2;
         $pdf->Line($lx+37, $lineY, $lx+37 + $w, $lineY);
     }

     // 5) move down by the row‐specific offset
     $y += $rowOff;
 }

L($pdf, $lx, $y, $LINE_H, 8, 'ประเภทรถ');
$cx    = $lx + 37;
$types = ['เก๋ง','ตู้','กระบะ'];
foreach ($types as $i => $t) {
    $pdf->Rect($cx + $i * 22, $y + 1, 5, 5);
    $pdf->SetXY($cx + $i * 22 + 7, $y);
    $pdf->Cell(14, $LINE_H, $t, 0, 0);
}

$vid = (int)$data['vehicle_id'];
if (in_array($vid, [1,2,3,4,5], true)) {
    $i = 0; // เก๋ง
} elseif (in_array($vid, [6,7,8], true)) {
    $i = 2; // กระบะ
} else {
    $i = 1;
}

if ($i !== null) {
    $boxX    = $cx + $i * 22 + 1;  
    $boxY    = $y  + 1;
    $s       = 5;
    $tickLen = 3;
    $half    = $tickLen / 2;
    $pdf->SetLineWidth(0.4);
    $pdf->Line(
        $boxX + 1,
        $boxY + $s/2,
        $boxX + $half,
        $boxY + $s - 1
    );
    $pdf->Line(
        $boxX + $half,
        $boxY + $s - 1,
        $boxX + $tickLen,
        $boxY + 1
    );
}

$y += $LINE_H + 1;

L($pdf, $lx, $y, 35, $LINE_H, 'สถานที่เริ่มต้น');

$placeText = 'กลุ่มขายและปฏิบัติการลูกค้า ภาคใต้ (ตน.)';
$pdf->SetXY($lx + 35, $y);
$pdf->Cell($colW - 35, $LINE_H, $placeText, 0, 0, 'L');

$lineY  = $y + $LINE_H - 2;                     
$lineX1 = $lx + 35;                             
$lineX2 = $lineX1 + $pdf->GetStringWidth($placeText); 
$pdf->Line($lineX1, $lineY, $lineX2, $lineY);

$y += $LINE_H + 1;

L($pdf, $lx, $y, 35, $LINE_H, 'สถานที่จะต้องไปปฏิบัติงาน');

$startX = $lx + 37;
$pdf->SetXY($startX, $y);

$province = $data['province'];
$pdf->Cell($colW - 35, $LINE_H, $province, 0, 0, 'L');

$textW  = $pdf->GetStringWidth($province);
$lineY  = $y + $LINE_H - 2; 
$pdf->Line($startX, $lineY, $startX + $textW, $lineY);

$y += $LINE_H + 1.5;

L($pdf,$lx,$y,35,$LINE_H,'จำนวนผู้โดยสาร/สัมภาระ');
$pdf->Cell($colW-35,$LINE_H,' 4 ',0,0);
$y+=$LINE_H+1;

// *** ผู้ขอใช้รถ  *** ------------------------------------

$sigOffsetX = 10;  // ← adjust this to move everything left/right

// 1) Label “ผู้ขอใช้รถ”
$pdf->SetXY($lx + $sigOffsetX, $y);
$pdf->Cell(25, $LINE_H, 'ผู้ขอใช้รถ', 0, 0, 'L');

// 2) Build the real filesystem path
$sigFile = rtrim($_SERVER['DOCUMENT_ROOT'], '/')
         . $data['signature_path'];  // e.g. "/carbooking/signaturePic/blotn.jpg"

$sigW = 25;   // image width in mm
$sigH = 10;   // image height in mm
$imgX = $lx + $sigOffsetX + 26;  // 26mm from label start
$imgY = $y - 1;                  // tweak vertically

if (file_exists($sigFile)) {
    $pdf->Image($sigFile, $imgX, $imgY, $sigW, $sigH, '', '', '', false, 300);
} else {
    // if no image, draw the blanks in the same place
    $pdf->SetXY($imgX, $y);
    $pdf->Cell($sigW, $LINE_H, str_repeat('_', 38), 0, 0, 'L');
}

// 4) move down to next line  
$y += $LINE_H + 1;

$pdf->SetFont($fontName, '', $BODY_F);
$nameW  = $pdf->GetStringWidth($headnameStr);

// 2) define paren width and total group width
$parenW = 1;
$groupW = $nameW + 2 * $parenW;

// 3) center the whole “(name)” group in the column
$groupX = $lx + ($colW - $groupW) / 2;

// 4) draw the left parenthesis
L($pdf, $groupX, $y, $parenW, $LINE_H, '(');

// 5) draw the name immediately after
$pdf->SetXY($groupX + $parenW, $y);
$pdf->Cell($nameW, $LINE_H, $headnameStr, 0, 0, 'L');

$lineY = $y + $LINE_H - 2;  // adjust “-1” if you need the line closer/further from the text
$pdf->Line(
  $groupX + $parenW,      // start under the first character
  $lineY,
  $groupX + $parenW + $nameW,  // end under the last character
  $lineY
);

// 6) draw the right parenthesis
L($pdf, $groupX + $parenW + $nameW, $y, $parenW, $LINE_H, ')');

// 7) advance to the next line
$y += $LINE_H + 1;

// รหัสประจำตัวพนักงานผู้ใช้รถ -------------------------------------------
L($pdf, $lx, $y, 35, $LINE_H, 'รหัสประจำตัวพนักงานผู้ใช้รถ');

// วาด 8 กล่อง
$bx = $lx + 37;
$bxFn($bx, $y, 8);
// เตรียมสตริง ID ให้มี 8 ตัว (space-padded)
$empIdStr = str_pad($data['employee_id'], 8, ' ', STR_PAD_LEFT);

// คำนวณกึ่งกลางแนวตั้ง
$vertOff = ($LINE_H - $BOX) / 50;

// เติมตัวอักษรลงในกล่อง
$fillFn($bx, $y + $vertOff, $empIdStr);

// เลื่อน Y แค่ครั้งเดียว
$y += $LINE_H + 1;

// หมายเลขโทรศัพท์ผู้ใช้รถ -------------------------------------------------
L($pdf, $lx, $y, 30, $LINE_H, 'หมายเลขโทรศัพท์ผู้ใช้รถ');

$rawPhone = preg_replace('/\D/', '', $data['phone']);

if (strlen($rawPhone) >= 9) {
    $phoneDigits = substr($rawPhone, 0, 9);
} else {
    $phoneDigits = $rawPhone;
}

// วาดกล่องตามจำนวนตัวเลขที่ต้องการ (dynamic)
$bx = $lx + 32;
$bxFn($bx, $y, strlen($phoneDigits));

// เติมตัวเลขลงในกล่อง (ให้กึ่งกลางแนวตั้ง)
$vertOff     = ($LINE_H - $BOX) / 50;
$fillFn($bx, $y + $vertOff, $phoneDigits);

// เลื่อนลงบรรทัดถัดไป
$y += $LINE_H + 1;

// กำหนด offset X ตามต้องการ (บวกไปทางขวา, ลบไปทางซ้าย)
$authOffsetX = 23;  

// 1) เตรียมคำว่า “ผู้อนุมัติ” และวัดความกว้าง
$label    = 'ผู้อนุมัติ';
$pdf->SetFont($fontName, '', $BODY_F);
$labelW   = $pdf->GetStringWidth($label) + 2;

// 2) พิมพ์คำว่า “ผู้อนุมัติ” ที่ตำแหน่งปรับแล้ว
$pdf->SetXY($lx + $authOffsetX, $y);
$pdf->Cell($labelW, $LINE_H, $label, 0, 0, 'L');

// 3) เตรียม path ของรูปเซ็นต์
$sigFile  = rtrim($_SERVER['DOCUMENT_ROOT'], '/')
          . $data['signature_path'];  // e.g. "/carbooking/signaturePic/blotn.jpg"

// 4) วาดรูปเซ็นต์ต่อจาก label แบบชิดติด
$sigW     = 30;
$sigH     = 10;
$imgX     = $lx + $authOffsetX + $labelW;
$imgY     = $y + ($LINE_H - $sigH) / 2;

if (file_exists($sigFile)) {
    $pdf->Image($sigFile, $imgX, $imgY, $sigW, $sigH, '', '', '', false, 300);
} else {
    // fallback: ตีเส้น underscore แทน
    $pdf->SetXY($imgX, $y + ($LINE_H - 1));
    $pdf->Cell($sigW, 0, str_repeat('_', 20), 0, 0, 'L');
}

$pdf->SetXY($imgX + $sigW + 2, $y);
$pdf->Cell(0, $LINE_H, '(ระดับตั้งแต่ผู้จัดการส่วนขึ้นไป/', 0, 1, 'L');

$y += $LINE_H;

// ปรับ offset ตามต้องการ
$nameOffsetX = 10;
$baseX       = $lx + $authOffsetX + $nameOffsetX;

// เตรียมข้อความ
$openParen   = '(';
$name        = $data['head_name'];
$after       = ')  หรือผู้ได้รับมอบหมาย)';

// วัดความกว้างแต่ละชิ้น
$pdf->SetFont($fontName, '', $BODY_F);
$wOpen       = $pdf->GetStringWidth($openParen);
$wName       = $pdf->GetStringWidth($name);

// 1) วาดวงเล็บเปิด
$pdf->SetXY($baseX, $y);
$pdf->Cell($wOpen, $LINE_H, $openParen, 0, 0, 'L');

// 2) วาดชื่อจริง แล้วขีดเส้นใต้
$pdf->SetXY($baseX + $wOpen, $y);
$pdf->Cell($wName, $LINE_H, $name, 0, 0, 'L');
// ขีดเส้นใต้ชื่อ
$lineY = $y + $LINE_H - 2;  // ปรับตำแหน่งเส้นใต้เล็กน้อย
$pdf->Line(
    $baseX + $wOpen,
    $lineY,
    $baseX + $wOpen + $wName,
    $lineY
);

$pdf->SetXY($baseX + $wOpen + $wName, $y);
$pdf->Cell(0, $LINE_H, $after, 0, 1, 'L');

// 7) เลื่อนบรรทัด
$y += $LINE_H + 1;

$posOffsetX =  33;  // +5 มม. เลื่อนขวา, -5 มม. เลื่อนซ้าย

// 1) วาด label “ตำแหน่ง”
$pdf->SetFont($fontName, '', $BODY_F);
$pdf->SetXY($lx + $posOffsetX, $y);
$pdf->Cell(0, $LINE_H, 'ตำแหน่ง', 0, 0, 'L');

// 2) เตรียมข้อความ “ผส.” + dept_name
$positionLabel = 'ผส.' . $data['dept_name'];

if ($data['dept_name'] == 'บตน.2 (สข.)') {
    $positionLabel = 'ผจ.' . $data['dept_name'] . 'สบ.';
}

$labelW = $pdf->GetStringWidth('ตำแหน่ง') + 2;  // ความกว้าง label + margin

// 3) วาดข้อความและ underline
$startX = $lx + $posOffsetX + $labelW;
$pdf->SetXY($startX, $y);
$pdf->Cell($pdf->GetStringWidth($positionLabel), $LINE_H, $positionLabel, 0, 0, 'L');

// underline เฉพาะข้อความ (ไม่ขีดช่องว่าง)
$lineY = $y + $LINE_H - 2;
$endX  = $startX + $pdf->GetStringWidth($positionLabel);
$pdf->Line($startX, $lineY, $endX, $lineY);

// 4) เลื่อนไปบรรทัดถัดไป (ตามเดิม)
$y += $LINE_H + 1;

$dateOffsetX = 5;  

// 1) วาด label (เว้นช่องว่าง 35 มม.) 
L($pdf, $lx, $y, 35, $LINE_H, '');

// 2) จัดรูปแบบวันที่จาก create_date
$dt        = strtotime($data['create_date']);
$formatted = date('d/m/Y', $dt);  // ตัวอย่าง: "07/05/2025"

// 3) คำนวณจุดเริ่มต้น X หลัง label และ offset
$startX = $lx + 35 + $dateOffsetX;

// 4) พิมพ์วันที่ทั้งหมด
$pdf->SetFont($fontName, '', $BODY_F);
$pdf->SetXY($startX, $y);
$pdf->Cell($pdf->GetStringWidth($formatted), $LINE_H, $formatted, 0, 0, 'L');

// 5) ขีดเส้นใต้ทั้งข้อความ (รวม slash)
$lineY = $y + $LINE_H - 2;
$endX  = $startX + $pdf->GetStringWidth($formatted);
$pdf->Line($startX, $lineY, $endX, $lineY);

// 6) เลื่อนลงบรรทัดถัดไป (ตามเดิม)
$y += $LINE_H + 15;

// หมายเหตุ ---------------------------------------------------------------
$pdf->SetFont($fontName,'B',$BODY_F+2);
L($pdf,$lx,$y,$colW,$LINE_H,'หมายเหตุ');
$pdf->SetFont($fontName,'',$BODY_F);
$y += $LINE_H + 2;

foreach ([
    ['โครงการให้บริการรถยนต์', '       (สำนักงานใหญ่ แจ้งวัฒนะ)'],
    ['โทร.',                      ''],
    ['จุดนัดพบ',                  str_repeat('_', 27)],
    ['เวลา',                      '____ น.']
] as $r) {
    // หัวข้อย่อย
    L($pdf, $lx, $y, 25, $LINE_H, $r[0]);
    $pdf->Cell($colW - 25, $LINE_H, $r[1], 0, 0);

    // ถ้าเป็นบรรทัด 'โทร.' วาดกล่อง 10 ช่อง และเติมหมายเลขตามรูป
    if ($r[0] === 'โทร.') {
        $bx = $lx + 27;
        $bxFn($bx, $y, 10);
        $fillFn($bx, $y, '0250543267');
    }

    $y += $LINE_H + 1;
}

$pdf->SetXY($lx, $y);
$pdf->Cell($colW, $LINE_H, 'กรณีมีการเปลี่ยนแปลง กรุณาโทรศัพท์แจ้งล่วงหน้า 30 นาที', 0, 0);

/* --------------------------------------------------------------------------
 | 5.2 Right column (unchanged layout)                                     |
 -------------------------------------------------------------------------- */
 $rx     = $midX + 2;
 $y2     = $MARGIN + 22;
 $pdf->SetFont($fontName, 'B', $BODY_F + 5);

 // เลื่อนขวา 10 มม. (ปรับตามต้องการ)
 $offsetX = 30;
 $pdf->SetLineWidth(0.4);
 // วาดกรอบตามเดิม
 $pdf->Rect($rx, $y2, $colW, $LINE_H + 0.5);

 // พิมพ์ข้อความชิดขวาขึ้น ด้วยการเลื่อน X
 L(
     $pdf,
     $rx + $offsetX,   
     $y2,
     $colW,
     $LINE_H,
     'ส่วนนี้ผู้จ่ายรถเป็นผู้บันทึก'
 );

 $pdf->SetFont($fontName, '', $BODY_F);
 $y2 += $LINE_H + 5;

$distance = $data['mileage_end'] - $data['mileage_start'];

// เตรียมค่า vertical offset สำหรับการกึ่งกลางตัวเลขในกล่อง
$vertOff = ($LINE_H - $BOX) / 50;
// custom line offset for specific rows
$timeRowOff   = $LINE_H + 3.5;
$defaultRowOff = $LINE_H + 1;

$right = [
    ['ประเภทรถ','opts',['ปกติ','ด่วน','ฉุกเฉิน'],$defaultRowOff],
    ['รหัสประเภทงาน','boxes',2,$defaultRowOff],
    ['ทะเบียนรถ','boxes',[1,2,4],$timeRowOff],
    ['วันที่/เวลา','boxes',[2,2,2,4],$defaultRowOff],
    ['ใช้ในเวลาราชการ','check',null,$defaultRowOff],
    ['ใช้นอกเวลาราชการ','check',null,$defaultRowOff],
    ['ประมาณระยะทางใช้รถ','txt','________ กม.',$defaultRowOff]
];

foreach ($right as $r) {
    list($label, $type, $cfg, $rowOff) = $r;

    // 1) พิมพ์หัวข้อ
    L($pdf, $rx, $y2, 40, $LINE_H, $label);

    switch ($type) {
        case 'opts':
            $cx = $rx + 30;
            foreach ($cfg as $i => $opt) {
                $pdf->Rect($cx + $i * 22, $y2 + 1, 5, 5);
                $pdf->SetXY($cx + $i * 22 + 7, $y2);
                $pdf->Cell(14, $LINE_H, $opt, 0, 0);
            }
            if ($label === 'ประเภทรถ') {
                $boxX = $cx + 1; $boxY = $y2 + 1; $s = 5; $tickLen = 3; $half = $tickLen/2;
                $pdf->SetLineWidth(0.4);
                $pdf->Line($boxX+1, $boxY+$s/2, $boxX+$half, $boxY+$s-1);
                $pdf->Line($boxX+$half, $boxY+$s-1, $boxX+$tickLen,$boxY+1);
            }
            break;

        case 'boxes':
            $bxStart = $rx + 30;
            if ($label === 'วันที่/เวลา') {
                $titles = ['วันที่','เดือน','ปี','เวลา'];
                $labelY = $y2 - ($LINE_H * 0.8);
                $bxLabel = $bxStart;
                foreach ($cfg as $i => $n) {
                    $groupW = $n * $BOX + ($n-1) * $GAP;
                    $tw = $pdf->GetStringWidth($titles[$i]);
                    $pdf->SetXY($bxLabel + ($groupW - $tw)/2, $labelY);
                    $pdf->Cell($tw, $LINE_H, $titles[$i], 0, 0, 'C');
                    $bxLabel += $groupW + 2;
                }
            }

            $bx = $bxStart;
            foreach ((array)$cfg as $n) {
                $bxFn($bx, $y2, $n);
                $bx += $n * ($BOX + $GAP) + 2;
            }

            if (in_array($label, ['ทะเบียนรถ','วันที่/เวลา'], true)) {
                $value = $label==='ทะเบียนรถ'
                       ? $data['number_plate']
                       : $beginStr;   
                $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
                $bx = $bxStart; $idx = 0;
                foreach ((array)$cfg as $n) {
                    for ($i=0; $i<$n; $i++) {
                        $char = isset($chars[$idx])? $chars[$idx]: '';
                        $idx++;
                        $cw = $pdf->GetStringWidth($char);
                        $x = $bx + ($BOX - $cw)/3;
                        $y = $y2 + $vertOff;
                        $pdf->SetXY($x, $y);
                        $pdf->Cell($cw, $BOX, $char, 0, 0);
                        $bx += $BOX + $GAP;
                    }
                    $bx += 2;
                }
            }

            if ($label === 'วันที่/เวลา') {
                $endX = $bx;
                $pdf->SetXY($endX, $y2 + $vertOff);
                $pdf->Cell($pdf->GetStringWidth('น.'), $LINE_H, 'น.', 0, 0, 'L');
            }
            break;

        case 'check':
                $boxX = $rx + 32; 
                $boxY = $y2 + 1;
                $pdf->Rect($boxX, $boxY, 5, 5);

                $tickInTime = $label === 'ใช้ในเวลาราชการ' && ($data['time_type'] === 'ในเวลา' || $data['time_type'] === 'ในเวลาและนอกเวลา');
                $tickOutTime = $label === 'ใช้นอกเวลาราชการ' && ($data['time_type'] === 'นอกเวลา' || $data['time_type'] === 'ในเวลาและนอกเวลา');

                if ($tickInTime) {
                    $s = 5; $tickLen = 3; $half = $tickLen / 2;
                    $pdf->SetLineWidth(0.4);
                    $pdf->Line($boxX + 1, $boxY + $s / 2, $boxX + $half, $boxY + $s - 1);
                    $pdf->Line($boxX + $half, $boxY + $s - 1, $boxX + $tickLen, $boxY + 1);
                }

                if ($tickOutTime) {
                    $s = 5; $tickLen = 3; $half = $tickLen / 2;
                    $pdf->SetLineWidth(0.4);
                    $pdf->Line($boxX + 1, $boxY + $s / 2, $boxX + $half, $boxY + $s - 1);
                    $pdf->Line($boxX + $half, $boxY + $s - 1, $boxX + $tickLen, $boxY + 1);
                }
            break;

        case 'txt':
            if ($label==='ประมาณระยะทางใช้รถ') {
                $pdf->Cell($colW-40, $LINE_H, $distance.' กม.', 0, 0);
            } else {
                $pdf->Cell($colW-40, $LINE_H, $cfg, 0, 0);
            }
            break;

        default:
            $pdf->Cell($colW-40, $LINE_H, $cfg, 0, 0);
            break;
    }

    // ขึ้นบรรทัดถัดไป ตาม offset เฉพาะบรรทัดวันที่/เวลา
    $y2 += $rowOff;
}

// กำหนดว่าจะเลื่อนไปขวากี่มม.
$offsetX = 50;
// 1) คำว่า “ลงชื่อ” ทางซ้าย
$pdf->SetFont($fontName, '', $BODY_F);
$pdf->SetXY($rx + $offsetX, $y2);
$label1 = 'ลงชื่อ';
$label1W = $pdf->GetStringWidth($label1) + 2;
$pdf->Cell($label1W, $LINE_H, $label1, 0, 0, 'L');

// 2) รูปเซ็นต์ตรงกลาง
$imgFile = __DIR__ . '/signaturePic/sign_car.png';
$imgW    = 20;
$imgH    = 6;
$imgX    = $rx + $offsetX + $label1W + 2;
$imgY    = $y2 + ($LINE_H - $imgH) / 2;
$pdf->Image(
    $imgFile,
    $imgX,
    $imgY,
    $imgW,
    $imgH,
    'PNG',
    '',
    'M',
    false,
    300
);

// 3) คำว่า “ผู้จ่ายรถ” ทางขวา
$pdf->SetFont($fontName, '', $BODY_F);
$label2 = 'ผู้จ่ายรถ';
$pdf->SetXY($imgX + $imgW + 2, $y2);
$pdf->Cell(0, $LINE_H, $label2, 0, 1, 'L');

// 4) เลื่อน Y ไปส่วนถัดไป
$y2 += max($LINE_H, $imgH) + 1;

// 5) ชื่อผู้จ่ายรถใต้ signature แบบคงที่
$nameOffsetY = -3; // เลื่อนขึ้นตามที่ตั้งไว้

$pdf->SetFont($fontName, '', $BODY_F);

$text = '( นายจุฑา อ่อนศรีทอง )';
$pdf->SetXY($imgX, $y2 + $nameOffsetY);
$pdf->Cell($imgW, $LINE_H, $text, 0, 1, 'C');

$textW   = $pdf->GetStringWidth($text);
$prefixW = $pdf->GetStringWidth('( ');
$nameW   = $pdf->GetStringWidth('นายจุฑา อ่อนศรีทอง');

$textX = $imgX + ($imgW - $textW) / 2;
$lineY = $y2 + $nameOffsetY + $LINE_H - 2;

// วาดเส้นใต้ชื่อ
$pdf->SetLineWidth(0.4);
$pdf->Line(
    $textX + $prefixW,         // จุดเริ่ม
    $lineY,                    // Y
    $textX + $prefixW + $nameW,// จุดสิ้นสุด
    $lineY
);

$y2 += $LINE_H * 1.4;

$offsetX = 25; 
$pdf->SetFont($fontName, 'B', $BODY_F + 5);
$pdf->SetLineWidth(0.4);
$pdf->Rect($rx, $y2, $colW, $LINE_H + 0.5);
L(
    $pdf,
    $rx + $offsetX,
    $y2,
    $colW,
    $LINE_H,
    'ส่วนนี้พนักงานขับรถเป็นผู้บันทึก'
);
$pdf->SetFont($fontName, '', $BODY_F);
$y2 += $LINE_H + 7;

L($pdf, $rx, $y2, 35, $LINE_H, 'รหัสประจำตัวพนักงานขับรถ', $driverIdStr = str_pad($data['employee_id'], 8, ' ', STR_PAD_LEFT));
$bx = $rx + 37;
$bxFn($bx, $y2, 8);
$fillFn($bx, $y2, $driverIdStr);
$y2 += $LINE_H + 6;

$pdf->SetXY($rx, $y2);

$name = $data['full_name'];

// ตัดคำ "นาย", "นาง", "นางสาว" ออกจากชื่อ
$name = str_replace(['นาย', 'นาง', 'นางสาว'], '', $name);

// กำหนดค่าตัวอักษร
$nameW = $pdf->GetStringWidth($name);
$startX = $rx + 50;

// กำหนดตำแหน่งและพิมพ์ชื่อ
$pdf->SetXY($startX, $y2);
$pdf->Cell($nameW, $LINE_H, $name, 0, 0, 'L');
//วาดเส้นใต้ชื่อ
$lineY = $y2 + $LINE_H - 2;
$pdf->Line($startX, $lineY, $startX + $nameW, $lineY);

$label   = 'ลงชื่อ';
$labelW  = $pdf->GetStringWidth($label);
$gap     = 2;  
$labelX  = $startX - $labelW - $gap;
$pdf->SetXY($labelX, $y2);
$pdf->Cell($labelW, $LINE_H, $label, 0, 0, 'L');

$pdf->SetFont($fontName, '', $BODY_F);
$staffLabel = 'พนักงานขับรถ';
$labelGap   = 3;  
$textX = $startX + $nameW + $labelGap;
$pdf->SetXY($textX, $y2);
$pdf->Cell($pdf->GetStringWidth($staffLabel), $LINE_H, $staffLabel, 0, 0, 'L');

$nameOffsetX = 51; 
$y2 += $LINE_H + 0;
$startNameX = $rx + $nameOffsetX;
$pdf->SetXY($startNameX, $y2);
$name  = '( ' . $data['full_name'] . ' )';
$nameW = $pdf->GetStringWidth($name);
$pdf->Cell($nameW, $LINE_H, $name, 0, 0, 'R');
$lineY = $y2 + $LINE_H - 2;
$pdf->Line($startNameX, $lineY, $startNameX + $nameW, $lineY);

$dateOffsetX = 53;
$y2         += $LINE_H + 0;
$baseX       = $rx + $dateOffsetX;
$pdf->SetXY($baseX, $y2);

$parts = [
  'paren_open' => '(',
  'day'         => date('d', strtotime($data['end'])),
  'slash1'      => '/',
  'month'       => date('m', strtotime($data['end'])),
  'slash2'      => '/',
  'year'        => date('Y', strtotime($data['end'])),
  'paren_close'=> ')',
];

$currentX = $baseX;
foreach ($parts as $key => $text) {
    $w = $pdf->GetStringWidth($text);
    // พิมพ์ข้อความ
    $pdf->SetXY($currentX, $y2);
    $pdf->Cell($w, $LINE_H, $text, 0, 0, 'L');
    //ขีดเส้นใต้ตัวเลข
    if (in_array($key, ['day','month','year'], true)) {
        $lineY = $y2 + $LINE_H - 2;
        $pdf->Line($currentX, $lineY, $currentX + $w, $lineY);
    }
    $currentX += $w;
}

$y2 += $LINE_H + 1;

$y2 += $LINE_H + 15;
$offsetX = 30;
// ส่วนนี้ผู้ใช้รถเป็นผู้บันทึก
$pdf->SetFont($fontName, 'B', $BODY_F + 5);
$pdf->SetLineWidth(0.4);
$pdf->Rect($rx, $y2, $colW, $LINE_H + 0.5);
L(
    $pdf,
    $rx + $offsetX,
    $y2,
    $colW,
    $LINE_H,
    'ส่วนนี้ผู้ใช้รถเป็นผู้บันทึก'
);
$pdf->SetFont($fontName, '', $BODY_F);
$y2 += $LINE_H + 7;

foreach([['เลขระยะทางเริ่มใช้',$mStartStr],['เลขระยะทางสิ้นสุด',$mEndStr]] as$r){
    L($pdf,$rx,$y2,40,$LINE_H,$r[0]);
    $bx=$rx+42;
    $bxFn($bx,$y2,6);
    $fillFn($bx,$y2,$r[1]);
    $y2+=$LINE_H+4;
}

// แปลงวัน-เวลาจาก create_date (ยังเป็น ค.ศ.)
$createDate = strtotime($data['create_date']);
$buddhYear = date('Y', $createDate) + 543;
$endStr = date('dm', $createDate) . substr($buddhYear, -2) . date('Hi', $createDate);
$day    = substr($endStr, 0, 2);
$month  = substr($endStr, 2, 2);
$year   = substr($endStr, 4, 2);
$time   = substr($endStr, 6, 4);

// พิมพ์ป้ายชื่อฟิลด์
L($pdf, $rx, $y2, 35, $LINE_H, 'วันที่สิ้นสุดการใช้');

// ตั้งค่าจุดเริ่มต้น และ offsets
$bxStart    = $rx + 27;
$vertOffset = ($LINE_H - $BOX) / 50;  // กึ่งกลางแนวตั้ง
$groupGap   = 3;                     // ระยะห่างระหว่างกลุ่ม
$groupSizes = [2, 2, 2, 4];
$titles     = ['วันที่','เดือน','ปี','เวลา'];

// 1) วาดป้ายหัวข้อย่อย (วันที่/เดือน/ปี/เวลา) เหนือกล่อง
$labelY  = $y2 - ($LINE_H * 0.8);
$bxLabel = $bxStart;
foreach ($titles as $i => $title) {
    $n      = $groupSizes[$i];
    $groupW = $n * $BOX + ($n - 1) * $GAP;
    $tw     = $pdf->GetStringWidth($title);

    $pdf->SetXY(
        $bxLabel + ($groupW - $tw) / 2,
        $labelY
    );
    $pdf->Cell($tw, $LINE_H, $title, 0, 0, 'C');

    // เลื่อนไปกลุ่มถัดไป
    $bxLabel += $groupW + $groupGap;
}

// 2) วาดกล่องแต่ละกลุ่ม และเติมค่าในกล่อง
$bx = $bxStart;

// วัน 2 บล็อก
$bxFn($bx, $y2, 2);
fillBoxes($pdf, $bx, $y2 + $vertOffset, $day, $BOX, $GAP);
$bx += 2 * ($BOX + $GAP) + $groupGap;

// เดือน 2 บล็อก
$bxFn($bx, $y2, 2);
fillBoxes($pdf, $bx, $y2 + $vertOffset, $month, $BOX, $GAP);
$bx += 2 * ($BOX + $GAP) + $groupGap;

// ปี 2 บล็อก
$bxFn($bx, $y2, 2);
fillBoxes($pdf, $bx, $y2 + $vertOffset, $year, $BOX, $GAP);
$bx += 2 * ($BOX + $GAP) + $groupGap;

// เวลา 4 บล็อก
$bxFn($bx, $y2, 4);
fillBoxes($pdf, $bx, $y2 + $vertOffset, $time, $BOX, $GAP);
$bx += 4 * ($BOX + $GAP) + $groupGap;

// “น.” 
$pdf->SetXY($bx-2, $y2 + $vertOffset);
$pdf->Cell($pdf->GetStringWidth('น.'), $LINE_H, 'น.', 0, 0, 'L');

// 4) เลื่อนไปบรรทัดถัดไป
$y2 += $LINE_H + 1;

$pdf->SetFont($fontName, 'B', $BODY_F);     // ฟอนต์หนา สำหรับคำว่า “ลงชื่อ”
$pdf->SetXY($rx, $y2);

//ลงชื่อ
$labelW = 60; 
$pdf->Cell($labelW, $LINE_H, 'ลงชื่อ  ', 0, 0, 'R');

//พิมพ์ชื่อจริง
$pdf->SetFont($fontName, '', $BODY_F);      
$name     = $data['full_name'];

// ตัดคำ "นาย", "นาง", "นางสาว" ออกจากชื่อ
$name = str_replace(['นาย', 'นาง', 'นางสาว'], '', $name);

// คำนวณความกว้างของชื่อ
$nameW    = $pdf->GetStringWidth($name);

// แสดงชื่อในตำแหน่งที่กำหนด
$pdf->Cell($nameW, $LINE_H, $name, 0, 0, 'R');

//วาดเส้นใต้ชื่อจริง
$lineY  = $y2 + $LINE_H - 2;                      
$lineX1 = $rx + $labelW;                           
$nameW   = $pdf->GetStringWidth($name);

//สำหรับเพิ่มความยาวเส้น
$extra   = 2;                                     
$startX  = $lineX1;                                
$endX    = $lineX1 + $nameW + $extra;

$pdf->Line($startX, $lineY, $endX, $lineY);

$pdf->SetFont($fontName, 'B', $BODY_F);
$pdf->Cell(0, $LINE_H, 'ผู้ใช้รถ', 0, 1, 'R');

$y2 += 7;               
$pdf->SetXY($rx, $y2);

$pdf->SetFont($fontName, '', $BODY_F);
$labelW = 60;
$pdf->Cell($labelW, $LINE_H, '( ', 0, 0, 'R');

// พิมพ์ชื่อจริง
$pdf->SetFont($fontName, '', $BODY_F);
$name  = $data['full_name'];
$nameW = $pdf->GetStringWidth($name);
$pdf->Cell($nameW, $LINE_H, $name, 0, 0, 'R');

//วาดเส้นใต้ชื่อจริง
$lineY  = $y2 + $LINE_H - 2;
$lineX1 = $rx + $labelW;
$extra2  = 3;                       
$lineX2 = $lineX1 + $nameW + $extra2;
$pdf->Line($lineX1, $lineY, $lineX2, $lineY);

$parenX = $rx + $labelW + $nameW + 3;  
$pdf->SetXY($parenX, $y2);
$pdf->Cell(5, $LINE_H, ')', 0, 1, 'L');

$pdf->Output('form_nt_max_'.$id.'.pdf','I');
?>
