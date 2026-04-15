@extends('layouts.app')

@section('content')

{{-- ── HERO ─────────────────────────────────────────── --}}
<section id="home" style="padding-top:96px;">

    <div class="container hero-grid">

        <!-- LEFT SIDE -->
        <div class="reveal reveal-left">

            <div class="eyebrow" style="margin-bottom:1.2rem;">
                risk intelligence · Independent financial advisors
            </div>

            <h1>
                When markets fall,<br>
                your clients call.<br>
                <em style="color:var(--gold);">
                    We help you control client panic — before it costs you AUM.
                </em>
            </h1>

            <p style="margin-top:1.4rem;max-width:460px;font-size:1rem;">
                risk signal for IFAs managing ₹5L–₹25L portfolios.
                One page. Three risks. One client script.
                Delivered on WhatsApp at 4:30 PM.
            </p>

            <div style="margin-top:2rem;display:flex;gap:1rem;flex-wrap:wrap;">
                <a href="#contact" class="btn-primary">
                    Start free trial →
                </a>
                <a href="#sample-report" class="btn-outline">
                    See a sample report
                </a>
            </div>
        </div> 

        <!-- RIGHT SIDE CARD -->
        <div class="card card-hover reveal reveal-right reveal-delay-2"
             style="
                overflow:hidden;
                transform:rotate(1.2deg);
                width:100%;
                max-width:100%;
                word-break:break-word;
             ">

            <div style="
                background:var(--ink);
                color:var(--paper);
                padding:1rem 1.25rem;
                display:flex;
                justify-content:space-between;
                align-items:center;
            ">
                <span style="font-family:var(--serif);font-size:1rem;">
                    Risk Intelligence
                </span>

                <span class="pill pill-gold" style="font-size:.6rem;">
                    DAY 12 · 2026
                </span>
            </div>

            <div style="padding:1.25rem;">

                <div style="
                    display:flex;
                    align-items:baseline;
                    gap:.75rem;
                    padding-bottom:1rem;
                    margin-bottom:1rem;
                    border-bottom:1px solid var(--paper-3);
                ">
                    <span style="
                        font-family:var(--serif);
                        font-size:2.2rem;
                        color:var(--amber);
                        line-height:1;
                    ">
                        6.4
                    </span>

                    <div>
                        <div style="
                            font-size:.7rem;
                            letter-spacing:.07em;
                            color:var(--amber);
                            text-transform:uppercase;
                        ">
                            Moderate-High
                        </div>

                        <div style="font-size:.7rem;color:var(--ink-3);">
                            ↑ from 5.1 yesterday
                        </div>
                    </div>
                </div>

                <div style="
                    display:flex;
                    flex-direction:column;
                    gap:.6rem;
                    margin-bottom:1rem;
                ">

                    <div style="display:flex;justify-content:space-between;gap:.6rem;font-size:.82rem;">
                        <span style="color:var(--ink-2);flex:1;">
                            FII outflows — 3rd week
                        </span>
                        <span class="pill pill-high">HIGH</span>
                    </div>

                    <div style="display:flex;justify-content:space-between;gap:.6rem;font-size:.82rem;">
                        <span style="color:var(--ink-2);flex:1;">
                            Rupee above 83.5 — import exposure
                        </span>
                        <span class="pill pill-high">HIGH</span>
                    </div>

                    <div style="display:flex;justify-content:space-between;gap:.6rem;font-size:.82rem;">
                        <span style="color:var(--ink-2);flex:1;">
                            Mid-cap valuations elevated
                        </span>
                        <span class="pill pill-med">MED</span>
                    </div>
                </div> 

                <div style="
                    background:var(--paper);
                    border-left:3px solid var(--gold);
                    padding:.8rem 1rem;
                ">
                    <div class="eyebrow" style="margin-bottom:.35rem;">
                        What to tell your client
                    </div>

                    <div style="
                        font-size:.78rem;
                        color:var(--ink-2);
                        line-height:1.5;
                        font-style:italic;
                    ">
                        "Foreign investors have been selling — this is normal during uncertainty.
                        Your long-term plan is intact. No action needed."
                    </div>
                </div>

            </div>
        </div> 
    </div> 
