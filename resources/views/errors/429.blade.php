<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Too Many Attempts | RiskSignal</title>

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            background:#06111f;
            color:#ffffff;
            font-family:Arial, Helvetica, sans-serif;
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            padding:30px;
        }

        .box{
            width:100%;
            max-width:560px;
            background:#0c1a2f;
            border:1px solid rgba(255,255,255,.08);
            border-radius:22px;
            padding:42px;
            box-shadow:0 20px 60px rgba(0,0,0,.35);
            text-align:center;
        }

        .badge{
            display:inline-block;
            font-size:12px;
            font-weight:800;
            letter-spacing:1.4px;
            color:#facc15;
            margin-bottom:18px;
            text-transform:uppercase;
        }

        h1{
            font-size:42px;
            font-weight:900;
            margin-bottom:14px;
            line-height:1.05;
        }

        .sub{
            color:#94a3b8;
            font-size:16px;
            line-height:1.7;
            margin-bottom:28px;
        }

        .warn{
            background:#111f39;
            border-radius:14px;
            padding:18px;
            margin-bottom:26px;
            color:#38bdf8;
            font-size:15px;
        }

        .btns{
            display:flex;
            gap:12px;
            justify-content:center;
            flex-wrap:wrap;
        }

        a{
            text-decoration:none;
            padding:14px 20px;
            border-radius:12px;
            font-weight:700;
            transition:.2s ease;
        }

        .primary{
            background:#2563eb;
            color:#fff;
        }

        .primary:hover{
            opacity:.92;
        }

        .secondary{
            border:1px solid rgba(255,255,255,.12);
            color:#fff;
        }

        .secondary:hover{
            background:rgba(255,255,255,.04);
        }

        .footer{
            margin-top:24px;
            color:#64748b;
            font-size:13px;
        }
    </style>
</head>
<body>

<div class="box">

    <div class="badge">Founder Protection Active</div>

    <h1>Too Many Attempts</h1>

    <p class="sub">
        Access temporarily paused due to repeated login attempts.
        Please wait a moment before trying again.
    </p>

    <div class="warn">
        Security throttle engaged to protect the RiskSignal control panel.
    </div>

    <div class="btns">
        <a href="{{ route('admin.login') }}" class="primary">Try Again Later</a>
        <a href="/" class="secondary">Back to Site</a>
    </div>

    <div class="footer">
        RiskSignal Fortress Mode • Unauthorized access monitored
    </div>

</div>

</body>
</html>