<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments — RiskSignal Admin</title>
    <style>
        :root{color-scheme:dark;--bg:#020817;--panel:#0f172a;--line:rgba(255,255,255,.08);--muted:#64748b;--soft:#94a3b8;--white:#fff;--gold:#facc15;--green:#22c55e;--blue:#2563eb;--danger:#ef4444;}
        select option{background:#0f172a;color:#fff;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:Inter,Arial,sans-serif;background:var(--bg);color:var(--white);min-height:100vh;padding:32px 24px 80px;}
        .container{max-width:1400px;margin:auto;}
        .topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:28px;}
        .eyebrow{color:var(--gold);font-size:11px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;margin-bottom:6px;}
        h1{font-size:32px;font-weight:900;line-height:1.05;margin-bottom:6px;}
        .subtitle{color:var(--soft);font-size:14px;}
        .actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
        .btn{display:inline-flex;align-items:center;gap:6px;padding:12px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;border:1px solid var(--line);color:var(--white);cursor:pointer;background:transparent;transition:.15s;}
        .btn:hover{background:rgba(255,255,255,.06);}
        .btn-primary{background:var(--blue);border-color:var(--blue);}
        .btn-primary:hover{background:#1d4ed8;}
        .stat-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;}
        .stat-card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:20px;}
        .stat-label{font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--soft);margin-bottom:8px;}
        .stat-value{font-size:26px;font-weight:900;}
        .filter-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;}
        .filter-bar input,.filter-bar select{padding:9px 14px;border-radius:10px;border:1px solid var(--line);background:var(--panel);color:var(--white);font-size:13px;font-family:inherit;outline:none;}
        .filter-bar input{min-width:240px;}
        .table-wrap{overflow-x:auto;border-radius:16px;border:1px solid var(--line);}
        table{width:100%;border-collapse:collapse;}
        th{background:var(--panel);padding:12px 16px;text-align:left;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--soft);white-space:nowrap;}
        td{padding:14px 16px;font-size:13px;border-top:1px solid var(--line);vertical-align:middle;}
        tr:hover td{background:rgba(255,255,255,.02);}
        .badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;}
        .badge-paid{background:rgba(34,197,94,.15);color:#86efac;}
        .badge-pending{background:rgba(234,179,8,.15);color:#fde68a;}
        .badge-failed{background:rgba(239,68,68,.12);color:#fca5a5;}
        .badge-refunded{background:rgba(100,116,139,.15);color:#94a3b8;}
        .pagination{display:flex;gap:8px;justify-content:center;margin-top:24px;flex-wrap:wrap;}
        .pagination a,.pagination span{padding:7px 13px;border-radius:8px;border:1px solid var(--line);font-size:13px;text-decoration:none;color:var(--soft);}
        .pagination .active span{background:var(--blue);border-color:var(--blue);color:#fff;}
        .mono{font-family:monospace;font-size:12px;color:var(--muted);}
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
        <div class="actions">
            <a href="{{ route('admin.dashboard') }}" class="btn">← Dashboard</a>
            <a href="{{ route('admin.users.index') }}" class="btn">Users</a>
            <a href="{{ route('admin.intakes.index') }}" class="btn">Leads</a>
        </div>
    </div>

    {{-- REVENUE SUMMARY --}}
    <div class="stat-row">
        <div class="stat-card">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value" style="color:var(--green);">₹{{ number_format($totalRevenue, 0) }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Paid Transactions</div>
            <div class="stat-value">{{ $paidCount }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">All Transactions</div>
            <div class="stat-value">{{ $payments->total() }}</div>
        </div>
    </div>

    {{-- FILTERS --}}
    <form method="GET" class="filter-bar">
        <input type="text" name="search" placeholder="Search email, order ID, payment ID…" value="{{ request('search') }}">
        <select name="status" onchange="this.form.submit()">
            <option value="all">All Statuses</option>
            <option value="paid"     {{ request('status') === 'paid'     ? 'selected' : '' }}>Paid</option>
            <option value="pending"  {{ request('status') === 'pending'  ? 'selected' : '' }}>Pending</option>
            <option value="failed"   {{ request('status') === 'failed'   ? 'selected' : '' }}>Failed</option>
            <option value="refunded" {{ request('status') === 'refunded' ? 'selected' : '' }}>Refunded</option>
        </select>
        <button type="submit" class="btn btn-primary">Search</button>
        @if(request()->hasAny(['search','status']))
            <a href="{{ route('admin.payments.index') }}" class="btn">Clear</a>
        @endif
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Customer</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Order ID</th>
                    <th>Payment ID</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr>
                    <td>
                        <span class="badge badge-{{ $payment->status }}">
                            {{ ucfirst($payment->status) }}
                        </span>
                    </td>
                    <td>
                        <div style="font-weight:700;">{{ $payment->name ?? $payment->user?->name ?? '—' }}</div>
                        <div style="font-size:12px;color:var(--muted);">{{ $payment->email }}</div>
                    </td>
                    <td style="color:var(--soft);">{{ $payment->plan?->name ?? '—' }}</td>
                    <td style="font-weight:800;">
                        @if($payment->status === 'paid')
                            <span style="color:var(--green);">₹{{ number_format($payment->amount, 0) }}</span>
                        @else
                            <span style="color:var(--muted);">₹{{ number_format($payment->amount, 0) }}</span>
                        @endif
                    </td>
                    <td><span class="mono">{{ $payment->order_id ?? '—' }}</span></td>
                    <td><span class="mono">{{ $payment->payment_id ?? '—' }}</span></td>
                    <td style="color:var(--muted);white-space:nowrap;">
                        {{ $payment->created_at->format('d M Y, h:i A') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center;padding:3rem;color:var(--muted);">No payments found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        {{ $payments->links() }}
    </div>

</div>
</body>
</html>