</section> 

<section>
  <div class="container text-center">

    <h2>Clients leave during market falls</h2>

    <p style="margin-top:.6rem;">
      Not because of portfolios. Because of communication.
    </p>

    <div style="margin-top:1.5rem;font-size:.9rem;">
      Panic → wrong advice → redemption → AUM loss
    </div>

    <div style="margin-top:.6rem;font-size:.9rem;">
      Calm response → trust → retained AUM
    </div>

  </div>
</section>

{{-- ── FOUNDER TRUST ────────────────────────────────── --}}
<section>
    <div class="container reveal reveal-pop"
         style="max-width:680px;margin-inline:auto;text-align:center;">

        <div class="eyebrow" style="margin-bottom:1rem;">
            Built by an IFA, for IFAs
        </div>

        <h2>Why I built this</h2>

        <p style="margin-top:1rem;font-size:1rem;line-height:1.6;">
            As an advisor, I’ve seen clients lost during market falls... not because portfolios were wrong,
            but because they didn’t know what to say when panic hit.
        </p>

        <p style="margin-top:.6rem;font-size:1rem;line-height:1.6;">
            Most research is written for analysts. Not for the advisor who has 20 minutes before a client call.
        </p>

        <p style="margin-top:.6rem;font-size:1rem;font-weight:500;">
            RiskSignal gives you exactly what to say — when it matters most.
        </p>

        <div class="reveal reveal-pop reveal-delay-2"
             style="margin-top:1.5rem;display:inline-flex;align-items:center;gap:.75rem;background:var(--paper-2);border:1px solid var(--paper-3);border-radius:var(--radius-lg);padding:.75rem 1.25rem;">

            <div style="width:42px;height:42px;border-radius:50%;background:var(--gold-lt);display:flex;align-items:center;justify-content:center;font-weight:500;font-size:.875rem;color:var(--gold);flex-shrink:0;">
                DK
            </div>

            <div style="text-align:left;">
                <div style="font-size:.875rem;font-weight:500;color:var(--ink);">
                    Durgesh Kumar
                </div>
                <div style="font-size:.75rem;color:var(--ink-3);">
                    Founder · Independent Financial Advisor
                </div>
            </div>

        </div>
    </div>
</section> 
 
{{-- ── HOW IT WORKS ─────────────────────────────────── --}}
<div id="how-it-works"
     style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.5rem;max-width:900px;margin-inline:auto;">

    {{-- STEP 1 --}}
    <div class="reveal reveal-pop reveal-delay-1" style="text-align:center;padding:1.25rem;">
        <div style="font-family:var(--serif);font-size:2.4rem;color:var(--paper-3);margin-bottom:.5rem;">1</div>

        <h3 style="margin-bottom:.4rem;">Subscribe</h3>

        <p style="font-size:.82rem;color:var(--ink-3);line-height:1.5;">
            Enter WhatsApp. Upload portfolio. Done in 60 seconds.
        </p>
    </div>

    {{-- STEP 2 --}}
    <div class="reveal reveal-pop reveal-delay-2" style="text-align:center;padding:1.25rem;">
        <div style="font-family:var(--serif);font-size:2.4rem;color:var(--paper-3);margin-bottom:.5rem;">2</div>

        <h3 style="margin-bottom:.4rem;">risk engine</h3>

        <p style="font-size:.82rem;color:var(--ink-3);margin-bottom:.6rem;">
            Processes market signals after close
        </p>
    </div>

    {{-- STEP 3 --}}
    <div class="reveal reveal-pop reveal-delay-3" style="text-align:center;padding:1.25rem;">
        <div style="font-family:var(--serif);font-size:2.4rem;color:var(--paper-3);margin-bottom:.5rem;">3</div>

        <h3 style="margin-bottom:.4rem;">4:30 PM delivery</h3>

        <p style="font-size:.82rem;color:var(--ink-3);line-height:1.5;">
            WhatsApp report + client script. Ready to use instantly.
        </p>
    </div>
