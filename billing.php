<?php
session_start();
require_once 'db_config.php';

// Check if landlord is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.html");
    exit();
}

$landlord_id = $_SESSION['admin_id'];

// Fetch Properties for filtering
$stmt = $pdo->prepare("SELECT * FROM properties WHERE landlord_id = ?");
$stmt->execute([$landlord_id]);
$properties = $stmt->fetchAll();

$current_property_id = $_GET['property_id'] ?? ($properties[0]['id'] ?? null);

// Fetch Bills for the selected property
$bills = [];
$property_tenants = [];
if ($current_property_id) {
    // Bills
    $stmt = $pdo->prepare("
        SELECT b.*, t.name as tenant_name, u.unit_number 
        FROM bills b 
        JOIN units u ON b.unit_id = u.id 
        JOIN tenants t ON b.tenant_id = t.id 
        WHERE u.property_id = ? 
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$current_property_id]);
    $bills = $stmt->fetchAll();

    // Tenants for the modal
    $stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE property_id = ? AND status = 'active'");
    $stmt->execute([$current_property_id]);
    $property_tenants = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detailed Billing - HomeSync</title>
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

        .app-container { display: flex; width: 100%; }
        .main { flex: 1; padding: 30px; overflow-y: auto; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: var(--dark); }

        .filter-bar { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; align-items: center; }
        .prop-select { padding: 10px 15px; border-radius: 10px; border: 1px solid #ddd; background: white; font-weight: 500; }

        .billing-card { background: white; border-radius: 20px; padding: 25px; box-shadow: var(--shadow); }
        
        .math-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
            padding: 20px;
            background: var(--light);
            border-radius: 15px;
        }
        .math-item h4 { font-size: 12px; color: var(--gray); text-transform: uppercase; letter-spacing: 1px; }
        .math-item p { font-size: 20px; font-weight: 700; margin-top: 5px; }

        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 15px; color: var(--gray); font-size: 14px; font-weight: 600; border-bottom: 1px solid #f1f5f9; }
        .table td { padding: 15px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        
        .status-badge { padding: 5px 12px; border-radius: 50px; font-size: 12px; font-weight: 600; }
        .status-paid { background: #dcfce7; color: #166534; }
        .status-unpaid { background: #fee2e2; color: #991b1b; }
        .status-partial { background: #fef3c7; color: #92400e; }

        .btn { padding: 10px 15px; border-radius: 8px; cursor: pointer; transition: var(--transition); border: none; font-weight: 600; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }

        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 450px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main">
            <div class="page-header">
                <h1>Detailed Billing</h1>
                <button class="btn btn-primary" onclick="openCustomBillModal()"><i class="fas fa-plus"></i> Create Custom Bill</button>
            </div>

            <div class="filter-bar">
                <label>Property:</label>
                <select class="prop-select" onchange="location.href='?property_id='+this.value">
                    <?php foreach ($properties as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $p['id'] == $current_property_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-sm" onclick="location.href='generate_bills.php?property_id=<?php echo $current_property_id; ?>'"><i class="fas fa-sync"></i> Generate Rent Bills</button>
            </div>

            <?php
            $total_billed = 0;
            $total_paid = 0;
            $total_pending = 0;
            foreach ($bills as $b) {
                $total_billed += $b['amount'];
                $total_pending += $b['balance'];
                $total_paid += ($b['amount'] - $b['balance']);
            }
            ?>

            <div class="billing-card">
                <div class="math-summary">
                    <div class="math-item">
                        <h4>Total Billed</h4>
                        <p>KES <?php echo number_format($total_billed); ?></p>
                    </div>
                    <div class="math-item">
                        <h4>Total Received</h4>
                        <p class="status-paid" style="background:transparent;">KES <?php echo number_format($total_paid); ?></p>
                    </div>
                    <div class="math-item">
                        <h4>Total Outstanding</h4>
                        <p class="status-unpaid" style="background:transparent;">KES <?php echo number_format($total_pending); ?></p>
                    </div>
                    <div class="math-item">
                        <h4>Collection Rate</h4>
                        <p><?php echo $total_billed > 0 ? round(($total_paid / $total_billed) * 100, 1) : 0; ?>%</p>
                    </div>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Tenant / House</th>
                            <th>Bill Type</th>
                            <th>Month</th>
                            <th>Total</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bills) > 0): ?>
                            <?php foreach ($bills as $b): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($b['tenant_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($b['unit_number']); ?></small>
                                    </td>
                                    <td><span style="text-transform: capitalize;"><?php echo $b['bill_type']; ?></span></td>
                                    <td><?php echo $b['month'] . ' ' . $b['year']; ?></td>
                                    <td>KES <?php echo number_format($b['amount']); ?></td>
                                    <td style="font-weight: 600; color: <?php echo $b['balance'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>">
                                        KES <?php echo number_format($b['balance']); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $b['status']; ?>">
                                            <?php echo ucfirst($b['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="openManualAdjustModal(<?php echo $b['id']; ?>, '<?php echo $b['tenant_name']; ?>', <?php echo $b['balance']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px; color: var(--gray);">No bills found for this property.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal" id="customBillModal">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 20px; width: 450px; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
            <h3>Create Custom Bill</h3>
            <form action="save_bill.php" method="POST" style="margin-top: 20px;">
                <div class="form-group">
                    <label>Select Tenant</label>
                    <select name="tenant_id" class="form-control" required>
                        <?php foreach ($property_tenants as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bill Type</label>
                    <select name="bill_type" class="form-control">
                        <option value="water">Water</option>
                        <option value="wifi">WiFi</option>
                        <option value="garbage">Garbage</option>
                        <option value="penalty">Penalty</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount (KES)</label>
                    <input type="number" name="amount" class="form-control" required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="button" class="btn btn-outline" style="flex: 1; background:#f8f9fa; border:1px solid #ddd;" onclick="closeModal('customBillModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Create Bill</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        function openCustomBillModal() { 
            document.getElementById('customBillModal').style.display = 'flex'; 
        }
        function openManualAdjustModal(id, name, balance) {
            alert("Adjusting bill #" + id + " for " + name + ". Manual adjustment logic coming soon.");
        }
    </script>
</body>
</html>
