@extends('layouts.app')

@section('title', 'Portfolio Upload Center — RiskSignal')

@section('content')

<div style="max-width:1200px;margin:0 auto;padding:2rem 0;">

    {{-- HEADER --}}
    <div style="
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        flex-wrap:wrap;
        gap:1rem;
        margin-bottom:2rem;
    ">

        <div>

            <div class="eyebrow" style="margin-bottom:.4rem;">
                {{ $planName }} Plan
            </div>

            <h1 style="font-size:1.85rem;font-weight:800;margin-bottom:.4rem;">
                Portfolio Upload Center
            </h1>

            <p style="color:var(--ink-3);font-size:.9rem;line-height:1.5;">
                Securely upload portfolio files for AI-powered risk analysis.
                &nbsp;·&nbsp;
                <strong>{{ $monthlyClientCount }} / {{ $monthlyClientLimit }}</strong> clients this month &nbsp;·&nbsp; resets {{ $monthlyResetDate }}
            </p>

        </div>

        <a
            href="{{ route('dashboard') }}"
            style="
                padding:.75rem 1.2rem;
                border-radius:12px;
                border:1px solid var(--paper-3);
                text-decoration:none;
                color:inherit;
                font-weight:600;
                font-size:.875rem;
                white-space:nowrap;
            ">
            ← Dashboard
        </a>

    </div>

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
    <div style="
        display:grid;
        grid-template-columns:minmax(300px,420px) 1fr;
        gap:2rem;
        align-items:start;
        min-width:0;
    ">

        {{-- ============================================================
           LEFT — UPLOAD CARD
        ============================================================ --}}
        <div class="card" style="padding:1.75rem;min-width:0;overflow:hidden;">

            <p style="color:var(--ink-3);font-size:.875rem;margin-bottom:1.5rem;line-height:1.5;">
                Supported: CSV, XLSX, XLS, PDF &nbsp;·&nbsp; Max 20 MB
            </p>

            <form
                method="POST"
                action="{{ route('portfolio.upload.store') }}"
                enctype="multipart/form-data"
                id="uploadForm">

                @csrf

                {{-- PORTFOLIO SELECT --}}
                <div style="margin-bottom:1.25rem;">

                    <label style="display:block;margin-bottom:.5rem;font-weight:600;font-size:.875rem;">
                        Select Portfolio
                    </label>

                    <select
                        name="portfolio_id"
                        class="form-select">

                        <option value="">Default Portfolio</option>

                        @foreach($portfolios as $portfolio)
                        <option
                            value="{{ $portfolio->id }}"
                            @selected(old('portfolio_id') == $portfolio->id)>
                            {{ $portfolio->name }}
                        </option>
                        @endforeach

                    </select>

                </div>

                {{-- FILE INPUT --}}
                <div style="margin-bottom:1.5rem;">

                    <label style="display:block;margin-bottom:.5rem;font-weight:600;font-size:.875rem;">
                        Choose File
                    </label>

                    <div
                        id="dropZone"
                        style="
                            border:2px dashed var(--paper-3);
                            border-radius:14px;
                            padding:1.5rem 1rem;
                            text-align:center;
                            cursor:pointer;
                            transition:.2s ease;
                            position:relative;
                        "
                        onclick="document.getElementById('fileInput').click()">

                        <div id="dropLabel" style="pointer-events:none;">
                            <div style="font-size:1.75rem;margin-bottom:.5rem;">📂</div>
                            <div style="font-weight:600;font-size:.9rem;margin-bottom:.25rem;">
                                Drop file here or click to browse
                            </div>
                            <div style="color:var(--ink-3);font-size:.8rem;">
                                CSV, XLSX, XLS, PDF — up to 20 MB
                            </div>
                        </div>

                        <input
                            type="file"
                            name="file"
                            id="fileInput"
                            accept=".csv,.xlsx,.xls,.pdf"
                            required
                            style="
                                position:absolute;
                                inset:0;
                                opacity:0;
                                cursor:pointer;
                                width:100%;
                                height:100%;
                            ">

                    </div>

                    <div
                        id="filePreview"
                        style="display:none;margin-top:.75rem;padding:.6rem .9rem;border-radius:10px;background:rgba(16,185,129,.1);color:#34d399;font-size:.85rem;font-weight:600;">
                    </div>

                </div>

                {{-- SUBMIT --}}
                <button
                    type="submit"
                    id="uploadBtn"
                    style="
                        width:100%;
                        border:none;
                        border-radius:12px;
                        padding:1rem 1.25rem;
                        background:linear-gradient(135deg,#111827,#1f2937);
                        color:white;
                        font-weight:700;
                        font-size:.95rem;
                        cursor:pointer;
                        transition:.2s ease;
                    "
                    onmouseover="this.style.opacity='.88'"
                    onmouseout="this.style.opacity='1'">
                    Upload Portfolio →
                </button>

            </form>

        </div>
        {{-- /left card --}}

        {{-- ============================================================
           RIGHT — UPLOAD HISTORY
        ============================================================ --}}
        <div class="card" style="padding:1.75rem;min-width:0;overflow:hidden;">

            <div style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                margin-bottom:1.5rem;
                flex-wrap:wrap;
                gap:.75rem;
            ">

                <div>
                    <h2 style="font-size:1.15rem;font-weight:700;margin-bottom:.2rem;">
                        Upload History
                    </h2>
                    <p style="color:var(--ink-3);font-size:.875rem;margin:0;">
                        Portfolio processing and file status.
                    </p>
                </div>

                <span style="
                    color:var(--ink-3);
                    font-size:.85rem;
                    font-weight:600;
                ">
                    {{ $files->count() }} {{ Str::plural('file', $files->count()) }}
                </span>

            </div>

            {{-- TABLE --}}
            <div style="overflow-x:auto;">

                <table style="width:100%;border-collapse:collapse;font-size:.875rem;">

                    <thead>
                        <tr style="border-bottom:1px solid var(--paper-3);">
                            <th style="text-align:left;padding:.75rem .6rem;font-weight:700;color:var(--ink-2);">File</th>
                            <th style="text-align:left;padding:.75rem .6rem;font-weight:700;color:var(--ink-2);">Status</th>
                            <th style="text-align:left;padding:.75rem .6rem;font-weight:700;color:var(--ink-2);">Size</th>
                            <th style="text-align:left;padding:.75rem .6rem;font-weight:700;color:var(--ink-2);">Uploaded</th>
                            <th style="text-align:center;padding:.75rem .6rem;font-weight:700;color:var(--ink-2);">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($files as $file)

                        @php
                            $statusStyles = match($file->status) {
                                'processed'  => ['bg' => 'rgba(16,185,129,.15)',  'color' => '#34d399',  'label' => '✓ Processed'],
                                'failed'     => ['bg' => 'rgba(239,68,68,.15)',   'color' => '#fca5a5',  'label' => '✗ Failed'],
                                'processing' => ['bg' => 'rgba(59,130,246,.15)',  'color' => '#93c5fd',  'label' => '⟳ Processing'],
                                default      => ['bg' => 'rgba(251,191,36,.15)',  'color' => '#fbbf24',  'label' => '⏳ Pending'],
                            };

                            // Human-readable file size
                            $bytes = $file->file_size;
                            if ($bytes >= 1048576) {
                                $sizeLabel = round($bytes / 1048576, 1) . ' MB';
                            } elseif ($bytes >= 1024) {
                                $sizeLabel = round($bytes / 1024, 1) . ' KB';
                            } else {
                                $sizeLabel = $bytes . ' B';
                            }
                        @endphp

                        <tr style="border-bottom:1px solid var(--paper-3);">

                            {{-- FILE NAME --}}
                            <td style="padding:.85rem .6rem;font-weight:600;max-width:220px;">
                                <div style="
                                    white-space:nowrap;
                                    overflow:hidden;
                                    text-overflow:ellipsis;
                                    max-width:200px;
                                " title="{{ $file->original_name }}">
                                    {{ $file->original_name }}
                                </div>
                                @if($file->portfolio)
                                <div style="color:var(--ink-3);font-size:.78rem;margin-top:.15rem;">
                                    {{ $file->portfolio->name }}
                                </div>
                                @endif
                            </td>

                            {{-- STATUS --}}
                            <td style="padding:.85rem .6rem;">
                                <span style="
                                    display:inline-block;
                                    padding:.3rem .7rem;
                                    border-radius:999px;
                                    font-size:.75rem;
                                    font-weight:700;
                                    background:{{ $statusStyles['bg'] }};
                                    color:{{ $statusStyles['color'] }};
                                    white-space:nowrap;
                                ">
                                    {{ $statusStyles['label'] }}
                                </span>
                                @if($file->isFailed() && isset($file->meta['error_message']))
                                <div style="color:#fca5a5;font-size:.75rem;margin-top:.25rem;max-width:160px;">
                                    {{ Str::limit($file->meta['error_message'], 60) }}
                                </div>
                                @endif
                            </td>

                            {{-- SIZE --}}
                            <td style="padding:.85rem .6rem;color:var(--ink-3);white-space:nowrap;">
                                {{ $sizeLabel }}
                            </td>

                            {{-- DATE --}}
                            <td style="padding:.85rem .6rem;color:var(--ink-3);white-space:nowrap;">
                                {{ $file->created_at->format('d M Y') }}<br>
                                <span style="font-size:.78rem;">{{ $file->created_at->format('h:i A') }}</span>
                            </td>

                            {{-- ACTIONS --}}
                            <td style="padding:.85rem .6rem;text-align:center;">

                                <div style="display:flex;gap:.5rem;justify-content:center;align-items:center;">

                                    {{-- DOWNLOAD --}}
                                    <a
                                        href="{{ route('file.view', $file->id) }}"
                                        title="Download"
                                        style="
                                            display:inline-flex;
                                            align-items:center;
                                            justify-content:center;
                                            width:32px;height:32px;
                                            border-radius:8px;
                                            background:rgba(59,130,246,.12);
                                            color:#93c5fd;
                                            text-decoration:none;
                                            font-size:.9rem;
                                            transition:.15s ease;
                                        "
                                        onmouseover="this.style.background='rgba(59,130,246,.22)'"
                                        onmouseout="this.style.background='rgba(59,130,246,.12)'"
                                    >⬇</a>

                                    {{-- DELETE (not while processing) --}}
                                    @unless($file->isProcessing())
                                    <form
                                        method="POST"
                                        action="{{ route('portfolio.file.destroy', $file->id) }}"
                                        onsubmit="return confirm('Delete \'{{ addslashes($file->original_name) }}\'? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            title="Delete"
                                            style="
                                                display:inline-flex;
                                                align-items:center;
                                                justify-content:center;
                                                width:32px;height:32px;
                                                border-radius:8px;
                                                border:none;
                                                background:rgba(239,68,68,.12);
                                                color:#fca5a5;
                                                cursor:pointer;
                                                font-size:.9rem;
                                                transition:.15s ease;
                                            "
                                            onmouseover="this.style.background='rgba(239,68,68,.25)'"
                                            onmouseout="this.style.background='rgba(239,68,68,.12)'"
                                        >✕</button>
                                    </form>
                                    @endunless

                                </div>

                            </td>

                        </tr>

                        @empty

                        <tr>
                            <td colspan="5" style="padding:2.5rem;text-align:center;color:var(--ink-3);">
                                <div style="font-size:2rem;margin-bottom:.5rem;">📁</div>
                                <div style="font-weight:600;">No files uploaded yet</div>
                                <div style="font-size:.85rem;margin-top:.25rem;">Upload your first portfolio file using the form on the left.</div>
                            </td>
                        </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

    const form      = document.getElementById('uploadForm');
    const btn       = document.getElementById('uploadBtn');
    const fileInput = document.getElementById('fileInput');
    const dropZone  = document.getElementById('dropZone');
    const preview   = document.getElementById('filePreview');

    // FILE SELECTION PREVIEW
    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file) { preview.style.display = 'none'; return; }
        const mb   = file.size / 1048576;
        const size = mb >= 1 ? mb.toFixed(1) + ' MB' : (file.size / 1024).toFixed(1) + ' KB';
        preview.textContent = '✓  ' + file.name + '  (' + size + ')';
        preview.style.display = 'block';
        dropZone.style.borderColor = '#34d399';
    });

    // DRAG AND DROP
    ['dragenter','dragover'].forEach(evt => {
        dropZone.addEventListener(evt, e => {
            e.preventDefault();
            dropZone.style.borderColor = '#fbbf24';
            dropZone.style.background  = 'rgba(251,191,36,.06)';
        });
    });

    ['dragleave','drop'].forEach(evt => {
        dropZone.addEventListener(evt, e => {
            e.preventDefault();
            dropZone.style.borderColor = 'var(--paper-3)';
            dropZone.style.background  = '';
        });
    });

    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        const dt = e.dataTransfer;
        if (dt.files.length) {
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });

    // SUBMIT STATE
    form.addEventListener('submit', () => {
        btn.disabled      = true;
        btn.textContent   = 'Uploading…';
        btn.style.opacity = '.65';
    });
});
</script>
@endpush

@endsection