</div> 

{{-- ── SAMPLE REPORT ────────────────────────────────── --}}
<section id="sample-report" style="background:var(--paper-2);">
    <div class="container reveal reveal-pop">

        <div class="text-center reveal reveal-pop" style="margin-bottom:2rem;">
            <div class="eyebrow" style="margin-bottom:.8rem;">See the real thing</div>
            <h2>Sample report — Week 12, 2026</h2>
            <p style="margin-top:.6rem;font-size:.875rem;">
                Mid-cap equity portfolios · Plain English edition
            </p>
        </div>

        <div class="card reveal reveal-pop reveal-delay-1"
             style="max-width:700px;margin-inline:auto;overflow:hidden;">

            <!-- HEADER -->
            <div class="reveal reveal-pop reveal-delay-2"
                 style="background:var(--ink);color:var(--paper);padding:1.5rem 2rem;">

                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;">
                    <span style="font-family:var(--serif);font-size:1.1rem;">
                        Risk<span style="color:var(--gold);">Lens</span>
                    </span>

                    <div style="text-align:right;font-family:var(--mono);font-size:.65rem;color:rgba(255,255,255,.4);line-height:1.7;">
                        Day 12 · 2026<br>
                        Mid-cap equity<br>
                        Prepared for: IFA Subscriber
                    </div>
                </div>

                <div style="font-family:var(--serif);font-size:1.6rem;line-height:1.2;">
                    Risk Intelligence Report
                </div>

                <div style="font-size:.825rem;color:rgba(255,255,255,.5);margin-top:.3rem;">
                    Mid-cap equity · Plain English edition
                </div>
            </div>

            <!-- BODY -->
            <div class="reveal reveal-pop reveal-delay-3"
                 style="padding:1.75rem 2rem;">

                <!-- SCORE -->
                <div class="reveal reveal-pop reveal-delay-4"
                     style="background:var(--amber-lt);border:1px solid rgba(176,94,0,.2);border-radius:var(--radius-md);padding:1.1rem 1.4rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1.5rem;">

                    <div style="font-family:var(--serif);font-size:3rem;color:var(--amber);line-height:1;flex-shrink:0;">
                        6.4
                    </div>

                    <div>
                        <div style="font-family:var(--mono);font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;color:var(--amber);margin-bottom:.3rem;">
                            Risk level this week: Moderate-High
                        </div>

                        <div style="font-size:.82rem;color:var(--ink-2);">
                            Up from 5.1 last week. FII selling and currency risk are primary drivers. No immediate portfolio action recommended.
                        </div>
                    </div>
                </div>

                <!-- FLAGS -->
                <div class="reveal reveal-pop reveal-delay-4" style="margin-bottom:1.5rem;">

                    <div class="eyebrow"
                         style="margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:1px solid var(--paper-3);">
                        Risk flags this week
                    </div>

                    <div style="display:grid;grid-template-columns:1fr auto;gap:.8rem;padding:.8rem 0;border-bottom:1px solid var(--paper-2);">
                        <div>
                            <div style="font-size:.875rem;font-weight:500;">FII outflows — 3rd consecutive week</div>
                            <div style="font-size:.8rem;color:var(--ink-3);">
                                Foreign investors net sellers for 3 weeks.
                            </div>
                        </div>
                        <span class="pill pill-high">HIGH</span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr auto;gap:.8rem;padding:.8rem 0;border-bottom:1px solid var(--paper-2);">
                        <div>
                            <div style="font-size:.875rem;font-weight:500;">Rupee above 83.5</div>
                            <div style="font-size:.8rem;color:var(--ink-3);">
                                Import-heavy stocks at risk.
                            </div>
                        </div>
                        <span class="pill pill-high">HIGH</span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr auto;gap:.8rem;padding:.8rem 0;border-bottom:1px solid var(--paper-2);">
                        <div>
                            <div style="font-size:.875rem;font-weight:500;">Mid-cap P/E elevated</div>
                            <div style="font-size:.8rem;color:var(--ink-3);">
                                Valuations are stretched.
                            </div>
                        </div>
                        <span class="pill pill-med">MED</span>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr auto;gap:.8rem;padding:.8rem 0;">
                        <div>
                            <div style="font-size:.875rem;font-weight:500;">RBI rate decision</div>
                            <div style="font-size:.8rem;color:var(--ink-3);">
                                No change expected.
                            </div>
                        </div>
                        <span class="pill pill-low">LOW</span>
                    </div>

                </div>

                <!-- SCRIPT -->
                <div class="reveal reveal-pop reveal-delay-4">
                    <div class="eyebrow"
                         style="margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:1px solid var(--paper-3);">
                        What to tell your client this week
                    </div>

                    <div style="background:var(--paper);border-left:3px solid var(--gold);padding:1.1rem 1.3rem;">
                        <div class="eyebrow" style="margin-bottom:.5rem;">Client conversation script</div>

                        <div style="font-size:.875rem;color:var(--ink-2);line-height:1.75;font-style:italic;">
                            "Markets have seen some selling from foreign investors for three weeks..."
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="reveal reveal-pop reveal-delay-4"
                     style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--paper-3);display:flex;justify-content:space-between;align-items:center;">

                    <span style="font-family:var(--serif);font-size:.9rem;color:var(--ink-3);">
                        Risk<span style="color:var(--gold);">Signal</span>
                    </span>

                    <span style="font-family:var(--mono);font-size:.65rem;color:var(--ink-3);">
                        Data: NSE, AMFI, RBI · Week 12, 2026
                    </span>
                </div>

            </div>
        </div>

        <div class="text-center mt-xl reveal reveal-pop reveal-delay-2">
            <a href="#contact" class="btn-primary">Start getting this daily →</a>

            <p style="margin-top:.75rem;font-size:.75rem;color:var(--ink-3);">
                Start free trial →
            </p>
        </div>

    </div>
