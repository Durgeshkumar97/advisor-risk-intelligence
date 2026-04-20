<h1>RiskSignal Admin Dashboard</h1>

<p>Total Leads: {{ $totalLeads }}</p>
<p>Active Trials: {{ $activeTrials }}</p>
<p>Paid Users: {{ $paidUsers }}</p>
<p>Expired Trials: {{ $expiredTrials }}</p>

<hr>

<h2>Recent Leads</h2>

@foreach($recentLeads as $lead)
    <p>
        {{ $lead->name }} |
        {{ $lead->phone }} |
        {{ $lead->selected_plan }}
    </p>
@endforeachs