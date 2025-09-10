
<?php
session_start();
require_once 'db_config.php';

// Check if user is logged in
/*if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}*/

// Fetch all tenants from the database
$tenants = [];
try {
    $stmt = $pdo->query("SELECT * FROM tenants ORDER BY created_at DESC");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching tenants: " . $e->getMessage();
}

// Handle form submission for adding new tenant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tenant'])) {
    $id_number = $_POST['id_number'];
    $house_number = $_POST['house_number'];
    $phone_number = $_POST['phone_number'];
    $rented_month = $_POST['rented_month'];
    $rented_year = $_POST['rented_year'];
    
    // Handle file upload (ID picture)
    $id_picture = null;
    if (isset($_FILES['id_picture']) && $_FILES['id_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/id_pictures/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['id_picture']['name'], PATHINFO_EXTENSION);
        $id_picture = $upload_dir . uniqid() . '.' . $file_extension;
        
        move_uploaded_file($_FILES['id_picture']['tmp_name'], $id_picture);
    }
    
    try {
        // Check if tenant already exists
        $stmt = $pdo->prepare("SELECT id FROM tenants WHERE id_number = ?");
        $stmt->execute([$id_number]);
        if ($stmt->fetch()) {
            $error = "A tenant with this ID number already exists.";
        } else {
            // Alter table to add id_picture column if it doesn't exist
            $pdo->exec("ALTER TABLE tenants ADD COLUMN IF NOT EXISTS id_picture VARCHAR(255) NULL");
            
            // Insert new tenant
            $stmt = $pdo->prepare("INSERT INTO tenants (id_number, house_number, phone_number, rented_month, rented_year, id_picture) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id_number, $house_number, $phone_number, $rented_month, $rented_year, $id_picture]);
            
            $success = "Tenant added successfully!";
            
            // Refresh the tenants list
            $stmt = $pdo->query("SELECT * FROM tenants ORDER BY created_at DESC");
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
        
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 24px 0;
            display: flex;
            flex-direction: column;
            box-shadow: var(--card-shadow);
            z-index: 100;
        }
        
        .sidebar-header {
            padding: 0 24px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 24px;
        }
        
        .sidebar-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 14px 24px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            border-left: 4px solid transparent;
        }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-left-color: var(--primary);
        }
        
        .nav-item i {
            margin-right: 12px;
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        
        .sidebar-footer {
            margin-top: auto;
            padding: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .user-info h4 {
            font-size: 14px;
            font-weight: 600;
        }
        
        .user-info p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
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
        <div class="sidebar">
            <div class="sidebar-header">
                <h1><i class="fas fa-home"></i> HomeSync</h1>
                <p>Property Management System</p>
            </div>
            
            <div class="nav-items">
                <a href="billing.php" class="nav-item">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Billing</span>
                </a>
                <a href="tenants.html" class="nav-item active">
                    <i class="fas fa-users"></i>
                    <span>Tenants</span>
                </a>
                <a href="concerns.php" class="nav-item">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Concerns</span>
                </a>
                <a href="visitors.php" class="nav-item">
                    <i class="fas fa-user-friends"></i>
                    <span>Visitors</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            
            <div class="sidebar-footer">
                <div class="user-profile">
                    <div class="user-avatar"><?php echo substr($_SESSION['admin_name'] ?? 'A', 0, 1); ?></div>
                    <div class="user-info">
                        <h4><?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></h4>
                        <p>Landlord</p>
                    </div>
                </div>
            </div>
        </div>
        
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
                                <th>Phone Number</th>
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
                                        <td>Tenant <?php echo $tenant['id']; ?></td>
                                        <td><?php echo htmlspecialchars($tenant['id_number']); ?></td>
                                        <td><?php echo htmlspecialchars($tenant['house_number']); ?></td>
                                        <td><?php echo htmlspecialchars($tenant['phone_number']); ?></td>
                                        <td><?php echo htmlspecialchars($tenant['rented_month'] . ' ' . $tenant['rented_year']); ?></td>
                                        <td><span class="status-badge status-occupied">Occupied</span></td>
                                        <td>
                                            <button class="action-btn btn-view"><i class="fas fa-eye"></i></button>
                                            <button class="action-btn btn-edit"><i class="fas fa-edit"></i></button>
                                            <button class="action-btn btn-delete"><i class="fas fa-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No tenants found. Add your first tenant using the button above.</td>
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
                        <label class="form-label">ID Number</label>
                        <input type="text" class="form-control" name="id_number" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">House Number</label>
                        <input type="text" class="form-control" name="house_number" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone_number" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Move-in Month</label>
                        <select class="form-control" name="rented_month" required>
                            <option value="">Select Month</option>
                            <option value="January">January</option>
                            <option value="February">February</option>
                            <option value="March">March</option>
                            <option value="April">April</option>
                            <option value="May">May</option>
                            <option value="June">June</option>
                            <option value="July">July</option>
                            <option value="August">August</option>
                            <option value="September">September</option>
                            <option value="October">October</option>
                            <option value="November">November</option>
                            <option value="December">December</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Move-in Year</label>
                        <input type="number" class="form-control" name="rented_year" min="2000" max="2030" value="<?php echo date('Y'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">ID Picture (Optional)</label>
                        <input type="file" class="form-control" name="id_picture" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancelTenant">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="add_tenant">Add Tenant</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        const addTenantBtn = document.getElementById('addTenantBtn');
        const tenantModal = document.getElementById('tenantModal');
        const closeModal = document.getElementById('closeModal');
        const cancelTenant = document.getElementById('cancelTenant');
        
        addTenantBtn.addEventListener('click', () => {
            tenantModal.style.display = 'flex';
        });
        
        closeModal.addEventListener('click', () => {
            tenantModal.style.display = 'none';
        });
        
        cancelTenant.addEventListener('click', () => {
            tenantModal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        tenantModal.addEventListener('click', (e) => {
            if (e.target === tenantModal) {
                tenantModal.style.display = 'none';
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