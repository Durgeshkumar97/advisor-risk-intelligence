@component('mail::message')
# Portfolio Risk Report Ready

Your portfolio risk analysis is complete. The full PDF report is attached to this email.

| Metric | Value |
|---|---|
| Portfolio | {{ $portfolioFile->portfolio?->name ?? $portfolioFile->original_name }} |
| Risk Score | {{ number_format($riskScore->score, 0) }}/100 |
| Risk Level | {{ $riskScore->level() }} |
| Volatility | {{ number_format($riskScore->volatility, 2) }}% |
| Max Drawdown | {{ number_format($riskScore->drawdown, 2) }}% |

@if(!empty($riskScore->meta['next_action']))
**Recommended Action:** {{ $riskScore->meta['next_action'] }}
@endif

@component('mail::button', ['url' => config('app.url').'/dashboard'])
View Dashboard
@endcomponent

Thanks,<br>
{{ config('app.name') }} Team
@endcomponent
