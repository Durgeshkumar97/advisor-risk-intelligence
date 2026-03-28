@extends('layouts.app')

@section('content')

{{-- ── NAV ──────────────────────────────────────────── --}}
<nav>
    <div class="container nav-inner">
        <a href="#home" class="nav-logo">Risk<span>Lens</span></a>
        <ul>
            <li><a href="#service">Services</a></li>
            <li><a href="#how-it-works">How it works</a></li>
            <li><a href="#pricing">Pricing</a></li>
            <li><a href="#sample-report">Sample report</a></li>
            <li><a href="#contact" style="color:var(--ink);font-weight:500;">Start free trial →</a></li>
        </ul> 
    </div> 
</nav>

{{-- ── HERO ─────────────────────────────────────────── --}}
<section id="home" style="padding-top:96px;">
    <div class="container" style="display:grid;grid-template-columns:1.15fr 1fr;gap:4rem;align-items:center;">

        <div>
            <div class="eyebrow" style="margin-bottom:1.2rem;">
                Weekly risk intelligence · Independent financial advisors
            </div>

            <h1>
                When markets fall,<br>
                your clients call.<br>
                <em style="color:var(--gold);">We write your reply.</em>
            </h1>

            <p style="margin-top:1.4rem;max-width:480px;font-size:1rem;">
                RiskLens delivers a plain-English weekly risk report to IFAs managing
                ₹5L–₹25L portfolios. One page. Three risks. One script to use with
                clients. On WhatsApp every Monday morning.
            </p>

            <div style="margin-top:2rem;display:flex;gap:1rem;flex-wrap:wrap;">
                <a href="#contact" class="btn-primary">Start 2-week free trial</a>
                <a href="#sample-report" class="btn-outline">See a sample report</a>
            </div>

            <p style="margin-top:.8rem;font-size:.75rem;color:var(--ink-3);">
                No credit card · Cancel anytime · Founding member price locked forever
            </p>
        </div>

        {{-- Live sample card --}}
        <div class="card card-hover" style="overflow:hidden;transform:rotate(1.2deg);">
            <div style="background:var(--ink);color:var(--paper);padding:1rem 1.25rem;display:flex;justify-content:space-between;align-items:center;">
                <span style="font-family:var(--serif);font-size:1rem;">Weekly Risk Intelligence</span>
                <span class="pill pill-gold" style="font-size:.6rem;">WEEK 12 · 2026</span>
            </div>
            <div style="padding:1.25rem;">
                <div style="display:flex;align-items:baseline;gap:.75rem;padding-bottom:1rem;margin-bottom:1rem;border-bottom:1px solid var(--paper-3);">
                    <span style="font-family:var(--serif);font-size:2.6rem;color:var(--amber);line-height:1;">6.4</span>
                    <div>
                        <div style="font-size:.7rem;font-family:var(--mono);letter-spacing:.07em;color:var(--amber);text-transform:uppercase;">Moderate-High</div>
                        <div style="font-size:.7rem;color:var(--ink-3);">↑ from 5.1 last week</div>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:.6rem;margin-bottom:1rem;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem;font-size:.82rem;">
                        <span style="color:var(--ink-2);flex:1;line-height:1.4;">FII outflows — 3rd consecutive week</span>
                        <span class="pill pill-high">HIGH</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem;font-size:.82rem;">
                        <span style="color:var(--ink-2);flex:1;line-height:1.4;">Rupee above 83.5 — import stocks exposed</span>
                        <span class="pill pill-high">HIGH</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem;font-size:.82rem;">
                        <span style="color:var(--ink-2);flex:1;line-height:1.4;">Mid-cap P/E elevated vs 5-year average</span>
                        <span class="pill pill-med">MED</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.6rem;font-size:.82rem;">
                        <span style="color:var(--ink-2);flex:1;line-height:1.4;">RBI rate stance — no change expected</span>
                        <span class="pill pill-low">LOW</span>
                    </div>
                </div>
                <div style="background:var(--paper);border-left:3px solid var(--gold);padding:.8rem 1rem;border-radius:0 var(--radius-sm) var(--radius-sm) 0;">
                    <div class="eyebrow" style="margin-bottom:.35rem;">Tell your client this week</div>
                    <div style="font-size:.78rem;color:var(--ink-2);line-height:1.5;font-style:italic;">
                        "Foreign investors have been selling for 3 weeks — that's normal during uncertainty. Your long-term plan is intact. No action needed."
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

