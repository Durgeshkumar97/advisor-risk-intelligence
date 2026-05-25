<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found — RiskSignal</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:Inter,Arial,sans-serif;background:#020817;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:30px;}
        .box{width:100%;max-width:560px;text-align:center;}
        .code{font-size:120px;font-weight:900;line-height:1;color:#0f172a;letter-spacing:-4px;margin-bottom:8px;}
        .eyebrow{color:#facc15;font-size:11px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;margin-bottom:18px;}
        h1{font-size:32px;font-weight:900;line-height:1.2;margin-bottom:12px;}
        .sub{color:#64748b;font-size:15px;line-height:1.7;margin-bottom:32px;}
        .btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
        a{text-decoration:none;padding:13px 22px;border-radius:12px;font-weight:700;font-size:14px;transition:.2s ease;}
        .primary{background:#2563eb;color:#fff;}
        .primary:hover{background:#1d4ed8;}
        .secondary{border:1px solid rgba(255,255,255,.12);color:#94a3b8;}
        .secondary:hover{background:rgba(255,255,255,.05);color:#fff;}
        .footer{margin-top:40px;color:#1e293b;font-size:12px;}
    </style>
</head>
<body>
<div class="box">
    <div class="code">404</div>
    <div class="eyebrow">RiskSignal</div>
    <h1>Page not found</h1>
    <p class="sub">
        The page you're looking for doesn't exist or has been moved.<br>
        Let's get you back on track.
    </p>
    <div class="btns">
        <a href="/" class="primary">← Go Home</a>
        @auth
            <a href="{{ route('dashboard') }}" class="secondary">Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="secondary">Login</a>
        @endauth
    </div>
    <div class="footer">RiskSignal · Portfolio Risk Intelligence</div>
</div>
</body>
</html>
