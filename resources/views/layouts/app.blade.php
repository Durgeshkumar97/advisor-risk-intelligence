<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RiskLens — Risk Intelligence for Independent Advisors</title>
    <meta name="description" content="Weekly plain-English risk intelligence reports for IFAs managing ₹5L–₹25L client portfolios. Delivered every Monday on WhatsApp.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:ital,wght@0,300;0,400;0,500;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        /* ── TOKENS ─────────────────────────────── */
        :root {
            --ink:        #0f0e0c;
            --ink-2:      #3a3830;
            --ink-3:      #8a8880;
            --paper:      #f5f2eb;
            --paper-2:    #ede9df;
            --paper-3:    #e2ddd2;
            --gold:       #b8960c;
            --gold-lt:    #f5e9a0;
            --green:      #1a6b3c;
            --green-lt:   #e4f2ea;
            --red:        #b83232;
            --red-lt:     #fae8e6;
            --amber:      #b05e00;
            --amber-lt:   #fdf0dc;
            --blue:       #1a3a6b;
            --blue-lt:    #e4ecf7;

            --serif:  'Instrument Serif', Georgia, serif;
            --sans:   'DM Sans', system-ui, sans-serif;
            --mono:   'JetBrains Mono', monospace;

            --radius-sm:  3px;
            --radius-md:  6px;
            --radius-lg:  10px;

            --shadow-sm:  0 1px 4px rgba(0,0,0,.06);
            --shadow-md:  0 4px 20px rgba(0,0,0,.08);
            --shadow-lg:  0 8px 40px rgba(0,0,0,.10);
        }

        /* ── RESET ───────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--sans);
            background: var(--paper);
            color: var(--ink);
            line-height: 1.6;
            padding-top: 72px;
            font-size: 16px;
        }

        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        img { display: block; max-width: 100%; }

        /* ── TYPOGRAPHY ──────────────────────────── */
        h1 {
            font-family: var(--serif);
            font-size: clamp(2.4rem, 5vw, 3.8rem);
            line-height: 1.06;
            letter-spacing: -.02em;
            font-weight: 400;
        }

        h2 {
            font-family: var(--serif);
            font-size: clamp(1.7rem, 3vw, 2.4rem);
            font-weight: 400;
            letter-spacing: -.015em;
            line-height: 1.15;
        }

        h3 {
            font-size: 1rem;
            font-weight: 500;
            letter-spacing: .01em;
        }

        p { color: var(--ink-2); line-height: 1.75; }

        .eyebrow {
            font-family: var(--mono);
            font-size: .72rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--ink-3);
        }

        /* ── LAYOUT ──────────────────────────────── */
        .container {
            width: min(90%, 1160px);
            margin-inline: auto;
        }

        section { padding: 80px 0; }

        section + section {
            border-top: 1px solid var(--paper-3);
        }

        /* ── NAV ─────────────────────────────────── */
        nav {
            position: fixed;
            inset: 0 0 auto 0;
            background: rgba(245,242,235,.94);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--paper-3);
            z-index: 900;
        }

        .nav-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.1rem 0;
        }

        .nav-logo {
            font-family: var(--serif);
            font-size: 1.25rem;
            color: var(--ink);
        }

        .nav-logo span { color: var(--gold); }

        nav ul {
            display: flex;
            gap: 2rem;
        }

        nav ul li a {
            font-size: .875rem;
            color: var(--ink-2);
            transition: color .15s;
        }

        nav ul li a:hover { color: var(--ink); }

        /* ── BUTTONS ─────────────────────────────── */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .7rem 1.6rem;
            border-radius: var(--radius-sm);
            background: var(--ink);
            color: var(--paper);
            font-size: .875rem;
            font-weight: 500;
            font-family: var(--sans);
            border: none;
            cursor: pointer;
            transition: background .2s, transform .15s;
            letter-spacing: .01em;
        }

        .btn-primary:hover {
            background: var(--ink-2);
            transform: translateY(-1px);
        }

        .btn-primary:active { transform: scale(.98); }

        .btn-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: .7rem 1.6rem;
            border-radius: var(--radius-sm);
            background: transparent;
            color: var(--ink);
            font-size: .875rem;
            font-weight: 500;
            font-family: var(--sans);
            border: 1.5px solid var(--ink);
            cursor: pointer;
            transition: all .2s;
            letter-spacing: .01em;
        }

        .btn-outline:hover { background: var(--ink); color: var(--paper); }

        /* ── CARDS ───────────────────────────────── */
        .card {
            background: white;
            border: 1px solid var(--paper-3);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .card-hover {
            transition: box-shadow .2s, transform .2s;
        }

        .card-hover:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        /* ── METRIC BOX ──────────────────────────── */
        .metric-box {
            background: var(--paper-2);
            border: 1px solid var(--paper-3);
            border-radius: var(--radius-md);
            padding: .75rem 1rem;
            font-size: .875rem;
            color: var(--ink-2);
        }

        /* ── PILLS / BADGES ──────────────────────── */
        .pill {
            display: inline-block;
            font-family: var(--mono);
            font-size: .65rem;
            padding: 3px 9px;
            border-radius: 2px;
            letter-spacing: .05em;
            font-weight: 500;
        }

        .pill-high   { background: var(--red-lt);   color: var(--red);   }
        .pill-med    { background: var(--amber-lt);  color: var(--amber); }
        .pill-low    { background: var(--green-lt);  color: var(--green); }
        .pill-gold   { background: var(--gold-lt);   color: var(--gold);  }
        .pill-blue   { background: var(--blue-lt);   color: var(--blue);  }

        /* ── FORMS ───────────────────────────────── */
        .form-group { margin-bottom: 1.1rem; }

        .form-group label {
            display: block;
            font-size: .78rem;
            font-weight: 500;
            color: var(--ink-2);
            margin-bottom: .4rem;
            letter-spacing: .02em;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        textarea,
        select {
            width: 100%;
            padding: .7rem 1rem;
            border: 1px solid var(--paper-3);
            border-radius: var(--radius-sm);
            background: var(--paper);
            color: var(--ink);
            font-family: var(--sans);
            font-size: .9rem;
            transition: border .15s, background .15s;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--ink-2);
            background: white;
        }

        textarea { resize: vertical; min-height: 110px; }

        /* ── ALERTS ──────────────────────────────── */
        .alert {
            max-width: 680px;
            margin: 0 auto 1.5rem;
            padding: 1rem 1.25rem;
            border-radius: var(--radius-md);
            font-size: .875rem;
            font-weight: 500;
            text-align: center;
        }

        .alert-success { background: var(--green-lt); color: var(--green); }
        .alert-error   { background: var(--red-lt);   color: var(--red);   }

        /* ── FOOTER ──────────────────────────────── */
        footer {
            background: var(--ink);
            color: rgba(255,255,255,.45);
            text-align: center;
            padding: 2.5rem 1rem;
            font-size: .8rem;
            font-family: var(--mono);
            letter-spacing: .04em;
        }

        footer strong { color: rgba(255,255,255,.7); font-weight: 500; }

        /* ── UTILITIES ───────────────────────────── */
        .text-center { text-align: center; }
        .text-muted  { color: var(--ink-3); }
        .mt-sm { margin-top: .5rem; }
        .mt-md { margin-top: 1rem; }
        .mt-lg { margin-top: 1.5rem; }
        .mt-xl { margin-top: 2.5rem; }

        /* ── RESPONSIVE ──────────────────────────── */
        @media (max-width: 768px) {
            nav ul { display: none; }
            .nav-inner { padding: .9rem 0; }
            h1 { font-size: 2.2rem; }
            section { padding: 56px 0; }
        }
    </style>
</head>

<body>

@yield('content')

<footer>
    <strong>RiskLens</strong> &nbsp;·&nbsp; Risk intelligence, not investment advice &nbsp;·&nbsp;
    For informational purposes only. Not SEBI-registered investment advisory. &nbsp;·&nbsp;
    © {{ date('Y') }} RiskLens
</footer>

</body>
</html>
 