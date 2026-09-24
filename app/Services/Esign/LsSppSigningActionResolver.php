<?php

declare(strict_types=1);

namespace App\Services\Esign;

use App\Enums\Esign\DocumentSigningStepStatus;
use App\Enums\Esign\DocumentSigningWorkflowStatus;
use App\Enums\Esign\EsignAttemptStatus;
use App\Models\Document;
use App\Models\User;
use App\Models\UserPosition;
use App\Services\Auth\CurrentUserContext;
use App\Services\User\YearAccessService;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class LsSppSigningActionResolver
{
    public function __construct(
        private ConfigRepository $config,
        private CurrentUserContext $currentUserContext,
        private YearAccessService $yearAccess,
        private Request $request,
    ) {}

    public function addSignableStep(Builder $documents, User $actor): Builder
    {
        $realPosition = $this->currentUserContext->realActivePosition($this->request);
        $selectedYear = $this->yearAccess->selectedYear();

        if (! $this->frontendIsReady()
            || ! $actor->isActive()
            || $actor->isLocked()
            || ! $realPosition instanceof UserPosition
            || ! $realPosition->isAvailableForSelection()
            || (int) $realPosition->user_id !== (int) $actor->getKey()
            || $this->currentUserContext->effectiveContextIsActing($this->request)
            || ! $this->yearAccess->canWrite($realPosition, $selectedYear)) {
            return $this->withoutSignableStep($documents);
        }

        $realPosition->loadMissing('jabatan');
        $roleCode = $this->canonicalRole((string) $realPosition->jabatan?->kode);

        if ($roleCode === '') {
            return $this->withoutSignableStep($documents);
        }

        $blockingAttemptStatuses = [
            EsignAttemptStatus::Prepared->value,
            EsignAttemptStatus::Signing->value,
            EsignAttemptStatus::PartiallySigned->value,
            EsignAttemptStatus::Validating->value,
            EsignAttemptStatus::Succeeded->value,
            EsignAttemptStatus::Unknown->value,
        ];

        $eligibleStep = DB::table('document_signing_steps as esign_step')
            ->join(
                'document_signing_workflows as esign_workflow',
                'esign_workflow.id',
                '=',
                'esign_step.document_signing_workflow_id',
            )
            ->join(
                'document_artifacts as esign_artifact',
                'esign_artifact.id',
                '=',
                'esign_workflow.current_artifact_id',
            )
            ->select('esign_step.public_id')
            ->whereColumn('esign_workflow.document_id', 'document.id')
            ->whereColumn('esign_workflow.root_document_id', 'document.id')
            ->whereColumn('esign_step.source_artifact_id', 'esign_workflow.current_artifact_id')
            ->whereColumn('esign_artifact.document_id', 'esign_workflow.document_id')
            ->where('esign_workflow.payment_type', 'LS')
            ->where('esign_workflow.document_type', Document::TYPE_SPP)
            ->where('esign_step.step_type', 'sign')
            ->where('esign_step.assigned_user_id', $actor->getKey())
            ->where('esign_step.assigned_user_position_id', $realPosition->getKey())
            ->where(function (Builder $query) use ($realPosition): void {
                $query->whereNull('esign_step.assigned_unit_kerja_id')
                    ->orWhere('esign_step.assigned_unit_kerja_id', $realPosition->unit_kerja_id);
            })
            ->where(function (Builder $query) use ($realPosition): void {
                $query->whereNull('esign_step.assigned_instansi_id')
                    ->orWhere('esign_step.assigned_instansi_id', $realPosition->instansi_id);
            })
            ->whereRaw(
                "UPPER(REPLACE(TRIM(esign_step.role_code), '-', '_')) = ?",
                [$roleCode],
            )
            ->where('esign_artifact.is_current', true)
            ->where('esign_artifact.document_year', $selectedYear)
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('document_signing_workflows as newer_workflow')
                    ->whereColumn('newer_workflow.document_id', 'esign_workflow.document_id')
                    ->where(function (Builder $query): void {
                        $query->whereColumn('newer_workflow.cycle_number', '>', 'esign_workflow.cycle_number')
                            ->orWhere(function (Builder $query): void {
                                $query->whereColumn('newer_workflow.cycle_number', 'esign_workflow.cycle_number')
                                    ->whereColumn('newer_workflow.id', '>', 'esign_workflow.id');
                            });
                    });
            })
            ->where(function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('esign_workflow.status', DocumentSigningWorkflowStatus::Active->value)
                        ->where('esign_step.status', DocumentSigningStepStatus::Active->value)
                        ->whereColumn('esign_step.sequence', 'esign_workflow.current_sequence')
                        ->whereNotExists(function (Builder $query): void {
                            $query->selectRaw('1')
                                ->from('document_signing_steps as previous_step')
                                ->whereColumn(
                                    'previous_step.document_signing_workflow_id',
                                    'esign_step.document_signing_workflow_id',
                                )
                                ->whereColumn('previous_step.sequence', '<', 'esign_step.sequence')
                                ->whereNotIn('previous_step.status', [
                                    DocumentSigningStepStatus::Completed->value,
                                    DocumentSigningStepStatus::Skipped->value,
                                ]);
                        });
                })->orWhere(function (Builder $query): void {
                    $query->where('esign_workflow.status', DocumentSigningWorkflowStatus::Draft->value)
                        ->whereNull('esign_workflow.current_sequence')
                        ->where('esign_step.status', DocumentSigningStepStatus::Pending->value)
                        ->where('esign_step.sequence', 1)
                        ->whereIn('esign_step.role_code', ['BP', 'BPP'])
                        ->whereNotExists(function (Builder $query): void {
                            $query->selectRaw('1')
                                ->from('document_signing_steps as non_pending_step')
                                ->whereColumn(
                                    'non_pending_step.document_signing_workflow_id',
                                    'esign_step.document_signing_workflow_id',
                                )
                                ->where('non_pending_step.status', '!=', DocumentSigningStepStatus::Pending->value);
                        })
                        ->whereNotExists(function (Builder $query): void {
                            $query->selectRaw('1')
                                ->from('esign_attempts as workflow_attempt')
                                ->join(
                                    'document_signing_steps as attempted_step',
                                    'attempted_step.id',
                                    '=',
                                    'workflow_attempt.document_signing_step_id',
                                )
                                ->whereColumn(
                                    'attempted_step.document_signing_workflow_id',
                                    'esign_step.document_signing_workflow_id',
                                );
                        });
                });
            })
            ->whereNotExists(function (Builder $query) use ($blockingAttemptStatuses): void {
                $query->selectRaw('1')
                    ->from('esign_attempts as blocking_attempt')
                    ->whereColumn('blocking_attempt.document_signing_step_id', 'esign_step.id')
                    ->whereIn('blocking_attempt.status', $blockingAttemptStatuses);
            })
            ->where(function (Builder $query): void {
                $query->whereNotExists(function (Builder $query): void {
                    $query->selectRaw('1')
                        ->from('esign_attempts as any_attempt')
                        ->whereColumn('any_attempt.document_signing_step_id', 'esign_step.id');
                })->orWhereExists(function (Builder $query): void {
                    $query->selectRaw('1')
                        ->from('esign_attempts as retryable_attempt')
                        ->whereColumn('retryable_attempt.document_signing_step_id', 'esign_step.id')
                        ->where('retryable_attempt.status', EsignAttemptStatus::Failed->value)
                        ->where('retryable_attempt.retryable', true)
                        ->whereRaw(
                            'retryable_attempt.attempt_number = ('.
                            'SELECT MAX(latest_attempt.attempt_number) FROM esign_attempts AS latest_attempt '.
                            'WHERE latest_attempt.document_signing_step_id = esign_step.id'.
                            ')',
                        );
                });
            })
            ->orderByDesc('esign_workflow.cycle_number')
            ->orderByDesc('esign_workflow.id')
            ->limit(1);

        return $documents->addSelect([
            'esign_step_public_id' => $eligibleStep,
        ]);
    }

    /**
     * @return array{step_public_id: string, can_sign: true, can_verify: false}|null
     */
    public function capabilities(object $document): ?array
    {
        $stepPublicId = $document->esign_step_public_id ?? null;

        if (! is_string($stepPublicId) || ! Str::isUuid($stepPublicId)) {
            return null;
        }

        return [
            'step_public_id' => $stepPublicId,
            'can_sign' => true,
            'can_verify' => false,
        ];
    }

    private function withoutSignableStep(Builder $documents): Builder
    {
        return $documents->addSelect(DB::raw('NULL AS esign_step_public_id'));
    }

    private function frontendIsReady(): bool
    {
        return $this->config->get('esign.frontend.enabled') === true
            && $this->config->get('esign.processing.multi_operation_enabled') === true;
    }

    private function canonicalRole(string $roleCode): string
    {
        return Str::of($roleCode)
            ->trim()
            ->upper()
            ->replace('-', '_')
            ->toString();
    }
}
