<?php

namespace App\Data\Esign;

use App\Models\Esign\DocumentArtifact;
use App\Models\Esign\DocumentSigningStep;
use App\Models\Esign\DocumentSigningWorkflow;

final readonly class SigningSessionContextData
{
    public function __construct(
        public SigningSessionData $session,
        public DocumentSigningWorkflow $workflow,
        public DocumentSigningStep $step,
        public DocumentArtifact $artifact,
    ) {}
}
