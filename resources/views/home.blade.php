@extends('layouts.app')

@section('content')

<!-- HERO SECTION -->
<section class="hero">

    <!-- LEFT CONTENT -->
    <div class="hero-left">

        <h1>
            You’re not as diversified as you think.
        </h1>

        <p>
            Get institutional-grade visibility into your portfolio risk —
            sector exposure, correlation clusters, and regime sensitivity.
        </p>

        <!-- CTA BUTTONS -->
        <div class="hero-buttons">

            <a href="/intake" class="btn-primary">
                Get Risk Scan
            </a>

            <a href="/investor-profile" class="btn-secondary">
                Assess Your Risk Archetype
            </a>

            <a href="/sample-report" class="btn-secondary">
                View Sample Report
            </a>

        </div>

        <!-- TRUST NOTE -->
        <p class="hero-subtrust">
            No advisory. No stock tips. Structural risk intelligence only.
        </p>

        <!-- CREDIBILITY STRIP -->
        <div class="credibility-strip">
            <div>Sector Risk Models</div>
            <div>Correlation Mapping</div>
            <div>Regime Analysis</div>
            <div>Institutional Frameworks</div>
        </div>

    </div>

    <!-- RIGHT IMAGE -->
    <div class="hero-right">

        <div class="dashboard-wrapper">
            <img
                src="{{ asset('images/Hero_page_Image.png') }}"
                alt="Portfolio Risk Dashboard Preview"
            >
        </div>

    </div>

</section>


<!-- TRUST STRIP -->
<section class="trust-strip">

    <div class="trust-item">
        Institutional-style portfolio risk framing
    </div>

    <div class="trust-item">
        Manual analytical delivery
    </div>

    <div class="trust-item">
        Built for self-directed investors
    </div>

</section>


<!-- PROBLEM SECTION -->
<section class="problem">

    <div class="problem-container">

        <h2>Hidden Risk Exists</h2>

        <p>
            Most traders assume diversification equals safety.
            But capital often clusters across sectors, themes, and liquidity regimes.
            Hidden exposure creates structural drawdowns.
        </p>

    </div>

</section>

<style>

.problem {
    padding: 100px 20px 80px;
    text-align: center;
}

.problem-container {
    max-width: 900px;
    margin: auto;
}

.problem h2 {
    font-size: 54px;
    margin-bottom: 28px;
}

.problem p {
    font-size: 20px;
    line-height: 1.8;
    color: var(--text-muted);
}

</style>

<!-- PAGE STYLING -->
<style>

/* HERO TRUST NOTE */

.hero-subtrust {
    margin-top: 18px;
    font-size: 14px;
    color: var(--text-muted);
}


/* TRUST STRIP */
.trust-strip {
    display: flex;
    justify-content: center;
    gap: 60px;
    padding: 36px 20px;
    border-top: 1px solid var(--border-light);
    border-bottom: 1px solid var(--border-light);
    margin-top: 40px;
    flex-wrap: wrap;
}

.trust-item {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-muted);
} 

/* CREDIBILITY STRIP */ 
.credibility-strip {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 28px;
    font-size: 14px;
    color: var(--text-muted);
}


/* PROBLEM SECTION */

.problem {
    padding: 80px 24px;
}

.problem-container {
    max-width: 900px;
    margin: auto;
    text-align: center;
}

.problem h2 {
    font-size: 32px;
    margin-bottom: 20px;
}

.problem p {
    font-size: 18px;
    color: var(--text-muted);
    line-height: 1.7;
}


/* MOBILE */

@media (max-width: 768px) {

    .credibility-strip {
        justify-content: center;
    }

    .trust-strip {
        flex-direction: column;
        text-align: center;
    }

}

</style>

@endsection  

