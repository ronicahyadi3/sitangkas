<div class="modal fade" id="pdfViewerModal" style="z-index: 1051;" tabindex="-1" aria-labelledby="pdfViewerModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pdfViewerModalLabel">Dokumen PDF</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <legend class="form-control-label mb-1">Status :</legend>
                <div class="card-body rounded bg-info p-3">
                    <span id="pdf-status-text" class="text-black"></span>
                </div>

                <div class="table-responsive pt-3">
                    <table class="table align-items-center table-flush mb-0" id="pdf-signature-table">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Nama</th>
                                <th scope="col">Keterangan</th>
                                <th scope="col">Tanggal Signature</th>
                            </tr>
                        </thead>
                        <tbody id="pdf-signature-body"></tbody>
                    </table>
                </div>

                <div id="pdfViewer" class="pt-3"></div>
            </div>
        </div>
    </div>
</div>

<script>
    const pdfValidationCache = new Map();
    const pdfBlobCache = new Map();
    const pdfViewerUrlCache = new Map();

    $(function() {
        const $pdfViewerModalEl = $('#pdfViewerModal');
        const pdfViewerModal = bootstrap.Modal.getOrCreateInstance($pdfViewerModalEl[0]);
        const $pdfViewer = $('#pdfViewer');
        const $overlayView = $('#overlay-view');
        const $statusText = $('#pdf-status-text');
        const $signatureTableBody = $('#pdf-signature-body');
        const validateUrl = "{{ route('esign.validate') }}";

        function loadPdfIntoViewer(viewerUrl) {
            return new Promise((resolve) => {
                $pdfViewer.empty();
                const embed = document.createElement('embed');
                embed.src = `${viewerUrl}#toolbar=0`;
                embed.type = 'application/pdf';
                embed.width = '100%';
                embed.height = '600';
                embed.onload = () => resolve();
                $pdfViewer.append(embed);
            });
        }

        async function getPdfBlob(pdfUrl) {
            if (pdfBlobCache.has(pdfUrl)) {
                return pdfBlobCache.get(pdfUrl);
            }

            const response = await fetch(pdfUrl);
            if (!response.ok) {
                throw new Error(`Gagal mengambil PDF: ${response.status}`);
            }

            const blob = await response.blob();
            pdfBlobCache.set(pdfUrl, blob);
            return blob;
        }

        function getViewerUrl(pdfUrl, blob) {
            if (pdfViewerUrlCache.has(pdfUrl)) {
                return pdfViewerUrlCache.get(pdfUrl);
            }

            const objectUrl = URL.createObjectURL(blob);
            pdfViewerUrlCache.set(pdfUrl, objectUrl);
            return objectUrl;
        }

        function renderValidationResult(parsed) {
            $statusText.html('');
            $signatureTableBody.empty();
            $('#pdf-signature-table').show();

            if (!parsed || !Array.isArray(parsed[0]) || parsed[0].length === 0) {
                $statusText.text('Dokumen tidak memiliki signature.');
                $('#pdf-signature-table').hide();
                return;
            }

            const signatures = parsed[0];
            const infoText = parsed[1] ?? '';

            $statusText.html(`Dokumen memiliki: ${signatures.length} signature<br>${infoText}`);

            let rowsHtml = '';
            signatures.forEach(([name, timestamp, status]) => {
                rowsHtml += `
                    <tr>
                        <th scope="row">
                            <div class="media align-items-center">
                                <div class="media-body">
                                    <span class="name mb-0 text-sm">${name}</span>
                                </div>
                            </div>
                        </th>
                        <td><span class="status">${status}</span></td>
                        <td class="budget">${timestamp}</td>
                    </tr>
                `;
            });
            $signatureTableBody.append(rowsHtml);
        }

        async function validatePdf(pdfUrl, blob) {
            if (pdfValidationCache.has(pdfUrl)) {
                return pdfValidationCache.get(pdfUrl);
            }

            try {
                const formData = new FormData();
                formData.append('pdf', blob, 'document.pdf');

                const result = await $.ajax({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    url: validateUrl,
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    contentType: false,
                    processData: false,
                    cache: false
                });

                pdfValidationCache.set(pdfUrl, result);
                return result;
            } catch (err) {
                notification({
                    status: 500,
                    message: 'Gagal memvalidasi dokumen.'
                });
                return null;
            }
        }


        $(document).on('click', '.view-pdf', async function(e) {
            e.preventDefault();

            const pdfUrl = $(this).data('url');
            if (!pdfUrl) {
                notification({
                    status: 400,
                    message: 'URL dokumen tidak valid.'
                });
                return;
            }

            $statusText.html('<i class="fa-solid fa-gear fa-spin"></i> Memvalidasi dokumen...');
            $signatureTableBody.empty();
            $overlayView.show();

            pdfViewerModal.show();

            try {
                const blob = await getPdfBlob(pdfUrl);
                const viewerUrl = getViewerUrl(pdfUrl, blob);

                const pdfLoadPromise = loadPdfIntoViewer(viewerUrl);
                const validationPromise = (async () => {
                    const validationResult = await validatePdf(pdfUrl, blob);

                    if (!validationResult) {
                        $statusText.text('Gagal memvalidasi dokumen.');
                        return;
                    }

                    renderValidationResult(validationResult);
                })();

                await Promise.all([pdfLoadPromise, validationPromise]);
            } catch (err) {
                notification({
                    status: 500,
                    message: 'Gagal memuat dokumen PDF.'
                });
                $statusText.text('Gagal memuat dokumen PDF.');
            }

            $overlayView.hide();
        });

        // Bersihkan viewer saat modal ditutup
        $pdfViewerModalEl.on('hidden.bs.modal', function() {
            $pdfViewer.empty();
        });
    });
</script>
