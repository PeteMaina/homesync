<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: auth.html");
    exit();
}

$landlord_id = $_SESSION['admin_id'];

// Handle Link Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $prop_id = $_POST['property_id'];
    $token = bin2hex(random_bytes(16));
    
    // Deactivate old tokens if needed or just add new one
    $stmt = $pdo->prepare("INSERT INTO security_links (property_id, access_token) VALUES (?, ?)");
    $stmt->execute([$prop_id, $token]);
}

// Fetch Properties and their links
$stmt = $pdo->prepare("
    SELECT p.name, p.id, s.access_token, s.created_at 
    FROM properties p 
    LEFT JOIN (
        SELECT property_id, access_token, created_at 
        FROM security_links 
        WHERE id IN (SELECT MAX(id) FROM security_links GROUP BY property_id)
    ) s ON p.id = s.property_id 
    WHERE p.landlord_id = ?
");
$stmt->execute([$landlord_id]);
$links = $stmt->fetchAll();

$baseUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/gate.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Links - HomeSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; padding: 30px; }
        .card { background: white; border-radius: 15px; padding: 25px; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); max-width: 800px; margin: 0 auto; }
        .link-box { background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px dashed #cbd5e1; display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        .token { color: #4361ee; font-family: monospace; font-size: 13px; }
        .btn { padding: 8px 15px; background: #4361ee; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 13px; }
        .copy-btn { background: #e2e8f0; color: #1e293b; }
    </style>
</head>
<body>
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <h1><i class="fas fa-shield-alt"></i> Security Portal Links</h1>
            <a href="index.php" style="text-decoration: none; color: #64748b;"><i class="fas fa-arrow-left"></i> Back</a>
        </div>

        <p style="color: #64748b; margin-bottom: 30px;">Generate and send these unique links to your gate personnel. These links grant access to the visitor matching portal for specific properties.</p>

        <?php foreach ($links as $row): ?>
            <div style="margin-bottom: 25px; border-bottom: 1px solid #f1f5f9; padding-bottom: 20px;">
                <h3 style="margin-bottom: 10px;"><?php echo $row['name']; ?></h3>
                <?php if ($row['access_token']): ?>
                    <div class="link-box">
                        <span class="token" id="link_<?php echo $row['id']; ?>"><?php echo $baseUrl . "?token=" . $row['access_token']; ?></span>
                        <button class="btn copy-btn" onclick="copyLink('link_<?php echo $row['id']; ?>')"><i class="fas fa-copy"></i> Copy</button>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="property_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" name="generate" class="btn">Generate Link</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        function copyLink(id) {
            const text = document.getElementById(id).innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('Security link copied to clipboard!');
            });
        }
    </script>
</body>
</html>
