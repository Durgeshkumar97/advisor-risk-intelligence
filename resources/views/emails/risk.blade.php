

<h2>Hello {{ $user->name }}</h2>

<p>Your latest portfolio risk signal:</p>

<h1>{{ $score }}</h1>

<p>Status: <strong>{{ $level }}</strong></p>

<p>
@if($level == 'HIGH')
⚠️ Reduce exposure immediately.
@elseif($level == 'MEDIUM')
⚠️ Monitor positions closely.
@else
✅ Portfolio stable.
@endif
</p>

<p>
👉 <a href="{{ url('/dashboard') }}">View Dashboard</a>
</p>

<hr>

<small>RiskSignal • AI Portfolio Intelligence</small>

