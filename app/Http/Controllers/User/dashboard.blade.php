@extends('layouts.app')

@section('content')

<div style="max-width:900px;margin:60px auto;color:#fff;">

    <h1>Welcome, {{ $user->name }}</h1>

    {{-- PLAN --}}
    <div style="margin-top:30px;padding:20px;background:#0f172a;border-radius:12px;">
        <h3>Your Plan</h3>
        <p><strong>{{ ucfirst($plan->name) }}</strong></p>
        <p>Expires in: {{ $daysLeft }} days</p>
    </div>

    {{-- RISK SIGNAL --}}
    <div style="margin-top:20px;padding:20px;background:#111827;border-radius:12px;">
        <h3>Risk Signal</h3>
        <h2>{{ $riskScore }}</h2>
        <p>Status: <strong>{{ $riskLevel }}</strong></p>
    </div>

    {{-- NEXT ACTION --}}
    <div style="margin-top:20px;padding:20px;background:#1e293b;border-radius:12px;">
        <h3>Next Action</h3>
        <p>{{ $nextAction }}</p>
    </div>

</div>

@endsection