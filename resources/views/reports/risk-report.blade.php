<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; margin: 0; padding: 0; }
.page { padding: 30px 36px; }

.header { margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #1e40af; }
.brand { font-size: 20px; font-weight: 700; color: #1e40af; }
.brand-accent { color: #f59e0b; }
.report-title { font-size: 15px; font-weight: 700; margin-top: 4px; }
.report-meta { font-size: 9px; color: #64748b; margin-top: 3px; }

.summary-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
.summary-table td { border: 1px solid #cbd5e1; padding: 12px; text-align: center; width: 25%; background: #f8fafc; }
.summary-label { font-size: 8px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; font-weight: 700; display: block; margin-bottom: 5px; }
.summary-value { font-size: 22px; font-weight: 700; display: block; }
.summary-unit { font-size: 11px; font-weight: 400; }

.risk-high { color: #dc2626; }
.risk-medium { color: #d97706; }
.risk-low { color: #16a34a; }
.green { color: #16a34a; }
.red { color: #dc2626; }

.section-heading { font-size: 11px; font-weight: 700; color: #1e40af; margin: 14px 0 7px; padding: 4px 0 4px 8px; border-left: 3px solid #1e40af; }
.action-box { background: #eff6ff; border: 1px solid #93c5fd; padding: 8px 12px; font-size: 10px; color: #1d4ed8; margin-bottom: 14px; }
.flag-row { background: #fff7ed; border: 1px solid #fdba74; padding: 5px 10px; font-size: 9px; color: #c2410c; margin-bottom: 3px; }

.assets-table { width: 100%; border-collapse: collapse; font-size: 9px; margin-bottom: 18px; }
.assets-table th { background: #1e40af; color: #fff; text-align: left; padding: 5px 7px; font-size: 8px; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; }
.assets-table td { padding: 5px 7px; border-bottom: 1px solid #e2e8f0; }
.assets-table tr.even td { background: #f8fafc; }

.footer { border-top: 1px solid #e2e8f0; padding-top: 10px; margin-top: 6px; font-size: 8px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<div class="page">

<div class="header">
    <div class="brand">Risk<span class="brand-accent">Signal</span></div>
    <div class="report-title">Portfolio Risk Report</div>
    <div class="report-meta">
        Portfolio: {{ $portfolio?->name ?? $file->original_name }}
        &nbsp;&middot;&nbsp;
        Generated: {{ now()->format('d M Y, h:i A') }}
    </div>
</div>

@php $level = $riskScore->level(); @endphp

<table class="summary-table">
    <tr>
        <td>
            <span class="summary-label">Risk Score</span>
            <span class="summary-value">{{ number_format($riskScore->score, 0) }}<span class="summary-unit">/100</span></span>
        </td>
        <td>
            <span class="summary-label">Risk Level</span>
            <span class="summary-value {{ $level === 'HIGH' ? 'risk-high' : ($level === 'MEDIUM' ? 'risk-medium' : 'risk-low') }}">
                {{ $level }}
            </span>
        </td>
        <td>
            <span class="summary-label">Volatility</span>
            <span class="summary-value">{{ number_format($riskScore->volatility, 2) }}<span class="summary-unit">%</span></span>
        </td>
        <td>
            <span class="summary-label">Max Drawdown</span>
            <span class="summary-value">{{ number_format($riskScore->drawdown, 2) }}<span class="summary-unit">%</span></span>
        </td>
    </tr>
</table>

@if(!empty($riskScore->meta['next_action']))
<div class="section-heading">Recommended Action</div>
<div class="action-box">{{ $riskScore->meta['next_action'] }}</div>
@endif

@if(!empty($riskScore->meta['risk_flags']))
<div class="section-heading">Risk Flags</div>
@foreach($riskScore->meta['risk_flags'] as $flag)
<div class="flag-row">&#8226; {{ $flag }}</div>
@endforeach
@endif

@if($assets->isNotEmpty())
<div class="section-heading">Portfolio Holdings ({{ $assets->count() }} assets)</div>
<table class="assets-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Symbol / ISIN</th>
            <th style="text-align:right;">Qty</th>
            <th style="text-align:right;">Current Value</th>
            <th style="text-align:right;">P&amp;L</th>
            <th style="text-align:center;">Risk Score</th>
            <th style="text-align:center;">Level</th>
        </tr>
    </thead>
    <tbody>
        @foreach($assets as $i => $asset)
        @php
            $lvlClass = match($asset->risk_level) { 'HIGH' => 'risk-high', 'MEDIUM' => 'risk-medium', default => 'risk-low' };
            $plPositive = ($asset->profit_loss ?? 0) >= 0;
        @endphp
        <tr class="{{ $i % 2 === 1 ? 'even' : '' }}">
            <td style="font-weight:600;">{{ $asset->name }}</td>
            <td style="color:#64748b;">{{ $asset->asset_type }}</td>
            <td style="color:#94a3b8;">{{ $asset->symbol ?: ($asset->isin ?: '—') }}</td>
            <td style="text-align:right;">{{ $asset->quantity }}</td>
            <td style="text-align:right;">{{ $asset->current_value !== null ? '₹'.number_format($asset->current_value, 2) : '—' }}</td>
            <td style="text-align:right;" class="{{ $plPositive ? 'green' : 'red' }}">
                {{ $asset->profit_loss !== null ? ($plPositive ? '+' : '').'₹'.number_format($asset->profit_loss, 2) : '—' }}
            </td>
            <td style="text-align:center;font-weight:700;" class="{{ $lvlClass }}">{{ number_format($asset->risk_score, 0) }}</td>
            <td style="text-align:center;font-weight:700;font-size:8px;" class="{{ $lvlClass }}">{{ $asset->risk_level }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<div class="footer">
    Generated by RiskSignal &middot; {{ config('app.url') }} &middot; {{ now()->format('d M Y') }}
    &middot; This report is confidential and intended for the named recipient only.
</div>

</div>
</body>
</html>
