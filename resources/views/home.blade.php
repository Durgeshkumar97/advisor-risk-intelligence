@extends('layouts.app')

@section('content')

{{-- ── HERO ─────────────────────────────────────────── --}}
<section id="home" style="padding-top:1.25rem; padding-bottom:1.25rem;">

    <div class="container hero-grid"
        style="align-items:center;">

        <!-- LEFT SIDE -->
        <div class="reveal reveal-left" style="max-width:520px;">

            <div class="eyebrow" style="margin-bottom:0.8rem;">
                risk intelligence · Independent financial advisors
            </div>

            <h1 style="line-height:1.1; font-size:clamp(2.0rem,4vw,2.8rem); margin-bottom:0.25rem;">
                When markets fall, clients panic.<br>
                RiskSignal helps advisors retain trust and AUM.<br>
                <em style="color:var(--gold);">
                    Daily Email risk summary + ready client script in plain English. Delivered before client calls.
                </em>
            </h1>

            <p style="
                margin-top:0.5rem;
                font-size:1rem;
                color:var(--ink-2);
                line-height:1.6;
            ">
                Risk signal for IFAs managing ₹5L–₹25L portfolios.
                One page. Three risks. One client script.
                Delivered straight to your inbox.
            </p>

            <!-- CTA -->
            <div style="
                margin-top:0.85rem;
                display:flex;
                gap:1rem;
                flex-wrap:wrap;
                align-items:center;
            ">
                <a href="#free-trial" class="btn-primary">
                    Get Report Now →
                </a>

                <a href="#sample-report" class="btn-outline">
                    See a sample report
                </a>
            </div>
        </div>

        <!-- RIGHT SIDE CARD -->
        <div class="reveal reveal-right reveal-delay-2"
            style="display:flex; justify-content:center; width:100%;">

            <div class="card card-hover"
                style="
                    max-width:420px;
                    width:100%;
                    min-width:0;
                    overflow:hidden;
                    transform:rotate(1.2deg);
                    margin:auto;
                ">

                <!-- HEADER -->
                <div style="
                    background:var(--ink);
                    color:var(--paper);
                    padding:1rem 1.25rem;
                    display:flex;
                    justify-content:space-between;
                    align-items:center;
                    gap:.6rem;
                    flex-wrap:wrap;
                ">
                    <span style="
                        font-family:var(--serif);
                        font-size:1rem;
                        line-height:1.3;
                    ">
                        Risk Intelligence
                    </span>

                    <span class="pill pill-gold"
                        style="
                            font-size:.6rem;
                            white-space:nowrap;
                        ">
                        DAY 12 · 2026
                    </span>
                </div>

                <!-- BODY -->
                <div style="padding:1.25rem;">

                    <!-- SCORE -->
                    <div style="
                        display:flex;
                        align-items:baseline;
                        gap:.75rem;
                        padding-bottom:1rem;
                        margin-bottom:1rem;
                        border-bottom:1px solid var(--paper-3);
                        flex-wrap:wrap;
                    ">
                        <span style="
                            font-family:var(--serif);
                            font-size:clamp(1.8rem,8vw,2.2rem);
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

                            <div style="
                                font-size:.7rem;
                                color:var(--ink-3);
                            ">
                                ↑ from 5.1 yesterday
                            </div>
                        </div>
                    </div>

                    <!-- FLAGS -->
                    <div style="
                        display:flex;
                        flex-direction:column;
                        gap:.6rem;
                        margin-bottom:1rem;
                    ">

                        <div style="
                            display:flex;
                            justify-content:space-between;
                            align-items:flex-start;
                            gap:.6rem;
                            font-size:.82rem;
                        ">
                            <span style="
                                color:var(--ink-2);
                                flex:1;
                                min-width:0;
                            ">
                                FII outflows — 3rd week
                            </span>
                            <span class="pill pill-high">HIGH</span>
                        </div>

                        <div style="
                            display:flex;
                            justify-content:space-between;
                            align-items:flex-start;
                            gap:.6rem;
                            font-size:.82rem;
                        ">
                            <span style="
                                color:var(--ink-2);
                                flex:1;
                                min-width:0;
                            ">
                                Rupee above 83.5 — import exposure
                            </span>
                            <span class="pill pill-high">HIGH</span>
                        </div>

                        <div style="
                            display:flex;
                            justify-content:space-between;
                            align-items:flex-start;
                            gap:.6rem;
                            font-size:.82rem;
                        ">
                            <span style="
                                color:var(--ink-2);
                                flex:1;
                                min-width:0;
                            ">
                                Mid-cap valuations elevated
                            </span>
                            <span class="pill pill-med">MED</span>
                        </div>
                    </div>

                    <!-- SCRIPT -->
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
                            word-break:break-word;
                        ">
                            "Foreign investors have been selling — this is normal during uncertainty.
                            Your long-term plan is intact. No action needed."
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

