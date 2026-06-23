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
           LEFT — UPLOAD / MANUAL ENTRY CARD
        ============================================================ --}}
        <div class="card" style="padding:1.75rem;min-width:0;overflow:hidden;" id="entryCard">

            {{-- ── TOP-LEVEL TAB TOGGLE ─────────────────────────────── --}}
            <div style="
                display:flex;
                border-radius:12px;
                background:var(--paper-2);
                padding:4px;
                margin-bottom:1.5rem;
                gap:4px;
            " role="tablist">

                <button
                    type="button"
                    role="tab"
                    id="tab-upload"
                    onclick="switchTab('upload')"
                    style="
                        flex:1;
                        padding:.6rem 1rem;
                        border-radius:9px;
                        border:none;
                        font-weight:700;
                        font-size:.85rem;
                        cursor:pointer;
                        transition:.15s ease;
                    ">
                    Upload File
                </button>

                <button
                    type="button"
                    role="tab"
                    id="tab-manual"
                    onclick="switchTab('manual')"
                    style="
                        flex:1;
                        padding:.6rem 1rem;
                        border-radius:9px;
                        border:none;
                        font-weight:700;
                        font-size:.85rem;
                        cursor:pointer;
                        transition:.15s ease;
                    ">
                    Enter Manually
                </button>

            </div>

            {{-- ── PANE: UPLOAD FILE ────────────────────────────────── --}}
            <div id="pane-upload">

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
                            style="
                                width:100%;
                                padding:.85rem 1rem;
                                border-radius:12px;
                                border:1px solid var(--paper-3);
                                background:var(--paper-1);
                                font-size:.9rem;
                                color:inherit;
                            ">

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
            {{-- /pane-upload --}}

            {{-- ── PANE: ENTER MANUALLY ─────────────────────────────── --}}
            <div id="pane-manual" style="display:none;">

                {{-- Second-level toggle: Add Holdings | Quick Allocation --}}
                <div style="
                    display:flex;
                    border-radius:10px;
                    background:var(--paper-2);
                    padding:3px;
                    margin-bottom:1.5rem;
                    gap:3px;
                " role="tablist">

                    <button
                        type="button"
                        role="tab"
                        id="subtab-holdings"
                        onclick="switchSubTab('holdings')"
                        style="
                            flex:1;
                            padding:.5rem .75rem;
                            border-radius:8px;
                            border:none;
                            font-weight:600;
                            font-size:.8rem;
                            cursor:pointer;
                            transition:.15s ease;
                        ">
                        Add Holdings
                    </button>

                    <button
                        type="button"
                        role="tab"
                        id="subtab-alloc"
                        onclick="switchSubTab('alloc')"
                        style="
                            flex:1;
                            padding:.5rem .75rem;
                            border-radius:8px;
                            border:none;
                            font-weight:600;
                            font-size:.8rem;
                            cursor:pointer;
                            transition:.15s ease;
                        ">
                        Quick Allocation
                    </button>

                </div>

                {{-- ── SUB-PANE: ADD HOLDINGS ───────────────────────── --}}
                <div id="subpane-holdings">

                    <form
                        id="holdingsForm"
                        method="POST"
                        action="{{ route('portfolio.manual.store') }}">

                        @csrf
                        <input type="hidden" name="mode" value="holdings">

                        {{-- Client / Portfolio Name --}}
                        <div style="margin-bottom:1rem;">
                            <label style="display:block;margin-bottom:.4rem;font-weight:600;font-size:.82rem;">
                                Client / Portfolio Name
                            </label>
                            <input
                                type="text"
                                name="client_name"
                                id="holdingsClientName"
                                required
                                maxlength="100"
                                placeholder="e.g. Amit Sharma"
                                oninput="updateHoldingsSubmitState()"
                                style="
                                    width:100%;
                                    padding:.65rem .8rem;
                                    border-radius:10px;
                                    border:1px solid var(--paper-3);
                                    background:var(--paper-1);
                                    font-size:.85rem;
                                    color:inherit;
                                ">
                        </div>

                        {{-- Portfolio selector --}}
                        <div style="margin-bottom:1rem;">
                            <label style="display:block;margin-bottom:.4rem;font-weight:600;font-size:.82rem;">
                                Portfolio (optional)
                            </label>
                            <select
                                name="portfolio_id"
                                style="
                                    width:100%;
                                    padding:.65rem .8rem;
                                    border-radius:10px;
                                    border:1px solid var(--paper-3);
                                    background:var(--paper-1);
                                    font-size:.85rem;
                                    color:inherit;
                                ">
                                <option value="">Default Portfolio</option>
                                @foreach($portfolios as $portfolio)
                                <option value="{{ $portfolio->id }}">{{ $portfolio->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Holding rows container — JS injects hidden inputs on submit --}}
                        <div id="holdingsRows">
                            {{-- rows injected by JS --}}
                        </div>

                        <button
                            type="button"
                            onclick="addHoldingRow()"
                            style="
                                width:100%;
                                padding:.65rem 1rem;
                                border-radius:10px;
                                border:1px dashed var(--paper-3);
                                background:transparent;
                                color:var(--ink-3);
                                font-size:.85rem;
                                font-weight:600;
                                cursor:pointer;
                                margin-bottom:1.25rem;
                                transition:.15s ease;
                            "
                            onmouseover="this.style.color='var(--ink)';this.style.borderColor='var(--ink-3)'"
                            onmouseout="this.style.color='var(--ink-3)';this.style.borderColor='var(--paper-3)'">
                            + Add another holding
                        </button>

                        {{-- Live chart --}}
                        <div style="margin-bottom:1.25rem;">
                            <canvas id="chartHoldings" height="220" style="width:100%;max-height:220px;"></canvas>
                            <div id="legendHoldings" style="display:flex;flex-wrap:wrap;gap:.4rem .75rem;margin-top:.75rem;font-size:.78rem;"></div>
                        </div>

                        <button
                            type="submit"
                            id="holdingsSubmitBtn"
                            disabled
                            style="
                                width:100%;
                                border:none;
                                border-radius:12px;
                                padding:1rem 1.25rem;
                                background:linear-gradient(135deg,#111827,#1f2937);
                                color:rgba(255,255,255,.4);
                                font-weight:700;
                                font-size:.95rem;
                                cursor:not-allowed;
                                transition:.2s ease;
                            ">
                            Analyse Portfolio →
                        </button>

                    </form>

                </div>
                {{-- /subpane-holdings --}}

                {{-- ── SUB-PANE: QUICK ALLOCATION ──────────────────── --}}
                <div id="subpane-alloc" style="display:none;">

                    <form
                        id="allocForm"
                        method="POST"
                        action="{{ route('portfolio.manual.store') }}">

                        @csrf
                        <input type="hidden" name="mode" value="alloc">

                        {{-- Client / Portfolio Name --}}
                        <div style="margin-bottom:1rem;">
                            <label style="display:block;margin-bottom:.4rem;font-weight:600;font-size:.82rem;">
                                Client / Portfolio Name
                            </label>
                            <input
                                type="text"
                                name="client_name"
                                id="allocClientName"
                                required
                                maxlength="100"
                                placeholder="e.g. Amit Sharma"
                                oninput="updateAllocValueChart()"
                                style="
                                    width:100%;
                                    padding:.65rem .8rem;
                                    border-radius:10px;
                                    border:1px solid var(--paper-3);
                                    background:var(--paper-1);
                                    font-size:.85rem;
                                    color:inherit;
                                ">
                        </div>

                        {{-- Portfolio selector --}}
                        <div style="margin-bottom:1rem;">
                            <label style="display:block;margin-bottom:.4rem;font-weight:600;font-size:.82rem;">
                                Portfolio (optional)
                            </label>
                            <select
                                name="portfolio_id"
                                style="
                                    width:100%;
                                    padding:.65rem .8rem;
                                    border-radius:10px;
                                    border:1px solid var(--paper-3);
                                    background:var(--paper-1);
                                    font-size:.85rem;
                                    color:inherit;
                                ">
                                <option value="">Default Portfolio</option>
                                @foreach($portfolios as $portfolio)
                                <option value="{{ $portfolio->id }}">{{ $portfolio->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div id="allocSliders" style="margin-bottom:1rem;">
                            {{-- value inputs injected by JS — each gets name="alloc[type_key]" --}}
                        </div>

                        {{-- Calculated total indicator --}}
                        <div style="
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                            padding:.6rem .9rem;
                            border-radius:10px;
                            margin-bottom:1.25rem;
                            font-size:.85rem;
                            font-weight:700;
                        " id="allocTotalBar">
                            <span>Total portfolio value</span>
                            <span id="allocSumDisplay">₹0</span>
                        </div>

                        {{-- Live chart --}}
                        <div style="margin-bottom:1.25rem;">
                            <canvas id="chartAlloc" height="220" style="width:100%;max-height:220px;"></canvas>
                            <div id="legendAlloc" style="display:flex;flex-wrap:wrap;gap:.4rem .75rem;margin-top:.75rem;font-size:.78rem;"></div>
                        </div>

                        <button
                            type="submit"
                            id="allocSubmitBtn"
                            disabled
                            style="
                                width:100%;
                                border:none;
                                border-radius:12px;
                                padding:1rem 1.25rem;
                                background:linear-gradient(135deg,#111827,#1f2937);
                                color:rgba(255,255,255,.4);
                                font-weight:700;
                                font-size:.95rem;
                                cursor:not-allowed;
                                transition:.2s ease;
                            ">
                            Analyse Portfolio →
                        </button>

                    </form>

                </div>
                {{-- /subpane-alloc --}}

            </div>
            {{-- /pane-manual --}}

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
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js" integrity="sha512-ZwR1/gSZM3ai6vCdI+LVF1zSq/5HznD3oD+sCoJrzXJ+yKgJ5RmDMVFOtEJhpBb3MQ1XZf5Y67F15Ux4NVHQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
// ── CONSTANTS ──────────────────────────────────────────────────────────────

const ASSET_TYPES = [
    { key: 'stock',         label: 'Stock',          color: '#6366f1' },
    { key: 'bond',          label: 'Bond / Debt',    color: '#f0c040' },
    { key: 'mutual_fund',   label: 'Mutual Fund',    color: '#34d399' },
    { key: 'etf',           label: 'ETF',            color: '#38bdf8' },
    { key: 'commodity',     label: 'Commodity',      color: '#fb923c' },
    { key: 'crypto',        label: 'Crypto',         color: '#a78bfa' },
    { key: 'cash',          label: 'Cash',           color: '#94a3b8' },
    { key: 'foreign_stock', label: 'Foreign Stock',  color: '#f472b6' },
];

const TYPE_COLOR = Object.fromEntries(ASSET_TYPES.map(t => [t.key, t.color]));
const TYPE_LABEL = Object.fromEntries(ASSET_TYPES.map(t => [t.key, t.label]));

// Resolve CSS custom property at runtime for chart backgrounds
function cssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

// ── TAB SWITCHING ──────────────────────────────────────────────────────────

function switchTab(tab) {
    const isUpload = tab === 'upload';

    document.getElementById('pane-upload').style.display = isUpload ? '' : 'none';
    document.getElementById('pane-manual').style.display = isUpload ? 'none' : '';

    styleTab('tab-upload', isUpload);
    styleTab('tab-manual', !isUpload);
}

function styleTab(id, active) {
    const el = document.getElementById(id);
    if (active) {
        el.style.background = cssVar('--paper') || '#162033';
        el.style.color      = cssVar('--ink')   || '#f1f5f9';
        el.style.boxShadow  = '0 1px 4px rgba(0,0,0,.35)';
    } else {
        el.style.background = 'transparent';
        el.style.color      = cssVar('--ink-3') || '#94a3b8';
        el.style.boxShadow  = 'none';
    }
}

function switchSubTab(sub) {
    const isHoldings = sub === 'holdings';

    document.getElementById('subpane-holdings').style.display = isHoldings ? '' : 'none';
    document.getElementById('subpane-alloc').style.display    = isHoldings ? 'none' : '';

    styleSubTab('subtab-holdings', isHoldings);
    styleSubTab('subtab-alloc',    !isHoldings);

    if (!isHoldings) updateAllocValueChart();
}

function styleSubTab(id, active) {
    const el = document.getElementById(id);
    if (active) {
        el.style.background = cssVar('--paper') || '#162033';
        el.style.color      = cssVar('--ink')   || '#f1f5f9';
        el.style.boxShadow  = '0 1px 3px rgba(0,0,0,.3)';
    } else {
        el.style.background = 'transparent';
        el.style.color      = cssVar('--ink-3') || '#94a3b8';
        el.style.boxShadow  = 'none';
    }
}

// ── HOLDINGS ROWS ──────────────────────────────────────────────────────────

let holdingRowCount = 0;
let chartHoldings   = null;

function buildTypeOptions(selected) {
    return ASSET_TYPES.map(t =>
        `<option value="${t.key}"${t.key === selected ? ' selected' : ''}>${t.label}</option>`
    ).join('');
}

function addHoldingRow(name = '', type = 'stock', value = '') {
    holdingRowCount++;
    const id  = holdingRowCount;
    const row = document.createElement('div');
    row.id    = `hrow-${id}`;
    row.style.cssText = `
        display:grid;
        grid-template-columns:1fr 130px 110px 28px;
        gap:.4rem;
        margin-bottom:.5rem;
        align-items:center;
    `;
    row.innerHTML = `
        <input
            type="text"
            placeholder="Holding name"
            value="${escHtml(name)}"
            oninput="updateHoldingsChart()"
            class="holding-field">
        <select onchange="updateHoldingsChart()" class="holding-field">
            ${buildTypeOptions(type)}
        </select>
        <input
            type="number"
            placeholder="₹ value"
            value="${escHtml(String(value))}"
            min="0"
            oninput="updateHoldingsChart()"
            class="holding-field">
        <button
            type="button"
            onclick="removeHoldingRow(${id})"
            title="Remove"
            style="
                width:28px;height:28px;
                border-radius:8px;
                border:none;
                background:rgba(239,68,68,.12);
                color:#fca5a5;
                cursor:pointer;
                font-size:.8rem;
                line-height:1;
                display:flex;align-items:center;justify-content:center;
                transition:.15s ease;
            "
            onmouseover="this.style.background='rgba(239,68,68,.25)'"
            onmouseout="this.style.background='rgba(239,68,68,.12)'"
        >✕</button>
    `;
    document.getElementById('holdingsRows').appendChild(row);
    updateHoldingsChart();
}

function removeHoldingRow(id) {
    const el = document.getElementById(`hrow-${id}`);
    if (el) el.remove();
    updateHoldingsChart();
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function updateHoldingsChart() {
    const container = document.getElementById('holdingsRows');
    const rows      = container.querySelectorAll('div[id^="hrow-"]');

    // Aggregate value by type
    const totals = {};
    rows.forEach(row => {
        const inputs  = row.querySelectorAll('input, select');
        const type    = inputs[1].value;
        const val     = parseFloat(inputs[2].value) || 0;
        totals[type]  = (totals[type] || 0) + val;
    });

    const labels = [], data = [], colors = [];
    ASSET_TYPES.forEach(t => {
        if (totals[t.key] > 0) {
            labels.push(t.label);
            data.push(totals[t.key]);
            colors.push(t.color);
        }
    });

    renderPieChart('chartHoldings', 'chartHoldingsInstance', labels, data, colors);
    renderLegend('legendHoldings', labels, colors);
    updateHoldingsSubmitState();
}

function updateHoldingsSubmitState() {
    const clientName = (document.getElementById('holdingsClientName')?.value || '').trim();
    const container  = document.getElementById('holdingsRows');
    const rows       = container.querySelectorAll('div[id^="hrow-"]');

    let hasValue = false;
    rows.forEach(row => {
        const inputs = row.querySelectorAll('input, select');
        if ((parseFloat(inputs[2]?.value) || 0) > 0) hasValue = true;
    });

    const btn    = document.getElementById('holdingsSubmitBtn');
    const enable = clientName.length > 0 && hasValue;
    btn.disabled     = !enable;
    btn.style.color  = enable ? 'white' : 'rgba(255,255,255,.4)';
    btn.style.cursor = enable ? 'pointer' : 'not-allowed';
}

// ── ALLOCATION VALUE INPUTS ────────────────────────────────────────────────

function initAllocValueInputs() {
    const container = document.getElementById('allocSliders');
    container.innerHTML = '';

    ASSET_TYPES.forEach(t => {
        const wrap = document.createElement('div');
        wrap.style.cssText = 'margin-bottom:.6rem;';
        wrap.innerHTML = `
            <label style="display:flex;align-items:center;gap:.5rem;margin-bottom:.25rem;font-size:.82rem;font-weight:600;">
                <span style="width:10px;height:10px;border-radius:50%;background:${t.color};display:inline-block;flex-shrink:0;"></span>
                ${t.label}
            </label>
            <input
                type="number"
                id="alloc-val-${t.key}"
                name="alloc[${t.key}]"
                min="0"
                placeholder="₹ 0"
                oninput="updateAllocValueChart()"
                style="
                    padding:.55rem .7rem;
                    border-radius:9px;
                    border:1px solid var(--paper-3);
                    background:var(--paper-1);
                    font-size:.82rem;
                    color:inherit;
                    width:100%;
                ">
        `;
        container.appendChild(wrap);
    });
    updateAllocValueChart();
}

function updateAllocValueChart() {
    let total = 0;
    const labels = [], data = [], colors = [];

    ASSET_TYPES.forEach(t => {
        const inp = document.getElementById(`alloc-val-${t.key}`);
        const val = parseFloat(inp ? inp.value : 0) || 0;
        total += val;
        if (val > 0) {
            labels.push(t.label);
            data.push(val);
            colors.push(t.color);
        }
    });

    // Update total display — always green, no validation state needed
    const bar     = document.getElementById('allocTotalBar');
    const display = document.getElementById('allocSumDisplay');
    bar.style.background = 'rgba(16,185,129,.1)';
    bar.style.color      = '#34d399';
    display.style.color  = '#34d399';
    display.textContent  = '₹' + total.toLocaleString('en-IN');

    // Enable submit once a client name and at least one value are entered
    const clientName = (document.getElementById('allocClientName')?.value || '').trim();
    const enable     = total > 0 && clientName.length > 0;
    const btn        = document.getElementById('allocSubmitBtn');
    btn.disabled     = !enable;
    btn.style.color  = enable ? 'white' : 'rgba(255,255,255,.4)';
    btn.style.cursor = enable ? 'pointer' : 'not-allowed';

    renderPieChart('chartAlloc', 'chartAllocInstance', labels, data, colors);
    renderLegend('legendAlloc', labels, colors);
}

// ── CHART RENDERER ─────────────────────────────────────────────────────────

// Store chart instances by canvas id so we can destroy/recreate
const _charts = {};

function renderPieChart(canvasId, instanceKey, labels, data, colors) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    if (_charts[instanceKey]) {
        _charts[instanceKey].destroy();
        delete _charts[instanceKey];
    }

    if (!data.length || data.every(v => v === 0)) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        return;
    }

    _charts[instanceKey] = new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: colors,
                borderColor: cssVar('--paper') || '#162033',
                borderWidth: 3,
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            cutout: '58%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct   = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                            return ` ${ctx.label}: ${pct}%`;
                        }
                    }
                }
            }
        }
    });
}

