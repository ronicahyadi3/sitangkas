<?php

namespace App\Enums\Esign;

enum EsignMigrationRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case CompletedWithExceptions = 'completed_with_exceptions';
    case Failed = 'failed';
}