<!-- BUILT FOR SECTION -->
<section id="services" style="padding:2.5rem 0; background:var(--paper); border-top:1px solid var(--paper-3); border-bottom:1px solid var(--paper-3);">

    <div class="container" style="max-width:1100px; margin:auto; padding:0 1rem;">

        <!-- Heading -->
        <div style="text-align:center; margin-bottom:1.4rem;">

            <div class="eyebrow" style="margin-bottom:.55rem;">
                Who this is for
            </div>

            <h2 style="margin-bottom:.55rem;">
                Built for professionals who manage client trust
            </h2>

            <p style="
                max-width:700px;
                margin:auto;
                font-size:.92rem;
                color:var(--ink-3);
                line-height:1.6;
            ">
                Designed for advisors who need calm, credible communication during volatile markets.
            </p>

        </div>

        <!-- Grid -->
        <div class="built-for-grid">

            <div class="built-for-card">
                Independent Financial Advisors
            </div>

            <div class="built-for-card">
                Mutual Fund Distributors
            </div>

            <div class="built-for-card">
                Wealth Relationship Managers
            </div>

            <div class="built-for-card">
                Small Advisory Teams
            </div>

        </div>

    </div>
</section>

{{-- ── PROBLEM STATEMENT ─────────────────────────────── --}}
<section style="display:flex; justify-content:center; text-align:center;">

    <div style="max-width:700px; width:100%; margin:0 auto; padding:0 1rem;">

        <h2>Clients leave during market falls</h2>

        <div style="margin-top:1.5rem; font-size:.9rem; text-align:center;">
            Not because of portfolios. Because of communication.
        </div>

        <div style="margin-top:1.5rem;font-size:.9rem;">
            Panic → wrong advice → redemption → AUM loss
        </div>

        <div style="margin-top:.6rem;font-size:.9rem;">
            Calm response → trust → retained AUM
        </div>

    </div>

</section>

