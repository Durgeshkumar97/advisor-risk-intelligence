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
                        'processed'  => ['bg' => 'rgba(16,185,129,.15)',  'color' => '#34d399',  'label' => '✓ Processed'],
                        'failed'     => ['bg' => 'rgba(239,68,68,.15)',   'color' => '#fca5a5',  'label' => '✗ Failed'],
                        'processing' => ['bg' => 'rgba(59,130,246,.15)',  'color' => '#93c5fd',  'label' => '⟳ Processing'],
                        default      => ['bg' => 'rgba(251,191,36,.15)',  'color' => '#fbbf24',  'label' => '⏳ Pending'],
                    };

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
                            display:inline-block;
                            padding:.3rem .7rem;
                            border-radius:999px;
                            font-size:.75rem;
                            font-weight:700;
                            background:{{ $statusStyles['bg'] }};
                            color:{{ $statusStyles['color'] }};
                            white-space:nowrap;
                        ">
                            {{ $statusStyles['label'] }}
                        </span>
                        @if($file->isFailed() && isset($file->meta['error_message']))
                        <div style="color:#fca5a5;font-size:.75rem;margin-top:.25rem;max-width:160px;">
                            {{ Str::limit($file->meta['error_message'], 60) }}
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
                            >⬇</a>

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
                                >✕</button>
                            </form>
                            @endunless

                        </div>

                    </td>

                </tr>

                @empty

                <tr>
                    <td colspan="5" style="padding:2.5rem;text-align:center;color:var(--ink-3);">
                        <div style="font-size:2rem;margin-bottom:.5rem;">📁</div>
                        <div style="font-weight:600;">No files uploaded yet</div>
                        <div style="font-size:.85rem;margin-top:.25rem;">Upload your first portfolio file using the form on the left.</div>
                    </td>
                </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>
