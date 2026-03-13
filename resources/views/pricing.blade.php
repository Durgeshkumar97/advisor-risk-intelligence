@extends('layouts.app')

@section('content')

<section class="pricing-wrapper">

    <h1>Portfolio Risk Intelligence Plans</h1>

    <p class="pricing-intro">
        Structured risk visibility for self-directed investors seeking institutional-grade portfolio clarity.
    </p>

    <div class="pricing-grid">

        <div class="pricing-card">
            <h3>Weekly Risk Snapshot</h3>
            <div class="price">₹999</div>
            <p>Short-term exposure visibility, volatility heatmap, and immediate concentration flags.</p>
        </div>

        <div class="pricing-card">
            <h3>Monthly Risk Intelligence</h3>
            <div class="price">₹1,999</div>
            <p>Full exposure mapping, regime interpretation, and structural risk summary.</p>
        </div>

        <div class="pricing-card">
            <h3>Quarterly Portfolio Audit</h3>
            <div class="price">₹4,999</div>
            <p>Historical exposure tracking, correlation drift, and restructuring zones.</p>
        </div>

        <div class="pricing-card">
            <h3>Half-Year Strategic Review</h3>
            <div class="price">₹8,999</div>
            <p>Multi-regime stress mapping with structural portfolio fragility assessment.</p>
        </div>

        <div class="pricing-card">
            <h3>Annual Portfolio Intelligence</h3>
            <div class="price">₹14,999</div>
            <p>Institutional-style annual risk architecture with long-cycle exposure analysis.</p>
        </div>

    </div>

    <section class="manual-process-box">
        <h3>How Delivery Works</h3>

        <p>
            After selecting a plan, you submit your holdings manually.
            The report is then prepared and delivered with structural analysis,
            exposure mapping, and regime interpretation.
        </p>

        <ul>
            <li>Step 1 — Choose your plan</li>
            <li>Step 2 — Share holdings manually</li>
            <li>Step 3 — Receive risk intelligence report</li>
        </ul>
    </section>

    <div class="pricing-cta">
        <a href="/intake" class="btn-primary">
            Submit Holdings → Get Risk Visibility
        </a>
    </div>

</section>


<style>

.pricing-wrapper {
    max-width: 1100px;
    margin: 80px auto;
    padding: 0 24px;
    text-align: center;
}

.pricing-wrapper h1 {
    font-size: 38px;
    margin-bottom: 16px;
}

.pricing-intro {
    color: var(--text-muted);
    max-width: 700px;
    margin: 0 auto 50px auto;
    line-height: 1.6;
}

.pricing-grid {
    display: grid;
    gap: 24px;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
}

.pricing-card {
    border: 1px solid var(--border-light);
    border-radius: 12px;
    padding: 28px;
    background: var(--card-bg);
}

.pricing-card h3 {
    font-size: 22px;
    margin-bottom: 12px;
}

.price {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 14px;
}

.pricing-card p {
    color: var(--text-muted);
    line-height: 1.6;
}

.manual-process-box {
    max-width: 800px;
    margin: 70px auto;
    padding: 32px;
    border: 1px solid var(--border-light);
    border-radius: 12px;
}

.manual-process-box h3 {
    margin-bottom: 16px;
}

.manual-process-box p {
    color: var(--text-muted);
    line-height: 1.6;
}

.manual-process-box ul {
    list-style: none;
    padding: 0;
    margin-top: 20px;
}

.manual-process-box li {
    margin: 10px 0;
}

.pricing-cta {
    margin-top: 40px;
}

</style>

@endsection 