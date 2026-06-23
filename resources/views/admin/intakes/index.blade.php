<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lead Intake — RiskSignal Admin</title>

    <style>
        :root {
            color-scheme: dark;
            --bg:#020817;
            --panel:#0f172a;
            --panel2:#111c34;
            --line:rgba(255,255,255,.08);
            --muted:#64748b;
            --soft:#94a3b8;
            --white:#ffffff;
            --gold:#facc15;
            --green:#22c55e;
            --blue:#2563eb;
            --danger:#ef4444;
        }

        * { box-sizing:border-box; margin:0; padding:0; }

        body {
            font-family:Inter, Arial, Helvetica, sans-serif;
            background:var(--bg);
            color:var(--white);
            min-height:100vh;
            padding:32px 24px 80px;
        }

        .container { max-width:1400px; margin:auto; }

        /* ── TOPBAR ── */
        .topbar {
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:16px;
            margin-bottom:28px;
        }

        .eyebrow {
            color:var(--gold);
            font-size:11px;
            font-weight:800;
            letter-spacing:1.4px;
            text-transform:uppercase;
            margin-bottom:6px;
        }

        h1 { font-size:36px; font-weight:900; line-height:1.05; margin-bottom:6px; }

        .subtitle { color:var(--soft); font-size:14px; }

        .actions { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

        .btn {
            padding:12px 18px;
            border-radius:10px;
            font-weight:700;
            font-size:13px;
            cursor:pointer;
            text-decoration:none;
            border:none;
            color:#fff;
            transition:.18s ease;
        }

        .btn-outline {
            background:transparent;
            border:1px solid rgba(255,255,255,.14);
        }

        .btn-outline:hover { background:rgba(255,255,255,.06); }

        .btn-primary {
            background:var(--blue);
        }

        /* ── CARD ── */
        .card {
            background:var(--panel);
            border:1px solid var(--line);
            border-radius:16px;
            padding:20px;
        }

        /* ── FILTERS ── */
        .filters-form {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));
            gap:12px;
            align-items:end;
        }

        .field {
            width:100%;
            background:#0b1730;
            border:1px solid var(--line);
            color:#fff;
            border-radius:10px;
            padding:11px 14px;
            font-size:13px;
        }

        .field:focus { outline:none; border-color:var(--blue); }

        select option { background: #0b1730; color: #ffffff; }

        .filter-actions {
            display:flex;
            gap:10px;
            justify-content:flex-end;
            margin-top:14px;
        }

        /* ── TABLE ── */
        .table-wrap { overflow-x:auto; margin-top:20px; }

        table { width:100%; border-collapse:collapse; font-size:14px; }

        thead th {
            color:var(--soft);
            font-size:11px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.08em;
            text-align:left;
            padding-bottom:12px;
            border-bottom:1px solid var(--line);
        }

        tbody td {
            padding:14px 0;
            border-bottom:1px solid rgba(255,255,255,.04);
            vertical-align:middle;
        }

        tbody tr:hover td { background:rgba(255,255,255,.015); }

        tbody tr:last-child td { border-bottom:none; }

        /* ── PILLS ── */
        .pill {
            display:inline-block;
            padding:3px 10px;
            border-radius:999px;
            font-size:11px;
            font-weight:700;
            white-space:nowrap;
        }

        .pill-new       { background:rgba(59,130,246,.15);  color:#93c5fd; }
        .pill-contacted { background:rgba(251,191,36,.15);  color:#fbbf24; }
        .pill-converted { background:rgba(34,197,94,.15);   color:#22c55e; }
        .pill-lost      { background:rgba(239,68,68,.15);   color:#fca5a5; }

        .plan-starter { color:#a5b4fc; font-weight:600; }
        .plan-pro     { color:#facc15; font-weight:600; }
        .plan-team    { color:#34d399; font-weight:600; }

        /* ── PAGINATION ── */
        .pagination {
            display:flex;
            gap:6px;
            justify-content:center;
            flex-wrap:wrap;
            margin-top:24px;
        }

        .pagination a,
        .pagination span {
            display:inline-block;
            padding:6px 12px;
            border-radius:8px;
            font-size:13px;
            font-weight:600;
            text-decoration:none;
            border:1px solid var(--line);
            color:var(--soft);
            background:var(--panel);
            transition:.15s ease;
        }

        .pagination a:hover { background:rgba(255,255,255,.06); color:#fff; }
        .pagination span[aria-current] { background:var(--blue); border-color:var(--blue); color:#fff; }

        /* ── ALERT ── */
        .alert-success {
            background:rgba(34,197,94,.12);
            border:1px solid rgba(34,197,94,.25);
            color:#34d399;
            padding:12px 16px;
            border-radius:10px;
            margin-bottom:18px;
            font-size:14px;
            font-weight:600;
        }

        .empty-state {
            text-align:center;
            padding:48px 20px;
            color:var(--soft);
        }

        .empty-state .icon { font-size:2.5rem; margin-bottom:12px; }

        @media (max-width:768px) {
            h1 { font-size:26px; }
            .topbar { flex-direction:column; align-items:flex-start; }
        }
    </style>
</head>

<body>
<div class="container">

    {{-- TOPBAR --}}
    <div class="topbar">
        <div>
            <div class="eyebrow">IFA Lead Intake</div>
            <h1>Lead Pipeline</h1>
            <p class="subtitle">
                {{ $leads->total() }} total leads · Page {{ $leads->currentPage() }} of {{ $leads->lastPage() }}
            </p>
        </div>
        <div class="actions">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline">← Dashboard</a>
            <form method="POST" action="{{ route('admin.logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-outline">Logout</button>
            </form>
        </div>
    </div>

    {{-- SUCCESS FLASH --}}
    @if(session('success'))
        <div class="alert-success">✓ {{ session('success') }}</div>
    @endif

    {{-- FILTERS --}}
    <div class="card" style="margin-bottom:18px;">
        <form method="GET" action="{{ route('admin.intakes.index') }}" class="filters-form">

            <input
                type="text"
                name="search"
                class="field"
                placeholder="Search name / email / phone"
                value="{{ request('search') }}"
            >

            <select name="plan" class="field">
                <option value="all" @selected(request('plan', 'all') === 'all')>All Plans</option>
                <option value="starter" @selected(request('plan') === 'starter')>Starter</option>
                <option value="pro"     @selected(request('plan') === 'pro')>Pro</option>
                <option value="team"    @selected(request('plan') === 'team')>Team</option>
            </select>

            <select name="status" class="field">
                <option value="all"       @selected(request('status', 'all') === 'all')>All Status</option>
                <option value="new"       @selected(request('status') === 'new')>New</option>
                <option value="contacted" @selected(request('status') === 'contacted')>Contacted</option>
                <option value="converted" @selected(request('status') === 'converted')>Converted</option>
                <option value="lost"      @selected(request('status') === 'lost')>Lost</option>
            </select>

            <select name="days" class="field">
                <option value=""  @selected(!request('days'))>All Time</option>
                <option value="1"  @selected(request('days') == 1)>Today</option>
                <option value="7"  @selected(request('days') == 7)>Last 7 Days</option>
                <option value="14" @selected(request('days') == 14)>Last 14 Days</option>
                <option value="30" @selected(request('days') == 30)>Last 30 Days</option>
                <option value="90" @selected(request('days') == 90)>Last 90 Days</option>
            </select>

            <div class="filter-actions" style="grid-column:1/-1;">
                <a href="{{ route('admin.intakes.index') }}" class="btn btn-outline">Clear</a>
                <button type="submit" class="btn btn-primary">Search →</button>
            </div>
        </form>
    </div>

    {{-- TABLE --}}
    <div class="card">

        @if($leads->isEmpty())

            <div class="empty-state">
                <div class="icon">📋</div>
                <div style="font-weight:700;font-size:16px;margin-bottom:8px;">No leads found</div>
                <div style="font-size:14px;">Try adjusting your filters.</div>
            </div>

        @else

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Source</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($leads as $lead)
                            @php
                                $statusPill = match($lead->status ?? 'new') {
                                    'contacted' => ['cls' => 'pill-contacted', 'lbl' => 'Contacted'],
                                    'converted' => ['cls' => 'pill-converted', 'lbl' => 'Converted'],
                                    'lost'      => ['cls' => 'pill-lost',      'lbl' => 'Lost'],
                                    default     => ['cls' => 'pill-new',       'lbl' => 'New'],
                                };
                                $planCls = match($lead->selected_plan ?? '') {
                                    'pro'  => 'plan-pro',
                                    'team' => 'plan-team',
                                    default => 'plan-starter',
                                };
                            @endphp
                            <tr>
                                <td style="color:var(--muted);font-size:12px;">{{ $lead->id }}</td>

                                <td>
                                    <strong style="font-size:14px;">{{ $lead->name }}</strong>
                                    @if($lead->contacted_at)
                                        <div style="font-size:11px;color:var(--muted);margin-top:2px;">
                                            Contacted {{ $lead->contacted_at->diffForHumans() }}
                                        </div>
                                    @endif
                                </td>

                                <td style="color:var(--soft);font-size:13px;">{{ $lead->phone ?? '—' }}</td>

                                <td style="color:var(--soft);font-size:13px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    {{ $lead->email ?? '—' }}
                                </td>

                                <td>
                                    <span class="{{ $planCls }}">{{ ucfirst($lead->selected_plan ?? '—') }}</span>
                                </td>

                                <td>
                                    <span class="pill {{ $statusPill['cls'] }}">{{ $statusPill['lbl'] }}</span>
                                </td>

                                <td style="color:var(--muted);font-size:12px;">{{ $lead->source ?? '—' }}</td>

                                <td style="color:var(--muted);font-size:12px;white-space:nowrap;">
                                    {{ $lead->created_at->format('d M Y') }}<br>
                                    <span style="font-size:11px;">{{ $lead->created_at->format('h:i A') }}</span>
                                </td>

                                <td>
                                    <a href="{{ route('admin.intakes.show', $lead->id) }}"
                                       style="color:#a5b4fc;font-size:13px;font-weight:700;text-decoration:none;">
                                        View →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- PAGINATION --}}
            @if($leads->hasPages())
                <div class="pagination">
                    {{-- Previous --}}
                    @if($leads->onFirstPage())
                        <span>‹ Prev</span>
                    @else
                        <a href="{{ $leads->previousPageUrl() }}">‹ Prev</a>
                    @endif

                    {{-- Page numbers --}}
                    @foreach($leads->getUrlRange(max(1, $leads->currentPage() - 3), min($leads->lastPage(), $leads->currentPage() + 3)) as $page => $url)
                        @if($page === $leads->currentPage())
                            <span aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach

                    {{-- Next --}}
                    @if($leads->hasMorePages())
                        <a href="{{ $leads->nextPageUrl() }}">Next ›</a>
                    @else
                        <span>Next ›</span>
                    @endif
                </div>
            @endif

        @endif
    </div>

</div>
</body>
</html>
