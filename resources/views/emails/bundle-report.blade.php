@component('mail::message')
# All Client Reports Ready

Your multi-client portfolio bundle has been processed and is attached to this email.

| | |
|---|---|
| **Source ZIP** | {{ $portfolioFile->original_name ?? '—' }} |
| **Processed** | {{ $portfolioFile->meta['bundle_processed_count'] ?? '—' }} client(s) |
| **Failed / skipped** | {{ $portfolioFile->meta['bundle_failed_count'] ?? '0' }} client(s) |
| **Generated** | {{ now()->format('d M Y, h:i A') }} UTC |

The attached ZIP contains one PDF report per successfully processed client, plus a `_SUMMARY.txt` with the full outcome list.

@component('mail::button', ['url' => route('dashboard')])
View Dashboard
@endcomponent

*This report is confidential and intended solely for the named adviser.*

{{ config('app.name') }}
@endcomponent
