<?php
require_once 'db_config.php';

function full_base_url() {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'];
}

$token = $_GET['token'] ?? '';
if ($token === '') { http_response_code(400); echo 'Missing token'; exit; }

// Load agreement by token
$stmt = $pdo->prepare("SELECT ta.*, t.name AS tenant_name FROM tenant_agreements ta JOIN tenants t ON t.id = ta.tenant_id WHERE access_token = ? LIMIT 1");
$stmt->execute([$token]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); echo 'Agreement not found'; exit; }

// Handle signature POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signature_data'])) {
    $dataUrl = $_POST['signature_data'];
    if (strpos($dataUrl, 'data:image/png;base64,') !== 0) {
        http_response_code(400); echo 'Invalid signature data'; exit;
    }
    $pngData = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')));
    if ($pngData === false) { http_response_code(400); echo 'Invalid signature data'; exit; }
    
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'signatures';
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    $fileName = 'sign_' . $row['id'] . '_' . time() . '.png';
    $filePath = $dir . DIRECTORY_SEPARATOR . $fileName;
    file_put_contents($filePath, $pngData);

    $relPath = 'uploads/signatures/' . $fileName;

    $upd = $pdo->prepare("UPDATE tenant_agreements SET signature_path = ?, signed_at = NOW(), status = 'signed' WHERE id = ?");
    $upd->execute([$relPath, $row['id']]);

    header('Location: agreement_sign.php?token=' . urlencode($token) . '&signed=1');
    exit;
}

$signed = isset($_GET['signed']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tenancy Agreement</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body{font-family:'Inter',system-ui;background:#f1f5f9;color:#0f172a;margin:0;padding:0}
        .container{max-width:900px;margin:30px auto;padding:24px;background:#fff;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08)}
        .title{font-size:22px;font-weight:700;margin:0 0 8px}
        .muted{color:#64748b}
        .agreement{padding:16px;border:1px solid #e2e8f0;border-radius:12px;max-height:50vh;overflow:auto;margin-top:16px}
        .sig-box{margin-top:20px;padding:16px;border:1px dashed #94a3b8;border-radius:12px;background:#f8fafc}
        .sig-toolbar{display:flex;gap:10px;margin-bottom:10px}
        .btn{padding:10px 14px;border-radius:8px;border:none;font-weight:600;cursor:pointer}
        .btn-primary{background:#4361ee;color:#fff}
        .btn-outline{background:transparent;border:1px solid #4361ee;color:#4361ee}
        canvas{background:#fff;border:1px solid #cbd5e1;border-radius:8px}
        .alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;border:1px solid}
        .ok{background:#ecfdf5;border-color:#a7f3d0;color:#065f46}
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">Tenancy Agreement</h1>
        <p class="muted">Tenant: <?php echo htmlspecialchars($row['tenant_name']); ?><?php if ($row['status']==='signed'): ?> • Signed on <?php echo htmlspecialchars($row['signed_at']); ?><?php endif; ?></p>

        <?php if ($signed): ?>
            <div class="alert ok"><i class="fas fa-check-circle"></i> Signature captured successfully.</div>
        <?php endif; ?>

        <div class="agreement"><?php echo $row['filled_html']; ?></div>

        <?php if ($row['status'] !== 'signed'): ?>
        <div class="sig-box">
            <div class="sig-toolbar">
                <button class="btn btn-outline" id="clearBtn" type="button"><i class="fas fa-eraser"></i> Clear</button>
            </div>
            <canvas id="signature" width="800" height="200"></canvas>
            <form method="POST" onsubmit="return submitSignature()" style="margin-top:10px">
                <input type="hidden" name="signature_data" id="signature_data">
                <button class="btn btn-primary" type="submit"><i class="fas fa-pen"></i> Sign Agreement</button>
            </form>
        </div>
        <?php else: ?>
            <?php if (!empty($row['signature_path'])): ?>
                <div style="margin-top:16px">
                    <div class="muted">Signature:</div>
                    <img src="<?php echo htmlspecialchars($row['signature_path']); ?>" alt="Signature" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;max-width:100%">
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        const canvas = document.getElementById('signature');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            let drawing = false, lastX = 0, lastY = 0;
            function start(e){ drawing = true; [lastX,lastY] = pos(e); }
            function end(){ drawing = false; ctx.beginPath(); }
            function draw(e){ if(!drawing) return; const [x,y]=pos(e); ctx.lineWidth=2; ctx.lineCap='round'; ctx.strokeStyle='#111827'; ctx.beginPath(); ctx.moveTo(lastX,lastY); ctx.lineTo(x,y); ctx.stroke(); [lastX,lastY]=[x,y]; }
            function pos(e){ const r = canvas.getBoundingClientRect(); const t = (e.touches? e.touches[0]: e); return [t.clientX - r.left, t.clientY - r.top]; }
            canvas.addEventListener('mousedown', start); canvas.addEventListener('touchstart', start);
            canvas.addEventListener('mouseup', end); canvas.addEventListener('mouseout', end); canvas.addEventListener('touchend', end);
            canvas.addEventListener('mousemove', draw); canvas.addEventListener('touchmove', draw);
            document.getElementById('clearBtn')?.addEventListener('click', ()=>{ ctx.clearRect(0,0,canvas.width,canvas.height); });
        }
        function submitSignature(){
            const sig = document.getElementById('signature');
            if (!sig) return true;
            const data = sig.toDataURL('image/png');
            if (data.length < 1000) { alert('Please draw your signature.'); return false; }
            document.getElementById('signature_data').value = data;
            return true;
        }
    </script>
</body>
</html>
