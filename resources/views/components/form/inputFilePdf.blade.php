<label>{{ $title }}</label>
<div class="mb-3">
    <div class="input-group">
        <span class="input-group-text pe-3"><i class="fa-solid fa-file-pdf fa-lg"></i></span>
        <input type="text" class="form-control" id="nomor_{{ $field }}" name="nomor_{{ $field }}"
            list="browsers_{{ $field }}" onfocus="this.placeholder=''" @readonly($readonly)
            @unless ($readonly)
                placeholder="Nomor {{ str_replace('_', ' ', strtoupper($field)) }}"
            @endunless>
        <span class="input-group-text pe-3" style="display: none" id="loading_{{ $field }}"><i
                class="fa-solid fa-gear fa-spin"></i></span>
        <label class="btn btn-primary mb-0" for="file_{{ $field }}">
            Upload
        </label>
        <input type="file" id="file_{{ $field }}" name="file_{{ $field }}" accept="application/pdf"
            class="d-none" />
    </div>
    <small class="form-text">Nama File : <span id="name_{{ $field }}" class="font-weight-bold">Belum
            ada file
            yang dipilih</span></small>
</div>

@if (!$readonly)
    <datalist id="browsers_{{ $field }}"></datalist>
@endif

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof bindFileName === 'function') {
                bindFileName('#file_{{ $field }}', '#name_{{ $field }}');
            }
            @if (!$readonly)
                document.getElementById('file_{{ $field }}').addEventListener('change', function(event) {
                    readFilePDF(event, 'browsers_{{ $field }}', 'loading_{{ $field }}');
                });
            @endif
        });
    </script>
@endpush
