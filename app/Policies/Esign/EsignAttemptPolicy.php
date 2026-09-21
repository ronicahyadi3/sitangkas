<?php

namespace App\Policies\Esign;

use App\Models\Esign\EsignAttempt;
use App\Models\User;
use App\Services\Esign\Authorization\EsignAuthorizationService;
use Illuminate\Auth\Access\Response;

final class EsignAttemptPolicy
{
    public function __construct(private EsignAuthorizationService $authorization) {}

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, EsignAttempt $esignAttempt): Response
    {
        if ((int) $esignAttempt->actor_user_id === (int) $user->getKey()) {
            return Response::allow();
        }

        $step = $esignAttempt->step()->first();

        return $step === null
            ? Response::denyAsNotFound()
            : $this->authorization->viewStep($user, $step);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EsignAttempt $esignAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EsignAttempt $esignAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EsignAttempt $esignAttempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EsignAttempt $esignAttempt): bool
    {
        return false;
    }
}
