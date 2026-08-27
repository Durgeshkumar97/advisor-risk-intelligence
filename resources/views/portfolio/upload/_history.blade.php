{{-- RIGHT — UPLOAD HISTORY --}}
<div class="card" style="padding:1.75rem;min-width:0;overflow:hidden;">

    <div style="
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:1.5rem;
        flex-wrap:wrap;
        gap:.75rem;
    ">

        <div>
            <h2 style="font-size:1.15rem;font-weight:700;margin-bottom:.2rem;">
                Upload History
            </h2>
            <p style="color:var(--ink-3);font-size:.875rem;margin:0;">
                Portfolio processing and file status.
            </p>
        </div>

        <span style="
            color:var(--ink-3);
            font-size:.85rem;
            font-weight:600;
        ">
            {{ $files->count() }} {{ Str::plural('file', $files->count()) }}
        </span>

    </div>

    {{-- TABLE --}}
    <div style="overflow-x:auto;">

        <table style="width:100%;border-collapse:collapse;font-size:.875rem;">

            <thead>
                <tr style="border-bottom:1px solid var(--paper-3);">
                    <th style="text-align:left;padding:.75rem .6rem;font-weight:700;color:var(--ink-2);">File</th>
                    <th style="text-align:left;padding:.75rem .6rem;font-weight:700;color:var(--ink-2);">Status</th>
                    <th style="text-align:left;padding:.75rem .6rem;font-weight:700;color:var(--ink-2);">Size</th>
                    <th style="text-align:left;padding:.75rem .6rem;font-weight:700;color:var(--ink-2);">Uploaded</th>
                    <th style="text-align:center;padding:.75rem .6rem;font-weight:700;color:var(--ink-2);">Action</th>
                </tr>
            </thead>

            <tbody>

                @forelse($files as $file)

                @php
                    $statusStyles = match($file->status) {
                        'processed'  => ['bg' => 'rgba(16,185,129,.15)',  'color' => '#34d399',  'label' => 'Processed',  'icon' => 'check-circle'],
                        'failed'     => ['bg' => 'rgba(239,68,68,.15)',   'color' => '#fca5a5',  'label' => 'Failed',     'icon' => 'x-circle'],
                        'processing' => ['bg' => 'rgba(59,130,246,.15)',  'color' => '#93c5fd',  'label' => 'Processing', 'icon' => 'arrow-path'],
                        default      => ['bg' => 'rgba(251,191,36,.15)',  'color' => '#fbbf24',  'label' => 'Pending',    'icon' => 'clock'],
                    };

                    $statusIcons = [
                        'check-circle' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:.8rem;height:.8rem;flex-shrink:0;"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>',
                        'x-circle'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:.8rem;height:.8rem;flex-shrink:0;"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd" /></svg>',
                        'arrow-path'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:.8rem;height:.8rem;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>',
                        'clock'        => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:.8rem;height:.8rem;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>',
                    ];

                    // Human-readable file size
                    $bytes = $file->file_size;
                    if ($bytes >= 1048576) {
                        $sizeLabel = round($bytes / 1048576, 1) . ' MB';
                    } elseif ($bytes >= 1024) {
                        $sizeLabel = round($bytes / 1024, 1) . ' KB';
                    } else {
                        $sizeLabel = $bytes . ' B';
                    }
                @endphp

                <tr style="border-bottom:1px solid var(--paper-3);">

                    {{-- FILE NAME --}}
                    <td style="padding:.85rem .6rem;font-weight:600;max-width:220px;">
                        <div style="
                            white-space:nowrap;
                            overflow:hidden;
                            text-overflow:ellipsis;
                            max-width:200px;
                        " title="{{ $file->original_name }}">
                            {{ $file->original_name }}
                        </div>
                        @if($file->portfolio)
                        <div style="color:var(--ink-3);font-size:.78rem;margin-top:.15rem;">
                            {{ $file->portfolio->name }}
                        </div>
                        @endif
                    </td>

                    {{-- STATUS --}}
                    <td style="padding:.85rem .6rem;">
                        <span style="
                            display:inline-flex;
                            align-items:center;
                            gap:.3rem;
                            padding:.3rem .7rem;
                            border-radius:999px;
                            font-size:.75rem;
                            font-weight:700;
                            background:{{ $statusStyles['bg'] }};
                            color:{{ $statusStyles['color'] }};
                            white-space:nowrap;
                        ">
                            {!! $statusIcons[$statusStyles['icon']] !!}
                            {{ $statusStyles['label'] }}
                        </span>

                        @if(in_array($file->status, ['pending', 'processing'], true))
                        {{-- data-status is what _scripts.blade.php polls on. Pending
                             counts as in-flight: the queue worker is cron-driven
                             (every minute), so a just-uploaded file sits pending for
                             up to a minute before it even starts. --}}
                        <div data-status="{{ $file->status }}"
                             style="color:#93c5fd;font-size:.72rem;margin-top:.3rem;max-width:260px;line-height:1.4;opacity:.85;">
                            Processing — this page refreshes automatically.
                        </div>
                        @endif

                        @if(! empty($file->meta['parse_warnings']))
                        {{-- Non-fatal: the file parsed and scored, but something
                             in it was interpreted rather than understood (e.g. an
                             asset type we don't recognise, scored as equity). --}}
                        <div style="color:#fbbf24;font-size:.72rem;margin-top:.3rem;max-width:260px;line-height:1.4;opacity:.9;">
                            @foreach(array_slice($file->meta['parse_warnings'], 0, 3) as $warning)
                            <div>{{ Str::limit($warning, 200) }}</div>
                            @endforeach
                        </div>
                        @endif

                        @if($file->isFailed() && isset($file->meta['error_message']))
                        {{-- 60 chars cut the actionable half off the longer messages
                             (the no-holdings and symbol-cap ones both name what to
                             change); 200 fits them whole at this width. --}}
                        <div style="color:#fca5a5;font-size:.75rem;margin-top:.25rem;max-width:260px;line-height:1.4;">
                            {{ Str::limit($file->meta['error_message'], 200) }}
                        </div>
                        @endif
                    </td>

                    {{-- SIZE --}}
                    <td style="padding:.85rem .6rem;color:var(--ink-3);white-space:nowrap;">
                        {{ $sizeLabel }}
                    </td>

                    {{-- DATE --}}
                    <td style="padding:.85rem .6rem;color:var(--ink-3);white-space:nowrap;">
                        {{ $file->created_at->format('d M Y') }}<br>
                        <span style="font-size:.78rem;">{{ $file->created_at->format('h:i A') }}</span>
                    </td>

                    {{-- ACTIONS --}}
                    <td style="padding:.85rem .6rem;text-align:center;">

                        <div style="display:flex;gap:.5rem;justify-content:center;align-items:center;">

                            {{-- DOWNLOAD --}}
                            <a
                                href="{{ route('file.view', $file->id) }}"
                                title="Download"
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    justify-content:center;
                                    width:40px;height:40px;
                                    border-radius:8px;
                                    background:rgba(59,130,246,.12);
                                    color:#93c5fd;
                                    text-decoration:none;
                                    font-size:1rem;
                                    transition:.15s ease;
                                "
                                onmouseover="this.style.background='rgba(59,130,246,.22)'"
                                onmouseout="this.style.background='rgba(59,130,246,.12)'"
                            ><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;"><path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .75.75v10.638l3.96-4.158a.75.75 0 1 1 1.08 1.04l-5.25 5.5a.75.75 0 0 1-1.08 0l-5.25-5.5a.75.75 0 1 1 1.08-1.04l3.96 4.158V3.75A.75.75 0 0 1 10 3ZM3.25 15a.75.75 0 0 1 .75.75v2.5c0 .138.112.25.25.25h11.5a.25.25 0 0 0 .25-.25v-2.5a.75.75 0 0 1 1.5 0v2.5A1.75 1.75 0 0 1 15.75 20H4.25A1.75 1.75 0 0 1 2.5 18.25v-2.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" /></svg></a>

                            {{-- DELETE (not while processing) --}}
                            @unless($file->isProcessing())
                            <form
                                method="POST"
                                action="{{ route('portfolio.file.destroy', $file->id) }}"
                                onsubmit="return confirm('Delete \'{{ addslashes($file->original_name) }}\'? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    title="Delete"
                                    style="
                                        display:inline-flex;
                                        align-items:center;
                                        justify-content:center;
                                        width:40px;height:40px;
                                        border-radius:8px;
                                        border:none;
                                        background:rgba(239,68,68,.12);
                                        color:#fca5a5;
                                        cursor:pointer;
                                        font-size:1rem;
                                        transition:.15s ease;
                                    "
                                    onmouseover="this.style.background='rgba(239,68,68,.25)'"
                                    onmouseout="this.style.background='rgba(239,68,68,.12)'"
                                ><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:1rem;height:1rem;"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" /></svg></button>
                            </form>
                            @endunless

                        </div>

                    </td>

                </tr>

                @empty

                <tr>
                    <td colspan="5" style="padding:2.5rem;text-align:center;color:var(--ink-3);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:2rem;height:2rem;margin:0 auto .5rem;display:block;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44l-1.122-1.12a1.5 1.5 0 0 0-1.06-.44H4.5A2.25 2.25 0 0 0 2.25 9v8.25A2.25 2.25 0 0 0 4.5 19.5Z" />
                        </svg>
                        <div style="font-weight:600;">No files uploaded yet</div>
                        <div style="font-size:.85rem;margin-top:.25rem;">Upload your first portfolio file using the form on the left.</div>
                    </td>
                </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>