</section>

<section>
    <div class="container reveal reveal-pop" style="max-width:700px;margin:auto;text-align:center;">

        <div class="eyebrow">How advisors actually use this</div>

        <h2 style="margin-top:.5rem;">Real scenario</h2>

        <div class="card" style="padding:1.5rem;margin-top:1.5rem;text-align:left;">

            <p style="font-size:.9rem;font-weight:500;">
                <strong>Client:</strong> “Market gir raha hai — exit karein?”
            </p>

            <div style="
                margin-top:1rem;
                padding:1rem;
                border-left:3px solid var(--gold);
                background:rgba(255,255,255,0.02);
            ">

                <p style="
                    font-size:.95rem;
                    font-weight:500;
                    color:var(--ink);
                    line-height:1.6;
                ">
                    <strong style="color:var(--gold);">Your response:</strong><br><br>
                    “Foreign investors have been selling for 3 weeks — that's normal during uncertainty.
                    Your long-term plan is intact. No action needed.”
                </p>

            </div>

        </div>

    </div>
</section>

{{-- POSITIONING LINE --}}
<section>
    <div class="container" style="text-align:center;margin:2rem auto 0;font-size:.9rem;color:var(--ink-2);max-width:600px;">
        Built for client conversations — not analyst reports.
    </div>
</section>

