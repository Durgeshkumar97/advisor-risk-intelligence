<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Maintenance · RiskSignal</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:system-ui,sans-serif;background:#0b1220;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
.wrap{text-align:center;max-width:520px;}
.icon{font-size:3.5rem;margin-bottom:1.25rem;}
.title{font-size:1.75rem;font-weight:800;margin-bottom:.75rem;}
.sub{color:#94a3b8;font-size:.95rem;line-height:1.6;margin-bottom:1.5rem;}
.msg{display:inline-block;padding:.75rem 1.25rem;border-radius:12px;background:rgba(250,204,21,.08);border:1px solid rgba(250,204,21,.2);color:#f0c040;font-size:.875rem;font-weight:600;}
.support{color:#64748b;font-size:.8rem;margin-top:1.5rem;}
.support a{color:#f0c040;text-decoration:none;}
</style>
</head>
<body>
<div class="wrap">
    <div class="icon">🔧</div>
    <h1 class="title">We'll be right back</h1>
    <p class="sub">RiskSignal is undergoing scheduled maintenance. We'll be back shortly.</p>
    @if(isset($exception) && $exception->getMessage())
    <div class="msg">{{ $exception->getMessage() }}</div>
    @endif
    <p class="support">Questions? <a href="mailto:support@risksignal.in">support@risksignal.in</a></p>
</div>
</body>
</html>
