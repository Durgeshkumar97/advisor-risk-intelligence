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
            <div style="display:flex;align-items:center;gap:.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:1rem;height:1rem;flex-shrink:0;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                {{ $error }}
            </div>
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
