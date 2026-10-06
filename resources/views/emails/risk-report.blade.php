@component('mail::message')
# Portfolio Risk Report Ready

Your portfolio risk analysis is complete. The full PDF report is attached to this email.

| Metric | Value |
|---|---|
| Portfolio | {{ $portfolioFile->portfolio?->name ?? $portfolioFile->original_name }} |
| Risk Score | {{ number_format($riskScore->score, 0) }}/100 |
| Risk Level | {{ $riskScore->level() }} |
| Largest holding | {{ $largest ? number_format($largest['share'], 1).'% — '.$largest['name'] : '—' }} |
| Gain / loss vs cost | {{ $gainLoss['pct'] !== null ? \App\Services\ReportHeadline::signed($gainLoss['pct']).'%'.($gainLoss['counted'] < $gainLoss['total'] ? ' (on '.$gainLoss['counted'].' of '.$gainLoss['total'].' holdings)' : '') : '— (cost not available)' }} |

@if(!empty($riskScore->meta['next_action']))
**Observations:** {{ $riskScore->meta['next_action'] }}
@endif

@component('mail::button', ['url' => config('app.url').'/dashboard'])
View Dashboard
@endcomponent

Thanks,<br>
{{ config('app.name') }} Team

@component('mail::subcopy')
@include('reports._disclaimer')
@endcomponent
@endcomponent
