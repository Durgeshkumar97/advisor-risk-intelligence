@extends('layouts.app')

@section('title', 'Manage Portfolios — RiskSignal')

@section('content')

<div style="max-width:900px;margin:0 auto;padding:2rem 0;">

    {{-- HEADER --}}
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <div>
            <div class="eyebrow" style="margin-bottom:.4rem;">Portfolio Management</div>
            <h1 style="font-size:1.85rem;font-weight:800;margin-bottom:.4rem;">My Portfolios</h1>
            <p style="color:var(--ink-3);font-size:.9rem;">Create, rename, or delete portfolios. Each portfolio holds one set of uploaded holdings.</p>
        </div>
        <a href="{{ route('dashboard') }}" style="padding:.75rem 1.2rem;border-radius:12px;border:1px solid var(--paper-3);text-decoration:none;color:inherit;font-weight:600;font-size:.875rem;white-space:nowrap;">
            ← Dashboard
        </a>
    </div>

    {{-- ALERTS --}}
    @if(session('success'))
        <div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:#86efac;padding:.85rem 1.1rem;border-radius:12px;margin-bottom:1.5rem;font-size:.9rem;font-weight:600;">
            ✓ {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:.85rem 1.1rem;border-radius:12px;margin-bottom:1.5rem;font-size:.9rem;font-weight:600;">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:.85rem 1.1rem;border-radius:12px;margin-bottom:1.5rem;font-size:.9rem;">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- CREATE NEW PORTFOLIO --}}
    <div style="background:var(--paper-2);border:1px solid var(--paper-3);border-radius:16px;padding:1.5rem;margin-bottom:2rem;">
        <h2 style="font-size:1rem;font-weight:800;margin-bottom:1rem;">+ Create New Portfolio</h2>
        <form method="POST" action="{{ route('portfolio.store') }}" style="display:flex;gap:10px;flex-wrap:wrap;">
            @csrf
            <input
                type="text"
                name="name"
                placeholder="Portfolio name (e.g. Client A — Equity)"
                value="{{ old('name') }}"
                required
                style="flex:1;min-width:220px;padding:.75rem 1rem;border-radius:10px;border:1px solid var(--paper-3);background:var(--paper);color:inherit;font-size:.9rem;font-family:inherit;"
            >
            <button type="submit" style="padding:.75rem 1.4rem;border-radius:10px;background:var(--accent);color:#fff;border:none;font-weight:700;font-size:.9rem;cursor:pointer;white-space:nowrap;">
                Create Portfolio
            </button>
        </form>
    </div>

    {{-- PORTFOLIO LIST --}}
    @forelse($portfolios as $portfolio)
        <div style="background:var(--paper-2);border:1px solid var(--paper-3);border-radius:16px;padding:1.25rem 1.5rem;margin-bottom:1rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">

            {{-- RISK BADGE --}}
            @php
                $badgeColor = match($portfolio->risk_level) {
                    'HIGH'   => 'rgba(239,68,68,.15)',
                    'MEDIUM' => 'rgba(234,179,8,.15)',
                    default  => 'rgba(34,197,94,.15)',
                };
                $textColor = match($portfolio->risk_level) {
                    'HIGH'   => '#fca5a5',
                    'MEDIUM' => '#fde68a',
                    default  => '#86efac',
                };
            @endphp
            <div style="width:44px;height:44px;border-radius:10px;background:{{ $badgeColor }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <span style="font-size:1.25rem;">{{ $portfolio->risk_level === 'HIGH' ? '🔴' : ($portfolio->risk_level === 'MEDIUM' ? '🟡' : '🟢') }}</span>
            </div>

            {{-- NAME + META --}}
            <div style="flex:1;min-width:180px;">
                <div style="font-weight:800;font-size:1rem;margin-bottom:.2rem;">{{ $portfolio->name }}</div>
                <div style="font-size:.8rem;color:var(--ink-3);">
                    {{ $portfolio->assets_count }} assets
                    &nbsp;·&nbsp;
                    {{ $portfolio->files_count }} {{ Str::plural('file', $portfolio->files_count) }} uploaded
                    &nbsp;·&nbsp;
                    Risk: <span style="color:{{ $textColor }};font-weight:700;">{{ $portfolio->risk_level }}</span>
                    @if($portfolio->risk_score > 0)
                        ({{ number_format($portfolio->risk_score, 0) }})
                    @endif
                </div>
            </div>

            {{-- ACTIONS --}}
            <div style="display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap;">

                {{-- UPLOAD FILE --}}
                <a href="{{ route('portfolio.upload') }}"
                   style="padding:.5rem 1rem;border-radius:8px;border:1px solid var(--paper-3);font-size:.8rem;font-weight:600;text-decoration:none;color:inherit;white-space:nowrap;">
                    Upload File
                </a>

                {{-- RENAME --}}
                <button
                    onclick="openRename({{ $portfolio->id }}, '{{ addslashes($portfolio->name) }}')"
                    style="padding:.5rem 1rem;border-radius:8px;border:1px solid var(--paper-3);font-size:.8rem;font-weight:600;background:transparent;color:inherit;cursor:pointer;white-space:nowrap;">
                    Rename
                </button>

                {{-- DELETE --}}
                @if($portfolios->count() > 1)
                <form method="POST" action="{{ route('portfolio.destroy', $portfolio->id) }}"
                      onsubmit="return confirm('Delete \'{{ addslashes($portfolio->name) }}\'? All assets and file records will be removed.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="padding:.5rem 1rem;border-radius:8px;border:1px solid rgba(239,68,68,.3);font-size:.8rem;font-weight:600;background:transparent;color:#fca5a5;cursor:pointer;white-space:nowrap;">
                        Delete
                    </button>
                </form>
                @endif

            </div>
        </div>
    @empty
        <div style="text-align:center;padding:3rem 1rem;color:var(--ink-3);">
            <div style="font-size:2rem;margin-bottom:.75rem;">📂</div>
            <p style="font-size:.95rem;">No portfolios yet. Create your first one above.</p>
        </div>
    @endforelse

