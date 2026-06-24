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