{{-- ── TRUST STRIP ──────────────────────────────────── --}}
<section style="padding:2rem 0;background:var(--paper-2);">
    <div class="container">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;text-align:center;">
            <div>
                <div style="font-size:.8rem;font-weight:500;color:var(--ink-2);">Pure market intelligence</div>
                <div style="font-size:.72rem;color:var(--ink-3);margin-top:.2rem;">Not SEBI-registered advice</div>
            </div>
            <div>
                <div style="font-size:.8rem;font-weight:500;color:var(--ink-2);">WhatsApp PDF every Monday</div>
                <div style="font-size:.72rem;color:var(--ink-3);margin-top:.2rem;">No app, no login, no friction</div>
            </div>
            <div>
                <div style="font-size:.8rem;font-weight:500;color:var(--ink-2);">Client script in every report</div>
                <div style="font-size:.72rem;color:var(--ink-3);margin-top:.2rem;">Know exactly what to say</div>
            </div>
        </div>
    </div>
</section>

{{-- ── FOUNDER TRUST ────────────────────────────────── --}}
<section>
    <div class="container" style="max-width:720px;margin-inline:auto;text-align:center;">
        <div class="eyebrow" style="margin-bottom:1rem;">Built by an IFA, for IFAs</div>
        <h2>Why I built this</h2>
        <p style="margin-top:1rem;font-size:1rem;">
            I'm Durgesh Kumar. I spent years watching advisors lose clients during market
            corrections — not because their portfolios were wrong, but because they didn't
            know what to say when a client called in a panic. The entire Indian research
            industry writes for analysts. Nobody writes for the IFA in Jaipur with 40
            clients and 20 minutes to prepare. RiskLens fixes that.
        </p>
        <div style="margin-top:1.5rem;display:inline-flex;align-items:center;gap:.75rem;background:var(--paper-2);border:1px solid var(--paper-3);border-radius:var(--radius-lg);padding:.75rem 1.25rem;">
            <div style="width:42px;height:42px;border-radius:50%;background:var(--gold-lt);display:flex;align-items:center;justify-content:center;font-weight:500;font-size:.875rem;color:var(--gold);flex-shrink:0;">DK</div>
            <div style="text-align:left;">
                <div style="font-size:.875rem;font-weight:500;color:var(--ink);">Durgesh Kumar</div>
                <div style="font-size:.75rem;color:var(--ink-3);">Founder, RiskLens · Independent Financial Advisor</div>
            </div>
        </div>
    </div>
</section>

{{-- ── SERVICES ─────────────────────────────────────── --}}
<section id="service">
    <div class="container">
        <div class="text-center" style="margin-bottom:2.5rem;">
            <div class="eyebrow" style="margin-bottom:.8rem;">What you get</div>
            <h2>Three things every IFA needs</h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.25rem;">
            <div class="card card-hover" style="padding:1.75rem;">
                <div class="eyebrow" style="margin-bottom:.8rem;color:var(--gold);">01</div>
                <h3 style="margin-bottom:.6rem;">Weekly risk score</h3>
                <p style="font-size:.875rem;">A single number — 1 to 10 — telling you exactly how much market risk your clients face this week. No jargon, no Greek letters.</p>
            </div>
            <div class="card card-hover" style="padding:1.75rem;">
                <div class="eyebrow" style="margin-bottom:.8rem;color:var(--gold);">02</div>
                <h3 style="margin-bottom:.6rem;">Three plain-English risk flags</h3>
                <p style="font-size:.875rem;">The three biggest things moving markets this week — written the way you'd explain them over tea, not the way a Bloomberg terminal would display them.</p>
            </div>
            <div class="card card-hover" style="padding:1.75rem;">
                <div class="eyebrow" style="margin-bottom:.8rem;color:var(--gold);">03</div>
                <h3 style="margin-bottom:.6rem;">Client conversation script</h3>
                <p style="font-size:.875rem;">The exact words to say when a client calls worried. Every report includes a ready-to-use paragraph you can copy into WhatsApp or read on a call.</p>
            </div>
        </div>
    </div>
