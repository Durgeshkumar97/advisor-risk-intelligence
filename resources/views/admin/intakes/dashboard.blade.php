@extends('layouts.app')

@section('content')

@php
    $conversion = $totalLeads > 0 ? round(($paidUsers / $totalLeads) * 100) : 0;

    $starterPrice = 999;
    $proPrice = 2499;

    $mrr = $paidUsers * $proPrice;
    $arr = $mrr * 12;

    $pipelineMRR = $totalLeads * $starterPrice;
    $expiringSoon = max($activeTrials - 1, 0);
    $renewalValue = $expiringSoon * $starterPrice;

    $cac = 320;
    $ltv = $paidUsers > 0 ? 19992 : 19992;
    $ltvCac = round($ltv / $cac, 1);

    $runwayMonths = 17;
@endphp

<div style="
    background:#020817;
    min-height:100vh;
    color:#fff;
    padding:38px 24px 80px;
">

<div style="max-width:1500px;margin:0 auto;">

    {{-- HEADER --}}
    <div style="
        display:flex;
        justify-content:space-between;
        gap:20px;
        flex-wrap:wrap;
        align-items:flex-start;
        margin-bottom:26px;
    ">

        <div>
            <div style="
                color:#facc15;
                font-size:11px;
                font-weight:800;
                letter-spacing:1.4px;
                text-transform:uppercase;
                margin-bottom:10px;
            ">
                ARR COMMAND MODE
            </div>

            <h1 style="
                margin:0;
                font-size:48px;
                line-height:1.05;
                font-weight:900;
            ">
                RiskSignal Growth OS
            </h1>

            <p style="
                margin-top:10px;
                color:#94a3b8;
                font-size:15px;
            ">
                Revenue visibility. Growth intelligence. Founder control.
            </p>
        </div>

        <div style="display:flex;gap:12px;align-items:center;">

            <a href="/" style="
                text-decoration:none;
                color:#fff;
                border:1px solid rgba(255,255,255,.14);
                padding:12px 18px;
                border-radius:12px;
                font-weight:600;
                display:inline-block;
            ">
                View Site
            </a>

            <form method="POST" action="{{ route('admin.logout') }}" style="margin:0;">
                @csrf

                <button type="submit" style="
                    background:transparent;
                    color:#fff;
                    border:1px solid rgba(255,255,255,.14);
                    padding:12px 18px;
                    border-radius:12px;
                    cursor:pointer;
                    font-weight:600;
                ">
                    Logout
                </button>
            </form>
        </div>
    </div>

    {{-- TOP METRICS --}}
    <div class="grid6">

        <div class="metric">
            <small>MRR</small>
            <h2 style="color:#a78bfa;">₹{{ number_format($mrr) }}</h2>
            <span>Booked monthly revenue</span>
        </div>

        <div class="metric">
            <small>ARR</small>
            <h2>₹{{ number_format($arr) }}</h2>
            <span>Annual recurring revenue</span>
        </div>

        <div class="metric">
            <small>Pipeline MRR</small>
            <h2 style="color:#22c55e;">₹{{ number_format($pipelineMRR) }}</h2>
            <span>Potential from leads</span>
        </div>

        <div class="metric">
            <small>Conversion</small>
            <h2 style="color:#facc15;">{{ $conversion }}%</h2>
            <span>Lead → paid</span>
        </div>

        <div class="metric">
            <small>LTV:CAC</small>
            <h2 style="color:#38bdf8;">{{ $ltvCac }}x</h2>
            <span>Unit economics</span>
        </div>

        <div class="metric">
            <small>Runway</small>
            <h2>{{ $runwayMonths }}m</h2>
            <span>Estimated</span>
        </div>

    </div>


    {{-- FILTERS --}}
    <div class="panel" style="margin-top:18px;margin-bottom:18px;">
        <div class="grid4">

            <select class="field">
                <option>All Plans</option>
                <option>Starter</option>
                <option>Pro</option>
                <option>Team</option>
            </select>

            <select class="field">
                <option>All Status</option>
                <option>Trial</option>
                <option>Paid</option>
                <option>Expired</option>
            </select>

            <select class="field">
                <option>Today</option>
                <option>1 Week</option>
                <option>2 Weeks</option>
                <option>1 Month</option>
                <option>Quarterly</option>
                <option>Half-Yearly</option>
                <option>Yearly</option>
            </select>

            <input class="field" placeholder="Search lead / phone / email">

        </div>
    </div>


    {{-- MAIN GRID --}}
    <div class="mainGrid">

        {{-- LEFT --}}
        <div style="display:flex;flex-direction:column;gap:18px;">

            {{-- PIPELINE --}}
            <div class="panel">
                <div class="head">
                    <h3>Revenue Pipeline</h3>
                    <span>{{ count($recentLeads) }} active leads</span>
                </div>

                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr class="thead">
                            <th align="left">Lead</th>
                            <th align="left">Plan</th>
                            <th align="left">Stage</th>
                            <th align="left">Value</th>
                            <th align="left">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                    @foreach($recentLeads as $lead)
                        <tr class="rowx">
                            <td>
                                <strong>{{ $lead->name }}</strong><br>
                                <small>{{ $lead->phone }}</small>
                            </td>
                            <td>{{ ucfirst($lead->selected_plan) }}</td>
                            <td><span class="pill green">Trial</span></td>
                            <td>₹999</td>
                            <td style="color:#22c55e;">Follow-up</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>


            {{-- OPPORTUNITIES --}}
            <div class="panel">
                <div class="head">
                    <h3>Upgrade Opportunities</h3>
                    <span>High intent leads</span>
                </div>

                @foreach($recentLeads as $lead)
                <div style="
                    display:flex;
                    justify-content:space-between;
                    padding:12px 0;
                    border-top:1px solid rgba(255,255,255,.04);
                ">
                    <div>
                        <div style="font-weight:700">{{ $lead->name }}</div>
                        <div style="font-size:12px;color:#64748b">{{ $lead->phone }}</div>
                    </div>

                    <div style="text-align:right">
                        <div>{{ ucfirst($lead->selected_plan) }}</div>
                        <div style="font-size:12px;color:#22c55e;">Likely Upgrade</div>
                    </div>
                </div>
                @endforeach
            </div>


            {{-- FUNNEL --}}
            <div class="panel">
                <div class="head">
                    <h3>Weekly Funnel</h3>
                    <span>Leak detection</span>
                </div>

                <div class="grid4" style="text-align:center;">
                    <div><small>Visitors</small><h2>112</h2></div>
                    <div><small>Leads</small><h2>18</h2></div>
                    <div><small>Trials</small><h2>6</h2></div>
                    <div><small>Paid</small><h2>1</h2></div>
                </div>
            </div>

        </div>


        {{-- RIGHT --}}
        <div style="display:flex;flex-direction:column;gap:18px;">

            {{-- RENEWALS --}}
            <div class="panel">
                <div class="head">
                    <h3>Renewals At Risk</h3>
                    <span>7 days</span>
                </div>

                <h2 style="font-size:40px;color:#f97316;margin:0;">
                    ₹{{ number_format($renewalValue) }}
                </h2>

                <p style="color:#94a3b8;margin-top:8px;">
                    {{ $expiringSoon }} accounts need conversion push
                </p>
            </div>


            {{-- FOUNDER PRIORITIES --}}
            <div class="panel">
                <div class="head">
                    <h3>Founder Priorities</h3>
                    <span>Today stack</span>
                </div>

                <div style="display:flex;flex-direction:column;gap:14px;">

                    <label class="taskrow p1">
                        <input type="checkbox">
                        <span><strong>1.</strong> Close first Pro user</span>
                    </label>

                    <label class="taskrow p2">
                        <input type="checkbox">
                        <span><strong>2.</strong> Improve landing conversion copy</span>
                    </label>

                    <label class="taskrow p2">
                        <input type="checkbox">
                        <span><strong>2.</strong> Enable Razorpay auto capture</span>
                    </label>

                    <label class="taskrow p3">
                        <input type="checkbox">
                        <span><strong>3.</strong> Polish dashboard styling</span>
                    </label>

                    <label class="taskrow p3">
                        <input type="checkbox">
                        <span><strong>3.</strong> Brainstorm referral engine</span>
                    </label>

                    <label class="taskrow p3">
                        <input type="checkbox">
                        <span><strong>3.</strong> Organize founder notes</span>
                    </label>

                </div>
            </div>


            {{-- NOTES --}}
            <div class="panel">
                <div class="head">
                    <h3>Strategic Notes</h3>
                    <span>{{ now()->format('h:i A') }}</span>
                </div>

                <textarea class="note">
