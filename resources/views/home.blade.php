@extends('layouts.app')

@section('content')

{{-- ── HERO ─────────────────────────────────────────── --}}
<section id="home" style="padding-top:96px;">

    <div class="container hero-grid">

        <!-- LEFT SIDE -->
        <div class="reveal reveal-left">

            <div class="eyebrow" style="margin-bottom:1.2rem;">
                Weekly risk intelligence · Independent financial advisors
            </div>

            <h1>
                When markets fall,<br>
                your clients call.<br>
                <em style="color:var(--gold);">We write your reply.</em>
            </h1>

            <p style="margin-top:1.4rem;max-width:480px;font-size:1rem;">
                RiskSignal delivers a plain-English weekly risk report to IFAs managing
                ₹5L–₹25L portfolios. One page. Three risks. One script to use with
                clients. On WhatsApp every Monday morning.
            </p>

            <div style="margin-top:2rem;display:flex;gap:1rem;flex-wrap:wrap;">
                <a href="#contact" class="btn-primary">
                    Start 2-week free trial
                </a>
                <a href="#sample-report" class="btn-outline">
                    See a sample report
                </a>
            </div>

            <p style="margin-top:.8rem;font-size:.75rem;color:var(--ink-3);">
                No credit card · Cancel anytime · Founding member price locked forever
            </p>

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
                    Weekly Risk Intelligence
                </span>

                <span class="pill pill-gold" style="font-size:.6rem;">
                    WEEK 12 · 2026
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
                            ↑ from 5.1 last week
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
                            FII outflows — 3rd consecutive week
                        </span>
                        <span class="pill pill-high">HIGH</span>
                    </div>

                    <div style="display:flex;justify-content:space-between;gap:.6rem;font-size:.82rem;">
                        <span style="color:var(--ink-2);flex:1;">
                            Rupee above 83.5 — import stocks exposed
                        </span>
                        <span class="pill pill-high">HIGH</span>
                    </div>

                    <div style="display:flex;justify-content:space-between;gap:.6rem;font-size:.82rem;">
                        <span style="color:var(--ink-2);flex:1;">
                            Mid-cap P/E elevated vs 5-year average
                        </span>
                        <span class="pill pill-med">MED</span>
                    </div>

                    <div style="display:flex;justify-content:space-between;gap:.6rem;font-size:.82rem;">
                        <span style="color:var(--ink-2);flex:1;">
                            RBI rate stance — no change expected
                        </span>
                        <span class="pill pill-low">LOW</span>
                    </div>

                </div>

                <div style="
                    background:var(--paper);
                    border-left:3px solid var(--gold);
                    padding:.8rem 1rem;
                ">
                    <div class="eyebrow" style="margin-bottom:.35rem;">
                        Tell your client this week
                    </div>

                    <div style="
                        font-size:.78rem;
                        color:var(--ink-2);
                        line-height:1.5;
                        font-style:italic;
                    ">
                        "Foreign investors have been selling for 3 weeks — that's normal during uncertainty. Your long-term plan is intact. No action needed."
                    </div>
                </div>

            </div>
        </div>

    </div>
</section>

{{-- HERO RESPONSIVE FIX --}}
<style>
.hero-grid {
    display: grid;
    gap: 2rem;
}

/* MOBILE */
@media (max-width: 768px) {
    .hero-grid {
        grid-template-columns: 1fr;
    }

    .hero-grid .card {
        transform: none !important;
    }
}

/* DESKTOP */
@media (min-width: 768px) {
    .hero-grid {
        grid-template-columns: 1.15fr 1fr;
        align-items: center;
        gap: 4rem;
    }
}
</style>

<section>
    <div class="container text-center">

        <h2>If you don’t control the narrative, clients will</h2>

        <p>
            When markets fall, clients don’t ask about valuation models.
            They ask: “Should I exit?”
        </p>

        <p>
            If you hesitate → you lose trust.
        </p>

    </div>
</section>

