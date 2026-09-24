<?php

namespace App\Actions\Esign;

use App\Data\Esign\EsignTransitionContext;
use App\Enums\Esign\EsignAttemptStatus;
use App\Jobs\Esign\PerformEsignAttempt;
use App\Models\Esign\EsignAttempt;
use App\Models\User;
use App\Services\Esign\EphemeralSigningSecretStore;
use App\Services\Esign\Persistence\EsignAttemptPersistenceService;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

final class ResumeEsignAttempt
{
    public function __construct(
        private EphemeralSigningSecretStore $secrets,
        private EsignAttemptPersistenceService $attempts,
    ) {}

    public function handle(
        User $user,
        EsignAttempt $attempt,
        #[SensitiveParameter] string $passphrase,
    ): EsignAttempt {
        $secretReference = $this->secrets->put($passphrase, (int) $user->getKey());

        try {
            return DB::transaction(function () use ($user, $attempt, $secretReference): EsignAttempt {
                $resumedAttempt = $this->attempts->transition(
                    $attempt,
                    EsignAttemptStatus::Signing,
                    new EsignTransitionContext(
                        actorUserId: (int) $user->getKey(),
                        actorUserPositionId: (int) $attempt->actor_user_position_id,
                        actorIsActing: false,
                        correlationId: $attempt->request_correlation_id,
                    ),
                );

                PerformEsignAttempt::dispatch(
                    (int) $resumedAttempt->getKey(),
                    $secretReference,
                )->afterCommit();

                return $resumedAttempt;
            }, attempts: 3);
        } catch (\Throwable $exception) {
            $this->secrets->forget($secretReference);

            throw $exception;
        }
    }
}
