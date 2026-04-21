<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body style="font-family:Arial;background:#081120;color:#fff;padding:60px;">

<div style="max-width:420px;margin:auto;background:#111c33;padding:30px;border-radius:12px;">
    <h1>Admin Login</h1>

    @if ($errors->any())
        <div style="color:#ff8080;margin-bottom:15px;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.login.post') }}">
        @csrf

        <input type="email" name="email" placeholder="Email"
            style="width:100%;padding:12px;margin-bottom:12px;">

        <input type="password" name="password" placeholder="Password"
            style="width:100%;padding:12px;margin-bottom:12px;">

        <button type="submit"
            style="width:100%;padding:12px;background:#2563eb;color:#fff;border:none;">
            Secure Login
        </button>
    </form>
</div>

</body>
</html>