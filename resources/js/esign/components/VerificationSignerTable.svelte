<script lang="ts">
    import type { ArtifactVerificationSignature } from '../types';

    let { signatures }: { signatures: ArtifactVerificationSignature[] } = $props();

    function formatTimestamp(value: string | null): string {
        if (value === null) {
            return '—';
        }

        const date = new Date(value);

        return Number.isNaN(date.getTime())
            ? '—'
            : new Intl.DateTimeFormat('id-ID', {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(date);
    }

    function booleanLabel(value: boolean | null): string {
        if (value === null) {
            return 'Tidak dilaporkan';
        }

        return value ? 'Valid' : 'Tidak valid';
    }
</script>

{#if signatures.length === 0}
    <div class="esign-verification-empty">
        <i class="fas fa-file-circle-question" aria-hidden="true"></i>
        <p class="mb-0">Tidak ada tanda tangan elektronik pada artifact ini.</p>
    </div>
{:else}
    <div class="table-responsive esign-verification-table-wrap">
        <table class="table align-items-center mb-0 esign-verification-table">
            <thead>
                <tr>
                    <th>Penandatangan</th>
                    <th>Waktu tanda tangan</th>
                    <th>Integritas</th>
                </tr>
            </thead>
            <tbody>
                {#each signatures as signature (signature.index)}
                    <tr>
                        <td>
                            <strong>{signature.signer_name}</strong>
                            {#if signature.reason}
                                <small>{signature.reason}</small>
                            {/if}
                            {#if signature.location}
                                <small><i class="fas fa-location-dot me-1" aria-hidden="true"></i>{signature.location}</small>
                            {/if}
                        </td>
                        <td>{formatTimestamp(signature.signed_at)}</td>
                        <td>
                            <span
                                class:esign-verification-pill--success={signature.integrity_valid === true && signature.certificate_trusted !== false}
                                class:esign-verification-pill--danger={signature.integrity_valid === false || signature.certificate_trusted === false}
                                class="esign-verification-pill"
                            >
                                {booleanLabel(signature.integrity_valid)}
                            </span>
                            <small>Sertifikat: {booleanLabel(signature.certificate_trusted)}</small>
                        </td>
                    </tr>
                {/each}
            </tbody>
        </table>
    </div>
{/if}
