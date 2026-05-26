<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>500 — Server Error · RiskSignal</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:system-ui,sans-serif;background:#0b1220;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
.wrap{text-align:center;max-width:480px;}
.code{font-size:6rem;font-weight:900;color:rgba(239,68,68,.15);line-height:1;}
.title{font-size:1.75rem;font-weight:800;margin-bottom:.75rem;}
.sub{color:#94a3b8;font-size:.95rem;line-height:1.6;margin-bottom:2rem;}
.actions{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;}
.btn{padding:.8rem 1.5rem;border-radius:12px;font-weight:700;font-size:.9rem;text-decoration:none;transition:.15s ease;}
.btn-primary{background:#2563eb;color:#fff;}
.btn-primary:hover{background:#1d4ed8;}
.support{color:#64748b;font-size:.8rem;margin-top:1.5rem;}
.support a{color:#f0c040;text-decoration:none;}
</style>
</head>
<body>
<div class="wrap">
    <div class="code">500</div>
    <h1 class="title">Something went wrong</h1>
    <p class="sub">An unexpected error occurred on our side. We've been notified and are looking into it.</p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn btn-primary">← Go Home</a>
    </div>
    <p class="support">Need help? <a href="mailto:support@risksignal.in">support@risksignal.in</a></p>
</div>
</body>
</html>
