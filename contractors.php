<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.html");
    exit();
}

$landlord_id = $_SESSION['admin_id'];

// Handle Add Contractor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contractor'])) {
    $name = $_POST['name'];
    $category = $_POST['category'];
    $phone = $_POST['phone'];
    
    $stmt = $pdo->prepare("INSERT INTO contractors (landlord_id, name, category, phone_number) VALUES (?, ?, ?, ?)");
    $stmt->execute([$landlord_id, $name, $category, $phone]);
    $success = "Contractor added!";
}

// Fetch all contractors
$stmt = $pdo->prepare("SELECT * FROM contractors WHERE landlord_id = ? ORDER BY category, name");
$stmt->execute([$landlord_id]);
$contractors = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contractor Directory - HomeSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; padding: 30px; }
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); max-width: 800px; margin: 0 auto; }
        .btn { padding: 10px 20px; background: #4361ee; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 12px; background: #e0e7ff; color: #4361ee; font-weight: 600; }
    </style>
</head>
<body>
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1><i class="fas fa-tools"></i> Contractor Directory</h1>
            <a href="index.php" style="text-decoration: none; color: #64748b;"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <form method="POST" style="background: #f8fafc; padding: 20px; border-radius: 10px; margin-bottom: 30px; display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-size: 13px;">Name</label>
                <input type="text" name="name" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 5px;">
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-size: 13px;">Category</label>
                <select name="category" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 5px;">
                    <option>Plumber</option>
                    <option>Electrician</option>
                    <option>Carpenter</option>
                    <option>Security</option>
                    <option>Cleaner</option>
                    <option>Other</option>
                </select>
            </div>
            <div>
                <label style="display: block; margin-bottom: 5px; font-size: 13px;">Phone</label>
                <input type="tel" name="phone" required style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 5px;">
            </div>
            <button type="submit" name="add_contractor" class="btn">Add</button>
        </form>

        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Phone</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contractors as $c): ?>
                    <tr>
                        <td><strong><?php echo $c['name']; ?></strong></td>
                        <td><span class="badge"><?php echo $c['category']; ?></span></td>
                        <td><?php echo $c['phone_number']; ?></td>
                        <td>
                            <a href="tel:<?php echo $c['phone_number']; ?>" style="color: #4361ee;"><i class="fas fa-phone"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($contractors)): ?>
                    <tr><td colspan="4" style="text-align: center; color: #64748b;">No contractors found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