{{-- ── FOUNDER TRUST ────────────────────────────────── --}}
<section style="text-align:center; display:flex; justify-content:center;">

    <div class="container reveal reveal-pop"
        style="max-width:680px; margin:0 auto; text-align:center; display:flex; flex-direction:column; align-items:center;">

        <div class="eyebrow" style="margin-bottom:1rem; text-align:center;">
            Built by an IFA, for IFAs
        </div>

        <h2 style="text-align:center;">Why I built this</h2>

        <p style="margin-top:1rem;font-size:1rem;line-height:1.6;text-align:center;">
            As an advisor, I’ve seen clients lost during market falls... not because portfolios were wrong,
            but because they didn’t know what to say when panic hit.
        </p>

        <p style="margin-top:.6rem;font-size:1rem;line-height:1.6;text-align:center;">
            Most research is written for analysts. Not for the advisor who has 20 minutes before a client call.
        </p>

        <p style="margin-top:.6rem;font-size:1rem;font-weight:500;text-align:center;">
            RiskSignal gives you exactly what to say — when it matters most.
        </p>

        <div class="reveal reveal-pop reveal-delay-2"
            style="
                margin-top:1.5rem;
                display:flex;
                flex-direction:column;
                align-items:center;
                justify-content:center;
                gap:.5rem;
                background:var(--paper-2);
                border:1px solid var(--paper-3);
                border-radius:var(--radius-lg);
                padding:1rem 1.25rem;
                margin-inline:auto;
                text-align:center;
             ">

            <div style="
                width:42px;
                height:42px;
                border-radius:50%;
                background:var(--gold-lt);
                display:flex;
                align-items:center;
                justify-content:center;
                font-weight:500;
                font-size:.875rem;
                color:var(--gold);
                margin:0 auto;
            ">
                DK
            </div>

            <div style="text-align:center;">
                <div style="font-size:.875rem;font-weight:500;color:var(--ink); text-align:center;">
                    Durgesh Kumar
                </div>
                <div style="font-size:.75rem;color:var(--ink-3); text-align:center;">
                    Founder · Independent Financial Advisor
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ── HOW IT WORKS ─────────────────────────────────── --}}
<section id="how-it-works"
    style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.5rem;max-width:900px;margin-inline:auto;">

    {{-- STEP 1 --}}
    <div class="reveal reveal-pop reveal-delay-1" style="text-align:center;padding:1.25rem;">
        <div style="font-family:var(--serif);font-size:2.4rem;color:var(--paper-3);margin-bottom:.5rem;">1</div>

        <h3 style="margin-bottom:.4rem;">Subscribe</h3>

        <p style="font-size:.82rem;color:var(--ink-3);line-height:1.5;">
            Sign up and upload your portfolio. Done in 60 seconds.
        </p>
    </div>

    {{-- STEP 2 --}}
    <div class="reveal reveal-pop reveal-delay-2" style="text-align:center;padding:1.25rem;">
        <div style="font-family:var(--serif);font-size:2.4rem;color:var(--paper-3);margin-bottom:.5rem;">2</div>

        <h3 style="margin-bottom:.4rem;">Risk engine</h3>

        <p style="font-size:.82rem;color:var(--ink-3);margin-bottom:.6rem;">
            Analyzes your portfolio and market signals
        </p>
    </div>

    {{-- STEP 3 --}}
    <div class="reveal reveal-pop reveal-delay-3" style="text-align:center;padding:1.25rem;">
        <div style="font-family:var(--serif);font-size:2.4rem;color:var(--paper-3);margin-bottom:.5rem;">3</div>

        <h3 style="margin-bottom:.4rem;">Email delivery</h3>

        <p style="font-size:.82rem;color:var(--ink-3);line-height:1.5;">
            Risk report + client script in your inbox. Ready to use instantly.
        </p>
    </div>
</section>