- Push yearly plan
- Build referral engine
- Add case studies
- Improve onboarding
                </textarea>
            </div>


            {{-- SIGNALS --}}
            <div class="panel">
                <div class="head">
                    <h3>Signals</h3>
                    <span>Live engine</span>
                </div>

                <div class="sig green">Lead Flow Healthy</div>
                <div class="sig yellow">Upsell Window Open</div>
                <div class="sig blue">Paid Base Growing</div>
                <div class="sig red">Need More Conversions</div>
            </div>

        </div>

    </div>

</div>
</div>


<style>
.grid6{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
gap:14px;
}

.grid4{
display:grid;
grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
gap:12px;
}

.mainGrid{
display:grid;
grid-template-columns:2fr 1fr;
gap:18px;
}

.metric,.panel{
background:#081226;
border:1px solid rgba(255,255,255,.08);
border-radius:18px;
padding:22px;
}

.metric small{
display:block;
color:#64748b;
margin-bottom:8px;
}

.metric h2{
margin:0;
font-size:34px;
font-weight:900;
}

.metric span{
font-size:12px;
color:#64748b;
}

.head{
display:flex;
justify-content:space-between;
align-items:center;
margin-bottom:16px;
}

.head h3{
margin:0;
font-size:28px;
font-weight:900;
}

