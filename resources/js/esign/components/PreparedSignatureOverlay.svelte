<script lang="ts">
    import type { PdfPageGeometry, PreparedSignatureOperation } from '../types';

    let {
        operation,
        page,
        imageUrl,
        pageWidthPx,
        pageHeightPx,
    }: {
        operation: PreparedSignatureOperation;
        page: PdfPageGeometry;
        imageUrl: string;
        pageWidthPx: number;
        pageHeightPx: number;
    } = $props();

    let left = $derived((operation.origin_x / page.width) * pageWidthPx);
    let top = $derived((operation.origin_y / page.height) * pageHeightPx);
    let width = $derived((operation.width / page.width) * pageWidthPx);
    let height = $derived((operation.height / page.height) * pageHeightPx);
</script>

<div
    class="esign-prepared-signature"
    style:left={`${left}px`}
    style:top={`${top}px`}
    style:width={`${width}px`}
    style:height={`${height}px`}
    aria-label="QR authoritative {operation.operation_index + 1} pada halaman {operation.page}"
>
    <img
        src={imageUrl}
        alt="QR tanda tangan {operation.operation_index + 1} berlogo Kota Malang"
        draggable="false"
    />
    <span aria-hidden="true">{operation.operation_index + 1}</span>
</div>
