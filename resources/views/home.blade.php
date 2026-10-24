@extends('layouts.app')

@section('content')

{{-- ── HERO ─────────────────────────────────────────── --}}
<section id="home" style="padding-top:1.25rem; padding-bottom:1.25rem;">

    <div class="container hero-grid"
        style="align-items:center;">

        <!-- LEFT SIDE -->
        <div class="reveal reveal-left" style="max-width:520px;">

            <div class="eyebrow" style="margin-bottom:0.8rem;">
                Portfolio risk reports · For independent financial advisors
            </div>

            <h1 style="line-height:1.1; font-size:clamp(2.0rem,4vw,2.8rem); margin-bottom:0.25rem;">
                When markets fall, clients panic.<br>
                RiskSignal gives you a clear, rules-based read of each client's portfolio risk.<br>
                <em style="color:var(--gold);">
                    Upload a holdings file — get a risk report PDF by email.
                </em>
            </h1>

            <p style="
                margin-top:0.5rem;
                font-size:1rem;
                color:var(--ink-2);
                line-height:1.6;
            ">
                For IFAs managing ₹5L–₹25L client portfolios.
                A risk report PDF with a 0–100 risk score, the flags behind it, and every holding —
                emailed to you after each upload.
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
                    Start free trial →
                </a>

                <a href="#sample-report" class="btn-outline">
                    See a sample report
                </a>
            </div>
        </div>

        <!-- RIGHT SIDE CARD -->
        <div class="hero-card-wrap" style="display:flex; justify-content:center; width:100%;">

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
                        Portfolio Risk Report
                    </span>

                    <span class="pill pill-gold"
                        style="
                            font-size:.6rem;
                            white-space:nowrap;
                        ">
                        ILLUSTRATIVE SAMPLE
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
                            58<span style="font-size:.5em;color:var(--ink-3);">/100</span>
                        </span>

                        <div>
                            <div style="
                                font-size:.7rem;
                                letter-spacing:.07em;
                                color:var(--amber);
                                text-transform:uppercase;
                            ">
                                Medium
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
                                Equity makes up more than 90% of the portfolio
                            </span>
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
                                The largest holding is 42% of portfolio value, across 6 holdings (holding concentration index above 30 of 100)
                            </span>
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
                                Holdings with a known cost are more than 7% below what was paid
                            </span>
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
                Built for professionals who manage client portfolios
            </h2>

            <p style="
                max-width:700px;
                margin:auto;
                font-size:.92rem;
                color:var(--ink-3);
                line-height:1.6;
            ">
                Designed for advisors who want a consistent risk read on each client's holdings.
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