</div>

{{-- RENAME MODAL --}}
<div id="rename-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;display:none;align-items:center;justify-content:center;">
    <div style="background:var(--paper-2);border:1px solid var(--paper-3);border-radius:20px;padding:2rem;width:100%;max-width:420px;margin:1rem;">
        <h3 style="font-size:1.1rem;font-weight:800;margin-bottom:1.25rem;">Rename Portfolio</h3>
        <form id="rename-form" method="POST">
            @csrf
            @method('PATCH')
            <input
                type="text"
                id="rename-input"
                name="name"
                required
                style="width:100%;padding:.85rem 1rem;border-radius:10px;border:1px solid var(--paper-3);background:var(--paper);color:inherit;font-size:.95rem;font-family:inherit;margin-bottom:1rem;"
            >
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="closeRename()"
                    style="padding:.65rem 1.2rem;border-radius:8px;border:1px solid var(--paper-3);background:transparent;color:inherit;font-weight:600;font-size:.875rem;cursor:pointer;">
                    Cancel
                </button>
                <button type="submit"
                    style="padding:.65rem 1.4rem;border-radius:8px;background:var(--accent);color:#fff;border:none;font-weight:700;font-size:.875rem;cursor:pointer;">
                    Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRename(id, currentName) {
    const modal = document.getElementById('rename-modal');
    const form  = document.getElementById('rename-form');
    const input = document.getElementById('rename-input');

    form.action = '/portfolios/' + id;
    input.value = currentName;
    modal.style.display = 'flex';
    setTimeout(() => input.focus(), 50);
}

function closeRename() {
    document.getElementById('rename-modal').style.display = 'none';
}

// Close modal on backdrop click
document.getElementById('rename-modal').addEventListener('click', function(e) {
    if (e.target === this) closeRename();
});
</script>

@endsection
