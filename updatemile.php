<?php
// updatemile.php

// ----------------------
// Database connection
// ----------------------
$servername = "localhost";
$username   = "root";
$password   = "W@tt7425j4636";
$dbname     = "car_booking";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----------------------
// ตรวจสอบค่าพารามิเตอร์ ID
// ----------------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger text-center'>ไม่พบค่า ID ใน URL</div>";
    exit;
}
$id = (int) $_GET['id'];

// ----------------------
// ดึงข้อมูลเดิมมาแสดงในฟอร์ม พร้อม license_expiry_date และ number_plate
// ----------------------
$stmt = $conn->prepare(
    "SELECT
        r.vehicle_id,
        r.employee_id,
        r.time_type,
        r.province,
        r.mileage_end,
        r.number_plate,
        d.license_expiry_date
     FROM app_car_reservation AS r
     LEFT JOIN drivers_license AS d
       ON r.employee_id = d.employee_id
     WHERE r.id = ?"
);
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo "<div class='alert alert-warning text-center'>ข้อมูลไม่พบ</div>";
    exit;
}

// ----------------------
// ดึงค่า mileage_end ล่าสุดจากตาราง check_distance
// ----------------------
$vehicle_id = (int) $row['vehicle_id'];
$distStmt = $conn->prepare(
    "SELECT mileage_end
       FROM check_distance
      WHERE vehicle_id = ?"
);
$distStmt->bind_param("i", $vehicle_id);
$distStmt->execute();
$distRow = $distStmt->get_result()->fetch_assoc();
$distStmt->close();

$startValue = !empty($distRow['mileage_end'])
    ? (int)$distRow['mileage_end']
    : 0;

