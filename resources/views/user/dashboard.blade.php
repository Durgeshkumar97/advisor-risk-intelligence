@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

<div
    style="
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    ">

    {{-- HEADER --}}

    <div
        style="
            margin-bottom: 2rem;
        ">

        <h1
            style="
                font-size: 2rem;
                font-weight: 700;
                margin-bottom: .5rem;
            ">
            Welcome,
            {{ $user->name }}
        </h1>

        <p
            style="
                color: var(--ink-3);
            ">
            Your RiskSignal advisor dashboard.
        </p>

    </div>

    {{-- GRID --}}

    <div
        style="
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
            gap:1.5rem;
        ">

        {{-- PLAN --}}

        <div class="card">

            <h3>Subscription</h3>

            <p>
                {{ $planName }}
            </p>

            <p>
                Days Left:
                {{ $daysLeft }}
            </p>

        </div>

        {{-- RISK SCORE --}}

        <div class="card">

            <h3>Risk Score</h3>

            <p
                style="
                    font-size:2rem;
                    font-weight:700;
                ">
                {{ $riskScore }}
            </p>

            <p>
                {{ $riskLevel }} RISK
            </p>

        </div>

        {{-- RECOMMENDATION --}}

        <div class="card">

            <h3>Recommendation</h3>

            <p>
                {{ $recommendation }}
            </p>

        </div>

        {{-- NEXT ACTION --}}

        <div class="card">

            <h3>Next Action</h3>

            <p>
                {{ $nextAction }}
            </p>

        </div>

    </div>

    {{-- CTA SECTION --}}

    <div
        style="
            margin-top:3rem;
            display:flex;
            gap:1rem;
            flex-wrap:wrap;
        ">

        <a
            href="{{ route('portfolio.upload') }}"
            class="btn btn-dark">
            Upload Portfolio
        </a>

        <a
            href="#"
            class="btn btn-outline-dark">
            View Risk Reports
        </a>

    </div>

</div>

@endsection