{{-- ── PRICING ──────────────────────────────────────── --}}
<section id="pricing" style="padding:5rem 0;">
    <div class="container reveal reveal-pop">

        <div class="text-center" style="margin-bottom:3rem;">
            <div class="eyebrow">Simple, honest pricing</div>
            <h2 style="margin-top:.4rem;">Flat monthly subscription</h2>
            <p style="margin-top:.6rem;font-size:.9rem;color:var(--ink-3);">
                Daily delivery · No per-client limits
            </p>

            <p style="margin-top:.4rem;font-size:.8rem;color:var(--ink-2);">
                No login. No dashboards. Delivered on WhatsApp.
            </p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:2rem;max-width:1000px;margin:auto;align-items:stretch;">

            {{-- STARTER --}}
            <div class="card card-hover" style="padding:2rem;display:flex;flex-direction:column;justify-content:space-between;">
                
                <div>
                    <div class="eyebrow">Starter</div>

                    <h2 style="font-size:2.2rem;margin:.5rem 0;">₹999</h2>
                    <p style="color:var(--ink-3);margin-bottom:1rem;">per month</p>

                    <p style="margin-bottom:1rem;font-size:.9rem;">
                        1 portfolio type
                    </p>

                    <hr style="margin:1rem 0;opacity:.2;">

                    <ul style="
                        list-style:none;
                        padding:0;
                        margin:0;
                        font-size:.9rem;
                        line-height:1.9;
                    ">
                        <li style="position:relative;padding-left:1.2rem;">
                            <span style="position:absolute;left:0;color:var(--gold);">•</span>
                            Daily risk report
                        </li>
                        <li style="position:relative;padding-left:1.2rem;">
                            <span style="position:absolute;left:0;color:var(--gold);">•</span>
                            Plain English summary
                        </li>
                        <li style="position:relative;padding-left:1.2rem;">
                            <span style="position:absolute;left:0;color:var(--gold);">•</span>
                            Client script
                        </li>
                        <li style="position:relative;padding-left:1.2rem;">
                            <span style="position:absolute;left:0;color:var(--gold);">•</span>
                            WhatsApp delivery (4:30 PM)
                        </li>
                    </ul>
                </div>

                <a href="{{ route('checkout', ['plan' => 'starter']) }}"
                   class="btn-outline"
                   style="margin-top:2rem;text-align:center;">
                    Start plan →
                </a>
            </div>

            {{-- PRO (HIGHLIGHTED) --}}
            <div class="card pro-card" style="
                position:relative;
                padding:2.2rem;
                border:2px solid var(--gold);
                transform:scale(1.05);
                box-shadow:0 20px 50px rgba(0,0,0,.25);
                display:flex;
                flex-direction:column;
                justify-content:space-between;
            ">

                <!-- TOP CENTER BADGE -->
                <div style="
                    position:absolute;
                    top:-12px;
                    left:50%;
                    transform:translateX(-50%);
                    background:var(--gold);
                    color:#000;
                    padding:4px 12px;
                    font-size:.7rem;
                    font-weight:600;
                    letter-spacing:.05em;
                    border-radius:6px;
                    white-space:nowrap;
                ">
                    MOST POPULAR
                </div>

                <div style="margin-top:.5rem;">
                    
                    <h3 style="margin-bottom:.2rem;text-align:center;">PRO</h3>

                    <h2 style="font-size:2.6rem;margin:.3rem 0;text-align:center;">₹2,499</h2>

                    <div style="font-size:.75rem;color:var(--ink-3);margin-bottom:.5rem;text-align:center;">
                        <span style="text-decoration:line-through;">₹4,999</span>
                    </div>

                    <p style="color:var(--ink-3);margin-bottom:1rem;text-align:center;">
                        per month
                    </p>

                    <p style="margin-bottom:1rem;font-size:.9rem;text-align:center;">
                        Up to 3 portfolio types
                    </p>

                    <hr style="margin:1rem 0;opacity:.2;">

                    <ul style="
                        list-style:none;
                        padding:0;
                        margin:0;
                        font-size:.9rem;
                        line-height:1.9;
                    ">
                        <li style="position:relative;padding-left:1.2rem;">
                            <span style="position:absolute;left:0;color:var(--gold);">•</span>
                            Up to 3 daily reports
                        </li>
                        <li style="position:relative;padding-left:1.2rem;">
                            <span style="position:absolute;left:0;color:var(--gold);">•</span>
                            Portfolio-wise signals
                        </li>
                        <li style="position:relative;padding-left:1.2rem;">
                            <span style="position:absolute;left:0;color:var(--gold);">•</span>
                            Monthly outlook
                        </li>
                        <li style="position:relative;padding-left:1.2rem;">
                            <span style="position:absolute;left:0;color:var(--gold);">•</span>
                            Branded PDF
                        </li>
                        <li style="position:relative;padding-left:1.2rem;">
                            <span style="position:absolute;left:0;color:var(--gold);">•</span>
                            Priority support
                        </li>
                    </ul>

                </div>

                <a href="{{ route('checkout', ['plan' => 'pro']) }}"
                class="btn-primary"
                style="margin-top:2rem;text-align:center;">
                    Start plan
                </a>
            </div> 

            {{-- TEAM --}}
            <div class="card card-hover" style="padding:2rem;display:flex;flex-direction:column;justify-content:space-between;">
                
                <div>
                    <div class="eyebrow">Team</div>

                    <h2 style="font-size:2.2rem;margin:.5rem 0;">₹4,999</h2>
                    <p style="color:var(--ink-3);margin-bottom:1rem;">per month</p>

                    <p style="margin-bottom:1rem;font-size:.9rem;">
                        Multi-advisor setup
                    </p>

                    <hr style="margin:1rem 0;opacity:.2;">

                    <ul style="line-height:2;font-size:.9rem;">
                        <li>•  Everything in Pro</li>
                        <li>•  Multiple advisors</li>
                        <li>•  Custom thresholds</li>
                        <li>•  Strategy support</li>
                    </ul>
                </div>

                <a href="{{ route('checkout', ['plan' => 'team']) }}"
                   class="btn-outline"
                   style="margin-top:2rem;text-align:center;">
                    Start plan
                </a>
            </div>

        </div>

    </div> 
