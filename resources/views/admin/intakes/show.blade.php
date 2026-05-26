<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Lead #{{ $intake->id }} — RiskSignal Admin</title>
    <style>
        :root{--bg:#020817;--panel:#0f172a;--line:rgba(255,255,255,.08);--muted:#64748b;--soft:#94a3b8;--white:#fff;--gold:#facc15;--green:#22c55e;--blue:#2563eb;--red:#ef4444;}
        *{box-sizing:border-box;margin:0;padding:0;}
        body{font-family:Inter,Arial,sans-serif;background:var(--bg);color:var(--white);min-height:100vh;padding:32px 24px 80px;}
        .container{max-width:900px;margin:auto;}
        .topbar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:28px;}
        .eyebrow{color:var(--gold);font-size:11px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;margin-bottom:6px;}
        h1{font-size:28px;font-weight:900;line-height:1.1;}
        .btn{display:inline-flex;align-items:center;padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;border:1px solid var(--line);color:var(--white);cursor:pointer;background:transparent;transition:.15s;}
        .btn:hover{background:rgba(255,255,255,.06);}
        .card{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:1.5rem;margin-bottom:1.25rem;}
        .card-title{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--soft);margin-bottom:1rem;}
        .row{display:flex;justify-content:space-between;align-items:flex-start;padding:.6rem 0;border-bottom:1px solid var(--line);font-size:13px;gap:1rem;}
        .row:last-child{border-bottom:none;}
        .label{color:var(--muted);min-width:140px;flex-shrink:0;}
        .val{font-weight:600;text-align:right;word-break:break-word;}
        .badge{display:inline-block;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;}
        .badge-new{background:rgba(37,99,235,.15);color:#93c5fd;}
        .badge-contacted{background:rgba(250,204,21,.15);color:#fde68a;}
        .badge-qualified{background:rgba(139,92,246,.15);color:#c4b5fd;}
        .badge-converted{background:rgba(34,197,94,.15);color:#86efac;}
        .badge-rejected{background:rgba(239,68,68,.12);color:#fca5a5;}
        .score-pill{display:inline-block;padding:4px 14px;border-radius:999px;font-weight:900;font-size:14px;}
        .alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#86efac;padding:.75rem 1rem;border-radius:10px;font-size:13px;margin-bottom:1.25rem;}
        select.status-select{padding:8px 12px;border-radius:10px;border:1px solid var(--line);background:var(--panel);color:var(--white);font-size:13px;font-family:inherit;outline:none;cursor:pointer;}
    </style>
</head>
<body>
<div class="container">

    <div class="topbar">
        <div>
            <div class="eyebrow">Admin → Leads</div>
            <h1>{{ $intake->name ?: 'Lead #' . $intake->id }}</h1>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="{{ route('admin.intakes.index') }}" class="btn">← All Leads</a>
            <a href="{{ route('admin.dashboard') }}" class="btn">Dashboard</a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert-success">✓ {{ session('success') }}</div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">

        {{-- CONTACT INFO --}}
        <div class="card">
            <div class="card-title">Contact</div>
            <div class="row"><span class="label">ID</span><span class="val">{{ $intake->id }}</span></div>
            <div class="row"><span class="label">Name</span><span class="val">{{ $intake->name ?: '—' }}</span></div>
            <div class="row"><span class="label">Email</span><span class="val">{{ $intake->email ?: '—' }}</span></div>
            <div class="row"><span class="label">WhatsApp</span><span class="val">{{ $intake->whatsapp ?: '—' }}</span></div>
            <div class="row"><span class="label">Firm</span><span class="val">{{ $intake->firm_name ?: '—' }}</span></div>
            <div class="row"><span class="label">Submitted</span><span class="val">{{ $intake->created_at->format('d M Y, H:i') }}</span></div>
        </div>

        {{-- LEAD SCORING --}}
        <div class="card">
            <div class="card-title">Lead Score & Status</div>
            <div class="row">
                <span class="label">Score</span>
                <span class="val">
                    @php $s = $intake->lead_score ?? 0; @endphp
                    <span class="score-pill" style="{{ $s >= 70 ? 'background:rgba(34,197,94,.15);color:#86efac;' : ($s >= 40 ? 'background:rgba(250,204,21,.15);color:#fde68a;' : 'background:rgba(100,116,139,.15);color:#94a3b8;') }}">
                        {{ $s }}
                    </span>
                </span>
            </div>
            <div class="row">
                <span class="label">Current Status</span>
                <span class="val"><span class="badge badge-{{ $intake->status ?? 'new' }}">{{ ucfirst($intake->status ?? 'new') }}</span></span>
            </div>
            <div class="row"><span class="label">Plan</span><span class="val">{{ $intake->plan ?: '—' }}</span></div>
            <div class="row"><span class="label">AI Status</span><span class="val">{{ $intake->ai_status ?: '—' }}</span></div>
        </div>

    </div>

    {{-- TRIAL INFO --}}
    @if($intake->trial_started_at || $intake->trial_ends_at)
    <div class="card">
        <div class="card-title">Trial Details</div>
        <div class="row"><span class="label">Trial Started</span><span class="val">{{ $intake->trial_started_at?->format('d M Y') ?? '—' }}</span></div>
        <div class="row"><span class="label">Trial Ends</span><span class="val">{{ $intake->trial_ends_at?->format('d M Y') ?? '—' }}</span></div>
        <div class="row"><span class="label">Plan Price</span><span class="val">{{ $intake->plan_price ? '₹' . number_format($intake->plan_price, 0) : '—' }}</span></div>
        <div class="row"><span class="label">Revenue</span><span class="val">{{ $intake->revenue_generated ? '₹' . number_format($intake->revenue_generated, 0) : '—' }}</span></div>
    </div>
    @endif

    {{-- UPDATE STATUS --}}
    <div class="card">
        <div class="card-title">Update Status</div>
        <form method="POST" action="{{ route('admin.intakes.status', $intake->id) }}" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
            @csrf
            <select name="status" class="status-select">
                @foreach(['new','contacted','qualified','converted','rejected'] as $opt)
                <option value="{{ $opt }}" {{ ($intake->status ?? 'new') === $opt ? 'selected' : '' }}>
                    {{ ucfirst($opt) }}
                </option>
                @endforeach
            </select>
            <button type="submit" class="btn" style="background:#2563eb;border-color:#2563eb;">
                Update Status
            </button>
        </form>
    </div>

    {{-- DOCUMENT --}}
    @if($intake->document_path)
    <div class="card">
        <div class="card-title">Uploaded Document</div>
        <div style="font-size:13px;color:var(--soft);">{{ $intake->document_path }}</div>
    </div>
    @endif

</div>
</body>
</html>