{{-- ── FOUNDER TRUST ────────────────────────────────── --}}
<section style="text-align:center; display:flex; justify-content:center;">

    <div class="container reveal reveal-pop"
        style="max-width:680px; margin:0 auto; text-align:center; display:flex; flex-direction:column; align-items:center;">

        <div class="eyebrow" style="margin-bottom:1rem; text-align:center;">
            Who built this
        </div>

        <h2 style="text-align:center;">Why I built this</h2>

        <p style="margin-top:1rem;font-size:1rem;line-height:1.6;text-align:center;">
            I’m a data scientist. RiskSignal turns a client’s holdings file into a risk report PDF:
            a 0–100 score from fixed rules, plus flags on concentration, equity share and losses against cost.
        </p>

        <p style="margin-top:.6rem;font-size:1rem;line-height:1.6;text-align:center;">
            Most research is written for analysts. Not for the advisor who has 20 minutes before a client call.
        </p>

        <p style="margin-top:.6rem;font-size:1rem;font-weight:500;text-align:center;">
            What you tell your clients stays your call — the report only describes the portfolio.
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
                    Founder · Data scientist
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

        <h3 style="margin-bottom:.4rem;">Sign up</h3>

        <p style="font-size:.82rem;color:var(--ink-3);line-height:1.5;">
            Start a free trial and upload a client's holdings file (CSV, Excel or ZIP).
        </p>
    </div>

    {{-- STEP 2 --}}
    <div class="reveal reveal-pop reveal-delay-2" style="text-align:center;padding:1.25rem;">
        <div style="font-family:var(--serif);font-size:2.4rem;color:var(--paper-3);margin-bottom:.5rem;">2</div>

        <h3 style="margin-bottom:.4rem;">Rules-based scoring</h3>

        <p style="font-size:.82rem;color:var(--ink-3);margin-bottom:.6rem;">
            Scores the holdings in your file with fixed rules — no live market feed.
        </p>
    </div>

    {{-- STEP 3 --}}
    <div class="reveal reveal-pop reveal-delay-3" style="text-align:center;padding:1.25rem;">
        <div style="font-family:var(--serif);font-size:2.4rem;color:var(--paper-3);margin-bottom:.5rem;">3</div>

        <h3 style="margin-bottom:.4rem;">Email delivery</h3>

        <p style="font-size:.82rem;color:var(--ink-3);line-height:1.5;">
            The risk report PDF is emailed to you once your file is processed.
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
                Illustrative sample
            </div>

            <h2>
                What a report looks like
            </h2>

            <div style="margin-top:.6rem;font-size:.875rem;">
                Invented portfolio · not a real client
            </div>
        </div>

        <!-- REPORT CARD (LEFT-ALIGNED) -->
        <div class="card reveal reveal-pop reveal-delay-1"
            style="max-width:700px;margin-inline:auto;overflow:hidden;text-align:left;">

            <!-- HEADER -->
            <div style="background:var(--ink);color:var(--paper);padding:1.5rem 2rem;">

                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;">
                    <span style="font-family:var(--serif);font-size:1.1rem;">
                        Risk<span style="color:var(--gold);">Signal</span>
                    </span>

                    <div style="text-align:right;font-family:var(--mono);font-size:.65rem;color:rgba(255,255,255,.4);line-height:1.7;">
                        Illustrative sample<br>
                        Portfolio: Sample client
                    </div>
                </div>

                <div style="font-family:var(--serif);font-size:1.6rem;line-height:1.2;">
                    Portfolio Risk Report
                </div>

                <div style="font-size:.825rem;color:rgba(255,255,255,.5);margin-top:.3rem;">
                    Scored from the holdings in an uploaded file
                </div>
            </div>

            <!-- BODY -->
            <div style="padding:1.75rem 2rem;">

                <!-- SCORE -->
                <div style="background:var(--amber-lt);border:1px solid rgba(176,94,0,.2);border-radius:var(--radius-md);padding:1.1rem 1.4rem;margin-bottom:1.5rem;display:flex;flex-wrap:wrap;align-items:center;gap:.75rem 1.5rem;">

                    <div style="font-family:var(--serif);font-size:3rem;color:var(--amber);line-height:1;flex-shrink:0;">
                        58<span style="font-size:.4em;color:var(--ink-3);">/100</span>
                    </div>

                    <div style="flex:1 1 12rem;min-width:0;">
                        <div style="font-family:var(--mono);font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;color:var(--amber);margin-bottom:.3rem;">
                            Risk level: Medium
                        </div>

                        <div style="font-size:.82rem;color:var(--ink-2);">
                            Score from asset mix, concentration, equity share and loss against cost.
                        </div>
                    </div>
                </div>

                <!-- FLAGS -->
                <div style="margin-bottom:1.5rem;">

                    <div class="eyebrow"
                        style="margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:1px solid var(--paper-3);">
                        Risk flags
                    </div>

                    <!-- ROW -->
                    <div style="padding:.8rem 0;border-bottom:1px solid var(--paper-2);">
                        <div style="font-size:.875rem;font-weight:500;">Equity makes up more than 90% of the portfolio</div>
                    </div>

                    <div style="padding:.8rem 0;border-bottom:1px solid var(--paper-2);">
                        <div style="font-size:.875rem;font-weight:500;">The largest holding is 42% of portfolio value, across 6 holdings (holding concentration index above 30 of 100)</div>
                    </div>

                    <div style="padding:.8rem 0;">
                        <div style="font-size:.875rem;font-weight:500;">Holdings with a known cost are more than 7% below what was paid</div>
                    </div>

                </div>

                <!-- FOOTER -->
                <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--paper-3);display:flex;flex-wrap:wrap;gap:.5rem 1rem;justify-content:space-between;align-items:center;">

                    <span style="font-family:var(--serif);font-size:.9rem;color:var(--ink-3);white-space:nowrap;">
                        Risk<span style="color:var(--gold);">Signal</span>
                    </span>

                    <span style="font-family:var(--mono);font-size:.65rem;color:var(--ink-3);">
                        Values from the uploaded file · Illustrative sample
                    </span>
                </div>

            </div>
        </div>

        <!-- CTA (CENTERED) -->
        <div style="text-align:center; margin-top:2rem;">
            <a href="#free-trial" class="btn-primary">Start free trial →</a>

            <div style="margin-top:.75rem;font-size:.75rem;color:var(--ink-3);">
                Upload a file — the report PDF is emailed to you.
            </div>
        </div>

    </div>
