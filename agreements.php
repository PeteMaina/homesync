<?php
require_once 'session_check.php';
require_once 'db_config.php';

requireLogin();

$landlord_id = $_SESSION['admin_id'];
$message = null; $message_type = null;

// Create a new agreement template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_template'])) {
    $title = trim($_POST['title'] ?? '');
    $template_html = trim($_POST['template_html'] ?? '');
    if ($title === '' || $template_html === '') {
        $message = 'Title and template content are required.';
        $message_type = 'error';
    } else {
        $stmt = $pdo->prepare("INSERT INTO agreements (landlord_id, title, template_html) VALUES (?, ?, ?)");
        $stmt->execute([$landlord_id, $title, $template_html]);
        $message = 'Template created successfully';
        $message_type = 'success';
    }
}

// Optional: delete template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_template'])) {
    $id = intval($_POST['agreement_id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM agreements WHERE id = ? AND landlord_id = ?");
        $stmt->execute([$id, $landlord_id]);
        $message = 'Template deleted';
        $message_type = 'success';
    }
}

// Fetch templates
$stmt = $pdo->prepare("SELECT * FROM agreements WHERE landlord_id = ? ORDER BY created_at DESC");
$stmt->execute([$landlord_id]);
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenancy Agreements - HomeSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary:#4361ee; --light:#f1f5f9; --dark:#0f172a; --gray:#64748b; --shadow:0 10px 30px rgba(0,0,0,0.08); }
        *{box-sizing:border-box} body{margin:0;display:flex;min-height:100vh;background:var(--light);color:var(--dark);font-family:'Inter',system-ui}
        .main{flex:1;padding:30px;overflow:auto}
        .card{background:#fff;border-radius:16px;box-shadow:var(--shadow);padding:24px;margin-bottom:24px}
        .title{font-size:22px;font-weight:700;margin:0 0 12px}
        .btn{padding:10px 16px;border:none;border-radius:8px;font-weight:600;cursor:pointer}
        .btn-primary{background:var(--primary);color:#fff}
        .btn-outline{background:transparent;border:1px solid var(--primary);color:var(--primary)}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px}
        label{font-size:13px;font-weight:600;color:#334155}
        .input, textarea{width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px}
        .muted{color:var(--gray);font-size:12px}
        .alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;border:1px solid}
        .ok{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
        .err{background:#fef2f2;border-color:#fecaca;color:#991b1b}
        pre{background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;overflow:auto}
    </style>
    <script>
        function insertPlaceholder(ph){
            const ta = document.getElementById('template_html');
            const start = ta.selectionStart, end = ta.selectionEnd;
            const val = ta.value; ta.value = val.substring(0,start)+ph+val.substring(end);
            ta.focus(); ta.selectionStart = ta.selectionEnd = start + ph.length;
        }
    </script>
    
    
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main">
        <h1 class="title">Tenancy Agreements</h1>
        <p class="muted">Create reusable templates, then generate a signable agreement for any tenant. Placeholders are replaced automatically.</p>

        <?php if ($message): ?>
            <div class="alert <?php echo $message_type==='success'?'ok':'err'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card">
            <h2 class="title" style="font-size:18px">New Template</h2>
            <form method="POST">
                <div style="display:grid;gap:12px;grid-template-columns:1fr">
                    <div>
                        <label>Title</label>
                        <input class="input" type="text" name="title" required placeholder="e.g., Standard Tenancy Agreement">
                    </div>
                    <div>
                        <label>Template Content</label>
                        <div class="muted" style="margin:6px 0">Placeholders: 
                            <button type="button" class="btn btn-outline" onclick="insertPlaceholder('{{tenant_name}}')">{{tenant_name}}</button>
                            <button type="button" class="btn btn-outline" onclick="insertPlaceholder('{{id_number}}')">{{id_number}}</button>
                            <button type="button" class="btn btn-outline" onclick="insertPlaceholder('{{unit_number}}')">{{unit_number}}</button>
                            <button type="button" class="btn btn-outline" onclick="insertPlaceholder('{{property_name}}')">{{property_name}}</button>
                            <button type="button" class="btn btn-outline" onclick="insertPlaceholder('{{rent_amount}}')">{{rent_amount}}</button>
                            <button type="button" class="btn btn-outline" onclick="insertPlaceholder('{{move_in_date}}')">{{move_in_date}}</button>
                        </div>
                        <textarea id="template_html" name="template_html" rows="14" class="input" placeholder="Write agreement HTML or plain text with placeholders..."></textarea>
                    </div>
                    <div>
                        <button type="submit" name="create_template" class="btn btn-primary"><i class="fas fa-save"></i> Save Template</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 class="title" style="font-size:18px">Templates</h2>
            <?php if (!$templates): ?>
                <p class="muted">No templates yet. Create one above.</p>
            <?php else: ?>
                <div class="grid">
                    <?php foreach ($templates as $t): ?>
                        <div class="card" style="padding:16px">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                                <div>
                                    <div style="font-weight:700"><?php echo htmlspecialchars($t['title']); ?></div>
                                    <div class="muted">Created: <?php echo date('M j, Y', strtotime($t['created_at'])); ?></div>
                                </div>
                                <form method="POST" onsubmit="return confirm('Delete this template?');">
                                    <input type="hidden" name="agreement_id" value="<?php echo $t['id']; ?>">
                                    <button class="btn btn-outline" name="delete_template"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                            <div style="margin-top:10px">
                                <details>
                                    <summary class="muted">Preview</summary>
                                    <div style="margin-top:8px">
                                        <?php echo nl2br(htmlspecialchars(mb_strimwidth($t['template_html'],0,600,'…'))); ?>
                                    </div>
                                </details>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
// eof
?>
