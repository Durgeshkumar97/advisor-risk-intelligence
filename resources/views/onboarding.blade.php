<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Welcome to RiskSignal — Setup</title>

    <style>
        :root {
            --bg: #020817;
            --panel: #0f172a;
            --panel2: #111c34;
            --line: rgba(255,255,255,.08);
            --text: #ffffff;
            --muted: #94a3b8;
            --soft: #64748b;
            --gold: #facc15;
            --green: #22c55e;
            --blue: #2563eb;
            --blue-hover: #1d4ed8;
            --purple: #a78bfa;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            min-height: 100%;
            width: 100%;
        }

        body {
            font-family: Inter, Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at top right, #12203f 0%, transparent 30%),
                radial-gradient(circle at bottom left, #0e1a2f 0%, transparent 25%),
                var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 20px 60px;
        }

        /* ── PROGRESS INDICATOR ── */
        .progress-bar {
            width: 100%;
            max-width: 520px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 36px;
        }

        .progress-step {
            flex: 1;
            height: 3px;
            border-radius: 999px;
            background: var(--line);
        }

        .progress-step.done { background: var(--green); }
        .progress-step.active { background: var(--gold); }

        .progress-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--muted);
            white-space: nowrap;
        }

        /* ── MAIN WRAPPER ── */
        .wrapper {
            width: 100%;
            max-width: 520px;
        }

        /* ── BADGE ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--gold);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.6px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .badge-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 0 0 rgba(34,197,94,.6);
            animation: pulse 1.8s infinite;
        }

        @keyframes pulse {
            0%   { box-shadow: 0 0 0 0 rgba(34,197,94,.6); }
            70%  { box-shadow: 0 0 0 10px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }

        /* ── HEADING ── */
        h1 {
            font-size: 40px;
            font-weight: 900;
            line-height: 1.05;
            letter-spacing: -.02em;
            margin-bottom: 10px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 32px;
        }

        .name-highlight {
            color: var(--gold);
        }

        /* ── CARD ── */
        .card {
            background: rgba(15,23,42,.96);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 36px;
            box-shadow:
                0 25px 60px rgba(0,0,0,.4),
                inset 0 1px 0 rgba(255,255,255,.03);
            backdrop-filter: blur(10px);
        }

        /* ── SECTION LABEL ── */
        .section-label {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--soft);
            margin-bottom: 16px;
        }

        /* ── OPTION CARDS ── */
        .options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 28px;
        }

        .option-label {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 18px 20px;
            border-radius: 14px;
            border: 1.5px solid var(--line);
            background: rgba(255,255,255,.02);
            cursor: pointer;
            transition: all .2s ease;
            position: relative;
        }

        .option-label:hover {
            border-color: rgba(255,255,255,.18);
            background: rgba(255,255,255,.04);
        }

        .option-label input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .option-label input[type="radio"]:checked ~ .option-content .option-title {
            color: #fff;
        }

        .option-label.selected {
            border-color: var(--gold);
            background: rgba(250,204,21,.06);
        }

        .option-icon {
            font-size: 28px;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .option-content {
            flex: 1;
        }

        .option-title {
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 4px;
            color: var(--text);
        }

        .option-desc {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }

        .option-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-left: 6px;
        }

        .badge-recommended {
            background: rgba(34,197,94,.15);
            color: #22c55e;
        }

        .badge-coming-soon {
            background: rgba(99,102,241,.15);
            color: #a5b4fc;
        }

        /* ── RADIO CIRCLE ── */
        .radio-circle {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--line);
            flex-shrink: 0;
            margin-top: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: .2s ease;
        }

        .option-label.selected .radio-circle {
            border-color: var(--gold);
            background: var(--gold);
        }

        .radio-circle::after {
            content: '';
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #000;
            opacity: 0;
            transition: .15s ease;
        }

        .option-label.selected .radio-circle::after {
            opacity: 1;
        }

        /* ── WHAT TO EXPECT ── */
        .expect-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 28px;
        }

        .expect-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            background: rgba(255,255,255,.02);
            border: 1px solid var(--line);
            border-radius: 10px;
        }

        .expect-icon { font-size: 18px; flex-shrink: 0; }

        .expect-text {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.5;
        }

        .expect-text strong {
            display: block;
            color: var(--text);
            font-size: 13px;
            margin-bottom: 2px;
        }

        /* ── ERROR ── */
        .error-msg {
            background: rgba(239,68,68,.12);
            border: 1px solid rgba(239,68,68,.22);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 13px;
            font-weight: 600;
        }

        /* ── BUTTON ── */
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(180deg, var(--blue), var(--blue-hover));
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: .22s ease;
            box-shadow: 0 12px 30px rgba(37,99,235,.25);
            letter-spacing: .01em;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            filter: brightness(1.07);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .btn-submit:disabled {
            opacity: .55;
            cursor: not-allowed;
            transform: none;
        }

        /* ── FOOTER NOTE ── */
        .footer-note {
            margin-top: 20px;
            text-align: center;
            color: var(--soft);
            font-size: 12px;
            line-height: 1.6;
        }

        /* ── DIVIDER ── */
        .divider {
            height: 1px;
            background: var(--line);
            margin: 24px 0;
        }

        @media (max-width: 540px) {
            h1 { font-size: 30px; }
            .card { padding: 24px 20px; border-radius: 18px; }
            .expect-grid { grid-template-columns: 1fr; }
            .option-icon { font-size: 22px; }
        }
    </style>
