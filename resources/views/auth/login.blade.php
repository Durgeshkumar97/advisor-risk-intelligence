<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — RiskSignal</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:Inter,Arial,sans-serif;background:#020817;color:#f8fafc;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;}

        /* LOGO */
        .logo{font-size:1.75rem;font-weight:900;letter-spacing:-1px;color:#f8fafc;text-decoration:none;margin-bottom:32px;display:block;text-align:center;}
        .logo span{color:#fbbf24;}

        /* CARD */
        .card{width:100%;max-width:420px;background:#0f172a;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:36px 32px;}

        .card-title{font-size:1.35rem;font-weight:800;margin-bottom:6px;}
        .card-sub{color:#64748b;font-size:.875rem;margin-bottom:28px;}

        /* FIELDS */
        label{display:block;font-size:.8rem;font-weight:700;color:#94a3b8;letter-spacing:.05em;text-transform:uppercase;margin-bottom:6px;}
        input[type="email"],input[type="password"]{
            width:100%;padding:.8rem 1rem;
            border-radius:12px;
            border:1px solid rgba(255,255,255,.1);
            background:#020817;
            color:#f8fafc;
            font-size:.9rem;
            font-family:inherit;
            outline:none;
            transition:.15s ease;
        }
        input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
        .field-group{margin-bottom:18px;}

        /* REMEMBER + FORGOT ROW */
        .meta-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;}
        .remember{display:flex;align-items:center;gap:8px;font-size:.82rem;color:#64748b;cursor:pointer;}
        .remember input[type="checkbox"]{
            width:16px;height:16px;accent-color:#2563eb;cursor:pointer;
        }
        .forgot{font-size:.82rem;color:#64748b;text-decoration:none;transition:.15s ease;}
        .forgot:hover{color:#f8fafc;}

        /* SUBMIT */
        .btn{
            width:100%;padding:.9rem;
            background:#2563eb;color:#fff;
            border:none;border-radius:12px;
            font-size:.95rem;font-weight:700;
            cursor:pointer;transition:.2s ease;
            font-family:inherit;
        }
        .btn:hover{background:#1d4ed8;}

        /* ERROR / STATUS */
        .alert-error{
            background:rgba(239,68,68,.1);
            border:1px solid rgba(239,68,68,.2);
            color:#fca5a5;
            padding:.75rem 1rem;
            border-radius:10px;
            font-size:.85rem;
            font-weight:600;
            margin-bottom:20px;
        }
        .alert-status{
            background:rgba(34,197,94,.1);
            border:1px solid rgba(34,197,94,.2);
            color:#86efac;
            padding:.75rem 1rem;
            border-radius:10px;
            font-size:.85rem;
            font-weight:600;
            margin-bottom:20px;
        }

        /* DIVIDER */
        .divider{border:none;border-top:1px solid rgba(255,255,255,.06);margin:24px 0;}

        /* FOOTER LINKS */
        .footer-links{text-align:center;font-size:.82rem;color:#64748b;margin-top:24px;}
        .footer-links a{color:#64748b;text-decoration:underline;transition:.15s ease;}
        .footer-links a:hover{color:#f8fafc;}

        /* TRUST */
        .trust{
            margin-top:28px;
            text-align:center;
            font-size:.75rem;
            color:#1e293b;
        }
    </style>
</head>
<body>

    {{-- LOGO --}}
    <a href="{{ url('/') }}" class="logo">Risk<span>Signal</span></a>

    {{-- CARD --}}
    <div class="card">

        <div class="card-title">Welcome back</div>
        <div class="card-sub">Sign in to your RiskSignal account</div>

        {{-- STATUS MESSAGE (e.g. password reset sent) --}}
        @if(session('status'))
            <div class="alert-status">{{ session('status') }}</div>
        @endif

        {{-- VALIDATION ERRORS --}}
        @if($errors->any())
            <div class="alert-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            {{-- EMAIL --}}
            <div class="field-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="you@example.com"
                >
            </div>

            {{-- PASSWORD --}}
            <div class="field-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                >
            </div>

            {{-- REMEMBER + FORGOT --}}
            <div class="meta-row">
                <label class="remember">
                    <input type="checkbox" name="remember" id="remember_me">
                    Remember me
                </label>
                @if(Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="forgot">
                        Forgot password?
                    </a>
                @endif
            </div>

            {{-- SUBMIT --}}
            <button type="submit" class="btn">Sign In →</button>

        </form>

        <hr class="divider">

        {{-- BACK TO SITE --}}
        <div style="text-align:center;font-size:.85rem;color:#64748b;">
            Don't have an account?
            <a href="{{ url('/#free-trial') }}" style="color:#fbbf24;font-weight:700;text-decoration:none;">
                Start free trial →
            </a>
        </div>

    </div>

    {{-- FOOTER LINKS --}}
    <div class="footer-links" style="margin-top:20px;">
        <a href="{{ route('terms') }}">Terms</a>
        &nbsp;·&nbsp;
        <a href="{{ route('privacy') }}">Privacy</a>
        &nbsp;·&nbsp;
        <a href="{{ route('refund') }}">Refund</a>
    </div>

    <div class="trust">© {{ date('Y') }} RiskSignal. All rights reserved.</div>

</body>
</html>
