@extends('layouts.app')

@section('content')

<div class="min-h-screen bg-slate-950 text-white py-10">

    <div class="max-w-7xl mx-auto px-6">

        {{-- HEADER --}}
        <div class="mb-10">
            <h1 class="text-4xl font-bold">
                Your Risk Dashboard
            </h1>

            <p class="text-slate-400 mt-2">
                AI-powered portfolio intelligence and risk monitoring.
            </p>
        </div>

        {{-- METRICS --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            {{-- PLAN --}}
            <div class="bg-slate-900 rounded-2xl p-6 border border-slate-800">

                <p class="text-slate-400 text-sm mb-2">
                    Current Plan
                </p>

                <h2 class="text-2xl font-bold">
                    {{ $planName }}
                </h2>

            </div>

            {{-- EXPIRY --}}
            <div class="bg-slate-900 rounded-2xl p-6 border border-slate-800">

                <p class="text-slate-400 text-sm mb-2">
                    Expiry Date
                </p>

                <h2 class="text-2xl font-bold">
                    {{ $expiryDate ? \Carbon\Carbon::parse($expiryDate)->format('d M Y') : 'N/A' }}
                </h2>

            </div>

            {{-- DAYS LEFT --}}
            <div class="bg-slate-900 rounded-2xl p-6 border border-slate-800">

                <p class="text-slate-400 text-sm mb-2">
                    Days Remaining
                </p>

                <h2 class="text-2xl font-bold">
                    {{ $daysLeft }}
                </h2>

            </div>

        </div>

        {{-- RISK SIGNAL --}}
        <div class="mt-8 bg-slate-900 rounded-2xl p-8 border border-slate-800">

            <div class="flex items-center justify-between">

                <div>
                    <p class="text-slate-400 text-sm">
                        Market Risk Signal
                    </p>

                    <h2 class="text-6xl font-bold mt-3">
                        {{ $riskScore }}
                    </h2>
                </div>

                <div>

                    @if($riskLevel === 'LOW')
                        <span class="px-4 py-2 rounded-full bg-green-500/20 text-green-400">
                            LOW RISK
                        </span>
                    @elseif($riskLevel === 'MEDIUM')
                        <span class="px-4 py-2 rounded-full bg-yellow-500/20 text-yellow-400">
                            MEDIUM RISK
                        </span>
                    @else
                        <span class="px-4 py-2 rounded-full bg-red-500/20 text-red-400">
                            HIGH RISK
                        </span>
                    @endif

                </div>

            </div>

            <p class="text-slate-300 mt-6 text-lg">
                {{ $recommendation }}
            </p>

        </div>

        {{-- NEXT ACTION --}}
        <div class="mt-8 bg-slate-900 rounded-2xl p-8 border border-slate-800">

            <h3 class="text-2xl font-semibold mb-4">
                Next Action
            </h3>

            <p class="text-slate-300 text-lg">
                {{ $nextAction }}
            </p>

            @if($daysLeft <= 3)

                <a href="/pricing"
                   class="inline-block mt-6 bg-indigo-600 hover:bg-indigo-700 px-6 py-3 rounded-xl font-semibold transition">
                    Renew Subscription
                </a>

            @endif

        </div>

    </div>

</div>

@endsection