</section>

{{-- ── WHEN ADVISORS USE THIS ───────────────────────── --}}
<section style="background:var(--paper-2);">
    <div class="container">
        <div class="text-center" style="margin-bottom:2rem;">
            <h2>Moments this pays for itself</h2>
        </div>
        <div style="display:grid;gap:1rem;max-width:680px;margin-inline:auto;">
            <div class="card" style="padding:1.1rem 1.4rem;display:flex;align-items:flex-start;gap:1rem;">
                <div style="font-family:var(--mono);font-size:.75rem;color:var(--gold);padding-top:2px;flex-shrink:0;">01</div>
                <div>
                    <div style="font-size:.9rem;font-weight:500;margin-bottom:.2rem;">Client calls on a Monday after a market fall</div>
                    <div style="font-size:.82rem;color:var(--ink-3);">You already have the script. You sound calm, prepared, and in control.</div>
                </div>
            </div>
            <div class="card" style="padding:1.1rem 1.4rem;display:flex;align-items:flex-start;gap:1rem;">
                <div style="font-family:var(--mono);font-size:.75rem;color:var(--gold);padding-top:2px;flex-shrink:0;">02</div>
                <div>
                    <div style="font-size:.9rem;font-weight:500;margin-bottom:.2rem;">Client asks: "Is my portfolio too risky right now?"</div>
                    <div style="font-size:.82rem;color:var(--ink-3);">You pull up this week's risk score and walk them through it in plain language.</div>
                </div>
            </div>
            <div class="card" style="padding:1.1rem 1.4rem;display:flex;align-items:flex-start;gap:1rem;">
                <div style="font-family:var(--mono);font-size:.75rem;color:var(--gold);padding-top:2px;flex-shrink:0;">03</div>
                <div>
                    <div style="font-size:.9rem;font-weight:500;margin-bottom:.2rem;">You need to justify a rebalancing decision</div>
                    <div style="font-size:.82rem;color:var(--ink-3);">Forward the report to the client before the meeting. Arrive already credible.</div>
                </div>
            </div>
            <div class="card" style="padding:1.1rem 1.4rem;display:flex;align-items:flex-start;gap:1rem;">
                <div style="font-family:var(--mono);font-size:.75rem;color:var(--gold);padding-top:2px;flex-shrink:0;">04</div>
                <div>
                    <div style="font-size:.9rem;font-weight:500;margin-bottom:.2rem;">A Smallcase app is competing for your client</div>
                    <div style="font-size:.82rem;color:var(--ink-3);">Your personalised human risk advice is something no app can replace. This proves it.</div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── HOW IT WORKS ─────────────────────────────────── --}}
<section id="how-it-works">
    <div class="container">
        <div class="text-center" style="margin-bottom:2.5rem;">
            <div class="eyebrow" style="margin-bottom:.8rem;">Simple as it gets</div>
            <h2>How it works</h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem;max-width:900px;margin-inline:auto;">
            <div style="text-align:center;padding:1.5rem 1rem;">
                <div style="font-family:var(--serif);font-size:3rem;color:var(--paper-3);line-height:1;margin-bottom:.8rem;">1</div>
                <h3 style="margin-bottom:.5rem;">Subscribe</h3>
                <p style="font-size:.85rem;">Choose your plan, enter your WhatsApp number, tell us your portfolio focus. 60 seconds.</p>
            </div>
            <div style="text-align:center;padding:1.5rem 1rem;">
                <div style="font-family:var(--serif);font-size:3rem;color:var(--paper-3);line-height:1;margin-bottom:.8rem;">2</div>
                <h3 style="margin-bottom:.5rem;">We read the market</h3>
                <p style="font-size:.85rem;">Every Sunday evening we analyse NSE, AMFI, RBI data and write your report from scratch.</p>
            </div>
            <div style="text-align:center;padding:1.5rem 1rem;">
                <div style="font-family:var(--serif);font-size:3rem;color:var(--paper-3);line-height:1;margin-bottom:.8rem;">3</div>
                <h3 style="margin-bottom:.5rem;">Monday 9am delivery</h3>
                <p style="font-size:.85rem;">PDF on WhatsApp before your clients start their week. No login, no app, no friction.</p>
            </div>
        </div>
    </div>
