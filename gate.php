<?php
session_start();
require_once 'db_config.php';

// Check for token in URL
$token = $_GET['token'] ?? '';
$property = null;

if ($token) {
    $stmt = $pdo->prepare("SELECT p.* FROM properties p JOIN security_links s ON p.id = s.property_id WHERE s.access_token = ?");
    $stmt->execute([$token]);
    $property = $stmt->fetch();
}

if (!$property) {
    die("<h1>Access Denied</h1><p>Invalid or expired security token.</p>");
}

// Handle Visitor Log Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_visitor'])) {
    $name = $_POST['v_name'];
    $phone = $_POST['v_phone'];
    $plate = $_POST['v_plate'] ?? '';
    $unit_id = $_POST['unit_id'];
    
    // Get tenant ID from unit
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE unit_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$unit_id]);
    $tenant = $stmt->fetch();
    
    $stmt = $pdo->prepare("INSERT INTO visitors (property_id, tenant_id, name, phone_number, number_plate, visit_date, time_in) VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME())");
    $stmt->execute([$property['id'], $tenant['id'] ?? null, $name, $phone, $plate]);
    $success = "Visitor logged successfully!";
}

// Fetch active tenants for matching
$stmt = $pdo->prepare("SELECT t.name, u.unit_number, u.id as unit_id FROM tenants t JOIN units u ON t.unit_id = u.id WHERE t.property_id = ? AND t.status = 'active' ORDER BY u.unit_number");
$stmt->execute([$property['id']]);
$tenants = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Portal - <?php echo $property['name']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8fafc; padding: 20px; color: #1e293b; }
        .container { max-width: 500px; margin: 0 auto; background: white; padding: 20px; border-radius: 15px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        h1 { font-size: 20px; margin-bottom: 5px; color: #4361ee; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 5px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: #4361ee; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .alert { padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .tenant-match { background: #f1f5f9; padding: 10px; border-radius: 8px; font-size: 13px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div style="text-align: center; margin-bottom: 20px;">
            <i class="fas fa-shield-alt" style="font-size: 40px; color: #4361ee;"></i>
            <h1>Security Portal</h1>
            <p style="color: #64748b; font-size: 14px;"><?php echo $property['name']; ?></p>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Visitor Name</label>
                <input type="text" name="v_name" class="form-control" required placeholder="Full Name">
            </div>
            <div class="form-group">
                <label>Visitor Phone</label>
                <input type="tel" name="v_phone" class="form-control" required placeholder="07...">
            </div>
            <div class="form-group">
                <label>Vehicle Plate (Optional)</label>
                <input type="text" name="v_plate" class="form-control" placeholder="KAA 001A">
            </div>
            <div class="form-group">
                <label>Visiting (House Number)</label>
                <select name="unit_id" class="form-control" required>
                    <option value="">Select House</option>
                    <?php foreach ($tenants as $t): ?>
                        <option value="<?php echo $t['unit_id']; ?>"><?php echo $t['unit_number']; ?> - <?php echo $t['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="log_visitor" class="btn">Log Entry</button>
        </form>

        <div class="tenant-match">
            <strong><i class="fas fa-info-circle"></i> Verification Tip:</strong>
            <p>Always verify the visitor's ID and cross-check the tenant name before allowing entry.</p>
        </div>
    </div>
</body>
</html>