</head>

<body>

    {{-- PROGRESS BAR --}}
    <div class="progress-bar">
        <div class="progress-step done"></div>
        <div class="progress-step done"></div>
        <div class="progress-step active"></div>
        <span class="progress-label">Step 3 of 3 — Delivery</span>
    </div>

    <div class="wrapper">

        <div class="badge">
            <span class="badge-dot"></span>
            Subscription Active
        </div>

        <h1>You're in, <span class="name-highlight">{{ Auth::user()->name }}</span>.</h1>

        <p class="subtitle">
            One last step — choose how you'd like to receive your<br>
            daily portfolio risk signals from RiskSignal.
        </p>

        <div class="card">

            @if($errors->any())
                <div class="error-msg">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('onboarding.store') }}" id="onboarding-form">
                @csrf

                <div class="section-label">How should we deliver your daily report?</div>

                <div class="options">

                    {{-- EMAIL --}}
                    <label
                        class="option-label {{ old('access_type', 'email') === 'email' ? 'selected' : '' }}"
                        id="label-email"
                    >
                        <input type="radio" name="access_type" value="email"
                            {{ old('access_type', 'email') === 'email' ? 'checked' : '' }}
                            id="opt-email">
                        <div class="option-icon">📧</div>
                        <div class="option-content">
                            <div class="option-title">
                                Email
                                <span class="option-badge badge-recommended">Recommended</span>
                            </div>
                            <div class="option-desc">
                                Daily risk report delivered to <strong style="color:var(--text);">{{ Auth::user()->email }}</strong>
                                every morning. Includes risk score, flags &amp; client script.
                            </div>
                        </div>
                        <div class="radio-circle"></div>
                    </label>

                </div>

                <div class="divider"></div>

                <div class="section-label" style="margin-bottom:14px;">What you'll receive every day</div>

                <div class="expect-grid">
                    <div class="expect-item">
                        <div class="expect-icon">📊</div>
                        <div class="expect-text">
                            <strong>Risk Score</strong>
                            Portfolio volatility score 0–100
                        </div>
                    </div>
                    <div class="expect-item">
                        <div class="expect-icon">🚨</div>
                        <div class="expect-text">
                            <strong>Risk Flags</strong>
                            Top 3 portfolio risk signals
                        </div>
                    </div>
                    <div class="expect-item">
                        <div class="expect-icon">💬</div>
                        <div class="expect-text">
                            <strong>Client Script</strong>
                            Ready-to-send client message
                        </div>
                    </div>
                    <div class="expect-item">
                        <div class="expect-icon">📅</div>
                        <div class="expect-text">
                            <strong>Daily at 8:00 AM</strong>
                            Before markets open
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <button type="submit" class="btn-submit" id="submit-btn">
                    Go to My Dashboard →
                </button>

            </form>

        </div>

        <div class="footer-note">
            Questions? Reply to your welcome email — we respond within 2 hours.
        </div>

    </div>

    <script>
        document.getElementById('onboarding-form').addEventListener('submit', function () {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            btn.textContent = 'Setting up your dashboard…';
        });
    </script>

</body>
</html>
