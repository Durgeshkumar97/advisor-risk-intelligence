<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Leads — RiskSignal Admin</title>
    <style>
        :root{--bg:#020817;--panel:#0f172a;--line:rgba(255,255,255,.08);--muted:#64748b;--soft:#94a3b8;--white:#fff;--gold:#facc15;--green:#22c55e;--blue:#2563eb;--red:#ef4444;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:Inter,Arial,sans-serif;background:var(--bg);color:var(--white);min-height:100vh;padding:32px 24px 80px;}
        .container{max-width:1300px;margin:auto;}
        .topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:28px;}
        .eyebrow{color:var(--gold);font-size:11px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;margin-bottom:6px;}
        h1{font-size:32px;font-weight:900;line-height:1.05;margin-bottom:6px;}
        .subtitle{color:var(--soft);font-size:14px;}
        .actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
        .btn{display:inline-flex;align-items:center;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;border:1px solid var(--line);color:var(--white);cursor:pointer;background:transparent;transition:.15s;}
        .btn:hover{background:rgba(255,255,255,.06);}
        .filter-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;}
        .filter-bar input,.filter-bar select{padding:9px 14px;border-radius:10px;border:1px solid var(--line);background:var(--panel);color:var(--white);font-size:13px;font-family:inherit;outline:none;}
        .filter-bar input{min-width:200px;}
        .table-wrap{overflow-x:auto;border-radius:16px;border:1px solid var(--line);}
        table{width:100%;border-collapse:collapse;}
        th{background:var(--panel);padding:12px 16px;text-align:left;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--soft);white-space:nowrap;}
        td{padding:13px 16px;font-size:13px;border-top:1px solid var(--line);vertical-align:middle;}
        tr:hover td{background:rgba(255,255,255,.02);}
        .score{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:800;}
        .score-high{background:rgba(34,197,94,.15);color:#86efac;}
        .score-mid{background:rgba(250,204,21,.15);color:#fde68a;}
        .score-low{background:rgba(100,116,139,.15);color:#94a3b8;}
        .badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;}
        .badge-new{background:rgba(37,99,235,.15);color:#93c5fd;}
        .badge-contacted{background:rgba(250,204,21,.15);color:#fde68a;}
        .badge-qualified{background:rgba(139,92,246,.15);color:#c4b5fd;}
        .badge-converted{background:rgba(34,197,94,.15);color:#86efac;}
        .badge-rejected{background:rgba(239,68,68,.12);color:#fca5a5;}
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
            <h1>Leads</h1>
            <p class="subtitle">{{ $intakes->total() }} total leads</p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.dashboard') }}" class="btn">← Dashboard</a>
            <a href="{{ route('admin.users.index') }}" class="btn">Users</a>
            <a href="{{ route('admin.payments.index') }}" class="btn">Payments</a>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <input type="text" name="email" placeholder="Search email…" value="{{ request('email') }}">
        <input type="number" name="min_score" placeholder="Min score" value="{{ request('min_score') }}" style="min-width:120px;">
        <select name="recent" onchange="this.form.submit()">
            <option value="">All Time</option>
            <option value="1" {{ request('recent') ? 'selected':'' }}>Last 7 Days</option>
        </select>
        <button type="submit" class="btn" style="background:#2563eb;border-color:#2563eb;">Search</button>
        @if(request()->hasAny(['email','min_score','recent']))
        <a href="{{ route('admin.intakes.index') }}" class="btn">Clear</a>
        @endif
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Name</th><th>Email</th><th>WhatsApp</th>
                    <th>Firm</th><th>Plan</th><th>Score</th><th>Status</th><th>Created</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($intakes as $intake)
                <tr>
                    <td style="color:var(--muted);">{{ $intake->id }}</td>
                    <td style="font-weight:600;">
                        <a href="{{ route('admin.intakes.show', $intake->id) }}" style="color:inherit;text-decoration:none;">
                            {{ $intake->name ?: '—' }}
                        </a>
                    </td>
                    <td style="color:var(--soft);">{{ $intake->email ?: '—' }}</td>
                    <td style="color:var(--soft);">{{ $intake->whatsapp ?: '—' }}</td>
                    <td style="color:var(--soft);">{{ $intake->firm_name ?: '—' }}</td>
                    <td style="color:var(--soft);">{{ $intake->plan ?: '—' }}</td>
                    <td>
                        @php $s = $intake->lead_score ?? 0; @endphp
                        <span class="score {{ $s >= 70 ? 'score-high' : ($s >= 40 ? 'score-mid' : 'score-low') }}">
                            {{ $s }}
                        </span>
                    </td>
                    <td>
                        @php $st = $intake->status ?? 'new'; @endphp
                        <span class="badge badge-{{ $st }}">{{ ucfirst($st) }}</span>
                    </td>
                    <td style="color:var(--muted);">{{ $intake->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('admin.intakes.show', $intake->id) }}" style="color:#a5b4fc;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap;">View →</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" style="text-align:center;padding:3rem;color:var(--muted);">No leads found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">{{ $intakes->links() }}</div>

</div>
</body>
</html>
