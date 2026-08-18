{{-- LEFT — UPLOAD CARD --}}
<div class="card" style="padding:1.75rem;min-width:0;overflow:hidden;">

    <p style="color:var(--ink-3);font-size:.875rem;margin-bottom:1.5rem;line-height:1.5;">
        Supported: CSV, XLSX, XLS, PDF &nbsp;·&nbsp; Max 20 MB
    </p>

    <form
        method="POST"
        action="{{ route('portfolio.upload.store') }}"
        enctype="multipart/form-data"
        id="uploadForm">

        @csrf

        {{-- PORTFOLIO SELECT --}}
        <div style="margin-bottom:1.25rem;">

            <label style="display:block;margin-bottom:.5rem;font-weight:600;font-size:.875rem;">
                Select Portfolio
            </label>

            <select
                name="portfolio_id"
                class="form-select">

                <option value="">Default Portfolio</option>

                @foreach($portfolios as $portfolio)
                <option
                    value="{{ $portfolio->id }}"
                    @selected(old('portfolio_id') == $portfolio->id)>
                    {{ $portfolio->name }}
                </option>
                @endforeach

            </select>

        </div>

        {{-- FILE INPUT --}}
        <div style="margin-bottom:1.5rem;">

            <label style="display:block;margin-bottom:.5rem;font-weight:600;font-size:.875rem;">
                Choose File
            </label>

            <div
                id="dropZone"
                style="
                    border:2px dashed var(--paper-3);
                    border-radius:14px;
                    padding:1.5rem 1rem;
                    text-align:center;
                    cursor:pointer;
                    transition:.2s ease;
                    position:relative;
                "
                onclick="document.getElementById('fileInput').click()">

                <div id="dropLabel" style="pointer-events:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:1.75rem;height:1.75rem;margin:0 auto .5rem;display:block;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
                    </svg>
                    <div style="font-weight:600;font-size:.9rem;margin-bottom:.25rem;">
                        Drop file here or click to browse
                    </div>
                    <div style="color:var(--ink-3);font-size:.8rem;">
                        CSV, XLSX, XLS, PDF — up to 20 MB
                    </div>
                </div>

                <input
                    type="file"
                    name="file"
                    id="fileInput"
                    accept=".csv,.xlsx,.xls,.pdf"
                    required
                    style="
                        position:absolute;
                        inset:0;
                        opacity:0;
                        cursor:pointer;
                        width:100%;
                        height:100%;
                    ">

            </div>

            <div
                id="filePreview"
                style="display:none;margin-top:.75rem;padding:.6rem .9rem;border-radius:10px;background:rgba(16,185,129,.1);color:#34d399;font-size:.85rem;font-weight:600;">
            </div>

        </div>

        {{-- SUBMIT --}}
        <button
            type="submit"
            id="uploadBtn"
            style="
                width:100%;
                border:none;
                border-radius:12px;
                padding:1rem 1.25rem;
                background:linear-gradient(135deg,#111827,#1f2937);
                color:white;
                font-weight:700;
                font-size:.95rem;
                cursor:pointer;
                transition:.2s ease;
            "
            onmouseover="this.style.opacity='.88'"
            onmouseout="this.style.opacity='1'">
            Upload Portfolio →
        </button>

    </form>

</div>