</section>

{{-- POSITIONING LINE --}}
<section>
    <div class="container" style="text-align:center;margin:2rem auto 0;font-size:.9rem;color:var(--ink-2);max-width:600px;">
        Plain-language risk reports — not analyst research.
    </div>
</section>

{{-- ── PRICING ──────────────────────────────────────── --}}
<section id="pricing" style="padding:5rem 0;">
    <div class="container reveal reveal-pop">

        <div class="text-center" style="margin-bottom:3rem;">
            <div class="eyebrow">Simple, honest pricing</div>
            <h2 style="margin-top:.4rem;">30-day plans, paid upfront</h2>
            <div style="margin-top:.6rem;font-size:.9rem;color:var(--ink-3);">
                Email delivery · CSV, Excel and ZIP uploads · Monthly client limits by plan
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
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>50 clients / month</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>Risk report PDF after each upload</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>Daily 8 AM email: latest upload's score</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>Email support</span>
                        </li>
                        @if($trialDays)
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>{{ $trialDays }}-day free trial</span>
                        </li>
                        @endif
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
                display:flex;
                flex-direction:column;
                justify-content:space-between;
            ">
                <div>
                    <div class="eyebrow" style="text-align:center;">Pro</div>
                    <div style="display:flex;align-items:baseline;gap:.4rem;margin:.5rem 0;justify-content:center;">
                        <h2 style="font-size:2.6rem;margin:0;">₹2,499</h2>
                        <span style="color:var(--ink-3);font-size:.9rem;">/ month</span>
                    </div>

                    <hr style="margin:1rem 0;opacity:.2;">

                    <ul style="list-style:none;padding:0;margin:0;font-size:.875rem;line-height:1;display:flex;flex-direction:column;gap:.75rem;">
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>Up to 250 clients / month</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>Risk report PDF after each upload</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>Daily 8 AM email: latest upload's score</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>Email support</span>
                        </li>
                        @if($trialDays)
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>{{ $trialDays }}-day free trial</span>
                        </li>
                        @endif
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
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>Up to 1,000 clients / month</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>Risk report PDF after each upload</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>Daily 8 AM email: latest upload's score</span>
                        </li>
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>Email support</span>
                        </li>
                        @if($trialDays)
                        <li style="display:flex;align-items:center;gap:.6rem;">
                            <span style="color:var(--gold);display:inline-flex;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                                </svg></span>
                            <span>{{ $trialDays }}-day free trial</span>
                        </li>
                        @endif
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
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:.8rem;height:.8rem;display:inline-block;vertical-align:-1px;">
                <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd" />
            </svg>
            Secure payment via Razorpay &nbsp;·&nbsp; 30-day plan · No auto-debit &nbsp;·&nbsp; No hidden fees
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
                Start your free trial
            </h2>

            <div style="font-size:.9rem;color:var(--ink-2);line-height:1.5;">
                Upload a holdings file and the report PDF is emailed to you.
            </div>

            <div style="margin-top:.4rem;font-size:.8rem;color:var(--ink-3);">
                No credit card
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
                    name="whatsapp"
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

                {{-- Cloudflare Turnstile. Same widget as /register; the token is
                     verified server-side by TurnstileVerifier. A failure shows in
                     the error list above the form. min-height reserves the
                     Managed widget's 65px before its script renders it, so the
                     button below does not jump (CLS) — visitors arriving via
                     /#free-trial see this form at load. --}}
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" style="margin:0 auto;min-height:65px;"></div>

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
                    Start your free trial
                </button>

                <p style="
                    font-size:.75rem;
                    color:var(--ink-3);
                    text-align:center;
                    margin-top:.4rem;
                ">
                    Report PDF emailed after each upload
                </p>

            </div>

        </form>

    </div>
</section>

@endsection