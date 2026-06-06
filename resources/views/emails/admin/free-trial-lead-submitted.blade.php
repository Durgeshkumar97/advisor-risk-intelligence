<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New RiskSignal free trial lead</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;color:#0f172a;font-family:Arial,sans-serif;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;padding:28px;">
        <p style="margin:0 0 8px;color:#b45309;font-size:12px;font-weight:700;text-transform:uppercase;">
            RiskSignal Lead Notification
        </p>

        <h1 style="margin:0 0 20px;font-size:24px;line-height:1.3;">
            New free trial lead submitted
        </h1>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
            <tr>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;font-weight:700;">Advisor name</td>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;">{{ $advisorName }}</td>
            </tr>
            <tr>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;font-weight:700;">Phone</td>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;">{{ $whatsapp }}</td>
            </tr>
            <tr>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;font-weight:700;">Email</td>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;">{{ $email }}</td>
            </tr>
            <tr>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;font-weight:700;">Firm name</td>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;">{{ $firmName }}</td>
            </tr>
            <tr>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;font-weight:700;">Timestamp</td>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;">{{ $submittedAt }}</td>
            </tr>
            <tr>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;font-weight:700;">IP address</td>
                <td style="padding:10px 0;border-top:1px solid #e2e8f0;">{{ $ipAddress }}</td>
            </tr>
        </table>

        <p style="margin:20px 0 0;color:#475569;font-size:13px;">
            Submission UUID: {{ $submissionUuid }}
        </p>
    </div>
</body>
</html>