{{-- ── SAMPLE REPORT ────────────────────────────────── --}}
<section id="sample-report" style="background:var(--paper-2); display:flex; justify-content:center;">

    <div class="container reveal reveal-pop"
        style="max-width:900px; width:100%; margin:0 auto;">

        <!-- TOP (CENTERED MARKETING) -->
        <div style="margin-bottom:2rem; text-align:center;">
            <div class="eyebrow" style="margin-bottom:.8rem;">
                See the real thing
            </div>

            <h2>
                Sample report — Week 12, 2026
            </h2>

            <div style="margin-top:.6rem;font-size:.875rem;">
                Mid-cap equity portfolios · Plain English edition
            </div>
        </div>

        <!-- REPORT CARD (LEFT-ALIGNED) -->
        <div class="card reveal reveal-pop reveal-delay-1"
            style="max-width:700px;margin-inline:auto;overflow:hidden;text-align:left;">

            <!-- HEADER -->
            <div style="background:var(--ink);color:var(--paper);padding:1.5rem 2rem;">

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
            <div style="padding:1.75rem 2rem;">

                <!-- SCORE -->
                <div style="background:var(--amber-lt);border:1px solid rgba(176,94,0,.2);border-radius:var(--radius-md);padding:1.1rem 1.4rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1.5rem;">

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
                <div style="margin-bottom:1.5rem;">

                    <div class="eyebrow"
                        style="margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:1px solid var(--paper-3);">
                        Risk flags this week
                    </div>

                    <!-- ROW -->
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
                <div>
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
                <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--paper-3);display:flex;justify-content:space-between;align-items:center;">

                    <span style="font-family:var(--serif);font-size:.9rem;color:var(--ink-3);">
                        Risk<span style="color:var(--gold);">Signal</span>
                    </span>

                    <span style="font-family:var(--mono);font-size:.65rem;color:var(--ink-3);">
                        Data: NSE, AMFI, RBI · Week 12, 2026
                    </span>
                </div>

            </div>
        </div>

        <!-- CTA (CENTERED) -->
        <div style="text-align:center; margin-top:2rem;">
            <a href="#free-trial" class="btn-primary">Get Today’s Free Sample Report →</a>

            <div style="margin-top:.75rem;font-size:.75rem;color:var(--ink-3);">
                Delivered straight to your inbox.
            </div>

            <div style="margin-top:1rem;">
                <a href="#live-example" class="btn-secondary">See Live Example</a>
            </div>
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
            <div style="margin-top:.6rem;font-size:.9rem;color:var(--ink-3);">
                Email delivery · Unlimited portfolio types · Monthly client limits by plan
            </div>
            <div style="margin-top:.4rem;font-size:.8rem;color:var(--ink-2);">
                No credit card required to start.
            </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:2rem;max-width:1100px;margin:auto;align-items:stretch;">

            {{-- STARTER --}}
            <div class="card card-hover" style="padding:2rem;display:flex;flex-direction:column;justify-content:space-between;">
                <div>
                    <div class="eyebrow">Starter</div>
                    <div style="display:flex;align-items:baseline;gap:.4rem;margin:.5rem 0;">
                        <h2 style="font-size:2.2rem;margin:0;">₹999</h2>
                        <span style="color:var(--ink-3);font-size:.9rem;">/ month</span>
                    </div>

                    <hr style="margin:1rem 0;opacity:.2;">

                    <ul style="list-style:none;padding:0;margin:0;font-size:.875rem;line-height:1;display:flex;flex-direction:column;gap:.75rem;">
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>50 clients / month</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Daily risk signals</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Daily email signal each morning</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Client conversation script</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>14-day free trial</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;opacity:.4;">
                            <span style="font-weight:700;">✗</span>
                            <span>Branded PDF reports</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;opacity:.4;">
                            <span style="font-weight:700;">✗</span>
                            <span>Priority support</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;opacity:.4;">
                            <span style="font-weight:700;">✗</span>
                            <span>Multi-advisor access</span>
                        </li>
                    </ul>
                </div>

                <a href="{{ route('checkout.show', ['plan' => 'starter']) }}"
                    class="btn-outline"
                    style="margin-top:2rem;text-align:center;">
                    Get Started
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
                ">MOST POPULAR</div>

                <div>
                    <div class="eyebrow" style="text-align:center;">Pro</div>
                    <div style="display:flex;align-items:baseline;gap:.4rem;margin:.5rem 0;justify-content:center;">
                        <h2 style="font-size:2.6rem;margin:0;">₹2,499</h2>
                        <span style="color:var(--ink-3);font-size:.9rem;">/ month</span>
                    </div>
                    <div style="text-align:center;font-size:.75rem;color:var(--ink-3);margin-bottom:.5rem;">
                        <span style="text-decoration:line-through;">₹4,999</span>
                    </div>

                    <hr style="margin:1rem 0;opacity:.2;">

                    <ul style="list-style:none;padding:0;margin:0;font-size:.875rem;line-height:1;display:flex;flex-direction:column;gap:.75rem;">
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Up to 250 clients / month</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Daily risk signals</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Daily email signal each morning</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Client conversation script</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Branded PDF reports</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Priority support (2-hour response)</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>14-day free trial</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;opacity:.4;">
                            <span style="font-weight:700;">✗</span>
                            <span>Multi-advisor access</span>
                        </li>
                    </ul>
                </div>

                <a href="{{ route('checkout.show', ['plan' => 'pro']) }}"
                    class="btn-primary"
                    style="margin-top:2rem;text-align:center;">
                    Get Pro →
                </a>
            </div>

            {{-- TEAM --}}
            <div class="card card-hover" style="padding:2rem;display:flex;flex-direction:column;justify-content:space-between;">
                <div>
                    <div class="eyebrow">Team</div>
                    <div style="display:flex;align-items:baseline;gap:.4rem;margin:.5rem 0;">
                        <h2 style="font-size:2.2rem;margin:0;">₹4,999</h2>
                        <span style="color:var(--ink-3);font-size:.9rem;">/ month</span>
                    </div>

                    <hr style="margin:1rem 0;opacity:.2;">

                    <ul style="list-style:none;padding:0;margin:0;font-size:.875rem;line-height:1;display:flex;flex-direction:column;gap:.75rem;">
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Up to 1,000 clients / month</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Daily risk signals</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Daily email signal each morning</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Client conversation script</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Branded PDF reports</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Priority support (2-hour response)</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>Multi-advisor access</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:#22c55e;font-weight:700;">✓</span>
                            <span>14-day free trial</span>
                        </li>
                    </ul>
                </div>

                <a href="{{ route('checkout.show', ['plan' => 'team']) }}"
                    class="btn-outline"
                    style="margin-top:2rem;text-align:center;">
                    Get Team →
                </a>
            </div>

        </div>

        {{-- FOOTER TRUST LINE --}}
        <div style="text-align:center;margin-top:2.5rem;font-size:.8rem;color:var(--ink-3);">
            🔒 Secure payment via Razorpay &nbsp;·&nbsp; Cancel anytime &nbsp;·&nbsp; No hidden fees
        </div>
        <div style="text-align:center;margin-top:.5rem;font-size:.8rem;color:var(--ink-3);">
            Questions? Email <a href="mailto:support@risksignal.in" style="color:var(--gold);text-decoration:none;">support@risksignal.in</a>
        </div>

    </div>
