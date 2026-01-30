<?php
require_once 'session_check.php';
require_once 'db_config.php';

// Check if user is logged in
requireLogin();

// Fetch properties to show in management
$landlord_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT * FROM properties WHERE landlord_id = ?");
$stmt->execute([$landlord_id]);
$properties = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - HomeSync</title>
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
        
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 28px; font-weight: 700; color: var(--dark); }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .settings-card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .settings-card:hover { transform: translateY(-5px); }
        
        .settings-card h3 { font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 10px; color: var(--primary); }
        .settings-card p { font-size: 14px; color: var(--gray); line-height: 1.5; }

        .btn {
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }

        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #3a0ca3; }
        
        .btn-outline { background: transparent; color: var(--primary); border: 1px solid var(--primary); }
        .btn-outline:hover { background: var(--primary); color: white; }

        .property-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: var(--light);
            border-radius: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main">
            <div class="page-header">
                <h1>Settings & Management</h1>
            </div>

            <div class="settings-grid">
                <!-- Property Management -->
                <div class="settings-card">
                    <h3><i class="fas fa-building"></i> My Properties</h3>
                    <p>Manage your apartment complexes, floor counts, and basic information.</p>
                    <div class="property-list">
                        <?php if (count($properties) > 0): ?>
                            <?php foreach ($properties as $p): ?>
                                <div class="property-item">
                                    <span><strong><?php echo htmlspecialchars($p['name']); ?></strong></span>
                                    <small><?php echo htmlspecialchars($p['location']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="font-style: italic;">No properties found.</p>
                        <?php endif; ?>
                    </div>
                    <a href="onboarding.html" class="btn btn-outline" style="margin-top: 10px;"><i class="fas fa-plus"></i> Add New Property</a>
                </div>

                <!-- Rate Management -->
                <div class="settings-card">
                    <h3><i class="fas fa-tint"></i> Utility Rates</h3>
                    <p>Update water, wifi, and garbage rates. Changes can be automatically broadcast to tenants.</p>
                    <form action="update_rates.php" method="POST">
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <label style="font-size: 12px; font-weight: 600;">Default Water Rate (KES)</label>
                            <input type="number" name="water_rate" class="form-control" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ddd;" placeholder="200">
                            
                            <div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                                <input type="checkbox" name="notify_tenants" id="notify">
                                <label for="notify" style="font-size: 12px;">Notify all tenants via SMS</label>
                            </div>
                            <button type="submit" class="btn btn-primary" style="margin-top: 5px;">Update & Notify</button>
                        </div>
                    </form>
                </div>

                <!-- Security Management -->
                <div class="settings-card">
                    <h3><i class="fas fa-shield-alt"></i> Security & Access</h3>
                    <p>Manage security tokens for the gate portal and visitor logging system.</p>
                    <div style="margin-top: auto;">
                        <a href="gate_links.php" class="btn btn-primary" style="width: 100%;">Manage Gate Links</a>
                    </div>
                </div>

                <!-- Contractor Management -->
                <div class="settings-card">
                    <h3><i class="fas fa-tools"></i> Service Providers</h3>
                    <p>Keep track of plumbers, electricians, and other contractors for your properties.</p>
                    <div style="margin-top: auto;">
                        <a href="contractors.php" class="btn btn-primary" style="width: 100%;">View Contractors</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
