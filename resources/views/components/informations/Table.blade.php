@php
    $tableCanWrite = true;

    if (auth()->check()) {
        $tableCanWrite = app(\App\Services\User\YearAccessService::class)->canWrite(
            app(\App\Services\User\ActivePositionService::class)->get(),
        );
    }
@endphp

<script>
    const {{ $nameTable }}Html = `
        @if (!empty($title))
            <div class="card mb-4">
                <div class="card-header pb-3">
                    <h4 class="text-body opacity-9 text-left">{{ $subtitle }}</h4>
                    <h6 class="text-body opacity-9 text-left">{{ $title }}</h6>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-flush table-striped" id="{{ $nameTable }}-table">
                            <thead></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-flush table-striped" id="{{ $nameTable }}-table">
                    <thead></thead>
                    <tbody></tbody>
                </table>
            </div>
        @endif
    `;

    document.getElementById('{{ $nameTable }}').innerHTML = {{ $nameTable }}Html;
</script>
<script>
    window.renderNomor = function(data, type) {
        if (!data) return '';
        return data.replaceAll('/', '/ ');
    };

    window.renderRupiah = function(data, type) {
        if (type !== 'display') return data;
        if (!data) return 'Rp. 0,00';

        return 'Rp. ' + Number(data).toLocaleString('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };

    const {{ $nameTable . 'columns' }} = @json($columns);
    {{ $nameTable . 'columns' }}.forEach(col => {
        if (typeof col.render === 'string' && typeof window[col.render] === 'function') {
            col.render = window[col.render];
        }
    });

    window.DT = window.DT || {};
    DT.state = DT.state || {};
    DT.set = (table, key, value) => {
        DT.state[table] ??= {};
        DT.state[table][key] = value;
    };
    DT.get = (table) => DT.state[table] ?? {};
    window.{{ $nameTable }}DataMap = {!! json_encode($data ?? []) !!};
    window.dtState = (table) => {
        const map = window[table + 'DataMap'];
        const state = DT.get(table);
        if (!map) return {};

        return Object.fromEntries(
            Object.entries(map)
            .map(([k, s]) => [k, state[s]])
            .filter(([, v]) => v !== undefined)
        );
    };

    const {{ $nameTable }} = new DataTable('#{{ $nameTable }}-table', {
        stateSave: true,
        processing: true,
        serverSide: true,
        deferRender: true,
        pageLength: 10,
        searchDelay: 500,
        lengthMenu: [
            [10, 50, 100, 500, -1],
            [10, 50, 100, 500, 'All']
        ],

        language: {
            paginate: {
                previous: "<i class='fas fa-angle-left'></i>",
                next: "<i class='fas fa-angle-right'></i>"
            }
        },

        ajax: {
            url: "{{ $url }}",
            data(d) {
                Object.assign(d, dtState('{{ $nameTable }}'));
            },
            error: (xhr, err, thrown) => {
                if (typeof notification === "function") {
                    notification({
                        status: xhr.status,
                        message: (xhr.responseJSON?.message ??
                            'Gagal memuat data {{ !empty($title) ?? $title }}')
                    });
                } else {
                    alert("Terjadi kesalahan: " + err);
                }
            }
        },
        layout: {
            top: ['pageLength', 'buttons', 'search'],
            topStart: null,
            topEnd: null
        },

        buttons: [{
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i>',
                className: 'btn btn-warning',
                titleAttr: 'Download as Excel'
            }
            @if ($tableCanWrite)
                @accessJabatan($jabatanAccessAdd)
                    , {
                text: '<i class="ni ni-folder-17"></i>',
                className: 'btn btn-success',
                titleAttr: 'Tambah Dokumen',
                action: () => ModalAddData()
            }
                @endaccessJabatan
            @endif
        ],
        columns: {{ $nameTable . 'columns' }},
        initComplete: function() {
            $('.dt-buttons').addClass('text-center mt-3');
        },
        order: [
            [1, 'desc']
        ],
    });
</script>