</section>

{{-- ── CONTACT / SUBSCRIBE ──────────────────────────── --}}
<section id="free-trial" style="background:var(--paper-2);padding:4rem 0;">
    <div class="container reveal reveal-pop">

        <div class="text-center" style="margin-bottom:2.2rem;max-width:520px;margin-inline:auto;">

            <div class="eyebrow" style="margin-bottom:.8rem;">
                Get started
            </div>

            <h2 style="margin-bottom:.5rem;">
                Get Today’s Client Panic Report Free
            </h2>

            <div style="font-size:.9rem;color:var(--ink-2);line-height:1.5;">
                Get your first report by Email.
            </div>

            <div style="margin-top:.4rem;font-size:.8rem;color:var(--ink-3);">
                No credit card · Takes 30 seconds
            </div>

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
                    ">

                <input
                    type="tel"
                    name="Phone"
                    placeholder="Phone number"
                    required
                    style="
                        padding:.9rem 1rem;
                        border:1px solid var(--paper-3);
                        border-radius:var(--radius-md);
                        background:transparent;
                        color:var(--ink);
                        font-size:.9rem;
                        outline:none;
                    ">

                <input
                    type="email"
                    name="email"
                    placeholder="Email address"
                    required
                    style="
                        padding:.9rem 1rem;
                        border:1px solid var(--paper-3);
                        border-radius:var(--radius-md);
                        background:transparent;
                        color:var(--ink);
                        font-size:.9rem;
                        outline:none;
                    ">

                <input
                    type="text"
                    name="firm_name"
                    placeholder="Firm name"
                    required
                    style="
                        padding:.9rem 1rem;
                        border:1px solid var(--paper-3);
                        border-radius:var(--radius-md);
                        background:transparent;
                        color:var(--ink);
                        font-size:.9rem;
                        outline:none;
                    ">

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
                    Get Today’s Client Panic Report Free
                </button>

                <p style="
                    font-size:.75rem;
                    color:var(--ink-3);
                    text-align:center;
                    margin-top:.4rem;
                ">
                    No login · No dashboard · Delivered by Email
                </p>

            </div>

        </form>

    </div>
</section>
