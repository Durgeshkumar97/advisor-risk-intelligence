@extends('layouts.app')

@section('title', 'New Portfolio — RiskSignal')

@section('content')

<div style="max-width:600px;margin:0 auto;padding:2rem 0;">

    {{-- HEADER --}}
    <div style="margin-bottom:2rem;">
        <div class="eyebrow" style="margin-bottom:.4rem;">Portfolio Management</div>
        <h1 style="font-size:1.85rem;font-weight:800;margin-bottom:.4rem;">New Portfolio</h1>
        <p style="color:var(--ink-3);font-size:.9rem;">Create a portfolio, then upload a holdings file to it from the portfolio list.</p>
    </div>

    {{-- ALERTS --}}
    @if($errors->any())
        <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:.85rem 1.1rem;border-radius:12px;margin-bottom:1.5rem;font-size:.9rem;">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- CREATE FORM --}}
    <div style="background:var(--paper-2);border:1px solid var(--paper-3);border-radius:16px;padding:1.5rem;">
        <form method="POST" action="{{ route('portfolio.store') }}">
            @csrf

            <label style="display:block;font-size:.85rem;font-weight:700;margin-bottom:.4rem;">Portfolio Name</label>
            <input
                type="text"
                name="name"
                placeholder="Portfolio name (e.g. Client A — Equity)"
                value="{{ old('name') }}"
                required
                style="width:100%;padding:.75rem 1rem;border-radius:10px;border:1px solid var(--paper-3);background:var(--paper);color:inherit;font-size:.9rem;font-family:inherit;margin-bottom:1.25rem;"
            >

            <label style="display:block;font-size:.85rem;font-weight:700;margin-bottom:.4rem;">Client Name</label>
            <input
                type="text"
                name="client_name"
                placeholder="e.g. Rajesh Sharma"
                value="{{ old('client_name') }}"
                style="width:100%;padding:.75rem 1rem;border-radius:10px;border:1px solid var(--paper-3);background:var(--paper);color:inherit;font-size:.9rem;font-family:inherit;margin-bottom:1.5rem;"
            >

            <div style="display:flex;gap:10px;">
                <button type="submit" style="padding:.75rem 1.4rem;border-radius:10px;background:var(--accent);color:#fff;border:none;font-weight:700;font-size:.9rem;cursor:pointer;">
                    Create Portfolio
                </button>
                <a href="{{ route('portfolio.manage') }}" style="padding:.75rem 1.4rem;border-radius:10px;border:1px solid var(--paper-3);text-decoration:none;color:inherit;font-weight:600;font-size:.9rem;">
                    Cancel
                </a>
            </div>
        </form>
    </div>

</div>

@endsection
