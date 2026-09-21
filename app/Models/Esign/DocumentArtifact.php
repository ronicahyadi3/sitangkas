<?php

namespace App\Models\Esign;

use App\Enums\Esign\DocumentArtifactType;
use App\Exceptions\Esign\EsignInvariantViolationException;
use App\Models\Document;
use App\Models\User;
use App\Policies\Esign\DocumentArtifactPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UsePolicy(DocumentArtifactPolicy::class)]
class DocumentArtifact extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'extension' => 'pdf',
        'is_current' => false,
        'mime_type' => 'application/pdf',
        'source_system' => 'application',
        'storage_disk' => 'private',
    ];

    /** @var list<string> */
    protected $fillable = [
        'public_id',
        'document_id',
        'parent_artifact_id',
        'artifact_type',
        'version',
        'is_current',
        'storage_disk',
        'file_path',
        'storage_path_sha256',
        'original_name',
        'stored_name',
        'mime_type',
        'extension',
        'size_bytes',
        'file_sha256',
        'document_year',
        'document_month',
        'source_system',
        'source_reference_type',
        'source_reference_id',
        'metadata',
        'created_by_user_id',
    ];

    /** @var list<string> */
    protected $hidden = [
        'file_path',
        'file_sha256',
        'storage_path_sha256',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $artifact): void {
            if ($artifact->isDirty([
                'public_id',
                'document_id',
                'parent_artifact_id',
                'artifact_type',
                'version',
                'storage_disk',
                'file_path',
                'storage_path_sha256',
                'original_name',
                'stored_name',
                'mime_type',
                'extension',
                'size_bytes',
                'file_sha256',
                'document_year',
                'document_month',
                'source_system',
                'source_reference_type',
                'source_reference_id',
                'metadata',
                'created_by_user_id',
            ])) {
                throw new EsignInvariantViolationException('artifact_identity_immutable');
            }
        });

        static::deleting(function (): never {
            throw new EsignInvariantViolationException('artifact_deletion_prohibited');
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id')->withTrashed();
    }

    public function parentArtifact(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_artifact_id');
    }

    public function childArtifacts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_artifact_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(DocumentArtifactSignature::class);
    }

    public function workflowsUsingAsCurrentArtifact(): HasMany
    {
        return $this->hasMany(DocumentSigningWorkflow::class, 'current_artifact_id');
    }

    public function sourceSteps(): HasMany
    {
        return $this->hasMany(DocumentSigningStep::class, 'source_artifact_id');
    }

    public function resultSteps(): HasMany
    {
        return $this->hasMany(DocumentSigningStep::class, 'result_artifact_id');
    }

    public function sourceAttempts(): HasMany
    {
        return $this->hasMany(EsignAttempt::class, 'source_artifact_id');
    }

    public function resultAttempts(): HasMany
    {
        return $this->hasMany(EsignAttempt::class, 'result_artifact_id');
    }

    public function providerResponsesAsInput(): HasMany
    {
        return $this->hasMany(EsignProviderResponse::class, 'input_artifact_id');
    }

    public function providerResponsesAsOutput(): HasMany
    {
        return $this->hasMany(EsignProviderResponse::class, 'output_artifact_id');
    }

    public function legacyLinks(): HasMany
    {
        return $this->hasMany(EsignAttemptLegacyLink::class, 'document_artifact_id');
    }

    public function migrationItems(): HasMany
    {
        return $this->hasMany(EsignMigrationItem::class, 'document_artifact_id');
    }

    protected function casts(): array
    {
        return [
            'artifact_type' => DocumentArtifactType::class,
            'document_id' => 'integer',
            'document_month' => 'integer',
            'document_year' => 'integer',
            'is_current' => 'boolean',
            'metadata' => 'array',
            'size_bytes' => 'integer',
            'version' => 'integer',
        ];
    }
}
