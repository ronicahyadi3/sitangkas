<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait TracksUserAudit
{
    protected static function bootTracksUserAudit(): void
    {
        static::creating(function (Model $model): void {
            $actorId = self::currentAuditUserId();

            if ($actorId === null) {
                return;
            }

            if ($model->getAttribute('created_by_user_id') === null) {
                $model->setAttribute('created_by_user_id', $actorId);
            }

            if ($model->getAttribute('updated_by_user_id') === null) {
                $model->setAttribute('updated_by_user_id', $actorId);
            }
        });

        static::updating(function (Model $model): void {
            $actorId = self::currentAuditUserId();

            if ($actorId === null || $model->isDirty('updated_by_user_id')) {
                return;
            }

            $model->setAttribute('updated_by_user_id', $actorId);
        });

        static::deleting(function (Model $model): void {
            $actorId = self::currentAuditUserId();

            if ($actorId === null || self::auditIsForceDeleting($model)) {
                return;
            }

            $model->newQueryWithoutScopes()
                ->whereKey($model->getKey())
                ->update(['deleted_by_user_id' => $actorId]);

            $model->setAttribute('deleted_by_user_id', $actorId);
        });

        static::restoring(function (Model $model): void {
            $actorId = self::currentAuditUserId();

            $model->setAttribute('deleted_by_user_id', null);

            if ($actorId !== null && ! $model->isDirty('updated_by_user_id')) {
                $model->setAttribute('updated_by_user_id', $actorId);
            }
        });
    }

    private static function currentAuditUserId(): ?int
    {
        $actorId = auth()->id();

        return is_numeric($actorId) ? (int) $actorId : null;
    }

    private static function auditIsForceDeleting(Model $model): bool
    {
        return method_exists($model, 'isForceDeleting') && $model->isForceDeleting();
    }
}
