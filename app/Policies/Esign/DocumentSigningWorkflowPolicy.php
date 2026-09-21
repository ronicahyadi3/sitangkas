<?php

namespace App\Policies\Esign;

use App\Models\Esign\DocumentSigningWorkflow;
use App\Models\User;
use App\Services\Esign\Authorization\EsignAuthorizationService;
use Illuminate\Auth\Access\Response;

final class DocumentSigningWorkflowPolicy
{
    public function __construct(private EsignAuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, DocumentSigningWorkflow $workflow): Response
    {
        return $this->authorization->viewWorkflow($user, $workflow);
    }

    public function download(User $user, DocumentSigningWorkflow $workflow): Response
    {
        return $this->authorization->downloadWorkflow($user, $workflow);
    }

    public function rejectPackage(User $user, DocumentSigningWorkflow $workflow): Response
    {
        return $this->authorization->rejectPackage($user, $workflow);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DocumentSigningWorkflow $workflow): bool
    {
        return false;
    }

    public function delete(User $user, DocumentSigningWorkflow $workflow): bool
    {
        return false;
    }
}
