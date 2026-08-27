@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {

    const form      = document.getElementById('uploadForm');
    const btn       = document.getElementById('uploadBtn');
    const fileInput = document.getElementById('fileInput');
    const dropZone  = document.getElementById('dropZone');
    const preview   = document.getElementById('filePreview');

    // FILE SELECTION PREVIEW
    fileInput.addEventListener('change', () => {
        const file = fileInput.files[0];
        if (!file) { preview.style.display = 'none'; return; }
        const mb   = file.size / 1048576;
        const size = mb >= 1 ? mb.toFixed(1) + ' MB' : (file.size / 1024).toFixed(1) + ' KB';
        preview.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:.8rem;height:.8rem;display:inline-block;vertical-align:-1px;margin-right:.4rem;"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" /></svg>';
        preview.append(file.name + '  (' + size + ')');
        preview.style.display = 'block';
        dropZone.style.borderColor = '#34d399';
    });

    // DRAG AND DROP
    ['dragenter','dragover'].forEach(evt => {
        dropZone.addEventListener(evt, e => {
            e.preventDefault();
            dropZone.style.borderColor = '#fbbf24';
            dropZone.style.background  = 'rgba(251,191,36,.06)';
        });
    });

    ['dragleave','drop'].forEach(evt => {
        dropZone.addEventListener(evt, e => {
            e.preventDefault();
            dropZone.style.borderColor = 'var(--paper-3)';
            dropZone.style.background  = '';
        });
    });

    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        const dt = e.dataTransfer;
        if (dt.files.length) {
            fileInput.files = dt.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });

    // SUBMIT STATE
    form.addEventListener('submit', () => {
        btn.disabled      = true;
        btn.textContent   = 'Uploading…';
        btn.style.opacity = '.65';
    });

    /*
     | AUTO-REFRESH WHILE WORK IS IN FLIGHT
     |
     | The queue worker is cron-driven (queue:work --stop-when-empty, every
     | minute), so a freshly uploaded file can sit pending for up to a minute
     | before it even starts. Previously the flash said "Processing has
     | started" and then the page never changed, leaving the advisor to guess
     | when to hit refresh.
     |
     | Reloads only while at least one row is still pending or processing —
     | _history.blade.php renders [data-status] for exactly those rows — so
     | the polling stops on its own once everything is processed or failed.
     | A full reload rather than a fetch/poll because the whole row (status,
     | error text, download buttons) is server-rendered.
     */
    if (document.querySelector('[data-status]')) {
        setTimeout(() => window.location.reload(), 10000);
    }
});
</script>
@endpush
