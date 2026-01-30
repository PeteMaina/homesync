<?php
session_start();
require_once 'db_config.php';

// Check session timeout (10 minutes)
if (isset($_SESSION['admin_id']) && isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > 600) { // 10 minutes
        session_unset();
        session_destroy();
        header("Location: auth.html");
        exit();
    }
}
$_SESSION['last_activity'] = time();

// Check if landlord is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.html");
    exit();
}

require_once 'SmsService.php';

$landlord_id = $_SESSION['admin_id'];
$sms = new SmsService(); // Credentials would be loaded from DB/Config in production

// Initialize messages
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle Manual Payment (Mark as Paid/Partial)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $bill_id = $_POST['bill_id'];
    $payment_amount = floatval($_POST['payment_amount']);
    $method = $_POST['payment_method'] ?? 'cash';
    
    try {
        $pdo->beginTransaction();
        
        // 1. Get current bill status
        $stmt = $pdo->prepare("SELECT balance, status, tenant_id, unit_id FROM bills WHERE id = ?");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch();
        
        if ($bill) {
            $total_paid_now = $payment_amount;
            
            // 2. Fetch Tenant Credit
            $tStmt = $pdo->prepare("SELECT balance_credit, name, phone_number FROM tenants WHERE id = ?");
            $tStmt->execute([$bill['tenant_id']]);
            $tenant = $tStmt->fetch();
            
            if ($tenant['balance_credit'] > 0) {
                $total_paid_now += $tenant['balance_credit'];
                // Reset tenant credit as it's being used
                $pdo->prepare("UPDATE tenants SET balance_credit = 0 WHERE id = ?")->execute([$bill['tenant_id']]);
            }

            $new_balance = $bill['balance'] - $total_paid_now;
            
            // 3. Handle Overpayment
            if ($new_balance < 0) {
                $excess = abs($new_balance);
                $pdo->prepare("UPDATE tenants SET balance_credit = balance_credit + ? WHERE id = ?")->execute([$excess, $bill['tenant_id']]);
                $new_balance = 0;
            }

            $new_status = $new_balance <= 0 ? 'paid' : 'partial';
            
            // 4. Update Bill
            $upd = $pdo->prepare("UPDATE bills SET balance = ?, status = ? WHERE id = ?");
            $upd->execute([$new_balance, $new_status, $bill_id]);
            
            // 5. Record Payment
            $pay = $pdo->prepare("INSERT INTO payments (bill_id, amount, payment_method) VALUES (?, ?, ?)");
            $pay->execute([$bill_id, $payment_amount, $method]);
            
            // 6. Trigger SMS Confirmation if requested
            if (isset($_POST['send_sms'])) {
                $propStmt = $pdo->prepare("SELECT p.name FROM properties p JOIN units u ON u.property_id = p.id WHERE u.id = ?");
                $propStmt->execute([$bill['unit_id']]);
                $pName = $propStmt->fetchColumn();
                
                $sms->sendPaymentConfirmation($tenant['phone_number'], $tenant['name'], $payment_amount, $new_balance, $pName);
            }
            
            $pdo->commit();
            $message = "Payment recorded successfully!" . ($new_balance == 0 && isset($excess) ? " Excess of KES ".number_format($excess)." added to tenant credit." : "");
            $message_type = "success";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Fetch Properties for the selector
$stmt = $pdo->prepare("SELECT * FROM properties WHERE landlord_id = ?");
$stmt->execute([$landlord_id]);
$properties = $stmt->fetchAll();

$current_property_id = $_GET['property_id'] ?? ($properties[0]['id'] ?? null);

// Redirect to onboarding if no properties exist
if (!$current_property_id) {
    header("Location: onboarding.html");
    exit();
}

// Fetch Summary Stats for the selected property
$stmt = $pdo->prepare("
    SELECT 
        SUM(balance) as total_pending,
        (SELECT COUNT(*) FROM units WHERE property_id = ?) as total_units,
        (SELECT COUNT(*) FROM tenants WHERE property_id = ? AND status = 'active') as total_tenants
    FROM bills b
    JOIN units u ON b.unit_id = u.id
    WHERE u.property_id = ? AND b.status != 'paid'
");
$stmt->execute([$current_property_id, $current_property_id, $current_property_id]);
$stats = $stmt->fetch();

// Fetch Active Bills for the grid/table
$stmt = $pdo->prepare("
    SELECT 
        u.unit_number, 
        t.name as tenant_name, 
        t.phone_number,
        b.id as bill_id,
        b.amount, 
        b.balance, 
        b.status, 
        b.month,
        b.bill_type
    FROM units u
    LEFT JOIN tenants t ON t.unit_id = u.id AND t.status = 'active'
    LEFT JOIN bills b ON b.unit_id = u.id AND b.month = ? AND b.year = ?
    WHERE u.property_id = ?
    ORDER BY u.unit_number ASC
");
// For simplicity, showing current month/year
$stmt->execute([date('F'), date('Y'), $current_property_id]);
$units_bills = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HomeSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --success: #2ec4b6;
            --warning: #ff9f1c;
            --danger: #e71d36;
            --light: #f8f9fa;
            --dark: #0f172a;
            --gray: #64748b;
            --shadow: 0 10px 30px rgba(0,0,0,0.08);
            --transition: all 0.3s ease;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: #f1f5f9; color: var(--dark); display: flex; min-height: 100vh; }

        /* Main Content */
        .main { flex: 1; padding: 30px; overflow-y: auto; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .prop-selector { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; }
        .prop-pill { padding: 10px 20px; border-radius: 50px; background: white; color: var(--gray); font-weight: 500; cursor: pointer; transition: var(--transition); text-decoration: none; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .prop-pill.active { background: var(--primary); color: white; }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 20px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 20px; }
        .stat-icon { width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px; }
        .stat-info h3 { font-size: 14px; color: var(--gray); font-weight: 500; }
        .stat-info p { font-size: 24px; font-weight: 700; margin-top: 5px; }

        /* Billing Table */
        .section-card { background: white; border-radius: 20px; padding: 30px; box-shadow: var(--shadow); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 15px; color: var(--gray); font-size: 14px; font-weight: 600; border-bottom: 1px solid #f1f5f9; }
        .table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .status-badge { padding: 5px 12px; border-radius: 50px; font-size: 12px; font-weight: 600; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-unpaid { background: #fee2e2; color: #991b1b; }
        .status-partial { background: #fef3c7; color: #92400e; }

        .btn-pay { width: 30px; height: 30px; border-radius: 8px; border: 1px solid var(--primary); color: var(--primary); background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .btn-pay:hover { background: var(--primary); color: white; }

        /* Modal */
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 400px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 14px; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; }
        
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main">
        <div class="top-bar">
            <h1>Dashboard</h1>
            <div class="user-info" style="display: flex; gap: 15px; align-items: center;">
                <div style="text-align: right;">
                    <p style="font-weight: 600;"><?php echo $_SESSION['admin_name']; ?></p>
                    <p style="font-size: 12px; color: var(--gray);">Landlord</p>
                </div>
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    <?php echo substr($_SESSION['admin_name'], 0, 1); ?>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="prop-selector">
            <?php foreach ($properties as $p): ?>
                <a href="?property_id=<?php echo $p['id']; ?>" class="prop-pill <?php echo $p['id'] == $current_property_id ? 'active' : ''; ?>">
                    <?php echo $p['name']; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Monthly Utility Notice -->
        <?php if (date('j') <= 5): ?>
        <div style="background: var(--warning); color: white; padding: 15px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong><i class="fas fa-exclamation-triangle"></i> Utility Reading Phase</strong>
                <p style="font-size: 14px; opacity: 0.9;">It's the beginning of the month. Please enter water readings before generating major bills.</p>
            </div>
            <a href="billing.php?property_id=<?php echo $current_property_id; ?>&tab=readings" class="btn" style="background: white; color: var(--warning);">Enter Readings</a>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1); color: var(--primary);"><i class="fas fa-home"></i></div>
                <div class="stat-info">
                    <h3>Total Units</h3>
                    <p><?php echo $stats['total_units'] ?? 0; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(46, 196, 182, 0.1); color: var(--success);"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3>Occupied Units</h3>
                    <p><?php echo $stats['total_tenants'] ?? 0; ?></p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(231, 29, 54, 0.1); color: var(--danger);"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h3>Outstanding Dues</h3>
                    <p>KES <?php echo number_format($stats['total_pending'] ?? 0); ?></p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="margin-bottom: 30px;">
            <h2 style="font-size: 18px; margin-bottom: 20px;">Quick Actions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="tenants.php" class="btn prop-pill active" style="justify-content: center;"><i class="fas fa-user-plus"></i> Add New Tenant</a>
                <a href="notifications.php" class="btn prop-pill" style="background: white; border: 1px solid #ddd; justify-content: center;"><i class="fas fa-bullhorn"></i> Send Bulk Notice</a>
                <a href="settings.php" class="btn prop-pill" style="background: white; border: 1px solid #ddd; justify-content: center;"><i class="fas fa-tint"></i> Update Rates</a>
                <button class="btn prop-pill" style="background: white; border: 1px solid #ddd; justify-content: center;" onclick="location.href='generate_bills.php?property_id=<?php echo $current_property_id; ?>'"><i class="fas fa-sync"></i> Generate Bills</button>
            </div>
        </div>

        <div class="section-card">
            <div class="card-header">
                <h2>Billing Overview - <?php echo date('F Y'); ?></h2>
                <div style="display: flex; gap: 10px;">
                    <button class="btn prop-pill"><i class="fas fa-sms"></i> Send Notices</button>
                    <button class="btn prop-pill active" onclick="location.href='generate_bills.php?property_id=<?php echo $current_property_id; ?>'"><i class="fas fa-plus"></i> Generate Month's Bills</button>
                </div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>House</th>
                        <th>Tenant</th>
                        <th>Bill Type</th>
                        <th>Total</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($units_bills as $item): ?>
                        <tr>
                            <td><strong><?php echo $item['unit_number']; ?></strong></td>
                            <td><?php echo $item['tenant_name'] ?? '<span style="color: #cbd5e1;">Vacant</span>'; ?></td>
                            <td><?php echo $item['bill_type'] ?? '-'; ?></td>
                            <td>KES <?php echo number_format($item['amount'] ?? 0); ?></td>
                            <td style="color: <?php echo ($item['balance'] > 0) ? 'var(--danger)' : 'var(--success)'; ?>">
                                KES <?php echo number_format($item['balance'] ?? 0); ?>
                            </td>
                            <td>
                                <?php if ($item['status']): ?>
                                    <span class="status-badge status-<?php echo $item['status']; ?>">
                                        <?php echo ucfirst($item['status']); ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['status'] != 'paid' && $item['tenant_name']): ?>
                                    <button class="btn-pay" onclick="openPayModal('<?php echo $item['bill_id']; ?>', '<?php echo $item['unit_number']; ?>', '<?php echo $item['balance']; ?>')" title="Record Payment">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal" id="payModal">
        <div class="modal-content">
            <h2 style="margin-bottom: 20px;">Record Payment</h2>
            <form method="POST">
                <input type="hidden" name="bill_id" id="modal_bill_id">
                <div class="form-group">
                    <label>House</label>
                    <input type="text" id="modal_unit" class="form-control" readOnly>
                </div>
                <div class="form-group">
                    <label>Balance Due</label>
                    <input type="text" id="modal_balance" class="form-control" readOnly>
                </div>
                <div class="form-group">
                    <label>Amount Paid (KES)</label>
                    <input type="number" name="payment_amount" id="modal_pay_amount" class="form-control" required step="0.01">
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="mpesa">M-Pesa (Manual)</option>
                    </select>
                </div>
                <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="send_sms" id="modal_sms" value="1" checked>
                    <label for="modal_sms" style="margin-bottom: 0;">Send SMS Confirmation</label>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="button" class="btn prop-pill" style="flex: 1;" onclick="closePayModal()">Cancel</button>
                    <button type="submit" name="update_payment" class="btn prop-pill active" style="flex: 1;">Save Payment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPayModal(id, unit, balance) {
            document.getElementById('modal_bill_id').value = id;
            document.getElementById('modal_unit').value = unit;
            document.getElementById('modal_balance').value = 'KES ' + Number(balance).toLocaleString();
            document.getElementById('modal_pay_amount').value = balance;
            document.getElementById('payModal').style.display = 'flex';
        }

        function closePayModal() {
            document.getElementById('payModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == document.getElementById('payModal')) {
                closePayModal();
            }
        }
    </script>
</body>
</html>