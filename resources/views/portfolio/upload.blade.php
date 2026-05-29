@extends('layouts.app')

@section('title', 'Portfolio Upload Center')

@section('content')

<div
    style="
        max-width:1200px;
        margin:0 auto;
        padding:2rem 1.5rem;
    ">

    {{-- HEADER --}}
    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:1rem;
            margin-bottom:2rem;
        ">

        <div>

            <h1
                style="
                    font-size:2rem;
                    font-weight:700;
                    margin-bottom:.5rem;
                ">
                Portfolio Upload Center
            </h1>

            <p
                style="
                    color:var(--ink-3);
                    max-width:700px;
                    line-height:1.6;
                ">
                Securely upload portfolio files for
                AI-powered portfolio risk analysis
                and advisor intelligence generation.
            </p>

        </div>

        <a
            href="{{ route('dashboard') }}"

            style="
                padding:.85rem 1.2rem;
                border-radius:12px;
                border:1px solid var(--paper-3);
                text-decoration:none;
                color:inherit;
                font-weight:600;
            ">
            ← Dashboard
        </a>

    </div>

    {{-- SUCCESS MESSAGE --}}
    @if(session('success'))

    <div
        style="
                background:#dcfce7;
                border:1px solid #bbf7d0;
                color:#166534;
                padding:1rem 1.25rem;
                border-radius:14px;
                margin-bottom:1.5rem;
            ">
        {{ session('success') }}
    </div>

    @endif

    {{-- ERROR MESSAGE --}}
    @if($errors->any())

    <div
        style="
                background:#fef2f2;
                border:1px solid #fecaca;
                color:#991b1b;
                padding:1rem 1.25rem;
                border-radius:14px;
                margin-bottom:1.5rem;
            ">

        <ul
            style="
                    margin:0;
                    padding-left:1rem;
                ">

            @foreach($errors->all() as $error)

            <li>{{ $error }}</li>

            @endforeach

        </ul>

    </div>

    @endif

    {{-- MAIN GRID --}}
    <div
        style="
            display:grid;
            grid-template-columns:
                minmax(320px,420px)
                1fr;

            gap:2rem;
            align-items:start;
        ">

        {{-- LEFT PANEL --}}
        <div
            style="
                background:var(--paper-2);
                border:1px solid var(--paper-3);
                border-radius:20px;
                padding:1.75rem;
            ">

            <div
                style="
                    margin-bottom:1.5rem;
                ">

                <h2
                    style="
                        font-size:1.25rem;
                        font-weight:700;
                        margin-bottom:.5rem;
                    ">
                    Upload Portfolio
                </h2>

                <p
                    style="
                        color:var(--ink-3);
                        line-height:1.6;
                        font-size:.95rem;
                    ">
                    Supported formats:
                    CSV, XLSX, PDF, ZIP
                </p>

            </div>

            {{-- FORM --}}
            <form
                method="POST"
                action="{{ route('portfolio.upload.store') }}"
                enctype="multipart/form-data"
                id="uploadForm">

                @csrf

                {{-- PORTFOLIO --}}
                <div
                    style="
                        margin-bottom:1.25rem;
                    ">

                    <label
                        style="
                            display:block;
                            margin-bottom:.5rem;
                            font-weight:600;
                        ">
                        Select Portfolio
                    </label>

                    <select
                        name="portfolio_id"

                        style="
                            width:100%;
                            padding:.95rem 1rem;
                            border-radius:14px;
                            border:1px solid var(--paper-3);
                            background:var(--paper-1);
                        ">

                        <option value="">
                            Default Portfolio
                        </option>

                        @foreach($portfolios as $portfolio)

                        <option
                            value="{{ $portfolio->id }}"
                            @selected(old('portfolio_id') == $portfolio->id)>
                            {{ $portfolio->name }}
                        </option>

                        @endforeach

                    </select>

                </div>

                {{-- FILE --}}
                <div
                    style="
                        margin-bottom:1.5rem;
                    ">

                    <label
                        style="
                            display:block;
                            margin-bottom:.5rem;
                            font-weight:600;
                        ">
                        Upload File
                    </label>

                    <input
                        type="file"
                        name="file"
                        id="fileInput"
                        accept=".csv,.xlsx,.xls,.pdf,.zip"
                        required

                        style="
                            width:100%;
                            padding:1rem;
                            border-radius:14px;
                            border:1px dashed var(--paper-3);
                            background:var(--paper-1);
                        ">

                    <small
                        style="
                            display:block;
                            margin-top:.65rem;
                            color:var(--ink-3);
                        ">
                        Max upload size:
                        20MB
                    </small>

                </div>

                {{-- BUTTON --}}
                <button
                    type="submit"
                    id="uploadBtn"

                    style="
                        width:100%;
                        border:none;
                        border-radius:14px;
                        padding:1rem 1.25rem;
                        background:#111827;
                        color:white;
                        font-weight:700;
                        cursor:pointer;
                    ">
                    Upload Portfolio
                </button>

            </form>

        </div>

        {{-- RIGHT PANEL --}}
        <div
            style="
                background:var(--paper-2);
                border:1px solid var(--paper-3);
                border-radius:20px;
                padding:1.75rem;
            ">

            <div
                style="
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    margin-bottom:1.5rem;
                    flex-wrap:wrap;
                    gap:1rem;
                ">

                <div>

                    <h2
                        style="
                            font-size:1.25rem;
                            font-weight:700;
                            margin-bottom:.25rem;
                        ">
                        Uploaded Files
                    </h2>

                    <p
                        style="
                            color:var(--ink-3);
                            margin:0;
                        ">
                        Portfolio processing and upload history.
                    </p>

                </div>

                <div
                    style="
                        color:var(--ink-3);
                        font-size:.9rem;
                    ">
                    Total:
                    {{ $files->count() }}
                </div>

            </div>

            {{-- TABLE --}}
            <div
                style="
                    overflow-x:auto;
                ">

                <table
                    style="
                        width:100%;
                        border-collapse:collapse;
                    ">

                    <thead>

                        <tr
                            style="
                                border-bottom:1px solid var(--paper-3);
                            ">

                            <th
                                style="
                                    text-align:left;
                                    padding:1rem .75rem;
                                ">
                                File
                            </th>

                            <th
                                style="
                                    text-align:left;
                                    padding:1rem .75rem;
                                ">
                                Status
                            </th>

                            <th
                                style="
                                    text-align:left;
                                    padding:1rem .75rem;
                                ">
                                Size
                            </th>

                            <th
                                style="
                                    text-align:left;
                                    padding:1rem .75rem;
                                ">
                                Uploaded
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($files as $file)

                        @php

                        $statusBg = match ($file->status) {
                            'processed' => '#dcfce7',
                            'failed' => '#fef2f2',
                            'processing' => '#dbeafe',
                            default => '#fef3c7',
                        };

                        $statusColor = match ($file->status) {
                            'processed' => '#166534',
                            'failed' => '#991b1b',
                            'processing' => '#1d4ed8',
                            default => '#92400e',
                        };

                        @endphp

                        <tr
                            style="
                                    border-bottom:1px solid var(--paper-3);
                                ">

                            {{-- FILE --}}
                            <td
                                style="
                                        padding:1rem .75rem;
                                        font-weight:600;
                                    ">
                                {{ $file->original_name }}
                            </td>

                            {{-- STATUS --}}
                            <td
                                style="
                                        padding:1rem .75rem;
                                    ">

                                <span
                                    style="
                                            padding:.4rem .7rem;
                                            border-radius:999px;
                                            font-size:.8rem;
                                            font-weight:700;
                                            background: {{ $statusBg }};
                                            color: {{ $statusColor }};
                                        ">
                                    {{ strtoupper($file->status) }}
                                </span>

                            </td>

                            {{-- SIZE --}}
                            <td
                                style="
                                        padding:1rem .75rem;
                                    ">
                                {{ round($file->file_size / 1024, 2) }} KB
                            </td>

                            {{-- DATE --}}
                            <td
                                style="
                                        padding:1rem .75rem;
                                        color:var(--ink-3);
                                    ">
                                {{ $file->created_at->format('d M Y h:i A') }}
                            </td>

                        </tr>

                        @empty

                        <tr>

                            <td
                                colspan="4"

                                style="
                                        padding:2rem;
                                        text-align:center;
                                        color:var(--ink-3);
                                    ">
                                No portfolio uploads yet.
                            </td>

                        </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

{{-- UX SCRIPT --}}
@push('scripts')

<script>
    document.addEventListener("DOMContentLoaded", () => {

        const form = document.getElementById("uploadForm");

        const uploadBtn = document.getElementById("uploadBtn");

        form.addEventListener("submit", () => {

            uploadBtn.disabled = true;

            uploadBtn.innerText = "Uploading...";

            uploadBtn.style.opacity = ".7";
        });
    });
</script>

@endpush

@endsection
