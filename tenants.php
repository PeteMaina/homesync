
<?php
require_once 'session_check.php';
require_once 'db_config.php';

// Check if user is logged in
requireLogin();

// Fetch all tenants with their property and unit details
$tenants = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, p.name as property_name, u.unit_number, u.rent_amount AS unit_rent
        FROM tenants t 
        JOIN properties p ON t.property_id = p.id 
        JOIN units u ON t.unit_id = u.id 
        WHERE p.landlord_id = ? 
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$_SESSION['admin_id']]);
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching tenants: " . $e->getMessage();
}

// Fetch properties and units for the modal
$stmt = $pdo->prepare("SELECT id, name FROM properties WHERE landlord_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$available_properties = $stmt->fetchAll();

// For simplicity, we'll fetch all units. In a real app, this would be updated via JS based on selected property.
$stmt = $pdo->prepare("SELECT u.id, u.unit_number, u.property_id FROM units u JOIN properties p ON u.property_id = p.id WHERE p.landlord_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$available_units = $stmt->fetchAll();

// Handle form submission for editing tenant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_tenant'])) {
    $tenant_id = $_POST['edit_tenant_id'];
    $name = $_POST['edit_name'];
    $id_number = $_POST['edit_id_number'];
    $phone_number = $_POST['edit_phone_number'];
    $has_wifi = isset($_POST['edit_has_wifi']) ? 1 : 0;
    $has_garbage = isset($_POST['edit_has_garbage']) ? 1 : 0;
    $initial_water_reading = $_POST['edit_initial_water_reading'] ?? 0;
    $initial_electricity_reading = $_POST['edit_initial_electricity_reading'] ?? 0;
    $edit_rent_amount = isset($_POST['edit_rent_amount']) && $_POST['edit_rent_amount'] !== '' ? floatval($_POST['edit_rent_amount']) : null;

    try {
        // First verify this tenant belongs to the current landlord
        $stmt = $pdo->prepare("
            SELECT t.id FROM tenants t
            JOIN properties p ON t.property_id = p.id
            WHERE t.id = ? AND p.landlord_id = ?
        ");
        $stmt->execute([$tenant_id, $_SESSION['admin_id']]);
        if (!$stmt->fetch()) {
            $error = "Access denied: Tenant not found or doesn't belong to you.";
        } else {
            // Check if ID number is already used by another tenant
            $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id_number = ? AND id != ?");
            $stmt->execute([$id_number, $tenant_id]);
            if ($stmt->fetch()) {
                $error = "A tenant with this ID number already exists.";
            } else {
                // Alter table to add initial readings columns if they don't exist
                $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS initial_water_reading DECIMAL(10,2) DEFAULT 0");
                $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS initial_electricity_reading DECIMAL(10,2) DEFAULT 0");
                $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS rent_amount DECIMAL(10,2) NULL");

                // Update tenant
                $stmt = $pdo->prepare("UPDATE tenants SET name = ?, id_number = ?, phone_number = ?, has_wifi = ?, has_garbage = ?, initial_water_reading = ?, initial_electricity_reading = ?, rent_amount = ? WHERE id = ?");
                $stmt->execute([$name, $id_number, $phone_number, $has_wifi, $has_garbage, $initial_water_reading, $initial_electricity_reading, $edit_rent_amount, $tenant_id]);

                $success = "Tenant updated successfully!";

                // Refresh the tenants list
                $stmt = $pdo->prepare("
                    SELECT t.*, p.name as property_name, u.unit_number, u.rent_amount AS unit_rent
                    FROM tenants t
                    JOIN properties p ON t.property_id = p.id
                    JOIN units u ON t.unit_id = u.id
                    WHERE p.landlord_id = ?
                    ORDER BY t.created_at DESC
                ");
                $stmt->execute([$_SESSION['admin_id']]);
                $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        $error = "Error updating tenant: " . $e->getMessage();
    }
}

// Handle tenant deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tenant'])) {
    $tenant_id = $_POST['delete_tenant_id'];

    try {
        // First verify this tenant belongs to the current landlord
        $stmt = $pdo->prepare("
            SELECT t.id FROM tenants t
            JOIN properties p ON t.property_id = p.id
            WHERE t.id = ? AND p.landlord_id = ?
        ");
        $stmt->execute([$tenant_id, $_SESSION['admin_id']]);
        if (!$stmt->fetch()) {
            $error = "Access denied: Tenant not found or doesn't belong to you.";
        } else {
            // Check if tenant has any unpaid bills
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE tenant_id = ? AND balance > 0");
            $stmt->execute([$tenant_id]);
            $unpaid_bills = $stmt->fetchColumn();

            if ($unpaid_bills > 0) {
                $error = "Cannot delete tenant with unpaid bills. Please settle all outstanding balances first.";
            } else {
                // Delete tenant (cascade will handle related records)
                $stmt = $pdo->prepare("DELETE FROM tenants WHERE id = ?");
                $stmt->execute([$tenant_id]);

                $success = "Tenant deleted successfully!";

                // Refresh the tenants list
                $stmt = $pdo->prepare("
                    SELECT t.*, p.name as property_name, u.unit_number, u.rent_amount AS unit_rent
                    FROM tenants t
                    JOIN properties p ON t.property_id = p.id
                    JOIN units u ON t.unit_id = u.id
                    WHERE p.landlord_id = ?
                    ORDER BY t.created_at DESC
                ");
                $stmt->execute([$_SESSION['admin_id']]);
                $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        $error = "Error deleting tenant: " . $e->getMessage();
    }
}

// Handle form submission for adding new tenant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tenant'])) {
    $name = $_POST['name'];
    $id_number = $_POST['id_number'];
    $property_id = $_POST['property_id'];
    $unit_id = $_POST['unit_id'];
    $phone_number = $_POST['phone_number'];
    $move_in_date = $_POST['move_in_date'];
    $has_wifi = isset($_POST['has_wifi']) ? 1 : 0;
    $has_garbage = isset($_POST['has_garbage']) ? 1 : 0;
    $rent_amount = isset($_POST['rent_amount']) && $_POST['rent_amount'] !== '' ? floatval($_POST['rent_amount']) : null;
    
    // Handle file upload (ID picture) with security validation
    $id_picture = null;
    if (isset($_FILES['id_picture']) && $_FILES['id_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/id_pictures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true); // Changed from 0777 to 0755 for security
        }
        
        // Security validations
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['id_picture']['type'];
        $file_size = $_FILES['id_picture']['size'];
        $file_extension = strtolower(pathinfo($_FILES['id_picture']['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $error = "Invalid file type. Only JPG, PNG, and GIF images are allowed.";
        }
        // Validate extension
        elseif (!in_array($file_extension, $allowed_extensions)) {
            $error = "Invalid file extension. Only jpg, jpeg, png, and gif are allowed.";
        }
        // Validate file size
        elseif ($file_size > $max_file_size) {
            $error = "File too large. Maximum size is 5MB.";
        }
        // All validations passed
        else {
            // Generate secure filename
            $id_picture = $upload_dir . uniqid('id_', true) . '.' . $file_extension;
            
            if (!move_uploaded_file($_FILES['id_picture']['tmp_name'], $id_picture)) {
                $error = "Failed to upload file. Please try again.";
                $id_picture = null;
            }
        }
    }
    
    try {
        // Check if tenant already exists
        $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id_number = ?");
        $stmt->execute([$id_number]);
        if ($stmt->fetch()) {
            $error = "A tenant with this ID number already exists.";
        } else {
            // Alter table to add id_picture and initial readings columns if they don't exist
            $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS id_picture VARCHAR(255) NULL");
            $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS initial_water_reading DECIMAL(10,2) DEFAULT 0");
            $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS initial_electricity_reading DECIMAL(10,2) DEFAULT 0");
            $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS rent_amount DECIMAL(10,2) NULL");

            // Get initial readings from form
            $initial_water_reading = $_POST['initial_water_reading'] ?? 0;
            $initial_electricity_reading = $_POST['initial_electricity_reading'] ?? 0;

            // Insert new tenant
            $stmt = $pdo->prepare("INSERT INTO tenants (property_id, unit_id, name, id_number, phone_number, move_in_date, id_picture, has_wifi, has_garbage, initial_water_reading, initial_electricity_reading, rent_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$property_id, $unit_id, $name, $id_number, $phone_number, $move_in_date, $id_picture, $has_wifi, $has_garbage, $initial_water_reading, $initial_electricity_reading, $rent_amount]);
            
            $success = "Tenant added successfully!";
            
            // Refresh the tenants list
            $stmt = $pdo->prepare("
                SELECT t.*, p.name as property_name, u.unit_number 
                FROM tenants t 
                JOIN properties p ON t.property_id = p.id 
                JOIN units u ON t.unit_id = u.id 
                WHERE p.landlord_id = ? 
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([$_SESSION['admin_id']]);
            $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = "Error adding tenant: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenant Management - Homesync</title>
    <link rel="shortcut icon" href="icons/home.png" type="image/x-icon">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a0ca3;
            --secondary: #4cc9f0;
            --success: #2ec4b6;
            --warning: #ff9f1c;
            --danger: #e71d36;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
        }
        
        .page-actions {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .bg-primary-light {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        .bg-success-light {
            background: rgba(46, 196, 182, 0.1);
            color: var(--success);
        }
        
        .bg-warning-light {
            background: rgba(255, 159, 28, 0.1);
            color: var(--warning);
        }
        
        .card-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .card-footer {
            font-size: 14px;
            color: var(--gray);
        }
        
        .positive {
            color: var(--success);
        }
        
        .negative {
            color: var(--danger);
        }
        
        /* Main Tenant Card */
        .tenant-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: var(--card-shadow);
            margin-bottom: 32px;
        }
        
        .card-header-lg {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .card-title-lg {
            font-size: 20px;
            font-weight: 700;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--light-gray);
        }
        
        .tenant-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .tenant-table th {
            background: var(--light);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
        }
        
        .tenant-table td {
            padding: 16px;
            border-top: 1px solid var(--light-gray);
        }
        
        .tenant-table tr:hover {
            background: rgba(67, 97, 238, 0.03);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-occupied {
            background: rgba(46, 196, 182, 0.1);
            color: var(--success);
        }
        
        .status-vacant {
            background: rgba(255, 159, 28, 0.1);
            color: var(--warning);
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }
        
        .btn-view {
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        .btn-view:hover {
            background: rgba(67, 97, 238, 0.2);
        }
        
        .btn-edit {
            background: rgba(255, 159, 28, 0.1);
            color: var(--warning);
        }
        
        .btn-edit:hover {
            background: rgba(255, 159, 28, 0.2);
        }
        
        .btn-delete {
            background: rgba(231, 29, 54, 0.1);
            color: var(--danger);
        }
        
        .btn-delete:hover {
            background: rgba(231, 29, 54, 0.2);
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
            display: none;
        }
        
        .modal {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 700;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .modal-footer {
            padding: 24px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        /* Notification Styles */
        .notification {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .notification-success {
            background: rgba(46, 196, 182, 0.1);
            color: var(--success);
            border: 1px solid rgba(46, 196, 182, 0.2);
        }
        
        .notification-error {
            background: rgba(231, 29, 54, 0.1);
            color: var(--danger);
            border: 1px solid rgba(231, 29, 54, 0.2);
        }
        
        .notification-close {
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
        }
        
        /* Responsive Styles */
        @media (max-width: 1024px) {
            .app-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 16px;
            }
            
            .sidebar-header {
                padding: 0 0 16px;
            }
            
            .nav-items {
                display: flex;
                overflow-x: auto;
                gap: 8px;
                margin-bottom: 16px;
            }
            
            .nav-item {
                border-left: none;
                border-bottom: 3px solid transparent;
                padding: 12px 16px;
                white-space: nowrap;
            }
            
            .nav-item:hover, .nav-item.active {
                border-left-color: transparent;
                border-bottom-color: var(--primary);
            }
            
            .sidebar-footer {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
        }
        
        /* Utility Classes */
        .text-center {
            text-align: center;
        }
        
        .text-right {
            text-align: right;
        }
        
        .mb-0 {
            margin-bottom: 0;
        }
        
        .mt-24 {
            margin-top: 24px;
        }
        
        .d-none {
            display: none;
        }
        
        .d-flex {
            display: flex;
        }
        
        .align-center {
            align-items: center;
        }
        
        .justify-between {
            justify-content: space-between;
        }
        
        /* ID Picture Styles */
        .id-picture {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid var(--light-gray);
        }
        
        .id-picture-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Tenant Management</h1>
                <div class="page-actions">
                    <button class="btn btn-outline">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button class="btn btn-primary" id="addTenantBtn">
                        <i class="fas fa-plus"></i> Add Tenant
                    </button>
                </div>
            </div>
            
            <!-- Notifications -->
            <?php if (isset($success)): ?>
                <div class="notification notification-success">
                    <span><?php echo $success; ?></span>
                    <button class="notification-close">&times;</button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="notification notification-error">
                    <span><?php echo $error; ?></span>
                    <button class="notification-close">&times;</button>
                </div>
            <?php endif; ?>
            
            <!-- Dashboard Cards -->
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Total Tenants</h3>
                        <div class="card-icon bg-primary-light">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="card-value"><?php echo count($tenants); ?></div>
                    <div class="card-footer">
                        <span class="positive"><i class="fas fa-arrow-up"></i> 5.2%</span> from last month
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Occupied Houses</h3>
                        <div class="card-icon bg-success-light">
                            <i class="fas fa-home"></i>
                        </div>
                    </div>
                    <div class="card-value"><?php echo count($tenants); ?></div>
                    <div class="card-footer">
                        <?php echo 20 - count($tenants); ?> houses vacant
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">New This Month</h3>
                        <div class="card-icon bg-warning-light">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                    <div class="card-value">3</div>
                    <div class="card-footer">
                        <span class="positive"><i class="fas fa-arrow-up"></i> 15%</span> from last month
                    </div>
                </div>
            </div>
            
            <!-- Tenant List Card -->
            <div class="tenant-card">
                <div class="card-header-lg">
                    <h2 class="card-title-lg">All Tenants</h2>
                    <div class="search-box">
                        <input type="text" class="form-control" placeholder="Search tenants..." style="width: 250px;">
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="tenant-table">
                        <thead>
                            <tr>
                                <th>ID Picture</th>
                                <th>Name</th>
                                <th>ID Number</th>
                                <th>House Number</th>
                                <th>Rent</th>
                                <th>Phone Number</th>
                                <th>Services</th>
                                <th>Move-in Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($tenants) > 0): ?>
                                <?php foreach ($tenants as $tenant): ?>
                                    <tr>
                                        <td>
                                            <?php if (!empty($tenant['id_picture'])): ?>
                                                <img src="<?php echo $tenant['id_picture']; ?>" alt="ID Picture" class="id-picture">
                                            <?php else: ?>
                                                <div class="id-picture-placeholder">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($tenant['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($tenant['id_number']); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($tenant['unit_number']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($tenant['property_name']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                                $displayRent = null;
                                                if (!empty($tenant['rent_amount']) && (float)$tenant['rent_amount'] > 0) {
                                                    $displayRent = (float)$tenant['rent_amount'];
                                                } elseif (isset($tenant['unit_rent'])) {
                                                    $displayRent = (float)$tenant['unit_rent'];
                                                }
                                                echo $displayRent !== null ? 'KES ' . number_format($displayRent, 2) : '—';
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($tenant['phone_number']); ?></td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <i class="fas fa-wifi" style="color: <?php echo $tenant['has_wifi'] ? 'var(--primary)' : '#ccc'; ?>;" title="WiFi"></i>
                                                <i class="fas fa-trash" style="color: <?php echo $tenant['has_garbage'] ? 'var(--primary)' : '#ccc'; ?>;" title="Garbage"></i>
                                            </div>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($tenant['move_in_date'])); ?></td>
                                        <td><span class="status-badge status-occupied">Occupied</span></td>
                                        <td>
                                            <button class="action-btn btn-edit" onclick="openEditModal(<?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['name']); ?>', '<?php echo htmlspecialchars($tenant['id_number']); ?>', '<?php echo htmlspecialchars($tenant['phone_number']); ?>', '<?php echo htmlspecialchars($tenant['unit_number']); ?>', '<?php echo htmlspecialchars($tenant['property_name']); ?>', '<?php echo $tenant['has_wifi']; ?>', '<?php echo $tenant['has_garbage']; ?>', '<?php echo $tenant['initial_water_reading']; ?>', '<?php echo $tenant['initial_electricity_reading']; ?>', '<?php echo $tenant['rent_amount'] ?? '' ; ?>')"><i class="fas fa-edit"></i></button>
                                            <button class="action-btn btn-delete" onclick="confirmDelete(<?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['name']); ?>')"><i class="fas fa-trash"></i></button>
                                            <form method="POST" action="agreement_generate.php" style="display:inline">
                                                <input type="hidden" name="tenant_id" value="<?php echo $tenant['id']; ?>">
                                                <button class="action-btn btn-edit" title="Generate Agreement" style="background: #10b981; color: #fff"><i class="fas fa-file-signature"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center">No tenants found. Add your first tenant using the button above.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-center mt-24">
                    <button class="btn btn-outline">Load More</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Tenant Modal -->
    <div class="modal-overlay" id="tenantModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Add New Tenant</h2>
                <button class="modal-close" id="closeModal">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="name" required placeholder="e.g. John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ID Number</label>
                        <input type="text" class="form-control" name="id_number" required placeholder="e.g. 12345678">
                    </div>
                    
                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Property</label>
                            <select class="form-control" name="property_id" id="propertySelect" required>
                                <option value="">Select Property</option>
                                <?php foreach ($available_properties as $p): ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Unit / House</label>
                            <select class="form-control" name="unit_id" id="unitSelect" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($available_units as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" data-property="<?php echo $u['property_id']; ?>">
                                        <?php echo htmlspecialchars($u['unit_number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone_number" required placeholder="e.g. 0712345678">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Monthly Rent (KES)</label>
                        <input type="number" step="0.01" class="form-control" name="rent_amount" placeholder="Leave blank to use unit default">
                    </div>

                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                            <input type="checkbox" name="has_wifi" style="width: 18px; height: 18px;"> Enable WiFi billing
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                            <input type="checkbox" name="has_garbage" style="width: 18px; height: 18px;"> Enable Garbage billing
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Move-in Date</label>
                        <input type="date" class="form-control" name="move_in_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ID Picture (Optional)</label>
                        <input type="file" class="form-control" name="id_picture" accept="image/*">
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Initial Water Reading</label>
                            <input type="number" step="0.01" class="form-control" name="initial_water_reading" placeholder="e.g. 123.45" value="0">
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Initial Electricity Reading</label>
                            <input type="number" step="0.01" class="form-control" name="initial_electricity_reading" placeholder="e.g. 456.78" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancelTenant">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="add_tenant">Add Tenant</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Tenant Modal -->
    <div class="modal-overlay" id="editTenantModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Edit Tenant</h2>
                <button class="modal-close" id="closeEditModal">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="edit_tenant_id" id="editTenantId">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="edit_name" id="editName" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ID Number</label>
                        <input type="text" class="form-control" name="edit_id_number" id="editIdNumber" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="edit_phone_number" id="editPhoneNumber" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Monthly Rent (KES)</label>
                        <input type="number" step="0.01" class="form-control" name="edit_rent_amount" id="editRentAmount" placeholder="Leave blank to use unit default">
                    </div>

                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                            <input type="checkbox" name="edit_has_wifi" id="editHasWifi" style="width: 18px; height: 18px;"> Enable WiFi billing
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer;">
                            <input type="checkbox" name="edit_has_garbage" id="editHasGarbage" style="width: 18px; height: 18px;"> Enable Garbage billing
                        </label>
                    </div>

                    <div style="display: flex; gap: 15px;">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Initial Water Reading</label>
                            <input type="number" step="0.01" class="form-control" name="edit_initial_water_reading" id="editInitialWaterReading">
                        </div>

                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Initial Electricity Reading</label>
                            <input type="number" step="0.01" class="form-control" name="edit_initial_electricity_reading" id="editInitialElectricityReading">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancelEditTenant">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="edit_tenant">Update Tenant</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Confirm Deletion</h2>
                <button class="modal-close" id="closeDeleteModal">&times;</button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: var(--danger); margin-bottom: 16px;"></i>
                    <h3 style="color: var(--danger); margin-bottom: 16px;">Are you sure you want to delete this tenant?</h3>
                </div>
                <div style="background: var(--light); padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <p style="margin-bottom: 12px;"><strong>Tenant:</strong> <span id="deleteTenantName"></span></p>
                    <p style="color: var(--danger); font-weight: 600; margin-bottom: 12px;">⚠️ This action cannot be undone!</p>
                    <p style="font-size: 14px; color: var(--gray);">
                        Deleting this tenant will permanently remove all associated data including:
                    </p>
                    <ul style="font-size: 14px; color: var(--gray); margin-top: 8px;">
                        <li>Tenant profile and contact information</li>
                        <li>All billing history and records</li>
                        <li>Associated visitor logs</li>
                        <li>Payment records</li>
                    </ul>
                </div>
                <p style="font-size: 14px; color: var(--gray); text-align: center;">
                    If this tenant has outstanding bills, deletion will be blocked. Please settle all balances first.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelDelete">Cancel</button>
                <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">Yes, Delete Tenant</button>
            </div>
        </div>
    </div>

    <script>
        // Modal functionality
        const addTenantBtn = document.getElementById('addTenantBtn');
        const tenantModal = document.getElementById('tenantModal');
        const editTenantModal = document.getElementById('editTenantModal');
        const deleteModal = document.getElementById('deleteModal');
        const closeModal = document.getElementById('closeModal');
        const closeEditModal = document.getElementById('closeEditModal');
        const closeDeleteModal = document.getElementById('closeDeleteModal');
        const cancelTenant = document.getElementById('cancelTenant');
        const cancelEditTenant = document.getElementById('cancelEditTenant');
        const cancelDelete = document.getElementById('cancelDelete');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

        addTenantBtn.addEventListener('click', () => {
            tenantModal.style.display = 'flex';
        });

        closeModal.addEventListener('click', () => {
            tenantModal.style.display = 'none';
        });

        cancelTenant.addEventListener('click', () => {
            tenantModal.style.display = 'none';
        });

        closeEditModal.addEventListener('click', () => {
            editTenantModal.style.display = 'none';
        });

        cancelEditTenant.addEventListener('click', () => {
            editTenantModal.style.display = 'none';
        });

        closeDeleteModal.addEventListener('click', () => {
            deleteModal.style.display = 'none';
        });

        cancelDelete.addEventListener('click', () => {
            deleteModal.style.display = 'none';
        });

        // Edit tenant modal functionality
        function openEditModal(tenantId, name, idNumber, phoneNumber, unitNumber, propertyName, hasWifi, hasGarbage, initialWaterReading, initialElectricityReading, rentAmount) {
            document.getElementById('editTenantId').value = tenantId;
            document.getElementById('editName').value = name;
            document.getElementById('editIdNumber').value = idNumber;
            document.getElementById('editPhoneNumber').value = phoneNumber;
            document.getElementById('editHasWifi').checked = hasWifi == '1';
            document.getElementById('editHasGarbage').checked = hasGarbage == '1';
            document.getElementById('editInitialWaterReading').value = initialWaterReading || 0;
            document.getElementById('editInitialElectricityReading').value = initialElectricityReading || 0;
            document.getElementById('editRentAmount').value = (rentAmount && rentAmount !== 'null') ? rentAmount : '';
            editTenantModal.style.display = 'flex';
        }

        // Delete confirmation modal functionality
        function confirmDelete(tenantId, tenantName) {
            document.getElementById('deleteTenantName').textContent = tenantName;
            deleteModal.style.display = 'flex';

            // Handle delete confirmation
            confirmDeleteBtn.onclick = function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';

                const tenantIdInput = document.createElement('input');
                tenantIdInput.type = 'hidden';
                tenantIdInput.name = 'delete_tenant_id';
                tenantIdInput.value = tenantId;
                form.appendChild(tenantIdInput);

                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'delete_tenant';
                submitInput.value = '1';
                form.appendChild(submitInput);

                document.body.appendChild(form);
                form.submit();
            };
        }

        // Dynamic unit filtering
        const propertySelect = document.getElementById('propertySelect');
        const unitSelect = document.getElementById('unitSelect');
        const unitOptions = Array.from(unitSelect.options).slice(1); // Exclude first "Select Unit"

        propertySelect.addEventListener('change', function() {
            const propertyId = this.value;
            unitSelect.innerHTML = '<option value="">Select Unit</option>';

            unitOptions.forEach(opt => {
                if (opt.dataset.property === propertyId) {
                    unitSelect.appendChild(opt);
                }
            });
        });

        // Close modal when clicking outside
        tenantModal.addEventListener('click', (e) => {
            if (e.target === tenantModal) {
                tenantModal.style.display = 'none';
            }
        });

        editTenantModal.addEventListener('click', (e) => {
            if (e.target === editTenantModal) {
                editTenantModal.style.display = 'none';
            }
        });

        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) {
                deleteModal.style.display = 'none';
            }
        });

        // Close notifications
        document.querySelectorAll('.notification-close').forEach(button => {
            button.addEventListener('click', (e) => {
                e.target.closest('.notification').style.display = 'none';
            });
        });
    </script>
</body>
</html>