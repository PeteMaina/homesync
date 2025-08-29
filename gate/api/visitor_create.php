<?php
// api/visitor_create.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/config.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.']);
    exit;
}

$full_name   = trim($data['full_name'] ?? '');
$citizen_id  = trim($data['citizen_id'] ?? null);
$phone       = trim($data['phone_number'] ?? null);
$plate       = trim($data['number_plate'] ?? null);
$house       = trim($data['house_number'] ?? '');
$time_in     = $data['time_in'] ?? null;
$time_out    = $data['time_out'] ?? null;
$signature   = $data['signature'] ?? null;

// Basic validation
$errors = [];
if ($full_name === '') $errors[] = 'Full name is required.';
if ($house === '') $errors[] = 'House number (house visited) is required.';

if (!empty($phone) && !preg_match('/^[\d\+\-\s]{6,20}$/', $phone)) {
    $errors[] = 'Phone number format seems invalid.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

// Normalize datetime-local (from HTML) -> MySQL DATETIME (if provided)
function normalize_datetime($dt) {
    if (!$dt) return null;
    // dt might be like "2025-08-29T14:12"
    $normalized = str_replace('T', ' ', $dt);
    // if missing seconds, add :00
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalized)) {
        $normalized .= ':00';
    }
    return $normalized;
}
$time_in  = normalize_datetime($time_in);
$time_out = normalize_datetime($time_out);

// Try to find tenant_id by house_number (optional)
$tenant_id = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE house_number = ? LIMIT 1");
    $stmt->execute([$house]);
    $row = $stmt->fetch();
    if ($row) $tenant_id = $row['id'];
} catch (Exception $e) {
    // If tenants table doesn't exist it's ok; continue without tenant_id
}

// Handle signature: accept base64 PNG in $signature
$signature_path = null;
if ($signature && preg_match('/^data:image\/png;base64,/', $signature)) {
    $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $signature);
    $base64 = str_replace(' ', '+', $base64);
    $dataDecoded = base64_decode($base64);
    if ($dataDecoded === false) {
        // ignore signature or fail
        http_response_code(400);
        echo json_encode(['error' => 'Signature data invalid.']);
        exit;
    }
    $uploadDir = __DIR__ . '/../uploads/signatures/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $fn = 'sig_' . time() . '_' . bin2hex(random_bytes(5)) . '.png';
    $fullPath = $uploadDir . $fn;
    file_put_contents($fullPath, $dataDecoded);
    // store relative path so the frontend can access via /uploads/...
    $signature_path = 'uploads/signatures/' . $fn;
}

try {
    $sql = "INSERT INTO visitors
        (citizen_id, full_name, phone_number, number_plate, time_in, time_out, signature, house_number, tenant_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $citizen_id ?: null,
        $full_name,
        $phone ?: null,
        $plate ?: null,
        $time_in ?: date('Y-m-d H:i:s'),
        $time_out ?: null,
        $signature_path ?: null,
        $house,
        $tenant_id ?: null
    ]);
    $lastId = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'id' => (int)$lastId]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Insert failed: ' . $e->getMessage()]);
    exit;
}
