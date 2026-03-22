@extends('layouts.app')

@section('content')

<nav>
    <div class="container nav-wrapper">
        <strong>Portfolio Risk Intelligence</strong>

        <ul>
            <li><a href="#home">Home</a></li>
            <li><a href="#service">Service</a></li>
            <li><a href="#pricing">Pricing</a></li>
            <li><a href="#how-it-works">How It Works</a></li>
            <li><a href="#contact" style="font-weight:600;">Request Report</a></li>
        </ul>

        <button id="themeBtn" class="theme-toggle">🌙</button>
    </div>
</nav>

<!-- HERO -->
<section id="home">
    <div class="container" style="display:grid;grid-template-columns:1.2fr 1fr;gap:40px;align-items:center;">

        <div>
            <h1>Portfolio Risk Intelligence for Independent Financial Advisors</h1>

            <p style="margin-top:18px;max-width:520px;">
                White-label structural portfolio diagnostics built for advisors managing client portfolios manually.
            </p>

            <p style="margin-top:10px;font-size:14px;color:var(--text-muted);">
                Built for advisors who need clarity, not market noise.
            </p>

            <div style="margin-top:28px;">
                <a href="#contact" class="btn-primary">Request Sample Report</a>
            </div>

            <div style="margin-top:8px;font-size:12px;color:var(--text-muted);">
                Used by independent advisors for client reporting
            </div>

            <div style="margin-top:10px;font-size:12px;color:var(--text-muted);">
                Designed for advisors managing ₹5L–₹25L portfolios • Scalable for larger books
            </div>
        </div>

        <div class="card premium-card" style="
            padding:26px;
            border:2px solid var(--border-light);
            background:linear-gradient(to bottom, var(--bg-card), transparent);
        ">
            <div style="font-weight:700;margin-bottom:14px;">Sample Intelligence Snapshot</div>

            <div style="display:grid;gap:12px;">
                <div class="metric-box">Portfolio Risk Score: 72 / 100</div>
                <div class="metric-box">Sector Concentration: Elevated</div>
                <div class="metric-box">Structural Risk Flags: 3 detected</div>
            </div>
        </div>

    </div> 
</section> 

<!-- TRUST STRIP -->
<section>
    <div class="container">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;text-align:center;">
            <div>🔒 Confidential portfolio handling</div>
            <div>⚖️ No investment advisory output</div>
            <div>📊 Pure structural diagnostics</div>
        </div>
    </div> 
</section>

<!-- SERVICES -->
<section id="service">
    <div class="container" style="text-align:center;">
        <h2>Services</h2>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:22px;margin-top:28px;max-width:1000px;margin-left:auto;margin-right:auto;">
            <div class="card premium-card" style="padding:28px;">
                <h3>Portfolio Risk Score</h3>
                <p>Clear structural diagnostics across manually managed portfolios.</p>
            </div>

            <div class="card premium-card" style="padding:28px;">
                <h3>Concentration Detection</h3>
                <p>Identify hidden exposure clusters and concentration risks.</p>
            </div>

            <div class="card premium-card" style="padding:28px;">
                <h3>White-label Reports</h3>
                <p>Advisor-ready branded reporting infrastructure.</p>
            </div>
        </div>
    </div> 
</section>

<!-- USE CASES -->
<section>
    <div class="container">
        <h2 style="text-align:center;">When Advisors Use This</h2>

        <div style="display:grid;gap:16px;max-width:700px;margin:20px auto;">
            <div class="card" style="padding:18px;">Client asks: “Is my portfolio too risky?”</div>
            <div class="card" style="padding:18px;">You need a structured review before rebalancing</div>
            <div class="card" style="padding:18px;">You want to justify decisions with data-backed insight</div>
        </div>
    </div> 
</section> 

<!-- HOW IT WORKS -->
<section id="how-it-works">
    <div class="container">
        <h2 style="text-align:center;">How It Works</h2>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px;">
            <div class="card" style="padding:20px;text-align:center;">1. Upload Portfolio</div>
            <div class="card" style="padding:20px;text-align:center;">2. Structural Risk Scan</div>
            <div class="card" style="padding:20px;text-align:center;">3. Advisor Report Delivered</div>
        </div>
    </div>  
</section> 

<!-- WHAT YOU RECEIVE -->
<section>
    <div class="container">
        <h2 style="text-align:center;">What You Will Receive</h2>

        <div style="display:grid;gap:16px;max-width:700px;margin:20px auto;">
            <div class="card" style="padding:18px;">✔ Portfolio Risk Score with explanation</div>
            <div class="card" style="padding:18px;">✔ Concentration & exposure analysis</div>
            <div class="card" style="padding:18px;">✔ Structured client-ready PDF report</div>
        </div>
    </div> 
</section> 

