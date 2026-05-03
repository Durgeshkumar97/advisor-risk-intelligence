@extends('layouts.app')

@section('content')

<div class="dashboard-shell">

    <div class="container-xl">

        <h1 class="page-title">Your Risk Dashboard</h1>

        {{-- PLAN INFO --}}
        <div class="grid metrics-grid">

            <div class="card">
                <small>Current Plan</small>
                <h2>{{ $planName }}</h2>
            </div>

            <div class="card">
                <small>Expiry Date</small>
                <h2>
                    {{ $expiryDate ? \Carbon\Carbon::parse($expiryDate)->format('d M Y') : 'N/A' }}
                </h2>
            </div>

            <div class="card">
                <small>Days Remaining</small>
                <h2>{{ $daysLeft }}</h2>
            </div>

        </div>

        {{-- VALUE BLOCK (CORE PRODUCT) --}}
        <div class="card mt-18">

            <h3>Market Risk Signal</h3>

            <h2 class="big-number">
                {{ $riskScore }}
            </h2>

            <p>Risk Level: <strong>{{ $riskLevel }}</strong></p>

            <p class="subtitle-sm">
                {{ $recommendation }}
            </p>

        </div>

        {{-- NEXT ACTION --}}
        <div class="card mt-18">

            <h3>Next Action</h3>

            <p class="subtitle-sm">
                {{ $nextAction }}
            </p>

            @if($daysLeft <= 3)
                <a href="/pricing" class="btn btn-outline mt-10">
                    Renew Now
                </a>
            @endif

        </div>

    </div>

</div>

@endsection