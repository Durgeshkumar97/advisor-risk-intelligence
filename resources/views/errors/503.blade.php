<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode — RiskSignal</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:Inter,Arial,sans-serif;background:#020817;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:30px;}
        .box{width:100%;max-width:560px;text-align:center;}
        .logo{font-size:2rem;font-weight:900;letter-spacing:-1px;margin-bottom:28px;}
        .logo span{color:#fbbf24;}
        .eyebrow{color:#fbbf24;font-size:11px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;margin-bottom:16px;}
        h1{font-size:36px;font-weight:900;line-height:1.2;margin-bottom:12px;}
        .sub{color:#64748b;font-size:15px;line-height:1.7;margin-bottom:28px;}
        .info{background:#0f172a;border:1px solid rgba(250,204,21,.15);border-radius:16px;padding:20px 24px;margin-bottom:28px;}
        .info p{color:#94a3b8;font-size:14px;line-height:1.7;}
        .info strong{color:#facc15;}
        .btns{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;}
        a{text-decoration:none;padding:13px 22px;border-radius:12px;font-weight:700;font-size:14px;transition:.2s ease;}
        .secondary{border:1px solid rgba(255,255,255,.12);color:#94a3b8;}
        .secondary:hover{background:rgba(255,255,255,.05);color:#fff;}
        .footer{margin-top:40px;color:#1e293b;font-size:12px;}
    </style>
</head>
<body>
<div class="box">
    <div class="logo">Risk<span>Signal</span></div>
    <div class="eyebrow">Scheduled Maintenance</div>
    <h1>We'll be right back</h1>
    <p class="sub">
        RiskSignal is undergoing a scheduled upgrade to improve performance<br>
        and reliability. Your data is safe.
    </p>
    <div class="info">
        <p>
            @if(isset($exception) && $exception->getMessage())
                {{ $exception->getMessage() }}
            @else
                We're performing routine maintenance to improve your experience.
                <strong>Daily risk signals will resume as normal.</strong>
                Expected downtime: under 15 minutes.
            @endif
        </p>
    </div>
    <div class="btns">
        <a href="mailto:support@risksignal.in" class="secondary">Contact Support</a>
    </div>
    <div class="footer">RiskSignal · Portfolio Risk Intelligence</div>
</div>
</body>
</html>