</section> 

{{-- ── CONTACT / SUBSCRIBE ──────────────────────────── --}}
<section id="contact" style="background:var(--paper-2);padding:4rem 0;">
    <div class="container reveal reveal-pop">

        <div class="text-center" style="margin-bottom:2.2rem;max-width:520px;margin-inline:auto;">
            
            <div class="eyebrow" style="margin-bottom:.8rem;">
                Get started
            </div>

            <h2 style="margin-bottom:.5rem;">
                Start free trial
            </h2>

            <p style="font-size:.9rem;color:var(--ink-2);line-height:1.5;">
                Get your first report on WhatsApp at 4:30 PM today.
            </p>

            <p style="margin-top:.4rem;font-size:.8rem;color:var(--ink-3);">
                No credit card · Takes 30 seconds
            </p>

        </div>

        {{-- SUCCESS --}}
        @if(session('success'))
            <div class="alert-success" style="max-width:420px;margin:0 auto 1rem;">
                {{ session('success') }}
            </div>
        @endif

        {{-- ERRORS --}}
        @if ($errors->any())
            <div class="alert-warning" style="max-width:420px;margin:0 auto 1rem;">
                <ul style="padding-left:1rem;">
                    @foreach ($errors->all() as $error)
                        <li style="font-size:.8rem;">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('ifa.submit') }}">
            @csrf

            <div style="
                display:flex;
                flex-direction:column;
                gap:1rem;
                max-width:420px;
                margin:auto;
            ">

                <input 
                    type="text" 
                    name="advisor_name" 
                    placeholder="Your name"
                    required
                    style="
                        padding:.9rem 1rem;
                        border:1px solid var(--paper-3);
                        border-radius:var(--radius-md);
                        background:transparent;
                        color:var(--ink);
                        font-size:.9rem;
                        outline:none;
                    "
                >

                <input 
                    type="tel" 
                    name="whatsapp" 
                    placeholder="WhatsApp number"
                    required
                    style="
                        padding:.9rem 1rem;
                        border:1px solid var(--paper-3);
                        border-radius:var(--radius-md);
                        background:transparent;
                        color:var(--ink);
                        font-size:.9rem;
                        outline:none;
                    "
                >

                <button 
                    type="submit" 
                    class="btn-primary"
                    style="
                        width:100%;
                        margin-top:.5rem;
                        padding:.9rem;
                        font-size:.95rem;
                        font-weight:500;
                    ">
                    Start free trial →
                </button>

                <p style="
                    font-size:.75rem;
                    color:var(--ink-3);
                    text-align:center;
                    margin-top:.4rem;
                ">
                    No login · No dashboard · Delivered on WhatsApp
                </p>

            </div>

        </form> 
    </div> 
</section>