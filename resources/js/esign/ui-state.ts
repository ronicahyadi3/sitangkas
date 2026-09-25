export type EsignShellStage =
    | 'loading'
    | 'editing'
    | 'preparing_rendition'
    | 'confirming_prepared'
    | 'submitting'
    | 'processing'
    | 'succeeded'
    | 'requires_passphrase'
    | 'unknown'
    | 'failed';

export type EsignShellStep = 'placement' | 'confirmation' | 'process' | 'result';

export interface EsignStagePresentation {
    badgeClass: string;
    label: string;
    step: EsignShellStep;
}

const STAGE_PRESENTATIONS: Record<EsignShellStage, EsignStagePresentation> = {
    loading: {
        badgeClass: 'bg-gradient-info',
        label: 'Menyiapkan',
        step: 'placement',
    },
    editing: {
        badgeClass: 'bg-gradient-info',
        label: 'Atur posisi',
        step: 'placement',
    },
    preparing_rendition: {
        badgeClass: 'bg-gradient-info',
        label: 'Menyiapkan final',
        step: 'placement',
    },
    confirming_prepared: {
        badgeClass: 'bg-gradient-primary',
        label: 'Konfirmasi',
        step: 'confirmation',
    },
    submitting: {
        badgeClass: 'bg-gradient-primary',
        label: 'Mengirim',
        step: 'confirmation',
    },
    processing: {
        badgeClass: 'bg-gradient-info',
        label: 'Diproses',
        step: 'process',
    },
    succeeded: {
        badgeClass: 'bg-gradient-success',
        label: 'Berhasil',
        step: 'result',
    },
    requires_passphrase: {
        badgeClass: 'bg-gradient-warning',
        label: 'Perlu tindakan',
        step: 'result',
    },
    unknown: {
        badgeClass: 'bg-gradient-warning',
        label: 'Perlu pemeriksaan',
        step: 'result',
    },
    failed: {
        badgeClass: 'bg-gradient-danger',
        label: 'Gagal',
        step: 'result',
    },
};

export function stagePresentation(stage: EsignShellStage): EsignStagePresentation {
    return STAGE_PRESENTATIONS[stage];
}

export function stageBlocksClose(stage: EsignShellStage): boolean {
    return stage === 'preparing_rendition' || stage === 'submitting';
}

export function stageIsBusy(stage: EsignShellStage): boolean {
    return stage === 'loading'
        || stage === 'preparing_rendition'
        || stage === 'submitting'
        || stage === 'processing';
}
