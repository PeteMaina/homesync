<?php
session_start();
require_once 'db_config.php';
require_once 'SmsService.php';

$sms = new SmsService(); // Credentials should be configured in SmsService.php or env

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

// Handle Visitor Log Submission (Entry)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_visitor'])) {
    $name = $_POST['v_name'];
    $phone = $_POST['v_phone'];
    $id_number = $_POST['v_id_number'] ?? '';
    $plate = $_POST['v_plate'] ?? '';
    $unit_id = $_POST['unit_id'];
    
    // Get tenant and unit info
    $stmt = $pdo->prepare("
        SELECT t.id as tenant_id, t.name as tenant_name, t.phone_number as tenant_phone, u.unit_number 
        FROM units u 
        LEFT JOIN tenants t ON t.unit_id = u.id AND t.status = 'active'
        WHERE u.id = ?
    ");
    $stmt->execute([$unit_id]);
    $data = $stmt->fetch();
    
    $stmt = $pdo->prepare("INSERT INTO visitors (property_id, tenant_id, name, id_number, phone_number, number_plate, visit_date, time_in) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())");
    $stmt->execute([$property['id'], $data['tenant_id'] ?? null, $name, $id_number, $phone, $plate]);
    
    // Send SMS Notifications
    if ($data) {
        $propName = $property['name'];
        $unitNum = $data['unit_number'];
        $tenantName = $data['tenant_name'] ?? 'Vacant Unit';
        $vMsg = "HI $name you have visited $propName to $unitNum, visiting $tenantName.";
        $sms->sendSms($phone, $vMsg);
        
        if ($data['tenant_phone']) {
            $tMsg = "Hello $tenantName, you have a visitor $name at your house.";
            $sms->sendSms($data['tenant_phone'], $tMsg);
        }
    }
    
    $success = "Visitor logged successfully!";
}

// Handle Visitor Logout (Exit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout_visitor'])) {
    $visitor_id = $_POST['visitor_id'];
    $stmt = $pdo->prepare("UPDATE visitors SET time_out = CURTIME() WHERE id = ?");
    $stmt->execute([$visitor_id]);
    $success = "Visitor logged out successfully!";
}

// Fetch checked-in visitors (no time_out yet)
$stmt = $pdo->prepare("
    SELECT v.*, u.unit_number 
    FROM visitors v 
    LEFT JOIN tenants t ON v.tenant_id = t.id
    LEFT JOIN units u ON t.unit_id = u.id
    WHERE v.property_id = ? AND v.time_out IS NULL AND v.visit_date = CURDATE()
    ORDER BY v.time_in DESC
");
$stmt->execute([$property['id']]);
$active_visitors = $stmt->fetchAll();

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
        .tenant-match { background: #f1f5f9; padding: 15px; border-radius: 8px; font-size: 13px; margin-top: 20px; }
        .active-visitors { margin-top: 30px; border-top: 2px solid #f1f5f9; padding-top: 20px; }
        .visitor-item { display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 10px; border: 1px solid #e2e8f0; }
        .visitor-info h4 { font-size: 14px; margin-bottom: 2px; }
        .visitor-info p { font-size: 12px; color: #64748b; }
        .btn-logout { background: #ef4444; color: white; padding: 6px 12px; border-radius: 6px; font-size: 12px; border: none; cursor: pointer; }
        .btn-logout:hover { background: #dc2626; }
        .search-box { margin-bottom: 15px; position: relative; }
        .search-box i { position: absolute; left: 10px; top: 12px; color: #94a3b8; }
        .search-box input { padding-left: 35px; }
        .required::after { content: " *"; color: #ef4444; }
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
                <label class="required">Visitor Name</label>
                <input type="text" name="v_name" class="form-control" required placeholder="Full Name">
            </div>
            <div class="form-group">
                <label class="required">ID Number</label>
                <input type="text" name="v_id_number" class="form-control" placeholder="ID Card Number">
                <small style="color: #94a3b8; font-size: 11px; display: block; margin-top: 2px;">Used for person verification</small>
            </div>
            <div class="form-group">
                <label class="required">Visitor Phone</label>
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
            <strong><i class="fas fa-info-circle"></i> Security Reminder:</strong>
            <p>Ensure all contractors and visitors are logged. Verify ID numbers visually for security compliance.</p>
        </div>

        <div class="active-visitors">
            <h2 style="font-size: 16px; margin-bottom: 15px;"><i class="fas fa-sign-out-alt"></i> Active Visitors</h2>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="visitorSearch" class="form-control" placeholder="Search by name or house...">
            </div>
            <div id="activeList">
                <?php foreach ($active_visitors as $v): ?>
                    <div class="visitor-item" data-search="<?php echo strtolower($v['name'] . ' ' . $v['unit_number']); ?>">
                        <div class="visitor-info">
                            <h4><?php echo htmlspecialchars($v['name']); ?></h4>
                            <p>Visits: <strong><?php echo htmlspecialchars($v['unit_number']); ?></strong> • In: <?php echo date('H:i', strtotime($v['time_in'])); ?></p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="visitor_id" value="<?php echo $v['id']; ?>">
                            <button type="submit" name="logout_visitor" class="btn-logout">Logout</button>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($active_visitors)): ?>
                    <p style="text-align: center; color: #94a3b8; font-size: 13px; padding: 20px;">No active visitors at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('visitorSearch').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.visitor-item').forEach(item => {
                const searchData = item.getAttribute('data-search');
                item.style.display = searchData.includes(term) ? 'flex' : 'none';
            });
        });
    </script>
</body>
</html>
