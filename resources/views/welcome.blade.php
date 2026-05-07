<!DOCTYPE html>
<html lang="en">
<head>
    <title>RiskSignal</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            font-family:Inter,Arial,sans-serif;
            background:#020817;
            color:#fff;
            min-height:100vh;

            display:flex;
            justify-content:center;
            align-items:center;

            padding:40px;
        }

        .card{
            width:100%;
            max-width:700px;

            background:#081226;

            border:1px solid rgba(255,255,255,.08);

            border-radius:24px;

            padding:50px;
        }

        .badge{
            display:inline-block;

            padding:8px 14px;

            background:rgba(59,130,246,.15);

            color:#60a5fa;

            border-radius:999px;

            font-size:12px;
            font-weight:700;

            margin-bottom:20px;
        }

        h1{
            font-size:58px;
            line-height:1;
            margin-bottom:18px;
        }

        p{
            color:#94a3b8;
            line-height:1.8;
            font-size:17px;
        }

        .actions{
            margin-top:35px;

            display:flex;
            gap:16px;
            flex-wrap:wrap;
        }

        .btn{
            text-decoration:none;

            padding:14px 20px;

            border-radius:14px;

            font-weight:700;

            transition:.2s;
        }

        .btn-primary{
            background:#2563eb;
            color:#fff;
        }

        .btn-primary:hover{
            background:#1d4ed8;
        }

        .btn-secondary{
            border:1px solid rgba(255,255,255,.12);
            color:#fff;
        }

        .btn-secondary:hover{
            background:rgba(255,255,255,.04);
        }

        @media(max-width:640px){

            .card{
                padding:30px;
            }

            h1{
                font-size:42px;
            }

        }

    </style>
</head>
<body>

    <div class="card">

        <div class="badge">
            AI Portfolio Intelligence
        </div>

        <h1>
            RiskSignal
        </h1>

        <p>
            Professional AI-powered portfolio risk analysis platform
            for advisors, wealth managers, and serious investors.
        </p>

        <div class="actions">

            <a href="/pricing" class="btn btn-primary">
                View Pricing
            </a>

            <a href="/login" class="btn btn-secondary">
                Login
            </a>

        </div>

    </div>

</body>
</html>