</section>

{{-- ── SAMPLE REPORT ────────────────────────────────── --}}
<section id="sample-report" style="background:var(--paper-2);">
    <div class="container">
        <div class="text-center" style="margin-bottom:2rem;">
            <div class="eyebrow" style="margin-bottom:.8rem;">See the real thing</div>
            <h2>Sample report — Week 12, 2026</h2>
            <p style="margin-top:.6rem;font-size:.875rem;">Mid-cap equity portfolios · Plain English edition</p>
        </div>

        <div class="card" style="max-width:700px;margin-inline:auto;overflow:hidden;">
            <div style="background:var(--ink);color:var(--paper);padding:1.5rem 2rem;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;">
                    <span style="font-family:var(--serif);font-size:1.1rem;">Risk<span style="color:var(--gold);">Lens</span></span>
                    <div style="text-align:right;font-family:var(--mono);font-size:.65rem;color:rgba(255,255,255,.4);line-height:1.7;">
                        Week 12 · March 17–21, 2026<br>Mid-cap equity<br>Prepared for: IFA Subscriber
                    </div>
                </div>
                <div style="font-family:var(--serif);font-size:1.6rem;line-height:1.2;">Weekly Risk Intelligence Report</div>
                <div style="font-size:.825rem;color:rgba(255,255,255,.5);margin-top:.3rem;">Mid-cap equity · Plain English edition</div>
            </div>

            <div style="padding:1.75rem 2rem;">
                <div style="background:var(--amber-lt);border:1px solid rgba(176,94,0,.2);border-radius:var(--radius-md);padding:1.1rem 1.4rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1.5rem;">
                    <div style="font-family:var(--serif);font-size:3rem;color:var(--amber);line-height:1;flex-shrink:0;">6.4</div>
                    <div>
                        <div style="font-family:var(--mono);font-size:.65rem;letter-spacing:.1em;text-transform:uppercase;color:var(--amber);margin-bottom:.3rem;">Risk level this week: Moderate-High</div>
                        <div style="font-size:.82rem;color:var(--ink-2);">Up from 5.1 last week. FII selling and currency risk are primary drivers. No immediate portfolio action recommended.</div>
                    </div>
                </div>

                <div style="margin-bottom:1.5rem;">
                    <div class="eyebrow" style="margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:1px solid var(--paper-3);">Risk flags this week</div>
                    <div style="display:grid;grid-template-columns:1fr auto;gap:.8rem;align-items:start;padding:.8rem 0;border-bottom:1px solid var(--paper-2);">
                        <div><div style="font-size:.875rem;font-weight:500;margin-bottom:.2rem;">FII outflows — 3rd consecutive week</div><div style="font-size:.8rem;color:var(--ink-3);line-height:1.5;">Foreign investors net sellers for 3 weeks, ₹8,200 crore out. Typically precedes short-term mid-cap volatility.</div></div>
                        <span class="pill pill-high">HIGH</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr auto;gap:.8rem;align-items:start;padding:.8rem 0;border-bottom:1px solid var(--paper-2);">
                        <div><div style="font-size:.875rem;font-weight:500;margin-bottom:.2rem;">Rupee above 83.5 — import-heavy stocks at risk</div><div style="font-size:.8rem;color:var(--ink-3);line-height:1.5;">Weaker rupee inflates costs for mid-cap manufacturers importing raw materials.</div></div>
                        <span class="pill pill-high">HIGH</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr auto;gap:.8rem;align-items:start;padding:.8rem 0;border-bottom:1px solid var(--paper-2);">
                        <div><div style="font-size:.875rem;font-weight:500;margin-bottom:.2rem;">Mid-cap P/E elevated at 38x vs 31x average</div><div style="font-size:.8rem;color:var(--ink-3);line-height:1.5;">Higher valuation leaves less buffer in volatile markets. Not a sell signal, but watch closely.</div></div>
                        <span class="pill pill-med">MED</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr auto;gap:.8rem;align-items:start;padding:.8rem 0;">
                        <div><div style="font-size:.875rem;font-weight:500;margin-bottom:.2rem;">RBI rate decision — no change expected</div><div style="font-size:.8rem;color:var(--ink-3);line-height:1.5;">MPC meeting outcome broadly priced in. Removes one uncertainty source.</div></div>
                        <span class="pill pill-low">LOW</span>
                    </div>
                </div>

                <div>
                    <div class="eyebrow" style="margin-bottom:.8rem;padding-bottom:.5rem;border-bottom:1px solid var(--paper-3);">What to tell your client this week</div>
                    <div style="background:var(--paper);border-left:3px solid var(--gold);padding:1.1rem 1.3rem;border-radius:0 var(--radius-sm) var(--radius-sm) 0;">
                        <div class="eyebrow" style="margin-bottom:.5rem;">Client conversation script</div>
                        <div style="font-size:.875rem;color:var(--ink-2);line-height:1.75;font-style:italic;">
                            "Markets have seen some selling from foreign investors for three weeks. This is a normal part of market cycles — it does not mean anything is wrong with your portfolio. Your investments are built for the long term. No action needed right now. I will keep you updated."
                        </div>
                    </div>
                </div>

                <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--paper-3);display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-family:var(--serif);font-size:.9rem;color:var(--ink-3);">Risk<span style="color:var(--gold);">Lens</span></span>
                    <span style="font-family:var(--mono);font-size:.65rem;color:var(--ink-3);">Data: NSE, AMFI, RBI · Week 12, 2026</span>
                </div>
            </div>
        </div>

        <div class="text-center mt-xl">
            <a href="#contact" class="btn-primary">Get this every Monday →</a>
            <p style="margin-top:.75rem;font-size:.75rem;color:var(--ink-3);">
                2-week free trial · No credit card · Pro subscribers get reports branded with their name
            </p>
        </div>
    </div>