.head span{
font-size:12px;
color:#64748b;
}

.field{
width:100%;
background:#0b1730;
border:1px solid rgba(255,255,255,.08);
color:#fff;
padding:12px 14px;
border-radius:12px;
}

.btnx{
padding:12px 18px;
border:1px solid rgba(255,255,255,.12);
border-radius:12px;
text-decoration:none;
color:#fff;
}

.thead{
color:#64748b;
font-size:13px;
}

.rowx{
border-top:1px solid rgba(255,255,255,.05);
}

.rowx td{
padding:14px 0;
}

.pill{
padding:4px 10px;
border-radius:999px;
font-size:12px;
font-weight:700;
}

.green{background:rgba(34,197,94,.15);color:#22c55e;}
.yellow{background:rgba(250,204,21,.15);color:#facc15;}
.red{background:rgba(239,68,68,.15);color:#ef4444;}
.blue{background:rgba(56,189,248,.15);color:#38bdf8;}

.note{
width:100%;
min-height:150px;
background:#0b1730;
border:1px solid rgba(255,255,255,.06);
border-radius:12px;
color:#fff;
padding:14px;
resize:vertical;
}

.sig{
padding:12px;
border-radius:12px;
margin-bottom:10px;
font-weight:700;
}

.taskrow{
display:flex;
align-items:center;
gap:12px;
padding:12px;
border-radius:12px;
background:#0b1730;
cursor:pointer;
}

.taskrow input{
width:18px;
height:18px;
}

.taskrow input:checked + span{
text-decoration:line-through;
opacity:.55;
}

.p1{border-left:4px solid #22c55e;}
.p2{border-left:4px solid #facc15;}
.p3{border-left:4px solid #ef4444;}



@media(max-width:1150px){
.mainGrid{
grid-template-columns:1fr;
}
}
</style>

@endsection