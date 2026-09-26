<?php

declare(strict_types=1);

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\User;
use App\Services\Document\DocumentDetailContractBuilder;
use App\Services\Document\DocumentOrganizationScope;
use App\Services\Esign\LsSppSigningActionResolver;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Support\EncryptedId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class Detail extends Controller
{
    private ?int $selectedYear = null;

    public function __construct(
        private readonly PositionIdentityResolver $positionIdentityResolver,
        private readonly DocumentOrganizationScope $documentOrganizationScope,
        private readonly DocumentDetailContractBuilder $documentDetailContractBuilder,
        private readonly LsSppSigningActionResolver $lsSppSigningActionResolver,
    ) {}

    public function detail(Request $request, ActivePositionService $user): JsonResponse
    {
        $startedAt = microtime(true);

        Log::channel('module_document_data')->debug('Document Detail Request', [
            'hash' => $request->id,
        ]);

        if (! $request->id) {
            Log::channel('module_document_data')->warning('Document Detail Missing ID');

            return response()->json([
                'status' => 400,
                'message' => 'Parameter tidak valid',
            ], 400);
        }

        try {
            $id = EncryptedId::decode($request->id);
        } catch (\Throwable $e) {
            Log::channel('module_document_data')->warning('Document Detail Invalid ID', [
                'hash' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 422,
                'message' => 'ID Dokumen tidak valid',
            ], 422);
        }

        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json([
                'status' => 401,
                'message' => 'Sesi pengguna tidak valid',
            ], 401);
        }

        $userData = $user->get();
        if (! $userData || ! $userData->jabatan) {
            Log::channel('module_document_data')->warning('Document Detail Invalid Actor Position');

            return response()->json([
                'status' => 403,
                'message' => 'Posisi aktif tidak valid',
            ], 403);
        }

        $userLevel = (int) $userData->jabatan->id;
        $this->selectedYear = $user->selectedYear();
        $includeDeleted = $userLevel === 13;
        $userUnit = $userData->unitKerja?->id;
        $actorIds = in_array($userLevel, [4, 8], true)
            ? $this->positionIdentityResolver->equivalentIds(
                $this->positionIdentityResolver->pptkActorPosition($userData),
            )
            : [];
        $actorBudIds = in_array($userLevel, [2, 3], true)
            ? $this->positionIdentityResolver->equivalentIds(
                $this->positionIdentityResolver->budActorPosition($userData),
            )
            : [];
        $scopeUnitId = $userUnit ? $this->resolveScopeUnitId((int) $userUnit) : null;
        $familyIds = $this->resolveDetailFamilyIds($id, $includeDeleted);
        $hasFamilySp2dTargetAccess = in_array($userLevel, [2, 3], true)
            ? $this->familyHasSp2dTargetAccess($familyIds, $actorBudIds)
            : false;
        $hasFamilyVerifierAccess = $userLevel === 4
            ? $this->familyHasVerifierAccess($familyIds, $actorIds)
            : false;
        if (! in_array($userLevel, [1, 13], true) && ! $userUnit) {
            Log::channel('module_document_data')->warning('Document Detail Missing User Unit', [
                'doc_id' => $id,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Unit kerja tidak ditemukan',
            ], 403);
        }

        $accessibleQuery = $this->documentQuery($includeDeleted)
            ->join('unit_kerjas', 'unit_kerjas.id', '=', 'document.id_unit_kerja')
            ->whereIn('document.id', $familyIds)
            ->when($userLevel === 1, function ($query) {
                $query->where(function ($scope) {
                    $scope->where('document.payment_type', '!=', 'UP')
                        ->where(function ($rule) {
                            $rule->where('document.payment_type', '!=', 'GU_UK')
                                ->orWhereNotIn('document.src_type', ['LPJ_BPP', 'LPJ', 'SPP', 'BMD']);
                        });
                });
            })
            ->when(! in_array($userLevel, [1, 13], true), function ($query) use ($id, $userUnit, $userLevel, $actorIds, $actorBudIds, $scopeUnitId, $hasFamilySp2dTargetAccess, $hasFamilyVerifierAccess) {
                if ($hasFamilySp2dTargetAccess || $hasFamilyVerifierAccess) {
                    return $query;
                }

                $query->where(function ($scope) use ($id, $userUnit, $userLevel, $actorIds, $actorBudIds, $scopeUnitId) {
                    $scope->where(function ($nonGuUk) use ($userUnit, $userLevel, $actorIds, $actorBudIds, $scopeUnitId) {
                        $nonGuUk->whereNotIn('document.payment_type', ['GU_UK', 'TU'])
                            ->where(function ($rule) use ($userUnit, $userLevel, $actorIds, $actorBudIds, $scopeUnitId) {
                                if ($userLevel === 8) {
                                    $rule->where(function ($pptkScope) use ($userUnit, $actorIds) {
                                        $pptkScope->where('document.payment_type', 'GU_SKPD')
                                            ->where('document.src_type', 'NPD')
                                            ->where('document.id_unit_kerja', $userUnit)
                                            ->whereIn('document.uploaded_by', $actorIds);
                                    })->orWhere(function ($defaultScope) use ($userUnit, $userLevel) {
                                        $defaultScope->where(function ($excludeGuSkpdNpd) {
                                            $excludeGuSkpdNpd->where('document.payment_type', '!=', 'GU_SKPD')
                                                ->orWhere('document.src_type', '!=', 'NPD');
                                        })->where(function ($defaultRule) use ($userUnit, $userLevel) {
                                            $defaultRule->where('document.id_unit_kerja', $userUnit)
                                                ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit))
                                                ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", [(string) $userLevel]);
                                        });
                                    });

                                    return;
                                }

                                if (in_array($userLevel, [5, 9], true)) {
                                    $rule->where(function ($tbpScope) use ($userUnit) {
                                        $tbpScope->where('document.payment_type', 'GU_SKPD')
                                            ->where('document.src_type', 'TBP')
                                            ->where(function ($tbpRule) use ($userUnit) {
                                                $tbpRule->where('document.id_unit_kerja', $userUnit)
                                                    ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit));
                                            });
                                    })->orWhere(function ($defaultScope) use ($userUnit, $userLevel, $scopeUnitId) {
                                        $defaultScope->where(function ($excludeGuSkpdTbp) {
                                            $excludeGuSkpdTbp->where('document.payment_type', '!=', 'GU_SKPD')
                                                ->orWhere('document.src_type', '!=', 'TBP');
                                        })->where(function ($defaultRule) use ($userUnit, $userLevel, $scopeUnitId) {
                                            $defaultRule->where('document.id_unit_kerja', $userUnit)
                                                ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit))
                                                ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", [(string) $userLevel]);

                                            $this->applyLsParentUnitDetailScope($defaultRule, $userLevel, $scopeUnitId);
                                        });
                                    });

                                    return;
                                }

                                if (in_array($userLevel, [5, 7, 9], true)) {
                                    $rule->where(function ($lpjScope) use ($userUnit) {
                                        $lpjScope->where('document.payment_type', 'GU_SKPD')
                                            ->where('document.src_type', 'LPJ')
                                            ->where(function ($lpjRule) use ($userUnit) {
                                                $lpjRule->where('document.id_unit_kerja', $userUnit)
                                                    ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit));
                                            });
                                    })->orWhere(function ($defaultScope) use ($userUnit, $userLevel) {
                                        $defaultScope->where(function ($excludeGuSkpdLpj) {
                                            $excludeGuSkpdLpj->where('document.payment_type', '!=', 'GU_SKPD')
                                                ->orWhere('document.src_type', '!=', 'LPJ');
                                        })->where(function ($defaultRule) use ($userUnit, $userLevel) {
                                            $defaultRule->where('document.id_unit_kerja', $userUnit)
                                                ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit))
                                                ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", [(string) $userLevel]);
                                        });
                                    });

                                    return;
                                }

                                if (in_array($userLevel, [4, 5, 7], true)) {
                                    $rule->where(function ($spmScope) use ($userUnit) {
                                        $spmScope->where('document.payment_type', 'GU_SKPD')
                                            ->whereIn('document.src_type', ['SPM', 'SP', 'SPTJM', 'SP_PENGAJUAN'])
                                            ->where(function ($spmRule) use ($userUnit) {
                                                $spmRule->where('document.id_unit_kerja', $userUnit)
                                                    ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit));
                                            });
                                    })->orWhere(function ($defaultScope) use ($userUnit, $userLevel) {
                                        $defaultScope->where(function ($excludeGuSkpdSpm) {
                                            $excludeGuSkpdSpm->where('document.payment_type', '!=', 'GU_SKPD')
                                                ->orWhereNotIn('document.src_type', ['SPM', 'SP', 'SPTJM', 'SP_PENGAJUAN']);
                                        })->where(function ($defaultRule) use ($userUnit, $userLevel) {
                                            $defaultRule->where('document.id_unit_kerja', $userUnit)
                                                ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit))
                                                ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", [(string) $userLevel]);
                                        });
                                    });

                                    return;
                                }

                                if (in_array($userLevel, [2, 3], true)) {
                                    $rule->where(function ($sp2dScope) use ($actorBudIds) {
                                        $sp2dScope->where('document.payment_type', 'GU_SKPD')
                                            ->where('document.src_type', 'SP2D')
                                            ->whereIn('document.users_to', $actorBudIds);
                                    })->orWhere(function ($defaultScope) use ($userUnit, $userLevel) {
                                        $defaultScope->where(function ($excludeGuSkpdSp2d) {
                                            $excludeGuSkpdSp2d->where('document.payment_type', '!=', 'GU_SKPD')
                                                ->orWhere('document.src_type', '!=', 'SP2D');
                                        })->where(function ($defaultRule) use ($userUnit, $userLevel) {
                                            $defaultRule->where('document.id_unit_kerja', $userUnit)
                                                ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit))
                                                ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", [(string) $userLevel]);
                                        });
                                    });

                                    return;
                                }

                                $rule->where('document.id_unit_kerja', $userUnit)
                                    ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit))
                                    ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", [(string) $userLevel]);

                                $this->applyLsParentUnitDetailScope($rule, $userLevel, $scopeUnitId);
                            });
                    })->orWhere(function ($guUk) use ($id, $userUnit, $userLevel, $actorIds, $actorBudIds, $scopeUnitId) {
                        $guUk->where('document.payment_type', 'GU_UK')
                            ->where(function ($rule) use ($id, $userUnit, $userLevel, $actorIds, $actorBudIds, $scopeUnitId) {
                                if ($userLevel === 8) {
                                    $rule->where('document.src_type', 'NPD')
                                        ->where('document.id_unit_kerja', $userUnit)
                                        ->whereIn('document.uploaded_by', $actorIds);

                                    return;
                                }

                                if (in_array($userLevel, [2, 3], true)) {
                                    $rule->where(function ($scope) use ($id, $actorBudIds) {
                                        $scope->where('document.src_type', 'SP2D')
                                            ->whereIn('document.users_to', $actorBudIds)
                                            ->orWhereExists(function ($sub) use ($id, $actorBudIds) {
                                                $sub->select(DB::raw(1))
                                                    ->from('document as family_docs')
                                                    ->whereNull('family_docs.deleted_at')
                                                    ->whereYear('family_docs.created_at', $this->selectedYear)
                                                    ->where('family_docs.payment_type', 'GU_UK')
                                                    ->where('family_docs.src_type', 'SP2D')
                                                    ->whereIn('family_docs.users_to', $actorBudIds)
                                                    ->where(function ($family) use ($id) {
                                                        $family->where('family_docs.id', $id)
                                                            ->orWhere('family_docs.reference_id', $id);
                                                    });
                                            });
                                    });

                                    return;
                                }

                                if ($userLevel === 4) {
                                    $rule->where(function ($scope) use ($id, $userUnit) {
                                        $scope->where('document.id_unit_kerja', $userUnit)
                                            ->orWhereExists(function ($sub) use ($id) {
                                                $sub->select(DB::raw(1))
                                                    ->from('document as family_docs')
                                                    ->whereNull('family_docs.deleted_at')
                                                    ->whereYear('family_docs.created_at', $this->selectedYear)
                                                    ->where('family_docs.payment_type', 'GU_UK')
                                                    ->where(function ($family) use ($id) {
                                                        $family->where('family_docs.id', $id)
                                                            ->orWhere('family_docs.reference_id', $id);
                                                    })
                                                    ->whereRaw("FIND_IN_SET('4', COALESCE(family_docs.assigned_to, ''))");
                                            });
                                    });

                                    return;
                                }

                                if (in_array($userLevel, [9, 5], true) && $scopeUnitId) {
                                    $rule->where(function ($scope) use ($scopeUnitId) {
                                        $scope->where('document.id_unit_kerja', $scopeUnitId)
                                            ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $scopeUnitId));
                                    });

                                    return;
                                }

                                $rule->where('document.id_unit_kerja', $userUnit)
                                    ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", [(string) $userLevel]);
                            });
                    })->orWhere(function ($tu) use ($userUnit, $userLevel, $actorIds) {
                        $tu->where('document.payment_type', 'TU')
                            ->where(function ($rule) use ($userUnit, $userLevel, $actorIds) {
                                if ($userLevel === 8) {
                                    $rule->where(function ($ownScope) use ($actorIds) {
                                        $ownScope->where(function ($spp) use ($actorIds) {
                                            $spp->where('document.src_type', 'SPP')
                                                ->whereIn('document.users_to', $actorIds);
                                        })->orWhere(function ($pengajuan) use ($actorIds) {
                                            $pengajuan->where('document.src_type', 'PENGAJUAN')
                                                ->whereIn('document.uploaded_by', $actorIds);
                                        });
                                    });

                                    return;
                                }

                                if ($userLevel === 4) {
                                    $rule->where(function ($scope) use ($userUnit, $userLevel) {
                                        $scope->where(function ($pengajuan) {
                                            $pengajuan->where('document.src_type', 'PENGAJUAN')
                                                ->where(function ($access) {
                                                    $access->whereRaw("FIND_IN_SET('4', COALESCE(document.assigned_to, ''))")
                                                        ->orWhereRaw("FIND_IN_SET('4', COALESCE(document.submit, ''))")
                                                        ->orWhereRaw("FIND_IN_SET('5', COALESCE(document.submit, ''))")
                                                        ->orWhereRaw("FIND_IN_SET('6', COALESCE(document.submit, ''))");
                                                });
                                        })->orWhere(function ($defaultScope) use ($userUnit, $userLevel) {
                                            $defaultScope->where('document.id_unit_kerja', $userUnit)
                                                ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", [(string) $userLevel]);
                                        });
                                    });

                                    return;
                                }

                                if ($userLevel === 2) {
                                    $rule->where(function ($scope) use ($userUnit, $userLevel) {
                                        $scope->where(function ($pengajuan) {
                                            $pengajuan->where('document.src_type', 'PENGAJUAN')
                                                ->where(function ($access) {
                                                    $access->whereRaw("FIND_IN_SET('2', COALESCE(document.assigned_to, ''))")
                                                        ->orWhere(function ($history) {
                                                            $history->whereRaw("FIND_IN_SET('2', COALESCE(document.status, ''))")
                                                                ->whereRaw("FIND_IN_SET('4', COALESCE(document.submit, ''))");
                                                        });
                                                });
                                        })->orWhere(function ($defaultScope) use ($userUnit, $userLevel) {
                                            $defaultScope->where('document.id_unit_kerja', $userUnit)
                                                ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", [(string) $userLevel]);
                                        });
                                    });

                                    return;
                                }

                                $rule->where('document.id_unit_kerja', $userUnit)
                                    ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", [(string) $userLevel]);
                            });
                    });
                });
            })
            ->when(in_array($userLevel, [5], true), function ($query) {
                $query->where(function ($scope) {
                    $scope->where('document.payment_type', '!=', 'GU_UK')
                        ->orWhere('document.src_type', '!=', 'LPJ_BPP');
                });
            });

        $dataQuery = (clone $accessibleQuery)
            ->with('pdfDeliveryArtifacts')
            ->select('document.*', 'unit_kerjas.nama as unit_kerja')
            ->get();

        if ($dataQuery->isEmpty()) {
            Log::channel('module_document_data')->warning('Document Detail Access Denied', [
                'doc_id' => $id,
            ]);

            return response()->json([
                'status' => 403,
                'message' => 'Anda tidak memiliki akses ke dokumen ini',
            ], 403);
        }

        Log::channel('module_document_data')->debug('Document Detail Data Loaded', [
            'doc_id' => $id,
            'count' => $dataQuery->count(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $canonicalCapabilities = $this->lsSppSigningActionResolver
            ->capabilitiesForDocumentIds(
                $dataQuery->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all(),
                $actor,
            );

        $dataQuery->each(static function (Document $document) use ($canonicalCapabilities): void {
            $capabilities = $canonicalCapabilities[(int) $document->id] ?? [];
            $document->setAttribute(
                'esign_step_public_id',
                $capabilities['step_public_id'] ?? null,
            );
        });

        return DataTables::of($dataQuery)
            ->addIndexColumn()
            ->addColumn('document_contract', function (Document $data) use ($actor): array {
                return $this->documentDetailContractBuilder->build(
                    document: $data,
                    actor: $actor,
                    stepPublicId: is_string($data->esign_step_public_id ?? null)
                        ? $data->esign_step_public_id
                        : null,
                )->toArray();
            })
            ->make(true);
    }

    private function applyLsParentUnitDetailScope($query, int $userLevel, ?int $scopeUnitId): void
    {
        if (! in_array($userLevel, [9, 10], true) || ! $scopeUnitId) {
            return;
        }

        // Keep access limited to the current family detail, while allowing child units
        // to inspect parent-unit LS package files that belong to the same package.
        $query->orWhere(function ($parentUnitScope) use ($scopeUnitId) {
            $parentUnitScope->whereIn('document.payment_type', ['LS', 'LS_GAJI'])
                ->whereIn('document.src_type', ['SP', 'SPM', 'SPTJM', 'SP_PENGAJUAN'])
                ->where('document.id_unit_kerja', $scopeUnitId);
        });
    }

    private function resolveScopeUnitId(int $unitKerjaId): ?int
    {
        return $this->documentOrganizationScope->scopeUnitIdForUnit($unitKerjaId);
    }

    private function resolveDetailFamilyIds(int $documentId, bool $includeDeleted = false): array
    {
        $anchorId = $this->resolveDetailAnchorId($documentId, $includeDeleted);
        $familyIds = [$anchorId => true];
        $queue = [$anchorId];

        while (! empty($queue)) {
            $children = $this->documentQuery($includeDeleted)
                ->whereIn('reference_id', $queue)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $queue = [];

            foreach ($children as $childId) {
                if (isset($familyIds[$childId])) {
                    continue;
                }

                $familyIds[$childId] = true;
                $queue[] = $childId;
            }
        }

        $familyIds[$documentId] = true;

        return array_keys($familyIds);
    }

    private function resolveDetailAnchorId(int $documentId, bool $includeDeleted = false): int
    {
        $visited = [];
        $currentId = $documentId;
        $anchorId = $documentId;

        while (! isset($visited[$currentId])) {
            $visited[$currentId] = true;

            $document = $this->documentQuery($includeDeleted)
                ->select(['id', 'reference_id'])
                ->find($currentId);

            if (! $document) {
                break;
            }

            $anchorId = (int) $document->id;
            $nextId = $document->reference_id ? (int) $document->reference_id : null;

            if (! $nextId || $nextId === $anchorId) {
                break;
            }

            $currentId = $nextId;
        }

        return $anchorId;
    }

    private function documentQuery(bool $includeDeleted = false): Builder
    {
        $query = $includeDeleted
            ? Document::withTrashed()
            : Document::query();

        return $query->when(
            $this->selectedYear !== null,
            fn (Builder $query): Builder => $query->whereYear('document.created_at', $this->selectedYear),
        );
    }

    private function familyHasSp2dTargetAccess(array $familyIds, array $actorBudIds): bool
    {
        if (empty($familyIds)) {
            return false;
        }

        return Document::query()
            ->whereNull('deleted_at')
            ->when($this->selectedYear !== null, fn (Builder $query): Builder => $query->whereYear('document.created_at', $this->selectedYear))
            ->whereIn('id', $familyIds)
            ->where('src_type', 'SP2D')
            ->whereIn('users_to', $actorBudIds)
            ->exists();
    }

    private function familyHasVerifierAccess(array $familyIds, array $actorIds): bool
    {
        if (empty($familyIds)) {
            return false;
        }

        return Document::query()
            ->whereNull('deleted_at')
            ->when($this->selectedYear !== null, fn (Builder $query): Builder => $query->whereYear('document.created_at', $this->selectedYear))
            ->whereIn('id', $familyIds)
            ->where(function ($query) use ($actorIds) {
                $query->whereRaw("FIND_IN_SET('4', COALESCE(assigned_to, ''))")
                    ->orWhere(function ($tuPengajuan) {
                        $tuPengajuan->where('payment_type', 'TU')
                            ->where('src_type', 'PENGAJUAN')
                            ->where(function ($access) {
                                $access->whereRaw("FIND_IN_SET('4', COALESCE(submit, ''))")
                                    ->orWhereRaw("FIND_IN_SET('5', COALESCE(submit, ''))")
                                    ->orWhereRaw("FIND_IN_SET('6', COALESCE(submit, ''))");
                            });
                    })
                    ->orWhere(function ($sp2dOwn) use ($actorIds) {
                        $sp2dOwn->where('src_type', 'SP2D')
                            ->whereIn('uploaded_by', $actorIds);
                    });
            })
            ->exists();
    }
}