</section>

{{-- ── PRICING ──────────────────────────────────────── --}}
<section id="pricing">
    <div class="container">
        <div class="text-center" style="margin-bottom:2.5rem;">
            <div class="eyebrow" style="margin-bottom:.8rem;">Simple, honest pricing</div>
            <h2>Flat monthly subscription</h2>
            <p style="margin-top:.6rem;font-size:.875rem;">No per-report counting. No surprise limits. One price, unlimited weekly reports.</p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.25rem;max-width:900px;margin-inline:auto;">

            <div class="card" style="padding:1.75rem;">
                <div class="eyebrow" style="margin-bottom:.8rem;">Starter</div>
                <div style="font-family:var(--serif);font-size:2.6rem;color:var(--ink);line-height:1;">₹799</div>
                <div style="font-size:.8rem;color:var(--ink-3);margin-bottom:1.2rem;">per month · billed monthly</div>
                <div style="font-size:.82rem;color:var(--ink-2);margin-bottom:1.2rem;padding-bottom:1.2rem;border-bottom:1px solid var(--paper-3);">Solo IFA managing clients in one portfolio type</div>
                <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.5rem;">
                    <div style="font-size:.82rem;color:var(--ink-2);">— 1 weekly report (1 asset class)</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— Plain English risk summary</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— Client conversation script</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— WhatsApp PDF delivery</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— Email support</div>
                </div>
                <a href="#contact" class="btn-outline" style="width:100%;display:flex;justify-content:center;">Start free trial</a>
            </div>

            <div class="card" style="padding:1.75rem;border:2px solid var(--ink);position:relative;">
                <div style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--ink);color:var(--paper);font-family:var(--mono);font-size:.65rem;padding:3px 14px;border-radius:2px;letter-spacing:.06em;white-space:nowrap;">MOST POPULAR</div>
                <div class="eyebrow" style="margin-bottom:.8rem;">Pro</div>
                <div style="font-family:var(--serif);font-size:2.6rem;color:var(--ink);line-height:1;">₹1,499</div>
                <div style="font-size:.8rem;color:var(--ink-3);margin-bottom:1.2rem;">per month · billed monthly</div>
                <div style="font-size:.82rem;color:var(--ink-2);margin-bottom:1.2rem;padding-bottom:1.2rem;border-bottom:1px solid var(--paper-3);">Active IFA with multi-asset clients who want to impress</div>
                <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.5rem;">
                    <div style="font-size:.82rem;color:var(--ink-2);">— 3 reports/week (equity + debt + hybrid)</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— Portfolio-level risk scoring</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— Monthly client outlook note</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— Branded PDF with your name & firm</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— Priority response within 24 hrs</div>
                </div>
                <a href="#contact" class="btn-primary" style="width:100%;display:flex;justify-content:center;">Start free trial</a>
            </div>

            <div class="card" style="padding:1.75rem;">
                <div class="eyebrow" style="margin-bottom:.8rem;">Team</div>
                <div style="font-family:var(--serif);font-size:2.6rem;color:var(--ink);line-height:1;">₹3,499</div>
                <div style="font-size:.8rem;color:var(--ink-3);margin-bottom:1.2rem;">per month · billed monthly</div>
                <div style="font-size:.82rem;color:var(--ink-2);margin-bottom:1.2rem;padding-bottom:1.2rem;border-bottom:1px solid var(--paper-3);">Small advisory firm, 3–5 advisors sharing intelligence</div>
                <div style="display:flex;flex-direction:column;gap:.5rem;margin-bottom:1.5rem;">
                    <div style="font-size:.82rem;color:var(--ink-2);">— Everything in Pro</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— Up to 5 advisors</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— Custom risk thresholds per advisor</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— Quarterly 30-min strategy call</div>
                    <div style="font-size:.82rem;color:var(--ink-2);">— Early access to new report types</div>
                </div>
                <a href="#contact" class="btn-outline" style="width:100%;display:flex;justify-content:center;">Start free trial</a>
            </div>

        </div>

        <div style="max-width:620px;margin:1.5rem auto 0;background:var(--paper-2);border:1px solid var(--paper-3);border-radius:var(--radius-lg);padding:1.1rem 1.4rem;font-size:.82rem;color:var(--ink-2);line-height:1.7;">
            <strong style="font-weight:500;color:var(--ink);">Founding member pricing:</strong>
            First 15 subscribers get their price locked forever. Annual billing saves 2 months (pay 10, get 12). Billing via Razorpay. Cancel anytime.
        </div>
    </div>
