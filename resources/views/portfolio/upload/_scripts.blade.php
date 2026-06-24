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
        preview.textContent = '✓  ' + file.name + '  (' + size + ')';
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
});
</script>
@endpush
