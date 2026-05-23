@extends('layouts.app')

@section('content')

@php
    $mrr = $mrr ?? 0;
    $arr = $arr ?? 0;
    $conversion = $conversion ?? 0;
@endphp

<div class="dashboard-shell">

    <div class="container-xl">

        {{-- HEADER --}}
        <div class="topbar">

            <div>
                <div class="eyebrow">ARR COMMAND MODE</div>

                <h1 class="page-title">
                    RiskSignal Growth OS
                </h1>

                <p class="subtitle">
                    Revenue visibility. Growth intelligence. Founder control.
                </p>
            </div>

            <div class="actions">

                <a href="/" class="btn btn-outline">
                    View Site
                </a>

                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf

                    <button type="submit" class="btn btn-outline">
                        Logout
                    </button>
                </form>

            </div>

        </div>

        {{-- TOP METRICS --}}
        <div class="grid metrics-grid">

            <div class="card metric">
                <small>MRR</small>
                <h2 class="purple">₹{{ number_format($mrr) }}</h2>
                <span>Booked monthly revenue</span>
            </div>

            <div class="card metric">
                <small>ARR</small>
                <h2>₹{{ number_format($arr) }}</h2>
                <span>Annual recurring revenue</span>
            </div>

            <div class="card metric">
                <small>Pipeline MRR</small>
                <h2 class="green">₹{{ number_format($pipelineMRR ?? 0) }}</h2>
                <span>Potential from leads</span>
            </div>

            <div class="card metric">
                <small>Conversion</small>
                <h2 class="yellow">{{ $conversion }}%</h2>
                <span>Lead → paid</span>
            </div>

            <div class="card metric">
                <small>LTV:CAC</small>
                <h2 class="blue">{{ $ltvCac }}x</h2>
                <span>Unit economics</span>
            </div>

            <div class="card metric">
                <small>Runway</small>
                <h2>{{ $runwayMonths }}m</h2>
                <span>Estimated</span>
            </div>

        </div>

        {{-- FILTERS (link to intakes index) --}}
        <form method="GET" action="{{ route('admin.intakes.index') }}" class="card mt-18">

            <div class="grid filters-grid">

                <select name="plan" class="field">
                    <option value="all">All Plans</option>
                    <option value="starter">Starter</option>
                    <option value="pro">Pro</option>
                    <option value="team">Team</option>
                </select>

                <select name="status" class="field">
                    <option value="all">All Status</option>
                    <option value="new">New</option>
                    <option value="contacted">Contacted</option>
                    <option value="converted">Converted</option>
                    <option value="lost">Lost</option>
                </select>

                <select name="days" class="field">
                    <option value="">All Time</option>
                    <option value="1">Today</option>
                    <option value="7">1 Week</option>
                    <option value="14">2 Weeks</option>
                    <option value="30">1 Month</option>
                    <option value="90">Quarterly</option>
                </select>

                <input name="search" class="field" placeholder="Search lead / phone / email">

            </div>

            <div style="margin-top:12px;text-align:right;">
                <button type="submit" class="btn btn-outline" style="padding:10px 20px;">
                    Search Leads →
                </button>
            </div>

        </form>

        {{-- MAIN GRID --}}
        <div class="main-grid">

            {{-- LEFT SIDE --}}
            <div class="stack">

                {{-- REVENUE PIPELINE --}}
                <div class="card">

                    <div class="head">
                        <h3>Revenue Pipeline</h3>
                        <span>{{ $recentLeads?->count() ?? 0 }} active leads</span>
                    </div>

                    <div class="table-wrap">
                        <table class="table">

                            <thead>
                                <tr>
                                    <th>Lead</th>
                                    <th>Plan</th>
                                    <th>Stage</th>
                                    <th>Value</th>
                                    <th>Action</th>
                                </tr>
                            </thead>

                            <tbody>
                            @forelse($recentLeads ?? [] as $lead)
                                @php
                                    $planPrice = match($lead->selected_plan ?? '') {
                                        'pro'  => $proPrice,
                                        'team' => $teamPrice,
                                        default => $starterPrice,
                                    };
                                    $stagePill = match($lead->status ?? 'new') {
                                        'contacted' => ['cls' => 'yellow-pill', 'lbl' => 'Contacted'],
                                        'converted' => ['cls' => 'green-pill',  'lbl' => 'Converted'],
                                        'lost'      => ['cls' => 'red-pill',    'lbl' => 'Lost'],
                                        default     => ['cls' => 'blue-pill',   'lbl' => 'New'],
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.intakes.show', $lead->id) }}" style="color:#fff;text-decoration:none;">
                                            <strong>{{ $lead->name }}</strong>
                                        </a><br>
                                        <small>{{ $lead->phone }}</small>
                                    </td>

                                    <td>{{ ucfirst($lead->selected_plan ?? '—') }}</td>

                                    <td>
                                        <span class="pill {{ $stagePill['cls'] }}">{{ $stagePill['lbl'] }}</span>
                                    </td>

                                    <td>₹{{ number_format($planPrice) }}</td>

                                    <td>
                                        <a href="{{ route('admin.intakes.show', $lead->id) }}" class="green-text" style="text-decoration:none;font-size:13px;">
                                            View →
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="muted center">No leads found.</td>
                                </tr>
                            @endforelse
                            </tbody>

                        </table>
                    </div>

                </div>

                {{-- UPGRADE OPPORTUNITIES --}}
                <div class="card">

                    <div class="head">
                        <h3>Upgrade Opportunities</h3>
                        <span>High intent leads</span>
                    </div>

                    @forelse($recentLeads ?? [] as $lead)
                        <div class="list-row">

                            <div>
                                <div class="strong">{{ $lead->name }}</div>
                                <div class="mini-muted">{{ $lead->phone }}</div>
                            </div>

                            <div class="text-right">
                                <div>{{ ucfirst($lead->selected_plan) }}</div>
                                <div class="green-text small">
                                    Likely Upgrade
                                </div>
                            </div>

                        </div>
                    @empty
                        <div class="muted">No upgrade opportunities.</div>
                    @endforelse

                </div>

                {{-- WEEKLY FUNNEL --}}
                <div class="card">

                    <div class="head">
                        <h3>Weekly Funnel</h3>
                        <span>Leak detection</span>
                    </div>

                    <div class="grid funnel-grid center">
                        <div><small>Visitors</small><h2>112</h2></div>
                        <div><small>Leads</small><h2>18</h2></div>
                        <div><small>Trials</small><h2>6</h2></div>
                        <div><small>Paid</small><h2>1</h2></div>
                    </div>

                </div>

            </div>
            
            {{-- RIGHT SIDE --}}
            <div class="stack">

                {{-- THREAT RADAR --}}
                <div class="card">
                    <div class="head">
                        <h3>AI Threat Radar V2</h3>
                        <span>{{ now()->format('h:i A') }}</span>
                    </div>

                    <div class="mini-grid">

                        <div class="mini-box">
                            <small>Threat Score</small>
                            <h2>{{ $threatScore ?? 0 }}</h2>
                        </div>

                        <div class="mini-box">
                            <small>Risk Level</small>
                            @php $riskColorStyle = 'color:' . ($riskColor ?? '#22c55e'); @endphp
                            <h2 style="{{ $riskColorStyle }}">
                                {{ $riskLevel ?? 'LOW' }}
                            </h2>
                        </div>

                        <div class="mini-box">
                            <small>Top Attacker IP</small>
                            <h2 class="small-title">
                                {{ optional($topAttacker)->ip ?? 'None' }}
                            </h2>
                        </div>

                        <div class="mini-box">
                            <small>Attempts</small>
                            <h2>{{ optional($topAttacker)->total ?? 0 }}</h2>
                        </div>

                        <div class="mini-box full">
                            <small>AI Recommendation</small>
                            <div class="mini-copy">
                                {{ $aiRecommendation ?? 'Normal monitoring only.' }}
                            </div>
                        </div>

                    </div>
                </div>

                {{-- SECURITY EVENTS --}}
                <div class="card">
                    <div class="head">
                        <h3>Recent Security Events</h3>
                        <span>{{ $recentSecurityEvents?->count() ?? 0 }}</span>
                    </div>

                    <div class="stack-sm">

                        @forelse($recentSecurityEvents ?? [] as $event)
                            <div class="event-box">
                                <div class="strong small">
                                    {{ strtoupper($event->type ?? 'UNKNOWN') }}
                                </div>

                                <div class="mini-muted">
                                    {{ $event->ip ?? 'N/A' }} • {{ $event->created_at ?? '' }}
                                </div>
                            </div>
                        @empty
                            <div class="muted">No recent events.</div>
                        @endforelse

                    </div>
                </div>

                {{-- QUICK SECURITY STATS --}}
                <div class="card">
                    <div class="mini-grid">

                        <div class="mini-box">
                            <small>Threat Attempts Today</small>
                            <h2>{{ $threatAttemptsToday ?? 0 }}</h2>
                        </div>

                        <div class="mini-box">
                            <small>Unknown IP Alerts</small>
                            <h2>{{ $unknownIpAlerts ?? 0 }}</h2>
                        </div>

                        <div class="mini-box">
                            <small>Last Login Time</small>
                            <h2 class="small-title">
                                {{ $lastLogin ?? 'N/A' }}
                            </h2>
                        </div>

                        <div class="mini-box">
                            <small>Status</small>
                            <h2 class="green">{{ $riskLevel ?? 'LOW' }}</h2>
                        </div>

                    </div>
                </div>

                {{-- RENEWALS --}}
                <div class="card">
                    <div class="head">
                        <h3>Renewals At Risk</h3>
                        <span>7 days</span>
                    </div>

                    <h2 class="orange big-number">
                        ₹{{ number_format($renewalValue ?? 0) }}
                    </h2>

                    <p class="subtitle-sm">
                        {{ $expiringSoon ?? 0 }} accounts need conversion push
                    </p>
                </div>

                {{-- FOUNDER PRIORITIES --}}
                <div class="card">
                    <div class="head">
                        <h3>Founder Priorities</h3>
                        <span>Today stack</span>
                    </div>

                    <div class="stack-sm">
                        <label class="task p1"><input type="checkbox"><span><strong>1.</strong> Close first Pro user</span></label>
                        <label class="task p2"><input type="checkbox"><span><strong>2.</strong> Improve landing conversion copy</span></label>
                        <label class="task p2"><input type="checkbox"><span><strong>3.</strong> Enable Razorpay auto capture</span></label>
                        <label class="task p3"><input type="checkbox"><span><strong>4.</strong> Polish dashboard styling</span></label>
                    </div>
                </div>

                {{-- RECENT PAYMENTS --}}
                <div class="card">
                    <div class="head">
                        <h3>Recent Payments</h3>
                        <span>₹{{ number_format($totalRevenue ?? 0) }} total</span>
                    </div>

                    <div class="stack-sm">
                        @forelse($recentPayments ?? [] as $payment)
                            <div class="list-row">
                                <div>
                                    <div class="strong" style="font-size:14px;">{{ $payment->user?->name ?? 'Unknown' }}</div>
                                    <div class="mini-muted">{{ $payment->plan?->name ?? '—' }} · {{ $payment->paid_at?->format('d M Y') }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="green" style="font-weight:700;">₹{{ number_format($payment->amount) }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="muted" style="font-size:13px;">No payments yet.</div>
                        @endforelse
                    </div>
                </div>

                {{-- RECENT SIGNUPS --}}
                <div class="card">
                    <div class="head">
                        <h3>Recent Signups</h3>
                        <span>{{ $newUsersWeek ?? 0 }} this week</span>
                    </div>

                    <div class="stack-sm">
                        @forelse($recentSignups ?? [] as $user)
                            <div class="list-row">
                                <div>
                                    <div class="strong" style="font-size:14px;">{{ $user->name }}</div>
                                    <div class="mini-muted">{{ $user->email }}</div>
                                </div>
                                <div class="text-right mini-muted" style="font-size:12px;white-space:nowrap;">
                                    {{ $user->created_at->diffForHumans() }}
                                </div>
                            </div>
                        @empty
                            <div class="muted" style="font-size:13px;">No recent signups.</div>
                        @endforelse
                    </div>
                </div>

                {{-- NOTES --}}
                <div class="card">
                    <div class="head">
                        <h3>Strategic Notes</h3>
                        <span>{{ now()->format('h:i A') }}</span>
                    </div>

                    <textarea class="note">- Push yearly plan
            - Build referral engine
            - Add case studies
            - Improve onboarding</textarea>
                </div>

            </div>
  
<style>
:root{
    --bg:#020817;
    --panel:#081226;
    --panel2:#0f172a;
    --line:rgba(255,255,255,.08);
    --muted:#64748b;
    --soft:#94a3b8;
    --white:#ffffff;
}

*{box-sizing:border-box}

.dashboard-shell{
    min-height:100vh;
    background:var(--bg);
    color:var(--white);
    padding:38px 24px 80px;
}

.container-xl{
    max-width:1500px;
    margin:auto;
}

.topbar{
    display:flex;
    justify-content:space-between;
    gap:20px;
    flex-wrap:wrap;
    margin-bottom:26px;
}

.eyebrow{
    color:#facc15;
    font-size:11px;
    font-weight:800;
    letter-spacing:1.4px;
    margin-bottom:10px;
}

.page-title{
    margin:0;
    font-size:48px;
    line-height:1.05;
    font-weight:900;
}

.subtitle,.subtitle-sm{
    color:var(--soft);
}

.subtitle{margin-top:10px}
.subtitle-sm{margin-top:8px}

.actions{
    display:flex;
    gap:12px;
    align-items:center;
}

.btn{
    border:none;
    cursor:pointer;
    padding:12px 18px;
    border-radius:12px;
    font-weight:700;
    color:#fff;
    text-decoration:none;
}

.btn-outline{
    background:transparent;
    border:1px solid rgba(255,255,255,.14);
}

.grid{
    display:grid;
    gap:14px;
}

.metrics-grid{
    grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
}

.filters-grid,
.funnel-grid{
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
}

.main-grid{
    display:grid;
    grid-template-columns:2fr 1fr;
    gap:18px;
    margin-top:18px;
}

.stack{display:flex;flex-direction:column;gap:18px}
.stack-sm{display:flex;flex-direction:column;gap:10px}

.card{
    background:var(--panel);
    border:1px solid var(--line);
    border-radius:18px;
    padding:22px;
}

.metric small,
.mini-box small{
    color:var(--soft);
    font-size:11px;
    display:block;
    margin-bottom:8px;
}

.metric h2,
.mini-box h2{
    margin:0;
    font-size:32px;
}

.metric span{
    font-size:12px;
    color:var(--muted);
}

.head{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:10px;
    margin-bottom:16px;
}

.head h3{
    margin:0;
    font-size:24px;
    font-weight:900;
}

.head span{
    font-size:12px;
    color:var(--muted);
}

.field,
.note{
    width:100%;
    background:#0b1730;
    border:1px solid var(--line);
    color:#fff;
    border-radius:12px;
    padding:12px 14px;
}

.note{
    min-height:150px;
    resize:vertical;
}

.table{
    width:100%;
    border-collapse:collapse;
}

.table th{
    color:var(--muted);
    font-size:13px;
    text-align:left;
    padding-bottom:12px;
}

.table td{
    padding:14px 0;
    border-top:1px solid rgba(255,255,255,.05);
} 
  
.pill{
    padding:4px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
}

.green-pill{background:rgba(34,197,94,.15);color:#22c55e;}
.yellow-pill{background:rgba(251,191,36,.15);color:#fbbf24;}
.blue-pill{background:rgba(59,130,246,.15);color:#93c5fd;}
.red-pill{background:rgba(239,68,68,.15);color:#fca5a5;}

.list-row{
    display:flex;
    justify-content:space-between;
    gap:12px;
    padding:12px 0;
    border-top:1px solid rgba(255,255,255,.04);
}

.mini-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
}

.full{grid-column:1/-1}

.mini-box{
    padding:14px;
    background:var(--panel2);
    border:1px solid rgba(255,255,255,.06);
    border-radius:12px;
}

.event-box{
    padding:10px;
    border:1px solid rgba(255,255,255,.06);
    border-radius:10px;
}

.task{
    display:flex;
    gap:12px;
    align-items:center;
    padding:12px;
    border-radius:12px;
    background:#0b1730;
}

.task input{
    width:18px;
    height:18px;
}

.task input:checked + span{
    text-decoration:line-through;
    opacity:.55;
}

.p1{border-left:4px solid #22c55e}
.p2{border-left:4px solid #facc15}
.p3{border-left:4px solid #ef4444}

.purple{color:#a78bfa}
.green{color:#22c55e}
.yellow{color:#facc15}
.blue{color:#38bdf8}
.orange{color:#f97316}

/* Utility Classes */

.green-text{
    color:#22c55e;
}

.muted{
    color:var(--soft);
}

.mini-muted{
    color:var(--soft);
    font-size:12px;
}

.text-sm{
    font-size:13px;
}

.small-title{
    font-size:14px !important;
}

.big-number{
    font-size:40px;
}

.center{
    text-align:center;
}

.text-right{
    text-align:right;
}

.font-bold{
    font-weight:700;
}

.mt-18{
    margin-top:18px;
}

@media(max-width:1150px){
    .main-grid{grid-template-columns:1fr;}
}

@media(max-width:640px){
    .page-title{font-size:34px}
    .mini-grid{grid-template-columns:1fr}
}
</style>

@endsection