<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Payments — RiskSignal Admin</title>
    <style>
        :root{--bg:#020817;--panel:#0f172a;--line:rgba(255,255,255,.08);--muted:#64748b;--soft:#94a3b8;--white:#fff;--gold:#facc15;--green:#22c55e;--blue:#2563eb;--red:#ef4444;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:Inter,Arial,sans-serif;background:var(--bg);color:var(--white);min-height:100vh;padding:32px 24px 80px;}
        .container{max-width:1400px;margin:auto;}
        .topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:28px;}
        .eyebrow{color:var(--gold);font-size:11px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;margin-bottom:6px;}
        h1{font-size:32px;font-weight:900;line-height:1.05;margin-bottom:6px;}
        .subtitle{color:var(--soft);font-size:14px;}
        .btn{display:inline-flex;align-items:center;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;border:1px solid var(--line);color:var(--white);cursor:pointer;background:transparent;transition:.15s;}
        .btn:hover{background:rgba(255,255,255,.06);}
        .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px;}
        .stat{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:1.25rem;}
        .stat-label{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:.4rem;}
        .stat-val{font-size:1.75rem;font-weight:900;}
        .filter-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;}
        .filter-bar input,.filter-bar select{padding:9px 14px;border-radius:10px;border:1px solid var(--line);background:var(--panel);color:var(--white);font-size:13px;font-family:inherit;outline:none;}
        .filter-bar input{min-width:260px;}
        .table-wrap{overflow-x:auto;border-radius:16px;border:1px solid var(--line);}
        table{width:100%;border-collapse:collapse;}
        th{background:var(--panel);padding:12px 16px;text-align:left;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--soft);}
        td{padding:13px 16px;font-size:13px;border-top:1px solid var(--line);vertical-align:middle;}
        tr:hover td{background:rgba(255,255,255,.02);}
        .badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;}
        .badge-paid{background:rgba(34,197,94,.15);color:#86efac;}
        .badge-pending{background:rgba(234,179,8,.15);color:#fde68a;}
        .badge-failed{background:rgba(239,68,68,.12);color:#fca5a5;}
        .pagination{display:flex;gap:8px;justify-content:center;margin-top:24px;flex-wrap:wrap;}
        .pagination a,.pagination span{padding:7px 13px;border-radius:8px;border:1px solid var(--line);font-size:13px;text-decoration:none;color:var(--soft);}
        .pagination .active span{background:var(--blue);border-color:var(--blue);color:#fff;}
    </style>
</head>
<body>
<div class="container">

    <div class="topbar">
        <div>
            <div class="eyebrow">Admin Panel</div>
            <h1>Payments</h1>
            <p class="subtitle">All Razorpay transactions</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('admin.dashboard') }}" class="btn">← Dashboard</a>
            <a href="{{ route('admin.users.index') }}" class="btn">Users</a>
            <a href="{{ route('admin.intakes.index') }}" class="btn">Leads</a>
        </div>
    </div>

    <div class="stats">
        <div class="stat">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-val" style="color:var(--green);">₹{{ number_format($totals['paid'], 0) }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Pending</div>
            <div class="stat-val" style="color:var(--gold);">{{ $totals['pending'] }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Failed</div>
            <div class="stat-val" style="color:var(--red);">{{ $totals['failed'] }}</div>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search email, order ID, name…" value="{{ request('search') }}">
        <select name="status" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="paid"    {{ request('status')==='paid'    ? 'selected':'' }}>Paid</option>
            <option value="pending" {{ request('status')==='pending' ? 'selected':'' }}>Pending</option>
            <option value="failed"  {{ request('status')==='failed'  ? 'selected':'' }}>Failed</option>
        </select>
        <button type="submit" class="btn" style="background:#2563eb;border-color:#2563eb;">Search</button>
        @if(request()->hasAny(['search','status']))
        <a href="{{ route('admin.payments.index') }}" class="btn">Clear</a>
        @endif
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Order ID</th><th>Name</th><th>Email</th><th>Plan</th>
                    <th>Amount</th><th>Status</th><th>Gateway</th><th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr>
                    <td style="font-family:monospace;font-size:12px;color:var(--soft);">{{ Str::limit($payment->order_id, 20) }}</td>
                    <td style="font-weight:600;">
                        @if($payment->user)
                        <a href="{{ route('admin.users.show', $payment->user_id) }}" style="color:inherit;text-decoration:none;">{{ $payment->name ?? $payment->user->name }}</a>
                        @else
                        {{ $payment->name ?? '—' }}
                        @endif
                    </td>
                    <td style="color:var(--soft);">{{ $payment->email }}</td>
                    <td style="color:var(--soft);">{{ $payment->plan?->name ?? '—' }}</td>
                    <td style="font-weight:700;">₹{{ number_format($payment->amount, 0) }}</td>
                    <td>
                        @if($payment->status==='paid')    <span class="badge badge-paid">Paid</span>
                        @elseif($payment->status==='pending') <span class="badge badge-pending">Pending</span>
                        @else <span class="badge badge-failed">{{ ucfirst($payment->status) }}</span>
                        @endif
                    </td>
                    <td style="color:var(--muted);">{{ $payment->gateway ?? 'razorpay' }}</td>
                    <td style="color:var(--muted);">{{ $payment->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--muted);">No payments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">{{ $payments->links() }}</div>

</div>
</body>
</html>