function renderLegend(containerId, labels, colors) {
    const el = document.getElementById(containerId);
    if (!el) return;
    if (!labels.length) { el.innerHTML = ''; return; }
    el.innerHTML = labels.map((l, i) => `
        <span style="display:flex;align-items:center;gap:.3rem;color:var(--ink-3);">
            <span style="width:10px;height:10px;border-radius:50%;background:${colors[i]};flex-shrink:0;"></span>
            ${l}
        </span>
    `).join('');
}

// ── FILE UPLOAD LOGIC (unchanged) ──────────────────────────────────────────

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

    // ── HOLDINGS FORM: inject hidden inputs from JS-rendered rows ────
    document.getElementById('holdingsForm').addEventListener('submit', function (e) {
        const container = document.getElementById('holdingsRows');
        const rows      = container.querySelectorAll('div[id^="hrow-"]');

        // Remove any previously injected hidden inputs (re-submit safety)
        this.querySelectorAll('input[data-injected]').forEach(el => el.remove());

        let idx = 0;
        rows.forEach(row => {
            const inputs = row.querySelectorAll('input, select');
            const name   = (inputs[0]?.value || '').trim();
            const type   = inputs[1]?.value || 'stock';
            const val    = parseFloat(inputs[2]?.value) || 0;
            if (!name || val <= 0) return;

            const append = (field, value) => {
                const el = document.createElement('input');
                el.type  = 'hidden';
                el.name  = `holdings[${idx}][${field}]`;
                el.value = value;
                el.dataset.injected = '1';
                this.appendChild(el);
            };
            append('name',  name);
            append('type',  type);
            append('value', val);
            idx++;
        });

        if (idx === 0) {
            e.preventDefault();
            return;
        }

        const btn      = document.getElementById('holdingsSubmitBtn');
        btn.disabled   = true;
        btn.textContent = 'Generating report…';
        btn.style.opacity = '.65';
    });

    // ── ALLOC FORM: loading state ─────────────────────────────────────
    document.getElementById('allocForm').addEventListener('submit', function () {
        const btn       = document.getElementById('allocSubmitBtn');
        btn.disabled    = true;
        btn.textContent = 'Generating report…';
        btn.style.opacity = '.65';
    });

    // ── INITIALISE TABS ──────────────────────────────────────────────
    switchTab('upload');
    switchSubTab('holdings');
    addHoldingRow();        // start with one blank row
    initAllocValueInputs();
});
</script>
@endpush

@endsection