{{-- ── TRUST STRIP ──────────────────────────────────── --}}
<section style="padding:2rem 0;background:var(--paper-2);">
    <div class="container reveal reveal-pop">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;text-align:center;">
            
            <div class="reveal reveal-pop reveal-delay-1">
                <div style="font-size:.8rem;font-weight:500;color:var(--ink-2);">
                    Not investment advice
                </div>
                <div style="font-size:.72rem;color:var(--ink-3);margin-top:.2rem;">
                    Not SEBI-registered advice
                </div>
            </div>

            <div class="reveal reveal-pop reveal-delay-2">
                <div style="font-size:.8rem;font-weight:500;color:var(--ink-2);">
                    WhatsApp PDF every Monday morning
                </div>
                <div style="font-size:.72rem;color:var(--ink-3);margin-top:.2rem;">
                    No app login, no friction
                </div>
            </div>

            <div class="reveal reveal-pop reveal-delay-3">
                <div style="font-size:.8rem;font-weight:500;color:var(--ink-2);">
                    Client script in every report
                </div>
                <div style="font-size:.72rem;color:var(--ink-3);margin-top:.2rem;">
                    Know exactly what to say
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ── FOUNDER TRUST ────────────────────────────────── --}}
<section>
    <div class="container reveal reveal-pop"
         style="max-width:720px;margin-inline:auto;text-align:center;">

        <div class="eyebrow" style="margin-bottom:1rem;">
            Built by an IFA, for IFAs
        </div>

        <h2>Why I built this</h2>

        <p style="margin-top:1rem;font-size:1rem;">
            I'm Durgesh Kumar. I spent years watching advisors lose clients during market
            corrections — not because their portfolios were wrong, but because they didn't
            know what to say when a client called in a panic. The entire Indian research
            industry writes for analysts. Nobody writes for the IFA in Jaipur with 40
            clients and 20 minutes to prepare. RiskSignal fixes that.
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
                    Founder, RiskSignal · Independent Financial Advisor
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ── SERVICES ─────────────────────────────────────── --}}
<section id="service">
    <div class="container">

        <div class="text-center reveal reveal-pop" style="margin-bottom:2.5rem;">
            <div class="eyebrow" style="margin-bottom:.8rem;">What you get</div>
            <h2>Three things every IFA needs</h2>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.25rem;">

            <div class="card card-hover reveal reveal-pop reveal-delay-1" style="padding:1.75rem;">
                <div class="eyebrow" style="margin-bottom:.8rem;color:var(--gold);">01</div>
                <h3 style="margin-bottom:.6rem;">Weekly risk score</h3>
                <p style="font-size:.875rem;">
                    A single number — 1 to 10 — telling you exactly how much market risk your clients face this week.
                </p>
            </div>

            <div class="card card-hover reveal reveal-pop reveal-delay-2" style="padding:1.75rem;">
                <div class="eyebrow" style="margin-bottom:.8rem;color:var(--gold);">02</div>
                <h3 style="margin-bottom:.6rem;">Three plain-English risk flags</h3>
                <p style="font-size:.875rem;">
                    The three biggest things moving markets this week — written simply.
                </p>
            </div>

            <div class="card card-hover reveal reveal-pop reveal-delay-3" style="padding:1.75rem;">
                <div class="eyebrow" style="margin-bottom:.8rem;color:var(--gold);">03</div>
                <h3 style="margin-bottom:.6rem;">Client conversation script</h3>
                <p style="font-size:.875rem;">
                    The exact words to say when a client calls worried.
                </p>
            </div>

        </div>
    </div>
</section>

