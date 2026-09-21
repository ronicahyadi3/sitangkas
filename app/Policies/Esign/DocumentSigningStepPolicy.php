<?php

namespace App\Policies\Esign;

use App\Models\Esign\DocumentSigningStep;
use App\Models\User;
use App\Services\Esign\Authorization\EsignAuthorizationService;
use Illuminate\Auth\Access\Response;

final class DocumentSigningStepPolicy
{
    public function __construct(private EsignAuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, DocumentSigningStep $step): Response
    {
        return $this->authorization->viewStep($user, $step);
    }

    public function placeSignature(User $user, DocumentSigningStep $step): Response
    {
        return $this->authorization->placeSignature($user, $step);
    }

    public function sign(User $user, DocumentSigningStep $step): Response
    {
        return $this->authorization->signStep($user, $step);
    }

    public function retrySign(User $user, DocumentSigningStep $step): Response
    {
        return $this->authorization->retrySign($user, $step);
    }

    public function reject(User $user, DocumentSigningStep $step): Response
    {
        return $this->authorization->rejectStep($user, $step);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DocumentSigningStep $step): bool
    {
        return false;
    }

    public function delete(User $user, DocumentSigningStep $step): bool
    {
        return false;
    }
}
