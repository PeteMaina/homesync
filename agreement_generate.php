<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) { header('Location: auth.html'); exit; }

$landlord_id = $_SESSION['admin_id'];

function generate_token($length = 32) {
    return bin2hex(random_bytes(intval($length/2)));
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo 'Method not allowed'; exit; }
    $tenant_id = intval($_POST['tenant_id'] ?? 0);
    $agreement_id = isset($_POST['agreement_id']) && $_POST['agreement_id'] !== '' ? intval($_POST['agreement_id']) : null;
    if (!$tenant_id) { throw new Exception('Missing tenant_id'); }

    // Load tenant + unit + property context
    $stmt = $pdo->prepare("SELECT t.*, u.unit_number, u.rent_amount AS unit_rent, p.name AS property_name, p.id AS property_id
                           FROM tenants t
                           JOIN units u ON t.unit_id = u.id
                           JOIN properties p ON t.property_id = p.id
                           WHERE t.id = ? AND p.landlord_id = ?");
    $stmt->execute([$tenant_id, $landlord_id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tenant) { throw new Exception('Tenant not found'); }

    // Resolve template: explicit or latest
    if ($agreement_id) {
        $tpl = $pdo->prepare("SELECT * FROM agreements WHERE id = ? AND landlord_id = ?");
        $tpl->execute([$agreement_id, $landlord_id]);
    } else {
        $tpl = $pdo->prepare("SELECT * FROM agreements WHERE landlord_id = ? ORDER BY created_at DESC LIMIT 1");
        $tpl->execute([$landlord_id]);
    }
    $template = $tpl->fetch(PDO::FETCH_ASSOC);
    if (!$template) { throw new Exception('No agreement template found. Create one first.'); }

    // Placeholder replacement
    $rent = (isset($tenant['rent_amount']) && $tenant['rent_amount'] !== null && $tenant['rent_amount'] > 0)
        ? (float)$tenant['rent_amount'] : (float)$tenant['unit_rent'];
    $replacements = [
        '{{tenant_name}}'   => $tenant['name'],
        '{{id_number}}'     => $tenant['id_number'],
        '{{unit_number}}'   => $tenant['unit_number'],
        '{{property_name}}' => $tenant['property_name'],
        '{{rent_amount}}'   => 'KES ' . number_format($rent, 2),
        '{{move_in_date}}'  => date('M j, Y', strtotime($tenant['move_in_date']))
    ];
    $filled = strtr($template['template_html'], $replacements);

    // Ensure uploads dirs exist
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($baseDir)) { @mkdir($baseDir, 0755, true); }
    $agreementsDir = $baseDir . DIRECTORY_SEPARATOR . 'agreements';
    if (!is_dir($agreementsDir)) { @mkdir($agreementsDir, 0755, true); }
    $signDir = $baseDir . DIRECTORY_SEPARATOR . 'signatures';
    if (!is_dir($signDir)) { @mkdir($signDir, 0755, true); }

    // Create tenant_agreement
    $token = generate_token(32);
    $ins = $pdo->prepare("INSERT INTO tenant_agreements (agreement_id, tenant_id, property_id, unit_id, access_token, filled_html)
                          VALUES (?, ?, ?, ?, ?, ?)");
    $ins->execute([$template['id'], $tenant['id'], $tenant['property_id'], $tenant['unit_id'], $token, $filled]);

    $link = sprintf('%s/agreement_sign.php?token=%s', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'), $token);

    // Show a minimal confirmation page with link
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Agreement Link</title>';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
    echo '<style>body{font-family:Inter,system-ui;padding:30px;background:#f8fafc;color:#0f172a} .card{background:#fff;padding:24px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.05);} .btn{padding:10px 14px;border-radius:8px;border:1px solid #4361ee;color:#4361ee;text-decoration:none} pre{background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;overflow:auto}</style></head><body>';
    echo '<div class="card">';
    echo '<h2>Agreement generated</h2>';
    echo '<p>Share this link with the tenant to review and sign:</p>';
    echo '<p><a class="btn" href="' . htmlspecialchars($link) . '" target="_blank">Open Sign Page</a></p>';
    echo '<pre>' . htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?'https':'http') . '://' . $_SERVER['HTTP_HOST'] . $link) . '</pre>';
    echo '<p><a href="tenants.php" class="btn" style="margin-top:10px">Back to Tenants</a></p>';
    echo '</div></body></html>';
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo 'Error: ' . htmlspecialchars($e->getMessage());
}
?>
