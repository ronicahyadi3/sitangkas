<script lang="ts">
    import type { EsignShellStep } from '../ui-state';

    interface SigningStep {
        id: EsignShellStep;
        label: string;
        number: number;
    }

    let { currentStep }: { currentStep: EsignShellStep } = $props();

    const steps: SigningStep[] = [
        { id: 'placement', label: 'Atur Posisi', number: 1 },
        { id: 'confirmation', label: 'Konfirmasi', number: 2 },
        { id: 'process', label: 'Proses', number: 3 },
        { id: 'result', label: 'Selesai', number: 4 },
    ];

    function stepIndex(step: EsignShellStep): number {
        return steps.findIndex((candidate) => candidate.id === step);
    }

    function statusFor(step: SigningStep): 'active' | 'complete' | 'pending' {
        const currentIndex = stepIndex(currentStep);
        const candidateIndex = stepIndex(step.id);

        if (candidateIndex === currentIndex) {
            return 'active';
        }

        return candidateIndex < currentIndex ? 'complete' : 'pending';
    }
</script>

<nav class="esign-stepper" aria-label="Tahapan tanda tangan elektronik">
    <ol class="esign-stepper__list">
        {#each steps as step}
            {@const status = statusFor(step)}
            <li
                class:esign-stepper__item--active={status === 'active'}
                class:esign-stepper__item--complete={status === 'complete'}
                class="esign-stepper__item"
                aria-current={status === 'active' ? 'step' : undefined}
            >
                <span class="esign-stepper__number" aria-hidden="true">
                    {#if status === 'complete'}
                        <i class="fas fa-check"></i>
                    {:else}
                        {step.number}
                    {/if}
                </span>
                <span class="esign-stepper__label">{step.label}</span>
            </li>
        {/each}
    </ol>
</nav>
