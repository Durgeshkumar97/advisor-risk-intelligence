@extends('layouts.app')

@section('title', 'Welcome — RiskSignal')

@section('content')

<section style="padding:5rem 1rem;">

    <div style="max-width:560px;margin:auto;">

        <div style="text-align:center;margin-bottom:2.5rem;">
            <div class="eyebrow" style="margin-bottom:.5rem;">Getting Started</div>
            <h1 style="font-size:1.75rem;font-weight:800;line-height:1.2;margin-bottom:.75rem;">
                How should we deliver your daily risk signals?
            </h1>
            <p style="color:var(--ink-3);font-size:.95rem;line-height:1.6;">
                Choose your preferred delivery method. You can change this later in your profile.
            </p>
        </div>

        @if($errors->any())
        <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#fca5a5;padding:.8rem 1rem;border-radius:10px;font-size:.875rem;margin-bottom:1.5rem;">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('onboarding.store') }}">
            @csrf

            <div style="display:flex;flex-direction:column;gap:1rem;margin-bottom:2rem;">

                {{-- EMAIL OPTION --}}
                <label style="display:flex;align-items:center;gap:1rem;padding:1.25rem;border-radius:14px;border:2px solid var(--paper-3);cursor:pointer;transition:.15s ease;" id="opt-email">
                    <input type="radio" name="access_type" value="email" {{ old('access_type','email')==='email'?'checked':'' }} style="width:18px;height:18px;accent-color:#2563eb;" onchange="document.querySelectorAll('.delivery-opt').forEach(e=>e.style.borderColor='var(--paper-3)');document.getElementById('opt-email').style.borderColor='#2563eb';document.getElementById('phone-wrap').style.display='none'">
                    <div>
                        <div style="font-weight:700;font-size:.95rem;">📧 Email delivery</div>
                        <div style="color:var(--ink-3);font-size:.8rem;margin-top:.2rem;">Risk report sent to {{ Auth::user()->email }}</div>
                    </div>
                </label>

                {{-- WHATSAPP OPTION --}}
                <label style="display:flex;flex-direction:column;gap:.75rem;padding:1.25rem;border-radius:14px;border:2px solid var(--paper-3);cursor:pointer;transition:.15s ease;" id="opt-wa" class="delivery-opt">
                    <div style="display:flex;align-items:center;gap:1rem;">
                        <input type="radio" name="access_type" value="whatsapp" {{ old('access_type')==='whatsapp'?'checked':'' }} style="width:18px;height:18px;accent-color:#22c55e;" onchange="document.querySelectorAll('.delivery-opt').forEach(e=>e.style.borderColor='var(--paper-3)');document.getElementById('opt-wa').style.borderColor='#22c55e';document.getElementById('phone-wrap').style.display='block'">
                        <div>
                            <div style="font-weight:700;font-size:.95rem;">💬 WhatsApp delivery</div>
                            <div style="color:var(--ink-3);font-size:.8rem;margin-top:.2rem;">Daily signal + client conversation script on WhatsApp before market open</div>
                        </div>
                    </div>

                    <div id="phone-wrap" style="display:{{ old('access_type')==='whatsapp' ? 'block' : 'none' }}">
                        <input type="tel" name="phone" value="{{ old('phone', Auth::user()->phone) }}"
                               placeholder="WhatsApp number (e.g. 9876543210)"
                               style="width:100%;padding:.75rem 1rem;border-radius:10px;border:1px solid var(--paper-3);background:var(--paper);color:var(--ink);font-size:.9rem;font-family:inherit;outline:none;">
                        @error('phone')<div style="color:#fca5a5;font-size:.78rem;margin-top:.3rem;">{{ $message }}</div>@enderror
                    </div>
                </label>

            </div>

            <button type="submit" style="width:100%;padding:1rem;border-radius:14px;background:#2563eb;color:#fff;font-weight:700;font-size:1rem;border:none;cursor:pointer;transition:.2s ease;" onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                Continue to Dashboard →
            </button>

        </form>

    </div>

</section>

@endsection
