<?php
// get_last_mileage.php
header('Content-Type: application/json');

$servername = "localhost";
$username   = "root";
$password   = "W@tt7425j4636";
$dbname     = "car_booking";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['error' => true, 'msg' => 'DB connect failed']);
    exit;
}

if (!isset($_GET['plate']) || empty($_GET['plate'])) {
    echo json_encode(['error' => true, 'msg' => 'no_plate']);
    exit;
}

$plate = $_GET['plate'];

// ดึง vehicle_id จากทะเบียนรถ
$stmt = $conn->prepare("SELECT vehicle_id FROM app_car_reservation WHERE number_plate = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $plate);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$res) {
    echo json_encode(['mileage' => null]);
    exit;
}

$vehicle_id = (int)$res['vehicle_id'];

// ดึงค่าไมล์ล่าสุดจาก check_distance
$mStmt = $conn->prepare("SELECT mileage_end FROM check_distance WHERE vehicle_id = ?");
$mStmt->bind_param("i", $vehicle_id);
$mStmt->execute();
$mRow = $mStmt->get_result()->fetch_assoc();
$mStmt->close();

echo json_encode([
    'mileage' => $mRow ? (int)$mRow['mileage_end'] : null
]);
