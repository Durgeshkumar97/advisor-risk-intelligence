<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>Daily Risk Signal — RiskSignal</title>
    <!--[if mso]>
    <noscript>
        <xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
    </noscript>
    <![endif]-->
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0; mso-table-rspace: 0; }
        img { -ms-interpolation-mode: bicubic; }
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; }
        .gmail-fix { display: none; display: none !important; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .stack { display: block !important; width: 100% !important; }
            .metric-cell { width: 50% !important; display: inline-block !important; }
            h1 { font-size: 28px !important; }
        }
    </style>
</head>

@php
    $levelColor = match($riskLevel) {
        'HIGH'   => '#ef4444',
        'MEDIUM' => '#f97316',
        default  => '#22c55e',
    };
    $levelBg = match($riskLevel) {
        'HIGH'   => 'rgba(239,68,68,.12)',
        'MEDIUM' => 'rgba(249,115,22,.12)',
        default  => 'rgba(34,197,94,.12)',
    };
    $levelEmoji = match($riskLevel) {
        'HIGH'   => '🔴',
        'MEDIUM' => '🟡',
        default  => '🟢',
    };
    $scoreBar = round(($riskScore / 100) * 100);
    $barColor = match(true) {
        $riskScore >= 70 => '#ef4444',
        $riskScore >= 40 => '#f97316',
        default          => '#22c55e',
    };
    $dateFormatted = now()->format('l, d F Y');
@endphp

