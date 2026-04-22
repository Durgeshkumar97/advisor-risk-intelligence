<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <title>Founder Access | RiskSignal</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        :root{
            --bg:#020817;
            --panel:#0f172a;
            --panel-2:#111c34;
            --line:rgba(255,255,255,.08);
            --text:#ffffff;
            --muted:#94a3b8;
            --soft:#64748b;
            --blue:#2563eb;
            --blue-hover:#1d4ed8;
            --gold:#facc15;
            --green:#22c55e;
            --danger:#ef4444;
        }

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        html,
        body{
            width:100%;
            min-height:100%;
        }

        body{
            font-family:Inter, Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at top right, #12203f 0%, transparent 28%),
                radial-gradient(circle at bottom left, #091224 0%, transparent 24%),
                var(--bg);

            color:var(--text);

            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;

            padding:24px;
            overflow-x:hidden;
        }

        .wrapper{
            width:100%;
            max-width:460px;
        }

        .card{
            width:100%;
            background:rgba(15,23,42,.96);
            border:1px solid var(--line);
            border-radius:22px;
            padding:38px;
            box-shadow:
                0 25px 60px rgba(0,0,0,.45),
                inset 0 1px 0 rgba(255,255,255,.03);

            backdrop-filter:blur(10px);
        }

        .badge{
            display:inline-flex;
            align-items:center;
            gap:8px;

            color:var(--gold);
            font-size:11px;
            font-weight:800;
            letter-spacing:1.6px;
            text-transform:uppercase;

            margin-bottom:18px;
        }

        .badge-dot{
            width:7px;
            height:7px;
            border-radius:50%;
            background:var(--gold);
        }

        h1{
            font-size:42px;
            line-height:1.04;
            font-weight:900;
            margin-bottom:10px;
            letter-spacing:-0.02em;
        }

        .sub{
            color:var(--muted);
            font-size:14px;
            line-height:1.75;
            margin-bottom:26px;
        }

        .error{
            background:rgba(239,68,68,.14);
            border:1px solid rgba(239,68,68,.22);
            color:#fff;
            padding:13px 14px;
            border-radius:12px;
            margin-bottom:16px;
            font-size:14px;
        }

        .form-group{
            margin-bottom:14px;
        }

        .label{
            display:block;
            font-size:12px;
            color:var(--muted);
            margin-bottom:8px;
            font-weight:700;
            letter-spacing:.03em;
        }

        .input{
            width:100%;
            padding:15px 16px;
            border-radius:14px;
            border:1px solid var(--line);
            background:#020617;
            color:#fff;
            font-size:15px;
            transition:.2s ease;
        }

        .input::placeholder{
            color:var(--soft);
        }

        .input:focus{
            outline:none;
            border-color:var(--blue);
            box-shadow:0 0 0 3px rgba(37,99,235,.18);
        }

        .row{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            margin:2px 0 18px;
            flex-wrap:wrap;
        }

        .remember{
            display:flex;
            align-items:center;
            gap:8px;
            color:var(--muted);
            font-size:13px;
        }

        .remember input{
            accent-color:var(--blue);
        }

        .mini-link{
            color:var(--gold);
            font-size:13px;
            text-decoration:none;
        }

        .mini-link:hover{
            opacity:.9;
        }

        .btn{
            width:100%;
            padding:15px;
            background:linear-gradient(180deg,var(--blue),var(--blue-hover));
            color:#fff;
            border:none;
            border-radius:14px;
            font-size:15px;
            font-weight:800;
            cursor:pointer;
            transition:.22s ease;
            box-shadow:0 12px 30px rgba(37,99,235,.22);
        }

        .btn:hover{
            transform:translateY(-2px);
            filter:brightness(1.05);
        }

        .btn:active{
            transform:translateY(0);
        }

        .footer{
            margin-top:18px;
            text-align:center;
            color:var(--soft);
            font-size:12px;
            line-height:1.6;
        }

        .pulse{
            display:inline-block;
            width:8px;
            height:8px;
            border-radius:50%;
            background:var(--green);
            margin-right:6px;
            box-shadow:0 0 0 0 rgba(34,197,94,.55);
            animation:pulse 1.8s infinite;
        }

        .shield{
            margin-top:18px;
            padding-top:18px;
            border-top:1px solid rgba(255,255,255,.05);
            color:var(--muted);
            font-size:12px;
            text-align:center;
        }

        @keyframes pulse{
            0%{
                box-shadow:0 0 0 0 rgba(34,197,94,.55);
            }
            70%{
                box-shadow:0 0 0 10px rgba(34,197,94,0);
            }
            100%{
                box-shadow:0 0 0 0 rgba(34,197,94,0);
            }
        }

        @media (max-width:640px){

            body{
                padding:18px;
                align-items:center;
            }

            .card{
                padding:26px 22px;
                border-radius:18px;
            }

            h1{
                font-size:34px;
            }

            .sub{
                font-size:13px;
            }

            .input,
            .btn{
                padding:14px;
            }

            .row{
                align-items:flex-start;
                flex-direction:column;
            }
        }

        @media (max-width:420px){

            h1{
                font-size:30px;
            }

            .card{
                padding:22px 18px;
            }
        }
    </style>
</head>

<body>

<div class="wrapper">

    <div class="card">

        <div class="badge">
            <span class="badge-dot"></span>
            Founder Secure Access
        </div>

        <h1>Control Room</h1>

        <div class="sub">
            Authorized personnel only.<br>
            All access attempts are monitored and logged.
        </div>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.post') }}">
            @csrf

            <div class="form-group">
                <label class="label">Founder Email</label>

                <input
                    type="email"
                    name="email"
                    class="input"
                    placeholder="you@company.com"
                    value="{{ old('email') }}"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label class="label">Password</label>

                <input
                    type="password"
                    name="password"
                    class="input"
                    placeholder="Enter password"
                    required
                >
            </div>

            <div class="row">

                <label class="remember">
                    <input type="checkbox" name="remember">
                    Keep me signed in
                </label>

                <a href="#" class="mini-link">
                    Protected Access
                </a>

            </div>

            <button type="submit" class="btn">
                Enter Control Room
            </button>

        </form>

        <div class="footer">
            <span class="pulse"></span>
            Encrypted session • RiskSignal Fortress Mode
        </div>

        <div class="shield">
            IP monitoring enabled • Device fingerprint active
        </div>

    </div>

</div>

</body>
</html>