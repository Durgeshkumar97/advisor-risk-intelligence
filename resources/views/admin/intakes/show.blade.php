<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Lead #{{ $lead->id }} — RiskSignal Admin</title>

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

        .container { max-width:900px; margin:auto; }

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

        h1 { font-size:32px; font-weight:900; line-height:1.05; }

        .actions { display:flex; gap:10px; align-items:center; }

        .btn {
            padding:10px 18px;
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

        .btn-primary { background:var(--blue); }
        .btn-primary:hover { filter:brightness(1.1); }

        /* ── CARD ── */
        .card {
            background:var(--panel);
            border:1px solid var(--line);
            border-radius:16px;
            padding:24px;
            margin-bottom:18px;
        }

        .card-title {
            font-size:14px;
            font-weight:800;
            letter-spacing:.06em;
            text-transform:uppercase;
            color:var(--soft);
            margin-bottom:18px;
            padding-bottom:12px;
            border-bottom:1px solid var(--line);
        }

        /* ── DETAIL GRID ── */
        .detail-grid {
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:16px;
        }

        .detail-item label {
            display:block;
            font-size:11px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.08em;
            color:var(--muted);
            margin-bottom:5px;
        }

        .detail-item .value {
            font-size:15px;
            font-weight:600;
            color:var(--white);
            word-break:break-word;
        }

        .detail-item .value.muted { color:var(--soft); font-weight:400; }

        /* ── PILLS ── */
        .pill {
            display:inline-block;
            padding:4px 12px;
            border-radius:999px;
            font-size:12px;
            font-weight:700;
        }

        .pill-new       { background:rgba(59,130,246,.15);  color:#93c5fd; }
        .pill-contacted { background:rgba(251,191,36,.15);  color:#fbbf24; }
        .pill-converted { background:rgba(34,197,94,.15);   color:#22c55e; }
        .pill-lost      { background:rgba(239,68,68,.15);   color:#fca5a5; }

        .plan-starter { color:#a5b4fc; font-weight:700; }
        .plan-pro     { color:#facc15; font-weight:700; }
        .plan-team    { color:#34d399; font-weight:700; }

        /* ── STATUS FORM ── */
        .status-form {
            display:flex;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
        }

        .field {
            background:#0b1730;
            border:1px solid var(--line);
            color:#fff;
            border-radius:10px;
            padding:11px 14px;
            font-size:14px;
            min-width:180px;
        }

        .field:focus { outline:none; border-color:var(--blue); }

        select option { background: #0b1730; color: #ffffff; }

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

        /* ── NOTES BOX ── */
        .notes-box {
            background:var(--panel2);
            border:1px solid var(--line);
            border-radius:10px;
            padding:14px;
            font-size:14px;
            color:var(--soft);
            line-height:1.6;
            min-height:60px;
        }

        /* ── UTM ── */
        .utm-badge {
            display:inline-block;
            padding:3px 10px;
            border-radius:6px;
            background:rgba(255,255,255,.06);
            font-size:12px;
            color:var(--soft);
            font-family:monospace;
        }

        @media (max-width:640px) {
            h1 { font-size:24px; }
            .detail-grid { grid-template-columns:1fr; }
            .topbar { flex-direction:column; align-items:flex-start; }
        }
    </style>
</head>

<body>
<div class="container">

    {{-- TOPBAR --}}
    <div class="topbar">
        <div>
            <div class="eyebrow">Lead Detail</div>
            <h1>{{ $lead->name }}</h1>
        </div>
        <div class="actions">
            <a href="{{ route('admin.intakes.index') }}" class="btn btn-outline">← All Leads</a>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline">Dashboard</a>
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

    {{-- LEAD DETAILS --}}
    <div class="card">
        <div class="card-title">Contact Information</div>

        <div class="detail-grid">

            <div class="detail-item">
                <label>Full Name</label>
                <div class="value">{{ $lead->name }}</div>
            </div>

            <div class="detail-item">
                <label>Phone</label>
                <div class="value">{{ $lead->phone ?? '—' }}</div>
            </div>

            <div class="detail-item">
                <label>Email</label>
                <div class="value">{{ $lead->email ?? '—' }}</div>
            </div>

            <div class="detail-item">
                <label>Selected Plan</label>
                @php
                    $planCls = match($lead->selected_plan ?? '') {
                        'pro'  => 'plan-pro',
                        'team' => 'plan-team',
                        default => 'plan-starter',
                    };
                @endphp
                <div class="value {{ $planCls }}">{{ ucfirst($lead->selected_plan ?? '—') }}</div>
            </div>

            <div class="detail-item">
                <label>Status</label>
                @php
                    $statusPill = match($lead->status ?? 'new') {
                        'contacted' => ['cls' => 'pill-contacted', 'lbl' => 'Contacted'],
                        'converted' => ['cls' => 'pill-converted', 'lbl' => 'Converted'],
                        'lost'      => ['cls' => 'pill-lost',      'lbl' => 'Lost'],
                        default     => ['cls' => 'pill-new',       'lbl' => 'New'],
                    };
                @endphp
                <div class="value">
                    <span class="pill {{ $statusPill['cls'] }}">{{ $statusPill['lbl'] }}</span>
                </div>
            </div>

            <div class="detail-item">
                <label>Submitted</label>
                <div class="value">{{ $lead->created_at->format('d M Y, h:i A') }}</div>
            </div>

            @if($lead->contacted_at)
            <div class="detail-item">
                <label>Last Contacted</label>
                <div class="value">{{ $lead->contacted_at->format('d M Y, h:i A') }}</div>
            </div>
            @endif

            <div class="detail-item">
                <label>Source</label>
                <div class="value muted">{{ $lead->source ?? '—' }}</div>
            </div>

        </div>
    </div>

    {{-- UTM DATA --}}
    @if($lead->utm_source || $lead->utm_campaign || $lead->utm_medium)
    <div class="card">
        <div class="card-title">UTM Attribution</div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            @if($lead->utm_source)
                <div>
                    <div style="font-size:11px;color:var(--muted);margin-bottom:4px;">Source</div>
                    <span class="utm-badge">{{ $lead->utm_source }}</span>
                </div>
            @endif
            @if($lead->utm_medium)
                <div>
                    <div style="font-size:11px;color:var(--muted);margin-bottom:4px;">Medium</div>
                    <span class="utm-badge">{{ $lead->utm_medium }}</span>
                </div>
            @endif
            @if($lead->utm_campaign)
                <div>
                    <div style="font-size:11px;color:var(--muted);margin-bottom:4px;">Campaign</div>
                    <span class="utm-badge">{{ $lead->utm_campaign }}</span>
                </div>
            @endif
        </div>
    </div>
    @endif

    {{-- NOTES --}}
    @if($lead->notes)
    <div class="card">
        <div class="card-title">Notes</div>
        <div class="notes-box">{{ $lead->notes }}</div>
    </div>
    @endif

    {{-- UPDATE STATUS --}}
    <div class="card">
        <div class="card-title">Update Status</div>

        <form method="POST" action="{{ route('admin.intakes.status', $lead->id) }}" class="status-form">
            @csrf

            <select name="status" class="field">
                <option value="new"       @selected($lead->status === 'new')>New</option>
                <option value="contacted" @selected($lead->status === 'contacted')>Contacted</option>
                <option value="converted" @selected($lead->status === 'converted')>Converted</option>
                <option value="lost"      @selected($lead->status === 'lost')>Lost</option>
            </select>

            <button type="submit" class="btn btn-primary">
                Update Status →
            </button>
        </form>

        @if($errors->any())
            <div style="margin-top:12px;color:#fca5a5;font-size:13px;">
                {{ $errors->first() }}
            </div>
        @endif
    </div>

</div>
</body>
</html>
