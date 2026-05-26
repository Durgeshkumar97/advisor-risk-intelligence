@extends('layouts.app')

@section('title', 'My Portfolios — RiskSignal')

@section('content')

<div style="max-width:800px;margin:0 auto;padding:2rem 0;">

    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;margin-bottom:2rem;">
        <div>
            <div class="eyebrow" style="margin-bottom:.4rem;">Your Portfolios</div>
            <h1 style="font-size:1.75rem;font-weight:800;">Manage Portfolios</h1>
        </div>
        <a href="{{ route('portfolio.upload') }}" class="btn-primary" style="padding:.75rem 1.5rem;font-size:.9rem;">
            + Upload File
        </a>
    </div>

    @if(session('success'))
    <div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#86efac;padding:.75rem 1rem;border-radius:10px;font-size:.875rem;font-weight:600;margin-bottom:1.5rem;">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- CREATE NEW PORTFOLIO --}}
    <div class="card" style="padding:1.5rem;margin-bottom:1.5rem;">
        <div style="font-weight:700;font-size:.95rem;margin-bottom:1rem;">Create New Portfolio</div>
        <form method="POST" action="{{ route('portfolio.store') }}" style="display:flex;gap:.75rem;flex-wrap:wrap;">
            @csrf
            <input type="text" name="name" placeholder="Portfolio name (e.g. Aggressive Growth)" required
                   style="flex:1;min-width:200px;padding:.75rem 1rem;border-radius:10px;border:1px solid var(--paper-3);background:var(--paper);color:var(--ink);font-size:.9rem;font-family:inherit;outline:none;">
            <button type="submit" style="padding:.75rem 1.5rem;border-radius:10px;background:#2563eb;color:#fff;font-weight:700;font-size:.9rem;border:none;cursor:pointer;">
                Create
            </button>
        </form>
        @error('name')<div style="color:#fca5a5;font-size:.78rem;margin-top:.4rem;">{{ $message }}</div>@enderror
    </div>

    {{-- PORTFOLIO LIST --}}
    @forelse($portfolios as $portfolio)
    <div class="card" style="padding:1.25rem 1.5rem;margin-bottom:1rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem;">

            <div>
                <div style="font-size:1rem;font-weight:700;" id="name-{{ $portfolio->id }}">{{ $portfolio->name }}</div>
                <div style="color:var(--ink-3);font-size:.8rem;margin-top:.2rem;">
                    {{ $portfolio->files_count }} file{{ $portfolio->files_count != 1 ? 's' : '' }}
                    &nbsp;·&nbsp;
                    {{ $portfolio->assets_count }} asset{{ $portfolio->assets_count != 1 ? 's' : '' }}
                    &nbsp;·&nbsp;
                    Created {{ $portfolio->created_at->format('d M Y') }}
                </div>
            </div>

            <div style="display:flex;gap:.5rem;">

                {{-- RENAME --}}
                <button type="button" style="padding:.5rem .9rem;border-radius:8px;border:1px solid var(--paper-3);background:transparent;color:var(--ink-3);font-size:.8rem;cursor:pointer;"
                        onclick="toggleRename({{ $portfolio->id }})">
                    Rename
                </button>

                {{-- DELETE --}}
                <form method="POST" action="{{ route('portfolio.destroy', $portfolio->id) }}"
                      onsubmit="return confirm('Delete portfolio &quot;{{ addslashes($portfolio->name) }}&quot; and all its files?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="padding:.5rem .9rem;border-radius:8px;border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.08);color:#fca5a5;font-size:.8rem;cursor:pointer;">
                        Delete
                    </button>
                </form>

            </div>

        </div>

        {{-- RENAME FORM (hidden by default) --}}
        <div id="rename-{{ $portfolio->id }}" style="display:none;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--paper-3);">
            <form method="POST" action="{{ route('portfolio.update', $portfolio->id) }}" style="display:flex;gap:.75rem;flex-wrap:wrap;">
                @csrf
                @method('PATCH')
                <input type="text" name="name" value="{{ $portfolio->name }}" required
                       style="flex:1;min-width:180px;padding:.65rem .9rem;border-radius:8px;border:1px solid var(--paper-3);background:var(--paper);color:var(--ink);font-size:.875rem;font-family:inherit;outline:none;">
                <button type="submit" style="padding:.65rem 1.2rem;border-radius:8px;background:#2563eb;color:#fff;font-weight:700;font-size:.875rem;border:none;cursor:pointer;">Save</button>
                <button type="button" style="padding:.65rem 1.2rem;border-radius:8px;border:1px solid var(--paper-3);background:transparent;color:var(--ink-3);font-size:.875rem;cursor:pointer;"
                        onclick="toggleRename({{ $portfolio->id }})">Cancel</button>
            </form>
        </div>

    </div>
    @empty
    <div class="card" style="padding:2.5rem;text-align:center;">
        <div style="font-size:2rem;margin-bottom:1rem;">📂</div>
        <div style="font-weight:700;margin-bottom:.5rem;">No portfolios yet</div>
        <p style="color:var(--ink-3);font-size:.875rem;">Create your first portfolio above, then upload client files to it.</p>
    </div>
    @endforelse

    <div style="margin-top:1.5rem;">
        <a href="{{ route('dashboard') }}" style="color:var(--ink-3);font-size:.875rem;text-decoration:underline;">← Back to Dashboard</a>
    </div>

</div>

@push('scripts')
<script>
function toggleRename(id) {
    const el = document.getElementById('rename-' + id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
@endpush

@endsection
