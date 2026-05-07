<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Daily Risk Signal</title>
</head>
<body style="font-family:Arial;background:#020817;color:#fff;padding:40px;">

    <div style="max-width:600px;margin:auto;background:#0f172a;padding:30px;border-radius:14px;">

        <h1 style="margin-top:0;">
            Daily Risk Signal
        </h1>

        <p>Hello {{ $user->name }},</p>

        <p>Your latest portfolio risk signal is ready.</p>

        <div style="padding:20px;background:#111827;border-radius:10px;margin-top:20px;">

            <h2 style="margin:0;">
                Risk Score: {{ $riskScore }}
            </h2>

            <p>
                Risk Level:
                <strong>{{ $riskLevel }}</strong>
            </p>

        </div>

        <div style="margin-top:20px;padding:20px;background:#1e293b;border-radius:10px;">

            <h3 style="margin-top:0;">
                Recommended Action
            </h3>

            <p>{{ $nextAction }}</p>

        </div>

        <div style="margin-top:30px;">
            <a href="{{ url('/dashboard') }}"
               style="background:#2563eb;color:#fff;padding:12px 20px;
                      text-decoration:none;border-radius:8px;">
                Open Dashboard
            </a>
        </div>

        <p style="margin-top:40px;font-size:12px;color:#94a3b8;">
            RiskSignal • Automated Intelligence System
        </p>

    </div>

</body>
</html>