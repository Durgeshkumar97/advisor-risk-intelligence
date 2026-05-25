<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — RiskSignal</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:Inter,Arial,sans-serif;background:#020817;color:#f8fafc;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;}
        .logo{font-size:1.75rem;font-weight:900;letter-spacing:-1px;color:#f8fafc;text-decoration:none;margin-bottom:32px;display:block;text-align:center;}
        .logo span{color:#fbbf24;}
        .card{width:100%;max-width:420px;background:#0f172a;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:36px 32px;}
        .card-title{font-size:1.35rem;font-weight:800;margin-bottom:6px;}
        .card-sub{color:#64748b;font-size:.875rem;margin-bottom:28px;}
        label{display:block;font-size:.8rem;font-weight:700;color:#94a3b8;letter-spacing:.05em;text-transform:uppercase;margin-bottom:6px;}
        input[type="email"],input[type="password"]{width:100%;padding:.8rem 1rem;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:#020817;color:#f8fafc;font-size:.9rem;font-family:inherit;outline:none;transition:.15s ease;}
        input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
        .field-group{margin-bottom:18px;}
        .btn{width:100%;padding:.9rem;background:#2563eb;color:#fff;border:none;border-radius:12px;font-size:.95rem;font-weight:700;cursor:pointer;transition:.2s ease;font-family:inherit;margin-top:6px;}
        .btn:hover{background:#1d4ed8;}
        .alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:.75rem 1rem;border-radius:10px;font-size:.85rem;font-weight:600;margin-bottom:20px;}
        .back{display:block;text-align:center;margin-top:20px;font-size:.85rem;color:#64748b;text-decoration:none;transition:.15s;}
        .back:hover{color:#f8fafc;}
        .trust{margin-top:28px;text-align:center;font-size:.75rem;color:#1e293b;}
    </style>
</head>
<body>

    <a href="{{ url('/') }}" class="logo">Risk<span>Signal</span></a>

    <div class="card">

        <div class="card-title">Set new password</div>
        <div class="card-sub">Choose a strong password for your account.</div>

        @if($errors->any())
            <div class="alert-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="field-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email', $request->email) }}"
                    required
                    autofocus
                    autocomplete="username"
                >
            </div>

            <div class="field-group">
                <label for="password">New Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Min. 8 characters"
                >
            </div>

            <div class="field-group">
                <label for="password_confirmation">Confirm Password</label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Repeat password"
                >
            </div>

            <button type="submit" class="btn">Reset Password →</button>

        </form>

        <a href="{{ route('login') }}" class="back">← Back to Sign In</a>

    </div>

    <div class="trust">© {{ date('Y') }} RiskSignal. All rights reserved.</div>

</body>
</html>
