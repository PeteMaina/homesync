<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.html");
    exit();
}

$property_id = $_GET['property_id'] ?? null;
if (!$property_id) {
    die("Property ID required.");
}

// Simple generation logic for the current month
$month = date('F');
$year = date('Y');
$due_date = date('Y-m-d', strtotime('next month 5th')); // Arbitrary due date: 5th of next month

try {
    $pdo->beginTransaction();

    // 1. Fetch all units for this property
    $stmt = $pdo->prepare("SELECT id, rent_amount, late_fee_enabled, late_fee_rate FROM units WHERE property_id = ?");
    $stmt->execute([$property_id]);
    $units = $stmt->fetchAll();

    foreach ($units as $unit) {
        // 2. Find active tenant for this unit
        $tStmt = $pdo->prepare("SELECT id FROM tenants WHERE unit_id = ? AND status = 'active' LIMIT 1");
        $tStmt->execute([$unit['id']]);
        $tenant_id = $tStmt->fetchColumn();

        if ($tenant_id) {
            // Check if bill already exists for this month/unit
            $check = $pdo->prepare("SELECT id FROM bills WHERE unit_id = ? AND month = ? AND year = ? AND bill_type = 'rent'");
            $check->execute([$unit['id'], $month, $year]);
            
            if (!$check->fetch()) {
                // 3. Insert Rent Bill
                $ins = $pdo->prepare("INSERT INTO bills (tenant_id, unit_id, bill_type, amount, balance, month, year, due_date, status) VALUES (?, ?, 'rent', ?, ?, ?, ?, ?, 'unpaid')");
                $ins->execute([
                    $tenant_id,
                    $unit['id'],
                    $unit['rent_amount'],
                    $unit['rent_amount'],
                    $month,
                    $year,
                    $due_date
                ]);
            }
        }
    }

    $pdo->commit();
    $_SESSION['message'] = "Bills for $month $year generated successfully!";
    $_SESSION['message_type'] = "success";
    header("Location: index.php?property_id=$property_id");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error generating bills: " . $e->getMessage());
}
?>
