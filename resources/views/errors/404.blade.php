<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>404 — Page Not Found · RiskSignal</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:system-ui,sans-serif;background:#0b1220;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem;}
.wrap{text-align:center;max-width:480px;}
.code{font-size:6rem;font-weight:900;color:rgba(250,204,21,.15);line-height:1;}
.title{font-size:1.75rem;font-weight:800;margin-bottom:.75rem;}
.sub{color:#94a3b8;font-size:.95rem;line-height:1.6;margin-bottom:2rem;}
.actions{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;}
.btn{padding:.8rem 1.5rem;border-radius:12px;font-weight:700;font-size:.9rem;text-decoration:none;transition:.15s ease;}
.btn-primary{background:#2563eb;color:#fff;}
.btn-primary:hover{background:#1d4ed8;}
.btn-ghost{border:1px solid rgba(255,255,255,.12);color:#94a3b8;}
.btn-ghost:hover{border-color:rgba(255,255,255,.25);color:#f1f5f9;}
</style>
</head>
<body>
<div class="wrap">
    <div class="code">404</div>
    <h1 class="title">Page not found</h1>
    <p class="sub">The page you're looking for doesn't exist or has been moved.</p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn btn-primary">← Go Home</a>
        @auth
        <a href="{{ route('dashboard') }}" class="btn btn-ghost">Dashboard</a>
        @else
        <a href="{{ route('login') }}" class="btn btn-ghost">Sign In</a>
        @endauth
    </div>
</div>
</body>
</html>
