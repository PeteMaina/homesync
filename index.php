<?php
// Start session and include database connection
session_start();
require_once 'db_config.php';


// Initialize error/success messages
$message = '';
$message_type = '';

// Check for messages in session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Fetch dashboard statistics
try {
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_revenue,
            SUM(CASE WHEN status = 'unpaid' THEN amount ELSE 0 END) as pending_payments,
            SUM(CASE WHEN bill_type = 'water' AND status = 'paid' THEN amount ELSE 0 END) as water_bills
        FROM tenant_bills
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch houses with tenant information
    $stmt = $pdo->query("
        SELECT DISTINCT house_number as id, house_number as name, 
               t.id_number, t.name as tenant_name,
               (SELECT status FROM tenant_bills WHERE house_number = h.house_number ORDER BY created_at DESC LIMIT 1) as last_status,
               (SELECT amount FROM tenant_bills WHERE house_number = h.house_number AND bill_type = 'water' ORDER BY created_at DESC LIMIT 1) as last_reading
        FROM (SELECT DISTINCT house_number FROM tenants UNION SELECT DISTINCT house_number FROM tenant_bills) h
        LEFT JOIN tenants t ON h.house_number = t.house_number
        ORDER BY h.house_number
    ");
    $houses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch billing history
    $stmt = $pdo->query("
        SELECT b.*, t.name as tenant_name
        FROM tenant_bills b
        LEFT JOIN tenants t ON b.id_number = t.id_number
        ORDER BY b.created_at DESC
        LIMIT 20
    ");
    $billingHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $message = "Unable to fetch data. Please try again later.";
    $message_type = "error";
}

// Helper function to format currency
function formatCurrency($amount) {
    return 'KES ' . number_format($amount, 2);
}

// Helper function to get status class
function getStatusClass($status) {
    switch ($status) {
        case 'paid': return 'status-paid';
        case 'unpaid': return 'status-pending';
        default: return 'status-vacant';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Management - HomeSync</title>
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
        
        /* Message notification */
        .message-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 16px 24px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 2000;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.4s ease;
            max-width: 400px;
        }
        
        .message-notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .message-notification.hide {
            transform: translateX(100%);
            opacity: 0;
        }
        
        .message-success {
            background: var(--success);
        }
        
        .message-error {
            background: var(--danger);
        }
        
        .message-warning {
            background: var(--warning);
        }
        
        .message-info {
            background: var(--primary);
        }
        
        /* iOS Style Confirmation Dialog */
        .ios-dialog {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .ios-dialog.active {
            opacity: 1;
            pointer-events: all;
        }
        
        .ios-dialog-content {
            background: white;
            border-radius: 14px;
            width: 100%;
            max-width: 300px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        
        .ios-dialog.active .ios-dialog-content {
            transform: scale(1);
        }
        
        .ios-dialog-title {
            padding: 20px;
            text-align: center;
            font-weight: 600;
            font-size: 18px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .ios-dialog-message {
            padding: 20px;
            text-align: center;
            color: var(--gray);
            border-bottom: 1px solid var(--light-gray);
        }
        
        .ios-dialog-actions {
            display: flex;
        }
        
        .ios-dialog-button {
            flex: 1;
            padding: 16px;
            text-align: center;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        
        .ios-dialog-button:hover {
            background: #f5f5f5;
        }
        
        .ios-dialog-button.cancel {
            color: var(--primary);
            border-right: 1px solid var(--light-gray);
        }
        
        .ios-dialog-button.confirm {
            color: var(--danger);
            font-weight: 700;
        }
        
        /* Rest of the CSS remains the same as in the original file, with additions for new elements */
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
        
        /* Main Billing Card */
        .billing-card {
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
        
        .apartment-selector {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        .apartment-pill {
            padding: 8px 16px;
            border-radius: 50px;
            background: var(--light);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .apartment-pill.active {
            background: var(--primary);
            color: white;
        }
        
        .billing-controls {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        
        .control-group {
            flex: 1;
            min-width: 250px;
        }
        
        .control-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .select-wrapper, .input-wrapper {
            position: relative;
        }
        
        .form-select, .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .form-select:focus, .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }
        
        .select-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .house-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .house-card {
            border: 1px solid var(--light-gray);
            border-radius: 12px;
            padding: 16px;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .house-card.selected {
            border-color: var(--primary);
            background: rgba(67, 97, 238, 0.05);
        }
        
        .house-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .house-name {
            font-weight: 600;
        }
        
        .house-checkbox {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            border: 2px solid var(--light-gray);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .house-card.selected .house-checkbox {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .house-details {
            font-size: 14px;
            color: var(--gray);
        }
        
        .house-details div {
            margin-bottom: 4px;
        }
        
        .bulk-actions {
            display: flex;
            gap: 12px;
            padding: 16px;
            background: var(--light);
            border-radius: 12px;
            margin-top: 16px;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--light-gray);
        }
        
        .billing-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .billing-table th {
            background: var(--light);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
        }
        
        .billing-table td {
            padding: 16px;
            border-top: 1px solid var(--light-gray);
        }
        
        .billing-table tr:hover {
            background: rgba(67, 97, 238, 0.03);
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-paid {
            background: rgba(46, 196, 182, 0.1);
            color: var(--success);
        }
        
        .status-pending {
            background: rgba(255, 159, 28, 0.1);
            color: var(--warning);
        }
        
        .status-overdue {
            background: rgba(231, 29, 54, 0.1);
            color: var(--danger);
        }
        
        .status-vacant {
            background: rgba(108, 117, 125, 0.1);
            color: var(--gray);
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
        
        /* SMS Preview Modal */
        .sms-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .sms-preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            color: var(--gray);
            font-size: 14px;
        }
        
        .sms-preview-content {
            background: white;
            border-radius: 12px;
            padding: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
            
            .billing-controls {
                flex-direction: column;
            }
            
            .house-grid {
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
        
        .small {
            font-size: 12px;
        }
        
        .text-muted {
            color: var(--gray);
        }
    </style>
</head>
<body>
    <!-- Message Notification -->
    <?php if ($message): ?>
    <div class="message-notification <?php echo $message_type; ?>" id="messageNotification">
        <i class="fas <?php 
            if ($message_type == 'success') echo 'fa-check-circle';
            elseif ($message_type == 'error') echo 'fa-exclamation-circle';
            elseif ($message_type == 'warning') echo 'fa-exclamation-triangle';
            else echo 'fa-info-circle';
        ?>"></i>
        <span><?php echo $message; ?></span>
    </div>
    <?php endif; ?>

    <!-- iOS Style Confirmation Dialog -->
    <div class="ios-dialog" id="iosDialog">
        <div class="ios-dialog-content">
            <div class="ios-dialog-title" id="iosDialogTitle">Confirm Action</div>
            <div class="ios-dialog-message" id="iosDialogMessage">Are you sure you want to proceed?</div>
            <div class="ios-dialog-actions">
                <div class="ios-dialog-button cancel" id="iosDialogCancel">Cancel</div>
                <div class="ios-dialog-button confirm" id="iosDialogConfirm">Confirm</div>
            </div>
        </div>
    </div>

    <!-- SMS Preview Dialog -->
    <div class="ios-dialog" id="smsDialog">
        <div class="ios-dialog-content" style="max-width: 350px;">
            <div class="ios-dialog-title">SMS Preview</div>
            <div class="ios-dialog-message">
                <div class="sms-preview">
                    <div class="sms-preview-header">
                        <span>To: <span id="smsRecipient"></span></span>
                        <span>Now</span>
                    </div>
                    <div class="sms-preview-content" id="smsContent"></div>
                </div>
                <p class="small text-muted" style="margin-top: 16px;">This message will be sent to the selected tenants.</p>
            </div>
            <div class="ios-dialog-actions">
                <div class="ios-dialog-button cancel" id="smsDialogCancel">Edit</div>
                <div class="ios-dialog-button confirm" id="smsDialogConfirm">Send</div>
            </div>
        </div>
    </div>

    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h1><i class="fas fa-home"></i> HomeSync</h1>
                <p>Property Management System</p>
            </div>
            
            <div class="nav-items">
                <a href="#" class="nav-item active">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Billing</span>
                </a>
                <a href="tenants.php" class="nav-item">
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
                    <div class="user-avatar">JD</div>
                    <div class="user-info">
                        <h4>John Doe</h4>
                        <p>Landlord</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Billing Management</h1>
                <div class="page-actions">
                    <button class="btn btn-outline" id="exportBtn">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button class="btn btn-primary" id="generateBillBtn">
                        <i class="fas fa-plus"></i> Generate Bills
                    </button>
                </div>
            </div>
            
            <!-- Dashboard Cards -->
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Total Revenue</h3>
                        <div class="card-icon bg-primary-light">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="card-value">KES <?php echo number_format($stats['total_revenue'] ?? 0); ?></div>
                    <div class="card-footer">
                        <span class="positive"><i class="fas fa-arrow-up"></i> 12.5%</span> from last month
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Pending Payments</h3>
                        <div class="card-icon bg-warning-light">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="card-value">KES <?php echo number_format($stats['pending_payments'] ?? 0); ?></div>
                    <div class="card-footer">
                        <span class="negative"><i class="fas fa-arrow-up"></i> 8.3%</span> from last month
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Water Bills</h3>
                        <div class="card-icon bg-success-light">
                            <i class="fas fa-tint"></i>
                        </div>
                    </div>
                    <div class="card-value">KES <?php echo number_format($stats['water_bills'] ?? 0); ?></div>
                    <div class="card-footer">
                        <span class="positive"><i class="fas fa-arrow-down"></i> 3.2%</span> from last month
                    </div>
                </div>
            </div>
            
            <!-- Billing Card -->
            <div class="billing-card">
                <div class="card-header-lg">
                    <h2 class="card-title-lg">Generate Water Bills</h2>
                    <div class="rate-display">
                        Current Rate: <strong>KES 200 per unit</strong>
                    </div>
                </div>
                
                <!-- Apartment Selector -->
                <div class="apartment-selector">
                    <div class="apartment-pill active">All Apartments</div>
                    <div class="apartment-pill">Apartment A</div>
                    <div class="apartment-pill">Apartment B</div>
                    <div class="apartment-pill">Apartment C</div>
                </div>
                
                <!-- Billing Controls -->
                <form method="POST" action="process_bills.php" id="billingForm">
                    <div class="billing-controls">
                        <div class="control-group">
                            <label class="control-label">Bill Type</label>
                            <div class="select-wrapper">
                                <select class="form-select" name="bill_type" required>
                                    <option value="Water Bill" selected>Water Bill</option>
                                    <option value="Rent">Rent</option>
                                    <option value="Garbage">Garbage</option>
                                    <option value="WiFi">WiFi</option>
                                </select>
                                <div class="select-icon"><i class="fas fa-chevron-down"></i></div>
                            </div>
                        </div>
                        
                        <div class="control-group">
                            <label class="control-label">Billing Month</label>
                            <div class="select-wrapper">
                                <select class="form-select" name="billing_month" required>
                                    <?php
                                    $months = [
                                        'January', 'February', 'March', 'April', 'May', 'June',
                                        'July', 'August', 'September', 'October', 'November', 'December'
                                    ];
                                    $currentMonth = date('n') - 1;
                                    $currentYear = date('Y');
                                    
                                    for ($i = 0; $i < 12; $i++) {
                                        $monthIndex = ($currentMonth - $i + 12) % 12;
                                        $year = $currentYear;
                                        if ($monthIndex > $currentMonth) $year--;
                                        
                                        $value = $months[$monthIndex] . ' ' . $year;
                                        $selected = $i === 0 ? 'selected' : '';
                                        echo "<option value='$value' $selected>$value</option>";
                                    }
                                    ?>
                                </select>
                                <div class="select-icon"><i class="fas fa-chevron-down"></i></div>
                            </div>
                        </div>
                        
                        <div class="control-group">
                            <label class="control-label">Due Date</label>
                            <input type="date" class="form-input" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                        </div>
                    </div>
                    
                    <!-- House Selection Grid -->
                    <h3 class="mb-0">Select Houses</h3>
                    <p class="small text-muted">Select individual houses or use the select all option</p>
                    
                    <div class="house-grid" id="houseGrid">
                        <?php foreach ($houses as $house): 
                            $statusClass = 'status-' . strtolower($house['last_status'] ?? 'vacant');
                            if (empty($house['last_status'])) $statusClass = 'status-vacant';
                            
                            $statusText = ucfirst($house['last_status'] ?? 'Vacant');
                            if (empty($house['tenant_name'])) $statusText = 'Vacant';
                        ?>
                        <div class="house-card" data-house-id="<?php echo $house['id']; ?>">
                            <div class="house-card-header">
                                <div class="house-name"><?php echo htmlspecialchars($house['name']); ?></div>
                                <div class="house-checkbox"></div>
                            </div>
                            <div class="house-details">
                                <div>Tenant: <?php echo !empty($house['tenant_name']) ? htmlspecialchars($house['tenant_name']) : 'Vacant'; ?></div>
                                <div>Last Reading: <?php echo $house['last_reading'] ?? 0; ?> units</div>
                                <div>Status: <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></div>
                            </div>
                            <input type="hidden" name="house_ids[]" value="<?php echo $house['id']; ?>" disabled>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Bulk Actions -->
                    <div class="bulk-actions">
                        <button type="button" class="btn btn-outline" id="selectAllBtn">
                            <i class="fas fa-check-square"></i> Select All
                        </button>
                        <button type="button" class="btn btn-outline" id="clearSelectionBtn">
                            <i class="fas fa-times-circle"></i> Clear Selection
                        </button>
                        <button type="button" class="btn btn-primary" id="calculateBtn">
                            <i class="fas fa-calculator"></i> Calculate Bills
                        </button>
                    </div>
                    
                    <!-- Hidden form for submission -->
                    <input type="hidden" name="selected_houses" id="selectedHouses">
                </form>
            </div>
            
            <!-- Billing History -->
            <div class="billing-card">
                <div class="card-header-lg">
                    <h2 class="card-title-lg">Billing History</h2>
                    <div class="table-actions">
                        <button class="btn btn-outline" id="filterBtn">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="billing-table">
                        <thead>
                            <tr>
                                <th>House</th>
                                <th>Tenant</th>
                                <th>Bill Type</th>
                                <th>Amount</th>
                                <th>Date Generated</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($billingHistory)): ?>
                                <?php foreach ($billingHistory as $bill): 
                                    $statusClass = 'status-' . strtolower($bill['status']);
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bill['house_name']); ?></td>
                                    <td><?php echo !empty($bill['tenant_name']) ? htmlspecialchars($bill['tenant_name']) : 'Vacant'; ?></td>
                                    <td><?php echo htmlspecialchars($bill['bill_type']); ?></td>
                                    <td>KES <?php echo number_format($bill['amount']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($bill['date_generated'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($bill['due_date'])); ?></td>
                                    <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo ucfirst($bill['status']); ?></span></td>
                                    <td>
                                        <button class="action-btn btn-view"><i class="fas fa-eye"></i></button>
                                        <button class="action-btn btn-edit"><i class="fas fa-edit"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">No billing records found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Generate Bill Modal -->
    <div class="modal-overlay" id="generateBillModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Generate Water Bills</h2>
                <button class="modal-close" id="closeGenerateModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Current Meter Reading</label>
                    <input type="number" class="form-control" id="currentReading" placeholder="Enter current reading">
                </div>
                <div class="form-group">
                    <label class="form-label">Rate per Unit (KES)</label>
                    <input type="number" class="form-control" id="ratePerUnit" value="200">
                </div>
                <div class="form-group">
                    <label class="form-label">Additional Charges (KES)</label>
                    <input type="number" class="form-control" id="additionalCharges" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelGenerate">Cancel</button>
                <button class="btn btn-primary" id="confirmGenerate">Generate Bills</button>
            </div>
        </div>
    </div>

    <!-- SMS Modal -->
    <div class="modal-overlay" id="smsModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Send SMS to Tenants</h2>
                <button class="modal-close" id="closeSmsModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Recipients</label>
                    <select class="form-control" id="smsRecipients" multiple>
                        <option value="all" selected>All Tenants</option>
                        <option value="selected">Selected Houses Only</option>
                        <option value="overdue">Tenants with Overdue Bills</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Message Template</label>
                    <select class="form-control" id="smsTemplate">
                        <option value="bill_generated">Bill Generated Notification</option>
                        <option value="payment_reminder">Payment Reminder</option>
                        <option value="overdue_notice">Overdue Notice</option>
                        <option value="custom">Custom Message</option>
                    </select>
                </div>
                <div class="form-group" id="customMessageGroup" style="display: none;">
                    <label class="form-label">Custom Message</label>
                    <textarea class="form-control" id="customMessage" rows="4" placeholder="Type your message here..."></textarea>
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-outline" id="previewSmsBtn">
                        <i class="fas fa-eye"></i> Preview Message
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelSms">Cancel</button>
                <button class="btn btn-primary" id="sendSms">Send SMS</button>
            </div>
        </div>
    </div>

    <script>
        // Show message notification and hide after 5 seconds
        <?php if ($message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const messageNotification = document.getElementById('messageNotification');
            if (messageNotification) {
                messageNotification.classList.add('show');
                
                setTimeout(() => {
                    messageNotification.classList.remove('show');
                    messageNotification.classList.add('hide');
                    
                    setTimeout(() => {
                        messageNotification.remove();
                    }, 400);
                }, 5000);
            }
        });
        <?php endif; ?>

        // iOS Style Dialog
        const iosDialog = document.getElementById('iosDialog');
        const iosDialogTitle = document.getElementById('iosDialogTitle');
        const iosDialogMessage = document.getElementById('iosDialogMessage');
        const iosDialogCancel = document.getElementById('iosDialogCancel');
        const iosDialogConfirm = document.getElementById('iosDialogConfirm');
        
        let confirmCallback = null;
        let cancelCallback = null;
        
        function showIOSDialog(title, message, confirmText = 'Confirm', cancelText = 'Cancel') {
            iosDialogTitle.textContent = title;
            iosDialogMessage.textContent = message;
            iosDialogConfirm.textContent = confirmText;
            iosDialogCancel.textContent = cancelText;
            
            iosDialog.classList.add('active');
            
            return new Promise((resolve) => {
                confirmCallback = () => {
                    hideIOSDialog();
                    resolve(true);
                };
                
                cancelCallback = () => {
                    hideIOSDialog();
                    resolve(false);
                };
            });
        }
        
        function hideIOSDialog() {
            iosDialog.classList.remove('active');
        }
        
        iosDialogConfirm.addEventListener('click', function() {
            if (confirmCallback) confirmCallback();
        });
        
        iosDialogCancel.addEventListener('click', function() {
            if (cancelCallback) cancelCallback();
        });
        
        // SMS Dialog
        const smsDialog = document.getElementById('smsDialog');
        const smsDialogCancel = document.getElementById('smsDialogCancel');
        const smsDialogConfirm = document.getElementById('smsDialogConfirm');
        const smsRecipient = document.getElementById('smsRecipient');
        const smsContent = document.getElementById('smsContent');
        
        function showSmsDialog(recipient, content) {
            smsRecipient.textContent = recipient;
            smsContent.textContent = content;
            
            smsDialog.classList.add('active');
            
            return new Promise((resolve) => {
                smsDialogConfirm.onclick = () => {
                    hideSmsDialog();
                    resolve(true);
                };
                
                smsDialogCancel.onclick = () => {
                    hideSmsDialog();
                    resolve(false);
                };
            });
        }
        
        function hideSmsDialog() {
            smsDialog.classList.remove('active');
        }
        
        // House Selection
        const houseCards = document.querySelectorAll('.house-card');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const clearSelectionBtn = document.getElementById('clearSelectionBtn');
        const selectedHousesInput = document.getElementById('selectedHouses');
        
        houseCards.forEach(card => {
            card.addEventListener('click', function() {
                this.classList.toggle('selected');
                const input = this.querySelector('input[type="hidden"]');
                input.disabled = !this.classList.contains('selected');
                
                updateSelectedHouses();
            });
        });
        
        selectAllBtn.addEventListener('click', function() {
            houseCards.forEach(card => {
                card.classList.add('selected');
                const input = card.querySelector('input[type="hidden"]');
                input.disabled = false;
            });
            updateSelectedHouses();
        });
        
        clearSelectionBtn.addEventListener('click', function() {
            houseCards.forEach(card => {
                card.classList.remove('selected');
                const input = card.querySelector('input[type="hidden"]');
                input.disabled = true;
            });
            updateSelectedHouses();
        });
        
        function updateSelectedHouses() {
            const selectedIds = [];
            document.querySelectorAll('.house-card.selected').forEach(card => {
                selectedIds.push(card.dataset.houseId);
            });
            selectedHousesInput.value = selectedIds.join(',');
        }
        
        // Generate Bill Button
        const generateBillBtn = document.getElementById('generateBillBtn');
        const generateBillModal = document.getElementById('generateBillModal');
        const closeGenerateModal = document.getElementById('closeGenerateModal');
        const cancelGenerate = document.getElementById('cancelGenerate');
        const confirmGenerate = document.getElementById('confirmGenerate');
        
        generateBillBtn.addEventListener('click', function() {
            const selectedCount = document.querySelectorAll('.house-card.selected').length;
            
            if (selectedCount === 0) {
                showIOSDialog('No Houses Selected', 'Please select at least one house to generate bills.', 'OK');
                return;
            }
            
            generateBillModal.style.display = 'flex';
        });
        
        closeGenerateModal.addEventListener('click', function() {
            generateBillModal.style.display = 'none';
        });
        
        cancelGenerate.addEventListener('click', function() {
            generateBillModal.style.display = 'none';
        });
        
        confirmGenerate.addEventListener('click', async function() {
            const currentReading = document.getElementById('currentReading').value;
            
            if (!currentReading) {
                showIOSDialog('Input Required', 'Please enter the current meter reading.', 'OK');
                return;
            }
            
            const confirmed = await showIOSDialog(
                'Confirm Bill Generation', 
                `Are you sure you want to generate bills for ${document.querySelectorAll('.house-card.selected').length} houses?`,
                'Generate',
                'Cancel'
            );
            
            if (confirmed) {
                // Show SMS modal after generation
                generateBillModal.style.display = 'none';
                smsModal.style.display = 'flex';
                
                // In a real application, you would submit the form here
                // document.getElementById('billingForm').submit();
            }
        });
        
        // SMS Modal
        const smsModalElement = document.getElementById('smsModal');
        const closeSmsModal = document.getElementById('closeSmsModal');
        const cancelSms = document.getElementById('cancelSms');
        const sendSms = document.getElementById('sendSms');
        const smsTemplate = document.getElementById('smsTemplate');
        const customMessageGroup = document.getElementById('customMessageGroup');
        const previewSmsBtn = document.getElementById('previewSmsBtn');
        
        smsTemplate.addEventListener('change', function() {
            if (this.value === 'custom') {
                customMessageGroup.style.display = 'block';
            } else {
                customMessageGroup.style.display = 'none';
            }
        });
        
        previewSmsBtn.addEventListener('click', function() {
            let message = '';
            const template = smsTemplate.value;
            
            if (template === 'bill_generated') {
                message = 'Hello, your water bill for this month has been generated. Amount: KES 2,500. Due date: 25th May 2023. Thank you.';
            } else if (template === 'payment_reminder') {
                message = 'Friendly reminder: Your water bill payment is due in 3 days. Amount: KES 2,500. Please make payment before due date.';
            } else if (template === 'overdue_notice') {
                message = 'URGENT: Your water bill payment is overdue. Amount: KES 2,500. Please make immediate payment to avoid disconnection.';
            } else {
                message = document.getElementById('customMessage').value || 'Your message preview will appear here.';
            }
            
            const recipientType = document.getElementById('smsRecipients').value;
            let recipientText = 'All Tenants';
            if (recipientType === 'selected') recipientText = 'Selected Houses';
            if (recipientType === 'overdue') recipientText = 'Tenants with Overdue Bills';
            
            showSmsDialog(recipientText, message);
        });
        
        sendSms.addEventListener('click', async function() {
            const confirmed = await showIOSDialog(
                'Confirm SMS', 
                'Are you sure you want to send these SMS messages? SMS charges may apply.',
                'Send',
                'Cancel'
            );
            
            if (confirmed) {
                // In a real application, you would send the SMS via AJAX to sms.php
                smsModalElement.style.display = 'none';
                
                // Show success message
                const event = new CustomEvent('showMessage', {
                    detail: { 
                        message: 'SMS messages sent successfully!', 
                        type: 'success' 
                    }
                });
                document.dispatchEvent(event);
            }
        });
        
        closeSmsModal.addEventListener('click', function() {
            smsModalElement.style.display = 'none';
        });
        
        cancelSms.addEventListener('click', function() {
            smsModalElement.style.display = 'none';
        });
        
        // Calculate Button
        const calculateBtn = document.getElementById('calculateBtn');
        
        calculateBtn.addEventListener('click', function() {
            const selectedCount = document.querySelectorAll('.house-card.selected').length;
            
            if (selectedCount === 0) {
                showIOSDialog('No Houses Selected', 'Please select at least one house to calculate bills.', 'OK');
                return;
            }
            
            // In a real application, this would calculate the bills based on readings
            showIOSDialog(
                'Calculation Complete', 
                `Calculated bills for ${selectedCount} houses. Total amount: KES ${(selectedCount * 2500).toLocaleString()}.`,
                'OK'
            );
        });
        
        // Export Button
        const exportBtn = document.getElementById('exportBtn');
        
        exportBtn.addEventListener('click', async function() {
            const confirmed = await showIOSDialog(
                'Export Data', 
                'Do you want to export the billing history to CSV?',
                'Export',
                'Cancel'
            );
            
            if (confirmed) {
                // In a real application, this would trigger a download
                const event = new CustomEvent('showMessage', {
                    detail: { 
                        message: 'Data exported successfully!', 
                        type: 'success' 
                    }
                });
                document.dispatchEvent(event);
            }
        });
        
        // Custom event for showing messages
        document.addEventListener('showMessage', function(e) {
            const { message, type } = e.detail;
            
            // Create and show message notification
            const notification = document.createElement('div');
            notification.className = `message-notification ${type}`;
            notification.innerHTML = `
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 
                                 type === 'error' ? 'fa-exclamation-circle' : 
                                 type === 'warning' ? 'fa-exclamation-triangle' : 
                                 'fa-info-circle'}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            // Trigger reflow
            void notification.offsetWidth;
            
            notification.classList.add('show');
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                notification.classList.add('hide');
                
                setTimeout(() => {
                    notification.remove();
                }, 400);
            }, 5000);
        });
    </script>
</body>
</html>