<body style="margin:0;padding:0;background:#020817;font-family:Arial,Helvetica,sans-serif;">

    {{-- PREHEADER TEXT (hidden, shows in inbox preview) --}}
    <span style="display:none;max-height:0;overflow:hidden;mso-hide:all;">
        {{ $levelEmoji }} Risk Score: {{ $riskScore }} ({{ $riskLevel }}) — {{ $nextAction }}
    </span>

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#020817;min-height:100vh;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                {{-- ── CONTAINER ── --}}
                <table class="container" width="600" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:600px;width:100%;">

                    {{-- TOP BAR --}}
                    <tr>
                        <td style="padding-bottom:24px;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td>
                                        <span style="color:#facc15;font-size:11px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;">
                                            ● RISKSIGNAL
                                        </span>
                                    </td>
                                    <td align="right">
                                        <span style="color:#64748b;font-size:12px;">{{ $dateFormatted }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- MAIN CARD --}}
                    <tr>
                        <td style="background:#0f172a;border:1px solid rgba(255,255,255,.08);border-radius:20px;overflow:hidden;">

                            {{-- HEADER --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:32px 36px 24px;border-bottom:1px solid rgba(255,255,255,.06);">
                                        <p style="margin:0 0 8px;color:#94a3b8;font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;">
                                            Daily Portfolio Intelligence
                                        </p>
                                        <h1 style="margin:0;color:#ffffff;font-size:32px;font-weight:900;line-height:1.1;letter-spacing:-.02em;">
                                            Good morning,<br>{{ $user->name }}.
                                        </h1>
                                        <p style="margin:12px 0 0;color:#94a3b8;font-size:14px;line-height:1.6;">
                                            Your daily risk signal is ready. Here's what you need to know before your first client call.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            {{-- RISK SCORE HERO --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:32px 36px;background:rgba(255,255,255,.01);border-bottom:1px solid rgba(255,255,255,.06);">

                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="vertical-align:middle;">
                                                    <p style="margin:0 0 4px;color:#64748b;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;">
                                                        Portfolio Risk Score
                                                    </p>
                                                    <span style="font-size:72px;font-weight:900;color:#ffffff;line-height:1;letter-spacing:-.04em;">
                                                        {{ $riskScore }}
                                                    </span>
                                                    <span style="font-size:24px;color:#64748b;font-weight:400;line-height:1;">
                                                        /100
                                                    </span>
                                                    <br>
                                                    <span style="display:inline-block;margin-top:10px;padding:4px 14px;border-radius:999px;
                                                                 background:{{ $levelBg }};color:{{ $levelColor }};
                                                                 font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;">
                                                        {{ $levelEmoji }} {{ $riskLevel }} RISK
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>

                                        {{-- SCORE BAR --}}
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px;">
                                            <tr>
                                                <td>
                                                    <div style="background:rgba(255,255,255,.06);border-radius:999px;height:8px;overflow:hidden;">
                                                        <!--[if mso]><v:rect xmlns:v="urn:schemas-microsoft-com:vml" style="width:{{ $scoreBar }}%;height:8px;" fillcolor="{{ $barColor }}" stroked="false"><v:fill type="solid"/></v:rect><![endif]-->
                                                        <div style="width:{{ $scoreBar }}%;height:8px;background:{{ $barColor }};border-radius:999px;"></div>
                                                    </div>
                                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:6px;">
                                                        <tr>
                                                            <td style="color:#22c55e;font-size:11px;font-weight:700;">0 — LOW</td>
                                                            <td align="center" style="color:#f97316;font-size:11px;font-weight:700;">MEDIUM</td>
                                                            <td align="right" style="color:#ef4444;font-size:11px;font-weight:700;">HIGH — 100</td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>

                                    </td>
                                </tr>
                            </table>

                            {{-- RECOMMENDED ACTION --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:28px 36px;border-bottom:1px solid rgba(255,255,255,.06);">
                                        <p style="margin:0 0 10px;color:#64748b;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;">
                                            Today's Recommended Action
                                        </p>
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="background:rgba(250,204,21,.06);border:1px solid rgba(250,204,21,.15);border-radius:12px;padding:16px 18px;">
                                                    <span style="font-size:24px;">⚡</span>
                                                    <p style="margin:8px 0 0;color:#ffffff;font-size:15px;font-weight:700;line-height:1.5;">
                                                        {{ $nextAction }}
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            {{-- CLIENT SCRIPT --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:28px 36px;border-bottom:1px solid rgba(255,255,255,.06);">
                                        <p style="margin:0 0 10px;color:#64748b;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;">
                                            Ready-to-Use Client Script
                                        </p>
                                        <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="background:#111c34;border:1px solid rgba(255,255,255,.06);border-left:3px solid #2563eb;border-radius:0 10px 10px 0;padding:16px 18px;">
                                                    <p style="margin:0;color:#94a3b8;font-size:13px;line-height:1.7;font-style:italic;">
                                                        @if($riskLevel === 'HIGH')
                                                            "Markets are showing elevated volatility this week. I've reviewed your portfolio and wanted to flag a few areas that need attention. Let's connect briefly to discuss your allocation — it's important we act strategically rather than emotionally."
                                                        @elseif($riskLevel === 'MEDIUM')
                                                            "Markets are slightly volatile this week. Your portfolio is holding steady but I'm keeping a close eye on a couple of positions. Stay invested and avoid making changes based on short-term noise — your plan is designed for this."
                                                        @else
                                                            "Markets are calm this week and your portfolio is well-positioned. No action needed — just wanted you to know everything is on track. Consistency is the key to long-term wealth."
                                                        @endif
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin:10px 0 0;color:#64748b;font-size:11px;">
                                            Copy and send directly to clients by email or message.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            {{-- CTA --}}
                            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="padding:28px 36px;text-align:center;">
                                        <p style="margin:0 0 20px;color:#94a3b8;font-size:13px;line-height:1.6;">
                                            View your full portfolio analysis, upload new portfolios,<br>
                                            and manage your account from your dashboard.
                                        </p>
                                        <a href="{{ url('/dashboard') }}"
                                           style="display:inline-block;padding:14px 32px;
                                                  background:#2563eb;color:#ffffff;
                                                  text-decoration:none;border-radius:12px;
                                                  font-size:14px;font-weight:800;letter-spacing:.02em;">
                                            Open My Dashboard →
                                        </a>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    {{-- FOOTER --}}
                    <tr>
                        <td style="padding:28px 0 0;text-align:center;">
                            <p style="margin:0 0 6px;color:#334155;font-size:12px;">
                                RiskSignal · Daily Portfolio Intelligence for IFAs
                            </p>
                            <p style="margin:0;color:#1e293b;font-size:11px;">
                                You're receiving this because you have an active RiskSignal subscription.<br>
                                Questions? Reply to this email — we respond within 2 hours.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