// ----------------------
// อัปเดตข้อมูลเมื่อมีการส่งฟอร์ม
// ----------------------
$errors = [];
if ($_SERVER["REQUEST_METHOD"] === 'POST') {
    $m1             = (int) $_POST['mileage_start'];
    $m2             = (int) $_POST['mileage_end'];
    $emp            = trim($_POST['employee_id']);
    $time_type      = $_POST['time_type'];
    $province       = $_POST['province'];
    $number_plate   = $_POST['number_plate'];

   // 1) ไมล์เริ่มต้นต้อง >= ค่าสุดท้าย

   
   // if ($m1 < $startValue) {
    //     $errors[] = "ค่า Mileage Start ต้องมากกว่าหรือเท่ากับ {$startValue}";
    // }

    // 2) ตรวจสอบรหัสพนักงานใน drivers_license
    $dlStmt = $conn->prepare(
      "SELECT DATE_FORMAT(license_expiry_date, '%d/%m/%Y') AS expiry
         FROM drivers_license
        WHERE employee_id = ?
        LIMIT 1"
    );
    $dlStmt->bind_param("s", $emp);
    $dlStmt->execute();
    $dlRes = $dlStmt->get_result();

    if ($dlRes->num_rows === 0) {
        $errors[] = "รหัสพนักงานไม่ถูกต้อง กรุณาใช้รหัสที่มีอยู่ในระบบ";
    } else {
        $dlRow = $dlRes->fetch_assoc();
        $license_ddmmyy = $dlRow['expiry'];
        list($d, $m, $y_p) = explode('/', $license_ddmmyy);
        $y = (int)$y_p - 543;
        $license_ymd = sprintf('%04d-%02d-%02d', $y, $m, $d);
        $today = date('Y-m-d');

        if ($today > $license_ymd) {
            $errors[] = "ใบขับขี่ของท่านหมดอายุวันที่ {$license_ddmmyy} ทำให้ไม่สามารถคืนรถได้";
        }
    }
    $dlStmt->close();

    // 3) ไมล์สิ้นสุดต้อง >= ไมล์เริ่มต้น
    if ($m2 < $m1) {
        $errors[] = "ค่า Mileage End ต้องมากกว่าหรือเท่ากับ Mileage Start";
    }

    // 4) number_plate ต้องไม่ว่าง
    if (empty($number_plate)) {
        $errors[] = "กรุณาเลือกทะเบียนรถ";
    }

    // 5) ถ้าไม่มีข้อผิดพลาด จึงอัปเดต
    if (empty($errors)) {
        $update = $conn->prepare(
          "UPDATE app_car_reservation
              SET mileage_start      = ?,
                  mileage_end        = ?,
                  employee_id        = ?,
                  time_type          = ?,
                  province           = ?,
                  number_plate       = ?,
                  actual_return_time = NOW()
            WHERE id = ?"
        );
        $update->bind_param("iissssi",
            $m1,
            $m2,
            $emp,
            $time_type,
            $province,
            $number_plate,
            $id
        );
        if ($update->execute()) {
            $upDist = $conn->prepare(
                "INSERT INTO check_distance (vehicle_id, mileage_end)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE mileage_end = VALUES(mileage_end)"
            );
            $upDist->bind_param("ii", $vehicle_id, $m2);
            $upDist->execute();
            $upDist->close();

            echo "<div class='alert alert-success text-center' style='font-size:24px;font-weight:bold;'>
                    คืนรถสำเร็จ!
                  </div>";
            echo "<script>setTimeout(() => window.location.href = 'index.php', 2000);</script>";
            exit;
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการอัปเดต: " . htmlspecialchars($conn->error);
        }
        $update->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>คืนรถ</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .card { border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .btn-custom { background-color: #28a745; color: #fff; transition:0.3s; }
    .btn-custom:hover { background-color: #218838; }
  </style>
</head>
<body>
  <div class="container mt-5">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card p-4">
          <a href="index.php" class="btn btn-outline-secondary mb-3">&#8592; กลับ</a>
          <h2 class="text-center mb-4">คืนรถ</h2>
          <form method="POST">
            <!-- รหัสพนักงาน -->
            <div class="mb-3">
              <label for="employee_id" class="form-label">รหัสพนักงาน 8 หลัก</label>
              <input type="text" id="employee_id" name="employee_id" class="form-control"
                     value="<?= htmlspecialchars($row['employee_id']) ?>" required>
            </div>

            <!-- Dropdown ทะเบียนรถ -->
            <div class="mb-3">
              <label for="number_plate" class="form-label">ทะเบียนรถ</label>
              <select id="number_plate" name="number_plate" class="form-select" required>
                <option value="">-- เลือกรถ --</option>
                <optgroup label="รถเก๋ง">
                  <?php foreach (['6ขข4192','6ขข4194','3ขถ9053','6ขค4105'] as $plate): ?>
                    <option value="<?= $plate ?>"
                      <?= $row['number_plate'] === $plate ? 'selected' : '' ?>>
                      <?= $plate ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="รถกระบะ">
                  <?php foreach (['4ฒฆ3092','4ฒฆ3229','4ฒฆ3231','4ฒฆ3233'] as $plate): ?>
                    <option value="<?= $plate ?>"
                      <?= $row['number_plate'] === $plate ? 'selected' : '' ?>>
                      <?= $plate ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
                <optgroup label="รถตู้">
                  <?php foreach (['1นช9371'] as $plate): ?>
                    <option value="<?= $plate ?>"
                      <?= $row['number_plate'] === $plate ? 'selected' : '' ?>>
                      <?= $plate ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              </select>
            </div>

           <!-- ไมล์เริ่มต้น -->
<div class="mb-3">
  <label for="mileage_start" class="form-label">
    เข็มไมล์เริ่มต้น <small class="text-muted" id="lastMileLabel"></small>
  </label>

  <input type="number" id="mileage_start" name="mileage_start"
         class="form-control" required>
</div>           
           <!-- <div class="mb-3">
              <label for="mileage_start" class="form-label">
                เข็มไมล์เริ่มต้น <small class="text-muted"></small>
              </label>
              <input type="number" id="mileage_start" name="mileage_start" class="form-control"
                 required>  
            </div> -->
            <!-- ไมล์สิ้นสุด -->
            <div class="mb-3">
              <label for="mileage_end" class="form-label">เข็มไมล์สิ้นสุด</label>
              <input type="number" id="mileage_end" name="mileage_end" class="form-control"
                     value="<?= htmlspecialchars($row['mileage_end']) ?>" required>
            </div>
            <!-- ประเภทการใช้งาน -->
            <div class="mb-3">
              <label for="time_type" class="form-label">ประเภทการใช้งาน</label>
              <select id="time_type" name="time_type" class="form-select" required>
                <option value="ในเวลา" <?= $row['time_type']=='ในเวลา'?'selected':'' ?>>
                  ในเวลาราชการ
                </option>
                <option value="นอกเวลา" <?= $row['time_type']=='นอกเวลา'?'selected':'' ?>>
                  นอกเวลาราชการ
                </option>
                <option value="ในเวลาและนอกเวลา" <?= $row['time_type']=='ในเวลาและนอกเวลา'?'selected':'' ?>>
                  ทั้งสองช่วงเวลา
                </option>
              </select>
            </div>
            <!-- จังหวัด -->
            <div class="mb-3">
              <label for="province" class="form-label">จังหวัด</label>
              <select id="province" name="province" class="form-select" required>
                <option value="">-- เลือกจังหวัด --</option>
                <?php
                  $south = [
                    'กระบี่','ชุมพร','ตรัง','พังงา','พัทลุง','ภูเก็ต',
                    'ระนอง','สงขลา','สตูล','สุราษฎร์ธานี',
                    'นครศรีธรรมราช','นราธิวาส','ปัตตานี','ยะลา'
                  ];
                  foreach($south as $prov) {
                    $sel = ($row['province']==$prov)? 'selected':''; 
                    echo "<option value=\"{$prov}\" {$sel}>{$prov}</option>";
                  }
                ?>
              </select>
            </div>

            <!-- แสดงข้อผิดพลาด -->
            <?php if (!empty($errors)): ?>
              <div class="alert alert-danger">
                <?php foreach($errors as $err): ?>
                  <p><?= htmlspecialchars($err) ?></p>
                <?php endforeach ?>
              </div>
            <?php endif ?>

            <div class="text-center">
              <button type="submit" class="btn btn-custom">คืนรถเรียบร้อย</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <script>
document.getElementById('number_plate').addEventListener('change', function () {
    const plate = this.value;
    const startInput = document.getElementById('mileage_start');
    const label = document.getElementById('lastMileLabel');

    if (!plate) {
        startInput.value = "";
        startInput.removeAttribute("min");
        label.textContent = "";
        return;
    }

    fetch("get_last_mileage.php?plate=" + plate)
        .then(res => res.json())
        .then(data => {
            if (data.mileage !== null) {
                label.textContent = `(ล่าสุด ${data.mileage})`;

                // ตั้งค่า min ให้ไมล์เริ่มต้น
                startInput.min = data.mileage;
                startInput.placeholder = "≥ " + data.mileage;
            } else {
                label.textContent = "(ไม่มีข้อมูลไมล์ล่าสุด)";
                startInput.removeAttribute("min");
                startInput.placeholder = "";
            }
        })
        .catch(() => {
            label.textContent = "(เกิดข้อผิดพลาด)";
        });
});
</script>
s
</body>
</html>


