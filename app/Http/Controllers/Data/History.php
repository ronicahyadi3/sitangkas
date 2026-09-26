<?php

declare(strict_types=1);

namespace App\Http\Controllers\Data;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentHistory;
use App\Services\Document\DocumentOrganizationScope;
use App\Services\User\ActivePositionService;
use App\Services\User\PositionIdentityResolver;
use App\Support\EncryptedId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;

class History extends Controller
{
    private ?int $selectedYear = null;

    public function __construct(
        private readonly PositionIdentityResolver $positionIdentityResolver,
        private readonly DocumentOrganizationScope $documentOrganizationScope,
    ) {}

    public function history(Request $request, ActivePositionService $activePosition): JsonResponse
    {
        $startedAt = microtime(true);

        Log::channel('module_document_data')->debug('Document History Request', [
            'hash' => $request->id,
        ]);

        if (! $request->id) {
            Log::channel('module_document_data')->warning('Document History Failed: ID missing');

            return response()->json([
                'status' => 'error',
                'message' => 'ID Dokumen Tidak Ditemukan',
            ], 400);
        }

        try {
            $documentId = EncryptedId::decode($request->id);

            Log::channel('module_document_data')->debug('Document History ID decoded', [
                'doc_id' => $documentId,
            ]);
        } catch (\Throwable $e) {

            Log::channel('module_document_data')->warning('Document History Failed: Invalid ID', [
                'hash' => $request->id,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'ID Dokumen Tidak Valid',
            ], 422);
        }

        try {
            $actor = $activePosition->get();
            if (! $actor || ! $actor->jabatan) {
                Log::channel('module_document_data')->warning('Document History Invalid Actor Position', [
                    'doc_id' => $documentId ?? null,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Posisi aktif tidak valid',
                ], 403);
            }

            $userLevel = (int) $actor->jabatan->id;
            $this->selectedYear = $activePosition->selectedYear();
            $includeDeleted = $userLevel === 13;
            $userUnit = $actor->unitKerja?->id;
            $actorIds = in_array($userLevel, [4, 8], true)
                ? $this->positionIdentityResolver->equivalentIds(
                    $this->positionIdentityResolver->pptkActorPosition($actor),
                )
                : [];
            $actorBudIds = in_array($userLevel, [2, 3], true)
                ? $this->positionIdentityResolver->equivalentIds(
                    $this->positionIdentityResolver->budActorPosition($actor),
                )
                : [];
            $scopeUnitId = $userUnit ? $this->resolveScopeUnitId((int) $userUnit) : null;
            $familyIds = $this->resolveHistoryFamilyIds($documentId, $includeDeleted);
            $hasFamilySp2dTargetAccess = in_array($userLevel, [2, 3], true)
                ? $this->familyHasSp2dTargetAccess($familyIds, $actorBudIds)
                : false;
            $hasFamilyVerifierAccess = $userLevel === 4
                ? $this->familyHasVerifierAccess($familyIds, $actorIds)
                : false;
            if (! in_array($userLevel, [1, 13], true) && ! $userUnit) {
                Log::channel('module_document_data')->warning('Document History Missing User Unit', [
                    'doc_id' => $documentId,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Unit kerja tidak ditemukan',
                ], 403);
            }

            Log::channel('module_document_data')->debug('Document History Access Scope', [
                'doc_id' => $documentId,
                'is_admin' => $userLevel === 1,
            ]);

            $accessibleQuery = $this->documentQuery($includeDeleted)
                ->from('document')
                ->join('unit_kerjas as uk', 'uk.id', '=', 'document.id_unit_kerja')
                ->whereIn('document.id', $familyIds)
                ->when($userLevel === 1, function ($q) {
                    $q->where(function ($scope) {
                        $scope->where('document.payment_type', '!=', 'UP')
                            ->where(function ($rule) {
                                $rule->where('document.payment_type', '!=', 'GU_UK')
                                    ->orWhereNotIn('document.src_type', ['LPJ_BPP', 'LPJ', 'SPP', 'BMD']);
                            });
                    });
                })
                ->when(! in_array($userLevel, [1, 13], true), function ($q) use ($userLevel, $userUnit, $actorIds, $actorBudIds, $scopeUnitId, $documentId, $hasFamilySp2dTargetAccess, $hasFamilyVerifierAccess) {
                    if ($hasFamilySp2dTargetAccess || $hasFamilyVerifierAccess) {
                        return $q;
                    }

                    $q->where(function ($scope) use ($userLevel, $userUnit, $actorIds, $actorBudIds, $scopeUnitId, $documentId) {
                        $scope->where(function ($nonGuUk) use ($userLevel, $userUnit, $actorIds, $actorBudIds) {
                            $nonGuUk->whereNotIn('document.payment_type', ['GU_UK', 'TU'])
                                ->where(function ($rule) use ($userLevel, $userUnit, $actorIds, $actorBudIds) {
                                    if ($userLevel === 8) {
                                        $rule->where(function ($pptkScope) use ($userUnit, $actorIds) {
                                            $pptkScope->where('document.payment_type', 'GU_SKPD')
                                                ->where('document.src_type', 'NPD')
                                                ->where('document.id_unit_kerja', $userUnit)
                                                ->whereIn('document.uploaded_by', $actorIds);
                                        })->orWhere(function ($defaultScope) use ($userLevel, $userUnit) {
                                            $defaultScope->where(function ($excludeGuSkpdNpd) {
                                                $excludeGuSkpdNpd->where('document.payment_type', '!=', 'GU_SKPD')
                                                    ->orWhere('document.src_type', '!=', 'NPD');
                                            })->where(function ($defaultRule) use ($userLevel, $userUnit) {
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
                                        })->orWhere(function ($defaultScope) use ($userLevel, $userUnit) {
                                            $defaultScope->where(function ($excludeGuSkpdTbp) {
                                                $excludeGuSkpdTbp->where('document.payment_type', '!=', 'GU_SKPD')
                                                    ->orWhere('document.src_type', '!=', 'TBP');
                                            })->where(function ($defaultRule) use ($userLevel, $userUnit) {
                                                $defaultRule->where('document.id_unit_kerja', $userUnit)
                                                    ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit))
                                                    ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", [(string) $userLevel]);
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
                                        })->orWhere(function ($defaultScope) use ($userLevel, $userUnit) {
                                            $defaultScope->where(function ($excludeGuSkpdLpj) {
                                                $excludeGuSkpdLpj->where('document.payment_type', '!=', 'GU_SKPD')
                                                    ->orWhere('document.src_type', '!=', 'LPJ');
                                            })->where(function ($defaultRule) use ($userLevel, $userUnit) {
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
                                                ->where('document.src_type', 'SPM')
                                                ->where(function ($spmRule) use ($userUnit) {
                                                    $spmRule->where('document.id_unit_kerja', $userUnit)
                                                        ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit));
                                                });
                                        })->orWhere(function ($defaultScope) use ($userLevel, $userUnit, $actorIds) {
                                            $defaultScope->where(function ($excludeGuSkpdSpm) {
                                                $excludeGuSkpdSpm->where('document.payment_type', '!=', 'GU_SKPD')
                                                    ->orWhere('document.src_type', '!=', 'SPM');
                                            })->where(function ($defaultRule) use ($userLevel, $userUnit, $actorIds) {
                                                $defaultRule->where('document.id_unit_kerja', $userUnit)
                                                    ->orWhereIn('document.id_unit_kerja', $this->documentOrganizationScope->accessibleUnitIdsForUnit((int) $userUnit))
                                                    ->orWhere(function ($sp2dOwn) use ($actorIds) {
                                                        $sp2dOwn->where('document.src_type', 'SP2D')
                                                            ->whereIn('document.uploaded_by', $actorIds);
                                                    })
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
                                        })->orWhere(function ($defaultScope) use ($userLevel, $userUnit) {
                                            $defaultScope->where(function ($excludeGuSkpdSp2d) {
                                                $excludeGuSkpdSp2d->where('document.payment_type', '!=', 'GU_SKPD')
                                                    ->orWhere('document.src_type', '!=', 'SP2D');
                                            })->where(function ($defaultRule) use ($userLevel, $userUnit) {
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
                                });
                        })->orWhere(function ($guUk) use ($userLevel, $userUnit, $actorIds, $actorBudIds, $scopeUnitId, $documentId) {
                            $guUk->where('document.payment_type', 'GU_UK')
                                ->where(function ($rule) use ($userLevel, $userUnit, $actorIds, $actorBudIds, $scopeUnitId, $documentId) {
                                    if ($userLevel === 8) {
                                        $rule->where('document.src_type', 'NPD')
                                            ->where('document.id_unit_kerja', $userUnit)
                                            ->whereIn('document.uploaded_by', $actorIds);

                                        return;
                                    }

                                    if (in_array($userLevel, [2, 3], true)) {
                                        $rule->where(function ($scope) use ($documentId, $actorBudIds) {
                                            $scope->where('document.src_type', 'SP2D')
                                                ->whereIn('document.users_to', $actorBudIds)
                                                ->orWhereExists(function ($sub) use ($documentId, $actorBudIds) {
                                                    $sub->select(DB::raw(1))
                                                        ->from('document as family_docs')
                                                        ->whereNull('family_docs.deleted_at')
                                                        ->whereYear('family_docs.created_at', $this->selectedYear)
                                                        ->where('family_docs.payment_type', 'GU_UK')
                                                        ->where('family_docs.src_type', 'SP2D')
                                                        ->whereIn('family_docs.users_to', $actorBudIds)
                                                        ->where(function ($family) use ($documentId) {
                                                            $family->where('family_docs.id', $documentId)
                                                                ->orWhere('family_docs.reference_id', $documentId);
                                                        });
                                                });
                                        });

                                        return;
                                    }

                                    if ($userLevel === 4) {
                                        $rule->where(function ($scope) use ($userUnit, $actorIds) {
                                            $scope->where('document.id_unit_kerja', $userUnit)
                                                ->orWhere(function ($sp2dOwn) use ($actorIds) {
                                                    $sp2dOwn->where('document.src_type', 'SP2D')
                                                        ->whereIn('document.uploaded_by', $actorIds);
                                                })
                                                ->orWhereRaw("FIND_IN_SET(?, COALESCE(document.assigned_to, ''))", ['4']);
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
                        })->orWhere(function ($tu) use ($userLevel, $userUnit, $actorIds) {
                            $tu->where('document.payment_type', 'TU')
                                ->where(function ($rule) use ($userLevel, $userUnit, $actorIds) {
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

            if (! $accessibleQuery->exists()) {
                Log::channel('module_document_data')->warning('Document History Access Denied', [
                    'doc_id' => $documentId,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses ke dokumen ini',
                ], 403);
            }

            $query = DocumentHistory::query()
                ->from('document_process as dp')
                ->select([
                    'dp.id',
                    'dp.src_name',
                    'dp.action',
                    'dp.assigned_to',
                    'dp.created_at',
                    'u.nama as user_nama',
                    'j.nama as jabatan_nama',
                    'd.src_type',
                ])
                ->join('user_positions as up', 'up.id', '=', 'dp.id_user')
                ->join('users as u', 'up.user_id', '=', 'u.id')
                ->join('document as d', 'dp.id_dokumen', '=', 'd.id')
                ->leftJoin('unit_kerjas as uk', 'uk.id', '=', 'd.id_unit_kerja')
                ->join('jabatans as j', 'dp.id_jabatan', '=', 'j.id')
                ->whereIn('dp.id_dokumen', $familyIds)
                ->orderByDesc('dp.created_at');

            $response = DataTables::of($query)
                ->addIndexColumn()

                ->addColumn('status', function ($row) {
                    $statusMap = [
                        'UPLOAD' => ['btn-secondary', 'black',  'File Telah di Upload',     'UPLOAD'],
                        'EDITED' => ['btn-warning',   'orange', 'File Telah di Update',     'EDITED'],
                        'SUBMIT' => ['btn-primary',   'white',  'File Telah di Submit',     'SUBMIT'],
                        'VERIFY' => ['btn-success',   'green',  'File Telah di Verifikasi', 'VERIFY'],
                        'TTE' => ['btn-info',      'blue',   'File Telah Tanda Tangan',  'Sudah TTE'],
                        'REJECT' => ['btn-danger',    'red',    'File Telah di Tolak',      'DITOLAK'],
                        'DELETE' => ['btn-danger',    'red',    'File Telah di Hapus',      'DIHAPUS'],
                    ];

                    if (! isset($statusMap[$row->action])) {
                        return '';
                    }

                    [$btnClass, $color, $tooltip, $label] = $statusMap[$row->action];

                    $viewerContractUrl = route('document.history-pdf.viewer', [
                        'history' => EncryptedId::encode((int) $row->id),
                    ]);

                    return '<button type="button" class="btn btn-sm '.e($btnClass).'"'
                        .' data-document-pdf-action="contract"'
                        .' data-pdf-viewer-contract-url="'.e($viewerContractUrl).'"'
                        .' data-wenk-pos="top" data-wenk="'.e($tooltip).'" data-wenk-color="'.e($color).'">'
                        .' <i class="far fa-check-square"></i> '.e($row->src_type).' '.e($label).'</button>';
                })

                ->addColumn('pengirim', function ($row) {
                    return $row->user_nama.' ('.($row->jabatan_nama ?? '-').')';
                })

                ->addColumn('tanggal', function ($row) {
                    return $row->created_at
                        ? date('Y-m-d H:i:s', strtotime((string) $row->created_at))
                        : null;
                })

                ->addColumn('action', function ($row) {
                    if (($row->assigned_to ?? '') === '9,10') {
                        return '<span class="btn btn-sm btn-success">Submit</span>';
                    }

                    return '';
                })

                ->rawColumns(['status', 'action'])
                ->make(true);

            Log::channel('module_document_data')->debug('Document History DataTables Rendered', [
                'doc_id' => $documentId,
                'family_count' => count($familyIds),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $response;
        } catch (\Throwable $e) {

            Log::channel('module_document_data')->error('Document History System Failure', [
                'doc_id' => $documentId ?? null,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            report($e);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan sistem.',
            ], 500);
        }
    }

    private function resolveScopeUnitId(int $unitKerjaId): ?int
    {
        return $this->documentOrganizationScope->scopeUnitIdForUnit($unitKerjaId);
    }

    private function resolveHistoryFamilyIds(int $documentId, bool $includeDeleted = false): array
    {
        $anchorId = $this->resolveHistoryAnchorId($documentId, $includeDeleted);
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

    private function resolveHistoryAnchorId(int $documentId, bool $includeDeleted = false): int
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
                    ->orWhere(function ($sp2dOwn) use ($actorIds) {
                        $sp2dOwn->where('src_type', 'SP2D')
                            ->whereIn('uploaded_by', $actorIds);
                    });
            })
            ->exists();
    }
}
