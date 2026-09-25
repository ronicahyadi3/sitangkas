<?php

namespace App\Policies\Esign;

use App\Models\Esign\DocumentArtifact;
use App\Models\User;
use App\Services\Esign\Authorization\EsignAuthorizationService;
use Illuminate\Auth\Access\Response;

final class DocumentArtifactPolicy
{
    public function __construct(private EsignAuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, DocumentArtifact $artifact): Response
    {
        return $this->authorization->viewArtifact($user, $artifact);
    }

    public function preview(User $user, DocumentArtifact $artifact): Response
    {
        return $this->authorization->viewArtifact($user, $artifact);
    }

    public function verify(User $user, DocumentArtifact $artifact): Response
    {
        return $this->authorization->viewArtifact($user, $artifact);
    }

    public function download(User $user, DocumentArtifact $artifact): Response
    {
        return $this->authorization->downloadArtifact($user, $artifact);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DocumentArtifact $artifact): bool
    {
        return false;
    }

    public function delete(User $user, DocumentArtifact $artifact): bool
    {
        return false;
    }
}
