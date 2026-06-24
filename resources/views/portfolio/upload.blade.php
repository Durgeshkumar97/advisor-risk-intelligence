@extends('layouts.app')

@section('title', 'Portfolio Upload Center — RiskSignal')

@section('content')

<div style="max-width:1200px;margin:0 auto;padding:2rem 0;">

    @include('portfolio.upload._header')

    {{-- VALIDATION ERRORS --}}
    @if($errors->any())

    <div style="
        background:rgba(239,68,68,.1);
        border:1px solid rgba(239,68,68,.2);
        color:#fca5a5;
        padding:1rem 1.25rem;
        border-radius:14px;
        margin-bottom:1.5rem;
        font-weight:600;
        font-size:.9rem;
    ">
        @foreach($errors->all() as $error)
            <div>⚠️ {{ $error }}</div>
        @endforeach
    </div>

    @endif

    {{-- MAIN GRID --}}
    <div class="upload-grid">

        @include('portfolio.upload._upload-form')

        @include('portfolio.upload._history')

    </div>

</div>

@endsection

@include('portfolio.upload._scripts')