<!-- REPORT PREVIEW -->
<section>
    <div class="container">
        <h2 style="text-align:center;">Sample Report Preview</h2>

        <div class="card premium-card" style="max-width:900px;margin:20px auto;padding:26px;">
            <div style="display:grid;gap:18px;">

                <div style="font-weight:700;font-size:18px;">
                    Portfolio Risk Intelligence Report
                </div>

                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;">
                    <div class="metric-box">Risk Score: 72 / 100</div>
                    <div class="metric-box">Concentration: Elevated</div>
                    <div class="metric-box">Volatility Band: Medium</div>
                </div>

                <div style="padding:16px;border:1px solid var(--border-light);border-radius:12px;">
                    Structural Observations:
                    <ul style="margin-top:10px;padding-left:18px;">
                        <li>High sector concentration in financials</li>
                        <li>Limited diversification across asset classes</li>
                        <li>Exposure overlap detected across holdings</li>
                    </ul>
                </div>

            </div>
        </div>

        <div style="text-align:center;margin-top:24px;">
            <a href="#contact" class="btn-primary">Request Full Sample Report</a>

            <div style="margin-top:8px;font-size:12px;color:var(--text-muted);">
                Delivered within 24–48 hours • Fully white-label, client-ready report
            </div>

            <div style="margin-top:6px;font-size:12px;color:var(--text-muted);">
                Currently onboarding a limited number of advisors each week
            </div>
        </div>
    </div>
</section> 

<!-- PRICING -->
<section id="pricing">
    <div class="container">

        <h2 style="text-align:center;">Pricing</h2>

        <p style="text-align:center;margin-bottom:20px;color:var(--text-muted);">
            Simple pricing designed for growing independent advisors
        </p>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">

            <div class="card" style="padding:28px;text-align:center;">
                <h3>Free Trial</h3>
                <div style="font-size:28px;font-weight:700;">₹0</div>
                <ul style="margin-top:15px;text-align:left;">
                    <li>✔ 2 reports</li>
                    <li>✔ Standard delivery</li>
                </ul>
                <a href="#contact" class="btn-primary" style="margin-top:20px;">Start Free</a>
            </div>

            <div class="card" style="padding:28px;text-align:center;">
                <h3>Starter</h3>
                <div style="font-size:28px;font-weight:700;">₹999/mo</div>
                <ul style="margin-top:15px;text-align:left;">
                    <li>✔ 5 reports</li>
                    <li>✔ Email support</li>
                </ul>
                <a href="#contact" class="btn-primary" style="margin-top:20px;">Choose</a>
            </div>

            <div class="card" style="padding:28px;text-align:center;border:2px solid var(--accent);">
                <h3>Growth</h3>
                <div style="font-size:28px;font-weight:700;">₹2,499/mo</div>
                <ul style="margin-top:15px;text-align:left;">
                    <li>✔ 20 reports</li>
                    <li>✔ Priority delivery</li>
                </ul>
                <a href="#contact" class="btn-primary" style="margin-top:20px;">Best Value</a>
            </div>

            <div class="card" style="padding:28px;text-align:center;">
                <h3>Pro</h3>
                <div style="font-size:28px;font-weight:700;">₹4,999/mo</div>
                <ul style="margin-top:15px;text-align:left;">
                    <li>✔ Unlimited</li>
                    <li>✔ Fastest delivery</li>
                </ul>
                <a href="#contact" class="btn-primary" style="margin-top:20px;">Upgrade</a>
            </div>

        </div>

        <div style="text-align:center;margin-top:20px;font-size:13px;color:var(--text-muted);">
            Free trial available • Cancel anytime
        </div>

    </div>
</section> 

<!-- BEFORE VS AFTER -->
<section>
    <div class="container">
        <h2 style="text-align:center;">Before vs After</h2>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px;">
            <div class="card" style="padding:20px;">
                <strong>Before</strong>
                <ul>
                    <li>No structured risk visibility</li>
                    <li>Manual analysis</li>
                </ul>
            </div>

            <div class="card" style="padding:20px;">
                <strong>After</strong>
                <ul>
                    <li>Clear risk scoring</li>
                    <li>Client-ready reporting</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- CONTACT -->
<section id="contact">
    <div class="container">
        <h2 style="text-align:center;">Advisor Submission</h2>

        @if(session('success'))
            <div class="success-box">
                Submission received. You’ll receive the report within 24–48 hours.
            </div>
        @endif

        <div style="text-align:center;margin-bottom:20px;">
            Get your first portfolio analyzed within 24–48 hours
        </div>

        <div class="card form-box">
            <form method="POST" action="{{ route('ifa.submit') }}" enctype="multipart/form-data">
                @csrf

                <div class="form-grid">
                    <input type="text" name="advisor_name" placeholder="Advisor Name" required>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="text" name="firm_name" placeholder="Firm Name" required>
                    <input type="text" name="clients_managed" placeholder="Clients Managed" required>
                    <textarea name="main_concern" rows="4" placeholder="Main Concern"></textarea>
                    <input type="file" name="portfolio_file" required>

                    <button type="submit">Submit Request</button>

                    <div style="text-align:center;font-size:12px;color:var(--text-muted);margin-top:8px;">
                        Takes less than 60 seconds • No obligation
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<footer>
    Risk Intelligence — Not Investment Advisory
</footer>

@endsection

