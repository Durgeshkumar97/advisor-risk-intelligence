<?php

/*
|--------------------------------------------------------------------------
| FUND CLASSIFICATION
|--------------------------------------------------------------------------
|
| How a mutual fund or ETF is classified and scored once its AMFI scheme is
| known (App\Services\RiskEngine\FundClassifier), and how a fund holding
| that matched no AMFI scheme is classified from its name.
|
|   categories  AMFI category header → class slug (exact lookup, no regex)
|   classes     slug → asset class, base risk score, equity share
|   name_rules  ordered name patterns, for the AMFI categories that do not
|               say what the fund holds (index funds, "other" ETFs, FoFs)
|               and for fund holdings with no confident AMFI match
|
| Sources
| ───────
|   [SEBI-2026]  SEBI circular HO/24/13/15(2)2026-IMD-RAC4/I/5764/2026,
|                26 Feb 2026, "Categorization and Rationalization of Mutual
|                Fund Schemes" (supersedes clause 2.6 of the 27 Jun 2024
|                Master Circular, which consolidated SEBI/HO/IMD/DF3/CIR/P/
|                2017/114 of 6 Oct 2017). Section C = hybrid schemes,
|                Annexure B = life cycle funds.
|   [AMFI]       NAVAll.txt category headers as published, verified 24 Oct
|                2026. AMCs are part-way through moving to the 2026 labels,
|                so both the old ("Debt Scheme - Short Duration Fund") and
|                the new ("Income/Debt Oriented Schemes - Short Term Fund")
|                spelling of a category appear and map to the same slug.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | CLASSES  (slug → asset class, base score 0–100, equity share 0–1)
    |--------------------------------------------------------------------------
    |
    | asset_class   equity | debt | hybrid | commodity | other
    | score         the holding's risk score (replaces the name-keyword score)
    | equity_share  fraction of the holding's value counted in the portfolio's
    |               equity ratio: 1 for equity, 0 for debt/commodity/other,
    |               SEBI's range midpoint for hybrids.
    |
    | SCORES are ordinal judgements, not measurements. Ordering:
    |   debt    rises with interest-rate sensitivity (overnight → long
    |           duration), then credit risk; credit risk is the highest debt
    |           class because its loss mode is default, not rate moves.
    |   hybrid  rises with equity share; arbitrage is hedged and sits with
    |           the shortest debt.
    |   equity  large < large & mid < diversified (flexi/multi/value/…) <
    |           mid < thematic < sectoral < small. Sectoral sits above
    |           thematic: one sector is narrower than one theme.
    |   No diversified fund scores above a single stock's default (65,
    |   AssetRiskScorer::BASE_SCORES['stock']).
    |
    */

    'classes' => [
        // Debt
        'overnight' => ['asset_class' => 'debt', 'score' => 5, 'equity_share' => 0.0],
        'liquid' => ['asset_class' => 'debt', 'score' => 8, 'equity_share' => 0.0],
        'money_market' => ['asset_class' => 'debt', 'score' => 10, 'equity_share' => 0.0],
        'ultra_short' => ['asset_class' => 'debt', 'score' => 12, 'equity_share' => 0.0],
        'low_duration' => ['asset_class' => 'debt', 'score' => 15, 'equity_share' => 0.0],
        'floater' => ['asset_class' => 'debt', 'score' => 18, 'equity_share' => 0.0],
        'short_duration' => ['asset_class' => 'debt', 'score' => 20, 'equity_share' => 0.0],
        'banking_psu' => ['asset_class' => 'debt', 'score' => 20, 'equity_share' => 0.0],
        'corporate_bond' => ['asset_class' => 'debt', 'score' => 22, 'equity_share' => 0.0],
        'debt_index' => ['asset_class' => 'debt', 'score' => 22, 'equity_share' => 0.0],   // target-maturity / SDL / G-Sec index funds & ETFs
        'fmp' => ['asset_class' => 'debt', 'score' => 22, 'equity_share' => 0.0],          // close-ended fixed maturity / fixed term plans
        'gilt' => ['asset_class' => 'debt', 'score' => 28, 'equity_share' => 0.0],
        'gilt_10y' => ['asset_class' => 'debt', 'score' => 28, 'equity_share' => 0.0],
        'medium_duration' => ['asset_class' => 'debt', 'score' => 28, 'equity_share' => 0.0],
        'debt_other' => ['asset_class' => 'debt', 'score' => 28, 'equity_share' => 0.0],   // legacy "Income", interval funds
        'dynamic_bond' => ['asset_class' => 'debt', 'score' => 30, 'equity_share' => 0.0],
        'debt_sectoral' => ['asset_class' => 'debt', 'score' => 30, 'equity_share' => 0.0],
        'medium_long' => ['asset_class' => 'debt', 'score' => 32, 'equity_share' => 0.0],
        'long_duration' => ['asset_class' => 'debt', 'score' => 35, 'equity_share' => 0.0],
        'credit_risk' => ['asset_class' => 'debt', 'score' => 40, 'equity_share' => 0.0],

        // Hybrid — equity_share is the midpoint of SEBI's equity range where one exists.
        //   arbitrage           0      Hedged: SEBI-2026 C.6 requires ≥65% in equity, but as arbitrage
        //                              positions, so the net equity exposure is ~0. (Your decision.)
        //   conservative        0.175  SEBI-2026 C.1: equity 10%–25%.
        //   equity_savings      0.275  SEBI-2026 C.7: net equity exposure 15%–40% (gross equity incl.
        //                              arbitrage ≥65%; the net band is what carries equity risk).
        //   balanced_hybrid     0.50   SEBI-2026 C.2: equity 40%–60%.
        //   aggressive          0.725  SEBI-2026 C.3: equity 65%–80%.
        //   balanced_advantage  0.50   ASSUMPTION — SEBI-2026 C.4 "managed dynamically", no range.
        //   multi_asset         0.50   ASSUMPTION — SEBI-2026 C.5: ≥10% in each of ≥3 asset classes,
        //                              no equity range.
        //   life_cycle          0.50   ASSUMPTION — SEBI-2026 Annexure B does give equity bands, but
        //                              they move with years to maturity (65–95% at 15+ years down to
        //                              5–20% under 1 year); a fixed figure is used until the maturity
        //                              year in the scheme name is read.
        //   solution            0.50   ASSUMPTION — retirement / children's funds: no equity range in
        //                              SEBI-2026 (the category is discontinued, C 2.6.3.16).
        //   hybrid_other        0.50   ASSUMPTION — hybrid index funds / ETFs / FoFs and fund names
        //                              that say "hybrid" without saying which kind: no single range.
        'arbitrage' => ['asset_class' => 'hybrid', 'score' => 12, 'equity_share' => 0.0],
        'conservative' => ['asset_class' => 'hybrid', 'score' => 30, 'equity_share' => 0.175],
        'equity_savings' => ['asset_class' => 'hybrid', 'score' => 33, 'equity_share' => 0.275],
        'balanced_advantage' => ['asset_class' => 'hybrid', 'score' => 45, 'equity_share' => 0.5],
        'hybrid_other' => ['asset_class' => 'hybrid', 'score' => 45, 'equity_share' => 0.5],
        'multi_asset' => ['asset_class' => 'hybrid', 'score' => 48, 'equity_share' => 0.5],
        'life_cycle' => ['asset_class' => 'hybrid', 'score' => 48, 'equity_share' => 0.5],
        'solution' => ['asset_class' => 'hybrid', 'score' => 50, 'equity_share' => 0.5],
        'balanced_hybrid' => ['asset_class' => 'hybrid', 'score' => 50, 'equity_share' => 0.5],
        'aggressive' => ['asset_class' => 'hybrid', 'score' => 58, 'equity_share' => 0.725],

        // Commodity
        'gold' => ['asset_class' => 'commodity', 'score' => 45, 'equity_share' => 0.0],
        'silver' => ['asset_class' => 'commodity', 'score' => 55, 'equity_share' => 0.0],

        // Equity
        'large_cap' => ['asset_class' => 'equity', 'score' => 50, 'equity_share' => 1.0],
        'dividend_yield' => ['asset_class' => 'equity', 'score' => 52, 'equity_share' => 1.0],
        'large_mid' => ['asset_class' => 'equity', 'score' => 54, 'equity_share' => 1.0],
        'value' => ['asset_class' => 'equity', 'score' => 55, 'equity_share' => 1.0],
        'contra' => ['asset_class' => 'equity', 'score' => 55, 'equity_share' => 1.0],
        'elss' => ['asset_class' => 'equity', 'score' => 55, 'equity_share' => 1.0],
        'flexi_cap' => ['asset_class' => 'equity', 'score' => 55, 'equity_share' => 1.0],
        'equity_other' => ['asset_class' => 'equity', 'score' => 55, 'equity_share' => 1.0],   // broad index (Nifty 500…), closed-ended equity, unlabelled equity
        'multi_cap' => ['asset_class' => 'equity', 'score' => 56, 'equity_share' => 1.0],
        'focused' => ['asset_class' => 'equity', 'score' => 57, 'equity_share' => 1.0],
        'mid_cap' => ['asset_class' => 'equity', 'score' => 60, 'equity_share' => 1.0],
        'thematic' => ['asset_class' => 'equity', 'score' => 60, 'equity_share' => 1.0],
        'equity_overseas' => ['asset_class' => 'equity', 'score' => 60, 'equity_share' => 1.0],
        'sectoral_thematic' => ['asset_class' => 'equity', 'score' => 61, 'equity_share' => 1.0],  // AMFI's combined bucket when the name doesn't settle which
        'sectoral' => ['asset_class' => 'equity', 'score' => 62, 'equity_share' => 1.0],
        'small_cap' => ['asset_class' => 'equity', 'score' => 63, 'equity_share' => 1.0],

        // Unknown AMFI category (reported to Sentry by amfi:sync): the old generic fund score.
        'other' => ['asset_class' => 'other', 'score' => 45, 'equity_share' => 0.0],
    ],

    /*
    |--------------------------------------------------------------------------
    | CATEGORIES  (AMFI header → slug, or by_name:<scope>)
    |--------------------------------------------------------------------------
    |
    | Key: "<open|close|interval>: <broad> - <sub>" lowercased, whitespace
    | collapsed, curly quotes straightened, '*' removed — see
    | FundClassifier::categoryKey(). Every header in the 24 Oct 2026 file is
    | listed; a header not listed here is an unknown category → 'other'.
    |
    | by_name:<scope> — the category does not say what the fund holds, so the
    | name decides (name_rules), limited to the scope's asset classes; a name
    | that settles nothing within scope gets the scope's default:
    |   any       all classes      default equity_other
    |   equity    equity           default equity_other
    |   debt      debt             default debt_index
    |   hybrid    hybrid           default hybrid_other
    |   overseas  all classes      default equity_overseas
    |   fof       all classes      default other
    |   sectoral  sectoral|thematic default sectoral_thematic
    |
    */

    'categories' => [
        // Equity — old labels
        'open: equity scheme - large cap fund' => 'large_cap',
        'open: equity scheme - large & mid cap fund' => 'large_mid',
        'open: equity scheme - mid cap fund' => 'mid_cap',
        'open: equity scheme - small cap fund' => 'small_cap',
        'open: equity scheme - multi cap fund' => 'multi_cap',
        'open: equity scheme - flexi cap fund' => 'flexi_cap',
        'open: equity scheme - focused fund' => 'focused',
        'open: equity scheme - value fund' => 'value',
        'open: equity scheme - contra fund' => 'contra',
        'open: equity scheme - dividend yield fund' => 'dividend_yield',
        'open: equity scheme - elss' => 'elss',
        'open: equity scheme - sectoral/ thematic' => 'by_name:sectoral',
        // Equity — 2026 labels
        'open: equity schemes - large cap fund' => 'large_cap',
        'open: equity schemes - large & mid cap fund' => 'large_mid',
        'open: equity schemes - mid cap fund' => 'mid_cap',
        'open: equity schemes - small cap fund' => 'small_cap',
        'open: equity schemes - multi cap fund' => 'multi_cap',
        'open: equity schemes - flexi cap fund' => 'flexi_cap',
        'open: equity schemes - focused fund' => 'focused',
        'open: equity schemes - value fund' => 'value',
        'open: equity schemes - contra fund' => 'contra',
        'open: equity schemes - dividend yield fund' => 'dividend_yield',
        'open: equity schemes - elss- tax saver fund' => 'elss',
        'open: equity schemes - sectoral fund' => 'sectoral',
        'open: equity schemes - thematic fund' => 'thematic',
        'close: elss' => 'elss',
        'close: growth' => 'equity_other',

        // Debt — old labels
        'open: debt scheme - overnight fund' => 'overnight',
        'open: debt scheme - liquid fund' => 'liquid',
        'open: debt scheme - money market fund' => 'money_market',
        'open: debt scheme - ultra short duration fund' => 'ultra_short',
        'open: debt scheme - low duration fund' => 'low_duration',
        'open: debt scheme - short duration fund' => 'short_duration',
        'open: debt scheme - medium duration fund' => 'medium_duration',
        'open: debt scheme - medium to long duration fund' => 'medium_long',
        'open: debt scheme - long duration fund' => 'long_duration',
        'open: debt scheme - dynamic bond' => 'dynamic_bond',
        'open: debt scheme - corporate bond fund' => 'corporate_bond',
        'open: debt scheme - credit risk fund' => 'credit_risk',
        'open: debt scheme - banking and psu fund' => 'banking_psu',
        'open: debt scheme - gilt fund' => 'gilt',
        // Debt — 2026 labels (SEBI-2026 section B renamed the duration categories)
        'open: income/debt oriented schemes - overnight fund' => 'overnight',
        'open: income/debt oriented schemes - liquid fund' => 'liquid',
        'open: income/debt oriented schemes - money market fund' => 'money_market',
        'open: income/debt oriented schemes - ultra short term fund' => 'ultra_short',
        'open: income/debt oriented schemes - ultra short to short term fund' => 'low_duration',
        'open: income/debt oriented schemes - short term fund' => 'short_duration',
        'open: income/debt oriented schemes - medium term fund' => 'medium_duration',
        'open: income/debt oriented schemes - medium to long term fund' => 'medium_long',
        'open: income/debt oriented schemes - long term fund' => 'long_duration',
        'open: income/debt oriented schemes - dynamic term fund' => 'dynamic_bond',
        'open: income/debt oriented schemes - corporate bond fund' => 'corporate_bond',
        'open: income/debt oriented schemes - credit risk fund' => 'credit_risk',
        'open: income/debt oriented schemes - banking and psu debt fund' => 'banking_psu',
        'open: income/debt oriented schemes - gilt fund' => 'gilt',
        'open: income/debt oriented schemes - 10-year constant maturity gilt fund' => 'gilt_10y',
        'open: income/debt oriented schemes - floating interest rates fund' => 'floater',
        'open: income/debt oriented schemes - sectoral fund' => 'debt_sectoral',
        // Debt — legacy and closed/interval
        'open: income' => 'debt_other',
        'open: gilt' => 'gilt',
        'interval: income' => 'debt_other',
        'close: income' => 'fmp',
        'close: income/debt oriented schemes - fixed term plan' => 'fmp',
        'close: income/debt oriented schemes - other debt scheme' => 'fmp',

        // Hybrid — old labels
        'open: hybrid scheme - conservative hybrid fund' => 'conservative',
        'open: hybrid scheme - balanced hybrid fund' => 'balanced_hybrid',
        'open: hybrid scheme - aggressive hybrid fund' => 'aggressive',
        'open: hybrid scheme - dynamic asset allocation or balanced advantage' => 'balanced_advantage',
        'open: hybrid scheme - multi asset allocation' => 'multi_asset',
        'open: hybrid scheme - arbitrage fund' => 'arbitrage',
        'open: hybrid scheme - equity savings' => 'equity_savings',
        // Hybrid — 2026 labels
        'open: hybrid schemes - conservative hybrid fund' => 'conservative',
        'open: hybrid schemes - balanced hybrid fund' => 'balanced_hybrid',
        'open: hybrid schemes - aggressive hybrid fund' => 'aggressive',
        'open: hybrid schemes - balanced advantage fund/ dynamic asset allocation' => 'balanced_advantage',
        'open: hybrid schemes - multi asset allocation fund' => 'multi_asset',
        'open: hybrid schemes - arbitrage fund' => 'arbitrage',
        'open: hybrid schemes - equity savings fund' => 'equity_savings',
        'open: growth' => 'hybrid_other',   // legacy; the one live scheme is "ICICI Prudential Blended Plan A"

        // Solution oriented (discontinued by SEBI-2026) and life cycle (new in SEBI-2026)
        'open: solution oriented scheme - retirement fund' => 'solution',
        'open: solution oriented schemes - retirement fund' => 'solution',
        "open: solution oriented scheme - children's fund" => 'solution',
        "open: children's fund - childrens' fund" => 'solution',
        'open: life cycle funds - life cycle fund with maturity of 5 years' => 'life_cycle',
        'open: life cycle funds - life cycle fund with maturity of 10 years' => 'life_cycle',
        'open: life cycle funds - life cycle fund with maturity of 15 years' => 'life_cycle',
        'open: life cycle funds - life cycle fund with maturity of 20 years' => 'life_cycle',   // permitted by Annexure B, not yet in the file
        'open: life cycle funds - life cycle fund with maturity of 25 years' => 'life_cycle',   // permitted by Annexure B, not yet in the file
        'open: life cycle funds - life cycle fund with maturity of 30 years' => 'life_cycle',

        // Index funds and ETFs
        'open: other scheme - index funds' => 'by_name:any',
        'open: other scheme - other etfs' => 'by_name:any',
        'open: other scheme - gold etf' => 'gold',
        'open: index funds - equity funds' => 'by_name:equity',
        'open: index funds - debt funds' => 'by_name:debt',
        'open: index funds - hybrid fund' => 'by_name:hybrid',
        'open: exchange traded funds (etfs) - equity etf' => 'by_name:equity',
        'open: exchange traded funds (etfs) - debt etf' => 'by_name:debt',
        'open: exchange traded funds (etfs) - hybrid etf' => 'by_name:hybrid',
        'open: exchange traded funds (etfs) - gold etf' => 'gold',
        'open: exchange traded funds (etfs) - silver etf' => 'silver',
        'open: exchange traded funds (etfs) - other etf' => 'by_name:any',
        'open: exchange traded funds (etfs) - etfs investing overseas' => 'by_name:overseas',

        // Fund of funds
        'open: other scheme - fof domestic' => 'by_name:fof',
        'open: fund of funds scheme (domestic) - fund of funds scheme (domestic)' => 'by_name:fof',
        'open: other scheme - fof overseas' => 'by_name:overseas',
        'open: overseas fund of funds - fund of funds investing overseas' => 'by_name:overseas',
    ],

    'scopes' => [
        'any' => ['classes' => ['equity', 'debt', 'hybrid', 'commodity'], 'default' => 'equity_other'],
        'equity' => ['classes' => ['equity'], 'default' => 'equity_other'],
        'debt' => ['classes' => ['debt'], 'default' => 'debt_index'],
        'hybrid' => ['classes' => ['hybrid'], 'default' => 'hybrid_other'],
        'overseas' => ['classes' => ['equity', 'debt', 'hybrid', 'commodity'], 'default' => 'equity_overseas'],
        'fof' => ['classes' => ['equity', 'debt', 'hybrid', 'commodity'], 'default' => 'other'],
        'sectoral' => ['slugs' => ['sectoral', 'thematic'], 'default' => 'sectoral_thematic'],
    ],

    /*
    |--------------------------------------------------------------------------
    | NAME RULES  (first match wins; matched against the normalised name)
    |--------------------------------------------------------------------------
    |
    | Run on SchemeNameNormalizer output: lowercase, "&" → "and", plan/option
    | words gone, cap sizes joined ("midcap", "smallcap", "largecap"), old
    | SEBI debt names rewritten to the 2026 ones ("short duration" →
    | "short term"). Order matters:
    |   commodity before everything ("gold" in an ETF's name decides it);
    |   hybrid before equity ("equity savings" is hybrid);
    |   explicit equity (ELSS, "long term equity") before debt, because
    |   "long term" is also a debt duration;
    |   debt before equity segments ("banking and psu" is debt, "banking and
    |   financial services" is sectoral equity);
    |   "large and midcap" before "midcap"; "next 50" before "nifty 50".
    | No rule = the scope's default (or equity_other for an unmatched fund).
    |
    */

    'name_rules' => [
        ['/\bgold\b/', 'gold'],
        ['/\bsilver\b/', 'silver'],

        ['/\barbitrage\b/', 'arbitrage'],
        ['/\bequity savings\b/', 'equity_savings'],
        ['/\bconservative\b/', 'conservative'],
        ['/\baggressive hybrid\b|\bhybrid equity\b|\bequity hybrid\b/', 'aggressive'],
        ['/\bbalanced advantage\b|\bdynamic asset allocation\b|\bbaf\b/', 'balanced_advantage'],
        ['/\bmulti asset\b/', 'multi_asset'],
        ['/\blife cycle\b/', 'life_cycle'],
        ['/\bretirement\b|\bchildrens?\b|\bpension\b/', 'solution'],
        ['/\bbalanced\b/', 'balanced_hybrid'],
        ['/\bhybrid\b/', 'hybrid_other'],

        ['/\belss\b|\btaxsaver\b|\btax\b|\blong term equity\b/', 'elss'],

        ['/\bovernight\b|\b1d rate\b/', 'overnight'],
        ['/\bliquid\b/', 'liquid'],
        ['/\bmoney market\b/', 'money_market'],
        ['/\bultra short to short term\b/', 'low_duration'],
        ['/\bultra short\b/', 'ultra_short'],
        ['/\bcredit risk\b/', 'credit_risk'],
        ['/\bbanking and psu\b|\bpsu bond\b/', 'banking_psu'],
        ['/\bcorporate bond\b/', 'corporate_bond'],
        ['/\bconstant maturity\b|\b10 year gilt\b/', 'gilt_10y'],
        ['/\bsdl\b|\bibx\b|\bbharat bond\b|\btarget maturity\b/', 'debt_index'],
        ['/\bgilt\b|\bgsec\b|\bgovernment securities\b/', 'gilt'],
        ['/\bfloating interest rates\b/', 'floater'],
        ['/\bdynamic term\b/', 'dynamic_bond'],
        ['/\bmedium to long term\b/', 'medium_long'],
        ['/\bmedium term\b/', 'medium_duration'],
        ['/\blong term\b/', 'long_duration'],
        ['/\bshort term\b/', 'short_duration'],
        // "savings" alone is debt ("… Savings Fund" is an ultra-short debt scheme);
        // "equity savings" was taken by the hybrid rule above.
        ['/\bbond\b|\bdebt\b|\bincome\b|\bfixed (maturity|term|horizon)\b|\bfmp\b|\bduration\b|\bterm\b|\bcredit\b|\baccrual\b|\bsavings\b|\btreasury\b|\bcash\b/', 'debt_other'],

        ['/\bsmallcap\b|\bmicrocap\b/', 'small_cap'],
        ['/\blarge and midcap\b|\blargemidcap\b|\bnext 50\b/', 'large_mid'],
        ['/\bmidcap\b/', 'mid_cap'],
        ['/\bnasdaq\b|\bs and p\b|\bsandp\b|\bglobal\b|\bworld\b|\binternational\b|\boverseas\b|\bus\b|\bhang seng\b|\bjapan\b|\bchina\b|\btaiwan\b|\bemerging\b|\bfang\b/', 'equity_overseas'],
        ['/\bbank\b|\bbanking\b|\bfinancial\b|\bpharma\b|\bhealthcare\b|\bhealth\b|\bit\b|\btechnology\b|\bdigital\b|\binfra\b|\binfrastructure\b|\benergy\b|\bpower\b|\bauto\b|\bautomotive\b|\bfmcg\b|\brealty\b|\bmetal\b|\bpsu\b|\bcpse\b|\bpsu bank\b|\bdefence\b|\bmedia\b|\bcommodities\b|\boil\b/', 'sectoral'],
        ['/\bnifty 50\b|\bsensex\b|\bnifty 100\b|\bbse 100\b|\blargecap\b|\bbluechip\b|\btop 100\b|\bfrontline\b/', 'large_cap'],
        ['/\bflexicap\b|\bnifty 500\b|\btotal market\b|\bnifty 200\b|\bbse 500\b/', 'flexi_cap'],
        ['/\bmulticap\b/', 'multi_cap'],
        ['/\bfocused\b/', 'focused'],
        ['/\bdividendyield\b/', 'dividend_yield'],
        ['/\bcontra\b/', 'contra'],
        ['/\bvalue\b/', 'value'],
        ['/\bmomentum\b|\bquality\b|\balpha\b|\blow vol(atility)?\b|\bequal weight\b|\besg\b|\bmanufacturing\b|\bconsumption\b|\binnovation\b|\bthematic\b|\bbusiness cycle\b|\bspecial situations\b|\bmnc\b|\bdividend opportunities\b/', 'thematic'],
        ['/\bsectoral\b|\bsector\b/', 'sectoral'],
    ],

];