{{-- ── HOW IT WORKS ─────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem;max-width:900px;margin-inline:auto;">

    {{-- STEP 1 --}}
    <div class="reveal reveal-pop reveal-delay-1" style="text-align:center;padding:1.5rem 1rem;">
        <div style="font-family:var(--serif);font-size:3rem;color:var(--paper-3);margin-bottom:.8rem;">1</div>

        <h3 style="margin-bottom:.5rem;">Subscribe</h3>

        <p style="font-size:.85rem;color:var(--ink-3);line-height:1.6;">
            Choose your plan, enter your WhatsApp number, and tell us what type of portfolios you manage.
            Setup takes under 60 seconds — no onboarding calls, no friction.
        </p>
    </div>

    {{-- STEP 2 --}}
    <div class="reveal reveal-pop reveal-delay-2" style="text-align:center;padding:1.5rem 1rem;">
        <div style="font-family:var(--serif);font-size:3rem;color:var(--paper-3);margin-bottom:.8rem;">2</div>

        <h3 style="margin-bottom:.5rem;">We analyze the market</h3>

        <p style="font-size:.85rem;color:var(--ink-3);line-height:1.6;">
            Every Sunday, we process NSE data, FII flows, currency movement, valuations, and macro signals —
            then convert it into a simple weekly risk score and 3 clear risk flags.
        </p>
    </div>

    {{-- STEP 3 --}}
    <div class="reveal reveal-pop reveal-delay-3" style="text-align:center;padding:1.5rem 1rem;">
        <div style="font-family:var(--serif);font-size:3rem;color:var(--paper-3);margin-bottom:.8rem;">3</div>

        <h3 style="margin-bottom:.5rem;">Monday delivery</h3>

        <p style="font-size:.85rem;color:var(--ink-3);line-height:1.6;">
            You receive a ready-to-use PDF on WhatsApp by 9 AM —
            including a client conversation script you can copy, forward, or read directly.
        </p>
    </div>

</div>

{{-- ── DIFFERENTIATION --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;max-width:800px;margin:auto;">

    <!-- LEFT: PROBLEM -->
    <div class="card reveal reveal-left reveal-delay-1" style="padding:1.5rem;">
        <strong style="display:block;margin-bottom:.8rem;">Traditional research</strong>

        <div style="display:flex;flex-direction:column;gap:.5rem;font-size:.85rem;">

            <div style="display:flex;gap:.5rem;">
                <span style="color:var(--red);">✕</span>
                <span>Complex language</span>
            </div>

            <div style="display:flex;gap:.5rem;">
                <span style="color:var(--red);">✕</span>
                <span>Built for analysts</span>
            </div>

            <div style="display:flex;gap:.5rem;">
                <span style="color:var(--red);">✕</span>
                <span>No client script</span>
            </div>

            <div style="display:flex;gap:.5rem;">
                <span style="color:var(--red);">✕</span>
                <span>Time-consuming to interpret</span>
            </div>

        </div>
    </div>

    <!-- RIGHT: SOLUTION -->
    <div class="card reveal reveal-right reveal-delay-2"
         style="padding:1.5rem;border:2px solid var(--gold);">

        <strong style="display:block;margin-bottom:.8rem;">RiskSignal</strong>

        <div style="display:flex;flex-direction:column;gap:.5rem;font-size:.85rem;">

            <div style="display:flex;gap:.5rem;">
                <span style="color:var(--gold);">✓</span>
                <span>Plain English insights</span>
            </div>

            <div style="display:flex;gap:.5rem;">
                <span style="color:var(--gold);">✓</span>
                <span>Built for IFAs</span>
            </div>

            <div style="display:flex;gap:.5rem;">
                <span style="color:var(--gold);">✓</span>
                <span>Ready-to-use client script</span>
            </div>

            <div style="display:flex;gap:.5rem;">
                <span style="color:var(--gold);">✓</span>
                <span>Actionable in under 2 minutes</span>
            </div>

        </div>
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
                        Week 12 · March 17–21, 2026<br>
                        Mid-cap equity<br>
                        Prepared for: IFA Subscriber
                    </div>
                </div>

                <div style="font-family:var(--serif);font-size:1.6rem;line-height:1.2;">
                    Weekly Risk Intelligence Report
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
            <a href="#contact" class="btn-primary">Get this every Monday →</a>

            <p style="margin-top:.75rem;font-size:.75rem;color:var(--ink-3);">
                2-week free trial · No credit card
            </p>
        </div>

    </div>
</section>

<section>
    <div class="container reveal reveal-pop" style="max-width:700px;margin:auto;text-align:center;">

        <div class="eyebrow">How advisors actually use this</div>

        <h2 style="margin-top:.5rem;">Real scenario</h2>

        <div class="card" style="padding:1.5rem;margin-top:1.5rem;text-align:left;">

            <p style="font-size:.9rem;">
                <strong>Client:</strong> “Market gir raha hai — exit karein?”
            </p>

            <p style="margin-top:.8rem;font-size:.9rem;color:var(--ink-2);">
                <strong>Your response (from RiskSignal):</strong><br>
                “Foreign investors have been selling for 3 weeks — that's normal during uncertainty.
                Your long-term plan is intact. No action needed.”
            </p>

        </div>

    </div>
</section>

{{-- ── PRICING ──────────────────────────────────────── --}}
<section id="pricing">
    <div class="container reveal reveal-pop">

        <div class="text-center reveal reveal-pop" style="margin-bottom:2.5rem;">
            <div class="eyebrow">Simple, honest pricing</div>
            <h2>Flat monthly subscription</h2>
            <p style="margin-top:.6rem;font-size:.875rem;">
                No per-report counting. No hidden limits.
            </p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem;max-width:950px;margin:auto;">

            {{-- STARTER --}}
            <div class="card card-hover reveal reveal-pop reveal-delay-1" style="padding:1.75rem;">
                <div class="eyebrow">Starter</div>

                <h2 style="font-size:2.4rem;">₹799</h2>
                <p style="color:var(--ink-3);margin-bottom:1rem;">
                    per month
                </p>

                <p style="margin-bottom:1rem;">
                    Solo IFA managing one portfolio type
                </p>

                <hr style="margin:1rem 0;">

                <ul style="line-height:2;">
                    <li>— 1 weekly report</li>
                    <li>— Plain English summary</li>
                    <li>— Client script</li>
                    <li>— WhatsApp delivery</li>
                </ul>

                <a href="{{ route('checkout', ['plan' => 'starter']) }}"
                   class="btn-outline"
                   style="display:block;text-align:center;margin-top:1.5rem;">
                    Purchase
                </a>
            </div>

            {{-- PRO --}}
            <div class="card pro-card reveal reveal-pop reveal-delay-2" style="padding:2rem;">

                <div class="popular-badge">
                    MOST POPULAR
                </div>

                <h3 style="margin-top:0.5rem;">PRO</h3>

                <h2 style="font-size:2.6rem;">₹1,499</h2>

                <p style="color:var(--ink-3);margin-bottom:1.2rem;">
                    per month · billed monthly
                </p>

                <p style="margin-bottom:1rem;">
                    Active IFA with multi-asset clients
                </p>

                <hr style="margin:1rem 0;">

                <ul style="line-height:2;">
                    <li>— 3 reports/week</li>
                    <li>— Portfolio risk scoring</li>
                    <li>— Monthly outlook</li>
                    <li>— Branded PDF</li>
                    <li>— Priority support</li>
                </ul>

                <a href="{{ route('checkout', ['plan' => 'pro']) }}"
                   class="btn-primary"
                   style="display:block;text-align:center;margin-top:1.5rem;">
                    Purchase
                </a>
            </div>


            {{-- TEAM --}}
            <div class="card card-hover reveal reveal-pop reveal-delay-3" style="padding:1.75rem;">
                <div class="eyebrow">Team</div>

                <h2 style="font-size:2.4rem;">₹3,499</h2>

                <p style="color:var(--ink-3);margin-bottom:1rem;">
                    per month
                </p>

                <p style="margin-bottom:1rem;">
                    Small advisory firm (3–5 advisors)
                </p>

                <hr style="margin:1rem 0;">

                <ul style="line-height:2;">
                    <li>— Everything in Pro</li>
                    <li>— Up to 5 advisors</li>
                    <li>— Custom thresholds</li>
                    <li>— Strategy calls</li>
                </ul>

                <a href="{{ route('checkout', ['plan' => 'team']) }}"
                   class="btn-outline"
                   style="display:block;text-align:center;margin-top:1.5rem;">
                    Purchase
                </a>
            </div>

        </div>

        {{-- TRUST NOTE --}}
        <div class="reveal reveal-pop reveal-delay-4"
             style="max-width:600px;margin:2rem auto 0;text-align:center;font-size:.8rem;color:var(--ink-3);">
            Founding pricing — locked for early users.  
        </div>

    </div>
</section>

<section>
    <div class="container reveal reveal-pop" style="max-width:700px;margin:auto;">

        <h2 style="text-align:center;">Questions advisors ask</h2>

        <div style="margin-top:2rem;display:grid;gap:1rem;">

            <div class="card" style="padding:1.2rem;">
                <strong>Is this investment advice?</strong>
                <p style="font-size:.85rem;color:var(--ink-3);margin-top:.4rem;">
                    No. This is market intelligence to help you communicate better with clients.
                </p>
            </div>

            <div class="card" style="padding:1.2rem;">
                <strong>Will this replace my research?</strong>
                <p style="font-size:.85rem;color:var(--ink-3);margin-top:.4rem;">
                    No. It simplifies communication — not decision-making.
                </p>
            </div>

            <div class="card" style="padding:1.2rem;">
                <strong>What if I don’t like it?</strong>
                <p style="font-size:.85rem;color:var(--ink-3);margin-top:.4rem;">
                    Cancel anytime. No lock-in.
                </p>
            </div>

        </div>
    </div>
</section>

<div class="reveal reveal-pop" style="text-align:center;margin-top:2rem;font-size:.85rem;color:var(--ink-3);">
    One saved client during a market fall pays for this for years.
</div>

{{-- ── CONTACT / SUBSCRIBE ──────────────────────────── --}}
<section id="contact" style="background:var(--paper-2);">
    <div class="container reveal reveal-pop">

        <div class="text-center reveal reveal-pop" style="margin-bottom:2rem;">
            <div class="eyebrow" style="margin-bottom:.8rem;">Get started</div>
            <h2>Start your 2-week free trial</h2>
            <p style="margin-top:.6rem;font-size:.875rem;">
                No credit card. First report arrives this Monday at 8am on WhatsApp.
            </p>
        </div>

        {{-- SUCCESS --}}
        @if(session('success'))
            <div class="alert-success">
                {{ session('success') }}
            </div>
        @endif

        {{-- ERRORS --}}
        @if ($errors->any())
            <div class="alert-warning">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('ifa.submit') }}" enctype="multipart/form-data">
            @csrf

            <div class="form-group">
                <label>Full name</label>
                <input type="text" name="advisor_name" required>
            </div>

            <div class="form-group">
                <label>WhatsApp number</label>
                <input type="tel" name="whatsapp" required>
            </div>

            <div class="form-group">
                <label>Email address</label>
                <input type="email" name="email" required>
            </div>

            <div class="form-group">
                <label>Firm name</label>
                <input type="text" name="firm_name" required>
            </div>

            <div class="form-group">
                <label>Upload client file (PDF, PNG, ZIP)</label>
                <input type="file" name="document">
            </div>

            <button type="submit" class="btn-primary" style="width:100%;">
                Start free trial
            </button>
        </form>

    </div>
</section>