</section>

{{-- ── CONTACT / SUBSCRIBE ──────────────────────────── --}}
<section id="contact" style="background:var(--paper-2);">
    <div class="container">
        <div class="text-center" style="margin-bottom:2rem;">
            <div class="eyebrow" style="margin-bottom:.8rem;">Get started</div>
            <h2>Start your 2-week free trial</h2>
            <p style="margin-top:.6rem;font-size:.875rem;">No credit card. First report arrives this Monday at 9am on WhatsApp.</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <div class="card" style="max-width:560px;margin-inline:auto;padding:2rem 2.5rem;">
            <form method="POST" action="{{ route('ifa.submit') }}">
                @csrf

                {{-- Plan selector --}}
                <div style="margin-bottom:1.5rem;">
                    <div style="font-size:.78rem;font-weight:500;color:var(--ink-2);margin-bottom:.6rem;letter-spacing:.02em;">Choose your plan</div>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;">
                        <label style="cursor:pointer;">
                            <input type="radio" name="plan" value="starter" style="display:none;">
                            <div class="plan-opt" style="border:1.5px solid var(--paper-3);border-radius:var(--radius-sm);padding:.65rem .5rem;text-align:center;transition:border .15s;">
                                <div style="font-size:.8rem;font-weight:500;">Starter</div>
                                <div style="font-size:.7rem;color:var(--ink-3);">₹799/mo</div>
                            </div>
                        </label>
                        <label style="cursor:pointer;">
                            <input type="radio" name="plan" value="pro" checked style="display:none;">
                            <div style="border:1.5px solid var(--ink);border-radius:var(--radius-sm);padding:.65rem .5rem;text-align:center;background:var(--ink);color:var(--paper);">
                                <div style="font-size:.8rem;font-weight:500;">Pro</div>
                                <div style="font-size:.7rem;opacity:.65;">₹1,499/mo</div>
                            </div>
                        </label>
                        <label style="cursor:pointer;">
                            <input type="radio" name="plan" value="team" style="display:none;">
                            <div class="plan-opt" style="border:1.5px solid var(--paper-3);border-radius:var(--radius-sm);padding:.65rem .5rem;text-align:center;transition:border .15s;">
                                <div style="font-size:.8rem;font-weight:500;">Team</div>
                                <div style="font-size:.7rem;color:var(--ink-3);">₹3,499/mo</div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Full name</label>
                    <input type="text" name="advisor_name" placeholder="Durgesh Kumar" required value="{{ old('advisor_name') }}">
                    @error('advisor_name')<div style="font-size:.75rem;color:var(--red);margin-top:.3rem;">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label>WhatsApp number (for report delivery)</label>
                    <input type="tel" name="whatsapp" placeholder="+91 98765 43210" required value="{{ old('whatsapp') }}">
                    @error('whatsapp')<div style="font-size:.75rem;color:var(--red);margin-top:.3rem;">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label>Email address</label>
                    <input type="email" name="email" placeholder="you@example.com" required value="{{ old('email') }}">
                    @error('email')<div style="font-size:.75rem;color:var(--red);margin-top:.3rem;">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label>Firm name</label>
                    <input type="text" name="firm_name" placeholder="Your firm or individual practice" required value="{{ old('firm_name') }}">
                    @error('firm_name')<div style="font-size:.75rem;color:var(--red);margin-top:.3rem;">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label>Primary portfolio type you manage</label>
                    <select name="portfolio_type">
                        <option value="midcap"   {{ old('portfolio_type') == 'midcap'   ? 'selected' : '' }}>Mid-cap equity</option>
                        <option value="largecap" {{ old('portfolio_type') == 'largecap' ? 'selected' : '' }}>Large-cap equity</option>
                        <option value="debt"     {{ old('portfolio_type') == 'debt'     ? 'selected' : '' }}>Debt / hybrid</option>
                        <option value="multi"    {{ old('portfolio_type') == 'multi'    ? 'selected' : '' }}>Multi-asset (all three)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Anything specific you want in your first report? <span style="font-weight:400;color:var(--ink-3)">(optional)</span></label>
                    <textarea name="main_concern" placeholder="e.g. I have many clients in small-cap funds and they have been asking about the recent fall…">{{ old('main_concern') }}</textarea>
                </div>

                <button type="submit" class="btn-primary" style="width:100%;padding:.9rem;font-size:.9rem;border-radius:var(--radius-sm);margin-top:.5rem;">
                    Start free trial — no card needed
                </button>

                <p style="text-align:center;font-size:.72rem;color:var(--ink-3);margin-top:.8rem;">
                    By continuing you agree to receive weekly WhatsApp messages from RiskLens.
                    Unsubscribe anytime. Not SEBI-registered investment advice.
                </p>
            </form>
        </div>
    </div>
</section> 

@endsection  
