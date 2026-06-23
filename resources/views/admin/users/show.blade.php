<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User #{{ $user->id }} — RiskSignal Admin</title>
    <style>
        :root{--bg:#020817;--panel:#0f172a;--panel2:#111c34;--line:rgba(255,255,255,.08);--muted:#64748b;--soft:#94a3b8;--white:#fff;--gold:#facc15;--green:#22c55e;--blue:#2563eb;--danger:#ef4444;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:Inter,Arial,sans-serif;background:var(--bg);color:var(--white);min-height:100vh;padding:32px 24px 80px;}
        .container{max-width:1100px;margin:auto;}
        .topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:28px;}
        .eyebrow{color:var(--gold);font-size:11px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;margin-bottom:6px;}
        h1{font-size:28px;font-weight:900;line-height:1.1;margin-bottom:4px;}
        .subtitle{color:var(--soft);font-size:14px;}
        .actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:12px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;border:1px solid var(--line);color:var(--white);cursor:pointer;background:transparent;transition:.15s;font-family:inherit;}
        .btn:hover{background:rgba(255,255,255,.06);}
        .btn-primary{background:var(--blue);border-color:var(--blue);}
        .btn-primary:hover{background:#1d4ed8;}
        .btn-danger{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.3);color:#fca5a5;}
        .btn-danger:hover{background:rgba(239,68,68,.22);}

        /* GRID LAYOUT */
        .main-grid{display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start;}
        @media(max-width:900px){.main-grid{grid-template-columns:1fr;}}

        /* CARDS */
        .card{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:22px;margin-bottom:18px;}
        .card-title{font-size:13px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--soft);margin-bottom:16px;}

        /* PROFILE */
        .avatar{width:56px;height:56px;border-radius:50%;background:rgba(250,204,21,.12);border:2px solid rgba(250,204,21,.25);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:800;color:var(--gold);margin-bottom:14px;}
        .user-name{font-size:1.2rem;font-weight:800;margin-bottom:4px;}
        .user-email{color:var(--soft);font-size:13px;margin-bottom:12px;}
        .meta-row{display:flex;justify-content:space-between;padding:9px 0;border-top:1px solid var(--line);font-size:13px;}
        .meta-label{color:var(--soft);}
        .meta-value{font-weight:600;text-align:right;}

        /* BADGES */
        .badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;}
        .badge-active{background:rgba(34,197,94,.15);color:#86efac;}
        .badge-trial{background:rgba(234,179,8,.15);color:#fde68a;}
        .badge-cancelled{background:rgba(239,68,68,.12);color:#fca5a5;}
        .badge-expired{background:rgba(239,68,68,.12);color:#fca5a5;}
        .badge-none{background:rgba(100,116,139,.15);color:#94a3b8;}
        .badge-admin{background:rgba(139,92,246,.15);color:#c4b5fd;}
        .badge-paid{background:rgba(34,197,94,.15);color:#86efac;}
        .badge-pending{background:rgba(234,179,8,.15);color:#fde68a;}
        .badge-failed{background:rgba(239,68,68,.12);color:#fca5a5;}
        .badge-low{background:rgba(34,197,94,.15);color:#86efac;}
        .badge-medium{background:rgba(249,115,22,.15);color:#fdba74;}
        .badge-high{background:rgba(239,68,68,.12);color:#fca5a5;}

        /* TABLE */
        table{width:100%;border-collapse:collapse;}
        th{font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--soft);padding:8px 12px;text-align:left;}
        td{padding:12px;font-size:13px;border-top:1px solid var(--line);vertical-align:middle;}
        tr:hover td{background:rgba(255,255,255,.02);}
        .mono{font-family:monospace;font-size:12px;color:var(--muted);}

        /* ALERTS */
        .alert-success{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);color:#86efac;padding:12px 16px;border-radius:10px;font-size:13px;font-weight:600;margin-bottom:18px;}
        .link-box{background:#020817;border:1px solid rgba(37,99,235,.35);border-radius:10px;padding:12px 16px;font-family:monospace;font-size:12px;color:#93c5fd;word-break:break-all;margin-top:8px;line-height:1.6;}
        .copy-btn{display:inline-block;margin-top:8px;padding:6px 14px;background:rgba(37,99,235,.15);border:1px solid rgba(37,99,235,.3);color:#93c5fd;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:.15s;}
        .copy-btn:hover{background:rgba(37,99,235,.25);}
        .copy-btn.copied{background:rgba(34,197,94,.15);color:#86efac;}

        /* EMPTY STATE */
        .empty{text-align:center;padding:2rem;color:var(--soft);font-size:13px;}
    </style>
</head>
<body>
<div class="container">

    {{-- TOPBAR --}}
    <div class="topbar">
        <div>
            <div class="eyebrow">Admin Panel · Users</div>
            <h1>
                {{ $user->name }}
                @if($user->is_admin)
                    <span class="badge badge-admin" style="margin-left:8px;font-size:12px;">Admin</span>
                @endif
            </h1>
            <p class="subtitle">User #{{ $user->id }} · Joined {{ $user->created_at->format('d M Y') }}</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.users.index') }}" class="btn">← All Users</a>
            <a href="{{ route('admin.dashboard') }}" class="btn">Dashboard</a>
        </div>
    </div>

    {{-- SUCCESS + LOGIN LINK --}}
    @if(session('success'))
        <div class="alert-success">
            ✓ {{ session('success') }}
            @if(session('login_link'))
                <div class="link-box" id="loginLinkBox">{{ session('login_link') }}</div>
                <button class="copy-btn" id="copyBtn" onclick="copyLink()">Copy Link</button>
            @endif
        </div>
    @endif

    <div class="main-grid">

        {{-- ── LEFT COLUMN ── --}}
        <div>

            {{-- PROFILE CARD --}}
            <div class="card">
                <div class="card-title">Profile</div>
                <div class="avatar">{{ strtoupper(substr($user->name, 0, 2)) }}</div>
                <div class="user-name">{{ $user->name }}</div>
                <div class="user-email">{{ $user->email }}</div>

                <div class="meta-row">
                    <span class="meta-label">Phone</span>
                    <span class="meta-value">{{ $user->phone ?: '—' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Login method</span>
                    <span class="meta-value">{{ $user->login_method ?? 'email' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Onboarded</span>
                    <span class="meta-value">
                        @if($user->onboarding_completed)
                            <span style="color:var(--green);">✓ Yes</span>
                        @else
                            <span style="color:var(--muted);">No</span>
                        @endif
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Last login</span>
                    <span class="meta-value">{{ $user->last_login_at ? $user->last_login_at->format('d M Y') : '—' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Joined</span>
                    <span class="meta-value">{{ $user->created_at->format('d M Y') }}</span>
                </div>
            </div>

            {{-- SUBSCRIPTION CARD --}}
            <div class="card">
                <div class="card-title">Subscription</div>
                @php $sub = $user->subscription; @endphp
                @if(!$sub)
                    <div class="empty">No subscription</div>
                @else
                    <div class="meta-row" style="border-top:none;padding-top:0;">
                        <span class="meta-label">Plan</span>
                        <span class="meta-value">{{ $sub->plan?->name ?? '—' }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Status</span>
                        <span class="meta-value">
                            <span class="badge badge-{{ $sub->status }}">{{ ucfirst($sub->status) }}</span>
                        </span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Starts</span>
                        <span class="meta-value">{{ $sub->starts_at?->format('d M Y') ?? '—' }}</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Ends</span>
                        <span class="meta-value">{{ $sub->ends_at?->format('d M Y') ?? '—' }}</span>
                    </div>
                    @if($sub->trial_ends_at)
                    <div class="meta-row">
                        <span class="meta-label">Trial ends</span>
                        <span class="meta-value">{{ $sub->trial_ends_at->format('d M Y') }}</span>
                    </div>
                    @endif
                @endif
            </div>

            {{-- RISK SCORE CARD --}}
            <div class="card">
                <div class="card-title">Latest Risk Score</div>
                @if($riskScore)
                    <div style="text-align:center;padding:.5rem 0;">
                        <div style="font-size:3rem;font-weight:900;line-height:1;">{{ number_format($riskScore->score, 0) }}</div>
                        <div style="margin-top:8px;">
                            <span class="badge badge-{{ strtolower($riskScore->level ?? 'none') }}">
                                {{ $riskScore->level ?? 'N/A' }} RISK
                            </span>
                        </div>
                        <div style="font-size:12px;color:var(--muted);margin-top:8px;">
                            {{ $riskScore->created_at->format('d M Y, h:i A') }}
                        </div>
                    </div>
                @else
                    <div class="empty">No risk score yet</div>
                @endif
            </div>

            {{-- SEND LOGIN LINK --}}
            <div class="card">
                <div class="card-title">Support Actions</div>
                <p style="font-size:13px;color:var(--soft);line-height:1.6;margin-bottom:14px;">
                    Generate a one-time magic login link for this user. Copy and use it to access their account for support.
                </p>
                <form method="POST" action="{{ route('admin.users.login-link', $user->id) }}"
                      onsubmit="return confirm('Generate a new login link for {{ addslashes($user->name) }}?');">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">
                        🔑 Generate Login Link
                    </button>
                </form>
            </div>

        </div>

        {{-- ── RIGHT COLUMN ── --}}
        <div>

            {{-- PORTFOLIOS --}}
            <div class="card">
                <div class="card-title">Portfolios ({{ $portfolios->count() }})</div>
                @if($portfolios->isEmpty())
                    <div class="empty">No portfolios yet</div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Risk</th>
                                <th>Score</th>
                                <th>Files</th>
                                <th>Assets</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($portfolios as $p)
                            <tr>
                                <td style="font-weight:700;">{{ $p->name }}</td>
                                <td>
                                    @if($p->risk_level)
                                        <span class="badge badge-{{ strtolower($p->risk_level) }}">{{ $p->risk_level }}</span>
                                    @else
                                        <span style="color:var(--muted);">—</span>
                                    @endif
                                </td>
                                <td>{{ $p->risk_score ? number_format($p->risk_score, 1) : '—' }}</td>
                                <td style="color:var(--soft);">{{ $p->files_count }}</td>
                                <td style="color:var(--soft);">{{ $p->assets_count }}</td>
                                <td style="color:var(--muted);white-space:nowrap;">{{ $p->created_at->format('d M Y') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- RECENT FILES --}}
            <div class="card">
                <div class="card-title">Recent Files (last 10)</div>
                @if($recentFiles->isEmpty())
                    <div class="empty">No files uploaded yet</div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Status</th>
                                <th>Size</th>
                                <th>Uploaded</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentFiles as $file)
                            @php
                                $statusBadge = match($file->status) {
                                    'processed'  => 'badge-active',
                                    'failed'     => 'badge-failed',
                                    'processing' => 'badge-pending',
                                    default      => 'badge-none',
                                };
                                $bytes = $file->file_size ?? 0;
                                $size = $bytes >= 1048576 ? round($bytes/1048576,1).' MB' : round($bytes/1024,1).' KB';
                            @endphp
                            <tr>
                                <td>
                                    <div style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $file->original_name }}">
                                        {{ $file->original_name }}
                                    </div>
                                    @if($file->portfolio)
                                        <div style="font-size:11px;color:var(--muted);">{{ $file->portfolio->name }}</div>
                                    @endif
                                </td>
                                <td><span class="badge {{ $statusBadge }}">{{ ucfirst($file->status) }}</span></td>
                                <td style="color:var(--soft);white-space:nowrap;">{{ $size }}</td>
                                <td style="color:var(--muted);white-space:nowrap;">{{ $file->created_at->format('d M Y') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- PAYMENTS --}}
            <div class="card">
                <div class="card-title">Payments</div>
                @if($payments->isEmpty())
                    <div class="empty">No payment records</div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Order ID</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($payments as $pay)
                            <tr>
                                <td>
                                    <span class="badge badge-{{ $pay->status }}">{{ ucfirst($pay->status) }}</span>
                                </td>
                                <td style="color:var(--soft);">{{ $pay->plan?->name ?? '—' }}</td>
                                <td style="font-weight:700;">
                                    @if($pay->status === 'paid')
                                        <span style="color:var(--green);">₹{{ number_format($pay->amount, 0) }}</span>
                                    @else
                                        <span style="color:var(--muted);">₹{{ number_format($pay->amount, 0) }}</span>
                                    @endif
                                </td>
                                <td><span class="mono">{{ $pay->order_id ?? '—' }}</span></td>
                                <td style="color:var(--muted);white-space:nowrap;">{{ $pay->created_at->format('d M Y') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

        </div>
    </div>

</div>

<script>
function copyLink() {
    const box = document.getElementById('loginLinkBox');
    const btn = document.getElementById('copyBtn');
    if (!box || !btn) return;
    navigator.clipboard.writeText(box.textContent.trim()).then(() => {
        btn.textContent = '✓ Copied!';
        btn.classList.add('copied');
        setTimeout(() => { btn.textContent = 'Copy Link'; btn.classList.remove('copied'); }, 2500);
    });
}
</script>

</body>
</html>
