<!DOCTYPE html>
<html lang="en">
<head>
    <title>Founder Access | RiskSignal</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            font-family:Arial, Helvetica, sans-serif;
            background:#020817;
            color:#ffffff;
            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            padding:24px;
        }

        .card{
            width:100%;
            max-width:460px;
            background:#0f172a;
            border:1px solid rgba(255,255,255,.08);
            border-radius:18px;
            padding:38px;
            box-shadow:0 25px 60px rgba(0,0,0,.45);
        }

        .badge{
            color:#facc15;
            font-size:11px;
            font-weight:800;
            letter-spacing:1.6px;
            text-transform:uppercase;
            margin-bottom:14px;
        }

        h1{
            font-size:40px;
            line-height:1.05;
            font-weight:900;
            margin-bottom:10px;
        }

        .sub{
            color:#94a3b8;
            font-size:14px;
            line-height:1.7;
            margin-bottom:24px;
        }

        .error{
            background:#3b0d0d;
            color:#ffffff;
            padding:12px 14px;
            border-radius:10px;
            margin-bottom:16px;
            font-size:14px;
        }

        .input{
            width:100%;
            padding:14px 16px;
            margin-bottom:14px;
            border-radius:12px;
            border:1px solid rgba(255,255,255,.08);
            background:#020617;
            color:#ffffff;
            font-size:15px;
        }

        .input:focus{
            outline:none;
            border-color:#2563eb;
        }

        .input::placeholder{
            color:#64748b;
        }

        .btn{
            width:100%;
            padding:14px;
            background:#2563eb;
            color:#ffffff;
            border:none;
            border-radius:12px;
            font-size:15px;
            font-weight:700;
            cursor:pointer;
            transition:.2s ease;
        }

        .btn:hover{
            background:#1d4ed8;
        }

        .footer{
            margin-top:18px;
            text-align:center;
            color:#64748b;
            font-size:12px;
        }

        .pulse{
            display:inline-block;
            width:8px;
            height:8px;
            border-radius:50%;
            background:#22c55e;
            margin-right:6px;
        }
    </style>
</head>

<body>

<div class="card">

    <div class="badge">Founder Secure Access</div>

    <h1>Control Room</h1>

    <div class="sub">
        Authorized personnel only.<br>
        All access attempts are monitored.
    </div>

    @if ($errors->any())
        <div class="error">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.login.post') }}">
        @csrf

        <input
            type="email"
            name="email"
            placeholder="Founder email"
            class="input"
            required
            autofocus
        >

        <input
            type="password"
            name="password"
            placeholder="Password"
            class="input"
            required
        >

        <button type="submit" class="btn">
            Enter Control Room
        </button>
    </form>

    <div class="footer">
        <span class="pulse"></span>
        Encrypted session • RiskSignal Fortress Mode
    </div>

</div>

</body>
</html>