<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $user->name }} — RiskSignal Admin</title>
    <style>
        :root{--bg:#020817;--panel:#0f172a;--line:rgba(255,255,255,.08);--muted:#64748b;--soft:#94a3b8;--white:#fff;--gold:#facc15;--green:#22c55e;--blue:#2563eb;--red:#ef4444;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:Inter,Arial,sans-serif;background:var(--bg);color:var(--white);min-height:100vh;padding:32px 24px 80px;}
        .container{max-width:1100px;margin:auto;}
        .topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:28px;}
        .eyebrow{color:var(--gold);font-size:11px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;margin-bottom:6px;}
        h1{font-size:28px;font-weight:900;line-height:1.1;}
        .btn{display:inline-flex;align-items:center;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;border:1px solid var(--line);color:var(--white);cursor:pointer;background:transparent;transition:.15s;}
        .btn:hover{background:rgba(255,255,255,.06);}
        .card{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:1.5rem;margin-bottom:1.25rem;}
        .card-title{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--soft);margin-bottom:1rem;}
        .row{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--line);font-size:13px;}
        .row:last-child{border-bottom:none;}
        .label{color:var(--muted);}
        .val{font-weight:600;text-align:right;}
        .badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;}
        .badge-active{background:rgba(34,197,94,.15);color:#86efac;}
        .badge-trial{background:rgba(234,179,8,.15);color:#fde68a;}
        .badge-cancelled{background:rgba(239,68,68,.12);color:#fca5a5;}
        .badge-none{background:rgba(100,116,139,.15);color:#94a3b8;}
        table{width:100%;border-collapse:collapse;}
        th{background:rgba(255,255,255,.03);padding:10px 14px;text-align:left;font-size:11px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--soft);}
        td{padding:12px 14px;font-size:13px;border-top:1px solid var(--line);vertical-align:middle;}
        .alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#86efac;padding:.75rem 1rem;border-radius:10px;font-size:13px;margin-bottom:1.25rem;}
        .link-box{background:rgba(255,255,255,.04);border:1px solid var(--line);border-radius:10px;padding:.75rem 1rem;font-family:monospace;font-size:12px;word-break:break-all;color:#a5b4fc;cursor:pointer;margin-top:.75rem;}
    </style>
</head>
<body>
<div class="container">

    <div class="topbar">
        <div>
            <div class="eyebrow">Admin → Users</div>
            <h1>{{ $user->name }}</h1>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('admin.users.index') }}" class="btn">← All Users</a>
            <a href="{{ route('admin.dashboard') }}" class="btn">Dashboard</a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert-success">✓ {{ session('success') }}</div>
    @endif

    @if(session('login_link'))
    <div class="alert-success">
        <strong>Magic Login Link</strong> — share securely, expires on first use:
        <div class="link-box" onclick="navigator.clipboard.writeText(this.textContent);this.style.background='rgba(34,197,94,.1)';" title="Click to copy">
            {{ session('login_link') }}
        </div>
    </div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

        {{-- PROFILE --}}
        <div class="card">
            <div class="card-title">Profile</div>
            <div class="row"><span class="label">ID</span><span class="val">{{ $user->id }}</span></div>
            <div class="row"><span class="label">Name</span><span class="val">{{ $user->name }}</span></div>
            <div class="row"><span class="label">Email</span><span class="val">{{ $user->email }}</span></div>
            <div class="row"><span class="label">Phone</span><span class="val">{{ $user->phone ?: '—' }}</span></div>
            <div class="row"><span class="label">Admin</span><span class="val">{{ $user->is_admin ? '✓ Yes' : 'No' }}</span></div>
            <div class="row"><span class="label">Login method</span><span class="val">{{ $user->login_method ?: 'email' }}</span></div>
            <div class="row"><span class="label">Onboarding</span><span class="val">{{ $user->onboarding_completed ? '✓ Complete' : 'Pending' }}</span></div>
            <div class="row"><span class="label">Last login</span><span class="val">{{ $user->last_login_at?->format('d M Y, H:i') ?? '—' }}</span></div>
            <div class="row"><span class="label">Joined</span><span class="val">{{ $user->created_at->format('d M Y') }}</span></div>
        </div>

        {{-- SUBSCRIPTION --}}
        <div class="card">
            <div class="card-title">Subscription</div>
            @if($user->subscription)
            @php $sub = $user->subscription; @endphp
            <div class="row"><span class="label">Plan</span><span class="val">{{ $sub->plan?->name ?? '—' }}</span></div>
            <div class="row">
                <span class="label">Status</span>
                <span class="val">
                    @if($sub->status==='active')<span class="badge badge-active">Active</span>
                    @elseif($sub->status==='trial')<span class="badge badge-trial">Trial</span>
                    @elseif($sub->status==='cancelled')<span class="badge badge-cancelled">Cancelled</span>
                    @else<span class="badge badge-none">{{ ucfirst($sub->status) }}</span>
                    @endif
                </span>
            </div>
            <div class="row"><span class="label">Starts</span><span class="val">{{ $sub->starts_at?->format('d M Y') ?? '—' }}</span></div>
            <div class="row"><span class="label">Ends</span><span class="val">{{ $sub->ends_at?->format('d M Y') ?? '—' }}</span></div>
            <div class="row"><span class="label">Trial start</span><span class="val">{{ $sub->trial_started_at?->format('d M Y') ?? '—' }}</span></div>
            <div class="row"><span class="label">Trial end</span><span class="val">{{ $sub->trial_ends_at?->format('d M Y') ?? '—' }}</span></div>
            <div class="row"><span class="label">Days left</span><span class="val">{{ $sub->daysRemaining() }}</span></div>
            @else
            <div style="color:var(--muted);font-size:13px;padding:.5rem 0;">No subscription found.</div>
            @endif
        </div>

    </div>

    {{-- RISK SCORE --}}
    @if($riskScore)
    <div class="card">
        <div class="card-title">Latest Risk Score</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;">
            <div style="text-align:center;padding:1rem;background:rgba(255,255,255,.03);border-radius:10px;">
                <div style="font-size:2.5rem;font-weight:900;color:var(--gold);">{{ $riskScore->score ?? '—' }}</div>
                <div style="font-size:11px;color:var(--muted);margin-top:.25rem;">Risk Score</div>
            </div>
            <div style="padding:1rem;background:rgba(255,255,255,.03);border-radius:10px;">
                <div style="font-size:12px;color:var(--muted);margin-bottom:.5rem;">Generated</div>
                <div style="font-size:13px;">{{ $riskScore->created_at->format('d M Y, H:i') }}</div>
            </div>
        </div>
    </div>
    @endif

    {{-- PORTFOLIOS --}}
    @if($portfolios->count())
    <div class="card">
        <div class="card-title">Portfolios ({{ $portfolios->count() }})</div>
        <table>
            <thead><tr><th>#</th><th>Name</th><th>Files</th><th>Assets</th><th>Created</th></tr></thead>
            <tbody>
                @foreach($portfolios as $p)
                <tr>
                    <td style="color:var(--muted);">{{ $p->id }}</td>
                    <td style="font-weight:600;">{{ $p->name }}</td>
                    <td style="color:var(--soft);">{{ $p->files_count }}</td>
                    <td style="color:var(--soft);">{{ $p->assets_count }}</td>
                    <td style="color:var(--muted);">{{ $p->created_at->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- RECENT FILES --}}
    @if($recentFiles->count())
    <div class="card">
        <div class="card-title">Recent Files (last 10)</div>
        <table>
            <thead><tr><th>File</th><th>Portfolio</th><th>Size</th><th>Uploaded</th></tr></thead>
            <tbody>
                @foreach($recentFiles as $f)
                <tr>
                    <td style="font-weight:600;">{{ $f->original_name }}</td>
                    <td style="color:var(--soft);">{{ $f->portfolio?->name ?? '—' }}</td>
                    <td style="color:var(--muted);">{{ number_format(($f->size ?? 0) / 1024, 1) }} KB</td>
                    <td style="color:var(--muted);">{{ $f->created_at->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- PAYMENTS --}}
    @if($payments->count())
    <div class="card">
        <div class="card-title">Payments</div>
        <table>
            <thead><tr><th>Order ID</th><th>Plan</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @foreach($payments as $p)
                <tr>
                    <td style="font-family:monospace;font-size:12px;color:var(--soft);">{{ Str::limit($p->order_id, 24) }}</td>
                    <td>{{ $p->plan?->name ?? '—' }}</td>
                    <td>₹{{ number_format($p->amount, 0) }}</td>
                    <td>
                        @if($p->status==='paid')<span class="badge badge-active">Paid</span>
                        @elseif($p->status==='pending')<span class="badge badge-trial">Pending</span>
                        @else<span class="badge badge-cancelled">{{ ucfirst($p->status) }}</span>
                        @endif
                    </td>
                    <td style="color:var(--muted);">{{ $p->created_at->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- MAGIC LOGIN --}}
    <div class="card">
        <div class="card-title">Admin Actions</div>
        <form method="POST" action="{{ route('admin.users.login-link', $user->id) }}">
            @csrf
            <button type="submit" class="btn" style="background:rgba(250,204,21,.08);border-color:rgba(250,204,21,.3);color:var(--gold);">
                🔑 Generate Magic Login Link
            </button>
            <p style="color:var(--muted);font-size:12px;margin-top:.5rem;">One-time link — auto-expires after first use. Use to log in as this user.</p>
        </form>
    </div>

</div>
</body>
</html>
